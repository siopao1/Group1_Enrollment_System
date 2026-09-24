/**
 * TEMPORARY in-memory dataset.
 *
 * This file (and the rest of `mock/`) exists only until the PHP API is
 * available. Its shape deliberately matches `database/migrations/*.sql` so the
 * real API can drop in without touching any page, service or api module.
 *
 * To remove the mock layer:
 *   1. set `useMockApi: false` in js/config/app.config.js
 *   2. delete the js/mock folder and its import in js/main.js
 */
export const mockDatabase = {
  users: [
    { id: 1, username: "admin", name: "Alex Domingo", role: "admin", email: "admin@university.edu" },
    { id: 2, username: "registrar", name: "Rita Gutierrez", role: "registrar", email: "registrar@university.edu" },
    { id: 3, username: "faculty", name: "Prof. Santos", role: "faculty", email: "santos@university.edu" },
  ],

  programs: [
    { code: "BSIT", name: "BS Information Technology" },
    { code: "BSCS", name: "BS Computer Science" },
    { code: "BSBA", name: "BS Business Administration" },
    { code: "BSED", name: "BS Education" },
  ],

  students: [
    { id: "2026-00125", name: "Juan Dela Cruz", email: "juan.dela@email.com", program: "BS Information Technology", year: "3rd Year", contact: "0917 123 4567", status: "Active", date: "Sept. 15, 2026", gender: "Male" },
    { id: "2026-00124", name: "Maria Santos", email: "maria.santos@email.com", program: "BS Computer Science", year: "2nd Year", contact: "0918 222 3344", status: "Active", date: "Sept. 15, 2026", gender: "Female" },
    { id: "2026-00123", name: "Angela Reyes", email: "angela.reyes@email.com", program: "BS Business Administration", year: "4th Year", contact: "0919 445 5566", status: "Active", date: "Sept. 14, 2026", gender: "Female" },
    { id: "2026-00122", name: "Carlos Mendoza", email: "carlos.mendoza@email.com", program: "BS Education", year: "1st Year", contact: "0920 667 7788", status: "Pending", date: "Sept. 14, 2026", gender: "Male" },
    { id: "2026-00121", name: "Sofia Garcia", email: "sofia.garcia@email.com", program: "BS Information Technology", year: "3rd Year", contact: "0921 889 9900", status: "Active", date: "Sept. 13, 2026", gender: "Female" },
    { id: "2026-00120", name: "Miguel Torres", email: "miguel.torres@email.com", program: "BS Computer Science", year: "4th Year", contact: "0922 111 2233", status: "Completed", date: "Sept. 13, 2026", gender: "Male" },
    { id: "2026-00119", name: "Patricia Cruz", email: "patricia.cruz@email.com", program: "BS Education", year: "2nd Year", contact: "0923 334 4455", status: "Active", date: "Sept. 12, 2026", gender: "Female" },
    { id: "2026-00118", name: "Daniel Flores", email: "daniel.flores@email.com", program: "BS Business Administration", year: "3rd Year", contact: "0924 556 6677", status: "Dropped", date: "Sept. 12, 2026", gender: "Male" },
    { id: "2026-00117", name: "Nicole Ramos", email: "nicole.ramos@email.com", program: "BS Information Technology", year: "1st Year", contact: "0925 778 8899", status: "Active", date: "Sept. 11, 2026", gender: "Female" },
    { id: "2026-00116", name: "Mark Villanueva", email: "mark.villanueva@email.com", program: "BS Computer Science", year: "2nd Year", contact: "0926 990 0011", status: "Pending", date: "Sept. 11, 2026", gender: "Male" },
  ],

  // These 5 were fabricated sample records for pages that don't have a real
  // backend yet (Subjects, Offerings, Enrollment, Academic Records). Left
  // empty on purpose — the mock server still serves them, so those pages
  // load normally and show a real "no records" empty state instead of fake
  // data or a broken page.
  subjects: [],

  offerings: [],

  enrollments: [],

  grades: [],

  academicSummaries: {},

  dashboard: {
    stats: [
      { label: "TOTAL STUDENTS", value: "2,458", icon: "users", trend: "↑ 8.2% from last semester" },
      { label: "ACTIVE STUDENTS", value: "2,210", icon: "user-check", trend: "↑ 6.4% from last semester" },
      { label: "TOTAL SUBJECTS", value: "86", icon: "book-open", trend: "↑ 3 new subjects" },
      { label: "CURRENT ENROLLMENTS", value: "1,874", icon: "clipboard-list", trend: "↑ 12.5% from last semester" },
    ],
    enrollmentTrend: {
      caption: "June – October 2026",
      points: [
        { label: "Jun", value: 45 }, { label: "Jul", value: 62 }, { label: "Aug", value: 72 },
        { label: "Sep", value: 88 }, { label: "Oct", value: 96 },
      ],
    },
    byProgram: [
      { label: "BS Information Technology", share: 34, tone: "" },
      { label: "BS Computer Science", share: 24, tone: "blue" },
      { label: "BS Business Administration", share: 19, tone: "gold" },
      { label: "BS Education", share: 13, tone: "gray" },
      { label: "Other Programs", share: 10, tone: "light" },
    ],
    statusBreakdown: {
      total: "1,874",
      items: [
        { status: "Active", count: "1,532", note: "81.8% of current records" },
        { status: "Pending", count: "186", note: "9.9% awaiting confirmation" },
        { status: "Dropped", count: "76", note: "4.1% of records" },
        { status: "Completed", count: "80", note: "4.3% of records" },
      ],
    },
    totalStudentCount: 2458,
  },

  settings: {
    academicYear: "2026–2027",
    semester: "1st Semester",
    rowsPerPage: "10",
  },

  reports: [
    { type: "enrollment", title: "Enrollment Report", description: "Summary of enrollment transactions by period and status.", icon: "file-check" },
    { type: "demographics", title: "Student Demographics", description: "Student population breakdown by program, year, and gender.", icon: "users" },
    { type: "by-program", title: "Enrollment by Program", description: "Compare current enrollment across academic programs.", icon: "chart-no-axes-combined" },
    { type: "by-semester", title: "Enrollment by Semester", description: "Review enrollment trends across semesters and academic years.", icon: "calendar-days" },
    { type: "academic-records", title: "Academic Records Report", description: "Grades, completed units, and academic standing.", icon: "graduation-cap" },
    { type: "master-list", title: "Student Master List", description: "Printable master list of registered students.", icon: "list" },
  ],
};

export default mockDatabase;
