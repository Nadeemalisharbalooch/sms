# TODO

## Section Assign Teacher Module

- [x] Create migration `2026_08_04_000000_create_section_teacher_table.php`
- [x] Create `SectionTeacher` model with `section()` and `teacher()` relations
- [x] Add `sectionTeachers()` relation to `AcademicSection` model
- [x] Add `sectionTeachers()` relation to `User` model
- [x] Create `StoreSectionTeacherRequest` and `UpdateSectionTeacherRequest`
- [x] Create `SectionTeacherResource`
- [x] Create `SectionTeacherController` (full CRUD)
- [x] Register `institutes/section-teachers` API routes
- [x] PHP lint all modified files
- [x] Register routes verified via `php artisan route:list`
- [ ] Run `php artisan migrate` (blocked: MySQL server not running on port 3306)

## Section Subjects Endpoint

- [x] Modify `AcademicSectionController@classes` to return the subjects assigned to the section via `class_subjects` (filtered by class_id + section_id), using `ClassSubjectResource`
