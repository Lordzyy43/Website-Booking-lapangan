<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Application Console Commands
|--------------------------------------------------------------------------
| Tempat mendaftarkan scheduler & command level aplikasi
| Laravel 12 replacement untuk Console Kernel
*/

/**
 * EXPIRE BOOKING
 * - Unlock schedule yang locked terlalu lama
 * - Cancel booking unpaid yang sudah lewat waktu
 */
Schedule::command('booking:expire')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();

/**
 * OPTIONAL: TEST COMMAND (boleh dihapus nanti)
 */
Artisan::command('health:check', function () {
    $this->info('Console OK. Scheduler hidup. Sistem belum ambruk.');
})->purpose('Check console & scheduler health');
