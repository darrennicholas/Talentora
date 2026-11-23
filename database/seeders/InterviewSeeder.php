<?php

namespace Database\Seeders;

use App\Models\Application;
use App\Models\Interview;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class InterviewSeeder extends Seeder
{
    public function run(): void
    {
        $adminId = User::where('role', 'admin')->where('is_active', true)->orderBy('id')->value('id');

        if (! $adminId) {
            throw new \RuntimeException('Create an active admin account before running DemoDataSeeder.');
        }

        $today = Carbon::now('Asia/Jakarta')->startOfDay();
        $interviews = [
            [
                'key' => 'Demo interview 001: upcoming data analysis interview',
                'application' => 'APP-01KATY00000000000000000003', 'days_from_today' => 2, 'start_time' => '10:00',
                'status' => 'scheduled', 'rating' => null, 'recommendation' => null, 'feedback' => null,
                'location' => 'Jakarta Office · Meeting Room 2',
            ],
            [
                'key' => 'Demo interview 002: cancelled after a candidate reschedule request',
                'application' => 'APP-01KATY00000000000000000003', 'days_from_today' => -2, 'start_time' => '10:00',
                'status' => 'cancelled', 'rating' => null, 'recommendation' => null, 'feedback' => null,
                'location' => 'Jakarta Office · Meeting Room 2',
            ],
            [
                'key' => 'Demo interview 003: recruiting experience panel',
                'application' => 'APP-01KATY00000000000000000004', 'days_from_today' => -8, 'start_time' => '09:00',
                'status' => 'completed', 'rating' => 4, 'recommendation' => 'recommended',
                'feedback' => 'Strong sourcing strategy and thoughtful candidate communication.',
                'location' => 'Jakarta Office · Meeting Room 1',
            ],
            [
                'key' => 'Demo interview 004: stakeholder partnership panel',
                'application' => 'APP-01KATY00000000000000000004', 'days_from_today' => -7, 'start_time' => '10:30',
                'status' => 'completed', 'rating' => 5, 'recommendation' => 'recommended',
                'feedback' => 'Clear examples of influencing hiring managers and improving time to hire.',
                'location' => 'Jakarta Office · Meeting Room 1',
            ],
            [
                'key' => 'Demo interview 005: customer success leadership panel',
                'application' => 'APP-01KATY00000000000000000005', 'days_from_today' => -12, 'start_time' => '14:00',
                'status' => 'completed', 'rating' => 5, 'recommendation' => 'recommended',
                'feedback' => 'Excellent enterprise onboarding approach with measurable adoption outcomes.',
                'location' => 'Surabaya Office · Meeting Room 3',
            ],
        ];

        foreach ($interviews as $interview) {
            $applicationId = Application::where('application_code', $interview['application'])->value('id');

            if (! $applicationId) {
                throw new \RuntimeException('Run ApplicationSeeder before InterviewSeeder.');
            }

            [$hour, $minute] = array_map('intval', explode(':', $interview['start_time']));
            $start = $today->copy()->addDays($interview['days_from_today'])->setTime($hour, $minute);

            Interview::firstOrCreate(
                ['notes' => $interview['key']],
                [
                    'application_id' => $applicationId,
                    'interviewer_id' => $adminId,
                    'start_at' => $start->copy()->utc(),
                    'end_at' => $start->copy()->addHour()->utc(),
                    'mode' => 'onsite',
                    'meeting_link' => null,
                    'location' => $interview['location'],
                    'status' => $interview['status'],
                    'rating' => $interview['rating'],
                    'recommendation' => $interview['recommendation'],
                    'feedback' => $interview['feedback'],
                    'created_by' => $adminId,
                ],
            );
        }
    }
}
