<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentPool extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'student_id',
        'played_at',
        'token'
    ];
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'played_at' => 'date'
        ];
    }

    public function pools(){
        return $this->hasMany(Pool::class,'student_pool_id');
    }
}
