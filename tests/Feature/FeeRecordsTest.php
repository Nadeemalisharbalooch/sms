<?php

namespace Tests\Feature;

use App\Models\AcademicClass;
use App\Models\AcademicSection;
use App\Models\AcademicSession;
use App\Models\Enrollment;
use App\Models\FeePayment;
use App\Models\FeeVoucher;
use App\Models\FeeVoucherItem;
use App\Models\Institute;
use App\Models\InstituteSubscription;
use App\Models\InstituteUser;
use App\Models\Plan;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FeeRecordsTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Institute $institute;

    private AcademicSession $session;

    private Student $student;

    private Student $secondStudent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();

        $this->institute = Institute::create(['name' => 'Fee Records School']);

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

        $class = AcademicClass::create(['institute_id' => $this->institute->id, 'code' => 'G5', 'name' => 'Grade 5']);
        $section = AcademicSection::create(['class_id' => $class->id, 'code' => 'A', 'name' => 'A']);

        $this->student = Student::create([
            'institute_id' => $this->institute->id,
            'first_name' => 'Bilal',
            'last_name' => 'Hussain',
            'dob' => '2015-03-15',
            'gender' => 'male',
            'guardian_name' => 'Ghulam Hussain',
            'guardian_phone' => '03001112233',
            'admission_date' => '2025-08-01',
        ]);

        $this->secondStudent = Student::create([
            'institute_id' => $this->institute->id,
            'first_name' => 'Ayesha',
            'last_name' => 'Faiz',
            'dob' => '2015-06-25',
            'gender' => 'female',
            'guardian_name' => 'Faiz Ahmed',
            'guardian_phone' => '03001112234',
            'admission_date' => '2025-08-01',
        ]);

        Enrollment::create([
            'student_id' => $this->student->id,
            'session_id' => $this->session->id,
            'class_id' => $class->id,
            'section_id' => $section->id,
            'roll_number' => 1,
        ]);

        Enrollment::create([
            'student_id' => $this->secondStudent->id,
            'session_id' => $this->session->id,
            'class_id' => $class->id,
            'section_id' => $section->id,
            'roll_number' => 2,
        ]);

        Sanctum::actingAs($this->owner);
    }

    private function addVoucher(Student $student, string $billingMonth, float $totalAmount, float $paidAmount, string $status, string $dueDate): FeeVoucher
    {
        $voucher = FeeVoucher::create([
            'institute_id' => $this->institute->id,
            'session_id' => $this->session->id,
            'student_id' => $student->id,
            'batch_id' => 1,
            'billing_month' => $billingMonth,
            'due_date' => $dueDate,
            'total_amount' => $totalAmount,
            'paid_amount' => $paidAmount,
            'status' => $status,
        ]);

        FeeVoucherItem::create([
            'fee_voucher_id' => $voucher->id,
            'fee_name' => 'Tuition',
            'amount' => $totalAmount,
        ]);

        return $voucher;
    }

    public function test_records_returns_all_vouchers_with_summary_and_no_pagination(): void
    {
        $voucher = $this->addVoucher($this->student, '2026-09', 5000, 5000, 'paid', '2026-09-10');
        $this->addVoucher($this->secondStudent, '2026-09', 5000, 0, 'unpaid', '2026-09-10');
        $this->addVoucher($this->student, '2026-10', 5000, 2000, 'partial', '2026-10-10');

        FeePayment::create([
            'institute_id' => $this->institute->id,
            'fee_voucher_id' => $voucher->id,
            'amount_paid' => 5000,
            'payment_date' => '2026-09-05',
            'payment_method' => 'cash',
            'collected_by_user_id' => $this->owner->id,
        ]);

        $response = $this->getJson('/api/institutes/fees/records');

        $response->assertOk()
            ->assertJsonPath('data.summary.total_vouchers', 3)
            ->assertJsonPath('data.summary.total_amount', 15000)
            ->assertJsonPath('data.summary.total_paid', 7000)
            ->assertJsonPath('data.summary.total_due', 8000)
            ->assertJsonPath('data.summary.status_counts.paid_count', 1)
            ->assertJsonPath('data.summary.status_counts.unpaid_count', 1)
            ->assertJsonPath('data.summary.status_counts.partial_count', 1)
            ->assertJsonCount(3, 'data.records')
            ->assertJsonMissingPath('data.pagination')
            ->assertJsonStructure([
                'data' => [
                    'institute',
                    'session',
                    'filters',
                    'summary',
                    'records' => [
                        ['voucher_id', 'billing_month', 'status', 'student' => ['id', 'full_name', 'roll_number'], 'items', 'payments'],
                    ],
                ],
            ]);
    }

    public function test_records_can_be_filtered_by_status_and_billing_month(): void
    {
        $this->addVoucher($this->student, '2026-09', 5000, 5000, 'paid', '2026-09-10');
        $this->addVoucher($this->secondStudent, '2026-09', 5000, 0, 'unpaid', '2026-09-10');
        $this->addVoucher($this->student, '2026-10', 5000, 2000, 'partial', '2026-10-10');

        $this->getJson('/api/institutes/fees/records?status=paid')
            ->assertOk()
            ->assertJsonPath('data.summary.total_vouchers', 1)
            ->assertJsonPath('data.records.0.status', 'paid');

        $this->getJson('/api/institutes/fees/records?billing_month=2026-10')
            ->assertOk()
            ->assertJsonPath('data.summary.total_vouchers', 1)
            ->assertJsonPath('data.records.0.billing_month', '2026-10');
    }

    public function test_records_can_be_filtered_by_student_search_roll_and_due_date(): void
    {
        $this->addVoucher($this->student, '2026-09', 5000, 0, 'unpaid', '2026-09-10');
        $this->addVoucher($this->secondStudent, '2026-09', 5000, 0, 'unpaid', '2026-09-15');

        $this->getJson('/api/institutes/fees/records?student_id='.$this->student->id)
            ->assertOk()
            ->assertJsonPath('data.summary.total_vouchers', 1)
            ->assertJsonPath('data.records.0.student.full_name', 'Bilal Hussain');

        $this->getJson('/api/institutes/fees/records?search=Faiz')
            ->assertOk()
            ->assertJsonPath('data.summary.total_vouchers', 1)
            ->assertJsonPath('data.records.0.student.full_name', 'Ayesha Faiz');

        $this->getJson('/api/institutes/fees/records?search=1')
            ->assertOk()
            ->assertJsonPath('data.summary.total_vouchers', 1)
            ->assertJsonPath('data.records.0.student.roll_number', 1);

        $this->getJson('/api/institutes/fees/records?due_date_from=2026-09-11&due_date_to=2026-09-30')
            ->assertOk()
            ->assertJsonPath('data.summary.total_vouchers', 1)
            ->assertJsonPath('data.records.0.student.id', $this->secondStudent->id);
    }

    public function test_records_rejects_foreign_session_and_scopes_to_institute(): void
    {
        $otherOwner = User::factory()->create();
        $otherInstitute = Institute::create(['name' => 'Foreign Fee School']);

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

        $foreignStudent = Student::create([
            'institute_id' => $otherInstitute->id,
            'first_name' => 'Una',
            'last_name' => 'Other',
            'dob' => '2016-01-01',
            'gender' => 'female',
            'guardian_name' => 'Guardian Other',
            'guardian_phone' => '03001112235',
            'admission_date' => '2025-08-01',
        ]);

        FeeVoucher::create([
            'institute_id' => $otherInstitute->id,
            'session_id' => $otherSession->id,
            'student_id' => $foreignStudent->id,
            'batch_id' => 1,
            'billing_month' => '2026-09',
            'due_date' => '2026-09-10',
            'total_amount' => 1000,
            'paid_amount' => 0,
            'status' => 'unpaid',
        ]);

        $this->addVoucher($this->student, '2026-09', 5000, 0, 'unpaid', '2026-09-10');

        $this->getJson('/api/institutes/fees/records?session_id='.$otherSession->id)
            ->assertStatus(422);

        $this->getJson('/api/institutes/fees/records')
            ->assertOk()
            ->assertJsonPath('data.summary.total_vouchers', 1)
            ->assertJsonPath('data.records.0.student.id', $this->student->id);
    }
}
