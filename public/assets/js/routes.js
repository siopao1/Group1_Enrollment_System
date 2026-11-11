

import { PERMISSIONS as P } from "./config/roles.js";
import { movedToLaravel } from "./config/navigation.js";

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
  // Old addresses of screens now in the Laravel module: the router sends
  // the browser there (e.g. #/subjects → http://localhost:8001/subjects).
  ...Object.entries(movedToLaravel).map(([path, url]) => ({ path, name: path, title: "Opening…", breadcrumb: "", redirect: url })),
];

export default routes;
