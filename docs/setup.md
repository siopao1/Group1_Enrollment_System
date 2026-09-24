# Setup

## Requirements

- PHP 8.1 or newer (`pdo_mysql` extension enabled)
- MySQL 8 or MariaDB 10.4+
- A browser released after 2021 (the frontend uses ES modules)

No Composer, npm or build step is needed for the native system. Composer is only
required if you install the Laravel module.

## 1. Frontend only (no PHP, no MySQL)

The UI ships with a mock API, so you can open it immediately:

```bash
cd enrollment-system
php -S localhost:8000 -t public      # or any static server
```

Because ES modules are blocked on `file://`, you do need *a* server — the VS
Code "Live Server" extension works too. Open <http://localhost:8000/login.php>
and sign in with any of `admin`, `registrar`, or `faculty` and the
password `password`.

## 2. Full stack

### Configure

```bash
cp .env.example .env
```

Edit `.env`:

```ini
DB_HOST=127.0.0.1
DB_DATABASE=enrollment_system
DB_USERNAME=root
DB_PASSWORD=your-password
```

`.env` is git-ignored. Never commit it and never copy these values into a
JavaScript file.

### Create the database

```bash
php database/migrate.php --seed
```

- `--seed` adds demo programs, subjects, students and enrollments
- `--fresh` drops the database first and rebuilds it
- no flags: schema only

Prefer phpMyAdmin? Create a `enrollment_system` database, then import the files
in `database/migrations/` in numeric order, followed by `database/seeders/`.

### Switch the frontend to the real API

In `public/assets/js/config/app.config.js`:

```js
useMockApi: false,
```

Then delete `public/assets/js/mock/` and the two `import("./mock/mockServer.js")`
lines in `main.js` and `login.js`. Nothing else changes — that is the whole
point of the api/service layering.

### Run

```bash
php -S localhost:8000 -t public
```

## 3. XAMPP

1. Copy the project to `C:\xampp\htdocs\enrollment-system`
2. Start Apache and MySQL from the control panel
3. Create the database at <http://localhost/phpmyadmin>, then import the
   migration and seeder files
4. Open <http://localhost/enrollment-system/public/>

Every path in the app is relative, so the subfolder URL works without changes.
`mod_rewrite` must be on for the API's pretty URLs — it is by default in XAMPP.

For a cleaner URL, point a virtual host's `DocumentRoot` at the `public/`
folder. Never expose the project root: that would put `.env` one request away.

## 4. Change the demo passwords

The seeded accounts all use `password`. Before showing this to anyone:

```bash
php database/hash-password.php "a-better-password"
```

Copy the hash into the `users` table for that account.

## Permissions

```bash
chmod -R 775 storage public/uploads
```

## Troubleshooting

**Blank page, console shows "Failed to load module script"**
The server is sending `.js` as the wrong MIME type, or you opened the file over
`file://`. Serve it over HTTP.

**"Database connection failed."**
Check the `DB_*` values in `.env` and that MySQL is running. The real driver
message is deliberately hidden because it can contain credentials; look in
`storage/logs/` for the logged detail.

**Every API call returns 404**
`mod_rewrite` is off, or `AllowOverride` is not `All` for the directory, so
`public/api/.htaccess` is ignored.

**Sign-in works, then every request is 401**
Session cookies are being dropped. Make sure you use one hostname consistently
(`localhost` and `127.0.0.1` are different origins), and set `SESSION_SECURE=false`
when not on HTTPS.

**Changes to a JS file do nothing**
Hard-reload (Ctrl+Shift+R). Module scripts cache aggressively.
