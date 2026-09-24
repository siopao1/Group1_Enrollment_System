/** Enrollment transactions. */
import { apiClient } from "../core/apiClient.js";

export const enrollmentApi = {
  list: (query) => apiClient.get("/enrollments", query),
  find: (id) => apiClient.get(`/enrollments/${encodeURIComponent(id)}`),
  create: (payload) => apiClient.post("/enrollments", payload),
  update: (id, payload) => apiClient.put(`/enrollments/${encodeURIComponent(id)}`, payload),
  remove: (id) => apiClient.delete(`/enrollments/${encodeURIComponent(id)}`),
};

export default enrollmentApi;
