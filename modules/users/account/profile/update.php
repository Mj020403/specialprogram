<?php
require_once dirname(__DIR__, 4) . '/app/bootstrap.php';

$conn = db_conn();require_once app_path('app/config/database.php');
app_require('app/includes/app_helpers.php');
app_require('app/includes/auth.php');
app_require('app/includes/flash.php');

require_login();

$user_id = (int)($_SESSION['user_id'] ?? 0);
if ($user_id <= 0) {
    set_flash('error', 'Invalid user session.');
    header('Location: /harvest/modules/users/auth/login.php');
    exit;
}

$currentStmt = $conn->prepare('SELECT u.username, u.email, up.* FROM users u LEFT JOIN user_profiles up ON up.user_id = u.id WHERE u.id = ? LIMIT 1');
$currentStmt->bind_param('i', $user_id);
$currentStmt->execute();
$current = $currentStmt->get_result()->fetch_assoc() ?: [];
$currentStmt->close();

$actionType = trim($_POST['action_type'] ?? 'profile');

$username = array_key_exists('username', $_POST) ? trim($_POST['username']) : trim($current['username'] ?? '');
$first_name = array_key_exists('first_name', $_POST) ? trim($_POST['first_name']) : trim($current['first_name'] ?? '');
$middle_name = array_key_exists('middle_name', $_POST) ? trim($_POST['middle_name']) : trim($current['middle_name'] ?? '');
$last_name = array_key_exists('last_name', $_POST) ? trim($_POST['last_name']) : trim($current['last_name'] ?? '');
$suffix_name = array_key_exists('suffix_name', $_POST) ? trim($_POST['suffix_name']) : trim($current['suffix_name'] ?? '');
$phone = array_key_exists('phone', $_POST) ? trim($_POST['phone']) : trim($current['phone'] ?? '');
$job_title = array_key_exists('job_title', $_POST) ? trim($_POST['job_title']) : trim($current['job_title'] ?? '');

if ($username === '' || $first_name === '' || $last_name === '') {
    set_flash('error', 'Username, first name, and last name are still required.');
    header('Location: /harvest/modules/users/account/profile/index.php');
    exit;
}

$imageUpload = app_store_profile_image($_FILES['profile_image'] ?? []);
if (!($imageUpload['ok'] ?? false)) {
    set_flash('error', $imageUpload['message'] ?? 'Profile image upload failed.');
    header('Location: /harvest/modules/users/account/profile/index.php');
    exit;
}

$profileImagePath = $imageUpload['path'] ?? null;
$effectiveImage = $profileImagePath ?: ($current['profile_image_path'] ?? null);

$conn->begin_transaction();

try {
    $stmt = $conn->prepare('UPDATE users SET username = ?, updated_at = NOW() WHERE id = ?');
    $stmt->bind_param('si', $username, $user_id);
    $stmt->execute();
    $stmt->close();

    $hasExistingProfile = !empty($current) && array_key_exists('user_id', $current);

    if ($hasExistingProfile) {
        $stmt = $conn->prepare('UPDATE user_profiles SET first_name = ?, middle_name = ?, last_name = ?, suffix_name = ?, phone = ?, job_title = ?, profile_image_path = ?, updated_at = NOW() WHERE user_id = ?');
        $stmt->bind_param('sssssssi', $first_name, $middle_name, $last_name, $suffix_name, $phone, $job_title, $effectiveImage, $user_id);
    } else {
        $stmt = $conn->prepare('INSERT INTO user_profiles (user_id, first_name, middle_name, last_name, suffix_name, phone, job_title, profile_image_path, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())');
        $stmt->bind_param('isssssss', $user_id, $first_name, $middle_name, $last_name, $suffix_name, $phone, $job_title, $effectiveImage);
    }
    $stmt->execute();
    $stmt->close();

    $desc = match (true) {
        $actionType === 'photo' && $profileImagePath => 'Updated own profile photo',
        $profileImagePath => 'Updated own profile and profile photo',
        default => 'Updated own profile',
    };

    $stmt = $conn->prepare("INSERT INTO activity_logs (user_id, action_code, entity_type, entity_id, description, created_at) VALUES (?, 'user_profile_updated', 'users', ?, ?, NOW())");
    $stmt->bind_param('iis', $user_id, $user_id, $desc);
    $stmt->execute();
    $stmt->close();

    $conn->commit();
    $message = match (true) {
        $actionType === 'photo' && $profileImagePath => 'Profile picture updated successfully.',
        $actionType === 'photo' => 'No new picture was selected, so your current photo stayed the same.',
        $profileImagePath => 'Profile and photo updated successfully.',
        default => 'Profile updated successfully.',
    };
    set_flash('success', $message);
} catch (Throwable $e) {
    $conn->rollback();
    set_flash('error', 'Failed to update profile: ' . $e->getMessage());
}

header('Location: /harvest/modules/users/account/profile/index.php');
exit;
