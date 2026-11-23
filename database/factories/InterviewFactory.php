<?php
namespace Database\Factories;
use App\Models\Interview;
use Illuminate\Database\Eloquent\Factories\Factory;
class InterviewFactory extends Factory {
 protected $model=Interview::class;
 public function definition():array{$start=now()->addDays(2);return ['application_id'=>\App\Models\Application::factory(),'interviewer_id'=>\App\Models\User::factory(),'start_at'=>$start,'end_at'=>$start->copy()->addHour(),'mode'=>'online','meeting_link'=>'https://meet.example.test/interview','status'=>'scheduled','created_by'=>\App\Models\User::factory()];}
 public function status(string $status):static{return $this->state(fn()=>['status'=>$status,'rating'=>$status==='completed'?4:null,'recommendation'=>$status==='completed'?'recommended':null,'feedback'=>$status==='completed'?'Factory feedback':null]);}
}
