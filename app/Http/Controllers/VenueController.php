<?php

namespace App\Http\Controllers;

use App\Models\Venue;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;

class VenueController extends Controller
{
    /**
     * ==========================================================
     * ACCESS CONTROL
     * ==========================================================
     * - auth:sanctum   : semua method
     * - role:admin     : CRUD write
     * - owner/user     : read-only (index, show)
     */
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('role:admin')->except(['index', 'show']);
    }

    // ==========================================================
    // GET /venues
    // List all venues (ALL ROLES)
    // ==========================================================
    public function index()
    {
        try {
            $venues = Venue::latest()->get();
            return response()->json([
                'success' => true,
                'data' => $venues,
            ], 200);
        } catch (\Throwable $e) {
            Log::error('Venue index error', ['message' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data venue',
            ], 500);
        }
    }

    // ==========================================================
    // GET /venues/{venue}
    // Detail venue (ALL ROLES)
    // ==========================================================
    public function show(Venue $venue)
    {
        return response()->json([
            'success' => true,
            'data' => $venue,
        ], 200);
    }

    // ==========================================================
    // POST /venues
    // Create venue (ADMIN ONLY)
    // ==========================================================
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'address'     => 'required|string',
            'description' => 'nullable|string',
            'open_time'   => 'required|date_format:H:i',
            'close_time'  => 'required|date_format:H:i|after:open_time',
        ]);

        try {
            $slug = $this->generateUniqueSlug($validated['name']);

            $venue = Venue::create([
                'user_id'     => auth()->id(),
                'name'        => $validated['name'],
                'slug'        => $slug,
                'address'     => $validated['address'],
                'description' => $validated['description'] ?? null,
                'open_time'   => $validated['open_time'],
                'close_time'  => $validated['close_time'],
                'image'       => null,
            ]);

            return response()->json([
                'success' => true,
                'data' => $venue,
            ], 201);

        } catch (QueryException $e) {
            Log::error('Venue store DB error', ['error' => $e->errorInfo]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan venue ke database',
            ], 500);

        } catch (\Throwable $e) {
            Log::error('Venue store error', ['message' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat membuat venue',
            ], 500);
        }
    }

    // ==========================================================
    // PUT /venues/{venue}
    // Update venue (ADMIN ONLY)
    // ==========================================================
    public function update(Request $request, Venue $venue)
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'address'     => 'required|string',
            'description' => 'nullable|string',
            'open_time'   => 'required|date_format:H:i',
            'close_time'  => 'required|date_format:H:i|after:open_time',
        ]);

        try {
            $venue->update([
                'name'        => $validated['name'],
                'slug'        => $this->generateUniqueSlug($validated['name'], $venue->id),
                'address'     => $validated['address'],
                'description' => $validated['description'] ?? null,
                'open_time'   => $validated['open_time'],
                'close_time'  => $validated['close_time'],
            ]);

            return response()->json([
                'success' => true,
                'data' => $venue,
            ], 200);

        } catch (\Throwable $e) {
            Log::error('Venue update error', ['venue_id' => $venue->id, 'message' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui venue',
            ], 500);
        }
    }

    // ==========================================================
    // DELETE /venues/{venue}
    // Delete venue (ADMIN ONLY)
    // ==========================================================
    public function destroy(Venue $venue)
    {
        try {
            $venue->delete();
            return response()->json([
                'success' => true,
                'message' => 'Venue berhasil dihapus',
            ], 200);

        } catch (\Throwable $e) {
            Log::error('Venue delete error', ['venue_id' => $venue->id, 'message' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus venue',
            ], 500);
        }
    }

    // ==========================================================
    // HELPER: Generate unique slug
    // ==========================================================
    private function generateUniqueSlug(string $name, ?int $excludeId = null): string
    {
        $baseSlug = Str::slug($name);
        $slug = $baseSlug;
        $counter = 1;

        while (Venue::where('slug', $slug)
                     ->when($excludeId, fn($q) => $q->where('id', '!=', $excludeId))
                     ->exists()) {
            $slug = "{$baseSlug}-{$counter}";
            $counter++;
        }

        return $slug;
    }
}
