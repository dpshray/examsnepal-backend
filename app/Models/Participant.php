<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Tymon\JWTAuth\Contracts\JWTSubject;

class Participant extends Authenticatable implements JWTSubject
{
    //
    protected $fillable = [
        'name',
        'username',
        'email',
        'phone',
        'password',
        'corporate_id',
        'raw_password'
    ];
    // Required for JWT
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    // Required for JWT
    public function getJWTCustomClaims()
    {
        return [];
    }
    public function examAttempts()
    {
        return $this->hasMany(ExamAttempt::class, 'participant_id');
    }
}
