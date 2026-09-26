<?php

namespace Tests\Feature\Marketing;

use App\Http\Middleware\CheckTokenVersionMiddleware;
use App\Http\Resources\StudentProfileResource;
use App\Models\StudentProfile;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class OnboardingTest extends MarketingDatabaseTestCase
{
    private StudentProfile $student;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(CheckTokenVersionMiddleware::class);
        $this->travelTo(CarbonImmutable::parse('2026-09-26 12:00', 'Asia/Kathmandu'));
        Schema::table('exam_types', fn ($t) => $t->boolean('is_active')->default(true));
        DB::table('exam_types')->where('id', 9)->update(['is_active' => false]);
        $this->student = StudentProfile::query()->find($this->student());
    }

    private function api(string $method, array $data = [])
    {
        return $this->actingAs($this->student, 'api')->json($method, '/api/student/onboarding', $data);
    }

    public function test_status_and_active_exam_list(): void
    {
        $this->api('GET')->assertOk()
            ->assertJsonPath('data.needs_onboarding', true)
            ->assertJsonPath('data.exam_type_id', 1)
            ->assertJsonPath('data.exam_locked', false)
            ->assertJsonPath('data.next_path', '/student/exams/free-quiz')
            ->assertJsonCount(2, 'data.exam_types'); // exam type 9 is inactive
        $this->assertTrue((new StudentProfileResource($this->student))->toArray(request())['needs_onboarding']);
    }

    public function test_saves_exam_and_month_then_routes_to_free_quiz(): void
    {
        $this->api('POST', ['exam_type_id' => 2, 'exam_month' => '2027-03'])
            ->assertOk()
            ->assertJsonPath('data.next_path', '/student/exams/free-quiz')
            ->assertJsonPath('data.target_exam_date', '2027-03-01');

        $this->student->refresh();
        $this->assertSame([2, '2027-03-01'], [$this->student->exam_type_id, $this->student->target_exam_date->toDateString()]);
        $this->assertNotNull($this->student->onboarded_at);
        $this->assertSame(['exam_type_id' => 2, 'has_exam_date' => true], json_decode(DB::table('events')->where('name', 'onboarding_completed')->value('properties'), true));
        $this->api('GET')->assertJsonPath('data.needs_onboarding', false);
    }

    public function test_not_sure_and_validation(): void
    {
        $this->api('POST', ['exam_type_id' => 1])->assertStatus(422);                         // month or not sure required
        $this->api('POST', ['exam_type_id' => 1, 'exam_month' => '2026-08'])->assertStatus(422); // past
        $this->api('POST', ['exam_type_id' => 9, 'not_sure' => true])->assertStatus(422);      // inactive exam
        $this->api('POST', ['exam_type_id' => 1, 'not_sure' => true])->assertOk()->assertJsonPath('data.target_exam_date', null);
        $this->assertNotNull($this->student->refresh()->onboarded_at);
    }

    public function test_paying_students_cannot_switch_exam(): void
    {
        $this->payment($this->student->id);
        $this->api('GET')->assertJsonPath('data.exam_locked', true);
        $this->api('POST', ['exam_type_id' => 2, 'not_sure' => true])->assertStatus(422);
        $this->api('POST', ['exam_type_id' => 1, 'exam_month' => '2026-12'])->assertOk();
    }

    public function test_skip(): void
    {
        $this->api('POST', ['skip' => true])->assertOk()->assertJsonPath('data.next_path', '/student/dashboard');
        $this->assertNotNull($this->student->refresh()->onboarded_at);
        $this->assertNull($this->student->target_exam_date);
    }

    public function test_exam_date_powers_the_countdown_condition(): void
    {
        $this->api('POST', ['exam_type_id' => 1, 'exam_month' => '2026-10'])->assertOk(); // 1 Oct = 5 days away
        (new \App\Services\Marketing\StudentMetricsCalculator())->refresh([$this->student->id]);

        $ids = fn (array $f) => \App\Services\Marketing\StudentFilter::apply(\App\Services\Marketing\StudentFilter::base(), $f)->pluck('m.student_id')->all();
        $this->assertSame([$this->student->id], $ids(['days_to_exam_min' => 4, 'days_to_exam_max' => 7]));
        $this->assertSame([], $ids(['days_to_exam_min' => 29, 'days_to_exam_max' => 30]));
        $this->assertContains('exam_date_near', json_decode(DB::table('student_metrics')->where('student_id', $this->student->id)->value('segments'), true));
    }
}
