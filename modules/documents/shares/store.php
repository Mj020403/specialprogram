<?php
require_once dirname(__DIR__, 3) . '/app/bootstrap.php';

$conn = db_conn();require_once app_path('app/config/database.php');
app_require('app/includes/auth.php');
app_require('app/includes/flash.php');

require_login();

$document_id = (int)($_POST['document_id'] ?? 0);
$share_type = trim($_POST['share_type'] ?? 'user');
$target_user_id = !empty($_POST['target_user_id']) ? (int)$_POST['target_user_id'] : null;
$target_department_id = !empty($_POST['target_department_id']) ? (int)$_POST['target_department_id'] : null;
$can_view = isset($_POST['can_view']) ? 1 : 0;
$can_edit = isset($_POST['can_edit']) ? 1 : 0;
$redirect_to = trim($_POST['redirect_to'] ?? '/harvest/');

if ($document_id <= 0) {
    set_flash('error', 'Invalid document.');
    header("Location: " . $redirect_to);
    exit;
}

if ($share_type === 'user' && !$target_user_id) {
    set_flash('error', 'Please select a target user.');
    header("Location: " . $redirect_to);
    exit;
}

if ($share_type === 'department' && !$target_department_id) {
    set_flash('error', 'Please select a target department.');
    header("Location: " . $redirect_to);
    exit;
}

if ($can_view === 0 && $can_edit === 0) {
    set_flash('error', 'At least one permission must be selected.');
    header("Location: " . $redirect_to);
    exit;
}

$user_id = $_SESSION['user_id'];

$stmt = $conn->prepare("
    INSERT INTO document_shares (
        document_id, shared_by_user_id, target_user_id, target_department_id,
        can_view, can_edit, created_at
    ) VALUES (?, ?, ?, ?, ?, ?, NOW())
");
$stmt->bind_param("iiiiii", $document_id, $user_id, $target_user_id, $target_department_id, $can_view, $can_edit);
$stmt->execute();
$share_id = $stmt->insert_id;
$stmt->close();

$notify_user_id = $target_user_id;

if ($notify_user_id) {
    $title = "Document Shared With You";
    $message = "A document has been shared with you.";

    $stmt = $conn->prepare("
        INSERT INTO notifications (
            user_id, type_code, title, message, related_table, related_id,
            dispatch_status, is_read, created_at
        ) VALUES (?, 'document_shared', ?, ?, 'documents', ?, 'sent', 0, NOW())
    ");
    $stmt->bind_param("issi", $notify_user_id, $title, $message, $document_id);
    $stmt->execute();
    $stmt->close();
}

$desc = "Shared document ID {$document_id}";
$stmt = $conn->prepare("
    INSERT INTO activity_logs (user_id, action_code, entity_type, entity_id, description, created_at)
    VALUES (?, 'document_shared', 'document_shares', ?, ?, NOW())
");
$stmt->bind_param("iis", $user_id, $share_id, $desc);
$stmt->execute();
$stmt->close();

set_flash('success', 'Document shared successfully.');
header("Location: " . $redirect_to);
exit;