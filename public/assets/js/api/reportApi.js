

import { apiClient } from "../core/apiClient.js";
import { appConfig } from "../config/app.config.js";

const options = { baseUrl: appConfig.reportsApiBaseUrl };

export const reportApi = {
  catalog: () => apiClient.get("/reports", undefined, options),
  generate: (type, filters) => apiClient.post(`/reports/${encodeURIComponent(type)}`, filters, options),
};

export default reportApi;
