<?php
require_once dirname(__DIR__, 3) . '/app/bootstrap.php';

$conn = db_conn();require_once app_path('app/config/database.php');
app_require('app/includes/auth.php');
app_require('app/includes/flash.php');

require_role(['developer','super_admin', 'system_admin', 'admin']);

if (!table_exists($conn, 'workflow_templates')) {
    set_flash('error', 'Workflow templates table is not ready yet. Run the admin upgrade SQL first.');
    header("Location: /harvest/modules/admin/workflows/index.php");
    exit;
}

$name = trim($_POST['name'] ?? '');
$description = trim($_POST['description'] ?? '');
$category_id = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
$user_id = (int)$_SESSION['user_id'];

if ($name === '') {
    set_flash('error', 'Workflow name is required.');
    header("Location: /harvest/modules/admin/workflows/create.php");
    exit;
}

$stmt = $conn->prepare("
    INSERT INTO workflow_templates (name, description, category_id, is_active, created_by_user_id, created_at, updated_at)
    VALUES (?, ?, ?, 1, ?, NOW(), NOW())
");
$stmt->bind_param("ssii", $name, $description, $category_id, $user_id);
$stmt->execute();
$template_id = $stmt->insert_id;
$stmt->close();

$desc = "Created workflow template: {$name}";
$stmt = $conn->prepare("
    INSERT INTO activity_logs (user_id, action_code, entity_type, entity_id, description, created_at)
    VALUES (?, 'workflow_template_created', 'workflow_templates', ?, ?, NOW())
");
$stmt->bind_param("iis", $user_id, $template_id, $desc);
$stmt->execute();
$stmt->close();

set_flash('success', 'Workflow template created successfully.');
header("Location: /harvest/modules/admin/workflows/steps.php?id=" . $template_id);
exit;