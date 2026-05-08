<?php
require_once dirname(__DIR__, 3) . '/app/bootstrap.php';

$conn = db_conn();require_once app_path('app/config/database.php');
app_require('app/includes/auth.php');
app_require('app/includes/flash.php');

require_role(['developer','super_admin', 'system_admin', 'admin']);

$code = trim($_POST['code'] ?? '');
$name = trim($_POST['name'] ?? '');
$description = trim($_POST['description'] ?? '');
$requires_workflow = (int)($_POST['requires_workflow'] ?? 1);
$default_retention_years = $_POST['default_retention_years'] !== '' ? (int)$_POST['default_retention_years'] : null;
$is_active = (int)($_POST['is_active'] ?? 1);

if ($code === '' || $name === '') {
    set_flash('error', 'Code and name are required.');
    header("Location: /harvest/admin/categories/create.php");
    exit;
}

$stmt = $conn->prepare("
    INSERT INTO document_categories (code, name, description, requires_workflow, default_retention_years, is_active, created_at, updated_at)
    VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
");
$stmt->bind_param("sssiii", $code, $name, $description, $requires_workflow, $default_retention_years, $is_active);
$stmt->execute();
$new_id = $stmt->insert_id;
$stmt->close();

$admin_id = $_SESSION['user_id'];
$desc = "Created category: " . $name;
$stmt = $conn->prepare("
    INSERT INTO activity_logs (user_id, action_code, entity_type, entity_id, description, created_at)
    VALUES (?, 'category_created', 'document_categories', ?, ?, NOW())
");
$stmt->bind_param("iis", $admin_id, $new_id, $desc);
$stmt->execute();
$stmt->close();

set_flash('success', 'Category created successfully.');
header("Location: /harvest/admin/categories/index.php");
exit;