<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Participant extends Model
{
    //
    protected $fillable = [
        'name',
        'username',
        'email',
        'phone',
        'password',
        'corporate_id',
    ];
}
