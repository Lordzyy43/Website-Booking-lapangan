<?php

namespace App\Http\Controllers;

use App\Models\BookingItem;
use App\Models\Schedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BookingItemController extends Controller
{
    /**
     * ==========================================================
     * ACCESS CONTROL
     * ==========================================================
     * - auth:sanctum : semua method
     * - role:user     : store
     */
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('role:user')->only('store');
    }

    /**
     * ==========================================================
     * POST /booking-items
     * Add schedule slot to booking (USER)
     * ==========================================================
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'booking_id'  => 'required|exists:bookings,id',
            'schedule_id' => 'required|exists:schedules,id',
        ]);

        try {
            $result = DB::transaction(function () use ($validated) {

                // 🔒 Ambil booking milik user, masih unpaid & belum expired
                $booking = auth()->user()
                    ->bookings()
                    ->where('id', $validated['booking_id'])
                    ->where('payment_status', 'unpaid')
                    ->where('expired_at', '>', now())
                    ->lockForUpdate()
                    ->firstOrFail();

                // 🔒 Ambil schedule + lock
                $schedule = Schedule::where('id', $validated['schedule_id'])
                    ->lockForUpdate()
                    ->firstOrFail();

                // ⛔ Cek availability schedule
                if (!$schedule->isAvailable()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Slot tidak tersedia / sudah dibooking',
                    ], 422);
                }

                // 📌 Snapshot harga
                $price = $schedule->field->price_per_hour;

                // ➕ Create booking item
                $bookingItem = BookingItem::create([
                    'booking_id'  => $booking->id,
                    'schedule_id' => $schedule->id,
                    'price'       => $price,
                ]);

                // 🔄 Update total booking
                $booking->update([
                    'total_amount' => $booking->items()->sum('price'),
                ]);

                // ✅ Finalisasi schedule slot
                $schedule->update([
                    'status'       => 'booked',
                    'locked_until' => null,
                ]);

                return [
                    'success' => true,
                    'message' => 'Slot berhasil ditambahkan ke booking',
                    'data'    => $bookingItem,
                    'total'   => $booking->total_amount,
                ];
            });

            return response()->json($result, $result['success'] ? 201 : 422);

        } catch (\Throwable $e) {
            Log::error('booking item store error', [
                'user_id' => auth()->id(),
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage() ?: 'Gagal menambahkan slot ke booking',
            ], 500);
        }
    }
}
