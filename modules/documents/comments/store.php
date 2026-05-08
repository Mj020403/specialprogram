<?php
require_once dirname(__DIR__, 3) . '/app/bootstrap.php';

$conn = db_conn();require_once app_path('app/config/database.php');
app_require('app/includes/auth.php');
app_require('app/includes/flash.php');

require_login();

$document_id = (int)($_POST['document_id'] ?? 0);
$comment_text = trim($_POST['comment_text'] ?? '');
$redirect_to = trim($_POST['redirect_to'] ?? '/harvest/');

if ($document_id <= 0 || $comment_text === '') {
    set_flash('error', 'Comment text is required.');
    header("Location: " . $redirect_to);
    exit;
}

$user_id = $_SESSION['user_id'];

$stmt = $conn->prepare("
    INSERT INTO document_comments (
        document_id, author_user_id, comment_text, is_internal, created_at, updated_at
    ) VALUES (?, ?, ?, 0, NOW(), NOW())
");
$stmt->bind_param("iis", $document_id, $user_id, $comment_text);
$stmt->execute();
$comment_id = $stmt->insert_id;
$stmt->close();

$desc = "Added comment to document ID {$document_id}";
$stmt = $conn->prepare("
    INSERT INTO activity_logs (user_id, action_code, entity_type, entity_id, description, created_at)
    VALUES (?, 'document_comment_added', 'document_comments', ?, ?, NOW())
");
$stmt->bind_param("iis", $user_id, $comment_id, $desc);
$stmt->execute();
$stmt->close();

set_flash('success', 'Comment added successfully.');
header("Location: " . $redirect_to);
exit;