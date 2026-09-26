

import { navigation } from "./config/navigation.js";
import { auth } from "./core/auth.js";
import { qs, qsa, on, escapeHtml } from "./core/dom.js";
import { initials } from "./components/avatar.js";
import { confirmModal } from "./components/modal.js?v=20260919-2";
import { authService } from "./services/authService.js";

function navButton(item) {
  return `<button class="nav-item" data-route="${escapeHtml(item.route)}" data-name="${escapeHtml(item.route)}"
  data-tooltip="${escapeHtml(item.label)}" aria-label="${escapeHtml(item.label)}">
  <i class="icon icon-${escapeHtml(item.icon)}"></i><span class="nav-label-text">${escapeHtml(item.label)}</span>
</button>`;
}

export function createShell({ navigate }) {
  const navPanel = qs("#navPanel");
  const overlay = qs("#overlay");
  const menuButton = qs("#menuBtn");
  const profileButton = qs("#profileMenu");

  function renderNav() {
    navPanel.innerHTML = `<div class="drawer-brand">
      <div class="brand-mark">U</div>
      <div><strong>UNIVERSITY</strong><span>Enrollment &amp; Records</span></div>
    </div>${navigation
      .filter((item) => auth.can(item.permission))
      .map(navButton)
      .join("")}<button class="drawer-logout" type="button" aria-label="Log out">
      <i class="icon icon-log-out"></i><span>Log Out</span>
    </button>`;
  }

  function renderProfile() {
    const user = auth.user();
    profileButton.innerHTML = `<div class="avatar">${escapeHtml(initials(user?.name ?? "Guest"))}</div>
      <span>${escapeHtml(auth.roleLabel())}</span><i class="icon icon-chevron-down"></i>`;
  }

  function closeMobileNav() {
    navPanel.classList.remove("open");
    overlay.classList.remove("show");
    menuButton.classList.remove("is-hidden");
    menuButton.setAttribute("aria-expanded", "false");
  }

  function setActive(route) {
    qsa(".nav-item", navPanel).forEach((button) =>
      button.classList.toggle("active", button.dataset.name === (route.name ?? route.path))
    );
    qs("#pageTitle").textContent = route.title;
    qs("#breadcrumb").textContent = route.breadcrumb;
    document.title = `${route.title} — Enrollment & Records`;
    window.scrollTo({ top: 0, behavior: "auto" });
    closeMobileNav();
  }

  renderNav();
  renderProfile();
  auth.onChange(() => {
    renderNav();
    renderProfile();
  });

  on(navPanel, "click", (event) => {
    const button = event.target.closest(".nav-item");
    if (button) navigate(button.dataset.route);
  });

  on(navPanel, "click", (event) => {
    if (!event.target.closest(".drawer-logout")) return;
    confirmModal({
      title: "Logout Confirmation",
      message: `<p>Are you sure you want to log out of the ${escapeHtml(auth.roleLabel())} session?</p>`,
      confirmLabel: "Log Out",
      async onConfirm() {
        await authService.logout();
        window.location.href = "login.php";
      },
    });
  });

  on(menuButton, "click", () => {
    const isOpen = navPanel.classList.toggle("open");
    overlay.classList.toggle("show", isOpen);
    menuButton.classList.toggle("is-hidden", isOpen);
    menuButton.setAttribute("aria-expanded", String(isOpen));
  });

  on(overlay, "click", closeMobileNav);

  on(qs("#globalSearch"), "keydown", (event) => {
    if (event.key !== "Enter") return;
    const term = event.target.value.trim();
    if (!term) return;
    sessionStorage.setItem("pendingStudentSearch", term);
    event.target.value = "";
    navigate("students");
  });

  on(document, "keydown", (event) => {
    if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === "k") {
      event.preventDefault();
      qs("#globalSearch").focus();
    }
  });

  on(profileButton, "click", () =>
    confirmModal({
      title: "Logout Confirmation",
      message: `<p>Are you sure you want to log out of the ${escapeHtml(auth.roleLabel())} session?</p>`,
      confirmLabel: "Log Out",
      async onConfirm() {
        await authService.logout();
        window.location.href = "login.php";
      },
    })
  );

  return { setActive };
}

export default createShell;
