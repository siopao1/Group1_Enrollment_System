

import { appConfig } from "../config/app.config.js";
import { permissionsForRole } from "../config/roles.js";
import { mockDatabase as db } from "./mockDatabase.js";

const ok = (data, message = "Request completed successfully.", meta = null) => ({
  status: 200,
  payload: { success: true, data, message, ...(meta ? { meta } : {}) },
});

const created = (data, message) => ({ status: 201, payload: { success: true, data, message } });

const fail = (status, message, errors = null) => ({
  status,
  payload: { success: false, data: null, message, ...(errors ? { errors } : {}) },
});

const delay = () => new Promise((resolve) => setTimeout(resolve, appConfig.mockLatencyMs));

function paginate(rows, query = {}) {
  const page = Math.max(1, Number(query.page) || 1);
  const perPage = Math.max(1, Number(query.perPage) || appConfig.pageSize);
  const start = (page - 1) * perPage;
  return {
    rows: rows.slice(start, start + perPage),
    meta: { page, perPage, total: rows.length, totalPages: Math.max(1, Math.ceil(rows.length / perPage)) },
  };
}

function studentOf(id) {
  return db.students.find((s) => s.id === id) || null;
}

const routes = [
  ["POST", "/auth/login", ({ body }) => {
    const user = db.users.find((u) => u.username === String(body?.username || "").toLowerCase().trim());
    
    if (!user || body?.password !== "password") {
      return fail(401, "Invalid username or password.");
    }
    return ok(
      { user: { ...user, permissions: permissionsForRole(user.role) }, token: `mock-token-${user.id}` },
      "Signed in successfully."
    );
  }],

  ["POST", "/auth/logout", () => ok(null, "Signed out successfully.")],

  ["GET", "/auth/session", () => fail(401, "No active session.")],

  ["GET", "/dashboard/summary", () => ok(db.dashboard)],

  ["GET", "/students", ({ query }) => {
    const search = String(query.search || "").toLowerCase();
    const filtered = db.students.filter((s) => {
      const haystack = `${s.id} ${s.name} ${s.email}`.toLowerCase();
      return (
        haystack.includes(search) &&
        (!query.program || s.program === query.program) &&
        (!query.year || s.year === query.year) &&
        (!query.status || s.status === query.status)
      );
    });
    const { rows, meta } = paginate(filtered, query);
    return ok(rows, "Students retrieved successfully.", meta);
  }],

  ["GET", "/students/:id", ({ params }) => {
    const student = studentOf(params.id);
    return student ? ok(student) : fail(404, "Student not found.");
  }],

  ["POST", "/students", ({ body }) => {
    if (db.students.some((s) => s.id === body.id)) {
      return fail(422, "A student with that ID already exists.", { id: ["Student ID must be unique."] });
    }
    db.students.unshift(body);
    db.dashboard.totalStudentCount += 1;
    return created(body, "Student successfully added.");
  }],

  ["PUT", "/students/:id", ({ params, body }) => {
    const student = studentOf(params.id);
    if (!student) return fail(404, "Student not found.");
    Object.assign(student, body, { id: student.id });
    return ok(student, "Student profile updated.");
  }],

  ["DELETE", "/students/:id", ({ params }) => {
    const index = db.students.findIndex((s) => s.id === params.id);
    if (index === -1) return fail(404, "Student not found.");
    db.students.splice(index, 1);
    db.dashboard.totalStudentCount -= 1;
    return ok(null, "Student deleted.");
  }],

  // Subjects, course offerings and enrollments are served by the Laravel module
  // (enrollment-module/), not by this mock.

  ["GET", "/settings", () => ok(db.settings)],

  ["PUT", "/settings", ({ body }) => {
    Object.assign(db.settings, body);
    return ok(db.settings, "System settings saved successfully.");
  }],

  ["GET", "/reports", () => ok(db.reports, "Report catalog retrieved successfully.")],

  ["POST", "/reports/:type", ({ params }) => {
    const report = db.reports.find((r) => r.type === params.type);
    if (!report) return fail(404, "Unknown report type.");
    return ok({ type: report.type, generatedAt: "Sept. 16, 2026", rows: [] }, "Report generated successfully.");
  }],
];

function match(method, endpoint) {
  const segments = endpoint.replace(/^\//, "").split("/");

  for (const [routeMethod, pattern, handler] of routes) {
    if (routeMethod !== method) continue;
    const parts = pattern.replace(/^\//, "").split("/");
    if (parts.length !== segments.length) continue;

    const params = {};
    const matched = parts.every((part, index) => {
      if (part.startsWith(":")) {
        params[part.slice(1)] = decodeURIComponent(segments[index]);
        return true;
      }
      return part === segments[index];
    });

    if (matched) return { handler, params };
  }
  return null;
}

export async function mockTransport({ method, endpoint, query = {}, body = null }) {
  await delay();
  const found = match(method, endpoint);
  if (!found) return fail(404, `No mock endpoint for ${method} ${endpoint}.`);

  try {
    return structuredClone(found.handler({ params: found.params, query, body }));
  } catch (error) {
    return fail(500, error.message || "Mock server error.");
  }
}

export default mockTransport;
