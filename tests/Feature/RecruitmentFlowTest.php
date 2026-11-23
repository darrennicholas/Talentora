<?php

namespace Tests\Feature;

use App\Models\{Application,Candidate,JobPosition,User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecruitmentFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_candidate_application_and_first_interview_share_one_workflow(): void
    {
        $admin=User::factory()->create(['role'=>'admin','is_active'=>true]);
        $job=$this->actingAs($admin)->postJson('/api/job-positions',['code'=>'pos-eng-01','title'=>'Backend Engineer','department'=>'Engineering','employment_type'=>'full_time','location'=>'Jakarta','openings'=>1,'description'=>'Build APIs','requirements'=>'Laravel','status'=>'open'])->assertCreated()->json('data');
        $candidate=$this->postJson('/api/candidates',['full_name'=>'Ayu Putri','email'=>'AYU@example.com','phone'=>'08123456789'])->assertCreated()->json('data');
        $this->assertSame('ayu@example.com',$candidate['email']);
        $application=$this->postJson('/api/applications',['candidate_id'=>$candidate['id'],'job_position_id'=>$job['id'],'source'=>'manual','applied_at'=>now('Asia/Jakarta')->toDateString()])->assertCreated()->json('data');
        $this->assertSame('applied',$application['stage']);
        $this->assertCount(1,$application['histories']);
        $this->patchJson("/api/applications/{$application['id']}/stage",['from_stage'=>'applied','to_stage'=>'screening'])->assertOk();
        $start=now()->addDays(2)->startOfHour();
        $interview=$this->postJson('/api/interviews',['application_id'=>$application['id'],'interviewer_id'=>$admin->id,'start_at'=>$start->toIso8601String(),'end_at'=>$start->copy()->addHour()->toIso8601String(),'mode'=>'online','meeting_link'=>'https://meet.example.test/1'])->assertCreated()->json('data');
        $this->assertSame('scheduled',$interview['status']);
        $this->assertDatabaseHas('applications',['id'=>$application['id'],'stage'=>'interview']);
        $this->assertDatabaseHas('application_stage_histories',['application_id'=>$application['id'],'from_stage'=>'screening','to_stage'=>'interview']);
        $this->postJson('/api/interviews',['application_id'=>$application['id'],'interviewer_id'=>$admin->id,'start_at'=>$start->copy()->addHours(2)->toIso8601String(),'end_at'=>$start->copy()->addHours(3)->toIso8601String(),'mode'=>'online','meeting_link'=>'https://meet.example.test/2'])->assertStatus(409);
        $this->postJson('/api/applications',['candidate_id'=>$candidate['id'],'job_position_id'=>$job['id'],'source'=>'manual','applied_at'=>now('Asia/Jakarta')->toDateString()])->assertStatus(409);
        $this->getJson('/api/pipeline')->assertOk()->assertJsonPath('data.interview.total',1)->assertJsonPath('data.hired.total',0);
        $this->getJson('/api/dashboard')->assertOk()->assertJsonPath('data.total_candidates',1)->assertJsonPath('data.total_applications',1);
        $this->getJson('/api/reports/stages')->assertOk()->assertJsonCount(6,'data');
        $this->getJson('/api/users')->assertOk();
        $this->getJson('/api/app-settings')->assertOk();
    }
}
