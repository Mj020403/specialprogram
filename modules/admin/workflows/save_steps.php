<?php
require_once dirname(__DIR__, 3) . '/app/bootstrap.php';

$conn = db_conn();require_once app_path('app/config/database.php');
app_require('app/includes/auth.php');
app_require('app/includes/flash.php');

require_role(['developer','super_admin', 'system_admin', 'admin']);

if (!table_exists($conn, 'workflow_template_steps')) {
    set_flash('error', 'Workflow steps table is not ready yet. Run the admin upgrade SQL first.');
    header("Location: /harvest/modules/admin/workflows/index.php");
    exit;
}

$workflow_template_id = (int)($_POST['workflow_template_id'] ?? 0);
$step_no = (int)($_POST['step_no'] ?? 0);
$step_name = trim($_POST['step_name'] ?? '');
$task_type = trim($_POST['task_type'] ?? 'review');
$assign_type = trim($_POST['assign_type'] ?? 'role');
$assigned_user_id = !empty($_POST['assigned_user_id']) ? (int)$_POST['assigned_user_id'] : null;
$assigned_role_code = trim($_POST['assigned_role_code'] ?? '');
$assigned_department_id = !empty($_POST['assigned_department_id']) ? (int)$_POST['assigned_department_id'] : null;
$due_days = (int)($_POST['due_days'] ?? 3);
$is_required = isset($_POST['is_required']) ? 1 : 0;

if ($workflow_template_id <= 0 || $step_no <= 0 || $step_name === '') {
    set_flash('error', 'Please complete all required fields.');
    header("Location: /harvest/modules/admin/workflows/steps.php?id=" . $workflow_template_id);
    exit;
}

$stmt = $conn->prepare("
    INSERT INTO workflow_template_steps (
        workflow_template_id, step_no, step_name, task_type, assign_type,
        assigned_user_id, assigned_role_code, assigned_department_id,
        due_days, is_required, created_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
");
$stmt->bind_param(
    "iisssisiii",
    $workflow_template_id,
    $step_no,
    $step_name,
    $task_type,
    $assign_type,
    $assigned_user_id,
    $assigned_role_code,
    $assigned_department_id,
    $due_days,
    $is_required
);
$stmt->execute();
$stmt->close();

$user_id = (int)$_SESSION['user_id'];
$desc = "Added workflow step {$step_no} to workflow template ID {$workflow_template_id}";

$stmt = $conn->prepare("
    INSERT INTO activity_logs (user_id, action_code, entity_type, entity_id, description, created_at)
    VALUES (?, 'workflow_step_created', 'workflow_templates', ?, ?, NOW())
");
$stmt->bind_param("iis", $user_id, $workflow_template_id, $desc);
$stmt->execute();
$stmt->close();

set_flash('success', 'Workflow step added successfully.');
header("Location: /harvest/modules/admin/workflows/steps.php?id=" . $workflow_template_id);
exit;