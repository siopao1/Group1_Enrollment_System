# University Enrollment System

A full-stack student enrollment and records system for a university registrar's
office, backed by a native PHP/MySQL backend.

```
HTML / CSS / JavaScript  →  REST API  →  PHP Controllers  →  Services  →  Repositories  →  MySQL
```

## Technologies

| Layer | Technology |
|---|---|
| Frontend | HTML5, CSS3, vanilla JavaScript (ES modules) |
| Backend | Native PHP 8.1+ (OOP, PSR-4) |
| Database | MySQL 8 / MariaDB 10.4+ (InnoDB, foreign keys, transactions) |
| API | REST with a consistent JSON envelope |
| Auth | Server-side sessions, bcrypt, role-based access control |

No build step, no bundler, no npm. Open it and it runs.

## Quick start

```bash
cp .env.example .env            # then edit the DB_* values
php database/migrate.php --seed # create the schema and demo data
php -S localhost:8000 -t public # serve the app
```

Open <http://localhost:8000> and sign in with `admin` / `password`.
Full instructions, including XAMPP, are in [docs/setup.md](docs/setup.md).

> While `useMockApi` is `true` in `public/assets/js/config/app.config.js`, the
> UI runs entirely in the browser and needs neither PHP nor MySQL. Set it to
> `false` to talk to the real API.

## Folder structure

```
enrollment-system/
├── public/                     the only web-accessible folder
│   ├── index.php               app shell (renders once, then JS takes over)
│   ├── login.php               sign-in page
│   ├── api/index.php           API front controller — everything under /api
│   └── assets/
│       ├── css/                base/ layout/ components/ pages/ + app.css
│       └── js/
│           ├── config/         app config, roles, navigation, option lists
│           ├── core/           apiClient, auth, router, storage, validator, dom
│           ├── api/            one thin module per REST resource
│           ├── services/       use-cases the pages call
│           ├── components/     reusable UI pieces (badge, table, modal, toast…)
│           ├── pages/          one module per screen
│           ├── mock/           TEMPORARY fake API — delete when PHP is live
│           ├── routes.js       route table
│           ├── shell.js        nav, topbar, global search
│           └── main.js         entry point
│
├── app/
│   ├── Core/                   framework pieces (Router, Request, Response, …)
│   ├── Controllers/Api/        HTTP layer — thin
│   ├── Services/               business rules
│   ├── Repositories/           the only place with SQL
│   ├── Models/                 entities
│   ├── Middleware/             authentication + one class per permission
│   ├── Helpers/                Logger, Hash, Permission
│   └── Views/                  the two server-rendered pages
│
├── config/                     app.php, database.php, auth.php
├── routes/api.php              every endpoint, with its required permission
├── database/                   migrations/, seeders/, migrate.php
├── storage/                    logs/, cache/ (writable, not public)
├── docs/                       setup, architecture, api, database, frontend
├── bootstrap.php               shared bootstrap for all entry points
└── .env.example
```

Only the web server's document root points at `public/`. Everything else —
config, credentials, SQL, logs — sits above it and cannot be requested.

### Deliberate departures from a generic layout

- **No top-level `api/` folder.** It would have duplicated
  `app/Controllers/Api/`. One controller per resource, one router, one place to
  look.
- **No `database/sql/` dump.** `database/migrations/` is the single source of
  truth for the schema; a parallel `schema.sql` drifts out of date.
- **No `routes/web.php`.** The frontend is a single-page app, so the web side is
  two entry scripts (`index.php`, `login.php`). A router for two static pages is
  ceremony. The API does have a full route table.
- **No empty folders.** `app/Views/` has only the layouts actually rendered,
  because the UI is drawn client-side.

## Documentation

| Document | Contents |
|---|---|
| [docs/setup.md](docs/setup.md) | Installation, XAMPP, database setup, troubleshooting |
| [docs/architecture.md](docs/architecture.md) | Layering, request lifecycle, auth and RBAC |
| [docs/api.md](docs/api.md) | Every endpoint, envelope, status codes, examples |
| [docs/database.md](docs/database.md) | Schema, relationships, design decisions |
| [docs/frontend.md](docs/frontend.md) | JS structure, adding a page, removing the mock layer |

## Roles

| Role | Can do |
|---|---|
| Administrator | Everything, including system settings |
| Registrar | Students, enrollment, records, reports |
| Faculty | View students, subjects, offerings and records |

The interface hides what a role cannot use; the backend independently refuses
it. Both checks exist because only the second one is security.

## Project status

**Done** — UI for all eleven screens, frontend architecture, PHP core framework,
controllers/services/repositories/models, authentication and RBAC, database
schema and seeders, live API connected (`useMockApi` set to `false`), docs.

**Next** — add the remaining validation rules for edge cases in the enrollment
flow.
