<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\VenueController;
use App\Http\Controllers\FieldController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\BookingItemController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\AdminBookingController;

/*
|--------------------------------------------------------------------------
| PUBLIC ROUTES (No Auth Needed)
|--------------------------------------------------------------------------
| Bisa diakses siapa saja
*/
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

/*
|--------------------------------------------------------------------------
| PROTECTED ROUTES (Sanctum Auth Required)
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {

    // ----------------------------------------------------------------------
    // Basic Auth Utilities
    // ----------------------------------------------------------------------
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // ----------------------------------------------------------------------
    // PUBLIC DATA (All Roles Can Read)
    // ----------------------------------------------------------------------
    Route::get('/venues', [VenueController::class, 'index']);
    Route::get('/venues/{venue}', [VenueController::class, 'show']);

    Route::get('/fields', [FieldController::class, 'index']);
    Route::get('/fields/{field}', [FieldController::class, 'show']);

    Route::get('/schedules', [ScheduleController::class, 'index']);
    Route::get('/schedules/{schedule}', [ScheduleController::class, 'show']);

    // ----------------------------------------------------------------------
    // USER ROUTES (Customer)
    // ----------------------------------------------------------------------
    Route::prefix('user')->middleware('role:user')->group(function () {

        // Buat booking (kosong, ada expired)
        Route::post('/bookings', [BookingController::class, 'store']);

        // Lihat booking milik sendiri
        Route::get('/bookings', [BookingController::class, 'myBookings']);

        // Tambah slot ke booking
        Route::post('/booking-items', [BookingItemController::class, 'store']);
    });

    // ----------------------------------------------------------------------
    // ADMIN ROUTES (System Operator)
    // ----------------------------------------------------------------------
    Route::prefix('admin')->middleware('role:admin')->group(function () {

        // Venue CRUD
        Route::post('/venues', [VenueController::class, 'store']);
        Route::put('/venues/{venue}', [VenueController::class, 'update']);
        Route::delete('/venues/{venue}', [VenueController::class, 'destroy']);

        // Field CRUD
        Route::post('/fields', [FieldController::class, 'store']);
        Route::put('/fields/{field}', [FieldController::class, 'update']);
        Route::delete('/fields/{field}', [FieldController::class, 'destroy']);

        // Schedule CRUD
        Route::post('/schedules', [ScheduleController::class, 'store']);
        Route::put('/schedules/{schedule}', [ScheduleController::class, 'update']);
        Route::delete('/schedules/{schedule}', [ScheduleController::class, 'destroy']);

        // Konfirmasi booking
        Route::post('/bookings/{booking}/confirm', [AdminBookingController::class, 'confirm']);

    });

    // ----------------------------------------------------------------------
    // OWNER ROUTES (Reports Only)
    // ----------------------------------------------------------------------
    Route::prefix('owner')->middleware('role:owner')->group(function () {

        Route::get('/reports/weekly', [ReportController::class, 'weeklyReport']);
        Route::get('/reports/monthly', [ReportController::class, 'monthlyReport']);
        Route::get('/reports/yearly', [ReportController::class, 'yearlyReport']);
    });
});
