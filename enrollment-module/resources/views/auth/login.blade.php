<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sign In — Enrollment Module</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/lucide-static@0.468.0/font/lucide.css">
  <link rel="stylesheet" href="{{ asset('css/app.css') }}">
  <link rel="stylesheet" href="{{ asset('css/laravel.css') }}">
</head>
<body class="auth-body">
  <main class="auth-shell">
    <div class="auth-card card">
      <div class="auth-brand">
        <div class="brand-mark">U</div>
        <div>
          <strong>UNIVERSITY</strong>
          <span>Enrollment &amp; Records — Subjects &amp; Enrollment</span>
        </div>
      </div>

      <h1>Sign in</h1>
      <p class="auth-lead">Use the same username and password as the main system.</p>

      <form method="POST" action="{{ route('login') }}" novalidate>
        @csrf

        <div class="field">
          <label for="username">Username</label>
          <input class="input" id="username" name="username" value="{{ old('username') }}"
                 autocomplete="username" required maxlength="50" autofocus>
        </div>
        <div class="field">
          <label for="password">Password</label>
          <input class="input" id="password" name="password" type="password" autocomplete="current-password" required>
        </div>

        @if ($errors->any())
          <p class="auth-error show" role="alert">{{ $errors->first() }}</p>
        @endif

        <button class="btn btn-primary auth-submit" type="submit">Sign In</button>
      </form>

      <p class="auth-note">
        This is the Laravel part of the system (subjects, course offerings and
        enrollment). <a href="{{ config('app.main_app_url') }}/login.php">Go to the main system</a>.
      </p>
    </div>
  </main>
</body>
</html>
