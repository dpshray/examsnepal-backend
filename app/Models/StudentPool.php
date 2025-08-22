<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentPool extends Model
{
    public $timestamps = false;
    protected $perPage = 10;

    protected $fillable = [
        'student_id',
        'played_at',
        'token'
    ];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'student_id' => 'integer',
            'strike' => 'integer',
            'played_at' => 'date'
        ];
    }

    public function pools(){
        return $this->hasMany(Pool::class,'student_pool_id');
    }

    public function student(){
        return $this->belongsTo(StudentProfile::class,'student_id');
    }
}
