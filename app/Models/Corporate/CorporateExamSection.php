<?php

namespace App\Models\Corporate;

use App\Traits\SlugTrait;
use Illuminate\Database\Eloquent\Model;

class CorporateExamSection extends Model
{
    use SlugTrait;
    protected $fillable = ['corporate_exam_id', 'title', 'slug','detail','is_published'];
    public function slugSource()
    {
        return 'title';
    }
    public function exam(){
        return $this->belongsTo(CorporateExam::class, 'corporate_exam_id');
    }

    public function scopePublished($query){
        return $query->wheren('is_published',1);
    }

    public function questions()
    {
        return $this->hasMany(CorporateQuestion::class);
    }
    public function getRouteKeyName()
    {
        return 'slug';
    }
}
