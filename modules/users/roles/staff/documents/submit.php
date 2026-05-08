<?php
require_once dirname(__DIR__, 5) . '/app/bootstrap.php';

$conn = db_conn();require_once app_path('app/config/database.php');
app_require('app/includes/auth.php');
app_require('app/includes/flash.php');

require_login();

$id = (int)($_REQUEST['id'] ?? 0);
$user_id = (int)($_SESSION['user_id'] ?? 0);

if ($id <= 0) {
    set_flash('error', 'Invalid document.');
    header('Location: /harvest/staff/documents/index.php');
    exit;
}

$conn->begin_transaction();

try {
    app_submit_document($conn, $id, $user_id);
    $conn->commit();

    set_flash('success', 'Document submitted successfully.');
    header('Location: /harvest/staff/documents/view.php?id=' . $id);
    exit;
} catch (Exception $e) {
    $conn->rollback();
    set_flash('error', 'Failed to submit document: ' . $e->getMessage());
    header('Location: /harvest/staff/documents/view.php?id=' . $id);
    exit;
}
