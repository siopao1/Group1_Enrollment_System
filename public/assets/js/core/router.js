/**
 * Hash router.
 *
 * The old app kept `currentPage` in a global and swapped `innerHTML` from a
 * `pages` object, so pages could not be linked, guarded or cleaned up. Routes
 * are now declarative, lazily imported, permission-aware and given a proper
 * lifecycle (render -> mount -> destroy).
 *
 * A page module exports a default object:
 *   { async render(ctx) -> html, mount(ctx) -> optional cleanup fn }
 */
import { auth } from "./auth.js";
import { ApiError } from "./apiError.js";
import { loadingState, errorState, unauthorizedState } from "../components/states.js";

function normalise(hash) {
  return (hash || "").replace(/^#\/?/, "").replace(/\/+$/, "").split("?")[0];
}

function matchRoute(routes, path) {
  const segments = path === "" ? [] : path.split("/");

  for (const route of routes) {
    const pattern = route.path === "" ? [] : route.path.split("/");
    if (pattern.length !== segments.length) continue;

    const params = {};
    const matched = pattern.every((part, index) => {
      if (part.startsWith(":")) {
        params[part.slice(1)] = decodeURIComponent(segments[index]);
        return true;
      }
      return part === segments[index];
    });

    if (matched) return { route, params };
  }
  return null;
}

export function createRouter({ routes, outlet, loginUrl = "login.php", onNavigated }) {
  let cleanup = null;
  let renderToken = 0;

  async function renderRoute(match) {
    const token = ++renderToken;
    const { route, params } = match;
    const ctx = { params, route, navigate };

    if (typeof cleanup === "function") cleanup();
    cleanup = null;

    if (route.permission && !auth.can(route.permission)) {
      outlet.innerHTML = unauthorizedState(route.title);
      outlet.querySelector("[data-link]")?.addEventListener("click", () => navigate("dashboard"));
      onNavigated?.(route, params);
      return;
    }

    outlet.innerHTML = loadingState(route.title);
    onNavigated?.(route, params);

    try {
      const module = await route.load();
      const page = module.default ?? module;
      const html = await page.render(ctx);

      if (token !== renderToken) return; // a newer navigation won the race
      outlet.innerHTML = html;
      cleanup = page.mount?.(ctx) ?? null;
    } catch (error) {
      if (token !== renderToken) return;

      if (error instanceof ApiError && error.isUnauthorized) {
        auth.clearSession();
        window.location.href = loginUrl;
        return;
      }

      outlet.innerHTML = errorState(error.message, { retry: true });
      outlet.querySelector("[data-retry]")?.addEventListener("click", () => renderRoute(match));
    }
  }

  function resolve() {
    const path = normalise(window.location.hash);
    const match = matchRoute(routes, path) ?? matchRoute(routes, "");
    if (match) renderRoute(match);
  }

  function navigate(path) {
    const target = `#/${String(path).replace(/^#?\/?/, "")}`;
    if (window.location.hash === target) resolve();
    else window.location.hash = target;
  }

  return {
    start() {
      window.addEventListener("hashchange", resolve);
      resolve();
    },
    navigate,
    current: () => normalise(window.location.hash),
  };
}
