<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        if (! User::where('role', 'admin')->where('is_active', true)->exists()) {
            throw new \RuntimeException('Run the required database seed and configure an active admin before DemoDataSeeder.');
        }

        $this->call([
            UserManagementSeeder::class,
            JobPositionSeeder::class,
            CandidateSeeder::class,
            ApplicationSeeder::class,
            InterviewSeeder::class,
        ]);
    }
}
