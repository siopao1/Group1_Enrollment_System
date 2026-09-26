

import { enrollmentApi } from "../api/enrollmentApi.js";
import { offeringApi } from "../api/offeringApi.js";
import { appConfig } from "../config/app.config.js";
import { ApiError } from "../core/apiError.js";

export function createSelection() {
  const selected = new Map();

  return {
    add(offering) {
      selected.set(offering.id, offering);
    },
    remove(id) {
      selected.delete(id);
    },
    has(id) {
      return selected.has(id);
    },
    clear() {
      selected.clear();
    },
    items() {
      return [...selected.values()];
    },
    totals() {
      const items = [...selected.values()];
      const units = items.reduce((total, offering) => total + (Number(offering.units) || 3), 0);
      return { count: items.length, units };
    },
  };
}

export const enrollmentService = {
  async list(filters = {}) {
    const { data, meta } = await enrollmentApi.list(filters);
    return { enrollments: data, meta };
  },

  async find(id) {
    const { data } = await enrollmentApi.find(id);
    return data;
  },

  async openOfferings() {
    const { data } = await offeringApi.list({ status: "Open" });
    return data;
  },

  async create({ studentId, selection, academicYear, semester }) {
    const items = selection.items();
    if (!studentId) throw new ApiError("Select a student before confirming.", { status: 422 });
    if (!items.length) throw new ApiError("Please add at least one course offering.", { status: 422 });

    const { data, message } = await enrollmentApi.create({
      studentId,
      offerings: items.map((offering) => offering.id),
      academicYear: academicYear || appConfig.defaults.academicYear,
      semester: semester || appConfig.defaults.semester,
    });

    selection.clear();
    return { enrollment: data, message };
  },

  async remove(id) {
    const { message } = await enrollmentApi.remove(id);
    return message;
  },
};

export default enrollmentService;
