import { http } from "../core/http.js";

// GET is open to every signed-in role; PUT is admin only (the server checks).
export const settingsApi = {
  get: () => http.get("api/settings"),
  update: (payload) => http.put("api/settings", payload),
};

export default settingsApi;
