/**
 * Student use-cases: list with filters/pagination, create, update, delete.
 * Pages call this; they never talk to `apiClient` or shape query strings.
 */
import { studentApi } from "../api/studentApi.js";
import { appConfig } from "../config/app.config.js";
import { validate, rules, firstError } from "../core/validator.js";
import { ApiError } from "../core/apiError.js";

const createSchema = {
  id: [rules.required, rules.studentId],
  firstName: [rules.required],
  lastName: [rules.required],
  address: [rules.required],
  program: [rules.required],
  year: [rules.required],
  email: [rules.required, rules.email],
  phone: [rules.required, rules.phone],
  dob: [rules.required, rules.notFutureDate],
  civilStatus: [rules.required],
  gender: [rules.required],
  emergencyNumber: [rules.phone],
};

/** Map raw form values to the payload shape the API expects. */
export function toStudentPayload(values) {
  const name = [values.firstName, values.middleName, values.lastName, values.suffix]
    .map((part) => String(part || "").trim())
    .filter(Boolean)
    .join(" ");

  return {
    id: String(values.studentId || values.id || "").trim(),
    name,
    firstName: values.firstName,
    middleName: values.middleName || "",
    lastName: values.lastName,
    suffix: values.suffix || "",
    email: values.email || "",
    contact: values.phone || values.contact || "Not provided",
    program: values.program || "",
    year: values.year || "",
    status: values.status || "Active",
    gender: values.gender || "Not specified",
    civilStatus: values.civilStatus || "",
    dateOfBirth: values.dob || "",
    address: values.address || "",
    academicYear: values.academicYear || appConfig.defaults.academicYear,
    emergencyContact: {
      name: values.emergencyName || "",
      relationship: values.relationship || "",
      number: values.emergencyNumber || "",
    },
    date: "Sept. 16, 2026",
  };
}

export const studentService = {
  async list({ search = "", program = "", year = "", status = "", sort = "", page = 1, perPage = appConfig.pageSize } = {}) {
    const { data, meta } = await studentApi.list({ search, program, year, status, sort, page, perPage });
    return { students: data, meta: meta ?? { page, perPage, total: data.length, totalPages: 1 } };
  },

  async find(id) {
    const { data } = await studentApi.find(id);
    return data;
  },

  async academicRecord(id) {
    const { data } = await studentApi.academicRecord(id);
    return data;
  },

  async create(formValues) {
    const payload = toStudentPayload({ ...formValues, id: formValues.studentId });
    // Validate the raw form input, not the payload: the payload substitutes
    // defaults such as "Not provided", which are not valid phone numbers.
    const check = validate({ ...formValues, id: payload.id }, createSchema);
    if (!check.valid) throw new ApiError(firstError(check.errors), { status: 422, errors: check.errors });

    const { data, message } = await studentApi.create(payload);
    return { student: data, message };
  },

  async update(id, values) {
    const check = validate(values, {
      firstName: [rules.required],
      lastName: [rules.required],
      email: [rules.email],
      phone: [rules.phone],
    });
    if (!check.valid) throw new ApiError(firstError(check.errors), { status: 422, errors: check.errors });

    const { data, message } = await studentApi.update(id, values);
    return { student: data, message };
  },

  async remove(id) {
    const { message } = await studentApi.remove(id);
    return message;
  },
};

export default studentService;
