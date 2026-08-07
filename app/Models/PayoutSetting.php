<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayoutSetting extends Model
{
    protected $fillable = [
        'pool_percentage',
    ];

    protected function casts(): array
    {
        return [
            'pool_percentage' => 'decimal:2',
        ];
    }

    public static function poolPercentage(): float
    {
        return (float) (static::query()->value('pool_percentage') ?? 80.00);
    }
}
