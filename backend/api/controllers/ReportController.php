<?php

declare(strict_types=1);

/**
 * /api/reports — admin and registrar only.
 *
 *   GET /api/reports          200  the catalog: report types and the filters each uses
 *   GET /api/reports/{type}   200  one report as JSON, or as a CSV file with ?format=csv
 *                             404  unknown report type
 *                             422  an invalid filter value
 *
 * Filters: ?academic_year=&semester=&program=&year_level= (empty = all)
 */
final class ReportController
{
    public function __construct(private ReportRepository $repository, private ReportService $reports)
    {
    }

    public function index(): never
    {
        Response::success('Report catalog retrieved.', ReportService::catalog(), 200, [
            'filterOptions' => [
                'academic_year' => AcademicTerm::ACADEMIC_YEARS,
                'semester'      => AcademicTerm::SEMESTERS,
                'program'       => $this->repository->programNames(),
                'year_level'    => AcademicTerm::YEAR_LEVELS,
            ],
        ]);
    }

    public function show(string $type): never
    {
        if (!ReportService::exists($type)) {
            Response::error('Report not found. Available: ' . implode(', ', array_keys(ReportService::TYPES)) . '.', 404);
        }

        $filters = [
            'academic_year' => Request::query('academic_year'),
            'semester'      => Request::query('semester'),
            'program'       => Request::query('program'),
            'year_level'    => Request::query('year_level'),
        ];
        $format = strtolower(Request::query('format', 'json'));

        $errors = [];
        if ($filters['academic_year'] !== '' && !AcademicTerm::isAcademicYear($filters['academic_year'])) {
            $errors['academic_year'][] = 'Academic year must be one of: ' . implode(', ', AcademicTerm::ACADEMIC_YEARS) . '.';
        }
        if ($filters['semester'] !== '' && !AcademicTerm::isSemester($filters['semester'])) {
            $errors['semester'][] = 'Semester must be one of: ' . implode(', ', AcademicTerm::SEMESTERS) . '.';
        }
        if ($filters['year_level'] !== '' && !AcademicTerm::isYearLevel($filters['year_level'])) {
            $errors['year_level'][] = 'Year level must be one of: ' . implode(', ', AcademicTerm::YEAR_LEVELS) . '.';
        }
        if ($filters['program'] !== '' && !in_array($filters['program'], $this->repository->programNames(), true)) {
            $errors['program'][] = 'Unknown program.';
        }
        if (!in_array($format, ['json', 'csv'], true)) {
            $errors['format'][] = 'Format must be json or csv.';
        }
        if ($errors !== []) {
            Response::error('Invalid report filters.', 422, $errors);
        }

        $report = $this->reports->build($type, $filters);

        if ($format === 'csv') {
            Response::csv($type . '-' . date('Ymd-His') . '.csv', self::csvLines($report));
        }

        Response::success('Report generated.', $report);
    }

    /** The report as CSV lines: title, filters, summary, then each table. */
    private static function csvLines(array $report): array
    {
        $lines = [[$report['title']], ['Generated', $report['generatedAt']]];
        foreach ($report['filters'] as $filter) {
            $lines[] = [$filter['label'], $filter['value']];
        }

        $lines[] = [];
        $lines[] = ['Summary'];
        foreach ($report['summary'] as $item) {
            $lines[] = [$item['label'], $item['value']];
        }

        foreach ($report['sections'] as $section) {
            $lines[] = [];
            $lines[] = [$section['title']];
            $lines[] = array_column($section['columns'], 'label');
            foreach ($section['rows'] as $row) {
                $lines[] = array_map(static fn (array $col) => $row[$col['key']] ?? '', $section['columns']);
            }
        }

        return $lines;
    }
}
