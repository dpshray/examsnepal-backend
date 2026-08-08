<?php

namespace App\Models;

use App\Traits\SlugTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ExamType extends Model
{
    //
    use HasFactory, SlugTrait;

    protected $fillable = ['name', 'is_active'];

    public function slugSource()
    {
        return 'name';
    }

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

    function scopeActive($qry) {
        return $qry->where('is_active',true);
    }

}
