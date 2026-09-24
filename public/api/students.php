<?php

/**
 * Real backend for the Students page in the main app: full CRUD.
 *
 * One file, branching on the HTTP method — the same resource the frontend
 * already expected (GET list / GET one / POST create / PUT update / DELETE),
 * just addressed with ?id=... instead of a /students/{id} path, since this
 * project has no URL router.
 *
 * MySQLi connection + prepared statements throughout, matching the course
 * slides (Week 2-3 "Basic Prepared Statements", Week 6-7 "Prepared
 * Statements", "Filtering Results with WHERE", "Sorting with ORDER BY",
 * "Limiting Results with LIMIT").
 */

require __DIR__ . '/../../backend/config/database.php';
require __DIR__ . '/../../backend/classes/Student.php';
require __DIR__ . '/../../backend/classes/DuplicateStudentException.php';

header('Content-Type: application/json');

function sendJson($success, $data, $message, $errors, $statusCode, $meta = null)
{
    http_response_code($statusCode);
    echo json_encode([
        'success' => $success,
        'data' => $data,
        'message' => $message,
        'errors' => $errors,
        'meta' => $meta,
    ]);
    exit;
}

/** Checks a phone number is exactly 11 digits, ignoring the spaces the
 *  frontend groups them with (e.g. "0922 289 8622"). Character-by-character,
 *  no preg_match — not covered in the course material. */
function isValidPhoneNumber($value)
{
    $digitCount = 0;
    $length = strlen($value);
    for ($i = 0; $i < $length; $i++) {
        $char = $value[$i];
        if ($char === ' ') {
            continue;
        }
        if ($char < '0' || $char > '9') {
            return false;
        }
        $digitCount++;
    }
    return $digitCount === 11;
}

/** Turns one database row into a Student (holding a Program) and shapes it
 *  the same way the API has always responded. */
function shapeStudent($row)
{
    $program = new Program((int) $row['program_id'], $row['program']);

    $student = new Student(
        $row['student_id'],
        $row['first_name'],
        $row['middle_name'] ?? '',
        $row['last_name'],
        $row['suffix'] ?? '',
        $row['email'] ?? '',
        $row['contact_number'] ?? '',
        $row['address'] ?? null,
        $program,
        $row['year_level'],
        $row['status'],
        date('M. j, Y', strtotime($row['created_at']))
    );

    return $student->toArray();
}

$conn = getDbConnection();
$method = $_SERVER['REQUEST_METHOD'];

// ---------------------------------------------------------------- READ ----
if ($method === 'GET') {
    $id = trim($_GET['id'] ?? '');

    if ($id !== '') {
        $stmt = $conn->prepare(
            'SELECT s.*, p.name AS program, d.gender, d.civil_status
               FROM students s
               JOIN programs p ON p.id = s.program_id
               LEFT JOIN student_personal_details d ON d.student_id = s.student_id
              WHERE s.student_id = ?'
        );
        $stmt->bind_param('s', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();

        if (!$row) {
            sendJson(false, null, "Student \"$id\" was not found.", null, 404);
        }

        $studentData = shapeStudent($row);
        $studentData['gender'] = $row['gender'] ?? '';
        $studentData['civilStatus'] = $row['civil_status'] ?? '';

        sendJson(true, $studentData, 'Student retrieved.', null, 200);
    }

    // No id: this is the listing, with search/filter/sort/pagination —
    // WHERE, ORDER BY and LIMIT, all built from bound parameters.
    $search   = trim($_GET['search'] ?? '');
    $program  = trim($_GET['program'] ?? '');
    $year     = trim($_GET['year'] ?? '');
    $status   = trim($_GET['status'] ?? '');
    $sort     = trim($_GET['sort'] ?? '');
    $page     = max(1, (int) ($_GET['page'] ?? 1));
    $perPage  = max(1, min(100, (int) ($_GET['perPage'] ?? 10)));
    $offset   = ($page - 1) * $perPage;

    $where = [];
    $params = [];
    $types = '';

    if ($search !== '') {
        // Split the typed search into separate words, so "Juan Dela Cruz"
        // still matches even though no single column holds that whole
        // string — each word just has to appear somewhere in the name
        // (first, middle, or last), and every word has to be found.
        $words = array_filter(explode(' ', $search));

        // Params must be pushed in the same left-to-right order the ?
        // placeholders will appear in below: student_id, email, then
        // each word's name group.
        $like = "%$search%";
        array_push($params, $like, $like);
        $types .= 'ss';

        $nameGroups = [];
        foreach ($words as $word) {
            $nameGroups[] = '(s.first_name LIKE ? OR s.middle_name LIKE ? OR s.last_name LIKE ?)';
            $likeWord = "%$word%";
            array_push($params, $likeWord, $likeWord, $likeWord);
            $types .= 'sss';
        }
        $nameSearchSql = $nameGroups ? '(' . implode(' AND ', $nameGroups) . ')' : '0';

        $where[] = "(s.student_id LIKE ? OR s.email LIKE ? OR $nameSearchSql)";
    }
    if ($program !== '') {
        $where[] = 'p.name = ?';
        $params[] = $program;
        $types .= 's';
    }
    if ($year !== '') {
        $where[] = 's.year_level = ?';
        $params[] = $year;
        $types .= 's';
    }
    if ($status !== '') {
        $where[] = 's.status = ?';
        $params[] = $status;
        $types .= 's';
    }

    $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

    // A column name can't be sent through a `?` placeholder the way a value
    // can, so instead the requested sort is matched against a fixed list of
    // safe, hardcoded ORDER BY clauses — never the raw query string itself.
    switch ($sort) {
        case 'oldest_first':
            $orderBySql = 's.created_at ASC';
            break;
        case 'name_asc':
            $orderBySql = 's.first_name ASC, s.middle_name ASC, s.last_name ASC';
            break;
        case 'name_desc':
            $orderBySql = 's.first_name DESC, s.middle_name DESC, s.last_name DESC';
            break;
        case 'student_id_asc':
            $orderBySql = 's.student_id ASC';
            break;
        case 'newest_first':
        default:
            $orderBySql = 's.created_at DESC';
            break;
    }

    $countStmt = $conn->prepare("SELECT COUNT(*) AS total FROM students s JOIN programs p ON p.id = s.program_id $whereSql");
    if ($types !== '') {
        $countStmt->bind_param($types, ...$params);
    }
    $countStmt->execute();
    $total = (int) $countStmt->get_result()->fetch_assoc()['total'];

    $listStmt = $conn->prepare(
        "SELECT s.*, p.name AS program
           FROM students s
           JOIN programs p ON p.id = s.program_id
           $whereSql
           ORDER BY $orderBySql
           LIMIT ? OFFSET ?"
    );
    $listTypes = $types . 'ii';
    $listParams = $params;
    $listParams[] = $perPage;
    $listParams[] = $offset;
    $listStmt->bind_param($listTypes, ...$listParams);
    $listStmt->execute();
    $result = $listStmt->get_result();

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = shapeStudent($row);
    }

    sendJson(true, $rows, 'Students retrieved.', null, 200, [
        'page' => $page,
        'perPage' => $perPage,
        'total' => $total,
        'totalPages' => max(1, (int) ceil($total / $perPage)),
    ]);
}

// -------------------------------------------------------------- UPDATE ----
if ($method === 'PUT') {
    $id = trim($_GET['id'] ?? '');
    if ($id === '') {
        sendJson(false, null, 'Missing student ID.', null, 400);
    }

    $body = json_decode(file_get_contents('php://input'), true);
    if (!is_array($body)) {
        $body = [];
    }

    $firstName  = trim($body['firstName'] ?? '');
    $middleName = trim($body['middleName'] ?? '');
    $lastName   = trim($body['lastName'] ?? '');
    $suffix     = trim($body['suffix'] ?? '');
    $email      = trim($body['email'] ?? '');
    $contact    = trim($body['phone'] ?? '');
    $status     = trim($body['status'] ?? '');

    $errors = [];
    if ($firstName == '') {
        $errors[] = 'First name is required.';
    }
    if ($lastName == '') {
        $errors[] = 'Last name is required.';
    }
    if ($email != '' && (strpos($email, '@') === false || strpos($email, '.') === false)) {
        $errors[] = "\"$email\" doesn't look like a valid email address.";
    }
    if ($contact != '' && !isValidPhoneNumber($contact)) {
        $errors[] = 'Phone number must be exactly 11 digits.';
    }

    $validStatus = false;
    switch ($status) {
        case 'Active':
        case 'Pending':
        case 'Dropped':
        case 'Completed':
            $validStatus = true;
            break;
    }
    if (!$validStatus) {
        $errors[] = 'Please choose a valid student status.';
    }

    if (count($errors) > 0) {
        sendJson(false, null, implode(' ', $errors), null, 422);
    }

    $checkStmt = $conn->prepare('SELECT student_id FROM students WHERE student_id = ?');
    $checkStmt->bind_param('s', $id);
    $checkStmt->execute();
    if (mysqli_num_rows($checkStmt->get_result()) === 0) {
        sendJson(false, null, "Student \"$id\" was not found.", null, 404);
    }

    $updateStmt = $conn->prepare(
        'UPDATE students
            SET first_name = ?, middle_name = ?, last_name = ?, suffix = ?, email = ?, contact_number = ?, status = ?
          WHERE student_id = ?'
    );
    $updateStmt->bind_param('ssssssss', $firstName, $middleName, $lastName, $suffix, $email, $contact, $status, $id);
    $updateStmt->execute();

    sendJson(true, ['id' => $id], "Student \"$id\" updated successfully.", null, 200);
}

// -------------------------------------------------------------- DELETE ----
if ($method === 'DELETE') {
    $id = trim($_GET['id'] ?? '');
    if ($id === '') {
        sendJson(false, null, 'Missing student ID.', null, 400);
    }

    $checkStmt = $conn->prepare('SELECT student_id FROM students WHERE student_id = ?');
    $checkStmt->bind_param('s', $id);
    $checkStmt->execute();
    if (mysqli_num_rows($checkStmt->get_result()) === 0) {
        sendJson(false, null, "Student \"$id\" was not found.", null, 404);
    }

    // ON DELETE CASCADE (set up when the related tables were created) removes
    // the matching student_personal_details and student_emergency_contacts
    // rows automatically — no extra queries needed here.
    $deleteStmt = $conn->prepare('DELETE FROM students WHERE student_id = ?');
    $deleteStmt->bind_param('s', $id);
    $deleteStmt->execute();

    sendJson(true, null, "Student \"$id\" was deleted.", null, 200);
}

// -------------------------------------------------------------- CREATE ----
if ($method !== 'POST') {
    sendJson(false, null, 'Method not allowed.', null, 405);
}

// The JS app sends a JSON body, not a normal form post, so it is read from
// php://input and decoded instead of using $_POST.
$body = json_decode(file_get_contents('php://input'), true);
if (!is_array($body)) {
    $body = [];
}

$studentId    = trim($body['id'] ?? '');
$firstName    = trim($body['firstName'] ?? '');
$middleName   = trim($body['middleName'] ?? '');
$lastName     = trim($body['lastName'] ?? '');
$suffix       = trim($body['suffix'] ?? '');
$email        = trim($body['email'] ?? '');
$contact      = trim($body['contact'] ?? '');
$address      = trim($body['address'] ?? '');
$program      = trim($body['program'] ?? '');
$yearLevel    = trim($body['year'] ?? '');
$academicYear = trim($body['academicYear'] ?? '');
$status       = trim($body['status'] ?? '') != '' ? trim($body['status']) : 'Active';
$dateOfBirth  = trim($body['dateOfBirth'] ?? '');
$gender       = trim($body['gender'] ?? '');
$civilStatus  = trim($body['civilStatus'] ?? '');

$emergencyContact = is_array($body['emergencyContact'] ?? null) ? $body['emergencyContact'] : [];
$emergencyName         = trim($emergencyContact['name'] ?? '');
$emergencyRelationship = trim($emergencyContact['relationship'] ?? '');
$emergencyNumber       = trim($emergencyContact['number'] ?? '');

// The frontend fills a blank phone number with the placeholder text "Not
// provided" — treat that the same as blank when deciding what to store.
if ($contact == 'Not provided') {
    $contact = '';
}

// The frontend fills a blank gender with "Not specified" — same idea.
if ($gender == 'Not specified') {
    $gender = '';
}

// An empty date string is not a valid DATE value in MySQL — store NULL instead.
$dateOfBirthValue = $dateOfBirth != '' ? $dateOfBirth : null;

$errors = [];

if ($studentId == '') {
    $errors[] = 'Student ID is required.';
}
if ($firstName == '') {
    $errors[] = 'First name is required.';
}
if ($lastName == '') {
    $errors[] = 'Last name is required.';
}
if ($address == '') {
    $errors[] = 'Address is required.';
}

// Program is required, and must be a real row in the programs table — this
// is looked up in the database now instead of a hardcoded list, since
// program_id is a real foreign key after the normalization pass.
$programId = null;
$programStmt = $conn->prepare('SELECT id FROM programs WHERE name = ?');
$programStmt->bind_param('s', $program);
$programStmt->execute();
$programRow = $programStmt->get_result()->fetch_assoc();

if ($programRow) {
    $programId = (int) $programRow['id'];
} else {
    $errors[] = 'Please choose a valid program.';
}

$validYear = false;
switch ($yearLevel) {
    case '1st Year':
    case '2nd Year':
    case '3rd Year':
    case '4th Year':
        $validYear = true;
        break;
}
if (!$validYear) {
    $errors[] = 'Please choose a valid year level (1st-4th Year).';
}

// Status always has a value by this point (defaults to 'Active' above), so
// this only ever fires if something other than a real option was sent.
$validStatus = false;
switch ($status) {
    case 'Active':
    case 'Pending':
    case 'Dropped':
    case 'Completed':
        $validStatus = true;
        break;
}
if (!$validStatus) {
    $errors[] = 'Please choose a valid student status.';
}

// Gender is required, so a blank value falls through every case below and is
// correctly rejected without a separate blank check.
$validGender = false;
switch ($gender) {
    case 'Male':
    case 'Female':
    case 'Other':
        $validGender = true;
        break;
}
if (!$validGender) {
    $errors[] = 'Please choose a valid gender.';
}

// Civil Status is required, so a blank value falls through every case below
// and is correctly rejected without a separate blank check.
$validCivilStatus = false;
switch ($civilStatus) {
    case 'Single':
    case 'Married':
    case 'Other':
        $validCivilStatus = true;
        break;
}
if (!$validCivilStatus) {
    $errors[] = 'Please choose a valid civil status.';
}

if ($email == '') {
    $errors[] = 'Email is required.';
} elseif (strpos($email, '@') === false || strpos($email, '.') === false) {
    $errors[] = "\"$email\" doesn't look like a valid email address.";
}

if ($contact == '') {
    $errors[] = 'Phone number is required.';
} elseif (!isValidPhoneNumber($contact)) {
    $errors[] = 'Phone number must be exactly 11 digits.';
}

if ($emergencyNumber != '' && !isValidPhoneNumber($emergencyNumber)) {
    $errors[] = 'Emergency contact number must be exactly 11 digits.';
}

// Date of birth is required, and if given it should not be in the future.
// ISO dates (YYYY-MM-DD) compare correctly as plain strings.
if ($dateOfBirth == '') {
    $errors[] = 'Date of birth is required.';
} elseif ($dateOfBirth > date('Y-m-d')) {
    $errors[] = 'Date of birth cannot be in the future.';
}

if (count($errors) > 0) {
    sendJson(false, null, implode(' ', $errors), null, 422);
}

// Emergency contact fields are optional — store NULL rather than an empty
// string when nothing was given.
$emergencyNameValue         = $emergencyName != '' ? $emergencyName : null;
$emergencyRelationshipValue = $emergencyRelationship != '' ? $emergencyRelationship : null;
$emergencyNumberValue       = $emergencyNumber != '' ? $emergencyNumber : null;

// This is the one critical operation guarded with a custom, thrown exception
// (Week 4-5 "Exception Handling"): registering a student under an ID that's
// already taken. The check runs first, before anything touches the database
// for real, and a DuplicateStudentException stops the whole operation cold.
try {
    $checkStmt = $conn->prepare('SELECT student_id FROM students WHERE student_id = ?');
    $checkStmt->bind_param('s', $studentId);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();

    if (mysqli_num_rows($checkResult) > 0) {
        throw new DuplicateStudentException("A student with ID \"$studentId\" already exists.");
    }

    // One student now spans 3 tables (students, student_personal_details,
    // student_emergency_contacts), so all 3 inserts run as a single
    // transaction: either every table gets its row, or none of them do.
    mysqli_begin_transaction($conn);

    $insertStudent = $conn->prepare(
        'INSERT INTO students
            (student_id, first_name, middle_name, last_name, suffix, email,
             contact_number, address, program_id, year_level, academic_year, status)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $insertStudent->bind_param(
        'ssssssssisss',
        $studentId,
        $firstName,
        $middleName,
        $lastName,
        $suffix,
        $email,
        $contact,
        $address,
        $programId,
        $yearLevel,
        $academicYear,
        $status
    );
    $insertStudent->execute();

    $insertPersonalDetails = $conn->prepare(
        'INSERT INTO student_personal_details (student_id, date_of_birth, gender, civil_status)
         VALUES (?, ?, ?, ?)'
    );
    $insertPersonalDetails->bind_param('ssss', $studentId, $dateOfBirthValue, $gender, $civilStatus);
    $insertPersonalDetails->execute();

    $insertEmergencyContact = $conn->prepare(
        'INSERT INTO student_emergency_contacts (student_id, name, relationship, number)
         VALUES (?, ?, ?, ?)'
    );
    $insertEmergencyContact->bind_param(
        'ssss',
        $studentId,
        $emergencyNameValue,
        $emergencyRelationshipValue,
        $emergencyNumberValue
    );
    $insertEmergencyContact->execute();

    mysqli_commit($conn);

    // Built from the same Student/Person classes the rest of the API uses —
    // getFullName() here instead of "$firstName $lastName" is what makes the
    // success message actually include Middle Name and Suffix too.
    $newStudent = new Student(
        $studentId, $firstName, $middleName, $lastName, $suffix,
        $email, $contact, $address, new Program($programId, $program),
        $yearLevel, $status, date('M. j, Y')
    );

    sendJson(true, [
        'id' => $studentId,
        'firstName' => $firstName,
        'lastName' => $lastName,
        'program' => $program,
        'year' => $yearLevel,
        'status' => $status,
    ], $newStudent->getFullName() . " ($studentId) was saved to the database.", null, 201);
} catch (DuplicateStudentException $e) {
    sendJson(false, null, $e->getMessage(), null, 409);
} catch (mysqli_sql_exception $e) {
    mysqli_rollback($conn);
    sendJson(false, null, 'Something went wrong while saving: ' . $e->getMessage(), null, 500);
}
