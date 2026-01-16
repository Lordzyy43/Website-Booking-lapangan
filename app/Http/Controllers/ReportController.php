<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth:sanctum', 'role:owner']);
    }

    public function index(Request $request)
    {
        $range = $request->query('range', 'monthly');

        switch ($range) {
            case 'weekly':
                $start = Carbon::now()->startOfWeek();
                $end = Carbon::now()->endOfWeek();
                break;
            case 'yearly':
                $start = Carbon::now()->startOfYear();
                $end = Carbon::now()->endOfYear();
                break;
            case 'monthly':
            default:
                $start = Carbon::now()->startOfMonth();
                $end = Carbon::now()->endOfMonth();
                break;
        }

        return $this->generateReport($start, $end, $range);
    }

    /**
     * Menampilkan 50 transaksi terbaru milik Owner
     * Termasuk yang statusnya masih 'unpaid'
     */
    public function transactions()
    {
        $ownerId = Auth::id();

        $transactions = Booking::with(['user:id,name,email'])
            ->whereHas('items.schedule.field.venue', function ($q) use ($ownerId) {
                // FIXED: Menggunakan 'owner_id' sesuai Model Venue
                $q->where('owner_id', $ownerId);
            })
            ->latest()
            ->take(50)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $transactions,
            'meta' => [
                'total' => $transactions->count(),
                'server_time' => now()->toDateTimeString()
            ]
        ], 200);
    }

    /**
     * Generate Laporan Ringkasan & Data Chart
     */
    private function generateReport(Carbon $start, Carbon $end, string $type)
    {
        try {
            $ownerId = Auth::id();

            // 1. Query Dasar: Ambil semua booking di Venue milik Owner ini
            $baseQuery = Booking::whereHas('items.schedule.field.venue', function ($q) use ($ownerId) {
                $q->where('owner_id', $ownerId); // FIXED: owner_id
            })->whereBetween('created_at', [$start, $end]);

            // Ambil semua data mentah (termasuk yang unpaid untuk hitung total booking)
            $allBookings = (clone $baseQuery)->get();

            // 2. Query khusus untuk Chart (Hanya yang 'paid' agar grafik uangnya akurat)
            $chartData = (clone $baseQuery)
                ->where('payment_status', 'paid') 
                ->select(
                    DB::raw('DATE(created_at) as created_at'),
                    DB::raw('SUM(total_amount) as total_amount')
                )
                ->groupBy('created_at')
                ->orderBy('created_at', 'ASC')
                ->get();

            // Kalkulasi Summary
            $totalRevenue = (float) $allBookings->where('payment_status', 'paid')->sum('total_amount');
            $totalBooking = $allBookings->count(); // Semua booking yang masuk
            
            // Success rate: Persentase yang sudah bayar dibanding total booking
            $paidCount = $allBookings->where('payment_status', 'paid')->count();
            $successRate = $totalBooking > 0 ? round(($paidCount / $totalBooking) * 100) : 0;

            return response()->json([
                'success' => true,
                'metadata' => [
                    'type' => $type,
                    'from' => $start->toDateTimeString(),
                    'to' => $end->toDateTimeString(),
                    'server_time' => Carbon::now()->toDateTimeString(),
                ],
                'summary' => [
                    'total_revenue' => $totalRevenue,
                    'total_bookings' => $totalBooking,
                    'success_rate' => $successRate,
                    'total_slots' => $totalBooking, 
                ],
                'data' => $chartData
            ], 200);

        } catch (\Throwable $e) {
            Log::error('SENSEI REPORT ERROR: ' . $e->getMessage());
            return response()->json([
                'success' => false, 
                'message' => 'Matrix Error: ' . $e->getMessage()
            ], 500);
        }
    }
}