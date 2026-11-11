import { http } from "../core/http.js";

async function summary() {
  const { data: real } = await http.get("api/dashboard");

  return {
    data: {
      stats: [
        { label: "TOTAL STUDENTS", value: String(real.totalStudents), icon: "users", trend: "Registered students" },
        { label: "ACTIVE STUDENTS", value: String(real.activeStudents), icon: "user-check", trend: "Currently active" },
        { label: "PENDING STUDENTS", value: String(real.pendingStudents), icon: "clock", trend: "Awaiting approval" },
        { label: "TOTAL PROGRAMS", value: String(real.totalPrograms), icon: "layers", trend: "Offered programs" },
        {
          label: "ENROLLED THIS TERM",
          value: String(real.term.enrolled),
          icon: "clipboard-check",
          trend: `${real.term.semester}, ${real.term.academicYear}`,
        },
        {
          label: "SECTIONS FULL",
          value: `${real.term.sectionsFull} / ${real.term.sections}`,
          icon: "layout-grid",
          trend: "Course offerings at capacity",
          direction: real.term.sectionsFull > 0 ? "down" : "up",
        },
      ],
      byProgram: real.byProgram,
      statusBreakdown: real.statusBreakdown,
    },
  };
}

export const dashboardApi = {
  summary,
};

export default dashboardApi;
