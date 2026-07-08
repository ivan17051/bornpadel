<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run()
    {
        $accounts = [
            [
                'name' => 'Admin Born Padel',
                'username' => 'admin',
                'email' => 'admin@bornpadel.com',
                'role' => 'admin',
                'id_turnamen' => null,
            ],
            [
                'name' => 'Panitia Born Padel',
                'username' => 'panitia',
                'email' => 'panitia@bornpadel.com',
                'role' => 'panitia',
                'id_turnamen' => null,
            ],
        ];

        $password = Hash::make('12345678');

        foreach ($accounts as $account) {
            $user = User::where('username', $account['username'])
                ->orWhere('email', $account['email'])
                ->first();

            $attributes = array_merge($account, [
                'password' => $password,
            ]);

            if ($user) {
                $user->update($attributes);
            } else {
                User::create($attributes);
            }
        }

        if ($this->command) {
            $this->command->info('Users seeded: admin / panitia (password: 12345678)');
        }
    }
}
