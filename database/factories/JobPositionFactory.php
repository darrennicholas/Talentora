<?php
namespace Database\Factories;
use App\Models\JobPosition;
use Illuminate\Database\Eloquent\Factories\Factory;
class JobPositionFactory extends Factory {
 protected $model=JobPosition::class;
 public function definition():array{return ['code'=>fake()->unique()->bothify('POS-??-###'),'title'=>fake()->jobTitle(),'department'=>fake()->word(),'employment_type'=>'full_time','location'=>'Jakarta','openings'=>1,'description'=>fake()->paragraph(),'requirements'=>fake()->paragraph(),'status'=>'open','created_by'=>\App\Models\User::factory()];}
}
