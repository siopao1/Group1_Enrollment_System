/**
 * Student list: search, filters, pagination and row actions.
 *
 * The table body is re-rendered on its own (`refresh()`), so filtering never
 * rebuilds the whole page or re-binds listeners — the toolbar keeps one
 * delegated listener for the lifetime of the page.
 */
import { pageHeader } from "../components/pageHeader.js";
import { badge } from "../components/badge.js";
import { avatar } from "../components/avatar.js";
import { dataTable, tableTools, rowActions } from "../components/table.js";
import { emptyState, errorState } from "../components/states.js";
import { pagination } from "../components/pagination.js";
import { openModal, closeModal, confirmModal } from "../components/modal.js?v=20260919-2";
import { field, selectField, filterSelect } from "../components/formFields.js";
import { notify } from "../components/toast.js";
import { qs, escapeHtml, delegate, on, bindPhoneInput } from "../core/dom.js";
import { auth } from "../core/auth.js";
import { PERMISSIONS } from "../config/roles.js";
import { PROGRAMS, YEAR_LEVELS, STUDENT_STATUSES, STUDENT_SORT_OPTIONS } from "../config/options.js";
import { studentService } from "../services/studentService.js";
import { studentFormMarkup, mountStudentForm } from "./studentForm.js";
import { openStudentProfileDialog } from "../components/studentProfileDialog.js";

const state = { search: "", program: "", year: "", status: "", sort: "", page: 1 };

function filtersMarkup() {
  return (
    filterSelect({ name: "programFilter", options: PROGRAMS, placeholder: "All Programs", width: "200px" }) +
    filterSelect({ name: "yearFilter", options: YEAR_LEVELS, placeholder: "Year Level", width: "125px" }) +
    filterSelect({ name: "statusFilter", options: STUDENT_STATUSES, placeholder: "Status", width: "120px" }) +
    filterSelect({ name: "sortFilter", options: STUDENT_SORT_OPTIONS, placeholder: "Sort by", width: "150px" }) +
    `<button class="btn btn-small" data-action="reset-filters">Reset</button>`
  );
}

function openStudentForm(onSuccess) {
  let cleanup = null;
  openModal({
    title: "Add Student",
    body: `<div class="modal-form">${studentFormMarkup()}</div>`,
    onMount(root) {
      cleanup = mountStudentForm(root.querySelector("#studentForm"), {
        onSuccess() {
          closeModal();
          onSuccess?.();
        },
        onCancel: closeModal,
      });
    },
    onClose() {
      cleanup?.();
    },
  });
}

async function openStudentProfile(id, navigate, onEdit) {
  try {
    await openStudentProfileDialog(id, navigate, onEdit);
  } catch (error) {
    notify.error(error.message);
  }
}

function studentRow(student) {
  const canManage = auth.can(PERMISSIONS.STUDENTS_MANAGE);
  const actions = [{ icon: "eye", title: "View", attr: `data-view="${escapeHtml(student.id)}"` }];

  if (canManage) {
    actions.push(
      { icon: "pencil", title: "Edit", attr: `data-edit="${escapeHtml(student.id)}"` },
      { icon: "trash-2", title: "Delete", attr: `data-delete="${escapeHtml(student.id)}"` }
    );
  }

  return `<tr>
  <td><strong>${escapeHtml(student.id)}</strong></td>
  <td><div class="student-cell">${avatar(student.name, "sm")}
    <div><strong>${escapeHtml(student.name)}</strong><small>${escapeHtml(student.email)}</small></div></div></td>
  <td>${escapeHtml(student.program)}</td>
  <td>${escapeHtml(student.year)}</td>
  <td>${escapeHtml(student.contact)}</td>
  <td>${badge(student.status)}</td>
  <td>${escapeHtml(student.date)}</td>
  <td>${rowActions(actions)}</td>
</tr>`;
}

export default {
  async render() {
    const addButton = auth.can(PERMISSIONS.STUDENTS_MANAGE)
      ? `<button class="btn btn-primary" data-link="students/new"><i class="icon icon-plus"></i>Add Student</button>`
      : "";

    return (
      pageHeader("Students", "Manage student profiles and academic information.", addButton) +
      `<div class="card table-card">
        ${tableTools({ searchId: "studentSearch", searchPlaceholder: "Search students...", filters: filtersMarkup() })}
        <div id="studentsTable">${dataTable({
          columns: ["Student ID", "Student", "Program", "Year Level", "Contact", "Status", "Date Registered", "Actions"],
          rows: [],
          emptyHtml: "",
        })}</div>
        <div id="studentsPagination"></div>
      </div>`
    );
  },

  mount({ navigate }) {
    const content = qs("#content");
    const tableHost = qs("#studentsTable");
    const paginationHost = qs("#studentsPagination");
    let timer = null;

    async function refresh() {
      try {
        const { students, meta } = await studentService.list(state);

        tableHost.innerHTML = dataTable({
          columns: ["Student ID", "Student", "Program", "Year Level", "Contact", "Status", "Date Registered", "Actions"],
          rows: students.map(studentRow),
          emptyHtml: emptyState({
            title: "No students found.",
            action: `<button class="btn btn-small" data-action="reset-filters">Clear Filters</button>`,
          }),
        });

        paginationHost.innerHTML = pagination({ page: meta.page, perPage: meta.perPage, total: meta.total });
      } catch (error) {
        tableHost.innerHTML = errorState(error.message, { retry: false });
        paginationHost.innerHTML = "";
      }
    }

    function resetFilters() {
      Object.assign(state, { search: "", program: "", year: "", status: "", sort: "", page: 1 });
      qs("#studentSearch").value = "";
      ["programFilter", "yearFilter", "statusFilter", "sortFilter"].forEach((name) => {
        const element = qs(`[name="${name}"]`);
        if (element) element.value = "";
      });
      refresh();
    }

    function openEdit(id) {
      studentService.find(id).then((student) => {
        openModal({
          title: "Edit Student",
          size: "compact",
          body: `<div class="form-grid">
            ${field({ label: "Student ID", name: "id", value: student.id })}
            ${field({ label: "First Name", name: "firstName", value: student.firstName, required: true })}
            ${field({ label: "Middle Name", name: "middleName", value: student.middleName })}
            ${field({ label: "Last Name", name: "lastName", value: student.lastName, required: true })}
            ${field({ label: "Suffix", name: "suffix", value: student.suffix })}
            ${field({ label: "Email", name: "email", type: "email", value: student.email })}
            ${field({ label: "Phone Number", name: "phone", placeholder: "09XX XXX XXXX", value: student.contact, inputmode: "numeric" })}
            ${selectField({ label: "Status", name: "status", options: STUDENT_STATUSES, value: student.status })}
          </div>`,
          footer: `<button class="btn" data-close-modal>Cancel</button>
            <button class="btn btn-primary" data-save>Save Changes</button>`,
          onMount(root) {
            root.querySelector(`[name="id"]`).readOnly = true;
            bindPhoneInput(root.querySelector(`[name="phone"]`));
            root.querySelector("[data-save]").addEventListener("click", async (event) => {
              const button = event.currentTarget;
              button.disabled = true;
              try {
                const values = {
                  firstName: root.querySelector(`[name="firstName"]`).value,
                  middleName: root.querySelector(`[name="middleName"]`).value,
                  lastName: root.querySelector(`[name="lastName"]`).value,
                  suffix: root.querySelector(`[name="suffix"]`).value,
                  email: root.querySelector(`[name="email"]`).value,
                  phone: root.querySelector(`[name="phone"]`).value,
                  status: root.querySelector(`[name="status"]`).value,
                };
                const { message } = await studentService.update(id, values);
                closeModal();
                notify.success(message);
                refresh();
              } catch (error) {
                notify.error(error.message);
              } finally {
                button.disabled = false;
              }
            });
          },
        });
      });
    }

    function openDelete(id) {
      confirmModal({
        title: "Delete Student",
        message: `<p><strong>Are you sure you want to delete student ${escapeHtml(id)}?</strong></p>
          <p style="color:#667085">This action cannot be easily undone.</p>`,
        confirmLabel: "Delete",
        async onConfirm() {
          try {
            notify.success(await studentService.remove(id));
            state.page = 1;
            refresh();
          } catch (error) {
            notify.error(error.message);
          }
        },
      });
    }

    const unsubscribe = [
      delegate(content, '[data-link="students/new"]', "click", () => openStudentForm(refresh)),
      delegate(content, "[data-view]", "click", (_e, element) =>
        openStudentProfile(element.dataset.view, navigate, () => openEdit(element.dataset.view))
      ),
      delegate(content, "[data-edit]", "click", (_e, element) => openEdit(element.dataset.edit)),
      delegate(content, "[data-delete]", "click", (_e, element) => openDelete(element.dataset.delete)),
      delegate(content, `[data-action="reset-filters"]`, "click", resetFilters),
      delegate(paginationHost, "[data-page]", "click", (_e, element) => {
        state.page = Number(element.dataset.page);
        refresh();
      }),
      on(qs("#studentSearch"), "input", (event) => {
        clearTimeout(timer);
        timer = setTimeout(() => {
          state.search = event.target.value.trim();
          state.page = 1;
          refresh();
        }, 250);
      }),
      delegate(content, ".filters select", "change", (event) => {
        const map = { programFilter: "program", yearFilter: "year", statusFilter: "status", sortFilter: "sort" };
        state[map[event.target.name]] = event.target.value;
        state.page = 1;
        refresh();
      }),
    ];

    // Allow the global search box to drive this page.
    const pending = sessionStorage.getItem("pendingStudentSearch");
    if (pending) {
      sessionStorage.removeItem("pendingStudentSearch");
      state.search = pending;
      qs("#studentSearch").value = pending;
    }

    refresh();
    return () => {
      clearTimeout(timer);
      unsubscribe.forEach((off) => off());
    };
  },
};
