<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB; // Import DB Facade

class AdminBookingController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth:sanctum', 'role:admin']);
    }

    public function index()
    {
        try {
            // Eager Loading 'items.schedule.field.venue' sangat krusial agar 
            // detail lapangan muncul di Frontend tanpa query berulang (N+1 Problem)
            $bookings = Booking::with(['user', 'items.schedule.field.venue'])
                ->latest()
                ->get();

            return response()->json([
                'success' => true,
                'data' => $bookings
            ], 200);
        } catch (\Throwable $e) {
            Log::error('admin fetch bookings error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Gagal mengambil data booking'], 500);
        }
    }

    public function confirm(Booking $booking)
    {
        // 1. Cek status awal (Early Return)
        if ($booking->payment_status === 'paid') {
            return response()->json([
                'success' => false,
                'message' => 'Booking ini sudah berstatus PAID sebelumnya.'
            ], 422);
        }

        // 2. Gunakan Database Transaction untuk menjaga integritas data
        // Jika salah satu proses di dalam closure gagal, semua perubahan dibatalkan (Rollback)
        return DB::transaction(function () use ($booking) {
            try {
                // Update header booking
                $booking->update([
                    'payment_status' => 'paid',
                ]);

                // Update status setiap slot jadwal yang dipesan
                foreach ($booking->items as $item) {
                    // Pastikan relasi schedule tersedia
                    if ($item->schedule) {
                        $item->schedule->update([
                            'status' => 'booked',
                            'locked_until' => null, // Melepas kunci checkout karena sudah sah dibayar
                        ]);
                    }
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Booking berhasil dikonfirmasi dan slot lapangan telah dikunci.',
                    'data' => $booking->load('items.schedule.field.venue')
                ], 200);

            } catch (\Throwable $e) {
                Log::error('Admin confirm booking transaction failed', [
                    'booking_id' => $booking->id,
                    'error' => $e->getMessage()
                ]);

                // Exception di sini akan memicu DB::transaction untuk melakukan rollback otomatis
                throw $e; 
            }
        });
    }
}