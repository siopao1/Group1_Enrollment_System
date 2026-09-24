# Database

MySQL 8 / MariaDB 10.4+, InnoDB, `utf8mb4_unicode_ci`.

## Tables

| Table | Holds |
|---|---|
| `roles` | admin, registrar, faculty |
| `users` | login accounts and their password hashes |
| `programs` | BSIT, BSCS, BSBA, BSED |
| `students` | student profiles, keyed by student number |
| `faculty` | instructors |
| `subjects` | the subject catalog |
| `academic_terms` | academic year + semester, with the current one flagged |
| `course_offerings` | a subject scheduled for a term, with slots |
| `enrollments` | one transaction per student per term |
| `enrollment_details` | the offerings inside an enrollment |
| `grades` | one grade per student per subject per term |
| `settings` | key/value system preferences |

## Relationships

```
roles ──< users ──< students (optional user link)
programs ──< students ──< enrollments ──< enrollment_details >── course_offerings
                    └──< grades >── subjects ──< course_offerings
faculty ──< course_offerings
```

`enrollment_details` is the junction table that makes an enrollment a
many-to-many relationship with offerings — which is what lets a student take
several subjects under one enrollment record.

## Design decisions

**Student numbers are the primary key.** `2026-00125` is already unique,
meaningful and quoted by staff and students. A surrogate integer would add a
join to every lookup for no benefit.

**`enrolled_count` is stored on `course_offerings`.** It duplicates
`COUNT(*)` over `enrollment_details`, which is normally a smell. It is here
because every listing shows "35 / 40" and recomputing that per row would be a
correlated subquery on the hottest screen in the app. It is only ever written
inside the enrollment transaction, and a `CHECK` constraint keeps it at or below
`max_slots`. The seeder recomputes it after inserting demo rows.

**Open/Full is not a column.** It is derived from `enrolled_count` and
`max_slots` in `CourseOffering::status()`. A stored status could contradict the
counts; a derived one cannot.

**One enrollment per student per term** is enforced by a unique key on
`(student_number, academic_year, semester)`, not only by the service. Two
concurrent requests can pass a service check; they cannot both pass a unique
index.

**Foreign keys have explicit behaviours.** Deleting a student cascades to their
enrollments and grades, because those records are meaningless without them.
Deleting a user only nulls the link on a student, because the person still
exists. Subjects referenced by offerings cannot be deleted at all.

**Grades store the numeric scale** (1.00 best, 5.00 worst) rather than letters,
so GPA is a weighted average computed in SQL — see
`AcademicRecordRepository::summaryFor()`. Computing it in the browser would give
a different answer in different places.

## Indexes

Beyond the primary and foreign keys:

- `students`: `status`, `program_id`, and `(last_name, first_name)` for the
  name search
- `enrollments`: `status`
- `course_offerings`: `(academic_year, semester)`
- `subjects`: `department`

These cover the filters the Students, Enrollment Records and Offerings pages
actually send.

## Migrations and seeders

```bash
php database/migrate.php            # schema only
php database/migrate.php --seed     # schema + demo data
php database/migrate.php --fresh    # drop and rebuild
```

Files run in filename order, so the numeric prefixes matter:

```
migrations/001_create_auth_tables.sql        roles, users
migrations/002_create_academic_tables.sql    programs, students, faculty, subjects, terms
migrations/003_create_enrollment_tables.sql  offerings, enrollments, details, grades, settings

seeders/001_roles_and_users.sql              roles + four demo accounts
seeders/002_reference_data.sql               programs, subjects, current term, settings
seeders/003_demo_records.sql                 sample students, offerings, enrollments, grades
```

Seeders use `ON DUPLICATE KEY UPDATE`, so they are safe to re-run.

The demo accounts share the bcrypt hash of `password`. Replace them before any
real use:

```bash
php database/hash-password.php "a-better-password"
```
