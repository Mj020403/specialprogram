<?php
require_once dirname(__DIR__, 3) . '/app/bootstrap.php';

$conn = db_conn();require_once app_path('app/config/database.php');
app_require('app/includes/auth.php');
app_require('app/includes/flash.php');

require_login();

$id = (int)($_GET['id'] ?? 0);
$redirect_to = trim($_GET['redirect_to'] ?? '/harvest/');

$stmt = $conn->prepare("
    SELECT author_user_id
    FROM document_comments
    WHERE id = ? AND deleted_at IS NULL
    LIMIT 1
");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$comment = $result->fetch_assoc();
$stmt->close();

if (!$comment) {
    set_flash('error', 'Comment not found.');
    header("Location: " . $redirect_to);
    exit;
}

if ((int)$comment['author_user_id'] !== (int)$_SESSION['user_id']) {
    set_flash('error', 'You can only delete your own comments.');
    header("Location: " . $redirect_to);
    exit;
}

$stmt = $conn->prepare("
    UPDATE document_comments
    SET deleted_at = NOW(), updated_at = NOW()
    WHERE id = ?
");
$stmt->bind_param("i", $id);
$stmt->execute();
$stmt->close();

set_flash('success', 'Comment deleted.');
header("Location: " . $redirect_to);
exit;