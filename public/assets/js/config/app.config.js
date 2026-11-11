export const appConfig = Object.freeze({
  appName: "University — Enrollment & Records",

  // The Laravel module (enrollment-module/): Subjects, Course Offerings,
  // Enroll Student, Enrollment Records and Student Records. Set LARAVEL_URL
  // in .env (index.php passes it here); the module links back here through
  // its own MAIN_APP_URL.
  laravelUrl: window.__CONFIG__?.laravelUrl || "http://localhost:8001",
});

export default appConfig;
