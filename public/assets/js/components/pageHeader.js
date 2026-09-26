import { escapeHtml } from "../core/dom.js";

export function pageHeader(title, description, actions = "") {
  return `<div class="page-intro">
  <div><h2>${escapeHtml(title)}</h2><p>${escapeHtml(description)}</p></div>
  <div class="actions">${actions}</div>
</div>`;
}
