<?php

namespace App\Models\Corporate;

use Illuminate\Database\Eloquent\Model;

class CorporateQuestionOption extends Model
{
    //
    protected $fillable = ['corporate_question_id', 'option', 'value'];
    protected $casts = [
        'value'=>'integer'
    ];
}
