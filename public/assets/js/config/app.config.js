/**
 * Application configuration.
 *
 * This is the ONLY place where environment-ish values live on the frontend.
 * Nothing secret belongs here — it is served to the browser. Real secrets stay
 * in the PHP `.env` file on the server.
 */
export const appConfig = Object.freeze({
  appName: "University — Enrollment & Records",

  /** Base URL of the native PHP REST API, relative to public/. */
  apiBaseUrl: "api",

  /**
   * Base URL for the Reports module. It defaults to the native API so the app
   * works out of the box; point it at the Laravel module to use the migrated
   * implementation, e.g. "http://localhost:8001/api". Nothing else changes.
   */
  reportsApiBaseUrl: "api",

  /**
   * While the PHP backend is not wired up yet, requests are answered by
   * `mock/mockServer.js`. Set this to `false` (and delete the `mock/` folder)
   * once the API is live — no other file needs to change.
   */
  useMockApi: true,

  /** Artificial delay for mock responses so loading states are exercised. */
  mockLatencyMs: 180,

  /** Default number of rows per page in paginated tables. */
  pageSize: 5,

  /** Prefix for every localStorage/sessionStorage key written by the app. */
  storagePrefix: "enrollment_system:",

  /** Defaults shown in forms and filters. */
  defaults: {
    academicYear: "2026–2027",
    semester: "1st Semester",
  },
});

export default appConfig;
