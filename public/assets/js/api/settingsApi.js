
import { apiClient } from "../core/apiClient.js";

export const settingsApi = {
  get: () => apiClient.get("/settings"),
  update: (payload) => apiClient.put("/settings", payload),
};

export default settingsApi;
