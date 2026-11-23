<?php

namespace Database\Seeders;

use App\Models\Application;
use App\Models\ApplicationStageHistory;
use App\Models\Candidate;
use App\Models\JobPosition;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class ApplicationSeeder extends Seeder
{
    public function run(): void
    {
        $adminId = User::where('role', 'admin')->where('is_active', true)->orderBy('id')->value('id');

        if (! $adminId) {
            throw new \RuntimeException('Create an active admin account before running DemoDataSeeder.');
        }

        $today = Carbon::now('Asia/Jakarta')->startOfDay();
        $plans = [
            [
                'code' => 'APP-01KATY00000000000000000001', 'candidate' => 'jordan.blake@example.test', 'position' => 'REQ-2026-001',
                'source' => 'job_portal', 'stage' => 'applied', 'applied_days_ago' => 8,
                'history' => [['from' => null, 'to' => 'applied', 'days_ago' => 8, 'note' => 'Application received from the company career portal.']],
            ],
            [
                'code' => 'APP-01KATY00000000000000000002', 'candidate' => 'maya.bennett@example.test', 'position' => 'REQ-2026-002',
                'source' => 'referral', 'stage' => 'screening', 'applied_days_ago' => 11,
                'history' => [
                    ['from' => null, 'to' => 'applied', 'days_ago' => 11, 'note' => 'Application received through an employee referral.'],
                    ['from' => 'applied', 'to' => 'screening', 'days_ago' => 8, 'note' => 'Profile selected for portfolio and experience review.'],
                ],
            ],
            [
                'code' => 'APP-01KATY00000000000000000003', 'candidate' => 'ethan.cole@example.test', 'position' => 'REQ-2026-003',
                'source' => 'job_portal', 'stage' => 'interview', 'applied_days_ago' => 14,
                'history' => [
                    ['from' => null, 'to' => 'applied', 'days_ago' => 14, 'note' => 'Application received through the company career portal.'],
                    ['from' => 'applied', 'to' => 'screening', 'days_ago' => 11, 'note' => 'Experience and analytical portfolio meet the role requirements.'],
                    ['from' => 'screening', 'to' => 'interview', 'days_ago' => 4, 'note' => 'Interview stage started after the first interview was scheduled.'],
                ],
            ],
            [
                'code' => 'APP-01KATY00000000000000000004', 'candidate' => 'sophie.carter@example.test', 'position' => 'REQ-2026-004',
                'source' => 'referral', 'stage' => 'offered', 'applied_days_ago' => 18,
                'history' => [
                    ['from' => null, 'to' => 'applied', 'days_ago' => 18, 'note' => 'Application received through an employee referral.'],
                    ['from' => 'applied', 'to' => 'screening', 'days_ago' => 15, 'note' => 'Recruiting experience and references approved for interview.'],
                    ['from' => 'screening', 'to' => 'interview', 'days_ago' => 12, 'note' => 'Interview stage started after the first interview was scheduled.'],
                    ['from' => 'interview', 'to' => 'offered', 'days_ago' => 6, 'note' => 'Interview panel completed its evaluation and recommended an offer.'],
                ],
            ],
            [
                'code' => 'APP-01KATY00000000000000000005', 'candidate' => 'liam.parker@example.test', 'position' => 'REQ-2026-005',
                'source' => 'manual', 'stage' => 'hired', 'applied_days_ago' => 22,
                'history' => [
                    ['from' => null, 'to' => 'applied', 'days_ago' => 22, 'note' => 'Application entered by the recruiting team.'],
                    ['from' => 'applied', 'to' => 'screening', 'days_ago' => 19, 'note' => 'Customer portfolio and account experience approved for interview.'],
                    ['from' => 'screening', 'to' => 'interview', 'days_ago' => 16, 'note' => 'Interview stage started after the first interview was scheduled.'],
                    ['from' => 'interview', 'to' => 'offered', 'days_ago' => 9, 'note' => 'Completed evaluation supports a customer success offer.'],
                    ['from' => 'offered', 'to' => 'hired', 'days_ago' => 3, 'note' => 'Offer accepted and hiring completed.'],
                ],
            ],
        ];

        foreach ($plans as $plan) {
            $candidateId = Candidate::where('email', $plan['candidate'])->value('id');
            $positionId = JobPosition::where('code', $plan['position'])->value('id');

            if (! $candidateId || ! $positionId) {
                throw new \RuntimeException('Run CandidateSeeder and JobPositionSeeder before ApplicationSeeder.');
            }

            $history = collect($plan['history'])->map(function (array $step, int $index) use ($today, $adminId) {
                return [
                    ...$step,
                    'changed_by' => $adminId,
                    'changed_at' => $today->copy()->subDays($step['days_ago'])->setTime(9 + $index, 0)->utc(),
                ];
            });
            $lastChange = $history->last()['changed_at'];

            $application = Application::firstOrCreate(
                ['application_code' => $plan['code']],
                [
                    'candidate_id' => $candidateId,
                    'job_position_id' => $positionId,
                    'source' => $plan['source'],
                    'stage' => $plan['stage'],
                    'applied_at' => $today->copy()->subDays($plan['applied_days_ago'])->toDateString(),
                    'stage_updated_at' => $lastChange,
                    'hired_at' => $plan['stage'] === 'hired' ? $lastChange : null,
                    'rejected_at' => null,
                    'rejection_reason' => null,
                    'notes' => 'Portfolio sample application for the ' . $plan['stage'] . ' stage.',
                    'created_by' => $adminId,
                ],
            );

            foreach ($history as $step) {
                ApplicationStageHistory::firstOrCreate(
                    ['application_id' => $application->id, 'to_stage' => $step['to']],
                    [
                        'from_stage' => $step['from'],
                        'note' => $step['note'],
                        'changed_by' => $step['changed_by'],
                        'changed_at' => $step['changed_at'],
                    ],
                );
            }
        }
    }
}
