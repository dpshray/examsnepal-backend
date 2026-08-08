<?php

namespace Database\Seeders;

use App\Models\ExamType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ExamTypeSlugSeeder extends Seeder
{
    /**
     * Backfills exam_types.slug for rows left null by the
     * add_slug_to_exam_types_table migration (schema-only, no data).
     * Safe to re-run: only touches rows where slug is still null.
     */
    public function run(): void
    {
        $updated = 0;

        ExamType::whereNull('slug')->orderBy('id')->each(function (ExamType $type) use (&$updated) {
            $base = Str::slug($type->name);
            $slug = $base;
            $suffix = 2;

            while (ExamType::where('slug', $slug)->where('id', '!=', $type->id)->exists()) {
                $slug = "{$base}-{$suffix}";
                $suffix++;
            }

            $type->update(['slug' => $slug]);
            $updated++;
        });

        $this->command?->info("Backfilled slug for {$updated} exam type(s).");
    }
}
