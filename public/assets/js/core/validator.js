/**
 * Client-side validation — for fast feedback only.
 *
 * The PHP layer runs the same rules again (app/Core/Validator.php) because a
 * browser check can be bypassed trivially.
 */
export const rules = {
  required: (value) => (String(value ?? "").trim() !== "" ? null : "This field is required."),
  email: (value) =>
    !value || /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value) ? null : "Enter a valid email address.",
  phone: (value) =>
    !value || /^\d{11}$/.test(String(value).replace(/\s/g, ""))
      ? null
      : "Phone number must be exactly 11 digits.",
  numeric: (value) => (!value || !Number.isNaN(Number(value)) ? null : "Enter a number."),
  studentId: (value) => (/^\d{4}-\d{5}$/.test(String(value ?? "")) ? null : "Use the format 2026-00125."),
  minLength: (length) => (value) =>
    String(value ?? "").length >= length ? null : `Must be at least ${length} characters.`,
  notFutureDate: (value) =>
    !value || value <= new Date().toISOString().slice(0, 10) ? null : "Date of birth cannot be in the future.",
};

/**
 * @param {object} values
 * @param {Record<string, Function[]>} schema
 * @returns {{ valid: boolean, errors: Record<string, string> }}
 */
export function validate(values, schema) {
  const errors = {};

  Object.entries(schema).forEach(([fieldName, fieldRules]) => {
    for (const rule of fieldRules) {
      const error = rule(values[fieldName]);
      if (error) {
        errors[fieldName] = error;
        break;
      }
    }
  });

  return { valid: Object.keys(errors).length === 0, errors };
}

/** First error message of a validation result, for toast display. */
export function firstError(errors) {
  const [message] = Object.values(errors || {});
  return message || "Please complete the required fields.";
}
