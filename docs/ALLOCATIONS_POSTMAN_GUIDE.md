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

```json
{
  "subject_ids": [1, 2, 5, 8]
}
```

### Response (Success)

```json
{
  "status": "success",
  "message": "Class subjects assigned successfully",
  "data": [
    {
      "id": 1,
      "class_id": 1,
      "section_id": null,
      "subject_id": 1,
      "subject": {
        "id": 1,
        "name": "Mathematics",
        "code": "MATH",
        "description": null,
        "is_active": true
      },
      "section": null,
      "created_at": "2026-08-04T00:00:00.000000Z",
      "updated_at": "2026-08-04T00:00:00.000000Z"
    }
  ]
}
```

### Behavior

| Scenario | Result |
| --- | --- |
| Class has sections (e.g., A, B) | Subject IDs assigned to EACH section (`section_id = 1`, `section_id = 2`) |
| Class has NO sections | Subject IDs assigned directly to class (`section_id = null`) |
| Subject ID removed in next request | Deleted from database for that class/section |

### Get Assigned Subjects

```
GET http://sms.test/api/institutes/classes/1/subjects
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
| 1. Class Subjects | POST | `/institutes/classes/{class_id}/subjects` | `{ "subject_ids": [1, 2, 5] }` |
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
4. **Section Logic:** Agar class ke sections hain, backend automatically sections pe apply karta hai. Frontend ko `section_id` sirf Module 2 aur 3 mein bhejna hai (agar sections hain), warna `null` bhejna hai.