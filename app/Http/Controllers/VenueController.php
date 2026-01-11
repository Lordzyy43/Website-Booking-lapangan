<?php

namespace App\Http\Controllers;

use App\Models\Venue;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class VenueController extends Controller
{
    public function __construct()
    {
        // Middleware untuk keamanan: Sanctum untuk auth, Role admin untuk modifikasi data
        $this->middleware('auth:sanctum')->except(['index', 'show']);
        $this->middleware('role:admin')->except(['index', 'show']);
    }

    /**
     * Menampilkan semua daftar venue.
     */
    public function index()
    {
        try {
            $venues = Venue::latest()->get();
            return response()->json(['success' => true, 'data' => $venues], 200);
        } catch (\Throwable $e) {
            Log::error('Venue index error', ['message' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Gagal mengambil data venue'], 500);
        }
    }

    /**
     * Menampilkan detail satu venue berdasarkan ID/Slug.
     */
   public function show($id)
    {
        // Gunakan 'with' agar data lapangan (fields) ikut terbawa ke React
        $venue = Venue::with('fields')->findOrFail($id);
        return response()->json(['data' => $venue]);
    }

    /**
     * Menyimpan venue baru ke database.
     */
    public function store(Request $request)
    {
        // Validasi input dari frontend
        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'address'     => 'required|string',
            'description' => 'nullable|string',
            'open_time'   => 'required', // Format jam dikontrol di Model Accessor/React
            'close_time'  => 'required', 
            'image'       => 'nullable|image|max:2048',
        ]);

        try {
            // Membuat slug unik otomatis dari nama venue
            $slug = $this->generateUniqueSlug($validated['name']);

            $imagePath = null;
            if ($request->hasFile('image')) {
                // Simpan file ke storage/app/public/venues
                // Kita simpan path relatifnya saja agar Accessor di Model bisa bekerja maksimal
                $imagePath = $request->file('image')->store('venues', 'public');
            }

            $venue = Venue::create([
                'user_id'     => auth()->id(), // Mengambil ID user yang sedang login
                'name'        => $validated['name'],
                'slug'        => $slug,
                'address'     => $validated['address'],
                'description' => $validated['description'] ?? null,
                'open_time'   => $validated['open_time'],
                'close_time'  => $validated['close_time'],
                'image'       => $imagePath,
            ]);

            return response()->json(['success' => true, 'data' => $venue], 201);

        } catch (QueryException $e) {
            Log::error('Venue store DB error', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Gagal menyimpan ke database: ' . $e->getMessage()], 500);
        } catch (\Throwable $e) {
            Log::error('Venue store error', ['message' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Memperbarui data venue yang sudah ada.
     */
    public function update(Request $request, Venue $venue)
    {
        // Catatan: Karena menggunakan FormData di React, kadang file dikirim via POST dengan _method=PUT
        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'address'     => 'required|string',
            'description' => 'nullable|string',
            'open_time'   => 'required',
            'close_time'  => 'required',
            'image'       => 'nullable|image|max:2048',
        ]);

        try {
            // Logika Update Gambar
            if ($request->hasFile('image')) {
                // Hapus gambar lama dari storage jika ada (agar tidak nyampah)
                if ($venue->getRawOriginal('image')) {
                    Storage::disk('public')->delete($venue->getRawOriginal('image'));
                }
                // Simpan gambar baru
                $venue->image = $request->file('image')->store('venues', 'public');
            }

            // Update data lainnya
            $venue->update([
                'name'        => $validated['name'],
                // Update slug jika nama berubah
                'slug'        => $this->generateUniqueSlug($validated['name'], $venue->id),
                'address'     => $validated['address'],
                'description' => $validated['description'] ?? null,
                'open_time'   => $validated['open_time'],
                'close_time'  => $validated['close_time'],
            ]);

            return response()->json(['success' => true, 'data' => $venue], 200);

        } catch (\Throwable $e) {
            Log::error('Venue update error', ['venue_id' => $venue->id, 'message' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Gagal memperbarui: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Menghapus venue (Soft Delete).
     */
    public function destroy(Venue $venue)
    {
        try {
            // Optional: Jika ingin gambar benar-benar hilang saat data dihapus
            // if ($venue->getRawOriginal('image')) {
            //     Storage::disk('public')->delete($venue->getRawOriginal('image'));
            // }

            $venue->delete();
            return response()->json(['success' => true, 'message' => 'Venue berhasil dihapus'], 200);

        } catch (\Throwable $e) {
            Log::error('Venue delete error', ['venue_id' => $venue->id, 'message' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Gagal menghapus venue'], 500);
        }
    }

    /**
     * Helper: Membuat slug yang unik meskipun ada nama venue yang sama.
     */
    private function generateUniqueSlug(string $name, ?int $excludeId = null): string
    {
        $baseSlug = Str::slug($name);
        $slug = $baseSlug;
        $counter = 1;

        // Cek ke database apakah slug sudah dipakai venue lain
        while (Venue::where('slug', $slug)
                     ->when($excludeId, fn($q) => $q->where('id', '!=', $excludeId))
                     ->exists()) {
            $slug = "{$baseSlug}-{$counter}";
            $counter++;
        }

        return $slug;
    }
}