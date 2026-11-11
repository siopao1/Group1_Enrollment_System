// The one place the frontend talks to the PHP REST API (public/api/).
// Every call goes through request(), which:
//   - sends the session cookie and the CSRF token (X-CSRF-Token header)
//   - sends and parses JSON in the API's envelope {success, data, message, errors, meta}
//   - sends the user to the login page on 401 (session expired / signed out)
//   - turns any failure into an ApiError the pages can show
import { ApiError } from "./apiError.js";
import { csrfToken } from "./csrf.js";

export async function request(method, path, body) {
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

/** {a: 1, b: ""} → "?a=1" (empty values are left out). */
export function toQueryString(query) {
  const params = new URLSearchParams();
  Object.entries(query || {}).forEach(([key, value]) => {
    if (value === undefined || value === null || value === "") return;
    params.set(key, value);
  });
  const qs = params.toString();
  return qs ? `?${qs}` : "";
}

export const http = {
  get: (path, query) => request("GET", `${path}${toQueryString(query)}`),
  post: (path, body) => request("POST", path, body),
  put: (path, body) => request("PUT", path, body),
  patch: (path, body) => request("PATCH", path, body),
  delete: (path) => request("DELETE", path),
};

export default http;
