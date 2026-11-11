

import { apiClient } from "../core/apiClient.js";
import { ApiError } from "../core/apiError.js";
import { csrfToken } from "../core/csrf.js";

async function realRequest(method, path, body) {
  let response;
  try {
    response = await fetch(path, {
      method,
      credentials: "same-origin",
      headers: {
        ...(body !== undefined ? { "Content-Type": "application/json" } : {}),
        "X-CSRF-Token": csrfToken(),
      },
      body: body !== undefined ? JSON.stringify(body) : undefined,
    });
  } catch {
    throw new ApiError("Unable to reach the server. Check your connection.", { status: 0 });
  }

  // Session expired or signed out in another tab: back to the login page.
  if (response.status === 401) {
    window.location.href = "login.php";
  }

  // 204 No Content (DELETE): success with no JSON body.
  if (response.status === 204) {
    return { data: null, message: "", meta: null };
  }

  const envelope = await response.json().catch(() => null);
  if (!envelope || typeof envelope !== "object") {
    throw new ApiError("The server returned an unexpected response.", { status: response.status });
  }

  if (!envelope.success) {
    // For 422 the server lists messages per field; show the first one.
    const firstFieldError = envelope.errors ? Object.values(envelope.errors).flat()[0] : null;
    throw new ApiError(firstFieldError || envelope.message, {
      status: response.status,
      errors: envelope.errors || null,
    });
  }

  return { data: envelope.data, message: envelope.message, meta: envelope.meta || null };
}

function toQueryString(query) {
  const params = new URLSearchParams();
  Object.entries(query || {}).forEach(([key, value]) => {
    if (value === undefined || value === null || value === "") return;
    params.set(key, value);
  });
  const qs = params.toString();
  return qs ? `?${qs}` : "";
}

// Relative URLs, so the app works at the server root or inside a subfolder.
const BASE = "api/students";
const one = (id) => `${BASE}/${encodeURIComponent(id)}`;

export const studentApi = {
  list: (query) => realRequest("GET", `${BASE}${toQueryString(query)}`),
  find: (id) => realRequest("GET", one(id)),
  create: (payload) => realRequest("POST", BASE, payload),
  replace: (id, payload) => realRequest("PUT", one(id), payload),
  patch: (id, payload) => realRequest("PATCH", one(id), payload),
  remove: (id) => realRequest("DELETE", one(id)),

  // Still served by the browser mock until the Laravel module (Phase 4).
  academicRecord: (id) => apiClient.get(`/students/${encodeURIComponent(id)}/academic-record`),
};

export default studentApi;
