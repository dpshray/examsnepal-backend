<?php

namespace Database\Seeders;

use App\Models\ExamCategory;
use App\Models\ExamGuide;
use Illuminate\Database\Seeder;

class ExamGuideSeeder extends Seeder
{
    /**
     * Seeds the /exams hub-and-spoke content (categories + guides).
     * Data was exported from the reviewed/published dev database so this
     * seeder is the source of truth for deploying that content to a new
     * environment. Safe to re-run: everything is keyed by slug.
     */
    public function run(): void
    {
        $data = require __DIR__ . '/data/exam_guides_data.php';

        $categoryIds = [];
        foreach ($data['categories'] as $category) {
            $record = ExamCategory::updateOrCreate(
                ['slug' => $category['slug']],
                $category
            );
            $categoryIds[$category['slug']] = $record->id;
        }

        foreach ($data['guides'] as $guide) {
            $categorySlug = $guide['category_slug'];
            unset($guide['category_slug']);
            $guide['exam_category_id'] = $categoryIds[$categorySlug];

            ExamGuide::updateOrCreate(
                ['exam_category_id' => $guide['exam_category_id'], 'slug' => $guide['slug']],
                $guide
            );
        }

        $this->command?->info('Seeded ' . count($data['categories']) . ' exam categories and ' . count($data['guides']) . ' exam guides.');
    }
}
