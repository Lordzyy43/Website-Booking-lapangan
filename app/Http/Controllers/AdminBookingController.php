<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class AdminBookingController extends Controller
{
    public function __construct()
    {
        // Memastikan hanya Admin yang bisa masuk
        $this->middleware(['auth:sanctum', 'role:admin']);
    }

    /**
     * Menampilkan semua data booking (History & Aktif)
     */
    public function index()
    {
        try {
            $bookings = Booking::with(['user', 'items.schedule.field.venue'])
                ->latest()
                ->get();

            return response()->json([
                'success' => true,
                'count' => $bookings->count(),
                'data' => $bookings
            ], 200);
        } catch (\Throwable $e) {
            Log::error('AdminBooking: Gagal ambil data. ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Server Error: Gagal mengambil data'], 500);
        }
    }

    /**
     * APPROVE PEMBAYARAN
     * Mengubah status Pending -> Paid dan mengunci jadwal secara permanen
     */
    public function confirm(Booking $booking)
    {
        // Guard: Pastikan hanya status pending yang bisa dikonfirmasi
        if ($booking->payment_status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => "Hanya status PENDING yang bisa dikonfirmasi. Status saat ini: {$booking->payment_status}"
            ], 422);
        }

        return DB::transaction(function () use ($booking) {
            try {
                // 1. Jalankan fungsi markAsPaid di model (Update Header & Item Status)
                $booking->markAsPaid();

                Log::info("Admin Approved Booking: #{$booking->booking_code} by Admin ID: " . auth()->id());

                return response()->json([
                    'success' => true,
                    'message' => 'Pembayaran berhasil dikonfirmasi. Jadwal telah dikunci.',
                    'data' => $booking->load(['user', 'items.schedule.field'])
                ], 200);

            } catch (\Throwable $e) {
                Log::error("Admin Confirm Error [#{$booking->booking_code}]: " . $e->getMessage());
                return response()->json(['success' => false, 'message' => 'Terjadi kesalahan sistem saat konfirmasi'], 500);
            }
        });
    }

    /**
     * REJECT PEMBAYARAN (CANCEL)
     * Menolak bukti bayar, menghapus file bukti, dan membuka kembali slot lapangan
     */
    public function reject(Booking $booking)
    {
        // Guard: Jika sudah lunas atau sudah cancel, jangan proses lagi
        if ($booking->payment_status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => "Hanya status PENDING yang bisa ditolak."
            ], 422);
        }

        return DB::transaction(function () use ($booking) {
            try {
                // 1. Amankan file: Hapus bukti pembayaran fisik agar storage tidak penuh
                if ($booking->payment_proof) {
                    Storage::disk('public')->delete($booking->payment_proof);
                }

                // 2. Real-time Release: Kembalikan status jadwal ke available agar orang lain bisa booking
                foreach ($booking->items as $item) {
                    if ($item->schedule) {
                        $item->schedule->update([
                            'status' => 'available',
                            'locked_until' => null, // Hapus timer pengunci
                        ]);
                    }
                }

                // 3. Update Header: Set ke 'cancelled'
                $booking->update([
                    'payment_status' => 'cancelled',
                    'payment_proof' => null,
                ]);

                Log::warning("Admin Rejected Booking: #{$booking->booking_code}. Slot dilepaskan.");

                return response()->json([
                    'success' => true,
                    'message' => 'Booking ditolak dan jadwal lapangan telah dibuka kembali.',
                    'data' => $booking->load('items.schedule.field')
                ], 200);

            } catch (\Throwable $e) {
                Log::error("Admin Reject Error [#{$booking->booking_code}]: " . $e->getMessage());
                return response()->json(['success' => false, 'message' => 'Gagal memproses penolakan'], 500);
            }
        });
    }

    /**
     * DETAIL BOOKING UNTUK ADMIN
     */
    public function show(Booking $booking)
    {
        return response()->json([
            'success' => true,
            'data' => $booking->load(['user', 'items.schedule.field.venue'])
        ]);
    }
}