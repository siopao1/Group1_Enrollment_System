import { openModal, closeModal } from "./modal.js";
import { badge } from "./badge.js";
import { initials } from "./avatar.js";
import { infoItem } from "./formFields.js";
import { escapeHtml } from "../core/dom.js";
import { auth } from "../core/auth.js";
import { PERMISSIONS } from "../config/roles.js";
import { studentService } from "../services/studentService.js";

function overviewTab(student) {
  return `<div class="card"><div class="card-body"><div class="info-grid">
    ${infoItem("Student ID", student.id)}
    ${infoItem("Program", student.program)}
    ${infoItem("Year Level", student.year)}
    ${infoItem("Academic Status", student.status)}
    ${infoItem("Email Address", student.email)}
    ${infoItem("Contact Number", student.contact)}
  </div></div></div>

  <div class="card" style="margin-top:18px">
    <div class="card-header"><h3>Personal Information</h3></div>
    <div class="card-body"><div class="info-grid">
      ${infoItem("Full Name", student.name)}
      ${infoItem("Gender", student.gender || "Not specified")}
      ${infoItem("Civil Status", student.civilStatus || "Not specified")}
      ${infoItem("Address", student.address)}
    </div></div>
  </div>`;
}

function profileMarkup(student) {
  return `<div class="profile-hero" style="margin-bottom:16px"><div class="profile-main">
    <div class="profile-avatar">${escapeHtml(initials(student.name))}</div>
    <div><h2>${escapeHtml(student.name)}</h2>
      <p>Student ID: ${escapeHtml(student.id)} · ${escapeHtml(student.program)} · ${escapeHtml(student.year)}</p>
      <div style="margin-top:9px">${badge(student.status)}</div></div>
  </div></div>

  <div class="tabs">
    <button class="tab active" type="button">Overview</button>
  </div>

  ${overviewTab(student)}`;
}

export async function openStudentProfileDialog(id, navigate, onEdit) {
  const student = await studentService.find(id);
  const canManage = auth.can(PERMISSIONS.STUDENTS_MANAGE);

  openModal({
    title: "Student Profile",
    body: profileMarkup(student),
    footer: `${
      canManage && onEdit ? `<button class="btn btn-primary" data-action="edit"><i class="icon icon-pencil"></i>Edit</button>` : ""
    }<button class="btn" data-close-modal>Close</button>`,
    onMount(root) {
      root.querySelector('[data-action="edit"]')?.addEventListener("click", () => {
        closeModal();
        onEdit?.();
      });
    },
  });
}
