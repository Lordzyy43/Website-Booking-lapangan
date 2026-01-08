<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AdminBookingController extends Controller
{
    /**
     * ==========================================================
     * ACCESS CONTROL
     * ==========================================================
     * - auth:sanctum : semua method
     * - role:admin   : hanya Admin
     */
    public function __construct()
    {
        $this->middleware(['auth:sanctum', 'role:admin']);
    }

    /**
     * ==========================================================
     * POST /admin/bookings/{booking}/confirm
     * Confirm booking & mark as paid
     * ==========================================================
     */
    public function confirm(Booking $booking)
    {
        try {
            if ($booking->payment_status === 'paid') {
                return response()->json([
                    'success' => false,
                    'message' => 'Booking sudah dikonfirmasi'
                ], 422);
            }

            // Update booking jadi paid
            $booking->update([
                'payment_status' => 'paid',
            ]);

            // Update semua schedule slot yang terkait jadi booked
            foreach ($booking->items as $item) {
                $item->schedule->update([
                    'status' => 'booked',
                    'locked_until' => null,
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Booking berhasil dikonfirmasi',
                'data' => $booking->load('items.schedule.field.venue')
            ], 200);

        } catch (\Throwable $e) {
            Log::error('admin confirm booking error', [
                'booking_id' => $booking->id,
                'message' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal konfirmasi booking'
            ], 500);
        }
    }
}
