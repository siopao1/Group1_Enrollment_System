# University Enrollment & Records System

**WMA4 – Advanced Web Development · Group 1 · System type: Student Enrollment & Records System**

A web application for a university registrar's office. It manages student
profiles, academic programs and enrollment records, with a dashboard computed
live from the database.

```
                ┌─ Main app, port 8000 (native PHP) ───────────────────────────────┐
Browser ──────► │ Dashboard, Students, Reports, Settings + REST API (/api/...)      │ ──┐
Postman ──────► │ Router → Controller → Validator → Repository (MySQLi)            │   │
                └───────────────────────────────────────────────────────────────────┘   ├─► one MySQL/MariaDB
                ┌─ Laravel module, port 8001 (enrollment-module/) ─────────────────┐   │   database
Browser ──────► │ Subjects, Course Offerings, Enroll Student, Enrollment Records,  │ ──┘
                │ Student Records — Routes → Controllers → Eloquent models → Blade │
                └───────────────────────────────────────────────────────────────────┘
```

The two parts share one database and one set of user accounts, and their
navigation menus link to each other.

## Project status

| Phase | Scope | Status |
|---|---|---|
| Midterm | Student CRUD, OOP classes, normalized database, search/filter/sort, dashboard | Done |
| Final – Phase 1 | Users and security tables, credentials in `.env`, user seeder | Done |
| Final – Phase 2 | Registration, login, sessions, role-based access, CSRF, secure logout | Done |
| Final – Phase 3 | REST API with clean URLs, bearer tokens, Postman collection | Done |
| Final – Phase 4 | Laravel module for subjects, course offerings and enrollments | Done |
| Final – Phase 5 | Reports and analytics, real settings, dashboard term figures | Done |
| Final – Phase 6 | Testing, documentation, deployment | Done |

**Everything runs against the real database:** sign-in and sign-out,
admin-only user registration, the Dashboard, the Students screens (list,
search, filter, sort, paginate, add, edit, delete, view profile), Reports &
Analytics (six reports with charts, CSV export and print), Settings, and the
REST API behind them (`/api/students`, `/api/dashboard`, `/api/reports`,
`/api/settings`, `/api/auth`), plus the Laravel module's Subjects, Course
Offerings, Enroll Student, Enrollment Records and Student Records pages. Each
of these checks the signed-in user's role on the server. No screen uses mock
data any more.

## Technologies

| Layer | Technology |
|---|---|
| Frontend | HTML5, CSS3, vanilla JavaScript (ES modules), no build step |
| Backend | Native PHP 8.2, MySQLi with prepared statements |
| Laravel module | Laravel 12 (MVC), Eloquent ORM, Blade views, migrations and seeders |
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
   C:\xampp\php\php.exe -S localhost:8000 -t public public/router.php
   ```
   Then open <http://localhost:8000>. The last argument, `public/router.php`,
   is required: it sends `/api/...` URLs to the API.

> **Updating an existing database:** `schema.sql` only adds what is missing
> (it never drops a table), so re-running it is safe. Re-run it after pulling
> Phase 5 to add the `settings` table.

> **Using XAMPP's Apache instead:** put the project under `htdocs`, set the
> addresses in both `.env` files, and open `http://localhost/<folder>/`.
> Step by step, with a checklist before real use:
> **[docs/deployment.md](docs/deployment.md)**. Both setups pass the full
> system test.

### Laravel module (`enrollment-module/`)

The Laravel 12 module uses the **same MySQL/MariaDB database** as the main
app, so do steps 1–4 above first. It needs
[Composer](https://getcomposer.org). From the `enrollment-module` folder:

```
composer install
copy .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed
php artisan serve --port=8001
```

Then open <http://localhost:8001> and sign in with the same demo accounts.
Keep the main app running on port 8000 at the same time.

- `migrate` adds the module's four tables (subjects, course_offerings,
  enrollments, enrollment_offering) next to the main app's tables.
- `db:seed` adds 15 subjects, 17 sections, 8 demo students and 7 enrollments.
  It can be run again safely.
- `.env.example` is already set to the `enrollment_system_min` database on
  XAMPP's MariaDB (`DB_CONNECTION=mariadb`). Change the `DB_*` values if yours
  differ, and `MAIN_APP_URL` if the main app is not on port 8000.
- If the module runs on a different address, also change `LARAVEL_URL` in the
  main app's `.env` so its menu links to it. Running both apps on XAMPP's
  Apache is described in [docs/deployment.md](docs/deployment.md).

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
│   ├── api/
│   │   ├── bootstrap.php         loads the API; turns any error into a generic JSON 500
│   │   ├── Router.php            route table matching; 404 / 405; runs the role check
│   │   ├── ApiAuth.php           bearer tokens, or session + CSRF; 401 / 419 / 403
│   │   ├── Request.php           path, query values, headers, JSON body (415 / 400)
│   │   ├── Response.php          JSON envelope and status codes
│   │   └── controllers/          Auth, Student, Dashboard, Report and Settings controllers
│   ├── auth/
│   │   └── Auth.php              sessions, login, rate limiting, roles, CSRF, logout
│   ├── repositories/             Student, Dashboard, Report and Settings repositories (all SQL)
│   ├── services/
│   │   └── ReportService.php     report catalog; builds each report from the query results
│   ├── validators/               StudentValidator (POST / PUT / PATCH rules)
│   ├── classes/
│   │   ├── AcademicTerm.php      allowed academic years, semesters, year levels
│   │   ├── Person.php            base class: name fields, getFullName()
│   │   ├── Student.php           extends Person; student data + toArray()
│   │   ├── Program.php           academic program
│   │   └── DuplicateStudentException.php
│   ├── config/
│   │   └── database.php          reads .env, opens the MySQLi connection
│   └── database/
│       ├── schema.sql            creates the database and every table
│       └── seed_users.php        creates the demo accounts
├── enrollment-module/            Laravel 12 module (Phase 4), shares the same database
│   ├── app/
│   │   ├── Models/               Subject, CourseOffering, Enrollment, Student, Program, User
│   │   ├── Http/Controllers/     Subject, CourseOffering, Enrollment, StudentRecord, Auth/Login
│   │   ├── Http/Requests/        form validation (SubjectRequest, CourseOfferingRequest, EnrollmentRequest)
│   │   ├── Http/Middleware/      EnsureRole ('role:admin,registrar' → 403 otherwise)
│   │   └── Services/             EnrollmentService (enrollment business rules)
│   ├── database/migrations/      the module's four tables
│   ├── database/seeders/         subjects, offerings, demo students, enrollments
│   ├── resources/views/          Blade pages (layout, subjects, offerings, enrollments, records)
│   ├── routes/web.php            every page and the roles allowed on it
│   └── public/css/               copy of the main app's CSS + laravel.css
├── postman/                      Postman collection (import and run)
├── tests/
│   ├── system_test.php           72 end-to-end tests over HTTP (both apps)
│   ├── run_postman.php           runs the Postman collection without Postman
│   └── lib/Http.php              small HTTP client used by the tests
├── .htaccess                     Apache: only the two public/ folders may be opened
└── public/                       the web root
    ├── index.php                 app shell (sign-in required); JavaScript renders each screen
    ├── login.php                 sign-in form, processed by PHP
    ├── logout.php                POST-only sign-out
    ├── register.php              admin-only "Create user account" page
    ├── router.php                for `php -S`: sends /api/... to api/index.php
    ├── api/
    │   ├── index.php             the API's single entry point and route table
    │   └── .htaccess             Apache: sends /api/... to index.php
    ├── uploads/                  reserved for uploaded files
    └── assets/
        ├── css/                  base/, layout/, components/, pages/
        └── js/
            ├── config/           app settings, roles, navigation, options
            ├── core/             http.js (API requests), auth state, settings, router, validation
            ├── api/              one module per API resource
            ├── services/         logic the pages call
            ├── components/       reusable UI (table, modal, toast, …)
            └── pages/            one module per screen
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
| `api_tokens` | SHA-256 hashes of API bearer tokens, with expiry | `user_id` → `users.id` |
| `sso_tokens` | One-time single sign-on codes (login or logout), hashed, valid 60 seconds | `user_id` → `users.id` |
| `login_attempts` | Failed sign-ins, used for rate limiting | — |
| `settings` | System preferences: default academic year and semester, rows per page | `updated_by` → `users.id` |

The Laravel module's migrations (`enrollment-module/database/migrations`) add:

| Table | Purpose | Key relationships |
|---|---|---|
| `subjects` | Subject code, name, units, department, status | `prerequisite_id` → `subjects.id` |
| `course_offerings` | A section of a subject in one term: instructor, room, schedule, slots | `subject_id` → `subjects.id`; one section code per subject per term |
| `enrollments` | One student's enrollment for one term, with status | `student_id` → `students.student_id` (deleted with the student); `enrolled_by` → `users.id`; one per student per term |
| `enrollment_offering` | The sections an enrollment includes (many-to-many) | `enrollment_id` → `enrollments.id`; `course_offering_id` → `course_offerings.id` |

## Laravel module

The enrollment part of the system is built with Laravel's MVC structure.

**Eloquent relationships**
- `Subject` *hasMany* `CourseOffering`, and *belongsTo* its prerequisite `Subject`.
- `CourseOffering` *belongsTo* `Subject`, and *belongsToMany* `Enrollment` through `enrollment_offering`.
- `Enrollment` *belongsTo* `Student`, *belongsToMany* `CourseOffering`, and *belongsTo* the `User` who enrolled the student.
- `Student` *hasMany* `Enrollment`, and *belongsTo* `Program`.
- `Student`, `Program` and `User` map the main app's existing tables.

**Pages**

| Page | URL | Admin | Registrar | Faculty |
|---|---|:-:|:-:|:-:|
| Subjects (list, search, filter) | `/subjects` | ✓ | ✓ | ✓ |
| Add, edit, delete subjects | `/subjects/create`, `/subjects/{id}/edit` | ✓ | 403 | 403 |
| Course Offerings (list by term), class list | `/offerings`, `/offerings/{id}` | ✓ | ✓ | ✓ |
| Add, edit, delete offerings | `/offerings/create`, `/offerings/{id}/edit` | ✓ | 403 | 403 |
| Enroll Student, edit an enrollment | `/enrollments/create`, `/enrollments/{id}/edit` | ✓ | ✓ | 403 |
| Enrollment Records | `/enrollments`, `/enrollments/{id}` | ✓ | ✓ | 403 |
| Delete an enrollment | — | ✓ | 403 | 403 |
| Student Records (enrollments by term) | `/records`, `/records/{student}` | ✓ | ✓ | ✓ |

**Enrollment rules** (`app/Services/EnrollmentService.php`), checked on the
server in a database transaction that locks the chosen sections:
- Every section must belong to the enrollment's term.
- New sections must be Open, and their subject Active.
- Only one section per subject.
- A section cannot exceed its slots. A Dropped enrollment frees its seats.
- At most 26 units per term.
- Only one enrollment per student per term.

Other safeguards:
- A subject that still has sections cannot be deleted.
- A section with enrolled students cannot be deleted or moved to another
  term, and its slots cannot drop below the number enrolled.

**Integration with the main app**
- Both parts use the same database. Students created in the main app can be
  enrolled right away, and deleting a student there also removes their
  enrollments (foreign key `ON DELETE CASCADE`).
- Both parts use the same `users` table and bcrypt password hashes, so the
  same usernames and passwords work in both.
- The menus link to each other. Old main-app addresses such as `#/subjects`
  forward to the Laravel pages.
- **Single sign-on:** users sign in once, on the main app's sign-in page.
  - Menu links between the apps go through `go-laravel.php` (main → Laravel)
    and `/go-main` (Laravel → main). These create a one-time code in the shared
    `sso_tokens` table, and the other app's hand-off page (`/sso` in Laravel,
    `sso.php` in the main app) signs the same user in and opens the page.
  - Codes are 64 random hex characters, valid for 60 seconds, usable once, and
    stored only as SHA-256 hashes. Only paths inside the system are accepted
    as targets, so the hand-off cannot redirect to other websites.
  - Opening a Laravel page while signed out leads to the main sign-in page and
    then back to that page.
  - Logging out of either app passes a one-time logout code to the other, so
    both sessions end. A link from another website still cannot log anyone
    out.
  - Each app keeps its own session cookie and still checks the role on every
    request.

## Reports and analytics

The **Reports & Analytics** page (admin and registrar) generates six reports
from live data. Each report has summary figures, bar charts and tables, and can
be exported as CSV or printed (print shows only the report, in landscape).

| Report | What it answers |
|---|---|
| Enrollment Summary | How many enrollments are pending, confirmed, active, completed or dropped; enrolled students, subject loads and units per year level |
| Enrollment by Program | Enrolled and dropped students, units and share of enrollment per program |
| Enrollment by Semester | Enrollment and drop rate in every semester of the academic year(s) |
| Section Utilization | Seats taken in every course offering; full sections and sections under 25% filled |
| Student Demographics | Students by gender, age group, year level, program, civil status and record status |
| Student Master List | Every student with program, year level, status and their enrollment in the term |

Filters: academic year, semester, program and year level (each report uses
the ones listed on its card). They start at the default term from **Settings**.
An enrollment counts as enrolled unless its status is Dropped. The SQL is in
`backend/repositories/ReportRepository.php` (JOIN and GROUP BY, prepared
statements), and `backend/services/ReportService.php` assembles each report.

The **Dashboard** also shows two current-term cards: students enrolled this
term and sections at full capacity.

**Settings** (admin only) are stored in the `settings` table: the default
academic year and semester (used by the Reports filters, the Dashboard's term
cards and new student records) and the rows per page on the Students list.

## REST API

Full reference with request and response examples: **[docs/api.md](docs/api.md)**.

| Method | Endpoint | Roles | Success | Errors |
|---|---|---|---|---|
| POST | `/api/auth/login` | public | 200 + token | 401, 422, 429 |
| POST | `/api/auth/logout` | signed in | 204 | 401 |
| GET | `/api/auth/me` | signed in | 200 | 401 |
| GET | `/api/students?search=&program=&year=&status=&sort=&page=&perPage=` | all | 200 | 401, 422 |
| GET | `/api/students/{id}` | all | 200 | 401, 404 |
| POST | `/api/students` | admin, registrar | 201 | 400, 401, 403, 409, 415, 422 |
| PUT | `/api/students/{id}` (full replacement) | admin, registrar | 200 | 400, 401, 403, 404, 415, 422 |
| PATCH | `/api/students/{id}` (partial update) | admin, registrar | 200 | 400, 401, 403, 404, 415, 422 |
| DELETE | `/api/students/{id}` | admin | 204 | 401, 403, 404 |
| GET | `/api/dashboard` | all | 200 | 401 |
| GET | `/api/reports` (catalog) | admin, registrar | 200 | 401, 403 |
| GET | `/api/reports/{type}?academic_year=&semester=&program=&year_level=&format=` | admin, registrar | 200 (JSON or CSV) | 401, 403, 404, 422 |
| GET | `/api/settings` | all | 200 | 401 |
| PUT | `/api/settings` | admin | 200 | 400, 401, 403, 415, 422 |

- **Response envelope:** every JSON response is
  `{ "success", "data", "message", "errors", "meta" }`, and 422 responses list
  the errors per field.
- **Unknown URLs and methods:** an unknown URL returns 404, and a wrong method
  returns 405 with an `Allow` header.
- **Authentication:** Postman and other clients sign in with
  `POST /api/auth/login` and send `Authorization: Bearer <token>`. The app's
  own pages use their session cookie plus an `X-CSRF-Token` header.

**Testing:** import
[`postman/Group1_Enrollment_API.postman_collection.json`](postman/Group1_Enrollment_API.postman_collection.json)
into Postman and click **Run**. It has 48 requests covering the success and
failure cases of every endpoint. It cleans up after itself.

## Testing

Full results with expected and actual outcomes: **[docs/testing.md](docs/testing.md)**.

| Suite | Command (from the project folder, both servers running) | Result |
|---|---|---|
| System test: sign-in, roles, CSRF, SQL injection, XSS, validation, API, single sign-on, integration, report accuracy | `php tests\system_test.php` | 72 / 72 |
| Laravel feature tests: enrollment rules, access control, sign-in, single sign-on, relationships | `cd enrollment-module` then `php artisan test` | 39 / 39 |
| Postman collection: every endpoint | Postman → *Run*, or `php tests\run_postman.php` | 48 / 48 |

The tests leave your data as it was: the system test and Postman delete what
they create, and the Laravel tests use a separate `enrollment_system_test`
database.

## Roles

| Action | Admin | Registrar | Faculty |
|---|:-:|:-:|:-:|
| View dashboard and students | ✓ | ✓ | ✓ |
| Add or edit students | ✓ | ✓ | ✗ (403) |
| Delete students | ✓ | ✗ (403) | ✗ (403) |
| Reports & Analytics, CSV export | ✓ | ✓ | ✗ (403) |
| Change settings | ✓ | ✗ (403) | ✗ (403) |
| Create user accounts (`register.php`) | ✓ | ✗ (403) | ✗ (403) |

Roles are stored in `users.role` and set only by the seeder or an
administrator. The navigation hides what a role cannot use, but that is only
for convenience: the server checks the role again on every page
(`Auth::requireRole()`), every API route (the roles listed in
`public/api/index.php`, checked by `ApiAuth`) and every Laravel route (the
`role:` middleware in `enrollment-module/routes/web.php`). The Laravel pages'
roles are listed under [Laravel module](#laravel-module).

## Security notes

| Threat | Protection | Where |
|---|---|---|
| SQL injection | MySQLi prepared statements for every query that uses input | all PHP files |
| Stolen or weak password storage | `password_hash()` / `password_verify()`, automatic rehash | `Auth::attempt()`, `register.php` |
| Brute-force sign-in | 5 failures per username or 20 per IP in 15 minutes → blocked (429) | `Auth::tooManyAttempts()` |
| Username guessing | One message for wrong username or wrong password | `login.php` |
| Session fixation | `session_regenerate_id(true)` on sign-in | `Auth::login()` |
| Session theft | HttpOnly + SameSite=Lax cookie (Secure over HTTPS), strict mode, 30-minute idle timeout | `Auth::start()` |
| Single sign-on abuse | One-time codes: random, hashed, 60-second lifetime, deleted on use; only in-system targets (no open redirect); a logout code only ends the session of the user it was issued for | `SsoToken`, `go-laravel.php`, `sso.php`, `SsoController` |
| CSRF | Per-session token on every form and on session-based API writes, compared with `hash_equals()`. Bearer-token requests cannot be forged cross-site. | `Auth::verifyCsrf()`, `ApiAuth` |
| Broken access control | Role checked on the server for every page and API route | `Auth::requireRole()`, `public/api/index.php` + `ApiAuth` |
| Stolen API token | Random 256-bit tokens, stored only as SHA-256 hashes, 8-hour expiry, revoked on logout | `ApiAuth` |
| Bad input | Server-side validation for every field (422 per field), JSON-only bodies (415 / 400), whitelisted sort and filter values | `StudentValidator`, `Request`, `StudentController` |
| Partial writes | A student's three tables are written in one transaction | `StudentRepository` |
| Error details leaking | Any error becomes a generic JSON 500; details go to the server log only | `backend/api/bootstrap.php` |
| Logout that leaves the session alive | POST-only logout clears data, expires the cookie, destroys the session | `logout.php`, `Auth::logout()` |
| XSS | `htmlspecialchars()` in PHP pages; JavaScript screens render data through `escapeHtml()`; Blade `{{ }}` in Laravel. Verified with `<script>` and `onerror` payloads (tests XSS-01/02, BRW-02) | PHP pages, `core/dom.js`, Blade views |
| Exposed files on Apache | Root `.htaccess` refuses everything outside the two `public/` folders (`.env`, `backend/`, `.git/` → 403) | `.htaccess` |
| Leaked credentials | Database settings in git-ignored `.env`; errors logged, not shown | `database.php` |
| Laravel module | Eloquent for every query; `@csrf` on every form (419 without it); `role:` middleware on every route (403); sign-in limited to 5 attempts per minute; session regenerated on sign-in; POST-only logout; Blade `{{ }}` escapes output | `enrollment-module/` |
| Overbooking a section | Seats checked inside a transaction with the chosen sections locked (`SELECT … FOR UPDATE`) | `EnrollmentService` |
| Report filter tampering | Every filter checked against an allowed list (422); values only ever bound as prepared-statement parameters | `ReportController`, `ReportRepository` |
| Formula injection in CSV exports | Text cells starting with `=`, `+`, `-` or `@` are prefixed with `'` | `Response::csv()` |

## AI and third-party code disclosure

Parts of this project were written with help from AI coding assistants
(Claude by Anthropic), including the Phase 1 schema changes, `.env` loading,
the user seeder, the Phase 2 authentication code (`Auth.php`, `login.php`,
`logout.php`, `register.php` and the related JavaScript changes), the Phase 3
REST API (everything under `backend/api/`, `backend/repositories/`,
`backend/validators/`, `public/api/`, `public/router.php`, the related
JavaScript changes), the Postman collection, `docs/api.md`, the Phase 4
Laravel module (the migrations, seeders, models, controllers, form requests,
middleware, service, Blade views and `laravel.css` in `enrollment-module/`,
and the main-app menu and redirect changes), the Phase 5 reports and settings
(`ReportRepository`, `ReportService`, `ReportController`, `SettingsRepository`,
`SettingsController`, `AcademicTerm`, the `settings` table, the dashboard term
figures, and the Reports, Settings and dashboard JavaScript and CSS changes,
including `core/http.js` and `core/settings.js`), the Phase 6 tests
(`tests/`, `enrollment-module/tests/`), the fixes they led to, the root
`.htaccess`, the documents in `docs/` (`testing.md`, `architecture.md` and its
diagrams, `deployment.md`, `defense.md`) and this README. The Laravel
project skeleton itself comes from `composer create-project laravel/laravel`.
The Phase 3 repository and validator reuse the SQL and validation rules from
the team's midterm `students.php`. Every team member has reviewed this code and
can explain and modify it. Third-party resources loaded by the frontend:
[Inter](https://fonts.google.com/specimen/Inter) (Google Fonts) and
[Lucide](https://lucide.dev) icons.
