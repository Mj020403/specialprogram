<?php
require_once dirname(__DIR__, 3) . '/app/bootstrap.php';

$conn = db_conn();require_once app_path('app/config/database.php');
app_require('app/includes/auth.php');
app_require('app/includes/flash.php');

require_role(['developer','super_admin', 'system_admin', 'admin']);

$code = trim($_POST['code'] ?? '');
$name = trim($_POST['name'] ?? '');
$parent_department_id = $_POST['parent_department_id'] !== '' ? (int)$_POST['parent_department_id'] : null;
$description = trim($_POST['description'] ?? '');
$is_active = (int)($_POST['is_active'] ?? 1);

if ($code === '' || $name === '') {
    set_flash('error', 'Code and name are required.');
    header("Location: /harvest/admin/departments/create.php");
    exit;
}

$stmt = $conn->prepare("
    INSERT INTO departments (code, name, description, parent_department_id, is_active, created_at, updated_at)
    VALUES (?, ?, ?, ?, ?, NOW(), NOW())
");
$stmt->bind_param("sssii", $code, $name, $description, $parent_department_id, $is_active);
$stmt->execute();
$new_id = $stmt->insert_id;
$stmt->close();

$admin_id = $_SESSION['user_id'];
$desc = "Created department: " . $name;
$stmt = $conn->prepare("
    INSERT INTO activity_logs (user_id, action_code, entity_type, entity_id, description, created_at)
    VALUES (?, 'department_created', 'departments', ?, ?, NOW())
");
$stmt->bind_param("iis", $admin_id, $new_id, $desc);
$stmt->execute();
$stmt->close();

set_flash('success', 'Department created successfully.');
header("Location: /harvest/admin/departments/index.php");
exit;