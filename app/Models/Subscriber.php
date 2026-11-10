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

    /**
     * Unguessable, unique id shared by eSewa, ConnectIPS and admin-added subscriptions.
     * Public payment callbacks look rows up by this id, so it must not be enumerable.
     * 19 characters: within ConnectIPS's 20-character TXNID limit.
     */
    public static function newTransactionId(): string
    {
        do {
            $id = 'TXN' . now()->format('ymd') . strtoupper(\Illuminate\Support\Str::random(10));
        } while (static::where('transaction_id', $id)->exists());

        return $id;
    }

    public function subscriptionType(){
        return $this->belongsTo(SubscriptionType::class);
    }
}
