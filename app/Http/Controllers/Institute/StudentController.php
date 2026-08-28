<?php

namespace App\Http\Controllers\Institute;

use App\Http\Controllers\Controller;
use App\Http\Requests\Institute\PromoteClassRequest;
use App\Http\Requests\Institute\PromoteStudentRequest;
use App\Http\Requests\Institute\StoreStudentRequest;
use App\Http\Requests\Institute\UpdateStudentEnrollmentRequest;
use App\Http\Requests\Institute\UpdateStudentRequest;
use App\Http\Resources\Institute\StudentResource;
use App\Models\AcademicClass;
use App\Models\AcademicSection;
use App\Models\AcademicSession;
use App\Models\Enrollment;
use App\Models\InstituteUser;
use App\Models\Student;
use App\Services\ResponseService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StudentController extends Controller
{
    private const IMPORT_HEADERS = [
        'first_name', 'last_name', 'dob', 'gender', 'guardian_name',
        'guardian_phone', 'address', 'admission_date', 'class_id',
        'section_id', 'roll_number',
    ];

    public function index(Request $request)
    {
        $instituteId = $this->activeInstituteId($request);

        if ($instituteId === null) {
            return ResponseService::error('No active institute is associated with this user', 422);
        }

        $request->validate([
            'session_id' => ['nullable', 'integer'],
            'class_id' => ['nullable', 'integer'],
            'section_id' => ['nullable', 'integer'],
        ]);

        $students = Student::query()
            ->where('institute_id', $instituteId)
            ->when(
                $request->filled('session_id') || $request->filled('class_id') || $request->filled('section_id'),
                function ($query) use ($request) {
                    $query->whereHas('enrollments', function ($enrollmentQuery) use ($request) {
                        $enrollmentQuery
                            ->when($request->filled('session_id'), fn ($q) => $q->where('session_id', $request->integer('session_id')))
                            ->when($request->filled('class_id'), fn ($q) => $q->where('class_id', $request->integer('class_id')))
                            ->when($request->filled('section_id'), fn ($q) => $q->where('section_id', $request->integer('section_id')));
                    });
                }
            )
            ->with(['enrollments' => function ($query) use ($request) {
                $query
                    ->when($request->filled('session_id'), fn ($q) => $q->where('session_id', $request->integer('session_id')))
                    ->when($request->filled('class_id'), fn ($q) => $q->where('class_id', $request->integer('class_id')))
                    ->when($request->filled('section_id'), fn ($q) => $q->where('section_id', $request->integer('section_id')))
                    ->latest('id');
            }])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate();

        return ResponseService::success(StudentResource::collection($students), 'Students retrieved successfully');
    }

    public function store(StoreStudentRequest $request)
    {
        $instituteId = $this->activeInstituteId($request);

        if ($instituteId === null) {
            return ResponseService::error('No active institute is associated with this user', 422);
        }

        $validated = $request->validated();
        $error = $this->validateEnrollmentScope($instituteId, $validated);

        if ($error !== null) {
            return $error;
        }

        $error = $this->validateUniqueStudentProfile($instituteId, $validated);

        if ($error !== null) {
            return $error;
        }

        $student = DB::transaction(function () use ($validated, $instituteId) {
            $student = Student::create([
                ...collect($validated)->except(['session_id', 'class_id', 'section_id', 'roll_number'])->all(),
                'institute_id' => $instituteId,
                'admission_date' => $validated['admission_date'] ?? now()->toDateString(),
            ]);

            $student->enrollments()->create([
                'session_id' => $validated['session_id'],
                'class_id' => $validated['class_id'],
                'section_id' => $validated['section_id'] ?? null,
                'roll_number' => $validated['roll_number'] ?? null,
            ]);

            return $student;
        });

        return ResponseService::success(
            new StudentResource($student->load('enrollments')),
            'Student admitted successfully',
            201
        );
    }

    /**
     * Import student profiles and active-session enrollments from an Excel or CSV file.
     * Supports passing class_id and section_id via request payload.
     */
    public function import(Request $request): JsonResponse
    {
        $instituteId = $this->activeInstituteId($request);

        if ($instituteId === null) {
            return ResponseService::error('No active institute is associated with this user', 422);
        }

        $sessionId = $request->integer('session_id') ?: $this->activeSessionId($instituteId);

        if ($sessionId === null) {
            return ResponseService::error('Validation failed', 422, [
                'session_id' => ['No active academic session exists for the active institute.'],
            ]);
        }

        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:20480'],
            'class_id' => ['nullable', 'integer'],
            'section_id' => ['nullable', 'integer'],
        ]);

        $defaultClassId = $request->integer('class_id') ?: null;
        $defaultSectionId = $request->integer('section_id') ?: null;

        // Verify class if provided in payload
        $targetClass = null;
        if ($defaultClassId !== null) {
            $targetClass = AcademicClass::query()
                ->whereKey($defaultClassId)
                ->where('institute_id', $instituteId)
                ->first();

            if ($targetClass === null) {
                return ResponseService::error('Validation failed', 422, [
                    'class_id' => ['The selected class does not belong to the active institute.'],
                ]);
            }
        }

        // Verify section if provided in payload
        $targetSection = null;
        if ($defaultSectionId !== null) {
            $targetSection = AcademicSection::query()
                ->whereKey($defaultSectionId)
                ->when($defaultClassId !== null, fn ($q) => $q->where('class_id', $defaultClassId))
                ->first();

            if ($targetSection === null) {
                return ResponseService::error('Validation failed', 422, [
                    'section_id' => ['The selected section does not belong to the selected class.'],
                ]);
            }
        }

        $uploadedFile = $request->file('file');

        try {
            $spreadsheet = IOFactory::load($uploadedFile->getRealPath());
            $worksheet = $spreadsheet->getActiveSheet();
            $highestRow = $worksheet->getHighestDataRow();
            $highestColumn = $worksheet->getHighestDataColumn();
        } catch (\Throwable $e) {
            return ResponseService::error('Validation failed', 422, [
                'file' => ['The uploaded file could not be read as an Excel or CSV file. ' . $e->getMessage()],
            ]);
        }

        if ($highestRow < 2) {
            return ResponseService::error('Validation failed', 422, [
                'file' => ['The import file must contain at least one student data row below the header.'],
            ]);
        }

        // Read header row
        $headerValues = $worksheet->rangeToArray("A1:{$highestColumn}1", null, true, false, false)[0] ?? [];
        $columnMap = [];

        foreach ($headerValues as $colIdx => $colHeader) {
            $normalizedField = $this->mapHeaderToField((string) $colHeader);
            if ($normalizedField !== null && ! isset($columnMap[$normalizedField])) {
                $columnMap[$normalizedField] = $colIdx;
            }
        }

        // Check if we at least found a name or first_name column
        if (! isset($columnMap['name']) && ! isset($columnMap['first_name'])) {
            return ResponseService::error('Validation failed', 422, [
                'file' => ['Could not find a student Name or First Name column in the header row. Available headers: ' . implode(', ', array_filter(array_map('trim', $headerValues)))],
            ]);
        }

        $instituteClasses = AcademicClass::query()
            ->where('institute_id', $instituteId)
            ->pluck('id', 'id');

        $sectionClassIds = AcademicSection::query()
            ->pluck('class_id', 'id');

        $rowsToCreate = [];
        $rowErrors = [];
        $seenProfiles = [];

        for ($rowNum = 2; $rowNum <= $highestRow; $rowNum++) {
            $rowCells = $worksheet->rangeToArray("A{$rowNum}:{$highestColumn}{$rowNum}", null, true, false, false)[0] ?? [];

            // Skip completely blank rows
            if (collect($rowCells)->every(fn ($v) => $v === null || trim((string) $v) === '')) {
                continue;
            }

            $rawRow = [];
            foreach ($columnMap as $field => $colIdx) {
                $rawRow[$field] = isset($rowCells[$colIdx]) ? trim((string) $rowCells[$colIdx]) : null;
            }

            // Extract Name (split full_name into first_name and last_name if needed)
            $firstName = $rawRow['first_name'] ?? null;
            $lastName = $rawRow['last_name'] ?? null;

            if (empty($firstName) && ! empty($rawRow['name'])) {
                [$firstName, $lastName] = $this->splitFullName($rawRow['name']);
            }

            if (empty($firstName)) {
                $rowErrors[$rowNum][] = 'Student name or first_name is required.';
                continue;
            }

            $lastName = $lastName ?: '-';

            // Guardian / Father Name
            $guardianName = $rawRow['guardian_name'] ?? $rawRow['father_name'] ?? null;
            if (empty($guardianName)) {
                $guardianName = 'Guardian of ' . $firstName;
            }

            // Phone
            $guardianPhone = $rawRow['guardian_phone'] ?? $rawRow['phone'] ?? '03000000000';

            // Gender
            $gender = $this->normalizeGender($rawRow['gender'] ?? null);

            // Dates
            $dob = $this->normalizeImportDate($rawRow['dob'] ?? null) ?: now()->subYears(12)->format('Y-m-d');
            $admissionDate = $this->normalizeImportDate($rawRow['admission_date'] ?? null) ?: now()->format('Y-m-d');

            // Class & Section ID
            $rowClassId = ! empty($rawRow['class_id']) && is_numeric($rawRow['class_id'])
                ? (int) $rawRow['class_id']
                : $defaultClassId;

            $rowSectionId = ! empty($rawRow['section_id']) && is_numeric($rawRow['section_id'])
                ? (int) $rawRow['section_id']
                : $defaultSectionId;

            if ($rowClassId === null) {
                $rowErrors[$rowNum][] = 'class_id is required either in the upload parameters or inside the Excel sheet.';
                continue;
            }

            if (! $instituteClasses->has($rowClassId)) {
                $rowErrors[$rowNum][] = "class_id [{$rowClassId}] does not belong to the active institute.";
                continue;
            }

            if ($rowSectionId !== null && (! $sectionClassIds->has($rowSectionId) || (int) $sectionClassIds->get($rowSectionId) !== $rowClassId)) {
                $rowErrors[$rowNum][] = "section_id [{$rowSectionId}] does not belong to class [{$rowClassId}].";
                continue;
            }

            $rollNumber = ! empty($rawRow['roll_number']) ? (string) $rawRow['roll_number'] : null;
            $address = ! empty($rawRow['address']) ? (string) $rawRow['address'] : null;

            // Check duplicate in seen batch
            $profileKey = strtolower($firstName . '|' . $dob . '|' . $guardianName . '|' . ($rollNumber ?? ''));
            if (isset($seenProfiles[$profileKey])) {
                $rowErrors[$rowNum][] = 'Duplicate student profile found within the same spreadsheet.';
                continue;
            }
            $seenProfiles[$profileKey] = true;

            $rowsToCreate[] = [
                'row_number' => $rowNum,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'guardian_name' => $guardianName,
                'guardian_phone' => $guardianPhone,
                'gender' => $gender,
                'dob' => $dob,
                'admission_date' => $admissionDate,
                'address' => $address,
                'class_id' => $rowClassId,
                'section_id' => $rowSectionId,
                'roll_number' => $rollNumber,
            ];
        }

        if (! empty($rowErrors)) {
            return ResponseService::error('Import validation failed', 422, ['rows' => $rowErrors]);
        }

        if (empty($rowsToCreate)) {
            return ResponseService::error('Validation failed', 422, [
                'file' => ['No valid student rows found to import.'],
            ]);
        }

        $createdStudents = DB::transaction(function () use ($rowsToCreate, $instituteId, $sessionId) {
            $created = [];
            foreach ($rowsToCreate as $item) {
                $student = Student::create([
                    'institute_id' => $instituteId,
                    'first_name' => $item['first_name'],
                    'last_name' => $item['last_name'],
                    'dob' => $item['dob'],
                    'gender' => $item['gender'],
                    'guardian_name' => $item['guardian_name'],
                    'guardian_phone' => $item['guardian_phone'],
                    'address' => $item['address'],
                    'admission_date' => $item['admission_date'],
                ]);

                $enrollment = $student->enrollments()->create([
                    'session_id' => $sessionId,
                    'class_id' => $item['class_id'],
                    'section_id' => $item['section_id'],
                    'roll_number' => $item['roll_number'],
                ]);

                $created[] = [
                    'id' => $student->id,
                    'name' => trim($student->first_name . ' ' . $student->last_name),
                    'guardian_name' => $student->guardian_name,
                    'roll_number' => $enrollment->roll_number,
                    'class_id' => $enrollment->class_id,
                    'section_id' => $enrollment->section_id,
                ];
            }

            return $created;
        });

        return ResponseService::success([
            'session_id' => $sessionId,
            'class' => $targetClass ? ['id' => $targetClass->id, 'name' => $targetClass->name] : null,
            'section' => $targetSection ? ['id' => $targetSection->id, 'name' => $targetSection->name] : null,
            'total_rows' => count($rowsToCreate),
            'imported_count' => count($createdStudents),
            'students' => $createdStudents,
        ], 'Students imported and enrolled successfully', 201);
    }

    /** Download a ready-to-fill Excel import template. */
    public function importTemplate(Request $request): StreamedResponse|JsonResponse
    {
        if ($this->activeInstituteId($request) === null) {
            return ResponseService::error('No active institute is associated with this user', 422);
        }

        $classId = $request->integer('class_id') ?: null;
        $sectionId = $request->integer('section_id') ?: null;

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Student Import');

        $headers = [
            'Student Name',
            'Father / Guardian Name',
            'Roll Number',
            'Gender',
            'Date of Birth',
            'Guardian Phone',
            'Address',
            'Admission Date',
        ];

        if ($classId === null) {
            $headers[] = 'Class ID';
            $headers[] = 'Section ID';
        }

        $sheet->fromArray($headers, null, 'A1');

        $sampleRow = [
            'Muhammad Ali',
            'Tariq Khan',
            '101',
            'male',
            '2012-05-15',
            '03001234567',
            'House 123, Street 4, City',
            now()->format('Y-m-d'),
        ];

        if ($classId === null) {
            $sampleRow[] = 1;
            $sampleRow[] = 1;
        }

        $sheet->fromArray([$sampleRow], null, 'A2');
        $sheet->getStyle('A1:J1')->getFont()->setBold(true);
        $sheet->freezePane('A2');

        foreach (range('A', 'J') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $filename = 'student-import-template' . ($classId ? "-class-{$classId}" : '') . '.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function show(Request $request, Student $student)
    {
        if (! $this->belongsToActiveInstitute($request, $student)) {
            return ResponseService::notFound('Student not found');
        }

        return ResponseService::success(
            new StudentResource($student->load(['enrollments' => fn ($query) => $query->latest('id')])),
            'Student retrieved successfully'
        );
    }

    public function update(UpdateStudentRequest $request, Student $student)
    {
        if (! $this->belongsToActiveInstitute($request, $student)) {
            return ResponseService::notFound('Student not found');
        }

        $validated = $request->validated();
        $rollNumber = $validated['roll_number'] ?? null;

        $profile = [
            'first_name' => $validated['first_name'] ?? $student->first_name,
            'dob' => $validated['dob'] ?? $student->dob->toDateString(),
            'guardian_name' => $validated['guardian_name'] ?? $student->guardian_name,
            'roll_number' => $request->has('roll_number')
                ? $rollNumber
                : $student->enrollments()->latest('id')->value('roll_number'),
        ];

        $error = $this->validateUniqueStudentProfile($student->institute_id, $profile, $student->id);

        if ($error !== null) {
            return $error;
        }

        unset($validated['roll_number']);

        DB::transaction(function () use ($student, $validated, $request, $rollNumber) {
            $student->update($validated);

            if ($request->has('roll_number')) {
                $student->enrollments()->latest('id')->first()?->update(['roll_number' => $rollNumber]);
            }
        });

        return ResponseService::success(
            new StudentResource($student->fresh()->load(['enrollments' => fn ($query) => $query->latest('id')])),
            'Student updated successfully'
        );
    }

    public function updateEnrollment(UpdateStudentEnrollmentRequest $request, Student $student)
    {
        if (! $this->belongsToActiveInstitute($request, $student)) {
            return ResponseService::notFound('Student not found');
        }

        $sessionId = AcademicSession::query()
            ->where('institute_id', $student->institute_id)
            ->where('is_active', true)
            ->value('id');

        if ($sessionId === null) {
            return ResponseService::error('Validation failed', 422, [
                'session_id' => ['No active academic session exists for the active institute.'],
            ]);
        }

        $validated = [
            ...$request->validated(),
            'session_id' => (int) $sessionId,
        ];

        $error = $this->validateEnrollmentScope($student->institute_id, $validated);

        if ($error !== null) {
            return $error;
        }

        DB::transaction(function () use ($student, $validated) {
            // The student/session unique index and this lookup ensure there can only be one current-session enrollment.
            $student->enrollments()->updateOrCreate(
                ['session_id' => $validated['session_id']],
                [
                    'class_id' => $validated['class_id'],
                    'section_id' => $validated['section_id'] ?? null,
                    'roll_number' => $validated['roll_number'] ?? null,
                ]
            );
        });

        return ResponseService::success(
            new StudentResource($student->fresh()->load(['enrollments' => fn ($query) => $query->latest('id')])),
            'Student enrollment updated successfully'
        );
    }

    public function promote(PromoteStudentRequest $request, Student $student)
    {
        if (! $this->belongsToActiveInstitute($request, $student)) {
            return ResponseService::notFound('Student not found');
        }

        $currentSessionId = $this->activeSessionId($student->institute_id);
        $validated = $request->validated();
        $error = $this->validatePromotionScope($student->institute_id, $currentSessionId, $validated);

        if ($error !== null) {
            return $error;
        }

        $currentEnrollment = $student->enrollments()->where('session_id', $currentSessionId)->first();

        if ($currentEnrollment === null) {
            return ResponseService::error('Validation failed', 422, [
                'student' => ['The student is not enrolled in the current academic session.'],
            ]);
        }

        if ($student->enrollments()->where('session_id', $validated['target_session_id'])->exists()) {
            return $this->targetEnrollmentExistsError();
        }

        DB::transaction(function () use ($student, $currentEnrollment, $validated) {
            $currentEnrollment->update(['result_status' => $validated['status']]);
            $student->enrollments()->create([
                'session_id' => $validated['target_session_id'],
                'class_id' => $validated['target_class_id'],
                'section_id' => $validated['target_section_id'] ?? null,
                'roll_number' => $validated['roll_number'] ?? null,
            ]);
        });

        return ResponseService::success(
            new StudentResource($student->fresh()->load(['enrollments' => fn ($query) => $query->where('session_id', $validated['target_session_id'])])),
            'Student promoted successfully'
        );
    }

    public function promoteClass(PromoteClassRequest $request)
    {
        $instituteId = $this->activeInstituteId($request);

        if ($instituteId === null) {
            return ResponseService::error('No active institute is associated with this user', 422);
        }

        $validated = $request->validated();
        $errors = $this->validateBulkPromotionScope($instituteId, $validated);

        if ($errors !== []) {
            return ResponseService::error('Validation failed', 422, $errors);
        }

        DB::transaction(function () use ($validated) {
            foreach ($validated['promotions'] as $promotion) {
                Enrollment::query()
                    ->where('student_id', $promotion['student_id'])
                    ->where('session_id', $validated['from_session_id'])
                    ->update(['result_status' => $promotion['promotion_status']]);

                if (in_array($promotion['promotion_status'], ['promoted', 'retained'], true)) {
                    Enrollment::query()->updateOrCreate(
                        [
                            'student_id' => $promotion['student_id'],
                            'session_id' => $validated['to_session_id'],
                        ],
                        [
                            'class_id' => $promotion['class_id'],
                            'section_id' => $promotion['section_id'] ?? null,
                            'roll_number' => $promotion['roll_number'] ?? null,
                        ]
                    );
                }
            }
        });

        return ResponseService::success([
            'processed_count' => count($validated['promotions']),
            'promoted_count' => collect($validated['promotions'])->where('promotion_status', 'promoted')->count(),
            'retained_count' => collect($validated['promotions'])->where('promotion_status', 'retained')->count(),
            'graduated_count' => collect($validated['promotions'])->where('promotion_status', 'graduated')->count(),
            'left_count' => collect($validated['promotions'])->where('promotion_status', 'left')->count(),
        ], 'Student promotions processed successfully');
    }

    public function destroy(Request $request, Student $student)
    {
        if (! $this->belongsToActiveInstitute($request, $student)) {
            return ResponseService::notFound('Student not found');
        }

        $student->delete();

        return ResponseService::success(null, 'Student deleted successfully');
    }

    private function validateEnrollmentScope(int $instituteId, array $validated): ?JsonResponse
    {
        if (! AcademicSession::query()->whereKey($validated['session_id'])->where('institute_id', $instituteId)->exists()) {
            return ResponseService::error('Validation failed', 422, ['session_id' => ['The selected session does not belong to the active institute.']]);
        }

        if (! AcademicClass::query()->whereKey($validated['class_id'])->where('institute_id', $instituteId)->exists()) {
            return ResponseService::error('Validation failed', 422, ['class_id' => ['The selected class does not belong to the active institute.']]);
        }

        if (($validated['section_id'] ?? null) !== null && ! AcademicSection::query()
            ->whereKey($validated['section_id'])
            ->where('class_id', $validated['class_id'])
            ->exists()) {
            return ResponseService::error('Validation failed', 422, ['section_id' => ['The selected section does not belong to the selected class.']]);
        }

        return null;
    }

    private function validatePromotionScope(int $instituteId, ?int $currentSessionId, array $validated): ?JsonResponse
    {
        if ($currentSessionId === null) {
            return ResponseService::error('Validation failed', 422, [
                'session_id' => ['No active academic session exists for the active institute.'],
            ]);
        }

        if ((int) $validated['target_session_id'] === $currentSessionId) {
            return ResponseService::error('Validation failed', 422, [
                'target_session_id' => ['The promotion session must be different from the current academic session.'],
            ]);
        }

        return $this->validateEnrollmentScope($instituteId, [
            'session_id' => $validated['target_session_id'],
            'class_id' => $validated['class_id'] ?? $validated['target_class_id'],
            'section_id' => $validated['section_id'] ?? $validated['target_section_id'] ?? null,
        ]);
    }

    private function targetEnrollmentExistsError(): JsonResponse
    {
        return ResponseService::error('Validation failed', 422, [
            'target_session_id' => ['One or more selected students are already enrolled in the target academic session.'],
        ]);
    }

    private function validateBulkPromotionScope(int $instituteId, array $validated): array
    {
        $errors = [];

        $sessionCount = AcademicSession::query()
            ->where('institute_id', $instituteId)
            ->whereIn('id', [$validated['from_session_id'], $validated['to_session_id']])
            ->count();

        if ($sessionCount !== 2) {
            $errors['session_id'] = ['Both sessions must belong to the active institute.'];
        }

        $studentIds = collect($validated['promotions'])->pluck('student_id');
        $enrolledStudentIds = Enrollment::query()
            ->where('session_id', $validated['from_session_id'])
            ->whereIn('student_id', $studentIds)
            ->pluck('student_id');
        $instituteStudentIds = Student::query()
            ->where('institute_id', $instituteId)
            ->whereIn('id', $studentIds)
            ->pluck('id');

        foreach ($validated['promotions'] as $index => $promotion) {
            if (! $instituteStudentIds->contains($promotion['student_id'])) {
                $errors["promotions.$index.student_id"][] = 'The selected student does not belong to the active institute.';
            }

            if (! $enrolledStudentIds->contains($promotion['student_id'])) {
                $errors["promotions.$index.student_id"][] = 'The student is not enrolled in the from session.';
            }

            $needsEnrollment = in_array($promotion['promotion_status'], ['promoted', 'retained'], true);

            if ($needsEnrollment && $promotion['class_id'] === null) {
                $errors["promotions.$index.class_id"][] = 'A class is required for promoted or retained students.';

                continue;
            }

            if (! $needsEnrollment) {
                continue;
            }

            $classBelongsToInstitute = AcademicClass::query()
                ->whereKey($promotion['class_id'])
                ->where('institute_id', $instituteId)
                ->exists();

            if (! $classBelongsToInstitute) {
                $errors["promotions.$index.class_id"][] = 'The selected class does not belong to the active institute.';
            }

            if (($promotion['section_id'] ?? null) !== null && ! AcademicSection::query()
                ->whereKey($promotion['section_id'])
                ->where('class_id', $promotion['class_id'])
                ->exists()) {
                $errors["promotions.$index.section_id"][] = 'The selected section does not belong to the selected class.';
            }
        }

        return $errors;
    }

    private function activeSessionId(int $instituteId): ?int
    {
        $sessionId = AcademicSession::query()
            ->where('institute_id', $instituteId)
            ->where('is_active', true)
            ->value('id');

        return $sessionId === null ? null : (int) $sessionId;
    }

    private function validateUniqueStudentProfile(int $instituteId, array $profile, ?int $ignoreStudentId = null): ?JsonResponse
    {
        $rollNumber = $profile['roll_number'] ?? null;

        $duplicateExists = Student::query()
            ->where('institute_id', $instituteId)
            ->where('first_name', $profile['first_name'])
            ->whereDate('dob', $profile['dob'])
            ->where('guardian_name', $profile['guardian_name'])
            ->when($ignoreStudentId !== null, fn ($query) => $query->whereKeyNot($ignoreStudentId))
            ->whereHas('enrollments', function ($query) use ($rollNumber) {
                $rollNumber === null
                    ? $query->whereNull('roll_number')
                    : $query->where('roll_number', $rollNumber);
            })
            ->exists();

        if (! $duplicateExists) {
            return null;
        }

        return ResponseService::error('Validation failed', 422, [
            'first_name' => ['A student with the same first name, date of birth, guardian name, and roll number already exists.'],
        ]);
    }

    private function activeInstituteId(Request $request): ?int
    {
        $instituteId = InstituteUser::query()->where('user_id', $request->user()->id)->where('is_active', true)->value('institute_id');

        return $instituteId === null ? null : (int) $instituteId;
    }

    private function belongsToActiveInstitute(Request $request, Student $student): bool
    {
        return $student->institute_id === $this->activeInstituteId($request);
    }

    /**
     * Map fuzzy or localized Excel column header to a standard field name.
     */
    private function mapHeaderToField(string $header): ?string
    {
        $clean = strtolower(trim(preg_replace('/[^a-zA-Z0-9]/', '', $header)));

        $aliases = [
            'name' => ['name', 'fullname', 'studentname', 'student', 'nameofstudent'],
            'first_name' => ['firstname', 'fname', 'first'],
            'last_name' => ['lastname', 'lname', 'surname', 'last'],
            'guardian_name' => ['guardianname', 'fathername', 'father', 'guardian', 'parentname', 'parent', 'guardianfathername', 'fathersname'],
            'guardian_phone' => ['guardianphone', 'phone', 'phonenumber', 'mobile', 'mobilenumber', 'contact', 'contactno', 'guardiancontact', 'fatherphone', 'cell', 'whatsapp', 'emergencycontact'],
            'roll_number' => ['rollnumber', 'rollno', 'roll', 'regno', 'registrationno', 'admissionno', 'grno', 'grnumber', 'studentid', 'rollnum'],
            'gender' => ['gender', 'sex'],
            'dob' => ['dob', 'dateofbirth', 'birthdate', 'd_o_b', 'birth'],
            'address' => ['address', 'city', 'residentialaddress', 'location', 'residence'],
            'admission_date' => ['admissiondate', 'dateofadmission', 'doa', 'joiningdate', 'admission'],
            'class_id' => ['classid', 'class'],
            'section_id' => ['sectionid', 'section'],
        ];

        foreach ($aliases as $field => $fieldAliases) {
            if (in_array($clean, $fieldAliases, true)) {
                return $field;
            }
        }

        return null;
    }

    /**
     * Split a full name string into [first_name, last_name].
     */
    private function splitFullName(string $fullName): array
    {
        $parts = preg_split('/\s+/', trim($fullName));
        if (count($parts) <= 1) {
            return [$parts[0] ?? 'Student', '-'];
        }

        $lastName = array_pop($parts);
        $firstName = implode(' ', $parts);

        return [$firstName, $lastName];
    }

    /**
     * Normalize gender string to 'male', 'female', or 'other'.
     */
    private function normalizeGender(?string $gender): string
    {
        $clean = strtolower(trim((string) $gender));
        if (in_array($clean, ['f', 'female', 'girl', 'woman'], true)) {
            return 'female';
        }
        if (in_array($clean, ['o', 'other'], true)) {
            return 'other';
        }

        return 'male';
    }

    /**
     * Normalize date value from Excel (numeric serial or string) to Y-m-d.
     */
    private function normalizeImportDate($value): ?string
    {
        if (empty($value)) {
            return null;
        }

        if (is_numeric($value)) {
            try {
                return ExcelDate::excelToDateTimeObject((float) $value)->format('Y-m-d');
            } catch (\Throwable) {
            }
        }

        try {
            return Carbon::parse((string) $value)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }
}
