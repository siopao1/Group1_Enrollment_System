
import { reportApi } from "../api/reportApi.js";

export const reportService = {
  async catalog() {
    const { data } = await reportApi.catalog();
    return data;
  },

  async generate(type, filters) {
    const { data, message } = await reportApi.generate(type, filters);
    return { report: data, message };
  },
};

export default reportService;
