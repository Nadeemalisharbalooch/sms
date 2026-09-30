<?php

namespace Tests\Feature;

use App\Models\AcademicClass;
use App\Models\AcademicSection;
use App\Models\AcademicSession;
use App\Models\Attendance;
use App\Models\Enrollment;
use App\Models\Institute;
use App\Models\InstituteSubscription;
use App\Models\InstituteUser;
use App\Models\Plan;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AttendanceReportTest extends TestCase
{
    use RefreshDatabase;

    private function makeInstitute(string $mode = 'class'): array
    {
        $user = User::factory()->create(['name' => 'Sir Kamran']);
        $institute = Institute::create(['name' => 'Army Public School', 'attendance_mode' => $mode]);

        InstituteUser::create([
            'user_id' => $user->id,
            'institute_id' => $institute->id,
            'is_owner' => true,
            'is_active' => true,
        ]);

        $plan = Plan::firstOrCreate(['name' => 'Trial'], [
            'price' => 0,
            'billing_interval' => 'monthly',
            'trial_days' => 14,
            'is_active' => true,
        ]);

        InstituteSubscription::create([
            'institute_id' => $institute->id,
            'plan_id' => $plan->id,
            'status' => 'trialing',
            'starts_at' => now(),
            'ends_at' => now()->addDays(14),
        ]);

        $session = AcademicSession::create([
            'institute_id' => $institute->id,
            'name' => '2026-2027',
            'start_date' => '2026-08-01',
            'end_date' => '2027-06-30',
            'is_active' => true,
        ]);

        $class = AcademicClass::create([
            'institute_id' => $institute->id,
            'name' => 'Class 10',
            'code' => 'C10',
        ]);

        $section = AcademicSection::create([
            'class_id' => $class->id,
            'name' => 'A',
            'code' => 'A',
        ]);

        $math = Subject::create(['institute_id' => $institute->id, 'name' => 'Maths', 'code' => 'M10']);
        $physics = Subject::create(['institute_id' => $institute->id, 'name' => 'Physics', 'code' => 'P10']);

        return compact('user', 'institute', 'session', 'class', 'section', 'math', 'physics');
    }

    private function makeStudent(array $ctx, string $first, string $last, int $roll): Student
    {
        $student = Student::create([
            'institute_id' => $ctx['institute']->id,
            'first_name' => $first,
            'last_name' => $last,
            'dob' => '2012-04-01',
            'gender' => 'male',
            'guardian_name' => 'Guardian of '.$first,
            'guardian_phone' => '03001234567',
            'admission_date' => '2025-04-01',
        ]);

        Enrollment::create([
            'student_id' => $student->id,
            'session_id' => $ctx['session']->id,
            'class_id' => $ctx['class']->id,
            'section_id' => $ctx['section']->id,
            'roll_number' => $roll,
        ]);

        return $student;
    }

    private function mark(array $ctx, Student $student, string $date, string $status, ?Subject $subject = null): void
    {
        Attendance::create([
            'session_id' => $ctx['session']->id,
            'class_id' => $ctx['class']->id,
            'section_id' => $ctx['section']->id,
            'subject_id' => $subject?->id,
            'student_id' => $student->id,
            'date' => $date,
            'status' => $status,
            'marked_by_user_id' => $ctx['user']->id,
        ]);
    }

    // ---------------------------------------------------------------- overview

    public function test_overview_requires_authentication(): void
    {
        $this->getJson('/api/institutes/attendance/overview?session_id=1&date=2026-09-26')
            ->assertUnauthorized();
    }

    public function test_overview_requires_session_and_date(): void
    {
        $ctx = $this->makeInstitute();
        Sanctum::actingAs($ctx['user']);

        $this->getJson('/api/institutes/attendance/overview')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['session_id', 'date']);
    }

    public function test_overview_rejects_session_from_another_institute(): void
    {
        $ctx = $this->makeInstitute();
        $other = $this->makeInstitute();
        Sanctum::actingAs($ctx['user']);

        $this->getJson("/api/institutes/attendance/overview?session_id={$other['session']->id}&date=2026-09-26")
            ->assertStatus(422)
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('errors.session_id.0', 'The selected session does not belong to the active institute.');
    }

    public function test_overview_class_mode_returns_snapshot(): void
    {
        $ctx = $this->makeInstitute();
        $bilal = $this->makeStudent($ctx, 'Muhammad', 'Bilal', 101);
        $ayesha = $this->makeStudent($ctx, 'Ayesha', 'Khan', 102);
        $unmarked = $this->makeStudent($ctx, 'Zara', 'Ahmed', 103);

        $this->mark($ctx, $bilal, '2026-09-26', 'present');
        $this->mark($ctx, $ayesha, '2026-09-26', 'absent');
        // $unmarked has no row for today

        Sanctum::actingAs($ctx['user']);

        $response = $this->getJson("/api/institutes/attendance/overview?session_id={$ctx['session']->id}&date=2026-09-26");

        $response->assertOk()->assertJsonPath('status', 'success')->assertJsonPath('data.date', '2026-09-26');

        $response->assertJsonPath('data.overall', [
            'total_students' => 3,
            'present' => 1,
            'absent' => 1,
            'late' => 0,
            'leave' => 0,
            'unmarked' => 1,
            'percentage' => 50,
        ]);

        // The per-class row must reconcile with the overall block: 1 + 1 + 0 + 0 + 1 = 3
        $class = $response->json('data.classes_status.0');
        $this->assertSame(3, $class['total']);
        $this->assertSame($class['total'], $class['present'] + $class['absent'] + $class['late'] + $class['leave'] + $class['unmarked']);
        $this->assertSame('Class 10', $class['class_name']);
        $this->assertSame('A', $class['section_name']);
        $this->assertFalse($class['is_marked']);
        $this->assertSame('Sir Kamran', $class['marked_by']);
    }

    public function test_overview_marks_class_when_every_student_is_marked(): void
    {
        $ctx = $this->makeInstitute();
        $bilal = $this->makeStudent($ctx, 'Muhammad', 'Bilal', 1);
        $ayesha = $this->makeStudent($ctx, 'Ayesha', 'Khan', 2);

        $this->mark($ctx, $bilal, '2026-09-26', 'present');
        $this->mark($ctx, $ayesha, '2026-09-26', 'late');

        Sanctum::actingAs($ctx['user']);

        $response = $this->getJson("/api/institutes/attendance/overview?session_id={$ctx['session']->id}&date=2026-09-26");

        $response->assertOk()->assertJsonPath('data.classes_status.0.is_marked', true);
        $response->assertJsonPath('data.overall.late', 1);
        $response->assertJsonPath('data.overall.percentage', 50);
    }

    public function test_overview_subject_mode_collapses_subjects_into_one_day_status(): void
    {
        $ctx = $this->makeInstitute('subject');
        $bilal = $this->makeStudent($ctx, 'Muhammad', 'Bilal', 1);
        $ayesha = $this->makeStudent($ctx, 'Ayesha', 'Khan', 2);

        // Bilal is present in maths but absent in physics -> the whole day is absent.
        $this->mark($ctx, $bilal, '2026-09-26', 'present', $ctx['math']);
        $this->mark($ctx, $bilal, '2026-09-26', 'absent', $ctx['physics']);
        $this->mark($ctx, $ayesha, '2026-09-26', 'present', $ctx['math']);
        $this->mark($ctx, $ayesha, '2026-09-26', 'present', $ctx['physics']);

        Sanctum::actingAs($ctx['user']);

        $response = $this->getJson("/api/institutes/attendance/overview?session_id={$ctx['session']->id}&date=2026-09-26");

        $response->assertOk()
            ->assertJsonPath('data.overall.total_students', 2)
            ->assertJsonPath('data.overall.present', 1)
            ->assertJsonPath('data.overall.absent', 1)
            ->assertJsonPath('data.overall.percentage', 50)
            ->assertJsonPath('data.classes_status.0.present', 1)
            ->assertJsonPath('data.classes_status.0.absent', 1);
    }

    // --------------------------------------------------------------- absentees

    public function test_absentees_defaults_to_absent_and_returns_guardian_details(): void
    {
        $ctx = $this->makeInstitute();
        $bilal = $this->makeStudent($ctx, 'Muhammad', 'Bilal', 101);
        $ayesha = $this->makeStudent($ctx, 'Ayesha', 'Khan', 102);

        $this->mark($ctx, $bilal, '2026-09-24', 'absent');
        $this->mark($ctx, $bilal, '2026-09-25', 'absent');
        $this->mark($ctx, $bilal, '2026-09-26', 'absent');
        $this->mark($ctx, $ayesha, '2026-09-26', 'present');

        Sanctum::actingAs($ctx['user']);

        $response = $this->getJson("/api/institutes/attendance/absentees?session_id={$ctx['session']->id}&date=2026-09-26");

        $response->assertOk()
            ->assertJsonPath('data.total_count', 1)
            ->assertJsonCount(1, 'data.records')
            ->assertJsonPath('data.records.0', [
                'student_id' => $bilal->id,
                'roll_number' => 101,
                'full_name' => 'Muhammad Bilal',
                'class_name' => 'Class 10',
                'section_name' => 'A',
                'guardian_name' => 'Guardian of Muhammad',
                'guardian_phone' => '03001234567',
                'status' => 'absent',
                'consecutive_absent_days' => 3,
                'overall_session_percentage' => 0,
            ]);
    }

    public function test_absentees_can_filter_by_status(): void
    {
        $ctx = $this->makeInstitute();
        $bilal = $this->makeStudent($ctx, 'Muhammad', 'Bilal', 1);
        $ayesha = $this->makeStudent($ctx, 'Ayesha', 'Khan', 2);
        $hamza = $this->makeStudent($ctx, 'Hamza', 'Ali', 3);

        $this->mark($ctx, $bilal, '2026-09-26', 'absent');
        $this->mark($ctx, $ayesha, '2026-09-26', 'leave');
        $this->mark($ctx, $hamza, '2026-09-26', 'late');

        Sanctum::actingAs($ctx['user']);

        $base = "/api/institutes/attendance/absentees?session_id={$ctx['session']->id}&date=2026-09-26";

        $this->getJson($base)->assertOk()->assertJsonPath('data.total_count', 1);
        $this->getJson($base.'&status=leave')->assertOk()->assertJsonPath('data.total_count', 1)->assertJsonPath('data.records.0.full_name', 'Ayesha Khan');
        $this->getJson($base.'&status=late')->assertOk()->assertJsonPath('data.total_count', 1)->assertJsonPath('data.records.0.full_name', 'Hamza Ali');
        $this->getJson($base.'&status=absent')->assertOk()->assertJsonPath('data.records.0.full_name', 'Muhammad Bilal');
    }

    public function test_absentees_rejects_invalid_status(): void
    {
        $ctx = $this->makeInstitute();
        Sanctum::actingAs($ctx['user']);

        $this->getJson("/api/institutes/attendance/absentees?session_id={$ctx['session']->id}&date=2026-09-26&status=present")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }

    public function test_absentees_streak_survives_unmarked_weekend_days(): void
    {
        $ctx = $this->makeInstitute();
        $bilal = $this->makeStudent($ctx, 'Muhammad', 'Bilal', 1);

        // Fri + Mon absent, Sat/Sun never marked -> still a 3 day working streak.
        $this->mark($ctx, $bilal, '2026-09-25', 'absent');
        $this->mark($ctx, $bilal, '2026-09-28', 'absent');
        $this->mark($ctx, $bilal, '2026-09-29', 'absent');

        Sanctum::actingAs($ctx['user']);

        $this->getJson("/api/institutes/attendance/absentees?session_id={$ctx['session']->id}&date=2026-09-29&consecutive_days=3")
            ->assertOk()
            ->assertJsonPath('data.total_count', 1)
            ->assertJsonPath('data.records.0.consecutive_absent_days', 3);
    }

    public function test_absentees_streak_breaks_on_a_marked_present_day(): void
    {
        $ctx = $this->makeInstitute();
        $bilal = $this->makeStudent($ctx, 'Muhammad', 'Bilal', 1);

        $this->mark($ctx, $bilal, '2026-09-25', 'absent');
        $this->mark($ctx, $bilal, '2026-09-28', 'present');
        $this->mark($ctx, $bilal, '2026-09-29', 'absent');

        Sanctum::actingAs($ctx['user']);

        $this->getJson("/api/institutes/attendance/absentees?session_id={$ctx['session']->id}&date=2026-09-29&consecutive_days=3")
            ->assertOk()
            ->assertJsonPath('data.total_count', 0);

        $this->getJson("/api/institutes/attendance/absentees?session_id={$ctx['session']->id}&date=2026-09-29")
            ->assertOk()
            ->assertJsonPath('data.records.0.consecutive_absent_days', 1);
    }

    public function test_absentees_percentage_treats_late_as_not_present(): void
    {
        $ctx = $this->makeInstitute();
        $bilal = $this->makeStudent($ctx, 'Muhammad', 'Bilal', 1);

        $this->mark($ctx, $bilal, '2026-09-24', 'present');
        $this->mark($ctx, $bilal, '2026-09-25', 'late');
        $this->mark($ctx, $bilal, '2026-09-26', 'absent');

        Sanctum::actingAs($ctx['user']);

        // present / (present + late + absent) = 1/3 = 33.33
        $this->getJson("/api/institutes/attendance/absentees?session_id={$ctx['session']->id}&date=2026-09-26")
            ->assertOk()
            ->assertJsonPath('data.records.0.overall_session_percentage', 33.33);
    }

    public function test_absentees_can_be_scoped_to_a_class(): void
    {
        $ctx = $this->makeInstitute();
        $bilal = $this->makeStudent($ctx, 'Muhammad', 'Bilal', 1);
        $this->mark($ctx, $bilal, '2026-09-26', 'absent');

        $otherClass = AcademicClass::create([
            'institute_id' => $ctx['institute']->id,
            'name' => 'Class 9',
            'code' => 'C9',
        ]);
        $stranger = Student::create([
            'institute_id' => $ctx['institute']->id,
            'first_name' => 'Bilal',
            'last_name' => 'Ghost',
            'dob' => '2012-04-01',
            'gender' => 'male',
            'guardian_name' => 'Ghost Guardian',
            'guardian_phone' => '03009999999',
            'admission_date' => '2025-04-01',
        ]);
        Enrollment::create([
            'student_id' => $stranger->id,
            'session_id' => $ctx['session']->id,
            'class_id' => $otherClass->id,
            'section_id' => null,
            'roll_number' => 1,
        ]);
        Attendance::create([
            'session_id' => $ctx['session']->id,
            'class_id' => $otherClass->id,
            'section_id' => null,
            'subject_id' => null,
            'student_id' => $stranger->id,
            'date' => '2026-09-26',
            'status' => 'absent',
            'marked_by_user_id' => $ctx['user']->id,
        ]);

        Sanctum::actingAs($ctx['user']);

        $this->getJson("/api/institutes/attendance/absentees?session_id={$ctx['session']->id}&date=2026-09-26")
            ->assertOk()
            ->assertJsonPath('data.total_count', 2);

        $this->getJson("/api/institutes/attendance/absentees?session_id={$ctx['session']->id}&date=2026-09-26&class_id={$ctx['class']->id}")
            ->assertOk()
            ->assertJsonPath('data.total_count', 1)
            ->assertJsonPath('data.records.0.student_id', $bilal->id);
    }

    public function test_absentees_rejects_class_from_another_institute(): void
    {
        $ctx = $this->makeInstitute();
        $other = $this->makeInstitute();
        Sanctum::actingAs($ctx['user']);

        $this->getJson("/api/institutes/attendance/absentees?session_id={$ctx['session']->id}&date=2026-09-26&class_id={$other['class']->id}")
            ->assertStatus(422)
            ->assertJsonPath('status', 'error');
    }

    public function test_absentees_subject_mode_counts_days_not_subject_rows(): void
    {
        $ctx = $this->makeInstitute('subject');
        $bilal = $this->makeStudent($ctx, 'Muhammad', 'Bilal', 1);

        // Present in one subject, absent in the other, on both days -> 2 absent days out of 2.
        $this->mark($ctx, $bilal, '2026-09-25', 'present', $ctx['math']);
        $this->mark($ctx, $bilal, '2026-09-25', 'absent', $ctx['physics']);
        $this->mark($ctx, $bilal, '2026-09-26', 'present', $ctx['math']);
        $this->mark($ctx, $bilal, '2026-09-26', 'absent', $ctx['physics']);

        Sanctum::actingAs($ctx['user']);

        $this->getJson("/api/institutes/attendance/absentees?session_id={$ctx['session']->id}&date=2026-09-26")
            ->assertOk()
            ->assertJsonPath('data.total_count', 1)
            ->assertJsonPath('data.records.0.consecutive_absent_days', 2)
            ->assertJsonPath('data.records.0.overall_session_percentage', 0.0);
    }

    // --------------------------------------------------------- monthly register

    public function test_monthly_register_returns_day_matrix(): void
    {
        $ctx = $this->makeInstitute();
        $bilal = $this->makeStudent($ctx, 'Muhammad', 'Bilal', 101);

        $this->mark($ctx, $bilal, '2026-09-01', 'present');
        $this->mark($ctx, $bilal, '2026-09-02', 'present');
        $this->mark($ctx, $bilal, '2026-09-03', 'absent');
        $this->mark($ctx, $bilal, '2026-09-04', 'late');
        $this->mark($ctx, $bilal, '2026-09-05', 'leave');

        Sanctum::actingAs($ctx['user']);

        $response = $this->getJson("/api/institutes/attendance/monthly-register?session_id={$ctx['session']->id}&class_id={$ctx['class']->id}&month=2026-09");

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.month', '2026-09')
            ->assertJsonPath('data.working_days_count', 5)
            ->assertJsonCount(30, 'data.days_header')
            ->assertJsonPath('data.days_header.0', [
                'day' => 1,
                'date' => '2026-09-01',
                'day_name' => 'Tue',
                'is_weekend' => false,
            ])
            ->assertJsonPath('data.days_header.5.is_weekend', true)
            ->assertJsonPath('data.students.0.daily_status', [
                '2026-09-01' => 'present',
                '2026-09-02' => 'present',
                '2026-09-03' => 'absent',
                '2026-09-04' => 'late',
                '2026-09-05' => 'leave',
            ])
            ->assertJsonPath('data.students.0.summary', [
                'present' => 2,
                'absent' => 1,
                'late' => 1,
                'leave' => 1,
                'percentage' => 40,
            ])
            ->assertJsonPath('data.students.0.guardian_name', 'Guardian of Muhammad');
    }

    public function test_monthly_register_requires_month(): void
    {
        $ctx = $this->makeInstitute();
        Sanctum::actingAs($ctx['user']);

        $this->getJson("/api/institutes/attendance/monthly-register?session_id={$ctx['session']->id}&class_id={$ctx['class']->id}")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['month']);
    }

    public function test_monthly_register_subject_mode_has_one_status_per_day(): void
    {
        $ctx = $this->makeInstitute('subject');
        $bilal = $this->makeStudent($ctx, 'Muhammad', 'Bilal', 1);

        $this->mark($ctx, $bilal, '2026-09-01', 'present', $ctx['math']);
        $this->mark($ctx, $bilal, '2026-09-01', 'present', $ctx['physics']);
        $this->mark($ctx, $bilal, '2026-09-02', 'present', $ctx['math']);
        $this->mark($ctx, $bilal, '2026-09-02', 'absent', $ctx['physics']);
        $this->mark($ctx, $bilal, '2026-09-03', 'present', $ctx['math']);
        $this->mark($ctx, $bilal, '2026-09-03', 'present', $ctx['physics']);

        Sanctum::actingAs($ctx['user']);

        $response = $this->getJson("/api/institutes/attendance/monthly-register?session_id={$ctx['session']->id}&class_id={$ctx['class']->id}&month=2026-09");

        $dailyStatus = $response->json('data.students.0.daily_status');

        // 3 calendar days, not 6 subject rows.
        $this->assertCount(3, $dailyStatus);
        $this->assertSame('present', $dailyStatus['2026-09-01']);
        $this->assertSame('absent', $dailyStatus['2026-09-02'], 'absent in any subject must make the whole day absent');
        $this->assertSame('present', $dailyStatus['2026-09-03']);

        $response->assertJsonPath('data.students.0.summary', [
            'present' => 2,
            'absent' => 1,
            'late' => 0,
            'leave' => 0,
            'percentage' => 66.67,
        ])->assertJsonPath('data.working_days_count', 3);
    }

    public function test_monthly_register_subject_mode_can_scope_to_one_subject(): void
    {
        $ctx = $this->makeInstitute('subject');
        $bilal = $this->makeStudent($ctx, 'Muhammad', 'Bilal', 1);

        $this->mark($ctx, $bilal, '2026-09-01', 'present', $ctx['math']);
        $this->mark($ctx, $bilal, '2026-09-01', 'absent', $ctx['physics']);
        $this->mark($ctx, $bilal, '2026-09-02', 'present', $ctx['math']);
        $this->mark($ctx, $bilal, '2026-09-02', 'present', $ctx['physics']);

        Sanctum::actingAs($ctx['user']);

        $response = $this->getJson("/api/institutes/attendance/monthly-register?session_id={$ctx['session']->id}&class_id={$ctx['class']->id}&month=2026-09&subject_id={$ctx['math']->id}");

        $response->assertOk()
            ->assertJsonPath('data.students.0.daily_status', [
                '2026-09-01' => 'present',
                '2026-09-02' => 'present',
            ])
            ->assertJsonPath('data.students.0.summary.present', 2)
            ->assertJsonPath('data.students.0.summary.percentage', 100);
    }

    public function test_monthly_register_rejects_subject_id_in_class_mode(): void
    {
        $ctx = $this->makeInstitute();
        Sanctum::actingAs($ctx['user']);

        $this->getJson("/api/institutes/attendance/monthly-register?session_id={$ctx['session']->id}&class_id={$ctx['class']->id}&month=2026-09&subject_id={$ctx['math']->id}")
            ->assertStatus(422)
            ->assertJsonPath('errors.subject_id.0', 'Subject attendance is not available for a class-based institute.');
    }

    public function test_monthly_register_sorts_roll_numbers_numerically(): void
    {
        $ctx = $this->makeInstitute();
        $this->makeStudent($ctx, 'Roll', 'Ten', 10);
        $this->makeStudent($ctx, 'Roll', 'Two', 2);
        $this->makeStudent($ctx, 'Roll', 'Nine', 9);

        Sanctum::actingAs($ctx['user']);

        $rolls = $this->getJson("/api/institutes/attendance/monthly-register?session_id={$ctx['session']->id}&class_id={$ctx['class']->id}&month=2026-09")
            ->assertOk()
            ->json('data.students.*.roll_number');

        $this->assertSame([2, 9, 10], $rolls);
    }

    public function test_monthly_register_can_scope_to_a_section(): void
    {
        $ctx = $this->makeInstitute();
        $this->makeStudent($ctx, 'In', 'A', 1);

        $sectionB = AcademicSection::create(['class_id' => $ctx['class']->id, 'name' => 'B', 'code' => 'B']);
        $other = Student::create([
            'institute_id' => $ctx['institute']->id,
            'first_name' => 'In',
            'last_name' => 'B',
            'dob' => '2012-04-01',
            'gender' => 'male',
            'guardian_name' => 'Guardian B',
            'guardian_phone' => '03001234567',
            'admission_date' => '2025-04-01',
        ]);
        Enrollment::create([
            'student_id' => $other->id,
            'session_id' => $ctx['session']->id,
            'class_id' => $ctx['class']->id,
            'section_id' => $sectionB->id,
            'roll_number' => 1,
        ]);

        Sanctum::actingAs($ctx['user']);

        $this->getJson("/api/institutes/attendance/monthly-register?session_id={$ctx['session']->id}&class_id={$ctx['class']->id}&section_id={$sectionB->id}&month=2026-09")
            ->assertOk()
            ->assertJsonCount(1, 'data.students')
            ->assertJsonPath('data.students.0.full_name', 'In B');
    }

    // ----------------------------------------------------------- class summary

    public function test_class_summary_reports_per_student_totals(): void
    {
        $ctx = $this->makeInstitute();
        $bilal = $this->makeStudent($ctx, 'Muhammad', 'Bilal', 101);

        foreach (['2026-08-03', '2026-08-04', '2026-08-05', '2026-09-01', '2026-09-02'] as $date) {
            $this->mark($ctx, $bilal, $date, 'present');
        }
        $this->mark($ctx, $bilal, '2026-08-06', 'absent');
        $this->mark($ctx, $bilal, '2026-08-07', 'late');
        $this->mark($ctx, $bilal, '2026-08-10', 'leave');

        Sanctum::actingAs($ctx['user']);

        $response = $this->getJson("/api/institutes/attendance/class-summary?session_id={$ctx['session']->id}&class_id={$ctx['class']->id}&start_date=2026-08-01&end_date=2026-09-30");

        $response->assertOk()
            ->assertJsonPath('data.total_working_days', 8)
            ->assertJsonPath('data.students.0', [
                'student_id' => $bilal->id,
                'roll_number' => 101,
                'full_name' => 'Muhammad Bilal',
                'guardian_phone' => '03001234567',
                'present' => 5,
                'absent' => 1,
                'late' => 1,
                'leave' => 1,
                'percentage' => 62.5,
            ]);
    }

    public function test_class_summary_filters_by_below_percentage(): void
    {
        $ctx = $this->makeInstitute();
        $good = $this->makeStudent($ctx, 'Good', 'Student', 1);
        $bad = $this->makeStudent($ctx, 'Bad', 'Student', 2);

        foreach (range(1, 9) as $i) {
            $this->mark($ctx, $good, "2026-08-0{$i}", 'present');
            $this->mark($ctx, $bad, "2026-08-0{$i}", $i <= 5 ? 'present' : 'absent');
        }

        Sanctum::actingAs($ctx['user']);

        $this->getJson("/api/institutes/attendance/class-summary?session_id={$ctx['session']->id}&class_id={$ctx['class']->id}&start_date=2026-08-01&end_date=2026-08-31&below_percentage=75")
            ->assertOk()
            ->assertJsonCount(1, 'data.students')
            ->assertJsonPath('data.students.0.full_name', 'Bad Student')
            ->assertJsonPath('data.students.0.percentage', 55.56);
    }

    public function test_class_summary_rejects_end_date_before_start_date(): void
    {
        $ctx = $this->makeInstitute();
        Sanctum::actingAs($ctx['user']);

        $this->getJson("/api/institutes/attendance/class-summary?session_id={$ctx['session']->id}&class_id={$ctx['class']->id}&start_date=2026-09-30&end_date=2026-09-01")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['end_date']);
    }

    public function test_class_summary_subject_mode_counts_days_not_rows(): void
    {
        $ctx = $this->makeInstitute('subject');
        $bilal = $this->makeStudent($ctx, 'Muhammad', 'Bilal', 1);

        $this->mark($ctx, $bilal, '2026-08-03', 'present', $ctx['math']);
        $this->mark($ctx, $bilal, '2026-08-03', 'absent', $ctx['physics']);
        $this->mark($ctx, $bilal, '2026-08-04', 'present', $ctx['math']);
        $this->mark($ctx, $bilal, '2026-08-04', 'present', $ctx['physics']);

        Sanctum::actingAs($ctx['user']);

        $response = $this->getJson("/api/institutes/attendance/class-summary?session_id={$ctx['session']->id}&class_id={$ctx['class']->id}&start_date=2026-08-01&end_date=2026-08-31");

        $response->assertOk()
            ->assertJsonPath('data.total_working_days', 2)
            ->assertJsonPath('data.students.0.present', 1)
            ->assertJsonPath('data.students.0.absent', 1)
            ->assertJsonPath('data.students.0.percentage', 50);
    }

    // -------------------------------------------------------------- student

    public function test_student_report_returns_summary_trend_and_logs(): void
    {
        $ctx = $this->makeInstitute();
        $bilal = $this->makeStudent($ctx, 'Muhammad', 'Bilal', 101);

        foreach (['2026-08-03', '2026-08-04', '2026-08-05'] as $date) {
            $this->mark($ctx, $bilal, $date, 'present');
        }
        $this->mark($ctx, $bilal, '2026-08-06', 'absent');
        $this->mark($ctx, $bilal, '2026-09-01', 'present');
        $this->mark($ctx, $bilal, '2026-09-02', 'absent');

        Sanctum::actingAs($ctx['user']);

        $response = $this->getJson("/api/institutes/attendance/student/{$bilal->id}?session_id={$ctx['session']->id}");

        $response->assertOk()->assertJsonPath('data.student', [
            'id' => $bilal->id,
            'full_name' => 'Muhammad Bilal',
            'roll_number' => 101,
            'class_name' => 'Class 10',
            'section_name' => 'A',
        ]);

        // 6 distinct marked days, not the number of rows.
        $response->assertJsonPath('data.session_summary', [
            'total_working_days' => 6,
            'present' => 4,
            'absent' => 2,
            'late' => 0,
            'leave' => 0,
            'percentage' => 66.67,
        ]);

        $response->assertJsonCount(2, 'data.monthly_trend')
            ->assertJsonPath('data.monthly_trend.0', [
                'month' => 'Aug 2026',
                'working_days' => 4,
                'present' => 3,
                'absent' => 1,
                'late' => 0,
                'leave' => 0,
                'percentage' => 75,
            ])
            ->assertJsonPath('data.monthly_trend.1.month', 'Sep 2026');

        $response->assertJsonCount(6, 'data.daily_logs')
            ->assertJsonPath('data.daily_logs.0', [
                'date' => '2026-08-03',
                'day' => 'Monday',
                'status' => 'present',
                'subject_name' => null,
            ])
            ->assertJsonPath('data.daily_logs.5.status', 'absent');
    }

    public function test_student_report_month_filter_only_narrows_daily_logs(): void
    {
        $ctx = $this->makeInstitute();
        $bilal = $this->makeStudent($ctx, 'Muhammad', 'Bilal', 1);

        $this->mark($ctx, $bilal, '2026-08-03', 'present');
        $this->mark($ctx, $bilal, '2026-08-04', 'absent');
        $this->mark($ctx, $bilal, '2026-09-01', 'present');
        $this->mark($ctx, $bilal, '2026-09-02', 'present');

        Sanctum::actingAs($ctx['user']);

        $response = $this->getJson("/api/institutes/attendance/student/{$bilal->id}?session_id={$ctx['session']->id}&month=2026-09");

        // Only daily_logs is narrowed to September.
        $response->assertOk()
            ->assertJsonPath('data.session_summary.total_working_days', 4)
            ->assertJsonPath('data.session_summary.present', 3)
            ->assertJsonPath('data.session_summary.absent', 1)
            ->assertJsonPath('data.session_summary.percentage', 75)
            ->assertJsonCount(2, 'data.monthly_trend')
            ->assertJsonCount(2, 'data.daily_logs')
            ->assertJsonPath('data.daily_logs.0.date', '2026-09-01')
            ->assertJsonPath('data.daily_logs.1.date', '2026-09-02');
    }

    public function test_student_report_subject_mode_has_one_log_per_day(): void
    {
        $ctx = $this->makeInstitute('subject');
        $bilal = $this->makeStudent($ctx, 'Muhammad', 'Bilal', 1);

        $this->mark($ctx, $bilal, '2026-09-01', 'present', $ctx['math']);
        $this->mark($ctx, $bilal, '2026-09-01', 'absent', $ctx['physics']);
        $this->mark($ctx, $bilal, '2026-09-02', 'present', $ctx['math']);
        $this->mark($ctx, $bilal, '2026-09-02', 'present', $ctx['physics']);

        Sanctum::actingAs($ctx['user']);

        $response = $this->getJson("/api/institutes/attendance/student/{$bilal->id}?session_id={$ctx['session']->id}");

        $response->assertOk()
            ->assertJsonPath('data.session_summary.total_working_days', 2)
            ->assertJsonPath('data.session_summary.present', 1)
            ->assertJsonPath('data.session_summary.absent', 1)
            ->assertJsonPath('data.session_summary.percentage', 50)
            ->assertJsonCount(2, 'data.daily_logs')
            ->assertJsonPath('data.daily_logs.0.date', '2026-09-01')
            ->assertJsonPath('data.daily_logs.0.status', 'absent')
            ->assertJsonPath('data.daily_logs.0.subject_name', 'Maths, Physics');
    }

    public function test_student_report_subject_mode_scoped_to_one_subject(): void
    {
        $ctx = $this->makeInstitute('subject');
        $bilal = $this->makeStudent($ctx, 'Muhammad', 'Bilal', 1);

        $this->mark($ctx, $bilal, '2026-09-01', 'present', $ctx['math']);
        $this->mark($ctx, $bilal, '2026-09-01', 'absent', $ctx['physics']);

        Sanctum::actingAs($ctx['user']);

        $this->getJson("/api/institutes/attendance/student/{$bilal->id}?session_id={$ctx['session']->id}&subject_id={$ctx['math']->id}")
            ->assertOk()
            ->assertJsonPath('data.session_summary.present', 1)
            ->assertJsonPath('data.session_summary.percentage', 100)
            ->assertJsonPath('data.daily_logs.0.subject_name', 'Maths');
    }

    public function test_student_report_rejects_subject_id_in_class_mode(): void
    {
        $ctx = $this->makeInstitute();
        $bilal = $this->makeStudent($ctx, 'Muhammad', 'Bilal', 1);
        Sanctum::actingAs($ctx['user']);

        $this->getJson("/api/institutes/attendance/student/{$bilal->id}?session_id={$ctx['session']->id}&subject_id={$ctx['math']->id}")
            ->assertStatus(422)
            ->assertJsonPath('errors.subject_id.0', 'Subject attendance is not available for a class-based institute.');
    }

    public function test_student_report_404s_for_student_outside_the_institute(): void
    {
        $ctx = $this->makeInstitute();
        $other = $this->makeInstitute();
        $stranger = $this->makeStudent($other, 'Some', 'Student', 1);
        Sanctum::actingAs($ctx['user']);

        $this->getJson("/api/institutes/attendance/student/{$stranger->id}?session_id={$ctx['session']->id}")
            ->assertNotFound()
            ->assertJsonPath('status', 'not found');
    }

    public function test_student_report_404s_when_not_enrolled_in_the_session(): void
    {
        $ctx = $this->makeInstitute();
        $student = Student::create([
            'institute_id' => $ctx['institute']->id,
            'first_name' => 'Not',
            'last_name' => 'Enrolled',
            'dob' => '2012-04-01',
            'gender' => 'male',
            'guardian_name' => 'Guardian',
            'guardian_phone' => '03001234567',
            'admission_date' => '2025-04-01',
        ]);
        Sanctum::actingAs($ctx['user']);

        $this->getJson("/api/institutes/attendance/student/{$student->id}?session_id={$ctx['session']->id}")
            ->assertNotFound()
            ->assertJsonPath('message', 'Student is not enrolled in the selected session');
    }

    public function test_student_report_requires_session_id(): void
    {
        $ctx = $this->makeInstitute();
        Sanctum::actingAs($ctx['user']);

        $this->getJson('/api/institutes/attendance/student/1')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['session_id']);
    }

    public function test_student_report_rejects_non_numeric_student_id(): void
    {
        $ctx = $this->makeInstitute();
        Sanctum::actingAs($ctx['user']);

        $this->getJson("/api/institutes/attendance/streeeeet?session_id={$ctx['session']->id}")->assertNotFound();
    }

    public function test_reports_do_not_leak_other_institutes_students(): void
    {
        $ctx = $this->makeInstitute();
        $other = $this->makeInstitute();
        $stranger = $this->makeStudent($other, 'Some', 'Student', 1);
        $this->mark($other, $stranger, '2026-09-26', 'absent');
        Sanctum::actingAs($ctx['user']);

        $this->getJson("/api/institutes/attendance/absentees?session_id={$ctx['session']->id}&date=2026-09-26")
            ->assertOk()
            ->assertJsonPath('data.total_count', 0);

        $this->getJson("/api/institutes/attendance/overview?session_id={$ctx['session']->id}&date=2026-09-26")
            ->assertOk()
            ->assertJsonPath('data.overall.total_students', 0);
    }

    public function test_unmarked_days_do_not_inflate_the_roll_up(): void
    {
        $ctx = $this->makeInstitute();
        $bilal = $this->makeStudent($ctx, 'Muhammad', 'Bilal', 1);
        $this->mark($ctx, $bilal, '2026-09-01', 'present');
        Sanctum::actingAs($ctx['user']);

        $response = $this->getJson("/api/institutes/attendance/monthly-register?session_id={$ctx['session']->id}&class_id={$ctx['class']->id}&month=2026-09");

        // Only one day was ever marked -> working days and summary must both reflect that.
        $response->assertOk()
            ->assertJsonPath('data.working_days_count', 1)
            ->assertJsonPath('data.students.0.summary', [
                'present' => 1,
                'absent' => 0,
                'late' => 0,
                'leave' => 0,
                'percentage' => 100.0,
            ]);
    }
}
