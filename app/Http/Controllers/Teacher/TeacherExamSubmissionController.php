<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Http\Resources\Teacher\TeacherExamSubmissionDetailResource;
use App\Http\Resources\Teacher\TeacherExamSubmissionResource;
use App\Models\Exam;
use App\Models\StudentExam;
use App\Services\ScoreService;
use App\Traits\PaginatorTrait;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Response;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class TeacherExamSubmissionController extends Controller
{
    use PaginatorTrait;

    /**
     * List every student's completed attempt (with score) for a teacher's own exam.
     */
    /**
     * @OA\Get(
     *     path="/teacher/exam/{exam}/submissions",
     *     summary="List submissions for a teacher's exam",
     *     description="Fetches every completed student attempt for the given exam, with score summary. Only the exam's owner (or an admin) may view this.",
     *     operationId="teacher_exam_submissions_list",
     *     tags={"TeacherExamSubmission"},
     *     @OA\Parameter(name="exam", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="page", in="query", required=false, @OA\Schema(type="integer", example=1)),
     *     @OA\Parameter(name="per_page", in="query", required=false, @OA\Schema(type="integer", example=10)),
     *     @OA\Parameter(name="search", in="query", required=false, description="filter by student name/email", @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Submission list")
     * )
     */
    public function index(Request $request, Exam $exam)
    {
        $this->isExamOwner($exam);

        $perPage = $request->query('per_page', 10);
        $search = $request->query('search');

        $studentExams = $exam->student_exams()
            ->select([
                'student_exams.id',
                'student_exams.student_id',
                'student_exams.institute_student_id',
                'student_exams.exam_id',
                'student_exams.created_at',
            ])
            ->with(['student:id,name,email,phone', 'institute_student:id,name,email,phone', 'exam.questions'])
            ->withCount([
                'correct_answers as correct_answer_count',
                'incorrect_answers as incorrect_answer_count',
            ])
            ->when($search, fn ($q) => $q->where(function ($q) use ($search) {
                $q->whereHas('student', fn ($sq) => $sq->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"))
                    ->orWhereHas('institute_student', fn ($sq) => $sq->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"));
            }))
            ->where('is_exam_completed', 1)
            ->orderByDesc('student_exams.created_at')
            ->paginate($perPage);

        $data = $this->setupPagination(
            $studentExams,
            fn ($items) => TeacherExamSubmissionResource::collection($items)
        )->data;

        return Response::apiSuccess("submissions for exam: {$exam->exam_name}", $data);
    }

    /**
     * Show one student's full answer-by-answer breakdown for a teacher's exam.
     */
    /**
     * @OA\Get(
     *     path="/teacher/exam/{exam}/submissions/{studentExam}",
     *     summary="Get one submission's detail",
     *     description="Fetches a single student's score plus a question-by-question breakdown (selected vs correct option) for the given exam. Only the exam's owner (or an admin) may view this.",
     *     operationId="teacher_exam_submission_detail",
     *     tags={"TeacherExamSubmission"},
     *     @OA\Parameter(name="exam", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="studentExam", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Submission detail")
     * )
     */
    public function show(Exam $exam, StudentExam $studentExam)
    {
        $this->isExamOwner($exam);
        abort_unless((int) $studentExam->exam_id === $exam->id, 404, 'Submission does not belong to this exam.');

        $studentExam = StudentExam::where('id', $studentExam->id)
            ->withCount([
                'correct_answers as correct_answer_count',
                'incorrect_answers as incorrect_answer_count',
            ])
            ->with([
                'student:id,name,email,phone',
                'institute_student:id,name,email,phone',
                'exam.questions.options',
                'answers',
            ])
            ->firstOrFail();

        $data = new TeacherExamSubmissionDetailResource($studentExam);

        return Response::apiSuccess('submission detail fetched successfully', $data);
    }

    /**
     * Export the ranked results summary (Rank, Name, Score) for a teacher's exam as an .xlsx file.
     */
    /**
     * @OA\Get(
     *     path="/teacher/exam/{exam}/submissions/export",
     *     summary="Export exam results summary",
     *     description="Downloads a ranked Rank/Name/Score spreadsheet of every completed attempt for the given exam. Only the exam's owner (or an admin) may download this.",
     *     operationId="teacher_exam_submissions_export",
     *     tags={"TeacherExamSubmission"},
     *     @OA\Parameter(name="exam", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Results spreadsheet (.xlsx)")
     * )
     */
    public function export(Exam $exam)
    {
        $this->isExamOwner($exam);

        $studentExams = $exam->student_exams()
            ->with(['student:id,name', 'institute_student:id,name', 'exam.questions'])
            ->withCount([
                'correct_answers as correct_answer_count',
                'incorrect_answers as incorrect_answer_count',
            ])
            ->where('is_exam_completed', 1)
            ->get();

        $scoreService = new ScoreService();
        $results = $studentExams->map(function (StudentExam $studentExam) use ($scoreService) {
            $score = $scoreService->fetchExamScore($studentExam);

            return [
                'name' => $studentExam->student->name ?? $studentExam->institute_student->name ?? 'Unknown',
                'score' => $score['final_exam_marks_after_reduction_of_negative_marking_point'],
            ];
        })->sortByDesc('score')->values();

        $ranked = $this->calculateRanks($results);

        $spreadsheet = $this->buildResultsSpreadsheet($exam, $ranked);

        $fileName = 'exam_results_' . str_replace(' ', '_', strtolower($exam->exam_name)) . '_' . date('Y-m-d_His') . '.xlsx';
        $tempFile = tempnam(sys_get_temp_dir(), 'exam_result_');

        (new Xlsx($spreadsheet))->save($tempFile);

        return response()->download($tempFile, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    /**
     * Assign competition-style ranks (ties share a rank, next rank skips accordingly).
     */
    private function calculateRanks(Collection $results): Collection
    {
        $rank = 1;
        $ranked = collect();
        $previousScore = null;
        $position = 0;

        foreach ($results as $result) {
            $position++;
            if ($result['score'] !== $previousScore) {
                $rank = $position;
                $previousScore = $result['score'];
            }
            $result['rank'] = $rank;
            $ranked->push($result);
        }

        return $ranked;
    }

    private function buildResultsSpreadsheet(Exam $exam, Collection $ranked): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Results');

        $spreadsheet->getProperties()
            ->setTitle('Exam Results - ' . $exam->exam_name)
            ->setDescription('Results summary for ' . $exam->exam_name);

        $sheet->setCellValue('A1', 'EXAM RESULTS SUMMARY');
        $sheet->mergeCells('A1:C1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->setCellValue('A3', 'Exam:');
        $sheet->setCellValue('B3', $exam->exam_name);
        $sheet->getStyle('A3')->getFont()->setBold(true);

        $sheet->setCellValue('A4', 'Generated:');
        $sheet->setCellValue('B4', now()->format('Y-m-d H:i:s'));
        $sheet->getStyle('A4')->getFont()->setBold(true);

        $headerRow = 6;
        $headers = ['Rank', 'Name', 'Score'];
        $columns = ['A', 'B', 'C'];
        foreach ($headers as $index => $header) {
            $sheet->setCellValue($columns[$index] . $headerRow, $header);
        }
        $sheet->getStyle("A{$headerRow}:C{$headerRow}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4472C4']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]],
        ]);

        $row = $headerRow + 1;
        $rankColors = [1 => 'FFD700', 2 => 'C0C0C0', 3 => 'CD7F32'];

        foreach ($ranked as $result) {
            $sheet->setCellValue("A{$row}", $result['rank']);
            $sheet->setCellValue("B{$row}", $result['name']);
            $sheet->setCellValue("C{$row}", $result['score']);

            $sheet->getStyle("A{$row}:C{$row}")->applyFromArray([
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $row % 2 === 0 ? 'E7E6E6' : 'FFFFFF']],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CCCCCC']]],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
            ]);
            $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("C{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            if (isset($rankColors[$result['rank']])) {
                $sheet->getStyle("A{$row}")->applyFromArray([
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $rankColors[$result['rank']]]],
                    'font' => ['bold' => true],
                ]);
            }

            $row++;
        }

        foreach (['A' => 10, 'B' => 30, 'C' => 12] as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }

        return $spreadsheet;
    }

    private function isExamOwner(Exam $exam)
    {
        throw_if(!Auth::user()->isAdmin() && $exam->user->isNot(Auth::guard('users')->user()), AuthorizationException::class, 'You are not the owner');
    }
}
