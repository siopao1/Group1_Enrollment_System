import { pageHeader } from "../components/pageHeader.js";
import { selectField } from "../components/formFields.js";
import { errorState } from "../components/states.js";
import { badge } from "../components/badge.js";
import { notify } from "../components/toast.js";
import { qs, qsa, escapeHtml, delegate, formValues } from "../core/dom.js";
import { settings } from "../core/settings.js";
import { ACADEMIC_YEARS, SEMESTERS, PROGRAMS, YEAR_LEVELS } from "../config/options.js";
import { reportService } from "../services/reportService.js";

const ICONS = {
  "enrollment-summary": "file-check",
  "enrollment-by-program": "chart-no-axes-combined",
  "enrollment-by-semester": "calendar-days",
  "section-utilization": "layout-grid",
  "student-demographics": "users",
  "student-master-list": "list",
};

const FILTER_NAMES = {
  academic_year: "Academic Year",
  semester: "Semester",
  program: "Program",
  year_level: "Year Level",
};

// Columns whose values are statuses get a coloured badge.
const BADGE_COLUMNS = new Set(["status", "flag"]);

// The last generated report, for Export CSV and Print.
let current = null;

function reportCard(report) {
  const uses = report.filters.map((f) => FILTER_NAMES[f]).join(" · ");
  return `<div class="card report-card" data-card="${escapeHtml(report.type)}">
  <div class="report-icon"><i class="icon icon-${escapeHtml(ICONS[report.type] || "file-text")}"></i></div>
  <h3>${escapeHtml(report.title)}</h3>
  <p>${escapeHtml(report.description)}</p>
  <p class="report-uses">Filters: ${escapeHtml(uses)}</p>
  <button class="btn btn-small" data-report="${escapeHtml(report.type)}">Generate</button>
</div>`;
}

function filtersForm(options) {
  const term = settings.get();
  return `<div class="card no-print" style="margin-bottom:18px"><div class="card-body">
    <form id="reportFilters"><div class="form-grid">
      ${selectField({
        label: "Academic Year",
        name: "academicYear",
        options: options.academic_year || ACADEMIC_YEARS,
        value: term.academicYear,
        placeholder: "All academic years",
      })}
      ${selectField({
        label: "Semester",
        name: "semester",
        options: options.semester || SEMESTERS,
        value: term.semester,
        placeholder: "All semesters",
      })}
      ${selectField({ label: "Program", name: "program", options: options.program || PROGRAMS, placeholder: "All programs" })}
      ${selectField({ label: "Year Level", name: "yearLevel", options: options.year_level || YEAR_LEVELS, placeholder: "All year levels" })}
    </div>
    <p class="report-hint">Defaults to the term set in Settings. A report uses only the filters listed on its card.</p></form>
  </div></div>`;
}

// ---------------------------------------------------------------- report view

function cell(column, value) {
  const text = value === null || value === undefined || value === "" ? "—" : String(value);
  if (BADGE_COLUMNS.has(column.key) && text !== "—") return badge(text);
  return escapeHtml(text);
}

function barChart(section) {
  const { label, value } = section.chart;
  const max = Math.max(1, ...section.rows.map((row) => Number(row[value]) || 0));

  return `<div class="hbar-chart" role="img" aria-label="${escapeHtml(section.title)} chart">
  ${section.rows
    .map((row) => {
      const amount = Number(row[value]) || 0;
      return `<div class="hbar-row">
    <span class="hbar-label">${escapeHtml(String(row[label]))}</span>
    <span class="hbar-track"><span class="hbar-fill" style="width:${((amount / max) * 100).toFixed(1)}%"></span></span>
    <span class="hbar-value">${escapeHtml(String(amount))}</span>
  </div>`;
    })
    .join("")}
</div>`;
}

function sectionTable(section) {
  const head = section.columns
    .map((c) => `<th${c.numeric ? ' class="num"' : ""}>${escapeHtml(c.label)}</th>`)
    .join("");
  const body = section.rows.length
    ? section.rows
        .map(
          (row) =>
            `<tr>${section.columns
              .map((c) => `<td${c.numeric ? ' class="num"' : ""}>${cell(c, row[c.key])}</td>`)
              .join("")}</tr>`
        )
        .join("")
    : `<tr><td colspan="${section.columns.length}" class="report-empty">No records match these filters.</td></tr>`;

  return `<div class="report-table-wrap"><table class="data-table report-table">
  <thead><tr>${head}</tr></thead><tbody>${body}</tbody></table></div>`;
}

function reportView(report) {
  const filters = report.filters
    .map((f) => `<span class="report-chip"><b>${escapeHtml(f.label)}:</b> ${escapeHtml(f.value)}</span>`)
    .join("");

  const summary = report.summary
    .map(
      (item) => `<div class="report-stat">
    <span>${escapeHtml(item.label)}</span><strong>${escapeHtml(String(item.value))}</strong></div>`
    )
    .join("");

  const sections = report.sections
    .map(
      (section) => `<div class="report-section">
    <h4>${escapeHtml(section.title)}</h4>
    ${section.chart && section.rows.length ? barChart(section) : ""}
    ${sectionTable(section)}
  </div>`
    )
    .join("");

  return `<div class="card report-output">
  <div class="card-header report-output-header">
    <div>
      <h3>${escapeHtml(report.title)}</h3>
      <span>Generated ${escapeHtml(report.generatedAt)} · University Enrollment &amp; Records System</span>
    </div>
    <button class="btn btn-small no-print" data-action="close-report" title="Close report"><i class="icon icon-x"></i></button>
  </div>
  <div class="card-body">
    <div class="report-chips">${filters}</div>
    <div class="report-stats">${summary}</div>
    ${sections}
  </div>
</div>`;
}

// ----------------------------------------------------------------------- page

function setOutputActions(enabled) {
  qsa("[data-action='print'], [data-action='export']").forEach((button) => {
    button.disabled = !enabled;
  });
}

export default {
  async render() {
    current = null;
    let catalog = null;
    let failure = null;

    try {
      catalog = await reportService.catalog();
    } catch (error) {
      failure = error.message;
    }

    return (
      pageHeader(
        "Reports & Analytics",
        "Enrollment and student reports from live records. Pick the filters, then generate a report.",
        `<button class="btn" data-action="export" disabled><i class="icon icon-download"></i>Export CSV</button>
         <button class="btn btn-primary" data-action="print" disabled><i class="icon icon-printer"></i>Print</button>`
      ) +
      (failure
        ? errorState(`Reports are unavailable: ${failure}`)
        : filtersForm(catalog.options) +
          `<div id="reportOutput"></div>
           <div class="report-grid no-print">${catalog.reports.map(reportCard).join("")}</div>`)
    );
  },

  mount() {
    const content = qs("#content");

    const generate = async (type, button) => {
      const form = qs("#reportFilters");
      const output = qs("#reportOutput");
      if (!form || !output) return;

      if (button) button.disabled = true;
      try {
        const values = formValues(form);
        const { report } = await reportService.generate(type, values);
        current = { type, values, report };

        output.innerHTML = reportView(report);
        qsa("[data-card]").forEach((card) => card.classList.toggle("selected", card.dataset.card === type));
        setOutputActions(true);
        output.scrollIntoView({ behavior: "smooth", block: "start" });
      } catch (error) {
        notify.error(error.message);
      } finally {
        if (button) button.disabled = false;
      }
    };

    const offs = [
      delegate(content, "[data-report]", "click", (_event, element) => generate(element.dataset.report, element)),

      // Changing a filter refreshes the report on screen.
      delegate(content, "#reportFilters select", "change", () => {
        if (current) generate(current.type);
      }),

      delegate(content, `[data-action="export"]`, "click", () => {
        if (!current) return;
        window.location.href = reportService.csvUrl(current.type, current.values);
      }),

      delegate(content, `[data-action="print"]`, "click", () => {
        if (current) window.print();
      }),

      delegate(content, `[data-action="close-report"]`, "click", () => {
        current = null;
        qs("#reportOutput").innerHTML = "";
        qsa("[data-card]").forEach((card) => card.classList.remove("selected"));
        setOutputActions(false);
      }),
    ];

    return () => {
      current = null;
      offs.forEach((off) => off());
    };
  },
};
