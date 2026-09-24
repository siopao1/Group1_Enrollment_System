<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>University — Enrollment &amp; Records</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/lucide-static@0.468.0/font/lucide.css">
  <link rel="stylesheet" href="assets/css/app.css?v=20260919-2">
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

  <script type="module" src="assets/js/main.js?v=20260919-2"></script>
</body>
</html>
