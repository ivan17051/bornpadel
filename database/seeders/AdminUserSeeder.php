<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run()
    {
        $user = User::where('username', 'admin')
            ->orWhere('email', 'admin@bornpadel.com')
            ->first();

        $attributes = [
            'name' => 'Admin Born Padel',
            'username' => 'admin',
            'email' => 'admin@bornpadel.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'id_turnamen' => null,
        ];

        if ($user) {
            $user->update($attributes);
        } else {
            User::create($attributes);
        }
    }
}
