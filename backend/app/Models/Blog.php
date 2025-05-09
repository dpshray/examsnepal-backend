<?php

namespace App\Models;

use Database\Factories\BlogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\Factory;

class Blog extends Model
{
    use HasFactory;
    protected $perPage = 12;

    public function author() {
        return $this->belongsTo(User::class,'user_id');
    }
}
