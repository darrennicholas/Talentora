<?php
namespace Database\Factories;
use App\Models\Candidate;
use Illuminate\Database\Eloquent\Factories\Factory;
class CandidateFactory extends Factory {
 protected $model=Candidate::class;
 public function definition():array{return ['full_name'=>fake()->name(),'email'=>fake()->unique()->safeEmail(),'phone'=>fake()->phoneNumber(),'location'=>fake()->city(),'education'=>fake()->randomElement(['Bachelor','Master',null]),'years_experience'=>fake()->numberBetween(0,20),'skills'=>fake()->words(4,true),'created_by'=>\App\Models\User::factory()];}
}
