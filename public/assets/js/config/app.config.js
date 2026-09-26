

export const appConfig = Object.freeze({
  appName: "University — Enrollment & Records",

  apiBaseUrl: "api",

  reportsApiBaseUrl: "api",

  useMockApi: true,

  mockLatencyMs: 180,

  pageSize: 5,

  storagePrefix: "enrollment_system:",

  defaults: {
    academicYear: "2026–2027",
    semester: "1st Semester",
  },
});

export default appConfig;
