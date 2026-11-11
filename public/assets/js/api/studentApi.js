

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

  const envelope = await response.json().catch(() => null);
  if (!envelope || typeof envelope !== "object") {
    throw new ApiError("The server returned an unexpected response.", { status: response.status });
  }

  if (!envelope.success) {
    throw new ApiError(envelope.message, { status: response.status, errors: envelope.errors || null });
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

export const studentApi = {
  list: (query) => realRequest("GET", `/api/students.php${toQueryString(query)}`),
  find: (id) => realRequest("GET", `/api/students.php?id=${encodeURIComponent(id)}`),
  create: (payload) => realRequest("POST", "/api/students.php", payload),
  update: (id, payload) => realRequest("PUT", `/api/students.php?id=${encodeURIComponent(id)}`, payload),
  remove: (id) => realRequest("DELETE", `/api/students.php?id=${encodeURIComponent(id)}`),
  
  academicRecord: (id) => apiClient.get(`/students/${encodeURIComponent(id)}/academic-record`),
};

export default studentApi;
