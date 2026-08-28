<?php

namespace Tests\Feature;

use App\Models\AcademicClass;
use App\Models\AcademicSection;
use App\Models\AcademicSession;
use App\Models\Enrollment;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class StudentImportTest extends TestCase
{
    use RefreshDatabase;

    private function createInstituteContext(): array
    {
        $user = User::factory()->create();
        $institute = Institute::create(['name' => 'Oxford Grammar School']);

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

        return [$user, $institute, $session];
    }

    public function test_can_import_students_using_excel_file_with_class_and_section_in_payload(): void
    {
        [$user, $institute, $session] = $this->createInstituteContext();

        $class = AcademicClass::create(['institute_id' => $institute->id, 'name' => 'Class 10', 'code' => 'C10']);
        $section = AcademicSection::create(['class_id' => $class->id, 'name' => 'Section A', 'code' => '10A']);

        // Create Excel File in memory
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([
            ['Student Name', 'Father Name', 'Roll Number', 'Gender', 'Date of Birth', 'Phone Number', 'Address'],
            ['Muhammad Bilal', 'Tariq Mehmood', '101', 'Male', '2010-06-15', '03001234567', 'Karachi, Pakistan'],
            ['Ayesha Siddiqa', 'Siddiq Ahmed', '102', 'Female', '2011-03-20', '03129876543', 'Lahore, Pakistan'],
        ]);

        $tempPath = tempnam(sys_get_temp_dir(), 'std_import_') . '.xlsx';
        (new Xlsx($spreadsheet))->save($tempPath);

        $uploadedFile = new UploadedFile($tempPath, 'students.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/institutes/students/import', [
            'file' => $uploadedFile,
            'class_id' => $class->id,
            'section_id' => $section->id,
        ]);

        @unlink($tempPath);

        $response->assertStatus(201);
        $response->assertJsonPath('status', 'success');
        $response->assertJsonPath('data.imported_count', 2);
        $response->assertJsonPath('data.class.name', 'Class 10');
        $response->assertJsonPath('data.section.name', 'Section A');

        // Check students created in database
        $this->assertDatabaseHas('students', [
            'institute_id' => $institute->id,
            'first_name' => 'Muhammad',
            'last_name' => 'Bilal',
            'guardian_name' => 'Tariq Mehmood',
            'gender' => 'male',
        ]);

        $this->assertDatabaseHas('students', [
            'institute_id' => $institute->id,
            'first_name' => 'Ayesha',
            'last_name' => 'Siddiqa',
            'guardian_name' => 'Siddiq Ahmed',
            'gender' => 'female',
        ]);

        // Check enrollments created in database
        $this->assertDatabaseHas('enrollments', [
            'session_id' => $session->id,
            'class_id' => $class->id,
            'section_id' => $section->id,
            'roll_number' => '101',
        ]);

        $this->assertDatabaseHas('enrollments', [
            'session_id' => $session->id,
            'class_id' => $class->id,
            'section_id' => $section->id,
            'roll_number' => '102',
        ]);
    }

    public function test_can_import_students_from_csv_file(): void
    {
        [$user, $institute, $session] = $this->createInstituteContext();

        $class = AcademicClass::create(['institute_id' => $institute->id, 'name' => 'Class 9', 'code' => 'C9']);

        $csvContent = "Name,Father Name,Roll No,Gender,Phone\n" .
            "Hamza Ali,Ali Nawaz,901,Male,03001112233\n" .
            "Zoya Khan,Kamran Khan,902,Female,03004445566\n";

        $tempPath = tempnam(sys_get_temp_dir(), 'std_csv_') . '.csv';
        file_put_contents($tempPath, $csvContent);

        $uploadedFile = new UploadedFile($tempPath, 'students.csv', 'text/csv', null, true);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/institutes/students/import', [
            'file' => $uploadedFile,
            'class_id' => $class->id,
        ]);

        @unlink($tempPath);

        $response->assertStatus(201);
        $response->assertJsonPath('data.imported_count', 2);
        $this->assertDatabaseHas('students', [
            'institute_id' => $institute->id,
            'first_name' => 'Hamza',
            'last_name' => 'Ali',
        ]);
    }

    public function test_can_download_student_import_template(): void
    {
        [$user, $institute, $session] = $this->createInstituteContext();
        $class = AcademicClass::create(['institute_id' => $institute->id, 'name' => 'Class 8', 'code' => 'C8']);

        Sanctum::actingAs($user);

        $response = $this->get("/api/institutes/students/import-template?class_id={$class->id}");
        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_import_validates_class_id_belongs_to_institute(): void
    {
        [$user, $institute, $session] = $this->createInstituteContext();

        $csvContent = "Name,Father Name\nUsman Ali,Ali Khan\n";
        $tempPath = tempnam(sys_get_temp_dir(), 'std_csv_') . '.csv';
        file_put_contents($tempPath, $csvContent);

        $uploadedFile = new UploadedFile($tempPath, 'students.csv', 'text/csv', null, true);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/institutes/students/import', [
            'file' => $uploadedFile,
            'class_id' => 999999, // Non-existent class
        ]);

        @unlink($tempPath);

        $response->assertStatus(422);
        $response->assertJsonPath('status', 'error');
    }

    public function test_can_import_actual_sample_xlsx_file(): void
    {
        [$user, $institute, $session] = $this->createInstituteContext();

        $class = AcademicClass::create(['institute_id' => $institute->id, 'name' => 'Class 7', 'code' => 'C7']);
        $section = AcademicSection::create(['class_id' => $class->id, 'name' => 'Section Blue', 'code' => '7B']);

        $samplePath = base_path('public/samples/student_import_sample.xlsx');
        $uploadedFile = new UploadedFile($samplePath, 'student_import_sample.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/institutes/students/import', [
            'file' => $uploadedFile,
            'class_id' => $class->id,
            'section_id' => $section->id,
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.imported_count', 8);
        $this->assertDatabaseHas('students', [
            'institute_id' => $institute->id,
            'first_name' => 'Muhammad',
            'last_name' => 'Bilal',
        ]);
        $this->assertDatabaseHas('students', [
            'institute_id' => $institute->id,
            'first_name' => 'Zoya',
            'last_name' => 'Khan',
        ]);
    }
}
