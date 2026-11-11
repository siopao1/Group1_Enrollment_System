<?php

declare(strict_types=1);

/**
 * The SQL behind the six reports. Every query is a prepared statement; the
 * only things put into the SQL text are column names from the fixed lists in
 * this class, never request values.
 *
 * Filters (all optional, '' = no filter):
 *   academic_year, semester, program (program name), year_level
 *
 * An enrollment counts as "enrolled" unless its status is Dropped.
 */
final class ReportRepository
{
    /** Columns the demographics report may group students by. */
    private const STUDENT_GROUPS = [
        'gender'       => "COALESCE(NULLIF(d.gender, ''), 'Not specified')",
        'civil_status' => "COALESCE(NULLIF(d.civil_status, ''), 'Not specified')",
        'year_level'   => 's.year_level',
        'program'      => 'p.name',
        'status'       => 's.status',
        'age_group'    => "CASE
                WHEN d.date_of_birth IS NULL THEN 'Not specified'
                WHEN TIMESTAMPDIFF(YEAR, d.date_of_birth, CURDATE()) <= 18 THEN '18 and below'
                WHEN TIMESTAMPDIFF(YEAR, d.date_of_birth, CURDATE()) <= 21 THEN '19–21'
                WHEN TIMESTAMPDIFF(YEAR, d.date_of_birth, CURDATE()) <= 24 THEN '22–24'
                ELSE '25 and above' END",
    ];

    public function __construct(private mysqli $conn)
    {
    }

    /** @return string[] program names, for validating the program filter */
    public function programNames(): array
    {
        return array_column($this->rows('SELECT name FROM programs ORDER BY name'), 'name');
    }

    // ---------------------------------------------------------------- enrollments

    /** Number of enrollments per status for the filtered term(s). */
    public function enrollmentStatusCounts(array $f): array
    {
        [$where, $params] = $this->conditions($f, [
            'academic_year' => 'e.academic_year', 'semester' => 'e.semester',
            'program' => 'p.name', 'year_level' => 's.year_level',
        ]);

        return $this->rows(
            "SELECT e.status, COUNT(*) AS total
               FROM enrollments e
               JOIN students s ON s.student_id = e.student_id
               JOIN programs p ON p.id = s.program_id
              WHERE 1 = 1 $where
              GROUP BY e.status",
            $params
        );
    }

    /** Enrolled (not Dropped) students, subject loads and units, per year level. */
    public function enrolledByYearLevel(array $f): array
    {
        [$where, $params] = $this->conditions($f, [
            'academic_year' => 'e.academic_year', 'semester' => 'e.semester',
            'program' => 'p.name', 'year_level' => 's.year_level',
        ]);

        return $this->rows(
            "SELECT s.year_level,
                    COUNT(DISTINCT e.id)        AS enrolled,
                    COUNT(eo.id)                AS subject_loads,
                    COALESCE(SUM(sub.units), 0) AS units
               FROM enrollments e
               JOIN students s ON s.student_id = e.student_id
               JOIN programs p ON p.id = s.program_id
               LEFT JOIN enrollment_offering eo ON eo.enrollment_id = e.id
               LEFT JOIN course_offerings o ON o.id = eo.course_offering_id
               LEFT JOIN subjects sub ON sub.id = o.subject_id
              WHERE e.status <> 'Dropped' $where
              GROUP BY s.year_level
              ORDER BY s.year_level",
            $params
        );
    }

    /** One row per program (including programs with no enrollments). */
    public function enrollmentByProgram(array $f): array
    {
        // Term and year-level filters go into the JOIN conditions so that a
        // program with nobody enrolled still appears with zeros.
        [$termOn, $termParams] = $this->conditions($f, ['academic_year' => 'e.academic_year', 'semester' => 'e.semester']);
        [$levelOn, $levelParams] = $this->conditions($f, ['year_level' => 's.year_level']);
        [$where, $whereParams] = $this->conditions($f, ['program' => 'p.name']);

        return $this->rows(
            "SELECT p.name AS program,
                    COUNT(DISTINCT CASE WHEN e.status <> 'Dropped' THEN e.id END) AS enrolled,
                    COUNT(DISTINCT CASE WHEN e.status = 'Dropped' THEN e.id END)  AS dropped,
                    COUNT(CASE WHEN e.status <> 'Dropped' THEN eo.id END)         AS subject_loads,
                    COALESCE(SUM(CASE WHEN e.status <> 'Dropped' THEN sub.units END), 0) AS units
               FROM programs p
               LEFT JOIN students s ON s.program_id = p.id $levelOn
               LEFT JOIN enrollments e ON e.student_id = s.student_id $termOn
               LEFT JOIN enrollment_offering eo ON eo.enrollment_id = e.id
               LEFT JOIN course_offerings o ON o.id = eo.course_offering_id
               LEFT JOIN subjects sub ON sub.id = o.subject_id
              WHERE 1 = 1 $where
              GROUP BY p.id, p.name
              ORDER BY p.name",
            [...$levelParams, ...$termParams, ...$whereParams]
        );
    }

    /** Totals per academic year and semester (the semester filter is not used). */
    public function enrollmentByTerm(array $f): array
    {
        [$where, $params] = $this->conditions($f, [
            'academic_year' => 'e.academic_year', 'program' => 'p.name', 'year_level' => 's.year_level',
        ]);

        return $this->rows(
            "SELECT e.academic_year, e.semester,
                    COUNT(*)                                 AS total,
                    SUM(CASE WHEN e.status <> 'Dropped' THEN 1 ELSE 0 END) AS enrolled,
                    SUM(CASE WHEN e.status = 'Dropped' THEN 1 ELSE 0 END)  AS dropped
               FROM enrollments e
               JOIN students s ON s.student_id = e.student_id
               JOIN programs p ON p.id = s.program_id
              WHERE 1 = 1 $where
              GROUP BY e.academic_year, e.semester",
            $params
        );
    }

    // ------------------------------------------------------------------ sections

    /** Every course offering in the term with its seats taken (not Dropped). */
    public function sectionUtilization(array $f): array
    {
        [$where, $params] = $this->conditions($f, ['academic_year' => 'o.academic_year', 'semester' => 'o.semester']);

        return $this->rows(
            "SELECT sub.code, sub.name AS subject, o.section, o.semester, o.academic_year,
                    o.instructor, o.slots, o.status,
                    COUNT(e.id) AS enrolled
               FROM course_offerings o
               JOIN subjects sub ON sub.id = o.subject_id
               LEFT JOIN enrollment_offering eo ON eo.course_offering_id = o.id
               LEFT JOIN enrollments e ON e.id = eo.enrollment_id AND e.status <> 'Dropped'
              WHERE 1 = 1 $where
              GROUP BY o.id, sub.code, sub.name, o.section, o.semester, o.academic_year, o.instructor, o.slots, o.status
              ORDER BY o.academic_year DESC, o.semester, sub.code, o.section",
            $params
        );
    }

    // ------------------------------------------------------------------ students

    /** Student counts grouped by one of STUDENT_GROUPS. */
    public function studentsGroupedBy(string $group, array $f): array
    {
        $column = self::STUDENT_GROUPS[$group] ?? throw new InvalidArgumentException("Unknown group: $group");
        [$where, $params] = $this->conditions($f, [
            'academic_year' => 's.academic_year', 'program' => 'p.name', 'year_level' => 's.year_level',
        ]);

        return $this->rows(
            "SELECT $column AS label, COUNT(*) AS total
               FROM students s
               JOIN programs p ON p.id = s.program_id
               LEFT JOIN student_personal_details d ON d.student_id = s.student_id
              WHERE 1 = 1 $where
              GROUP BY label
              ORDER BY total DESC, label",
            $params
        );
    }

    /** Average age of the filtered students (null when no birth dates). */
    public function averageAge(array $f): ?float
    {
        [$where, $params] = $this->conditions($f, [
            'academic_year' => 's.academic_year', 'program' => 'p.name', 'year_level' => 's.year_level',
        ]);

        $row = $this->rows(
            "SELECT AVG(TIMESTAMPDIFF(YEAR, d.date_of_birth, CURDATE())) AS age
               FROM students s
               JOIN programs p ON p.id = s.program_id
               LEFT JOIN student_personal_details d ON d.student_id = s.student_id
              WHERE 1 = 1 $where",
            $params
        )[0];

        return $row['age'] === null ? null : round((float) $row['age'], 1);
    }

    /**
     * The student list (program and year-level filters) with each student's
     * enrollment(s) in the filtered term(s), e.g. "1st Semester: Active".
     */
    public function masterList(array $f): array
    {
        [$termOn, $termParams] = $this->conditions($f, ['academic_year' => 'e.academic_year', 'semester' => 'e.semester']);
        [$where, $whereParams] = $this->conditions($f, ['program' => 'p.name', 'year_level' => 's.year_level']);

        return $this->rows(
            "SELECT s.student_id,
                    CONCAT(s.last_name, ', ', CONCAT_WS(' ', s.first_name, NULLIF(s.middle_name, ''), NULLIF(s.suffix, ''))) AS name,
                    p.name AS program, s.year_level, s.status, s.email,
                    GROUP_CONCAT(DISTINCT CONCAT(e.academic_year, ' ', e.semester, ': ', e.status)
                                 ORDER BY e.academic_year DESC, e.semester SEPARATOR '; ') AS enrollment
               FROM students s
               JOIN programs p ON p.id = s.program_id
               LEFT JOIN enrollments e ON e.student_id = s.student_id $termOn
              WHERE 1 = 1 $where
              GROUP BY s.student_id, s.last_name, s.first_name, s.middle_name, s.suffix, p.name, s.year_level, s.status, s.email
              ORDER BY p.name, s.year_level, s.last_name, s.first_name",
            [...$termParams, ...$whereParams]
        );
    }

    // ------------------------------------------------------------------- helpers

    /**
     * Builds " AND column = ?" for every filter that has a value.
     *
     * @param array<string, string> $f       the filter values
     * @param array<string, string> $columns filter name => column (from this class only)
     * @return array{0: string, 1: string[]}
     */
    private function conditions(array $f, array $columns): array
    {
        $sql = '';
        $params = [];
        foreach ($columns as $filter => $column) {
            if (($f[$filter] ?? '') !== '') {
                $sql .= " AND $column = ?";
                $params[] = $f[$filter];
            }
        }

        return [$sql, $params];
    }

    private function rows(string $sql, array $params = []): array
    {
        $stmt = $this->conn->prepare($sql);
        $params === [] ? $stmt->execute() : $stmt->execute($params);

        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}
