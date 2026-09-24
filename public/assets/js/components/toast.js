import { qs, escapeHtml } from "../core/dom.js";

const TITLES = {
  success: "Success",
  error: "Unable to complete action",
  info: "Notice",
};

export function toast(message, type = "success", timeout = 3000) {
  const region = qs("#toastRegion");
  if (!region) return;

  const element = document.createElement("div");
  element.className = `toast ${type}`;
  element.setAttribute("role", type === "error" ? "alert" : "status");
  element.innerHTML = `<strong>${escapeHtml(TITLES[type] || TITLES.info)}</strong><span>${escapeHtml(message)}</span>`;

  region.appendChild(element);
  setTimeout(() => element.remove(), timeout);
}

export const notify = {
  success: (message) => toast(message, "success"),
  error: (message) => toast(message, "error"),
  info: (message) => toast(message, "info"),
};
