<?php

namespace App\Console\Commands\Marketing;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Applies only the marketing migrations, in order, by path. The production
 * database has old migrations stuck as "Pending" that would re-create
 * existing tables, so a bare `php artisan migrate` must not be used.
 */
class MigrateMarketing extends Command
{
    protected $signature = 'marketing:migrate {--pretend : Only list what would run}';

    protected $description = 'Run the marketing migrations (2026_09_26_*) by path, skipping ones already applied';

    public function handle(): int
    {
        $files = collect(File::glob(database_path('migrations/2026_09_26_1*.php')))->map(fn ($f) => basename($f, '.php'))->sort()->values();
        $ran = DB::table('migrations')->whereIn('migration', $files)->pluck('migration')->flip();

        $pending = $files->reject(fn ($m) => $ran->has($m));
        $this->line(sprintf('%d marketing migration(s): %d already applied, %d to run.', $files->count(), $ran->count(), $pending->count()));

        foreach ($pending as $migration) {
            if ($this->option('pretend')) {
                $this->line("  would run {$migration}");
                continue;
            }
            $this->call('migrate', ['--path' => "database/migrations/{$migration}.php", '--force' => true]);
        }

        return self::SUCCESS;
    }
}
