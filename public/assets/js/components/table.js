import { escapeHtml } from "../core/dom.js";

/** Renders `<thead>` + `<tbody>` with a colspan-aware empty row. */
export function dataTable({ columns, rows, emptyHtml }) {
  const body = rows.length
    ? rows.join("")
    : `<tr><td colspan="${columns.length}">${emptyHtml || ""}</td></tr>`;

  return `<div class="table-wrap"><table class="data-table">
  <thead><tr>${columns.map((c) => `<th>${escapeHtml(c)}</th>`).join("")}</tr></thead>
  <tbody>${body}</tbody>
</table></div>`;
}

/** Search + filter toolbar above a table. */
export function tableTools({ searchId, searchPlaceholder = "Search...", filters = "" }) {
  const search = searchId
    ? `<div class="search-box"><i class="icon icon-search"></i>
        <input id="${escapeHtml(searchId)}" placeholder="${escapeHtml(searchPlaceholder)}" aria-label="${escapeHtml(
        searchPlaceholder
      )}"></div>`
    : "";
  return `<div class="table-tools">${search}<div class="filters">${filters}</div></div>`;
}

/** Row action buttons; `actions` is a list of { icon, title, attr }. */
export function rowActions(actions) {
  return `<div class="row-actions">${actions
    .map(
      (a) =>
        `<button class="icon-action" title="${escapeHtml(a.title)}" ${a.attr || ""}><i class="icon icon-${escapeHtml(
          a.icon
        )}"></i></button>`
    )
    .join("")}</div>`;
}
