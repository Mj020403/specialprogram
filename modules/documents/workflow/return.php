<?php
require_once dirname(__DIR__, 3) . '/app/bootstrap.php';

$conn = db_conn();require_once app_path('app/config/database.php');
app_require('app/includes/app_helpers.php');
app_require('app/includes/auth.php');
app_require('app/includes/flash.php');

require_login();

$task_id = (int)($_POST['task_id'] ?? 0);
$remarks = trim($_POST['remarks'] ?? '');
$user_id = (int)($_SESSION['user_id'] ?? 0);
$role_code = $_SESSION['role_code'] ?? '';
$allowedRoles = ['records_officer', 'approver', 'department_head', 'super_admin', 'system_admin', 'admin'];

if ($remarks === '') {
    set_flash('error', 'Revision reason is required.');
    header('Location: ' . app_workflow_view_path($role_code, $task_id));
    exit;
}

if (!in_array($role_code, $allowedRoles, true)) {
    set_flash('error', 'You are not allowed to return this task.');
    header('Location: ' . app_workflow_index_path($role_code));
    exit;
}

$stmt = $conn->prepare('SELECT t.*, d.creator_user_id, d.id AS document_id, d.document_number FROM document_workflow_tasks t JOIN documents d ON d.id = t.document_id WHERE t.id = ? LIMIT 1');
$stmt->bind_param('i', $task_id);
$stmt->execute();
$task = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$task) {
    set_flash('error', 'Task not found.');
    header('Location: ' . app_workflow_index_path($role_code));
    exit;
}

$conn->begin_transaction();

try {
    $stmt = $conn->prepare("INSERT INTO document_approvals (workflow_instance_id, task_id, document_id, decided_by_user_id, decision, remarks, decided_at) VALUES (?, ?, ?, ?, 'returned_for_revision', ?, NOW())");
    $stmt->bind_param('iiiis', $task['workflow_instance_id'], $task_id, $task['document_id'], $user_id, $remarks);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("UPDATE document_workflow_tasks SET status = 'returned', acted_at = NOW(), completed_at = NOW(), updated_at = NOW() WHERE id = ?");
    $stmt->bind_param('i', $task_id);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("UPDATE document_workflow_instances SET status = 'returned', completed_at = NOW(), ended_reason = ? WHERE id = ?");
    $stmt->bind_param('si', $remarks, $task['workflow_instance_id']);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("UPDATE documents SET status = 'for_revision', returned_at = NOW(), updated_at = NOW() WHERE id = ?");
    $stmt->bind_param('i', $task['document_id']);
    $stmt->execute();
    $stmt->close();

    $title = 'Document Returned for Revision';
    $message = "Your document {$task['document_number']} was returned for revision. Reason: {$remarks}";
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, type_code, title, message, related_table, related_id, dispatch_status, is_read, created_at) VALUES (?, 'document_returned', ?, ?, 'documents', ?, 'sent', 0, NOW())");
    $stmt->bind_param('issi', $task['creator_user_id'], $title, $message, $task['document_id']);
    $stmt->execute();
    $stmt->close();

    $desc = "Returned document {$task['document_number']} for revision";
    $stmt = $conn->prepare("INSERT INTO activity_logs (user_id, action_code, entity_type, entity_id, description, created_at) VALUES (?, 'document_returned_for_revision', 'documents', ?, ?, NOW())");
    $stmt->bind_param('iis', $user_id, $task['document_id'], $desc);
    $stmt->execute();
    $stmt->close();

    $conn->commit();
    set_flash('success', 'Document returned for revision.');
    header('Location: ' . app_workflow_index_path($role_code));
    exit;
} catch (Exception $e) {
    $conn->rollback();
    set_flash('error', 'Return action failed: ' . $e->getMessage());
    header('Location: ' . app_workflow_view_path($role_code, $task_id));
    exit;
}
