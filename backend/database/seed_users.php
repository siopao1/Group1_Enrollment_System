<?php

/**
 * Creates one demo account per role.
 *
 * Run once from the project folder, after importing schema.sql:
 *     php backend/database/seed_users.php
 * (With XAMPP: C:\xampp\php\php.exe backend/database/seed_users.php)
 *
 * Passwords are hashed with password_hash() before they are stored, so the
 * users table never contains a plain-text password. Running the script again
 * is safe: INSERT IGNORE skips accounts whose username or email already exists.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Run this script from the command line.');
}

require __DIR__ . '/../config/database.php';

$conn = getDbConnection();

$users = [
    // username,   full name,              email,                        password,          role
    ['admin',     'System Administrator', 'admin@university.test',     'Admin@12345',     'admin'],
    ['registrar', 'Registrar Office',     'registrar@university.test', 'Registrar@12345', 'registrar'],
    ['faculty',   'Faculty Member',       'faculty@university.test',   'Faculty@12345',   'faculty'],
];

$stmt = $conn->prepare(
    'INSERT IGNORE INTO users (username, full_name, email, password_hash, role)
     VALUES (?, ?, ?, ?, ?)'
);

foreach ($users as [$username, $fullName, $email, $plainPassword, $role]) {
    $hash = password_hash($plainPassword, PASSWORD_DEFAULT);
    $stmt->bind_param('sssss', $username, $fullName, $email, $hash, $role);
    $stmt->execute();

    echo $stmt->affected_rows === 1
        ? "Created  {$username} ({$role})\n"
        : "Skipped  {$username} (already exists)\n";
}
