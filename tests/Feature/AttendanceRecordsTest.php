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
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AttendanceRecordsTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Institute $institute;

    private AcademicSession $session;

    private AcademicClass $class;

    private AcademicSection $section;

    private Student $student;

    private Student $secondStudent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();

        $this->institute = Institute::create(['name' => 'Records School']);

        InstituteUser::create([
            'user_id' => $this->owner->id,
            'institute_id' => $this->institute->id,
            'is_owner' => true,
            'is_active' => true,
        ]);

        $plan = Plan::firstOrCreate(
            ['name' => 'Trial'],
            [
                'price' => 0,
                'billing_interval' => 'monthly',
                'trial_days' => 14,
                'is_active' => true,
            ]
        );

        InstituteSubscription::create([
            'institute_id' => $this->institute->id,
            'plan_id' => $plan->id,
            'status' => 'trialing',
            'starts_at' => now(),
            'ends_at' => now()->addDays(14),
        ]);

        $this->session = AcademicSession::create([
            'institute_id' => $this->institute->id,
            'name' => '2025-2026',
            'start_date' => now()->subMonths(6),
            'end_date' => now()->addMonths(6),
            'is_active' => true,
        ]);

        $this->class = AcademicClass::create(['institute_id' => $this->institute->id, 'code' => 'G3', 'name' => 'Grade 3']);
        $this->section = AcademicSection::create(['class_id' => $this->class->id, 'code' => 'A', 'name' => 'A']);

        $this->student = Student::create([
            'institute_id' => $this->institute->id,
            'first_name' => 'Ali',
            'last_name' => 'Khan',
            'dob' => '2016-05-10',
            'gender' => 'male',
            'guardian_name' => 'Ahmed Khan',
            'guardian_phone' => '03001234567',
            'admission_date' => '2025-08-01',
        ]);

        $this->secondStudent = Student::create([
            'institute_id' => $this->institute->id,
            'first_name' => 'Sara',
            'last_name' => 'Ahmed',
            'dob' => '2016-08-20',
            'gender' => 'female',
            'guardian_name' => 'Imran Ahmed',
            'guardian_phone' => '03001234568',
            'admission_date' => '2025-08-01',
        ]);

        Enrollment::create([
            'student_id' => $this->student->id,
            'session_id' => $this->session->id,
            'class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'roll_number' => 1,
        ]);

        Enrollment::create([
            'student_id' => $this->secondStudent->id,
            'session_id' => $this->session->id,
            'class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'roll_number' => 2,
        ]);

        Sanctum::actingAs($this->owner);
    }

    private function addAttendance(Student $student, string $date, string $status): Attendance
    {
        return Attendance::create([
            'session_id' => $this->session->id,
            'class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'student_id' => $student->id,
            'date' => $date,
            'status' => $status,
            'marked_by_user_id' => $this->owner->id,
        ]);
    }

    public function test_records_returns_all_records_with_summary_and_student_details(): void
    {
        $this->addAttendance($this->student, '2026-09-01', 'present');
        $this->addAttendance($this->secondStudent, '2026-09-01', 'absent');
        $this->addAttendance($this->student, '2026-09-02', 'late');

        $response = $this->getJson('/api/institutes/attendance/records');

        $response->assertOk()
            ->assertJsonPath('data.summary.total_records', 3)
            ->assertJsonPath('data.summary.present_count', 1)
            ->assertJsonPath('data.summary.absent_count', 1)
            ->assertJsonPath('data.summary.late_count', 1)
            ->assertJsonCount(3, 'data.records')
            ->assertJsonMissingPath('data.pagination')
            ->assertJsonStructure([
                'data' => [
                    'attendance_mode',
                    'filters',
                    'summary',
                    'records' => [
                        ['id', 'date', 'status', 'student' => ['id', 'full_name', 'roll_number'], 'class' => ['id', 'name'], 'section' => ['id', 'name']],
                    ],
                ],
            ]);
    }

    public function test_records_can_be_filtered_by_status(): void
    {
        $this->addAttendance($this->student, '2026-09-01', 'present');
        $this->addAttendance($this->secondStudent, '2026-09-01', 'absent');
        $this->addAttendance($this->student, '2026-09-02', 'late');

        $response = $this->getJson('/api/institutes/attendance/records?status=present');

        $response->assertOk()
            ->assertJsonPath('data.summary.total_records', 1)
            ->assertJsonPath('data.records.0.status', 'present');
    }

    public function test_records_can_be_filtered_by_date_range(): void
    {
        $this->addAttendance($this->student, '2026-09-01', 'present');
        $this->addAttendance($this->secondStudent, '2026-09-02', 'absent');
        $this->addAttendance($this->student, '2026-09-05', 'leave');

        $response = $this->getJson('/api/institutes/attendance/records?date_from=2026-09-01&date_to=2026-09-02');

        $response->assertOk()
            ->assertJsonPath('data.summary.total_records', 2)
            ->assertJsonPath('data.summary.present_count', 1)
            ->assertJsonPath('data.summary.absent_count', 1);
    }

    public function test_records_can_be_filtered_by_student_id_and_search(): void
    {
        $this->addAttendance($this->student, '2026-09-01', 'present');
        $this->addAttendance($this->secondStudent, '2026-09-01', 'absent');

        $this->getJson('/api/institutes/attendance/records?student_id='.$this->student->id)
            ->assertOk()
            ->assertJsonPath('data.summary.total_records', 1)
            ->assertJsonPath('data.records.0.student.id', $this->student->id);

        $this->getJson('/api/institutes/attendance/records?search=Ahmed')
            ->assertOk()
            ->assertJsonPath('data.summary.total_records', 1)
            ->assertJsonPath('data.records.0.student.full_name', 'Sara Ahmed');

        $this->getJson('/api/institutes/attendance/records?search=2')
            ->assertOk()
            ->assertJsonPath('data.summary.total_records', 1)
            ->assertJsonPath('data.records.0.student.roll_number', 2);
    }

    public function test_records_scoped_only_to_active_institute(): void
    {
        $otherOwner = User::factory()->create();
        $otherInstitute = Institute::create(['name' => 'Foreign School']);

        InstituteUser::create([
            'user_id' => $otherOwner->id,
            'institute_id' => $otherInstitute->id,
            'is_owner' => true,
            'is_active' => true,
        ]);

        $otherSession = AcademicSession::create([
            'institute_id' => $otherInstitute->id,
            'name' => '2025-2026',
            'start_date' => now()->subMonths(6),
            'end_date' => now()->addMonths(6),
            'is_active' => true,
        ]);
        $otherClass = AcademicClass::create(['institute_id' => $otherInstitute->id, 'code' => 'G4', 'name' => 'Grade 4']);

        $foreignStudent = Student::create([
            'institute_id' => $otherInstitute->id,
            'first_name' => 'Una',
            'last_name' => 'Other',
            'dob' => '2016-01-01',
            'gender' => 'female',
            'guardian_name' => 'Guardian Other',
            'guardian_phone' => '03001234569',
            'admission_date' => '2025-08-01',
        ]);

        Attendance::create([
            'session_id' => $otherSession->id,
            'class_id' => $otherClass->id,
            'student_id' => $foreignStudent->id,
            'date' => '2026-09-01',
            'status' => 'present',
            'marked_by_user_id' => $otherOwner->id,
        ]);

        $this->addAttendance($this->student, '2026-09-01', 'present');

        // A session from another institute must be rejected.
        $this->getJson('/api/institutes/attendance/records?session_id='.$otherSession->id)
            ->assertStatus(422);

        // Without explicit filters only own records are returned.
        $this->getJson('/api/institutes/attendance/records')
            ->assertOk()
            ->assertJsonPath('data.summary.total_records', 1)
            ->assertJsonPath('data.records.0.student.id', $this->student->id);
    }
}
