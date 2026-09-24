<?php

namespace Database\Seeders;

use App\Models\NoticeSource;
use Illuminate\Database\Seeder;

class NoticeSourceSeeder extends Seeder
{
    public function run(): void
    {
        $sources = require database_path('seeders/data/notice_sources.php');

        foreach ($sources as $code => $attributes) {
            // Match on list_url so admin edits to other fields survive a
            // re-seed only when the URL itself changed.
            NoticeSource::updateOrCreate(
                ['list_url' => $attributes['list_url']],
                $attributes + ['notes' => $attributes['notes'] ?? "seed:{$code}"],
            );
        }
    }
}
