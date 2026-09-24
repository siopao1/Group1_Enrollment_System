<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sign In — University — Enrollment &amp; Records</title>
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
        <div class="brand-mark">U</div>
        <div>
          <strong>UNIVERSITY</strong>
          <span>Enrollment &amp; Records</span>
        </div>
      </div>

      <h1>Sign in</h1>
      <p class="auth-lead">Use the account issued by the registrar's office.</p>

      <form id="loginForm" novalidate>
        <div class="field">
          <label for="username">Username</label>
          <input class="input" id="username" name="username" autocomplete="username" required>
        </div>
        <div class="field">
          <label for="password">Password</label>
          <input class="input" id="password" name="password" type="password" autocomplete="current-password" required>
        </div>

        <p class="auth-error" id="loginError" role="alert"></p>

        <button class="btn btn-primary auth-submit" type="submit">Sign In</button>
      </form>

      <p class="auth-note">
        Demo accounts while the database is being set up:
        <strong>admin</strong>, <strong>registrar</strong> or <strong>faculty</strong>,
        password <strong>password</strong>.
      </p>
    </div>
  </main>

  <script type="module" src="assets/js/login.js"></script>
</body>
</html>
