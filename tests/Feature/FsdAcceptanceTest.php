<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Candidate;
use App\Models\Interview;
use App\Models\JobPosition;
use App\Models\User;
use App\Services\ActiveAdminGuard;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class FsdAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_position_rules_and_case_insensitive_code_are_enforced(): void
    {
        $admin = $this->admin();
        $job = $this->job($admin, ['code' => 'POS-DEV-01']);

        $this->actingAs($admin)->postJson('/api/job-positions', $this->jobPayload('pos-dev-01'))
            ->assertUnprocessable()->assertJsonValidationErrors('code');

        $missingLocation = $this->jobPayload('POS-DEV-02');
        unset($missingLocation['location']);
        $this->postJson('/api/job-positions', $missingLocation)
            ->assertUnprocessable()->assertJsonValidationErrors('location');

        $candidate = $this->candidate($admin);
        $this->application($admin, $candidate, $job);
        $this->getJson('/api/job-positions?status=open&per_page=100')->assertOk()
            ->assertJsonPath('data.0.applicants_count', 1);
        $this->deleteJson("/api/job-positions/{$job->id}")->assertStatus(409);

        $closed = $this->job($admin, ['status' => 'closed']);
        $other = $this->candidate($admin);
        $this->postJson('/api/applications', [
            'candidate_id' => $other->id,
            'job_position_id' => $closed->id,
            'source' => 'manual',
            'applied_at' => now('Asia/Jakarta')->subDay()->toDateString(),
        ])->assertUnprocessable();
    }

    public function test_candidate_email_cv_privacy_replacement_and_delete_rules(): void
    {
        Storage::fake('local');
        $admin = $this->admin();
        $candidate = $this->actingAs($admin)->postJson('/api/candidates', [
            'full_name' => 'Taylor Jordan',
            'email' => 'TAYLOR@example.com',
            'phone' => '+1 555 0100',
        ])->assertCreated()->assertJsonPath('data.email', 'taylor@example.com')
            ->assertJsonMissingPath('data.cv_path')->json('data');

        $first = UploadedFile::fake()->create('resume.pdf', 5120, 'application/pdf');
        $uploaded = $this->postJson("/api/candidates/{$candidate['id']}/cv", ['cv' => $first])
            ->assertOk()->assertJsonPath('data.has_cv', true)->assertJsonMissingPath('data.cv_path');
        $stored = Candidate::findOrFail($candidate['id']);
        $oldPath = $stored->cv_path;
        $this->assertNotEmpty($oldPath);
        Storage::disk('local')->assertExists($oldPath);
        $this->assertStringNotContainsString('cv_path', json_encode($uploaded->json('data')));

        foreach ([
            ['doc', 'application/msword'],
            ['docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        ] as [$extension, $mime]) {
            $replacement = UploadedFile::fake()->create("updated-resume.{$extension}", 20, $mime);
            $this->postJson("/api/candidates/{$candidate['id']}/cv", ['cv' => $replacement])->assertOk();
            $newPath = $stored->fresh()->cv_path;
            $this->assertNotSame($oldPath, $newPath);
            Storage::disk('local')->assertMissing($oldPath);
            Storage::disk('local')->assertExists($newPath);
            $oldPath = $newPath;
        }

        $oversized = UploadedFile::fake()->create('too-large.pdf', 5121, 'application/pdf');
        $this->postJson("/api/candidates/{$candidate['id']}/cv", ['cv' => $oversized])->assertUnprocessable();

        $this->deleteJson("/api/candidates/{$candidate['id']}/cv")->assertNoContent();
        Storage::disk('local')->assertMissing($oldPath);
        $this->assertNull($stored->fresh()->cv_path);

        $candidateB = $this->candidate($admin);
        $this->actingAs($admin)->postJson('/api/candidates', [
            'full_name' => 'Duplicate Email', 'email' => 'taylor@example.com', 'phone' => '+1 555 0101',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->application($admin, Candidate::findOrFail($candidateB->id), $this->job($admin));
        $this->deleteJson("/api/candidates/{$candidateB->id}")->assertStatus(409);
    }

    public function test_candidate_cv_download_requires_an_authenticated_session(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $candidate = Candidate::factory()->create(['created_by' => $admin->id, 'cv_path' => 'cvs/private.pdf']);

        $this->getJson("/api/candidates/{$candidate->id}/cv")->assertUnauthorized();
    }

    public function test_each_module_deep_link_serves_the_spa_shell(): void
    {
        foreach ([
            '/dashboard', '/job-positions', '/candidates', '/applications',
            '/hiring-pipeline', '/interview-schedule', '/reports-analytics', '/settings',
        ] as $path) {
            $this->get($path)->assertOk()->assertSee('id="app"', false);
        }
    }

    public function test_application_uniqueness_history_and_stage_transition_guards(): void
    {
        $admin = $this->admin();
        $firstPosition = $this->job($admin);
        $secondPosition = $this->job($admin);
        $candidate = $this->candidate($admin);
        $payload = [
            'candidate_id' => $candidate->id,
            'job_position_id' => $firstPosition->id,
            'source' => 'manual',
            'applied_at' => now('Asia/Jakarta')->subDay()->toDateString(),
        ];

        $first = $this->actingAs($admin)->postJson('/api/applications', $payload)
            ->assertCreated()->assertJsonPath('data.stage', 'applied')->json('data');
        $this->assertCount(1, $first['histories']);
        $this->assertNull($first['histories'][0]['from_stage']);
        $this->assertSame('applied', $first['histories'][0]['to_stage']);
        $this->postJson('/api/applications', $payload)->assertStatus(409);

        $second = $this->postJson('/api/applications', [...$payload, 'job_position_id' => $secondPosition->id])
            ->assertCreated()->json('data');
        $this->assertNotSame($first['id'], $second['id']);
        $this->assertSame(2, Application::where('candidate_id', $candidate->id)->count());

        $this->patchJson("/api/applications/{$first['id']}/stage", ['from_stage' => 'applied', 'to_stage' => 'screening'])
            ->assertOk()->assertJsonPath('data.stage', 'screening');
        $this->patchJson("/api/applications/{$first['id']}/stage", ['from_stage' => 'applied', 'to_stage' => 'screening'])
            ->assertStatus(409)->assertJsonPath('data.current_stage', 'screening');
        $this->patchJson("/api/applications/{$first['id']}/stage", ['from_stage' => 'screening', 'to_stage' => 'interview'])
            ->assertUnprocessable();
        $this->patchJson("/api/applications/{$second['id']}/stage", ['from_stage' => 'applied', 'to_stage' => 'hired'])
            ->assertUnprocessable();
        $this->patchJson("/api/applications/{$second['id']}/stage", ['from_stage' => 'applied', 'to_stage' => 'rejected'])
            ->assertUnprocessable();
    }

    public function test_interview_schedule_evaluation_offer_hire_and_rejection_rules(): void
    {
        $admin = $this->admin();
        $position = $this->job($admin);
        $application = $this->application($admin, $this->candidate($admin), $position, 'screening');
        $start = now()->addDays(2)->startOfHour();
        $interviewPayload = [
            'application_id' => $application->id,
            'interviewer_id' => $admin->id,
            'start_at' => $start->toIso8601String(),
            'end_at' => $start->copy()->addHour()->toIso8601String(),
            'mode' => 'online',
            'meeting_link' => 'https://meet.example.test/round-1',
        ];

        $interview = $this->actingAs($admin)->postJson('/api/interviews', $interviewPayload)
            ->assertCreated()->assertJsonPath('data.status', 'scheduled')->json('data');
        $this->assertDatabaseHas('applications', ['id' => $application->id, 'stage' => 'interview']);
        $this->assertDatabaseHas('application_stage_histories', [
            'application_id' => $application->id, 'from_stage' => 'screening', 'to_stage' => 'interview',
        ]);
        $this->postJson('/api/interviews', $interviewPayload)->assertStatus(409);

        $another = $this->application($admin, $this->candidate($admin), $position, 'screening');
        $overlap = [...$interviewPayload, 'application_id' => $another->id];
        $this->postJson('/api/interviews', $overlap)->assertStatus(409);

        $rescheduledStart = $start->copy()->addHours(3);
        $rescheduledEnd = $rescheduledStart->copy()->addHour();
        $this->patchJson("/api/interviews/{$interview['id']}", [
            'interviewer_id' => $admin->id,
            'start_at' => $rescheduledStart->toIso8601String(),
            'end_at' => $rescheduledEnd->toIso8601String(),
            'mode' => 'online',
            'meeting_link' => 'https://meet.example.test/round-1-updated',
        ])->assertOk();
        $this->patchJson("/api/interviews/{$interview['id']}/complete", [])->assertUnprocessable();
        $this->patchJson("/api/interviews/{$interview['id']}/complete", [
            'rating' => 5, 'recommendation' => 'recommended', 'feedback' => 'Strong technical interview.',
        ])->assertUnprocessable();

        $this->travelTo($rescheduledEnd->copy()->addMinute());
        $this->patchJson("/api/interviews/{$interview['id']}/complete", [
            'rating' => 5, 'recommendation' => 'recommended', 'feedback' => 'Strong technical interview.',
        ])->assertOk()->assertJsonPath('data.status', 'completed');
        $this->patchJson("/api/applications/{$application->id}/stage", [
            'from_stage' => 'interview', 'to_stage' => 'offered',
        ])->assertOk()->assertJsonPath('data.stage', 'offered');
        $this->patchJson("/api/applications/{$application->id}/stage", [
            'from_stage' => 'offered', 'to_stage' => 'hired',
        ])->assertOk()->assertJsonPath('data.stage', 'hired');
        $this->assertNotNull(Application::findOrFail($application->id)->hired_at);
        $this->patchJson("/api/applications/{$application->id}/stage", [
            'from_stage' => 'hired', 'to_stage' => 'rejected', 'note' => 'Stale update',
        ])->assertUnprocessable();

        $scheduledApplication = $this->application($admin, $this->candidate($admin), $position, 'screening');
        $scheduledStart = now()->addDays(3)->startOfHour();
        $scheduled = $this->postJson('/api/interviews', [
            ...$interviewPayload,
            'application_id' => $scheduledApplication->id,
            'start_at' => $scheduledStart->toIso8601String(),
            'end_at' => $scheduledStart->copy()->addHour()->toIso8601String(),
        ])->assertCreated()->json('data');
        $this->patchJson("/api/applications/{$scheduledApplication->id}/stage", [
            'from_stage' => 'interview', 'to_stage' => 'rejected', 'note' => 'Role requirements changed.',
        ])->assertOk()->assertJsonPath('data.stage', 'rejected');
        $this->assertNotNull(Application::findOrFail($scheduledApplication->id)->rejected_at);
        $this->assertDatabaseHas('interviews', ['id' => $scheduled['id'], 'status' => 'cancelled']);
    }

    public function test_dashboard_and_reports_match_fixture_totals_and_return_empty_values(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);
        $this->getJson('/api/reports/summary')->assertOk()->assertJsonPath('data.total_applications', 0);
        $this->getJson('/api/reports/metrics')->assertOk()->assertJsonPath('data.hiring_rate_percent', 0)
            ->assertJsonPath('data.average_time_to_hire_days', null);
        $this->getJson('/api/reports/stages')->assertOk()->assertJsonCount(6, 'data');

        $this->travelTo(Carbon::parse('2026-11-22 12:00:00', 'Asia/Jakarta'));
        $position = $this->job($admin);
        $hired = $this->application($admin, $this->candidate($admin), $position, 'hired', '2026-11-12');
        $hired->update(['hired_at' => Carbon::parse('2026-11-22 00:00:00', 'Asia/Jakarta')->utc()]);
        $this->application($admin, $this->candidate($admin), $position, 'rejected', '2026-11-20');
        $this->application($admin, $this->candidate($admin), $position, 'applied', '2026-11-21');
        $interviewApplication = $this->application($admin, $this->candidate($admin), $position, 'interview', '2026-11-22');
        $interviewStart = now()->addDays(2)->startOfHour();
        Interview::factory()->create([
            'application_id' => $interviewApplication->id,
            'interviewer_id' => $admin->id,
            'created_by' => $admin->id,
            'start_at' => $interviewStart,
            'end_at' => $interviewStart->copy()->addHour(),
        ]);

        $this->getJson('/api/dashboard?start_date=2026-11-01&end_date=2026-11-22')
            ->assertOk()->assertJsonPath('data.total_candidates', 4)
            ->assertJsonPath('data.active_job_positions', 1)
            ->assertJsonPath('data.total_applications', 4)
            ->assertJsonPath('data.upcoming_scheduled_interviews', 1)
            ->assertJsonPath('data.hired_applications', 1)
            ->assertJsonPath('data.rejected_applications', 1);
        $this->getJson('/api/reports/stages?start_date=2026-11-01&end_date=2026-11-22')
            ->assertOk()->assertJsonPath('data.0.total', 1)
            ->assertJsonPath('data.2.total', 1)->assertJsonPath('data.4.total', 1)->assertJsonPath('data.5.total', 1);
        $this->getJson('/api/reports/trends?start_date=2026-11-01&end_date=2026-11-22&group_by=daily')
            ->assertOk()->assertJsonCount(4, 'data');
        $this->getJson('/api/reports/trends?start_date=2026-11-01&end_date=2026-11-22&group_by=weekly')
            ->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('data.0.total', 1)->assertJsonPath('data.1.total', 3);
        $this->getJson('/api/reports/trends?start_date=2026-11-01&end_date=2026-11-22&group_by=monthly')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.period', '2026-11')->assertJsonPath('data.0.total', 4);
        $this->getJson('/api/reports/metrics?start_date=2026-11-01&end_date=2026-11-22')
            ->assertOk()->assertJsonPath('data.hiring_rate_percent', 25)
            ->assertJsonPath('data.average_time_to_hire_days', 10);
        $this->getJson('/api/reports/positions?start_date=2026-11-01&end_date=2026-11-22')
            ->assertOk()->assertJsonPath('data.0.total', 4)->assertJsonPath('data.0.hired', 1)
            ->assertJsonPath('data.0.rejected', 1)->assertJsonPath('meta.total', 1);
    }

    public function test_position_report_query_count_does_not_grow_with_positions(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);
        foreach (range(1, 12) as $index) {
            $this->job($admin, ['title' => "Position {$index}", 'code' => sprintf('POS-%03d', $index)]);
        }

        $applicationQueries = 0;
        DB::listen(function ($query) use (&$applicationQueries) {
            if (str_contains(strtolower($query->sql), 'from `applications`')) {
                $applicationQueries++;
            }
        });

        $this->getJson('/api/reports/positions?per_page=100')->assertOk()->assertJsonCount(12, 'data');
        $this->assertLessThanOrEqual(3, $applicationQueries, 'Position aggregates should be queried in one grouped query, independent of row count.');
    }

    public function test_admin_only_user_settings_and_inactive_session_rules(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);
        $created = $this->postJson('/api/users', [
            'name' => 'Second Administrator', 'email' => 'SECOND@EXAMPLE.COM', 'password' => 'developer-pass',
        ])->assertCreated()->assertJsonPath('data.role', 'admin')->json('data');
        $this->postJson('/api/users', [
            'name' => 'HR Staff', 'email' => 'hr@example.com', 'password' => 'developer-pass', 'role' => 'hr_staff',
        ])->assertUnprocessable();
        $this->patchJson("/api/users/{$created['id']}", ['role' => 'hr_staff'])->assertUnprocessable();
        $this->patchJson("/api/users/{$created['id']}/status", ['is_active' => false])->assertOk();
        $this->patchJson("/api/users/{$admin->id}/status", ['is_active' => false])->assertUnprocessable();

        $this->patchJson('/api/app-settings', ['company_name' => 'Talentora Labs', 'timezone' => 'Asia/Makassar'])
            ->assertOk()->assertJsonPath('data.timezone', 'Asia/Makassar')
            ->assertJsonPath('data.company_name', 'Talentora Labs');
        $this->getJson('/api/me')->assertOk()->assertJsonPath('data.app_timezone', 'Asia/Makassar')
            ->assertJsonMissingPath('data.password')->assertJsonMissingPath('data.remember_token');

        $inactive = User::factory()->create(['role' => 'admin', 'is_active' => false]);
        $this->withHeader('Origin', 'http://localhost')->actingAs($inactive)->getJson('/api/me')->assertUnauthorized();

        $legacyRole = User::factory()->create(['role' => 'hr_staff', 'is_active' => true]);
        $this->actingAs($legacyRole)->getJson('/api/users')->assertUnauthorized();
    }

    public function test_profile_and_password_updates_are_validated_and_persisted(): void
    {
        $admin = $this->admin();
        $this->patchJson('/api/me', ['name' => 'Updated Administrator', 'email' => 'UPDATED@EXAMPLE.COM'])
            ->assertOk()->assertJsonPath('data.name', 'Updated Administrator')->assertJsonPath('data.email', 'updated@example.com');

        $this->patchJson('/api/me/password', [
            'current_password' => 'password',
            'new_password' => 'secure-new-password',
            'new_password_confirmation' => 'secure-new-password',
        ])->assertOk();
        $this->assertTrue(Hash::check('secure-new-password', $admin->fresh()->password));

        $this->patchJson('/api/me/password', [
            'current_password' => 'wrong-password',
            'new_password' => 'another-secure-password',
            'new_password_confirmation' => 'another-secure-password',
        ])->assertUnprocessable()->assertJsonValidationErrors('current_password');
    }

    public function test_active_admin_cannot_deactivate_their_own_account(): void
    {
        $admin = $this->admin();

        $this->patchJson("/api/users/{$admin->id}/status", ['is_active' => false])
            ->assertUnprocessable()->assertJsonPath('message', 'Cannot deactivate yourself.');
        $this->assertDatabaseHas('users', ['id' => $admin->id, 'is_active' => true]);
        $this->assertSame(1, User::where('role', 'admin')->where('is_active', true)->count());
    }

    public function test_last_active_admin_invariant_is_enforced(): void
    {
        $admin = $this->admin();

        try {
            app(ActiveAdminGuard::class)->assertAnotherActiveAdminExists($admin);
            $this->fail('The last active admin must not be deactivated.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('is_active', $exception->errors());
        }

        $this->assertSame(1, User::where('role', 'admin')->where('is_active', true)->count());
    }

    public function test_stale_concurrent_stage_transition_returns_conflict_and_keeps_one_history(): void
    {
        $admin = $this->admin();
        $application = $this->application($admin, $this->candidate($admin), $this->job($admin));

        $this->patchJson("/api/applications/{$application->id}/stage", [
            'from_stage' => 'applied', 'to_stage' => 'screening',
        ])->assertOk()->assertJsonPath('data.stage', 'screening');

        $this->patchJson("/api/applications/{$application->id}/stage", [
            'from_stage' => 'applied', 'to_stage' => 'rejected', 'note' => 'A stale second request.',
        ])->assertStatus(409)->assertJsonPath('code', 'STAGE_CONFLICT');
        $this->assertDatabaseCount('application_stage_histories', 1);
        $this->assertDatabaseHas('application_stage_histories', [
            'application_id' => $application->id, 'from_stage' => 'applied', 'to_stage' => 'screening',
        ]);
        $this->assertDatabaseHas('applications', ['id' => $application->id, 'stage' => 'screening']);
    }

    private function admin(): User
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->actingAs($admin);

        return $admin;
    }

    private function job(User $admin, array $attributes = []): JobPosition
    {
        return JobPosition::factory()->create($attributes + ['created_by' => $admin->id]);
    }

    private function candidate(User $admin, array $attributes = []): Candidate
    {
        return Candidate::factory()->create($attributes + ['created_by' => $admin->id]);
    }

    private function application(User $admin, Candidate $candidate, JobPosition $position, string $stage = 'applied', ?string $appliedAt = null): Application
    {
        return Application::factory()->create([
            'candidate_id' => $candidate->id,
            'job_position_id' => $position->id,
            'created_by' => $admin->id,
            'stage' => $stage,
            'applied_at' => $appliedAt ?? now('Asia/Jakarta')->subDay()->toDateString(),
            'stage_updated_at' => now(),
            'hired_at' => $stage === 'hired' ? now() : null,
            'rejected_at' => $stage === 'rejected' ? now() : null,
            'rejection_reason' => $stage === 'rejected' ? 'Factory rejection' : null,
        ]);
    }

    private function jobPayload(string $code): array
    {
        return [
            'code' => $code,
            'title' => 'Backend Engineer',
            'department' => 'Engineering',
            'employment_type' => 'full_time',
            'location' => 'Jakarta',
            'openings' => 2,
            'description' => 'Build reliable services.',
            'requirements' => 'PHP and Laravel experience.',
            'status' => 'open',
        ];
    }
}
