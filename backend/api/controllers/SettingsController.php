<?php

declare(strict_types=1);

/**
 * /api/settings — system preferences.
 *
 *   GET /api/settings   200   every signed-in role (pages need the defaults)
 *   PUT /api/settings   200   admin only; 422 for invalid values
 */
final class SettingsController
{
    public function __construct(private SettingsRepository $settings)
    {
    }

    public function show(): never
    {
        Response::success('Settings retrieved.', $this->settings->all());
    }

    public function update(array $user): never
    {
        $input = Request::json();
        $text = static fn (string $key): string => is_scalar($input[$key] ?? null) ? trim((string) $input[$key]) : '';

        $data = [
            'academicYear' => $text('academicYear'),
            'semester'     => $text('semester'),
            'rowsPerPage'  => $text('rowsPerPage'),
        ];

        $errors = [];
        if (!AcademicTerm::isAcademicYear($data['academicYear'])) {
            $errors['academicYear'][] = 'Academic year must be one of: ' . implode(', ', AcademicTerm::ACADEMIC_YEARS) . '.';
        }
        if (!AcademicTerm::isSemester($data['semester'])) {
            $errors['semester'][] = 'Semester must be one of: ' . implode(', ', AcademicTerm::SEMESTERS) . '.';
        }
        if (!ctype_digit($data['rowsPerPage']) || !in_array((int) $data['rowsPerPage'], SettingsRepository::ROWS_PER_PAGE, true)) {
            $errors['rowsPerPage'][] = 'Rows per page must be one of: ' . implode(', ', SettingsRepository::ROWS_PER_PAGE) . '.';
        }
        if ($errors !== []) {
            Response::error('Validation failed.', 422, $errors);
        }

        $data['rowsPerPage'] = (int) $data['rowsPerPage'];
        $this->settings->save($data, $user['id']);

        Response::success('Settings saved.', $this->settings->all());
    }
}
