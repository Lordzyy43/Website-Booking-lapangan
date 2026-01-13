<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\{
    AuthController,
    VenueController,
    FieldController,
    ScheduleController,
    UserBookingController, 
    AdminBookingController,
    ReportController,
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
| PUBLIC ROUTES (Tanpa Login)
|--------------------------------------------------------------------------
*/
// Auth
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Explore
Route::prefix('explore')->group(function () {
    Route::get('/venues', [VenueController::class, 'index']);
    Route::get('/venues/{venue}', [VenueController::class, 'show']);

    Route::get('/fields', [FieldController::class, 'explore']);
    Route::get('/fields/{field}/schedules', [ScheduleController::class, 'availableSchedules']);
});

/*| PROTECTED ROUTES (Harus Login)
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {

    // Profile & Logout
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::put('/profile/update', [AuthController::class, 'update']);

    /*
    |--------------------------------------------------------------------------
    | USER (Customer)
    |--------------------------------------------------------------------------
    */
    Route::prefix('user')->middleware('role:user')->group(function () {
        // Booking
        Route::get('/bookings', [UserBookingController::class, 'index']);      // list my bookings (sebelumnya /my)
        Route::post('/bookings', [UserBookingController::class, 'store']);     // create booking
        Route::get('/bookings/{booking}', [UserBookingController::class, 'show']); // detail booking
        Route::delete('/bookings/{booking}', [UserBookingController::class, 'destroy']); // delete booking
        
        // Payment & Cancel
        Route::post('/bookings/{booking}/upload-payment', [UserBookingController::class, 'uploadPayment']);
        Route::post('/bookings/{booking}/cancel', [UserBookingController::class, 'cancel']);
    });

    /*
    |--------------------------------------------------------------------------
    | ADMIN (Pengelola Lapangan)
    |--------------------------------------------------------------------------
    */
    Route::prefix('admin')->middleware('role:admin')->group(function () {

        // Venue
        Route::get('/venues', [VenueController::class, 'index']);
        Route::post('/venues', [VenueController::class, 'store']);
        Route::get('/venues/{venue}', [VenueController::class, 'show']);
        Route::put('/venues/{venue}', [VenueController::class, 'update']);
        Route::delete('/venues/{venue}', [VenueController::class, 'destroy']);

        // Field
        Route::get('/fields', [FieldController::class, 'index']);
        Route::post('/fields', [FieldController::class, 'store']);
        Route::get('/fields/{field}', [FieldController::class, 'show']);
        Route::put('/fields/{field}', [FieldController::class, 'update']);
        Route::delete('/fields/{field}', [FieldController::class, 'destroy']);

        // Schedule
        Route::get('/schedules', [ScheduleController::class, 'index']);
        Route::post('/schedules', [ScheduleController::class, 'store']);
        Route::post('/schedules/generate', [ScheduleController::class, 'generate']);
        Route::put('/schedules/{schedule}', [ScheduleController::class, 'update']);
        Route::delete('/schedules/{schedule}', [ScheduleController::class, 'destroy']);

        // Booking Admin
        Route::get('/bookings', [AdminBookingController::class, 'index']);                 // all bookings
        Route::post('/bookings/{booking}/confirm', [AdminBookingController::class, 'confirm']);
        Route::post('/bookings/{booking}/reject', [AdminBookingController::class, 'reject']);
    });

    /*
    |--------------------------------------------------------------------------
    | OWNER (Laporan & Analytics)
    |--------------------------------------------------------------------------
    */
    Route::prefix('owner')->middleware('role:owner')->group(function () {
        Route::get('/dashboard-stats', [ReportController::class, 'dashboard']);
        Route::get('/reports/income', [ReportController::class, 'incomeReport']);
    });
});
