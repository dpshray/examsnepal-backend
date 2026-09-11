<?php

namespace App\Http\Controllers\Corporate\Classroom;

use App\Http\Controllers\Controller;
use App\Http\Requests\Corporate\Classroom\ClassExamBulkPublishQuestionRequest;
use App\Http\Requests\Corporate\Classroom\ClassExamQuestionRequest;
use App\Http\Resources\Corporate\Classroom\ClassExamQuestionCollection;
use App\Http\Resources\Corporate\Classroom\ClassExamQuestionResource;
use App\Models\Corporate\ClassExamQuestion;
use App\Models\Corporate\ClassExamQuestionOption;
use App\Models\Corporate\ClassExamSection;
use App\Models\Corporate\Classroom;
use App\Models\Exam;
use App\Services\QuestionWordImportService;
use App\Support\DataUriImage;
use App\Traits\PaginatorTrait;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;
use PhpOffice\PhpWord\Element\Image;
use Throwable;

class ClassExamQuestionController extends Controller
{
    use PaginatorTrait;

    public function index(Request $request, Classroom $class, Exam $exam, ClassExamSection $section)
    {
        $this->authorize($class, $exam, $section);

        $perPage = $request->query('per_page', 10);
        $questions = $section->questions()->with('options')->paginate($perPage);
        $data = $this->setupPagination($questions, ClassExamQuestionCollection::class)->data;

        return Response::apiSuccess('Questions', $data);
    }

    public function store(ClassExamQuestionRequest $request, Classroom $class, Exam $exam, ClassExamSection $section)
    {
        $this->authorize($class, $exam, $section);

        $data = $request->validated();

        DB::beginTransaction();
        try {
            $question = $section->questions()->create($data);

            if ($request->hasFile('image')) {
                $question->addMediaFromRequest('image')->toMediaCollection(ClassExamQuestion::QUESTION_IMAGE);
            }

            if ($question->question_type === 'mcq') {
                foreach ($data['options'] ?? [] as $optionData) {
                    $question->options()->create([
                        'option' => $optionData['option'],
                        'value' => $optionData['value'],
                    ]);
                }
            }

            DB::commit();

            return Response::apiSuccess('Question created successfully', new ClassExamQuestionResource($question->load('options')));
        } catch (Throwable $e) {
            DB::rollBack();
            return Response::apiError('Failed to create question', null, 500);
        }
    }

    public function update(ClassExamQuestionRequest $request, Classroom $class, Exam $exam, ClassExamSection $section, ClassExamQuestion $question)
    {
        $this->authorize($class, $exam, $section);
        $this->authorizeQuestionBelongsToSection($section, $question);

        $data = $request->validated();

        DB::beginTransaction();
        try {
            $question->update($data);

            if ($request->hasFile('image')) {
                $question->clearMediaCollection(ClassExamQuestion::QUESTION_IMAGE);
                $question->addMediaFromRequest('image')->toMediaCollection(ClassExamQuestion::QUESTION_IMAGE);
            }

            if ($question->question_type === 'mcq') {
                $question->options()->delete();
                foreach ($data['options'] ?? [] as $optionData) {
                    $question->options()->create([
                        'option' => $optionData['option'],
                        'value' => $optionData['value'],
                    ]);
                }
            } else {
                $question->options()->delete();
            }

            DB::commit();

            return Response::apiSuccess('Question updated successfully', new ClassExamQuestionResource($question->load('options')));
        } catch (Throwable $e) {
            DB::rollBack();
            return Response::apiError('Failed to update question', null, 500);
        }
    }

    public function destroy(Classroom $class, Exam $exam, ClassExamSection $section, ClassExamQuestion $question)
    {
        $this->authorize($class, $exam, $section);
        $this->authorizeQuestionBelongsToSection($section, $question);

        $question->delete();

        return Response::apiSuccess('Question deleted successfully');
    }

    /**
     * Parse an uploaded .docx into question DTOs for the teacher to preview
     * and edit in the browser. Nothing is written to the database here.
     */
    public function bulkImport(Request $request, Classroom $class, Exam $exam, ClassExamSection $section, QuestionWordImportService $importService)
    {
        $this->authorize($class, $exam, $section);

        $request->validate([
            'file' => 'required|mimes:docx',
        ]);

        $parsed = $importService->parse($request->file('file')->getRealPath());

        return Response::apiSuccess('Parsed ' . count($parsed['valid']) . ' question(s)', [
            'valid' => array_map([$this, 'questionToPreview'], $parsed['valid']),
            'errors' => $parsed['errors'],
        ]);
    }

    /**
     * Create questions from the (possibly teacher-edited) JSON produced by
     * the preview step above. Always creates plain MCQ questions (bulk .docx
     * import only supports the 4-option MCQ shape), full_marks = 1, no
     * negative marking — matching each other exam type's bulk-import default.
     */
    public function publishBulkImport(ClassExamBulkPublishQuestionRequest $request, Classroom $class, Exam $exam, ClassExamSection $section)
    {
        $this->authorize($class, $exam, $section);

        $questions = $request->validated()['questions'];
        $errors = [];
        $createdCount = 0;

        foreach ($questions as $index => $q) {
            try {
                DB::transaction(function () use ($q, $section) {
                    $question = $section->questions()->create([
                        'question' => $q['text'],
                        'description' => $q['explanation'],
                        'question_type' => 'mcq',
                        'is_negative_marking' => false,
                        'full_marks' => 1,
                    ]);

                    $createdOptions = $question->options()->createMany(array_map(
                        fn ($option) => [
                            'option' => $option['text'],
                            'value' => $option['is_correct'],
                        ],
                        $q['options']
                    ));

                    foreach ($q['options'] as $optIndex => $option) {
                        if (!empty($option['image_data_uri'])) {
                            $image = DataUriImage::decode($option['image_data_uri']);
                            $createdOptions[$optIndex]
                                ->addMediaFromString($image['binary'])
                                ->usingFileName('option_' . $optIndex . '.' . $image['extension'])
                                ->toMediaCollection(ClassExamQuestionOption::OPTION_IMAGE);
                        }
                    }

                    if (!empty($q['image_data_uri'])) {
                        $image = DataUriImage::decode($q['image_data_uri']);
                        $question->addMediaFromString($image['binary'])
                            ->usingFileName('question.' . $image['extension'])
                            ->toMediaCollection(ClassExamQuestion::QUESTION_IMAGE);
                    }
                });
                $createdCount++;
            } catch (Throwable $e) {
                $errors[] = [
                    'question_number' => $index + 1,
                    'reason' => 'Failed to save: ' . $e->getMessage(),
                ];
            }
        }

        return Response::apiSuccess('Bulk import finished', [
            'created_count' => $createdCount,
            'failed_count' => count($errors),
            'errors' => $errors,
        ]);
    }

    private function questionToPreview(array $q): array
    {
        $q['image'] = $this->imageToPreview($q['image']);

        foreach ($q['options'] as $letter => $option) {
            $q['options'][$letter]['image'] = $this->imageToPreview($option['image']);
        }

        unset($q['explanation_image']);

        return $q;
    }

    private function imageToPreview(?Image $image): ?array
    {
        if ($image === null) {
            return null;
        }

        return [
            'data_uri' => 'data:' . $image->getImageType() . ';base64,' . $image->getImageStringData(true),
            'filename' => 'image.' . $image->getImageExtension(),
        ];
    }

    private function authorize(Classroom $class, Exam $exam, ClassExamSection $section): void
    {
        if ($class->institute_id !== Auth::user()->id) {
            throw new AuthorizationException('You do not have access to this class.');
        }

        if (!$class->exams()->where('exams.id', $exam->id)->exists()) {
            throw new AuthorizationException('This exam does not belong to this class.');
        }

        if ($section->exam_id !== $exam->id) {
            throw new AuthorizationException('This section does not belong to this exam.');
        }
    }

    private function authorizeQuestionBelongsToSection(ClassExamSection $section, ClassExamQuestion $question): void
    {
        if ($question->class_exam_section_id !== $section->id) {
            throw new AuthorizationException('This question does not belong to this section.');
        }
    }
}
