/**
 * TEMPORARY transport that answers the same routes the PHP API will expose,
 * with the same JSON envelope and the same HTTP status codes.
 *
 * It is plugged into `apiClient` from `main.js` only while
 * `appConfig.useMockApi` is true, so no api/service/page module knows it exists.
 */
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

/* ------------------------------------------------------------------ helpers */

function paginate(rows, query = {}) {
  const page = Math.max(1, Number(query.page) || 1);
  const perPage = Math.max(1, Number(query.perPage) || appConfig.pageSize);
  const start = (page - 1) * perPage;
  return {
    rows: rows.slice(start, start + perPage),
    meta: { page, perPage, total: rows.length, totalPages: Math.max(1, Math.ceil(rows.length / perPage)) },
  };
}

function nextEnrollmentId() {
  const numbers = db.enrollments.map((e) => Number(e.id.split("-").pop()));
  return `ENR-2026-${String(Math.max(0, ...numbers) + 1).padStart(4, "0")}`;
}

function studentOf(id) {
  return db.students.find((s) => s.id === id) || null;
}

/* ------------------------------------------------------------------- routes */

const routes = [
  ["POST", "/auth/login", ({ body }) => {
    const user = db.users.find((u) => u.username === String(body?.username || "").toLowerCase().trim());
    // Demo credentials only. The real check is password_verify() in PHP.
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

  ["GET", "/students/:id/academic-record", ({ params }) => {
    const student = studentOf(params.id);
    if (!student) return fail(404, "Student not found.");
    return ok({
      student,
      summary: db.academicSummaries[student.id] || {
        gpa: "—", completedUnits: 0, totalUnits: 120, standing: "No records yet", civilStatus: "—",
      },
      grades: db.grades.filter((g) => g.studentId === student.id),
    });
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

  ["GET", "/subjects", ({ query }) => {
    const search = String(query.search || "").toLowerCase();
    const rows = db.subjects.filter(
      (s) =>
        `${s.code} ${s.name}`.toLowerCase().includes(search) &&
        (!query.department || s.department === query.department)
    );
    return ok(rows, "Subjects retrieved successfully.");
  }],

  ["POST", "/subjects", ({ body }) => {
    db.subjects.push({ status: "Active", ...body, units: Number(body.units) || 3 });
    return created(body, "Subject saved successfully.");
  }],

  ["PUT", "/subjects/:code", ({ params, body }) => {
    const subject = db.subjects.find((s) => s.code === params.code);
    if (!subject) return fail(404, "Subject not found.");
    Object.assign(subject, body);
    return ok(subject, "Subject saved successfully.");
  }],

  ["DELETE", "/subjects/:code", ({ params }) => {
    const index = db.subjects.findIndex((s) => s.code === params.code);
    if (index === -1) return fail(404, "Subject not found.");
    db.subjects.splice(index, 1);
    return ok(null, "Subject deleted.");
  }],

  ["GET", "/course-offerings", ({ query }) => {
    const rows = db.offerings.filter((o) => !query.status || o.status === query.status);
    return ok(rows, "Course offerings retrieved successfully.");
  }],

  ["POST", "/course-offerings", ({ body }) => {
    const offering = { id: `OFF-2026-${String(db.offerings.length + 1).padStart(3, "0")}`, enrolled: 0, status: "Open", ...body };
    db.offerings.push(offering);
    return created(offering, "Course offering saved successfully.");
  }],

  ["GET", "/enrollments", ({ query }) => {
    const search = String(query.search || "").toLowerCase();
    const rows = db.enrollments
      .map((enrollment) => ({ ...enrollment, student: studentOf(enrollment.studentId) }))
      .filter((row) => {
        const haystack = `${row.id} ${row.student?.name ?? ""}`.toLowerCase();
        return (
          haystack.includes(search) &&
          (!query.status || row.status === query.status) &&
          (!query.semester || row.semester === query.semester) &&
          (!query.program || row.student?.program === query.program)
        );
      });
    const { rows: pageRows, meta } = paginate(rows, { ...query, perPage: query.perPage || 10 });
    return ok(pageRows, "Enrollment records retrieved successfully.", meta);
  }],

  ["GET", "/enrollments/:id", ({ params }) => {
    const enrollment = db.enrollments.find((e) => e.id === params.id);
    if (!enrollment) return fail(404, "Enrollment record not found.");
    return ok({ ...enrollment, student: studentOf(enrollment.studentId) });
  }],

  ["POST", "/enrollments", ({ body }) => {
    const student = studentOf(body.studentId);
    if (!student) return fail(422, "Select a valid student before confirming.", { studentId: ["Unknown student."] });
    if (!body.offerings?.length) {
      return fail(422, "Please add at least one course offering.", { offerings: ["At least one offering is required."] });
    }

    const offerings = db.offerings.filter((o) => body.offerings.includes(o.id));
    const full = offerings.find((o) => o.enrolled >= o.slots);
    if (full) return fail(409, `${full.code} has no remaining slots.`);

    const enrollment = {
      id: nextEnrollmentId(),
      studentId: student.id,
      academicYear: body.academicYear || appConfig.defaults.academicYear,
      semester: body.semester || appConfig.defaults.semester,
      units: offerings.reduce((total, o) => total + (o.units || 3), 0),
      date: "Sept. 16, 2026",
      status: "Pending",
      offerings: offerings.map((o) => o.id),
    };

    offerings.forEach((offering) => {
      offering.enrolled += 1;
      if (offering.enrolled >= offering.slots) offering.status = "Full";
    });

    db.enrollments.unshift(enrollment);
    return created(enrollment, "Enrollment successfully created.");
  }],

  ["PUT", "/enrollments/:id", ({ params, body }) => {
    const enrollment = db.enrollments.find((e) => e.id === params.id);
    if (!enrollment) return fail(404, "Enrollment record not found.");
    Object.assign(enrollment, body, { id: enrollment.id });
    return ok(enrollment, "Enrollment record updated.");
  }],

  ["DELETE", "/enrollments/:id", ({ params }) => {
    const index = db.enrollments.findIndex((e) => e.id === params.id);
    if (index === -1) return fail(404, "Enrollment record not found.");
    db.enrollments.splice(index, 1);
    return ok(null, "Enrollment record deleted.");
  }],

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

/**
 * Signature mirrors what `apiClient` expects from a transport.
 *
 * The result is cloned on the way out: a real HTTP response is always a fresh
 * object, so handing back live references to the in-memory rows would let the
 * UI mutate "the database" by accident and hide bugs that would appear against
 * the real API.
 */
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
