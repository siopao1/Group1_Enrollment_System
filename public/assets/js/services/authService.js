

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

      auth.clearSession();
    }
  },

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
