<?php

namespace Tests\Feature;

use App\Models\AcademicClass;
use App\Models\AcademicSection;
use App\Models\AcademicSession;
use App\Models\Enrollment;
use App\Models\FeeVoucher;
use App\Models\FeeVoucherItem;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StudentLedgerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Institute $institute;
    private AcademicSession $session;
    private AcademicClass $class1;
    private AcademicClass $class2;
    private AcademicSection $section1;
    private AcademicSection $section2;
    private Student $student1;
    private Student $student2;
    private Student $student3;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->institute = Institute::create([
            'name' => 'Apex Grammar School',
        ]);

        InstituteUser::create([
            'user_id' => $this->user->id,
            'institute_id' => $this->institute->id,
            'is_active' => true,
        ]);

        $this->session = AcademicSession::create([
            'institute_id' => $this->institute->id,
            'name' => '2026-2027',
            'start_date' => '2026-08-01',
            'end_date' => '2027-06-30',
            'is_active' => true,
        ]);

        $this->class1 = AcademicClass::create([
            'institute_id' => $this->institute->id,
            'name' => 'Class 9',
            'code' => 'C9',
        ]);

        $this->class2 = AcademicClass::create([
            'institute_id' => $this->institute->id,
            'name' => 'Class 10',
            'code' => 'C10',
        ]);

        $this->section1 = AcademicSection::create([
            'class_id' => $this->class1->id,
            'name' => 'Section A',
            'code' => '9-A',
        ]);

        $this->section2 = AcademicSection::create([
            'class_id' => $this->class1->id,
            'name' => 'Section B',
            'code' => '9-B',
        ]);

        // Student 1 in Class 9, Section A
        $this->student1 = Student::create([
            'institute_id' => $this->institute->id,
            'first_name' => 'Ali',
            'last_name' => 'Khan',
            'gender' => 'male',
            'guardian_name' => 'Tariq Khan',
            'guardian_phone' => '03001111111',
            'dob' => '2010-01-01',
            'admission_date' => '2026-08-01',
        ]);

        Enrollment::create([
            'student_id' => $this->student1->id,
            'session_id' => $this->session->id,
            'class_id' => $this->class1->id,
            'section_id' => $this->section1->id,
            'roll_number' => '9A-01',
        ]);

        // Student 2 in Class 9, Section B
        $this->student2 = Student::create([
            'institute_id' => $this->institute->id,
            'first_name' => 'Bilal',
            'last_name' => 'Ahmed',
            'gender' => 'male',
            'guardian_name' => 'Ahmed Saeed',
            'guardian_phone' => '03002222222',
            'dob' => '2010-02-02',
            'admission_date' => '2026-08-01',
        ]);

        Enrollment::create([
            'student_id' => $this->student2->id,
            'session_id' => $this->session->id,
            'class_id' => $this->class1->id,
            'section_id' => $this->section2->id,
            'roll_number' => '9B-01',
        ]);

        // Student 3 in Class 10
        $this->student3 = Student::create([
            'institute_id' => $this->institute->id,
            'first_name' => 'Sara',
            'last_name' => 'Noor',
            'gender' => 'female',
            'guardian_name' => 'Noor Muhammad',
            'guardian_phone' => '03003333333',
            'dob' => '2009-03-03',
            'admission_date' => '2026-08-01',
        ]);

        Enrollment::create([
            'student_id' => $this->student3->id,
            'session_id' => $this->session->id,
            'class_id' => $this->class2->id,
            'section_id' => null,
            'roll_number' => '10-01',
        ]);

        // Vouchers:
        // Student 1: Voucher 1 (5000 total, 5000 paid => paid), Voucher 2 (3000 total, 1000 paid => partial) => total_amount=8000, total_paid=6000, total_due=2000
        $v1 = FeeVoucher::create([
            'institute_id' => $this->institute->id,
            'session_id' => $this->session->id,
            'student_id' => $this->student1->id,
            'billing_month' => '2026-08',
            'due_date' => '2026-08-10',
            'total_amount' => 5000.00,
            'paid_amount' => 5000.00,
            'status' => 'paid',
        ]);
        FeeVoucherItem::create(['fee_voucher_id' => $v1->id, 'fee_name' => 'Tuition Fee', 'amount' => 5000.00]);

        $v2 = FeeVoucher::create([
            'institute_id' => $this->institute->id,
            'session_id' => $this->session->id,
            'student_id' => $this->student1->id,
            'billing_month' => '2026-09',
            'due_date' => '2026-09-10',
            'total_amount' => 3000.00,
            'paid_amount' => 1000.00,
            'status' => 'partial',
        ]);
        FeeVoucherItem::create(['fee_voucher_id' => $v2->id, 'fee_name' => 'Tuition Fee', 'amount' => 3000.00]);

        // Student 2: Voucher (4000 total, 0 paid => unpaid) => total_amount=4000, total_paid=0, total_due=4000
        $v3 = FeeVoucher::create([
            'institute_id' => $this->institute->id,
            'session_id' => $this->session->id,
            'student_id' => $this->student2->id,
            'billing_month' => '2026-08',
            'due_date' => '2026-08-10',
            'total_amount' => 4000.00,
            'paid_amount' => 0.00,
            'status' => 'unpaid',
        ]);
        FeeVoucherItem::create(['fee_voucher_id' => $v3->id, 'fee_name' => 'Tuition Fee', 'amount' => 4000.00]);

        // Student 3: No vouchers generated => total_amount=0, total_paid=0, total_due=0, status=no_vouchers

        Sanctum::actingAs($this->user);
    }

    public function test_can_fetch_whole_institute_ledger_when_no_filter_sent(): void
    {
        $response = $this->getJson('/api/institutes/fees/student-ledger');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'message' => 'Student ledger retrieved successfully',
                'data' => [
                    'institute' => [
                        'id' => $this->institute->id,
                        'name' => 'Apex Grammar School',
                    ],
                    'session' => [
                        'id' => $this->session->id,
                        'name' => '2026-2027',
                    ],
                    'class' => null,
                    'section' => null,
                    'student' => null,
                    'summary' => [
                        'total_students' => 3,
                        'total_vouchers' => 3,
                        'total_amount' => 12000,
                        'total_paid' => 6000,
                        'total_due' => 6000,
                    ],
                ],
            ]);

        $this->assertCount(3, $response->json('data.students'));
    }

    public function test_can_filter_by_class(): void
    {
        $response = $this->getJson("/api/institutes/fees/student-ledger?class_id={$this->class1->id}");

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'class' => [
                        'id' => $this->class1->id,
                        'name' => 'Class 9',
                    ],
                    'summary' => [
                        'total_students' => 2,
                        'total_vouchers' => 3,
                        'total_amount' => 12000,
                        'total_paid' => 6000,
                        'total_due' => 6000,
                    ],
                ],
            ]);

        $this->assertCount(2, $response->json('data.students'));
    }

    public function test_can_filter_by_section(): void
    {
        $response = $this->getJson("/api/institutes/fees/student-ledger?section_id={$this->section1->id}");

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'section' => [
                        'id' => $this->section1->id,
                        'name' => 'Section A',
                    ],
                    'summary' => [
                        'total_students' => 1,
                        'total_vouchers' => 2,
                        'total_amount' => 8000,
                        'total_paid' => 6000,
                        'total_due' => 2000,
                    ],
                ],
            ]);

        $this->assertCount(1, $response->json('data.students'));
        $this->assertEquals('Ali Khan', $response->json('data.students.0.name'));
        $this->assertEquals(2000, $response->json('data.students.0.summary.total_due'));
    }

    public function test_can_filter_by_student(): void
    {
        $response = $this->getJson("/api/institutes/fees/student-ledger?student_id={$this->student1->id}");

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'student' => [
                        'id' => $this->student1->id,
                        'name' => 'Ali Khan',
                        'class' => 'Class 9',
                        'section' => 'Section A',
                        'roll_number' => '9A-01',
                    ],
                    'summary' => [
                        'total_students' => 1,
                        'total_vouchers' => 2,
                        'total_amount' => 8000,
                        'total_paid' => 6000,
                        'total_due' => 2000,
                    ],
                ],
            ]);

        $this->assertCount(1, $response->json('data.students'));
        $this->assertEquals($this->student1->id, $response->json('data.students.0.id'));
    }

    public function test_can_filter_by_billing_month(): void
    {
        $response = $this->getJson('/api/institutes/fees/student-ledger?billing_month=2026-08');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'summary' => [
                        'total_students' => 3,
                        'total_vouchers' => 2,
                        'total_amount' => 9000,
                        'total_paid' => 5000,
                        'total_due' => 4000,
                    ],
                ],
            ]);
    }

    public function test_returns_404_when_class_not_found(): void
    {
        $response = $this->getJson('/api/institutes/fees/student-ledger?class_id=9999');

        $response->assertStatus(404)
            ->assertJson([
                'status' => 'error',
                'message' => 'Class not found',
            ]);
    }

    public function test_returns_404_when_student_not_found(): void
    {
        $response = $this->getJson('/api/institutes/fees/student-ledger?student_id=9999');

        $response->assertStatus(404)
            ->assertJson([
                'status' => 'error',
                'message' => 'Student not found',
            ]);
    }

    public function test_can_paginate_student_ledger(): void
    {
        // Request page 1 with per_page = 2
        $response = $this->getJson('/api/institutes/fees/student-ledger?per_page=2&page=1');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'summary' => [
                        'total_students' => 3,
                        'total_vouchers' => 3,
                        'total_amount' => 12000,
                        'total_paid' => 6000,
                        'total_due' => 6000,
                    ],
                    'pagination' => [
                        'current_page' => 1,
                        'per_page' => 2,
                        'total' => 3,
                        'last_page' => 2,
                        'has_more' => true,
                    ],
                ],
            ]);

        $this->assertCount(2, $response->json('data.students'));

        // Request page 2
        $responsePage2 = $this->getJson('/api/institutes/fees/student-ledger?per_page=2&page=2');

        $responsePage2->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'pagination' => [
                        'current_page' => 2,
                        'per_page' => 2,
                        'total' => 3,
                        'last_page' => 2,
                        'has_more' => false,
                    ],
                ],
            ]);

        $this->assertCount(1, $responsePage2->json('data.students'));
    }

    public function test_can_search_student_by_name(): void
    {
        // Search using 'search' parameter
        $response = $this->getJson('/api/institutes/fees/student-ledger?search=Ali');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'summary' => [
                        'total_students' => 1,
                        'total_amount' => 8000,
                        'total_paid' => 6000,
                        'total_due' => 2000,
                    ],
                ],
            ]);

        $this->assertCount(1, $response->json('data.students'));
        $this->assertEquals('Ali Khan', $response->json('data.students.0.name'));

        // Search using 'name' parameter
        $responseName = $this->getJson('/api/institutes/fees/student-ledger?name=Sara');
        $responseName->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'summary' => [
                        'total_students' => 1,
                        'total_amount' => 0,
                    ],
                ],
            ]);
        $this->assertCount(1, $responseName->json('data.students'));
        $this->assertEquals('Sara Noor', $responseName->json('data.students.0.name'));

        // Search using 'student_name' parameter
        $responseStudentName = $this->getJson('/api/institutes/fees/student-ledger?student_name=Bilal');
        $responseStudentName->assertStatus(200);
        $this->assertCount(1, $responseStudentName->json('data.students'));
        $this->assertEquals('Bilal Ahmed', $responseStudentName->json('data.students.0.name'));

        // Search using 'roll_number' parameter
        $responseRoll = $this->getJson('/api/institutes/fees/student-ledger?roll_number=9A-01');
        $responseRoll->assertStatus(200);
        $this->assertCount(1, $responseRoll->json('data.students'));
        $this->assertEquals('Ali Khan', $responseRoll->json('data.students.0.name'));
    }

    public function test_returns_403_when_accessing_unauthorized_institute(): void
    {
        $otherInstitute = Institute::create(['name' => 'Other School']);

        $response = $this->getJson("/api/institutes/fees/student-ledger?institute_id={$otherInstitute->id}");

        $response->assertStatus(403)
            ->assertJson([
                'status' => 'error',
                'message' => 'You do not have access to the selected institute.',
            ]);
    }
}
