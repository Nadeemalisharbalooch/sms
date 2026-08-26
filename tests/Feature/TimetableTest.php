<?php

namespace Tests\Feature;

use App\Models\AcademicClass;
use App\Models\AcademicSection;
use App\Models\AcademicSession;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\Subject;
use App\Models\SubjectAllocation;
use App\Models\TimetableEntry;
use App\Models\TimetableTimeSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TimetableTest extends TestCase
{
    use RefreshDatabase;

    private function createInstituteContext(string $name = 'City Grammar School'): array
    {
        $user = User::factory()->create(['name' => 'Admin User']);
        $institute = Institute::create(['name' => $name]);

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

    public function test_can_manage_timetable_time_slots(): void
    {
        [$user, $institute, $session] = $this->createInstituteContext();
        Sanctum::actingAs($user);

        // 1. Create Slot
        $response = $this->postJson('/api/institutes/timetable/slots', [
            'name' => 'Period 1',
            'start_time' => '08:00',
            'end_time' => '08:45',
            'is_break' => false,
            'sort_order' => 1,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.name', 'Period 1');
        $slotId = $response->json('data.id');

        // 2. List Slots
        $listResponse = $this->getJson('/api/institutes/timetable/slots');
        $listResponse->assertOk();
        $listResponse->assertJsonCount(1, 'data');

        // 3. Update Slot
        $updateResponse = $this->putJson("/api/institutes/timetable/slots/{$slotId}", [
            'name' => 'Period 1 (Morning)',
        ]);
        $updateResponse->assertOk();
        $updateResponse->assertJsonPath('data.name', 'Period 1 (Morning)');

        // 4. Seed Preset Slots
        $presetResponse = $this->postJson('/api/institutes/timetable/slots/preset', [
            'preset' => 'standard_6',
        ]);
        $presetResponse->assertOk();
        $presetResponse->assertJsonCount(7, 'data'); // 6 periods + 1 recess
    }

    public function test_can_auto_generate_clash_free_timetable(): void
    {
        [$user, $institute, $session] = $this->createInstituteContext();

        // Create Teachers
        $teacher1 = User::factory()->create(['name' => 'Sir Ali (Math)']);
        $teacher2 = User::factory()->create(['name' => 'Mam Sara (English)']);
        $teacher3 = User::factory()->create(['name' => 'Sir Tariq (Science)']);

        foreach ([$teacher1, $teacher2, $teacher3] as $t) {
            InstituteUser::create(['user_id' => $t->id, 'institute_id' => $institute->id, 'is_active' => true]);
        }

        // Create Classes & Sections
        $class1 = AcademicClass::create(['institute_id' => $institute->id, 'name' => 'Grade 9', 'code' => 'G9']);
        $class2 = AcademicClass::create(['institute_id' => $institute->id, 'name' => 'Grade 10', 'code' => 'G10']);

        $section1 = AcademicSection::create(['class_id' => $class1->id, 'name' => 'Section A', 'code' => '9A']);
        $section2 = AcademicSection::create(['class_id' => $class2->id, 'name' => 'Section A', 'code' => '10A']);

        // Create Subjects
        $subMath = Subject::create(['institute_id' => $institute->id, 'name' => 'Mathematics', 'code' => 'MATH']);
        $subEng = Subject::create(['institute_id' => $institute->id, 'name' => 'English', 'code' => 'ENG']);
        $subSci = Subject::create(['institute_id' => $institute->id, 'name' => 'Science', 'code' => 'SCI']);

        // Allocate subjects to teachers:
        // Teacher 1 teaches Math to BOTH Grade 9 and Grade 10 (Strict Clash Test!)
        SubjectAllocation::create(['session_id' => $session->id, 'class_id' => $class1->id, 'section_id' => $section1->id, 'subject_id' => $subMath->id, 'teacher_user_id' => $teacher1->id]);
        SubjectAllocation::create(['session_id' => $session->id, 'class_id' => $class1->id, 'section_id' => $section1->id, 'subject_id' => $subEng->id, 'teacher_user_id' => $teacher2->id]);
        SubjectAllocation::create(['session_id' => $session->id, 'class_id' => $class1->id, 'section_id' => $section1->id, 'subject_id' => $subSci->id, 'teacher_user_id' => $teacher3->id]);

        SubjectAllocation::create(['session_id' => $session->id, 'class_id' => $class2->id, 'section_id' => $section2->id, 'subject_id' => $subMath->id, 'teacher_user_id' => $teacher1->id]);
        SubjectAllocation::create(['session_id' => $session->id, 'class_id' => $class2->id, 'section_id' => $section2->id, 'subject_id' => $subEng->id, 'teacher_user_id' => $teacher2->id]);
        SubjectAllocation::create(['session_id' => $session->id, 'class_id' => $class2->id, 'section_id' => $section2->id, 'subject_id' => $subSci->id, 'teacher_user_id' => $teacher3->id]);

        // Create 4 Lecture Slots + 1 Break Slot
        TimetableTimeSlot::create(['institute_id' => $institute->id, 'name' => 'Period 1', 'start_time' => '08:00', 'end_time' => '08:45', 'is_break' => false, 'sort_order' => 1]);
        TimetableTimeSlot::create(['institute_id' => $institute->id, 'name' => 'Period 2', 'start_time' => '08:45', 'end_time' => '09:30', 'is_break' => false, 'sort_order' => 2]);
        TimetableTimeSlot::create(['institute_id' => $institute->id, 'name' => 'Break', 'start_time' => '09:30', 'end_time' => '10:00', 'is_break' => true, 'sort_order' => 3]);
        TimetableTimeSlot::create(['institute_id' => $institute->id, 'name' => 'Period 3', 'start_time' => '10:00', 'end_time' => '10:45', 'is_break' => false, 'sort_order' => 4]);
        TimetableTimeSlot::create(['institute_id' => $institute->id, 'name' => 'Period 4', 'start_time' => '10:45', 'end_time' => '11:30', 'is_break' => false, 'sort_order' => 5]);

        Sanctum::actingAs($user);

        // Run Generator
        $genResponse = $this->postJson('/api/institutes/timetable/generate', [
            'session_id' => $session->id,
            'days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
            'overwrite_existing' => true,
        ]);

        $genResponse->assertOk();
        $genResponse->assertJsonPath('data.success', true);
        $genResponse->assertJsonPath('data.classes_scheduled', 2);
        $this->assertGreaterThan(0, $genResponse->json('data.created_count'));

        // Verify Zero Teacher Clashes in Database
        $entries = TimetableEntry::where('session_id', $session->id)->get();

        $teacherSlotCombinations = [];
        foreach ($entries as $entry) {
            $key = $entry->teacher_user_id.'_'.$entry->day_of_week.'_'.$entry->time_slot_id;
            $this->assertFalse(isset($teacherSlotCombinations[$key]), "Teacher clash detected for key: {$key}");
            $teacherSlotCombinations[$key] = true;
        }

        // Verify Break slot has NO lectures
        $breakSlot = TimetableTimeSlot::where('institute_id', $institute->id)->where('is_break', true)->first();
        $breakLecturesCount = TimetableEntry::where('time_slot_id', $breakSlot->id)->count();
        $this->assertEquals(0, $breakLecturesCount, 'Break slot contains scheduled lectures!');
    }

    public function test_can_view_class_and_teacher_timetable_schedule(): void
    {
        [$user, $institute, $session] = $this->createInstituteContext();

        $teacher = User::factory()->create(['name' => 'Sir Tariq']);
        InstituteUser::create(['user_id' => $teacher->id, 'institute_id' => $institute->id, 'is_active' => true]);

        $class = AcademicClass::create(['institute_id' => $institute->id, 'name' => 'Grade 10', 'code' => 'G10']);
        $section = AcademicSection::create(['class_id' => $class->id, 'name' => 'Section B', 'code' => '10B']);
        $subject = Subject::create(['institute_id' => $institute->id, 'name' => 'Physics', 'code' => 'PHY']);

        $slot = TimetableTimeSlot::create([
            'institute_id' => $institute->id,
            'name' => 'Period 1',
            'start_time' => '08:00',
            'end_time' => '08:45',
            'sort_order' => 1,
        ]);

        TimetableEntry::create([
            'session_id' => $session->id,
            'class_id' => $class->id,
            'section_id' => $section->id,
            'subject_id' => $subject->id,
            'teacher_user_id' => $teacher->id,
            'time_slot_id' => $slot->id,
            'day_of_week' => 'monday',
        ]);

        Sanctum::actingAs($user);

        // Class Schedule View
        $classResponse = $this->getJson("/api/institutes/timetable/class?class_id={$class->id}&section_id={$section->id}");
        $classResponse->assertOk();
        $classResponse->assertJsonPath('data.class.name', 'Grade 10');
        $classResponse->assertJsonPath('data.schedule.monday.0.subject.name', 'Physics');
        $classResponse->assertJsonPath('data.schedule.monday.0.teacher.name', 'Sir Tariq');

        // Teacher Schedule View
        $teacherResponse = $this->getJson("/api/institutes/timetable/teacher?teacher_id={$teacher->id}");
        $teacherResponse->assertOk();
        $teacherResponse->assertJsonPath('data.teacher.name', 'Sir Tariq');
        $teacherResponse->assertJsonPath('data.schedule.monday.0.class.name', 'Grade 10');

        // Master Schedule View
        $masterResponse = $this->getJson('/api/institutes/timetable/master');
        $masterResponse->assertOk();
        $masterResponse->assertJsonPath('data.total_entries', 1);
        $this->assertNotEmpty($masterResponse->json('data.classes_schedules'));
        $this->assertNotEmpty($masterResponse->json('data.day_matrix'));
    }

    public function test_can_swap_entries_and_detects_clashes(): void
    {
        [$user, $institute, $session] = $this->createInstituteContext();

        $teacher = User::factory()->create(['name' => 'Sir Kamran']);
        InstituteUser::create(['user_id' => $teacher->id, 'institute_id' => $institute->id, 'is_active' => true]);

        $class = AcademicClass::create(['institute_id' => $institute->id, 'name' => 'Grade 8', 'code' => 'G8']);
        $subject = Subject::create(['institute_id' => $institute->id, 'name' => 'Computer', 'code' => 'CS']);

        $slot1 = TimetableTimeSlot::create(['institute_id' => $institute->id, 'name' => 'Period 1', 'start_time' => '08:00', 'end_time' => '08:45', 'sort_order' => 1]);
        $slot2 = TimetableTimeSlot::create(['institute_id' => $institute->id, 'name' => 'Period 2', 'start_time' => '08:45', 'end_time' => '09:30', 'sort_order' => 2]);
        $breakSlot = TimetableTimeSlot::create(['institute_id' => $institute->id, 'name' => 'Break', 'start_time' => '09:30', 'end_time' => '10:00', 'is_break' => true, 'sort_order' => 3]);

        $entry = TimetableEntry::create([
            'session_id' => $session->id,
            'class_id' => $class->id,
            'section_id' => null,
            'subject_id' => $subject->id,
            'teacher_user_id' => $teacher->id,
            'time_slot_id' => $slot1->id,
            'day_of_week' => 'monday',
        ]);

        Sanctum::actingAs($user);

        // 1. Reject moving to break slot
        $breakSwap = $this->postJson('/api/institutes/timetable/swap', [
            'entry_id' => $entry->id,
            'target_day_of_week' => 'monday',
            'target_time_slot_id' => $breakSlot->id,
        ]);
        $breakSwap->assertStatus(422);

        // 2. Valid move to Tuesday Period 2
        $validSwap = $this->postJson('/api/institutes/timetable/swap', [
            'entry_id' => $entry->id,
            'target_day_of_week' => 'tuesday',
            'target_time_slot_id' => $slot2->id,
        ]);
        $validSwap->assertOk();

        $entry->refresh();
        $this->assertEquals('tuesday', $entry->day_of_week);
        $this->assertEquals($slot2->id, $entry->time_slot_id);
    }

    public function test_can_export_timetable_as_html_pdf_excel_and_json(): void
    {
        [$user, $institute, $session] = $this->createInstituteContext();

        $class = AcademicClass::create(['institute_id' => $institute->id, 'name' => 'Grade 7', 'code' => 'G7']);
        TimetableTimeSlot::create(['institute_id' => $institute->id, 'name' => 'Period 1', 'start_time' => '08:00', 'end_time' => '08:45', 'sort_order' => 1]);

        Sanctum::actingAs($user);

        // Export as HTML view
        $htmlResponse = $this->getJson("/api/institutes/timetable/export?type=class&class_id={$class->id}&format=html&template=classic_grid");
        $htmlResponse->assertOk();
        $this->assertStringContainsString('<!DOCTYPE html>', $htmlResponse->getContent());
        $this->assertStringContainsString('Grade 7', $htmlResponse->getContent());

        // The class-specific endpoint defaults to a class timetable and returns a real PDF.
        $pdfResponse = $this->get("/institutes/timetable/export/classes?class_id={$class->id}&format=pdf");
        $pdfResponse->assertOk();
        $pdfResponse->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $pdfResponse->getContent());

        // Export as Excel spreadsheet
        $excelResponse = $this->get("/api/institutes/timetable/export?type=class&class_id={$class->id}&format=excel");
        $excelResponse->assertOk();
        $excelResponse->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        // Export as JSON structured response
        $jsonResponse = $this->getJson("/api/institutes/timetable/export?type=class&class_id={$class->id}&format=json&template=classic_grid");
        $jsonResponse->assertOk();
        $jsonResponse->assertJsonPath('status', 'success');
        $jsonResponse->assertJsonPath('data.template', 'classic_grid');
        $jsonResponse->assertJsonPath('data.class.name', 'Grade 7');
        $this->assertArrayHasKey('schedule', $jsonResponse->json('data'));
    }

    public function test_rejects_duplicate_slot_name_and_overlapping_times(): void
    {
        [$user, $institute, $session] = $this->createInstituteContext();
        Sanctum::actingAs($user);

        // Create Period 1 (08:00 - 08:45)
        $this->postJson('/api/institutes/timetable/slots', [
            'name' => 'Period 1',
            'start_time' => '08:00',
            'end_time' => '08:45',
        ])->assertCreated();

        // 1. Reject Duplicate Name
        $dupNameResponse = $this->postJson('/api/institutes/timetable/slots', [
            'name' => 'Period 1',
            'start_time' => '08:45',
            'end_time' => '09:30',
        ]);
        $dupNameResponse->assertStatus(422);
        $this->assertStringContainsString('already exists', $dupNameResponse->json('message'));

        // 2. Reject Overlapping Time (08:30 - 09:15 overlaps with 08:00 - 08:45)
        $overlapResponse = $this->postJson('/api/institutes/timetable/slots', [
            'name' => 'Period 2',
            'start_time' => '08:30',
            'end_time' => '09:15',
        ]);
        $overlapResponse->assertStatus(422);
        $this->assertStringContainsString('overlaps with existing slot', $overlapResponse->json('message'));

        // 3. Allow Back-to-Back Non-Overlapping Slot (08:45 - 09:30)
        $backToBackResponse = $this->postJson('/api/institutes/timetable/slots', [
            'name' => 'Period 2',
            'start_time' => '08:45',
            'end_time' => '09:30',
        ]);
        $backToBackResponse->assertCreated();
    }

    public function test_step1_admin_shifts_and_timing_configuration(): void
    {
        [$user, $institute, $session] = $this->createInstituteContext();
        Sanctum::actingAs($user);

        // Step 1: Admin configures 40 min duration, Standard Days (08:00 - 13:30 with Break 11:00-12:00) & Friday (08:00 - 12:00 No Break)
        $response = $this->postJson('/api/institutes/timetable/shifts', [
            'period_duration' => 40,
            'standard_days' => [
                'days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'saturday'],
                'start_time' => '08:00',
                'end_time' => '13:30',
                'has_break' => true,
                'break_name' => 'Recess / Break',
                'break_start' => '11:00',
                'break_end' => '12:00',
            ],
            'friday' => [
                'days' => ['friday'],
                'start_time' => '08:00',
                'end_time' => '12:00',
                'has_break' => false,
            ],
        ]);

        $response->assertOk();
        $response->assertJsonPath('status', 'success');

        $slots = TimetableTimeSlot::where('institute_id', $institute->id)->get();
        $this->assertNotEmpty($slots);

        // Check standard day break exists
        $breakSlot = $slots->firstWhere('is_break', true);
        $this->assertNotNull($breakSlot);
        $this->assertEquals('11:00', substr($breakSlot->start_time, 0, 5));
        $this->assertEquals('12:00', substr($breakSlot->end_time, 0, 5));
        $this->assertContains('monday', $breakSlot->days);

        // Check Friday period exists
        $fridayPeriod = $slots->first(fn ($s) => ! empty($s->days) && in_array('friday', $s->days, true) && ! $s->is_break);
        $this->assertNotNull($fridayPeriod);
        $this->assertEquals('08:00', substr($fridayPeriod->start_time, 0, 5));
    }

    public function test_step2_curriculum_subject_weightage_per_class(): void
    {
        [$user, $institute, $session] = $this->createInstituteContext();

        $teacher = User::factory()->create(['name' => 'Sir Tariq']);
        InstituteUser::create(['user_id' => $teacher->id, 'institute_id' => $institute->id, 'is_active' => true]);

        $class = AcademicClass::create(['institute_id' => $institute->id, 'name' => 'Grade 9', 'code' => 'G9']);
        $subMath = Subject::create(['institute_id' => $institute->id, 'name' => 'Mathematics', 'code' => 'MATH']);
        $subSci = Subject::create(['institute_id' => $institute->id, 'name' => 'Science', 'code' => 'SCI']);
        $subIsl = Subject::create(['institute_id' => $institute->id, 'name' => 'Islamiat', 'code' => 'ISL']);

        SubjectAllocation::create(['session_id' => $session->id, 'class_id' => $class->id, 'subject_id' => $subMath->id, 'teacher_user_id' => $teacher->id]);
        SubjectAllocation::create(['session_id' => $session->id, 'class_id' => $class->id, 'subject_id' => $subSci->id, 'teacher_user_id' => $teacher->id]);
        SubjectAllocation::create(['session_id' => $session->id, 'class_id' => $class->id, 'subject_id' => $subIsl->id, 'teacher_user_id' => $teacher->id]);

        Sanctum::actingAs($user);

        // 1. Read Class Curriculum
        $curriculumReadResponse = $this->getJson("/api/institutes/timetable/curriculum?class_id={$class->id}&session_id={$session->id}");
        $curriculumReadResponse->assertOk();
        $curriculumReadResponse->assertJsonCount(3, 'data.curriculum');

        // 2. Save Custom Subject Weightages (Math: 6, Science: 5, Islamiat: 3)
        $curriculumSaveResponse = $this->postJson('/api/institutes/timetable/curriculum', [
            'session_id' => $session->id,
            'class_id' => $class->id,
            'weightages' => [
                ['subject_id' => $subMath->id, 'weekly_periods' => 6],
                ['subject_id' => $subSci->id, 'weekly_periods' => 5],
                ['subject_id' => $subIsl->id, 'weekly_periods' => 3],
            ],
        ]);

        $curriculumSaveResponse->assertOk();
        $this->assertDatabaseHas('timetable_workloads', [
            'session_id' => $session->id,
            'class_id' => $class->id,
            'subject_id' => $subMath->id,
            'weekly_periods' => 6,
        ]);
        $this->assertDatabaseHas('timetable_workloads', [
            'session_id' => $session->id,
            'class_id' => $class->id,
            'subject_id' => $subSci->id,
            'weekly_periods' => 5,
        ]);
    }

    public function test_unified_wizard_generation_with_shifts_and_curriculum(): void
    {
        [$user, $institute, $session] = $this->createInstituteContext();

        $teacher1 = User::factory()->create(['name' => 'Sir Math']);
        $teacher2 = User::factory()->create(['name' => 'Mam Science']);
        $teacher3 = User::factory()->create(['name' => 'Qari Islamiat']);

        foreach ([$teacher1, $teacher2, $teacher3] as $t) {
            InstituteUser::create(['user_id' => $t->id, 'institute_id' => $institute->id, 'is_active' => true]);
        }

        $class = AcademicClass::create(['institute_id' => $institute->id, 'name' => 'Grade 10', 'code' => 'G10']);
        $subMath = Subject::create(['institute_id' => $institute->id, 'name' => 'Mathematics', 'code' => 'MATH']);
        $subSci = Subject::create(['institute_id' => $institute->id, 'name' => 'Science', 'code' => 'SCI']);
        $subIsl = Subject::create(['institute_id' => $institute->id, 'name' => 'Islamiat', 'code' => 'ISL']);

        SubjectAllocation::create(['session_id' => $session->id, 'class_id' => $class->id, 'subject_id' => $subMath->id, 'teacher_user_id' => $teacher1->id]);
        SubjectAllocation::create(['session_id' => $session->id, 'class_id' => $class->id, 'subject_id' => $subSci->id, 'teacher_user_id' => $teacher2->id]);
        SubjectAllocation::create(['session_id' => $session->id, 'class_id' => $class->id, 'subject_id' => $subIsl->id, 'teacher_user_id' => $teacher3->id]);

        Sanctum::actingAs($user);

        // Run Unified All-In-One Wizard: Step 1 (Timing) + Step 2 (Curriculum: Math=6, Sci=5, Isl=3) + Step 3 (Generate)
        $wizardResponse = $this->postJson('/api/institutes/timetable/wizard-generate', [
            'session_id' => $session->id,
            'timing' => [
                'period_duration' => 40,
                'standard_days' => [
                    'days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'saturday'],
                    'start_time' => '08:00',
                    'end_time' => '13:30',
                    'has_break' => true,
                    'break_start' => '11:00',
                    'break_end' => '12:00',
                ],
                'friday' => [
                    'days' => ['friday'],
                    'start_time' => '08:00',
                    'end_time' => '12:00',
                    'has_break' => false,
                ],
            ],
            'curriculum' => [
                [
                    'class_id' => $class->id,
                    'weightages' => [
                        ['subject_id' => $subMath->id, 'weekly_periods' => 6],
                        ['subject_id' => $subSci->id, 'weekly_periods' => 5],
                        ['subject_id' => $subIsl->id, 'weekly_periods' => 3],
                    ],
                ],
            ],
            'days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'],
            'overwrite_existing' => true,
        ]);

        $wizardResponse->assertOk();
        $wizardResponse->assertJsonPath('data.success', true);
        $this->assertEquals(14, $wizardResponse->json('data.created_count')); // 6 + 5 + 3 = 14 total lectures!

        // Verify exact subject distribution matches weightages
        $mathCount = TimetableEntry::where('session_id', $session->id)->where('subject_id', $subMath->id)->count();
        $sciCount = TimetableEntry::where('session_id', $session->id)->where('subject_id', $subSci->id)->count();
        $islCount = TimetableEntry::where('session_id', $session->id)->where('subject_id', $subIsl->id)->count();

        $this->assertEquals(6, $mathCount, 'Mathematics weekly periods count mismatch');
        $this->assertEquals(5, $sciCount, 'Science weekly periods count mismatch');
        $this->assertEquals(3, $islCount, 'Islamiat weekly periods count mismatch');
    }

    public function test_wizard_generate_with_days_config_and_curriculum_map_payload(): void
    {
        [$user, $institute, $session] = $this->createInstituteContext();

        $teacher1 = User::factory()->create();
        $teacher2 = User::factory()->create();

        foreach ([$teacher1, $teacher2] as $t) {
            InstituteUser::create(['user_id' => $t->id, 'institute_id' => $institute->id, 'is_active' => true]);
        }

        $class1 = AcademicClass::create(['institute_id' => $institute->id, 'name' => 'Class 1', 'code' => 'C1']);
        $sub3 = Subject::create(['institute_id' => $institute->id, 'name' => 'English', 'code' => 'ENG']);
        $sub4 = Subject::create(['institute_id' => $institute->id, 'name' => 'Math', 'code' => 'MTH']);

        SubjectAllocation::create(['session_id' => $session->id, 'class_id' => $class1->id, 'subject_id' => $sub3->id, 'teacher_user_id' => $teacher1->id]);
        SubjectAllocation::create(['session_id' => $session->id, 'class_id' => $class1->id, 'subject_id' => $sub4->id, 'teacher_user_id' => $teacher2->id]);

        Sanctum::actingAs($user);

        $payload = [
            'periodDuration' => 60,
            'daysConfig' => [
                [
                    'name' => 'Monday',
                    'active' => true,
                    'startTime' => '09:00',
                    'endTime' => '14:00',
                    'hasBreak' => true,
                    'breakStart' => '11:00',
                    'breakEnd' => '12:00',
                ],
                [
                    'name' => 'Tuesday',
                    'active' => true,
                    'startTime' => '09:00',
                    'endTime' => '14:00',
                    'hasBreak' => true,
                    'breakStart' => '11:00',
                    'breakEnd' => '12:00',
                ],
                [
                    'name' => 'Wednesday',
                    'active' => true,
                    'startTime' => '09:00',
                    'endTime' => '14:00',
                    'hasBreak' => true,
                    'breakStart' => '11:00',
                    'breakEnd' => '12:00',
                ],
                [
                    'name' => 'Thursday',
                    'active' => true,
                    'startTime' => '09:00',
                    'endTime' => '14:00',
                    'hasBreak' => true,
                    'breakStart' => '11:00',
                    'breakEnd' => '12:00',
                ],
                [
                    'name' => 'Friday',
                    'active' => true,
                    'startTime' => '09:00',
                    'endTime' => '12:00',
                    'hasBreak' => false,
                    'breakStart' => '11:00',
                    'breakEnd' => '11:30',
                ],
                [
                    'name' => 'Saturday',
                    'active' => true,
                    'startTime' => '09:00',
                    'endTime' => '14:00',
                    'hasBreak' => true,
                    'breakStart' => '11:00',
                    'breakEnd' => '12:00',
                ],
                [
                    'name' => 'Sunday',
                    'active' => false,
                    'startTime' => '09:00',
                    'endTime' => '14:00',
                    'hasBreak' => true,
                    'breakStart' => '11:00',
                    'breakEnd' => '12:00',
                ],
            ],
            'curriculum' => [
                (string) $class1->id => [
                    (string) $sub3->id => 3,
                    (string) $sub4->id => 3,
                ],
            ],
        ];

        $response = $this->postJson('/api/institutes/timetable/wizard-generate', $payload);
        $response->assertOk();
        $response->assertJsonPath('data.success', true);
        $this->assertEquals(6, $response->json('data.created_count'));

        $this->assertEquals(3, TimetableEntry::where('session_id', $session->id)->where('subject_id', $sub3->id)->count());
        $this->assertEquals(3, TimetableEntry::where('session_id', $session->id)->where('subject_id', $sub4->id)->count());
    }

    public function test_auto_generates_timetable_for_all_sections_of_a_class(): void
    {
        [$user, $institute, $session] = $this->createInstituteContext();

        $teacher1 = User::factory()->create(['name' => 'Sir Math']);
        $teacher2 = User::factory()->create(['name' => 'Sir Science']);

        foreach ([$teacher1, $teacher2] as $t) {
            InstituteUser::create(['user_id' => $t->id, 'institute_id' => $institute->id, 'is_active' => true]);
        }

        // Class 9 with 2 Sections: Section A and Section B
        $class9 = AcademicClass::create(['institute_id' => $institute->id, 'name' => 'Grade 9', 'code' => 'G9']);
        $secA = AcademicSection::create(['class_id' => $class9->id, 'name' => 'Section A', 'code' => '9A']);
        $secB = AcademicSection::create(['class_id' => $class9->id, 'name' => 'Section B', 'code' => '9B']);

        $subMath = Subject::create(['institute_id' => $institute->id, 'name' => 'Mathematics', 'code' => 'MATH']);
        $subSci = Subject::create(['institute_id' => $institute->id, 'name' => 'Science', 'code' => 'SCI']);

        // Allocations assigned at class level (section_id = null)
        SubjectAllocation::create(['session_id' => $session->id, 'class_id' => $class9->id, 'section_id' => null, 'subject_id' => $subMath->id, 'teacher_user_id' => $teacher1->id]);
        SubjectAllocation::create(['session_id' => $session->id, 'class_id' => $class9->id, 'section_id' => null, 'subject_id' => $subSci->id, 'teacher_user_id' => $teacher2->id]);

        Sanctum::actingAs($user);

        $payload = [
            'periodDuration' => 45,
            'daysConfig' => [
                ['name' => 'Monday', 'active' => true, 'startTime' => '08:00', 'endTime' => '12:00', 'hasBreak' => false],
                ['name' => 'Tuesday', 'active' => true, 'startTime' => '08:00', 'endTime' => '12:00', 'hasBreak' => false],
            ],
            'curriculum' => [
                (string) $class9->id => [
                    (string) $subMath->id => 2,
                    (string) $subSci->id => 2,
                ],
            ],
        ];

        $response = $this->postJson('/api/institutes/timetable/wizard-generate', $payload);
        $response->assertOk();
        $response->assertJsonPath('data.success', true);

        // Verify Section A entries exist (2 Math + 2 Science = 4)
        $secAEntries = TimetableEntry::where('session_id', $session->id)->where('class_id', $class9->id)->where('section_id', $secA->id)->get();
        $this->assertCount(4, $secAEntries);

        // Verify Section B entries exist (2 Math + 2 Science = 4)
        $secBEntries = TimetableEntry::where('session_id', $session->id)->where('class_id', $class9->id)->where('section_id', $secB->id)->get();
        $this->assertCount(4, $secBEntries);

        // Verify Teacher 1 is never double booked across Section A and Section B at the same time
        foreach ($secAEntries as $entryA) {
            $clashExists = $secBEntries->contains(function ($entryB) use ($entryA) {
                return $entryB->teacher_user_id === $entryA->teacher_user_id
                    && $entryB->day_of_week === $entryA->day_of_week
                    && $entryB->time_slot_id === $entryA->time_slot_id;
            });
            $this->assertFalse($clashExists, 'Teacher clash detected between Section A and Section B!');
        }

        // Test GET class schedule for Section A
        $viewSecA = $this->getJson("/api/institutes/timetable/class?class_id={$class9->id}&section_id={$secA->id}");
        $viewSecA->assertOk();
        $viewSecA->assertJsonPath('data.section.name', 'Section A');

        // Test GET class schedule for Section B
        $viewSecB = $this->getJson("/api/institutes/timetable/class?class_id={$class9->id}&section_id={$secB->id}");
        $viewSecB->assertOk();
        $viewSecB->assertJsonPath('data.section.name', 'Section B');
    }
}
