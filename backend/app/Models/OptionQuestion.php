<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OptionQuestion extends Model
{
    public $timestamps = false;
    protected $fillable = [
        'option',
        'value'
    ];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'question_id' => 'integer',
            'value' => 'integer',
        ];
    }
}
