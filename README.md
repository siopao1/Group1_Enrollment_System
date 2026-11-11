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
| Final – Phase 2 | Registration, login, sessions, role-based access, CSRF, secure logout | Not started |
| Final – Phase 3 | REST API with clean URLs, bearer tokens, Postman collection | Not started |
| Final – Phase 4 | Laravel module for subjects, course offerings and enrollments | Not started |
| Final – Phase 5 | Enrollment report and analytics | Not started |
| Final – Phase 6 | Testing, documentation, deployment | Not started |

**What works against the real database today:** the Dashboard and the
Students screens (list, search, filter, sort, paginate, add, edit, delete,
view profile).

**Still on browser-only mock data:** sign-in (the app signs in automatically
as Admin), Subjects, Course Offerings, Enroll Student, Enrollment Records,
Student Records, Reports and Settings. These are replaced in later phases.

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

Created by `backend/database/seed_users.php`. They are stored in the `users`
table and become usable when real sign-in is added in Phase 2.

| Username | Password | Role |
|---|---|---|
| `admin` | `Admin@12345` | Administrator |
| `registrar` | `Registrar@12345` | Registrar |
| `faculty` | `Faculty@12345` | Faculty |

These are local demo credentials only. Change them before any public deployment.

## Folder structure

```
Group1_Enrollment_System/
├── .env.example                  template for the git-ignored .env file
├── backend/                      not web-accessible
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
    ├── index.php                 app shell; JavaScript renders each screen
    ├── login.php                 sign-in page
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
| `login_attempts` | Failed sign-ins for rate limiting (Phase 2) | — |

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

Clean REST URLs, authentication and a Postman collection are added in Phase 3.

## Roles

| Role | Planned access |
|---|---|
| Administrator | Everything, including user accounts and settings |
| Registrar | Students, enrollment, records, reports |
| Faculty | View students, subjects, offerings and records |

Roles are stored in `users.role`. Server-side enforcement is added in Phase 2;
until then the screens only hide what a role cannot use, which is not security.

## Security notes

- Every query that uses input runs as a MySQLi prepared statement.
- Passwords are stored only as `password_hash()` output (bcrypt).
- Database credentials live in `.env`, which is listed in `.gitignore`.
- Database connection errors are logged on the server; visitors see a generic message.

## AI and third-party code disclosure

Parts of this project were written with help from AI coding assistants
(Claude by Anthropic), including the Phase 1 schema changes, `.env` loading,
the user seeder and this README. Every team member has reviewed this code and
can explain and modify it. Third-party resources loaded by the frontend:
[Inter](https://fonts.google.com/specimen/Inter) (Google Fonts) and
[Lucide](https://lucide.dev) icons.
