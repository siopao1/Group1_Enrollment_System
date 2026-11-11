

import { appConfig } from "./config/app.config.js";
import { useMockTransport } from "./core/apiClient.js";
import { auth } from "./core/auth.js";
import { createRouter } from "./core/router.js";
import { createShell } from "./shell.js";
import { routes } from "./routes.js";

const LOGIN_URL = "login.php";

function closeSelectMenus(except = null) {
  document.querySelectorAll(".select-wrap.is-open").forEach((wrap) => {
    if (wrap !== except) {
      wrap.classList.remove("is-open");
      wrap.querySelector(".select")?.setAttribute("aria-expanded", "false");
      wrap.querySelector(".select-menu")?.setAttribute("aria-hidden", "true");
    }
  });
}

function syncSelectMenu(wrap) {
  const select = wrap.querySelector(".select");
  const value = select?.value;
  wrap.querySelectorAll(".select-menu-item").forEach((item) => {
    item.classList.toggle("is-selected", item.dataset.value === value);
  });
}

function installSelectMenus() {
  document.addEventListener("pointerdown", (event) => {
    const select = event.target.closest(".select");
    if (!select) {
      if (!event.target.closest(".select-menu")) closeSelectMenus();
      return;
    }

    event.preventDefault();
    const wrap = select.closest(".select-wrap");
    const willOpen = !wrap.classList.contains("is-open");
    closeSelectMenus(willOpen ? wrap : null);
    wrap.classList.toggle("is-open", willOpen);
    select.setAttribute("aria-expanded", String(willOpen));
    wrap.querySelector(".select-menu")?.setAttribute("aria-hidden", String(!willOpen));
    if (willOpen) syncSelectMenu(wrap);
  });

  document.addEventListener("click", (event) => {
    const item = event.target.closest(".select-menu-item");
    if (!item) return;
    const wrap = item.closest(".select-wrap");
    const select = wrap.querySelector(".select");
    if (select.value !== item.dataset.value) {
      select.value = item.dataset.value;
      select.dispatchEvent(new Event("change", { bubbles: true }));
    }
    closeSelectMenus();
    select.focus();
  });

  document.addEventListener("keydown", (event) => {
    const select = event.target.closest(".select");
    if (!select) return;
    if (event.key === "Escape") {
      closeSelectMenus();
    } else if (event.key === "Enter" || event.key === " ") {
      event.preventDefault();
      select.dispatchEvent(new PointerEvent("pointerdown", { bubbles: true }));
    }
  });
}

async function boot() {
  installSelectMenus();
  
  // Screens not yet backed by the database (subjects, offerings, enrollment,
  // records, reports, settings) still read from the browser mock.
  if (appConfig.useMockApi) {
    const { mockTransport } = await import("./mock/mockServer.js");
    useMockTransport(mockTransport);
  }

  // index.php only renders for a signed-in user and passes that user here.
  const user = window.__USER__ ?? null;
  if (!user) {
    window.location.replace(LOGIN_URL);
    return;
  }
  auth.setSession({ user, token: null });

  const outlet = document.querySelector("#content");
  let shell;

  const router = createRouter({
    routes,
    outlet,
    loginUrl: LOGIN_URL,
    onNavigated: (route) => shell?.setActive(route),
  });

  shell = createShell({ navigate: router.navigate });
  router.start();
}

boot();
