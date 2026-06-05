<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('ADMIN_EMAIL') ?: 'admin@volgjeraad.test';
        $password = env('ADMIN_PASSWORD') ?: 'password';

        User::updateOrCreate(
            ['email' => $email],
            [
                'name' => 'Volgjeraad Admin',
                'password' => Hash::make($password),
                'is_admin' => true,
            ],
        );
    }
}
