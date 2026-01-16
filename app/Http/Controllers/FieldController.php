<?php

namespace App\Http\Controllers;

use App\Models\Field;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class FieldController extends Controller
{
    /**
     * ==========================================================
     * ACCESS CONTROL
     * ==========================================================
     */
    public function __construct()
    {
        $this->middleware('auth:sanctum')->except(['index', 'show', 'explore']);
        $this->middleware('role:admin')->except([
            'index',
            'show',
            'explore',
        ]);
    }

    /**
     * ==========================================================
     * RESPONSE FORMATTER (SATU PINTU)
     * ==========================================================
     */
    private function formatField(Field $field): array
    {
        return [
            'id'     => $field->id,
            'name'   => $field->name,
            'type'   => $field->type,
            'price'  => $field->price_per_hour,
            'image'  => $field->image
            ? asset('storage/' . $field->image)
            : null,
            'status' => $field->is_active ? 'active' : 'inactive',
            'venue'  => $field->relationLoaded('venue') && $field->venue
                ? [
                    'id'   => $field->venue->id,
                    'name' => $field->venue->name,
                ]
                : null,
        ];
    }

    /**
     * ==========================================================
     * GET /fields
     * List all fields (ALL ROLES)
     * ==========================================================
     */
    public function index()
    {
        try {
            $fields = Field::with('venue:id,name')->get();

            return response()->json([
                'success' => true,
                'data' => $fields->map(
                    fn ($f) => $this->formatField($f)
                ),
            ]);

        } catch (\Throwable $e) {
            Log::error('Field index error', ['message' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data field',
            ], 500);
        }
    }

    /**
     * ==========================================================
     * GET /fields/explore
     * Public explore (ACTIVE ONLY)
     * ==========================================================
     */
    public function explore()
    {
        $fields = Field::where('is_active', true)
            ->with('venue:id,name,address')
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $fields->map(
                fn ($f) => $this->formatField($f)
            ),
        ]);
    }

    /**
     * ==========================================================
     * GET /fields/{field}
     * Detail field (ALL ROLES)
     * ==========================================================
     */
    public function show(Field $field)
    {
        $field->load('venue:id,name');

        return response()->json([
            'success' => true,
            'data' => $this->formatField($field),
        ]);
    }

    /**
     * ==========================================================
     * POST /fields
     * Create field (ADMIN ONLY)
     * multipart/form-data
     * ==========================================================
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'venue_id'       => 'required|exists:venues,id',
            'name'           => 'required|string|max:255',
            'type'           => ['required', Rule::in(Field::TYPES)],
            'price_per_hour' => 'required|numeric|min:0',
            'image'          => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            'is_active'      => 'boolean',
        ]);

        try {
            if ($request->hasFile('image')) {
                $validated['image'] = $request
                    ->file('image')
                    ->store('fields', 'public');
            }

            $field = Field::create($validated);
            $field->load('venue:id,name');

            return response()->json([
                'success' => true,
                'data' => $this->formatField($field),
            ], 201);

        } catch (QueryException $e) {
            Log::error('Field store DB error', [
                'error' => $e->errorInfo,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan field ke database',
            ], 500);

        } catch (\Throwable $e) {
            Log::error('Field store error', [
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat membuat field',
            ], 500);
        }
    }

    /**
     * ==========================================================
     * PUT /fields/{field}
     * Update field (ADMIN ONLY)
     * multipart/form-data
     * ==========================================================
     */
    public function update(Request $request, Field $field)
    {
        $validated = $request->validate([
            'venue_id'       => 'required|exists:venues,id',
            'name'           => 'required|string|max:255',
            'type'           => ['required', Rule::in(Field::TYPES)],
            'price_per_hour' => 'required|numeric|min:0',
            'image'          => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            'is_active'      => 'boolean',
        ]);

        try {
            if ($request->hasFile('image')) {
                if (
                    $field->image &&
                    Storage::disk('public')->exists($field->image)
                ) {
                    Storage::disk('public')->delete($field->image);
                }

                $validated['image'] = $request
                    ->file('image')
                    ->store('fields', 'public');
            }

            $field->update($validated);
            $field->load('venue:id,name');

            return response()->json([
                'success' => true,
                'data' => $this->formatField($field),
            ]);

        } catch (QueryException $e) {
            Log::error('Field update DB error', [
                'field_id' => $field->id,
                'error' => $e->errorInfo,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui field',
            ], 500);

        } catch (\Throwable $e) {
            Log::error('Field update error', [
                'field_id' => $field->id,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat memperbarui field',
            ], 500);
        }
    }

    /**
     * ==========================================================
     * DELETE /fields/{field}
     * Soft delete (ADMIN ONLY)
     * ==========================================================
     */
    public function destroy(Field $field)
    {
        try {
            $field->delete();

            return response()->json([
                'success' => true,
                'message' => 'Field berhasil dihapus',
            ]);

        } catch (\Throwable $e) {
            Log::error('Field delete error', [
                'field_id' => $field->id,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus field',
            ], 500);
        }
    }
}
