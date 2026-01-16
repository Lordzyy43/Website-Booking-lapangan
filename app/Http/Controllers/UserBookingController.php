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
            'field_id' => 'required|exists:fields,id',
            'slots'    => 'required|array|min:1',
            'slots.*.start_time' => 'required|date_format:Y-m-d H:i:s',
            'slots.*.end_time'   => 'required|date_format:Y-m-d H:i:s|after:slots.*.start_time',
        ]);

        try {
            // Ambil data field sekali saja (Efisiensi)
            $field = \App\Models\Field::findOrFail($request->field_id);

            return DB::transaction(function () use ($request, $field) {
                $bookedSchedules = [];
                $totalAmount = 0;

                foreach ($request->slots as $slot) {
                    // 1. Dapatkan atau buat slot
                    $schedule = Schedule::firstOrCreate(
                        [
                            'field_id'   => $field->id,
                            'start_time' => $slot['start_time'],
                        ],
                        [
                            'end_time'   => $slot['end_time'],
                            'status'     => 'available'
                        ]
                    );

                    // 2. KUNCI DATA (Locking) untuk mencegah double booking
                    $schedule = Schedule::where('id', $schedule->id)->lockForUpdate()->first();
                    
                    if ($schedule->status !== 'available') {
                        // Pakai throw agar DB::transaction otomatis Rollback
                        throw new \Exception("Jam " . date('H:i', strtotime($schedule->start_time)) . " baru saja dipesan orang lain.");
                    }

                    $bookedSchedules[] = $schedule;
                    $totalAmount += $field->price_per_hour;
                }

                // 3. Buat Header Booking dengan Expired 5 Menit
                $booking = Booking::create([
                    'user_id'        => auth()->id(),
                    'booking_code'   => 'BK-' . strtoupper(Str::random(8)),
                    'total_amount'   => $totalAmount,
                    'payment_status' => 'unpaid',
                    'expired_at'     => now()->addMinutes(5),
                ]);

                // 4. Hubungkan Item dan Tandai "Booked"
                foreach ($bookedSchedules as $s) {
                    $booking->items()->create([
                        'schedule_id' => $s->id,
                        'price'       => $field->price_per_hour,
                    ]);
                    $s->update(['status' => 'booked']);
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Booking berhasil! Segera bayar dalam 5 menit.',
                    'data'    => $booking->load('items.schedule.field.venue')
                ], 201);
            });
        } catch (\Exception $e) {
            // Response 422 agar React bisa menangkap pesan error spesifik
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
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
