/** Auth endpoints. Credentials are verified by PHP — never in the browser. */
import { apiClient } from "../core/apiClient.js";

export const authApi = {
  login: (credentials) => apiClient.post("/auth/login", credentials),
  logout: () => apiClient.post("/auth/logout"),
  session: () => apiClient.get("/auth/session"),
};

export default authApi;
