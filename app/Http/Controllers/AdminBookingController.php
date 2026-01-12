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
        $this->middleware(['auth:sanctum', 'role:admin']);
    }

    /**
     * GET /admin/bookings
     */
    public function index()
    {
        try {
            $bookings = Booking::with(['user', 'items.schedule.field.venue'])
                ->latest()
                ->get();

            return response()->json([
                'success' => true,
                'data' => $bookings
            ], 200);
        } catch (\Throwable $e) {
            Log::error('AdminBookingController@index error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Gagal mengambil data booking'], 500);
        }
    }

    /**
     * POST /admin/bookings/{booking}/confirm
     * Konfirmasi booking (status paid) - IMPROVED VERSION
     */
    public function confirm(Booking $booking)
    {
        // Validasi status agar tidak double confirm
        if ($booking->isPaid()) {
            return response()->json([
                'success' => false,
                'message' => 'Booking ini sudah berstatus PAID.'
            ], 422);
        }

        try {
            // Panggil fungsi "Sakti" dari Model Booking.php
            // Ini otomatis handle DB::transaction dan update schedule status
            $booking->markAsPaid();

            return response()->json([
                'success' => true,
                'message' => 'Booking berhasil dikonfirmasi secara real-time!',
                'data' => $booking->load('items.schedule.field.venue')
            ], 200);

        } catch (\Throwable $e) {
            Log::error('AdminBookingController@confirm failed: ' . $e->getMessage());
            return response()->json([
                'success' => false, 
                'message' => 'Gagal mengonfirmasi pembayaran'
            ], 500);
        }
    }

    /**
     * POST /admin/bookings/{booking}/reject
     * Tolak booking dan kembalikan slot lapangan
     */
    public function reject(Booking $booking)
    {
        // Hanya yang status pending (sudah upload bukti) yang bisa ditolak/verifikasi
        if ($booking->payment_status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Hanya booking berstatus PENDING yang bisa diproses.'
            ], 422);
        }

        return DB::transaction(function () use ($booking) {
            try {
                // 1. Hapus bukti pembayaran fisik dari storage
                if ($booking->payment_proof) {
                    Storage::disk('public')->delete($booking->payment_proof);
                }

                // 2. Kembalikan status slot ke available (Real-time unlock)
                foreach ($booking->items as $item) {
                    if ($item->schedule) {
                        $item->schedule->update([
                            'status' => 'available',
                            'locked_until' => null,
                        ]);
                    }
                }

                // 3. Reset header booking
                $booking->update([
                    'payment_status' => 'unpaid',
                    'payment_proof' => null,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Booking ditolak, slot lapangan telah dibuka kembali.',
                    'data' => $booking->load('items.schedule.field.venue')
                ], 200);

            } catch (\Throwable $e) {
                Log::error('AdminBookingController@reject failed: ' . $e->getMessage());
                throw $e;
            }
        });
    }
}