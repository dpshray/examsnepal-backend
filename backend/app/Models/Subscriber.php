<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subscriber extends Model
{
    public $timestamps = false;
    protected $fillable = ['status', 'payment_status'];
    
    protected function casts(){
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'data' => 'array'
        ];
    }

    public function subscriptionType(){
        return $this->belongsTo(SubscriptionType::class);
    }
}
