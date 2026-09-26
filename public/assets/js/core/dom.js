

export const qs = (selector, scope = document) => scope.querySelector(selector);
export const qsa = (selector, scope = document) => [...scope.querySelectorAll(selector)];

export function on(target, type, handler, options) {
  if (!target) return () => {};
  target.addEventListener(type, handler, options);
  return () => target.removeEventListener(type, handler, options);
}

export function delegate(root, selector, type, handler) {
  const listener = (event) => {
    const match = event.target.closest(selector);
    if (match && root.contains(match)) handler(event, match);
  };
  root.addEventListener(type, listener);
  return () => root.removeEventListener(type, listener);
}

export function escapeHtml(value) {
  if (value === null || value === undefined) return "";
  return String(value)
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#39;");
}

export function formValues(form) {
  return Object.fromEntries(new FormData(form).entries());
}

export function bindPhoneInput(input) {
  if (!input) return () => {};
  const reformat = () => {
    const digits = input.value.replace(/\D/g, "").slice(0, 11);
    const groups = [digits.slice(0, 4), digits.slice(4, 7), digits.slice(7, 11)].filter(Boolean);
    input.value = groups.join(" ");
  };
  return on(input, "input", reformat);
}
