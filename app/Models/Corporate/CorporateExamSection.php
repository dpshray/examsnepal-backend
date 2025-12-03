<?php

namespace App\Models\Corporate;

use Illuminate\Database\Eloquent\Model;

class CorporateExamSection extends Model
{
    protected $fillable = ['corporate_exam_id', 'title', 'detail','is_published'];

    public function exam(){
        return $this->belongsTo(CorporateExam::class, 'corporate_exam_id');
    }

    public function scopePublished($query){
        return $query->wheren('is_published',1);
    }
}
