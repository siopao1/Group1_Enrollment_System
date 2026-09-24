import { escapeHtml } from "../core/dom.js";

export function statCard({ label, value, icon, trend = "", direction = "up" }) {
  return `<div class="stat-card">
  <div class="stat-top"><span class="stat-label">${escapeHtml(label)}</span>
    <div class="stat-icon"><i class="icon icon-${escapeHtml(icon)}"></i></div>
  </div>
  <div class="stat-number">${escapeHtml(value)}</div>
  <div class="trend ${direction}">${escapeHtml(trend)}</div>
</div>`;
}
