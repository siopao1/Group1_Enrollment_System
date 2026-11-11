import { escapeHtml } from "../core/dom.js";

const TONES = {
  Active: "success",
  Confirmed: "success",
  Open: "success",
  Completed: "info",
  Pending: "warning",
  Dropped: "danger",
  Full: "danger",
  Low: "warning",
  Normal: "success",
  Inactive: "neutral",
};

export function badge(status) {
  const tone = TONES[status] || "neutral";
  return `<span class="badge ${tone}">${escapeHtml(status)}</span>`;
}
