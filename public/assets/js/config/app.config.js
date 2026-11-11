

export const appConfig = Object.freeze({
  appName: "University — Enrollment & Records",

  apiBaseUrl: "api",

  reportsApiBaseUrl: "api",

  // The Laravel module (enrollment-module/): Subjects, Course Offerings,
  // Enroll Student, Enrollment Records and Student Records. Must match its
  // APP_URL; the module links back here through its MAIN_APP_URL.
  laravelUrl: "http://localhost:8001",

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
