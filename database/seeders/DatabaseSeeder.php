<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        //admin user
        User::create([
            'name' => 'Admin',
            'email' => 'admin@gmail.com',
            'password' => Hash::make('12345678'),
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);
        //tutor user
        User::create([
            'name' => 'Tutor',
            'email' => 'tutor@gmail.com',
            'password' => Hash::make('12345678'),
            'role' => 'tutor',
            'email_verified_at' => now(),
        ]);
        //student user
        User::create([
            'name' => 'Student',
            'email' => 'user@gmail.com',
            'password' => Hash::make('12345678'),
            'role' => 'user',
            'email_verified_at' => now(),
        ]);

    }
}
