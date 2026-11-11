<?php

declare(strict_types=1);

/**
 * Dashboard figures computed live from the database with COUNT and GROUP BY.
 * None of these queries use request input, so plain query() is safe here.
 */
final class DashboardRepository
{
    private const TONES = ['', 'blue', 'gold', 'gray', 'light'];

    public function __construct(private mysqli $conn)
    {
    }

    /** $academicYear and $semester are the current term, from the settings table. */
    public function summary(string $academicYear, string $semester): array
    {
        $totalStudents   = $this->count('SELECT COUNT(*) FROM students');
        $activeStudents  = $this->count("SELECT COUNT(*) FROM students WHERE status = 'Active'");
        $pendingStudents = $this->count("SELECT COUNT(*) FROM students WHERE status = 'Pending'");
        $totalPrograms   = $this->count('SELECT COUNT(*) FROM programs');

        $byProgram = [];
        $result = $this->conn->query(
            'SELECT p.name AS program, COUNT(*) AS total
               FROM students s
               JOIN programs p ON p.id = s.program_id
              GROUP BY p.name
              ORDER BY total DESC'
        );
        $index = 0;
        while ($row = $result->fetch_assoc()) {
            $byProgram[] = [
                'label' => $row['program'],
                'share' => $totalStudents > 0 ? (int) round($row['total'] / $totalStudents * 100) : 0,
                'tone'  => self::TONES[$index++ % count(self::TONES)],
            ];
        }

        $statusItems = [];
        $result = $this->conn->query('SELECT status, COUNT(*) AS total FROM students GROUP BY status');
        while ($row = $result->fetch_assoc()) {
            $percent = $totalStudents > 0 ? round($row['total'] / $totalStudents * 100, 1) : 0;
            $statusItems[] = [
                'status' => $row['status'],
                'count'  => (int) $row['total'],
                'note'   => "{$percent}% of records",
            ];
        }

        return [
            'term'            => $this->termFigures($academicYear, $semester),
            'totalStudents'   => $totalStudents,
            'activeStudents'  => $activeStudents,
            'pendingStudents' => $pendingStudents,
            'totalPrograms'   => $totalPrograms,
            'byProgram'       => $byProgram,
            'statusBreakdown' => ['total' => $totalStudents, 'items' => $statusItems],
        ];
    }

    /**
     * Enrollment figures for the current term: students enrolled (not Dropped)
     * and how many of the term's sections are full. The term comes from the
     * settings table, so these queries are prepared statements.
     */
    private function termFigures(string $academicYear, string $semester): array
    {
        $stmt = $this->conn->prepare(
            "SELECT COUNT(*) FROM enrollments
              WHERE academic_year = ? AND semester = ? AND status <> 'Dropped'"
        );
        $stmt->execute([$academicYear, $semester]);
        $enrolled = (int) $stmt->get_result()->fetch_row()[0];

        $stmt = $this->conn->prepare(
            "SELECT COUNT(*) AS sections, COALESCE(SUM(taken >= slots), 0) AS full
               FROM (SELECT o.id, o.slots, COUNT(e.id) AS taken
                       FROM course_offerings o
                       LEFT JOIN enrollment_offering eo ON eo.course_offering_id = o.id
                       LEFT JOIN enrollments e ON e.id = eo.enrollment_id AND e.status <> 'Dropped'
                      WHERE o.academic_year = ? AND o.semester = ?
                      GROUP BY o.id, o.slots) AS term_sections"
        );
        $stmt->execute([$academicYear, $semester]);
        $sections = $stmt->get_result()->fetch_assoc();

        return [
            'academicYear' => $academicYear,
            'semester'     => $semester,
            'enrolled'     => $enrolled,
            'sections'     => (int) $sections['sections'],
            'sectionsFull' => (int) $sections['full'],
        ];
    }

    private function count(string $sql): int
    {
        return (int) $this->conn->query($sql)->fetch_row()[0];
    }
}
