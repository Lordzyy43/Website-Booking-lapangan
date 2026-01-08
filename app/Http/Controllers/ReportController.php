<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class ReportController extends Controller
{
    /**
     * ==========================================================
     * ACCESS CONTROL
     * ==========================================================
     * - auth:sanctum : semua method
     * - role:owner    : hanya Owner
     */
    public function __construct()
    {
        $this->middleware(['auth:sanctum', 'role:owner']);
    }

    /**
     * Weekly Report
     */
    public function weeklyReport()
    {
        return $this->generateReport(
            Carbon::now()->startOfWeek(),
            Carbon::now()->endOfWeek(),
            'weekly'
        );
    }

    /**
     * Monthly Report
     */
    public function monthlyReport()
    {
        return $this->generateReport(
            Carbon::now()->startOfMonth(),
            Carbon::now()->endOfMonth(),
            'monthly'
        );
    }

    /**
     * Yearly Report
     */
    public function yearlyReport()
    {
        return $this->generateReport(
            Carbon::now()->startOfYear(),
            Carbon::now()->endOfYear(),
            'yearly'
        );
    }

    /**
     * ==========================================================
     * CORE REPORT LOGIC
     * ==========================================================
     */
    private function generateReport(Carbon $start, Carbon $end, string $type)
    {
        try {
            $bookings = Booking::with([
                    'items.schedule.field.venue',
                    'user:id,name,email' // optional: hanya ambil field tertentu
                ])
                ->where('payment_status', 'paid')
                ->whereBetween('created_at', [$start, $end])
                ->get();

            return response()->json([
                'success' => true,
                'type' => $type,
                'period' => [
                    'from' => $start->toDateString(),
                    'to'   => $end->toDateString(),
                ],
                'summary' => [
                    'total_booking' => $bookings->count(),
                    'total_slot'    => $bookings->sum(fn ($b) => $b->items->count()),
                    'total_revenue' => $bookings->sum('total_amount'),
                ],
                'data' => $bookings,
            ], 200);

        } catch (\Throwable $e) {
            Log::error('report error', [
                'type' => $type,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil laporan ' . $type,
            ], 500);
        }
    }
}
