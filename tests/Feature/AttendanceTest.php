<?php

namespace Tests\Feature;

use App\Models\AcademicClass;
use App\Models\AcademicSection;
use App\Models\AcademicSession;
use App\Models\Attendance;
use App\Models\Enrollment;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AttendanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_cannot_get_attendance_unauthenticated(): void
    {
        $response = $this->getJson('/api/institutes/attendance?class_id=1&date=2026-08-23');
        $response->assertUnauthorized();
    }

    public function test_cannot_get_attendance_without_active_institute(): void
    {
        $user = User::factory()->create();
        $institute = Institute::create(['name' => 'Institute A']);

        $class = AcademicClass::create([
            'institute_id' => $institute->id,
            'name' => 'Class 1',
            'code' => 'C1',
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/institutes/attendance?class_id={$class->id}&date=2026-08-23");
        $response->assertStatus(422);
        $response->assertJson([
            'status' => 'error',
            'message' => 'No active institute is associated with this user',
        ]);
    }

    public function test_requires_date_parameter(): void
    {
        $user = User::factory()->create();
        $institute = Institute::create([
            'name' => 'Army Public School',
            'attendance_mode' => 'class',
        ]);

        InstituteUser::create([
            'user_id' => $user->id,
            'institute_id' => $institute->id,
            'is_active' => true,
        ]);

        $class = AcademicClass::create([
            'institute_id' => $institute->id,
            'name' => 'Grade 10',
            'code' => 'G10',
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/institutes/attendance?class_id={$class->id}");
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['date']);
    }

    public function test_can_get_attendance_in_class_mode(): void
    {
        $user = User::factory()->create(['name' => 'Teacher John']);
        $institute = Institute::create([
            'name' => 'Army Public School',
            'attendance_mode' => 'class',
        ]);

        InstituteUser::create([
            'user_id' => $user->id,
            'institute_id' => $institute->id,
            'is_active' => true,
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
            'name' => 'Grade 10',
            'code' => 'G10',
        ]);

        $section = AcademicSection::create([
            'class_id' => $class->id,
            'name' => 'Section A',
            'code' => 'SEC-A',
        ]);

        // Create students and enrollments
        $student1 = Student::create([
            'institute_id' => $institute->id,
            'first_name' => 'Ali',
            'last_name' => 'Khan',
            'dob' => '2010-05-15',
            'gender' => 'male',
            'guardian_name' => 'Khan Senior',
            'guardian_phone' => '03001234567',
            'admission_date' => '2025-01-10',
        ]);

        $student2 = Student::create([
            'institute_id' => $institute->id,
            'first_name' => 'Sara',
            'last_name' => 'Ahmed',
            'dob' => '2010-08-20',
            'gender' => 'female',
            'guardian_name' => 'Ahmed Senior',
            'guardian_phone' => '03007654321',
            'admission_date' => '2025-01-10',
        ]);

        $student3 = Student::create([
            'institute_id' => $institute->id,
            'first_name' => 'Bilal',
            'last_name' => 'Tariq',
            'dob' => '2010-11-25',
            'gender' => 'male',
            'guardian_name' => 'Tariq Senior',
            'guardian_phone' => '03009876543',
            'admission_date' => '2025-01-10',
        ]);

        Enrollment::create([
            'student_id' => $student1->id,
            'session_id' => $session->id,
            'class_id' => $class->id,
            'section_id' => $section->id,
            'roll_number' => 1,
        ]);

        Enrollment::create([
            'student_id' => $student2->id,
            'session_id' => $session->id,
            'class_id' => $class->id,
            'section_id' => $section->id,
            'roll_number' => 2,
        ]);

        Enrollment::create([
            'student_id' => $student3->id,
            'session_id' => $session->id,
            'class_id' => $class->id,
            'section_id' => $section->id,
            'roll_number' => 3,
        ]);

        $date = '2026-08-23';

        // Mark attendance for student 1 (present) and student 2 (absent)
        Attendance::create([
            'session_id' => $session->id,
            'class_id' => $class->id,
            'section_id' => $section->id,
            'subject_id' => null,
            'student_id' => $student1->id,
            'date' => $date,
            'status' => 'present',
            'marked_by_user_id' => $user->id,
        ]);

        Attendance::create([
            'session_id' => $session->id,
            'class_id' => $class->id,
            'section_id' => $section->id,
            'subject_id' => null,
            'student_id' => $student2->id,
            'date' => $date,
            'status' => 'absent',
            'marked_by_user_id' => $user->id,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/institutes/attendance?class_id={$class->id}&section_id={$section->id}&date={$date}");

        $response->assertOk();
        $response->assertJson([
            'status' => 'success',
            'message' => 'Attendance retrieved successfully',
            'data' => [
                'attendance_mode' => 'class',
                'session_id' => $session->id,
                'date' => $date,
                'class' => [
                    'id' => $class->id,
                    'name' => 'Grade 10',
                ],
                'section' => [
                    'id' => $section->id,
                    'name' => 'Section A',
                ],
                'subject' => null,
                'summary' => [
                    'total_students' => 3,
                    'present_count' => 1,
                    'absent_count' => 1,
                    'late_count' => 0,
                    'leave_count' => 0,
                    'unmarked_count' => 1,
                    'is_fully_marked' => false,
                ],
            ],
        ]);

        $records = $response->json('data.records');
        $this->assertCount(3, $records);
        $this->assertEquals('present', $records[0]['status']);
        $this->assertEquals('Teacher John', $records[0]['marked_by']['name']);
        $this->assertEquals('absent', $records[1]['status']);
        $this->assertNull($records[2]['status']); // unmarked

        // Also test records alias route
        $recordsResponse = $this->getJson("/api/institutes/attendance/records?class_id={$class->id}&section_id={$section->id}&date={$date}");
        $recordsResponse->assertOk();
        $recordsResponse->assertJsonCount(3, 'data.records');
    }

    public function test_can_get_attendance_in_subject_mode(): void
    {
        $user = User::factory()->create(['name' => 'Sir Tariq']);
        $institute = Institute::create([
            'name' => 'Beaconhouse School System',
            'attendance_mode' => 'subject',
        ]);

        InstituteUser::create([
            'user_id' => $user->id,
            'institute_id' => $institute->id,
            'is_active' => true,
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
            'name' => 'Grade 9',
            'code' => 'G9',
        ]);

        $subject = Subject::create([
            'institute_id' => $institute->id,
            'name' => 'Physics',
            'code' => 'PHY-9',
        ]);

        $student = Student::create([
            'institute_id' => $institute->id,
            'first_name' => 'Hamza',
            'last_name' => 'Ali',
            'dob' => '2011-03-10',
            'gender' => 'male',
            'guardian_name' => 'Ali Senior',
            'guardian_phone' => '03001112233',
            'admission_date' => '2025-01-10',
        ]);

        Enrollment::create([
            'student_id' => $student->id,
            'session_id' => $session->id,
            'class_id' => $class->id,
            'section_id' => null,
            'roll_number' => 1,
        ]);

        $date = '2026-08-23';

        Attendance::create([
            'session_id' => $session->id,
            'class_id' => $class->id,
            'section_id' => null,
            'subject_id' => $subject->id,
            'student_id' => $student->id,
            'date' => $date,
            'status' => 'late',
            'marked_by_user_id' => $user->id,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/institutes/attendance?class_id={$class->id}&subject_id={$subject->id}&date={$date}");

        $response->assertOk();
        $response->assertJson([
            'status' => 'success',
            'data' => [
                'attendance_mode' => 'subject',
                'subject' => [
                    'id' => $subject->id,
                    'name' => 'Physics',
                ],
                'summary' => [
                    'total_students' => 1,
                    'present_count' => 0,
                    'absent_count' => 0,
                    'late_count' => 1,
                    'leave_count' => 0,
                    'unmarked_count' => 0,
                    'is_fully_marked' => true,
                ],
            ],
        ]);
    }

    public function test_rejects_subject_id_in_class_mode(): void
    {
        $user = User::factory()->create();
        $institute = Institute::create([
            'name' => 'Test School',
            'attendance_mode' => 'class',
        ]);

        InstituteUser::create([
            'user_id' => $user->id,
            'institute_id' => $institute->id,
            'is_active' => true,
        ]);

        AcademicSession::create([
            'institute_id' => $institute->id,
            'name' => '2026-2027',
            'start_date' => '2026-08-01',
            'end_date' => '2027-06-30',
            'is_active' => true,
        ]);

        $class = AcademicClass::create([
            'institute_id' => $institute->id,
            'name' => 'Class 1',
            'code' => 'C1',
        ]);

        $subject = Subject::create([
            'institute_id' => $institute->id,
            'name' => 'Math',
            'code' => 'M1',
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/institutes/attendance?class_id={$class->id}&subject_id={$subject->id}&date=2026-08-23");

        $response->assertStatus(422);
        $response->assertJson([
            'status' => 'error',
            'message' => 'Validation failed',
            'errors' => [
                'subject_id' => ['Subject attendance is not available for a class-based institute.'],
            ],
        ]);
    }

    public function test_requires_subject_id_in_subject_mode(): void
    {
        $user = User::factory()->create();
        $institute = Institute::create([
            'name' => 'Test College',
            'attendance_mode' => 'subject',
        ]);

        InstituteUser::create([
            'user_id' => $user->id,
            'institute_id' => $institute->id,
            'is_active' => true,
        ]);

        AcademicSession::create([
            'institute_id' => $institute->id,
            'name' => '2026-2027',
            'start_date' => '2026-08-01',
            'end_date' => '2027-06-30',
            'is_active' => true,
        ]);

        $class = AcademicClass::create([
            'institute_id' => $institute->id,
            'name' => 'BSCS 1',
            'code' => 'BSCS1',
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/institutes/attendance?class_id={$class->id}&date=2026-08-23");

        $response->assertStatus(422);
        $response->assertJson([
            'status' => 'error',
            'message' => 'Validation failed',
            'errors' => [
                'subject_id' => ['A subject is required for a subject-based institute.'],
            ],
        ]);
    }
}
