<?php

declare(strict_types=1);

require_once __DIR__ . '/../classes/Student.php';
require_once __DIR__ . '/../classes/DuplicateStudentException.php';

/**
 * The only place that runs SQL for students. Every query that includes a
 * value from the request uses a MySQLi prepared statement; column names and
 * ORDER BY clauses come from fixed whitelists, never from the request.
 *
 * A student is stored across three tables (students, student_personal_details,
 * student_emergency_contacts), so writes run inside a transaction: either all
 * three tables change, or none do.
 */
final class StudentRepository
{
    /** Allowed ?sort= values and the ORDER BY each one uses. */
    public const SORTS = [
        'newest_first'   => 's.created_at DESC',
        'oldest_first'   => 's.created_at ASC',
        'name_asc'       => 's.first_name ASC, s.middle_name ASC, s.last_name ASC',
        'name_desc'      => 's.first_name DESC, s.middle_name DESC, s.last_name DESC',
        'student_id_asc' => 's.student_id ASC',
    ];

    /** API field => column, per table (used by update()). */
    private const STUDENT_COLUMNS = [
        'firstName' => 'first_name', 'middleName' => 'middle_name', 'lastName' => 'last_name',
        'suffix' => 'suffix', 'email' => 'email', 'contact' => 'contact_number',
        'address' => 'address', 'programId' => 'program_id', 'year' => 'year_level',
        'academicYear' => 'academic_year', 'status' => 'status',
    ];
    private const DETAIL_COLUMNS = [
        'dateOfBirth' => 'date_of_birth', 'gender' => 'gender', 'civilStatus' => 'civil_status',
    ];
    private const EMERGENCY_COLUMNS = [
        'emergencyContact.name' => 'name',
        'emergencyContact.relationship' => 'relationship',
        'emergencyContact.number' => 'number',
    ];

    public function __construct(private mysqli $conn)
    {
    }

    /** @return array<string, int> program name => id */
    public function programIds(): array
    {
        $ids = [];
        $result = $this->conn->query('SELECT id, name FROM programs');
        while ($row = $result->fetch_assoc()) {
            $ids[$row['name']] = (int) $row['id'];
        }

        return $ids;
    }

    public function exists(string $id): bool
    {
        $stmt = $this->conn->prepare('SELECT 1 FROM students WHERE student_id = ?');
        $stmt->bind_param('s', $id);
        $stmt->execute();

        return $stmt->get_result()->num_rows > 0;
    }

    /** One student with personal details and emergency contact, or null. */
    public function find(string $id): ?array
    {
        $stmt = $this->conn->prepare(
            'SELECT s.*, p.name AS program,
                    d.date_of_birth, d.gender, d.civil_status,
                    e.name AS emergency_name, e.relationship AS emergency_relationship,
                    e.number AS emergency_number
               FROM students s
               JOIN programs p ON p.id = s.program_id
               LEFT JOIN student_personal_details d ON d.student_id = s.student_id
               LEFT JOIN student_emergency_contacts e ON e.student_id = s.student_id
              WHERE s.student_id = ?'
        );
        $stmt->bind_param('s', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();

        if (!$row) {
            return null;
        }

        return self::shape($row) + [
            'academicYear'     => $row['academic_year'] ?? '',
            'dateOfBirth'      => $row['date_of_birth'] ?? '',
            'gender'           => $row['gender'] ?? '',
            'civilStatus'      => $row['civil_status'] ?? '',
            'emergencyContact' => [
                'name'         => $row['emergency_name'] ?? '',
                'relationship' => $row['emergency_relationship'] ?? '',
                'number'       => $row['emergency_number'] ?? '',
            ],
        ];
    }

    /**
     * Search, filter, sort and paginate (WHERE, ORDER BY, LIMIT/OFFSET).
     *
     * @param array{search: string, program: string, year: string, status: string,
     *              sort: string, page: int, perPage: int} $filters
     * @return array{rows: list<array>, total: int}
     */
    public function paginate(array $filters): array
    {
        $where = [];
        $params = [];
        $types = '';

        if ($filters['search'] !== '') {
            // Matches the ID or email anywhere, or every typed word somewhere in the name.
            $like = '%' . $filters['search'] . '%';
            array_push($params, $like, $like);
            $types .= 'ss';

            $nameGroups = [];
            foreach (array_filter(explode(' ', $filters['search'])) as $word) {
                $nameGroups[] = '(s.first_name LIKE ? OR s.middle_name LIKE ? OR s.last_name LIKE ?)';
                $likeWord = '%' . $word . '%';
                array_push($params, $likeWord, $likeWord, $likeWord);
                $types .= 'sss';
            }
            $nameSql = $nameGroups ? '(' . implode(' AND ', $nameGroups) . ')' : '0';

            $where[] = "(s.student_id LIKE ? OR s.email LIKE ? OR {$nameSql})";
        }
        if ($filters['program'] !== '') {
            $where[] = 'p.name = ?';
            $params[] = $filters['program'];
            $types .= 's';
        }
        if ($filters['year'] !== '') {
            $where[] = 's.year_level = ?';
            $params[] = $filters['year'];
            $types .= 's';
        }
        if ($filters['status'] !== '') {
            $where[] = 's.status = ?';
            $params[] = $filters['status'];
            $types .= 's';
        }

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $orderBy = self::SORTS[$filters['sort']] ?? self::SORTS['newest_first'];

        $count = $this->conn->prepare(
            "SELECT COUNT(*) FROM students s JOIN programs p ON p.id = s.program_id {$whereSql}"
        );
        if ($types !== '') {
            $count->bind_param($types, ...$params);
        }
        $count->execute();
        $total = (int) $count->get_result()->fetch_row()[0];

        $list = $this->conn->prepare(
            "SELECT s.*, p.name AS program
               FROM students s
               JOIN programs p ON p.id = s.program_id
               {$whereSql}
              ORDER BY {$orderBy}
              LIMIT ? OFFSET ?"
        );
        $offset = ($filters['page'] - 1) * $filters['perPage'];
        $list->bind_param($types . 'ii', ...[...$params, $filters['perPage'], $offset]);
        $list->execute();
        $result = $list->get_result();

        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = self::shape($row);
        }

        return ['rows' => $rows, 'total' => $total];
    }

    /**
     * Inserts a new student into all three tables.
     *
     * @throws DuplicateStudentException when the student ID is already taken
     */
    public function create(array $data): void
    {
        if ($this->exists($data['id'])) {
            throw new DuplicateStudentException("A student with ID \"{$data['id']}\" already exists.");
        }

        $this->transaction(function () use ($data): void {
            $student = $this->conn->prepare(
                'INSERT INTO students
                    (student_id, first_name, middle_name, last_name, suffix, email,
                     contact_number, address, program_id, year_level, academic_year, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $student->bind_param(
                'ssssssssisss',
                $data['id'], $data['firstName'], $data['middleName'], $data['lastName'],
                $data['suffix'], $data['email'], $data['contact'], $data['address'],
                $data['programId'], $data['year'], $data['academicYear'], $data['status']
            );
            $student->execute();

            $this->upsert('student_personal_details', self::DETAIL_COLUMNS, $data['id'], $data);
            $this->upsert('student_emergency_contacts', self::EMERGENCY_COLUMNS, $data['id'], $data);
        }, $data['id']);
    }

    /**
     * Updates only the fields present in $data. PUT passes every field
     * (full replacement); PATCH passes just the fields that were sent.
     */
    public function update(string $id, array $data): void
    {
        $this->transaction(function () use ($id, $data): void {
            $sets = [];
            $values = [];
            foreach (self::STUDENT_COLUMNS as $field => $column) {
                if (array_key_exists($field, $data)) {
                    $sets[] = "{$column} = ?";
                    $values[] = $data[$field];
                }
            }

            if ($sets !== []) {
                $values[] = $id;
                $stmt = $this->conn->prepare(
                    'UPDATE students SET ' . implode(', ', $sets) . ' WHERE student_id = ?'
                );
                $stmt->bind_param(self::types($values), ...$values);
                $stmt->execute();
            }

            $this->upsert('student_personal_details', self::DETAIL_COLUMNS, $id, $data);
            $this->upsert('student_emergency_contacts', self::EMERGENCY_COLUMNS, $id, $data);
        });
    }

    /** Deletes a student; the two detail tables follow via ON DELETE CASCADE. */
    public function delete(string $id): bool
    {
        $stmt = $this->conn->prepare('DELETE FROM students WHERE student_id = ?');
        $stmt->bind_param('s', $id);
        $stmt->execute();

        return $stmt->affected_rows > 0;
    }

    /**
     * Inserts or updates the one-to-one row in a detail table, writing only
     * the columns present in $data. Empty optional values are stored as NULL.
     */
    private function upsert(string $table, array $columnMap, string $id, array $data): void
    {
        $columns = [];
        $values = [];
        foreach ($columnMap as $field => $column) {
            if (array_key_exists($field, $data)) {
                $columns[] = $column;
                $values[] = $data[$field] === '' ? null : $data[$field];
            }
        }
        if ($columns === []) {
            return;
        }

        $placeholders = implode(', ', array_fill(0, count($columns) + 1, '?'));
        $updates = implode(', ', array_map(static fn (string $c): string => "{$c} = VALUES({$c})", $columns));

        $stmt = $this->conn->prepare(
            "INSERT INTO {$table} (student_id, " . implode(', ', $columns) . ")
             VALUES ({$placeholders})
             ON DUPLICATE KEY UPDATE {$updates}"
        );
        $params = [$id, ...$values];
        $stmt->bind_param(self::types($params), ...$params);
        $stmt->execute();
    }

    /**
     * Runs $work inside a transaction. A duplicate-key error on create (two
     * requests racing for the same ID) is reported as DuplicateStudentException.
     */
    private function transaction(callable $work, ?string $newId = null): void
    {
        $this->conn->begin_transaction();
        try {
            $work();
            $this->conn->commit();
        } catch (Throwable $e) {
            $this->conn->rollback();
            if ($newId !== null && $e instanceof mysqli_sql_exception && $e->getCode() === 1062) {
                throw new DuplicateStudentException("A student with ID \"{$newId}\" already exists.", 0, $e);
            }
            throw $e;
        }
    }

    /** "i" for integers, "s" for everything else (strings and NULL). */
    private static function types(array $values): string
    {
        return implode('', array_map(static fn ($v): string => is_int($v) ? 'i' : 's', $values));
    }

    /** Builds the list-row shape through the Student and Program classes. */
    private static function shape(array $row): array
    {
        $student = new Student(
            $row['student_id'],
            $row['first_name'],
            $row['middle_name'] ?? '',
            $row['last_name'],
            $row['suffix'] ?? '',
            $row['email'] ?? '',
            $row['contact_number'] ?? '',
            $row['address'] ?? null,
            new Program((int) $row['program_id'], $row['program']),
            $row['year_level'],
            $row['status'],
            date('M. j, Y', strtotime($row['created_at']))
        );

        return $student->toArray();
    }
}
