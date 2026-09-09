<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PromoCode extends Model
{
    //
    protected $fillable = [
        'code',
        'discount_percent',
        'detail',
        'status'
    ];
    protected $casts = [
        'status' => 'boolean',
    ];
}
