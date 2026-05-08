<?php
require_once dirname(__DIR__, 4) . '/app/bootstrap.php';

$conn = db_conn();require_once app_path('app/config/database.php');
app_require('app/includes/auth.php');
app_require('app/includes/flash.php');

require_login();

$user_id = (int)$_SESSION['user_id'];

$current_password = trim($_POST['current_password'] ?? '');
$new_password = trim($_POST['new_password'] ?? '');
$confirm_new_password = trim($_POST['confirm_new_password'] ?? '');

if ($current_password === '' || $new_password === '' || $confirm_new_password === '') {
    set_flash('error', 'All password fields are required.');
    header("Location: /harvest/password/change.php");
    exit;
}

if ($new_password !== $confirm_new_password) {
    set_flash('error', 'New password and confirmation do not match.');
    header("Location: /harvest/password/change.php");
    exit;
}

if (strlen($new_password) < 6) {
    set_flash('error', 'New password must be at least 6 characters long.');
    header("Location: /harvest/password/change.php");
    exit;
}

$stmt = $conn->prepare("
    SELECT password_hash
    FROM users
    WHERE id = ?
    LIMIT 1
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    set_flash('error', 'User account not found.');
    header("Location: /harvest/password/change.php");
    exit;
}

if (!password_verify($current_password, $user['password_hash'])) {
    set_flash('error', 'Current password is incorrect.');
    header("Location: /harvest/password/change.php");
    exit;
}

$new_hash = password_hash($new_password, PASSWORD_DEFAULT);

$stmt = $conn->prepare("
    UPDATE users
    SET password_hash = ?, updated_at = NOW()
    WHERE id = ?
");
$stmt->bind_param("si", $new_hash, $user_id);
$stmt->execute();
$stmt->close();

$desc = "Changed own password";
$stmt = $conn->prepare("
    INSERT INTO activity_logs (user_id, action_code, entity_type, entity_id, description, created_at)
    VALUES (?, 'user_password_changed', 'users', ?, ?, NOW())
");
$stmt->bind_param("iis", $user_id, $user_id, $desc);
$stmt->execute();
$stmt->close();

set_flash('success', 'Password updated successfully.');
header("Location: /harvest/password/change.php");
exit;