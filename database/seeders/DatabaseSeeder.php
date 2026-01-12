<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Venue;
use App\Models\Field;
use App\Models\Schedule;
use Illuminate\Support\Str;
use Carbon\Carbon;

class TestBookingSeeder extends Seeder
{
    public function run()
    {
        // 1. Users
        $user = User::updateOrCreate(
            ['email' => 'user@test.com'],
            [
                'name' => 'Test User',
                'password' => bcrypt('password123'),
                'role' => 'user',
                'remember_token' => Str::random(10),
            ]
        );

        $admin = User::updateOrCreate(
            ['email' => 'admin@test.com'],
            [
                'name' => 'Admin Test',
                'password' => bcrypt('password123'),
                'role' => 'admin',
                'remember_token' => Str::random(10),
            ]
        );

        $owner = User::updateOrCreate(
            ['email' => 'owner@test.com'],
            [
                'name' => 'Owner Test',
                'password' => bcrypt('password123'),
                'role' => 'owner',
                'remember_token' => Str::random(10),
            ]
        );

        // 2. Venue
        $venue = Venue::updateOrCreate(
            ['name' => 'Stadium Test'],
            ['address' => 'Jl. Test No.1']
        );

        // 3. Field
        $field1 = Field::updateOrCreate(
            ['name' => 'Field A', 'venue_id' => $venue->id],
            ['price_per_hour' => 100_000]
        );
        $field2 = Field::updateOrCreate(
            ['name' => 'Field B', 'venue_id' => $venue->id],
            ['price_per_hour' => 150_000]
        );

        // 4. Schedule (hari ini + besok)
        for ($i = 0; $i < 2; $i++) {
            $date = Carbon::today()->addDays($i)->toDateString();

            for ($hour = 8; $hour <= 20; $hour++) {
                Schedule::updateOrCreate(
                    [
                        'field_id' => $field1->id,
                        'start_time' => $date . " {$hour}:00:00"
                    ],
                    [
                        'end_time' => $date . " " . ($hour + 1) . ":00:00",
                        'status' => 'available'
                    ]
                );

                Schedule::updateOrCreate(
                    [
                        'field_id' => $field2->id,
                        'start_time' => $date . " {$hour}:00:00"
                    ],
                    [
                        'end_time' => $date . " " . ($hour + 1) . ":00:00",
                        'status' => 'available'
                    ]
                );
            }
        }

        $this->command->info("Test users, venue, field, and schedule created.");
    }
}
