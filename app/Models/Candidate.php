<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model; use Illuminate\Database\Eloquent\Factories\HasFactory;
class Candidate extends Model {
 use HasFactory;
 protected $guarded=[];
 protected $hidden=['cv_path'];
 protected $appends=['has_cv'];
 public function getHasCvAttribute(){return filled($this->cv_path);}
 public function applications(){return $this->hasMany(Application::class);}
}
