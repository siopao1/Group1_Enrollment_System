<?php

/**
 * User registration — administrators only.
 *
 * In a registrar's office, accounts are issued, not self-created, so only an
 * admin can open this page and the admin chooses the new account's role
 * (least privilege). The password is stored only as password_hash() output.
 */

require __DIR__ . '/../backend/config/database.php';
require __DIR__ . '/../backend/auth/Auth.php';

Auth::start();
$admin = Auth::requireRole(['admin']); // signed out → login.php; other roles → 403

$roleLabels = [
    'faculty'   => 'Faculty',
    'registrar' => 'Registrar',
    'admin'     => 'Administrator',
];

$errors = [];
$old = ['username' => '', 'full_name' => '', 'email' => '', 'role' => 'faculty'];
$created = isset($_GET['created']) ? (string) $_GET['created'] : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::verifyCsrf($_POST['csrf'] ?? null)) {
        Auth::sendCsrfFailedStatus();
        exit('Invalid or missing CSRF token. Go back, refresh the page and try again.');
    }

    $old = [
        'username'  => strtolower(trim((string) ($_POST['username'] ?? ''))),
        'full_name' => trim((string) ($_POST['full_name'] ?? '')),
        'email'     => trim((string) ($_POST['email'] ?? '')),
        'role'      => (string) ($_POST['role'] ?? ''),
    ];
    $password = (string) ($_POST['password'] ?? '');
    $confirm  = (string) ($_POST['password_confirm'] ?? '');

    if (!preg_match('/^[a-z0-9_.]{4,50}$/', $old['username'])) {
        $errors['username'] = 'Username must be 4–50 characters: letters, numbers, dots or underscores.';
    }
    if ($old['full_name'] === '' || mb_strlen($old['full_name']) > 100) {
        $errors['full_name'] = 'Full name is required (up to 100 characters).';
    }
    if (filter_var($old['email'], FILTER_VALIDATE_EMAIL) === false || strlen($old['email']) > 120) {
        $errors['email'] = 'Enter a valid email address.';
    }
    if (strlen($password) < 8) {
        $errors['password'] = 'Password must be at least 8 characters.';
    } elseif (strlen($password) > 72) {
        $errors['password'] = 'Password must be at most 72 characters.'; // bcrypt's input limit
    } elseif ($password !== $confirm) {
        $errors['password_confirm'] = 'Passwords do not match.';
    }
    if (!in_array($old['role'], Auth::ROLES, true)) {
        $errors['role'] = 'Choose a valid role.';
    }

    if ($errors === []) {
        $conn = getDbConnection();
        $hash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $conn->prepare(
            'INSERT INTO users (username, full_name, email, password_hash, role) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->bind_param('sssss', $old['username'], $old['full_name'], $old['email'], $hash, $old['role']);

        try {
            $stmt->execute();
            // Post/Redirect/Get: refreshing the page will not submit the form again.
            header('Location: register.php?created=' . urlencode($old['username']));
            exit;
        } catch (mysqli_sql_exception $e) {
            if ($e->getCode() !== 1062) { // 1062 = duplicate entry on a UNIQUE column
                throw $e;
            }
            $errors['username'] = 'That username or email is already registered.';
        }
    }
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function fieldError(array $errors, string $field): string
{
    return isset($errors[$field]) ? '<p class="field-error">' . e($errors[$field]) . '</p>' : '';
}

$csrf = Auth::csrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Create User Account — University — Enrollment &amp; Records</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/lucide-static@0.468.0/font/lucide.css">
  <link rel="stylesheet" href="assets/css/app.css">
</head>
<body class="auth-body">
  <main class="auth-shell auth-shell-wide">
    <div class="auth-card card">
      <div class="auth-brand">
        <div class="brand-mark">U</div>
        <div>
          <strong>UNIVERSITY</strong>
          <span>Enrollment &amp; Records</span>
        </div>
      </div>

      <h1>Create user account</h1>
      <p class="auth-lead">
        Signed in as <?= e($admin['name']) ?> (Administrator). The new user signs in
        with the username and password you set here.
      </p>

      <?php if ($created !== ''): ?>
        <p class="auth-success" role="status">Account “<?= e($created) ?>” was created.</p>
      <?php endif; ?>
      <?php if ($errors !== []): ?>
        <p class="auth-error show" role="alert">Please fix the highlighted fields.</p>
      <?php endif; ?>

      <form method="post" action="register.php" novalidate>
        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">

        <div class="form-grid">
          <div class="field">
            <label for="username">Username</label>
            <input class="input<?= isset($errors['username']) ? ' is-invalid' : '' ?>" id="username" name="username"
                   autocomplete="off" required maxlength="50" value="<?= e($old['username']) ?>">
            <?= fieldError($errors, 'username') ?>
          </div>
          <div class="field">
            <label for="role">Role</label>
            <select class="input<?= isset($errors['role']) ? ' is-invalid' : '' ?>" id="role" name="role" required>
              <?php foreach ($roleLabels as $value => $label): ?>
                <option value="<?= e($value) ?>" <?= $old['role'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
              <?php endforeach; ?>
            </select>
            <?= fieldError($errors, 'role') ?>
          </div>
        </div>

        <div class="field">
          <label for="full_name">Full name</label>
          <input class="input<?= isset($errors['full_name']) ? ' is-invalid' : '' ?>" id="full_name" name="full_name"
                 required maxlength="100" value="<?= e($old['full_name']) ?>">
          <?= fieldError($errors, 'full_name') ?>
        </div>

        <div class="field">
          <label for="email">Email</label>
          <input class="input<?= isset($errors['email']) ? ' is-invalid' : '' ?>" id="email" name="email" type="email"
                 required maxlength="120" value="<?= e($old['email']) ?>">
          <?= fieldError($errors, 'email') ?>
        </div>

        <div class="form-grid">
          <div class="field">
            <label for="password">Password</label>
            <input class="input<?= isset($errors['password']) ? ' is-invalid' : '' ?>" id="password" name="password"
                   type="password" autocomplete="new-password" required minlength="8" maxlength="72">
            <?= fieldError($errors, 'password') ?>
          </div>
          <div class="field">
            <label for="password_confirm">Confirm password</label>
            <input class="input<?= isset($errors['password_confirm']) ? ' is-invalid' : '' ?>" id="password_confirm"
                   name="password_confirm" type="password" autocomplete="new-password" required minlength="8" maxlength="72">
            <?= fieldError($errors, 'password_confirm') ?>
          </div>
        </div>

        <button class="btn btn-primary auth-submit" type="submit">Create Account</button>
      </form>

      <p class="auth-note"><a href="index.php#/settings">← Back to Settings</a></p>
    </div>
  </main>
</body>
</html>
