<?php

namespace App\Models\Corporate;

use App\Models\ExamAttempt;
use App\Models\Participant;
use App\Models\ParticipantExam;
use App\Models\Traits\Uuid;
use App\Models\User;
use App\Traits\SlugTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CorporateExam extends Model
{
    use  SoftDeletes, SlugTrait; // use the trait
    protected $fillable =
    [
        'corporate_id',
        'title',
        'slug',
        'exam_date',
        'start_time',
        'end_time',
        'description',
        'instructions',
        'is_published',
        'duration',
        'is_shuffled_question',
        'is_shuffled_option',
        'limit_attempts',
        'exam_type',
    ];

    public function slugSource()
    {
        return 'title';
    }

    protected $dates = ['deleted_at']; // mark this column as a date

    public static function boot()
    {
        parent::boot();
        /* static::creating(function ($product) {
            $product->slug = substr(md5(uniqid(rand(), true)), 0, 10);
            $product->added_by = auth()->id();
        }); */
    }

    public function corporate()
    {
        return $this->belongsTo(User::class, 'corporate_id');
    }

    public function sections()
    {
        return $this->hasMany(CorporateExamSection::class);
    }

    public function questions()
    {
        return $this->hasMany(CorporateQuestion::class);
    }
    public function participants()
    {
        return $this->belongsToMany(
            Participant::class,
            'participant_exams',
            'corporate_exam_id',
            'participant_id'
        )
            ->withPivot('id');
    }
    public function getRouteKeyName()
    {
        return 'slug';
    }
    public function attempts()
    {
        return $this->hasMany(ExamAttempt::class, 'corporate_exam_id');
    }
}
