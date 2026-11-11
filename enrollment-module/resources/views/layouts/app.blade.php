@php
    $user = auth()->user();
    $main = config('app.main_app_url');

    // Same menu as the main app (public/assets/js/config/navigation.js). Items
    // owned by the main app link to it ('url'); the rest are routes in this
    // Laravel module ('route'). 'roles' => null means every signed-in role.
    $nav = [
        ['label' => 'Dashboard', 'icon' => 'layout-dashboard', 'url' => "{$main}/index.php#/dashboard", 'roles' => null],
        ['label' => 'Students', 'icon' => 'users', 'url' => "{$main}/index.php#/students", 'roles' => null],
        ['label' => 'Subjects', 'icon' => 'book-open', 'route' => 'subjects.index', 'match' => 'subjects.*', 'roles' => null],
        ['label' => 'Course Offerings', 'icon' => 'calendar-days', 'route' => 'offerings.index', 'match' => 'offerings.*', 'roles' => null],
        ['label' => 'Enroll Student', 'icon' => 'clipboard-list', 'route' => 'enrollments.create', 'match' => 'enrollments.create', 'roles' => ['admin', 'registrar']],
        ['label' => 'Enrollment Records', 'icon' => 'file-check', 'route' => 'enrollments.index', 'match' => ['enrollments.index', 'enrollments.show', 'enrollments.edit'], 'roles' => ['admin', 'registrar']],
        ['label' => 'Student Records', 'icon' => 'graduation-cap', 'route' => 'records.index', 'match' => 'records.*', 'roles' => null],
        ['label' => 'Reports & Analytics', 'icon' => 'chart-no-axes-combined', 'url' => "{$main}/index.php#/reports", 'roles' => ['admin', 'registrar']],
        ['label' => 'Settings', 'icon' => 'settings', 'url' => "{$main}/index.php#/settings", 'roles' => ['admin']],
    ];

    foreach ($nav as $i => $item) {
        if (isset($item['route'])) {
            if (!Route::has($item['route'])) {
                unset($nav[$i]); // page not built yet
                continue;
            }
            $nav[$i]['url'] = route($item['route']);
        }
        $nav[$i]['active'] = isset($item['match']) && request()->routeIs(...(array) $item['match']);
    }
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>@yield('title') — Enrollment &amp; Records</title>
  <link rel="icon" href="{{ asset('favicon.ico') }}">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/lucide-static@0.468.0/font/lucide.css">
  <link rel="stylesheet" href="{{ asset('css/app.css') }}">
  <link rel="stylesheet" href="{{ asset('css/laravel.css') }}">
</head>
<body>
  <div class="app-shell">
    <header class="topnav">
      <div class="topnav-inner">
        <button class="icon-btn menu-btn" id="menuBtn" type="button" aria-label="Open navigation" aria-expanded="false"><i class="icon icon-menu"></i></button>

        <div class="brand">
          <div class="brand-mark" aria-hidden="true"></div>
          <div>
            <strong>UNIVERSITY</strong>
            <span>Enrollment &amp; Records</span>
          </div>
        </div>

        <div class="nav-pill-wrap">
          <nav class="nav-pill" id="navPanel" aria-label="Primary">
            <div class="drawer-brand">
              <div class="brand-mark" aria-hidden="true"></div>
              <div><strong>UNIVERSITY</strong><span>Enrollment &amp; Records</span></div>
            </div>
            @foreach ($nav as $item)
              @if ($item['roles'] === null || $user->hasRole(...$item['roles']))
                <a class="nav-item{{ $item['active'] ? ' active' : '' }}" href="{{ $item['url'] }}"
                   data-tooltip="{{ $item['label'] }}" aria-label="{{ $item['label'] }}">
                  <i class="icon icon-{{ $item['icon'] }}"></i><span class="nav-label-text">{{ $item['label'] }}</span>
                </a>
              @endif
            @endforeach
            <form method="POST" action="{{ route('logout') }}" class="drawer-logout-form">
              @csrf
              <button class="drawer-logout" type="submit" aria-label="Log out">
                <i class="icon icon-log-out"></i><span>Log Out</span>
              </button>
            </form>
          </nav>
        </div>
      </div>
    </header>

    <main class="main">
      <header class="topbar">
        <div class="topbar-left">
          <div>
            <div class="breadcrumb">Home / @yield('breadcrumb', View::getSection('title'))</div>
            <h1>@yield('title')</h1>
          </div>
        </div>
        <div class="topbar-actions">
          <span class="module-tag" title="This section is built with Laravel">Laravel module</span>
          <div class="profile-menu" aria-label="Signed-in user">
            <div class="avatar">{{ Str::upper(Str::substr($user->full_name, 0, 1)) }}</div>
            <span>{{ $user->full_name }} · {{ $user->roleLabel() }}</span>
          </div>
          <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button class="btn btn-small" type="submit"><i class="icon icon-log-out"></i>Log Out</button>
          </form>
        </div>
      </header>

      <section class="content">
        @if (session('status'))
          <div class="alert alert-success" role="status"><i class="icon icon-circle-check"></i>{{ session('status') }}</div>
        @endif
        @if (session('error'))
          <div class="alert alert-danger" role="alert"><i class="icon icon-triangle-alert"></i>{{ session('error') }}</div>
        @endif
        @if ($errors->any())
          <div class="alert alert-danger" role="alert">
            <i class="icon icon-triangle-alert"></i>
            <div>
              <strong>Please fix the following:</strong>
              <ul>@foreach ($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul>
            </div>
          </div>
        @endif

        @yield('content')
      </section>
    </main>
  </div>

  <div class="overlay" id="overlay"></div>

  <script>
    // Mobile menu toggle (same behavior as the main app's shell.js).
    (function () {
      const panel = document.getElementById('navPanel');
      const overlay = document.getElementById('overlay');
      const button = document.getElementById('menuBtn');
      function toggle(open) {
        panel.classList.toggle('open', open);
        overlay.classList.toggle('show', open);
        button.setAttribute('aria-expanded', String(open));
      }
      button.addEventListener('click', () => toggle(!panel.classList.contains('open')));
      overlay.addEventListener('click', () => toggle(false));
    })();
  </script>
  @stack('scripts')
</body>
</html>
