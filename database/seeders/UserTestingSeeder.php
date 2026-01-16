<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Str;

class UserTestingSeeder extends Seeder
{
    public function run()
    {
        // Data akun yang ingin dibuat
        $users = [
            [
                'id'       => 1,
                'name'     => 'Test User',
                'email'    => 'user@test.com',
                'password' => bcrypt('password123'),
                'role'     => 'user',
            ],
            [
                'id'       => 2,
                'name'     => 'Admin Test',
                'email'    => 'admin@test.com',
                'password' => bcrypt('password123'),
                'role'     => 'admin',
            ],
            [
                'id'       => 3,
                'name'     => 'Owner Test',
                'email'    => 'owner@test.com',
                'password' => bcrypt('password123'),
                'role'     => 'owner',
            ],
        ];

        foreach ($users as $userData) {
            User::updateOrCreate(
                ['email' => $userData['email']], // Cek berdasarkan email supaya tidak duplikat
                [
                    'name'           => $userData['name'],
                    'password'       => $userData['password'],
                    'role'           => $userData['role'],
                    'remember_token' => Str::random(10),
                ]
            );
        }

        $this->command->info("Success! Akun User, Admin, dan Owner berhasil dibuat.");
        $this->command->warn("Sekarang silakan tambah Venue & Field manual via Admin Panel ya Sensei!");
    }
}