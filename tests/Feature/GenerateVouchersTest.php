<?php

namespace Tests\Feature;

use App\Models\AcademicClass;
use App\Models\AcademicSession;
use App\Models\Enrollment;
use App\Models\FeeCategory;
use App\Models\FeeStructure;
use App\Models\FeeVoucher;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\Student;
use App\Models\StudentFeeAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GenerateVouchersTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_generate_vouchers_filtered_by_fee_category_ids(): void
    {
        $user = User::factory()->create();
        $institute = Institute::create(['name' => 'City Academy']);

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

        $class1 = AcademicClass::create([
            'institute_id' => $institute->id,
            'name' => 'Class 1',
            'code' => 'C1',
        ]);

        $class2 = AcademicClass::create([
            'institute_id' => $institute->id,
            'name' => 'Class 2',
            'code' => 'C2',
        ]);

        $student1 = Student::create([
            'institute_id' => $institute->id,
            'first_name' => 'Ali',
            'last_name' => 'Khan',
            'gender' => 'male',
            'guardian_name' => 'Tariq Khan',
            'guardian_phone' => '03001234567',
            'dob' => '2015-05-10',
            'admission_date' => '2026-08-01',
        ]);

        Enrollment::create([
            'student_id' => $student1->id,
            'session_id' => $session->id,
            'class_id' => $class1->id,
            'roll_number' => '101',
        ]);

        $student2 = Student::create([
            'institute_id' => $institute->id,
            'first_name' => 'Sara',
            'last_name' => 'Ahmed',
            'gender' => 'female',
            'guardian_name' => 'Ahmed Ali',
            'guardian_phone' => '03009876543',
            'dob' => '2015-06-15',
            'admission_date' => '2026-08-01',
        ]);

        Enrollment::create([
            'student_id' => $student2->id,
            'session_id' => $session->id,
            'class_id' => $class2->id,
            'roll_number' => '102',
        ]);

        $tuitionCategory = FeeCategory::create([
            'institute_id' => $institute->id,
            'name' => 'Tuition Fee',
        ]);

        $transportCategory = FeeCategory::create([
            'institute_id' => $institute->id,
            'name' => 'Transport Fee',
        ]);

        $examCategory = FeeCategory::create([
            'institute_id' => $institute->id,
            'name' => 'Exam Fee',
        ]);

        FeeStructure::create([
            'institute_id' => $institute->id,
            'session_id' => $session->id,
            'class_id' => $class1->id,
            'fee_category_id' => $tuitionCategory->id,
            'amount' => 5000,
            'recurrence' => 'monthly',
        ]);

        FeeStructure::create([
            'institute_id' => $institute->id,
            'session_id' => $session->id,
            'class_id' => $class1->id,
            'fee_category_id' => $transportCategory->id,
            'amount' => 2000,
            'recurrence' => 'monthly',
        ]);

        FeeStructure::create([
            'institute_id' => $institute->id,
            'session_id' => $session->id,
            'class_id' => $class2->id,
            'fee_category_id' => $tuitionCategory->id,
            'amount' => 6000,
            'recurrence' => 'monthly',
        ]);

        StudentFeeAssignment::create([
            'institute_id' => $institute->id,
            'session_id' => $session->id,
            'student_id' => $student1->id,
            'fee_category_id' => $examCategory->id,
            'amount' => 1000,
        ]);

        Sanctum::actingAs($user);

        // 1. Generate only Tuition Fee for all classes (class_id = null)
        $response = $this->postJson('/api/institutes/fees/generate-vouchers', [
            'class_id' => null,
            'fee_category_ids' => [$tuitionCategory->id],
            'billing_month' => '2026-09',
            'due_date' => '2026-09-10',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'generated_count' => 2,
                    'skipped_count' => 0,
                ],
            ]);

        $voucher1 = FeeVoucher::where('student_id', $student1->id)->where('billing_month', '2026-09')->first();
        $this->assertNotNull($voucher1);
        $this->assertEquals(5000, $voucher1->total_amount);
        $this->assertCount(1, $voucher1->items);
        $this->assertEquals('Tuition Fee', $voucher1->items->first()->fee_name);

        $voucher2 = FeeVoucher::where('student_id', $student2->id)->where('billing_month', '2026-09')->first();
        $this->assertNotNull($voucher2);
        $this->assertEquals(6000, $voucher2->total_amount);
        $this->assertCount(1, $voucher2->items);
        $this->assertEquals('Tuition Fee', $voucher2->items->first()->fee_name);

        // 2. Generate for single class with single fee_category_id in next month
        $responseClass = $this->postJson('/api/institutes/fees/generate-vouchers', [
            'class_id' => $class1->id,
            'fee_category_id' => $transportCategory->id,
            'billing_month' => '2026-10',
            'due_date' => '2026-10-10',
        ]);

        $responseClass->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'generated_count' => 1,
                    'skipped_count' => 0,
                ],
            ]);

        $voucherClass1 = FeeVoucher::where('student_id', $student1->id)->where('billing_month', '2026-10')->first();
        $this->assertNotNull($voucherClass1);
        $this->assertEquals(2000, $voucherClass1->total_amount);
        $this->assertCount(1, $voucherClass1->items);
        $this->assertEquals('Transport Fee', $voucherClass1->items->first()->fee_name);
    }

    public function test_fails_when_fee_category_ids_is_missing(): void
    {
        $user = User::factory()->create();
        $institute = Institute::create(['name' => 'City Academy']);

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

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/institutes/fees/generate-vouchers', [
            'class_id' => null,
            'billing_month' => '2026-09',
            'due_date' => '2026-09-10',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['fee_category_ids']);
    }

    public function test_can_generate_vouchers_filtered_by_student_ids(): void
    {
        $user = User::factory()->create();
        $institute = Institute::create(['name' => 'City Academy']);

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
            'name' => 'Class 1',
            'code' => 'C1',
        ]);

        $student1 = Student::create([
            'institute_id' => $institute->id,
            'first_name' => 'Ali',
            'last_name' => 'Khan',
            'gender' => 'male',
            'guardian_name' => 'Tariq Khan',
            'guardian_phone' => '03001234567',
            'dob' => '2015-05-10',
            'admission_date' => '2026-08-01',
        ]);

        Enrollment::create([
            'student_id' => $student1->id,
            'session_id' => $session->id,
            'class_id' => $class->id,
            'roll_number' => '101',
        ]);

        $student2 = Student::create([
            'institute_id' => $institute->id,
            'first_name' => 'Sara',
            'last_name' => 'Ahmed',
            'gender' => 'female',
            'guardian_name' => 'Ahmed Ali',
            'guardian_phone' => '03009876543',
            'dob' => '2015-06-15',
            'admission_date' => '2026-08-01',
        ]);

        Enrollment::create([
            'student_id' => $student2->id,
            'session_id' => $session->id,
            'class_id' => $class->id,
            'roll_number' => '102',
        ]);

        $tuitionCategory = FeeCategory::create([
            'institute_id' => $institute->id,
            'name' => 'Tuition Fee',
        ]);

        FeeStructure::create([
            'institute_id' => $institute->id,
            'session_id' => $session->id,
            'class_id' => $class->id,
            'fee_category_id' => $tuitionCategory->id,
            'amount' => 5000,
            'recurrence' => 'monthly',
        ]);

        Sanctum::actingAs($user);

        // Generate voucher ONLY for student1
        $response = $this->postJson('/api/institutes/fees/generate-vouchers', [
            'class_id' => null,
            'student_ids' => [$student1->id],
            'fee_category_ids' => [$tuitionCategory->id],
            'billing_month' => '2026-11',
            'due_date' => '2026-11-10',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'generated_count' => 1,
                    'skipped_count' => 0,
                ],
            ]);

        $this->assertTrue(FeeVoucher::where('student_id', $student1->id)->where('billing_month', '2026-11')->exists());
        $this->assertFalse(FeeVoucher::where('student_id', $student2->id)->where('billing_month', '2026-11')->exists());
    }

    public function test_can_bulk_delete_unpaid_generated_vouchers(): void
    {
        $user = User::factory()->create();
        $institute = Institute::create(['name' => 'City Academy']);

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
            'name' => 'Class 1',
            'code' => 'C1',
        ]);

        $student = Student::create([
            'institute_id' => $institute->id,
            'first_name' => 'Bilal',
            'last_name' => 'Ahmed',
            'dob' => '2012-05-10',
            'gender' => 'male',
            'guardian_name' => 'Ahmed',
            'guardian_phone' => '03001234567',
            'admission_date' => '2026-08-01',
        ]);

        Enrollment::create([
            'student_id' => $student->id,
            'session_id' => $session->id,
            'class_id' => $class->id,
            'roll_number' => '101',
        ]);

        $voucher = FeeVoucher::create([
            'institute_id' => $institute->id,
            'session_id' => $session->id,
            'student_id' => $student->id,
            'billing_month' => '2026-08',
            'due_date' => '2026-08-10',
            'total_amount' => 5000,
            'paid_amount' => 0,
            'status' => 'unpaid',
        ]);

        Sanctum::actingAs($user);

        // Delete generated vouchers for 2026-08
        $response = $this->deleteJson('/api/institutes/fees/generate-vouchers', [
            'billing_month' => '2026-08',
            'class_id' => $class->id,
        ]);

        $response->assertOk();
        $response->assertJson([
            'status' => 'success',
            'data' => [
                'deleted_count' => 1,
                'skipped_paid_count' => 0,
            ],
        ]);

        $this->assertDatabaseMissing('fee_vouchers', ['id' => $voucher->id]);
    }

    public function test_skips_paid_vouchers_during_bulk_delete_unless_force(): void
    {
        $user = User::factory()->create();
        $institute = Institute::create(['name' => 'City Academy']);

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

        $student = Student::create([
            'institute_id' => $institute->id,
            'first_name' => 'Zaid',
            'last_name' => 'Khan',
            'dob' => '2012-05-10',
            'gender' => 'male',
            'guardian_name' => 'Khan',
            'guardian_phone' => '03001234567',
            'admission_date' => '2026-08-01',
        ]);

        $paidVoucher = FeeVoucher::create([
            'institute_id' => $institute->id,
            'session_id' => $session->id,
            'student_id' => $student->id,
            'billing_month' => '2026-09',
            'due_date' => '2026-09-10',
            'total_amount' => 5000,
            'paid_amount' => 5000,
            'status' => 'paid',
        ]);

        Sanctum::actingAs($user);

        // Try deleting without force -> should skip paid voucher
        $response = $this->deleteJson('/api/institutes/fees/generate-vouchers', [
            'billing_month' => '2026-09',
            'force' => false,
        ]);

        $response->assertOk();
        $response->assertJson([
            'status' => 'success',
            'data' => [
                'deleted_count' => 0,
                'skipped_paid_count' => 1,
            ],
        ]);
        $this->assertDatabaseHas('fee_vouchers', ['id' => $paidVoucher->id]);

        // Delete with force=true -> deletes voucher
        $forceResponse = $this->deleteJson('/api/institutes/fees/generate-vouchers', [
            'billing_month' => '2026-09',
            'force' => true,
        ]);

        $forceResponse->assertOk();
        $forceResponse->assertJson([
            'status' => 'success',
            'data' => [
                'deleted_count' => 1,
                'skipped_paid_count' => 0,
            ],
        ]);
        $this->assertDatabaseMissing('fee_vouchers', ['id' => $paidVoucher->id]);
    }

    public function test_can_delete_single_voucher_by_id(): void
    {
        $user = User::factory()->create();
        $institute = Institute::create(['name' => 'City Academy']);

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

        $student = Student::create([
            'institute_id' => $institute->id,
            'first_name' => 'Usman',
            'last_name' => 'Tariq',
            'dob' => '2012-05-10',
            'gender' => 'male',
            'guardian_name' => 'Tariq',
            'guardian_phone' => '03001234567',
            'admission_date' => '2026-08-01',
        ]);

        $voucher = FeeVoucher::create([
            'institute_id' => $institute->id,
            'session_id' => $session->id,
            'student_id' => $student->id,
            'billing_month' => '2026-10',
            'due_date' => '2026-10-10',
            'total_amount' => 3000,
            'paid_amount' => 0,
            'status' => 'unpaid',
        ]);

        Sanctum::actingAs($user);

        $response = $this->deleteJson("/api/institutes/fees/vouchers/{$voucher->id}");
        $response->assertOk();
        $this->assertDatabaseMissing('fee_vouchers', ['id' => $voucher->id]);
    }

    public function test_can_get_generated_vouchers_with_filters_and_summary(): void
    {
        $user = User::factory()->create();
        $institute = Institute::create(['name' => 'City Academy']);

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
            'name' => 'Class 10',
            'code' => 'C10',
        ]);

        $student = Student::create([
            'institute_id' => $institute->id,
            'first_name' => 'Zain',
            'last_name' => 'Malik',
            'dob' => '2010-05-10',
            'gender' => 'male',
            'guardian_name' => 'Malik',
            'guardian_phone' => '03001234567',
            'admission_date' => '2026-08-01',
        ]);

        Enrollment::create([
            'session_id' => $session->id,
            'class_id' => $class->id,
            'student_id' => $student->id,
            'roll_number' => '101',
        ]);

        $voucher = FeeVoucher::create([
            'institute_id' => $institute->id,
            'session_id' => $session->id,
            'student_id' => $student->id,
            'billing_month' => '2026-08',
            'due_date' => '2026-08-10',
            'total_amount' => 5000,
            'paid_amount' => 2000,
            'status' => 'partial',
        ]);

        $voucher->items()->create(['fee_name' => 'Tuition Fee', 'amount' => 5000]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/institutes/fees/generate-vouchers?billing_month=2026-08');
        $response->assertOk();
        $response->assertJsonPath('data.summary.total_vouchers', 1);
        $response->assertJsonPath('data.summary.total_amount', 5000);
        $response->assertJsonPath('data.summary.total_paid', 2000);
        $response->assertJsonPath('data.summary.total_balance_due', 3000);
        $response->assertJsonPath('data.vouchers.0.student.name', 'Zain Malik');
        $response->assertJsonPath('data.vouchers.0.student.class.name', 'Class 10');
        $response->assertJsonPath('data.vouchers.0.items.0.fee_name', 'Tuition Fee');
    }

    public function test_can_get_generate_vouchers_preview(): void
    {
        $user = User::factory()->create();
        $institute = Institute::create(['name' => 'City Academy']);

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
            'name' => 'Class 9',
            'code' => 'C9',
        ]);

        $student = Student::create([
            'institute_id' => $institute->id,
            'first_name' => 'Ahmed',
            'last_name' => 'Raza',
            'dob' => '2011-05-10',
            'gender' => 'male',
            'guardian_name' => 'Raza',
            'guardian_phone' => '03001234567',
            'admission_date' => '2026-08-01',
        ]);

        Enrollment::create([
            'session_id' => $session->id,
            'class_id' => $class->id,
            'student_id' => $student->id,
            'roll_number' => '901',
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/institutes/fees/generate-vouchers?billing_month=2026-11&preview=true');
        $response->assertOk();
        $response->assertJsonPath('data.mode', 'preview');
        $response->assertJsonPath('data.total_eligible_students', 1);
        $response->assertJsonPath('data.already_generated_count', 0);
        $response->assertJsonPath('data.pending_generation_count', 1);
    }
}
