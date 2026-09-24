/**
 * Reports & Analytics.
 *
 * This module is the one that gets migrated to Laravel, so it points at
 * `appConfig.reportsApiBaseUrl` rather than the native PHP API base. Switching
 * between the native implementation and the Laravel one is a config change.
 */
import { apiClient } from "../core/apiClient.js";
import { appConfig } from "../config/app.config.js";

const options = { baseUrl: appConfig.reportsApiBaseUrl };

export const reportApi = {
  catalog: () => apiClient.get("/reports", undefined, options),
  generate: (type, filters) => apiClient.post(`/reports/${encodeURIComponent(type)}`, filters, options),
};

export default reportApi;
