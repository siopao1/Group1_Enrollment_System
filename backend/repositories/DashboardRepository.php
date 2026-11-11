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

    public function summary(): array
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
            'totalStudents'   => $totalStudents,
            'activeStudents'  => $activeStudents,
            'pendingStudents' => $pendingStudents,
            'totalPrograms'   => $totalPrograms,
            'byProgram'       => $byProgram,
            'statusBreakdown' => ['total' => $totalStudents, 'items' => $statusItems],
        ];
    }

    private function count(string $sql): int
    {
        return (int) $this->conn->query($sql)->fetch_row()[0];
    }
}
