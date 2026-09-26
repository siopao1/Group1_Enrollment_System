<?php

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

        
        
        $words = array_filter(explode(' ', $search));

        
        
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

    
    
    $deleteStmt = $conn->prepare('DELETE FROM students WHERE student_id = ?');
    $deleteStmt->bind_param('s', $id);
    $deleteStmt->execute();

    sendJson(true, null, "Student \"$id\" was deleted.", null, 200);
}

if ($method !== 'POST') {
    sendJson(false, null, 'Method not allowed.', null, 405);
}

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

if ($contact == 'Not provided') {
    $contact = '';
}

if ($gender == 'Not specified') {
    $gender = '';
}

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

if ($dateOfBirth == '') {
    $errors[] = 'Date of birth is required.';
} elseif ($dateOfBirth > date('Y-m-d')) {
    $errors[] = 'Date of birth cannot be in the future.';
}

if (count($errors) > 0) {
    sendJson(false, null, implode(' ', $errors), null, 422);
}

$emergencyNameValue         = $emergencyName != '' ? $emergencyName : null;
$emergencyRelationshipValue = $emergencyRelationship != '' ? $emergencyRelationship : null;
$emergencyNumberValue       = $emergencyNumber != '' ? $emergencyNumber : null;

try {
    $checkStmt = $conn->prepare('SELECT student_id FROM students WHERE student_id = ?');
    $checkStmt->bind_param('s', $studentId);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();

    if (mysqli_num_rows($checkResult) > 0) {
        throw new DuplicateStudentException("A student with ID \"$studentId\" already exists.");
    }

    
    
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
