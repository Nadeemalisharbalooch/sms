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
        $masterResponse->assertJsonCount(1, 'data.entries');
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

    public function test_can_export_timetable_as_html_and_excel(): void
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
}
