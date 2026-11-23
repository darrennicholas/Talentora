<?php

namespace Database\Seeders;

use App\Models\JobPosition;
use App\Models\User;
use Illuminate\Database\Seeder;

class JobPositionSeeder extends Seeder
{
    public function run(): void
    {
        $adminId = User::where('role', 'admin')->where('is_active', true)->orderBy('id')->value('id');

        if (! $adminId) {
            throw new \RuntimeException('Create an active admin account before running DemoDataSeeder.');
        }

        $positions = [
            [
                'code' => 'REQ-2026-001',
                'title' => 'Senior Full Stack Engineer',
                'department' => 'Engineering',
                'employment_type' => 'full_time',
                'location' => 'Jakarta, Indonesia · Hybrid',
                'openings' => 2,
                'description' => 'Build dependable recruitment products and improve the platform across its Laravel and React services.',
                'requirements' => '5+ years of full stack experience; strong PHP, Laravel, React, and SQL skills; clear written communication.',
                'status' => 'open',
            ],
            [
                'code' => 'REQ-2026-002',
                'title' => 'Product Designer',
                'department' => 'Product Design',
                'employment_type' => 'full_time',
                'location' => 'Remote, Indonesia',
                'openings' => 1,
                'description' => 'Shape accessible product workflows from early research through polished, production-ready interface details.',
                'requirements' => '4+ years in product design; strong interaction design and prototyping skills; experience working with design systems.',
                'status' => 'open',
            ],
            [
                'code' => 'REQ-2026-003',
                'title' => 'Data Analyst',
                'department' => 'Data & Analytics',
                'employment_type' => 'full_time',
                'location' => 'Bandung, Indonesia · Hybrid',
                'openings' => 1,
                'description' => 'Turn product and recruitment data into reliable reporting that helps teams make better decisions.',
                'requirements' => '3+ years in analytics; advanced SQL; practical experience with Python, dashboards, and metric definitions.',
                'status' => 'open',
            ],
            [
                'code' => 'REQ-2026-004',
                'title' => 'Talent Acquisition Partner',
                'department' => 'People & Culture',
                'employment_type' => 'contract',
                'location' => 'Jakarta, Indonesia · Onsite',
                'openings' => 1,
                'description' => 'Partner with hiring teams to plan searches, build candidate pipelines, and deliver a thoughtful interview experience.',
                'requirements' => '5+ years in talent acquisition; experience hiring technical and business roles; confident stakeholder management.',
                'status' => 'open',
            ],
            [
                'code' => 'REQ-2026-005',
                'title' => 'Customer Success Manager',
                'department' => 'Customer Success',
                'employment_type' => 'full_time',
                'location' => 'Surabaya, Indonesia · Hybrid',
                'openings' => 2,
                'description' => 'Help enterprise customers adopt the platform, reach their hiring goals, and share feedback with product teams.',
                'requirements' => '4+ years in customer success or account management; strong presentation, planning, and problem-solving skills.',
                'status' => 'open',
            ],
        ];

        foreach ($positions as $position) {
            JobPosition::firstOrCreate(
                ['code' => $position['code']],
                $position + ['created_by' => $adminId],
            );
        }
    }
}
