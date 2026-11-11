import { http, toQueryString } from "../core/http.js";

// Admin and registrar only (the server answers 403 for faculty).
const BASE = "api/reports";

export const reportApi = {
  catalog: () => http.get(BASE),
  generate: (type, filters) => http.get(`${BASE}/${encodeURIComponent(type)}`, filters),
  // A plain link: the browser downloads the CSV with the session cookie.
  csvUrl: (type, filters) => `${BASE}/${encodeURIComponent(type)}${toQueryString({ ...filters, format: "csv" })}`,
};

export default reportApi;
