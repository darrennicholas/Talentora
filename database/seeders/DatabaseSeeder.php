<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        \Illuminate\Support\Facades\DB::table('app_settings')->upsert([
            ['setting_key' => 'company_name', 'setting_value' => 'Talentora Demo Company', 'created_at' => now(), 'updated_at' => now()],
            ['setting_key' => 'timezone', 'setting_value' => 'Asia/Jakarta', 'created_at' => now(), 'updated_at' => now()],
        ], ['setting_key'], ['setting_value', 'updated_at']);

        if (env('ADMIN_EMAIL') && env('ADMIN_PASSWORD')) {
            User::firstOrCreate(['email' => strtolower(trim(env('ADMIN_EMAIL')))], [
                'name' => env('ADMIN_NAME', 'Administrator'), 'password' => env('ADMIN_PASSWORD'),
                'role' => 'admin', 'is_active' => true,
            ]);
        }
    }
}
