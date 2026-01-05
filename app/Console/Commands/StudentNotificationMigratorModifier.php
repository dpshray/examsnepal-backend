<?php

namespace App\Console\Commands;

use App\Models\StudentNotification;
use App\Models\StudentProfile;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StudentNotificationMigratorModifier extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:student-notification-migrator-modifier';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Log::info('here');
        $this->info('Processing...');
        $rows = DB::select('SELECT *
            FROM student_notifications sn
            WHERE sn.id IN (
                SELECT MAX(id)
                FROM student_notifications
                GROUP BY created_at
        )');
        DB::transaction(function () use ($rows) {
            $keep_these[] = null;
            foreach ($rows as $row) {
                $keep_these[] = $row->id;
                $temp = [];
                StudentNotification::where('created_at', $row->created_at)->get()
                ->map(function ($item) use ($row, &$temp) {
                        $temp[] = [
                            'student_profile_id' => $item->student_profile_id,
                        ];
                    });
                StudentNotification::find($row->id)->reads()->createMany($temp);
            }
            $keep_these = array_filter($keep_these);
            $this->info(json_encode($keep_these));
            // dd($keep_these);
            DB::table('student_notifications')->whereNotIn('id', $keep_these)->delete();
        });
        $this->info('Done.');
        // $this->info($keep_these);
    }
}
