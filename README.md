# University Enrollment & Records System

**WMA4 – Advanced Web Development · Group 1 · System type: Student Enrollment & Records System**

A web application for a university registrar's office. It manages student
profiles, academic programs and enrollment records, with a dashboard computed
live from the database.

```
Browser / Postman  →  REST API (native PHP: Router → Controller → Validator → Repository)  →  MySQL
```

## Project status

| Phase | Scope | Status |
|---|---|---|
| Midterm | Student CRUD, OOP classes, normalized database, search/filter/sort, dashboard | Done |
| Final – Phase 1 | Users and security tables, credentials in `.env`, user seeder | Done |
| Final – Phase 2 | Registration, login, sessions, role-based access, CSRF, secure logout | Done |
| Final – Phase 3 | REST API with clean URLs, bearer tokens, Postman collection | Done |
| Final – Phase 4 | Laravel module for subjects, course offerings and enrollments | In progress (Laravel 12 installed) |
| Final – Phase 5 | Enrollment report and analytics | Not started |
| Final – Phase 6 | Testing, documentation, deployment | Not started |

**What works against the real database today:** sign-in and sign-out,
admin-only user registration, the Dashboard, the Students screens (list,
search, filter, sort, paginate, add, edit, delete, view profile), and the REST
API behind them (`/api/students`, `/api/dashboard`, `/api/auth`). Each of
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
   C:\xampp\php\php.exe -S localhost:8000 -t public public/router.php
   ```
   Then open <http://localhost:8000>. The last argument, `public/router.php`,
   is required: it sends `/api/...` URLs to the API.

### Laravel module (`enrollment-module/`)

The Laravel 12 module uses the **same MySQL/MariaDB database** as the main app.
It needs [Composer](https://getcomposer.org). From the `enrollment-module` folder:

```
composer install
copy .env.example .env
php artisan key:generate
php artisan serve --port=8001
```

Then open <http://localhost:8001>. `.env.example` is already set to the
`enrollment_system_min` database on XAMPP's MariaDB (`DB_CONNECTION=mariadb`);
change the `DB_*` values if yours differ. Run the main app on port 8000 and the
Laravel module on port 8001 at the same time.

> Do not run `php artisan migrate` until the module's own migrations are
> added: Laravel's default migrations include a `users` table that would
> conflict with the main app's `users` table.

> **Using XAMPP's Apache instead:** put the project under `htdocs` and open
> `http://localhost/<folder>/public/`. `public/api/.htaccess` routes the API
> (Apache needs `mod_rewrite` and `AllowOverride All`). The frontend uses
> relative URLs, so it works from a subfolder. This setup has not been tested
> yet; the `php -S` command above has.

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
│   │   └── controllers/          AuthController, StudentController, DashboardController
│   ├── auth/
│   │   └── Auth.php              sessions, login, rate limiting, roles, CSRF, logout
│   ├── repositories/             StudentRepository, DashboardRepository (all SQL)
│   ├── validators/               StudentValidator (POST / PUT / PATCH rules)
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
├── enrollment-module/            Laravel 12 module (Phase 4), shares the same database
├── docs/
│   ├── api.md                    REST API reference
│   └── postman/                  Postman collection (import and run)
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
| `api_tokens` | SHA-256 hashes of API bearer tokens, with expiry | `user_id` → `users.id` |
| `login_attempts` | Failed sign-ins, used for rate limiting | — |

Subjects, course offerings and enrollments will be created by the Laravel
module's migrations in Phase 4.

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

- **Response envelope:** every JSON response is
  `{ "success", "data", "message", "errors", "meta" }`, and 422 responses list
  the errors per field.
- **Unknown URLs and methods:** an unknown URL returns 404, and a wrong method
  returns 405 with an `Allow` header.
- **Authentication:** Postman and other clients sign in with
  `POST /api/auth/login` and send `Authorization: Bearer <token>`. The app's
  own pages use their session cookie plus an `X-CSRF-Token` header.

**Testing:** import
[`docs/postman/Group1_Enrollment_API.postman_collection.json`](docs/postman/Group1_Enrollment_API.postman_collection.json)
into Postman and click **Run**. It has 37 requests covering the success and
failure cases of every endpoint. It cleans up after itself.

## Roles

| Action | Admin | Registrar | Faculty |
|---|:-:|:-:|:-:|
| View dashboard and students | ✓ | ✓ | ✓ |
| Add or edit students | ✓ | ✓ | ✗ (403) |
| Delete students | ✓ | ✗ (403) | ✗ (403) |
| Create user accounts (`register.php`) | ✓ | ✗ (403) | ✗ (403) |

Roles are stored in `users.role` and set only by the seeder or an
administrator. The navigation hides what a role cannot use, but that is only
for convenience: the server checks the role again on every page
(`Auth::requireRole()`) and every API route (the roles listed in
`public/api/index.php`, checked by `ApiAuth`).

## Security notes

| Threat | Protection | Where |
|---|---|---|
| SQL injection | MySQLi prepared statements for every query that uses input | all PHP files |
| Stolen or weak password storage | `password_hash()` / `password_verify()`, automatic rehash | `Auth::attempt()`, `register.php` |
| Brute-force sign-in | 5 failures per username or 20 per IP in 15 minutes → blocked (429) | `Auth::tooManyAttempts()` |
| Username guessing | One message for wrong username or wrong password | `login.php` |
| Session fixation | `session_regenerate_id(true)` on sign-in | `Auth::login()` |
| Session theft | HttpOnly + SameSite=Lax cookie, strict mode, 30-minute idle timeout | `Auth::start()` |
| CSRF | Per-session token on every form and on session-based API writes, compared with `hash_equals()`. Bearer-token requests cannot be forged cross-site. | `Auth::verifyCsrf()`, `ApiAuth` |
| Broken access control | Role checked on the server for every page and API route | `Auth::requireRole()`, `public/api/index.php` + `ApiAuth` |
| Stolen API token | Random 256-bit tokens, stored only as SHA-256 hashes, 8-hour expiry, revoked on logout | `ApiAuth` |
| Bad input | Server-side validation for every field (422 per field), JSON-only bodies (415 / 400), whitelisted sort and filter values | `StudentValidator`, `Request`, `StudentController` |
| Partial writes | A student's three tables are written in one transaction | `StudentRepository` |
| Error details leaking | Any error becomes a generic JSON 500; details go to the server log only | `backend/api/bootstrap.php` |
| Logout that leaves the session alive | POST-only logout clears data, expires the cookie, destroys the session | `logout.php`, `Auth::logout()` |
| XSS | `htmlspecialchars()` in PHP pages; JavaScript screens render data through `escapeHtml()` (re-checked during Phase 6 testing) | PHP pages, `core/dom.js` |
| Leaked credentials | Database settings in git-ignored `.env`; errors logged, not shown | `database.php` |

## AI and third-party code disclosure

Parts of this project were written with help from AI coding assistants
(Claude by Anthropic), including the Phase 1 schema changes, `.env` loading,
the user seeder, the Phase 2 authentication code (`Auth.php`, `login.php`,
`logout.php`, `register.php` and the related JavaScript changes), the Phase 3
REST API (everything under `backend/api/`, `backend/repositories/`,
`backend/validators/`, `public/api/`, `public/router.php`, the related
JavaScript changes), the Postman collection, `docs/api.md` and this README.
The Phase 3 repository and validator reuse the SQL and validation rules from
the team's midterm `students.php`. Every team member has reviewed this code and
can explain and modify it. Third-party resources loaded by the frontend:
[Inter](https://fonts.google.com/specimen/Inter) (Google Fonts) and
[Lucide](https://lucide.dev) icons.
