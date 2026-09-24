/**
 * Reports & Analytics.
 *
 * This is the module extended with Laravel (see laravel-module/). The page
 * talks to `reportService`, which points at `appConfig.reportsApiBaseUrl`, so
 * moving it between the native API and the Laravel app is a config change.
 */
import { pageHeader } from "../components/pageHeader.js";
import { field, selectField } from "../components/formFields.js";
import { errorState } from "../components/states.js";
import { notify } from "../components/toast.js";
import { qs, escapeHtml, delegate, formValues } from "../core/dom.js";
import { ACADEMIC_YEARS, SEMESTERS, PROGRAMS, YEAR_LEVELS } from "../config/options.js";
import { reportService } from "../services/reportService.js";

function reportCard(report) {
  return `<div class="card report-card">
  <div class="report-icon"><i class="icon icon-${escapeHtml(report.icon)}"></i></div>
  <h3>${escapeHtml(report.title)}</h3>
  <p>${escapeHtml(report.description)}</p>
  <button class="btn btn-small" data-report="${escapeHtml(report.type)}">Generate</button>
</div>`;
}

export default {
  async render() {
    let catalog = [];
    let failure = null;

    try {
      catalog = await reportService.catalog();
    } catch (error) {
      failure = error.message;
    }

    return (
      pageHeader(
        "Reports & Analytics",
        "Generate administrative reports from enrollment and academic records.",
        `<button class="btn" data-action="print"><i class="icon icon-printer"></i>Print</button>
          <button class="btn btn-primary" data-report="enrollment"><i class="icon icon-file-chart-column"></i>Generate Report</button>`
      ) +
      `<div class="card" style="margin-bottom:18px"><div class="card-body">
        <form id="reportFilters"><div class="form-grid">
          ${selectField({ label: "Academic Year", name: "academicYear", options: ACADEMIC_YEARS })}
          ${selectField({ label: "Semester", name: "semester", options: SEMESTERS })}
          ${selectField({ label: "Program", name: "program", options: PROGRAMS, placeholder: "All Programs" })}
          ${selectField({ label: "Year Level", name: "yearLevel", options: YEAR_LEVELS, placeholder: "All Year Levels" })}
          ${field({ label: "Date Range", name: "dateRange", placeholder: "MM/DD/YYYY – MM/DD/YYYY" })}
        </div></form>
      </div></div>` +
      (failure
        ? errorState(`Reports module unavailable: ${failure}`)
        : `<div class="report-grid">${catalog.map(reportCard).join("")}</div>`)
    );
  },

  mount() {
    const content = qs("#content");
    const offs = [
      delegate(content, `[data-action="print"]`, "click", () => window.print()),
      delegate(content, "[data-report]", "click", async (_event, element) => {
        element.disabled = true;
        try {
          const filters = formValues(qs("#reportFilters"));
          const { message } = await reportService.generate(element.dataset.report, filters);
          notify.success(message);
        } catch (error) {
          notify.error(error.message);
        } finally {
          element.disabled = false;
        }
      }),
    ];
    return () => offs.forEach((off) => off());
  },
};
