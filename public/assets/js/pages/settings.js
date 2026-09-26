
import { pageHeader } from "../components/pageHeader.js";
import { selectField, infoItem } from "../components/formFields.js";
import { notify } from "../components/toast.js";
import { qs, on, formValues } from "../core/dom.js";
import { auth } from "../core/auth.js";
import { ACADEMIC_YEARS, SEMESTERS, ROWS_PER_PAGE } from "../config/options.js";
import { settingsService } from "../services/settingsService.js";
import { authService } from "../services/authService.js";
import { confirmModal } from "../components/modal.js?v=20260919-2";

export default {
  async render() {
    const settings = await settingsService.get();

    return (
      pageHeader("Settings", "Manage system preferences and administrative configuration.") +
      `<div class="grid-2">
  <div class="card"><div class="card-header"><h3>System Preferences</h3></div>
    <div class="card-body"><form id="settingsForm">
      <div class="form-section">${selectField({
        label: "Default Academic Year",
        name: "academicYear",
        options: ACADEMIC_YEARS,
        value: settings.academicYear,
      })}</div>
      <div class="form-section">${selectField({
        label: "Default Semester",
        name: "semester",
        options: SEMESTERS,
        value: settings.semester,
      })}</div>
      <div class="form-section">${selectField({
        label: "Rows Per Page",
        name: "rowsPerPage",
        options: ROWS_PER_PAGE,
        value: settings.rowsPerPage,
      })}</div>
      <button type="submit" class="btn btn-primary">Save Changes</button>
    </form></div>
  </div>

  <div class="card"><div class="card-header"><h3>Account & Access</h3></div>
    <div class="card-body"><div class="info-grid" style="grid-template-columns:1fr">
      ${infoItem("Signed in as", auth.user()?.name ?? "—")}
      ${infoItem("Current Role", auth.roleLabel())}
      ${infoItem("Authorization", "Enforced server-side on every API request")}
    </div>
    <button class="btn" style="margin-top:14px" id="logoutBtn">Log Out</button></div>
  </div>
</div>`
    );
  },

  mount() {
    const form = qs("#settingsForm");

    const offs = [
      on(form, "submit", async (event) => {
        event.preventDefault();
        try {
          const { message } = await settingsService.update(formValues(form));
          notify.success(message);
        } catch (error) {
          notify.error(error.message);
        }
      }),
      on(qs("#logoutBtn"), "click", () =>
        confirmModal({
          title: "Logout Confirmation",
          message: `<p>Are you sure you want to log out of this session?</p>`,
          confirmLabel: "Log Out",
          async onConfirm() {
            await authService.logout();
            window.location.href = "login.php";
          },
        })
      ),
    ];

    return () => offs.forEach((off) => off());
  },
};
