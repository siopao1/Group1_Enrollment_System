<?php

declare(strict_types=1);

/**
 * System preferences stored in the `settings` table (one row per key).
 * Values come back with the API's field names: academicYear, semester, rowsPerPage.
 */
final class SettingsRepository
{
    public const ROWS_PER_PAGE = [10, 25, 50, 100];

    /** API field => setting_key, with the default used if a row is missing. */
    private const KEYS = [
        'academicYear' => ['academic_year', '2026–2027'],
        'semester'     => ['semester', '1st Semester'],
        'rowsPerPage'  => ['rows_per_page', '10'],
    ];

    public function __construct(private mysqli $conn)
    {
    }

    /** @return array{academicYear: string, semester: string, rowsPerPage: int} */
    public function all(): array
    {
        $stored = [];
        $result = $this->conn->query('SELECT setting_key, setting_value FROM settings');
        while ($row = $result->fetch_assoc()) {
            $stored[$row['setting_key']] = $row['setting_value'];
        }

        $settings = [];
        foreach (self::KEYS as $field => [$key, $default]) {
            $settings[$field] = $stored[$key] ?? $default;
        }
        $settings['rowsPerPage'] = (int) $settings['rowsPerPage'];

        return $settings;
    }

    /** @param array{academicYear: string, semester: string, rowsPerPage: int} $settings */
    public function save(array $settings, int $updatedBy): void
    {
        $stmt = $this->conn->prepare(
            'INSERT INTO settings (setting_key, setting_value, updated_by) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by)'
        );

        $this->conn->begin_transaction();
        try {
            foreach (self::KEYS as $field => [$key]) {
                $value = (string) $settings[$field];
                $stmt->bind_param('ssi', $key, $value, $updatedBy);
                $stmt->execute();
            }
            $this->conn->commit();
        } catch (Throwable $e) {
            $this->conn->rollback();
            throw $e;
        }
    }
}
