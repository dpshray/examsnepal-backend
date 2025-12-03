<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subscriber extends Model
{
    public $timestamps = false;
    protected $fillable =
    [
        'student_profile_id',
        'subscription_type_id',
        'status',
        'paid',
        'price',
        'start_date',
        'end_date' ,
        'transaction_id',
        'subscribed_at',
        'data',
        'remark',
        'paid_in_paisa',
        'promo_code_id',
        'payment_status'
    ];

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
