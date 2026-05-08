<?php

function ensure_harvest_schema(mysqli $conn): void {
    static $done = false;
    if ($done) return;
    $done = true;
    if (!table_exists($conn, 'official_crops')) {
        $conn->query("CREATE TABLE official_crops (crop_id BIGINT AUTO_INCREMENT PRIMARY KEY, crop_name VARCHAR(100) NOT NULL UNIQUE, is_active TINYINT(1) NOT NULL DEFAULT 1, created_by BIGINT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
        foreach (['Cacao','Guava','Lanzones','Mangosteen'] as $seed) {
            $stmt = $conn->prepare("INSERT IGNORE INTO official_crops (crop_name, is_active) VALUES (?,1)");
            if ($stmt) { $stmt->bind_param('s', $seed); $stmt->execute(); $stmt->close(); }
        }
    }
}

function official_crop_rows(mysqli $conn): array {
    ensure_harvest_schema($conn);
    return fetch_all_assoc($conn, "SELECT crop_id, crop_name, is_active FROM official_crops WHERE is_active=1 ORDER BY crop_name");
}

function ensure_family_upgrade_schema(mysqli $conn): void {
    static $done = false;
    if ($done) return;
    $done = true;
    if (table_exists($conn, 'households')) {
        if (!column_exists($conn, 'households', 'profile_photo_path')) {
            @$conn->query("ALTER TABLE households ADD COLUMN profile_photo_path VARCHAR(255) NULL AFTER contact_number");
        }
        if (!column_exists($conn, 'households', 'head_member_id')) {
            @$conn->query("ALTER TABLE households ADD COLUMN head_member_id BIGINT NULL AFTER profile_photo_path");
        }
        $householdAdds = [
            "record_status ENUM('active','archived','deleted') NOT NULL DEFAULT 'active' AFTER family_last_accessed_at",
            "archived_at DATETIME NULL AFTER record_status",
            "archived_by BIGINT NULL AFTER archived_at",
            "archive_reason TEXT NULL AFTER archived_by",
            "reactivated_at DATETIME NULL AFTER archive_reason",
            "reactivated_by BIGINT NULL AFTER reactivated_at",
            "deleted_at DATETIME NULL AFTER reactivated_by",
            "deleted_by BIGINT NULL AFTER deleted_at",
            "delete_reason TEXT NULL AFTER deleted_by"
        ];
        foreach ($householdAdds as $sql) {
            if (preg_match('/^([a-z_]+)/i', $sql, $m) && !column_exists($conn, 'households', $m[1])) {
                @$conn->query("ALTER TABLE households ADD COLUMN " . $sql);
            }
        }
        @$conn->query("UPDATE households SET record_status='active' WHERE record_status IS NULL OR record_status=''");
    }
    if (table_exists($conn, 'family_members')) {
        $adds = [
            "is_household_head TINYINT(1) NOT NULL DEFAULT 0 AFTER household_id",
            "first_name VARCHAR(80) NULL AFTER full_name",
            "middle_name VARCHAR(80) NULL AFTER first_name",
            "last_name VARCHAR(80) NULL AFTER middle_name",
            "suffix_name VARCHAR(20) NULL AFTER last_name",
            "civil_status VARCHAR(50) NULL AFTER age",
            "education_level VARCHAR(100) NULL AFTER occupation",
            "member_status VARCHAR(50) NULL AFTER education_level",
            "email_address VARCHAR(120) NULL AFTER contact_number",
            "member_photo_path VARCHAR(255) NULL AFTER member_status",
            "notes TEXT NULL AFTER remarks"
        ];
        foreach ($adds as $sql) {
            if (preg_match('/^([a-z_]+)/i', $sql, $m) && !column_exists($conn, 'family_members', $m[1])) {
                @$conn->query("ALTER TABLE family_members ADD COLUMN " . $sql);
            }
        }
        @$conn->query("UPDATE family_members SET relationship_to_head='Head' WHERE relationship_to_head IS NULL AND is_household_head=1");
        @$conn->query("UPDATE family_members SET member_status=COALESCE(NULLIF(member_status,''), 'Living in household')");
    }
    @mkdir(app_path('public/uploads/family_members'), 0777, true);
    @mkdir(app_path('public/uploads/households'), 0777, true);
    seed_head_members_from_households($conn);
}

function seed_head_members_from_households(mysqli $conn): void {
    static $done = false;
    if ($done || !table_exists($conn, 'households') || !table_exists($conn, 'family_members')) return;
    $done = true;
    $rows = fetch_all_assoc($conn, "SELECT household_id, household_head_name, sex, birthdate, age, contact_number, profile_photo_path, remarks, household_size, head_member_id FROM households");
    foreach ($rows as $row) {
        $householdId = (int)$row['household_id'];
        $existingHead = (int)scalar($conn, "SELECT member_id FROM family_members WHERE household_id={$householdId} AND is_household_head=1 LIMIT 1", 0);
        if ($existingHead <= 0) {
            $stmt = $conn->prepare("INSERT INTO family_members (household_id, is_household_head, full_name, relationship_to_head, sex, birthdate, age, contact_number, member_status, member_photo_path, remarks, is_active) VALUES (?,1,?,'Head',?,?,?,?,?,?,?,1)");
            if ($stmt) {
                $fullName = $row['household_head_name'] ?: 'Unnamed Head';
                $sex = $row['sex'] ?: null;
                $birthdate = $row['birthdate'] ?: null;
                $age = $row['age'] !== null ? (int)$row['age'] : null;
                $contact = $row['contact_number'] ?: null;
                $memberStatus = 'Living in household';
                $photo = $row['profile_photo_path'] ?: null;
                $remarks = $row['remarks'] ?: null;
                $stmt->bind_param('isssissss', $householdId, $fullName, $sex, $birthdate, $age, $contact, $memberStatus, $photo, $remarks);
                @$stmt->execute();
                $existingHead = (int)$stmt->insert_id;
                $stmt->close();
            }
        }
        if ($existingHead > 0 && column_exists($conn, 'households', 'head_member_id')) {
            $stmt = $conn->prepare("UPDATE households SET head_member_id=?, household_size=GREATEST(1, (SELECT COUNT(*) FROM family_members WHERE household_id=? AND is_active=1)) WHERE household_id=?");
            if ($stmt) { $stmt->bind_param('iii', $existingHead, $householdId, $householdId); @$stmt->execute(); $stmt->close(); }
        }
    }
}

function family_member_relationship_options(): array {
    return ['Head','Spouse','Father','Mother','Son','Daughter','Brother','Sister','Grandfather','Grandmother','Grandson','Granddaughter','Uncle','Aunt','Nephew','Niece','Cousin','In-law','Guardian','Boarder','Other'];
}

function family_member_status_options(): array {
    return ['Living in household','Temporarily away','Working elsewhere','Studying elsewhere','Visitor','Other'];
}

function relationship_options(): array {
    return ['Head','Spouse','Father','Mother','Son','Daughter','Brother','Sister','Grandfather','Grandmother','Grandson','Granddaughter','Uncle','Aunt','Nephew','Niece','Cousin','In-law','Partner','Guardian','Helper','Boarder','Other'];
}

function sex_options(): array {
    return ['Male','Female','Other'];
}

function civil_status_options(): array {
    return ['Single','Married','Live-in','Separated','Widowed','Annulled','Other'];
}

function education_level_options(): array {
    return ['No formal education','Elementary level','Elementary graduate','Junior high level','Junior high graduate','Senior high level','Senior high graduate','Vocational','College level','College graduate','Postgraduate','Other'];
}

function occupation_options(): array {
    return ['Farmer','Farm worker','Housewife/Househusband','Student','Laborer','Vendor','Driver','Fisherfolk','Government employee','Private employee','Self-employed','Overseas worker','Retired','Unemployed','Other'];
}

function family_member_extra_profile_fields(): array {
    return [
        'place_of_birth' => 's',
        'weight_kg' => 'd',
        'height_cm' => 'd',
        'citizenship' => 's',
        'language_spoken' => 's',
        'religious_affiliation' => 's',
        'employment_status' => 's',
        'ofw_details' => 's',
        'current_skill' => 's',
        'desired_skill' => 's',
        'unemployed_current_skill' => 's',
        'unemployed_desired_skill' => 's',
        'average_monthly_income' => 'd',
        'emerging_diseases' => 's',
        'disability' => 's',
        'source_profile_json' => 's',
        'notes' => 's',
    ];
}

function append_family_member_extra_fields(mysqli $conn, array $payload, array &$columns, array &$values, string &$types): void {
    foreach (family_member_extra_profile_fields() as $column => $type) {
        if (!column_exists($conn, 'family_members', $column)) continue;
        if (!array_key_exists($column, $payload)) continue;
        $columns[] = $column;
        $values[] = $payload[$column];
        $types .= $type;
    }
}

function household_member_count(mysqli $conn, int $householdId): int {
    if ($householdId <= 0 || !table_exists($conn, 'family_members')) return 0;
    return (int)scalar($conn, "SELECT COUNT(*) FROM family_members WHERE household_id={$householdId} AND is_active=1", 0);
}

function upsert_household_head_member(mysqli $conn, int $householdId, array $payload, ?array $file, ?int $existingHeadId = null): int {
    ensure_family_upgrade_schema($conn);
    $fullName = trim((string)($payload['full_name'] ?? ''));
    if ($householdId <= 0 || $fullName === '') return 0;
    [$firstName, $middleName, $lastName, $suffixName] = split_person_name($fullName);
    $sex = ($payload['sex'] ?? '') !== '' ? (string)$payload['sex'] : null;
    $birthdate = ($payload['birthdate'] ?? '') !== '' ? (string)$payload['birthdate'] : null;
    $age = calculate_age_from_birthdate($birthdate);
    if ($age === null && ($payload['age'] ?? '') !== '') $age = (int)$payload['age'];
    $contact = trim((string)($payload['contact_number'] ?? '')) ?: null;
    $civil = trim((string)($payload['civil_status'] ?? '')) ?: null;
    $occupation = trim((string)($payload['occupation'] ?? '')) ?: null;
    $education = trim((string)($payload['education_level'] ?? '')) ?: null;
    $status = trim((string)($payload['member_status'] ?? 'Living in household')) ?: 'Living in household';
    $remarks = trim((string)($payload['remarks'] ?? '')) ?: null;
    $photoPath = $file ? upload_image_file($file, 'public/uploads/family_members') : null;
    if ($existingHeadId) {
        $existing = fetch_one($conn, "SELECT member_photo_path FROM family_members WHERE member_id=" . (int)$existingHeadId);
        if (!$photoPath) $photoPath = $existing['member_photo_path'] ?? null;
        $sets = [
            'full_name=?', 'first_name=?', 'middle_name=?', 'last_name=?', 'suffix_name=?',
            "relationship_to_head='Head'", 'is_household_head=1', 'sex=?', 'birthdate=?', 'age=?', 'contact_number=?',
            'civil_status=?', 'occupation=?', 'education_level=?', 'member_status=?', 'member_photo_path=?', 'remarks=?', 'is_active=1'
        ];
        $values = [$fullName, $firstName, $middleName, $lastName, $suffixName, $sex, $birthdate, $age, $contact, $civil, $occupation, $education, $status, $photoPath, $remarks];
        $types = 'sssssssisisssss';
        foreach (family_member_extra_profile_fields() as $column => $type) {
            if (!column_exists($conn, 'family_members', $column) || !array_key_exists($column, $payload)) continue;
            $sets[] = $column . '=?';
            $values[] = $payload[$column];
            $types .= $type;
        }
        $types .= 'ii';
        $values[] = $existingHeadId;
        $values[] = $householdId;
        $stmt = $conn->prepare('UPDATE family_members SET ' . implode(', ', $sets) . ' WHERE member_id=? AND household_id=?');
        if (!$stmt) return 0;
        $stmt->bind_param($types, ...$values);
        $stmt->execute();
        $stmt->close();
        return $existingHeadId;
    }
    $columns = ['household_id','is_household_head','full_name','first_name','middle_name','last_name','suffix_name','relationship_to_head','sex','birthdate','age','contact_number','civil_status','occupation','education_level','member_status','member_photo_path','remarks','is_active'];
    $values = [$householdId,1,$fullName,$firstName,$middleName,$lastName,$suffixName,'Head',$sex,$birthdate,$age,$contact,$civil,$occupation,$education,$status,$photoPath,$remarks,1];
    $types = 'iissssssssisssssssi';
    append_family_member_extra_fields($conn, $payload, $columns, $values, $types);
    $placeholders = implode(',', array_fill(0, count($columns), '?'));
    $stmt = $conn->prepare('INSERT INTO family_members (' . implode(',', $columns) . ') VALUES (' . $placeholders . ')');
    if (!$stmt) return 0;
    $stmt->bind_param($types, ...$values);
    $stmt->execute();
    $id = (int)$stmt->insert_id;
    $stmt->close();
    return $id;
}

function save_household_member(mysqli $conn, int $householdId, array $payload, ?array $file, int $userId = 0): int {
    ensure_family_upgrade_schema($conn);
    $fullName = trim((string)($payload['full_name'] ?? ''));
    if ($householdId <= 0 || $fullName === '') return 0;
    [$firstName, $middleName, $lastName, $suffixName] = split_person_name($fullName);
    $relationship = trim((string)($payload['relationship_to_head'] ?? 'Member')) ?: 'Member';
    $sex = ($payload['sex'] ?? '') !== '' ? (string)$payload['sex'] : null;
    $birthdate = ($payload['birthdate'] ?? '') !== '' ? (string)$payload['birthdate'] : null;
    $age = calculate_age_from_birthdate($birthdate);
    if ($age === null && ($payload['age'] ?? '') !== '') $age = (int)$payload['age'];
    $contact = trim((string)($payload['contact_number'] ?? '')) ?: null;
    $email = trim((string)($payload['email_address'] ?? '')) ?: null;
    $civil = trim((string)($payload['civil_status'] ?? '')) ?: null;
    $occupation = trim((string)($payload['occupation'] ?? '')) ?: null;
    $education = trim((string)($payload['education_level'] ?? '')) ?: null;
    $status = trim((string)($payload['member_status'] ?? 'Living in household')) ?: 'Living in household';
    $remarks = trim((string)($payload['remarks'] ?? '')) ?: null;
    $memberTags = normalize_member_tags($payload['member_tags'] ?? []);
    $isFarmer = !empty($payload['is_primary_farmer']) ? 1 : 0;
    $photoPath = $file ? upload_image_file($file, 'public/uploads/family_members') : null;

    $columns = ['household_id','is_household_head','full_name','first_name','middle_name','last_name','suffix_name','relationship_to_head','sex','birthdate','age','contact_number'];
    $values = [$householdId,0,$fullName,$firstName,$middleName,$lastName,$suffixName,$relationship,$sex,$birthdate,$age,$contact];
    $types = 'iissssssssis';

    foreach ([
        'email_address' => [$email, 's'],
        'civil_status' => [$civil, 's'],
        'occupation' => [$occupation, 's'],
        'education_level' => [$education, 's'],
        'member_status' => [$status, 's'],
        'member_photo_path' => [$photoPath, 's'],
        'is_primary_farmer' => [$isFarmer, 'i'],
        'remarks' => [$remarks, 's'],
        'member_tags' => [$memberTags, 's'],
        'is_active' => [1, 'i'],
    ] as $column => [$value, $type]) {
        if (column_exists($conn, 'family_members', $column)) {
            $columns[] = $column;
            $values[] = $value;
            $types .= $type;
        }
    }
    append_family_member_extra_fields($conn, $payload, $columns, $values, $types);

    $placeholders = implode(',', array_fill(0, count($columns), '?'));
    $sql = 'INSERT INTO family_members (' . implode(',', $columns) . ') VALUES (' . $placeholders . ')';
    $stmt = $conn->prepare($sql);
    if (!$stmt) return 0;
    $stmt->bind_param($types, ...$values);
    $stmt->execute();
    $id = (int)$stmt->insert_id;
    $stmt->close();
    sync_household_auto_fields($conn, $householdId);
    if ($id > 0 && $userId > 0) app_log($conn, $userId, 'FAMILY_MEMBERS', 'CREATE', $id, 'Added family member to household #' . $householdId);
    return $id;
}

function update_household_member(mysqli $conn, int $householdId, int $memberId, array $payload, ?array $file, int $userId = 0): int {
    ensure_family_upgrade_schema($conn);
    if ($householdId <= 0 || $memberId <= 0) return 0;
    $existing = fetch_one($conn, "SELECT * FROM family_members WHERE member_id=" . (int)$memberId . " AND household_id=" . (int)$householdId . " LIMIT 1");
    if (!$existing) return 0;
    $fullName = trim((string)($payload['full_name'] ?? ''));
    if ($fullName === '') return 0;
    [$firstName, $middleName, $lastName, $suffixName] = split_person_name($fullName);
    $relationship = trim((string)($payload['relationship_to_head'] ?? ($existing['relationship_to_head'] ?? 'Member'))) ?: 'Member';
    $sex = ($payload['sex'] ?? '') !== '' ? (string)$payload['sex'] : null;
    $birthdate = ($payload['birthdate'] ?? '') !== '' ? (string)$payload['birthdate'] : null;
    $age = calculate_age_from_birthdate($birthdate);
    if ($age === null && ($payload['age'] ?? '') !== '') $age = (int)$payload['age'];
    $contact = trim((string)($payload['contact_number'] ?? '')) ?: null;
    $email = trim((string)($payload['email_address'] ?? '')) ?: null;
    $civil = trim((string)($payload['civil_status'] ?? '')) ?: null;
    $occupation = trim((string)($payload['occupation'] ?? '')) ?: null;
    $education = trim((string)($payload['education_level'] ?? '')) ?: null;
    $status = trim((string)($payload['member_status'] ?? 'Living in household')) ?: 'Living in household';
    $remarks = trim((string)($payload['remarks'] ?? '')) ?: null;
    $memberTags = normalize_member_tags($payload['member_tags'] ?? []);
    $isFarmer = !empty($payload['is_primary_farmer']) ? 1 : 0;
    $photoPath = $file ? upload_image_file($file, 'public/uploads/family_members') : null;
    if (!$photoPath) $photoPath = $existing['member_photo_path'] ?? null;

    $sets = [
        'full_name=?', 'first_name=?', 'middle_name=?', 'last_name=?', 'suffix_name=?',
        'relationship_to_head=?', 'sex=?', 'birthdate=?', 'age=?', 'contact_number=?'
    ];
    $values = [$fullName, $firstName, $middleName, $lastName, $suffixName, $relationship, $sex, $birthdate, $age, $contact];
    $types = 'ssssssssis';

    foreach ([
        'email_address' => [$email, 's'],
        'civil_status' => [$civil, 's'],
        'occupation' => [$occupation, 's'],
        'education_level' => [$education, 's'],
        'member_status' => [$status, 's'],
        'member_photo_path' => [$photoPath, 's'],
        'is_primary_farmer' => [$isFarmer, 'i'],
        'remarks' => [$remarks, 's'],
        'member_tags' => [$memberTags, 's'],
    ] as $column => [$value, $type]) {
        if (column_exists($conn, 'family_members', $column)) {
            $sets[] = $column . '=?';
            $values[] = $value;
            $types .= $type;
        }
    }
    foreach (family_member_extra_profile_fields() as $column => $type) {
        if (!column_exists($conn, 'family_members', $column) || !array_key_exists($column, $payload)) continue;
        $sets[] = $column . '=?';
        $values[] = $payload[$column];
        $types .= $type;
    }

    $types .= 'ii';
    $values[] = $memberId;
    $values[] = $householdId;
    $sql = 'UPDATE family_members SET ' . implode(',', $sets) . ' WHERE member_id=? AND household_id=?';
    $stmt = $conn->prepare($sql);
    if (!$stmt) return 0;
    $stmt->bind_param($types, ...$values);
    $stmt->execute();
    $ok = $stmt->affected_rows >= 0;
    $stmt->close();
    sync_household_auto_fields($conn, $householdId);
    if ($ok && $userId > 0) app_log($conn, $userId, 'FAMILY_MEMBERS', 'UPDATE', $memberId, 'Updated family member in household #' . $householdId);
    return $ok ? $memberId : 0;
}

function household_record_status(mysqli $conn, int $householdId): string {
    if ($householdId <= 0 || !table_exists($conn, 'households') || !column_exists($conn, 'households', 'record_status')) return 'active';
    $status = (string)scalar($conn, "SELECT record_status FROM households WHERE household_id=" . (int)$householdId . " LIMIT 1", 'active');
    return in_array($status, ['active','archived','deleted'], true) ? $status : 'active';
}

function household_lifecycle_allowed(array $user, string $action): bool {
    $role = (string)($user['role'] ?? '');
    return match ($action) {
        'archive', 'reactivate' => in_array($role, ['task_force','mayor','admin','developer'], true),
        'delete' => in_array($role, ['mayor','admin','developer'], true),
        default => false,
    };
}

function set_household_record_status(mysqli $conn, int $householdId, string $status, int $userId = 0, ?string $reason = null): bool {
    if ($householdId <= 0 || !table_exists($conn, 'households')) return false;
    ensure_family_upgrade_schema($conn);
    $current = household_record_status($conn, $householdId);
    if (!in_array($status, ['active','archived','deleted'], true) || $current === $status) return false;

    $reason = trim((string)$reason) ?: null;
    $sql = null;
    if ($status === 'archived') {
        $sql = "UPDATE households SET record_status='archived', archived_at=NOW(), archived_by=?, archive_reason=?, reactivated_at=NULL, reactivated_by=NULL, deleted_at=NULL, deleted_by=NULL, delete_reason=NULL, updated_by=? WHERE household_id=?";
    } elseif ($status === 'active') {
        $sql = "UPDATE households SET record_status='active', reactivated_at=NOW(), reactivated_by=?, updated_by=?, household_id=household_id WHERE household_id=?";
    } elseif ($status === 'deleted') {
        $sql = "UPDATE households SET record_status='deleted', deleted_at=NOW(), deleted_by=?, delete_reason=?, updated_by=? WHERE household_id=?";
    }
    $stmt = $conn->prepare($sql);
    if (!$stmt) return false;
    if ($status === 'active') {
        $stmt->bind_param('iii', $userId, $userId, $householdId);
    } else {
        $stmt->bind_param('isii', $userId, $reason, $userId, $householdId);
    }
    $ok = $stmt->execute();
    $stmt->close();
    if ($ok && $userId > 0) {
        $map = ['active' => 'REACTIVATE', 'archived' => 'ARCHIVE', 'deleted' => 'DELETE'];
        $message = ucfirst($status) . ' family record';
        if ($reason) $message .= ' · ' . $reason;
        app_log($conn, $userId, 'HOUSEHOLDS', $map[$status] ?? 'UPDATE', $householdId, $message);
    }
    return $ok;
}

function household_search_sql(mysqli $conn, string $query = '', int $barangayId = 0, string $status = '', string $recordStatus = 'active', string $profileFilter = ''): string {
    $recordStatus = trim($recordStatus) ?: 'active';
    if (!in_array($recordStatus, ['active','archived','deleted','all'], true)) $recordStatus = 'active';
    $profileFilter = trim($profileFilter);
    $sql = "SELECT h.household_id, h.household_code, h.household_head_name, h.contact_number, h.household_size, h.profile_photo_path, h.head_member_id, h.record_status, h.archived_at, h.archive_reason, h.deleted_at, h.purok_sitio, h.full_address, h.registered_hh_no, h.official_hh_no, h.source_hh_no, b.barangay_name, q.qualification_status, q.score, chp.farming_household, chp.main_livelihood, chp.monthly_income_band, bf.is_4ps, bf.has_senior, bf.has_pwd, bf.has_solo_parent, bf.has_pregnant_member, bf.has_philhealth, bf.receives_lgu_assistance, bf.priority_level, GROUP_CONCAT(DISTINCT fm.full_name ORDER BY fm.is_household_head DESC, fm.full_name SEPARATOR ' | ') AS member_names, GROUP_CONCAT(DISTINCT CONCAT(fm.full_name, ' (', COALESCE(fm.relationship_to_head,'Member'), ')') ORDER BY fm.is_household_head DESC, fm.full_name SEPARATOR ' | ') AS member_summary, GROUP_CONCAT(DISTINCT qr.qr_reference ORDER BY qr.qr_reference SEPARATOR ' | ') AS qr_references FROM households h LEFT JOIN barangays b ON b.barangay_id = h.barangay_id LEFT JOIN household_qualification q ON q.household_id = h.household_id LEFT JOIN cbms_household_profiles chp ON chp.household_id = h.household_id LEFT JOIN household_beneficiary_flags bf ON bf.household_id = h.household_id LEFT JOIN family_members fm ON fm.household_id = h.household_id AND fm.is_active=1 LEFT JOIN qr_codes qr ON qr.household_id = h.household_id AND qr.qr_type='HOUSEHOLD' WHERE 1=1";
    if ($recordStatus !== 'all') {
        $sql .= " AND COALESCE(h.record_status,'active')='" . $conn->real_escape_string($recordStatus) . "'";
    }
    if ($query !== '') {
        $tokens = preg_split('/\s+/', trim($query)) ?: [];
        $tokenClauses = [];
        foreach ($tokens as $token) {
            $token = trim($token);
            if ($token === '') continue;
            $safe = '%' . $conn->real_escape_string($token) . '%';
            $tokenClauses[] = "(h.household_head_name LIKE '{$safe}' OR h.household_code LIKE '{$safe}' OR h.contact_number LIKE '{$safe}' OR COALESCE(h.purok_sitio,'') LIKE '{$safe}' OR COALESCE(h.full_address,'') LIKE '{$safe}' OR COALESCE(h.registered_hh_no,'') LIKE '{$safe}' OR COALESCE(h.official_hh_no,'') LIKE '{$safe}' OR COALESCE(h.source_hh_no,'') LIKE '{$safe}' OR COALESCE(b.barangay_name,'') LIKE '{$safe}' OR COALESCE(qr.qr_reference,'') LIKE '{$safe}' OR fm.full_name LIKE '{$safe}' OR COALESCE(fm.first_name,'') LIKE '{$safe}' OR COALESCE(fm.last_name,'') LIKE '{$safe}' OR COALESCE(fm.middle_name,'') LIKE '{$safe}' OR COALESCE(fm.relationship_to_head,'') LIKE '{$safe}')";
        }
        if ($tokenClauses) $sql .= ' AND ' . implode(' AND ', $tokenClauses);
    }
    if ($barangayId > 0) $sql .= " AND h.barangay_id=" . (int)$barangayId;
    if ($status !== '') $sql .= " AND q.qualification_status='" . $conn->real_escape_string($status) . "'";
    if ($profileFilter !== '') {
        $conditionMap = [
            'farmers' => "(UPPER(TRIM(COALESCE(fx.occupation,'')))='FARMER' OR COALESCE(fx.member_tags,'') LIKE '%Farmer%')",
            'pwd' => "(COALESCE(fx.disability,'') <> '' OR COALESCE(fx.member_tags,'') LIKE '%PWD%')",
            'senior_citizen' => "(COALESCE(fx.member_tags,'') LIKE '%Senior Citizen%' OR COALESCE(fx.age,0) >= 60)",
            'solo_parent' => "COALESCE(fx.member_tags,'') LIKE '%Solo Parent%'",
            'ofw' => "(COALESCE(fx.member_tags,'') LIKE '%OFW%' OR COALESCE(fx.ofw_details,'') <> '')",
            'unemployed' => "(COALESCE(fx.member_tags,'') LIKE '%Unemployed%' OR UPPER(TRIM(COALESCE(fx.occupation,'')))='UNEMPLOYED' OR UPPER(TRIM(COALESCE(fx.employment_status,'')))='UNEMPLOYED')",
            'pregnant' => "COALESCE(fx.member_tags,'') LIKE '%Pregnant%'",
            'breastfeeding' => "COALESCE(fx.member_tags,'') LIKE '%Breastfeeding Mother%'",
            'youth' => "(COALESCE(fx.member_tags,'') LIKE '%Youth%' OR (COALESCE(fx.age,0) BETWEEN 15 AND 30))",
            'farming_household' => "COALESCE(chp.farming_household,0)=1",
            '4ps' => "COALESCE(bf.is_4ps,0)=1",
            'philhealth' => "COALESCE(bf.has_philhealth,0)=1",
            'lgu_assistance' => "COALESCE(bf.receives_lgu_assistance,0)=1",
            'priority_high' => "COALESCE(bf.priority_level,'') IN ('High','Urgent')",
            'priority_medium' => "COALESCE(bf.priority_level,'')='Medium'"
        ];
        if (isset($conditionMap[$profileFilter])) {
            if (in_array($profileFilter, ['farming_household','4ps','philhealth','lgu_assistance','priority_high','priority_medium'], true)) {
                $sql .= " AND " . $conditionMap[$profileFilter];
            } else {
                $sql .= " AND EXISTS (SELECT 1 FROM family_members fx WHERE fx.household_id = h.household_id AND fx.is_active=1 AND " . $conditionMap[$profileFilter] . ")";
            }
        }
    }
    $sql .= " GROUP BY h.household_id ORDER BY h.household_id DESC";
    return $sql;
}

function household_qr_reference(mysqli $conn, int $householdId): string {
    if ($householdId <= 0 || !table_exists($conn, 'qr_codes')) return 'QR-HH-' . str_pad((string)$householdId, 6, '0', STR_PAD_LEFT);
    $row = fetch_one($conn, "SELECT qr_reference FROM qr_codes WHERE household_id={$householdId} AND qr_type='HOUSEHOLD' ORDER BY qr_id DESC LIMIT 1");
    return $row['qr_reference'] ?? ('QR-HH-' . str_pad((string)$householdId, 6, '0', STR_PAD_LEFT));
}

function household_profile_photo(mysqli $conn, int $householdId, ?string $fallback = null): string {
    $row = fetch_one($conn, "SELECT member_photo_path FROM family_members WHERE household_id={$householdId} AND is_household_head=1 AND is_active=1 ORDER BY member_id DESC LIMIT 1");
    return member_photo_url($row['member_photo_path'] ?? $fallback);
}

function household_head_snapshot(mysqli $conn, int $householdId): array {
    $row = fetch_one($conn, "SELECT fm.member_id, fm.full_name, fm.sex, fm.birthdate, fm.age, fm.contact_number, fm.member_photo_path, h.household_head_name, h.household_code, h.contact_number AS household_contact, h.profile_photo_path, b.barangay_name FROM households h LEFT JOIN family_members fm ON fm.household_id=h.household_id AND fm.is_household_head=1 AND fm.is_active=1 LEFT JOIN barangays b ON b.barangay_id=h.barangay_id WHERE h.household_id=" . (int)$householdId . " LIMIT 1") ?: [];
    $row['head_name'] = $row['full_name'] ?? ($row['household_head_name'] ?? '');
    $row['photo_url'] = household_profile_photo($conn, $householdId, $row['member_photo_path'] ?? ($row['profile_photo_path'] ?? null));
    return $row;
}

function family_showcase_cards(mysqli $conn, string $sql, int $limit = 6): string {
    $rows = fetch_all_assoc($conn, $sql . ' LIMIT ' . (int)$limit);
    if (!$rows) return '<div class="text-sm text-slate-500">No family profiles yet.</div>';
    $html = '<div class="app-family-grid">';
    foreach ($rows as $row) {
        $hid = (int)($row['household_id'] ?? 0);
        $photo = household_profile_photo($conn, $hid, $row['profile_photo_path'] ?? null);
        $score = isset($row['score']) ? number_format((float)$row['score'], 0) : null;
        $html .= '<article class="app-family-card">';
        $html .= '<div class="app-family-card-media"><img src="' . e($photo) . '" alt="Family photo" class="app-family-card-photo"></div>';
        $html .= '<div class="app-family-card-body">';
        $html .= '<div class="app-family-card-topline"><span class="app-family-card-brgy">' . e($row['barangay_name'] ?? 'Family profile') . '</span>';
        if ($score !== null) { $html .= '<span class="app-family-card-score">Score ' . e($score) . '</span>'; }
        $html .= '</div>';
        $html .= '<h3 class="app-family-card-title">' . e($row['household_head_name'] ?? 'Unnamed family') . '</h3>';
        $html .= '<div class="app-family-card-meta">';
        $html .= '<span>' . e($row['household_code'] ?? '-') . '</span>';
        $html .= '<span>' . e((string)($row['household_size'] ?? 0)) . ' members</span>';
        $html .= '</div>';
        if (isset($row['qualification_status'])) $html .= '<div class="mt-3">' . format_status_badge($row['qualification_status'] ?: 'For Validation') . '</div>';
        $html .= '<div class="app-family-card-actions">';
        $html .= '<a href="' . e(app_url('modules/agri/households/view.php?id=' . $hid)) . '" class="app-btn-outline">Open family</a>';
        $html .= '<a href="' . e(app_url('modules/agri/qr/print_household.php?household_id=' . $hid)) . '" target="_blank" class="app-btn-outline">QR card</a>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</article>';
    }
    return $html . '</div>';
}

function household_family_tree_html(array $members): string {
    if (!$members) return '<div class="text-sm text-slate-500">No family members encoded yet.</div>';
    $head = null; $others = [];
    foreach ($members as $m) {
        if (!empty($m['is_household_head'])) $head = $m; else $others[] = $m;
    }
    $html = '<div class="space-y-4">';
    if ($head) {
        $html .= '<div class="rounded-3xl border border-emerald-200 bg-emerald-50/70 p-4">';
        $html .= '<div class="text-xs uppercase tracking-wide text-emerald-700">Head of family</div>';
        $html .= '<div class="mt-2 flex items-center gap-4"><img src="' . e(member_photo_url($head['member_photo_path'] ?? null)) . '" class="h-16 w-16 rounded-2xl object-cover border" alt="Head photo"><div><div class="text-lg font-black">' . e($head['full_name']) . '</div><div class="text-sm text-slate-600">' . e(($head['relationship_to_head'] ?: 'Head') . (($head['sex'] ?? '') ? ' · ' . $head['sex'] : '')) . '</div></div></div>';
        $html .= '</div>';
    }
    if ($others) {
        $html .= '<div class="grid gap-3 sm:grid-cols-2">';
        foreach ($others as $m) {
            $html .= '<div class="rounded-2xl border border-slate-200 p-4 flex items-center gap-3">';
            $html .= '<img src="' . e(member_photo_url($m['member_photo_path'] ?? null)) . '" class="h-14 w-14 rounded-2xl object-cover border" alt="Member photo">';
            $html .= '<div><div class="font-semibold">' . e($m['full_name']) . '</div><div class="text-sm text-slate-500">' . e($m['relationship_to_head'] ?: 'Member') . (($m['occupation'] ?? '') ? ' · ' . $m['occupation'] : '') . '</div></div>';
            $html .= '</div>';
        }
        $html .= '</div>';
    }
    return $html . '</div>';
}

function household_event_participation_count(mysqli $conn, int $householdId): int {
    if ($householdId <= 0) return 0;
    return (int)scalar($conn, "SELECT COUNT(DISTINCT event_id) FROM event_attendance WHERE household_id={$householdId} AND attendance_status IN ('Present','Late')", 0);
}

function household_active_tree_count(mysqli $conn, int $householdId): int {
    if ($householdId <= 0) return 0;
    return (int)scalar($conn, "SELECT COALESCE(SUM(tree_count),0) FROM crops WHERE household_id={$householdId} AND crop_status='Active'", 0);
}

function sync_household_auto_fields(mysqli $conn, int $householdId): void {
    if ($householdId <= 0 || !table_exists($conn, 'households')) return;
    $attendanceCount = household_event_participation_count($conn, $householdId);
    $memberCount = household_member_count($conn, $householdId);
    if ($memberCount <= 0) $memberCount = 1;
    $stmt = $conn->prepare("UPDATE households SET program_participation_count=?, household_size=? WHERE household_id=?");
    if ($stmt) { $stmt->bind_param('iii', $attendanceCount, $memberCount, $householdId); $stmt->execute(); $stmt->close(); }
}

function ensure_household_assets(mysqli $conn, int $householdId, int $userId = 0): void {
    if ($householdId <= 0) return;
    $code = 'HH-' . str_pad((string)$householdId, 6, '0', STR_PAD_LEFT);
    $stmt = $conn->prepare("UPDATE households SET household_code = COALESCE(NULLIF(household_code,''), ?) WHERE household_id = ?");
    if ($stmt) { $stmt->bind_param('si', $code, $householdId); $stmt->execute(); $stmt->close(); }
    if (table_exists($conn, 'qr_codes')) {
        $prefix = app_setting($conn, 'qr_prefix_household', 'QR-HH');
        $qrRef = $prefix . '-' . str_pad((string)$householdId, 6, '0', STR_PAD_LEFT);
        $exists = (int)scalar($conn, "SELECT COUNT(*) FROM qr_codes WHERE household_id = {$householdId} AND qr_type = 'HOUSEHOLD'", 0);
        if ($exists === 0) {
            $payload = 'HOUSEHOLD:' . $householdId . '|CODE:' . $code;
            $stmt = $conn->prepare("INSERT INTO qr_codes (household_id, qr_type, qr_reference, qr_payload, generated_by) VALUES (?, 'HOUSEHOLD', ?, ?, ?)");
            if ($stmt) { $stmt->bind_param('issi', $householdId, $qrRef, $payload, $userId); $stmt->execute(); $stmt->close(); }
        }
    }
}

function ensure_crop_assets(mysqli $conn, int $cropId, int $householdId, int $userId = 0): void {
    if ($cropId <= 0) return;
    $code = 'CRP-' . str_pad((string)$cropId, 6, '0', STR_PAD_LEFT);
    $prefix = app_setting($conn, 'qr_prefix_crop', 'QR-CRP');
    $qrRef = $prefix . '-' . str_pad((string)$cropId, 6, '0', STR_PAD_LEFT);
    $stmt = $conn->prepare("UPDATE crops SET crop_code = COALESCE(NULLIF(crop_code,''), ?), qr_reference = COALESCE(NULLIF(qr_reference,''), ?) WHERE crop_id = ?");
    if ($stmt) { $stmt->bind_param('ssi', $code, $qrRef, $cropId); $stmt->execute(); $stmt->close(); }
    if (table_exists($conn, 'qr_codes')) {
        $exists = (int)scalar($conn, "SELECT COUNT(*) FROM qr_codes WHERE crop_id = {$cropId} AND qr_type = 'CROP'", 0);
        if ($exists === 0) {
            $payload = 'CROP:' . $cropId . '|HOUSEHOLD:' . $householdId . '|CODE:' . $code;
            $stmt = $conn->prepare("INSERT INTO qr_codes (household_id, crop_id, qr_type, qr_reference, qr_payload, generated_by) VALUES (?, ?, 'CROP', ?, ?, ?)");
            if ($stmt) { $stmt->bind_param('iissi', $householdId, $cropId, $qrRef, $payload, $userId); $stmt->execute(); $stmt->close(); }
        }
    }
}

function household_completion_metrics(mysqli $conn, int $householdId): array {
    if ($householdId <= 0) return ['profile'=>0,'interview'=>0,'crops'=>0,'monitoring'=>0,'overall'=>0];
    $household = fetch_one($conn, "SELECT household_head_name,barangay_id,contact_number,full_address,household_size,area_sqm,birthdate FROM households WHERE household_id={$householdId} LIMIT 1");
    $profileFields = 0;
    foreach (['household_head_name','barangay_id','contact_number','full_address','household_size','area_sqm','birthdate'] as $key) {
        if (!empty($household[$key])) $profileFields++;
    }
    $profile = (int)round(($profileFields / 7) * 100);
    $interview = (int)min(100, scalar($conn, "SELECT CASE WHEN COUNT(*)>0 THEN 100 ELSE 0 END FROM interviews WHERE household_id={$householdId} AND status='Completed'", 0));
    $crops = (int)min(100, scalar($conn, "SELECT CASE WHEN COUNT(*)>0 THEN 100 ELSE 0 END FROM crops WHERE household_id={$householdId} AND crop_status='Active'", 0));
    $monitoring = (int)min(100, scalar($conn, "SELECT CASE WHEN COUNT(*)>0 THEN 100 ELSE 0 END FROM monitoring_visits WHERE household_id={$householdId} AND monitoring_date >= DATE_SUB(CURDATE(), INTERVAL 180 DAY)", 0));
    $overall = (int)round(($profile + $interview + $crops + $monitoring) / 4);
    return compact('profile','interview','crops','monitoring','overall');
}

function get_household_snapshot(mysqli $conn, int $householdId): array {
    if ($householdId <= 0) return [];
    $row = fetch_one($conn, "SELECT h.household_id,h.household_code,h.reference_no,h.household_head_name,h.full_address,h.contact_number,h.birthdate,h.age,h.area_sqm,h.program_participation_count,h.household_size,h.purok_sitio,b.barangay_name,q.qualification_status,q.score,(SELECT COALESCE(SUM(tree_count),0) FROM crops c WHERE c.household_id=h.household_id AND c.crop_status='Active') AS total_trees,(SELECT COUNT(*) FROM crops c WHERE c.household_id=h.household_id AND c.crop_status='Active') AS crop_count,(SELECT qr_reference FROM qr_codes qr WHERE qr.household_id=h.household_id AND qr.qr_type='HOUSEHOLD' ORDER BY qr_id DESC LIMIT 1) AS qr_reference FROM households h LEFT JOIN barangays b ON b.barangay_id=h.barangay_id LEFT JOIN household_qualification q ON q.household_id=h.household_id WHERE h.household_id={$householdId} LIMIT 1");
    if (!$row) return [];
    $head = household_head_snapshot($conn, $householdId);
    $row['head_name'] = $head['head_name'] ?? ($row['household_head_name'] ?? '');
    $row['photo_url'] = $head['photo_url'] ?? household_profile_photo($conn, $householdId, null);
    $row['program_participation_count'] = household_event_participation_count($conn, $householdId);
    $row['total_trees'] = household_active_tree_count($conn, $householdId);
    $row['pending_actions'] = household_pending_actions($conn, $householdId);
    $row['completion'] = household_completion_metrics($conn, $householdId);
    $row['latest_interview'] = fetch_one($conn, "SELECT interview_date,register_no,current_number_of_trees,intended_number_of_trees,program_participation_count,primary_concern,source_of_livelihood,water_source,farm_location_notes,compliance_status,remarks,status,allowed_fruit_backyard,hh_planter_program,fruit_planting_backyard_program FROM interviews WHERE household_id={$householdId} ORDER BY interview_id DESC LIMIT 1") ?: [];
    $row['latest_monitoring'] = fetch_one($conn, "SELECT monitoring_date,tree_count_observed,fruiting_status,crop_condition,harvest_kg,issue_observed,action_recommended,monitoring_method,crop_id FROM monitoring_visits WHERE household_id={$householdId} ORDER BY monitoring_id DESC LIMIT 1") ?: [];
    $row['active_crops'] = fetch_all_assoc($conn, "SELECT crop_id,crop_name,tree_count,fruiting_status,current_condition,qr_reference FROM crops WHERE household_id={$householdId} AND crop_status='Active' ORDER BY crop_name LIMIT 10");
    if (!empty($row['latest_monitoring']['monitoring_date'])) {
        $row['next_monitoring_due'] = date('Y-m-d', strtotime($row['latest_monitoring']['monitoring_date'] . ' +90 days'));
    } else {
        $row['next_monitoring_due'] = null;
    }
    return $row;
}

function duplicate_household_matches(mysqli $conn, string $head, int $barangayId, string $contact = ''): array {
    $head = trim($head);
    $contact = trim($contact);
    if ($head === '' && $contact === '') return [];
    $clauses = [];
    if ($head !== '' && $barangayId > 0) $clauses[] = "(household_head_name='" . $conn->real_escape_string($head) . "' AND barangay_id=" . $barangayId . ")";
    if ($contact !== '') $clauses[] = "contact_number='" . $conn->real_escape_string($contact) . "'";
    if (!$clauses) return [];
    return fetch_all_assoc($conn, "SELECT household_id, household_head_name, household_code FROM households WHERE " . implode(' OR ', $clauses) . " LIMIT 5");
}

function save_household_document(mysqli $conn, int $householdId, array $payload, ?array $file, int $userId = 0): int {
    ensure_decision_support_schema($conn);
    repair_household_family_source_fields($conn);
    if ($householdId <= 0 || !$file) return 0;
    $path = upload_document_file($file);
    if (!$path) return 0;
    $type = trim((string)($payload['document_type'] ?? 'Evidence')) ?: 'Evidence';
    $title = trim((string)($payload['title'] ?? 'Untitled document')) ?: 'Untitled document';
    $notes = trim((string)($payload['notes'] ?? '')) ?: null;
    $stmt = $conn->prepare("INSERT INTO household_documents (household_id, document_type, title, file_path, notes, uploaded_by, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
    if (!$stmt) return 0;
    $stmt->bind_param('issssi', $householdId, $type, $title, $path, $notes, $userId);
    $stmt->execute();
    $id = (int)$stmt->insert_id;
    $stmt->close();
    return $id;
}



function household_json_hh_expr(string $alias = 'h'): string {
    return "COALESCE(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(" . $alias . ".source_profile_json, '$.hh_no')), ''), NULLIF(JSON_UNQUOTE(JSON_EXTRACT(" . $alias . ".source_profile_json, '$.hh_no_')), ''), NULLIF(JSON_UNQUOTE(JSON_EXTRACT(" . $alias . ".source_profile_json, '$.hh_no__')), ''))";
}

function household_sql_official_hh_expr(string $alias = 'h'): string {
    return "COALESCE(NULLIF(" . $alias . ".official_hh_no,''), NULLIF(" . $alias . ".registered_hh_no,''), NULLIF(" . $alias . ".source_hh_no,''), " . household_json_hh_expr($alias) . ")";
}

function household_sql_base_hh_expr(string $alias = 'h'): string {
    $official = household_sql_official_hh_expr($alias);
    return "CASE WHEN COALESCE(NULLIF(" . $alias . ".hh_base_no,''), '') <> '' THEN " . $alias . ".hh_base_no WHEN " . $official . " IS NULL OR TRIM(" . $official . ")='' THEN NULL WHEN INSTR(" . $official . ", '-') > 0 THEN TRIM(SUBSTRING_INDEX(" . $official . ", '-', 1)) ELSE TRIM(" . $official . ") END";
}

function household_sql_family_key_expr(string $houseAlias = 'h', string $memberAlias = 'fm'): string {
    $official = household_sql_official_hh_expr($houseAlias);
    return "CASE WHEN COALESCE(NULLIF(" . $houseAlias . ".source_family_key,''), '') <> '' THEN " . $houseAlias . ".source_family_key WHEN " . $official . " IS NOT NULL AND TRIM(" . $official . ")<>'' THEN CONCAT('BRGY|', COALESCE(" . $houseAlias . ".barangay_id,0), '|FAM|', UPPER(TRIM(" . $official . ")), '|HH|', " . $houseAlias . ".household_id) WHEN " . $memberAlias . ".member_id IS NOT NULL AND COALESCE(" . $memberAlias . ".is_household_head,0)=1 THEN CONCAT('HH|', " . $houseAlias . ".household_id, '|HEAD|', " . $memberAlias . ".member_id) ELSE CONCAT('HH|', " . $houseAlias . ".household_id) END";
}

function household_sql_group_key_expr(string $houseAlias = 'h', string $memberAlias = 'fm'): string {
    $base = household_sql_base_hh_expr($houseAlias);
    $official = household_sql_official_hh_expr($houseAlias);
    return "CASE WHEN " . $base . " IS NOT NULL AND TRIM(" . $base . ")<>'' AND TRIM(COALESCE(" . $houseAlias . ".hh_suffix,''))<>'' AND UPPER(TRIM(" . $houseAlias . ".hh_suffix)) REGEXP '^[A-Z]+$' AND TRIM(" . $official . ") REGEXP '^[0-9]+[[:space:]-]*[A-Za-z]+$' THEN CONCAT('BRGY|', COALESCE(" . $houseAlias . ".barangay_id,0), '|BASE|', UPPER(TRIM(" . $base . "))) WHEN COALESCE(NULLIF(" . $houseAlias . ".household_cluster_key,''), '')<>'' AND COALESCE(NULLIF(" . $houseAlias . ".source_block_label,''), '')<>'' AND UPPER(TRIM(" . $houseAlias . ".source_block_label)) REGEXP '^[A-Z]+$' THEN CONCAT('CLUSTER|', UPPER(TRIM(" . $houseAlias . ".household_cluster_key))) ELSE " . household_sql_family_key_expr($houseAlias, $memberAlias) . " END";
}

function household_is_letter_only_hh(?string $hhNo): bool {
    $hhNo = trim((string)$hhNo);
    return $hhNo !== '' && (bool)preg_match('/^[A-Za-z]+$/', $hhNo);
}

function household_letter_marker_index(string $marker): ?int {
    $marker = strtoupper(trim($marker));
    if ($marker === '' || !preg_match('/^[A-Z]+$/', $marker)) return null;
    $value = 0;
    $len = strlen($marker);
    for ($i = 0; $i < $len; $i++) {
        $value = ($value * 26) + (ord($marker[$i]) - 64);
    }
    return $value > 0 ? $value : null;
}

function household_backfill_letter_clusters(mysqli $conn): void {
    if (!table_exists($conn, 'households')) return;
    $rows = fetch_all_assoc($conn, "SELECT household_id, barangay_id, source_sheet_name, source_family_key, official_hh_no, registered_hh_no, source_hh_no FROM households WHERE COALESCE(record_status,'active') <> 'deleted' ORDER BY COALESCE(barangay_id,0) ASC, COALESCE(source_sheet_name,'') ASC, CASE WHEN source_family_key REGEXP '[|]SEQ[|][0-9]+' THEN CAST(REGEXP_SUBSTR(source_family_key, '[0-9]+$') AS UNSIGNED) ELSE household_id END ASC, household_id ASC");
    $lastContext = null;
    $lastLetterIndex = null;
    $activeCluster = null;
    $clusterCounter = 0;
    $stmt = $conn->prepare("UPDATE households SET source_block_label=?, household_cluster_key=? WHERE household_id=?");
    if (!$stmt) return;
    foreach ($rows as $row) {
        $context = (int)($row['barangay_id'] ?? 0) . '|' . strtoupper(trim((string)($row['source_sheet_name'] ?? '')));
        if ($context !== $lastContext) {
            $lastContext = $context;
            $lastLetterIndex = null;
            $activeCluster = null;
            $clusterCounter = 0;
        }
        $official = household_official_hh_no($row);
        $marker = household_is_letter_only_hh($official) ? strtoupper(trim((string)$official)) : '';
        $clusterKey = null;
        $blockLabel = null;
        if ($marker !== '') {
            $markerIndex = household_letter_marker_index($marker);
            if ($activeCluster === null || $lastLetterIndex === null || $markerIndex === null || $markerIndex !== ($lastLetterIndex + 1)) {
                $clusterCounter++;
                $base = strtoupper(preg_replace('/[^A-Z0-9]+/', '', trim((string)($row['source_sheet_name'] ?? ''))));
                if ($base === '') $base = 'BRGY' . (int)($row['barangay_id'] ?? 0);
                $activeCluster = 'CL-' . $base . '-' . str_pad((string)$clusterCounter, 4, '0', STR_PAD_LEFT);
            }
            $clusterKey = $activeCluster;
            $blockLabel = $marker;
            $lastLetterIndex = $markerIndex;
        } else {
            $lastLetterIndex = null;
            $activeCluster = null;
        }
        $householdId = (int)$row['household_id'];
        $stmt->bind_param('ssi', $blockLabel, $clusterKey, $householdId);
        $stmt->execute();
    }
    $stmt->close();
}

function repair_household_family_source_fields(mysqli $conn): void {
    static $done = false;
    if ($done || !table_exists($conn, 'households')) return;
    $done = true;
    foreach ([
        'official_hh_no varchar(60) DEFAULT NULL',
        'hh_base_no varchar(60) DEFAULT NULL',
        'hh_suffix varchar(30) DEFAULT NULL',
        'hh_is_excel_supplied tinyint(1) NOT NULL DEFAULT 0',
        'household_group_key varchar(190) DEFAULT NULL',
        'household_cluster_key varchar(190) DEFAULT NULL',
        'source_sheet_name varchar(120) DEFAULT NULL',
        'source_block_label varchar(50) DEFAULT NULL',
        'source_family_key varchar(190) DEFAULT NULL'
    ] as $sql) {
        if (preg_match('/^([a-z_]+)/i', $sql, $m) && !column_exists($conn, 'households', $m[1])) {
            @$conn->query('ALTER TABLE households ADD COLUMN ' . $sql);
        }
    }

    $memberHasJson = table_exists($conn, 'family_members') && column_exists($conn, 'family_members', 'source_profile_json');
    $memberHeadFilter = table_exists($conn, 'family_members') ? " AND (fm.is_household_head=1 OR UPPER(COALESCE(fm.relationship_to_head,''))='HEAD')" : '';
    if ($memberHasJson) {
        $headJson = "COALESCE(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(fm.source_profile_json, '$.hh_no')), ''), NULLIF(JSON_UNQUOTE(JSON_EXTRACT(fm.source_profile_json, '$.hh_no_')), ''), NULLIF(JSON_UNQUOTE(JSON_EXTRACT(fm.source_profile_json, '$.hh_no__')), ''), NULLIF(JSON_UNQUOTE(JSON_EXTRACT(fm.source_profile_json, '$.source_hh_no')), ''), NULLIF(JSON_UNQUOTE(JSON_EXTRACT(fm.source_profile_json, '$.registered_hh_no')), ''))";
        @$conn->query("UPDATE households h LEFT JOIN family_members fm ON fm.household_id=h.household_id" . $memberHeadFilter . " SET h.source_hh_no = CASE WHEN " . $headJson . " IS NULL OR TRIM(" . $headJson . ")='' THEN NULL ELSE " . $headJson . " END, h.registered_hh_no = CASE WHEN " . $headJson . " IS NULL OR TRIM(" . $headJson . ")='' THEN NULL ELSE " . $headJson . " END, h.official_hh_no = CASE WHEN " . $headJson . " IS NULL OR TRIM(" . $headJson . ")='' THEN NULL ELSE " . $headJson . " END, h.source_sheet_name = COALESCE(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(fm.source_profile_json, '$.sheet_name')), ''), h.source_sheet_name), h.hh_is_excel_supplied = CASE WHEN " . $headJson . " IS NULL OR TRIM(" . $headJson . ")='' THEN 0 ELSE 1 END WHERE COALESCE(h.record_status,'active') <> 'deleted'");
    }
    $official = household_sql_official_hh_expr('h');
    @$conn->query("UPDATE households h SET h.hh_base_no = CASE WHEN " . $official . " IS NULL OR TRIM(" . $official . ")='' THEN NULL WHEN TRIM(" . $official . ") REGEXP '^[0-9]+[[:space:]-]*[A-Za-z]+$' THEN TRIM(REGEXP_SUBSTR(TRIM(" . $official . "), '^[0-9]+')) WHEN TRIM(" . $official . ") REGEXP '^[0-9]+$' THEN TRIM(" . $official . ") ELSE TRIM(" . $official . ") END, h.hh_suffix = CASE WHEN " . $official . " IS NULL OR TRIM(" . $official . ")='' THEN NULL WHEN TRIM(" . $official . ") REGEXP '^[0-9]+[[:space:]-]*[A-Za-z]+$' THEN UPPER(REGEXP_REPLACE(TRIM(" . $official . "), '^[0-9]+[[:space:]-]*', '')) ELSE NULL END WHERE COALESCE(h.record_status,'active') <> 'deleted'");
    @$conn->query("UPDATE households h SET h.source_family_key = CASE WHEN COALESCE(NULLIF(h.source_family_key,''), '') <> '' THEN h.source_family_key WHEN COALESCE(NULLIF(h.reference_no,''), '') <> '' THEN CONCAT('REF|', UPPER(TRIM(h.reference_no))) WHEN " . $official . " IS NOT NULL AND TRIM(" . $official . ")<>'' THEN CONCAT('BRGY|', COALESCE(h.barangay_id,0), '|FAM|', UPPER(TRIM(" . $official . ")), '|HH|', h.household_id) ELSE CONCAT('BLANK|', COALESCE(h.barangay_id,0), '|HH|', h.household_id) END WHERE COALESCE(h.record_status,'active') <> 'deleted'");
    household_backfill_letter_clusters($conn);
    @$conn->query("UPDATE households h SET h.household_group_key = CASE WHEN COALESCE(NULLIF(h.hh_base_no,''), '')<>'' AND COALESCE(NULLIF(h.hh_suffix,''), '')<>'' AND UPPER(TRIM(h.hh_suffix)) REGEXP '^[A-Z]+$' AND TRIM(" . $official . ") REGEXP '^[0-9]+[[:space:]-]*[A-Za-z]+$' THEN CONCAT('BRGY|', COALESCE(h.barangay_id,0), '|BASE|', UPPER(TRIM(h.hh_base_no))) WHEN COALESCE(NULLIF(h.hh_base_no,''), '')='' AND COALESCE(NULLIF(h.household_cluster_key,''), '')<>'' AND COALESCE(NULLIF(h.source_block_label,''), '')<>'' AND UPPER(TRIM(h.source_block_label)) REGEXP '^[A-Z]+$' THEN CONCAT('CLUSTER|', UPPER(TRIM(h.household_cluster_key))) ELSE COALESCE(NULLIF(h.source_family_key,''), CONCAT('BLANK|', COALESCE(h.barangay_id,0), '|HH|', h.household_id)) END WHERE COALESCE(h.record_status,'active') <> 'deleted'");
}

function household_official_hh_no(array $household): ?string {
    foreach (['official_hh_no', 'registered_hh_no', 'source_hh_no'] as $key) {
        $value = trim((string)($household[$key] ?? ''));
        if ($value !== '') return $value;
    }
    $json = trim((string)($household['json_hh_no'] ?? ''));
    return $json !== '' ? $json : null;
}

function household_hh_base_no(?string $hhNo): ?string {
    $hhNo = trim((string)$hhNo);
    if ($hhNo == '') return null;
    if (preg_match('/^\s*([0-9]+)\s*[- ]?\s*([A-Za-z]+)\s*$/', $hhNo, $m)) {
        return trim($m[1]);
    }
    if (preg_match('/^\s*([0-9]+)\s*$/', $hhNo, $m)) {
        return trim($m[1]);
    }
    if (preg_match('/^\s*[A-Za-z]+\s*$/', $hhNo)) {
        return null;
    }
    return $hhNo;
}

function household_hh_suffix(?string $hhNo): ?string {
    $hhNo = trim((string)$hhNo);
    if ($hhNo == '') return null;
    if (preg_match('/^\s*([0-9]+)\s*[- ]?\s*([A-Za-z]+)\s*$/', $hhNo, $m)) {
        return strtoupper(trim($m[2]));
    }
    if (preg_match('/^\s*([A-Za-z]+)\s*$/', $hhNo, $m)) {
        return strtoupper(trim($m[1]));
    }
    return null;
}

function household_hh_has_letter_suffix(?string $hhNo): bool {
    $suffix = household_hh_suffix($hhNo);
    return $suffix !== null && $suffix !== '' && (bool)preg_match('/^[A-Z]+$/', $suffix);
}

function household_family_runtime_map(mysqli $conn): array {
    static $cache = null;
    if ($cache !== null) return $cache;
    repair_household_family_source_fields($conn);
    $cache = ['households'=>[], 'families'=>[], 'barangays'=>[], 'household_to_group'=>[], 'group_households'=>[]];
    if (!table_exists($conn, 'households')) return $cache;
    $sql = "SELECT h.household_id, h.barangay_id, h.source_family_key, h.household_group_key, h.household_cluster_key, h.source_block_label, h.source_sheet_name, h.official_hh_no, h.registered_hh_no, h.source_hh_no, h.hh_base_no, h.hh_suffix, COALESCE(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(fm.source_profile_json, '$.hh_no')), ''), NULLIF(JSON_UNQUOTE(JSON_EXTRACT(fm.source_profile_json, '$.hh_no_')), ''), NULLIF(JSON_UNQUOTE(JSON_EXTRACT(fm.source_profile_json, '$.hh_no__')), '')) AS head_json_hh_no FROM households h LEFT JOIN family_members fm ON fm.household_id=h.household_id AND (fm.is_household_head=1 OR UPPER(COALESCE(fm.relationship_to_head,''))='HEAD') WHERE COALESCE(h.record_status,'active') <> 'deleted' ORDER BY COALESCE(h.barangay_id,0) ASC, COALESCE(h.source_sheet_name,'') ASC, CASE WHEN h.source_family_key REGEXP '[|]SEQ[|][0-9]+' THEN CAST(REGEXP_SUBSTR(h.source_family_key, '[0-9]+$') AS UNSIGNED) ELSE h.household_id END ASC, h.household_id ASC";
    $rows = fetch_all_assoc($conn, $sql);
    $lastContext = null;
    $lastLetterIndex = null;
    $activeClusterKey = null;
    $clusterCounter = 0;
    foreach ($rows as $row) {
        $barangayId = (int)($row['barangay_id'] ?? 0);
        $official = household_official_hh_no($row);
        if ($official === null) {
            $jsonHead = trim((string)($row['head_json_hh_no'] ?? ''));
            if ($jsonHead !== '') $official = $jsonHead;
        }
        $base = trim((string)($row['hh_base_no'] ?? ''));
        if ($base === '') $base = (string)(household_hh_base_no($official) ?? '');
        $familyKey = trim((string)($row['source_family_key'] ?? ''));
        if ($familyKey === '') {
            $familyKey = $official !== null && trim($official) !== '' ? 'BRGY|' . $barangayId . '|FAM|' . strtoupper(trim($official)) . '|HH|' . (int)$row['household_id'] : 'BLANK|' . $barangayId . '|HH|' . (int)$row['household_id'];
        }
        $context = $barangayId . '|' . strtoupper(trim((string)($row['source_sheet_name'] ?? '')));
        if ($context !== $lastContext) {
            $lastContext = $context;
            $lastLetterIndex = null;
            $activeClusterKey = null;
            $clusterCounter = 0;
        }
        $householdKey = trim((string)($row['household_group_key'] ?? ''));
        $marker = household_is_letter_only_hh($official) ? strtoupper(trim((string)$official)) : '';
        if ($base !== '' && household_hh_has_letter_suffix($official) && !household_is_letter_only_hh($official)) {
            $householdKey = 'BRGY|' . $barangayId . '|BASE|' . strtoupper($base);
            $lastLetterIndex = null;
            $activeClusterKey = null;
        } elseif ($marker !== '') {
            $markerIndex = household_letter_marker_index($marker);
            if ($activeClusterKey === null || $lastLetterIndex === null || $markerIndex === null || $markerIndex !== ($lastLetterIndex + 1)) {
                $clusterCounter++;
                $baseToken = strtoupper(preg_replace('/[^A-Z0-9]+/', '', trim((string)($row['source_sheet_name'] ?? ''))));
                if ($baseToken === '') $baseToken = 'BRGY' . $barangayId;
                $activeClusterKey = 'CL-' . $baseToken . '-' . str_pad((string)$clusterCounter, 4, '0', STR_PAD_LEFT);
            }
            $householdKey = 'CLUSTER|' . $activeClusterKey;
            $lastLetterIndex = $markerIndex;
        } else {
            if ($householdKey === '') $householdKey = $familyKey;
            $lastLetterIndex = null;
            $activeClusterKey = null;
        }
        $cache['families'][$familyKey] = true;
        $cache['households'][$householdKey] = true;
        $cache['household_to_group'][(int)$row['household_id']] = $householdKey;
        if (!isset($cache['group_households'][$householdKey])) $cache['group_households'][$householdKey] = [];
        $cache['group_households'][$householdKey][] = (int)$row['household_id'];
        if (!isset($cache['barangays'][$barangayId])) $cache['barangays'][$barangayId] = ['households'=>[], 'families'=>[]];
        $cache['barangays'][$barangayId]['families'][$familyKey] = true;
        $cache['barangays'][$barangayId]['households'][$householdKey] = true;
    }
    foreach ($cache['barangays'] as $id => $vals) {
        $cache['barangays'][$id] = ['total_households' => count($vals['households']), 'total_families' => count($vals['families'])];
    }
    return $cache;
}

function household_group_context(mysqli $conn, int $householdId): array {
    $house = fetch_one($conn, "SELECT household_id, barangay_id, household_head_name, registered_hh_no, source_hh_no, official_hh_no, household_code, household_group_key, household_cluster_key, source_block_label FROM households WHERE household_id=" . (int)$householdId . " LIMIT 1");
    if (!$house) return ['family_count' => 0, 'member_count' => 0, 'official_hh_no' => null, 'base_no' => null, 'suffix' => null, 'related_families' => []];
    $official = household_official_hh_no($house);
    $baseNo = household_hh_base_no($official);
    $suffix = household_hh_suffix($official);
    $runtime = household_family_runtime_map($conn);
    $groupKey = (string)($runtime['household_to_group'][(int)$householdId] ?? trim((string)($house['household_group_key'] ?? '')));
    $relatedIds = $groupKey !== '' ? array_values(array_unique(array_map('intval', $runtime['group_households'][$groupKey] ?? [(int)$householdId]))) : [(int)$householdId];
    if (!$relatedIds) $relatedIds = [(int)$householdId];
    $idList = implode(',', array_map('intval', $relatedIds));
    $rows = fetch_all_assoc($conn, "SELECT h.household_id, h.household_head_name, h.household_code, COALESCE(NULLIF(h.official_hh_no,''), NULLIF(h.registered_hh_no,''), NULLIF(h.source_hh_no,'')) AS official_hh_no, COUNT(fm.member_id) AS member_count FROM households h LEFT JOIN family_members fm ON fm.household_id=h.household_id AND COALESCE(fm.is_active,1)=1 WHERE h.household_id IN (" . $idList . ") GROUP BY h.household_id, h.household_head_name, h.household_code, official_hh_no ORDER BY h.household_id ASC");
    $memberTotal = 0;
    foreach ($rows as &$row) { $memberTotal += (int)($row['member_count'] ?? 0); }
    unset($row);
    if (!$rows) {
        $members = (int)scalar($conn, "SELECT COUNT(*) FROM family_members WHERE household_id=" . (int)$householdId . " AND is_active=1", 0);
        $rows[] = ['household_id' => (int)$house['household_id'], 'household_head_name' => $house['household_head_name'], 'official_hh_no' => $official, 'household_code' => $house['household_code'] ?? '', 'member_count' => $members];
        $memberTotal = $members;
    }
    return ['family_count' => count($rows), 'member_count' => $memberTotal, 'official_hh_no' => $official, 'base_no' => $baseNo, 'suffix' => $suffix, 'related_families' => $rows];
}

function total_household_groups(mysqli $conn): int {
    $map = household_family_runtime_map($conn);
    return count($map['households']);
}

function total_family_units(mysqli $conn): int {
    $map = household_family_runtime_map($conn);
    return count($map['families']);
}

if (!function_exists('ensure_decision_support_schema')) {
    function ensure_decision_support_schema($conn = null) {
        return true;
    }
}

if (isset($conn) && $conn instanceof mysqli) {
    apply_runtime_db_fixes($conn);
    ensure_harvest_schema($conn);
    ensure_family_upgrade_schema($conn);
    ensure_decision_support_schema($conn);
    repair_household_family_source_fields($conn);
}

