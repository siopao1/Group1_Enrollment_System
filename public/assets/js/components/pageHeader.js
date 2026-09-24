import { escapeHtml } from "../core/dom.js";

/** Title + description + optional action buttons shown at the top of a page. */
export function pageHeader(title, description, actions = "") {
  return `<div class="page-intro">
  <div><h2>${escapeHtml(title)}</h2><p>${escapeHtml(description)}</p></div>
  <div class="actions">${actions}</div>
</div>`;
}
