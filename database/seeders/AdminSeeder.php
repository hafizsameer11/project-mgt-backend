<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'admin@admin.com'],
            [
                'name' => 'Admin User',
                'email' => 'admin@admin.com',
                'password' => Hash::make('11221122'),
                'role' => 'Admin',
            ]
        );

        $this->command->info('Admin user created!');
        $this->command->info('Email: admin@admin.com');
        $this->command->info('Password: password');
    }
}

