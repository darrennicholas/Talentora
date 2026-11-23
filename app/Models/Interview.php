<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model; use Illuminate\Database\Eloquent\Factories\HasFactory;
class Interview extends Model {
 use HasFactory;
 protected $guarded=[];
 protected $casts=['start_at'=>'immutable_datetime','end_at'=>'immutable_datetime'];
 public function application(){return $this->belongsTo(Application::class);}
 public function interviewer(){return $this->belongsTo(User::class,'interviewer_id');}
}
