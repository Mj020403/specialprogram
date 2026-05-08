<?php
require_once dirname(__DIR__, 3) . '/app/bootstrap.php';

$conn = db_conn();require_once app_path('app/config/database.php');
app_require('app/includes/auth.php');
app_require('app/includes/flash.php');

require_role(['developer','super_admin', 'system_admin', 'admin']);

$id = (int)($_POST['id'] ?? 0);
$code = trim($_POST['code'] ?? '');
$name = trim($_POST['name'] ?? '');
$description = trim($_POST['description'] ?? '');
$requires_workflow = (int)($_POST['requires_workflow'] ?? 1);
$default_retention_years = $_POST['default_retention_years'] !== '' ? (int)$_POST['default_retention_years'] : null;
$is_active = (int)($_POST['is_active'] ?? 1);

$stmt = $conn->prepare("
    UPDATE document_categories
    SET code = ?, name = ?, description = ?, requires_workflow = ?, default_retention_years = ?, is_active = ?, updated_at = NOW()
    WHERE id = ?
");
$stmt->bind_param("sssiiii", $code, $name, $description, $requires_workflow, $default_retention_years, $is_active, $id);
$stmt->execute();
$stmt->close();

$admin_id = $_SESSION['user_id'];
$desc = "Updated category ID: " . $id;
$stmt = $conn->prepare("
    INSERT INTO activity_logs (user_id, action_code, entity_type, entity_id, description, created_at)
    VALUES (?, 'category_updated', 'document_categories', ?, ?, NOW())
");
$stmt->bind_param("iis", $admin_id, $id, $desc);
$stmt->execute();
$stmt->close();

set_flash('success', 'Category updated successfully.');
header("Location: /harvest/admin/categories/index.php");
exit;