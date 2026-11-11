# Enrollment module (Laravel 12)

The Laravel part of the University Enrollment & Records System: **Subjects,
Course Offerings, Enroll Student, Enrollment Records and Student Records**.
It shares the main app's MySQL/MariaDB database and user accounts.

Setup, the table list, Eloquent relationships, role rules and enrollment rules
are documented in the project's main README:
[../README.md — Laravel module](../README.md#laravel-module).

Quick start (after the main app's database and demo accounts exist):

```
composer install
copy .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed
php artisan serve --port=8001
```

Open <http://localhost:8001> and sign in with `admin` / `Admin@12345`,
`registrar` / `Registrar@12345` or `faculty` / `Faculty@12345`.
