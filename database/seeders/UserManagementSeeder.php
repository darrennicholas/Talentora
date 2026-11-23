<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class UserManagementSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            ['name' => 'Taylor Brooks', 'email' => 'taylor.brooks@talentora-demo.test'],
            ['name' => 'Morgan Ellis', 'email' => 'morgan.ellis@talentora-demo.test'],
            ['name' => 'Casey Bennett', 'email' => 'casey.bennett@talentora-demo.test'],
            ['name' => 'Jordan Reyes', 'email' => 'jordan.reyes@talentora-demo.test'],
            ['name' => 'Avery Parker', 'email' => 'avery.parker@talentora-demo.test'],
        ];

        foreach ($users as $user) {
            User::firstOrCreate(['email' => $user['email']], [
                'name' => $user['name'],
                'password' => Str::random(48),
                'role' => 'admin',
                'is_active' => false,
            ]);
        }
    }
}
