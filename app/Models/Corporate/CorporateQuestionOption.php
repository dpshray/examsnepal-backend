<?php

namespace App\Models\Corporate;

use App\Support\MojibakeFixer;
use Illuminate\Database\Eloquent\Model;

class CorporateQuestionOption extends Model
{
    //
    protected $fillable = ['corporate_question_id', 'option', 'value'];
    protected $casts = [
        'value'=>'integer'
    ];

    public function getOptionAttribute(?string $value): ?string
    {
        return MojibakeFixer::fix($value);
    }
}
