<?php
require_once dirname(__DIR__, 3) . '/app/bootstrap.php';

$conn = db_conn();require_once app_path('app/config/database.php');
app_require('app/includes/auth.php');

require_login();

$version_id = (int)($_GET['id'] ?? 0);
$user_id = (int)$_SESSION['user_id'];

if ($version_id <= 0) {
    http_response_code(400);
    exit('Invalid file request.');
}

$stmt = $conn->prepare("
    SELECT
        dv.*,
        d.creator_user_id,
        d.current_department_id
    FROM document_versions dv
    JOIN documents d ON d.id = dv.document_id
    WHERE dv.id = ?
    LIMIT 1
");
$stmt->bind_param("i", $version_id);
$stmt->execute();
$result = $stmt->get_result();
$file = $result->fetch_assoc();
$stmt->close();

if (!$file) {
    http_response_code(404);
    exit('File not found.');
}

/*
   Basic access rules:
   - creator can access
   - assigned shared user can access
   - shared department users can access
   - admins/auditors/records/approver can access if logged in
*/

$role_code = $_SESSION['role_code'] ?? '';
$allowed = false;

if ((int)$file['creator_user_id'] === $user_id) {
    $allowed = true;
}

if (in_array($role_code, ['super_admin', 'system_admin', 'admin', 'records_officer', 'approver', 'auditor'], true)) {
    $allowed = true;
}

if (!$allowed) {
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS total
        FROM document_shares
        WHERE document_id = ?
          AND revoked_at IS NULL
          AND target_user_id = ?
    ");
    $stmt->bind_param("ii", $file['document_id'], $user_id);
    $stmt->execute();
    $shareResult = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ((int)$shareResult['total'] > 0) {
        $allowed = true;
    }
}

if (!$allowed) {
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS total
        FROM document_shares ds
        JOIN user_department_assignments uda
          ON uda.department_id = ds.target_department_id
         AND uda.user_id = ?
         AND uda.is_active = 1
         AND uda.ended_at IS NULL
        WHERE ds.document_id = ?
          AND ds.revoked_at IS NULL
    ");
    $stmt->bind_param("ii", $user_id, $file['document_id']);
    $stmt->execute();
    $deptShareResult = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ((int)$deptShareResult['total'] > 0) {
        $allowed = true;
    }
}

if (!$allowed) {
    http_response_code(403);
    exit('Access denied.');
}

$full_path = app_path($file['file_path']);

if (!file_exists($full_path)) {
    http_response_code(404);
    exit('Stored file not found.');
}

$desc = "Downloaded file version ID {$version_id}";
$stmt = $conn->prepare("
    INSERT INTO activity_logs (user_id, action_code, entity_type, entity_id, description, created_at)
    VALUES (?, 'document_file_downloaded', 'document_versions', ?, ?, NOW())
");
$logDesc = $desc;
$stmt->bind_param("iis", $user_id, $version_id, $logDesc);
$stmt->execute();
$stmt->close();

header('Content-Description: File Transfer');
header('Content-Type: ' . ($file['mime_type'] ?: 'application/octet-stream'));
header('Content-Disposition: attachment; filename="' . basename($file['original_file_name']) . '"');
header('Content-Length: ' . filesize($full_path));
header('Pragma: public');
header('Cache-Control: must-revalidate');
readfile($full_path);
exit;