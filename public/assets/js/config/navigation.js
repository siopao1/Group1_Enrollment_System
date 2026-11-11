import { PERMISSIONS as P } from "./roles.js";

// Pages built in the Laravel module open there (href); the rest are screens
// in this app (route). Both kinds are filtered by the signed-in user's role.
// Laravel links go through go-laravel.php, which signs the same user in to
// the module with a one-time code (single sign-on) and then opens the page.
const laravel = (path) => `go-laravel.php?to=${encodeURIComponent(`/${path}`)}`;

export const navigation = [
  { route: "dashboard", label: "Dashboard", icon: "layout-dashboard", permission: P.DASHBOARD_VIEW },
  { route: "students", label: "Students", icon: "users", permission: P.STUDENTS_VIEW },
  { href: laravel("subjects"), label: "Subjects", icon: "book-open", permission: P.SUBJECTS_VIEW },
  { href: laravel("offerings"), label: "Course Offerings", icon: "calendar-days", permission: P.OFFERINGS_VIEW },
  { href: laravel("enrollments/create"), label: "Enroll Student", icon: "clipboard-list", permission: P.ENROLLMENTS_MANAGE },
  { href: laravel("enrollments"), label: "Enrollment Records", icon: "file-check", permission: P.ENROLLMENTS_VIEW },
  { href: laravel("records"), label: "Student Records", icon: "graduation-cap", permission: P.RECORDS_VIEW },
  { route: "reports", label: "Reports & Analytics", icon: "chart-no-axes-combined", permission: P.REPORTS_VIEW },
  { route: "settings", label: "Settings", icon: "settings", permission: P.SETTINGS_MANAGE },
];

/** Old #/ addresses of screens that moved to the Laravel module. */
export const movedToLaravel = {
  subjects: laravel("subjects"),
  offerings: laravel("offerings"),
  enrollment: laravel("enrollments/create"),
  enrollments: laravel("enrollments"),
  records: laravel("records"),
};

export default navigation;
