<?php

declare(strict_types=1);

/**
 * Builds the six reports. Every report has the same shape, so the Reports
 * page and the CSV export handle all of them the same way:
 *
 *   type, title, description, filters (the ones applied, as text), generatedAt,
 *   summary:  [{label, value}]                         — the cards at the top
 *   sections: [{title, columns: [{key, label, numeric?}], rows: [{key: value}],
 *               chart?: {label: key, value: key}}]      — tables (+ a bar chart)
 */
final class ReportService
{
    /** Below this share of seats taken, a section is flagged "Low". */
    public const LOW_UTILIZATION = 25;

    /**
     * The catalog. "filters" lists the query parameters each report uses;
     * the others are ignored for that report.
     */
    public const TYPES = [
        'enrollment-summary' => [
            'title'       => 'Enrollment Summary',
            'description' => 'Enrollment records by status and enrolled students by year level, with subject loads and units.',
            'filters'     => ['academic_year', 'semester', 'program', 'year_level'],
        ],
        'enrollment-by-program' => [
            'title'       => 'Enrollment by Program',
            'description' => 'Enrolled and dropped students, subject loads and units for each program.',
            'filters'     => ['academic_year', 'semester', 'program', 'year_level'],
        ],
        'enrollment-by-semester' => [
            'title'       => 'Enrollment by Semester',
            'description' => 'Enrollment totals for every semester of the selected academic year(s).',
            'filters'     => ['academic_year', 'program', 'year_level'],
        ],
        'section-utilization' => [
            'title'       => 'Section Utilization',
            'description' => 'Seats taken in every course offering, flagging full sections and sections under 25% filled.',
            'filters'     => ['academic_year', 'semester'],
        ],
        'student-demographics' => [
            'title'       => 'Student Demographics',
            'description' => 'Students by gender, age group, year level, program, civil status and record status.',
            'filters'     => ['academic_year', 'program', 'year_level'],
        ],
        'student-master-list' => [
            'title'       => 'Student Master List',
            'description' => 'Every student with program, year level and their enrollment in the selected term.',
            'filters'     => ['academic_year', 'semester', 'program', 'year_level'],
        ],
    ];

    private const FILTER_LABELS = [
        'academic_year' => ['Academic Year', 'All academic years'],
        'semester'      => ['Semester', 'All semesters'],
        'program'       => ['Program', 'All programs'],
        'year_level'    => ['Year Level', 'All year levels'],
    ];

    private const ENROLLMENT_STATUSES = ['Pending', 'Confirmed', 'Active', 'Completed', 'Dropped'];

    private const GROUP_ORDER = [
        'year_level' => AcademicTerm::YEAR_LEVELS,
        'age_group'  => ['18 and below', '19–21', '22–24', '25 and above', 'Not specified'],
    ];

    public function __construct(private ReportRepository $reports)
    {
    }

    public static function exists(string $type): bool
    {
        return isset(self::TYPES[$type]);
    }

    /** @return list<array{type: string, title: string, description: string, filters: string[]}> */
    public static function catalog(): array
    {
        $list = [];
        foreach (self::TYPES as $type => $info) {
            $list[] = ['type' => $type] + $info;
        }

        return $list;
    }

    /** @param array<string, string> $filters already validated */
    public function build(string $type, array $filters): array
    {
        $info = self::TYPES[$type];

        // Drop the filters this report does not use, so they are not applied or shown.
        $applied = array_intersect_key($filters, array_flip($info['filters']));

        [$summary, $sections] = match ($type) {
            'enrollment-summary'     => $this->enrollmentSummary($applied),
            'enrollment-by-program'  => $this->enrollmentByProgram($applied),
            'enrollment-by-semester' => $this->enrollmentBySemester($applied),
            'section-utilization'    => $this->sectionUtilization($applied),
            'student-demographics'   => $this->studentDemographics($applied),
            'student-master-list'    => $this->studentMasterList($applied),
        };

        $shown = [];
        foreach ($info['filters'] as $key) {
            [$label, $all] = self::FILTER_LABELS[$key];
            $shown[] = ['key' => $key, 'label' => $label, 'value' => ($applied[$key] ?? '') !== '' ? $applied[$key] : $all];
        }

        return [
            'type'        => $type,
            'title'       => $info['title'],
            'description' => $info['description'],
            'filters'     => $shown,
            'generatedAt' => date('Y-m-d H:i:s'),
            'summary'     => $summary,
            'sections'    => $sections,
        ];
    }

    // -------------------------------------------------------------------- reports

    private function enrollmentSummary(array $f): array
    {
        $counts = array_fill_keys(self::ENROLLMENT_STATUSES, 0);
        foreach ($this->reports->enrollmentStatusCounts($f) as $row) {
            $counts[$row['status']] = (int) $row['total'];
        }
        $total = array_sum($counts);
        $enrolled = $total - $counts['Dropped'];

        $statusRows = [];
        foreach ($counts as $status => $count) {
            $statusRows[] = ['status' => $status, 'count' => $count, 'share' => self::percent($count, $total)];
        }

        $levelRows = [];
        $units = 0;
        foreach ($this->reports->enrolledByYearLevel($f) as $row) {
            $units += (int) $row['units'];
            $levelRows[] = [
                'yearLevel'    => $row['year_level'],
                'enrolled'     => (int) $row['enrolled'],
                'subjectLoads' => (int) $row['subject_loads'],
                'units'        => (int) $row['units'],
                'averageUnits' => self::average((int) $row['units'], (int) $row['enrolled']),
            ];
        }

        $summary = [
            ['label' => 'Enrollment records', 'value' => $total],
            ['label' => 'Enrolled (not dropped)', 'value' => $enrolled],
            ['label' => 'Dropped', 'value' => $counts['Dropped']],
            ['label' => 'Average units per student', 'value' => self::average($units, $enrolled)],
        ];

        $sections = [
            [
                'title'   => 'Enrollments by status',
                'columns' => [self::col('status', 'Status'), self::col('count', 'Enrollments', true), self::col('share', 'Share', true)],
                'rows'    => $statusRows,
                'chart'   => ['label' => 'status', 'value' => 'count'],
            ],
            [
                'title'   => 'Enrolled students by year level',
                'columns' => [
                    self::col('yearLevel', 'Year Level'), self::col('enrolled', 'Enrolled', true),
                    self::col('subjectLoads', 'Subject Loads', true), self::col('units', 'Total Units', true),
                    self::col('averageUnits', 'Avg. Units', true),
                ],
                'rows'    => $levelRows,
                'chart'   => ['label' => 'yearLevel', 'value' => 'enrolled'],
            ],
        ];

        return [$summary, $sections];
    }

    private function enrollmentByProgram(array $f): array
    {
        $rows = [];
        foreach ($this->reports->enrollmentByProgram($f) as $row) {
            $rows[] = [
                'program'      => $row['program'],
                'enrolled'     => (int) $row['enrolled'],
                'dropped'      => (int) $row['dropped'],
                'subjectLoads' => (int) $row['subject_loads'],
                'units'        => (int) $row['units'],
                'averageUnits' => self::average((int) $row['units'], (int) $row['enrolled']),
            ];
        }

        $enrolled = array_sum(array_column($rows, 'enrolled'));
        $top = $rows === [] ? null : array_reduce($rows, static fn (?array $best, array $r) => $best === null || $r['enrolled'] > $best['enrolled'] ? $r : $best);

        foreach ($rows as &$row) {
            $row['share'] = self::percent($row['enrolled'], $enrolled);
        }
        unset($row);

        $summary = [
            ['label' => 'Programs', 'value' => count($rows)],
            ['label' => 'Enrolled students', 'value' => $enrolled],
            ['label' => 'Dropped', 'value' => array_sum(array_column($rows, 'dropped'))],
            ['label' => 'Largest program', 'value' => $top !== null && $top['enrolled'] > 0 ? $top['program'] : '—'],
        ];

        $sections = [[
            'title'   => 'Enrollment per program',
            'columns' => [
                self::col('program', 'Program'), self::col('enrolled', 'Enrolled', true), self::col('share', 'Share', true),
                self::col('dropped', 'Dropped', true), self::col('subjectLoads', 'Subject Loads', true),
                self::col('units', 'Total Units', true), self::col('averageUnits', 'Avg. Units', true),
            ],
            'rows'    => $rows,
            'chart'   => ['label' => 'program', 'value' => 'enrolled'],
        ]];

        return [$summary, $sections];
    }

    private function enrollmentBySemester(array $f): array
    {
        $found = [];
        foreach ($this->reports->enrollmentByTerm($f) as $row) {
            $found[$row['academic_year'] . '|' . $row['semester']] = $row;
        }

        // Every semester of each academic year is listed, even with no enrollments.
        $years = ($f['academic_year'] ?? '') !== '' ? [$f['academic_year']] : AcademicTerm::ACADEMIC_YEARS;
        $rows = [];
        foreach ($years as $year) {
            foreach (AcademicTerm::SEMESTERS as $semester) {
                $row = $found["$year|$semester"] ?? ['total' => 0, 'enrolled' => 0, 'dropped' => 0];
                $rows[] = [
                    'term'         => "$year · $semester",
                    'academicYear' => $year,
                    'semester'     => $semester,
                    'total'        => (int) $row['total'],
                    'enrolled'     => (int) $row['enrolled'],
                    'dropped'      => (int) $row['dropped'],
                    'dropRate'     => self::percent((int) $row['dropped'], (int) $row['total']),
                ];
            }
        }

        $busiest = array_reduce($rows, static fn (?array $best, array $r) => $best === null || $r['enrolled'] > $best['enrolled'] ? $r : $best);

        $summary = [
            ['label' => 'Terms shown', 'value' => count($rows)],
            ['label' => 'Enrollment records', 'value' => array_sum(array_column($rows, 'total'))],
            ['label' => 'Enrolled (not dropped)', 'value' => array_sum(array_column($rows, 'enrolled'))],
            ['label' => 'Busiest term', 'value' => $busiest !== null && $busiest['enrolled'] > 0 ? $busiest['term'] : '—'],
        ];

        $sections = [[
            'title'   => 'Enrollment per semester',
            'columns' => [
                self::col('academicYear', 'Academic Year'), self::col('semester', 'Semester'),
                self::col('total', 'Records', true), self::col('enrolled', 'Enrolled', true),
                self::col('dropped', 'Dropped', true), self::col('dropRate', 'Drop Rate', true),
            ],
            'rows'    => $rows,
            'chart'   => ['label' => 'term', 'value' => 'enrolled'],
        ]];

        return [$summary, $sections];
    }

    private function sectionUtilization(array $f): array
    {
        $rows = [];
        foreach ($this->reports->sectionUtilization($f) as $row) {
            $slots = (int) $row['slots'];
            $taken = (int) $row['enrolled'];
            $used = $slots > 0 ? $taken / $slots * 100 : 0;

            $rows[] = [
                'code'        => $row['code'],
                'subject'     => $row['subject'],
                'section'     => $row['section'],
                'term'        => $row['academic_year'] . ' · ' . $row['semester'],
                'instructor'  => $row['instructor'] ?: 'TBA',
                'slots'       => $slots,
                'enrolled'    => $taken,
                'available'   => max(0, $slots - $taken),
                'utilization' => round($used, 1),
                'flag'        => $taken >= $slots ? 'Full' : ($used < self::LOW_UTILIZATION ? 'Low' : 'Normal'),
            ];
        }

        $seats = array_sum(array_column($rows, 'slots'));
        $taken = array_sum(array_column($rows, 'enrolled'));
        $flags = array_count_values(array_column($rows, 'flag'));

        $summary = [
            ['label' => 'Sections', 'value' => count($rows)],
            ['label' => 'Seats taken', 'value' => "$taken of $seats"],
            ['label' => 'Overall utilization', 'value' => self::percent($taken, $seats)],
            ['label' => 'Full sections', 'value' => $flags['Full'] ?? 0],
            ['label' => 'Under ' . self::LOW_UTILIZATION . '% filled', 'value' => $flags['Low'] ?? 0],
        ];

        $sections = [[
            'title'   => 'Seats per section',
            'columns' => [
                self::col('code', 'Code'), self::col('subject', 'Subject'), self::col('section', 'Section'),
                self::col('term', 'Term'), self::col('instructor', 'Instructor'),
                self::col('enrolled', 'Enrolled', true), self::col('slots', 'Slots', true),
                self::col('available', 'Available', true), self::col('utilization', 'Utilization %', true),
                self::col('flag', 'Flag'),
            ],
            'rows'    => $rows,
        ]];

        return [$summary, $sections];
    }

    private function studentDemographics(array $f): array
    {
        $groups = [
            'gender'       => 'By gender',
            'age_group'    => 'By age group',
            'year_level'   => 'By year level',
            'program'      => 'By program',
            'civil_status' => 'By civil status',
            'status'       => 'By record status',
        ];

        $sections = [];
        $byGroup = [];
        foreach ($groups as $group => $title) {
            $data = $this->reports->studentsGroupedBy($group, $f);
            $total = array_sum(array_column($data, 'total'));
            $byGroup[$group] = array_column($data, 'total', 'label');

            $rows = array_map(static fn (array $r): array => [
                'label' => $r['label'],
                'count' => (int) $r['total'],
                'share' => self::percent((int) $r['total'], $total),
            ], $data);

            // Ordered groups are listed in their natural order, the rest largest first.
            if (isset(self::GROUP_ORDER[$group])) {
                $order = array_flip(self::GROUP_ORDER[$group]);
                usort($rows, static fn (array $a, array $b) => ($order[$a['label']] ?? 99) <=> ($order[$b['label']] ?? 99));
            }

            $sections[] = [
                'title'   => $title,
                'columns' => [self::col('label', ucfirst(substr($title, 3))), self::col('count', 'Students', true), self::col('share', 'Share', true)],
                'rows'    => $rows,
                'chart'   => ['label' => 'label', 'value' => 'count'],
            ];
        }

        $students = array_sum($byGroup['status']);
        $age = $this->reports->averageAge($f);

        $summary = [
            ['label' => 'Students', 'value' => $students],
            ['label' => 'Active', 'value' => (int) ($byGroup['status']['Active'] ?? 0)],
            ['label' => 'Female / Male', 'value' => (int) ($byGroup['gender']['Female'] ?? 0) . ' / ' . (int) ($byGroup['gender']['Male'] ?? 0)],
            ['label' => 'Average age', 'value' => $age === null ? '—' : $age],
        ];

        return [$summary, $sections];
    }

    private function studentMasterList(array $f): array
    {
        $rows = [];
        foreach ($this->reports->masterList($f) as $row) {
            $rows[] = [
                'studentId'  => $row['student_id'],
                'name'       => $row['name'],
                'program'    => $row['program'],
                'yearLevel'  => $row['year_level'],
                'status'     => $row['status'],
                'email'      => $row['email'] ?: '—',
                'enrollment' => $row['enrollment'] ?: 'Not enrolled',
            ];
        }

        $notEnrolled = count(array_filter($rows, static fn (array $r) => $r['enrollment'] === 'Not enrolled'));

        $summary = [
            ['label' => 'Students listed', 'value' => count($rows)],
            ['label' => 'With an enrollment in the term', 'value' => count($rows) - $notEnrolled],
            ['label' => 'Not enrolled', 'value' => $notEnrolled],
        ];

        $sections = [[
            'title'   => 'Students',
            'columns' => [
                self::col('studentId', 'Student ID'), self::col('name', 'Name'), self::col('program', 'Program'),
                self::col('yearLevel', 'Year Level'), self::col('status', 'Status'), self::col('email', 'Email'),
                self::col('enrollment', 'Enrollment (term)'),
            ],
            'rows'    => $rows,
        ]];

        return [$summary, $sections];
    }

    // -------------------------------------------------------------------- helpers

    private static function col(string $key, string $label, bool $numeric = false): array
    {
        return $numeric ? ['key' => $key, 'label' => $label, 'numeric' => true] : ['key' => $key, 'label' => $label];
    }

    private static function percent(int|float $part, int|float $whole): string
    {
        return $whole > 0 ? number_format($part / $whole * 100, 1) . '%' : '0.0%';
    }

    private static function average(int $sum, int $count): float
    {
        return $count > 0 ? round($sum / $count, 1) : 0.0;
    }
}
