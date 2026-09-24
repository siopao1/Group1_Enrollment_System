/**
 * Route table.
 *
 * `load` uses dynamic import so a page module is only downloaded when it is
 * first visited. `permission` is checked by the router for the UI; the same
 * permission is checked again by PHP for the data.
 */
import { PERMISSIONS as P } from "./config/roles.js";

const dashboard = {
  name: "dashboard",
  title: "Dashboard",
  breadcrumb: "Home / Dashboard",
  permission: P.DASHBOARD_VIEW,
  load: () => import("./pages/dashboard.js"),
};

export const routes = [
  { path: "", ...dashboard },
  { path: "dashboard", ...dashboard },
  {
    path: "students",
    name: "students",
    title: "Students",
    breadcrumb: "Home / Student Management / Students",
    permission: P.STUDENTS_VIEW,
    load: () => import("./pages/students.js?v=20260919-2"),
  },
  {
    path: "students/new",
    name: "students/new",
    title: "Add New Student",
    breadcrumb: "Home / Student Management / Add Student",
    permission: P.STUDENTS_MANAGE,
    load: () => import("./pages/studentForm.js"),
  },
  {
    path: "subjects",
    name: "subjects",
    title: "Subjects",
    breadcrumb: "Home / Academic / Subjects",
    permission: P.SUBJECTS_VIEW,
    load: () => import("./pages/subjects.js"),
  },
  {
    path: "offerings",
    name: "offerings",
    title: "Course Offerings",
    breadcrumb: "Home / Academic / Course Offerings",
    permission: P.OFFERINGS_VIEW,
    load: () => import("./pages/offerings.js"),
  },
  {
    path: "enrollment",
    name: "enrollment",
    title: "Enroll Student",
    breadcrumb: "Home / Enrollment / Enroll Student",
    permission: P.ENROLLMENTS_MANAGE,
    load: () => import("./pages/enrollment.js?v=20260919-2"),
  },
  {
    path: "enrollments",
    name: "enrollments",
    title: "My Enrollment Records",
    breadcrumb: "Home / Enrollment / My Records",
    permission: P.ENROLLMENTS_VIEW,
    load: () => import("./pages/enrollmentRecords.js"),
  },
  {
    path: "records",
    name: "records",
    title: "Student Records",
    breadcrumb: "Home / Records / Student Records",
    permission: P.RECORDS_VIEW,
    load: () => import("./pages/academicRecords.js"),
  },
  {
    path: "reports",
    name: "reports",
    title: "Reports & Analytics",
    breadcrumb: "Home / Reports",
    permission: P.REPORTS_VIEW,
    load: () => import("./pages/reports.js"),
  },
  {
    path: "settings",
    name: "settings",
    title: "Settings",
    breadcrumb: "Home / System / Settings",
    permission: P.SETTINGS_MANAGE,
    load: () => import("./pages/settings.js"),
  },
];

export default routes;
