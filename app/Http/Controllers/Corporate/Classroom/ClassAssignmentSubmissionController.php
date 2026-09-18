<?php

namespace App\Http\Controllers\Corporate\Classroom;

use App\Http\Controllers\Controller;
use App\Http\Requests\Corporate\Classroom\ClassAssignmentGradeRequest;
use App\Http\Resources\Corporate\Classroom\ClassAssignmentSubmissionResource;
use App\Models\Corporate\ClassAssignment;
use App\Models\Corporate\ClassAssignmentSubmission;
use App\Models\Corporate\Classroom;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ClassAssignmentSubmissionController extends Controller
{
    public function index(Classroom $class, ClassAssignment $assignment)
    {
        $this->authorize($class, $assignment);

        $submissions = $assignment->submissions()->with('student:id,name,email')->latest()->get();

        return Response::apiSuccess('Submissions', ClassAssignmentSubmissionResource::collection($submissions));
    }

    public function grade(ClassAssignmentGradeRequest $request, Classroom $class, ClassAssignment $assignment, ClassAssignmentSubmission $submission)
    {
        $this->authorize($class, $assignment);
        $this->authorizeSubmissionBelongsToAssignment($assignment, $submission);

        $submission->update([
            'score' => $request->validated()['score'],
            'remark' => $request->validated()['remark'] ?? null,
            'graded_at' => now(),
            'graded_by' => Auth::user()->id,
        ]);

        return Response::apiSuccess('Submission graded successfully', new ClassAssignmentSubmissionResource($submission->load('student:id,name,email')));
    }

    /**
     * Stream the file the markup tool should load, through the API (rather
     * than the /storage/* static URL) so the browser can read the bytes via
     * fetch() - /storage isn't covered by the CORS config (only api/* is),
     * so a direct cross-origin fetch of that URL would be blocked even
     * though it loads fine in an <iframe>/<img>.
     *
     * Prefers the previously marked-up copy over the clean original, so
     * re-opening the markup tool continues from where the teacher left off
     * instead of silently discarding earlier marks on the next save.
     */
    public function downloadOriginal(Classroom $class, ClassAssignment $assignment, ClassAssignmentSubmission $submission)
    {
        $this->authorize($class, $assignment);
        $this->authorizeSubmissionBelongsToAssignment($assignment, $submission);

        $path = $submission->annotated_file_path ?: $submission->file_path;

        abort_unless($path && Storage::disk('public')->exists($path), 404, 'File not found.');

        return Storage::disk('public')->response($path);
    }

    /**
     * Store the teacher's pen-marked-up copy of a PDF submission. The
     * student's original upload (file_path) is left untouched.
     */
    public function annotate(Request $request, Classroom $class, ClassAssignment $assignment, ClassAssignmentSubmission $submission)
    {
        $this->authorize($class, $assignment);
        $this->authorizeSubmissionBelongsToAssignment($assignment, $submission);

        if ($submission->type !== 'pdf') {
            return Response::apiError('Only PDF submissions can be marked up.');
        }

        $request->validate([
            'file' => 'required|file|mimes:pdf|max:20480',
        ]);

        if ($submission->annotated_file_path) {
            Storage::disk('public')->delete($submission->annotated_file_path);
        }

        $submission->update([
            'annotated_file_path' => $request->file('file')->store('classes/assignment-submissions/annotated', 'public'),
        ]);

        return Response::apiSuccess('Marked-up PDF saved successfully', new ClassAssignmentSubmissionResource($submission->load('student:id,name,email')));
    }

    public function export(Classroom $class, ClassAssignment $assignment)
    {
        $this->authorize($class, $assignment);

        $submissions = $assignment->submissions()->with('student:id,name,email')->get();

        $graded = $submissions->whereNotNull('score')->sortByDesc('score');
        $ungraded = $submissions->whereNull('score');

        $rank = 1;
        $rankedRows = collect();
        foreach ($graded->groupBy('score')->sortKeysDesc() as $score => $group) {
            foreach ($group as $submission) {
                $rankedRows->push(['rank' => $rank, 'submission' => $submission]);
            }
            $rank += $group->count();
        }
        foreach ($ungraded as $submission) {
            $rankedRows->push(['rank' => null, 'submission' => $submission]);
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Score Card');

        $spreadsheet->getProperties()
            ->setCreator('ExamsNepal')
            ->setTitle('Assignment Score Card - ' . $assignment->title);

        $sheet->setCellValue('A1', 'ASSIGNMENT SCORE CARD');
        $sheet->mergeCells('A1:F1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $row = 3;
        $sheet->setCellValue("A{$row}", 'Assignment:');
        $sheet->setCellValue("B{$row}", $assignment->title);
        $sheet->getStyle("A{$row}")->getFont()->setBold(true);

        $row++;
        $sheet->setCellValue("A{$row}", 'Class:');
        $sheet->setCellValue("B{$row}", $class->name);
        $sheet->getStyle("A{$row}")->getFont()->setBold(true);

        $row++;
        $sheet->setCellValue("A{$row}", 'Full Marks:');
        $sheet->setCellValue("B{$row}", (float) $assignment->full_marks);
        $sheet->getStyle("A{$row}")->getFont()->setBold(true);

        $row++;
        $sheet->setCellValue("A{$row}", 'Generated:');
        $sheet->setCellValue("B{$row}", now()->format('Y-m-d H:i:s'));
        $sheet->getStyle("A{$row}")->getFont()->setBold(true);

        $row += 2;
        $headers = ['Rank', 'Student Name', 'Email', 'Score', 'Full Marks', 'Percentage (%)', 'Remark'];
        $columns = ['A', 'B', 'C', 'D', 'E', 'F', 'G'];
        foreach ($headers as $i => $header) {
            $sheet->setCellValue($columns[$i] . $row, $header);
        }
        $sheet->getStyle("A{$row}:G{$row}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4472C4']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]],
        ]);

        $row++;
        $fullMarks = (float) $assignment->full_marks;
        foreach ($rankedRows as $entry) {
            $submission = $entry['submission'];
            $percentage = $submission->score !== null && $fullMarks > 0 ? round(((float) $submission->score / $fullMarks) * 100, 2) : null;

            $sheet->setCellValue("A{$row}", $entry['rank'] ?? 'N/A');
            $sheet->setCellValue("B{$row}", $submission->student?->name ?? 'N/A');
            $sheet->setCellValue("C{$row}", $submission->student?->email ?? 'N/A');
            $sheet->setCellValue("D{$row}", $submission->score !== null ? (float) $submission->score : 'Not graded');
            $sheet->setCellValue("E{$row}", $fullMarks);
            $sheet->setCellValue("F{$row}", $percentage ?? 'N/A');
            $sheet->setCellValue("G{$row}", $submission->remark ?? '');

            $sheet->getStyle("A{$row}:G{$row}")->applyFromArray([
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $row % 2 === 0 ? 'E7E6E6' : 'FFFFFF']],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CCCCCC']]],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
            ]);
            $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("D{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("E{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("F{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $row++;
        }

        foreach (range('A', 'G') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $fileName = 'assignment-scorecard-' . str($assignment->title)->slug() . '-' . now()->format('Ymd_His') . '.xlsx';
        $tempFile = tempnam(sys_get_temp_dir(), 'assignment_scorecard_');

        $writer = new Xlsx($spreadsheet);
        $writer->save($tempFile);

        return response()->download($tempFile, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    private function authorize(Classroom $class, ClassAssignment $assignment): void
    {
        if ($class->institute_id !== Auth::user()->id) {
            throw new AuthorizationException('You do not have access to this class.');
        }

        if ($assignment->class_id !== $class->id) {
            throw new AuthorizationException('This assignment does not belong to this class.');
        }
    }

    private function authorizeSubmissionBelongsToAssignment(ClassAssignment $assignment, ClassAssignmentSubmission $submission): void
    {
        if ($submission->class_assignment_id !== $assignment->id) {
            throw new AuthorizationException('This submission does not belong to this assignment.');
        }
    }
}
