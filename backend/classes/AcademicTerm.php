<?php

declare(strict_types=1);

/**
 * The academic years, semesters and year levels the system works with.
 * Used to validate settings and report filters. Keep these in step with
 * public/assets/js/config/options.js and the Laravel module's
 * CourseOffering::ACADEMIC_YEARS / SEMESTERS.
 */
final class AcademicTerm
{
    public const ACADEMIC_YEARS = ['2026–2027', '2025–2026'];

    public const SEMESTERS = ['1st Semester', '2nd Semester', 'Summer'];

    public const YEAR_LEVELS = ['1st Year', '2nd Year', '3rd Year', '4th Year'];

    public static function isAcademicYear(string $value): bool
    {
        return in_array($value, self::ACADEMIC_YEARS, true);
    }

    public static function isSemester(string $value): bool
    {
        return in_array($value, self::SEMESTERS, true);
    }

    public static function isYearLevel(string $value): bool
    {
        return in_array($value, self::YEAR_LEVELS, true);
    }
}
