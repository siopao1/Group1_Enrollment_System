/**
 * The single HTTP entry point of the frontend.
 *
 * Every `api/*.js` module goes through this client, so there is exactly one
 * place that knows about base URLs, headers, the JSON envelope and error
 * handling. No page or component should ever call `fetch()` directly.
 *
 * Expected response envelope (see docs/api.md):
 *   { "success": true,  "data": ..., "message": "...", "meta": {...} }
 *   { "success": false, "data": null, "message": "...", "errors": {...} }
 */
import { appConfig } from "../config/app.config.js";
import { ApiError } from "./apiError.js";
import { storage } from "./storage.js";

const TOKEN_KEY = "auth.token";

export function setAuthToken(token) {
  if (token) storage.set(TOKEN_KEY, token);
  else storage.remove(TOKEN_KEY);
}

export function getAuthToken() {
  return storage.get(TOKEN_KEY, null);
}

/** Set by mock/mockServer.js when `appConfig.useMockApi` is true. */
let mockHandler = null;
export function useMockTransport(handler) {
  mockHandler = handler;
}

function buildUrl(baseUrl, endpoint, query) {
  const path = endpoint.startsWith("/") ? endpoint : `/${endpoint}`;
  const search = new URLSearchParams();

  Object.entries(query || {}).forEach(([key, value]) => {
    if (value === undefined || value === null || value === "") return;
    search.append(key, value);
  });

  const qs = search.toString();
  return `${baseUrl}${path}${qs ? `?${qs}` : ""}`;
}

async function transport(method, baseUrl, endpoint, { query, body, headers } = {}) {
  if (mockHandler) {
    return mockHandler({ method, endpoint, query, body });
  }

  const token = getAuthToken();
  const response = await fetch(buildUrl(baseUrl, endpoint, query), {
    method,
    credentials: "same-origin",
    headers: {
      Accept: "application/json",
      ...(body ? { "Content-Type": "application/json" } : {}),
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
      ...headers,
    },
    ...(body ? { body: JSON.stringify(body) } : {}),
  });

  let payload = null;
  try {
    payload = await response.json();
  } catch {
    payload = null;
  }

  return { status: response.status, payload };
}

async function request(method, endpoint, options = {}) {
  const baseUrl = options.baseUrl || appConfig.apiBaseUrl;
  let result;

  try {
    result = await transport(method, baseUrl, endpoint, options);
  } catch (networkError) {
    throw new ApiError("Unable to reach the server. Check your connection.", {
      status: 0,
      endpoint,
    });
  }

  const { status, payload } = result;

  if (!payload || typeof payload !== "object") {
    throw new ApiError("The server returned an unexpected response.", { status, endpoint });
  }

  if (status === 401) setAuthToken(null);

  if (!payload.success) {
    throw new ApiError(payload.message, {
      status,
      endpoint,
      errors: payload.errors || null,
    });
  }

  return { data: payload.data, message: payload.message, meta: payload.meta || null };
}

export const apiClient = {
  get: (endpoint, query, options) => request("GET", endpoint, { ...options, query }),
  post: (endpoint, body, options) => request("POST", endpoint, { ...options, body }),
  put: (endpoint, body, options) => request("PUT", endpoint, { ...options, body }),
  patch: (endpoint, body, options) => request("PATCH", endpoint, { ...options, body }),
  delete: (endpoint, options) => request("DELETE", endpoint, options),
};

export default apiClient;
