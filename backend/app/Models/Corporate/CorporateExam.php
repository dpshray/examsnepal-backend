<?php

namespace App\Models\Corporate;

use App\Models\Traits\Uuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CorporateExam extends Model
{    
    use Uuid, SoftDeletes; // use the trait
    protected $fillable = ['uuid','corporate_id','title','exam_date','start_time','end_time','about','rules','is_published'];
    
    protected $dates = ['deleted_at']; // mark this column as a date

    public static function boot()
    {
        parent::boot();
        /* static::creating(function ($product) {
            $product->slug = substr(md5(uniqid(rand(), true)), 0, 10);
            $product->added_by = auth()->id();
        }); */
    }

    public function corporate(){
        return $this->belongsTo(User::class,'corporate_id');
    }
}
