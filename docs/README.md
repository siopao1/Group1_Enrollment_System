# Documentation

| Document | Read it when you want to |
|---|---|
| [setup.md](setup.md) | Install and run the project, or fix something that will not start |
| [architecture.md](architecture.md) | Understand the layering, request lifecycle, auth and RBAC |
| [api.md](api.md) | Call the API: endpoints, envelope, status codes, permissions |
| [database.md](database.md) | Understand the schema and why it is shaped this way |
| [frontend.md](frontend.md) | Add a page, or remove the temporary mock layer |

Start with [setup.md](setup.md), then [architecture.md](architecture.md).

## How the learning objectives map to the code

| Objective | Where to look |
|---|---|
| 1. Relational database + dynamic site | `database/migrations/`, `docs/database.md` |
| 2. OOP in PHP | `app/` — `Core/`, `Controllers/`, `Services/`, `Repositories/`, `Models/` |
| 3. Auth, authorization, RBAC | `app/Services/AuthService.php`, `app/Middleware/`, `config/auth.php` |
| 4. RESTful API | `routes/api.php`, `docs/api.md` |
| 5. Integrated, documented app | this folder, the root `README.md` |
