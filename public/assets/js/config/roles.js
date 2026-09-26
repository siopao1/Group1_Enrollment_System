

export const ROLES = Object.freeze({
  ADMIN: "admin",
  REGISTRAR: "registrar",
  FACULTY: "faculty",
});

export const ROLE_LABELS = Object.freeze({
  [ROLES.ADMIN]: "Administrator",
  [ROLES.REGISTRAR]: "Registrar",
  [ROLES.FACULTY]: "Faculty",
});

export const PERMISSIONS = Object.freeze({
  DASHBOARD_VIEW: "dashboard.view",
  STUDENTS_VIEW: "students.view",
  STUDENTS_MANAGE: "students.manage",
  SUBJECTS_VIEW: "subjects.view",
  SUBJECTS_MANAGE: "subjects.manage",
  OFFERINGS_VIEW: "offerings.view",
  OFFERINGS_MANAGE: "offerings.manage",
  ENROLLMENTS_VIEW: "enrollments.view",
  ENROLLMENTS_MANAGE: "enrollments.manage",
  RECORDS_VIEW: "records.view",
  REPORTS_VIEW: "reports.view",
  SETTINGS_MANAGE: "settings.manage",
});

const P = PERMISSIONS;

export const ROLE_PERMISSIONS = Object.freeze({
  [ROLES.ADMIN]: Object.values(P),
  [ROLES.REGISTRAR]: [
    P.DASHBOARD_VIEW,
    P.STUDENTS_VIEW,
    P.STUDENTS_MANAGE,
    P.SUBJECTS_VIEW,
    P.OFFERINGS_VIEW,
    P.ENROLLMENTS_VIEW,
    P.ENROLLMENTS_MANAGE,
    P.RECORDS_VIEW,
    P.REPORTS_VIEW,
  ],
  [ROLES.FACULTY]: [
    P.DASHBOARD_VIEW,
    P.STUDENTS_VIEW,
    P.SUBJECTS_VIEW,
    P.OFFERINGS_VIEW,
    P.RECORDS_VIEW,
  ],
});

export function permissionsForRole(role) {
  return ROLE_PERMISSIONS[role] ?? [];
}
