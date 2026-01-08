<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Base Controller untuk semua controller di project Booking
 * 
 * Fungsi utama:
 * 1. Memberikan akses ke middleware, validasi, dan authorisasi
 * 2. Menjadi tempat method utilitas global untuk controller lain
 * 3. Bisa ditambahkan helper untuk logging, response, dsb
 */
class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    /**
     * Contoh helper standar untuk response sukses
     */
    protected function successResponse($data = [], $message = 'Success', $status = 200)
    {
        return response()->json([
            'message' => $message,
            'data'    => $data,
        ], $status);
    }

    /**
     * Contoh helper standar untuk response error
     */
    protected function errorResponse($message = 'Error', $status = 500, $extra = [])
    {
        $payload = array_merge(['message' => $message], $extra);
        Log::error('API Error', $payload); // log ke storage/logs/laravel.log
        return response()->json($payload, $status);
    }

    /**
     * Contoh method untuk validate dengan handling otomatis
     */
    protected function validateRequest(Request $request, array $rules, array $messages = [])
    {
        try {
            return $request->validate($rules, $messages);
        } catch (\Throwable $e) {
            return $this->errorResponse('Validasi gagal: ' . $e->getMessage(), 422);
        }
    }
}
