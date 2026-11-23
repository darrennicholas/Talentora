<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ApplicationStageHistory extends Model { public $timestamps=false; protected $guarded=[]; public function changedBy(){return $this->belongsTo(User::class,'changed_by');} }
