import { escapeHtml } from "../core/dom.js";

/** Skeleton shown while a page awaits its data. */
export function loadingState(title = "") {
  return `<div class="card"><div class="card-body">
  <div class="empty">
    <div class="empty-icon"><i class="icon icon-loader"></i></div>
    <h3>Loading${title ? ` ${escapeHtml(title)}` : ""}…</h3>
    <p>Fetching the latest records from the server.</p>
  </div>
</div></div>`;
}

/** Shown when a request fails. Includes an optional retry button. */
export function errorState(message, { retry = false } = {}) {
  return `<div class="card"><div class="card-body">
  <div class="empty">
    <div class="empty-icon"><i class="icon icon-triangle-alert"></i></div>
    <h3>Something went wrong.</h3>
    <p>${escapeHtml(message || "The request could not be completed.")}</p>
    ${retry ? `<button class="btn btn-small" data-retry>Try Again</button>` : ""}
  </div>
</div></div>`;
}

/** Shown inside a table body when a filtered result set is empty. */
export function emptyState({
  title = "No records found.",
  description = "Try adjusting your search or filters.",
  action = "",
} = {}) {
  return `<div class="empty">
  <div class="empty-icon"><i class="icon icon-search-x"></i></div>
  <h3>${escapeHtml(title)}</h3>
  <p>${escapeHtml(description)}</p>
  ${action}
</div>`;
}

/** Shown when the signed-in role may not view a route. */
export function unauthorizedState(title = "this page") {
  return `<div class="card"><div class="card-body">
  <div class="empty">
    <div class="empty-icon"><i class="icon icon-lock"></i></div>
    <h3>You do not have access to ${escapeHtml(title)}.</h3>
    <p>Your account role does not include this permission. Contact the system administrator if you believe this is a mistake.</p>
    <button class="btn btn-small" data-link="dashboard">Back to Dashboard</button>
  </div>
</div></div>`;
}
