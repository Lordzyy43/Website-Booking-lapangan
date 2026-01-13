<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\Booking;
use App\Models\Schedule;

class ExpireBookings extends Command
{
    protected $signature = 'booking:expire';
    protected $description = 'Expire unpaid bookings and unlock schedules';

    public function handle()
    {
        DB::transaction(function () {

            $expiredBookings = Booking::where('payment_status', 'unpaid')
                ->where('expired_at', '<', now())
                ->with('items.schedule')
                ->lockForUpdate()
                ->get();

            foreach ($expiredBookings as $booking) {

                // update booking status
                $booking->update([
                    'payment_status' => 'expired',
                ]);

                // unlock schedules
                foreach ($booking->items as $item) {
                    $schedule = $item->schedule;

                    if ($schedule && $schedule->status !== 'booked') {
                        $schedule->unlock();
                    }
                }
            }
        });

        $this->info('Expired bookings processed successfully');
    }
}
