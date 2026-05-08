<?php
require_once dirname(__DIR__, 3) . '/app/bootstrap.php';

$conn = db_conn();require_once app_path('app/config/database.php');
app_require('app/includes/auth.php');
app_require('app/includes/flash.php');

require_role(['developer','super_admin', 'system_admin', 'admin']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /harvest/modules/admin/users/index.php');
    exit;
}

$id = (int)($_POST['id'] ?? 0);
$email = trim($_POST['email'] ?? '');
$username = trim($_POST['username'] ?? '');
$password = trim($_POST['password'] ?? '');
$employee_no = trim($_POST['employee_no'] ?? '');
$first_name = trim($_POST['first_name'] ?? '');
$middle_name = trim($_POST['middle_name'] ?? '');
$last_name = trim($_POST['last_name'] ?? '');
$suffix_name = trim($_POST['suffix_name'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$job_title = trim($_POST['job_title'] ?? '');
$role_id = (int)($_POST['role_id'] ?? 0);
$department_id = (int)($_POST['department_id'] ?? 0);
$employment_status = trim($_POST['employment_status'] ?? 'active');

if ($id <= 0 || $email === '' || $username === '' || $first_name === '' || $last_name === '' || $role_id <= 0 || $department_id <= 0) {
    set_flash('error', 'Please fill in all required fields.');
    header('Location: /harvest/admin/users/edit.php?id=' . $id);
    exit;
}

$imageUpload = app_store_profile_image($_FILES['profile_image'] ?? []);
if (!($imageUpload['ok'] ?? false)) {
    set_flash('error', $imageUpload['message'] ?? 'Profile image upload failed.');
    header('Location: /harvest/admin/users/edit.php?id=' . $id);
    exit;
}

$conn->begin_transaction();
try {
    if ($password !== '') {
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare('UPDATE users SET email = ?, username = ?, password_hash = ?, updated_at = NOW() WHERE id = ?');
        $stmt->bind_param('sssi', $email, $username, $password_hash, $id);
    } else {
        $stmt = $conn->prepare('UPDATE users SET email = ?, username = ?, updated_at = NOW() WHERE id = ?');
        $stmt->bind_param('ssi', $email, $username, $id);
    }
    $stmt->execute();
    $stmt->close();

    $imageStmt = $conn->prepare('SELECT profile_image_path FROM user_profiles WHERE user_id = ? LIMIT 1');
    $imageStmt->bind_param('i', $id);
    $imageStmt->execute();
    $existing = $imageStmt->get_result()->fetch_assoc();
    $imageStmt->close();
    $effectiveImage = ($imageUpload['path'] ?? null) ?: ($existing['profile_image_path'] ?? null);

    $stmt = $conn->prepare('UPDATE user_profiles SET employee_no = ?, first_name = ?, middle_name = ?, last_name = ?, suffix_name = ?, phone = ?, job_title = ?, profile_image_path = ?, primary_department_id = ?, employment_status = ?, updated_at = NOW() WHERE user_id = ?');
    $stmt->bind_param('ssssssssisi', $employee_no, $first_name, $middle_name, $last_name, $suffix_name, $phone, $job_title, $effectiveImage, $department_id, $employment_status, $id);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare('UPDATE user_role_assignments SET ended_at = NOW(), is_active = 0 WHERE user_id = ? AND ended_at IS NULL');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare('INSERT INTO user_role_assignments (user_id, role_id, is_active, assigned_at, ended_at) VALUES (?, ?, 1, NOW(), NULL)');
    $stmt->bind_param('ii', $id, $role_id);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare('UPDATE user_department_assignments SET ended_at = NOW(), is_active = 0 WHERE user_id = ? AND ended_at IS NULL');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare('INSERT INTO user_department_assignments (user_id, department_id, is_primary, is_active, assigned_at, ended_at) VALUES (?, ?, 1, 1, NOW(), NULL)');
    $stmt->bind_param('ii', $id, $department_id);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("INSERT INTO activity_logs (user_id, action_code, entity_type, entity_id, description, created_at) VALUES (?, 'user_updated', 'users', ?, ?, NOW())");
    $admin_id = (int)$_SESSION['user_id'];
    $description = 'Updated user ID: ' . $id;
    $stmt->bind_param('iis', $admin_id, $id, $description);
    $stmt->execute();
    $stmt->close();

    $conn->commit();
    set_flash('success', 'User updated successfully.');
    header('Location: /harvest/modules/admin/users/index.php');
    exit;
} catch (Exception $e) {
    $conn->rollback();
    set_flash('error', 'Failed to update user. Error: ' . $e->getMessage());
    header('Location: /harvest/admin/users/edit.php?id=' . $id);
    exit;
}
