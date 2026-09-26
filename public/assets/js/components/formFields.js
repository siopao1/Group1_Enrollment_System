import { escapeHtml } from "../core/dom.js";

function requiredMark(required) {
  return required ? ` <span class="required">*</span>` : "";
}

export function field({ label, name, placeholder = "", type = "text", value = "", required = false, inputmode = "" }) {
  return `<div class="field">
  <label for="f-${escapeHtml(name)}">${escapeHtml(label)}${requiredMark(required)}</label>
  <input class="input" id="f-${escapeHtml(name)}" name="${escapeHtml(name)}" type="${escapeHtml(type)}"
    placeholder="${escapeHtml(placeholder)}" value="${escapeHtml(value)}"${required ? " required" : ""}${
    inputmode ? ` inputmode="${escapeHtml(inputmode)}"` : ""
  }>
</div>`;
}

export function textareaField({ label, name, placeholder = "", value = "", required = false, full = true }) {
  return `<div class="field"${full ? ` style="grid-column:1/-1"` : ""}>
  <label for="f-${escapeHtml(name)}">${escapeHtml(label)}${requiredMark(required)}</label>
  <textarea class="input" id="f-${escapeHtml(name)}" name="${escapeHtml(name)}"
    placeholder="${escapeHtml(placeholder)}"${required ? " required" : ""}>${escapeHtml(value)}</textarea>
</div>`;
}

export function selectField({ label, name, options, value = "", placeholder = "", required = false }) {
  const items = placeholder ? [{ value: "", label: placeholder }, ...normalise(options)] : normalise(options);
  return `<div class="field">
  <label for="f-${escapeHtml(name)}">${escapeHtml(label)}${requiredMark(required)}</label>
    <div class="select-wrap">
    <select class="select" id="f-${escapeHtml(name)}" name="${escapeHtml(name)}"${required ? " required" : ""} aria-haspopup="listbox" aria-expanded="false">
    ${items
      .map(
        (option) =>
          `<option value="${escapeHtml(option.value)}"${option.value === value ? " selected" : ""}>${escapeHtml(
            option.label
          )}</option>`
      )
      .join("")}
  </select>
  <div class="select-menu" role="listbox" aria-hidden="true">
    ${items
      .map(
        (option) =>
          `<button type="button" class="select-menu-item" role="option" data-value="${escapeHtml(option.value)}">${escapeHtml(
            option.label
          )}</button>`
      )
      .join("")}
  </div>
    </div>
</div>`;
}

export function infoItem(label, value) {
  return `<div class="info-item"><span>${escapeHtml(label)}</span><strong>${escapeHtml(value)}</strong></div>`;
}

function normalise(options) {
  return (options || []).map((option) =>
    typeof option === "string" ? { value: option, label: option } : option
  );
}

export function filterSelect({ name, options, placeholder, value = "", width = "" }) {
  const items = placeholder ? [{ value: "", label: placeholder }, ...normalise(options)] : normalise(options);
  return `<div class="select-wrap"${width ? ` style="width:${escapeHtml(width)}"` : ""}>
  <select class="select" name="${escapeHtml(name)}" aria-label="${escapeHtml(placeholder || name)}" aria-haspopup="listbox" aria-expanded="false">
  ${items
    .map(
      (option) =>
        `<option value="${escapeHtml(option.value)}"${option.value === value ? " selected" : ""}>${escapeHtml(
          option.label
        )}</option>`
    )
    .join("")}
  </select>
<div class="select-menu" role="listbox" aria-hidden="true">
  ${items
    .map(
      (option) =>
        `<button type="button" class="select-menu-item" role="option" data-value="${escapeHtml(option.value)}">${escapeHtml(
          option.label
        )}</button>`
    )
    .join("")}
</div>
  </div>`;
}
