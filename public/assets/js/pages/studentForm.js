
import { pageHeader } from "../components/pageHeader.js";
import { field, selectField, textareaField } from "../components/formFields.js";
import { notify } from "../components/toast.js";
import { qs, qsa, on, delegate, formValues, bindPhoneInput } from "../core/dom.js";
import { appConfig } from "../config/app.config.js";
import {
  PROGRAMS,
  YEAR_LEVELS,
  STUDENT_STATUSES,
  GENDERS,
  CIVIL_STATUSES,
} from "../config/options.js";
import { studentService } from "../services/studentService.js";

export function studentFormMarkup() {
  return `<form id="studentForm" novalidate>
  <div class="form-section"><h3>Personal Information</h3><div class="form-grid">
    ${field({ label: "Student ID", name: "studentId", placeholder: "2026-00126", required: true })}
    ${field({ label: "First Name", name: "firstName", placeholder: "Enter first name", required: true })}
    ${field({ label: "Middle Name", name: "middleName", placeholder: "Enter middle name" })}
    ${field({ label: "Last Name", name: "lastName", placeholder: "Enter last name", required: true })}
    ${field({ label: "Suffix", name: "suffix", placeholder: "Optional" })}
    ${field({ label: "Date of Birth", name: "dob", type: "date", required: true })}
    ${selectField({ label: "Gender", name: "gender", options: GENDERS, placeholder: "Select gender", required: true })}
    ${selectField({ label: "Civil Status", name: "civilStatus", options: CIVIL_STATUSES, placeholder: "Select civil status", required: true })}
  </div></div>

  <div class="form-section"><h3>Contact Information</h3><div class="form-grid">
    ${field({ label: "Email Address", name: "email", type: "email", placeholder: "student@email.com", required: true })}
    ${field({ label: "Phone Number", name: "phone", placeholder: "09XX XXX XXXX", required: true, inputmode: "numeric" })}
    ${textareaField({ label: "Address", name: "address", placeholder: "Complete address", required: true })}
  </div></div>

  <div class="form-section"><h3>Academic Information</h3><div class="form-grid">
    ${selectField({ label: "Program", name: "program", options: PROGRAMS, placeholder: "Select program", required: true })}
    ${selectField({ label: "Year Level", name: "year", options: YEAR_LEVELS, placeholder: "Select year level", required: true })}
    ${field({ label: "Academic Year", name: "academicYear", value: appConfig.defaults.academicYear })}
    ${selectField({ label: "Student Status", name: "status", options: STUDENT_STATUSES, value: "Active" })}
  </div></div>

  <div class="form-section"><h3>Emergency Contact</h3><div class="form-grid">
    ${field({ label: "Contact Name", name: "emergencyName", placeholder: "Full name" })}
    ${field({ label: "Relationship", name: "relationship", placeholder: "e.g. Parent" })}
    ${field({ label: "Contact Number", name: "emergencyNumber", placeholder: "09XX XXX XXXX", inputmode: "numeric" })}
  </div></div>

  <div class="form-actions">
    <button type="button" class="btn" data-link="students">Cancel</button>
    <button type="submit" class="btn btn-primary"><i class="icon icon-save"></i>Save Student</button>
  </div>
</form>`;
}

function fieldNameFor(errorKey) {
  return errorKey === "id" ? "studentId" : errorKey;
}

function clearFieldErrors(form) {
  qsa(".is-invalid", form).forEach((el) => el.classList.remove("is-invalid"));
}

function highlightInvalidFields(form, errors) {
  const names = Object.keys(errors).map(fieldNameFor);
  names.forEach((name) => form.querySelector(`[name="${name}"]`)?.classList.add("is-invalid"));

  const target = form.querySelector(`[name="${names[0]}"]`);
  target?.scrollIntoView({ behavior: "smooth", block: "center" });
  target?.focus({ preventScroll: true });
}

export function mountStudentForm(form, { onSuccess, onCancel } = {}) {
  const offSubmit = on(form, "submit", async (event) => {
    event.preventDefault();
    clearFieldErrors(form);
    const submitButton = form.querySelector("[type=submit]");
    submitButton.disabled = true;

    try {
      const { message } = await studentService.create(formValues(form));
      notify.success(message);
      form.reset();
      onSuccess?.();
    } catch (error) {
      notify.error(error.message);
      if (error.errors) highlightInvalidFields(form, error.errors);
    } finally {
      submitButton.disabled = false;
    }
  });

  const offClearOnInput = delegate(form, ".is-invalid", "input", (_e, el) => el.classList.remove("is-invalid"));
  const offClearOnChange = delegate(form, ".is-invalid", "change", (_e, el) => el.classList.remove("is-invalid"));

  const offPhone = bindPhoneInput(form.querySelector('[name="phone"]'));
  const offEmergencyNumber = bindPhoneInput(form.querySelector('[name="emergencyNumber"]'));

  const offCancel = on(form.querySelector('[data-link="students"]'), "click", () => onCancel?.());
  return () => {
    offSubmit();
    offClearOnInput();
    offClearOnChange();
    offPhone();
    offEmergencyNumber();
    offCancel();
  };
}

export default {
  async render() {
    return (
      pageHeader("Add New Student", "Create a new student profile and academic record.") +
      `<div class="card form-card">${studentFormMarkup()}</div>`
    );
  },

  mount({ navigate }) {
    const form = qs("#studentForm");
    return mountStudentForm(form, { onSuccess: () => navigate("students"), onCancel: () => navigate("students") });
  },
};
