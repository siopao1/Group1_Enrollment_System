
import { pageHeader } from "../components/pageHeader.js";
import { statCard } from "../components/statCard.js";
import { badge } from "../components/badge.js";
import { dataTable, rowActions } from "../components/table.js";
import { emptyState } from "../components/states.js";
import { escapeHtml, delegate } from "../core/dom.js";
import { openStudentProfileDialog } from "../components/studentProfileDialog.js";
import { notify } from "../components/toast.js";
import { dashboardService } from "../services/dashboardService.js";
import { studentService } from "../services/studentService.js";

const DONUT_TONE_COLORS = {
  "": "#c62828",
  blue: "#2f7fe0",
  gold: "#d9a441",
  gray: "#b7aeea",
  light: "#e7e4f7",
};

function donutGradient(byProgram) {
  if (byProgram.length === 0) {
    return "#f0ece2";
  }

  let cumulative = 0;
  const stops = byProgram.map((row, index) => {
    const color = DONUT_TONE_COLORS[row.tone] || DONUT_TONE_COLORS[""];
    const start = cumulative;

    cumulative = index === byProgram.length - 1 ? 100 : cumulative + row.share;
    return `${color} ${start}% ${cumulative}%`;
  });

  return `conic-gradient(${stops.join(", ")})`;
}

function programBreakdown(byProgram) {
  return `<div class="card program-breakdown"><div class="card-header"><h3>Students by Program</h3><span>Current</span></div>
  <div class="card-body"><div class="donut-wrap"><div class="donut" style="background:${donutGradient(
    byProgram
  )}"></div><div class="legend">
    ${byProgram
      .map(
        (row) =>
          `<div class="legend-row"><i class="dot ${escapeHtml(row.tone)}"></i>${escapeHtml(
            row.label
          )} <strong>${row.share}%</strong></div>`
      )
      .join("")}
  </div></div></div></div>`;
}

function statusBreakdown(breakdown) {
  return `<div class="card" style="margin-bottom:18px">
  <div class="card-header"><h3>Student Status</h3><span>${escapeHtml(
    String(breakdown.total)
  )} total students</span></div>
  <div class="card-body"><div class="status-grid">
    ${breakdown.items
      .map(
        (item) =>
          `<div class="status-box">${badge(item.status)}<strong>${escapeHtml(
            String(item.count)
          )}</strong><span>${escapeHtml(item.note)}</span></div>`
      )
      .join("")}
  </div></div></div>`;
}

function recentlyRegisteredStudents(rows) {
  const body = rows.map(
    (row) => `<tr>
    <td><strong>${escapeHtml(row.id)}</strong></td>
    <td>${escapeHtml(row.name)}</td>
    <td>${escapeHtml(row.program)}</td>
    <td>${escapeHtml(row.year)}</td>
    <td>${escapeHtml(row.date)}</td>
    <td>${badge(row.status)}</td>
    <td>${rowActions([{ icon: "eye", title: "View student", attr: `data-view-student="${escapeHtml(row.id)}"` }])}</td>
  </tr>`
  );

  return `<div class="card table-card">
  <div class="card-header"><h3>Recently Registered Students</h3>
    <button class="btn btn-small" data-link="students">View all</button></div>
  ${dataTable({
    columns: ["Student ID", "Student Name", "Program", "Year Level", "Date Registered", "Status", "Actions"],
    rows: body,
    emptyHtml: emptyState({ title: "No students yet.", description: "Newly registered students will appear here." }),
  })}
</div>`;
}

export default {
  async render() {
    const [summary, recent] = await Promise.all([
      dashboardService.summary(),
      studentService.list({ sort: "newest_first", page: 1, perPage: 5 }),
    ]);

    return (
      pageHeader("Dashboard", "Overview of student enrollment and academic records.") +
      `<div class="stats">${summary.stats.map(statCard).join("")}</div>
      ${programBreakdown(summary.byProgram)}` +
      statusBreakdown(summary.statusBreakdown) +
      recentlyRegisteredStudents(recent.students)
    );
  },

  mount({ navigate }) {
    const content = document.querySelector("#content");
    const offs = [
      delegate(content, "[data-link]", "click", (_event, element) => navigate(element.dataset.link)),
      delegate(content, "[data-view-student]", "click", async (_event, element) => {
        try {
          await openStudentProfileDialog(element.dataset.viewStudent, navigate);
        } catch (error) {
          notify.error(error.message);
        }
      }),
    ];
    return () => offs.forEach((off) => off());
  },
};
