<?php

namespace App\Http\Controllers\Corporate\Participant\Exam\Result;

use App\Http\Controllers\Controller;
use App\Models\Corporate\CorporateExam;
use App\Models\ExamAttempt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Response;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;

class ExamResultController extends Controller
{
    //
    /**
     * @OA\Get(
     *     path="/corporate/exams/{exam}/download-results",
     *     summary="Download exam results as Excel",
     *     description="Download evaluated exam results in Excel format. Supports filtering by section and status.",
     *     tags={"orporate Exam Results"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="exam",
     *         in="path",
     *         required=true,
     *         description="Corporate Exam slug",
     *         @OA\Schema(type="string", example="exam-slug")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Excel file download",
     *         @OA\MediaType(
     *             mediaType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized"
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Exam not found or no results available"
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error"
     *     )
     * )
     */
    public function downloadExamResults(Request $request, CorporateExam $exam)
    {
        $teacher = Auth::user();
        // Get exam with validation
        if ($exam->corporate_id !== $teacher->id) {
            return Response::apiError('Exam not found or access denied', 404);
        }
        // Get filter parameters
        $section_id = $request->input('section_id');
        $status = $request->input('status', 'evaluated'); // Default to evaluated only

        // Query attempts
        $query = ExamAttempt::where('corporate_exam_id', $exam->id)
            ->where('status', $status)
            ->with(['section']);

        // Filter by section if provided
        if ($section_id) {
            $query->where('corporate_exam_section_id', $section_id);
        }

        // Get all attempts
        $attempts = $query->orderBy('obtained_mark', 'desc')->get();
        if ($attempts->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No evaluated results found for this exam'
            ], 404);
        }

        // Calculate ranks
        $rankedAttempts = $this->calculateRanks($attempts);

        // Generate Excel file
        $spreadsheet = $this->generateExcelReport($exam, $rankedAttempts, $section_id);

        // Save to temp file
        $fileName = $this->generateFileName($exam, $section_id);
        $tempFile = tempnam(sys_get_temp_dir(), 'exam_result_');

        $writer = new Xlsx($spreadsheet);
        $writer->save($tempFile);

        // Return file download
        return response()->download($tempFile, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    /**
     * Calculate ranks based on marks
     */
    private function calculateRanks($attempts)
    {
        // Group by obtained marks for handling ties
        $grouped = $attempts->groupBy('obtained_mark')->sortKeysDesc();

        $rank = 1;
        $rankedAttempts = collect();

        foreach ($grouped as $mark => $group) {
            foreach ($group as $attempt) {
                $attempt->rank = $rank;
                $rankedAttempts->push($attempt);
            }
            $rank += $group->count(); // Increment rank by number of students with same marks
        }

        return $rankedAttempts->sortBy('rank');
    }

    /**
     * Generate Excel spreadsheet
     */
    private function generateExcelReport($exam, $attempts, $section_id = null)
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Set document properties
        $spreadsheet->getProperties()
            ->setCreator('Exam System')
            ->setTitle('Exam Results - ' . $exam->title)
            ->setSubject('Exam Results')
            ->setDescription('Results for ' . $exam->title);

        // Set sheet name
        $sheet->setTitle('Results');

        // Header section
        $sheet->setCellValue('A1', 'EXAM RESULTS REPORT');
        $sheet->mergeCells('A1:F1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Exam details
        $row = 3;
        $sheet->setCellValue("A{$row}", 'Exam Title:');
        $sheet->setCellValue("B{$row}", $exam->title);
        $sheet->getStyle("A{$row}")->getFont()->setBold(true);

        $row++;
        $sheet->setCellValue("A{$row}", 'Exam Date:');
        $sheet->setCellValue("B{$row}", $exam->exam_date ? $exam->exam_date->format('Y-m-d') : 'N/A');
        $sheet->getStyle("A{$row}")->getFont()->setBold(true);

        $row++;
        $sheet->setCellValue("A{$row}", 'Total Participants:');
        $sheet->setCellValue("B{$row}", $attempts->count());
        $sheet->getStyle("A{$row}")->getFont()->setBold(true);

        if ($section_id) {
            $row++;
            $sectionTitle = $attempts->first()->section->title ?? 'N/A';
            $sheet->setCellValue("A{$row}", 'Section:');
            $sheet->setCellValue("B{$row}", $sectionTitle);
            $sheet->getStyle("A{$row}")->getFont()->setBold(true);
        }

        $row++;
        $sheet->setCellValue("A{$row}", 'Generated Date:');
        $sheet->setCellValue("B{$row}", now()->format('Y-m-d H:i:s'));
        $sheet->getStyle("A{$row}")->getFont()->setBold(true);

        // Table headers
        $row += 2;
        $headerRow = $row;
        $headers = ['Rank', 'Student Name', 'Email', 'Phone', 'Score', 'Total Marks', 'Percentage (%)', 'Status'];
        $columns = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'];

        foreach ($headers as $index => $header) {
            $cell = $columns[$index] . $row;
            $sheet->setCellValue($cell, $header);
        }

        // Style headers
        $headerRange = "A{$row}:H{$row}";
        $sheet->getStyle($headerRange)->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
                'size' => 12
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4472C4']
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '000000']
                ]
            ]
        ]);

        // Data rows
        $row++;
        foreach ($attempts as $attempt) {
            $percentage = $attempt->total_mark > 0
                ? round(($attempt->obtained_mark / $attempt->total_mark) * 100, 2)
                : 0;

            $sheet->setCellValue("A{$row}", $attempt->rank);
            $sheet->setCellValue("B{$row}", $attempt->name);
            $sheet->setCellValue("C{$row}", $attempt->email);
            $sheet->setCellValue("D{$row}", $attempt->phone ?? 'N/A');
            $sheet->setCellValue("E{$row}", $attempt->obtained_mark);
            $sheet->setCellValue("F{$row}", $attempt->total_mark);
            $sheet->setCellValue("G{$row}", $percentage);
            $sheet->setCellValue("H{$row}", ucfirst($attempt->status));

            // Style data rows with alternating colors
            $rowRange = "A{$row}:H{$row}";
            $fillColor = ($row % 2 == 0) ? 'E7E6E6' : 'FFFFFF';

            $sheet->getStyle($rowRange)->applyFromArray([
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => $fillColor]
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => 'CCCCCC']
                    ]
                ],
                'alignment' => [
                    'vertical' => Alignment::VERTICAL_CENTER
                ]
            ]);

            // Center align rank, score, and percentage
            $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("E{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("F{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("G{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            // Highlight top 3 ranks
            if ($attempt->rank <= 3) {
                $rankColors = [
                    1 => 'FFD700', // Gold
                    2 => 'C0C0C0', // Silver
                    3 => 'CD7F32'  // Bronze
                ];

                $sheet->getStyle("A{$row}")->applyFromArray([
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => $rankColors[$attempt->rank]]
                    ],
                    'font' => ['bold' => true]
                ]);
            }

            $row++;
        }

        // Summary statistics
        $row += 2;
        $sheet->setCellValue("A{$row}", 'STATISTICS');
        $sheet->mergeCells("A{$row}:D{$row}");
        $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(14);

        $row++;
        $avgScore = $attempts->avg('obtained_mark');
        $maxScore = $attempts->max('obtained_mark');
        $minScore = $attempts->min('obtained_mark');
        $passCount = $attempts->filter(function ($attempt) {
            $percentage = ($attempt->obtained_mark / $attempt->total_mark) * 100;
            return $percentage >= 40; // Assuming 40% is passing
        })->count();

        $sheet->setCellValue("A{$row}", 'Average Score:');
        $sheet->setCellValue("B{$row}", round($avgScore, 2));
        $row++;
        $sheet->setCellValue("A{$row}", 'Highest Score:');
        $sheet->setCellValue("B{$row}", $maxScore);
        $row++;
        $sheet->setCellValue("A{$row}", 'Lowest Score:');
        $sheet->setCellValue("B{$row}", $minScore);
        $row++;
        $sheet->setCellValue("A{$row}", 'Pass Count (≥40%):');
        $sheet->setCellValue("B{$row}", $passCount);
        $row++;
        $sheet->setCellValue("A{$row}", 'Fail Count (<40%):');
        $sheet->setCellValue("B{$row}", $attempts->count() - $passCount);

        $sheet->getStyle("A" . ($row - 4) . ":A{$row}")->getFont()->setBold(true);

        // Auto-size columns
        foreach (range('A', 'H') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Set row height for header
        $sheet->getRowDimension($headerRow)->setRowHeight(25);

        return $spreadsheet;
    }

    /**
     * Generate file name
     */
    private function generateFileName($exam, $section_id = null)
    {
        $baseFileName = 'exam_results_' . str_replace(' ', '_', strtolower($exam->title));

        if ($section_id) {
            $baseFileName .= '_section_' . $section_id;
        }

        $baseFileName .= '_' . date('Y-m-d_His') . '.xlsx';

        return $baseFileName;
    }
}
