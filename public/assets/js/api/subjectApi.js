/** Subject catalog resource. */
import { apiClient } from "../core/apiClient.js";

export const subjectApi = {
  list: (query) => apiClient.get("/subjects", query),
  find: (code) => apiClient.get(`/subjects/${encodeURIComponent(code)}`),
  create: (payload) => apiClient.post("/subjects", payload),
  update: (code, payload) => apiClient.put(`/subjects/${encodeURIComponent(code)}`, payload),
  remove: (code) => apiClient.delete(`/subjects/${encodeURIComponent(code)}`),
};

export default subjectApi;
