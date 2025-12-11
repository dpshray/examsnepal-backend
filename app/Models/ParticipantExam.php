<?php

namespace App\Models;

use App\Models\Corporate\CorporateExam;
use Illuminate\Database\Eloquent\Model;

class ParticipantExam extends Model
{
    //
    protected $fillable = [
        'corporate_exam_id',
        'participant_id'
    ];
    function exam()
    {
        return $this->belongsTo(CorporateExam::class);
    }
    function participants()
    {
        return $this->belongsTo(Participant::class);
    }
}
