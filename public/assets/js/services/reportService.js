import { reportApi } from "../api/reportApi.js";

/** Form field names → the API's query parameter names. */
function toQuery(values) {
  return {
    academic_year: values.academicYear || "",
    semester: values.semester || "",
    program: values.program || "",
    year_level: values.yearLevel || "",
  };
}

export const reportService = {
  /** @returns {Promise<{reports: object[], options: object}>} */
  async catalog() {
    const { data, meta } = await reportApi.catalog();
    return { reports: data, options: meta?.filterOptions ?? {} };
  },

  async generate(type, values) {
    const { data, message } = await reportApi.generate(type, toQuery(values));
    return { report: data, message };
  },

  csvUrl(type, values) {
    return reportApi.csvUrl(type, toQuery(values));
  },
};

export default reportService;
