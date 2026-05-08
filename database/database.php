<?php
$server = 'localhost';
$user = 'root';
$password = '';
$dbname = 'harvest';

$conn = new mysqli($server, $user, $password, $dbname);
if (!$conn || $conn->connect_error) {
    die('Error!: ' . ($conn->connect_error ?? 'Database connection failed.'));
}

$conn->set_charset('utf8mb4');
$GLOBALS['conn'] = $conn;
