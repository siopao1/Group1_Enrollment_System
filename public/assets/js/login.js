/**
 * Login page entry point.
 *
 * The form only collects credentials and shows the result. Verification,
 * hashing and session creation all happen in PHP (AuthController -> AuthService
 * -> UserRepository).
 */
import { appConfig } from "./config/app.config.js";
import { useMockTransport } from "./core/apiClient.js";
import { auth } from "./core/auth.js";
import { qs, on, formValues } from "./core/dom.js";
import { authService } from "./services/authService.js";

async function boot() {
  if (appConfig.useMockApi) {
    const { mockTransport } = await import("./mock/mockServer.js");
    useMockTransport(mockTransport);
  }

  if (auth.isAuthenticated()) {
    window.location.replace("index.php");
    return;
  }

  const form = qs("#loginForm");
  const error = qs("#loginError");
  const submit = form.querySelector("[type=submit]");

  on(form, "submit", async (event) => {
    event.preventDefault();
    error.textContent = "";
    error.classList.remove("show");
    submit.disabled = true;
    submit.textContent = "Signing in…";

    try {
      await authService.login(formValues(form));
      window.location.replace("index.php");
    } catch (exception) {
      error.textContent = exception.message;
      error.classList.add("show");
      form.querySelector("[name=password]").value = "";
      form.querySelector("[name=password]").focus();
    } finally {
      submit.disabled = false;
      submit.textContent = "Sign In";
    }
  });
}

boot();
