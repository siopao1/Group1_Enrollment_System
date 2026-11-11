<?php

declare(strict_types=1);

/**
 * System test: checks the running application end to end over HTTP —
 * authentication, role-based access, CSRF, SQL injection, XSS, validation,
 * the REST API, the reports, and the integration between the main app and
 * the Laravel module.
 *
 * Needs both servers running (see README) and the demo data/accounts:
 *   php -S localhost:8000 -t public public/router.php
 *   cd enrollment-module && php artisan serve --port=8001
 *
 * Usage (from the project folder):
 *   php tests/system_test.php                  results on screen
 *   php tests/system_test.php --markdown=FILE  also writes the results as a Markdown table
 *
 * The test creates its own students (IDs 2098-xxxxx), enrolls them, and
 * deletes them again at the end, together with the failed sign-ins it causes
 * and the API tokens it issues. Settings are restored. The demo data is left
 * as it was. Exit code 0 = every test passed.
 */

require __DIR__ . '/../backend/config/database.php';
require __DIR__ . '/lib/Http.php';

$options  = getopt('', ['main::', 'laravel::', 'markdown::']);
$MAIN     = rtrim($options['main'] ?? 'http://localhost:8000', '/');
$LARAVEL  = rtrim($options['laravel'] ?? 'http://localhost:8001', '/');
$markdown = $options['markdown'] ?? null;

const PASSWORDS = ['admin' => 'Admin@12345', 'registrar' => 'Registrar@12345', 'faculty' => 'Faculty@12345'];
const TERM_YEAR = '2026–2027';
const TERM_SEM  = '1st Semester';

// ------------------------------------------------------------------ framework

final class Fail extends Exception
{
}

/** Throws (fails the test) with $actual as the reason when $condition is false. */
function ensure(bool $condition, string $actual): void
{
    if (!$condition) {
        throw new Fail($actual);
    }
}

$results = [];
$section = '';

/**
 * Runs one test. $run returns a short description of what actually happened
 * (shown in the "Actual" column), or throws Fail.
 */
function check(string $id, string $title, string $expected, callable $run): void
{
    global $results, $section;
    try {
        $actual = (string) $run();
        $ok = true;
    } catch (Fail $e) {
        $actual = $e->getMessage();
        $ok = false;
    } catch (Throwable $e) {
        $actual = 'Error: ' . $e->getMessage();
        $ok = false;
    }
    $results[] = compact('section', 'id', 'title', 'expected', 'actual', 'ok');
    printf("  %s %-9s %s%s\n", $ok ? 'PASS' : 'FAIL', $id, $title, $ok ? '' : "\n              → $actual");
}

function section(string $name): void
{
    global $section;
    $section = $name;
    echo "\n== $name\n";
}

// -------------------------------------------------------------------- helpers

/** A signed-in browser session on the main app. */
function mainSession(string $user, ?string $password = null): Http
{
    global $MAIN;
    $http = new Http($MAIN);
    $csrf = $http->formToken('/login.php', 'csrf');
    $res = $http->post('/login.php', ['form' => ['csrf' => $csrf, 'username' => $user, 'password' => $password ?? PASSWORDS[$user]]]);
    if ($res->status !== 302) {
        throw new Fail("Could not sign in as $user (HTTP {$res->status})");
    }
    return $http;
}

/** The CSRF token the app page gives a signed-in session (meta csrf-token). */
function pageCsrf(Http $http): string
{
    preg_match('/name="csrf-token" content="([^"]+)"/', $http->get('/index.php')->body, $m);
    return $m[1] ?? '';
}

/** A signed-in browser session on the Laravel module. */
function laravelSession(string $user): Http
{
    global $LARAVEL;
    $http = new Http($LARAVEL);
    $token = $http->formToken('/login', '_token');
    $res = $http->post('/login', ['form' => ['_token' => $token, 'username' => $user, 'password' => PASSWORDS[$user]]]);
    if ($res->status !== 302 || str_contains((string) $res->header('location'), '/login')) {
        throw new Fail("Could not sign in to Laravel as $user (HTTP {$res->status})");
    }
    return $http;
}

$tokens = [];
function token(string $user): string
{
    global $MAIN, $tokens;
    if (!isset($tokens[$user])) {
        $res = (new Http($MAIN))->post('/api/auth/login', ['json' => ['username' => $user, 'password' => PASSWORDS[$user]]]);
        $tokens[$user] = $res->json()['data']['token'] ?? throw new Fail("No token for $user");
    }
    return $tokens[$user];
}

/** An API call with a bearer token. */
function api(string $user, string $method, string $path, array $options = []): HttpResponse
{
    global $MAIN;
    $options['headers'][] = 'Authorization: Bearer ' . token($user);
    return (new Http($MAIN))->request($method, '/api/' . ltrim($path, '/'), $options);
}

$createdStudents = [];
function newStudentId(): string
{
    global $createdStudents, $db;
    do {
        $id = '2098-' . random_int(10000, 99999);
        $exists = $db->query("SELECT 1 FROM students WHERE student_id = '$id'")->num_rows > 0;
    } while ($exists || in_array($id, $createdStudents, true));
    $createdStudents[] = $id;
    return $id;
}

function studentPayload(string $id, array $overrides = []): array
{
    return array_replace_recursive([
        'id' => $id, 'firstName' => 'Test', 'middleName' => '', 'lastName' => 'Student', 'suffix' => '',
        'email' => strtolower(str_replace('-', '', $id)) . '@example.com', 'contact' => '0917 000 0000',
        'address' => 'Tarlac City', 'program' => 'BS Information Technology', 'year' => '1st Year',
        'academicYear' => TERM_YEAR, 'status' => 'Active', 'dateOfBirth' => '2006-01-15',
        'gender' => 'Female', 'civilStatus' => 'Single',
        'emergencyContact' => ['name' => 'Parent', 'relationship' => 'Mother', 'number' => '0918 000 0000'],
    ], $overrides);
}

function scalar(string $sql, array $params = []): mixed
{
    global $db;
    $stmt = $db->prepare($sql);
    $params === [] ? $stmt->execute() : $stmt->execute($params);
    return $stmt->get_result()->fetch_row()[0] ?? null;
}

function report(string $type, array $query = []): array
{
    $res = api('admin', 'GET', 'reports/' . $type, ['query' => $query]);
    ensure($res->status === 200, "reports/$type returned {$res->status}");
    return $res->json()['data'];
}

function summaryValue(array $report, string $label): mixed
{
    foreach ($report['summary'] as $item) {
        if ($item['label'] === $label) {
            return $item['value'];
        }
    }
    throw new Fail("No summary item \"$label\"");
}

function offeringId(string $code, string $section): int
{
    return (int) scalar(
        'SELECT o.id FROM course_offerings o JOIN subjects s ON s.id = o.subject_id
          WHERE s.code = ? AND o.section = ? AND o.academic_year = ? AND o.semester = ?',
        [$code, $section, TERM_YEAR, TERM_SEM]
    );
}

/** Posts the Laravel "Enroll Student" form; returns the response after redirects. */
function enrollInLaravel(Http $lv, string $studentId, array $offeringIds): HttpResponse
{
    $token = $lv->formToken('/enrollments/create', '_token');
    return $lv->post('/enrollments', [
        'form' => ['_token' => $token, 'student_id' => $studentId, 'semester' => TERM_SEM,
                   'academic_year' => TERM_YEAR, 'status' => 'Active', 'offerings' => $offeringIds],
        'follow' => true,
    ]);
}

// ---------------------------------------------------------------------- start

echo "System test — main app $MAIN, Laravel module $LARAVEL\n";

foreach ([$MAIN . '/login.php', $LARAVEL . '/login'] as $url) {
    $probe = (new Http(''))->get($url);
    if ($probe->status === 0) {
        fwrite(STDERR, "Cannot reach $url. Start both servers first (see README).\n");
        exit(2);
    }
}

$db = getDbConnection(false);
$firstAttemptId = (int) scalar('SELECT COALESCE(MAX(id), 0) FROM login_attempts');
$firstSsoId = (int) scalar('SELECT COALESCE(MAX(id), 0) FROM sso_tokens');
$settingsBefore = api('admin', 'GET', 'settings')->json()['data'];

try {
    // ================================================================ AUTH
    section('Authentication and sessions (req. 7, 8)');

    check('AUTH-01', 'Opening the app while signed out', 'Redirect (302) to login.php', function () use ($MAIN) {
        $res = (new Http($MAIN))->get('/index.php');
        ensure($res->status === 302 && str_contains((string) $res->header('location'), 'login.php'), "HTTP {$res->status}, Location: " . $res->header('location'));
        return '302 → login.php';
    });

    check('AUTH-02', 'Sign in with a correct username and password', 'Redirect to index.php; the page shows the user\'s role', function () {
        $http = mainSession('registrar');
        $page = $http->get('/index.php');
        ensure($page->status === 200 && str_contains($page->body, '"role":"registrar"'), "index.php HTTP {$page->status}");
        return '302 → index.php, signed in as registrar';
    });

    check('AUTH-03', 'Sign in with a wrong password', 'Generic error "Invalid username or password."; not signed in', function () use ($MAIN) {
        $http = new Http($MAIN);
        $csrf = $http->formToken('/login.php', 'csrf');
        $res = $http->post('/login.php', ['form' => ['csrf' => $csrf, 'username' => 'faculty', 'password' => 'wrong-password']]);
        ensure(str_contains($res->body, 'Invalid username or password.'), "HTTP {$res->status}, no error message");
        ensure($http->get('/index.php')->status === 302, 'index.php opened without signing in');
        return 'Error shown, index.php still redirects';
    });

    check('AUTH-04', 'Sign in with a username that does not exist', 'The same message as a wrong password (no username guessing)', function () use ($MAIN) {
        $http = new Http($MAIN);
        $csrf = $http->formToken('/login.php', 'csrf');
        $res = $http->post('/login.php', ['form' => ['csrf' => $csrf, 'username' => 'p6test_nobody', 'password' => 'whatever']]);
        ensure(str_contains($res->body, 'Invalid username or password.'), 'Different or missing message');
        return 'Same "Invalid username or password." message';
    });

    check('AUTH-05', 'Sign-in form posted without its CSRF token', 'Rejected; not signed in', function () use ($MAIN) {
        $http = new Http($MAIN);
        $http->get('/login.php');
        $res = $http->post('/login.php', ['form' => ['username' => 'admin', 'password' => PASSWORDS['admin']]]);
        ensure($res->status !== 302 && str_contains($res->body, 'session expired'), "HTTP {$res->status}");
        ensure($http->get('/index.php')->status === 302, 'Signed in without a CSRF token');
        return '"Your session expired" shown, not signed in';
    });

    check('AUTH-06', 'Session cookie flags', 'HttpOnly and SameSite=Lax', function () use ($MAIN) {
        $cookie = implode(' ', (new Http($MAIN))->get('/login.php')->headers['set-cookie'] ?? []);
        ensure(stripos($cookie, 'httponly') !== false && stripos($cookie, 'samesite=lax') !== false, "Set-Cookie: $cookie");
        return 'Set-Cookie has HttpOnly; SameSite=Lax';
    });

    check('AUTH-07', 'Session ID after signing in (session fixation)', 'A new session ID is issued at sign-in', function () use ($MAIN) {
        $http = new Http($MAIN);
        $csrf = $http->formToken('/login.php', 'csrf');
        $before = $http->cookie('PHPSESSID');
        $http->post('/login.php', ['form' => ['csrf' => $csrf, 'username' => 'faculty', 'password' => PASSWORDS['faculty']]]);
        $after = $http->cookie('PHPSESSID');
        ensure($before !== null && $after !== null && $before !== $after, 'Session ID did not change');
        return 'Session ID changed (session_regenerate_id)';
    });

    check('AUTH-08', 'Five failed sign-ins for one username, then a sixth', 'The sixth attempt is blocked with 429', function () use ($MAIN) {
        $http = new Http($MAIN);
        for ($i = 1; $i <= 5; $i++) {
            $csrf = $http->formToken('/login.php', 'csrf');
            $http->post('/login.php', ['form' => ['csrf' => $csrf, 'username' => 'p6test_lockout', 'password' => "wrong$i"]]);
        }
        $csrf = $http->formToken('/login.php', 'csrf');
        $res = $http->post('/login.php', ['form' => ['csrf' => $csrf, 'username' => 'p6test_lockout', 'password' => 'wrong6']]);
        ensure($res->status === 429 && str_contains($res->body, 'Too many'), "Sixth attempt: HTTP {$res->status}");
        return '6th attempt: 429 "Too many failed sign-in attempts"';
    });

    check('AUTH-09', 'How passwords are stored', 'bcrypt hash from password_hash(), never the plain password', function () {
        $hash = (string) scalar("SELECT password_hash FROM users WHERE username = 'admin'");
        ensure(str_starts_with($hash, '$2y$') && $hash !== PASSWORDS['admin'] && password_verify(PASSWORDS['admin'], $hash), 'Not a bcrypt hash');
        return 'users.password_hash = $2y$10$… (bcrypt), verifies with password_verify()';
    });

    check('AUTH-10', 'Sign out with a GET request (e.g. a link or image)', '405; still signed in', function () {
        $http = mainSession('faculty');
        $res = $http->get('/logout.php');
        ensure($res->status === 405 && $http->get('/index.php')->status === 200, "HTTP {$res->status}");
        return '405, session still active';
    });

    check('AUTH-11', 'Sign out by POST without the CSRF token', '419; still signed in', function () {
        $http = mainSession('faculty');
        $res = $http->post('/logout.php', ['form' => []]);
        ensure($res->status === 419 && $http->get('/index.php')->status === 200, "HTTP {$res->status}");
        return '419, session still active';
    });

    check('AUTH-12', 'Sign out properly, then reuse the old session', 'Session ended (logout passes through the Laravel module); app and API refuse the old session', function () use ($LARAVEL) {
        $http = mainSession('faculty');
        $csrf = pageCsrf($http);
        $res = $http->post('/logout.php', ['form' => ['csrf' => $csrf]]);
        ensure($res->status === 302 && str_starts_with((string) $res->header('location'), "$LARAVEL/sso?token="), "Logout HTTP {$res->status} → " . $res->header('location'));
        $page = $http->get('/index.php')->status;
        $apiCall = $http->get('/api/students')->status;
        ensure($page === 302 && $apiCall === 401, "After logout: index.php $page, API $apiCall");
        return 'Logout 302 → Laravel /sso (one-time logout code); then index.php 302, API 401';
    });

    check('AUTH-13', 'Browser caching of the signed-in page (Back button after logout)', 'index.php is sent with Cache-Control: no-store', function () {
        $res = mainSession('faculty')->get('/index.php');
        $cache = (string) $res->header('cache-control');
        ensure(str_contains($cache, 'no-store'), "Cache-Control: $cache");
        return "Cache-Control: $cache";
    });

    check('AUTH-14', 'API sign-in, then the token after logout', 'Wrong password 401; token works; revoked token 401', function () use ($MAIN) {
        $http = new Http($MAIN);
        $bad = $http->post('/api/auth/login', ['json' => ['username' => 'faculty', 'password' => 'nope']])->status;
        $login = $http->post('/api/auth/login', ['json' => ['username' => 'faculty', 'password' => PASSWORDS['faculty']]]);
        $token = $login->json()['data']['token'] ?? '';
        $auth = ['headers' => ["Authorization: Bearer $token"]];
        $me = $http->get('/api/auth/me', $auth)->status;
        $out = $http->post('/api/auth/logout', $auth)->status;
        $after = $http->get('/api/auth/me', $auth)->status;
        ensure($bad === 401 && $login->status === 200 && $me === 200 && $out === 204 && $after === 401, "login $bad/{$login->status}, me $me, logout $out, after $after");
        return 'Wrong password 401 → login 200 → /me 200 → logout 204 → /me 401';
    });

    check('AUTH-15', 'How API tokens are stored', 'Only the SHA-256 hash is in the database', function () {
        $token = token('registrar');
        $raw = (int) scalar('SELECT COUNT(*) FROM api_tokens WHERE token_hash = ?', [$token]);
        $hashed = (int) scalar('SELECT COUNT(*) FROM api_tokens WHERE token_hash = ?', [hash('sha256', $token)]);
        ensure($raw === 0 && $hashed === 1, "raw matches $raw, hash matches $hashed");
        return 'Raw token not stored; sha256(token) found';
    });

    // ================================================================ RBAC
    section('Role-based access control (req. 7)');

    $rbacStudent = newStudentId();

    check('RBAC-01', 'Faculty tries to add, edit and delete students (API)', '403 for POST, PATCH and DELETE', function () use ($rbacStudent) {
        $post = api('faculty', 'POST', 'students', ['json' => studentPayload($rbacStudent)])->status;
        $patch = api('faculty', 'PATCH', 'students/2026-00101', ['json' => ['status' => 'Active']])->status;
        $delete = api('faculty', 'DELETE', 'students/2026-00101')->status;
        ensure([$post, $patch, $delete] === [403, 403, 403], "POST $post, PATCH $patch, DELETE $delete");
        return 'POST 403, PATCH 403, DELETE 403';
    });

    check('RBAC-02', 'Registrar adds and edits a student, then tries to delete it', 'POST 201, PATCH 200, DELETE 403', function () use ($rbacStudent) {
        $post = api('registrar', 'POST', 'students', ['json' => studentPayload($rbacStudent)])->status;
        $patch = api('registrar', 'PATCH', "students/$rbacStudent", ['json' => ['status' => 'Pending']])->status;
        $delete = api('registrar', 'DELETE', "students/$rbacStudent")->status;
        ensure([$post, $patch, $delete] === [201, 200, 403], "POST $post, PATCH $patch, DELETE $delete");
        return 'POST 201, PATCH 200, DELETE 403';
    });

    check('RBAC-03', 'Admin deletes the student', '204', function () use ($rbacStudent) {
        $delete = api('admin', 'DELETE', "students/$rbacStudent")->status;
        ensure($delete === 204, "DELETE $delete");
        return 'DELETE 204';
    });

    check('RBAC-04', 'Reports by role (API)', 'Admin 200, registrar 200, faculty 403', function () {
        $codes = array_map(fn ($u) => api($u, 'GET', 'reports/enrollment-summary')->status, ['admin', 'registrar', 'faculty']);
        ensure($codes === [200, 200, 403], 'admin/registrar/faculty: ' . implode('/', $codes));
        return 'admin 200, registrar 200, faculty 403';
    });

    check('RBAC-05', 'Changing settings by role (API)', 'Admin 200, registrar 403, faculty 403', function () use ($settingsBefore) {
        $codes = array_map(fn ($u) => api($u, 'PUT', 'settings', ['json' => $settingsBefore])->status, ['admin', 'registrar', 'faculty']);
        ensure($codes === [200, 403, 403], 'admin/registrar/faculty: ' . implode('/', $codes));
        return 'admin 200, registrar 403, faculty 403';
    });

    check('RBAC-06', 'Opening register.php (create user accounts) by URL', 'Admin 200, registrar 403, faculty 403', function () {
        $codes = array_map(fn ($u) => mainSession($u)->get('/register.php')->status, ['admin', 'registrar', 'faculty']);
        ensure($codes === [200, 403, 403], 'admin/registrar/faculty: ' . implode('/', $codes));
        return 'admin 200, registrar 403, faculty 403';
    });

    check('RBAC-07', 'Laravel: opening "Add subject" by URL', 'Admin 200, registrar 403, faculty 403', function () {
        $codes = array_map(fn ($u) => laravelSession($u)->get('/subjects/create')->status, ['admin', 'registrar', 'faculty']);
        ensure($codes === [200, 403, 403], 'admin/registrar/faculty: ' . implode('/', $codes));
        return 'admin 200, registrar 403, faculty 403';
    });

    check('RBAC-08', 'Laravel: Enrollment Records and deleting an enrollment', 'Records: registrar 200, faculty 403. Delete as registrar: 403', function () {
        $registrar = laravelSession('registrar');
        $list = $registrar->get('/enrollments')->status;
        $faculty = laravelSession('faculty')->get('/enrollments')->status;
        $id = (int) scalar('SELECT MIN(id) FROM enrollments');
        $token = $registrar->formToken('/enrollments', '_token');
        $delete = $registrar->post("/enrollments/$id", ['form' => ['_token' => $token, '_method' => 'DELETE']])->status;
        $still = (int) scalar('SELECT COUNT(*) FROM enrollments WHERE id = ?', [$id]);
        ensure($list === 200 && $faculty === 403 && $delete === 403 && $still === 1, "registrar list $list, faculty $faculty, registrar delete $delete");
        return 'registrar 200, faculty 403; registrar delete 403 (record kept)';
    });

    check('RBAC-09', 'Laravel pages while signed out', 'Redirect to the main app\'s sign-in (single sign-on), remembering the page', function () use ($LARAVEL, $MAIN) {
        $res = (new Http($LARAVEL))->get('/enrollments');
        $location = (string) $res->header('location');
        ensure($res->status === 302 && $location === "$MAIN/go-laravel.php?to=%2Fenrollments", "HTTP {$res->status} → $location");
        return '302 → main app go-laravel.php?to=/enrollments';
    });

    // ================================================================ SSO
    section('Single sign-on between the two applications');

    /** One browser for both apps: full URLs, one cookie jar. */
    $browser = static function (): Http {
        return new Http('');
    };
    $signInMain = static function (Http $http, string $user) use ($MAIN): void {
        preg_match('/name="csrf" value="([^"]+)"/', $http->get("$MAIN/login.php")->body, $m);
        $http->post("$MAIN/login.php", ['form' => ['csrf' => $m[1] ?? '', 'username' => $user, 'password' => PASSWORDS[$user]]]);
    };
    $codeFrom = static function (?string $location): string {
        preg_match('/token=([a-f0-9]{64})/', (string) $location, $m);
        return $m[1] ?? '';
    };

    check('SSO-01', 'Signed in to the main app, open a Laravel page from the menu', 'Signed in to Laravel without a second login', function () use ($browser, $signInMain, $MAIN, $LARAVEL) {
        $http = $browser();
        $signInMain($http, 'registrar');
        $hop = $http->get("$MAIN/go-laravel.php?to=%2Fsubjects");
        ensure($hop->status === 302 && str_starts_with((string) $hop->header('location'), "$LARAVEL/sso?token="), "go-laravel HTTP {$hop->status}");
        $land = $http->get((string) $hop->header('location'));
        ensure($land->status === 302 && $land->header('location') === "$LARAVEL/subjects", "sso → " . $land->header('location'));
        ensure($http->get("$LARAVEL/subjects")->status === 200, 'Subjects page not opened');
        return 'go-laravel.php 302 → Laravel /sso → /subjects 200';
    });

    check('SSO-02', 'Role after the hand-off', 'Same user and role in Laravel: faculty still cannot add subjects', function () use ($browser, $signInMain, $MAIN, $LARAVEL) {
        $http = $browser();
        $signInMain($http, 'faculty');
        $http->get("$MAIN/go-laravel.php?to=%2Fsubjects", ['follow' => true]);
        $codes = [$http->get("$LARAVEL/subjects")->status, $http->get("$LARAVEL/subjects/create")->status];
        ensure($codes === [200, 403], 'subjects/create: ' . implode('/', $codes));
        return 'Subjects 200, Add subject 403';
    });

    check('SSO-03', 'Use the same one-time code twice', 'Second use refused (sent to sign-in); no Laravel session', function () use ($browser, $signInMain, $codeFrom, $MAIN, $LARAVEL) {
        $http = $browser();
        $signInMain($http, 'admin');
        $url = (string) $http->get("$MAIN/go-laravel.php?to=%2Fofferings")->header('location');
        $http->get($url); // first use
        $other = $browser();
        $second = $other->get($url);
        ensure($second->status === 302 && $second->header('location') === "$MAIN/login.php", 'Second use → ' . $second->header('location'));
        ensure($other->get("$LARAVEL/records")->status === 302, 'Signed in with a used code');
        return 'Reused code → main login.php, not signed in';
    });

    check('SSO-04', 'Use a code after it expired', 'Refused', function () use ($browser, $signInMain, $codeFrom, $MAIN, $LARAVEL) {
        $http = $browser();
        $signInMain($http, 'admin');
        $url = (string) $http->get("$MAIN/go-laravel.php?to=%2Fofferings")->header('location');
        $stmt = $GLOBALS['db']->prepare('UPDATE sso_tokens SET expires_at = NOW() - INTERVAL 1 SECOND WHERE token_hash = ?');
        $stmt->execute([hash('sha256', $codeFrom($url))]);
        $res = $browser()->get($url);
        ensure($res->status === 302 && $res->header('location') === "$MAIN/login.php", 'Expired code → ' . $res->header('location'));
        return 'Expired code → main login.php';
    });

    check('SSO-05', 'A made-up code', 'Refused', function () use ($browser, $MAIN, $LARAVEL) {
        $res = $browser()->get("$LARAVEL/sso?token=" . bin2hex(random_bytes(32)));
        $res2 = $browser()->get("$MAIN/sso.php?token=" . bin2hex(random_bytes(32)));
        ensure($res->header('location') === "$MAIN/login.php" && $res2->header('location') === 'login.php', 'Forged code accepted');
        return 'Both apps → sign-in page';
    });

    check('SSO-06', 'How the one-time codes are stored', 'Only the SHA-256 hash; valid for 60 seconds', function () use ($browser, $signInMain, $codeFrom, $MAIN) {
        $http = $browser();
        $signInMain($http, 'admin');
        $code = $codeFrom($http->get("$MAIN/go-laravel.php?to=%2F")->header('location'));
        $raw = (int) scalar('SELECT COUNT(*) FROM sso_tokens WHERE token_hash = ?', [$code]);
        $life = (int) scalar('SELECT TIMESTAMPDIFF(SECOND, NOW(), expires_at) FROM sso_tokens WHERE token_hash = ?', [hash('sha256', $code)]);
        ensure($raw === 0 && $life > 50 && $life <= 60, "raw $raw, lifetime $life s");
        return "Raw code not stored; hash stored, expires in {$life} s";
    });

    check('SSO-07', 'Signed in to Laravel, open a main-app screen from the menu', 'Signed in to the main app on that screen', function () use ($LARAVEL, $MAIN) {
        $lv = laravelSession('registrar');
        $hop = $lv->get('/go-main?to=students');
        $location = (string) $hop->header('location');
        ensure($hop->status === 302 && str_starts_with($location, "$MAIN/sso.php?token=") && str_ends_with($location, '&to=students'), "go-main → $location");
        $main = new Http('');
        $land = $main->get($location);
        ensure($land->header('location') === 'index.php#/students', 'sso.php → ' . $land->header('location'));
        $page = $main->get("$MAIN/index.php");
        ensure($page->status === 200 && str_contains($page->body, '"role":"registrar"'), "index.php {$page->status}");
        return 'go-main 302 → main sso.php → index.php#/students, signed in as registrar';
    });

    check('SSO-08', 'Open a Laravel page while signed out of both', 'Main sign-in page, then straight back to that page', function () use ($browser, $MAIN, $LARAVEL) {
        $http = $browser();
        $start = $http->get("$LARAVEL/records", ['follow' => true]);
        ensure(str_starts_with($start->url, "$MAIN/login.php?next="), "Landed on {$start->url}");
        preg_match('/name="csrf" value="([^"]+)"/', $start->body, $m);
        preg_match('/name="next" value="([^"]+)"/', $start->body, $n);
        $done = $http->post("$MAIN/login.php", ['form' => ['csrf' => $m[1] ?? '', 'next' => html_entity_decode($n[1] ?? ''), 'username' => 'faculty', 'password' => PASSWORDS['faculty']], 'follow' => true]);
        ensure($done->url === "$LARAVEL/records" && $done->status === 200, "After sign-in: {$done->status} {$done->url}");
        return "Laravel /records → main login.php → (sign in) → Laravel /records 200";
    });

    check('SSO-09', 'Log out of the main app', 'The Laravel session ends too', function () use ($browser, $signInMain, $MAIN, $LARAVEL) {
        $http = $browser();
        $signInMain($http, 'registrar');
        $http->get("$MAIN/go-laravel.php?to=%2Frecords", ['follow' => true]);
        ensure($http->get("$LARAVEL/records")->status === 200, 'Not signed in to Laravel first');
        preg_match('/name="csrf-token" content="([^"]+)"/', $http->get("$MAIN/index.php")->body, $m);
        $out = $http->post("$MAIN/logout.php", ['form' => ['csrf' => $m[1] ?? ''], 'follow' => true]);
        $laravel = $http->get("$LARAVEL/records")->status;
        $main = $http->get("$MAIN/index.php")->status;
        ensure($out->url === "$MAIN/login.php" && $laravel === 302 && $main === 302, "end {$out->url}, Laravel $laravel, main $main");
        return 'Ends on main login.php; Laravel 302, main 302 (both signed out)';
    });

    check('SSO-10', 'Log out of the Laravel module', 'The main app session ends too', function () use ($browser, $signInMain, $MAIN, $LARAVEL) {
        $http = $browser();
        $signInMain($http, 'registrar');
        $http->get("$MAIN/go-laravel.php?to=%2Frecords", ['follow' => true]);
        preg_match('/name="_token" value="([^"]+)"/', $http->get("$LARAVEL/records")->body, $m);
        $out = $http->post("$LARAVEL/logout", ['form' => ['_token' => $m[1] ?? ''], 'follow' => true]);
        $main = $http->get("$MAIN/index.php")->status;
        $laravel = $http->get("$LARAVEL/records")->status;
        ensure($out->url === "$MAIN/login.php" && $main === 302 && $laravel === 302, "end {$out->url}, main $main, Laravel $laravel");
        return 'Ends on main login.php; main 302, Laravel 302 (both signed out)';
    });

    check('SSO-11', 'A logout code issued for a different user', 'Ignored: the signed-in user stays signed in', function () use ($browser, $signInMain, $MAIN, $LARAVEL) {
        $http = $browser();
        $signInMain($http, 'admin');
        $http->get("$MAIN/go-laravel.php?to=%2Frecords", ['follow' => true]);
        $code = bin2hex(random_bytes(32));
        $facultyId = (int) scalar("SELECT id FROM users WHERE username = 'faculty'");
        $stmt = $GLOBALS['db']->prepare("INSERT INTO sso_tokens (user_id, purpose, token_hash, expires_at) VALUES (?, 'logout', ?, NOW() + INTERVAL 60 SECOND)");
        $stmt->execute([$facultyId, hash('sha256', $code)]);
        $http->get("$LARAVEL/sso?token=$code");
        ensure($http->get("$LARAVEL/records")->status === 200, 'Admin was signed out by another user\'s code');
        return 'Admin still signed in to Laravel';
    });

    check('SSO-12', 'Hand-off pointing to another website (open redirect)', 'Only pages of the system are used', function () use ($browser, $signInMain, $MAIN, $LARAVEL) {
        $http = $browser();
        $signInMain($http, 'admin');
        $hop = (string) $http->get("$MAIN/go-laravel.php?to=" . rawurlencode('//evil.example/x'))->header('location');
        ensure(str_ends_with($hop, '&to=%2F'), "go-laravel → $hop");
        $url = preg_replace('/&to=.*$/', '&to=' . rawurlencode('https://evil.example'), $hop);
        $land = (string) $http->get($url)->header('location');
        ensure($land === "$LARAVEL/offerings", "Laravel /sso → $land");
        return 'to=//evil.example → "/"; to=https://evil.example → /offerings';
    });

    // ================================================================ CSRF
    section('CSRF protection (req. 8)');

    check('CSRF-01', 'Session-based API write without the X-CSRF-Token header', '419; nothing saved', function () {
        $id = newStudentId();
        $res = mainSession('admin')->post('/api/students', ['json' => studentPayload($id)]);
        ensure($res->status === 419 && (int) scalar('SELECT COUNT(*) FROM students WHERE student_id = ?', [$id]) === 0, "HTTP {$res->status}");
        return '419, student not created';
    });

    check('CSRF-02', 'Session-based API write with a wrong token', '419', function () {
        $res = mainSession('admin')->post('/api/students', ['json' => studentPayload(newStudentId()), 'headers' => ['X-CSRF-Token: ' . str_repeat('a', 64)]]);
        ensure($res->status === 419, "HTTP {$res->status}");
        return '419';
    });

    check('CSRF-03', 'Session-based API write with the page\'s token', '201', function () {
        $http = mainSession('admin');
        $id = newStudentId();
        $res = $http->post('/api/students', ['json' => studentPayload($id), 'headers' => ['X-CSRF-Token: ' . pageCsrf($http)]]);
        ensure($res->status === 201, "HTTP {$res->status}");
        api('admin', 'DELETE', "students/$id");
        return '201 (test student removed again)';
    });

    check('CSRF-04', 'Create-user form (register.php) posted without its token', '419; no account created', function () {
        $res = mainSession('admin')->post('/register.php', ['form' => [
            'username' => 'p6test_user', 'full_name' => 'Test', 'email' => 'p6@example.com',
            'role' => 'faculty', 'password' => 'Secret@12345', 'password_confirm' => 'Secret@12345',
        ]]);
        $created = (int) scalar("SELECT COUNT(*) FROM users WHERE username = 'p6test_user'");
        ensure($res->status === 419 && $created === 0, "HTTP {$res->status}, created $created");
        return '419, no account created';
    });

    check('CSRF-05', 'Laravel form posted without @csrf token', '419', function () {
        $res = laravelSession('admin')->post('/subjects', ['form' => ['code' => 'ZZ 999', 'name' => 'Test', 'units' => 3]]);
        ensure($res->status === 419, "HTTP {$res->status}");
        return '419 (Page Expired)';
    });

    // ================================================================ SQLi
    section('SQL injection (req. 8)');

    check('SQLI-01', "Student search for ' OR '1'='1", '200 with 0 results (not every student)', function () {
        $res = api('admin', 'GET', 'students', ['query' => ['search' => "' OR '1'='1"]]);
        $total = $res->json()['meta']['total'] ?? null;
        ensure($res->status === 200 && $total === 0, "HTTP {$res->status}, total " . var_export($total, true));
        return '200, total 0';
    });

    check('SQLI-02', "Sign in as admin' -- and as admin with password ' OR '1'='1", 'Both rejected', function () use ($MAIN) {
        foreach ([["admin' -- ", 'x'], ['admin', "' OR '1'='1"]] as [$user, $pass]) {
            $http = new Http($MAIN);
            $csrf = $http->formToken('/login.php', 'csrf');
            $http->post('/login.php', ['form' => ['csrf' => $csrf, 'username' => $user, 'password' => $pass]]);
            ensure($http->get('/index.php')->status === 302, "Signed in with $user / $pass");
        }
        return 'Both: "Invalid username or password."';
    });

    check('SQLI-03', 'API sign-in with an injected username', '401', function () use ($MAIN) {
        $res = (new Http($MAIN))->post('/api/auth/login', ['json' => ['username' => "' OR 1=1 -- ", 'password' => 'x']]);
        ensure($res->status === 401, "HTTP {$res->status}");
        return '401';
    });

    check('SQLI-04', "Student ID 2026-00101' OR '1'='1 in the URL", '404', function () {
        $res = api('admin', 'GET', 'students/' . rawurlencode("2026-00101' OR '1'='1"));
        ensure($res->status === 404, "HTTP {$res->status}");
        return '404';
    });

    check('SQLI-05', 'sort=name; DROP TABLE students', '422; students table intact', function () {
        $before = (int) scalar('SELECT COUNT(*) FROM students');
        $res = api('admin', 'GET', 'students', ['query' => ['sort' => 'name; DROP TABLE students']]);
        ensure($res->status === 422 && (int) scalar('SELECT COUNT(*) FROM students') === $before, "HTTP {$res->status}");
        return "422 (not an allowed sort); still $before students";
    });

    check('SQLI-06', 'Report filter program=x\' OR 1=1 --', '422', function () {
        $res = api('admin', 'GET', 'reports/student-master-list', ['query' => ['program' => "x' OR 1=1 -- "]]);
        ensure($res->status === 422, "HTTP {$res->status}");
        return '422 "Unknown program."';
    });

    check('SQLI-07', "Laravel subject search for ' OR 1=1 --", '200, no subjects listed, no SQL error', function () {
        $res = laravelSession('faculty')->get('/subjects', ['query' => ['q' => "' OR 1=1 -- "]]);
        ensure($res->status === 200 && !preg_match('/SQLSTATE|syntax error/i', $res->body) && !str_contains($res->body, 'GE 101'), "HTTP {$res->status}");
        return '200, empty list';
    });

    // ================================================================ XSS
    section('Cross-site scripting');

    $xssId = newStudentId();
    $payload = '<script>alert(1)</script>';

    check('XSS-01', 'Save a student whose first name is <script>alert(1)</script>', 'Stored as plain text; returned unchanged as JSON', function () use ($xssId, $payload) {
        $post = api('registrar', 'POST', 'students', ['json' => studentPayload($xssId, ['firstName' => $payload])])->status;
        $res = api('faculty', 'GET', "students/$xssId");
        ensure($post === 201 && ($res->json()['data']['firstName'] ?? '') === $payload && str_contains((string) $res->header('content-type'), 'application/json'), "POST $post");
        return '201; GET returns the same text with Content-Type application/json';
    });

    check('XSS-02', 'Laravel Student Records page for that student', 'Shown escaped (&lt;script&gt;), never as a tag', function () use ($xssId, $payload) {
        $res = laravelSession('faculty')->get("/records/$xssId");
        ensure($res->status === 200 && str_contains($res->body, '&lt;script&gt;alert(1)&lt;/script&gt;') && !str_contains($res->body, $payload), "HTTP {$res->status}");
        return 'Rendered as &lt;script&gt;alert(1)&lt;/script&gt;';
    });

    // ================================================================ VALIDATION
    section('Validation and error responses (req. 9)');

    check('VAL-01', 'Create a student with an empty body {}', '422 with a message for each required field', function () {
        $res = api('admin', 'POST', 'students', ['json' => new stdClass()]);
        $fields = array_keys($res->json()['errors'] ?? []);
        ensure($res->status === 422 && count($fields) >= 10, "HTTP {$res->status}, fields: " . implode(',', $fields));
        return '422, errors for ' . count($fields) . ' fields';
    });

    check('VAL-02', 'Bad email, 10-digit phone, future birth date, bad ID format, unknown program', '422 naming each of those fields', function () {
        $res = api('admin', 'POST', 'students', ['json' => studentPayload('26-1', [
            'email' => 'not-an-email', 'contact' => '0917 000 000', 'dateOfBirth' => '2099-01-01', 'program' => 'BS Nursing',
        ])]);
        $fields = array_keys($res->json()['errors'] ?? []);
        $want = ['id', 'email', 'contact', 'dateOfBirth', 'program'];
        ensure($res->status === 422 && array_diff($want, $fields) === [], "HTTP {$res->status}, fields: " . implode(',', $fields));
        return '422: ' . implode(', ', $want);
    });

    check('VAL-03', 'Create a student with an ID that already exists', '409 Conflict', function () {
        $res = api('admin', 'POST', 'students', ['json' => studentPayload('2026-00101')]);
        ensure($res->status === 409, "HTTP {$res->status}");
        return '409';
    });

    check('VAL-04', 'Body sent as text/plain, then malformed JSON', '415, then 400', function () {
        $plain = api('admin', 'POST', 'students', ['raw' => 'id=1', 'headers' => ['Content-Type: text/plain']])->status;
        $broken = api('admin', 'POST', 'students', ['raw' => '{"id": ', 'headers' => ['Content-Type: application/json']])->status;
        ensure($plain === 415 && $broken === 400, "text/plain $plain, malformed $broken");
        return '415, 400';
    });

    check('VAL-05', 'Laravel: enroll with no subjects selected', 'Form shows "Select at least one subject."; nothing saved', function () {
        $lv = laravelSession('registrar');
        $before = (int) scalar('SELECT COUNT(*) FROM enrollments');
        $res = enrollInLaravel($lv, '2026-00108', []);
        ensure(str_contains($res->body, 'Select at least one subject.') && (int) scalar('SELECT COUNT(*) FROM enrollments') === $before, "HTTP {$res->status}");
        return 'Message shown, no enrollment saved';
    });

    // ================================================================ API
    section('REST API behaviour (req. 9)');

    check('API-01', 'Unknown URL, and a known URL with the wrong method', '404; 405 with an Allow header', function () {
        $missing = api('admin', 'GET', 'courses')->status;
        $wrong = api('admin', 'PATCH', 'students');
        ensure($missing === 404 && $wrong->status === 405 && str_contains((string) $wrong->header('allow'), 'GET'), "404? $missing, 405? {$wrong->status}");
        return '404; 405 Allow: ' . $wrong->header('allow');
    });

    check('API-02', 'Response envelope on success and on error', 'Both have success, data, message, errors, meta', function () {
        $keys = ['success', 'data', 'message', 'errors', 'meta'];
        $ok = array_keys(api('admin', 'GET', 'dashboard')->json());
        $err = array_keys(api('faculty', 'GET', 'reports')->json());
        ensure($ok === $keys && $err === $keys, 'Keys: ' . implode(',', $ok) . ' / ' . implode(',', $err));
        return 'Same five keys on 200 and 403';
    });

    check('API-03', 'POST, PUT (full replace) and PATCH (partial) on one student', '201 + Location; PUT 200; PATCH 200 changes only status', function () {
        $id = newStudentId();
        $post = api('admin', 'POST', 'students', ['json' => studentPayload($id)]);
        $put = api('admin', 'PUT', "students/$id", ['json' => array_diff_key(studentPayload($id, ['year' => '2nd Year']), ['id' => 1])]);
        $patch = api('admin', 'PATCH', "students/$id", ['json' => ['status' => 'Pending']]);
        $data = $patch->json()['data'] ?? [];
        ensure($post->status === 201 && str_ends_with((string) $post->header('location'), "/api/students/$id")
            && $put->status === 200 && $patch->status === 200 && $data['year'] === '2nd Year' && $data['status'] === 'Pending',
            "POST {$post->status}, PUT {$put->status}, PATCH {$patch->status}");
        api('admin', 'DELETE', "students/$id");
        return 'POST 201 (Location set), PUT 200, PATCH 200; year kept, status changed';
    });

    check('API-04', 'Pagination: perPage=3, page=2', '3 rows; meta has page, perPage, total, totalPages', function () {
        $res = api('faculty', 'GET', 'students', ['query' => ['perPage' => 3, 'page' => 2]]);
        $meta = $res->json()['meta'] ?? [];
        ensure(count($res->json()['data'] ?? []) <= 3 && $meta['page'] === 2 && $meta['perPage'] === 3 && $meta['totalPages'] === (int) ceil($meta['total'] / 3), json_encode($meta));
        return "page 2 of {$meta['totalPages']} ({$meta['total']} students)";
    });

    // ================================================================ INTEGRATION
    section('Integration: main app ↔ Laravel module ↔ reports (req. 10, 11, 12)');

    $intId = newStudentId();
    $fullId = newStudentId();
    $summaryBefore = report('enrollment-summary', ['academic_year' => TERM_YEAR, 'semester' => TERM_SEM]);
    $dashBefore = api('admin', 'GET', 'dashboard')->json()['data']['term']['enrolled'];

    check('INT-01', 'A student added in the main app, seen from Laravel', 'Listed in Laravel Student Records and the Enroll Student form', function () use ($intId) {
        api('registrar', 'POST', 'students', ['json' => studentPayload($intId, ['firstName' => 'Integration', 'lastName' => 'Tester'])]);
        $lv = laravelSession('registrar');
        $records = $lv->get('/records', ['query' => ['q' => $intId]])->body;
        $form = $lv->get('/enrollments/create')->body;
        ensure(str_contains($records, $intId) && str_contains($form, $intId), 'Not found in Laravel');
        return 'Found on /records and in the /enrollments/create student list';
    });

    check('INT-02', 'Enroll that student in Laravel (registrar) in GE 101-A and CC 101-A', 'Saved; enrolled_by = registrar; 2 sections linked', function () use ($intId) {
        $res = enrollInLaravel(laravelSession('registrar'), $intId, [offeringId('GE 101', 'A'), offeringId('CC 101', 'A')]);
        $row = (array) $GLOBALS['db']->query("SELECT e.id, u.username, (SELECT COUNT(*) FROM enrollment_offering eo WHERE eo.enrollment_id = e.id) n
            FROM enrollments e LEFT JOIN users u ON u.id = e.enrolled_by WHERE e.student_id = '$intId'")->fetch_assoc();
        ensure(($row['username'] ?? '') === 'registrar' && (int) ($row['n'] ?? 0) === 2 && str_contains($res->body, 'is enrolled for'), "HTTP {$res->status}");
        return "Enrollment #{$row['id']} saved by registrar with 2 sections";
    });

    check('INT-03', 'Enroll the same student again in the same term', 'Rejected: "already enrolled for that term"', function () use ($intId) {
        $res = enrollInLaravel(laravelSession('registrar'), $intId, [offeringId('GE 102', 'A')]);
        $count = (int) scalar('SELECT COUNT(*) FROM enrollments WHERE student_id = ?', [$intId]);
        ensure(str_contains($res->body, 'already enrolled for that term') && $count === 1, "count $count");
        return 'Message shown; still 1 enrollment';
    });

    check('INT-04', 'Enroll another student in a full section (IT 202-B, 3 of 3)', 'Rejected: "is full"', function () use ($fullId) {
        api('registrar', 'POST', 'students', ['json' => studentPayload($fullId)]);
        $res = enrollInLaravel(laravelSession('registrar'), $fullId, [offeringId('IT 202', 'B')]);
        $count = (int) scalar('SELECT COUNT(*) FROM enrollments WHERE student_id = ?', [$fullId]);
        ensure(str_contains($res->body, 'is full') && $count === 0, "count $count");
        return '"IT 202-B is full (3 of 3 slots taken)"; nothing saved';
    });

    check('INT-05', 'Reports and dashboard after the new enrollment', 'Enrollment Summary +1, master list shows it, dashboard "Enrolled this term" +1', function () use ($intId, $summaryBefore, $dashBefore) {
        $after = report('enrollment-summary', ['academic_year' => TERM_YEAR, 'semester' => TERM_SEM]);
        $list = report('student-master-list', ['academic_year' => TERM_YEAR, 'semester' => TERM_SEM]);
        $row = array_values(array_filter($list['sections'][0]['rows'], fn ($r) => $r['studentId'] === $intId))[0] ?? null;
        $dash = api('admin', 'GET', 'dashboard')->json()['data']['term']['enrolled'];
        $b = summaryValue($summaryBefore, 'Enrollment records');
        $a = summaryValue($after, 'Enrollment records');
        ensure($a === $b + 1 && $row !== null && str_contains($row['enrollment'], 'Active') && $dash === $dashBefore + 1, "records {$b} → {$a}, dashboard {$dashBefore} → {$dash}");
        return "Records {$b} → {$a}; master list: \"{$row['enrollment']}\"; dashboard {$dashBefore} → {$dash}";
    });

    check('INT-06', 'Delete the student in the main app', 'Their enrollment is deleted with them (ON DELETE CASCADE)', function () use ($intId) {
        $status = api('admin', 'DELETE', "students/$intId")->status;
        $left = (int) scalar('SELECT COUNT(*) FROM enrollments WHERE student_id = ?', [$intId]);
        ensure($status === 204 && $left === 0, "DELETE $status, enrollments left $left");
        return 'DELETE 204; 0 enrollments left';
    });

    check('INT-07', 'Change the default term in Settings', 'Dashboard term cards follow the new term', function () use ($settingsBefore) {
        api('admin', 'PUT', 'settings', ['json' => ['semester' => '2nd Semester'] + $settingsBefore]);
        $term = api('faculty', 'GET', 'dashboard')->json()['data']['term'];
        api('admin', 'PUT', 'settings', ['json' => $settingsBefore]);
        ensure($term['semester'] === '2nd Semester', 'Dashboard term: ' . $term['semester']);
        return "Dashboard term → {$term['semester']} (setting restored)";
    });

    // ================================================================ REPORTS
    section('Report accuracy (req. 11)');

    $term = ['academic_year' => TERM_YEAR, 'semester' => TERM_SEM];

    check('RPT-01', 'Enrollment Summary vs SQL', 'Records and enrolled counts equal COUNT(*) on enrollments', function () use ($term) {
        $r = report('enrollment-summary', $term);
        $sqlAll = (int) scalar('SELECT COUNT(*) FROM enrollments WHERE academic_year = ? AND semester = ?', [TERM_YEAR, TERM_SEM]);
        $sqlIn = (int) scalar("SELECT COUNT(*) FROM enrollments WHERE academic_year = ? AND semester = ? AND status <> 'Dropped'", [TERM_YEAR, TERM_SEM]);
        ensure(summaryValue($r, 'Enrollment records') === $sqlAll && summaryValue($r, 'Enrolled (not dropped)') === $sqlIn, 'Mismatch');
        return "records $sqlAll, enrolled $sqlIn (report = SQL)";
    });

    check('RPT-02', 'Enrollment by Program vs SQL', 'Per-program enrolled counts equal a GROUP BY query', function () use ($term) {
        $rows = report('enrollment-by-program', $term)['sections'][0]['rows'];
        $res = $GLOBALS['db']->query("SELECT p.name, COUNT(*) n FROM enrollments e JOIN students s ON s.student_id = e.student_id
            JOIN programs p ON p.id = s.program_id WHERE e.academic_year = '" . TERM_YEAR . "' AND e.semester = '" . TERM_SEM . "'
            AND e.status <> 'Dropped' GROUP BY p.name");
        $sql = array_column($res->fetch_all(MYSQLI_ASSOC), 'n', 'name');
        foreach ($rows as $row) {
            ensure($row['enrolled'] === (int) ($sql[$row['program']] ?? 0), "{$row['program']}: report {$row['enrolled']}, SQL " . ($sql[$row['program']] ?? 0));
        }
        return implode(', ', array_map(fn ($r) => "{$r['program']} {$r['enrolled']}", $rows));
    });

    check('RPT-03', 'Section Utilization vs SQL', 'Seats taken equal the number of non-dropped section enrollments', function () use ($term) {
        $r = report('section-utilization', $term);
        $sql = (int) scalar("SELECT COUNT(*) FROM enrollment_offering eo JOIN enrollments e ON e.id = eo.enrollment_id
            JOIN course_offerings o ON o.id = eo.course_offering_id
            WHERE e.status <> 'Dropped' AND o.academic_year = ? AND o.semester = ?", [TERM_YEAR, TERM_SEM]);
        $taken = (int) explode(' ', (string) summaryValue($r, 'Seats taken'))[0];
        ensure($taken === $sql, "report $taken, SQL $sql");
        return "seats taken $taken (report = SQL)";
    });

    check('RPT-04', 'Student Demographics and Master List vs SQL', 'Student totals equal COUNT(*) on students', function () {
        $sql = (int) scalar('SELECT COUNT(*) FROM students');
        $demo = summaryValue(report('student-demographics'), 'Students');
        $list = count(report('student-master-list')['sections'][0]['rows']);
        ensure($demo === $sql && $list === $sql, "demographics $demo, master list $list, SQL $sql");
        return "$sql students in both reports (= SQL)";
    });

    check('RPT-05', 'Export a report as CSV', 'text/csv download with a UTF-8 BOM and one line per student', function () {
        $res = api('admin', 'GET', 'reports/student-master-list', ['query' => ['format' => 'csv']]);
        $lines = array_filter(explode("\n", trim($res->body)), fn ($l) => preg_match('/^"?\d{4}-\d{5}/', $l));
        $sql = (int) scalar('SELECT COUNT(*) FROM students');
        ensure(str_contains((string) $res->header('content-type'), 'text/csv') && str_starts_with($res->body, "\xEF\xBB\xBF")
            && str_contains((string) $res->header('content-disposition'), 'attachment') && count($lines) === $sql, 'lines ' . count($lines));
        return 'text/csv, attachment, BOM, ' . count($lines) . ' student lines';
    });

    check('RPT-06', 'CSV export of a name starting with = (formula injection)', 'Cell is prefixed with \' so Excel does not run it', function () use ($fullId) {
        api('admin', 'PATCH', "students/$fullId", ['json' => ['lastName' => '=HYPERLINK("http://example.com")']]);
        $csv = api('admin', 'GET', 'reports/student-master-list', ['query' => ['format' => 'csv']])->body;
        ensure(str_contains($csv, "\"'=HYPERLINK"), 'Cell not prefixed');
        return "Exported as '=HYPERLINK(…)";
    });
} finally {
    // ------------------------------------------------------------------ cleanup
    foreach ($createdStudents as $id) {
        $stmt = $db->prepare('DELETE FROM students WHERE student_id = ?');
        $stmt->execute([$id]); // enrollments and details go with it (ON DELETE CASCADE)
    }
    $db->query("DELETE FROM users WHERE username LIKE 'p6test%'");
    $stmt = $db->prepare('DELETE FROM login_attempts WHERE id > ?');
    $stmt->execute([$firstAttemptId]);
    $stmt = $db->prepare('DELETE FROM sso_tokens WHERE id > ?');
    $stmt->execute([$firstSsoId]);
    try {
        api('admin', 'PUT', 'settings', ['json' => $settingsBefore]);
    } catch (Throwable) {
    }
    foreach ($tokens as $token) {
        (new Http($MAIN))->post('/api/auth/logout', ['headers' => ["Authorization: Bearer $token"]]);
    }
}

// --------------------------------------------------------------------- report
$passed = count(array_filter($results, fn ($r) => $r['ok']));
$failed = count($results) - $passed;
echo "\n$passed passed, $failed failed (" . count($results) . " tests)\n";

if ($markdown !== null) {
    $out = "| ID | Test | Expected | Actual | Result |\n|---|---|---|---|---|\n";
    $current = null;
    foreach ($results as $r) {
        if ($r['section'] !== $current) {
            $current = $r['section'];
            $out .= "| | **{$current}** | | | |\n";
        }
        $cells = array_map(fn ($v) => str_replace(['|', "\n"], ['\\|', ' '], $v), [$r['id'], $r['title'], $r['expected'], $r['actual']]);
        $out .= '| ' . implode(' | ', $cells) . ' | ' . ($r['ok'] ? 'Pass' : '**Fail**') . " |\n";
    }
    file_put_contents($markdown, $out);
    echo "Markdown results written to $markdown\n";
}

exit($failed === 0 ? 0 : 1);
