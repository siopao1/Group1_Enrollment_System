

import { PERMISSIONS as P } from "./roles.js";

export const navigation = [
  { route: "dashboard", label: "Dashboard", icon: "layout-dashboard", permission: P.DASHBOARD_VIEW },
  { route: "students", label: "Students", icon: "users", permission: P.STUDENTS_VIEW },
  { route: "subjects", label: "Subjects", icon: "book-open", permission: P.SUBJECTS_VIEW },
  { route: "offerings", label: "Course Offerings", icon: "calendar-days", permission: P.OFFERINGS_VIEW },
  { route: "enrollment", label: "Enroll Student", icon: "clipboard-list", permission: P.ENROLLMENTS_MANAGE },
  { route: "enrollments", label: "Enrollment Records", icon: "file-check", permission: P.ENROLLMENTS_VIEW },
  { route: "records", label: "Student Records", icon: "graduation-cap", permission: P.RECORDS_VIEW },
  { route: "reports", label: "Reports & Analytics", icon: "chart-no-axes-combined", permission: P.REPORTS_VIEW },
  { route: "settings", label: "Settings", icon: "settings", permission: P.SETTINGS_MANAGE },
];

export default navigation;
