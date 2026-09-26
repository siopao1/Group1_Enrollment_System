<?php

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
