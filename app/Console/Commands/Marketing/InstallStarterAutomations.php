<?php

namespace App\Console\Commands\Marketing;

use App\Models\Marketing\Automation;
use App\Models\Marketing\EmailTemplate;
use App\Services\Marketing\StarterCatalog;
use Illuminate\Console\Command;

/**
 * Adds the starter emails and automations (StarterCatalog). New automations
 * are always created switched OFF. Existing ones are left alone unless
 * --force, which refreshes copy/settings but never changes on/off state.
 */
class InstallStarterAutomations extends Command
{
    protected $signature = 'marketing:install-starter {--force : Overwrite existing templates/automations with the catalogue version}';

    protected $description = 'Install the starter lifecycle email templates and automations (all switched off)';

    public function handle(): int
    {
        $counts = ['templates created' => 0, 'templates updated' => 0, 'automations created' => 0, 'automations updated' => 0, 'skipped (already exist)' => 0];

        foreach (StarterCatalog::templates() as $key => $data) {
            $data += ['category' => 'lifecycle', 'is_active' => true];
            $existing = EmailTemplate::where('key', $key)->first();
            if (!$existing) {
                EmailTemplate::create(['key' => $key] + $data);
                $counts['templates created']++;
            } elseif ($this->option('force')) {
                $existing->update($data);
                $counts['templates updated']++;
            } else {
                $counts['skipped (already exist)']++;
            }
        }

        foreach (StarterCatalog::automations() as $key => $data) {
            $existing = Automation::where('key', $key)->first();
            if (!$existing) {
                Automation::create(['key' => $key, 'is_active' => false] + $data);
                $counts['automations created']++;
            } elseif ($this->option('force')) {
                $existing->update($data); // is_active not in $data: on/off is kept
                $counts['automations updated']++;
            } else {
                $counts['skipped (already exist)']++;
            }
        }

        $this->table(['', 'count'], collect($counts)->map(fn ($n, $k) => [$k, $n])->values());
        $this->comment('All new automations are OFF. Dry-run each on Marketing > Email automation, send a test, then switch it on.');

        return self::SUCCESS;
    }
}
