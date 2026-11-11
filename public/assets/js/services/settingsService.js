import { settingsApi } from "../api/settingsApi.js";
import { settings } from "../core/settings.js";

export const settingsService = {
  async get() {
    const { data } = await settingsApi.get();
    settings.set(data);
    return data;
  },

  async update(values) {
    const { data, message } = await settingsApi.update({
      academicYear: values.academicYear,
      semester: values.semester,
      rowsPerPage: Number(values.rowsPerPage),
    });
    settings.set(data); // other pages pick up the new defaults right away
    return { settings: data, message };
  },
};

export default settingsService;
