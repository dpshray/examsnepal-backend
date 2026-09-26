<?php

namespace App\Models\Marketing;

use Illuminate\Database\Eloquent\Model;

class EmailTemplate extends Model
{
    public const CATEGORIES = ['transactional', 'lifecycle', 'promotional'];

    protected $fillable = ['key', 'name', 'category', 'subject', 'preheader', 'html_body', 'text_body', 'cta_label', 'cta_path', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function isTransactional(): bool
    {
        return $this->category === 'transactional';
    }
}
