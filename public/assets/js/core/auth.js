import { permissionsForRole, ROLE_LABELS } from "../config/roles.js";

// The signed-in user for this page load. index.php prints it from the PHP
// session (window.__USER__) and main.js passes it in here. The JavaScript only
// uses it to decide what to show; the server checks the role on every request.
const listeners = new Set();

let currentUser = null;

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

  setUser(user) {
    currentUser = user ?? null;
    notify();
  },

  clearSession() {
    currentUser = null;
    notify();
  },

  onChange(listener) {
    listeners.add(listener);
    return () => listeners.delete(listener);
  },
};

export default auth;
