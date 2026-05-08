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
$reviewRoles = ['approver', 'department_head', 'super_admin', 'system_admin', 'admin'];

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
    if ($role_code === 'records_officer') {
        $decision = 'forwarded';
    } elseif (in_array($role_code, $reviewRoles, true)) {
        $decision = 'approved';
    } else {
        throw new Exception('Unauthorized workflow action.');
    }

    $stmt = $conn->prepare('INSERT INTO document_approvals (workflow_instance_id, task_id, document_id, decided_by_user_id, decision, remarks, decided_at) VALUES (?, ?, ?, ?, ?, ?, NOW())');
    $stmt->bind_param('iiiiss', $task['workflow_instance_id'], $task_id, $task['document_id'], $user_id, $decision, $remarks);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("UPDATE document_workflow_tasks SET status = 'completed', acted_at = NOW(), completed_at = NOW(), updated_at = NOW() WHERE id = ?");
    $stmt->bind_param('i', $task_id);
    $stmt->execute();
    $stmt->close();

    $nextStepStmt = $conn->prepare("SELECT id, assigned_user_id, document_id FROM document_workflow_tasks WHERE workflow_instance_id = ? AND step_no > ? AND status NOT IN ('completed', 'cancelled') ORDER BY step_no ASC LIMIT 1");
    $nextStepStmt->bind_param('ii', $task['workflow_instance_id'], $task['step_no']);
    $nextStepStmt->execute();
    $nextTask = $nextStepStmt->get_result()->fetch_assoc();
    $nextStepStmt->close();

    if ($nextTask) {
        $stmt = $conn->prepare("UPDATE document_workflow_tasks SET status = 'pending', updated_at = NOW() WHERE id = ?");
        $stmt->bind_param('i', $nextTask['id']);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare("UPDATE documents SET status = 'in_review', updated_at = NOW() WHERE id = ?");
        $stmt->bind_param('i', $task['document_id']);
        $stmt->execute();
        $stmt->close();

        if (!empty($nextTask['assigned_user_id'])) {
            $title = 'Document Needs Review';
            $message = "Document {$task['document_number']} is ready for your action.";
            $stmt = $conn->prepare("INSERT INTO notifications (user_id, type_code, title, message, related_table, related_id, dispatch_status, is_read, created_at) VALUES (?, 'document_for_review', ?, ?, 'documents', ?, 'sent', 0, NOW())");
            $stmt->bind_param('issi', $nextTask['assigned_user_id'], $title, $message, $task['document_id']);
            $stmt->execute();
            $stmt->close();
        }

        $desc = $role_code === 'records_officer'
            ? "Forwarded document {$task['document_number']} to next workflow step"
            : "Approved workflow step for document {$task['document_number']} and activated next task";
        $actionCode = 'document_forwarded';
        $successMessage = $role_code === 'records_officer'
            ? 'Document forwarded to next workflow step.'
            : 'Task completed and next workflow step activated.';
    } else {
        $stmt = $conn->prepare("UPDATE document_workflow_instances SET status = 'completed', completed_at = NOW() WHERE id = ?");
        $stmt->bind_param('i', $task['workflow_instance_id']);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare("UPDATE documents SET status = 'approved', approved_at = NOW(), updated_at = NOW() WHERE id = ?");
        $stmt->bind_param('i', $task['document_id']);
        $stmt->execute();
        $stmt->close();

        $title = 'Document Approved';
        $message = "Your document {$task['document_number']} has been approved.";
        $stmt = $conn->prepare("INSERT INTO notifications (user_id, type_code, title, message, related_table, related_id, dispatch_status, is_read, created_at) VALUES (?, 'document_approved', ?, ?, 'documents', ?, 'sent', 0, NOW())");
        $stmt->bind_param('issi', $task['creator_user_id'], $title, $message, $task['document_id']);
        $stmt->execute();
        $stmt->close();

        $desc = "Approved document {$task['document_number']}";
        $actionCode = 'document_approved';
        $successMessage = 'Document approved successfully.';
    }

    $stmt = $conn->prepare("INSERT INTO activity_logs (user_id, action_code, entity_type, entity_id, description, created_at) VALUES (?, ?, 'documents', ?, ?, NOW())");
    $stmt->bind_param('isis', $user_id, $actionCode, $task['document_id'], $desc);
    $stmt->execute();
    $stmt->close();

    $conn->commit();
    set_flash('success', $successMessage);
    header('Location: ' . app_workflow_index_path($role_code));
    exit;
} catch (Exception $e) {
    $conn->rollback();
    set_flash('error', 'Approval failed: ' . $e->getMessage());
    header('Location: ' . app_workflow_view_path($role_code, $task_id));
    exit;
}
