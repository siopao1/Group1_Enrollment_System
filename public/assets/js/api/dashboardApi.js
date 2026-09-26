

import { ApiError } from "../core/apiError.js";

async function summary() {
  let response;
  try {
    response = await fetch("/api/dashboard.php");
  } catch {
    throw new ApiError("Unable to reach the server. Check your connection.", { status: 0 });
  }

  const envelope = await response.json().catch(() => null);
  if (!envelope || typeof envelope !== "object") {
    throw new ApiError("The server returned an unexpected response.", { status: response.status });
  }
  if (!envelope.success) {
    throw new ApiError(envelope.message, { status: response.status });
  }

  const real = envelope.data;

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
