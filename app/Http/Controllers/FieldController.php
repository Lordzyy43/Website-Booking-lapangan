<?php

namespace App\Http\Controllers;

use App\Models\Field;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;

class FieldController extends Controller
{
    /**
     * ==========================================================
     * ACCESS CONTROL
     * ==========================================================
     * - auth:sanctum   : semua method
     * - role:admin     : CRUD write
     * - user/owner     : read-only (index, show)
     */
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('role:admin')->except(['index', 'show']);
    }

    // ==========================================================
    // GET /fields
    // List all fields (ALL ROLES)
    // ==========================================================
   public function index()
    {
        try {
            $fields = Field::with('venue')->get(); 
            $data = $fields->map(function ($field) {
                return [
                    'id'             => $field->id,
                    'name'           => $field->name,
                    'type'           => $field->type, // Pastikan dikirim agar tidak hilang
                    'venue'          => [
                        'id'         => $field->venue->id,
                        'name'       => $field->venue->name,
                        'address'    => $field->venue->address,
                    ],
                    // MATCH-KAN DISINI
                    'price'          => $field->price_per_hour, 
                    'status'         => $field->is_active ? 'active' : 'inactive', 
                ];
            });

            return response()->json([
                'success' => true,
                'data'    => $data,
            ], 200);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }



    // ==========================================================
    // GET /fields/{field}
    // Detail field (ALL ROLES)
    // ==========================================================
    public function show(Field $field)
    {
        return response()->json([
            'success' => true,
            'data' => $field->load('venue'),
        ], 200);
    }

    // ==========================================================
    // POST /fields
    // Create field (ADMIN ONLY)
    // ==========================================================
    public function store(Request $request)
    {
        $validated = $request->validate([
            'venue_id'       => 'required|exists:venues,id',
            'name'           => 'required|string|max:255',
            'type'           => 'required|in:' . implode(',', Field::TYPES),
            'price_per_hour' => 'required|numeric|min:0',
            'is_active'      => 'boolean',
        ]);

        try {
            $field = Field::create([
                'venue_id'       => $validated['venue_id'],
                'name'           => $validated['name'],
                'type'           => $validated['type'],
                'price_per_hour' => $validated['price_per_hour'],
                'is_active'      => $validated['is_active'] ?? true,
            ]);

            return response()->json([
                'success' => true,
                'data' => $field,
            ], 201);

        } catch (QueryException $e) {
            Log::error('Field store DB error', ['error' => $e->errorInfo]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan field ke database',
            ], 500);

        } catch (\Throwable $e) {
            Log::error('Field store error', ['message' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat membuat field',
            ], 500);
        }
    }

    // ==========================================================
    // PUT /fields/{field}
    // Update field (ADMIN ONLY)
    // ==========================================================
    public function update(Request $request, Field $field)
    {
        $validated = $request->validate([
            'venue_id'       => 'required|exists:venues,id',
            'name'           => 'required|string|max:255',
            'type'           => 'required|in:' . implode(',', Field::TYPES),
            'price_per_hour' => 'required|numeric|min:0',
            'is_active'      => 'boolean',
        ]);

        try {
            $field->update($validated);

            return response()->json([
                'success' => true,
                'data' => $field,
            ], 200);

        } catch (QueryException $e) {
            Log::error('Field update DB error', ['field_id' => $field->id, 'error' => $e->errorInfo]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui field',
            ], 500);

        } catch (\Throwable $e) {
            Log::error('Field update error', ['field_id' => $field->id, 'message' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat memperbarui field',
            ], 500);
        }
    }

    // ==========================================================
    // DELETE /fields/{field}
    // Delete field (ADMIN ONLY)
    // ==========================================================
    public function destroy(Field $field)
    {
        try {
            $field->delete();

            return response()->json([
                'success' => true,
                'message' => 'Field berhasil dihapus',
            ], 200);

        } catch (\Throwable $e) {
            Log::error('Field delete error', ['field_id' => $field->id, 'message' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus field',
            ], 500);
        }
    }
}
