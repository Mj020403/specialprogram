<?php

function ensure_event_code(mysqli $conn, int $eventId): void {
    if ($eventId <= 0) return;
    $code = 'EVT-' . str_pad((string)$eventId, 6, '0', STR_PAD_LEFT);
    $stmt = $conn->prepare("UPDATE events SET event_code = COALESCE(NULLIF(event_code,''), ?) WHERE event_id = ?");
    if ($stmt) { $stmt->bind_param('si', $code, $eventId); $stmt->execute(); $stmt->close(); }
}

function routine_exists(mysqli $conn, string $routineName, string $routineType = 'PROCEDURE'): bool {
    $sql = "SELECT COUNT(*) FROM information_schema.routines WHERE routine_schema = DATABASE() AND routine_name = ? AND routine_type = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) return false;
    $stmt->bind_param('ss', $routineName, $routineType);
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();
    return (int)$count > 0;
}

function ensure_refresh_household_qualification_procedure(mysqli $conn): void {
    if (routine_exists($conn, 'sp_refresh_household_qualification', 'PROCEDURE')) return;
    $sql = "CREATE PROCEDURE sp_refresh_household_qualification(IN p_household_id BIGINT) BEGIN DECLARE v_interview_count INT DEFAULT 0; DECLARE v_active_crops INT DEFAULT 0; DECLARE v_recent_monitoring INT DEFAULT 0; DECLARE v_attendance INT DEFAULT 0; DECLARE v_score DECIMAL(5,2) DEFAULT 0; DECLARE v_status VARCHAR(100) DEFAULT 'For Validation'; DECLARE v_explanation TEXT DEFAULT ''; SELECT COUNT(*) INTO v_interview_count FROM interviews WHERE household_id = p_household_id AND status='Completed'; SELECT COUNT(*) INTO v_active_crops FROM crops WHERE household_id = p_household_id AND crop_status='Active'; SELECT COUNT(*) INTO v_recent_monitoring FROM monitoring_visits WHERE household_id = p_household_id AND monitoring_date >= DATE_SUB(CURDATE(), INTERVAL 180 DAY); SELECT COUNT(*) INTO v_attendance FROM event_attendance ea INNER JOIN events e ON e.event_id=ea.event_id WHERE ea.household_id=p_household_id AND ea.attendance_status IN ('Present','Late') AND e.event_status <> 'Cancelled'; SET v_score = (CASE WHEN v_interview_count > 0 THEN 25 ELSE 0 END) + (CASE WHEN v_active_crops > 0 THEN 25 ELSE 0 END) + (CASE WHEN v_recent_monitoring > 0 THEN 20 ELSE 0 END) + (CASE WHEN v_attendance > 0 THEN 10 ELSE 0 END); IF v_score >= 65 THEN SET v_status = 'Qualified'; ELSEIF v_score >= 40 THEN SET v_status = 'Needs Support'; ELSEIF v_score = 0 THEN SET v_status = 'Not Qualified'; ELSE SET v_status = 'For Validation'; END IF; SET v_explanation = CONCAT('Interview: ', v_interview_count, ', Active crops: ', v_active_crops, ', Recent monitoring: ', v_recent_monitoring, ', Attendance: ', v_attendance); INSERT INTO household_qualification (household_id, score, qualification_status, explanation, last_evaluated_at) VALUES (p_household_id, v_score, v_status, v_explanation, NOW()) ON DUPLICATE KEY UPDATE score=VALUES(score), qualification_status=VALUES(qualification_status), explanation=VALUES(explanation), last_evaluated_at=VALUES(last_evaluated_at); END";
    @$conn->query($sql);
}

function create_notification(mysqli $conn, string $title, string $message, string $severity = 'Low', ?int $userId = null, ?int $householdId = null, ?int $cropId = null, string $type = 'Qualification Updated'): void {
    if (!table_exists($conn, 'notifications')) return;
    $cols = [];
    $vals = [];
    if (column_exists($conn, 'notifications', 'user_id')) { $cols[] = 'user_id'; $vals[] = $userId === null ? 'NULL' : (int)$userId; }
    if (column_exists($conn, 'notifications', 'household_id')) { $cols[] = 'household_id'; $vals[] = $householdId === null ? 'NULL' : (int)$householdId; }
    if (column_exists($conn, 'notifications', 'crop_id')) { $cols[] = 'crop_id'; $vals[] = $cropId === null ? 'NULL' : (int)$cropId; }
    if (column_exists($conn, 'notifications', 'notification_type')) { $cols[] = 'notification_type'; $vals[] = "'" . $conn->real_escape_string($type) . "'"; }
    if (column_exists($conn, 'notifications', 'target_type')) { $cols[] = 'target_type'; $vals[] = "'SYSTEM'"; }
    $cols[] = 'title'; $vals[] = "'" . $conn->real_escape_string($title) . "'";
    $cols[] = 'message'; $vals[] = "'" . $conn->real_escape_string($message) . "'";
    if (column_exists($conn, 'notifications', 'severity')) { $cols[] = 'severity'; $vals[] = "'" . $conn->real_escape_string($severity) . "'"; }
    if (column_exists($conn, 'notifications', 'is_read')) { $cols[] = 'is_read'; $vals[] = '0'; }
    if (column_exists($conn, 'notifications', 'created_at')) { $cols[] = 'created_at'; $vals[] = 'NOW()'; }
    $conn->query('INSERT INTO notifications (' . implode(',', $cols) . ') VALUES (' . implode(',', $vals) . ')');
}

function refresh_household_qualification_php(mysqli $conn, int $householdId): void {
    if ($householdId <= 0 || !table_exists($conn, 'household_qualification')) return;
    ensure_decision_support_schema($conn);

    $interviewCount = (int)scalar($conn, "SELECT COUNT(*) FROM interviews WHERE household_id={$householdId} AND status='Completed'", 0);
    $activeCrops = (int)scalar($conn, "SELECT COUNT(*) FROM crops WHERE household_id={$householdId} AND crop_status='Active'", 0);
    $fruitingCrops = (int)scalar($conn, "SELECT COUNT(*) FROM crops WHERE household_id={$householdId} AND crop_status='Active' AND fruiting_status='Fruiting'", 0);
    $recentMonitoring = (int)scalar($conn, "SELECT COUNT(*) FROM monitoring_visits WHERE household_id={$householdId} AND monitoring_date >= DATE_SUB(CURDATE(), INTERVAL 180 DAY)", 0);
    $recentGood = (int)scalar($conn, "SELECT COUNT(*) FROM monitoring_visits WHERE household_id={$householdId} AND monitoring_date >= DATE_SUB(CURDATE(), INTERVAL 180 DAY) AND crop_condition='Good'", 0);
    $attendance = (int)scalar($conn, "SELECT COUNT(*) FROM event_attendance WHERE household_id={$householdId} AND attendance_status IN ('Present','Late') AND created_at >= DATE_SUB(NOW(), INTERVAL 365 DAY)", 0);
    $latestHarvest = (float)scalar($conn, "SELECT COALESCE(MAX(harvest_kg),0) FROM monitoring_visits WHERE household_id={$householdId}", 0);
    $lastMonitoringDate = scalar($conn, "SELECT MAX(monitoring_date) FROM monitoring_visits WHERE household_id={$householdId}", null);
    $daysSinceMonitoring = $lastMonitoringDate ? max(0, (int)floor((time() - strtotime((string)$lastMonitoringDate)) / 86400)) : null;
    $recentDeliveredAssistance = table_exists($conn, 'assistance_records')
        ? (int)scalar($conn, "SELECT COUNT(*) FROM assistance_records WHERE household_id={$householdId} AND assistance_status IN ('Delivered','Completed') AND assistance_date >= DATE_SUB(CURDATE(), INTERVAL 365 DAY)", 0)
        : 0;
    $familyPointsBonus = table_exists($conn, 'household_points_log') ? (float)scalar($conn, "SELECT COALESCE(SUM(points_awarded),0) FROM household_points_log WHERE household_id={$householdId} AND status='Active'", 0) : 0.0;
    $approvedCropHarvest = table_exists($conn, 'family_portal_updates') ? (int)scalar($conn, "SELECT COUNT(*) FROM family_portal_updates WHERE household_id={$householdId} AND reviewed_status='Approved' AND update_type IN ('Harvest Update','Crop Update')", 0) : 0;
    $followupDue = table_exists($conn, 'assistance_records')
        ? (int)scalar($conn, "SELECT COUNT(*) FROM assistance_records WHERE household_id={$householdId} AND next_followup_date IS NOT NULL AND next_followup_date <= DATE_ADD(CURDATE(), INTERVAL 14 DAY) AND assistance_status NOT IN ('Cancelled','Completed')", 0)
        : 0;

    $score = 0;
    if ($interviewCount > 0) $score += 20;
    if ($activeCrops > 0) $score += 20;
    if ($fruitingCrops > 0) $score += 15;
    if ($recentMonitoring > 0) $score += 15;
    if ($recentGood > 0) $score += 10;
    if ($attendance > 0) $score += 10;
    if ($latestHarvest > 0) $score += 5;
    if ($recentDeliveredAssistance > 0) $score += 5;
    $score += $familyPointsBonus;

    $reasons = [];
    if ($interviewCount <= 0) $reasons[] = 'No completed interview';
    if ($activeCrops <= 0) $reasons[] = 'No active crop';
    if ($fruitingCrops <= 0) $reasons[] = 'No fruiting crop';
    if ($recentMonitoring <= 0) $reasons[] = 'No recent monitoring';
    if ($attendance <= 0) $reasons[] = 'Low participation';
    if ($latestHarvest <= 0) $reasons[] = 'No harvest recorded';
    if ($approvedCropHarvest <= 0 && $activeCrops > 0) $reasons[] = 'No approved crop or harvest family update';
    if ($followupDue > 0) $reasons[] = 'Assistance follow-up due';

    $riskFlags = [];
    if ($activeCrops > 0 && $recentMonitoring <= 0) $riskFlags[] = 'Monitoring overdue';
    if ($recentMonitoring > 0 && $recentGood <= 0) $riskFlags[] = 'Condition needs review';
    if ($attendance <= 0 && $interviewCount > 0) $riskFlags[] = 'No recent event attendance';
    if ($followupDue > 0) $riskFlags[] = 'Pending intervention follow-up';

    $qualifiedTarget = qualification_rule_points($conn, 'minimum_total_score_qualified', 60.0);
    $validationTarget = qualification_rule_points($conn, 'minimum_total_score_validation', 40.0);

    $status = 'For Validation';
    if ($score >= ($qualifiedTarget + 20) && $approvedCropHarvest > 0) $status = 'Highly Qualified';
    elseif ($score >= $qualifiedTarget && $approvedCropHarvest > 0 && $interviewCount > 0 && $recentMonitoring > 0) $status = 'Qualified';
    elseif ($score >= $validationTarget) $status = 'Needs Support';
    elseif ($score === 0) $status = 'Not Qualified';

    if (($activeCrops > 0 && $recentMonitoring <= 0) || ($followupDue > 0 && $score < $qualifiedTarget)) {
        $status = 'High Risk';
    }

    $explanationParts = [
        'Interview: ' . $interviewCount,
        'Active crops: ' . $activeCrops,
        'Fruiting: ' . $fruitingCrops,
        'Recent monitoring: ' . $recentMonitoring,
        'Good condition: ' . $recentGood,
        'Attendance: ' . $attendance,
        'Latest harvest kg: ' . number_format($latestHarvest, 2),
        'Family contribution points: ' . number_format($familyPointsBonus, 2),
        'Approved crop/harvest updates: ' . $approvedCropHarvest,
    ];
    if ($daysSinceMonitoring !== null) $explanationParts[] = 'Days since monitoring: ' . $daysSinceMonitoring;
    if ($reasons) $explanationParts[] = 'Reasons: ' . implode('; ', $reasons);
    if ($riskFlags) $explanationParts[] = 'Risk flags: ' . implode('; ', $riskFlags);
    $explanation = implode(' | ', $explanationParts);

    $current = fetch_one($conn, "SELECT qualification_status, score FROM household_qualification WHERE household_id={$householdId} LIMIT 1");
    $escapedStatus = "'" . $conn->real_escape_string($status) . "'";
    $escapedExplanation = "'" . $conn->real_escape_string($explanation) . "'";
    $sets = [
        'score=' . (float)$score,
        'qualification_status=' . $escapedStatus,
        'explanation=' . $escapedExplanation,
        'last_evaluated_at=NOW()'
    ];
    if (column_exists($conn, 'household_qualification', 'has_active_crop')) $sets[] = 'has_active_crop=' . ($activeCrops > 0 ? 1 : 0);
    if (column_exists($conn, 'household_qualification', 'has_recent_monitoring')) $sets[] = 'has_recent_monitoring=' . ($recentMonitoring > 0 ? 1 : 0);
    if (column_exists($conn, 'household_qualification', 'has_good_condition')) $sets[] = 'has_good_condition=' . ($recentGood > 0 ? 1 : 0);
    if (column_exists($conn, 'household_qualification', 'has_fruiting_crop')) $sets[] = 'has_fruiting_crop=' . ($fruitingCrops > 0 ? 1 : 0);
    if (column_exists($conn, 'household_qualification', 'has_recent_attendance')) $sets[] = 'has_recent_attendance=' . ($attendance > 0 ? 1 : 0);
    if (column_exists($conn, 'household_qualification', 'has_completed_interview')) $sets[] = 'has_completed_interview=' . ($interviewCount > 0 ? 1 : 0);
    if (column_exists($conn, 'household_qualification', 'latest_harvest_kg')) $sets[] = 'latest_harvest_kg=' . (float)$latestHarvest;
    if (column_exists($conn, 'household_qualification', 'evaluated_by_system')) $sets[] = 'evaluated_by_system=1';

    if ($current) {
        $conn->query("UPDATE household_qualification SET " . implode(',', $sets) . " WHERE household_id={$householdId}");
    } else {
        $cols = ['household_id','score','qualification_status','explanation','last_evaluated_at'];
        $vals = [$householdId, (float)$score, $escapedStatus, $escapedExplanation, 'NOW()'];
        if (column_exists($conn, 'household_qualification', 'has_active_crop')) { $cols[] = 'has_active_crop'; $vals[] = ($activeCrops > 0 ? 1 : 0); }
        if (column_exists($conn, 'household_qualification', 'has_recent_monitoring')) { $cols[] = 'has_recent_monitoring'; $vals[] = ($recentMonitoring > 0 ? 1 : 0); }
        if (column_exists($conn, 'household_qualification', 'has_good_condition')) { $cols[] = 'has_good_condition'; $vals[] = ($recentGood > 0 ? 1 : 0); }
        if (column_exists($conn, 'household_qualification', 'has_fruiting_crop')) { $cols[] = 'has_fruiting_crop'; $vals[] = ($fruitingCrops > 0 ? 1 : 0); }
        if (column_exists($conn, 'household_qualification', 'has_recent_attendance')) { $cols[] = 'has_recent_attendance'; $vals[] = ($attendance > 0 ? 1 : 0); }
        if (column_exists($conn, 'household_qualification', 'has_completed_interview')) { $cols[] = 'has_completed_interview'; $vals[] = ($interviewCount > 0 ? 1 : 0); }
        if (column_exists($conn, 'household_qualification', 'latest_harvest_kg')) { $cols[] = 'latest_harvest_kg'; $vals[] = (float)$latestHarvest; }
        if (column_exists($conn, 'household_qualification', 'evaluated_by_system')) { $cols[] = 'evaluated_by_system'; $vals[] = 1; }
        $valSql = array_map(static function($v){ return is_string($v) && !in_array($v, ['NOW()'], true) ? $v : (is_numeric($v) ? (string)$v : $v); }, $vals);
        $conn->query('INSERT INTO household_qualification (' . implode(',', $cols) . ') VALUES (' . implode(',', $valSql) . ')');
    }
    if (table_exists($conn, 'qualification_history') && (!$current || (string)$current['qualification_status'] !== $status || (float)$current['score'] !== (float)$score)) {
        $stmt = $conn->prepare("INSERT INTO qualification_history (household_id, score, qualification_status, explanation, recorded_at) VALUES (?, ?, ?, ?, NOW())");
        if ($stmt) { $stmt->bind_param('idss', $householdId, $score, $status, $explanation); $stmt->execute(); $stmt->close(); }
    }
}


function log_qr_scan_event(mysqli $conn, int $qrId, int $userId, string $action = 'Lookup', string $notes = ''): void {
    if ($qrId <= 0) return;
    log_qr_scan($conn, $qrId, $userId, $action, $notes);
}

function log_qr_rejection(mysqli $conn, int $qrId, int $userId, string $action = 'Attendance Rejected', string $notes = ''): void {
    if ($qrId <= 0 || !table_exists($conn, 'qr_scan_logs')) return;
    $stmt = $conn->prepare("INSERT INTO qr_scan_logs (qr_id, scanned_by, scanned_at, scan_location, device_info, action_taken, notes) VALUES (?, ?, NOW(), ?, ?, ?, ?)");
    if ($stmt) {
        $location = $_SERVER['REMOTE_ADDR'] ?? null;
        $device = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
        $stmt->bind_param('iissss', $qrId, $userId, $location, $device, $action, $notes);
        $stmt->execute();
        $stmt->close();
    }
}

function check_event_household_eligibility(mysqli $conn, int $eventId, int $householdId): array {
    if ($eventId <= 0 || $householdId <= 0) {
        return ['ok' => false, 'message' => 'Missing event or household.'];
    }

    $eventTargetFilterSelect = column_exists($conn, 'events', 'target_profile_filter') ? 'target_profile_filter' : "'' AS target_profile_filter";
    $eventTargetLabelSelect = column_exists($conn, 'events', 'target_profile_label') ? 'target_profile_label' : "'' AS target_profile_label";
    $eventTypeSelect = column_exists($conn, 'events', 'event_type') ? 'event_type' : "'General' AS event_type";
    $eventRow = fetch_one($conn, "SELECT event_id,event_name,event_status,event_date,barangay_id," . $eventTargetFilterSelect . "," . $eventTargetLabelSelect . "," . $eventTypeSelect . " FROM events WHERE event_id=" . (int)$eventId . " LIMIT 1");
    if (!$eventRow) {
        return ['ok' => false, 'message' => 'Event not found.'];
    }
    if (($eventRow['event_status'] ?? '') === 'Cancelled') {
        return ['ok' => false, 'message' => 'This event is cancelled. Attendance was not saved.'];
    }

    $householdRow = fetch_one($conn, "SELECT household_id, household_head_name, barangay_id FROM households WHERE household_id=" . (int)$householdId . " LIMIT 1");
    if (!$householdRow) {
        return ['ok' => false, 'message' => 'Household not found.'];
    }

    $eventBarangayId = (int)($eventRow['barangay_id'] ?? 0);
    $householdBarangayId = (int)($householdRow['barangay_id'] ?? 0);
    if ($eventBarangayId > 0 && $householdBarangayId !== $eventBarangayId) {
        $eventBarangayName = (string)scalar($conn, "SELECT barangay_name FROM barangays WHERE barangay_id=" . $eventBarangayId . " LIMIT 1", 'selected barangay');
        return ['ok' => false, 'message' => 'This household is not invited for this event. Only households from ' . $eventBarangayName . ' can be saved.', 'event' => $eventRow, 'household' => $householdRow];
    }

    $targetProfileFilter = trim((string)($eventRow['target_profile_filter'] ?? ''));
    if (function_exists('household_matches_event_program_targets') && !household_matches_event_program_targets($conn, $householdId, $eventId)) {
        $targetPrograms = function_exists('golden_event_target_program_label') ? golden_event_target_program_label($conn, $eventId) : 'selected programs';
        $targetHint = function_exists('golden_event_household_status_hint') ? golden_event_household_status_hint($conn, $eventId) : ('eligible households under: ' . $targetPrograms);
        $eventType = strtolower(trim((string)($eventRow['event_type'] ?? 'General')));
        $programReason = $eventType === 'general'
            ? 'This household is not eligible for this event yet.'
            : 'This household is not eligible for this ' . ucfirst($eventType) . ' event yet.';
        return ['ok' => false, 'message' => $programReason . ' Allowed attendance is limited to ' . $targetHint . '.', 'event' => $eventRow, 'household' => $householdRow];
    }

    if ($targetProfileFilter !== '') {
        $profileMatchSql = "SELECT COUNT(*) FROM family_members fm WHERE fm.household_id=" . (int)$householdId . " AND fm.is_active=1 AND (";
        switch ($targetProfileFilter) {
            case 'farmers':
                $profileMatchSql .= "UPPER(TRIM(COALESCE(fm.occupation,'')))='FARMER' OR COALESCE(fm.member_tags,'') LIKE '%Farmer%'";
                break;
            case 'pwd':
                $profileMatchSql .= "COALESCE(fm.disability,'') <> '' OR COALESCE(fm.member_tags,'') LIKE '%PWD%'";
                break;
            case 'senior_citizen':
                $profileMatchSql .= "COALESCE(fm.age,0) >= 60 OR COALESCE(fm.member_tags,'') LIKE '%Senior%'";
                break;
            case 'solo_parent':
                $profileMatchSql .= "COALESCE(fm.member_tags,'') LIKE '%Solo Parent%'";
                break;
            case 'ofw':
                $profileMatchSql .= "COALESCE(fm.member_tags,'') LIKE '%OFW%' OR UPPER(TRIM(COALESCE(fm.occupation,'')))='OFW'";
                break;
            case 'unemployed':
                $profileMatchSql .= "UPPER(TRIM(COALESCE(fm.employment_status,'')))='UNEMPLOYED' OR COALESCE(fm.member_tags,'') LIKE '%Unemployed%'";
                break;
            case 'pregnant':
                $profileMatchSql .= "COALESCE(fm.member_tags,'') LIKE '%Pregnant%'";
                break;
            case 'breastfeeding':
                $profileMatchSql .= "COALESCE(fm.member_tags,'') LIKE '%Breastfeeding%'";
                break;
            case 'youth':
                $profileMatchSql .= "COALESCE(fm.age,0) BETWEEN 15 AND 30 OR COALESCE(fm.member_tags,'') LIKE '%Youth%'";
                break;
            default:
                $profileMatchSql .= "1=1";
                break;
        }
        $profileMatchSql .= ") LIMIT 1";
        $eligibleCount = (int)scalar($conn, $profileMatchSql, 0);
        if ($eligibleCount <= 0) {
            $targetLabel = (string)($eventRow['target_profile_label'] ?? $targetProfileFilter);
            return ['ok' => false, 'message' => 'This household does not match the invited audience for this event: ' . $targetLabel . '.', 'event' => $eventRow, 'household' => $householdRow];
        }
    }

    return ['ok' => true, 'event' => $eventRow, 'household' => $householdRow];
}

function save_event_attendance(mysqli $conn, int $eventId, int $householdId, int $userId, string $status = "Present", ?string $timeIn = null, ?string $timeOut = null, string $method = "QR Scan", ?string $notes = null): array {
    if ($eventId <= 0 || $householdId <= 0 || $userId <= 0) {
        return ['ok'=>false, 'message'=>'Missing event, household, or user.'];
    }
    if (!table_exists($conn, 'event_attendance')) {
        return ['ok'=>false, 'message'=>'Attendance table is missing.'];
    }

    $eligibility = check_event_household_eligibility($conn, $eventId, $householdId);
    if (!$eligibility['ok']) {
        return ['ok'=>false, 'message'=>(string)($eligibility['message'] ?? 'This household is not eligible for this event.')];
    }
    $eventRow = (array)($eligibility['event'] ?? []);
    if (table_exists($conn, 'events') && column_exists($conn, 'events', 'attendance_closed_at')) {
        $closedAt = (string)scalar($conn, "SELECT COALESCE(attendance_closed_at,'') FROM events WHERE event_id=" . (int)$eventId . " LIMIT 1", '');
        if ($closedAt !== '') {
            return ['ok'=>false, 'message'=>'Attendance is already closed for this event.'];
        }
    }
    $targetProfileFilter = trim((string)($eventRow['target_profile_filter'] ?? ''));

    if ($targetProfileFilter !== '') {
        $profileMatchSql = "SELECT COUNT(*) FROM family_members fm WHERE fm.household_id=" . (int)$householdId . " AND fm.is_active=1 AND (";
        switch ($targetProfileFilter) {
            case 'farmers':
                $profileMatchSql .= "UPPER(TRIM(COALESCE(fm.occupation,'')))='FARMER' OR COALESCE(fm.member_tags,'') LIKE '%Farmer%'";
                break;
            case 'pwd':
                $profileMatchSql .= "COALESCE(fm.disability,'') <> '' OR COALESCE(fm.member_tags,'') LIKE '%PWD%'";
                break;
            case 'senior_citizen':
                $profileMatchSql .= "COALESCE(fm.age,0) >= 60 OR COALESCE(fm.member_tags,'') LIKE '%Senior%'";
                break;
            case 'solo_parent':
                $profileMatchSql .= "COALESCE(fm.member_tags,'') LIKE '%Solo Parent%'";
                break;
            case 'ofw':
                $profileMatchSql .= "COALESCE(fm.member_tags,'') LIKE '%OFW%' OR UPPER(TRIM(COALESCE(fm.occupation,'')))='OFW'";
                break;
            case 'unemployed':
                $profileMatchSql .= "UPPER(TRIM(COALESCE(fm.employment_status,'')))='UNEMPLOYED' OR COALESCE(fm.member_tags,'') LIKE '%Unemployed%'";
                break;
            case 'pregnant':
                $profileMatchSql .= "COALESCE(fm.member_tags,'') LIKE '%Pregnant%'";
                break;
            case 'breastfeeding':
                $profileMatchSql .= "COALESCE(fm.member_tags,'') LIKE '%Breastfeeding%'";
                break;
            case 'youth':
                $profileMatchSql .= "COALESCE(fm.age,0) BETWEEN 15 AND 30 OR COALESCE(fm.member_tags,'') LIKE '%Youth%'";
                break;
            default:
                $profileMatchSql .= "1=1";
                break;
        }
        $profileMatchSql .= ") LIMIT 1";
        $eligibleCount = (int)scalar($conn, $profileMatchSql, 0);
        if ($eligibleCount <= 0) {
            $targetLabel = (string)($eventRow['target_profile_label'] ?? $targetProfileFilter);
            return ['ok'=>false, 'message'=>'This household does not match the invited audience for this event: ' . $targetLabel . '.'];
        }
    }

    ensure_refresh_household_qualification_procedure($conn);
    $timeIn = $timeIn ?: date('Y-m-d H:i:s');
    $existing = fetch_one($conn, "SELECT attendance_id FROM event_attendance WHERE event_id=" . (int)$eventId . " AND household_id=" . (int)$householdId . " LIMIT 1");

    if ($existing && column_exists($conn, 'event_attendance', 'attendance_id')) {
        $parts = [];
        $types = '';
        $params = [];
        foreach ([
            ['recorded_by', 'i', $userId],
            ['attendance_status', 's', $status],
            ['time_in', 's', $timeIn],
            ['time_out', 's', $timeOut],
            ['method', 's', $method],
            ['notes', 's', $notes],
        ] as [$col, $type, $value]) {
            if (column_exists($conn, 'event_attendance', $col)) {
                $parts[] = "{$col}=?";
                $types .= $type;
                $params[] = $value;
            }
        }
        if (column_exists($conn, 'event_attendance', 'updated_at')) {
            $parts[] = 'updated_at=NOW()';
        }
        if (!$parts) {
            return ['ok'=>false, 'message'=>'Attendance table has no editable columns.'];
        }
        $types .= 'i';
        $params[] = (int)$existing['attendance_id'];
        $stmt = $conn->prepare("UPDATE event_attendance SET " . implode(', ', $parts) . " WHERE attendance_id=?");
        if (!$stmt) {
            return ['ok'=>false, 'message'=>'Could not prepare attendance update: ' . $conn->error];
        }
        $stmt->bind_param($types, ...$params);
        $saved = $stmt->execute();
        $sqlError = $stmt->error ?: $conn->error;
        $stmt->close();
        if (!$saved) {
            return ['ok'=>false, 'message'=>'Could not update attendance: ' . $sqlError];
        }
        $actionMessage = 'Attendance updated successfully.';
    } else {
        $cols = [];
        $placeholders = [];
        $types = '';
        $params = [];
        foreach ([
            ['event_id', 'i', $eventId],
            ['household_id', 'i', $householdId],
            ['recorded_by', 'i', $userId],
            ['attendance_status', 's', $status],
            ['time_in', 's', $timeIn],
            ['time_out', 's', $timeOut],
            ['method', 's', $method],
            ['notes', 's', $notes],
        ] as [$col, $type, $value]) {
            if (column_exists($conn, 'event_attendance', $col)) {
                $cols[] = $col;
                $placeholders[] = '?';
                $types .= $type;
                $params[] = $value;
            }
        }
        if (!in_array('event_id', $cols, true) || !in_array('household_id', $cols, true)) {
            return ['ok'=>false, 'message'=>'Attendance table structure is incomplete.'];
        }
        $stmt = $conn->prepare("INSERT INTO event_attendance (" . implode(', ', $cols) . ") VALUES (" . implode(', ', $placeholders) . ")");
        if (!$stmt) {
            return ['ok'=>false, 'message'=>'Could not prepare attendance save: ' . $conn->error];
        }
        $stmt->bind_param($types, ...$params);
        $saved = $stmt->execute();
        $sqlError = $stmt->error ?: $conn->error;
        $stmt->close();
        if (!$saved) {
            return ['ok'=>false, 'message'=>'Could not save attendance: ' . $sqlError];
        }
        $actionMessage = 'Attendance saved successfully.';
    }

    if (function_exists('golden_sync_event_program_progress_from_attendance')) {
        golden_sync_event_program_progress_from_attendance($conn, (int)$eventId, (int)$householdId, (string)$status, (int)$userId);
    } elseif (function_exists('golden_sync_orientation_from_attendance')) {
        golden_sync_orientation_from_attendance($conn, (int)$eventId, (int)$householdId, (string)$status, (int)$userId);
    }
    sync_household_auto_fields($conn, $householdId);
    refresh_household_qualification_php($conn, $householdId);
    create_notification($conn, 'Attendance captured', 'Attendance recorded for household #' . $householdId . ' in ' . ($eventRow['event_name'] ?? 'event') . ' via ' . $method . '.', 'Low', $userId, $householdId, null, 'Upcoming Event');
    app_log($conn, $userId, 'ATTENDANCE', 'UPSERT', null, 'Attendance recorded');
    $qualification = fetch_one($conn, "SELECT score, qualification_status FROM household_qualification WHERE household_id=" . (int)$householdId . " LIMIT 1");
    $attendanceCount = (int)scalar($conn, "SELECT COUNT(*) FROM event_attendance WHERE household_id=" . (int)$householdId . " AND attendance_status IN ('Present','Late')", 0);
    return [
        'ok'=>true,
        'message'=>$actionMessage,
        'event'=>$eventRow,
        'qualification'=>$qualification ?: ['score'=>0, 'qualification_status'=>'For Validation'],
        'attendance_count'=>$attendanceCount,
    ];
}

function household_pending_actions(mysqli $conn, int $householdId): array {
    $actions = [];
    if ($householdId <= 0) return $actions;
    $recordStatus = function_exists('household_record_status') ? household_record_status($conn, $householdId) : 'active';
    if ($recordStatus === 'archived') {
        $actions[] = 'Archived family';
    } elseif ($recordStatus === 'deleted') {
        $actions[] = 'Deleted family';
        return $actions;
    }
    if ((int)scalar($conn, "SELECT COUNT(*) FROM interviews WHERE household_id={$householdId} AND status='Completed'", 0) === 0) $actions[] = 'Needs interview';
    if ((int)scalar($conn, "SELECT COUNT(*) FROM qr_codes WHERE household_id={$householdId} AND qr_type='HOUSEHOLD' AND is_active=1", 0) === 0) $actions[] = 'Needs QR';
    if ((int)scalar($conn, "SELECT COUNT(*) FROM crops WHERE household_id={$householdId} AND crop_status='Active'", 0) === 0) $actions[] = 'Needs crop profile';
    if ((int)scalar($conn, "SELECT COUNT(*) FROM monitoring_visits WHERE household_id={$householdId} AND monitoring_date >= DATE_SUB(CURDATE(), INTERVAL 180 DAY)", 0) === 0) $actions[] = 'Needs monitoring';
    if ((int)scalar($conn, "SELECT COUNT(*) FROM event_attendance WHERE household_id={$householdId} AND created_at >= DATE_SUB(NOW(), INTERVAL 365 DAY)", 0) === 0) $actions[] = 'Needs event attendance';
    if (table_exists($conn, 'family_submissions') && (int)scalar($conn, "SELECT COUNT(*) FROM family_submissions WHERE household_id={$householdId} AND status IN ('submitted','pending','needs_revision')", 0) > 0) $actions[] = 'Pending family submission';
    return $actions;
}

function household_pending_actions_html(mysqli $conn, int $householdId): string {
    $actions = household_pending_actions($conn, $householdId);
    if (!$actions) return '<span class="app-badge app-badge-emerald">Operationally Ready</span>';
    $html = '';
    foreach ($actions as $action) $html .= '<span class="app-badge app-badge-amber">' . e($action) . '</span> ';
    return trim($html);
}

function sync_all_event_statuses(mysqli $conn): void {
    if (!table_exists($conn, 'events')) return;
    $conn->query("UPDATE events SET event_status='Scheduled' WHERE event_status <> 'Cancelled' AND event_date > CURDATE()");
    $conn->query("UPDATE events SET event_status='Scheduled' WHERE event_status <> 'Cancelled' AND event_date = CURDATE() AND start_time > CURTIME()");
    $conn->query("UPDATE events SET event_status='Ongoing' WHERE event_status <> 'Cancelled' AND event_date = CURDATE() AND start_time <= CURTIME() AND end_time >= CURTIME()");
    $conn->query("UPDATE events SET event_status='Completed' WHERE event_status <> 'Cancelled' AND (event_date < CURDATE() OR (event_date = CURDATE() AND end_time < CURTIME()))");
}

function event_attendance_summary(mysqli $conn, int $eventId): array {
    if ($eventId <= 0) return ['total'=>0,'present'=>0,'late'=>0,'absent'=>0];
    return [
        'total' => (int)scalar($conn, "SELECT COUNT(*) FROM event_attendance WHERE event_id={$eventId}", 0),
        'present' => (int)scalar($conn, "SELECT COUNT(*) FROM event_attendance WHERE event_id={$eventId} AND attendance_status='Present'", 0),
        'late' => (int)scalar($conn, "SELECT COUNT(*) FROM event_attendance WHERE event_id={$eventId} AND attendance_status='Late'", 0),
        'absent' => (int)scalar($conn, "SELECT COUNT(*) FROM event_attendance WHERE event_id={$eventId} AND attendance_status='Absent'", 0),
    ];
}

function log_qr_scan(mysqli $conn, int $qrId, int $userId, string $action = 'Lookup', string $notes = ''): void {
    if ($qrId <= 0 || !table_exists($conn, 'qr_codes')) return;
    $stmt = $conn->prepare("UPDATE qr_codes SET total_scans = total_scans + 1, last_scanned_at = NOW() WHERE qr_id = ?");
    if ($stmt) { $stmt->bind_param('i', $qrId); $stmt->execute(); $stmt->close(); }
    if (table_exists($conn, 'qr_scan_logs')) {
        $stmt = $conn->prepare("INSERT INTO qr_scan_logs (qr_id, scanned_by, scanned_at, scan_location, device_info, action_taken, notes) VALUES (?, ?, NOW(), ?, ?, ?, ?)");
        if ($stmt) {
            $location = $_SERVER['REMOTE_ADDR'] ?? null;
            $device = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
            $stmt->bind_param('iissss', $qrId, $userId, $location, $device, $action, $notes);
            $stmt->execute();
            $stmt->close();
        }
    }
}

function backfill_all_qr_assets(mysqli $conn, int $userId = 0): array {
    $households = fetch_all_assoc($conn, "SELECT household_id FROM households ORDER BY household_id");
    $crops = fetch_all_assoc($conn, "SELECT crop_id, household_id FROM crops ORDER BY crop_id");
    $hCount = 0; $cCount = 0;
    foreach ($households as $h) { ensure_household_assets($conn, (int)$h['household_id'], $userId); sync_household_auto_fields($conn, (int)$h['household_id']); refresh_household_qualification_php($conn, (int)$h['household_id']); $hCount++; }
    foreach ($crops as $c) { ensure_crop_assets($conn, (int)$c['crop_id'], (int)$c['household_id'], $userId); $cCount++; }
    return ['households' => $hCount, 'crops' => $cCount];
}

if (!function_exists('ensure_decision_support_schema')) {
function ensure_decision_support_schema(mysqli $conn): void {
    static $done = false;
    if ($done) return;
    $done = true;
    ensure_family_upgrade_schema($conn);

    if (table_exists($conn, 'family_members') && !column_exists($conn, 'family_members', 'member_tags')) {
        @$conn->query("ALTER TABLE family_members ADD COLUMN member_tags TEXT NULL AFTER notes");
    }

    if (!table_exists($conn, 'assistance_records')) {
        @$conn->query("CREATE TABLE assistance_records (
            assistance_id BIGINT AUTO_INCREMENT PRIMARY KEY,
            household_id BIGINT NOT NULL,
            assistance_date DATE NOT NULL,
            assistance_type VARCHAR(100) NOT NULL,
            assistance_status VARCHAR(50) NOT NULL DEFAULT 'Planned',
            provider_name VARCHAR(150) NULL,
            amount_value DECIMAL(12,2) NOT NULL DEFAULT 0,
            description TEXT NULL,
            outcome_notes TEXT NULL,
            next_followup_date DATE NULL,
            evidence_file_path VARCHAR(255) NULL,
            created_by BIGINT NULL,
            updated_by BIGINT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    } elseif (!column_exists($conn, 'assistance_records', 'evidence_file_path')) {
        @$conn->query("ALTER TABLE assistance_records ADD COLUMN evidence_file_path VARCHAR(255) NULL AFTER next_followup_date");
    }

    if (!table_exists($conn, 'household_documents')) {
        @$conn->query("CREATE TABLE household_documents (
            document_id BIGINT AUTO_INCREMENT PRIMARY KEY,
            household_id BIGINT NOT NULL,
            document_type VARCHAR(100) NOT NULL,
            title VARCHAR(150) NOT NULL,
            file_path VARCHAR(255) NOT NULL,
            notes TEXT NULL,
            uploaded_by BIGINT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    }

    @mkdir(app_path('public/uploads/documents'), 0777, true);
}

function member_tag_options(): array {
    return ['Farmer','Student','Senior Citizen','PWD','Solo Parent','Pregnant','Breastfeeding Mother','Unemployed','OFW','Child','Youth'];
}

function normalize_member_tags($tags): string {
    if (is_string($tags)) {
        $tags = array_filter(array_map('trim', explode(',', $tags)));
    } elseif (!is_array($tags)) {
        $tags = [];
    }
    $allowed = member_tag_options();
    $clean = [];
    foreach ($tags as $tag) {
        if (in_array($tag, $allowed, true) && !in_array($tag, $clean, true)) $clean[] = $tag;
    }
    return implode(', ', $clean);
}

function member_tags_array(?string $tags): array {
    $tags = trim((string)$tags);
    if ($tags === '') return [];
    return array_values(array_filter(array_map('trim', explode(',', $tags))));
}

function member_tags_badges(?string $tags): string {
    $parts = member_tags_array($tags);
    if (!$parts) return '<span class="text-xs text-slate-400">No tags</span>';
    $html = '';
    foreach ($parts as $tag) {
        $html .= '<span class="app-badge app-badge-sky">'.e($tag).'</span> ';
    }
    return trim($html);
}

function assistance_type_options(): array {
    return ['Seedlings','Fertilizer','Training','Cash Assistance','Equipment','Farm Inputs','Monitoring Support','Livelihood','Documentation','Other'];
}

function assistance_status_options(): array {
    return ['Planned','Scheduled','Delivered','Completed','Cancelled'];
}

function save_assistance_record(mysqli $conn, array $payload, int $userId = 0): int {
    ensure_decision_support_schema($conn);
    $householdId = (int)($payload['household_id'] ?? 0);
    $date = trim((string)($payload['assistance_date'] ?? '')) ?: date('Y-m-d');
    $type = trim((string)($payload['assistance_type'] ?? 'Other')) ?: 'Other';
    if ($householdId <= 0 || $type === '') return 0;
    $status = trim((string)($payload['assistance_status'] ?? 'Planned')) ?: 'Planned';
    $provider = trim((string)($payload['provider_name'] ?? '')) ?: null;
    $amount = (float)($payload['amount_value'] ?? 0);
    $desc = trim((string)($payload['description'] ?? '')) ?: null;
    $outcome = trim((string)($payload['outcome_notes'] ?? '')) ?: null;
    $follow = trim((string)($payload['next_followup_date'] ?? '')) ?: null;
    $stmt = $conn->prepare("INSERT INTO assistance_records (household_id, assistance_date, assistance_type, assistance_status, provider_name, amount_value, description, outcome_notes, next_followup_date, created_by, updated_by) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
    if (!$stmt) return 0;
    $stmt->bind_param('issssdsssii', $householdId, $date, $type, $status, $provider, $amount, $desc, $outcome, $follow, $userId, $userId);
    $stmt->execute();
    $id = (int)$stmt->insert_id;
    $stmt->close();
    if ($id && $userId) app_log($conn, $userId, 'ASSISTANCE', 'CREATE', $id, 'Assistance added for household #' . $householdId);
    return $id;
}

function family_timeline(mysqli $conn, int $householdId): array {
    ensure_decision_support_schema($conn);
    if ($householdId <= 0) return [];
    $items = [];
    $push = function($date, $title, $meta, $icon='history', $tone='slate') use (&$items) {
        if (!$date) return;
        $items[] = ['date' => $date, 'title' => $title, 'meta' => $meta, 'icon' => $icon, 'tone' => $tone];
    };
    $house = fetch_one($conn, "SELECT household_head_name, created_at FROM households WHERE household_id=".(int)$householdId." LIMIT 1");
    if ($house) $push($house['created_at'], 'Family profile created', ($house['household_head_name'] ?: 'Unnamed family') . ' was profiled in the system.', 'house', 'emerald');
    foreach (fetch_all_assoc($conn, "SELECT full_name, relationship_to_head, created_at FROM family_members WHERE household_id=".(int)$householdId." ORDER BY created_at DESC LIMIT 20") as $row) {
        $push($row['created_at'], 'Family member encoded', ($row['full_name'] ?: 'Member') . ' · ' . ($row['relationship_to_head'] ?: 'Member'), 'users', 'sky');
    }
    foreach (fetch_all_assoc($conn, "SELECT interview_date, compliance_status, remarks, created_at FROM interviews WHERE household_id=".(int)$householdId." ORDER BY interview_id DESC LIMIT 20") as $row) {
        $push($row['created_at'] ?: $row['interview_date'], 'Interview completed', ($row['compliance_status'] ?: 'For Validation') . ($row['remarks'] ? ' · ' . $row['remarks'] : ''), 'file-text', 'amber');
    }
    foreach (fetch_all_assoc($conn, "SELECT monitoring_date, crop_condition, fruiting_status, notes, created_at FROM monitoring_visits WHERE household_id=".(int)$householdId." ORDER BY monitoring_id DESC LIMIT 20") as $row) {
        $push($row['created_at'] ?: $row['monitoring_date'], 'Monitoring visit', ($row['crop_condition'] ?: 'For Validation') . ' · ' . ($row['fruiting_status'] ?: 'Unknown'), 'clipboard-list', 'emerald');
    }
    foreach (fetch_all_assoc($conn, "SELECT e.event_name, e.event_date, a.attendance_status, a.created_at FROM event_attendance a JOIN events e ON e.event_id=a.event_id WHERE a.household_id=".(int)$householdId." ORDER BY a.attendance_id DESC LIMIT 20") as $row) {
        $push($row['created_at'] ?: $row['event_date'], 'Event attendance', ($row['event_name'] ?: 'Event') . ' · ' . ($row['attendance_status'] ?: 'Present'), 'calendar-days', 'sky');
    }
    if (table_exists($conn, 'assistance_records')) {
        foreach (fetch_all_assoc($conn, "SELECT assistance_date, assistance_type, assistance_status, provider_name, created_at FROM assistance_records WHERE household_id=".(int)$householdId." ORDER BY assistance_id DESC LIMIT 20") as $row) {
            $meta = ($row['assistance_type'] ?: 'Assistance') . ' · ' . ($row['assistance_status'] ?: 'Planned');
            if (!empty($row['provider_name'])) $meta .= ' · ' . $row['provider_name'];
            $push($row['created_at'] ?: $row['assistance_date'], 'Assistance record', $meta, 'hand-heart', 'amber');
        }
    }
    foreach (fetch_all_assoc($conn, "SELECT q.last_scanned_at, q.total_scans, q.qr_reference FROM qr_codes q WHERE q.household_id=".(int)$householdId." AND q.last_scanned_at IS NOT NULL ORDER BY q.last_scanned_at DESC LIMIT 10") as $row) {
        $push($row['last_scanned_at'], 'QR scanned', ($row['qr_reference'] ?: 'Family QR') . ' · Total scans: ' . (int)$row['total_scans'], 'scan-qr-code', 'slate');
    }
    usort($items, fn($a,$b) => strcmp((string)$b['date'], (string)$a['date']));
    return $items;
}

function household_case_summary(mysqli $conn, int $householdId): array {
    $snapshot = get_household_snapshot($conn, $householdId);
    if (!$snapshot) return [];
    $members = fetch_all_assoc($conn, "SELECT birthdate, member_tags, occupation FROM family_members WHERE household_id=".(int)$householdId." AND is_active=1");
    $summary = [
        'members' => count($members),
        'dependents' => 0,
        'seniors' => 0,
        'pwd' => 0,
        'students' => 0,
        'farmers' => 0,
        'unemployed' => 0,
    ];
    foreach ($members as $member) {
        $age = calculate_age_from_birthdate($member['birthdate'] ?? null);
        $tags = member_tags_array($member['member_tags'] ?? null);
        $age = calculate_age_from_birthdate($member['birthdate'] ?? null);
        $tags = member_tags_array($member['member_tags'] ?? null);
        if ($age !== null && $age < 18) $summary['dependents']++;
        if ($age !== null && $age >= 60) $summary['seniors']++;
        if (in_array('PWD', $tags, true)) $summary['pwd']++;
        if (in_array('Student', $tags, true)) $summary['students']++;
        if (in_array('Farmer', $tags, true) || strcasecmp((string)($member['occupation'] ?? ''), 'Farmer') === 0) $summary['farmers']++;
        if (in_array('Unemployed', $tags, true) || strcasecmp((string)($member['occupation'] ?? ''), 'Unemployed') === 0) $summary['unemployed']++;
    }
    $summary['snapshot'] = $snapshot;
    $summary['latest_assistance'] = table_exists($conn, 'assistance_records') ? fetch_one($conn, "SELECT assistance_type, assistance_status, assistance_date FROM assistance_records WHERE household_id=".(int)$householdId." ORDER BY assistance_id DESC LIMIT 1") : null;
    return $summary;
}

function data_quality_issues(mysqli $conn): array {
    ensure_decision_support_schema($conn);
    $issues = [];
    foreach (fetch_all_assoc($conn, "SELECT household_id, household_head_name, household_code FROM households WHERE (contact_number IS NULL OR contact_number='') OR (full_address IS NULL OR full_address='')") as $row) {
        $issues[] = ['household_id'=>(int)$row['household_id'], 'household'=>($row['household_head_name'] ?: 'Unnamed') . ' · ' . ($row['household_code'] ?: '-'), 'issue'=>'Missing contact or address', 'severity'=>'Medium'];
    }
    foreach (fetch_all_assoc($conn, "SELECT h.household_id,h.household_head_name,h.household_code FROM households h LEFT JOIN family_members fm ON fm.household_id=h.household_id AND fm.is_household_head=1 WHERE fm.member_id IS NULL") as $row) {
        $issues[] = ['household_id'=>(int)$row['household_id'], 'household'=>($row['household_head_name'] ?: 'Unnamed') . ' · ' . ($row['household_code'] ?: '-'), 'issue'=>'No head member linked', 'severity'=>'High'];
    }
    foreach (fetch_all_assoc($conn, "SELECT household_id, full_name FROM family_members WHERE is_active=1 AND (member_photo_path IS NULL OR member_photo_path='') LIMIT 50") as $row) {
        $issues[] = ['household_id'=>(int)$row['household_id'], 'household'=>$row['full_name'], 'issue'=>'Member has no photo', 'severity'=>'Low'];
    }
    foreach (fetch_all_assoc($conn, "SELECT household_id, full_name FROM family_members WHERE is_active=1 AND birthdate IS NULL LIMIT 50") as $row) {
        $issues[] = ['household_id'=>(int)$row['household_id'], 'household'=>$row['full_name'], 'issue'=>'Member missing birthdate', 'severity'=>'Medium'];
    }
    foreach (fetch_all_assoc($conn, "SELECT household_id, full_name FROM family_members WHERE is_active=1 AND (sex IS NULL OR sex='') LIMIT 50") as $row) {
        $issues[] = ['household_id'=>(int)$row['household_id'], 'household'=>$row['full_name'], 'issue'=>'Member missing sex', 'severity'=>'Medium'];
    }
    foreach (fetch_all_assoc($conn, "SELECT household_id, full_name FROM family_members WHERE is_active=1 AND (relationship_to_head IS NULL OR relationship_to_head='') LIMIT 50") as $row) {
        $issues[] = ['household_id'=>(int)$row['household_id'], 'household'=>$row['full_name'], 'issue'=>'Missing relationship to head', 'severity'=>'Medium'];
    }
    foreach (fetch_all_assoc($conn, "SELECT household_id, full_name, age, birthdate FROM family_members WHERE is_active=1 AND birthdate IS NOT NULL AND age IS NOT NULL AND ABS(TIMESTAMPDIFF(YEAR, birthdate, CURDATE()) - age) >= 3 LIMIT 50") as $row) {
        $issues[] = ['household_id'=>(int)$row['household_id'], 'household'=>$row['full_name'], 'issue'=>'Possible age and birthdate mismatch', 'severity'=>'Medium'];
    }
    foreach (fetch_all_assoc($conn, "SELECT household_id, household_head_name, household_code FROM households WHERE household_id NOT IN (SELECT DISTINCT household_id FROM interviews)") as $row) {
        $issues[] = ['household_id'=>(int)$row['household_id'], 'household'=>($row['household_head_name'] ?: 'Unnamed') . ' · ' . ($row['household_code'] ?: '-'), 'issue'=>'No completed interview yet', 'severity'=>'Medium'];
    }
    foreach (fetch_all_assoc($conn, "SELECT household_id, household_head_name, household_code FROM households WHERE household_id NOT IN (SELECT DISTINCT household_id FROM qr_codes WHERE qr_type='HOUSEHOLD' AND is_active=1)") as $row) {
        $issues[] = ['household_id'=>(int)$row['household_id'], 'household'=>($row['household_head_name'] ?: 'Unnamed') . ' · ' . ($row['household_code'] ?: '-'), 'issue'=>'No active family QR', 'severity'=>'High'];
    }
    foreach (fetch_all_assoc($conn, "SELECT household_id, full_name, birthdate FROM family_members WHERE is_active=1 GROUP BY household_id, full_name, birthdate HAVING COUNT(*) > 1 LIMIT 50") as $row) {
        $issues[] = ['household_id'=>(int)$row['household_id'], 'household'=>$row['full_name'], 'issue'=>'Possible duplicate person inside the same household', 'severity'=>'High'];
    }
    usort($issues, static function($a,$b) {
        $rank = ['High'=>3,'Medium'=>2,'Low'=>1];
        return ($rank[$b['severity']] ?? 0) <=> ($rank[$a['severity']] ?? 0);
    });
    return $issues;
}

function qualification_reason_tags(?string $explanation): array {
    $explanation = trim((string)$explanation);
    if ($explanation === '') return [];
    if (preg_match('/Reasons:\s*([^|]+)/i', $explanation, $m)) {
        return array_values(array_filter(array_map('trim', explode(';', trim($m[1])))));
    }
    return [];
}

}