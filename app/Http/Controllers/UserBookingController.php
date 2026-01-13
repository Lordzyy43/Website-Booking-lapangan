<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Schedule;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class UserBookingController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth:sanctum', 'role:user']);
    }

    /**
     * GET /user/bookings/my
     * Ambil riwayat booking user yang login
     */
    public function index()
    {
        try {
            $bookings = Booking::with(['items.schedule.field.venue'])
                ->where('user_id', auth()->id())
                ->latest()
                ->get();

            return response()->json([
                'success' => true,
                'data' => $bookings
            ], 200);

        } catch (\Exception $e) {
            Log::error('UserBookingController@index error', [
                'user_id' => auth()->id(),
                'message' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data booking'
            ], 500);
        }
    }

    /**
     * POST /user/bookings
     * Buat booking baru
     */
    public function store(Request $request)
    {
        $request->validate([
            'schedule_ids' => 'required|array|min:1',
            'schedule_ids.*' => 'exists:schedules,id',
        ]);

        try {
            return DB::transaction(function () use ($request) {
                $schedules = Schedule::with('field')
                    ->whereIn('id', $request->schedule_ids)
                    ->lockForUpdate()
                    ->get();

                foreach ($schedules as $s) {
                    if ($s->status !== 'available') {
                        return response()->json([
                            'success' => false,
                            'message' => "Slot jam " . date('H:i', strtotime($s->start_time)) . " sudah tidak tersedia."
                        ], 422);
                    }
                }

                $totalAmount = $schedules->sum(fn($s) => $s->field->price_per_hour);

                $booking = Booking::create([
                    'user_id' => auth()->id(),
                    'booking_code' => 'BK-' . strtoupper(Str::random(8)),
                    'total_amount' => $totalAmount,
                    'payment_status' => 'unpaid',
                    'expired_at' => now()->addHours(2),
                ]);

                foreach ($schedules as $s) {
                    $booking->items()->create([
                        'schedule_id' => $s->id,
                        'price' => $s->field->price_per_hour,
                    ]);
                    $s->update(['status' => 'booked']);
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Booking berhasil dibuat!',
                    'data' => $booking->load('items.schedule.field.venue')
                ], 201);
            });
        } catch (\Exception $e) {
            Log::error("UserBookingController@store error: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan sistem saat memproses booking.'
            ], 500);
        }
    }

    /**
     * GET /user/bookings/{booking}
     * Detail booking by ID
     */
   public function show($bookingCode)
    {
        try {
            $booking = Booking::with('items.schedule.field.venue')
                ->where('booking_code', $bookingCode)
                ->where('user_id', auth()->id())
                ->first();

            if (!$booking) {
                return response()->json([
                    'success' => false,
                    'message' => 'Booking tidak ditemukan'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $booking
            ], 200);

        } catch (\Exception $e) {
            Log::error('UserBookingController@show error', [
                'booking_code' => $bookingCode,
                'user_id' => auth()->id(),
                'message' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil detail booking'
            ], 500);
        }
    }

    /**
     * POST /user/bookings/{booking}/upload-payment
     * Upload bukti pembayaran
     */
    public function uploadPayment(Request $request, Booking $booking)
    {
        try {
            if ($booking->user_id !== auth()->id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Booking tidak ditemukan'
                ], 404);
            }

            if (!in_array($booking->payment_status, ['unpaid', 'pending'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Booking tidak dapat diupload pembayarannya'
                ], 422);
            }

            $request->validate([
                'payment_proof' => 'required|file|mimes:jpg,jpeg,png,pdf|max:2048',
            ]);

            if ($booking->payment_proof) {
                // Hapus bukti pembayaran lama jika ada
                Storage::disk('public')->delete($booking->payment_proof);
            }

            $path = $request->file('payment_proof')->store('payments', 'public');

            $booking->update([
                'payment_proof' => $path,
                'payment_status' => 'pending',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Bukti pembayaran berhasil diupload, menunggu konfirmasi admin.',
                'data' => $booking->fresh()
            ], 200);

        } catch (\Exception $e) {
            Log::error('UserBookingController@uploadPayment error', [
                'booking_id' => $booking->id,
                'user_id' => auth()->id(),
                'message' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat upload pembayaran.'
            ], 500);
        }
    }

    /**
     * POST /user/bookings/{booking}/cancel
     * Batalkan booking user
     */
    public function cancel(Booking $booking)
    {
        try {
            if ($booking->user_id !== auth()->id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Booking tidak ditemukan'
                ], 404);
            }

            if (in_array($booking->payment_status, ['paid'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Booking yang sudah dibayar tidak dapat dibatalkan'
                ], 422);
            }

            // Kembalikan status schedule
            foreach ($booking->items as $item) {
                $item->schedule->update(['status' => 'available']);
            }

            $booking->update(['payment_status' => 'cancelled']);
            $booking->delete(); // soft delete

            return response()->json([
                'success' => true,
                'message' => 'Booking berhasil dibatalkan'
            ], 200);

        } catch (\Exception $e) {
            Log::error('UserBookingController@cancel error', [
                'booking_id' => $booking->id,
                'user_id' => auth()->id(),
                'message' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal membatalkan booking'
            ], 500);
        }
    }

    /**
     * DELETE /user/bookings/{booking}
     * Hapus booking (soft delete)
     */
    public function destroy(Booking $booking)
    {
        try {
            if ($booking->user_id !== auth()->id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Booking tidak ditemukan'
                ], 404);
            }

            // Hapus bukti pembayaran jika ada
            if ($booking->payment_proof) {
                Storage::disk('public')->delete($booking->payment_proof);
            }

            $booking->delete(); // soft delete

            return response()->json([
                'success' => true,
                'message' => 'Booking berhasil dihapus'
            ], 200);

        } catch (\Exception $e) {
            Log::error('UserBookingController@destroy error', [
                'booking_id' => $booking->id,
                'user_id' => auth()->id(),
                'message' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus booking'
            ], 500);
        }
    }
}
