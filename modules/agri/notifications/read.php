<?php
require_once dirname(__DIR__, 3) . '/app/bootstrap.php';

$conn = db_conn();require_once app_path('app/config/database.php');
app_require('app/includes/auth.php');
app_require('app/includes/app_helpers.php');

require_login();

$id = (int)($_GET['id'] ?? 0);
$redirect_to = trim((string)($_GET['redirect_to'] ?? '/harvest/modules/agri/notifications/index.php'));
if ($id > 0 && table_exists($conn, 'notifications')) {
    $stmt = $conn->prepare('UPDATE notifications SET is_read = 1, read_at = NOW() WHERE notification_id = ?');
    if ($stmt) {
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
    }
}

header('Location: ' . $redirect_to);
exit;
