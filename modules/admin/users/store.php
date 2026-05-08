<?php
require_once dirname(__DIR__, 3) . '/app/bootstrap.php';

$conn = db_conn();require_once app_path('app/config/database.php');
app_require('app/includes/auth.php');
app_require('app/includes/flash.php');

require_role(['developer','super_admin', 'system_admin', 'admin']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /harvest/modules/admin/users/index.php');
    exit;
}

$email = trim($_POST['email'] ?? '');
$username = trim($_POST['username'] ?? '');
$password = trim($_POST['password'] ?? '');
$employee_no = trim($_POST['employee_no'] ?? '');
$first_name = trim($_POST['first_name'] ?? '');
$middle_name = trim($_POST['middle_name'] ?? '');
$last_name = trim($_POST['last_name'] ?? '');
$suffix_name = trim($_POST['suffix_name'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$job_title = trim($_POST['job_title'] ?? '');
$role_id = (int)($_POST['role_id'] ?? 0);
$department_id = (int)($_POST['department_id'] ?? 0);
$employment_status = trim($_POST['employment_status'] ?? 'active');

if ($email === '' || $username === '' || $password === '' || $first_name === '' || $last_name === '' || $role_id <= 0 || $department_id <= 0) {
    set_flash('error', 'Please fill in all required fields.');
    header('Location: /harvest/admin/users/create.php');
    exit;
}

$imageUpload = app_store_profile_image($_FILES['profile_image'] ?? []);
if (!($imageUpload['ok'] ?? false)) {
    set_flash('error', $imageUpload['message'] ?? 'Profile image upload failed.');
    header('Location: /harvest/admin/users/create.php');
    exit;
}
$profileImagePath = $imageUpload['path'] ?? null;
$password_hash = password_hash($password, PASSWORD_DEFAULT);
$uuid = $conn->query('SELECT UUID() AS uuid')->fetch_assoc()['uuid'];

$conn->begin_transaction();
try {
    $stmt = $conn->prepare('INSERT INTO users (uuid, email, username, password_hash, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, 1, NOW(), NOW())');
    $stmt->bind_param('ssss', $uuid, $email, $username, $password_hash);
    $stmt->execute();
    $user_id = $stmt->insert_id;
    $stmt->close();

    $stmt = $conn->prepare('INSERT INTO user_profiles (user_id, employee_no, first_name, middle_name, last_name, suffix_name, phone, job_title, profile_image_path, primary_department_id, employment_status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())');
    $stmt->bind_param('issssssssis', $user_id, $employee_no, $first_name, $middle_name, $last_name, $suffix_name, $phone, $job_title, $profileImagePath, $department_id, $employment_status);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare('INSERT INTO user_role_assignments (user_id, role_id, is_active, assigned_at, ended_at) VALUES (?, ?, 1, NOW(), NULL)');
    $stmt->bind_param('ii', $user_id, $role_id);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare('INSERT INTO user_department_assignments (user_id, department_id, is_primary, is_active, assigned_at, ended_at) VALUES (?, ?, 1, 1, NOW(), NULL)');
    $stmt->bind_param('ii', $user_id, $department_id);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("INSERT INTO activity_logs (user_id, action_code, entity_type, entity_id, description, created_at) VALUES (?, 'user_created', 'users', ?, ?, NOW())");
    $admin_id = (int)$_SESSION['user_id'];
    $description = 'Created user: ' . $email;
    $stmt->bind_param('iis', $admin_id, $user_id, $description);
    $stmt->execute();
    $stmt->close();

    $conn->commit();
    set_flash('success', 'User created successfully.');
    header('Location: /harvest/modules/admin/users/index.php');
    exit;
} catch (Exception $e) {
    $conn->rollback();
    set_flash('error', 'Failed to create user. Error: ' . $e->getMessage());
    header('Location: /harvest/admin/users/create.php');
    exit;
}
