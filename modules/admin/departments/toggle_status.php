<?php
require_once dirname(__DIR__, 3) . '/app/bootstrap.php';

$conn = db_conn();
app_require('app/includes/auth.php');
app_require('app/includes/flash.php');

require_role(['developer','super_admin', 'system_admin', 'admin']);

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    set_flash('error', 'Invalid department ID.');
    header('Location: ' . app_url('modules/admin/departments/index.php'));
    exit;
}

$stmt = $conn->prepare('SELECT is_active, name FROM departments WHERE id = ? LIMIT 1');
if (!$stmt) {
    set_flash('error', 'Could not prepare department lookup.');
    header('Location: ' . app_url('modules/admin/departments/index.php'));
    exit;
}
$stmt->bind_param('i', $id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc() ?: null;
$stmt->close();

if (!$row) {
    set_flash('error', 'Department not found.');
    header('Location: ' . app_url('modules/admin/departments/index.php'));
    exit;
}

$newStatus = ((int)$row['is_active'] === 1) ? 0 : 1;
$stmt = $conn->prepare('UPDATE departments SET is_active = ?, updated_at = NOW() WHERE id = ?');
if ($stmt) {
    $stmt->bind_param('ii', $newStatus, $id);
    $stmt->execute();
    $stmt->close();
}

$actorId = (int)($_SESSION['user_id'] ?? 0);
$description = 'Changed department status: ' . ($row['name'] ?? ('Department #' . $id));
$stmt = $conn->prepare("INSERT INTO activity_logs (user_id, action_code, entity_type, entity_id, description, created_at) VALUES (?, 'department_status_changed', 'departments', ?, ?, NOW())");
if ($stmt) {
    $stmt->bind_param('iis', $actorId, $id, $description);
    $stmt->execute();
    $stmt->close();
}

set_flash('success', 'Department status updated.');
header('Location: ' . app_url('modules/admin/departments/index.php'));
exit;
