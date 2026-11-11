import { auth } from "../core/auth.js";
import { csrfToken } from "../core/csrf.js";

// Signing in happens on login.php (a server-side form), so the only
// browser-side action left is signing out.
export const authService = {
  async logout() {
    try {
      // logout.php destroys the PHP session; it requires POST + the CSRF token.
      await fetch("logout.php", {
        method: "POST",
        credentials: "same-origin",
        headers: { "X-CSRF-Token": csrfToken() },
      });
    } finally {
      auth.clearSession();
    }
  },
};

export default authService;
