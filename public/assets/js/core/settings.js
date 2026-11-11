// The system preferences (default term, rows per page) from the `settings`
// table. index.php prints them into window.__SETTINGS__ so pages can use them
// without waiting for a request; the Settings page updates them after saving.
const FALLBACK = Object.freeze({
  academicYear: "2026–2027",
  semester: "1st Semester",
  rowsPerPage: 10,
});

let current = { ...FALLBACK, ...(window.__SETTINGS__ || {}) };

export const settings = {
  get: () => ({ ...current }),
  set(values) {
    current = { ...current, ...values };
  },
};

export default settings;
