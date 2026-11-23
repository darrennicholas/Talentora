<?php
namespace Database\Factories;
use App\Models\Application;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
class ApplicationFactory extends Factory {
 protected $model=Application::class;
 public function definition():array{return ['application_code'=>'APP-'.Str::upper((string)Str::ulid()),'candidate_id'=>\App\Models\Candidate::factory(),'job_position_id'=>\App\Models\JobPosition::factory(),'source'=>'manual','stage'=>'applied','applied_at'=>now()->toDateString(),'stage_updated_at'=>now(),'created_by'=>\App\Models\User::factory()];}
 public function stage(string $stage):static{return $this->state(fn()=>['stage'=>$stage,'hired_at'=>$stage==='hired'?now():null,'rejected_at'=>$stage==='rejected'?now():null,'rejection_reason'=>$stage==='rejected'?'Factory rejection':null]);}
}
