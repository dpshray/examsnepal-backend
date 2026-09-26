<?php

namespace Tests\Feature\Marketing;

use App\Enums\ExamTypeEnum;
use App\Models\Marketing\Automation;
use App\Models\Marketing\EmailTemplate;
use App\Models\Marketing\MessageSend;
use App\Services\Marketing\AutomationEngine;
use App\Services\Marketing\MarketingSettings;
use App\Services\Marketing\StudentMetricsCalculator;
use App\Services\Marketing\TemplateRenderer;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class SubjectScoresTest extends MarketingDatabaseTestCase
{
    private int $pathology;
    private int $anatomy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-09-26 18:30', 'Asia/Kathmandu'));
        $this->pathology = DB::table('subjects')->insertGetId(['name' => 'Pathology']);
        $this->anatomy = DB::table('subjects')->insertGetId(['name' => 'Anatomy']);
    }

    private function subjectAttempt(int $studentId, int $subjectId, float $score, int $daysAgo = 1): void
    {
        $exam = $this->exam(ExamTypeEnum::SPRINT_QUIZ->value, 5, ['subject_id' => $subjectId]);
        $this->attempt($studentId, ExamTypeEnum::SPRINT_QUIZ->value, $score, now()->subDays($daysAgo), examId: $exam);
    }

    public function test_weakest_and_strongest_need_two_subjects_with_two_attempts(): void
    {
        $id = $this->student();
        $this->subjectAttempt($id, $this->pathology, 30);
        $this->subjectAttempt($id, $this->anatomy, 70);
        (new StudentMetricsCalculator())->refresh([$id]);
        $this->assertNull(DB::table('student_metrics')->where('student_id', $id)->value('weakest_subject_id'), 'one attempt each is too noisy');

        $this->subjectAttempt($id, $this->pathology, 36, 2);
        $this->subjectAttempt($id, $this->anatomy, 80, 2);
        $this->attempt($id, ExamTypeEnum::MOCK_TEST->value, 5, now()); // mixed mock: ignored for subjects
        (new StudentMetricsCalculator())->refresh([$id]);

        $m = $this->metrics($id);
        $this->assertSame([$this->pathology, 33.0, $this->anatomy], [(int) $m->weakest_subject_id, (float) $m->weakest_subject_score_pct, (int) $m->strongest_subject_id]);
        $this->assertContains('has_weak_subject', json_decode($m->segments, true));
    }

    public function test_weak_subject_email_names_the_subject(): void
    {
        Mail::fake();
        MarketingSettings::set(MarketingSettings::PAUSED, false);
        $this->artisan('marketing:install-starter')->assertSuccessful();
        Automation::where('key', 'weak_subject')->update(['is_active' => true]);

        $weak = $this->student(['name' => 'Rita Shah']);
        $fine = $this->student();
        foreach ([[$weak, 30, 34], [$fine, 55, 60]] as [$id, $a, $b]) {
            $this->subjectAttempt($id, $this->pathology, $a);
            $this->subjectAttempt($id, $this->pathology, $b, 2);
            $this->subjectAttempt($id, $this->anatomy, 75);
            $this->subjectAttempt($id, $this->anatomy, 80, 2);
        }
        (new StudentMetricsCalculator())->refresh([$weak, $fine]);
        (new AutomationEngine())->plan();
        (new AutomationEngine())->dispatch();

        $send = MessageSend::sole(); // $fine's weakest (57.5%) is above the 40% threshold
        $this->assertSame($weak, $send->student_id);
        $this->assertSame('Pathology is pulling your score down', $send->subject);
    }

    public function test_recommended_exam_and_next_free_quiz(): void
    {
        $id = $this->student();
        $taken = $this->exam(ExamTypeEnum::SPRINT_QUIZ->value, 5, ['exam_name' => 'Taken sprint', 'live' => '1']);
        $this->attempt($id, ExamTypeEnum::SPRINT_QUIZ->value, 50, now()->subDay(), examId: $taken);
        $this->exam(ExamTypeEnum::SPRINT_QUIZ->value, 5, ['exam_name' => 'Fresh sprint', 'live' => '1']);
        $this->exam(ExamTypeEnum::SPRINT_QUIZ->value, 5, ['exam_name' => 'Draft sprint', 'live' => '0']);
        $this->exam(ExamTypeEnum::FREE_QUIZ->value, 5, ['exam_mode' => 'scheduled', 'exam_date' => '2026-10-02']);
        (new StudentMetricsCalculator())->refresh([$id]);
        $template = new EmailTemplate(['key' => 't', 'subject' => 's', 'html_body' => 'x']);

        $vars = (new TemplateRenderer())->variables($id, $template);

        $this->assertSame('Fresh sprint', $vars['recommended_exam']);
        $this->assertSame('https://www.examsnepal.com/student/exams/sprint-quiz', $vars['recommended_exam_url']);
        $this->assertSame('Friday, 2 Oct', $vars['next_free_quiz_date']);
    }

    public function test_infer_command_tags_exams_and_questions(): void
    {
        $pathExam = $this->exam(ExamTypeEnum::SPRINT_QUIZ->value, 3, ['exam_name' => 'Sprint Quiz Pathology']);
        $mixed = $this->exam(ExamTypeEnum::MOCK_TEST->value, 2, ['exam_name' => 'MDMS Revision Exam Set 5']);
        $manual = $this->exam(ExamTypeEnum::SPRINT_QUIZ->value, 1, ['exam_name' => 'Sprint Quiz Anatomy', 'subject_id' => $this->pathology, 'subject_source' => 'manual']);

        $this->artisan('subjects:infer')->assertSuccessful(); // dry run
        $this->assertNull(DB::table('exams')->where('id', $pathExam)->value('subject_id'));

        $this->artisan('subjects:infer', ['--apply' => true])->assertSuccessful();
        $this->assertSame($this->pathology, (int) DB::table('exams')->where('id', $pathExam)->value('subject_id'));
        $this->assertSame(3, DB::table('questions')->where('exam_id', $pathExam)->where('subject_id', $this->pathology)->count());
        $this->assertNull(DB::table('exams')->where('id', $mixed)->value('subject_id'));
        $this->assertSame($this->pathology, (int) DB::table('exams')->where('id', $manual)->value('subject_id'), 'manual tags are never overwritten');
    }
}
