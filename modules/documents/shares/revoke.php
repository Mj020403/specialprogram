<?php
require_once dirname(__DIR__, 3) . '/app/bootstrap.php';

$conn = db_conn();require_once app_path('app/config/database.php');
app_require('app/includes/auth.php');
app_require('app/includes/flash.php');

require_login();

$id = (int)($_GET['id'] ?? 0);
$redirect_to = trim($_GET['redirect_to'] ?? '/harvest/');

$stmt = $conn->prepare("
    UPDATE document_shares
    SET revoked_at = NOW()
    WHERE id = ? AND revoked_at IS NULL
");
$stmt->bind_param("i", $id);
$stmt->execute();
$stmt->close();

set_flash('success', 'Share revoked successfully.');
header("Location: " . $redirect_to);
exit;