<?php
require_once dirname(__DIR__, 5) . '/app/bootstrap.php';

$conn = db_conn();require_once app_path('app/config/database.php');
app_require('app/includes/auth.php');
app_require('app/includes/flash.php');

require_role(['staff', 'records_officer', 'super_admin', 'system_admin', 'admin']);

$title = trim($_POST['title'] ?? '');
$description = trim($_POST['description'] ?? '');
$category_id = (int)($_POST['category_id'] ?? 0);
$originating_department_id = (int)($_POST['originating_department_id'] ?? 0);
$confidentiality = trim($_POST['confidentiality'] ?? 'internal');
$priority_level = trim($_POST['priority_level'] ?? 'normal');
$submitNow = ($_POST['submit_mode'] ?? 'draft') === 'submit';

if ($title === '' || $category_id <= 0 || $originating_department_id <= 0) {
    set_flash('error', 'Please fill in all required fields.');
    header('Location: /harvest/staff/documents/create.php');
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$uuid = $conn->query('SELECT UUID() AS uuid')->fetch_assoc()['uuid'];
$public_tracking_code = 'DOC-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));

$conn->begin_transaction();
try {
    $stmt = $conn->prepare('INSERT INTO documents (uuid, title, description, category_id, creator_user_id, owner_user_id, originating_department_id, current_department_id, status, confidentiality, priority_level, document_number, public_tracking_code, current_version_no, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, "draft", ?, ?, NULL, ?, 0, NOW(), NOW())');
    $stmt->bind_param('sssiiiiisss', $uuid, $title, $description, $category_id, $user_id, $user_id, $originating_department_id, $originating_department_id, $confidentiality, $priority_level, $public_tracking_code);
    $stmt->execute();
    $document_id = $stmt->insert_id;
    $stmt->close();

    $uploadResult = app_store_document_file($conn, $document_id, $_FILES['document_file'] ?? [], $user_id);
    if (!($uploadResult['ok'] ?? false)) {
        throw new Exception($uploadResult['message'] ?? 'Document upload failed.');
    }

    if ($submitNow && !($uploadResult['uploaded'] ?? false)) {
        throw new Exception('Please upload a file if you want to submit immediately.');
    }

    $desc = ($uploadResult['uploaded'] ?? false)
        ? 'Created draft document with initial upload: ' . $title
        : 'Created draft document: ' . $title;
    $stmt = $conn->prepare("INSERT INTO activity_logs (user_id, action_code, entity_type, entity_id, description, created_at) VALUES (?, 'document_created', 'documents', ?, ?, NOW())");
    $stmt->bind_param('iis', $user_id, $document_id, $desc);
    $stmt->execute();
    $stmt->close();

    if ($submitNow) {
        app_submit_document($conn, $document_id, $user_id);
    }

    $conn->commit();

    if ($submitNow) {
        set_flash('success', 'Document created, file attached, and submitted automatically.');
    } else {
        set_flash('success', ($uploadResult['uploaded'] ?? false) ? 'Draft document created and initial file uploaded successfully.' : 'Draft document created successfully.');
    }

    header('Location: /harvest/staff/documents/view.php?id=' . $document_id);
    exit;
} catch (Exception $e) {
    $conn->rollback();
    set_flash('error', 'Failed to create document. Error: ' . $e->getMessage());
    header('Location: /harvest/staff/documents/create.php');
    exit;
}
