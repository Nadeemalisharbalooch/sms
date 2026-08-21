# Module 6 – Smart Fees Management API Guide

All endpoints are under the `api` prefix with `auth:sanctum` middleware.
The frontend **never sends `session_id`** — the backend auto-retrieves the globally active session (`is_active = 1`).

**Base URL:** `http://localhost:8000/api`

---

## API 1: Fee Categories (Master Data)

### 1.1 List Fee Categories
```
GET  /api/institutes/fee-categories
```
**Response:**
```json
{
  "status": "success",
  "message": "Fee categories retrieved successfully",
  "data": {
    "current_page": 1,
    "data": [
      {
        "id": 1,
        "institute_id": 1,
        "name": "Tuition Fee",
        "description": "Monthly tuition",
        "created_at": "2026-08-15T12:00:00.000000Z",
        "updated_at": "2026-08-15T12:00:00.000000Z"
      }
    ]
  }
}
```

### 1.2 Create Fee Category
```
POST  /api/institutes/fee-categories
```
**Payload:**
```json
{
  "name": "Transport Fee",
  "description": "Monthly bus fee"
}
```
Validation: `name` required (string, unique per institute), `description` nullable.

### 1.3 Update Fee Category
```
PATCH  /api/institutes/fee-categories/{category_id}
```
**Payload:**
```json
{
  "name": "Transport Fee Updated",
  "description": "Monthly bus service"
}
```
Both fields optional for PATCH (partial update).

### 1.4 Delete Fee Category
```
DELETE  /api/institutes/fee-categories/{category_id}
```

---

## API 2: Class Fee Structures (Class-wide Rules)

### 2.1 List Fee Structures (active session only)
```
GET  /api/institutes/fee-structures
```
**Response:**
```json
{
  "status": "success",
  "data": {
    "data": [
      {
        "id": 1,
        "institute_id": 1,
        "session_id": 2,
        "class_id": 5,
        "fee_category_id": 1,
        "fee_category_name": "Tuition Fee",
        "amount": 5000.0,
        "recurrence": "monthly",
        "created_at": "...",
        "updated_at": "..."
      }
    ]
  }
}
```

### 2.2 Create or Update Class Fee Structure (upsert)
```
POST  /api/institutes/fee-structures
```
**Payload:**
```json
{
  "class_id": 5,
  "fee_category_id": 1,
  "amount": 5000.00,
  "recurrence": "monthly"
}
```
**Logic:** Uses `updateOrCreate` matching `[class_id, fee_category_id, session_id]` — so calling it twice with the same class/category simply updates the amount.

Validation: `recurrence` must be one of `monthly`, `yearly`, `one-time`.

### 2.3 Delete Class Fee Structure
```
DELETE  /api/institutes/fee-structures/{structure_id}
```

---

## API 3: Student-Specific Fee Assignments (Optional Fees)

### 3.1 List Student Fee Assignments
```
GET  /api/institutes/fees/student-assignments
```
Optional query param:
```
GET  /api/institutes/fees/student-assignments?student_id=8
```

### 3.2 Create / Update Student Fee Assignment (upsert)
```
POST  /api/institutes/fees/student-assignments
```
**Payload:**
```json
{
  "student_id": 8,
  "fee_category_id": 3,
  "amount": 2500.00
}
```
**Logic:** Uses `updateOrCreate` matching `[session_id, student_id, fee_category_id]`.

### 3.3 Delete Student Fee Assignment
```
DELETE  /api/institutes/fees/student-assignments/{assignment_id}
```

---

## API 4: Smart Bulk Voucher Generation (The Engine)

```
POST  /api/institutes/fees/generate-vouchers
```

**Payload — Whole Institute (all classes, specific categories):**
```json
{
  "class_id": null,
  "fee_category_ids": [1, 2],
  "billing_month": "2026-09",
  "due_date": "2026-09-10"
}
```

**Payload — Single Class (specific categories):**
```json
{
  "class_id": 1,
  "fee_category_ids": [1, 2],
  "billing_month": "2026-09",
  "due_date": "2026-09-10"
}
```

**Payload — Single Category (via fee_category_id):**
```json
{
  "class_id": 1,
  "fee_category_id": 2,
  "billing_month": "2026-09",
  "due_date": "2026-09-10"
}
```

**Payload — Specific Students Only:**
```json
{
  "student_ids": [2, 5, 8],
  "fee_category_ids": [1, 2],
  "billing_month": "2026-09",
  "due_date": "2026-09-10"
}
```

**Payload — Single Student (via student_id):**
```json
{
  "student_id": 2,
  "fee_category_ids": [1],
  "billing_month": "2026-09",
  "due_date": "2026-09-10"
}
```

> **Note on Parameters:**
> - `fee_category_ids` (**Required**): Accepts an array of category IDs (e.g. `[1, 2]`) or a single integer via `fee_category_id: 1` or `fee_category_ids: [1]`.
> - `student_ids` (**Optional**): If provided (array `[2, 5]` or single integer `student_id: 2`), vouchers will be generated **only** for the specified students. If omitted / null, vouchers are generated for all students in the scope (class or whole institute).
> - `class_id` (**Optional**): Filter by class ID, or `null` for all classes.

`billing_month` must match `YYYY-MM` format.

**Backend Logic (wrapped in `DB::transaction`):**
1. Fetch active students (filtered by `student_ids` if provided, and `class_id` if provided).
2. **Idempotent Check:** Skip if a voucher exists for `student_id + billing_month + session_id`.
3. **Calculate:** Sum of Student's Class `fee_structures` + Sum of Student's `student_fee_assignments` (filtered strictly by `fee_category_ids`).
4. **Create Header:** Insert into `fee_vouchers` (total_amount, unpaid status).
5. **Create Line Items:** Insert each breakdown row into `fee_voucher_items`.

**Response:**
```json
{
  "status": "success",
  "message": "Vouchers generated successfully",
  "data": {
    "generated_count": 450,
    "skipped_count": 5
  }
}
```

---

## API 5: Fetch Student Ledger (Cashier Search)

```
GET  /api/institutes/fees/ledger
```
Returns all vouchers for the active institute and active session when no filter is sent.

Optional filters (send only one):

- `student_id=1` returns that student's vouchers.
- `class_id=3` returns vouchers for every student enrolled in that class.
- `search=10-A-01` remains supported for an active-session roll number, student ID, or student name.

**Response:**
```json
{
  "status": "success",
  "message": "Student ledger retrieved successfully",
  "data": {
    "student": {
      "id": 1,
      "name": "Ali Khan",
      "class": "Grade 10",
      "roll_number": "10-A-01"
    },
    "summary": {
      "total_due": 7500.0,
      "total_paid": 0.0
    },
    "vouchers": [
      {
        "voucher_id": 1045,
        "student_id": 1,
        "session_id": 2,
        "billing_month": "2026-09",
        "due_date": "2026-09-10",
        "total_amount": 7500.0,
        "paid_amount": 0.0,
        "balance_due": 7500.0,
        "status": "unpaid",
        "items": [
          { "id": 1, "fee_name": "Tuition Fee", "amount": 5000.0 },
          { "id": 2, "fee_name": "Transport Fee", "amount": 2500.0 }
        ],
        "created_at": "...",
        "updated_at": "..."
      }
    ]
  }
}
```

---

## API 5A: Student Fee Ledger & Summary (Multi-Level Scope)

```
GET /api/institutes/fees/student-ledger
```

Fetches the fee ledger summary and list of students with their individual fee summaries.
When no filter ID is provided, it automatically returns the complete data for the active institute in the active academic session.

### Optional Query Filters:
- `institute_id` or `institute`: Filter by specific institute (user must have access). Defaults to active institute.
- `session_id` or `session`: Filter by specific academic session. Defaults to active session.
- `class_id` or `class`: Filter by class ID.
- `section_id` or `section`: Filter by section ID.
- `student_id` or `student`: Filter by student ID.
- `billing_month`: Filter vouchers by month (`YYYY-MM`).
- `search` / `name` / `student_name` / `query` / `q`: Search students by first name, last name, full name, roll number, or ID.
- `roll_number`: Filter specifically by student roll number.
- `per_page`: Number of student records per page (default `15`, max `500`).
- `page`: Current page number (default `1`).

**Response:**
```json
{
  "status": "success",
  "message": "Student ledger retrieved successfully",
  "data": {
    "institute": {
      "id": 1,
      "name": "Apex Grammar School"
    },
    "session": {
      "id": 2,
      "name": "2026-2027"
    },
    "class": {
      "id": 5,
      "name": "Class 9"
    },
    "section": null,
    "student": null,
    "summary": {
      "total_students": 25,
      "total_vouchers": 50,
      "total_amount": 250000.0,
      "total_paid": 180000.0,
      "total_due": 70000.0
    },
    "students": [
      {
        "id": 1,
        "student_id": 1,
        "first_name": "Ali",
        "last_name": "Khan",
        "name": "Ali Khan",
        "roll_number": "9A-01",
        "class_id": 5,
        "class_name": "Class 9",
        "class": "Class 9",
        "section_id": 2,
        "section_name": "Section A",
        "section": "Section A",
        "guardian_name": "Tariq Khan",
        "guardian_phone": "03001111111",
        "summary": {
          "total_vouchers": 2,
          "total_amount": 10000.0,
          "total_paid": 8000.0,
          "total_due": 2000.0,
          "status": "partial"
        }
      }
    ],
    "pagination": {
      "current_page": 1,
      "per_page": 15,
      "total": 25,
      "last_page": 2,
      "from": 1,
      "to": 15,
      "has_more": true,
      "prev_page_url": null,
      "next_page_url": "http://localhost:8000/api/institutes/fees/student-ledger?page=2"
    }
  }
}
```

---

## API 5B: Student Fee Vouchers List

```
GET /api/institutes/fees/student-vouchers?student_id=1
```
or
```
GET /api/institutes/fees/vouchers?student_id=1
```

`student_id` is required. The response returns only the list of fee vouchers for the student.

**Optional Query Filters:**
- `status`: Filter by voucher status (`paid`, `unpaid`, `partially_paid`, `overdue`, `cancelled`).
- `billing_month`: Filter by billing month (`YYYY-MM`).
- `session_id`: Specific academic session ID (defaults to active session).
- `all_sessions`: Boolean (`1` or `true`) to retrieve vouchers across all sessions.

**Response:**
```json
{
  "status": "success",
  "message": "Student fee vouchers retrieved successfully",
  "data": {
    "student": {
      "id": 2,
      "name": "Ali Khan",
      "first_name": "Ali",
      "last_name": "Khan",
      "roll_number": "101",
      "class": "Class 10",
      "section": "Section A",
      "session": "2026-2027",
      "guardian_name": "Tariq Khan",
      "guardian_phone": "03001234567"
    },
    "fees_summary": {
      "total_vouchers": 2,
      "total_amount": 7000,
      "total_paid": 3500,
      "total_due": 3500,
      "paid_vouchers_count": 1,
      "unpaid_vouchers_count": 1,
      "partially_paid_vouchers_count": 0,
      "overdue_vouchers_count": 0
    },
    "vouchers": [
      {
        "voucher_id": 6,
        "billing_month": "2026-10",
        "total_amount": 3500,
        "paid_amount": 0,
        "balance_due": 3500,
        "status": "unpaid",
        "due_date": "2026-09-30",
        "items": [
          {
            "fee_name": "Tution Fees",
            "amount": 3500
          }
        ]
      },
      {
        "voucher_id": 1,
        "billing_month": "2026-09",
        "total_amount": 3500,
        "paid_amount": 3500,
        "balance_due": 0,
        "status": "paid",
        "due_date": "2026-09-10",
        "items": [
          {
            "fee_name": "Tution Fees",
            "amount": 3500
          }
        ]
      }
    ]
  }
}
```

---

## API 6: Collect Payment

```
POST  /api/institutes/fees/collect
```
**Payload:**
```json
{
  "fee_voucher_id": 1045,
  "amount_paid": 7500.00,
  "payment_method": "cash",
  "payment_date": "2026-09-01"
}
```
`payment_method` must be `cash` or `bank`.

**Backend Logic (wrapped in `DB::transaction`):**
1. Verify `amount_paid <= balance_due` — **rejects overpayment** with 422 error.
2. Create a record in `fee_payments`.
3. Increment `paid_amount` on `fee_vouchers`.
4. If `paid_amount == total_amount` → status `paid`. If less → status `partial`.

**Response:**
```json
{
  "status": "success",
  "message": "Payment collected successfully",
  "data": {
    "payment": {
      "id": 1,
      "fee_voucher_id": 1045,
      "amount_paid": 7500.0,
      "payment_date": "2026-09-01",
      "payment_method": "cash",
      "collected_by_user_id": 3,
      "created_at": "...",
      "updated_at": "..."
    },
    "voucher": {
      "voucher_id": 1045,
      "student_id": 1,
      "session_id": 2,
      "billing_month": "2026-09",
      "due_date": "2026-09-10",
      "total_amount": 7500.0,
      "paid_amount": 7500.0,
      "balance_due": 0.0,
      "status": "paid",
      "items": [
        { "id": 1, "fee_name": "Tuition Fee", "amount": 5000.0 },
        { "id": 2, "fee_name": "Transport Fee", "amount": 2500.0 }
      ],
      "created_at": "...",
      "updated_at": "..."
    }
  }
}
```

---

## Route Summary Table

| # | Method | URL | Purpose |
|---|--------|-----|---------|
| 1 | GET | `/api/institutes/fee-categories` | List fee categories |
| 2 | POST | `/api/institutes/fee-categories` | Create fee category |
| 3 | PATCH | `/api/institutes/fee-categories/{category}` | Update fee category |
| 4 | DELETE | `/api/institutes/fee-categories/{category}` | Delete fee category |
| 5 | GET | `/api/institutes/fee-structures` | List class fee structures |
| 6 | POST | `/api/institutes/fee-structures` | Create/update class fee structure |
| 7 | DELETE | `/api/institutes/fee-structures/{structure}` | Delete class fee structure |
| 8 | GET | `/api/institutes/fees/student-assignments` | List student fee assignments |
| 9 | POST | `/api/institutes/fees/student-assignments` | Create/update student assignment |
| 10 | DELETE | `/api/institutes/fees/student-assignments/{assignment}` | Delete student assignment |
| 11 | POST | `/api/institutes/fees/generate-vouchers` | Bulk generate monthly vouchers |
| 12 | GET | `/api/institutes/fees/ledger?search=` | Fetch student ledger |
| 13 | GET | `/api/institutes/fees/student-ledger` | Fetch student fee ledger with multi-level filters & summary |
| 14 | GET | `/api/institutes/fees/student-vouchers?student_id=1` | Fetch one student's fee vouchers list |
| 15 | POST | `/api/institutes/fees/collect` | Collect payment |

**Note:** All requests must include `Authorization: Bearer <token>` header and are scoped to the user's active institute + active academic session automatically.
