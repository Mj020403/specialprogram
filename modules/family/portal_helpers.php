<?php

require_once dirname(__DIR__, 2) . '/app/includes/helpers/core.php';
require_once dirname(__DIR__, 2) . '/app/includes/helpers/accounts.php';
require_once dirname(__DIR__, 2) . '/app/includes/helpers/households.php';
require_once dirname(__DIR__, 2) . '/app/includes/helpers/operations.php';
require_once dirname(__DIR__, 2) . '/app/includes/helpers/branding.php';

function ensure_family_portal_control_settings(mysqli $conn): void {
    if (!table_exists($conn, 'system_settings')) return;
    $defaults = [
        'family_portal_enabled' => ['1', 'Master switch for the family portal.'],
        'family_scan_enabled' => ['1', 'Show the Scan QR family access entry point on the login page.'],
        'family_dashboard_enabled' => ['1', 'Allow families to open family dashboard pages.'],
        'family_submission_enabled' => ['1', 'Allow families to submit crop, harvest, and photo updates.'],
    ];
    foreach ($defaults as $key => [$value, $description]) {
        if (app_setting($conn, $key, '') === '') {
            set_app_setting($conn, $key, $value, $description);
        }
    }
}

function family_portal_master_enabled(mysqli $conn): bool {
    ensure_family_portal_control_settings($conn);
    return setting_enabled($conn, 'family_portal_enabled', true);
}

function family_scan_enabled(mysqli $conn): bool {
    ensure_family_portal_control_settings($conn);
    return family_portal_master_enabled($conn) && setting_enabled($conn, 'family_scan_enabled', true);
}

function family_dashboard_enabled(mysqli $conn): bool {
    ensure_family_portal_control_settings($conn);
    return family_portal_master_enabled($conn) && setting_enabled($conn, 'family_dashboard_enabled', true);
}

function family_submission_enabled(mysqli $conn): bool {
    ensure_family_portal_control_settings($conn);
    return family_dashboard_enabled($conn) && setting_enabled($conn, 'family_submission_enabled', true);
}

function render_family_portal_unavailable_page(mysqli $conn, string $title, string $message): void {
    $logoUrl = function_exists('system_logo_url') ? system_logo_url($conn) : app_url('assets/img/image.jpg');
    $appName = function_exists('system_title') ? system_title($conn) : 'HARVEST System';
    http_response_code(403);
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">';
    echo '<title>' . e($title) . ' - ' . e($appName) . '</title>';
    echo '<link rel="stylesheet" href="' . e(app_url('assets/css/app.css')) . '">';
    echo '<script src="https://cdn.tailwindcss.com"></script></head><body class="min-h-screen bg-slate-50 text-slate-800">';
    echo '<div class="min-h-screen flex items-center justify-center px-4"><div class="w-full max-w-xl rounded-[2rem] border border-slate-200 bg-white p-8 shadow-sm">';
    echo '<div class="flex items-center gap-4"><img src="' . e($logoUrl) . '" alt="Logo" class="h-16 w-16 rounded-2xl object-cover">';
    echo '<div><div class="text-sm text-slate-500">' . e($appName) . '</div><h1 class="text-3xl font-black text-slate-900">' . e($title) . '</h1></div></div>';
    echo '<p class="mt-5 text-slate-600 leading-7">' . e($message) . '</p>';
    echo '<div class="mt-8 flex flex-wrap gap-3"><a href="' . e(app_url('modules/users/auth/login.php')) . '" class="app-btn-primary">Back to login</a>';
    echo '<a href="' . e(app_url('modules/family/logout.php')) . '" class="app-btn-outline">Exit family portal</a></div>';
    echo '</div></div></body></html>';
    exit;
}

function require_family_scan_enabled(mysqli $conn): void {
    if (!family_scan_enabled($conn)) {
        render_family_portal_unavailable_page($conn, 'Family access unavailable', 'The developer has turned off family QR access for now. Please use the staff login page or contact the developer to re-enable the family portal.');
    }
}

function require_family_dashboard_enabled(mysqli $conn): void {
    if (!family_dashboard_enabled($conn)) {
        render_family_portal_unavailable_page($conn, 'Family dashboard unavailable', 'The developer has temporarily turned off the family dashboard. Please try again later or contact the developer.');
    }
}

function require_family_submission_enabled(mysqli $conn): void {
    if (!family_submission_enabled($conn)) {
        render_family_portal_unavailable_page($conn, 'Family submissions unavailable', 'The developer has temporarily turned off family submissions. You can still view your dashboard when the portal is enabled again.');
    }
}

function family_portal_enabled(mysqli $conn, int $householdId): bool {
    if (!family_portal_master_enabled($conn)) {
        return false;
    }
    if (column_exists($conn, 'households', 'qr_scan_enabled')) {
        return (int)scalar($conn, "SELECT qr_scan_enabled FROM households WHERE household_id=" . (int)$householdId . " LIMIT 1", 0) === 1;
    }
    return true;
}

function ensure_family_portal_schema(mysqli $conn): void {
    ensure_user_account_schema($conn);
    ensure_family_portal_control_settings($conn);
    if (!table_exists($conn, 'family_portal_updates')) {
        @$conn->query("CREATE TABLE family_portal_updates (
            update_id BIGINT AUTO_INCREMENT PRIMARY KEY,
            household_id BIGINT NOT NULL,
            crop_id BIGINT NULL,
            update_type VARCHAR(50) NOT NULL DEFAULT 'Harvest Update',
            title VARCHAR(150) NULL,
            notes TEXT NULL,
            activity_date DATE NULL,
            quantity_value DECIMAL(12,2) NULL,
            quantity_unit VARCHAR(30) NULL,
            photo_path VARCHAR(255) NULL,
            submitted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            reviewed_status VARCHAR(30) NOT NULL DEFAULT 'Pending',
            reviewed_by BIGINT NULL,
            reviewed_at DATETIME NULL,
            review_notes TEXT NULL,
            points_awarded DECIMAL(8,2) NOT NULL DEFAULT 0,
            INDEX idx_family_updates_household (household_id),
            INDEX idx_family_updates_crop (crop_id),
            INDEX idx_family_updates_status (reviewed_status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    }
    if (!column_exists($conn, 'family_portal_updates', 'crop_id')) @$conn->query("ALTER TABLE family_portal_updates ADD COLUMN crop_id BIGINT NULL AFTER household_id");
    if (!column_exists($conn, 'family_portal_updates', 'activity_date')) @$conn->query("ALTER TABLE family_portal_updates ADD COLUMN activity_date DATE NULL AFTER notes");
    if (!column_exists($conn, 'family_portal_updates', 'quantity_value')) @$conn->query("ALTER TABLE family_portal_updates ADD COLUMN quantity_value DECIMAL(12,2) NULL AFTER activity_date");
    if (!column_exists($conn, 'family_portal_updates', 'quantity_unit')) @$conn->query("ALTER TABLE family_portal_updates ADD COLUMN quantity_unit VARCHAR(30) NULL AFTER quantity_value");
    if (!column_exists($conn, 'family_portal_updates', 'points_awarded')) @$conn->query("ALTER TABLE family_portal_updates ADD COLUMN points_awarded DECIMAL(8,2) NOT NULL DEFAULT 0 AFTER review_notes");

    if (!table_exists($conn, 'household_points_log')) {
        @$conn->query("CREATE TABLE household_points_log (
            point_log_id BIGINT AUTO_INCREMENT PRIMARY KEY,
            household_id BIGINT NOT NULL,
            source_type VARCHAR(50) NOT NULL,
            source_id BIGINT NULL,
            points_awarded DECIMAL(8,2) NOT NULL DEFAULT 0,
            remarks VARCHAR(255) NULL,
            awarded_by BIGINT NULL,
            awarded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            status VARCHAR(20) NOT NULL DEFAULT 'Active',
            INDEX idx_points_household (household_id),
            INDEX idx_points_source (source_type, source_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    }

    if (!table_exists($conn, 'qualification_rules')) {
        @$conn->query("CREATE TABLE qualification_rules (
            rule_id BIGINT AUTO_INCREMENT PRIMARY KEY,
            rule_key VARCHAR(80) NOT NULL,
            rule_label VARCHAR(120) NOT NULL,
            points_value DECIMAL(8,2) NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            monthly_cap INT NULL,
            per_crop_day_cap INT NULL,
            description VARCHAR(255) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_qualification_rule_key (rule_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    }

    if (!table_exists($conn, 'household_points_summary')) {
        @$conn->query("CREATE TABLE household_points_summary (
            household_id BIGINT PRIMARY KEY,
            total_points DECIMAL(10,2) NOT NULL DEFAULT 0,
            total_score DECIMAL(10,2) NOT NULL DEFAULT 0,
            qualification_status VARCHAR(80) NULL,
            approved_updates INT NOT NULL DEFAULT 0,
            last_calculated_at DATETIME NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    }

    $defaults = [
        ['harvest_update_points','Harvest update points',20,null,1,'Approved harvest update for a registered crop'],
        ['crop_update_points','Crop update points',10,null,1,'Approved crop progress update for a registered crop'],
        ['field_photo_points','Field photo points',5,2,null,'Approved field photo or proof update'],
        ['family_note_points','Family note points',0,null,null,'Approved non-crop family note'],
        ['minimum_total_score_qualified','Minimum total score to qualify',60,null,null,'Combined score needed for Qualified status'],
        ['minimum_total_score_validation','Minimum total score for validation',40,null,null,'Combined score needed for For Validation status'],
    ];
    foreach ($defaults as $rule) {
        [$key,$label,$points,$monthlyCap,$perCropCap,$description] = $rule;
        $stmt = $conn->prepare("INSERT IGNORE INTO qualification_rules (rule_key, rule_label, points_value, is_active, monthly_cap, per_crop_day_cap, description) VALUES (?, ?, ?, 1, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param('ssdiis', $key, $label, $points, $monthlyCap, $perCropCap, $description);
            $stmt->execute();
            $stmt->close();
        }
    }
    @mkdir(app_path('public/uploads/family_portal'), 0777, true);
}

function family_submission_rule_key(string $type): string {
    return match ($type) {
        'Harvest Update' => 'harvest_update_points',
        'Crop Update' => 'crop_update_points',
        'Field Photo' => 'field_photo_points',
        default => 'family_note_points',
    };
}

function qualification_rule_row(mysqli $conn, string $ruleKey): ?array {
    if (!table_exists($conn, 'qualification_rules')) return null;
    $stmt = $conn->prepare("SELECT * FROM qualification_rules WHERE rule_key=? LIMIT 1");
    if (!$stmt) return null;
    $stmt->bind_param('s', $ruleKey);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();
    return $row;
}

function qualification_rule_points(mysqli $conn, string $ruleKey, float $default = 0.0): float {
    $row = qualification_rule_row($conn, $ruleKey);
    if (!$row || (int)($row['is_active'] ?? 0) !== 1) return $default;
    return (float)($row['points_value'] ?? $default);
}

function family_submission_points_for_type(mysqli $conn, string $type): float {
    return match ($type) {
        'Harvest Update' => qualification_rule_points($conn, 'harvest_update_points', 20.0),
        'Crop Update' => qualification_rule_points($conn, 'crop_update_points', 10.0),
        'Field Photo' => qualification_rule_points($conn, 'field_photo_points', 5.0),
        default => qualification_rule_points($conn, 'family_note_points', 0.0),
    };
}

function family_submission_point_limits(mysqli $conn, string $type): array {
    $row = qualification_rule_row($conn, family_submission_rule_key($type));
    return [
        'monthly_cap' => isset($row['monthly_cap']) && $row['monthly_cap'] !== null ? (int)$row['monthly_cap'] : null,
        'per_crop_day_cap' => isset($row['per_crop_day_cap']) && $row['per_crop_day_cap'] !== null ? (int)$row['per_crop_day_cap'] : null,
        'active' => $row ? ((int)($row['is_active'] ?? 0) === 1) : true,
    ];
}

function family_submission_award_preview(mysqli $conn, array $update): array {
    $type = (string)($update['update_type'] ?? 'Family Note');
    $points = family_submission_points_for_type($conn, $type);
    $limits = family_submission_point_limits($conn, $type);
    $reason = '';
    if (!$limits['active']) {
        return ['points' => 0.0, 'reason' => 'This rule is currently disabled.'];
    }
    if ($points <= 0) {
        return ['points' => 0.0, 'reason' => 'This submission type does not earn points.'];
    }

    $householdId = (int)($update['household_id'] ?? 0);
    $cropId = (int)($update['crop_id'] ?? 0);
    $activityDate = trim((string)($update['activity_date'] ?? ''));
    $updateId = (int)($update['update_id'] ?? 0);

    if ($activityDate !== '') {
        $stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM family_portal_updates WHERE household_id=? AND update_type=? AND reviewed_status='Approved' AND COALESCE(activity_date,'0000-00-00')=? AND update_id<>?" . ($cropId > 0 ? " AND crop_id=?" : ''));
        if ($stmt) {
            if ($cropId > 0) {
                $stmt->bind_param('issii', $householdId, $type, $activityDate, $updateId, $cropId);
            } else {
                $stmt->bind_param('issi', $householdId, $type, $activityDate, $updateId);
            }
            $stmt->execute();
            $res = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ((int)($res['cnt'] ?? 0) > 0) {
                return ['points' => 0.0, 'reason' => 'A similar approved update already exists for this date.'];
            }
        }
    }

    if ($limits['monthly_cap'] !== null) {
        $month = $activityDate !== '' ? substr($activityDate, 0, 7) : date('Y-m', strtotime((string)($update['submitted_at'] ?? 'now')));
        $stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM family_portal_updates WHERE household_id=? AND update_type=? AND reviewed_status='Approved' AND DATE_FORMAT(COALESCE(activity_date, DATE(submitted_at)),'%Y-%m')=? AND update_id<>?");
        if ($stmt) {
            $stmt->bind_param('issi', $householdId, $type, $month, $updateId);
            $stmt->execute();
            $res = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ((int)($res['cnt'] ?? 0) >= (int)$limits['monthly_cap']) {
                return ['points' => 0.0, 'reason' => 'Monthly point cap reached for this submission type.'];
            }
        }
    }

    if ($cropId > 0 && $activityDate !== '' && $limits['per_crop_day_cap'] !== null) {
        $stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM family_portal_updates WHERE household_id=? AND crop_id=? AND update_type=? AND reviewed_status='Approved' AND activity_date=? AND update_id<>?");
        if ($stmt) {
            $stmt->bind_param('iissi', $householdId, $cropId, $type, $activityDate, $updateId);
            $stmt->execute();
            $res = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ((int)($res['cnt'] ?? 0) >= (int)$limits['per_crop_day_cap']) {
                return ['points' => 0.0, 'reason' => 'Point cap reached for this crop on the selected date.'];
            }
        }
    }

    return ['points' => $points, 'reason' => ''];
}

function recalculate_household_points_summary(mysqli $conn, int $householdId): void {
    if ($householdId <= 0 || !table_exists($conn, 'household_points_summary')) return;
    $totalPoints = household_family_points_total($conn, $householdId);
    $qualification = fetch_one($conn, "SELECT score, qualification_status FROM household_qualification WHERE household_id=" . (int)$householdId . " LIMIT 1") ?: ['score' => 0, 'qualification_status' => 'For Validation'];
    $approvedUpdates = (int)scalar($conn, "SELECT COUNT(*) FROM family_portal_updates WHERE household_id=" . (int)$householdId . " AND reviewed_status='Approved'", 0);
    $stmt = $conn->prepare("INSERT INTO household_points_summary (household_id, total_points, total_score, qualification_status, approved_updates, last_calculated_at) VALUES (?, ?, ?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE total_points=VALUES(total_points), total_score=VALUES(total_score), qualification_status=VALUES(qualification_status), approved_updates=VALUES(approved_updates), last_calculated_at=VALUES(last_calculated_at)");
    if ($stmt) {
        $totalScore = (float)($qualification['score'] ?? 0);
        $status = (string)($qualification['qualification_status'] ?? 'For Validation');
        $stmt->bind_param('iddsi', $householdId, $totalPoints, $totalScore, $status, $approvedUpdates);
        $stmt->execute();
        $stmt->close();
    }
}

function family_points_breakdown(mysqli $conn, int $householdId): array {
    if (!table_exists($conn, 'household_points_log')) return [];
    return fetch_all_assoc($conn, "SELECT source_type, COUNT(*) AS item_count, COALESCE(SUM(points_awarded),0) AS total_points FROM household_points_log WHERE household_id=" . (int)$householdId . " AND status='Active' GROUP BY source_type ORDER BY total_points DESC, item_count DESC");
}

function family_points_history(mysqli $conn, int $householdId, int $limit = 12): array {
    if (!table_exists($conn, 'household_points_log')) return [];
    return fetch_all_assoc($conn, "SELECT * FROM household_points_log WHERE household_id=" . (int)$householdId . " AND status='Active' ORDER BY awarded_at DESC, point_log_id DESC LIMIT " . (int)$limit);
}

function family_recent_timeline(mysqli $conn, int $householdId, int $limit = 12): array {
    $items = [];
    if (table_exists($conn, 'family_portal_updates')) {
        foreach (fetch_all_assoc($conn, "SELECT update_id, title, update_type, reviewed_status, review_notes, submitted_at, reviewed_at, points_awarded FROM family_portal_updates WHERE household_id=" . (int)$householdId . " ORDER BY submitted_at DESC LIMIT " . (int)$limit) as $row) {
            $label = $row['title'] ?: $row['update_type'];
            $items[] = [
                'event_at' => $row['reviewed_at'] ?: $row['submitted_at'],
                'title' => $label,
                'tag' => $row['reviewed_status'] === 'Pending' ? 'Submitted' : $row['reviewed_status'],
                'details' => $row['review_notes'] ?: ((string)$row['reviewed_status'] === 'Pending' ? 'Waiting for staff review.' : ('Review result: ' . $row['reviewed_status'])),
            ];
        }
    }
    if (table_exists($conn, 'monitoring_visits')) {
        foreach (fetch_all_assoc($conn, "SELECT monitoring_date, notes, harvest_kg FROM monitoring_visits WHERE household_id=" . (int)$householdId . " ORDER BY monitoring_date DESC LIMIT 6") as $row) {
            $items[] = [
                'event_at' => $row['monitoring_date'],
                'title' => 'Monitoring visit completed',
                'tag' => 'Monitoring',
                'details' => trim((string)($row['notes'] ?: ('Recorded harvest: ' . (string)($row['harvest_kg'] ?? '0') . ' kg.'))),
            ];
        }
    }
    if (table_exists($conn, 'qualification_history')) {
        foreach (fetch_all_assoc($conn, "SELECT qualification_status, score, recorded_at FROM qualification_history WHERE household_id=" . (int)$householdId . " ORDER BY recorded_at DESC LIMIT 6") as $row) {
            $items[] = [
                'event_at' => $row['recorded_at'],
                'title' => 'Qualification updated',
                'tag' => $row['qualification_status'] ?: 'For Validation',
                'details' => 'Score: ' . rtrim(rtrim(number_format((float)($row['score'] ?? 0), 2, '.', ''), '0'), '.'),
            ];
        }
    }
    usort($items, fn($a, $b) => strcmp((string)($b['event_at'] ?? ''), (string)($a['event_at'] ?? '')));
    return array_slice($items, 0, $limit);
}

function family_qualification_progress(mysqli $conn, int $householdId): array {
    $qualification = fetch_one($conn, "SELECT score, qualification_status FROM household_qualification WHERE household_id=" . (int)$householdId . " LIMIT 1") ?: ['score' => 0, 'qualification_status' => 'For Validation'];
    $totalScore = (float)($qualification['score'] ?? 0);
    $familyPoints = household_family_points_total($conn, $householdId);
    $qualifiedTarget = qualification_rule_points($conn, 'minimum_total_score_qualified', 60.0);
    $validationTarget = qualification_rule_points($conn, 'minimum_total_score_validation', 40.0);
    $interviewCount = (int)scalar($conn, "SELECT COUNT(*) FROM interviews WHERE household_id=" . (int)$householdId . " AND status='Completed'", 0);
    $monitoringCount = (int)scalar($conn, "SELECT COUNT(*) FROM monitoring_visits WHERE household_id=" . (int)$householdId, 0);
    $approvedCropHarvest = (int)scalar($conn, "SELECT COUNT(*) FROM family_portal_updates WHERE household_id=" . (int)$householdId . " AND reviewed_status='Approved' AND update_type IN ('Harvest Update','Crop Update')", 0);
    $activeCrops = (int)scalar($conn, "SELECT COUNT(*) FROM crops WHERE household_id=" . (int)$householdId . " AND crop_status='Active'", 0);
    $missing = [];
    if ($interviewCount <= 0) $missing[] = 'Interview not completed';
    if ($monitoringCount <= 0) $missing[] = 'No monitoring visit recorded';
    if ($approvedCropHarvest <= 0 && $activeCrops > 0) $missing[] = 'No approved crop or harvest update yet';
    $nextTarget = $totalScore < $validationTarget ? $validationTarget : $qualifiedTarget;
    return [
        'total_score' => $totalScore,
        'family_points' => $familyPoints,
        'qualification_status' => (string)($qualification['qualification_status'] ?? 'For Validation'),
        'qualified_target' => $qualifiedTarget,
        'validation_target' => $validationTarget,
        'next_target' => $nextTarget,
        'remaining_points' => max(0, $nextTarget - $totalScore),
        'progress_percent' => $nextTarget > 0 ? min(100, round(($totalScore / $nextTarget) * 100)) : 0,
        'missing_requirements' => $missing,
        'requirements' => [
            'interview_completed' => $interviewCount > 0,
            'monitoring_completed' => $monitoringCount > 0,
            'approved_crop_or_harvest' => $approvedCropHarvest > 0,
            'active_crops' => $activeCrops,
        ],
    ];
}

function family_dashboard_submission_options(mysqli $conn, int $householdId): array {
    $types = family_update_type_options();
    $crops = family_portal_crop_options($conn, $householdId);
    if (!$crops) {
        unset($types['Harvest Update'], $types['Crop Update']);
    }
    return $types;
}

function family_portal_crop_options(mysqli $conn, int $householdId): array {
    ensure_family_portal_schema($conn);
    return fetch_all_assoc($conn, "SELECT crop_id, crop_name, variety, plot_name, crop_status, current_condition, fruiting_status FROM crops WHERE household_id=" . (int)$householdId . " AND crop_status='Active' ORDER BY crop_name ASC, crop_id ASC");
}

function family_update_type_options(): array {
    return [
        'Harvest Update' => 'Harvest update',
        'Crop Update' => 'Crop update',
        'Field Photo' => 'Field photo',
        'Family Note' => 'Family note',
    ];
}

function family_update_requires_crop(string $type): bool {
    return in_array($type, ['Harvest Update', 'Crop Update'], true);
}

function household_family_points_total(mysqli $conn, int $householdId): float {
    if (!table_exists($conn, 'household_points_log')) return 0.0;
    return (float)scalar($conn, "SELECT COALESCE(SUM(points_awarded),0) FROM household_points_log WHERE household_id=" . (int)$householdId . " AND status='Active'", 0);
}

function reverse_family_update_points(mysqli $conn, int $updateId): void {
    if (!table_exists($conn, 'household_points_log')) return;
    @$conn->query("UPDATE household_points_log SET status='Reversed', remarks=CONCAT(COALESCE(remarks,''), ' [reversed]') WHERE source_type='family_update' AND source_id=" . (int)$updateId . " AND status='Active'");
}

function upload_family_portal_photo(array $file): ?string {
    return upload_image_file($file, 'public/uploads/family_portal');
}

function family_portal_login(mysqli $conn, int $householdId): void {
    $_SESSION['family_household_id'] = $householdId;
    $_SESSION['family_portal'] = 1;
    if (table_exists($conn, 'qr_codes')) {
        @$conn->query("UPDATE qr_codes SET total_scans = total_scans + 1, last_scanned_at = NOW() WHERE household_id=" . (int)$householdId . " AND qr_type='HOUSEHOLD'");
    }
}

function family_portal_logout(): void {
    unset($_SESSION['family_household_id'], $_SESSION['family_portal']);
}

function current_family_household(mysqli $conn): ?array {
    $householdId = (int)($_SESSION['family_household_id'] ?? 0);
    if ($householdId <= 0) return null;
    $sql = "SELECT h.*, b.barangay_name, q.score, q.qualification_status
            FROM households h
            LEFT JOIN barangays b ON b.barangay_id=h.barangay_id
            LEFT JOIN household_qualification q ON q.household_id=h.household_id
            WHERE h.household_id=" . $householdId . " LIMIT 1";
    $row = fetch_one($conn, $sql);
    if ($row) {
        $row['family_points_total'] = household_family_points_total($conn, (int)$row['household_id']);
    }
    return $row;
}

function require_family_portal_login(mysqli $conn): array {
    $household = current_family_household($conn);
    if (!$household) {
        header('Location: ' . app_url('modules/family/scan.php'));
        exit;
    }
    return $household;
}

function family_updates_summary(mysqli $conn): array {
    ensure_family_portal_schema($conn);
    return [
        'pending' => (int)scalar($conn, "SELECT COUNT(*) FROM family_portal_updates WHERE reviewed_status='Pending'", 0),
        'approved' => (int)scalar($conn, "SELECT COUNT(*) FROM family_portal_updates WHERE reviewed_status='Approved'", 0),
        'needs_revision' => (int)scalar($conn, "SELECT COUNT(*) FROM family_portal_updates WHERE reviewed_status='Needs Revision'", 0),
        'rejected' => (int)scalar($conn, "SELECT COUNT(*) FROM family_portal_updates WHERE reviewed_status='Rejected'", 0),
    ];
}

function family_portal_notifications(mysqli $conn, int $householdId, int $limit = 12): array {
    $items = [];
    if (table_exists($conn, 'notifications')) {
        $items = fetch_all_assoc($conn, "SELECT notification_id, title, message, severity, created_at FROM notifications WHERE household_id=" . (int)$householdId . " ORDER BY created_at DESC LIMIT " . (int)$limit);
    }
    if (table_exists($conn, 'family_portal_updates')) {
        $feedback = fetch_all_assoc($conn, "SELECT update_id, title, update_type, reviewed_status, review_notes, reviewed_at, points_awarded FROM family_portal_updates WHERE household_id=" . (int)$householdId . " AND reviewed_status IN ('Approved','Rejected','Needs Revision') ORDER BY COALESCE(reviewed_at, submitted_at) DESC LIMIT " . (int)$limit);
        foreach ($feedback as $row) {
            $message = $row['review_notes'] ?: ('Your family submission was reviewed as ' . strtolower((string)$row['reviewed_status']) . '.');
            if ((string)$row['reviewed_status'] === 'Approved' && (float)($row['points_awarded'] ?? 0) > 0) {
                $message .= ' +' . rtrim(rtrim(number_format((float)$row['points_awarded'], 2, '.', ''), '0'), '.') . ' points added to your household qualification.';
            }
            $items[] = [
                'notification_id' => 'family-update-' . $row['update_id'],
                'title' => (($row['title'] ?: $row['update_type']) . ' · ' . $row['reviewed_status']),
                'message' => $message,
                'severity' => $row['reviewed_status'] === 'Approved' ? 'Low' : ((string)$row['reviewed_status'] === 'Needs Revision' ? 'Medium' : 'High'),
                'created_at' => $row['reviewed_at'] ?: null,
            ];
        }
    }
    usort($items, function($a, $b){
        return strcmp((string)($b['created_at'] ?? ''), (string)($a['created_at'] ?? ''));
    });
    return array_slice($items, 0, $limit);
}

function review_family_update(mysqli $conn, int $updateId, int $reviewerId, string $decision, string $notes = ''): bool {
    ensure_family_portal_schema($conn);
    $decision = in_array($decision, ['Approved','Rejected','Needs Revision'], true) ? $decision : 'Pending';
    $row = fetch_one($conn, "SELECT household_id, crop_id, title, update_type, reviewed_status, activity_date, submitted_at FROM family_portal_updates WHERE update_id=" . (int)$updateId . " LIMIT 1");
    if (!$row) return false;

    $award = ['points' => 0.0, 'reason' => ''];
    if ($decision === 'Approved') {
        $award = family_submission_award_preview($conn, array_merge($row, ['update_id' => $updateId]));
        if ($award['reason'] !== '' && trim($notes) === '') {
            $notes = $award['reason'];
        }
    }
    $points = $decision === 'Approved' ? (float)$award['points'] : 0.0;
    if ((string)$row['reviewed_status'] === 'Approved' && $decision !== 'Approved') {
        reverse_family_update_points($conn, $updateId);
    }
    if ((string)$row['reviewed_status'] === 'Approved' && $decision === 'Approved') {
        reverse_family_update_points($conn, $updateId);
    }

    $stmt = $conn->prepare("UPDATE family_portal_updates SET reviewed_status=?, reviewed_by=?, reviewed_at=NOW(), review_notes=?, points_awarded=? WHERE update_id=?");
    if (!$stmt) return false;
    $stmt->bind_param('sisdi', $decision, $reviewerId, $notes, $points, $updateId);
    $ok = $stmt->execute();
    $stmt->close();

    if ($ok && $decision === 'Approved' && $points > 0 && table_exists($conn, 'household_points_log')) {
        $stmtPts = $conn->prepare("INSERT INTO household_points_log (household_id, source_type, source_id, points_awarded, remarks, awarded_by, awarded_at, status) VALUES (?, 'family_update', ?, ?, ?, ?, NOW(), 'Active')");
        if ($stmtPts) {
            $hid = (int)$row['household_id'];
            $remarks = trim(((string)($row['title'] ?: $row['update_type'])) . ' approved');
            $stmtPts->bind_param('iidsi', $hid, $updateId, $points, $remarks, $reviewerId);
            $stmtPts->execute();
            $stmtPts->close();
        }
    }
    if ($ok) {
        refresh_household_qualification_php($conn, (int)$row['household_id']);
        recalculate_household_points_summary($conn, (int)$row['household_id']);
    }
    if ($ok && table_exists($conn, 'notifications')) {
        $title = (($row['title'] ?: $row['update_type']) . ' reviewed');
        $message = $notes !== '' ? $notes : ('Your family update was reviewed as ' . strtolower($decision) . '.');
        if ($decision === 'Approved' && $points > 0) {
            $message .= ' +' . rtrim(rtrim(number_format($points, 2, '.', ''), '0'), '.') . ' points added to your household score.';
        }
        $severity = $decision === 'Approved' ? 'Low' : ($decision === 'Needs Revision' ? 'Medium' : 'High');
        $stmt2 = $conn->prepare("INSERT INTO notifications (household_id, crop_id, notification_type, title, message, severity, is_read, created_at) VALUES (?, ?, 'Qualification Updated', ?, ?, ?, 0, NOW())");
        if ($stmt2) {
            $hid = (int)$row['household_id'];
            $cropId = isset($row['crop_id']) ? (int)$row['crop_id'] : null;
            $stmt2->bind_param('iisss', $hid, $cropId, $title, $message, $severity);
            $stmt2->execute();
            $stmt2->close();
        }
    }
    return $ok;
}

