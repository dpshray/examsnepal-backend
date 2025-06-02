<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subscriber extends Model
{
    protected function casts(){
        return [
            'start_date' => 'date',
            'end_date' => 'date'
        ];
    }

    public function subscriptionType(){
        return $this->belongsTo(SubscriptionType::class);
    }
}
