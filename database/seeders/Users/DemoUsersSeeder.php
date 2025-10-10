<?php

namespace Database\Seeders\Users;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoUsersSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'kelvin@example.com'],
            [
                'name' => 'Kelvin Demo',
                'password' => Hash::make('password'),
                'balance' => '1000.00',
            ]
        );

        User::updateOrCreate(
            ['email' => 'tolu@example.com'],
            [
                'name' => 'Tolu Demo',
                'password' => Hash::make('password'),
                'balance' => '100.00',
            ]
        );
    }
}
