/** Enrollment records: search, filter and manage enrollment transactions. */
import { pageHeader } from "../components/pageHeader.js";
import { badge } from "../components/badge.js";
import { dataTable, tableTools, rowActions } from "../components/table.js";
import { emptyState, errorState } from "../components/states.js";
import { pagination } from "../components/pagination.js";
import { filterSelect } from "../components/formFields.js";
import { confirmModal } from "../components/modal.js?v=20260919-2";
import { openStudentProfileDialog } from "../components/studentProfileDialog.js";
import { notify } from "../components/toast.js";
import { qs, escapeHtml, delegate, on } from "../core/dom.js";
import { auth } from "../core/auth.js";
import { PERMISSIONS } from "../config/roles.js";
import { ACADEMIC_YEARS, SEMESTERS, PROGRAMS, ENROLLMENT_STATUSES } from "../config/options.js";
import { enrollmentService } from "../services/enrollmentService.js";

const COLUMNS = [
  "Enrollment ID", "Student", "Program", "Academic Year",
  "Semester", "Total Units", "Enrollment Date", "Status", "Actions",
];

const state = { search: "", semester: "", program: "", status: "", page: 1 };

function enrollmentRow(row) {
  const actions = [{ icon: "eye", title: "View student", attr: `data-view-student="${escapeHtml(row.studentId)}"` }];
  if (auth.can(PERMISSIONS.ENROLLMENTS_MANAGE)) {
    actions.push({ icon: "trash-2", title: "Delete", attr: `data-delete="${escapeHtml(row.id)}"` });
  }

  return `<tr>
  <td><strong>${escapeHtml(row.id)}</strong></td>
  <td>${escapeHtml(row.student?.name ?? "—")}</td>
  <td>${escapeHtml(row.student?.program ?? "—")}</td>
  <td>${escapeHtml(row.academicYear)}</td>
  <td>${escapeHtml(row.semester)}</td>
  <td>${escapeHtml(row.units)}</td>
  <td>${escapeHtml(row.date)}</td>
  <td>${badge(row.status)}</td>
  <td>${rowActions(actions)}</td>
</tr>`;
}

export default {
  async render() {
    return (
      pageHeader("Enrollment Records", "Search, filter, and manage enrollment transactions.") +
      `<div class="card table-card">
        ${tableTools({
          searchId: "enrollmentSearch",
          searchPlaceholder: "Search enrollment ID or student...",
          filters:
            filterSelect({ name: "yearFilter", options: ACADEMIC_YEARS, placeholder: "Academic Year", width: "160px" }) +
            filterSelect({ name: "semesterFilter", options: SEMESTERS, placeholder: "All Semesters", width: "160px" }) +
            filterSelect({ name: "programFilter", options: PROGRAMS, placeholder: "All Programs", width: "200px" }) +
            filterSelect({ name: "statusFilter", options: ENROLLMENT_STATUSES, placeholder: "All Statuses", width: "150px" }) +
            `<button class="btn btn-small" data-action="reset-filters">Reset</button>`,
        })}
        <div id="enrollmentsTable"></div>
        <div id="enrollmentsPagination"></div>
      </div>`
    );
  },

  mount({ navigate }) {
    const content = qs("#content");
    const host = qs("#enrollmentsTable");
    const paginationHost = qs("#enrollmentsPagination");
    let timer = null;

    async function refresh() {
      try {
        const { enrollments, meta } = await enrollmentService.list(state);
        host.innerHTML = dataTable({
          columns: COLUMNS,
          rows: enrollments.map(enrollmentRow),
          emptyHtml: emptyState({ title: "No enrollment records found." }),
        });
        paginationHost.innerHTML = meta
          ? pagination({ page: meta.page, perPage: meta.perPage, total: meta.total })
          : "";
      } catch (error) {
        host.innerHTML = errorState(error.message);
      }
    }

    const offs = [
      delegate(content, "[data-link]", "click", (_e, element) => navigate(element.dataset.link)),
      delegate(content, "[data-view-student]", "click", async (_e, element) => {
        try {
          await openStudentProfileDialog(element.dataset.viewStudent, navigate);
        } catch (error) {
          notify.error(error.message);
        }
      }),
      delegate(content, "[data-delete]", "click", (_e, element) =>
        confirmModal({
          title: "Delete Enrollment Record",
          message: `<p><strong>Delete ${escapeHtml(element.dataset.delete)}?</strong></p>
            <p style="color:#667085">The student's slots in the related offerings are released.</p>`,
          confirmLabel: "Delete",
          async onConfirm() {
            try {
              notify.success(await enrollmentService.remove(element.dataset.delete));
              refresh();
            } catch (error) {
              notify.error(error.message);
            }
          },
        })
      ),
      delegate(content, ".filters select", "change", (event) => {
        const map = { semesterFilter: "semester", programFilter: "program", statusFilter: "status" };
        if (map[event.target.name]) state[map[event.target.name]] = event.target.value;
        state.page = 1;
        refresh();
      }),
      delegate(content, `[data-action="reset-filters"]`, "click", () => {
        Object.assign(state, { search: "", semester: "", program: "", status: "", page: 1 });
        qs("#enrollmentSearch").value = "";
        content.querySelectorAll(".filters select").forEach((select) => (select.value = ""));
        refresh();
      }),
      delegate(paginationHost, "[data-page]", "click", (_e, element) => {
        state.page = Number(element.dataset.page);
        refresh();
      }),
      on(qs("#enrollmentSearch"), "input", (event) => {
        clearTimeout(timer);
        timer = setTimeout(() => {
          state.search = event.target.value.trim();
          state.page = 1;
          refresh();
        }, 250);
      }),
    ];

    refresh();
    return () => {
      clearTimeout(timer);
      offs.forEach((off) => off());
    };
  },
};
