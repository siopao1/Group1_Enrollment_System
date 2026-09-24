<?php

/**
 * Real backend for the Dashboard's student-based figures.
 *
 * Only computes what the real database actually has data for: student counts,
 * pending students, total programs, students by program, and students by
 * status. Same MySQLi + prepared statement approach as students.php.
 */

require __DIR__ . '/../../backend/config/database.php';

header('Content-Type: application/json');

function sendJson($success, $data, $message, $statusCode)
{
    http_response_code($statusCode);
    echo json_encode(['success' => $success, 'data' => $data, 'message' => $message]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendJson(false, null, 'This endpoint only accepts GET requests.', 405);
}

$conn = getDbConnection();

$totalStudents = (int) $conn->query('SELECT COUNT(*) AS total FROM students')->fetch_assoc()['total'];
$activeStudents = (int) $conn->query("SELECT COUNT(*) AS total FROM students WHERE status = 'Active'")->fetch_assoc()['total'];
$pendingStudents = (int) $conn->query("SELECT COUNT(*) AS total FROM students WHERE status = 'Pending'")->fetch_assoc()['total'];
$totalPrograms = (int) $conn->query('SELECT COUNT(*) AS total FROM programs')->fetch_assoc()['total'];

// Students by program — GROUP BY, largest program first.
$byProgram = [];
$tones = ['', 'blue', 'gold', 'gray', 'light'];
$programResult = $conn->query(
    'SELECT p.name AS program, COUNT(*) AS total
       FROM students s
       JOIN programs p ON p.id = s.program_id
      GROUP BY p.name
      ORDER BY total DESC'
);
$toneIndex = 0;
while ($row = $programResult->fetch_assoc()) {
    $share = $totalStudents > 0 ? (int) round(($row['total'] / $totalStudents) * 100) : 0;
    $byProgram[] = [
        'label' => $row['program'],
        'share' => $share,
        'tone' => $tones[$toneIndex % count($tones)],
    ];
    $toneIndex++;
}

// Students by status — GROUP BY, every status the system allows.
$statusItems = [];
$statusResult = $conn->query('SELECT status, COUNT(*) AS total FROM students GROUP BY status');
while ($row = $statusResult->fetch_assoc()) {
    $percent = $totalStudents > 0 ? round(($row['total'] / $totalStudents) * 100, 1) : 0;
    $statusItems[] = [
        'status' => $row['status'],
        'count' => (int) $row['total'],
        'note' => "$percent% of records",
    ];
}

sendJson(true, [
    'totalStudents' => $totalStudents,
    'activeStudents' => $activeStudents,
    'pendingStudents' => $pendingStudents,
    'totalPrograms' => $totalPrograms,
    'byProgram' => $byProgram,
    'statusBreakdown' => [
        'total' => $totalStudents,
        'items' => $statusItems,
    ],
], 'Dashboard summary retrieved.', 200);
