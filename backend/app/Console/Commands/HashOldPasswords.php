<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class HashOldPasswords extends Command
{
    protected $signature = 'passwords:rehash-old'; // <== This is the command you’ll run

    protected $description = 'Hash passwords from old database and update them into student_profiles';

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    // protected $signature = 'app:hash-old-passwords';

    /**
     * The console command description.
     *
     * @var string
     */
    // protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $results = DB::select('
                    SELECT DISTINCT old.exam_id FROM examsnepal_old.answersheet AS old 
                    JOIN examsnepal_api.exams AS api ON old.exam_id = api.id
                ');
         $student_id_email = collect(DB::select("
    SELECT a.st_email, s.id AS student_id
    FROM examsnepal_old.answersheet AS a
    JOIN examsnepal_api.student_profiles AS s
        ON a.st_email = s.email
    WHERE a.st_email != ''
    GROUP BY a.st_email, s.id
"))->pluck('student_id', 'st_email')->toArray();



        // Flatten the exam_id values
        $existing_exams_id = array_map(fn($row) => $row->exam_id, $results);

        // Build column list
        $columns = ['st_email', 'exam_id'];
        $old_responses = [];
        for ($i = 1; $i <= 180; $i++) {
            $old_responses[] = 'q' . $i;
            $columns[] = 'q' . $i;
        }
        $qs_w_no_opt = [];
        $no_email = [];
        // Process in chunks of 500
        DB::connection('examsnepal_old')
            ->table('answersheet')
            ->select($columns)
            ->whereIn('exam_id', $existing_exams_id)
            ->orderBy('id') // required for chunking
            ->chunk(500, function ($rows) use ($student_id_email, $old_responses, $qs_w_no_opt, $no_email) {
                foreach ($rows as $row) {
                    // dd($row);
                    if (str_contains($row->st_email,"@") && in_array($row->st_email, $student_id_email)) {

                        // Process each row
                        // dd($row); // Replace with actual logic
                        // $user = DB::table('student_profiles')->where('email',$row->st_email)->first();
                        $student_id = $student_id_email[$row->st_email];
                        $student_exam_id = DB::table('student_exams')
                            ->insertGetId(['exam_id' =>  $row->exam_id, 'student_id' => $student_id]);
                        $questions = DB::table('questions')->where('exam_id', $row->exam_id)->orderBy('id', 'ASC')->get();
                        // dd($questions->count());
                        // dd($old_responses);
                        $temp = [];
                        foreach ($questions as $key => $question) {
                            $p = $old_responses[$key];
                            // dd($row->$p);
                            // dd($old_responses[$key]);
                            $question_id = $question->id;
                            $questions_options = DB::table('option_questions')->where('question_id', $question_id)->get();
                            dd($questions_options);
                            if ($questions_options->count()) {
                                $right_option_id = $questions_options->where('value', 1)->first()->id;
                                $wrong_option_id = $questions_options->where('value', 0)->first()->id;
                                if ($row->$p == 1) {
                                    $temp[] = [
                                        'student_exam_id' => $student_exam_id,
                                        'selected_option_id' => $right_option_id,
                                        'question_id' => $question->id,
                                        'is_correct' => 1
                                    ];
                                } elseif ($row->$p == 0) {
                                    $temp[] = [
                                        'student_exam_id' => $student_exam_id,
                                        'selected_option_id' => $wrong_option_id,
                                        'question_id' => $question->id,
                                        'is_correct' => 0
                                    ];
                                } else {
                                    $temp[] = [
                                        'student_exam_id' => $student_exam_id,
                                        'question_id' => $question->id,
                                        'selected_option_id' => null,
                                        'is_correct' => null
                                    ];
                                }
                                // dd($temp);
                            } else {
                                $qs_w_no_opt[] = $question_id;
                            }
                        }
                        dd($temp);
                        DB::table('answersheets')->insert($temp);
                    }else{
                        $no_email[] = $row->st_email;
                    }
                }
            });
            var_dump($qs_w_no_opt);
            var_dump($no_email);
    }
}
