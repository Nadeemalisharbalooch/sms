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

**Payload — Whole Institute (class_id = null):**
```json
{
  "class_id": null,
  "billing_month": "2026-09",
  "due_date": "2026-09-10"
}
```

**Payload — Single Class (class_id = 5):**
```json
{
  "class_id": 5,
  "billing_month": "2026-09",
  "due_date": "2026-09-10"
}
```

`billing_month` must match `YYYY-MM` format.

**Backend Logic (wrapped in `DB::transaction`):**
1. Fetch all active students (filter by `class_id` if provided, otherwise all students in active session).
2. **Idempotent Check:** Skip if a voucher exists for `student_id + billing_month + session_id`.
3. **Calculate:** Sum of Student's Class `fee_structures` + Sum of Student's `student_fee_assignments`.
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
| 13 | POST | `/api/institutes/fees/collect` | Collect payment |

**Note:** All requests must include `Authorization: Bearer <token>` header and are scoped to the user's active institute + active academic session automatically.
