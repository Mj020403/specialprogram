<?php

function ensure_golden_household_schema(mysqli $conn): void {
    static $done = false;
    if ($done) return;
    $done = true;

    $conn->query("CREATE TABLE IF NOT EXISTS special_programs (
        program_id INT AUTO_INCREMENT PRIMARY KEY,
        program_name VARCHAR(150) NOT NULL,
        description TEXT NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL DEFAULT NULL,
        UNIQUE KEY uniq_special_program_name (program_name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $conn->query("CREATE TABLE IF NOT EXISTS special_program_items (
        item_id INT AUTO_INCREMENT PRIMARY KEY,
        program_id INT NOT NULL,
        item_name VARCHAR(150) NOT NULL,
        description TEXT NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_program_item (program_id, item_name),
        KEY idx_program_items_program (program_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $conn->query("CREATE TABLE IF NOT EXISTS household_special_programs (
        application_id BIGINT AUTO_INCREMENT PRIMARY KEY,
        household_id BIGINT NOT NULL,
        program_id INT NOT NULL,
        item_id INT NULL,
        application_status VARCHAR(30) NOT NULL DEFAULT 'Pending Validation',
        qualification_result VARCHAR(30) NULL DEFAULT NULL,
        target_notes TEXT NULL,
        validation_notes TEXT NULL,
        date_applied DATE NULL,
        date_reviewed DATE NULL,
        applied_by INT NULL,
        reviewed_by INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL DEFAULT NULL,
        KEY idx_hsp_household (household_id),
        KEY idx_hsp_status (application_status),
        KEY idx_hsp_program (program_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    if (table_exists($conn, 'household_special_programs')) {
        $schemaAdds = [
            ['applicant_contact', "ALTER TABLE household_special_programs ADD COLUMN applicant_contact VARCHAR(80) NULL AFTER item_id"],
            ['land_location', "ALTER TABLE household_special_programs ADD COLUMN land_location VARCHAR(180) NULL AFTER applicant_contact"],
            ['land_area_text', "ALTER TABLE household_special_programs ADD COLUMN land_area_text VARCHAR(80) NULL AFTER land_location"],
            ['ownership_type', "ALTER TABLE household_special_programs ADD COLUMN ownership_type VARCHAR(80) NULL AFTER land_area_text"],
            ['orientation_status', "ALTER TABLE household_special_programs ADD COLUMN orientation_status VARCHAR(40) NULL AFTER ownership_type"],
            ['intake_notes', "ALTER TABLE household_special_programs ADD COLUMN intake_notes TEXT NULL AFTER target_notes"],
            ['scheduled_validation_date', "ALTER TABLE household_special_programs ADD COLUMN scheduled_validation_date DATE NULL AFTER intake_notes"],
            ['first_validation_result', "ALTER TABLE household_special_programs ADD COLUMN first_validation_result VARCHAR(30) NULL AFTER scheduled_validation_date"],
            ['orientation_event_id', "ALTER TABLE household_special_programs ADD COLUMN orientation_event_id BIGINT NULL AFTER first_validation_result"],
            ['orientation_attendance_status', "ALTER TABLE household_special_programs ADD COLUMN orientation_attendance_status VARCHAR(20) NULL AFTER orientation_event_id"],
            ['orientation_attended_at', "ALTER TABLE household_special_programs ADD COLUMN orientation_attended_at DATETIME NULL AFTER orientation_attendance_status"],
            ['final_validation_date', "ALTER TABLE household_special_programs ADD COLUMN final_validation_date DATE NULL AFTER orientation_attended_at"],
            ['final_validation_result', "ALTER TABLE household_special_programs ADD COLUMN final_validation_result VARCHAR(30) NULL AFTER final_validation_date"],
            ['approved_chicks_qty', "ALTER TABLE household_special_programs ADD COLUMN approved_chicks_qty INT NULL AFTER final_validation_result"],
            ['seminar_event_id', "ALTER TABLE household_special_programs ADD COLUMN seminar_event_id BIGINT NULL AFTER approved_chicks_qty"],
            ['seminar_attendance_status', "ALTER TABLE household_special_programs ADD COLUMN seminar_attendance_status VARCHAR(20) NULL AFTER seminar_event_id"],
            ['seminar_attended_at', "ALTER TABLE household_special_programs ADD COLUMN seminar_attended_at DATETIME NULL AFTER seminar_attendance_status"],
            ['release_date', "ALTER TABLE household_special_programs ADD COLUMN release_date DATE NULL AFTER seminar_attended_at"],
            ['released_chicks_qty', "ALTER TABLE household_special_programs ADD COLUMN released_chicks_qty INT NULL AFTER release_date"],
            ['monitoring_day', "ALTER TABLE household_special_programs ADD COLUMN monitoring_day VARCHAR(20) NULL AFTER released_chicks_qty"],
            ['next_monitoring_date', "ALTER TABLE household_special_programs ADD COLUMN next_monitoring_date DATE NULL AFTER monitoring_day"],
        ];
        foreach ($schemaAdds as [$columnName, $sql]) {
            if (!column_exists($conn, 'household_special_programs', $columnName)) $conn->query($sql);
        }
    }

    if (table_exists($conn, 'events') && !column_exists($conn, 'events', 'event_type')) {
        $conn->query("ALTER TABLE events ADD COLUMN event_type VARCHAR(40) NULL AFTER event_name");
    }
    if (table_exists($conn, 'events') && !column_exists($conn, 'events', 'attendance_closed_at')) {
        $conn->query("ALTER TABLE events ADD COLUMN attendance_closed_at DATETIME NULL AFTER event_status");
    }

    $conn->query("CREATE TABLE IF NOT EXISTS household_violation_types (
        violation_type_id INT AUTO_INCREMENT PRIMARY KEY,
        violation_name VARCHAR(150) NOT NULL,
        description TEXT NULL,
        severity_level VARCHAR(20) NOT NULL DEFAULT 'Common',
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_violation_name (violation_name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $conn->query("CREATE TABLE IF NOT EXISTS household_violations (
        violation_id BIGINT AUTO_INCREMENT PRIMARY KEY,
        household_id BIGINT NOT NULL,
        violation_type_id INT NOT NULL,
        violation_status VARCHAR(20) NOT NULL DEFAULT 'Open',
        observed_on DATE NULL,
        resolved_on DATE NULL,
        remarks TEXT NULL,
        encoded_by INT NULL,
        resolved_by INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL DEFAULT NULL,
        KEY idx_hv_household (household_id),
        KEY idx_hv_status (violation_status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $conn->query("CREATE TABLE IF NOT EXISTS event_program_targets (
        event_program_target_id BIGINT AUTO_INCREMENT PRIMARY KEY,
        event_id BIGINT NOT NULL,
        program_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_event_program_target (event_id, program_id),
        KEY idx_ept_event (event_id),
        KEY idx_ept_program (program_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $conn->query("CREATE TABLE IF NOT EXISTS household_rule_checklist_types (
        checklist_type_id INT AUTO_INCREMENT PRIMARY KEY,
        item_code VARCHAR(80) NOT NULL,
        item_label VARCHAR(160) NOT NULL,
        checklist_group VARCHAR(80) NOT NULL DEFAULT 'Rule Compliance',
        sort_order INT NOT NULL DEFAULT 0,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_rule_item_code (item_code)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $conn->query("CREATE TABLE IF NOT EXISTS household_rule_checklists (
        household_rule_checklist_id BIGINT AUTO_INCREMENT PRIMARY KEY,
        household_id BIGINT NOT NULL,
        checklist_type_id INT NOT NULL,
        is_checked TINYINT(1) NOT NULL DEFAULT 0,
        checked_at DATETIME NULL DEFAULT NULL,
        checked_by INT NULL DEFAULT NULL,
        notes TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL DEFAULT NULL,
        UNIQUE KEY uniq_household_rule_item (household_id, checklist_type_id),
        KEY idx_hrc_household (household_id),
        KEY idx_hrc_checked (is_checked)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    golden_household_seed_defaults($conn);
}

function golden_household_seed_defaults(mysqli $conn): void {
    $programs = [
        'Gamefowl' => 'Chicken breeding and sport support program.',
        'Livestock' => 'Animal raising support program.',
        'Fruit Bearing Trees' => 'Fruit tree planting and maintenance support.',
        'HVCD' => 'High Value Crops Development Program.',
    ];
    foreach ($programs as $name => $desc) {
        $stmt = $conn->prepare("INSERT IGNORE INTO special_programs (program_name, description) VALUES (?, ?)");
        if ($stmt) {
            $stmt->bind_param('ss', $name, $desc);
            $stmt->execute();
            $stmt->close();
        }
    }

    $items = [
        'Gamefowl' => ['American Gamefowl','Sweater','Roundhead','Kelso','Hatch'],
        'Livestock' => ['Cattle','Goat','Pig','Carabao','Sheep'],
        'Fruit Bearing Trees' => ['Mango','Coconut','Banana','Calamansi','Jackfruit','Avocado','Guava'],
        'HVCD' => ['Tomato','Eggplant','Chili','Onion','Coffee','Cacao','Garlic','Ginger','Pineapple','Banana'],
    ];
    foreach ($items as $programName => $programItems) {
        $pid = (int)scalar($conn, "SELECT program_id FROM special_programs WHERE program_name='" . $conn->real_escape_string($programName) . "' LIMIT 1", 0);
        if ($pid <= 0) continue;
        foreach ($programItems as $itemName) {
            $stmt = $conn->prepare("INSERT IGNORE INTO special_program_items (program_id, item_name) VALUES (?, ?)");
            if ($stmt) {
                $stmt->bind_param('is', $pid, $itemName);
                $stmt->execute();
                $stmt->close();
            }
        }
    }

    $violations = [
        ['Smoking in household areas', 'Common'],
        ['Improper garbage disposal', 'Common'],
        ['Dog roaming freely', 'Common'],
        ['Public topless behavior', 'Common'],
        ['Noise disturbance', 'Common'],
        ['Unsanitary surroundings', 'Common'],
        ['Open burning of waste', 'Common'],
    ];
    foreach ($violations as [$name, $severity]) {
        $stmt = $conn->prepare("INSERT IGNORE INTO household_violation_types (violation_name, severity_level) VALUES (?, ?)");
        if ($stmt) {
            $stmt->bind_param('ss', $name, $severity);
            $stmt->execute();
            $stmt->close();
        }
    }

    $ruleItems = [
        ['no_garbage_scattered', 'No garbage scattered', 'Cleanliness', 10],
        ['proper_waste_disposal', 'Proper waste disposal', 'Cleanliness', 20],
        ['clean_surroundings', 'Clean surroundings', 'Cleanliness', 30],
        ['no_dogs_roaming', 'No dogs roaming freely', 'Safety & Order', 40],
        ['animals_controlled', 'Animals controlled', 'Safety & Order', 50],
        ['peaceful_household', 'Peaceful household', 'Safety & Order', 60],
        ['no_smoking_violation', 'No smoking violations', 'Discipline', 70],
        ['no_topless_outside', 'No topless outside', 'Discipline', 80],
        ['follows_barangay_rules', 'Following barangay rules', 'Discipline', 90],
    ];
    foreach ($ruleItems as [$code, $label, $group, $sort]) {
        $stmt = $conn->prepare("INSERT IGNORE INTO household_rule_checklist_types (item_code, item_label, checklist_group, sort_order) VALUES (?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param('sssi', $code, $label, $group, $sort);
            $stmt->execute();
            $stmt->close();
        }
    }
}

function golden_rule_checklist_types(mysqli $conn): array {
    ensure_golden_household_schema($conn);
    $sql = "SELECT t.checklist_type_id, t.item_code, t.item_label, t.checklist_group, t.sort_order
            FROM household_rule_checklist_types t
            INNER JOIN (
                SELECT LOWER(TRIM(item_label)) AS item_key, MIN(checklist_type_id) AS checklist_type_id
                FROM household_rule_checklist_types
                WHERE is_active=1
                GROUP BY LOWER(TRIM(item_label))
            ) uniq ON uniq.checklist_type_id = t.checklist_type_id
            WHERE t.is_active=1
            ORDER BY t.checklist_group, t.sort_order, t.checklist_type_id";
    return fetch_all_assoc($conn, $sql);
}

function household_rule_checklist(mysqli $conn, int $householdId): array {
    ensure_golden_household_schema($conn);
    $sql = "SELECT t.checklist_type_id,t.item_code,t.item_label,t.checklist_group,t.sort_order, COALESCE(r.is_checked,0) AS is_checked, r.checked_at, r.notes
            FROM household_rule_checklist_types t
            LEFT JOIN household_rule_checklists r ON r.checklist_type_id=t.checklist_type_id AND r.household_id=" . (int)$householdId . "
            INNER JOIN (
                SELECT LOWER(TRIM(item_label)) AS item_key, MIN(checklist_type_id) AS checklist_type_id
                FROM household_rule_checklist_types
                WHERE is_active=1
                GROUP BY LOWER(TRIM(item_label))
            ) uniq ON uniq.checklist_type_id = t.checklist_type_id
            WHERE t.is_active=1
            ORDER BY t.checklist_group, t.sort_order, t.checklist_type_id";
    return fetch_all_assoc($conn, $sql);
}

function save_household_rule_checklist(mysqli $conn, int $householdId, array $checkedIds, array $notesById, int $userId): bool {
    ensure_golden_household_schema($conn);
    $types = golden_rule_checklist_types($conn);
    $checkedMap = [];
    foreach ($checkedIds as $id) $checkedMap[(int)$id] = true;
    foreach ($types as $type) {
        $typeId = (int)$type['checklist_type_id'];
        $isChecked = isset($checkedMap[$typeId]) ? 1 : 0;
        $notes = trim((string)($notesById[$typeId] ?? ''));
        $stmt = $conn->prepare("INSERT INTO household_rule_checklists (household_id, checklist_type_id, is_checked, checked_at, checked_by, notes, updated_at) VALUES (?, ?, ?, NOW(), ?, ?, NOW()) ON DUPLICATE KEY UPDATE is_checked=VALUES(is_checked), checked_at=VALUES(checked_at), checked_by=VALUES(checked_by), notes=VALUES(notes), updated_at=VALUES(updated_at)");
        if (!$stmt) return false;
        $stmt->bind_param('iiiis', $householdId, $typeId, $isChecked, $userId, $notes);
        $ok = $stmt->execute();
        $stmt->close();
        if (!$ok) return false;
    }
    return true;
}

function golden_workflow_statuses(): array {
    return ['Pending First Validation','Pending Orientation','Pending Final Validation','Pending Seminar','Pending Release','Active','For Compliance','Declined','Completed','Inactive'];
}

function golden_gamefowl_program_id(mysqli $conn): int {
    ensure_golden_household_schema($conn);
    return (int)scalar($conn, "SELECT program_id FROM special_programs WHERE program_name='Gamefowl' LIMIT 1", 0);
}

function golden_program_name(mysqli $conn, int $programId): string {
    return (string)scalar($conn, "SELECT program_name FROM special_programs WHERE program_id=" . (int)$programId . " LIMIT 1", '');
}

function golden_event_auto_title(mysqli $conn, int $programId, string $eventType, string $eventDate): string {
    $programName = golden_program_name($conn, $programId);
    $dateLabel = trim($eventDate) !== '' ? date('M d, Y', strtotime($eventDate)) : date('M d, Y');
    $base = trim($programName !== '' ? ($programName . ' ' . $eventType) : $eventType);
    return trim($base . ' - ' . $dateLabel);
}

function golden_programs(mysqli $conn): array {
    ensure_golden_household_schema($conn);
    return fetch_all_assoc($conn, "SELECT program_id, program_name, description FROM special_programs WHERE is_active=1 ORDER BY FIELD(program_name,'Gamefowl','Livestock','Fruit Bearing Trees','HVCD'), program_name");
}

function golden_program_items(mysqli $conn, ?int $programId = null): array {
    ensure_golden_household_schema($conn);
    $where = $programId ? "WHERE i.program_id=" . (int)$programId . " AND i.is_active=1" : "WHERE i.is_active=1";
    return fetch_all_assoc($conn, "SELECT i.item_id, i.program_id, i.item_name, p.program_name FROM special_program_items i JOIN special_programs p ON p.program_id=i.program_id {$where} ORDER BY p.program_name, i.item_name");
}

function golden_violation_types(mysqli $conn): array {
    ensure_golden_household_schema($conn);
    return fetch_all_assoc($conn, "SELECT violation_type_id, violation_name, severity_level FROM household_violation_types WHERE is_active=1 ORDER BY violation_name");
}

function golden_save_event_program_targets(mysqli $conn, int $eventId, array $programIds): void {
    ensure_golden_household_schema($conn);
    if ($eventId <= 0 || !table_exists($conn, 'event_program_targets')) return;
    $conn->query("DELETE FROM event_program_targets WHERE event_id=" . (int)$eventId);
    $seen = [];
    foreach ($programIds as $programId) {
        $programId = (int)$programId;
        if ($programId <= 0 || isset($seen[$programId])) continue;
        $seen[$programId] = true;
        $stmt = $conn->prepare("INSERT IGNORE INTO event_program_targets (event_id, program_id) VALUES (?, ?)");
        if ($stmt) {
            $stmt->bind_param('ii', $eventId, $programId);
            $stmt->execute();
            $stmt->close();
        }
    }
}

function golden_event_target_programs(mysqli $conn, int $eventId): array {
    ensure_golden_household_schema($conn);
    if ($eventId <= 0 || !table_exists($conn, 'event_program_targets')) return [];
    ensure_golden_household_schema($conn);
    return fetch_all_assoc($conn, "SELECT p.program_id, p.program_name FROM event_program_targets t JOIN special_programs p ON p.program_id=t.program_id WHERE t.event_id=" . (int)$eventId . " ORDER BY FIELD(p.program_name,'Gamefowl','Livestock','Fruit Bearing Trees','HVCD'), p.program_name");
}

function golden_event_target_program_ids(mysqli $conn, int $eventId): array {
    $rows = golden_event_target_programs($conn, $eventId);
    return array_map(static fn($row) => (int)$row['program_id'], $rows);
}

function golden_event_target_program_label(mysqli $conn, int $eventId): string {
    $rows = golden_event_target_programs($conn, $eventId);
    if (!$rows) return 'All programs';
    return implode(', ', array_map(static fn($row) => (string)$row['program_name'], $rows));
}

function golden_event_type_rules(string $eventType): array {
    $type = strtolower(trim($eventType));
    switch ($type) {
        case 'orientation':
            return [
                'event_type' => 'Orientation',
                'eligible_statuses' => ['Pending Orientation'],
                'next_status' => 'Pending Final Validation',
                'summary_label' => 'households waiting for orientation',
                'attendance_note' => 'Orientation completed',
            ];
        case 'seminar':
            return [
                'event_type' => 'Seminar',
                'eligible_statuses' => ['Pending Seminar'],
                'next_status' => 'Pending Release',
                'summary_label' => 'households waiting for seminar',
                'attendance_note' => 'Seminar completed',
            ];
        case 'training':
            return [
                'event_type' => 'Training',
                'eligible_statuses' => ['Active'],
                'next_status' => 'Active',
                'summary_label' => 'active households ready for training',
                'attendance_note' => 'Training completed',
            ];
        case 'monitoring':
            return [
                'event_type' => 'Monitoring',
                'eligible_statuses' => ['Active', 'Completed'],
                'next_status' => 'Active',
                'summary_label' => 'active households ready for monitoring',
                'attendance_note' => 'Monitoring recorded',
            ];
        case 'awarding':
            return [
                'event_type' => 'Awarding',
                'eligible_statuses' => ['Active'],
                'next_status' => 'Completed',
                'summary_label' => 'qualified households ready for awarding',
                'attendance_note' => 'Awarding completed',
            ];
        default:
            return [
                'event_type' => 'General',
                'eligible_statuses' => [],
                'next_status' => null,
                'summary_label' => 'all households',
                'attendance_note' => 'Attendance recorded',
            ];
    }
}

function golden_event_details(mysqli $conn, int $eventId): ?array {
    ensure_golden_household_schema($conn);
    if ($eventId <= 0 || !table_exists($conn, 'events')) return null;
    $typeSelect = column_exists($conn, 'events', 'event_type') ? 'event_type' : "'General' AS event_type";
    $row = fetch_one($conn, "SELECT event_id, event_name, " . $typeSelect . ", barangay_id FROM events WHERE event_id=" . (int)$eventId . " LIMIT 1");
    if (!$row) return null;
    $rules = golden_event_type_rules((string)($row['event_type'] ?? 'General'));
    $row['workflow_rules'] = $rules;
    $row['target_program_ids'] = golden_event_target_program_ids($conn, $eventId);
    return $row;
}

function golden_event_program_candidates(mysqli $conn, int $eventId, int $householdId = 0): array {
    ensure_golden_household_schema($conn);
    $event = golden_event_details($conn, $eventId);
    if (!$event) return [];
    $rules = $event['workflow_rules'] ?? golden_event_type_rules('General');
    $targetIds = $event['target_program_ids'] ?? [];
    $where = [];
    if ($householdId > 0) $where[] = 'sp.household_id=' . (int)$householdId;
    if (!empty($rules['eligible_statuses'])) {
        $statusSql = implode(',', array_map(static fn($s) => "'" . addslashes((string)$s) . "'", $rules['eligible_statuses']));
        $where[] = 'sp.application_status IN (' . $statusSql . ')';
    }
    if (!empty($targetIds)) {
        $where[] = 'sp.program_id IN (' . implode(',', array_map('intval', $targetIds)) . ')';
    }
    if ((int)($event['barangay_id'] ?? 0) > 0) {
        $where[] = 'h.barangay_id=' . (int)$event['barangay_id'];
    }
    $sql = "SELECT sp.application_id, sp.household_id, sp.program_id, sp.application_status, sp.orientation_status, sp.target_notes, sp.validation_notes, p.program_name
            FROM household_special_programs sp
            JOIN households h ON h.household_id=sp.household_id
            JOIN special_programs p ON p.program_id=sp.program_id";
    if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
    $sql .= " ORDER BY FIELD(sp.application_status,'Pending First Validation','Pending Orientation','Pending Final Validation','Pending Seminar','Pending Release','Active','For Compliance','Declined','Completed','Inactive'), sp.application_id DESC";
    return fetch_all_assoc($conn, $sql);
}

function golden_event_candidate_program_ids_for_household(mysqli $conn, int $eventId, int $householdId): array {
    $rows = golden_event_program_candidates($conn, $eventId, $householdId);
    $ids = [];
    foreach ($rows as $row) {
        $ids[(int)$row['program_id']] = true;
    }
    return array_map('intval', array_keys($ids));
}

function golden_event_household_status_hint(mysqli $conn, int $eventId): string {
    $event = golden_event_details($conn, $eventId);
    if (!$event) return 'All households';
    $rules = $event['workflow_rules'] ?? golden_event_type_rules('General');
    $programLabel = golden_event_target_program_label($conn, $eventId);
    if (($rules['event_type'] ?? 'General') === 'General') return 'All households';
    return ucfirst((string)$rules['summary_label']) . ' under: ' . $programLabel;
}

function household_matches_event_program_targets(mysqli $conn, int $householdId, int $eventId): bool {
    ensure_golden_household_schema($conn);
    $event = golden_event_details($conn, $eventId);
    if (!$event) return true;
    $rules = $event['workflow_rules'] ?? golden_event_type_rules('General');
    if (($rules['event_type'] ?? 'General') === 'General') {
        $targetIds = $event['target_program_ids'] ?? [];
        if (!$targetIds) return true;
        $approvedIds = fetch_all_assoc($conn, "SELECT DISTINCT program_id FROM household_special_programs WHERE household_id=" . (int)$householdId . " AND application_status IN ('Approved','Active','Completed')");
        $approvedMap = [];
        foreach ($approvedIds as $row) $approvedMap[(int)$row['program_id']] = true;
        foreach ($targetIds as $programId) {
            if (isset($approvedMap[(int)$programId])) return true;
        }
        return false;
    }
    return count(golden_event_program_candidates($conn, $eventId, $householdId)) > 0;
}

function apply_household_program(mysqli $conn, int $householdId, int $programId, ?int $itemId, string $notes, int $userId, array $meta = []): bool {
    ensure_golden_household_schema($conn);
    if ($householdId <= 0 || $programId <= 0) return false;
    $contact = trim((string)($meta['applicant_contact'] ?? ''));
    $landLocation = trim((string)($meta['land_location'] ?? ''));
    $landArea = trim((string)($meta['land_area_text'] ?? ''));
    $ownership = trim((string)($meta['ownership_type'] ?? ''));
    $orientation = trim((string)($meta['orientation_status'] ?? ''));
    $intakeNotes = trim((string)($meta['intake_notes'] ?? ''));
    $scheduledValidationDate = trim((string)($meta['scheduled_validation_date'] ?? ''));
    $normalizedOrientation = $orientation !== '' ? $orientation : 'Not yet attended';
    $isGamefowl = $programId === golden_gamefowl_program_id($conn);
    $initialStatus = $isGamefowl ? 'Pending First Validation' : (in_array($normalizedOrientation, ['Attended orientation','Validated orientation'], true) ? 'Pending Final Validation' : 'Pending Orientation');
    $stmt = $conn->prepare("INSERT INTO household_special_programs (household_id, program_id, item_id, applicant_contact, land_location, land_area_text, ownership_type, orientation_status, application_status, target_notes, intake_notes, scheduled_validation_date, date_applied, applied_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), ?)");
    if (!$stmt) return false;
    $scheduledDate = $scheduledValidationDate !== '' ? $scheduledValidationDate : null;
    $stmt->bind_param('iiisssssssssi', $householdId, $programId, $itemId, $contact, $landLocation, $landArea, $ownership, $normalizedOrientation, $initialStatus, $notes, $intakeNotes, $scheduledDate, $userId);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

function review_household_program(mysqli $conn, int $applicationId, string $status, string $notes, int $userId): bool {
    ensure_golden_household_schema($conn);
    $allowed = golden_workflow_statuses();
    if (!in_array($status, $allowed, true)) return false;
    $qualificationResult = null;
    if ($status === 'Inactive' && trim($notes) !== '') $qualificationResult = 'Not qualified / inactive';
    $stmt = $conn->prepare("UPDATE household_special_programs SET application_status=?, qualification_result=?, validation_notes=?, reviewed_by=?, date_reviewed=CURDATE(), updated_at=NOW() WHERE application_id=?");
    if (!$stmt) return false;
    $stmt->bind_param('sssii', $status, $qualificationResult, $notes, $userId, $applicationId);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

function add_household_violation(mysqli $conn, int $householdId, int $typeId, string $observedOn, string $remarks, int $userId): bool {
    ensure_golden_household_schema($conn);
    if ($householdId <= 0 || $typeId <= 0) return false;
    $obs = trim($observedOn) !== '' ? $observedOn : date('Y-m-d');
    $stmt = $conn->prepare("INSERT INTO household_violations (household_id, violation_type_id, violation_status, observed_on, remarks, encoded_by) VALUES (?, ?, 'Open', ?, ?, ?)");
    if (!$stmt) return false;
    $stmt->bind_param('iissi', $householdId, $typeId, $obs, $remarks, $userId);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

function resolve_household_violation(mysqli $conn, int $violationId, int $userId): bool {
    ensure_golden_household_schema($conn);
    $stmt = $conn->prepare("UPDATE household_violations SET violation_status='Resolved', resolved_on=CURDATE(), resolved_by=?, updated_at=NOW() WHERE violation_id=?");
    if (!$stmt) return false;
    $stmt->bind_param('ii', $userId, $violationId);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

function household_program_applications(mysqli $conn, int $householdId): array {
    ensure_golden_household_schema($conn);
    return fetch_all_assoc($conn, "SELECT a.application_id,a.application_status,a.target_notes,a.intake_notes,a.validation_notes,a.date_applied,a.date_reviewed,a.applicant_contact,a.land_location,a.land_area_text,a.ownership_type,a.orientation_status,a.scheduled_validation_date,a.orientation_attendance_status,a.final_validation_date,a.approved_chicks_qty,a.seminar_attendance_status,a.release_date,p.program_name,i.item_name
        FROM household_special_programs a
        JOIN special_programs p ON p.program_id=a.program_id
        LEFT JOIN special_program_items i ON i.item_id=a.item_id
        WHERE a.household_id=" . (int)$householdId . "
        ORDER BY FIELD(a.application_status,'Pending First Validation','Pending Orientation','Pending Final Validation','Pending Seminar','Pending Release','Active','For Compliance','Declined','Completed','Inactive'), a.application_id DESC");
}

function household_violation_logs(mysqli $conn, int $householdId): array {
    ensure_golden_household_schema($conn);
    return fetch_all_assoc($conn, "SELECT v.violation_id,v.violation_status,v.observed_on,v.resolved_on,v.remarks,t.violation_name,t.severity_level
        FROM household_violations v
        JOIN household_violation_types t ON t.violation_type_id=v.violation_type_id
        WHERE v.household_id=" . (int)$householdId . "
        ORDER BY FIELD(v.violation_status,'Open','Resolved'), v.observed_on DESC, v.violation_id DESC");
}

function household_golden_summary(mysqli $conn, int $householdId): array {
    ensure_golden_household_schema($conn);
    $programSummary = fetch_all_assoc($conn, "SELECT application_status, COUNT(*) total FROM household_special_programs WHERE household_id=" . (int)$householdId . " GROUP BY application_status");
    $programCounts = ['Pending Validation'=>0,'Pending First Validation'=>0,'Pending Orientation'=>0,'Pending Final Validation'=>0,'Pending Seminar'=>0,'Pending Release'=>0,'Approved'=>0,'Active'=>0,'For Compliance'=>0,'Declined'=>0,'Rejected'=>0,'Completed'=>0,'Inactive'=>0];
    foreach ($programSummary as $row) {
        $status = (string)($row['application_status'] ?? '');
        $statusAliases = ['Pending' => 'Pending Validation'];
        $status = $statusAliases[$status] ?? $status;
        if (!array_key_exists($status, $programCounts)) $programCounts[$status] = 0;
        $programCounts[$status] += (int)$row['total'];
    }
    $eventsAttended = (int)scalar($conn, "SELECT COUNT(DISTINCT event_id) FROM event_attendance WHERE household_id={$householdId} AND attendance_status IN ('Present','Late')", 0);
    $openViolations = (int)scalar($conn, "SELECT COUNT(*) FROM household_violations WHERE household_id={$householdId} AND violation_status='Open'", 0);
    $totalViolations = (int)scalar($conn, "SELECT COUNT(*) FROM household_violations WHERE household_id={$householdId}", 0);
    $ruleRows = household_rule_checklist($conn, $householdId);
    $ruleTotal = count($ruleRows);
    $ruleChecked = 0;
    foreach ($ruleRows as $row) if (!empty($row['is_checked'])) $ruleChecked++;

    $approvedPrograms = (int)($programCounts['Approved'] ?? 0);
    $activePrograms = (int)($programCounts['Active'] ?? 0);
    $completedPrograms = (int)($programCounts['Completed'] ?? 0);
    $programChecklistCompleted = ($approvedPrograms + $activePrograms + $completedPrograms) >= 1;
    $programStrong = ($activePrograms + $completedPrograms) >= 1 || ($approvedPrograms >= 2);
    $eventChecklistCompleted = $eventsAttended >= 1;
    $eventStrong = $eventsAttended >= 3;
    $rulesChecklistCompleted = $ruleTotal > 0 ? ($ruleChecked >= max(1, $ruleTotal - 1)) : true;
    $rulesStrong = $ruleTotal > 0 ? ($ruleChecked === $ruleTotal) : true;

    $status = 'Needs Coaching';
    if ($programStrong && $eventStrong && $rulesStrong && $openViolations === 0) {
        $status = 'Golden Household';
    } elseif ($programChecklistCompleted && $eventChecklistCompleted && $rulesChecklistCompleted && $openViolations <= 1) {
        $status = 'Rising Household';
    } elseif ($openViolations > 0 || !$rulesChecklistCompleted) {
        $status = 'For Rule Compliance';
    }

    return [
        'program_counts' => $programCounts,
        'approved_programs' => $approvedPrograms,
        'active_programs' => $activePrograms,
        'completed_programs' => $completedPrograms,
        'pending_first_validation' => $programCounts['Pending First Validation'],
        'pending_orientation' => $programCounts['Pending Orientation'],
        'pending_final_validation' => $programCounts['Pending Final Validation'],
        'pending_seminar' => $programCounts['Pending Seminar'],
        'pending_release' => $programCounts['Pending Release'],
        'inactive_programs' => $programCounts['Inactive'],
        'events_attended' => $eventsAttended,
        'open_violations' => $openViolations,
        'total_violations' => $totalViolations,
        'rule_items_total' => $ruleTotal,
        'rule_items_checked' => $ruleChecked,
        'check_programs' => $programChecklistCompleted,
        'check_events' => $eventChecklistCompleted,
        'check_rules' => $rulesChecklistCompleted,
        'status' => $status,
        'eligible' => $status === 'Golden Household',
        'summary_line' => 'Programs: ' . ($approvedPrograms + $activePrograms + $completedPrograms) . ' active/qualified · Events: ' . $eventsAttended . ' attended · Rules: ' . $ruleChecked . '/' . $ruleTotal . ' compliant · Open violations: ' . $openViolations,
    ];
}


function golden_orientation_queue_count(mysqli $conn): int {
    ensure_golden_household_schema($conn);
    return (int)scalar($conn, "SELECT COUNT(*) FROM household_special_programs WHERE application_status='Pending Orientation'", 0);
}

function golden_validation_queue_count(mysqli $conn): int {
    ensure_golden_household_schema($conn);
    return (int)scalar($conn, "SELECT COUNT(*) FROM household_special_programs WHERE application_status IN ('Pending First Validation','Pending Final Validation','For Compliance')", 0);
}

function golden_open_violation_count(mysqli $conn): int {
    ensure_golden_household_schema($conn);
    return (int)scalar($conn, "SELECT COUNT(*) FROM household_violations WHERE violation_status='Open'", 0);
}

function golden_orientation_event(mysqli $conn, int $eventId): bool {
    ensure_golden_household_schema($conn);
    if ($eventId <= 0 || !table_exists($conn, 'events')) return false;
    $row = fetch_one($conn, "SELECT event_type, event_name FROM events WHERE event_id=" . (int)$eventId . " LIMIT 1");
    if (!$row) return false;
    $eventType = strtolower(trim((string)($row['event_type'] ?? '')));
    if ($eventType === 'orientation') return true;
    return str_contains(strtolower((string)($row['event_name'] ?? '')), 'orientation');
}

function golden_sync_event_program_progress_from_attendance(mysqli $conn, int $eventId, int $householdId, string $attendanceStatus, int $userId = 0): void {
    ensure_golden_household_schema($conn);
    if ($eventId <= 0 || $householdId <= 0) return;
    if (!in_array($attendanceStatus, ['Present','Late'], true)) return;
    $event = golden_event_details($conn, $eventId);
    if (!$event) return;
    $rules = $event['workflow_rules'] ?? golden_event_type_rules('General');
    $eventType = (string)($rules['event_type'] ?? 'General');
    if ($eventType === 'General') return;
    $rows = golden_event_program_candidates($conn, $eventId, $householdId);
    if (!$rows) return;

    $noteColumn = column_exists($conn, 'household_special_programs', 'validation_notes') ? 'validation_notes' : (column_exists($conn, 'household_special_programs', 'target_notes') ? 'target_notes' : '');
    $eventName = trim((string)($event['event_name'] ?? $eventType));
    $progressNote = trim($eventType . ' attendance recorded via event ' . ($eventName !== '' ? '"' . $eventName . '"' : ''));
    $newStatus = $rules['next_status'] ?? null;
    $updatedAny = false;

    foreach ($rows as $row) {
        $applicationId = (int)($row['application_id'] ?? 0);
        if ($applicationId <= 0) continue;
        $parts = ['updated_at=NOW()'];
        if ($eventType === 'Orientation') {
            if (column_exists($conn, 'household_special_programs', 'orientation_status')) $parts[] = "orientation_status='Attended orientation'";
            if (column_exists($conn, 'household_special_programs', 'orientation_attendance_status')) $parts[] = "orientation_attendance_status='Present'";
            if (column_exists($conn, 'household_special_programs', 'orientation_attended_at')) $parts[] = "orientation_attended_at=NOW()";
            if (column_exists($conn, 'household_special_programs', 'orientation_event_id')) $parts[] = 'orientation_event_id=' . (int)$eventId;
        } elseif ($eventType === 'Seminar') {
            if (column_exists($conn, 'household_special_programs', 'seminar_attendance_status')) $parts[] = "seminar_attendance_status='Present'";
            if (column_exists($conn, 'household_special_programs', 'seminar_attended_at')) $parts[] = "seminar_attended_at=NOW()";
            if (column_exists($conn, 'household_special_programs', 'seminar_event_id')) $parts[] = 'seminar_event_id=' . (int)$eventId;
        }
        if ($newStatus) $parts[] = "application_status='" . $conn->real_escape_string($newStatus) . "'";
        if ($noteColumn !== '') {
            $parts[] = $noteColumn . "=TRIM(CONCAT(COALESCE(" . $noteColumn . ",'') , CASE WHEN COALESCE(" . $noteColumn . ",'')='' THEN '' ELSE '
' END, '" . $conn->real_escape_string($progressNote) . "'))";
        }
        $sql = 'UPDATE household_special_programs SET ' . implode(', ', $parts) . ' WHERE application_id=' . $applicationId;
        $conn->query($sql);
        if ($conn->affected_rows > 0) $updatedAny = true;
    }

    if ($updatedAny) {
        $message = 'Attendance updated the household workflow for ' . strtolower($eventType) . '.';
        if ($eventType === 'Orientation') {
            $message = 'A household attended orientation and is now ready for final validation.';
        } elseif ($eventType === 'Seminar') {
            $message = 'A household attended seminar and is now ready for release.';
        } elseif ($eventType === 'Training') {
            $message = 'A household attended training and remains active in the target program.';
        } elseif ($eventType === 'Monitoring') {
            $message = 'A household attendance was recorded for monitoring and kept active in the target program.';
        } elseif ($eventType === 'Awarding') {
            $message = 'A household completed awarding and is now marked completed in the target program.';
        }
        create_notification($conn, $rules['attendance_note'] ?? ($eventType . ' completed'), $message, 'Medium', $userId ?: null, $householdId, null, 'Program Workflow');
    }
}

function golden_close_event_attendance(mysqli $conn, int $eventId, int $userId = 0): array {
    ensure_golden_household_schema($conn);
    if ($eventId <= 0) return ['ok' => false, 'message' => 'Missing event.'];
    $event = golden_event_details($conn, $eventId);
    if (!$event) return ['ok' => false, 'message' => 'Event not found.'];
    $candidates = golden_event_program_candidates($conn, $eventId);
    $presentMap = [];
    foreach (fetch_all_assoc($conn, "SELECT household_id, attendance_status FROM event_attendance WHERE event_id=" . (int)$eventId) as $row) {
        $presentMap[(int)$row['household_id']] = (string)$row['attendance_status'];
    }
    $createdAbsent = 0;
    foreach ($candidates as $row) {
        $householdId = (int)($row['household_id'] ?? 0);
        if ($householdId <= 0 || isset($presentMap[$householdId])) continue;
        $stmt = $conn->prepare("INSERT INTO event_attendance (event_id, household_id, attendance_status, time_in, method, notes, recorded_by" . (column_exists($conn, 'event_attendance', 'updated_at') ? ', updated_at' : '') . ") VALUES (?, ?, 'Absent', NOW(), 'System Close', ?, ?" . (column_exists($conn, 'event_attendance', 'updated_at') ? ', NOW()' : '') . ")");
        if ($stmt) {
            $note = 'Marked absent when attendance was closed.';
            $uid = $userId ?: null;
            $stmt->bind_param('iisi', $eventId, $householdId, $note, $uid);
            $stmt->execute();
            $stmt->close();
            $createdAbsent++;
        }
        if (($event['workflow_rules']['event_type'] ?? '') === 'Orientation' && column_exists($conn, 'household_special_programs', 'orientation_attendance_status')) {
            $conn->query("UPDATE household_special_programs SET orientation_attendance_status='Absent', orientation_event_id=" . (int)$eventId . ", updated_at=NOW() WHERE household_id=" . $householdId . " AND application_status='Pending Orientation'");
        }
        if (($event['workflow_rules']['event_type'] ?? '') === 'Seminar' && column_exists($conn, 'household_special_programs', 'seminar_attendance_status')) {
            $conn->query("UPDATE household_special_programs SET seminar_attendance_status='Absent', seminar_event_id=" . (int)$eventId . ", updated_at=NOW() WHERE household_id=" . $householdId . " AND application_status='Pending Seminar'");
        }
    }
    if (table_exists($conn, 'events') && column_exists($conn, 'events', 'attendance_closed_at')) {
        $conn->query("UPDATE events SET attendance_closed_at=NOW() WHERE event_id=" . (int)$eventId);
    }
    create_notification($conn, 'Attendance closed', 'Attendance is now closed for event #' . $eventId . '. Absent households were marked automatically.', 'Low', $userId ?: null, null, null, 'Events');
    return ['ok' => true, 'message' => 'Attendance closed. ' . $createdAbsent . ' invited households were marked absent.', 'created_absent' => $createdAbsent, 'invited_total' => count($candidates)];
}

function golden_sync_orientation_from_attendance(mysqli $conn, int $eventId, int $householdId, string $attendanceStatus, int $userId = 0): void {
    golden_sync_event_program_progress_from_attendance($conn, $eventId, $householdId, $attendanceStatus, $userId);
}

function golden_household_candidates(mysqli $conn, int $limit = 12): array {
    ensure_golden_household_schema($conn);
    $rows = fetch_all_assoc($conn, "SELECT h.household_id,h.household_head_name,h.household_code,h.household_size,b.barangay_name,
        (SELECT COUNT(*) FROM household_special_programs sp WHERE sp.household_id=h.household_id AND sp.application_status IN ('Approved','Active','Completed')) approved_programs,
        (SELECT COUNT(DISTINCT ea.event_id) FROM event_attendance ea WHERE ea.household_id=h.household_id AND ea.attendance_status IN ('Present','Late')) events_attended,
        (SELECT COUNT(*) FROM household_violations hv WHERE hv.household_id=h.household_id) total_violations,
        (SELECT COUNT(*) FROM household_violations hv WHERE hv.household_id=h.household_id AND hv.violation_status='Open') open_violations
        FROM households h
        LEFT JOIN barangays b ON b.barangay_id=h.barangay_id
        WHERE COALESCE(h.record_status,'active') <> 'deleted'
        ORDER BY approved_programs DESC, events_attended DESC, open_violations ASC, total_violations ASC, h.household_id DESC
        LIMIT " . (int)$limit);
    foreach ($rows as &$row) {
        $summary = household_golden_summary($conn, (int)$row['household_id']);
        $row['golden_status'] = $summary['status'];
        $row['golden_eligible'] = $summary['eligible'];
    }
    unset($row);
    usort($rows, static function ($a, $b) {
        $rank = ['Golden Household'=>3,'Rising Household'=>2,'For Rule Compliance'=>1,'Needs Coaching'=>0];
        $cmp = ($rank[$b['golden_status']] ?? 0) <=> ($rank[$a['golden_status']] ?? 0);
        if ($cmp !== 0) return $cmp;
        $cmp = ((int)$b['approved_programs']) <=> ((int)$a['approved_programs']);
        if ($cmp !== 0) return $cmp;
        $cmp = ((int)$b['events_attended']) <=> ((int)$a['events_attended']);
        if ($cmp !== 0) return $cmp;
        return ((int)$a['open_violations']) <=> ((int)$b['open_violations']);
    });
    return $rows;
}

function golden_program_queue(mysqli $conn, string $status = 'Pending First Validation', int $limit = 20): array {
    ensure_golden_household_schema($conn);
    $statusSql = $conn->real_escape_string($status);
    return fetch_all_assoc($conn, "SELECT a.application_id,a.program_id,a.household_id,a.application_status,a.date_applied,a.validation_notes,a.target_notes,a.intake_notes,a.applicant_contact,a.land_location,a.land_area_text,a.ownership_type,a.orientation_status,a.scheduled_validation_date,a.orientation_attendance_status,a.final_validation_date,a.approved_chicks_qty,a.seminar_attendance_status,a.release_date,p.program_name,i.item_name,h.household_head_name,h.household_code,b.barangay_name
        FROM household_special_programs a
        JOIN households h ON h.household_id=a.household_id
        LEFT JOIN barangays b ON b.barangay_id=h.barangay_id
        JOIN special_programs p ON p.program_id=a.program_id
        LEFT JOIN special_program_items i ON i.item_id=a.item_id
        WHERE a.application_status='{$statusSql}'
        ORDER BY a.application_id DESC
        LIMIT " . (int)$limit);
}

function golden_violation_queue(mysqli $conn, string $status = 'Open', int $limit = 20): array {
    ensure_golden_household_schema($conn);
    $statusSql = $conn->real_escape_string($status);
    return fetch_all_assoc($conn, "SELECT v.violation_id,v.violation_status,v.observed_on,t.violation_name,h.household_id,h.household_head_name,h.household_code,b.barangay_name
        FROM household_violations v
        JOIN households h ON h.household_id=v.household_id
        LEFT JOIN barangays b ON b.barangay_id=h.barangay_id
        JOIN household_violation_types t ON t.violation_type_id=v.violation_type_id
        WHERE v.violation_status='{$statusSql}'
        ORDER BY v.observed_on DESC, v.violation_id DESC
        LIMIT " . (int)$limit);
}
