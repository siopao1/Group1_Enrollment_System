<?php

require __DIR__ . '/../backend/config/database.php';
require __DIR__ . '/../backend/auth/Auth.php';

Auth::start();

// Where to go after signing in. Only a hand-off to a Laravel module page is
// accepted (single sign-on, see go-laravel.php); anything else → index.php.
$next = (string) ($_POST['next'] ?? $_GET['next'] ?? '');
if (preg_match('#^go-laravel\.php\?to=/[A-Za-z0-9/_-]*$#', $next) !== 1) {
    $next = '';
}
$afterLogin = $next !== '' ? $next : 'index.php';

if (Auth::user() !== null) {
    header('Location: ' . $afterLogin);
    exit;
}

$error = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if (!Auth::verifyCsrf($_POST['csrf'] ?? null)) {
        $error = 'Your session expired. Please try again.';
    } elseif ($username === '' || $password === '') {
        $error = 'Enter your username and password.';
    } else {
        $conn = getDbConnection();

        if (Auth::tooManyAttempts($conn, $username)) {
            http_response_code(429);
            $error = 'Too many failed sign-in attempts. Please wait 15 minutes and try again.';
        } else {
            $user = Auth::attempt($conn, $username, $password);

            if ($user !== null) {
                Auth::login($user);
                header('Location: ' . $afterLogin);
                exit;
            }

            // One message for both cases, so attackers cannot tell which usernames exist.
            $error = 'Invalid username or password.';
        }
    }
}

$csrf = Auth::csrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sign In — University — Enrollment &amp; Records</title>
  <link rel="icon" href="favicon.ico">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/lucide-static@0.468.0/font/lucide.css">
  <link rel="stylesheet" href="assets/css/app.css">
</head>
<body class="auth-body">
  <main class="auth-shell">
    <div class="auth-card card">
      <div class="auth-brand">
        <div class="brand-mark" aria-hidden="true"></div>
        <div>
          <strong>UNIVERSITY</strong>
          <span>Enrollment &amp; Records</span>
        </div>
      </div>

      <h1>Sign in</h1>
      <p class="auth-lead">Use the account issued by the registrar's office.</p>

      <form method="post" action="login.php" novalidate>
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
        <?php if ($next !== ''): ?>
          <input type="hidden" name="next" value="<?= htmlspecialchars($next, ENT_QUOTES, 'UTF-8') ?>">
        <?php endif; ?>

        <div class="field">
          <label for="username">Username</label>
          <input class="input" id="username" name="username" autocomplete="username" required maxlength="50"
                 value="<?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?>" <?= $username === '' ? 'autofocus' : '' ?>>
        </div>
        <div class="field">
          <label for="password">Password</label>
          <input class="input" id="password" name="password" type="password" autocomplete="current-password" required
                 <?= $username !== '' ? 'autofocus' : '' ?>>
        </div>

        <?php if ($error !== ''): ?>
          <p class="auth-error show" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>

        <button class="btn btn-primary auth-submit" type="submit">Sign In</button>
      </form>

      <p class="auth-note">
        Accounts are created by an administrator. Contact the registrar's office
        if you need access.
      </p>
    </div>
  </main>
</body>
</html>
