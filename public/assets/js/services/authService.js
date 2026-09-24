/**
 * Sign-in orchestration.
 *
 * The service validates input, asks the API, and updates the client session
 * object. It never decides whether the credentials are correct.
 */
import { authApi } from "../api/authApi.js";
import { auth } from "../core/auth.js";
import { validate, rules, firstError } from "../core/validator.js";
import { ApiError } from "../core/apiError.js";

export const authService = {
  async login({ username, password }) {
    const check = validate({ username, password }, {
      username: [rules.required],
      password: [rules.required],
    });

    if (!check.valid) throw new ApiError(firstError(check.errors), { status: 422, errors: check.errors });

    const { data } = await authApi.login({ username: String(username).trim().toLowerCase(), password });
    auth.setSession({ user: data.user, token: data.token });
    return data.user;
  },

  async logout() {
    try {
      await authApi.logout();
    } finally {
      // Clear locally even if the network call fails: the server session is
      // short-lived and the UI must not keep pretending someone is signed in.
      auth.clearSession();
    }
  },

  /** Re-hydrate the session from the server cookie on app start. */
  async restore() {
    try {
      const { data } = await authApi.session();
      auth.setSession({ user: data.user, token: data.token });
      return data.user;
    } catch {
      return auth.user();
    }
  },
};

export default authService;
