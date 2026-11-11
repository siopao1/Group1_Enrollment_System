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
