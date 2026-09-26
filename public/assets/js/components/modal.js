

import { qs, escapeHtml } from "../core/dom.js";

let backdrop = null;
let container = null;
let onCloseCallback = null;
let scrollLock = null;

function lockBodyScroll() {
  if (scrollLock) return;

  const body = document.body;
  const scrollY = window.scrollY;
  const scrollbarWidth = window.innerWidth - document.documentElement.clientWidth;
  scrollLock = {
    scrollY,
    position: body.style.position,
    top: body.style.top,
    left: body.style.left,
    right: body.style.right,
    width: body.style.width,
    paddingRight: body.style.paddingRight,
  };

  body.style.position = "fixed";
  body.style.top = `-${scrollY}px`;
  body.style.left = "0";
  body.style.right = "0";
  body.style.width = "100%";
  body.style.paddingRight = scrollbarWidth ? `${scrollbarWidth}px` : "";
}

function unlockBodyScroll() {
  if (!scrollLock) return;

  const body = document.body;
  const { scrollY, position, top, left, right, width, paddingRight } = scrollLock;
  body.style.position = position;
  body.style.top = top;
  body.style.left = left;
  body.style.right = right;
  body.style.width = width;
  body.style.paddingRight = paddingRight;
  scrollLock = null;
  window.scrollTo(0, scrollY);
}

function ensureElements() {
  if (backdrop) return;
  backdrop = qs("#modalBackdrop");
  container = qs("#modal");

  backdrop.addEventListener("click", (event) => {
    if (event.target === backdrop || event.target.closest("[data-close-modal]")) closeModal();
  });

  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape" && backdrop.classList.contains("open")) closeModal();
  });
}

export function openModal({ title, body, footer = "", size = "wide", onMount, onClose }) {
  ensureElements();
  onCloseCallback = onClose ?? null;
  const isLogout = title === "Logout Confirmation";

  container.innerHTML = `<div class="modal modal-${size}${isLogout ? " modal-confirm-logout" : ""}">
  <div class="modal-head">
  ${isLogout ? '<div class="modal-symbol"><i class="icon icon-log-out"></i></div>' : ""}
  <h3>${escapeHtml(title)}</h3>
  <button class="icon-btn" data-close-modal aria-label="Close dialog"><i class="icon icon-x"></i></button>
</div>
<div class="modal-body">${body}</div>
<div class="modal-foot">${footer}</div>
</div>`;

  container.classList.toggle("modal-host-compact", size === "compact");
  container.classList.toggle("modal-host-confirm", size === "confirm" || isLogout);
  container.classList.toggle("modal-host-logout", isLogout);
  lockBodyScroll();
  document.body.classList.add("modal-open");
  backdrop.classList.add("open");
  onMount?.(container);
  container.querySelector("input, select, textarea, button")?.focus();
  container.scrollLeft = 0;
}

export function closeModal() {
  if (!backdrop) return;
  backdrop.classList.remove("open");
  container.classList.remove("modal-host-compact", "modal-host-confirm", "modal-host-logout");
  document.body.classList.remove("modal-open");
  unlockBodyScroll();
  container.innerHTML = "";
  onCloseCallback?.();
  onCloseCallback = null;
}

export function confirmModal({ title, message, confirmLabel = "Confirm", tone = "danger", onConfirm }) {
  openModal({
    title,
    size: "confirm",
    body: message,
    footer: `<button class="btn" data-close-modal>Cancel</button>
      <button class="btn btn-${tone}" data-confirm>${escapeHtml(confirmLabel)}</button>`,
    onMount(root) {
      root.querySelector("[data-confirm]").addEventListener("click", async (event) => {
        const button = event.currentTarget;
        button.disabled = true;
        try {
          await onConfirm();
          closeModal();
        } finally {
          button.disabled = false;
        }
      });
    },
  });
}
