<?php

require __DIR__ . '/../backend/auth/Auth.php';
require_once __DIR__ . '/../backend/config/database.php';
require_once __DIR__ . '/../backend/repositories/SettingsRepository.php';

Auth::start();
$user = Auth::requireLogin(); // not signed in → login.php

// System preferences (default term, rows per page) for the JavaScript pages.
$settings = (new SettingsRepository(getDbConnection()))->all();

// Where the Laravel module runs (LARAVEL_URL in .env), for the menu links.
$config = ['laravelUrl' => rtrim((string) envValue('LARAVEL_URL', 'http://localhost:8001'), '/')];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <!-- Sent back in the X-CSRF-Token header on every request that changes data. -->
  <meta name="csrf-token" content="<?= htmlspecialchars(Auth::csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
  <title>University — Enrollment &amp; Records</title>
  <script>
    // The signed-in user, from the server session. The JavaScript only uses it
    // to choose what to show; the server re-checks the role on every request.
    window.__USER__ = <?= json_encode($user, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    window.__SETTINGS__ = <?= json_encode($settings, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    window.__CONFIG__ = <?= json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES) ?>;
  </script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/lucide-static@0.468.0/font/lucide.css">
  <link rel="stylesheet" href="assets/css/app.css?v=20261111-4">
</head>
<body>
  <div class="app-shell">
    <header class="topnav">
      <div class="topnav-inner">
        <button class="icon-btn menu-btn" id="menuBtn" aria-label="Open navigation" aria-expanded="false"><i class="icon icon-menu"></i></button>

        <div class="brand">
          <div class="brand-mark">U</div>
          <div>
            <strong>UNIVERSITY</strong>
            <span>Enrollment &amp; Records</span>
          </div>
        </div>

        <div class="nav-pill-wrap">
          <!-- Filled by js/shell.js according to the signed-in role. -->
          <nav class="nav-pill" id="navPanel" aria-label="Primary"></nav>
        </div>

      </div>
    </header>

    <main class="main">
      <header class="topbar">
        <div class="topbar-left">
          <div>
            <div class="breadcrumb" id="breadcrumb">Home / Dashboard</div>
            <h1 id="pageTitle">Dashboard</h1>
          </div>
        </div>
        <div class="topbar-actions">
          <label class="global-search">
            <i class="icon icon-search"></i>
            <input id="globalSearch" placeholder="Search" aria-label="Global search">
          </label>
          <button class="icon-btn notification-btn" title="Notifications"><i class="icon icon-bell"></i><span></span></button>
          <button class="profile-menu" id="profileMenu" aria-label="Account menu"></button>
        </div>
      </header>

      <section class="content" id="content"></section>
    </main>
  </div>

  <div class="overlay" id="overlay"></div>
  <div id="toastRegion" class="toast-region" aria-live="polite"></div>

  <div class="modal-backdrop" id="modalBackdrop" role="dialog" aria-modal="true">
    <div class="modal" id="modal"></div>
  </div>

  <script type="module" src="assets/js/main.js?v=20261111-4"></script>
</body>
</html>
