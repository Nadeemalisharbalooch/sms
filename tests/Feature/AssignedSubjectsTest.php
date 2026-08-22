<?php

namespace Tests\Feature;

use App\Models\AcademicClass;
use App\Models\AcademicSection;
use App\Models\AcademicSession;
use App\Models\ClassSubject;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\Subject;
use App\Models\SubjectAllocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AssignedSubjectsTest extends TestCase
{
    use RefreshDatabase;

    public function test_cannot_access_assigned_subjects_unauthenticated(): void
    {
        $response = $this->getJson('/api/institutes/assigned-subjects');
        $response->assertUnauthorized();
    }

    public function test_cannot_access_assigned_subjects_without_active_institute(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/institutes/assigned-subjects');
        $response->assertStatus(422);
        $response->assertJson([
            'status' => 'error',
            'message' => 'No active institute is associated with this user',
        ]);
    }

    public function test_can_list_assigned_subjects_for_active_institute(): void
    {
        $user = User::factory()->create();
        $institute = Institute::create(['name' => 'The City School']);

        InstituteUser::create([
            'user_id' => $user->id,
            'institute_id' => $institute->id,
            'is_active' => true,
        ]);

        $class = AcademicClass::create([
            'institute_id' => $institute->id,
            'name' => 'Grade 1',
            'code' => 'GRADE-1',
        ]);

        $section = AcademicSection::create([
            'class_id' => $class->id,
            'name' => 'Section A',
            'code' => 'SECTION-A',
        ]);

        $subject1 = Subject::create([
            'institute_id' => $institute->id,
            'name' => 'Mathematics',
            'code' => 'MATH-1',
        ]);

        $subject2 = Subject::create([
            'institute_id' => $institute->id,
            'name' => 'English',
            'code' => 'ENG-1',
        ]);

        ClassSubject::create([
            'class_id' => $class->id,
            'section_id' => $section->id,
            'subject_id' => $subject1->id,
        ]);

        ClassSubject::create([
            'class_id' => $class->id,
            'section_id' => $section->id,
            'subject_id' => $subject2->id,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/institutes/assigned-subjects');
        $response->assertOk();
        $response->assertJsonStructure([
            'status',
            'message',
            'data' => [
                '*' => [
                    'id',
                    'class_id',
                    'section_id',
                    'subject_id',
                    'class' => ['id', 'name', 'code'],
                    'section' => ['id', 'name', 'code'],
                    'subject' => ['id', 'name', 'code'],
                ],
            ],
        ]);
        $response->assertJsonCount(2, 'data');
    }

    public function test_can_filter_assigned_subjects_by_class_and_section(): void
    {
        $user = User::factory()->create();
        $institute = Institute::create(['name' => 'The City School']);

        InstituteUser::create([
            'user_id' => $user->id,
            'institute_id' => $institute->id,
            'is_active' => true,
        ]);

        $class1 = AcademicClass::create([
            'institute_id' => $institute->id,
            'name' => 'Grade 1',
            'code' => 'GRADE-1',
        ]);

        $class2 = AcademicClass::create([
            'institute_id' => $institute->id,
            'name' => 'Grade 2',
            'code' => 'GRADE-2',
        ]);

        $section1 = AcademicSection::create([
            'class_id' => $class1->id,
            'name' => 'Section A',
            'code' => 'SECTION-A',
        ]);

        $subject1 = Subject::create([
            'institute_id' => $institute->id,
            'name' => 'Mathematics',
            'code' => 'MATH-1',
        ]);

        $subject2 = Subject::create([
            'institute_id' => $institute->id,
            'name' => 'Science',
            'code' => 'SCI-1',
        ]);

        ClassSubject::create([
            'class_id' => $class1->id,
            'section_id' => $section1->id,
            'subject_id' => $subject1->id,
        ]);

        ClassSubject::create([
            'class_id' => $class2->id,
            'section_id' => null,
            'subject_id' => $subject2->id,
        ]);

        Sanctum::actingAs($user);

        // Filter by class_id
        $response = $this->getJson('/api/institutes/assigned-subjects?class_id=' . $class1->id);
        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.subject.name', 'Mathematics');

        // Filter by section_id=null
        $responseNullSection = $this->getJson('/api/institutes/assigned-subjects?section_id=null');
        $responseNullSection->assertOk();
        $responseNullSection->assertJsonCount(1, 'data');
        $responseNullSection->assertJsonPath('data.0.subject.name', 'Science');
    }

    public function test_can_include_teacher_allocation_when_session_id_provided(): void
    {
        $user = User::factory()->create();
        $teacher = User::factory()->create(['name' => 'Sir Ali', 'email' => 'ali@example.com']);
        $institute = Institute::create(['name' => 'The City School']);

        InstituteUser::create([
            'user_id' => $user->id,
            'institute_id' => $institute->id,
            'is_active' => true,
        ]);

        InstituteUser::create([
            'user_id' => $teacher->id,
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
            'name' => 'Grade 1',
            'code' => 'GRADE-1',
        ]);

        $section = AcademicSection::create([
            'class_id' => $class->id,
            'name' => 'Section A',
            'code' => 'SECTION-A',
        ]);

        $subject = Subject::create([
            'institute_id' => $institute->id,
            'name' => 'Mathematics',
            'code' => 'MATH-1',
        ]);

        ClassSubject::create([
            'class_id' => $class->id,
            'section_id' => $section->id,
            'subject_id' => $subject->id,
        ]);

        SubjectAllocation::create([
            'session_id' => $session->id,
            'class_id' => $class->id,
            'section_id' => $section->id,
            'subject_id' => $subject->id,
            'teacher_user_id' => $teacher->id,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/institutes/assigned-subjects?session_id=' . $session->id);
        $response->assertOk();
        $response->assertJsonPath('data.0.teacher.name', 'Sir Ali');
        $response->assertJsonPath('data.0.teacher.email', 'ali@example.com');
    }

    public function test_can_access_via_alias_route_class_subjects(): void
    {
        $user = User::factory()->create();
        $institute = Institute::create(['name' => 'The City School']);

        InstituteUser::create([
            'user_id' => $user->id,
            'institute_id' => $institute->id,
            'is_active' => true,
        ]);

        $class = AcademicClass::create([
            'institute_id' => $institute->id,
            'name' => 'Grade 1',
            'code' => 'GRADE-1',
        ]);

        $subject = Subject::create([
            'institute_id' => $institute->id,
            'name' => 'Mathematics',
            'code' => 'MATH-1',
        ]);

        ClassSubject::create([
            'class_id' => $class->id,
            'section_id' => null,
            'subject_id' => $subject->id,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/institutes/class-subjects');
        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.class.name', 'Grade 1');
        $this->assertNull($response->json('data.0.section'));
    }
}
