<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Organization extends Model
{
    //
    use HasFactory;
    
    protected $fillable = [
        'name', 'email', 'phone', 'address', 'is_active'
    ];

    public function exams()
    {
        return $this->hasMany(Exam::class); // One Organization has many Exams
    }
}
