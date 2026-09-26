
import { pageHeader } from "../components/pageHeader.js";
import { badge } from "../components/badge.js";
import { dataTable, tableTools, rowActions } from "../components/table.js";
import { emptyState, errorState } from "../components/states.js";
import { field, textareaField, selectField, filterSelect } from "../components/formFields.js";
import { openModal, closeModal, confirmModal } from "../components/modal.js?v=20260919-2";
import { notify } from "../components/toast.js";
import { qs, escapeHtml, delegate, on, formValues } from "../core/dom.js";
import { auth } from "../core/auth.js";
import { PERMISSIONS } from "../config/roles.js";
import { DEPARTMENTS } from "../config/options.js";
import { catalogService } from "../services/catalogService.js";

const COLUMNS = ["Subject Code", "Subject Name", "Description", "Units", "Department", "Status", "Actions"];
const state = { search: "", department: "" };

function subjectRow(subject) {
  const actions = auth.can(PERMISSIONS.SUBJECTS_MANAGE)
    ? rowActions([
        { icon: "pencil", title: "Edit", attr: `data-edit="${escapeHtml(subject.code)}"` },
        { icon: "trash-2", title: "Delete", attr: `data-delete="${escapeHtml(subject.code)}"` },
      ])
    : "";

  return `<tr>
  <td><strong>${escapeHtml(subject.code)}</strong></td>
  <td>${escapeHtml(subject.name)}</td>
  <td>${escapeHtml(subject.description)}</td>
  <td>${escapeHtml(subject.units)}</td>
  <td>${escapeHtml(subject.department)}</td>
  <td>${badge(subject.status)}</td>
  <td>${actions}</td>
</tr>`;
}

function subjectForm(subject = {}) {
  return `<form id="subjectForm"><div class="form-grid">
  ${field({ label: "Subject Code", name: "code", placeholder: "IT 303", value: subject.code || "", required: true })}
  ${field({ label: "Subject Name", name: "name", placeholder: "Subject name", value: subject.name || "", required: true })}
  ${textareaField({ label: "Description", name: "description", placeholder: "Subject description", value: subject.description || "" })}
  ${field({ label: "Units", name: "units", placeholder: "3", value: subject.units ?? "3", required: true })}
  ${selectField({ label: "Department", name: "department", options: DEPARTMENTS, value: subject.department || "" })}
  ${field({ label: "Prerequisite", name: "prerequisite", placeholder: "Optional", value: subject.prerequisite || "" })}
  ${selectField({ label: "Status", name: "status", options: ["Active", "Inactive"], value: subject.status || "Active" })}
</div></form>`;
}

export default {
  async render() {
    const addButton = auth.can(PERMISSIONS.SUBJECTS_MANAGE)
      ? `<button class="btn btn-primary" data-action="add-subject"><i class="icon icon-plus"></i>Add Subject</button>`
      : "";

    return (
      pageHeader("Subjects", "Manage the academic subject catalog.", addButton) +
      `<div class="card table-card">
        ${tableTools({
          searchId: "subjectSearch",
          searchPlaceholder: "Search subject code or name...",
          filters:
            filterSelect({ name: "departmentFilter", options: DEPARTMENTS, placeholder: "All Departments", width: "190px" }) +
            `<button class="btn btn-small" data-action="reset-filters">Reset</button>`,
        })}
        <div id="subjectsTable"></div>
      </div>`
    );
  },

  mount() {
    const content = qs("#content");
    const host = qs("#subjectsTable");
    let timer = null;

    async function refresh() {
      try {
        const subjects = await catalogService.subjects(state);
        host.innerHTML = dataTable({
          columns: COLUMNS,
          rows: subjects.map(subjectRow),
          emptyHtml: emptyState({ title: "No subjects found." }),
        });
      } catch (error) {
        host.innerHTML = errorState(error.message);
      }
    }

    function openForm(subject = {}, code = null) {
      openModal({
        title: code ? "Edit Subject" : "Add Subject",
        body: subjectForm(subject),
        footer: `<button class="btn" data-close-modal>Cancel</button>
          <button class="btn btn-primary" data-save>Save</button>`,
        onMount(root) {
          root.querySelector("[data-save]").addEventListener("click", async (event) => {
            event.currentTarget.disabled = true;
            try {
              const message = await catalogService.saveSubject(formValues(root.querySelector("#subjectForm")), code);
              closeModal();
              notify.success(message);
              refresh();
            } catch (error) {
              notify.error(error.message);
              event.currentTarget.disabled = false;
            }
          });
        },
      });
    }

    const offs = [
      delegate(content, `[data-action="add-subject"]`, "click", () => openForm()),
      delegate(content, "[data-edit]", "click", async (_e, element) => {
        const subjects = await catalogService.subjects({ search: element.dataset.edit });
        openForm(subjects.find((s) => s.code === element.dataset.edit) || {}, element.dataset.edit);
      }),
      delegate(content, "[data-delete]", "click", (_e, element) =>
        confirmModal({
          title: "Delete Subject",
          message: `<p><strong>Delete ${escapeHtml(element.dataset.delete)}?</strong></p>
            <p style="color:#667085">Course offerings that use this subject must be reassigned.</p>`,
          confirmLabel: "Delete",
          async onConfirm() {
            try {
              notify.success(await catalogService.removeSubject(element.dataset.delete));
              refresh();
            } catch (error) {
              notify.error(error.message);
            }
          },
        })
      ),
      delegate(content, `[data-action="reset-filters"]`, "click", () => {
        state.search = "";
        state.department = "";
        qs("#subjectSearch").value = "";
        const select = qs(`[name="departmentFilter"]`);
        if (select) select.value = "";
        refresh();
      }),
      delegate(content, `[name="departmentFilter"]`, "change", (event) => {
        state.department = event.target.value;
        refresh();
      }),
      on(qs("#subjectSearch"), "input", (event) => {
        clearTimeout(timer);
        timer = setTimeout(() => {
          state.search = event.target.value.trim();
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
