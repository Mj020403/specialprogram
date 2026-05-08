<?php
require_once dirname(__DIR__, 5) . '/app/bootstrap.php';

$conn = db_conn();require_once app_path('app/config/database.php');
app_require('app/includes/auth.php');
app_require('app/includes/flash.php');

require_login();

$id = (int)($_POST['id'] ?? 0);
$user_id = $_SESSION['user_id'];

$title = trim($_POST['title'] ?? '');
$description = trim($_POST['description'] ?? '');
$category_id = (int)($_POST['category_id'] ?? 0);
$originating_department_id = (int)($_POST['originating_department_id'] ?? 0);
$confidentiality = trim($_POST['confidentiality'] ?? 'internal');

if ($id <= 0 || $title === '' || $category_id <= 0 || $originating_department_id <= 0) {
    set_flash('error', 'Please fill in all required fields.');
    header("Location: /harvest/staff/documents/edit.php?id=" . $id);
    exit;
}

$stmt = $conn->prepare("
    UPDATE documents
    SET title = ?, description = ?, category_id = ?, originating_department_id = ?, confidentiality = ?, updated_at = NOW()
    WHERE id = ? AND creator_user_id = ? AND status IN ('draft','for_revision')
");
$stmt->bind_param("ssiisii", $title, $description, $category_id, $originating_department_id, $confidentiality, $id, $user_id);
$stmt->execute();
$stmt->close();

$desc = "Updated draft document ID {$id}";
$stmt = $conn->prepare("
    INSERT INTO activity_logs (user_id, action_code, entity_type, entity_id, description, created_at)
    VALUES (?, 'document_updated', 'documents', ?, ?, NOW())
");
$stmt->bind_param("iis", $user_id, $id, $desc);
$stmt->execute();
$stmt->close();

set_flash('success', 'Draft updated successfully.');
header("Location: /harvest/staff/documents/view.php?id=" . $id);
exit;