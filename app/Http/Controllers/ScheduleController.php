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
        $this->middleware('auth:sanctum')->except(['index', 'show', 'availableSchedules']);
        $this->middleware('role:admin')->except(['index', 'show', 'availableSchedules']);
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

    public function availableSchedules($fieldId, Request $request)
    {
        $request->validate([
            'date' => 'required|date',
            'start_hour' => 'nullable|integer|min:0|max:23',
            'end_hour'   => 'nullable|integer|min:0|max:23',
        ]);

        $date = $request->date;
        $startHour = $request->start_hour ?? 7;   // default 07:00
        $endHour   = $request->end_hour ?? 22;    // default 22:00

        try {
            // Ambil jadwal yang sudah ada beserta relasi field & venue
            $schedules = Schedule::with('field.venue')
                ->where('field_id', $fieldId)
                ->whereDate('start_time', $date)
                ->orderBy('start_time')
                ->get();

            // Jika belum ada jadwal, generate slot kosong
            if ($schedules->isEmpty()) {
                for ($hour = $startHour; $hour < $endHour; $hour++) {
                    $startTime = sprintf('%s %02d:00:00', $date, $hour);
                    $endTime   = sprintf('%s %02d:00:00', $date, $hour + 1);

                    $slot = Schedule::create([
                        'field_id'   => $fieldId,
                        'start_time' => $startTime,
                        'end_time'   => $endTime,
                        'status'     => 'available',
                    ]);

                    // reload relasi agar price & venue bisa diakses
                    $slot->load('field.venue');
                    $schedules->push($slot);
                }
            }

            // Format response bersih termasuk price dari field
            $response = $schedules->map(function ($slot) {
                return [
                    'id'         => $slot->id,
                    'start_time' => $slot->start_time->format('H:i'),
                    'end_time'   => $slot->end_time->format('H:i'),
                    'status'     => $slot->status,
                    'price'      => $slot->field->price_per_hour ?? 0, // <-- tambahan price
                    'is_locked'  => $slot->isLocked(),
                    'is_booked'  => $slot->isBooked(),
                    'can_lock'   => $slot->canBeLocked(),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $response,
            ], 200);

        } catch (\Throwable $e) {
            \Log::error('Fetch available schedules error', ['message' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil jadwal',
            ], 500);
        }
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

    public function generate(Request $request)
    {
        $validated = $request->validate([
            'field_id'   => 'required|exists:fields,id',
            'date'       => 'required|date|after_or_equal:today',
            'start_hour' => 'required|integer|min:0|max:23',
            'end_hour'   => 'required|integer|min:0|max:23|gt:start_hour',
        ]);

        try {
            $date = $validated['date'];
            $fieldId = $validated['field_id'];
            $createdCount = 0;
            $skippedCount = 0;

            // Loop dari jam mulai sampai jam selesai
            for ($hour = $validated['start_hour']; $hour < $validated['end_hour']; $hour++) {
                $startTime = sprintf('%s %02d:00:00', $date, $hour);
                $endTime   = sprintf('%s %02d:00:00', $date, $hour + 1);

                // Cek apakah slot sudah ada agar tidak duplikat (Unique Constraint Safe)
                $exists = Schedule::where('field_id', $fieldId)
                    ->where('start_time', $startTime)
                    ->exists();

                if (!$exists) {
                    Schedule::create([
                        'field_id'   => $fieldId,
                        'start_time' => $startTime,
                        'end_time'   => $endTime,
                        'status'     => 'available',
                    ]);
                    $createdCount++;
                } else {
                    $skippedCount++;
                }
            }

            return response()->json([
                'success' => true,
                'message' => "Berhasil generate $createdCount slot. ($skippedCount slot dilewati karena sudah ada)",
            ], 201);

        } catch (\Throwable $e) {
            Log::error('Schedule generate error', ['message' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Gagal generate jadwal'], 500);
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
