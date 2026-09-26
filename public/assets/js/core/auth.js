

import { permissionsForRole, ROLE_LABELS } from "../config/roles.js";
import { storage } from "./storage.js";
import { setAuthToken } from "./apiClient.js";

const USER_KEY = "auth.user";
const listeners = new Set();

let currentUser = storage.get(USER_KEY, null);

function notify() {
  listeners.forEach((listener) => listener(currentUser));
}

export const auth = {
  user: () => currentUser,

  isAuthenticated: () => Boolean(currentUser),

  role: () => currentUser?.role ?? null,

  roleLabel: () => (currentUser ? ROLE_LABELS[currentUser.role] ?? currentUser.role : "Guest"),

  can(permission) {
    if (!currentUser) return false;
    const granted = currentUser.permissions?.length
      ? currentUser.permissions
      : permissionsForRole(currentUser.role);
    return granted.includes(permission);
  },

  is(...roles) {
    return currentUser ? roles.includes(currentUser.role) : false;
  },

  setSession({ user, token }) {
    currentUser = user ?? null;
    if (user) storage.set(USER_KEY, user);
    else storage.remove(USER_KEY);
    setAuthToken(token ?? null);
    notify();
  },

  clearSession() {
    currentUser = null;
    storage.remove(USER_KEY);
    setAuthToken(null);
    notify();
  },

  onChange(listener) {
    listeners.add(listener);
    return () => listeners.delete(listener);
  },
};

export default auth;
