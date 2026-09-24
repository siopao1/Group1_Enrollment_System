# REST API

Base URL: `/api` (relative to `public/`, so it works in a subfolder too).

## Envelope

Every response — success or failure — has the same shape, so the client never
special-cases an endpoint.

```json
{
  "success": true,
  "data": [],
  "message": "Students retrieved successfully.",
  "meta": { "page": 1, "perPage": 10, "total": 248, "totalPages": 25 }
}
```

```json
{
  "success": false,
  "data": null,
  "message": "Student not found.",
  "errors": { "id": ["Use the format 2026-00125."] }
}
```

`meta` appears only on paginated lists. `errors` appears only on validation
failures, keyed by field name.

## Status codes

| Code | Meaning |
|---|---|
| 200 | OK |
| 201 | Created |
| 401 | Not signed in, or the session expired |
| 403 | Signed in, but the role lacks the permission |
| 404 | No such resource or endpoint |
| 405 | Wrong method for a valid path |
| 409 | Conflict — a full offering, a duplicate enrollment, a subject in use |
| 422 | Validation failed; see `errors` |
| 500 | Server error; details are logged, not returned |

## Authentication

Sign in once; the `HttpOnly` session cookie carries the session from then on.

| Method | Endpoint | Permission |
|---|---|---|
| POST | `/auth/login` | public |
| POST | `/auth/logout` | public |
| GET | `/auth/session` | public (401 when there is no session) |

```http
POST /api/auth/login
Content-Type: application/json

{ "username": "admin", "password": "password" }
```

## Endpoints

Every endpoint below needs a session. The permission column is enforced by
middleware declared in `routes/api.php`.

### Students

| Method | Endpoint | Permission |
|---|---|---|
| GET | `/students` | `students.view` |
| GET | `/students/{id}` | `students.view` |
| GET | `/students/{id}/academic-record` | `records.view` |
| POST | `/students` | `students.manage` |
| PUT | `/students/{id}` | `students.manage` |
| DELETE | `/students/{id}` | `students.manage` |

Query parameters on the list: `search`, `program`, `year`, `status`, `page`,
`perPage` (capped at 100).

```http
GET /api/students?search=dela&status=Active&page=1&perPage=10
```

### Subjects

| Method | Endpoint | Permission |
|---|---|---|
| GET | `/subjects` | `subjects.view` |
| GET | `/subjects/{code}` | `subjects.view` |
| POST | `/subjects` | `subjects.manage` |
| PUT | `/subjects/{code}` | `subjects.manage` |
| DELETE | `/subjects/{code}` | `subjects.manage` |

Deleting a subject that a course offering uses returns 409 rather than a
foreign-key error.

### Course offerings

| Method | Endpoint | Permission |
|---|---|---|
| GET | `/course-offerings` | `offerings.view` |
| GET | `/course-offerings/{id}` | `offerings.view` |
| POST | `/course-offerings` | `offerings.manage` |
| PUT | `/course-offerings/{id}` | `offerings.manage` |
| DELETE | `/course-offerings/{id}` | `offerings.manage` |

Filters: `academicYear`, `semester`, `status` (`Open` or `Full`). Status is
derived from the live slot counts, never stored, so it cannot go stale.

### Enrollments

| Method | Endpoint | Permission |
|---|---|---|
| GET | `/enrollments` | `enrollments.view` |
| GET | `/enrollments/{id}` | `enrollments.view` |
| POST | `/enrollments` | `enrollments.manage` |
| PUT | `/enrollments/{id}` | `enrollments.manage` |
| DELETE | `/enrollments/{id}` | `enrollments.manage` |

```http
POST /api/enrollments
Content-Type: application/json

{
  "studentId": "2026-00125",
  "offerings": ["OFF-2026-001", "OFF-2026-002"],
  "academicYear": "2026-2027",
  "semester": "1st Semester"
}
```

Rejected with 409 when an offering is full, when the student already has an
enrollment for that term, or when the selection exceeds the per-term unit limit
(`app.academic.max_units_per_term`). The whole operation is transactional.

`PUT` changes the status only: `Pending`, `Confirmed`, `Active`, `Dropped`,
`Completed`.

### Dashboard, reports and settings

| Method | Endpoint | Permission |
|---|---|---|
| GET | `/dashboard/summary` | `dashboard.view` |
| GET | `/reports` | `reports.view` |
| POST | `/reports/{type}` | `reports.view` |
| GET | `/settings` | `settings.manage` |
| PUT | `/settings` | `settings.manage` |

Report types: `enrollment`, `demographics`, `by-program`, `by-semester`,
`academic-records`, `master-list`.

## Design notes

- **Resources, not actions.** `POST /enrollments`, not `/createEnrollment`.
- **Natural keys in URLs.** Students are identified by their student number and
  subjects by their code, because those are what users actually quote.
- **Filtering via the query string**, never via a request body on `GET`.
- **`perPage` is clamped server-side** so a client cannot request the whole
  table in one call.
