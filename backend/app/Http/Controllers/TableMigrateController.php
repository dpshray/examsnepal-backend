<?php

namespace App\Http\Controllers;

use App\Models\Question;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;

class TableMigrateController extends Controller
{
    public function migrateQuestionOption()
    {
        try {
            $batchSize = 1000; // insert in chunks of 1000 rows
            Question::chunk(500, function ($questions) use ($batchSize) {
                $insertData = [];

                foreach ($questions as $qn) {
                    $insertData[] = [
                        'question_id' => $qn->id,
                        'option' => $qn->option_1,
                        'value' => $qn->option_value_1,
                    ];
                    $insertData[] = [
                        'question_id' => $qn->id,
                        'option' => $qn->option_2,
                        'value' => $qn->option_value_2,
                    ];
                    $insertData[] = [
                        'question_id' => $qn->id,
                        'option' => $qn->option_3,
                        'value' => $qn->option_value_3,
                    ];
                    $insertData[] = [
                        'question_id' => $qn->id,
                        'option' => $qn->option_4,
                        'value' => $qn->option_value_4,
                    ];

                    // Insert when batch reaches the size
                    if (count($insertData) >= $batchSize) {
                        DB::table('option_questions')->insert($insertData);
                        $insertData = []; // reset for next batch
                    }
                }

                // Insert remaining data
                if (!empty($insertData)) {
                    DB::table('option_questions')->insert($insertData);
                }
                return Response::apiSuccess('All question\'s option migrated');
            });
        } catch (\Exception $e) {
            return Response::apiError($e->getMessage());
        }
    }
}
