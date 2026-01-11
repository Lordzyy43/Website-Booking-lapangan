<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Schedule;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UserBookingController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth:sanctum', 'role:user']);
    }

    /**
     * Mengambil riwayat booking user yang sedang login
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
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Proses Simpan Booking (Checkout)
     */
    public function store(Request $request)
    {
        // 1. Validasi Input Dasar
        $request->validate([
            'schedule_ids' => 'required|array|min:1',
            'schedule_ids.*' => 'exists:schedules,id',
        ]);

        try {
            // Gunakan Transaction agar jika salah satu gagal, semua dibatalkan
            return DB::transaction(function () use ($request) {
                
                // 2. Ambil data schedule dan kunci baris (lockForUpdate) untuk mencegah Race Condition
                $schedules = Schedule::with('field')
                    ->whereIn('id', $request->schedule_ids)
                    ->lockForUpdate() 
                    ->get();

                // 3. Validasi: Pastikan SEMUA slot yang dipilih berstatus 'available'
                foreach ($schedules as $s) {
                    if ($s->status !== 'available') {
                        return response()->json([
                            'success' => false,
                            'message' => "Slot jam " . date('H:i', strtotime($s->start_time)) . " sudah tidak tersedia. Silakan pilih jam lain."
                        ], 422);
                    }
                }

                // 4. Hitung Total Harga
                $totalAmount = $schedules->sum(function($s) {
                    return $s->field->price_per_hour;
                });

                // 5. Buat Header Booking
                $booking = Booking::create([
                    'user_id' => auth()->id(),
                    'booking_code' => 'BK-' . strtoupper(Str::random(8)),
                    'total_amount' => $totalAmount,
                    'payment_status' => 'unpaid',
                    'expired_at' => now()->addHours(2),
                ]);

                // 6. Buat Detail Item & Update Status Slot
                foreach ($schedules as $s) {
                    $booking->items()->create([
                        'schedule_id' => $s->id,
                        'price' => $s->field->price_per_hour, // Snapshot harga saat ini
                    ]);

                    // Ubah status menjadi booked
                    $s->update(['status' => 'booked']); 
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Booking berhasil dibuat! Silakan cek menu Riwayat untuk konfirmasi pembayaran.',
                    'data' => $booking->load('items.schedule.field.venue')
                ], 201);
            });

        } catch (\Exception $e) {
            Log::error("Booking Store Error: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan sistem saat memproses booking.'
            ], 500);
        }
    }
}