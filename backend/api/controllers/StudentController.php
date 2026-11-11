<?php

declare(strict_types=1);

/**
 * HTTP layer for /api/students. Reads the request, asks the validator and
 * repository to do the work, and picks the status code. No SQL here.
 *
 *   GET    /api/students          index()    200
 *   GET    /api/students/{id}     show()     200 | 404
 *   POST   /api/students          store()    201 | 409 | 415 | 422
 *   PUT    /api/students/{id}     replace()  200 | 404 | 415 | 422
 *   PATCH  /api/students/{id}     patch()    200 | 404 | 415 | 422
 *   DELETE /api/students/{id}     destroy()  204 | 404
 */
final class StudentController
{
    public function __construct(private StudentRepository $students)
    {
    }

    public function index(): never
    {
        $filters = [
            'search'  => Request::query('search'),
            'program' => Request::query('program'),
            'year'    => Request::query('year'),
            'status'  => Request::query('status'),
            'sort'    => Request::query('sort', 'newest_first'),
            'page'    => Request::query('page', '1'),
            'perPage' => Request::query('perPage', '10'),
        ];

        $errors = [];
        if ($filters['year'] !== '' && !in_array($filters['year'], StudentValidator::YEAR_LEVELS, true)) {
            $errors['year'][] = 'Year must be one of: ' . implode(', ', StudentValidator::YEAR_LEVELS) . '.';
        }
        if ($filters['status'] !== '' && !in_array($filters['status'], StudentValidator::STATUSES, true)) {
            $errors['status'][] = 'Status must be one of: ' . implode(', ', StudentValidator::STATUSES) . '.';
        }
        if ($filters['sort'] === '') {
            $filters['sort'] = 'newest_first';
        }
        if (!isset(StudentRepository::SORTS[$filters['sort']])) {
            $errors['sort'][] = 'Sort must be one of: ' . implode(', ', array_keys(StudentRepository::SORTS)) . '.';
        }
        if (!ctype_digit($filters['page']) || (int) $filters['page'] < 1) {
            $errors['page'][] = 'Page must be a whole number of 1 or more.';
        }
        if (!ctype_digit($filters['perPage']) || (int) $filters['perPage'] < 1 || (int) $filters['perPage'] > 100) {
            $errors['perPage'][] = 'perPage must be a whole number from 1 to 100.';
        }
        if ($errors !== []) {
            Response::error('Invalid query parameters.', 422, $errors);
        }

        $filters['page'] = (int) $filters['page'];
        $filters['perPage'] = (int) $filters['perPage'];
        $result = $this->students->paginate($filters);

        Response::success('Students retrieved.', $result['rows'], 200, [
            'page'       => $filters['page'],
            'perPage'    => $filters['perPage'],
            'total'      => $result['total'],
            'totalPages' => max(1, (int) ceil($result['total'] / $filters['perPage'])),
        ]);
    }

    public function show(string $id): never
    {
        $student = $this->students->find($id) ?? self::notFound($id);

        Response::success('Student retrieved.', $student);
    }

    public function store(): never
    {
        $input = Request::json();
        [$data, $errors] = $this->validator()->validateCreate($input);
        if ($errors !== []) {
            Response::error('Validation failed.', 422, $errors);
        }

        try {
            $this->students->create($data);
        } catch (DuplicateStudentException $e) {
            Response::error($e->getMessage(), 409, ['id' => [$e->getMessage()]]);
        }

        $student = $this->students->find($data['id']);
        header('Location: ' . Request::apiBase() . '/students/' . rawurlencode($data['id']));

        Response::success("{$student['name']} ({$data['id']}) was added.", $student, 201);
    }

    /** PUT: full replacement — every required field must be sent. */
    public function replace(string $id): never
    {
        $this->ensureExists($id);
        $input = Request::json();
        self::rejectIdChange($input, $id);

        [$data, $errors] = $this->validator()->validateReplace($input);
        if ($errors !== []) {
            Response::error('Validation failed.', 422, $errors);
        }

        $this->students->update($id, $data);

        Response::success("Student \"{$id}\" was updated.", $this->students->find($id));
    }

    /** PATCH: partial update — only the fields sent are changed. */
    public function patch(string $id): never
    {
        $this->ensureExists($id);
        $input = Request::json();
        self::rejectIdChange($input, $id);

        [$data, $errors] = $this->validator()->validatePatch($input);
        if ($errors !== []) {
            Response::error('Validation failed.', 422, $errors);
        }

        $this->students->update($id, $data);

        Response::success("Student \"{$id}\" was updated.", $this->students->find($id));
    }

    public function destroy(string $id): never
    {
        if (!$this->students->delete($id)) {
            self::notFound($id);
        }

        Response::noContent();
    }

    private function validator(): StudentValidator
    {
        return new StudentValidator($this->students->programIds());
    }

    private function ensureExists(string $id): void
    {
        if (!$this->students->exists($id)) {
            self::notFound($id);
        }
    }

    /** The ID lives in the URL; a body that tries to change it is rejected. */
    private static function rejectIdChange(array $input, string $id): void
    {
        if (array_key_exists('id', $input) && trim((string) (is_scalar($input['id']) ? $input['id'] : '')) !== $id) {
            Response::error('Validation failed.', 422, ['id' => ['The student ID cannot be changed.']]);
        }
    }

    private static function notFound(string $id): never
    {
        Response::error("Student \"{$id}\" was not found.", 404);
    }
}
