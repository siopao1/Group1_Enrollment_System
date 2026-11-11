<?php

declare(strict_types=1);

/**
 * Server-side validation for student data sent to the API.
 *
 * Three modes, matching the HTTP methods:
 *   validateCreate()  POST   every required field, including the student ID
 *   validateReplace() PUT    every required field; missing optional fields are cleared
 *   validatePatch()   PATCH  only the fields that were sent
 *
 * Each returns [$data, $errors]:
 *   $data   — trimmed, normalized values keyed by API field name, plus
 *             "programId" when a program was given
 *   $errors — field name => list of messages; empty when the input is valid
 *
 * The browser checks these rules too, but only this check counts: Postman,
 * curl or a script never run the browser's JavaScript.
 */
final class StudentValidator
{
    public const YEAR_LEVELS = ['1st Year', '2nd Year', '3rd Year', '4th Year'];
    public const STATUSES = ['Active', 'Pending', 'Dropped', 'Completed'];
    public const GENDERS = ['Male', 'Female', 'Other'];
    public const CIVIL_STATUSES = ['Single', 'Married', 'Other'];

    /** Plain text fields accepted in the JSON body. */
    private const TEXT_FIELDS = [
        'id', 'firstName', 'middleName', 'lastName', 'suffix', 'email', 'contact',
        'address', 'program', 'year', 'academicYear', 'status', 'dateOfBirth',
        'gender', 'civilStatus',
    ];

    /** Nested emergencyContact fields, flattened as "emergencyContact.<key>". */
    private const EMERGENCY_FIELDS = ['name', 'relationship', 'number'];

    /** Must be present and non-empty on POST and PUT, and cannot be blanked by PATCH. */
    private const REQUIRED = [
        'firstName', 'lastName', 'email', 'contact', 'address', 'program',
        'year', 'dateOfBirth', 'gender', 'civilStatus',
    ];

    /** Optional fields and their default when POST/PUT leaves them out. */
    private const OPTIONAL_DEFAULTS = [
        'middleName' => '',
        'suffix' => '',
        'academicYear' => '',
        'status' => 'Active',
        'emergencyContact.name' => '',
        'emergencyContact.relationship' => '',
        'emergencyContact.number' => '',
    ];

    /** Column sizes from schema.sql. */
    private const MAX_LENGTH = [
        'firstName' => 60, 'middleName' => 60, 'lastName' => 60, 'suffix' => 20,
        'email' => 120, 'address' => 255, 'academicYear' => 20,
        'emergencyContact.name' => 100, 'emergencyContact.relationship' => 50,
    ];

    private const LABELS = [
        'id' => 'Student ID', 'firstName' => 'First name', 'middleName' => 'Middle name',
        'lastName' => 'Last name', 'suffix' => 'Suffix', 'email' => 'Email',
        'contact' => 'Phone number', 'address' => 'Address', 'program' => 'Program',
        'year' => 'Year level', 'academicYear' => 'Academic year', 'status' => 'Status',
        'dateOfBirth' => 'Date of birth', 'gender' => 'Gender', 'civilStatus' => 'Civil status',
        'emergencyContact.name' => 'Emergency contact name',
        'emergencyContact.relationship' => 'Emergency contact relationship',
        'emergencyContact.number' => 'Emergency contact number',
    ];

    /** @param array<string, int> $programIds program name => programs.id */
    public function __construct(private array $programIds)
    {
    }

    public function validateCreate(array $input): array
    {
        return $this->validate($input, full: true, requireId: true);
    }

    public function validateReplace(array $input): array
    {
        return $this->validate($input, full: true, requireId: false);
    }

    public function validatePatch(array $input): array
    {
        return $this->validate($input, full: false, requireId: false);
    }

    /** True for exactly 11 digits; spaces are allowed ("0917 123 4567"). */
    public static function isValidPhoneNumber(string $value): bool
    {
        return preg_match('/^\d{11}$/', str_replace(' ', '', $value)) === 1;
    }

    private function validate(array $input, bool $full, bool $requireId): array
    {
        $values = $this->normalize($input);
        $errors = [];

        // The ID is set by POST only; PUT/PATCH take it from the URL.
        // (array_key_exists, not ??=, because null here means "sent, but not text".)
        if (!$requireId) {
            unset($values['id']);
        } elseif (!array_key_exists('id', $values)) {
            $values['id'] = '';
        }

        if ($full) {
            foreach (self::REQUIRED as $field) {
                if (!array_key_exists($field, $values)) {
                    $values[$field] = '';
                }
            }
            foreach (self::OPTIONAL_DEFAULTS as $field => $default) {
                if (!array_key_exists($field, $values) || $values[$field] === '') {
                    $values[$field] = $default;
                }
            }
        } elseif ($values === []) {
            return [[], ['body' => ['Send at least one field to update.']]];
        }

        $data = [];
        foreach ($values as $field => $value) {
            $error = $this->checkField($field, $value, $requireId);
            if ($error !== null) {
                $errors[$field][] = $error;
                continue;
            }
            $data[$field] = $value;
            if ($field === 'program' && $value !== '') {
                $data['programId'] = $this->programIds[$value];
            }
        }

        return [$data, $errors];
    }

    /** Returns an error message, or null when the value is acceptable. */
    private function checkField(string $field, ?string $value, bool $requireId): ?string
    {
        $label = self::LABELS[$field];

        if ($value === null) {
            return "{$label} must be text.";
        }

        if ($value === '') {
            // Status has a default on POST/PUT, but PATCH must not blank it.
            $required = in_array($field, self::REQUIRED, true)
                || $field === 'status'
                || ($field === 'id' && $requireId);
            return $required ? "{$label} is required." : null;
        }

        if (isset(self::MAX_LENGTH[$field]) && mb_strlen($value) > self::MAX_LENGTH[$field]) {
            return "{$label} must be at most " . self::MAX_LENGTH[$field] . ' characters.';
        }

        return match ($field) {
            'id' => preg_match('/^\d{4}-\d{5}$/', $value) === 1
                ? null : 'Student ID must use the format 2026-00125.',
            'email' => filter_var($value, FILTER_VALIDATE_EMAIL) !== false
                ? null : 'Enter a valid email address.',
            'contact', 'emergencyContact.number' => self::isValidPhoneNumber($value)
                ? null : "{$label} must be exactly 11 digits.",
            'program' => isset($this->programIds[$value])
                ? null : 'Choose a valid program.',
            'year' => in_array($value, self::YEAR_LEVELS, true)
                ? null : 'Choose a valid year level (1st–4th Year).',
            'status' => in_array($value, self::STATUSES, true)
                ? null : 'Status must be Active, Pending, Dropped or Completed.',
            'gender' => in_array($value, self::GENDERS, true)
                ? null : 'Choose a valid gender.',
            'civilStatus' => in_array($value, self::CIVIL_STATUSES, true)
                ? null : 'Choose a valid civil status.',
            'dateOfBirth' => $this->checkDateOfBirth($value),
            default => null,
        };
    }

    private function checkDateOfBirth(string $value): ?string
    {
        $date = DateTime::createFromFormat('!Y-m-d', $value);

        // Re-formatting catches dates PHP would silently roll over, like 2005-02-31.
        if ($date === false || $date->format('Y-m-d') !== $value) {
            return 'Date of birth must be a real date in the format YYYY-MM-DD.';
        }
        if ($value > date('Y-m-d')) {
            return 'Date of birth cannot be in the future.';
        }

        return null;
    }

    /**
     * Keeps only known fields that were actually sent, trimmed.
     * A value that isn't text or a number becomes null (reported as an error).
     *
     * @return array<string, ?string>
     */
    private function normalize(array $input): array
    {
        $values = [];

        foreach (self::TEXT_FIELDS as $field) {
            if (array_key_exists($field, $input)) {
                $values[$field] = self::toText($input[$field]);
            }
        }

        if (array_key_exists('emergencyContact', $input)) {
            $contact = $input['emergencyContact'];
            foreach (self::EMERGENCY_FIELDS as $key) {
                if (is_array($contact) && array_key_exists($key, $contact)) {
                    $values["emergencyContact.{$key}"] = self::toText($contact[$key]);
                }
            }
        }

        // Placeholders the existing add-student form sends for empty choices.
        if (($values['contact'] ?? null) === 'Not provided') {
            $values['contact'] = '';
        }
        if (($values['gender'] ?? null) === 'Not specified') {
            $values['gender'] = '';
        }

        return $values;
    }

    private static function toText(mixed $value): ?string
    {
        if (is_string($value) || is_int($value) || is_float($value)) {
            return trim((string) $value);
        }

        return $value === null ? '' : null;
    }
}
