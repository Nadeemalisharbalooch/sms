<?php

namespace Tests\Feature;

use App\Models\AcademicSession;
use App\Models\FeeVoucher;
use App\Models\FeeVoucherItem;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StudentVouchersTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_fetch_student_fee_vouchers_list(): void
    {
        $user = User::factory()->create();
        $institute = Institute::create([
            'name' => 'City Academy',
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

        $student = Student::create([
            'institute_id' => $institute->id,
            'first_name' => 'Ali',
            'last_name' => 'Khan',
            'gender' => 'male',
            'guardian_name' => 'Tariq Khan',
            'guardian_phone' => '03001234567',
            'dob' => '2015-05-10',
            'admission_date' => '2026-08-01',
        ]);

        $voucher = FeeVoucher::create([
            'institute_id' => $institute->id,
            'session_id' => $session->id,
            'student_id' => $student->id,
            'billing_month' => '2026-09',
            'due_date' => '2026-09-10',
            'total_amount' => 7500.00,
            'paid_amount' => 0.00,
            'status' => 'unpaid',
        ]);

        FeeVoucherItem::create([
            'fee_voucher_id' => $voucher->id,
            'fee_name' => 'Tuition Fee',
            'amount' => 5000.00,
        ]);

        FeeVoucherItem::create([
            'fee_voucher_id' => $voucher->id,
            'fee_name' => 'Transport Fee',
            'amount' => 2500.00,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/institutes/fees/student-vouchers?student_id={$student->id}");

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'message' => 'Student fee vouchers retrieved successfully',
            ])
            ->assertJsonPath('data.student.id', $student->id)
            ->assertJsonPath('data.student.name', 'Ali Khan')
            ->assertJsonPath('data.fees_summary.total_vouchers', 1)
            ->assertJsonPath('data.fees_summary.total_amount', 7500)
            ->assertJsonPath('data.fees_summary.total_due', 7500)
            ->assertJsonPath('data.vouchers.0.voucher_id', $voucher->id)
            ->assertJsonPath('data.vouchers.0.billing_month', '2026-09')
            ->assertJsonPath('data.vouchers.0.total_amount', 7500)
            ->assertJsonPath('data.vouchers.0.balance_due', 7500)
            ->assertJsonPath('data.vouchers.0.items.0.fee_name', 'Tuition Fee')
            ->assertJsonPath('data.vouchers.0.items.0.amount', 5000);

        // Also test the alias endpoint
        $responseAlias = $this->getJson("/api/institutes/fees/vouchers?student_id={$student->id}");
        $responseAlias->assertStatus(200)
            ->assertJsonPath('data.student.id', $student->id)
            ->assertJsonPath('data.vouchers.0.voucher_id', $voucher->id);
    }
}
