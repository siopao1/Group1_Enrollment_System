<?php

/**
 * Opens the database connection every backend script uses.
 * Same mysqli_connect() pattern shown in the course slides
 * (Week 2-3 "PHP-MySQL Connection", Week 6-7 "Connecting PHP to MySQL"),
 * just wrapped in a function so every script can reuse it.
 */
function getDbConnection()
{
    $host = '127.0.0.1';
    $username = 'root';
    $password = '';
    $database = 'enrollment_system_min';

    $conn = mysqli_connect($host, $username, $password, $database);

    if (!$conn) {
        die('Connection failed: ' . mysqli_connect_error());
    }

    return $conn;
}
