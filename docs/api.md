# REST API Reference

University Enrollment & Records System — Group 1, WMA4.

The API exposes the system's core resource, **students**, plus sign-in and the
dashboard summary. It is written in native PHP with MySQLi prepared statements.

- **Base URL (local):** `http://localhost:8000/api`
- **Format:** JSON in, JSON out (`Content-Type: application/json`)
- **Postman collection:** [`postman/Group1_Enrollment_API.postman_collection.json`](postman/Group1_Enrollment_API.postman_collection.json)

## Contents

1. [Running the API](#running-the-api)
2. [Authentication](#authentication)
3. [Response format](#response-format)
4. [Status codes](#status-codes)
5. [Endpoints](#endpoints)
6. [Student object](#student-object)
7. [Testing with Postman](#testing-with-postman)
8. [How a request is handled](#how-a-request-is-handled)

## Running the API

```
C:\xampp\php\php.exe -S localhost:8000 -t public public/router.php
```

`public/router.php` sends every `/api/...` request to `public/api/index.php`.
On Apache, `public/api/.htaccess` does the same.

## Authentication

Every endpoint except `POST /api/auth/login` needs a signed-in user.

| Client | How it authenticates |
|---|---|
| Postman, scripts, other apps | `Authorization: Bearer <token>`. Get a token from `POST /api/auth/login`. |
| This app's own pages | The session cookie from `login.php`, plus an `X-CSRF-Token` header on POST, PUT, PATCH and DELETE. |

Tokens are 64 hexadecimal characters, last **8 hours**, and are deleted by
`POST /api/auth/logout`. The server stores only each token's SHA-256 hash.

Bearer requests do not need a CSRF token: browsers never add an
`Authorization` header by themselves, so another website cannot forge one.

### Roles

| Action | Admin | Registrar | Faculty |
|---|:-:|:-:|:-:|
| `GET` students, dashboard, `auth/me` | ✓ | ✓ | ✓ |
| `POST`, `PUT`, `PATCH` students | ✓ | ✓ | 403 |
| `DELETE` students | ✓ | 403 | 403 |

### Demo accounts

| Username | Password | Role |
|---|---|---|
| `admin` | `Admin@12345` | Administrator |
| `registrar` | `Registrar@12345` | Registrar |
| `faculty` | `Faculty@12345` | Faculty |

## Response format

Every JSON response has the same five keys:

```json
{
  "success": true,
  "data": { },
  "message": "Student retrieved.",
  "errors": null,
  "meta": null
}
```

| Key | Meaning |
|---|---|
| `success` | `true` for 2xx responses, `false` otherwise |
| `data` | The result: an object, an array, or `null` on errors |
| `message` | A short human-readable summary |
| `errors` | On 422 (and 409): messages per field, e.g. `{"email": ["Enter a valid email address."]}`; otherwise `null` |
| `meta` | Pagination details on list responses; otherwise `null` |

`204 No Content` responses have no body at all.

## Status codes

| Code | When |
|---|---|
| 200 OK | A GET, PUT or PATCH succeeded, or a token was issued |
| 201 Created | A student was created (`Location` header points to it) |
| 204 No Content | A student was deleted, or a token was revoked |
| 400 Bad Request | The body is not valid JSON, or not a JSON object |
| 401 Unauthorized | No credentials, a wrong password, or an invalid/expired token |
| 403 Forbidden | Signed in, but the role is not allowed to do this |
| 404 Not Found | The student, or the route, does not exist |
| 405 Method Not Allowed | The URL exists but not with this method (`Allow` header lists the valid ones) |
| 409 Conflict | A student with this ID already exists |
| 415 Unsupported Media Type | The body was not sent as `application/json` |
| 419 | Session-based request without a valid `X-CSRF-Token` |
| 422 Unprocessable Content | The JSON is valid but values fail validation (see `errors`) |
| 429 Too Many Requests | Too many failed sign-ins (5 per username or 20 per IP in 15 minutes) |
| 500 Internal Server Error | An unexpected server or database error; details are logged, never sent |

## Endpoints

| Method | URL | Roles | Success |
|---|---|---|---|
| POST | [`/api/auth/login`](#post-apiauthlogin) | public | 200 |
| POST | [`/api/auth/logout`](#post-apiauthlogout) | any signed-in | 204 |
| GET | [`/api/auth/me`](#get-apiauthme) | any signed-in | 200 |
| GET | [`/api/students`](#get-apistudents) | any signed-in | 200 |
| GET | [`/api/students/{id}`](#get-apistudentsid) | any signed-in | 200 |
| POST | [`/api/students`](#post-apistudents) | admin, registrar | 201 |
| PUT | [`/api/students/{id}`](#put-apistudentsid) | admin, registrar | 200 |
| PATCH | [`/api/students/{id}`](#patch-apistudentsid) | admin, registrar | 200 |
| DELETE | [`/api/students/{id}`](#delete-apistudentsid) | admin | 204 |
| GET | [`/api/dashboard`](#get-apidashboard) | any signed-in | 200 |

---

### POST /api/auth/login

Signs in and returns a bearer token.

**Request**

```http
POST /api/auth/login
Content-Type: application/json

{ "username": "registrar", "password": "Registrar@12345" }
```

**200 OK**

```json
{
  "success": true,
  "data": {
    "token": "3f9c…64 hex characters…",
    "tokenType": "Bearer",
    "expiresAt": "2026-11-11 22:30:00",
    "user": { "id": 2, "username": "registrar", "name": "Registrar Office",
              "email": "registrar@university.test", "role": "registrar" }
  },
  "message": "Signed in.",
  "errors": null,
  "meta": null
}
```

**Errors:** 401 wrong username or password (one message for both) · 422 missing
`username` or `password` · 429 too many failed attempts (`Retry-After: 900`) ·
415 / 400 body not JSON.

### POST /api/auth/logout

Revokes the token used in the request (or ends the session, for the app's own
pages). **204 No Content.** Using the same token afterwards returns 401.

### GET /api/auth/me

Returns the signed-in user, plus `authenticatedVia` (`token` or `session`).

```json
{ "id": 3, "username": "faculty", "name": "Faculty Member",
  "email": "faculty@university.test", "role": "faculty", "authenticatedVia": "token" }
```

---

### GET /api/students

Lists students with search, filters, sorting and pagination.

| Query parameter | Values | Default |
|---|---|---|
| `search` | Matches student ID or email anywhere, or every word somewhere in the name | — |
| `program` | Program name, e.g. `BS Information Technology` | — |
| `year` | `1st Year`, `2nd Year`, `3rd Year`, `4th Year` | — |
| `status` | `Active`, `Pending`, `Dropped`, `Completed` | — |
| `sort` | `newest_first`, `oldest_first`, `name_asc`, `name_desc`, `student_id_asc` | `newest_first` |
| `page` | 1 or more | 1 |
| `perPage` | 1–100 | 10 |

```http
GET /api/students?search=dela cruz&status=Active&sort=name_asc&page=1&perPage=5
Authorization: Bearer <token>
```

**200 OK** — `data` is an array of [list rows](#student-object); `meta` is:

```json
{ "page": 1, "perPage": 5, "total": 12, "totalPages": 3 }
```

**Errors:** 422 when `year`, `status`, `sort`, `page` or `perPage` has a value
not listed above.

### GET /api/students/{id}

Returns one [full student](#student-object). **404** if the ID does not exist.

### POST /api/students

Creates a student. The record is written to three tables (`students`,
`student_personal_details`, `student_emergency_contacts`) in one transaction.

```http
POST /api/students
Authorization: Bearer <token>
Content-Type: application/json

{
  "id": "2026-00125",
  "firstName": "Juan",
  "middleName": "Santos",
  "lastName": "Dela Cruz",
  "suffix": "",
  "email": "juan.delacruz@example.com",
  "contact": "0917 123 4567",
  "address": "San Vicente, Tarlac City",
  "program": "BS Information Technology",
  "year": "1st Year",
  "academicYear": "2026–2027",
  "status": "Active",
  "dateOfBirth": "2006-04-12",
  "gender": "Male",
  "civilStatus": "Single",
  "emergencyContact": { "name": "Maria Dela Cruz", "relationship": "Mother", "number": "0918 765 4321" }
}
```

| Field | Rule |
|---|---|
| `id` | Required. Format `YYYY-NNNNN`, e.g. `2026-00125`. Unique. |
| `firstName`, `lastName` | Required, up to 60 characters |
| `middleName` (60), `suffix` (20), `academicYear` (20) | Optional |
| `email` | Required, valid email, up to 120 characters |
| `contact` | Required, exactly 11 digits (spaces allowed) |
| `address` | Required, up to 255 characters |
| `program` | Required, an existing program name |
| `year` | Required: `1st Year` … `4th Year` |
| `status` | Optional, default `Active`: `Active`, `Pending`, `Dropped`, `Completed` |
| `dateOfBirth` | Required, a real date `YYYY-MM-DD`, not in the future |
| `gender` | Required: `Male`, `Female`, `Other` |
| `civilStatus` | Required: `Single`, `Married`, `Other` |
| `emergencyContact.name` (100), `.relationship` (50) | Optional |
| `emergencyContact.number` | Optional; if given, exactly 11 digits |

**201 Created** with the full student in `data` and
`Location: /api/students/2026-00125`.

**Errors:** 409 ID already exists · 422 validation (per field) · 415 / 400 body
not JSON · 403 faculty · 401 not signed in.

```json
{
  "success": false,
  "data": null,
  "message": "Validation failed.",
  "errors": {
    "email": ["Enter a valid email address."],
    "contact": ["Phone number must be exactly 11 digits."]
  },
  "meta": null
}
```

### PUT /api/students/{id}

**Full replacement.** Send every required field (same rules as POST, without
`id`). Optional fields that are left out are cleared, and `status` resets to
`Active`. **200 OK** with the updated student.

**Errors:** 404 unknown ID · 422 missing or invalid fields, or a body `id` that
differs from the URL · 415 / 400 · 403 · 401.

### PATCH /api/students/{id}

**Partial update.** Send only the fields to change; everything else keeps its
value. Each field sent follows the POST rules, and required fields cannot be
set to an empty string.

```http
PATCH /api/students/2026-00125
Authorization: Bearer <token>
Content-Type: application/json

{ "status": "Dropped" }
```

**200 OK** with the updated student. **Errors:** 404 · 422 (including an empty
body, or trying to change `id`) · 415 / 400 · 403 · 401.

> **PUT or PATCH?** PUT replaces the whole record; PATCH changes some fields.
> The app's Edit Student dialog uses PATCH because it edits only some fields.

### DELETE /api/students/{id}

Deletes the student; their personal details and emergency contact are removed
too (`ON DELETE CASCADE`). **204 No Content.** **Errors:** 404 · 403 (not
admin) · 401.

---

### GET /api/dashboard

Live totals for the dashboard screen.

```json
{
  "totalStudents": 12,
  "activeStudents": 9,
  "pendingStudents": 2,
  "totalPrograms": 3,
  "byProgram": [ { "label": "BS Information Technology", "share": 50, "tone": "" } ],
  "statusBreakdown": {
    "total": 12,
    "items": [ { "status": "Active", "count": 9, "note": "75% of records" } ]
  }
}
```

## Student object

**List row** (`GET /api/students`):

```json
{
  "id": "2026-00125",
  "name": "Juan Santos Dela Cruz",
  "firstName": "Juan", "middleName": "Santos", "lastName": "Dela Cruz", "suffix": "",
  "email": "juan.delacruz@example.com",
  "contact": "0917 123 4567",
  "address": "San Vicente, Tarlac City",
  "program": "BS Information Technology",
  "year": "1st Year",
  "status": "Active",
  "date": "Nov. 11, 2026"
}
```

**Full student** (GET one, POST, PUT, PATCH) adds:

```json
{
  "academicYear": "2026–2027",
  "dateOfBirth": "2006-04-12",
  "gender": "Male",
  "civilStatus": "Single",
  "emergencyContact": { "name": "Maria Dela Cruz", "relationship": "Mother", "number": "0918 765 4321" }
}
```

`date` is when the record was created.

## Testing with Postman

1. Start MySQL, import `backend/database/schema.sql`, run
   `backend/database/seed_users.php`, and start the server (see
   [Running the API](#running-the-api)).
2. In Postman: **Import** →
   `docs/postman/Group1_Enrollment_API.postman_collection.json`.
3. Open the collection → **Run** → keep the default order → **Run**.

The collection has 37 requests in 8 folders: for every endpoint, the
successful case and the failure cases (401, 403, 404, 405, 409, 415, 400, 422).

- **Folder 1** signs in as each role and saves the tokens in collection variables.
- **Folder 2** creates a test student with a random `2099-xxxxx` ID.
- **Folder 6** deletes it again.
- **Folder 8** revokes the tokens.

The database therefore ends as it started.

Each request has `pm.test` checks on the status code, the envelope and key
values, and a description of what it demonstrates.

To change the server address, edit the collection variable `baseUrl`.

The collection's wrong-password request uses a username that does not exist,
so running it never locks a real account. Each run counts as one failed
attempt for your IP address. More than 20 runs within 15 minutes would
trigger the 429 lockout; clear it with:

```
C:\xampp\mysql\bin\mysql.exe -u root -e "DELETE FROM enrollment_system_min.login_attempts"
```

## How a request is handled

```
HTTP request
  → public/router.php (php -S) or public/api/.htaccess (Apache)
  → public/api/index.php            route table: method + URL + allowed roles
  → Router::dispatch()              404 / 405, then ApiAuth: 401 / 419 / 403
  → Controller                      reads the request, picks the status code
      → Request::json()             415 / 400
      → StudentValidator            422 with per-field errors
      → StudentRepository           prepared SQL, transactions
  → Response                        JSON envelope (or 204 with no body)
```

| File | Responsibility |
|---|---|
| `public/api/index.php` | Route table |
| `backend/api/Router.php` | Matches method and URL; 404 / 405; runs the role check |
| `backend/api/ApiAuth.php` | Bearer tokens and session + CSRF; 401 / 419 / 403 |
| `backend/api/Request.php` | Path, query values, headers, JSON body (415 / 400) |
| `backend/api/Response.php` | JSON envelope and status codes |
| `backend/api/bootstrap.php` | Loads everything; turns any error into a generic JSON 500 |
| `backend/api/controllers/*` | One controller per resource |
| `backend/validators/StudentValidator.php` | Validation rules for POST, PUT and PATCH |
| `backend/repositories/*` | All SQL |
