

import { pageHeader } from "../components/pageHeader.js";
import { badge } from "../components/badge.js";
import { avatar } from "../components/avatar.js";
import { dataTable } from "../components/table.js";
import { emptyState } from "../components/states.js";
import { notify } from "../components/toast.js";
import { qs, escapeHtml, delegate, on } from "../core/dom.js";
import { appConfig } from "../config/app.config.js";
import { studentService } from "../services/studentService.js";
import { enrollmentService, createSelection } from "../services/enrollmentService.js";

const COLUMNS = ["Subject Code", "Subject", "Instructor", "Schedule", "Units", "Slots", "Action"];

let loadedOfferings = [];

function offeringRow(offering, selected) {
  return `<tr>
  <td><strong>${escapeHtml(offering.code)}</strong></td>
  <td>${escapeHtml(offering.subject)}</td>
  <td>${escapeHtml(offering.instructor)}</td>
  <td>${escapeHtml(offering.schedule)}</td>
  <td>${escapeHtml(offering.units ?? 3)}</td>
  <td>${offering.enrolled}/${offering.slots}</td>
  <td><button class="btn btn-small btn-primary" data-add="${escapeHtml(offering.id)}"${
    selected ? " disabled" : ""
  }>${selected ? "Added" : "Add"}</button></td>
</tr>`;
}

function studentSummary(student) {
  if (!student) {
    return `<div class="student-summary"><div class="avatar">—</div>
      <div><strong>No student selected</strong>
      <small style="display:block;color:#667085;margin-top:4px">Search for a Student ID or name to begin.</small></div></div>`;
  }
  return `<div class="student-summary">${avatar(student.name)}
    <div><strong>${escapeHtml(student.name)}</strong>
    <small style="display:block;color:#667085;margin-top:4px">${escapeHtml(student.id)} · ${escapeHtml(
    student.program
  )} · ${escapeHtml(student.year)}</small></div></div>`;
}

export default {
  async render() {
    loadedOfferings = await enrollmentService.openOfferings();
    const offerings = loadedOfferings;

    return (
      pageHeader("Enroll Student", "Select a student and available course offerings to create an enrollment.") +
      `<div class="enroll-layout">
  <div>
    <div class="card"><div class="card-header"><h3>Student Selection</h3></div>
      <div class="card-body">
        <div class="student-picker">
          <div class="search-box" style="flex:1"><i class="icon icon-search"></i>
            <input id="enrollSearch" placeholder="Search Student ID or Name" aria-label="Search student"></div>
          <button class="btn" id="enrollSearchBtn">Search</button>
        </div>
        <div id="selectedStudent">${studentSummary(null)}</div>
      </div>
    </div>

    <div class="card offering-table">
      <div class="card-header"><h3>Available Course Offerings</h3>
        <span>${escapeHtml(appConfig.defaults.academicYear)} · ${escapeHtml(appConfig.defaults.semester)}</span></div>
      <div id="offeringsHost">${dataTable({
        columns: COLUMNS,
        rows: offerings.map((offering) => offeringRow(offering, false)),
        emptyHtml: emptyState({ title: "No open offerings.", description: "All course offerings are currently full." }),
      })}</div>
    </div>
  </div>

  <div class="card summary-card">
    <div class="card-header"><h3>Enrollment Summary</h3>${badge("Pending")}</div>
    <div class="card-body">
      <div style="color:#667085;font-size:11px">Student</div>
      <strong style="display:block;margin:5px 0 16px" id="summaryStudent">—</strong>
      <div class="summary-line"><span>Semester</span><strong>${escapeHtml(appConfig.defaults.semester)}</strong></div>
      <div class="summary-line"><span>Subjects</span><strong id="courseCount">0</strong></div>
      <div class="summary-subjects" id="summarySubjects"><span class="summary-subjects-empty">No subjects added yet.</span></div>
      <div class="summary-line"><span>Total Units</span><strong id="unitCount">0</strong></div>
      <button class="btn btn-primary summary-confirm" id="confirmEnrollment">
        Confirm Enrollment</button>
    </div>
  </div>
</div>`
    );
  },

  mount() {
    const content = qs("#content");
    const selection = createSelection();
    let student = null;
    const offerings = loadedOfferings;

    function updateSummary() {
      const totals = selection.totals();
      const subjectList = qs("#summarySubjects");
      qs("#courseCount").textContent = totals.count;
      qs("#unitCount").textContent = totals.units;
      qs("#summaryStudent").textContent = student ? student.name : "—";
      subjectList.innerHTML = selection.items().length
        ? selection.items()
            .map(
              (offering) =>
                `<div class="summary-subject"><strong>${escapeHtml(offering.code)}</strong><span>${escapeHtml(
                  offering.subject
                )}</span></div>`
            )
            .join("")
        : `<span class="summary-subjects-empty">No subjects added yet.</span>`;
    }

    async function searchStudent() {
      const term = qs("#enrollSearch").value.trim();
      if (!term) return notify.error("Enter a Student ID or name to search.");

      try {
        const { students } = await studentService.list({ search: term, perPage: 1 });
        student = students[0] ?? null;
        qs("#selectedStudent").innerHTML = studentSummary(student);
        if (!student) notify.error("No student matched that search.");
        updateSummary();
      } catch (error) {
        notify.error(error.message);
      }
    }

    const offs = [
      on(qs("#enrollSearchBtn"), "click", searchStudent),
      on(qs("#enrollSearch"), "keydown", (event) => {
        if (event.key === "Enter") {
          event.preventDefault();
          searchStudent();
        }
      }),
      delegate(content, "[data-add]", "click", (_event, element) => {
        const offering = offerings.find((item) => item.id === element.dataset.add);
        if (!offering) return;
        selection.add(offering);
        element.textContent = "Added";
        element.disabled = true;
        updateSummary();
      }),
      on(qs("#confirmEnrollment"), "click", async (event) => {
        const button = event.currentTarget;
        button.disabled = true;
        try {
          const { message } = await enrollmentService.create({ studentId: student?.id, selection });
          notify.success(message);
          content.querySelectorAll("[data-add]").forEach((element) => {
            element.textContent = "Add";
            element.disabled = false;
          });
          updateSummary();
        } catch (error) {
          notify.error(error.message);
        } finally {
          button.disabled = false;
        }
      }),
    ];

    return () => offs.forEach((off) => off());
  },
};
