<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MigrationController extends Controller
{
    /**
     * @OA\Get(
     *     path="/migrate",
     *     operationId="migrateAnswersheets",
     *     tags={"AnswerSheet Migration"},
     *     summary="Migrate old answer sheet data to new format",
     *     description="This endpoint migrates legacy answer sheet records from the old format (answersheet table) into the new answersheets table format.",
     *     @OA\Response(
     *         response=200,
     *         description="Migration completed successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Migration completed successfully.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Old answer sheet or student not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Old answer sheet or student not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Migration failed: {error_message}")
     *         )
     *     )
     * )
     */
    public function migrateAnswersheets()
    {
        try {
            // Fetch 10 records at a time, ordered by 'id'
            DB::table('answersheet')->orderBy('id')->chunk(2, function ($answerSheets) {
                foreach ($answerSheets as $old_answerSheetData) {
                    try {
                        $student = DB::table('student_profiles')->where('email', $old_answerSheetData->st_email)->first();
                        $examId = $old_answerSheetData->exam_id ?? null;
                        $studentId = $student->id ?? null;

                        // Fetch questions related to the exam, limit to 200 questions
                        $fetchedQuestionNotArray = DB::table('questions')
                            ->where('exam_id', $examId)
                            ->limit(200)
                            ->get();

                        if ($fetchedQuestionNotArray->isEmpty()) {
                            DB::table('answersheet')->where('id', $old_answerSheetData->id)->delete();
                            throw new \Exception('No questions found for the provided exam ID: ' . $examId);
                        }

                        $fetchedQuestion = $fetchedQuestionNotArray->toArray();

                        // Loop through 200 questions to check and migrate answers
                        for ($i = 0; $i < count($fetchedQuestion); $i++) {
                            $questionColumn = 'q' . ($i + 1); // Starting from q1
                            if (!isset($old_answerSheetData->$questionColumn) || empty($old_answerSheetData->$questionColumn)) {
                                continue;
                            }
                            // Check if the answer exists for this question
                            if (isset($old_answerSheetData->$questionColumn)) {
                                // Determine the correctness of the answer (set to 1 or 0)
                                $answerValue = $old_answerSheetData->$questionColumn ?? null;

                                $correctAnswer = $answerValue === null ? null : ($answerValue < 0 ? 0 : 1);
                                // Insert the answer sheet record into the answersheets table
                                DB::table('answersheets')->insert([
                                    'exam_id' => $examId,
                                    'question_id' => $fetchedQuestion[$i]->id,
                                    'student_id' => $studentId,
                                    'choosed_option_value' => null,
                                    'correct_answer_submitted' => $correctAnswer,
                                    'created_at' => now(),
                                    'updated_at' => now(),
                                ]);
                            }
                        }

                        // Delete the old answer sheet after successful migration
                        DB::table('answersheet')->where('id', $old_answerSheetData->id)->delete();
                        return response()->json([
                            'message' => 'Data Deleted',
                            'data' => $old_answerSheetData,
                        ], 200);
                    } catch (\Exception $e) {
                        // Log the error for this specific record and continue with the next record
                        \Log::error("Migration failed for answer sheet ID {$old_answerSheetData->id}: " . $e->getMessage());
                        // Continue with the next answer sheet
                    }
                }
            });

            return response()->json([
                'message' => 'Migration completed.',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Migration failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function migrateNext()
    {
        DB::table('answersheet')->orderBy('id')->chunk(2, function ($answerSheets) {
            foreach ($answerSheets as $old_answerSheetData) {
                try {
                    // 1. Get the student by email from student_profiles table
                    $student = DB::table('student_profiles')->where('email', $old_answerSheetData->st_email)->first();

                    // If student is not found, skip this answer sheet
                    if (!$student) {
                        echo "No student found with email: {$old_answerSheetData->st_email}\n";
                        continue; // Skip to the next iteration
                    }

                    $examId = $old_answerSheetData->exam_id ?? null;
                    $studentId = $student->id ?? null;

                    // 2. Fetch questions associated with the exam_id
                    $fetchedQuestions = DB::table('questions')
                        ->where('exam_id', $examId)
                        ->limit(200) // Limit to 200 questions
                        ->get();

                    // If no questions are found, log and delete the record
                    if ($fetchedQuestions->isEmpty()) {
                        DB::table('answersheet')->where('id', $old_answerSheetData->id)->delete();
                        echo "No questions found for exam ID: {$examId}, deleted old record ID: {$old_answerSheetData->id}\n";
                        continue; // Skip to the next record
                    }

                    $totalInserts = 0;

                    // 3. Loop through each question and insert answers if valid
                    foreach ($fetchedQuestions as $index => $question) {
                        $column = 'q' . ($index + 1); // Dynamic column name (q1, q2, ...)

                        // Skip if the column doesn't exist in the old_answerSheetData
                        if (!property_exists($old_answerSheetData, $column)) {
                            continue;
                        }

                        $value = $old_answerSheetData->$column; // Get the value for the current question

                        // 4. Skip insert if the value is null
                        if (is_null($value)) {
                            continue; // Do not insert if value is null
                        }

                        // Determine correct answer based on value
                        $correctAnswer = $this->determineCorrectAnswer($value);

                        // 5. Insert answer into answersheets table
                        DB::table('answersheets')->insert([
                            'exam_id' => $examId,
                            'question_id' => $question->id,
                            'student_id' => $studentId,
                            'choosed_option_value' => null, // Assuming this value is null for now
                            'correct_answer_submitted' => $correctAnswer,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);

                        $totalInserts++; // Increment insert counter
                        echo "Inserted answer for student ID: {$studentId}, Question ID: {$question->id}\n";
                    }

                    // 6. If all 200 questions are successfully inserted, delete the old record
                    if ($totalInserts === $fetchedQuestions->count()) {
                        // Delete the old record from answersheet after successful insertion
                        DB::table('answersheet')->where('id', $old_answerSheetData->id)->delete();
                        echo "Deleted old record ID: {$old_answerSheetData->id}\n";
                    } else {
                        echo "Not all answers were inserted for student ID: {$studentId}, skipping deletion\n";
                    }
                } catch (\Exception $e) {
                    // Catch any exceptions and continue with the next record
                    echo "Migration failed for answer sheet ID {$old_answerSheetData->id}: " . $e->getMessage() . "\n";
                    continue;
                }
            }
        });
    }

    /**
     * Determines the correct answer based on the given value.
     * 
     * @param mixed $value
     * @return int|null
     */
    private function determineCorrectAnswer($value)
    {
        if (is_null($value)) {
            return null;
        }

        // Force cast to numeric before comparison
        $numericValue = is_numeric($value) ? floatval($value) : null;
        if ($numericValue === null) {
            return null;
        }

        // Logic to determine the correct answer
        return $numericValue <= 0 ? 0 : 1;
    }
}
