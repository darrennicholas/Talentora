<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model; use Illuminate\Database\Eloquent\Factories\HasFactory;
class Application extends Model {
 use HasFactory;
 protected $guarded=[];
 protected $casts=['applied_at'=>'date:Y-m-d','stage_updated_at'=>'immutable_datetime','hired_at'=>'immutable_datetime','rejected_at'=>'immutable_datetime'];
 public function candidate(){return $this->belongsTo(Candidate::class);}
 public function jobPosition(){return $this->belongsTo(JobPosition::class);}
 public function histories(){return $this->hasMany(ApplicationStageHistory::class)->orderByDesc('changed_at')->orderByDesc('id');}
 public function interviews(){return $this->hasMany(Interview::class)->orderByDesc('start_at');}
}
