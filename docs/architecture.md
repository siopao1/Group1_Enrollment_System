# Architecture

## The rule

Each layer talks only to the one below it, and nothing skips a layer.

```
Browser
  │  fetch()  — only ever from core/apiClient.js
  ▼
public/api/index.php            front controller
  │
  ▼
Router  →  Middleware           authentication, then the required permission
  │
  ▼
Controller                      read input, validate shape, return a response
  │
  ▼
Service                         business rules, transactions
  │
  ▼
Repository                      SQL (prepared statements only)
  │
  ▼
MySQL
```

A controller that contained SQL, or a repository that decided a business rule,
would break the pattern. The split is what keeps each piece testable: services
can be unit-tested with a fake repository, repositories can be checked against a
test database, and controllers stay boring.

## Request lifecycle, end to end

Take "delete a student":

1. `pages/students.js` handles the click and calls `studentService.remove(id)`.
   The page knows nothing about URLs.
2. `services/studentService.js` calls `studentApi.remove(id)`.
3. `api/studentApi.js` maps that to `DELETE /students/{id}`.
4. `core/apiClient.js` performs the request, unwraps the envelope, and throws an
   `ApiError` if `success` is false.
5. `public/api/index.php` boots, `Router` matches the route in `routes/api.php`.
6. `AuthMiddleware` requires a live session; `RequiresStudentsManage` requires
   the `students.manage` permission.
7. `StudentController::destroy()` calls `StudentService::delete()`.
8. `StudentService` checks the student exists, then calls the repository.
9. `StudentRepository` runs a prepared `DELETE`.
10. `Response::success()` writes `{ success, data, message }`; the page shows a
    toast and refreshes the table.

## Why the frontend is layered too

The original code read `db.students` directly inside the markup-building
functions, which meant every page would have had to be rewritten when the API
arrived. Now:

- **pages/** render and handle events. No URLs, no fetch, no business rules.
- **services/** hold use-cases and client-side validation.
- **api/** know endpoints and nothing else.
- **core/apiClient.js** is the only place that calls `fetch()`.
- **mock/** is a transport swapped in behind the api layer.

Swapping the mock for the real backend is one boolean because the seam is in
exactly one place.

## Authentication

Passwords are hashed with bcrypt (cost 12) and verified with `password_verify()`.
The browser never sees a hash and never decides anything:

1. `POST /api/auth/login` with a username and password.
2. `AuthService` looks the user up, verifies the password, rejects inactive
   accounts, and rehashes the password if the cost factor has changed.
3. `Session::login()` regenerates the session ID (defeating session fixation)
   and stores the user server-side.
4. The response contains a display-only copy of the user. The *credential* is
   the `HttpOnly` session cookie, which JavaScript cannot read.

Failed logins return the same message whether or not the username exists, so the
endpoint cannot be used to discover valid accounts. They are logged.

## Role-based access control

Permissions are defined once per role in `config/auth.php`. The frontend has a
mirror in `js/config/roles.js`.

They serve different purposes:

| | `js/config/roles.js` | `config/auth.php` |
|---|---|---|
| Purpose | hide what the user cannot use | refuse what the user may not do |
| Trust | none — editable in DevTools | authoritative |
| Effect if bypassed | a menu item appears | nothing; the API returns 403 |

Routes declare their requirement:

```php
$router->post('/students', [StudentController::class, 'store'],
    [AuthMiddleware::class, RequiresStudentsManage::class]);
```

The permission sits next to the route, so `routes/api.php` is a complete and
readable access-control policy. Blocked attempts are logged with the user, role
and path.

## Transactions

Enrollment is the one flow where two users can collide over the same resource —
the last slot in a section. `EnrollmentService::create()` therefore runs inside
`Database::transaction()` and selects the offering rows `FOR UPDATE`, so a
second registrar waits rather than overbooking. Any rule violation throws, the
transaction rolls back, and the slot counts are untouched.

The same reasoning applies to deleting an enrollment, which releases slots.

## Security summary

| Risk | Mitigation |
|---|---|
| SQL injection | Prepared statements everywhere; `PDO::ATTR_EMULATE_PREPARES` off |
| XSS | `escapeHtml()` on interpolated values; `View::e()` server-side |
| Session fixation | `session_regenerate_id(true)` on login |
| Cookie theft | `HttpOnly`, `SameSite=Lax`, `Secure` in production |
| Credential leaks | `.env` outside the document root and git-ignored |
| Information leaks | Generic 500s; details go to `storage/logs/` |
| Privilege escalation | Permission re-checked server-side on every request |
| Mass assignment | Controllers validate; `validated()` returns only known keys |
| Overbooking | Row-level locks inside a transaction |
