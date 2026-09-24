/** Course offerings (a subject scheduled for a term, with an instructor/room). */
import { apiClient } from "../core/apiClient.js";

export const offeringApi = {
  list: (query) => apiClient.get("/course-offerings", query),
  find: (id) => apiClient.get(`/course-offerings/${encodeURIComponent(id)}`),
  create: (payload) => apiClient.post("/course-offerings", payload),
  update: (id, payload) => apiClient.put(`/course-offerings/${encodeURIComponent(id)}`, payload),
  remove: (id) => apiClient.delete(`/course-offerings/${encodeURIComponent(id)}`),
};

export default offeringApi;
