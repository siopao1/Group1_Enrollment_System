
import { pageHeader } from "../components/pageHeader.js";
import { badge } from "../components/badge.js";
import { dataTable, tableTools, rowActions } from "../components/table.js";
import { emptyState, errorState } from "../components/states.js";
import { field, selectField, filterSelect } from "../components/formFields.js";
import { openModal, closeModal } from "../components/modal.js?v=20260919-2";
import { notify } from "../components/toast.js";
import { qs, escapeHtml, delegate, formValues } from "../core/dom.js";
import { auth } from "../core/auth.js";
import { PERMISSIONS } from "../config/roles.js";
import { ACADEMIC_YEARS, SEMESTERS } from "../config/options.js";
import { catalogService } from "../services/catalogService.js";

const COLUMNS = ["Subject", "Instructor", "Schedule", "Room", "Slots", "Enrollment", "Status", "Actions"];
const state = { status: "" };

function offeringRow(offering) {
  const percentage = Math.round((offering.enrolled / offering.slots) * 100);
  const actions = auth.can(PERMISSIONS.OFFERINGS_MANAGE)
    ? rowActions([
        { icon: "pencil", title: "Edit", attr: `data-edit="${escapeHtml(offering.id)}"` },
        { icon: "trash-2", title: "Delete", attr: `data-delete="${escapeHtml(offering.id)}"` },
      ])
    : "";

  return `<tr>
  <td><strong>${escapeHtml(offering.code)}</strong>
    <small style="display:block;color:#667085;margin-top:3px">${escapeHtml(offering.subject)}</small></td>
  <td>${escapeHtml(offering.instructor)}</td>
  <td>${escapeHtml(offering.schedule)}</td>
  <td>${escapeHtml(offering.room)}</td>
  <td>${escapeHtml(offering.slots)}</td>
  <td style="min-width:150px"><strong>${offering.enrolled} / ${offering.slots}</strong>
    <div class="progress" style="margin-top:7px"><span style="width:${percentage}%"></span></div>
    <small style="color:#667085">${offering.slots - offering.enrolled} slots remaining</small></td>
  <td>${badge(offering.status)}</td>
  <td>${actions}</td>
</tr>`;
}

export default {
  async render() {
    const addButton = auth.can(PERMISSIONS.OFFERINGS_MANAGE)
      ? `<button class="btn btn-primary" data-action="add-offering"><i class="icon icon-plus"></i>Add Course Offering</button>`
      : "";

    return (
      pageHeader(
        "Course Offerings",
        "Manage subjects, schedules, instructors, rooms, and available slots.",
        addButton
      ) +
      `<div class="card table-card">
        ${tableTools({
          filters:
            filterSelect({ name: "yearFilter", options: ACADEMIC_YEARS, placeholder: "Academic Year", width: "185px" }) +
            filterSelect({ name: "semesterFilter", options: SEMESTERS, placeholder: "All Semesters", width: "165px" }) +
            filterSelect({ name: "statusFilter", options: ["Open", "Full"], placeholder: "All Statuses", width: "150px" }),
        })}
        <div id="offeringsTable"></div>
      </div>`
    );
  },

  mount() {
    const content = qs("#content");
    const host = qs("#offeringsTable");

    async function refresh() {
      try {
        const offerings = await catalogService.offerings(state);
        host.innerHTML = dataTable({
          columns: COLUMNS,
          rows: offerings.map(offeringRow),
          emptyHtml: emptyState({ title: "No course offerings found." }),
        });
      } catch (error) {
        host.innerHTML = errorState(error.message);
      }
    }

    function openForm() {
      catalogService.subjects().then((subjects) => {
        openModal({
          title: "Add Course Offering",
          body: `<form id="offeringForm"><div class="form-grid">
            ${selectField({
              label: "Subject",
              name: "subject",
              options: subjects.map((s) => ({ value: s.code, label: `${s.code} — ${s.name}` })),
              placeholder: "Select subject",
              required: true,
            })}
            ${field({ label: "Instructor", name: "instructor", placeholder: "Instructor name", required: true })}
            ${selectField({ label: "Academic Year", name: "academicYear", options: ACADEMIC_YEARS })}
            ${selectField({ label: "Semester", name: "semester", options: SEMESTERS })}
            ${field({ label: "Schedule", name: "schedule", placeholder: "MWF 9:00–10:00" })}
            ${field({ label: "Room", name: "room", placeholder: "Room / Lab" })}
            ${field({ label: "Maximum Slots", name: "slots", placeholder: "40", value: "40", required: true })}
          </div></form>`,
          footer: `<button class="btn" data-close-modal>Cancel</button>
            <button class="btn btn-primary" data-save>Save</button>`,
          onMount(root) {
            root.querySelector("[data-save]").addEventListener("click", async (event) => {
              event.currentTarget.disabled = true;
              try {
                const values = formValues(root.querySelector("#offeringForm"));
                const subject = subjects.find((s) => s.code === values.subject);
                const message = await catalogService.saveOffering({
                  ...values,
                  code: values.subject,
                  subject: subject?.name ?? values.subject,
                  units: subject?.units ?? 3,
                });
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
      });
    }

    const offs = [
      delegate(content, `[data-action="add-offering"]`, "click", openForm),
      delegate(content, `[name="statusFilter"]`, "change", (event) => {
        state.status = event.target.value;
        refresh();
      }),
    ];

    refresh();
    return () => offs.forEach((off) => off());
  },
};
