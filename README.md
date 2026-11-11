# University Enrollment & Records System

**WMA4 – Advanced Web Development · Group 1 · System type: Student Enrollment & Records System**

A web application for a university registrar's office. It manages student
profiles, academic programs and enrollment records, with a dashboard computed
live from the database.

```
Browser (HTML / CSS / JavaScript)  →  JSON API (native PHP)  →  MySQL
```

## Project status

| Phase | Scope | Status |
|---|---|---|
| Midterm | Student CRUD, OOP classes, normalized database, search/filter/sort, dashboard | Done |
| Final – Phase 1 | Users and security tables, credentials in `.env`, user seeder | Done |
| Final – Phase 2 | Registration, login, sessions, role-based access, CSRF, secure logout | Done |
| Final – Phase 3 | REST API with clean URLs, bearer tokens, Postman collection | Not started |
| Final – Phase 4 | Laravel module for subjects, course offerings and enrollments | Not started |
| Final – Phase 5 | Enrollment report and analytics | Not started |
| Final – Phase 6 | Testing, documentation, deployment | Not started |

**What works against the real database today:** sign-in and sign-out,
admin-only user registration, the Dashboard, and the Students screens (list,
search, filter, sort, paginate, add, edit, delete, view profile). Each of
these checks the signed-in user's role on the server.

**Still on browser-only mock data:** Subjects, Course Offerings, Enroll
Student, Enrollment Records, Student Records, Reports and the Settings
preferences. These are replaced in later phases and are not yet protected by
server-side checks.

## Technologies

| Layer | Technology |
|---|---|
| Frontend | HTML5, CSS3, vanilla JavaScript (ES modules), no build step |
| Backend | Native PHP 8.2, MySQLi with prepared statements |
| Database | MySQL / MariaDB (XAMPP), InnoDB, utf8mb4 |
| Tools | XAMPP, Visual Studio Code, Postman, Git/GitHub |

## Setup (XAMPP)

1. **Start MySQL** in the XAMPP Control Panel.
2. **Create the database.** In phpMyAdmin open the *SQL* tab, paste the
   contents of `backend/database/schema.sql` and click *Go*. From a terminal:
   ```
   C:\xampp\mysql\bin\mysql.exe -u root < backend\database\schema.sql
   ```
3. **Create your `.env`.** Copy `.env.example` to `.env` and change the
   `DB_*` values if your MySQL user or password is not the XAMPP default
   (`root` with no password).
4. **Create the demo accounts.** From the project folder:
   ```
   C:\xampp\php\php.exe backend\database\seed_users.php
   ```
5. **Start the app.** From the project folder:
   ```
   C:\xampp\php\php.exe -S localhost:8000 -t public
   ```
   Then open <http://localhost:8000>.

> The frontend calls the API at absolute paths such as `/api/students.php`,
> so the app must be served from the root of a host, as `php -S` does above.
> Opening it through XAMPP at `http://localhost/<folder>/public/` will not
> reach the API until those paths are made relative (Phase 3).

## Demo accounts

Created by `backend/database/seed_users.php`. An administrator can create more
accounts from **Settings → Create User Account** (`register.php`).

| Username | Password | Role |
|---|---|---|
| `admin` | `Admin@12345` | Administrator |
| `registrar` | `Registrar@12345` | Registrar |
| `faculty` | `Faculty@12345` | Faculty |

These are local demo credentials only. Change them before any public deployment.

After 5 wrong passwords for one username (or 20 from one IP address) within
15 minutes, sign-in is blocked for that account. To unlock it during testing:

```
C:\xampp\mysql\bin\mysql.exe -u root -e "DELETE FROM enrollment_system_min.login_attempts"
```

## Folder structure

```
Group1_Enrollment_System/
├── .env.example                  template for the git-ignored .env file
├── backend/                      not web-accessible
│   ├── auth/
│   │   ├── Auth.php              sessions, login, rate limiting, roles, CSRF, logout
│   │   └── api_guard.php         requireApiRole(): 401 / 403 / 419 for API endpoints
│   ├── classes/
│   │   ├── Person.php            base class: name fields, getFullName()
│   │   ├── Student.php           extends Person; student data + toArray()
│   │   ├── Program.php           academic program
│   │   └── DuplicateStudentException.php
│   ├── config/
│   │   └── database.php          reads .env, opens the MySQLi connection
│   └── database/
│       ├── schema.sql            creates the database and every table
│       └── seed_users.php        creates the demo accounts
└── public/                       the web root
    ├── index.php                 app shell (sign-in required); JavaScript renders each screen
    ├── login.php                 sign-in form, processed by PHP
    ├── logout.php                POST-only sign-out
    ├── register.php              admin-only "Create user account" page
    ├── api/
    │   ├── students.php          student CRUD, search, filter, sort, paging
    │   └── dashboard.php         dashboard statistics
    ├── uploads/                  reserved for uploaded files
    └── assets/
        ├── css/                  base/, layout/, components/, pages/
        └── js/
            ├── config/           app settings, roles, navigation, options
            ├── core/             API client, auth state, router, validation
            ├── api/              one module per API resource
            ├── services/         logic the pages call
            ├── components/       reusable UI (table, modal, toast, …)
            ├── pages/            one module per screen
            └── mock/             temporary fake API for unfinished screens
```

## Database

`backend/database/schema.sql` creates `enrollment_system_min` with
`utf8mb4_unicode_ci` on every table.

| Table | Purpose | Key relationships |
|---|---|---|
| `programs` | Academic programs (lookup table) | — |
| `students` | Core student record and enrollment status | `program_id` → `programs.id` |
| `student_personal_details` | Birth date, gender, civil status | one-to-one with `students` |
| `student_emergency_contacts` | Emergency contact person | one-to-one with `students` |
| `users` | Sign-in accounts with hashed passwords and a role | — |
| `api_tokens` | Hashed bearer tokens for the API (Phase 3) | `user_id` → `users.id` |
| `login_attempts` | Failed sign-ins, used for rate limiting | — |

Subjects, course offerings and enrollments will be created by the Laravel
module's migrations in Phase 4.

## API (current)

All responses use the same JSON envelope:
`{ "success", "data", "message", "errors", "meta" }`.

| Method | Endpoint | Purpose |
|---|---|---|
| GET | `/api/students.php` | List students. Query: `search`, `program`, `year`, `status`, `sort`, `page`, `perPage` |
| GET | `/api/students.php?id={id}` | Get one student |
| POST | `/api/students.php` | Add a student (JSON body) |
| PUT | `/api/students.php?id={id}` | Update a student (JSON body) |
| DELETE | `/api/students.php?id={id}` | Delete a student |
| GET | `/api/dashboard.php` | Dashboard statistics |

Every endpoint requires a signed-in session. Requests that change data (POST,
PUT, DELETE) must also send the page's CSRF token in an `X-CSRF-Token` header.
Errors: **401** not signed in, **403** role not allowed, **419** missing or
invalid CSRF token.

Clean REST URLs, bearer tokens for Postman and a Postman collection are added
in Phase 3.

## Roles

| Action | Admin | Registrar | Faculty |
|---|:-:|:-:|:-:|
| View dashboard and students | ✓ | ✓ | ✓ |
| Add or edit students | ✓ | ✓ | ✗ (403) |
| Delete students | ✓ | ✗ (403) | ✗ (403) |
| Create user accounts (`register.php`) | ✓ | ✗ (403) | ✗ (403) |

Roles are stored in `users.role` and set only by the seeder or an
administrator. The navigation hides what a role cannot use, but that is only
for convenience: the server checks the role again on every page and API
request (`Auth::requireRole()` and `requireApiRole()`).

## Security notes

| Threat | Protection | Where |
|---|---|---|
| SQL injection | MySQLi prepared statements for every query that uses input | all PHP files |
| Stolen or weak password storage | `password_hash()` / `password_verify()`, automatic rehash | `Auth::attempt()`, `register.php` |
| Brute-force sign-in | 5 failures per username or 20 per IP in 15 minutes → blocked (429) | `Auth::tooManyAttempts()` |
| Username guessing | One message for wrong username or wrong password | `login.php` |
| Session fixation | `session_regenerate_id(true)` on sign-in | `Auth::login()` |
| Session theft | HttpOnly + SameSite=Lax cookie, strict mode, 30-minute idle timeout | `Auth::start()` |
| CSRF | Per-session token on every form and data-changing API request, compared with `hash_equals()` | `Auth::verifyCsrf()`, `api_guard.php` |
| Broken access control | Role checked on the server for every page and API request | `Auth::requireRole()`, `requireApiRole()` |
| Logout that leaves the session alive | POST-only logout clears data, expires the cookie, destroys the session | `logout.php`, `Auth::logout()` |
| XSS | `htmlspecialchars()` in PHP pages; JavaScript screens render data through `escapeHtml()` (re-checked during Phase 6 testing) | PHP pages, `core/dom.js` |
| Leaked credentials | Database settings in git-ignored `.env`; errors logged, not shown | `database.php` |

## AI and third-party code disclosure

Parts of this project were written with help from AI coding assistants
(Claude by Anthropic), including the Phase 1 schema changes, `.env` loading,
the user seeder, the Phase 2 authentication code (`Auth.php`, `api_guard.php`,
`login.php`, `logout.php`, `register.php` and the related JavaScript changes)
and this README. Every team member has reviewed this code and
can explain and modify it. Third-party resources loaded by the frontend:
[Inter](https://fonts.google.com/specimen/Inter) (Google Fonts) and
[Lucide](https://lucide.dev) icons.
