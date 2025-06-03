<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubscriptionType extends Model
{
    protected function casts()
    {
        return [
            'id' => 'integer',
            'duration' => 'integer',
            'price' => 'decimal:2'
        ];
    }
}
