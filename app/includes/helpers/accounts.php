<?php

function refresh_session_user(mysqli $conn, int $userId): void {
    $stmt = $conn->prepare("SELECT u.full_name,u.username,u.email,u.contact_number,u.position_title,u.avatar_path,u.profile_status,r.role_name FROM users u LEFT JOIN roles r ON r.role_id=u.role_id WHERE u.user_id=? LIMIT 1");
    if (!$stmt) return;
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();
    if (!$row) return;
    $_SESSION['full_name'] = $row['full_name'] ?? $_SESSION['full_name'] ?? 'User';
    $_SESSION['username'] = $row['username'] ?? $_SESSION['username'] ?? '';
    $_SESSION['role_code'] = role_code_from_name((string)($row['role_name'] ?? $_SESSION['role_code'] ?? 'task_force'));
    $_SESSION['email'] = $row['email'] ?? '';
    $_SESSION['contact_number'] = $row['contact_number'] ?? '';
    $_SESSION['position_title'] = $row['position_title'] ?? '';
    $_SESSION['avatar_path'] = $row['avatar_path'] ?? null;
    $_SESSION['profile_status'] = $row['profile_status'] ?? 'approved';
}

function ensure_user_account_schema(mysqli $conn): void {
    static $done = false;
    if ($done) return;
    $done = true;
    if (table_exists($conn, 'roles')) {
        $devCheck = fetch_one($conn, "SELECT role_id FROM roles WHERE role_name='DEVELOPER' LIMIT 1");
        if (!$devCheck) {
            @$conn->query("INSERT INTO roles (role_name, description, can_manage_users, can_interview, can_monitor, can_manage_events, can_take_attendance, can_view_dashboard, can_view_reports, can_export_data, can_scan_qr, created_at) VALUES ('DEVELOPER','System developer account with user governance rights',1,1,1,1,1,1,1,1,1,NOW())");
        }
    }
    if (table_exists($conn, 'users')) {
        $adds = [
            "avatar_path VARCHAR(255) NULL AFTER position_title",
            "bio TEXT NULL AFTER avatar_path",
            "profile_status VARCHAR(30) NOT NULL DEFAULT 'approved' AFTER bio",
            "profile_reviewed_by BIGINT NULL AFTER profile_status",
            "profile_reviewed_at DATETIME NULL AFTER profile_reviewed_by",
        ];
        foreach ($adds as $sql) {
            if (preg_match('/^([a-z_]+)/i', $sql, $m) && !column_exists($conn, 'users', $m[1])) {
                @$conn->query("ALTER TABLE users ADD COLUMN " . $sql);
            }
        }
        $devRoleId = (int)scalar($conn, "SELECT role_id FROM roles WHERE role_name='DEVELOPER' LIMIT 1", 0);
        if ($devRoleId > 0) {
            $hasDev = (int)scalar($conn, "SELECT COUNT(*) FROM users WHERE role_id={$devRoleId}", 0);
            if ($hasDev === 0) {
                $hash = hash('sha256', 'Developer@123');
                $stmt = $conn->prepare("INSERT INTO users (role_id,full_name,username,password_hash,email,contact_number,position_title,avatar_path,is_active,created_at,updated_at) VALUES (?, 'System Developer','developer', ?, 'developer@matagob.gov.ph','09170000003','System Developer', NULL, 1, NOW(), NOW())");
                if ($stmt) {
                    $stmt->bind_param('is', $devRoleId, $hash);
                    @$stmt->execute();
                    $stmt->close();
                }
            }
        }
    }
    if (!table_exists($conn, 'profile_update_requests')) {
        @$conn->query("CREATE TABLE profile_update_requests (
            request_id BIGINT AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT NOT NULL,
            full_name VARCHAR(150) NOT NULL,
            email VARCHAR(150) NULL,
            contact_number VARCHAR(30) NULL,
            position_title VARCHAR(100) NULL,
            bio TEXT NULL,
            avatar_path VARCHAR(255) NULL,
            request_status VARCHAR(30) NOT NULL DEFAULT 'Pending',
            reviewed_by BIGINT NULL,
            reviewed_at DATETIME NULL,
            review_notes VARCHAR(255) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_profile_request_user (user_id),
            INDEX idx_profile_request_status (request_status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    }
        if (!table_exists($conn, 'password_reset_requests')) {
        @$conn->query("CREATE TABLE password_reset_requests (
            reset_request_id BIGINT AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT NOT NULL,
            requested_by VARCHAR(150) NULL,
            reason VARCHAR(255) NULL,
            request_status VARCHAR(30) NOT NULL DEFAULT 'Pending',
            temp_password_hash VARCHAR(255) NULL,
            temp_password_plain VARCHAR(120) NULL,
            approved_by BIGINT NULL,
            approved_at DATETIME NULL,
            review_notes VARCHAR(255) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_reset_user (user_id),
            INDEX idx_reset_status (request_status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    }
    if (!table_exists($conn, 'account_activity_log')) {
        @$conn->query("CREATE TABLE account_activity_log (
            activity_id BIGINT AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT NULL,
            actor_user_id BIGINT NULL,
            activity_type VARCHAR(80) NOT NULL,
            activity_summary VARCHAR(255) NOT NULL,
            metadata_json LONGTEXT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_activity_user (user_id),
            INDEX idx_activity_actor (actor_user_id),
            INDEX idx_activity_type (activity_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    }
    if (!table_exists($conn, 'signup_requests')) {
        @$conn->query("CREATE TABLE signup_requests (
            signup_request_id BIGINT AUTO_INCREMENT PRIMARY KEY,
            full_name VARCHAR(150) NOT NULL,
            username VARCHAR(100) NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            email VARCHAR(150) NULL,
            contact_number VARCHAR(30) NULL,
            position_title VARCHAR(100) NULL,
            desired_role VARCHAR(50) NOT NULL DEFAULT 'TASK_FORCE',
            avatar_path VARCHAR(255) NULL,
            request_status VARCHAR(30) NOT NULL DEFAULT 'Pending',
            reviewed_by BIGINT NULL,
            reviewed_at DATETIME NULL,
            review_notes VARCHAR(255) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_signup_username (username),
            INDEX idx_signup_status (request_status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    }
    @mkdir(app_path('public/uploads/profile_pictures'), 0777, true);
    @mkdir(app_path('public/uploads/system'), 0777, true);
}

function pending_profile_request_count(mysqli $conn): int {
    if (!table_exists($conn, 'profile_update_requests')) return 0;
    return (int)scalar($conn, "SELECT COUNT(*) FROM profile_update_requests WHERE request_status='Pending'", 0);
}

function user_profile_request_open(mysqli $conn, int $userId): ?array {
    if (!table_exists($conn, 'profile_update_requests')) return null;
    $stmt = $conn->prepare("SELECT * FROM profile_update_requests WHERE user_id=? AND request_status='Pending' ORDER BY request_id DESC LIMIT 1");
    if (!$stmt) return null;
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();
    return $row;
}

function submit_profile_update_request(mysqli $conn, int $userId, array $payload): bool {
    if ($userId <= 0) return false;
    ensure_user_account_schema($conn);
    $existing = user_profile_request_open($conn, $userId);
    if ($existing) {
        $stmt = $conn->prepare("UPDATE profile_update_requests SET full_name=?, email=?, contact_number=?, position_title=?, bio=?, avatar_path=?, request_status='Pending', reviewed_by=NULL, reviewed_at=NULL, review_notes=NULL WHERE request_id=?");
        if (!$stmt) return false;
        $stmt->bind_param('ssssssi', $payload['full_name'], $payload['email'], $payload['contact_number'], $payload['position_title'], $payload['bio'], $payload['avatar_path'], $existing['request_id']);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }
    $stmt = $conn->prepare("INSERT INTO profile_update_requests (user_id, full_name, email, contact_number, position_title, bio, avatar_path, request_status) VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending')");
    if (!$stmt) return false;
    $stmt->bind_param('issssss', $userId, $payload['full_name'], $payload['email'], $payload['contact_number'], $payload['position_title'], $payload['bio'], $payload['avatar_path']);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

function approve_profile_request(mysqli $conn, int $requestId, int $reviewerId, string $decision = 'Approved', string $notes = ''): bool {
    if (!table_exists($conn, 'profile_update_requests') || !table_exists($conn, 'users')) return false;
    $stmt = $conn->prepare("SELECT * FROM profile_update_requests WHERE request_id=? LIMIT 1");
    if (!$stmt) return false;
    $stmt->bind_param('i', $requestId);
    $stmt->execute();
    $req = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();
    if (!$req || ($req['request_status'] ?? '') !== 'Pending') return false;
    $status = strtolower($decision) === 'approved' ? 'Approved' : 'Rejected';
    if ($status === 'Approved') {
        $update = $conn->prepare("UPDATE users SET full_name=?, email=?, contact_number=?, position_title=?, bio=?, avatar_path=?, profile_status='approved', profile_reviewed_by=?, profile_reviewed_at=NOW(), updated_at=NOW() WHERE user_id=?");
        if ($update) {
            $update->bind_param('ssssssii', $req['full_name'], $req['email'], $req['contact_number'], $req['position_title'], $req['bio'], $req['avatar_path'], $reviewerId, $req['user_id']);
            $update->execute();
            $update->close();
        }
    }
    $upd = $conn->prepare("UPDATE profile_update_requests SET request_status=?, reviewed_by=?, reviewed_at=NOW(), review_notes=? WHERE request_id=?");
    if (!$upd) return false;
    $upd->bind_param('sisi', $status, $reviewerId, $notes, $requestId);
    $ok = $upd->execute();
    $upd->close();
    return $ok;
}

function log_account_activity(mysqli $conn, ?int $userId, ?int $actorUserId, string $type, string $summary, array $metadata = []): void {
    if (table_exists($conn, 'account_activity_log')) {
        $stmt = $conn->prepare("INSERT INTO account_activity_log (user_id, actor_user_id, activity_type, activity_summary, metadata_json, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        if ($stmt) {
            $meta = $metadata ? json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
            $stmt->bind_param('iisss', $userId, $actorUserId, $type, $summary, $meta);
            $stmt->execute();
            $stmt->close();
        }
    }
    app_log($conn, $actorUserId, 'ACCOUNT', strtoupper($type), $userId, $summary);
}

function generate_temp_password(int $length = 10): string {
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%';
    $out = '';
    $max = strlen($alphabet) - 1;
    for ($i = 0; $i < $length; $i++) $out .= $alphabet[random_int(0, $max)];
    return $out;
}

function change_user_password(mysqli $conn, int $userId, string $currentPassword, string $newPassword, ?string &$error = null): bool {
    $stmt = $conn->prepare("SELECT password_hash FROM users WHERE user_id=? LIMIT 1");
    if (!$stmt) { $error = 'Unable to validate current password.'; return false; }
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();
    if (!$row) { $error = 'User not found.'; return false; }
    $dbHash = (string)($row['password_hash'] ?? '');
    $ok = password_verify($currentPassword, $dbHash) || hash('sha256', $currentPassword) === $dbHash;
    if (!$ok) { $error = 'Current password is incorrect.'; return false; }
    if (strlen($newPassword) < 8) { $error = 'New password must be at least 8 characters.'; return false; }
    $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
    $update = $conn->prepare("UPDATE users SET password_hash=?, updated_at=NOW() WHERE user_id=?");
    if (!$update) { $error = 'Unable to save the new password.'; return false; }
    $update->bind_param('si', $newHash, $userId);
    $saved = $update->execute();
    $update->close();
    if ($saved) {
        log_account_activity($conn, $userId, $userId, 'password_changed', 'Changed account password.');
        create_notification($conn, 'Password updated', 'Your account password was changed successfully.', 'Low', $userId, null, null, 'Account Security');
        return true;
    }
    $error = 'Unable to update password.';
    return false;
}

function create_password_reset_request(mysqli $conn, int $userId, string $requestedBy = '', string $reason = ''): bool {
    if (!table_exists($conn, 'password_reset_requests')) return false;
    $open = (int)scalar($conn, "SELECT COUNT(*) FROM password_reset_requests WHERE user_id=" . (int)$userId . " AND request_status='Pending'", 0);
    if ($open > 0) return true;
    $stmt = $conn->prepare("INSERT INTO password_reset_requests (user_id, requested_by, reason, request_status, created_at, updated_at) VALUES (?, ?, ?, 'Pending', NOW(), NOW())");
    if (!$stmt) return false;
    $stmt->bind_param('iss', $userId, $requestedBy, $reason);
    $ok = $stmt->execute();
    $stmt->close();
    if ($ok) {
        log_account_activity($conn, $userId, $userId ?: null, 'password_reset_requested', 'Requested a password reset.', ['requested_by' => $requestedBy]);
        $devId = (int)scalar($conn, "SELECT u.user_id FROM users u JOIN roles r ON r.role_id=u.role_id WHERE r.role_name='DEVELOPER' AND u.is_active=1 ORDER BY u.user_id ASC LIMIT 1", 0);
        if ($devId > 0) create_notification($conn, 'Password reset request', 'A user requested a password reset and needs developer approval.', 'Medium', $devId, null, null, 'Account Security');
    }
    return $ok;
}

function approve_password_reset_request(mysqli $conn, int $requestId, int $developerId, string $decision, string $notes = ''): ?array {
    if (!table_exists($conn, 'password_reset_requests')) return null;
    $stmt = $conn->prepare("SELECT r.*, u.username, u.full_name FROM password_reset_requests r JOIN users u ON u.user_id=r.user_id WHERE r.reset_request_id=? LIMIT 1");
    if (!$stmt) return null;
    $stmt->bind_param('i', $requestId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();
    if (!$row || ($row['request_status'] ?? '') !== 'Pending') return null;

    if ($decision === 'Approved') {
        $temp = generate_temp_password(10);
        $tempHash = password_hash($temp, PASSWORD_DEFAULT);
        $upd = $conn->prepare("UPDATE password_reset_requests SET request_status='Approved', temp_password_hash=?, temp_password_plain=?, approved_by=?, approved_at=NOW(), review_notes=?, updated_at=NOW() WHERE reset_request_id=?");
        if ($upd) {
            $upd->bind_param('ssisi', $tempHash, $temp, $developerId, $notes, $requestId);
            $upd->execute();
            $upd->close();
        }
        $u2 = $conn->prepare("UPDATE users SET password_hash=?, updated_at=NOW() WHERE user_id=?");
        if ($u2) {
            $uid = (int)$row['user_id'];
            $u2->bind_param('si', $tempHash, $uid);
            $u2->execute();
            $u2->close();
        }
        log_account_activity($conn, (int)$row['user_id'], $developerId, 'password_reset_approved', 'Developer approved a password reset.', ['request_id' => $requestId]);
        create_notification($conn, 'Password reset approved', 'Your password reset was approved. Use the temporary password shown to the developer.', 'High', (int)$row['user_id'], null, null, 'Account Security');
        return ['decision' => 'Approved', 'temp_password' => $temp, 'user' => $row];
    }

    $upd = $conn->prepare("UPDATE password_reset_requests SET request_status='Rejected', approved_by=?, approved_at=NOW(), review_notes=?, updated_at=NOW() WHERE reset_request_id=?");
    if ($upd) {
        $upd->bind_param('isi', $developerId, $notes, $requestId);
        $upd->execute();
        $upd->close();
    }
    log_account_activity($conn, (int)$row['user_id'], $developerId, 'password_reset_rejected', 'Developer rejected a password reset request.', ['request_id' => $requestId]);
    create_notification($conn, 'Password reset rejected', 'Your password reset request was rejected. Please contact the developer account.', 'Medium', (int)$row['user_id'], null, null, 'Account Security');
    return ['decision' => 'Rejected', 'user' => $row];
}

function pending_password_reset_count(mysqli $conn): int {
    if (!table_exists($conn, 'password_reset_requests')) return 0;
    return (int)scalar($conn, "SELECT COUNT(*) FROM password_reset_requests WHERE request_status='Pending'", 0);
}

function fetch_account_activity(mysqli $conn, int $userId, int $limit = 30): array {
    if (table_exists($conn, 'account_activity_log')) {
        return fetch_all_assoc($conn, "SELECT l.*, actor.full_name AS actor_name FROM account_activity_log l LEFT JOIN users actor ON actor.user_id=l.actor_user_id WHERE l.user_id=" . (int)$userId . " ORDER BY l.created_at DESC LIMIT " . (int)$limit);
    }
    if (table_exists($conn, 'audit_logs')) {
        return fetch_all_assoc($conn, "SELECT NULL AS activity_id, user_id, user_id AS actor_user_id, action_name AS activity_type, description AS activity_summary, NULL AS metadata_json, created_at, NULL AS actor_name FROM audit_logs WHERE user_id=" . (int)$userId . " ORDER BY created_at DESC LIMIT " . (int)$limit);
    }
    return [];
}

function role_permissions_rows(mysqli $conn): array {
    if (!table_exists($conn, 'roles')) return [];
    return fetch_all_assoc($conn, "SELECT role_id, role_name, can_manage_users, can_interview, can_monitor, can_manage_events, can_take_attendance, can_view_dashboard, can_view_reports, can_export_data, can_scan_qr FROM roles ORDER BY FIELD(role_name,'DEVELOPER','TASK_FORCE','MAYOR'), role_name");
}

function update_role_permissions(mysqli $conn, int $roleId, array $flags): bool {
    $columns = ['can_manage_users','can_interview','can_monitor','can_manage_events','can_take_attendance','can_view_dashboard','can_view_reports','can_export_data','can_scan_qr'];
    $sets = [];
    foreach ($columns as $c) $sets[] = $c . '=' . (!empty($flags[$c]) ? '1' : '0');
    return (bool)$conn->query("UPDATE roles SET " . implode(',', $sets) . " WHERE role_id=" . (int)$roleId . " LIMIT 1");
}

function submit_signup_request(mysqli $conn, array $payload): bool {
    if (!table_exists($conn, 'signup_requests')) return false;
    $username = trim((string)($payload['username'] ?? ''));
    if ($username === '') return false;
    $safeUsername = $conn->real_escape_string($username);
    $existingUsers = (int)scalar($conn, "SELECT COUNT(*) FROM users WHERE username='{$safeUsername}'", 0);
    $existingPending = (int)scalar($conn, "SELECT COUNT(*) FROM signup_requests WHERE username='{$safeUsername}' AND request_status='Pending'", 0);
    if ($existingUsers > 0 || $existingPending > 0) return false;

    $stmt = $conn->prepare("INSERT INTO signup_requests (full_name, username, password_hash, email, contact_number, position_title, desired_role, avatar_path, request_status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Pending', NOW(), NOW())");
    if (!$stmt) return false;
    $stmt->bind_param('ssssssss',
        $payload['full_name'],
        $payload['username'],
        $payload['password_hash'],
        $payload['email'],
        $payload['contact_number'],
        $payload['position_title'],
        $payload['desired_role'],
        $payload['avatar_path']
    );
    $ok = $stmt->execute();
    $stmt->close();
    if ($ok) {
        $devId = (int)scalar($conn, "SELECT u.user_id FROM users u JOIN roles r ON r.role_id=u.role_id WHERE r.role_name='DEVELOPER' AND u.is_active=1 ORDER BY u.user_id ASC LIMIT 1", 0);
        if ($devId > 0) create_notification($conn, 'Signup request pending', 'A new account signup request needs developer approval.', 'Medium', $devId, null, null, 'Account Governance');
    }
    return $ok;
}

function pending_signup_request_count(mysqli $conn): int {
    if (!table_exists($conn, 'signup_requests')) return 0;
    return (int)scalar($conn, "SELECT COUNT(*) FROM signup_requests WHERE request_status='Pending'", 0);
}

function approve_signup_request(mysqli $conn, int $requestId, int $developerId, string $decision, string $notes = ''): bool {
    if (!table_exists($conn, 'signup_requests') || !table_exists($conn, 'users')) return false;
    $row = fetch_one($conn, "SELECT * FROM signup_requests WHERE signup_request_id=" . (int)$requestId . " LIMIT 1");
    if (!$row || ($row['request_status'] ?? '') !== 'Pending') return false;

    if ($decision === 'Approved') {
        $roleName = strtoupper((string)($row['desired_role'] ?? 'TASK_FORCE'));
        if ($roleName === 'DEVELOPER') $roleName = 'TASK_FORCE';
        $roleId = (int)scalar($conn, "SELECT role_id FROM roles WHERE role_name='" . $conn->real_escape_string($roleName) . "' LIMIT 1", 0);
        if ($roleId <= 0) $roleId = (int)scalar($conn, "SELECT role_id FROM roles WHERE role_name='TASK_FORCE' LIMIT 1", 0);
        $stmt = $conn->prepare("INSERT INTO users (role_id, full_name, username, password_hash, email, contact_number, position_title, avatar_path, is_active, profile_status, created_by, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, 'approved', ?, NOW(), NOW())");
        if (!$stmt) return false;
        $uid = $developerId;
        $stmt->bind_param('isssssssi', $roleId, $row['full_name'], $row['username'], $row['password_hash'], $row['email'], $row['contact_number'], $row['position_title'], $row['avatar_path'], $uid);
        $ok = $stmt->execute();
        $stmt->close();
        if (!$ok) return false;

        $upd = $conn->prepare("UPDATE signup_requests SET request_status='Approved', reviewed_by=?, reviewed_at=NOW(), review_notes=?, updated_at=NOW() WHERE signup_request_id=?");
        if ($upd) {
            $upd->bind_param('isi', $developerId, $notes, $requestId);
            $upd->execute();
            $upd->close();
        }
        return true;
    }

    $upd = $conn->prepare("UPDATE signup_requests SET request_status='Rejected', reviewed_by=?, reviewed_at=NOW(), review_notes=?, updated_at=NOW() WHERE signup_request_id=?");
    if ($upd) {
        $upd->bind_param('isi', $developerId, $notes, $requestId);
        $upd->execute();
        $upd->close();
        return true;
    }
    return false;
}

