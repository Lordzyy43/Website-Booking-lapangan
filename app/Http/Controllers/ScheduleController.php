<?php

namespace App\Http\Controllers;

use App\Models\Schedule;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;

class ScheduleController extends Controller
{
    /**
     * ==========================================================
     * ACCESS CONTROL
     * ==========================================================
     * - auth:sanctum : semua method
     * - role:admin   : CRUD write
     * - owner/user   : read-only
     */
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('role:admin')->except(['index', 'show']);
    }

    // ==========================================================
    // GET /schedules
    // List all schedules (ALL ROLES)
    // ==========================================================
    public function index()
    {
        try {
            $schedules = Schedule::with('field.venue')
                ->orderBy('start_time')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $schedules,
            ], 200);

        } catch (\Throwable $e) {
            Log::error('Schedule index error', ['message' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data schedule',
            ], 500);
        }
    }

    // ==========================================================
    // GET /schedules/{schedule}
    // Detail schedule (ALL ROLES)
    // ==========================================================
    public function show(Schedule $schedule)
    {
        return response()->json([
            'success' => true,
            'data' => $schedule->load('field.venue'),
        ], 200);
    }

    // ==========================================================
    // POST /schedules
    // Create schedule (ADMIN ONLY)
    // ==========================================================
    public function store(Request $request)
    {
        $validated = $request->validate([
            'field_id'   => 'required|exists:fields,id',
            'start_time' => 'required|date',
            'end_time'   => 'required|date|after:start_time',
            'status'     => 'nullable|in:' . implode(',', Schedule::STATUSES),
        ]);

        try {
            // 🔒 Cek bentrok jadwal
            $conflict = Schedule::where('field_id', $validated['field_id'])
                ->where(function ($q) use ($validated) {
                    $q->whereBetween('start_time', [$validated['start_time'], $validated['end_time']])
                      ->orWhereBetween('end_time', [$validated['start_time'], $validated['end_time']])
                      ->orWhere(function ($q) use ($validated) {
                          $q->where('start_time', '<=', $validated['start_time'])
                            ->where('end_time', '>=', $validated['end_time']);
                      });
                })
                ->exists();

            if ($conflict) {
                return response()->json([
                    'success' => false,
                    'message' => 'Schedule bentrok dengan slot lain',
                ], 422);
            }

            $schedule = Schedule::create([
                'field_id'   => $validated['field_id'],
                'start_time' => $validated['start_time'],
                'end_time'   => $validated['end_time'],
                'status'     => $validated['status'] ?? 'available',
            ]);

            return response()->json([
                'success' => true,
                'data' => $schedule,
            ], 201);

        } catch (QueryException $e) {
            Log::error('Schedule store DB error', ['error' => $e->errorInfo]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan schedule ke database',
            ], 500);

        } catch (\Throwable $e) {
            Log::error('Schedule store error', ['message' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat membuat schedule',
            ], 500);
        }
    }

    // ==========================================================
    // PUT /schedules/{schedule}
    // Update schedule (ADMIN ONLY)
    // ==========================================================
    public function update(Request $request, Schedule $schedule)
    {
        $validated = $request->validate([
            'start_time' => 'required|date',
            'end_time'   => 'required|date|after:start_time',
            'status'     => 'nullable|in:' . implode(',', Schedule::STATUSES),
        ]);

        if ($schedule->status === 'booked') {
            return response()->json([
                'success' => false,
                'message' => 'Schedule yang sudah dibooking tidak bisa diubah',
            ], 422);
        }

        try {
            $schedule->update($validated);

            return response()->json([
                'success' => true,
                'data' => $schedule,
            ], 200);

        } catch (QueryException $e) {
            Log::error('Schedule update DB error', ['schedule_id' => $schedule->id, 'error' => $e->errorInfo]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui schedule',
            ], 500);

        } catch (\Throwable $e) {
            Log::error('Schedule update error', ['schedule_id' => $schedule->id, 'message' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat memperbarui schedule',
            ], 500);
        }
    }

    // ==========================================================
    // DELETE /schedules/{schedule}
    // Delete schedule (ADMIN ONLY)
    // ==========================================================
    public function destroy(Schedule $schedule)
    {
        if ($schedule->status === 'booked') {
            return response()->json([
                'success' => false,
                'message' => 'Schedule yang sudah dibooking tidak bisa dihapus',
            ], 422);
        }

        try {
            $schedule->delete();

            return response()->json([
                'success' => true,
                'message' => 'Schedule berhasil dihapus',
            ], 200);

        } catch (\Throwable $e) {
            Log::error('Schedule delete error', ['schedule_id' => $schedule->id, 'message' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus schedule',
            ], 500);
        }
    }
}
