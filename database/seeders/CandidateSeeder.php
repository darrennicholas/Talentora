<?php

namespace Database\Seeders;

use App\Models\Candidate;
use App\Models\User;
use Illuminate\Database\Seeder;

class CandidateSeeder extends Seeder
{
    public function run(): void
    {
        $adminId = User::where('role', 'admin')->where('is_active', true)->orderBy('id')->value('id');

        if (! $adminId) {
            throw new \RuntimeException('Create an active admin account before running DemoDataSeeder.');
        }

        $candidates = [
            [
                'full_name' => 'Jordan Blake',
                'email' => 'jordan.blake@example.test',
                'phone' => '+62 812-5550-0101',
                'location' => 'South Jakarta, Indonesia',
                'education' => 'B.Sc. Computer Science',
                'years_experience' => 7,
                'skills' => 'PHP, Laravel, React, PostgreSQL, API design',
                'experience_summary' => 'Senior engineer with experience building secure SaaS products and mentoring cross-functional teams.',
            ],
            [
                'full_name' => 'Maya Bennett',
                'email' => 'maya.bennett@example.test',
                'phone' => '+62 812-5550-0102',
                'location' => 'Bandung, Indonesia',
                'education' => 'B.Des. Visual Communication Design',
                'years_experience' => 5,
                'skills' => 'Product design, Figma, prototyping, user research, accessibility',
                'experience_summary' => 'Product designer who turns research findings into clear workflows and consistent design systems.',
            ],
            [
                'full_name' => 'Ethan Cole',
                'email' => 'ethan.cole@example.test',
                'phone' => '+62 812-5550-0103',
                'location' => 'Bandung, Indonesia',
                'education' => 'M.Sc. Applied Statistics',
                'years_experience' => 4,
                'skills' => 'SQL, Python, statistics, data visualization, dbt',
                'experience_summary' => 'Data analyst experienced in building trusted metrics and explaining analytical findings to product teams.',
            ],
            [
                'full_name' => 'Sophie Carter',
                'email' => 'sophie.carter@example.test',
                'phone' => '+62 812-5550-0104',
                'location' => 'Central Jakarta, Indonesia',
                'education' => 'B.A. Human Resources Management',
                'years_experience' => 6,
                'skills' => 'Technical recruiting, sourcing, interview planning, stakeholder management',
                'experience_summary' => 'Talent partner with a record of delivering thoughtful hiring processes for engineering and product teams.',
            ],
            [
                'full_name' => 'Liam Parker',
                'email' => 'liam.parker@example.test',
                'phone' => '+62 812-5550-0105',
                'location' => 'Surabaya, Indonesia',
                'education' => 'B.B.A. Business Administration',
                'years_experience' => 8,
                'skills' => 'Customer success, onboarding, account planning, product adoption',
                'experience_summary' => 'Customer success manager focused on enterprise onboarding, adoption, and long-term customer outcomes.',
            ],
        ];

        foreach ($candidates as $candidate) {
            Candidate::firstOrCreate(
                ['email' => $candidate['email']],
                $candidate + ['created_by' => $adminId],
            );
        }
    }
}
