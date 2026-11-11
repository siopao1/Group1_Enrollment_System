import { auth } from "../core/auth.js";
import { csrfToken } from "../core/csrf.js";

// Signing in happens on login.php (a server-side form), so the only
// browser-side action left is signing out.
export const authService = {
  /**
   * Submits a real POST form (with the CSRF token) to logout.php, so the
   * browser follows its redirects: logout.php ends this session and passes
   * through the Laravel module to end that one too (single sign-on), then
   * lands on login.php. The page is left, so the promise never resolves.
   */
  logout() {
    auth.clearSession();
    const form = document.createElement("form");
    form.method = "POST";
    form.action = "logout.php";
    const field = document.createElement("input");
    field.type = "hidden";
    field.name = "csrf";
    field.value = csrfToken();
    form.append(field);
    document.body.append(form);
    form.submit();
    return new Promise(() => {});
  },
};

export default authService;
