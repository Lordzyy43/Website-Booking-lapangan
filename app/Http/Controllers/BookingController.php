<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Database\QueryException;

class BookingController extends Controller
{
    /**
     * ==========================================================
     * ACCESS CONTROL
     * ==========================================================
     * - auth:sanctum      : semua method
     * - role:user          : store, myBookings
     * - role:admin,owner   : index, show
     */
    public function __construct()
    {
        $this->middleware('auth:sanctum');

        $this->middleware('role:user')->only(['store', 'myBookings']);
        $this->middleware('role:admin,owner')->only(['index', 'show']);
    }

    // ==========================================================
    // GET /bookings/my
    // List own bookings (USER)
    // ==========================================================
    public function myBookings()
    {
        try {
            $bookings = Booking::where('user_id', auth()->id())
                ->with('items.schedule.field.venue')
                ->latest()
                ->get();

            return response()->json([
                'success' => true,
                'data' => $bookings,
            ], 200);

        } catch (\Throwable $e) {
            Log::error('myBookings error', [
                'user_id' => auth()->id(),
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data booking',
            ], 500);
        }
    }

    // ==========================================================
    // GET /bookings
    // List all bookings (ADMIN / OWNER)
    // ==========================================================
    public function index()
    {
        try {
            $bookings = Booking::with('user', 'items.schedule.field.venue')
                ->latest()
                ->get();

            return response()->json([
                'success' => true,
                'data' => $bookings,
            ], 200);

        } catch (\Throwable $e) {
            Log::error('booking index error', ['message' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data booking',
            ], 500);
        }
    }

    // ==========================================================
    // GET /bookings/{booking}
    // Detail booking (ADMIN / OWNER)
    // ==========================================================
    public function show(Booking $booking)
    {
        try {
            return response()->json([
                'success' => true,
                'data' => $booking->load('user', 'items.schedule.field.venue'),
            ], 200);

        } catch (\Throwable $e) {
            Log::error('booking show error', [
                'booking_id' => $booking->id,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil detail booking',
            ], 500);
        }
    }

    // ==========================================================
    // POST /bookings
    // Create booking (USER / cart)
    // ==========================================================
    public function store()
    {
        try {
            // ⛔ Cegah booking aktif dobel
            $existing = Booking::where('user_id', auth()->id())
                ->where('payment_status', 'unpaid')
                ->where('expired_at', '>', now())
                ->first();

            if ($existing) {
                return response()->json([
                    'success' => false,
                    'message' => 'Masih ada booking aktif yang belum diselesaikan',
                    'booking_id' => $existing->id,
                ], 409);
            }

            $booking = Booking::create([
                'user_id'        => auth()->id(),
                'booking_code'   => 'BK-' . Str::uuid(),
                'total_amount'   => 0,
                'payment_status' => 'unpaid',
                'expired_at'     => now()->addMinutes(30),
            ]);

            return response()->json([
                'success' => true,
                'data' => $booking,
            ], 201);

        } catch (QueryException $e) {
            Log::error('booking store DB error', [
                'user_id' => auth()->id(),
                'error' => $e->errorInfo,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan booking ke database',
            ], 500);

        } catch (\Throwable $e) {
            Log::error('booking store error', [
                'user_id' => auth()->id(),
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat membuat booking',
            ], 500);
        }
    }
}
