<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ExamType extends Model
{
    //
    use HasFactory;

    protected $fillable = ['name', 'is_active'];

    protected function casts(): array
    {
        return [
            'id' => 'integer'
        ];
    }

    public function exams()
    {
        return $this->hasMany(Exam::class); // One Exam Type has many Exams
    }

}
