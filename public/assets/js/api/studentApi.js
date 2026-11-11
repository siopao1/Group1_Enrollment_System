import { http } from "../core/http.js";

// Relative URLs, so the app works at the server root or inside a subfolder.
const BASE = "api/students";
const one = (id) => `${BASE}/${encodeURIComponent(id)}`;

export const studentApi = {
  list: (query) => http.get(BASE, query),
  find: (id) => http.get(one(id)),
  create: (payload) => http.post(BASE, payload),
  replace: (id, payload) => http.put(one(id), payload),
  patch: (id, payload) => http.patch(one(id), payload),
  remove: (id) => http.delete(one(id)),
};

export default studentApi;
