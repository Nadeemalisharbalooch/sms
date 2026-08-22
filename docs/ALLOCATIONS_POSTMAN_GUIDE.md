# Allocations API — Postman Guide

Complete guide for testing all 3 allocation modules with Postman.

## Base Setup

- **Base URL:** `http://sms.test/api`
- **Headers:**
  - `Authorization: Bearer {token}`
  - `Accept: application/json`
  - `Content-Type: application/json`

---

## Module 1: Assign Subjects to Classes (Syllabus Setup)

Assign subject IDs to a class. Backend automatically applies to sections if class has them.

### URL

```
POST {{base_url}}/institutes/classes/{class_id}/subjects
```

### Example URL

```
POST http://sms.test/api/institutes/classes/1/subjects
```

### Request Body (JSON)

**Option 1: Assign to a SPECIFIC section:**

```json
{
  "section_id": 1,
  "subject_ids": [1, 2, 5, 8]
}
```

**Option 2: Assign to ALL sections (or class if no sections exist):**

```json
{
  "subject_ids": [1, 2, 5, 8]
}
```

### Behavior

| Payload | Result |
| --- | --- |
| `section_id` provided | Subjects assigned to ONLY that section (`section_id = 1`) |
| No `section_id` + class HAS sections | Subjects assigned to EACH section (`section_id = 1`, `section_id = 2`) |
| No `section_id` + class has NO sections | Subjects assigned directly to class (`section_id = null`) |
| Subject ID removed in next request | Deleted from database for that class/section |

### Get Assigned Subjects for a Specific Class

```
GET http://sms.test/api/institutes/classes/1/subjects
```

### Get All Assigned Subjects (with Class & Section)

Retrieve all assigned subjects across classes and sections for the active institute.

```
GET http://sms.test/api/institutes/assigned-subjects
```
*(Also available at `GET http://sms.test/api/institutes/class-subjects`)*

**Optional Query Parameters:**
- `class_id`: Filter by specific class ID (e.g. `?class_id=1`)
- `section_id`: Filter by specific section ID, or `null` for class-level (e.g. `?section_id=2` or `?section_id=null`)
- `subject_id`: Filter by specific subject ID (e.g. `?subject_id=5`)
- `session_id`: Include teacher allocation for a specific session (e.g. `?session_id=1`)
- `teacher_id`: Filter by teacher user ID (used together with `session_id`, e.g. `?session_id=1&teacher_id=3`)

**Example Request:**
```
GET http://sms.test/api/institutes/assigned-subjects?class_id=1&session_id=1
```

**Response (Success):**
```json
{
  "status": "success",
  "message": "Assigned subjects retrieved successfully",
  "data": [
    {
      "id": 1,
      "class_id": 1,
      "section_id": 2,
      "subject_id": 5,
      "class": {
        "id": 1,
        "name": "Grade 1",
        "code": "GRADE-1"
      },
      "section": {
        "id": 2,
        "name": "Section A",
        "code": "SECTION-A"
      },
      "subject": {
        "id": 5,
        "name": "Mathematics",
        "code": "MATH",
        "description": "General Mathematics",
        "is_active": true
      },
      "teacher": {
        "id": 3,
        "name": "Ali Khan",
        "email": "ali@example.com"
      },
      "created_at": "2026-08-22T00:00:00.000000Z",
      "updated_at": "2026-08-22T00:00:00.000000Z"
    }
  ]
}
```

---

## Module 2: Assign Subject Teachers (Allocations)

Link teachers to subjects for a specific session + class + section.

### URL

```
POST {{base_url}}/institutes/allocations/subject-teachers
```

### Example URL

```
POST http://sms.test/api/institutes/allocations/subject-teachers
```

### Request Body (JSON)

```json
{
  "session_id": 1,
  "class_id": 3,
  "section_id": 2,
  "allocations": [
    { "subject_id": 1, "teacher_id": 5 },
    { "subject_id": 2, "teacher_id": 8 }
  ]
}
```

### Notes

- If class has NO sections, send `"section_id": null`.
- Unique combination: `session_id + class_id + section_id + subject_id`
- If a record already exists for this combo → it updates the teacher.
- If not → it creates a new row.

### Response (Success)

```json
{
  "status": "success",
  "message": "Subject teachers assigned successfully",
  "data": [
    {
      "id": 1,
      "session_id": 1,
      "class_id": 3,
      "section_id": 2,
      "subject_id": 1,
      "teacher_user_id": 5,
      "session": {
        "id": 1,
        "name": "2026-2027"
      },
      "class": {
        "id": 3,
        "name": "Grade 1",
        "code": "GRADE-1"
      },
      "section": {
        "id": 2,
        "name": "Section B",
        "code": "SECTION-B"
      },
      "subject": {
        "id": 1,
        "name": "Mathematics",
        "code": "MATH"
      },
      "teacher": {
        "id": 5,
        "name": "Ali Khan",
        "email": "ali@example.com"
      },
      "created_at": "2026-08-04T00:00:00.000000Z",
      "updated_at": "2026-08-04T00:00:00.000000Z"
    }
  ]
}
```

### Get All Subject Teacher Allocations

```
GET http://sms.test/api/institutes/allocations/subject-teachers
```

Optional query parameters:

```
GET http://sms.test/api/institutes/allocations/subject-teachers?session_id=1&class_id=3&section_id=2
```

### Get Single Allocation

```
GET http://sms.test/api/institutes/allocations/subject-teachers/{id}
```

### Delete Allocation

```
DELETE http://sms.test/api/institutes/allocations/subject-teachers/{id}
```

---

## Module 3: Assign Room Teacher (Homeroom)

Assign a primary administrative teacher to a classroom for the session.

### URL

```
POST {{base_url}}/institutes/allocations/room-teachers
```

### Example URL

```
POST http://sms.test/api/institutes/allocations/room-teachers
```

### Request Body (JSON)

```json
{
  "session_id": 1,
  "class_id": 3,
  "section_id": 2,
  "teacher_id": 12
}
```

### Notes

- If class has NO sections, send `"section_id": null`.
- Unique combination: `session_id + class_id + section_id`
- If record exists for this combo → updates the `teacher_id`.
- If not → creates a new row.

### Response (Success)

```json
{
  "status": "success",
  "message": "Room teacher assigned successfully",
  "data": {
    "id": 1,
    "session_id": 1,
    "class_id": 3,
    "section_id": 2,
    "teacher_user_id": 12,
    "session": {
      "id": 1,
      "name": "2026-2027"
    },
    "class": {
      "id": 3,
      "name": "Grade 1",
      "code": "GRADE-1"
    },
    "section": {
      "id": 2,
      "name": "Section B",
      "code": "SECTION-B"
    },
    "teacher": {
      "id": 12,
      "name": "Sara Ahmed",
      "email": "sara@example.com"
    },
    "created_at": "2026-08-04T00:00:00.000000Z",
    "updated_at": "2026-08-04T00:00:00.000000Z"
  }
}
```

### Get All Room Teachers

```
GET http://sms.test/api/institutes/allocations/room-teachers
```

Optional query parameters:

```
GET http://sms.test/api/institutes/allocations/room-teachers?session_id=1&class_id=3
```

### Get Single Room Teacher

```
GET http://sms.test/api/institutes/allocations/room-teachers/{id}
```

### Delete Room Teacher

```
DELETE http://sms.test/api/institutes/allocations/room-teachers/{id}
```

---

## Quick Reference Table

| Module | Method | URL | Body |
| --- | --- | --- | --- |
| 1. Class Subjects | POST | `/institutes/classes/{class_id}/subjects` | `{ "section_id": 1, "subject_ids": [1, 2, 5] }` (section_id optional) |
| 1. Get Class Subjects | GET | `/institutes/classes/{class_id}/subjects` | — |
| 2. Subject Teachers | POST | `/institutes/allocations/subject-teachers` | `{ "session_id": 1, "class_id": 3, "section_id": 2, "allocations": [{ "subject_id": 1, "teacher_id": 5 }] }` |
| 2. List Subject Teachers | GET | `/institutes/allocations/subject-teachers` | — |
| 2. Single Subject Teacher | GET | `/institutes/allocations/subject-teachers/{id}` | — |
| 2. Delete Subject Teacher | DELETE | `/institutes/allocations/subject-teachers/{id}` | — |
| 3. Room Teachers | POST | `/institutes/allocations/room-teachers` | `{ "session_id": 1, "class_id": 3, "section_id": 2, "teacher_id": 12 }` |
| 3. List Room Teachers | GET | `/institutes/allocations/room-teachers` | — |
| 3. Single Room Teacher | GET | `/institutes/allocations/room-teachers/{id}` | — |
| 3. Delete Room Teacher | DELETE | `/institutes/allocations/room-teachers/{id}` | — |

---

## Error Responses

### Validation Error (422)

```json
{
  "status": "error",
  "message": "Validation failed",
  "errors": {
    "teacher_id": ["The selected user must be an active Teacher in the active institute."]
  }
}
```

### Not Found (404)

```json
{
  "status": "not found",
  "message": "Class not found"
}
```

### No Active Institute (422)

```json
{
  "status": "error",
  "message": "No active institute is associated with this user"
}
```

---

## Important Rules

1. **Authentication:** Har request ke saath `Authorization: Bearer {token}` send karein.
2. **Institute:** Backend logged-in user ka active institute automatically find karta hai. `institute_id` send nahi karna.
3. **Validation:** Teachers ka role `Teacher` hona chahiye aur institute user active hona chahiye.
4. **Section Logic (Module 1):** `section_id` optional hai. Agar bheja jaye to sirf us section pe apply hota hai. Agar nahi bheja aur class ke sections hain to har section pe apply hota hai. Agar class ke sections nahi hain to class pe (`section_id = null`) apply hota hai.
5. **Section Logic (Modules 2 & 3):** `section_id` bhejna hai agar class ke sections hain, warna `null` bhejna hai.
