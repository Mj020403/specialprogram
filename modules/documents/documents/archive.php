<?php
require_once dirname(__DIR__, 3) . '/app/bootstrap.php';

$conn = db_conn();
app_require('app/includes/auth.php');
app_require('app/includes/flash.php');

require_role(['super_admin', 'system_admin', 'admin', 'records_officer']);

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    set_flash('error', 'Invalid document ID.');
    header('Location: ' . app_url('modules/documents/search/index.php'));
    exit;
}

$stmt = $conn->prepare("SELECT id, document_number, status FROM documents WHERE id = ? AND deleted_at IS NULL LIMIT 1");
if (!$stmt) {
    set_flash('error', 'Could not prepare document lookup.');
    header('Location: ' . app_url('modules/documents/search/index.php'));
    exit;
}
$stmt->bind_param('i', $id);
$stmt->execute();
$document = $stmt->get_result()->fetch_assoc() ?: null;
$stmt->close();

if (!$document) {
    set_flash('error', 'Document not found.');
    header('Location: ' . app_url('modules/documents/search/index.php'));
    exit;
}

if (!in_array((string)$document['status'], ['approved', 'rejected', 'cancelled'], true)) {
    set_flash('error', 'Only approved, rejected, or cancelled documents can be archived.');
    header('Location: ' . app_url('modules/users/roles/staff/documents/view.php?id=' . $id));
    exit;
}

$stmt = $conn->prepare("UPDATE documents SET status = 'archived', archived_at = NOW(), updated_at = NOW() WHERE id = ?");
if ($stmt) {
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
}

$userId = (int)($_SESSION['user_id'] ?? 0);
$description = 'Archived document ' . ($document['document_number'] ?: ('ID ' . $id));
$stmt = $conn->prepare("INSERT INTO activity_logs (user_id, action_code, entity_type, entity_id, description, created_at) VALUES (?, 'document_archived', 'documents', ?, ?, NOW())");
if ($stmt) {
    $stmt->bind_param('iis', $userId, $id, $description);
    $stmt->execute();
    $stmt->close();
}

set_flash('success', 'Document archived successfully.');
header('Location: ' . app_url('modules/users/roles/staff/documents/view.php?id=' . $id));
exit;
