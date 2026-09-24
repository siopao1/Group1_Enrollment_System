import { escapeHtml } from "../core/dom.js";

/**
 * Pagination bar driven by real meta from the API instead of the fixed
 * "1 2 3 4 5" buttons the mockup used.
 */
export function pagination({ page, perPage, total }) {
  const pages = Math.max(1, Math.ceil(total / perPage));
  const from = total === 0 ? 0 : (page - 1) * perPage + 1;
  const to = Math.min(page * perPage, total);

  return `<div class="pagination">
  <span>Showing ${from}–${to} of ${total.toLocaleString()} records</span>
  <div class="pager">
    <button data-page="${page - 1}"${page <= 1 ? " disabled" : ""} aria-label="Previous page">‹</button>
    <span class="pager-current">${page}</span>
    <button data-page="${page + 1}"${page >= pages ? " disabled" : ""} aria-label="Next page">›</button>
  </div>
</div>`;
}

export function paginationLabel(text) {
  return `<span>${escapeHtml(text)}</span>`;
}
