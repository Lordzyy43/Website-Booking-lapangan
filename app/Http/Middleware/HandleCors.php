<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class HandleCors
{
    /**
     * Daftar origin yang diizinkan (localhost, LAN, HP dev, prod)
     */
    protected $allowedOrigins = [
        'http://localhost:5173',     // dev frontend
        'http://192.168.1.100:5173', // HP / LAN dev
        'https://myproduction.com',  // production
    ];

    public function handle(Request $request, Closure $next)
    {
        $origin = $request->header('Origin');

        // Izinkan origin hanya jika ada di whitelist
        if ($origin && in_array($origin, $this->allowedOrigins)) {
            $headers = [
                'Access-Control-Allow-Origin'      => $origin,
                'Access-Control-Allow-Methods'     => 'GET, POST, PUT, PATCH, DELETE, OPTIONS',
                'Access-Control-Allow-Headers'     => 'Content-Type, Authorization, X-Requested-With',
                'Access-Control-Allow-Credentials' => 'true',
            ];

            // Preflight OPTIONS request → langsung return 204
            if ($request->getMethod() === 'OPTIONS') {
                return response()->noContent(204, $headers);
            }

            // Request biasa → tambahkan header CORS ke response
            $response = $next($request);
            foreach ($headers as $key => $value) {
                $response->headers->set($key, $value);
            }

            return $response;
        }

        // Jika origin tidak di whitelist, tetap lanjutkan request tanpa CORS
        return $next($request);
    }
}
