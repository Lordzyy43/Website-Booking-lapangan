<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\{
    AuthController,
    VenueController,
    FieldController,
    ScheduleController,
    UserBookingController, 
    AdminBookingController,
    ReportController
};

/*
|--------------------------------------------------------------------------
| API ROUTES - SPORT CENTER PROJECT
|--------------------------------------------------------------------------
| PENTING: Jangan ubah urutan PUBLIC dan PROTECTED.
| Laravel mengeksekusi route dari atas ke bawah.
*/

/*
|--------------------------------------------------------------------------
| PUBLIC ROUTES (Bisa Diakses Tanpa Token/Login)
|--------------------------------------------------------------------------
*/
// 1. Auth Dasar
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// 2. Endpoint Eksplorasi (Digunakan Frontend Home & Detail Page)
// Improve: Penambahan Group untuk mempermudah maintenance URL
Route::prefix('explore')->group(function () {
    Route::get('/venues', [VenueController::class, 'index']);
    Route::get('/venues/{venue}', [VenueController::class, 'show']);
    
    // User butuh melihat jadwal yang tersedia sebelum login/booking
    Route::get('/fields/{field}/schedules', [ScheduleController::class, 'availableSchedules']);
});

/*
|--------------------------------------------------------------------------
| PROTECTED ROUTES (Harus Login & Membawa Bearer Token)
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {

    // Info Profile & Logout
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    /*
    |--------------------------------------------------------------------------
    | ROLE: USER (Customer)
    |--------------------------------------------------------------------------
    */
    Route::prefix('user')->middleware('role:user')->group(function () {
        // Management Booking User
        Route::get('/bookings', [UserBookingController::class, 'index']);
        Route::post('/bookings', [UserBookingController::class, 'store']);
        Route::get('/bookings/{booking}', [UserBookingController::class, 'show']);
        
        // Fitur Tambahan: Upload Bukti Bayar & Pembatalan
        Route::post('/bookings/{booking}/upload-payment', [UserBookingController::class, 'uploadPayment']);
        Route::post('/bookings/{booking}/cancel', [UserBookingController::class, 'cancel']);
    });

    /*
    |--------------------------------------------------------------------------
    | ROLE: ADMIN (Pengelola Lapangan)
    |--------------------------------------------------------------------------
    */
    Route::prefix('admin')->middleware('role:admin')->group(function () {
        // Kelola Venue
        Route::get('/venues', [VenueController::class, 'index']);
        Route::post('/venues', [VenueController::class, 'store']);
        Route::get('/venues/{venue}', [VenueController::class, 'show']);
        Route::put('/venues/{venue}', [VenueController::class, 'update']);
        Route::delete('/venues/{venue}', [VenueController::class, 'destroy']);

        // Kelola Field
        Route::get('/fields', [FieldController::class, 'index']);
        Route::post('/fields', [FieldController::class, 'store']);
        Route::get('/fields/{field}', [FieldController::class, 'show']);
        Route::put('/fields/{field}', [FieldController::class, 'update']);
        Route::delete('/fields/{field}', [FieldController::class, 'destroy']);

        // Kelola Schedule (Pengaturan Waktu Main)
        Route::get('/schedules', [ScheduleController::class, 'index']);
        Route::post('/schedules/generate', [ScheduleController::class, 'generate']);
        Route::post('/schedules', [ScheduleController::class, 'store']);
        Route::put('/schedules/{schedule}', [ScheduleController::class, 'update']);
        Route::delete('/schedules/{schedule}', [ScheduleController::class, 'destroy']);
        
        // Konfirmasi & Kelola Semua Booking (AdminBookingController)
        Route::get('/bookings', [AdminBookingController::class, 'index']);
        Route::post('/bookings/{booking}/confirm', [AdminBookingController::class, 'confirm']);
        Route::post('/bookings/{booking}/reject', [AdminBookingController::class, 'reject']);
    });

    /*
    |--------------------------------------------------------------------------
    | ROLE: OWNER (Laporan & Analytics)
    |--------------------------------------------------------------------------
    */
    Route::prefix('owner')->middleware('role:owner')->group(function () {
        Route::get('/dashboard-stats', [ReportController::class, 'dashboard']);
        Route::get('/reports/income', [ReportController::class, 'incomeReport']); 
    });
});