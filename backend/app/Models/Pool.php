<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pool extends Model
{
    public $timestamps = false;
    protected $fillable = [
        'question_id'
    ];
}
