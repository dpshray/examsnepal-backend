<?php

namespace App\Http\Controllers;

use App\Models\Question;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Response;

class TableMigrateController extends Controller
{
    public function migrateQuestionOption()
    {
        try {
            $batchSize = 1000;

            Question::whereNotNull('exam_id')->chunk(500, function ($questions) use ($batchSize) {
                $insertData = [];
                $currentBatchSize = 0;

                foreach ($questions as $qn) {
                    $options = [];

                    if (
                        !in_array($qn->value_option_1, [1, 0]) ||
                        !in_array($qn->value_option_2, [1, 0]) ||
                        !in_array($qn->value_option_3, [1, 0]) ||
                        !in_array($qn->value_option_4, [1, 0])
                    ) {
                        $options = [
                            ['option' => $qn->option_1, 'value' => $qn->value_option_1 <= 0 ? 0 : 1],
                            ['option' => $qn->option_2, 'value' => $qn->value_option_2 <= 0 ? 0 : 1],
                            ['option' => $qn->option_3, 'value' => $qn->value_option_3 <= 0 ? 0 : 1],
                            ['option' => $qn->option_4, 'value' => $qn->value_option_4 <= 0 ? 0 : 1],
                        ];
                    } else {
                        $options = [
                            ['option' => $qn->option_1, 'value' => $qn->value_option_1],
                            ['option' => $qn->option_2, 'value' => $qn->value_option_2],
                            ['option' => $qn->option_3, 'value' => $qn->value_option_3],
                            ['option' => $qn->option_4, 'value' => $qn->value_option_4],
                        ];
                    }

                    foreach ($options as $opt) {
                        $insertData[] = [
                            'question_id' => $qn->id,
                            'option' => $opt['option'],
                            'value' => $opt['value'],
                        ];
                        $currentBatchSize++;

                        if ($currentBatchSize >= $batchSize) {
                            DB::table('option_questions')->insert($insertData);
                            $insertData = [];
                            $currentBatchSize = 0;
                        }
                    }
                }

                // Insert remaining data after each chunk
                if (!empty($insertData)) {
                    DB::table('option_questions')->insert($insertData);
                }
            });

            return Response::apiSuccess("All question's options migrated");
        } catch (\Exception $e) {
            return Response::apiError($e->getMessage());
        }
    }

    public function migratePool()
    {
        $ignores = array_column(DB::select('SELECT qid FROM pool where qid not in(select id from questions) group by qid'), 'qid');
        DB::table('pool')->get()->groupBy('date')->each(function ($item, $date) use ($ignores) {
            // dd($date);
            // dd($item->groupBy('email'));
            if (!empty($date)) {

                foreach ($item->groupBy('email') as $email => $itms) {
                    // dd($itm);
                    if (!empty($email)) {
                        $id = DB::table('student_pools')->insertGetId([
                            'email' => $email,
                            'played_at' => now()->createFromFormat('m/d/Y', $date)->format('Y-m-d')
                        ]);
                        foreach ($itms as $itm) {
                            if (!in_array($itm->qid, $ignores)) {
                                DB::table('pools')->insertGetId(['student_pool_id' => $id, 'question_id' => $itm->qid]);
                            }
                        }
                    }
                }
            }
        });
        echo 'OK';
    }

    public function passwordHasher()
    {

        // Assuming you're connected to `examsnepal_api` by default
        DB::table('student_profiles')
            ->where('password', '!=', '')
            ->orderBy('id') // ensure consistent chunking
            ->chunk(500, function ($students) {
                foreach ($students as $student) {
                    // Lookup matching old password from old database
                    $old = DB::connection('examsnepal_old')
                        ->table('student_profile')
                        ->where('email', $student->email)
                        ->first();

                    if ($old && $old->password) {
                        $hashed = Hash::make($old->password);

                        // Update password in the current (api) database
                        DB::table('student_profiles')
                            ->where('id', $student->id)
                            ->update(['password' => $hashed]);
                    }
                }
            });
        echo 'HERE';
    }

    public function migrateAnswersheets()
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
        $exams_w_no_qs = [];
        // Process in chunks of 500
        DB::connection('examsnepal_old')
            ->table('answersheet')
            ->select($columns)
            ->whereIn('exam_id', $existing_exams_id)
            ->orderBy('id') // required for chunking
            ->chunk(500, function ($rows) use ($student_id_email, $old_responses, $qs_w_no_opt, $no_email, $exams_w_no_qs) {
                foreach ($rows as $row) {
                    // dd($row);
                    if (str_contains($row->st_email, "@") && array_key_exists($row->st_email, $student_id_email)) {

                        // Process each row
                        // dd($row); // Replace with actual logic
                        // $user = DB::table('student_profiles')->where('email',$row->st_email)->first();
                        $student_id = $student_id_email[$row->st_email];
                        $student_exam_id = DB::table('student_exams')
                            ->insertGetId(['exam_id' =>  $row->exam_id, 'student_id' => $student_id]);
                        $questions = DB::table('questions')->where('exam_id', $row->exam_id)->orderBy('id', 'ASC')->get();
                        // dd($questions->count());
                        // dd($old_responses);
                        if ($questions->count() == 0) {
                            $exams_w_no_qs[] = $row->exam_id;
                            continue;
                        }
                        $temp = [];
                        foreach ($questions as $key => $question) {
                            if (!array_key_exists($key, $old_responses)) {
                                break; #exams questions > user response(180)
                                // dd($row);
                                // continue;
                            }
                            $p = $old_responses[$key];
                            // dd($row->$p);
                            // dd($old_responses[$key]);
                            $question_id = $question->id;
                            $questions_options = DB::table('option_questions')->where('question_id', $question_id)->get();
                            // dd($questions_options);
                            if ($questions_options->count()) {

                                $wrong_option = $questions_options->where('value', 0)->first();
                                $right_option = $questions_options->where('value', 1)->first();
                                if ($row->$p == 1) {
                                    $temp[] = [
                                        'student_exam_id' => $student_exam_id,
                                        'selected_option_id' => $right_option ? $right_option->id : null, #if all options are wrong and so when student skipped, makes it right choice
                                        'question_id' => $question->id,
                                        'is_correct' => 1
                                    ];
                                } elseif ($row->$p == 0) {
                                    $temp[] = [
                                        'student_exam_id' => $student_exam_id,
                                        'selected_option_id' => $wrong_option ? $wrong_option->id : null,
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
                            } else {
                                $qs_w_no_opt[] = $question_id; #question w no options
                            }
                        }
                        // dd($temp);
                        DB::table('answersheets')->insert($temp);
                    } else {
                        $no_email[] = $row->st_email;
                    }
                }
            });
        echo 'question w no options';
        var_dump($qs_w_no_opt);
        echo 'no email found';
        var_dump($no_email);
        echo 'exam w no qs';
        var_dump($exams_w_no_qs);
    }
}
