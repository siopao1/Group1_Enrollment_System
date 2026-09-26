
import { dashboardApi } from "../api/dashboardApi.js";

export const dashboardService = {
  async summary() {
    const { data } = await dashboardApi.summary();
    return data;
  },
};

export default dashboardService;
