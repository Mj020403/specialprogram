<?php
require_once dirname(__DIR__, 3) . '/app/bootstrap.php';

$conn = db_conn();require_once app_path('app/config/database.php');
app_require('app/includes/auth.php');
app_require('app/includes/flash.php');

require_role(['developer','super_admin', 'system_admin', 'admin']);

$id = (int)($_POST['id'] ?? 0);
$code = trim($_POST['code'] ?? '');
$name = trim($_POST['name'] ?? '');
$parent_department_id = $_POST['parent_department_id'] !== '' ? (int)$_POST['parent_department_id'] : null;
$description = trim($_POST['description'] ?? '');
$is_active = (int)($_POST['is_active'] ?? 1);

if ($id <= 0 || $code === '' || $name === '') {
    set_flash('error', 'Code and name are required.');
    header("Location: /harvest/admin/departments/edit.php?id=" . $id);
    exit;
}

$stmt = $conn->prepare("
    UPDATE departments
    SET code = ?, name = ?, description = ?, parent_department_id = ?, is_active = ?, updated_at = NOW()
    WHERE id = ?
");
$stmt->bind_param("sssiii", $code, $name, $description, $parent_department_id, $is_active, $id);
$stmt->execute();
$stmt->close();

$admin_id = $_SESSION['user_id'];
$desc = "Updated department ID: " . $id;
$stmt = $conn->prepare("
    INSERT INTO activity_logs (user_id, action_code, entity_type, entity_id, description, created_at)
    VALUES (?, 'department_updated', 'departments', ?, ?, NOW())
");
$stmt->bind_param("iis", $admin_id, $id, $desc);
$stmt->execute();
$stmt->close();

set_flash('success', 'Department updated successfully.');
header("Location: /harvest/admin/departments/index.php");
exit;