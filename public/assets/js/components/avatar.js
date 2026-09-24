import { escapeHtml } from "../core/dom.js";

export function initials(name) {
  return String(name || "")
    .trim()
    .split(/\s+/)
    .map((part) => part[0] || "")
    .slice(0, 2)
    .join("")
    .toUpperCase();
}

export function avatar(name, size = "") {
  return `<div class="avatar ${size}">${escapeHtml(initials(name))}</div>`;
}
