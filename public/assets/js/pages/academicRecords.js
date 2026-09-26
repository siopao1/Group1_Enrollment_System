
import { pageHeader } from "../components/pageHeader.js";
import { badge } from "../components/badge.js";
import { initials } from "../components/avatar.js";
import { infoItem } from "../components/formFields.js";
import { dataTable } from "../components/table.js";
import { emptyState, errorState } from "../components/states.js";
import { notify } from "../components/toast.js";
import { qs, escapeHtml, on } from "../core/dom.js";
import { studentService } from "../services/studentService.js";

const COLUMNS = ["Subject Code", "Subject", "Units", "Grade", "Semester", "Academic Year", "Remarks"];

function recordCard({ student, summary, grades }) {
  return `<div class="card">
  <div class="profile-hero"><div class="profile-main">
    <div class="profile-avatar">${escapeHtml(initials(student.name))}</div>
    <div><h2>${escapeHtml(student.name)}</h2>
      <p>${escapeHtml(student.id)} · ${escapeHtml(student.program)}</p></div>
  </div></div>
  <div class="card-body"><div class="info-grid academic-summary-grid">
    ${infoItem("GPA / General Average", summary.gpa)}
    ${infoItem("Completed Units", summary.completedUnits)}
    ${infoItem("Total Units", summary.totalUnits)}
    ${infoItem("Academic Standing", summary.standing)}
  </div></div>
  ${dataTable({
    columns: COLUMNS,
    rows: grades.map(
      (grade) => `<tr>
        <td><strong>${escapeHtml(grade.code)}</strong></td>
        <td>${escapeHtml(grade.subject)}</td>
        <td>${escapeHtml(grade.units)}</td>
        <td><strong>${escapeHtml(grade.grade)}</strong></td>
        <td>${escapeHtml(grade.semester)}</td>
        <td>${escapeHtml(grade.academicYear)}</td>
        <td>${badge(grade.remarks)}</td>
      </tr>`
    ),
    emptyHtml: emptyState({
      title: "No grades recorded yet.",
      description: "Grades appear once faculty submit them for the term.",
    }),
  })}
</div>`;
}

export default {
  async render() {
    return (
      pageHeader("Student Academic Records", "Search a student to review grades, units, and academic standing.") +
      `<div class="card" style="margin-bottom:18px"><div class="card-body">
        <div class="search-box" style="max-width:420px"><i class="icon icon-search"></i>
          <input id="recordSearch" placeholder="Search student..." aria-label="Search student"></div>
      </div></div>
      <div id="recordHost">${emptyState({
        title: "No student selected.",
        description: "Search for a Student ID or name to view their academic record.",
      })}</div>`
    );
  },

  mount() {
    const host = qs("#recordHost");
    let timer = null;

    async function search(term) {
      if (!term) {
        host.innerHTML = emptyState({
          title: "No student selected.",
          description: "Search for a Student ID or name to view their academic record.",
        });
        return;
      }

      try {
        const { students } = await studentService.list({ search: term, perPage: 1 });
        if (!students.length) {
          host.innerHTML = emptyState({ title: "No student matched that search." });
          return;
        }
        host.innerHTML = recordCard(await studentService.academicRecord(students[0].id));
      } catch (error) {
        host.innerHTML = errorState(error.message);
        notify.error(error.message);
      }
    }

    const off = on(qs("#recordSearch"), "input", (event) => {
      clearTimeout(timer);
      const term = event.target.value.trim();
      timer = setTimeout(() => search(term), 300);
    });

    return () => {
      clearTimeout(timer);
      off();
    };
  },
};
