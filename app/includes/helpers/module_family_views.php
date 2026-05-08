<?php
require_once dirname(__DIR__, 3) . '/app/bootstrap.php';
app_require('app/includes/helpers/core.php');
app_require('app/includes/helpers/households.php');

function ensure_module_family_support_schema(mysqli $conn): void {
    if (!table_exists($conn, 'cbms_household_profiles')) {
        @$conn->query("CREATE TABLE cbms_household_profiles (
            cbms_household_profile_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            household_id BIGINT UNSIGNED NOT NULL,
            tenure_status VARCHAR(120) NULL,
            housing_materials VARCHAR(255) NULL,
            toilet_type VARCHAR(150) NULL,
            water_source VARCHAR(150) NULL,
            electricity_source VARCHAR(120) NULL,
            internet_access VARCHAR(120) NULL,
            waste_disposal_method VARCHAR(150) NULL,
            monthly_household_income DECIMAL(12,2) NULL,
            poverty_status VARCHAR(120) NULL,
            housing_type VARCHAR(120) NULL,
            livelihood_summary TEXT NULL,
            crop_summary TEXT NULL,
            vehicle_count INT NOT NULL DEFAULT 0,
            farming_household TINYINT(1) NOT NULL DEFAULT 0,
            farm_area_hectares DECIMAL(10,2) NULL,
            fruit_tree_count_estimate INT NULL,
            special_program_notes TEXT NULL,
            notes TEXT NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_cbms_household_profile_household (household_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    }
    if (!table_exists($conn, 'cbms_housing_profiles')) {
        @$conn->query("CREATE TABLE cbms_housing_profiles (
            housing_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            household_id BIGINT UNSIGNED NOT NULL,
            housing_type VARCHAR(120) NULL,
            roof_material VARCHAR(120) NULL,
            wall_material VARCHAR(120) NULL,
            tenure_status VARCHAR(120) NULL,
            electricity_source VARCHAR(120) NULL,
            notes TEXT NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_cbms_housing_household (household_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    }
    if (!table_exists($conn, 'cbms_livelihood_profiles')) {
        @$conn->query("CREATE TABLE cbms_livelihood_profiles (
            livelihood_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            household_id BIGINT UNSIGNED NOT NULL,
            primary_income_source VARCHAR(150) NULL,
            main_livelihood VARCHAR(150) NULL,
            monthly_income_band VARCHAR(100) NULL,
            employment_notes TEXT NULL,
            notes TEXT NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_cbms_livelihood_household (household_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    }
    if (!table_exists($conn, 'cbms_sanitation_profiles')) {
        @$conn->query("CREATE TABLE cbms_sanitation_profiles (
            sanitation_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            household_id BIGINT UNSIGNED NOT NULL,
            water_source VARCHAR(150) NULL,
            toilet_type VARCHAR(150) NULL,
            waste_disposal VARCHAR(150) NULL,
            drainage_status VARCHAR(150) NULL,
            notes TEXT NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_cbms_sanitation_household (household_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    }
    if (!table_exists($conn, 'cbms_asset_records')) {
        @$conn->query("CREATE TABLE cbms_asset_records (
            asset_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            household_id BIGINT UNSIGNED NOT NULL,
            asset_name VARCHAR(150) NOT NULL,
            asset_category VARCHAR(120) NULL,
            asset_brand VARCHAR(120) NULL,
            asset_model VARCHAR(120) NULL,
            quantity INT NOT NULL DEFAULT 1,
            notes TEXT NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_cbms_asset_household (household_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    }

    if (!table_exists($conn, 'cbms_pets')) {
        @$conn->query("CREATE TABLE cbms_pets (
            pet_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            household_id BIGINT UNSIGNED NOT NULL,
            pet_type VARCHAR(150) NOT NULL,
            animal_name VARCHAR(120) NULL,
            quantity INT NOT NULL DEFAULT 1,
            notes TEXT NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_cbms_pets_household (household_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    }
    if (!table_exists($conn, 'cbms_vehicles')) {
        @$conn->query("CREATE TABLE cbms_vehicles (
            cbms_vehicle_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            household_id BIGINT UNSIGNED NOT NULL,
            vehicle_type VARCHAR(150) NOT NULL,
            vehicle_brand VARCHAR(120) NULL,
            vehicle_model VARCHAR(120) NULL,
            year_model VARCHAR(20) NULL,
            plate_number VARCHAR(60) NULL,
            color VARCHAR(60) NULL,
            ownership_status VARCHAR(80) NULL,
            registration_status VARCHAR(80) NULL,
            quantity INT NOT NULL DEFAULT 1,
            notes TEXT NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_cbms_vehicles_household (household_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    }
    if (table_exists($conn, 'cbms_pets')) {
        if (!column_exists($conn, 'cbms_pets', 'pet_type') && column_exists($conn, 'cbms_pets', 'animal_type')) @$conn->query("ALTER TABLE cbms_pets ADD COLUMN pet_type VARCHAR(150) NULL AFTER household_id");
        if (!column_exists($conn, 'cbms_pets', 'quantity')) @$conn->query("ALTER TABLE cbms_pets ADD COLUMN quantity INT NOT NULL DEFAULT 1 AFTER animal_name");
        if (!column_exists($conn, 'cbms_pets', 'notes')) @$conn->query("ALTER TABLE cbms_pets ADD COLUMN notes TEXT NULL AFTER quantity");
        if (column_exists($conn, 'cbms_pets', 'animal_type') && !column_exists($conn, 'cbms_pets', 'pet_type')) {
            @$conn->query("ALTER TABLE cbms_pets ADD COLUMN pet_type VARCHAR(150) NULL AFTER household_id");
        }
        if (column_exists($conn, 'cbms_pets', 'animal_type')) @$conn->query("UPDATE cbms_pets SET pet_type = COALESCE(NULLIF(TRIM(pet_type), ''), animal_type) WHERE COALESCE(TRIM(animal_type), '') <> ''");
        if (column_exists($conn, 'cbms_pets', 'pet_count')) @$conn->query("UPDATE cbms_pets SET quantity = CASE WHEN COALESCE(pet_count,0) > 0 THEN pet_count ELSE COALESCE(quantity,1) END");
    }
    if (table_exists($conn, 'cbms_vehicles')) {
        if (!column_exists($conn, 'cbms_vehicles', 'vehicle_brand')) @$conn->query("ALTER TABLE cbms_vehicles ADD COLUMN vehicle_brand VARCHAR(120) NULL AFTER vehicle_type");
        if (!column_exists($conn, 'cbms_vehicles', 'vehicle_model')) @$conn->query("ALTER TABLE cbms_vehicles ADD COLUMN vehicle_model VARCHAR(120) NULL AFTER vehicle_brand");
        if (!column_exists($conn, 'cbms_vehicles', 'year_model')) @$conn->query("ALTER TABLE cbms_vehicles ADD COLUMN year_model VARCHAR(20) NULL AFTER vehicle_model");
        if (!column_exists($conn, 'cbms_vehicles', 'plate_number')) @$conn->query("ALTER TABLE cbms_vehicles ADD COLUMN plate_number VARCHAR(60) NULL AFTER year_model");
        if (!column_exists($conn, 'cbms_vehicles', 'color')) @$conn->query("ALTER TABLE cbms_vehicles ADD COLUMN color VARCHAR(60) NULL AFTER plate_number");
        if (!column_exists($conn, 'cbms_vehicles', 'ownership_status')) @$conn->query("ALTER TABLE cbms_vehicles ADD COLUMN ownership_status VARCHAR(80) NULL AFTER color");
        if (!column_exists($conn, 'cbms_vehicles', 'registration_status')) @$conn->query("ALTER TABLE cbms_vehicles ADD COLUMN registration_status VARCHAR(80) NULL AFTER ownership_status");
        if (!column_exists($conn, 'cbms_vehicles', 'quantity')) @$conn->query("ALTER TABLE cbms_vehicles ADD COLUMN quantity INT NOT NULL DEFAULT 1 AFTER registration_status");
        if (!column_exists($conn, 'cbms_vehicles', 'notes')) @$conn->query("ALTER TABLE cbms_vehicles ADD COLUMN notes TEXT NULL AFTER quantity");
        if (column_exists($conn, 'cbms_vehicles', 'vehicle_count')) @$conn->query("UPDATE cbms_vehicles SET quantity = CASE WHEN COALESCE(vehicle_count,0) > 0 THEN vehicle_count ELSE COALESCE(quantity,1) END");
    }
    if (table_exists($conn, 'cbms_asset_records')) {
        if (!column_exists($conn, 'cbms_asset_records', 'asset_category')) @$conn->query("ALTER TABLE cbms_asset_records ADD COLUMN asset_category VARCHAR(120) NULL AFTER asset_name");
        if (!column_exists($conn, 'cbms_asset_records', 'asset_brand')) @$conn->query("ALTER TABLE cbms_asset_records ADD COLUMN asset_brand VARCHAR(120) NULL AFTER asset_category");
        if (!column_exists($conn, 'cbms_asset_records', 'asset_model')) @$conn->query("ALTER TABLE cbms_asset_records ADD COLUMN asset_model VARCHAR(120) NULL AFTER asset_brand");
    }
    if (table_exists($conn, 'cbms_household_profiles')) {
        $householdAdds = [
            "farming_household TINYINT(1) NOT NULL DEFAULT 0 AFTER vehicle_count",
            "farm_area_hectares DECIMAL(10,2) NULL AFTER farming_household",
            "fruit_tree_count_estimate INT NULL AFTER farm_area_hectares",
            "special_program_notes TEXT NULL AFTER fruit_tree_count_estimate"
        ];
        foreach ($householdAdds as $sql) {
            if (preg_match('/^([a-z_]+)/i', $sql, $m) && !column_exists($conn, 'cbms_household_profiles', $m[1])) {
                @$conn->query("ALTER TABLE cbms_household_profiles ADD COLUMN " . $sql);
            }
        }
    }
    if (!table_exists($conn, 'household_beneficiary_flags')) {
        @$conn->query("CREATE TABLE household_beneficiary_flags (
            beneficiary_flag_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            household_id BIGINT UNSIGNED NOT NULL,
            is_4ps TINYINT(1) NOT NULL DEFAULT 0,
            has_senior TINYINT(1) NOT NULL DEFAULT 0,
            has_pwd TINYINT(1) NOT NULL DEFAULT 0,
            has_solo_parent TINYINT(1) NOT NULL DEFAULT 0,
            has_pregnant_member TINYINT(1) NOT NULL DEFAULT 0,
            has_philhealth TINYINT(1) NOT NULL DEFAULT 0,
            receives_lgu_assistance TINYINT(1) NOT NULL DEFAULT 0,
            priority_level VARCHAR(50) NULL,
            priority_notes TEXT NULL,
            updated_by BIGINT NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_household_beneficiary_flags_household (household_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    }
}

function cbms_table_pk_column(mysqli $conn, string $table): string {
    foreach (['pet_id', 'cbms_vehicle_id', 'asset_id', 'housing_id', 'livelihood_id', 'sanitation_id', 'cbms_household_profile_id'] as $col) {
        if (column_exists($conn, $table, $col)) return $col;
    }
    return 'id';
}

function cbms_table_quantity_column(mysqli $conn, string $table): string {
    foreach (['quantity', 'pet_count', 'vehicle_count'] as $col) {
        if (column_exists($conn, $table, $col)) return $col;
    }
    return 'quantity';
}

function cbms_asset_quantity(array $row): int {
    foreach (['quantity', 'pet_count', 'vehicle_count'] as $col) {
        if (isset($row[$col]) && $row[$col] !== null && $row[$col] !== '') return max(1, (int)$row[$col]);
    }
    return 1;
}

function cbms_asset_record_label(array $row, string $fallback = 'Record'): string {
    foreach (['pet_type','animal_type','asset_name','vehicle_type','type_name'] as $col) {
        if (!empty($row[$col])) return (string)$row[$col];
    }
    return $fallback;
}

function cbms_pet_type_options(): array {
    return [
        'Cat','Dog','Chicken','Rooster','Duck','Goose','Turkey','Rabbit','Goat','Sheep','Pig','Cow','Carabao','Horse','Pigeon','Quail','Fish','Bee','Other'
    ];
}

function cbms_vehicle_type_options(): array {
    return [
        'Bicycle','E-bike','Motorcycle','Scooter','Tricycle','Pedicab','Sidecar','ATV','Multi-cab','Jeep','Jeepney','Van','Pickup','Car','SUV','Mini truck','Truck','Mini dump truck','Hand tractor','Farm tractor','Kuliglig','Boat','Bangka','Other'
    ];
}


function cbms_asset_type_groups(): array {
    return [
        'Home appliances' => ['Refrigerator','Freezer','Electric fan','Air conditioner','Television','Rice cooker','Gas stove','Washing machine','Water dispenser'],
        'Farm and livelihood' => ['Rice mill','Chainsaw','Grass cutter','Water pump','Generator','Sprayer','Hand tractor','Plow','Fishing net','Fishing boat'],
        'Electronics' => ['Mobile phone','Smartphone','Tablet','Laptop','Desktop computer','Printer','Wi-Fi router','Radio'],
        'Furniture and storage' => ['Cabinet','Bed','Dining set','Sofa','Water tank'],
        'Business assets' => ['Store equipment','Freezer for store','Display rack','POS device','Sewing machine'],
        'Other' => ['Other'],
    ];
}

function household_language_options(): array {
    return ['Waray','Cebuano','Tagalog','English','Bisaya','Ilocano','Hiligaynon','Bicolano','Kapampangan','Pangasinan','Maranao','Maguindanao','Tausug','Chavacano','Other'];
}

function household_religion_options(): array {
    return ['Roman Catholic','Christian','Iglesia ni Cristo','Seventh-day Adventist','Islam','Born Again','Baptist','Jehovah\'s Witness','Aglipayan','Pentecostal','Other'];
}

function household_employment_status_options(): array {
    return ['Employed','Self-employed','Seasonal worker','Unemployed','Student','Retired','PWD','Homemaker','OFW','Other'];
}

function household_citizenship_options(): array {
    return ['Filipino','Dual Citizen','Resident Alien','Other'];
}

function household_disease_options(): array {
    return ['None','Hypertension','Diabetes','Asthma','Tuberculosis','Heart disease','Kidney disease','Cancer','Stroke','Other'];
}

function household_disability_options(): array {
    return ['None','Visual impairment','Hearing impairment','Speech impairment','Physical disability','Psychosocial disability','Intellectual disability','Learning disability','Multiple disability','Other'];
}

function beneficiary_sector_type_options(mysqli $conn): array {
    if (table_exists($conn, 'beneficiary_sector_types')) {
        $rows = fetch_all_assoc($conn, "SELECT sector_code, sector_name FROM beneficiary_sector_types WHERE is_active=1 ORDER BY sort_order ASC, sector_name ASC");
        if ($rows) {
            return array_map(static fn($row) => ['code' => (string)($row['sector_code'] ?? ''), 'name' => (string)($row['sector_name'] ?? '')], $rows);
        }
    }
    return [
        ['code' => 'male', 'name' => 'Male'],
        ['code' => 'female', 'name' => 'Female'],
        ['code' => 'pwd', 'name' => 'PWD'],
        ['code' => 'senior', 'name' => 'Senior Citizen'],
        ['code' => 'indigent', 'name' => 'Indigent'],
        ['code' => 'solo_parent', 'name' => 'Solo Parent'],
        ['code' => 'youth', 'name' => 'Youth'],
    ];
}

function beneficiary_indigent_status_options(): array {
    return ['Indigent','Near-indigent','Non-indigent','For Validation'];
}

function beneficiary_priority_level_options(): array {
    return ['Critical','High','Medium','Low','For Validation'];
}

function beneficiary_recommendation_options(): array {
    return ['Financial assistance','Medical assistance','Food support','Livelihood support','Scholarship / education support','PWD support','Senior citizen support','Solo parent support','For validation / interview'];
}

function parse_multi_value_payload($value): string {
    if (is_array($value)) {
        $clean = [];
        foreach ($value as $item) {
            $item = trim((string)$item);
            if ($item !== '') $clean[] = $item;
        }
        return implode(',', array_values(array_unique($clean)));
    }
    return trim((string)$value);
}

function save_beneficiary_record(mysqli $conn, int $householdId, array $payload, int $userId = 0, int $recordId = 0): int {
    if ($householdId <= 0 || !table_exists($conn, 'beneficiary_member_records')) return 0;
    $memberId = (int)($payload['member_id'] ?? 0);
    if ($memberId <= 0) $memberId = null;
    $sectorTags = parse_multi_value_payload($payload['sector_tags'] ?? []);
    $indigent = trim((string)($payload['indigent_status'] ?? '')) ?: null;
    $priority = trim((string)($payload['priority_level'] ?? '')) ?: null;
    $recommendation = trim((string)($payload['recommendation'] ?? '')) ?: null;
    $notes = trim((string)($payload['notes'] ?? '')) ?: null;
    $pk = 'beneficiary_record_id';
    if ($recordId > 0) {
        $existing = fetch_one($conn, "SELECT {$pk} FROM beneficiary_member_records WHERE {$pk}=" . (int)$recordId . " AND household_id=" . (int)$householdId . " LIMIT 1");
        if (!$existing) return 0;
        $stmt = $conn->prepare("UPDATE beneficiary_member_records SET member_id=?, sector_tags=?, indigent_status=?, priority_level=?, recommendation=?, notes=?, updated_by=? WHERE {$pk}=? AND household_id=? LIMIT 1");
        if (!$stmt) return 0;
        $stmt->bind_param('isssssiii', $memberId, $sectorTags, $indigent, $priority, $recommendation, $notes, $userId, $recordId, $householdId);
        $ok = $stmt->execute();
        $stmt->close();
        if ($ok && $userId > 0) app_log($conn, $userId, 'BENEFICIARIES', 'UPDATE', $householdId, 'Updated beneficiary record for household #' . $householdId);
        return $ok ? $recordId : 0;
    }
    $stmt = $conn->prepare("INSERT INTO beneficiary_member_records (household_id, member_id, sector_tags, indigent_status, priority_level, recommendation, notes, updated_by) VALUES (?,?,?,?,?,?,?,?)");
    if (!$stmt) return 0;
    $stmt->bind_param('iisssssi', $householdId, $memberId, $sectorTags, $indigent, $priority, $recommendation, $notes, $userId);
    $ok = $stmt->execute();
    $newId = $ok ? (int)$stmt->insert_id : 0;
    $stmt->close();
    if ($newId > 0 && table_exists($conn, 'beneficiary_profiles')) {
        $profileId = (int)scalar($conn, "SELECT beneficiary_id FROM beneficiary_profiles WHERE household_id=" . (int)$householdId . " LIMIT 1", 0);
        if ($profileId > 0) {
            $stmt = $conn->prepare("UPDATE beneficiary_profiles SET member_id=?, sector_tags=?, indigent_status=?, notes=?, beneficiary_notes=?, last_assistance_type=?, updated_by=? WHERE beneficiary_id=? LIMIT 1");
            if ($stmt) {
                $lastType = $recommendation;
                $stmt->bind_param('isssssii', $memberId, $sectorTags, $indigent, $notes, $notes, $lastType, $userId, $profileId);
                $stmt->execute();
                $stmt->close();
            }
        } else {
            $stmt = $conn->prepare("INSERT INTO beneficiary_profiles (household_id, member_id, sector_tags, indigent_status, notes, beneficiary_notes, last_assistance_type, updated_by) VALUES (?,?,?,?,?,?,?,?)");
            if ($stmt) {
                $lastType = $recommendation;
                $stmt->bind_param('iisssssi', $householdId, $memberId, $sectorTags, $indigent, $notes, $notes, $lastType, $userId);
                $stmt->execute();
                $stmt->close();
            }
        }
    }
    if ($newId > 0 && $userId > 0) app_log($conn, $userId, 'BENEFICIARIES', 'CREATE', $householdId, 'Added beneficiary record for household #' . $householdId);
    return $newId;
}

function delete_beneficiary_record(mysqli $conn, int $householdId, int $recordId, int $userId = 0): bool {
    if ($householdId <= 0 || $recordId <= 0 || !table_exists($conn, 'beneficiary_member_records')) return false;
    $stmt = $conn->prepare("DELETE FROM beneficiary_member_records WHERE beneficiary_record_id=? AND household_id=? LIMIT 1");
    if (!$stmt) return false;
    $stmt->bind_param('ii', $recordId, $householdId);
    $ok = $stmt->execute();
    $stmt->close();
    if ($ok && $userId > 0) app_log($conn, $userId, 'BENEFICIARIES', 'DELETE', $householdId, 'Deleted beneficiary record for household #' . $householdId);
    return $ok;
}

function cbms_vehicle_details_meta(array $row): string {
    $parts = [];
    if (!empty($row['vehicle_brand'])) $parts[] = (string)$row['vehicle_brand'];
    if (!empty($row['vehicle_model'])) $parts[] = (string)$row['vehicle_model'];
    if (!empty($row['year_model'])) $parts[] = (string)$row['year_model'];
    if (!empty($row['plate_number'])) $parts[] = 'Plate ' . (string)$row['plate_number'];
    if (!empty($row['color'])) $parts[] = (string)$row['color'];
    return implode(' · ', $parts);
}

function cbms_asset_details_meta(array $row): string {
    $parts = [];
    if (!empty($row['asset_category'])) $parts[] = (string)$row['asset_category'];
    if (!empty($row['asset_brand'])) $parts[] = (string)$row['asset_brand'];
    if (!empty($row['asset_model'])) $parts[] = (string)$row['asset_model'];
    return implode(' · ', $parts);
}

function save_cbms_profile_row(mysqli $conn, string $table, int $householdId, array $payload, int $userId = 0): bool {
    ensure_module_family_support_schema($conn);
    if (!table_exists($conn, $table)) return false;
    $allowed = [
        'cbms_housing_profiles' => ['housing_type','roof_material','wall_material','tenure_status','electricity_source','notes'],
        'cbms_livelihood_profiles' => ['primary_income_source','main_livelihood','monthly_income_band','employment_notes','notes'],
        'cbms_sanitation_profiles' => ['water_source','toilet_type','waste_disposal','drainage_status','notes'],
        'cbms_household_profiles' => ['tenure_status','housing_materials','toilet_type','water_source','electricity_source','internet_access','waste_disposal_method','monthly_household_income','poverty_status','housing_type','livelihood_summary','crop_summary','vehicle_count','farming_household','farm_area_hectares','fruit_tree_count_estimate','special_program_notes','notes'],
    ];
    $fields = $allowed[$table] ?? [];
    $data = [];
    foreach ($fields as $field) {
        if (column_exists($conn, $table, $field)) {
            $value = $payload[$field] ?? null;
            if (is_string($value)) $value = trim($value);
            if ($value === '') $value = null;
            $data[$field] = $value;
        }
    }
    if (!$data) return false;
    $existing = fetch_one($conn, "SELECT household_id FROM {$table} WHERE household_id=" . (int)$householdId . " LIMIT 1");
    if ($existing) {
        $sets = [];
        $types = '';
        $vals = [];
        foreach ($data as $field => $value) {
            $sets[] = "{$field}=?";
            $types .= 's';
            $vals[] = is_array($value) ? json_encode($value) : (string)$value;
        }
        if (column_exists($conn, $table, 'updated_by')) { $sets[] = 'updated_by=?'; $types .= 'i'; $vals[] = $userId; }
        $types .= 'i'; $vals[] = $householdId;
        $stmt = $conn->prepare("UPDATE {$table} SET " . implode(',', $sets) . " WHERE household_id=? LIMIT 1");
        if (!$stmt) return false;
        $stmt->bind_param($types, ...$vals);
        $ok = $stmt->execute();
        $stmt->close();
    } else {
        $cols = ['household_id']; $ph = ['?']; $types = 'i'; $vals = [$householdId];
        foreach ($data as $field => $value) { $cols[] = $field; $ph[] = '?'; $types .= 's'; $vals[] = is_array($value) ? json_encode($value) : (string)$value; }
        if (column_exists($conn, $table, 'created_by')) { $cols[] = 'created_by'; $ph[] = '?'; $types .= 'i'; $vals[] = $userId; }
        if (column_exists($conn, $table, 'updated_by')) { $cols[] = 'updated_by'; $ph[] = '?'; $types .= 'i'; $vals[] = $userId; }
        $stmt = $conn->prepare("INSERT INTO {$table} (" . implode(',', $cols) . ") VALUES (" . implode(',', $ph) . ")");
        if (!$stmt) return false;
        $stmt->bind_param($types, ...$vals);
        $ok = $stmt->execute();
        $stmt->close();
    }
    if (!empty($ok) && $userId > 0) app_log($conn, $userId, 'CBMS', 'UPDATE', $householdId, 'Updated ' . $table . ' for household #' . $householdId);
    return !empty($ok);
}

function save_cbms_asset_record(mysqli $conn, string $kind, int $householdId, array $payload, int $userId = 0, int $recordId = 0): int {
    ensure_module_family_support_schema($conn);
    $table = match ($kind) {
        'pet' => 'cbms_pets',
        'vehicle' => 'cbms_vehicles',
        'asset' => 'cbms_asset_records',
        default => '',
    };
    if ($table === '' || !table_exists($conn, $table)) return 0;
    $typeField = match ($kind) {
        'pet' => column_exists($conn, $table, 'pet_type') ? 'pet_type' : 'animal_type',
        'vehicle' => 'vehicle_type',
        default => 'asset_name',
    };
    $nameField = ($kind === 'pet' && column_exists($conn, $table, 'animal_name')) ? 'animal_name' : null;
    $qtyField = cbms_table_quantity_column($conn, $table);
    $pk = cbms_table_pk_column($conn, $table);
    $typeValue = trim((string)($payload['item_type'] ?? $payload[$typeField] ?? ''));
    if (strcasecmp($typeValue, 'Other') === 0 || $typeValue === '__other__') {
        $typeValue = trim((string)($payload['item_type_other'] ?? ''));
    }
    if ($typeValue === '') return 0;
    $quantity = max(1, (int)($payload['quantity'] ?? 1));
    $notes = trim((string)($payload['notes'] ?? ''));
    $animalName = trim((string)($payload['animal_name'] ?? ''));

    $extraFields = [];
    if ($kind === 'vehicle') {
        foreach (['vehicle_brand','vehicle_model','year_model','plate_number','color','ownership_status','registration_status'] as $field) {
            if (column_exists($conn, $table, $field)) {
                $value = trim((string)($payload[$field] ?? ''));
                $extraFields[$field] = ($value !== '' ? $value : null);
            }
        }
    } elseif ($kind === 'asset') {
        foreach (['asset_category','asset_brand','asset_model'] as $field) {
            if (column_exists($conn, $table, $field)) {
                $value = trim((string)($payload[$field] ?? ''));
                $extraFields[$field] = ($value !== '' ? $value : null);
            }
        }
    }

    if ($recordId > 0) {
        $existing = fetch_one($conn, "SELECT {$pk} FROM {$table} WHERE {$pk}=" . (int)$recordId . " AND household_id=" . (int)$householdId . " LIMIT 1");
        if (!$existing) return 0;
        $sets = ["{$typeField}=?", "{$qtyField}=?"];
        $types = 'si';
        $vals = [$typeValue, $quantity];
        if ($nameField) { $sets[] = "{$nameField}=?"; $types .= 's'; $vals[] = ($animalName !== '' ? $animalName : null); }
        foreach ($extraFields as $field => $value) { $sets[] = "{$field}=?"; $types .= 's'; $vals[] = $value; }
        if (column_exists($conn, $table, 'notes')) { $sets[] = 'notes=?'; $types .= 's'; $vals[] = ($notes !== '' ? $notes : null); }
        if (column_exists($conn, $table, 'pet_count') && $qtyField !== 'pet_count') { $sets[] = 'pet_count=?'; $types .= 'i'; $vals[] = $quantity; }
        if (column_exists($conn, $table, 'vehicle_count') && $qtyField !== 'vehicle_count') { $sets[] = 'vehicle_count=?'; $types .= 'i'; $vals[] = $quantity; }
        $types .= 'ii'; $vals[] = $recordId; $vals[] = $householdId;
        $stmt = $conn->prepare("UPDATE {$table} SET " . implode(',', $sets) . " WHERE {$pk}=? AND household_id=? LIMIT 1");
        if (!$stmt) return 0;
        $stmt->bind_param($types, ...$vals);
        $ok = $stmt->execute();
        $stmt->close();
        if ($ok && $userId > 0) app_log($conn, $userId, 'CBMS', 'UPDATE', $householdId, 'Updated ' . $kind . ' record for household #' . $householdId);
        return $ok ? $recordId : 0;
    }
    $cols = ['household_id', $typeField, $qtyField];
    $ph = ['?', '?', '?'];
    $types = 'isi';
    $vals = [$householdId, $typeValue, $quantity];
    if ($nameField) { $cols[] = $nameField; $ph[] = '?'; $types .= 's'; $vals[] = ($animalName !== '' ? $animalName : null); }
    foreach ($extraFields as $field => $value) { $cols[] = $field; $ph[] = '?'; $types .= 's'; $vals[] = $value; }
    if (column_exists($conn, $table, 'notes')) { $cols[] = 'notes'; $ph[] = '?'; $types .= 's'; $vals[] = ($notes !== '' ? $notes : null); }
    if (column_exists($conn, $table, 'pet_count') && $qtyField !== 'pet_count') { $cols[] = 'pet_count'; $ph[] = '?'; $types .= 'i'; $vals[] = $quantity; }
    if (column_exists($conn, $table, 'vehicle_count') && $qtyField !== 'vehicle_count') { $cols[] = 'vehicle_count'; $ph[] = '?'; $types .= 'i'; $vals[] = $quantity; }
    $stmt = $conn->prepare("INSERT INTO {$table} (" . implode(',', $cols) . ") VALUES (" . implode(',', $ph) . ")");
    if (!$stmt) return 0;
    $stmt->bind_param($types, ...$vals);
    $ok = $stmt->execute();
    $newId = $ok ? (int)$stmt->insert_id : 0;
    $stmt->close();
    if ($newId > 0 && $userId > 0) app_log($conn, $userId, 'CBMS', 'CREATE', $householdId, 'Added ' . $kind . ' record to household #' . $householdId);
    return $newId;
}

function delete_cbms_asset_record(mysqli $conn, string $kind, int $householdId, int $recordId, int $userId = 0): bool {
    $table = match ($kind) {
        'pet' => 'cbms_pets',
        'vehicle' => 'cbms_vehicles',
        'asset' => 'cbms_asset_records',
        default => '',
    };
    if ($table === '' || !table_exists($conn, $table) || $recordId <= 0) return false;
    $pk = cbms_table_pk_column($conn, $table);
    $stmt = $conn->prepare("DELETE FROM {$table} WHERE {$pk}=? AND household_id=? LIMIT 1");
    if (!$stmt) return false;
    $stmt->bind_param('ii', $recordId, $householdId);
    $ok = $stmt->execute();
    $stmt->close();
    if ($ok && $userId > 0) app_log($conn, $userId, 'CBMS', 'DELETE', $householdId, 'Deleted ' . $kind . ' record from household #' . $householdId);
    return $ok;
}

function fetch_cbms_edit_record(mysqli $conn, string $kind, int $householdId, int $recordId): ?array {
    $table = match ($kind) {
        'pet' => 'cbms_pets',
        'vehicle' => 'cbms_vehicles',
        'asset' => 'cbms_asset_records',
        default => '',
    };
    if ($table === '' || !table_exists($conn, $table) || $recordId <= 0) return null;
    $pk = cbms_table_pk_column($conn, $table);
    return fetch_one($conn, "SELECT * FROM {$table} WHERE {$pk}=" . (int)$recordId . " AND household_id=" . (int)$householdId . " LIMIT 1");
}

function cbms_profile_form_value(array $primary = [], array $secondary = [], string $key = ''): string {
    $value = $primary[$key] ?? $secondary[$key] ?? '';
    return is_scalar($value) ? (string)$value : '';
}

function module_family_index_url(?string $moduleCode = null): string {
    $moduleCode = $moduleCode ?: current_platform_module_code();
    return match ($moduleCode) {
        'beneficiaries' => app_url('modules/beneficiaries/families/index.php'),
        'cbms' => app_url('modules/cbms/families/index.php'),
        'mayor' => app_url('modules/mayor/families/index.php'),
        default => app_url('modules/agri/households/index.php'),
    };
}

function module_family_view_url(int $householdId, ?string $moduleCode = null): string {
    $moduleCode = $moduleCode ?: current_platform_module_code();
    return match ($moduleCode) {
        'beneficiaries' => app_url('modules/beneficiaries/families/view.php?id=' . $householdId),
        'cbms' => app_url('modules/cbms/families/view.php?id=' . $householdId),
        'mayor' => app_url('modules/mayor/families/view.php?id=' . $householdId),
        default => app_url('modules/agri/households/view.php?id=' . $householdId),
    };
}

function shared_household_search_where(mysqli $conn, string $q): string {
    $q = trim($q);
    if ($q === '') return '1=1';
    $safe = $conn->real_escape_string($q);
    $like = "%{$safe}%";
    $parts = [
        "h.household_head_name LIKE '{$like}'",
        "h.household_code LIKE '{$like}'",
        (column_exists($conn, 'households', 'reference_no') ? "h.reference_no LIKE '{$like}'" : '0=1'),
        "h.contact_number LIKE '{$like}'",
        (table_exists($conn, 'family_members') ? "EXISTS (SELECT 1 FROM family_members fm WHERE fm.household_id=h.household_id AND fm.is_active=1 AND fm.full_name LIKE '{$like}')" : '0=1'),
    ];
    return '(' . implode(' OR ', $parts) . ')';
}

function household_table_count_or_sum(mysqli $conn, string $table, int $householdId, ?string $quantityColumn = 'quantity'): int {
    if (!table_exists($conn, $table)) return 0;
    $qtyColumn = ($quantityColumn && column_exists($conn, $table, $quantityColumn)) ? $quantityColumn : cbms_table_quantity_column($conn, $table);
    $qtyExpr = ($qtyColumn && column_exists($conn, $table, $qtyColumn)) ? "SUM(COALESCE({$qtyColumn},1))" : 'COUNT(*)';
    return (int)scalar($conn, "SELECT COALESCE({$qtyExpr},0) FROM {$table} WHERE household_id={$householdId}", 0);
}

function cbms_display_type(array $row, string $fallback = 'Record'): string {
    return cbms_asset_record_label($row, $fallback);
}

function fetch_module_household_list(mysqli $conn, string $moduleCode, string $q = ''): array {
    ensure_family_upgrade_schema($conn);
    ensure_module_family_support_schema($conn);
    $where = shared_household_search_where($conn, $q);
    $rows = fetch_all_assoc($conn, "
        SELECT h.household_id,h.household_head_name,h.household_code,h.reference_no,h.contact_number,h.profile_photo_path,
               h.record_status,b.barangay_name,
               (SELECT COUNT(*) FROM family_members fm WHERE fm.household_id=h.household_id AND fm.is_active=1) AS member_count,
               (SELECT COUNT(*) FROM crops c WHERE c.household_id=h.household_id) AS crop_count
        FROM households h
        LEFT JOIN barangays b ON b.barangay_id=h.barangay_id
        WHERE {$where}
        ORDER BY h.household_head_name ASC
        LIMIT 150
    ");
    foreach ($rows as &$row) {
        $hid = (int)($row['household_id'] ?? 0);
        $row['pet_count'] = household_table_count_or_sum($conn, 'cbms_pets', $hid);
        $row['vehicle_count'] = household_table_count_or_sum($conn, 'cbms_vehicles', $hid);
        $row['beneficiary_count'] = table_exists($conn, 'beneficiary_profiles') ? (int)scalar($conn, "SELECT COUNT(*) FROM beneficiary_profiles WHERE household_id={$hid}", 0) : 0;
        $row['open_url'] = module_family_view_url($hid, $moduleCode);
        $row['photo_url'] = household_profile_photo($conn, $hid, $row['profile_photo_path'] ?? null);
    }
    unset($row);
    return $rows;
}

function fetch_household_shared_summary(mysqli $conn, int $householdId): ?array {
    ensure_family_upgrade_schema($conn);
    ensure_module_family_support_schema($conn);
    $stmt = $conn->prepare("SELECT h.*, b.barangay_name FROM households h LEFT JOIN barangays b ON b.barangay_id=h.barangay_id WHERE h.household_id=? LIMIT 1");
    if (!$stmt) return null;
    $stmt->bind_param('i', $householdId);
    $stmt->execute();
    $house = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();
    if (!$house) return null;
    $members = table_exists($conn, 'family_members') ? fetch_all_assoc($conn, "SELECT * FROM family_members WHERE household_id={$householdId} AND is_active=1 ORDER BY is_household_head DESC, full_name") : [];
    $house['members'] = $members;
    $house['head_member'] = null;
    foreach ($members as $member) {
        if (!empty($member['is_household_head'])) { $house['head_member'] = $member; break; }
    }
    $house['member_count'] = count($members);
    $house['crop_count'] = table_exists($conn, 'crops') ? (int)scalar($conn, "SELECT COUNT(*) FROM crops WHERE household_id={$householdId}", 0) : 0;
    $house['pet_count'] = household_table_count_or_sum($conn, 'cbms_pets', $householdId);
    $house['vehicle_count'] = household_table_count_or_sum($conn, 'cbms_vehicles', $householdId);
    $house['photo_url'] = household_profile_photo($conn, $householdId, $house['profile_photo_path'] ?? null);
    return $house;
}

function detect_household_tags(mysqli $conn, array $house): array {
    $tags = [];
    $members = $house['members'] ?? [];
    $pwd = 0; $senior = 0; $youth = 0; $farmers = 0;
    foreach ($members as $m) {
        $age = isset($m['age']) && $m['age'] !== null ? (int)$m['age'] : null;
        if (!empty($m['disability'])) $pwd++;
        if ($age !== null && $age >= 60) $senior++;
        if ($age !== null && $age >= 15 && $age <= 30) $youth++;
        if (stripos((string)($m['occupation'] ?? ''), 'farm') !== false) $farmers++;
    }
    if (($house['crop_count'] ?? 0) > 0) $tags[] = 'Crop Household';
    if (($house['pet_count'] ?? 0) > 0) $tags[] = 'Has Pets';
    if (($house['vehicle_count'] ?? 0) > 0) $tags[] = 'Has Vehicles';
    if ($pwd > 0) $tags[] = 'PWD';
    if ($senior > 0) $tags[] = 'Senior';
    if ($youth > 0) $tags[] = 'Youth';
    if ($farmers > 0) $tags[] = 'Farmer';
    return $tags;
}

function fetch_beneficiary_profiles_for_household(mysqli $conn, int $householdId): array {
    if (!table_exists($conn, 'beneficiary_profiles')) return [];
    $select = ['household_id'];
    foreach (['member_id','indigent_status','priority_level','sector_tags','recommendation','notes','updated_at','created_at'] as $col) {
        if (column_exists($conn, 'beneficiary_profiles', $col)) $select[] = $col;
    }
    return fetch_all_assoc($conn, 'SELECT ' . implode(',', $select) . ' FROM beneficiary_profiles WHERE household_id=' . $householdId . ' ORDER BY ' . (column_exists($conn, 'beneficiary_profiles', 'updated_at') ? 'updated_at DESC' : 'household_id DESC'));
}

function household_priority_level_options(): array {
    return ['Routine','Watchlist','Priority','High Priority'];
}

function sp_cbms_housing_type_options(): array {
    return ['Single house','Duplex','Apartment','Condominium','Temporary shelter','Rent-free shelter','Other'];
}

function sp_cbms_tenure_status_options(): array {
    return ['Own house and lot','Own house, rent lot','Rent house','Rent-free with consent','Rent-free without consent','Caretaker / staying with relatives','Other'];
}

function sp_cbms_roof_material_options(): array {
    return ['Concrete','Galvanized iron','Aluminum','Nipa','Cogon','Mixed materials','Makeshift / salvaged','Other'];
}

function sp_cbms_wall_material_options(): array {
    return ['Concrete','Wood','Mixed materials','Bamboo','Light materials','Makeshift / salvaged','Other'];
}

function sp_cbms_electricity_source_options(): array {
    return ['Electricity (grid)','Generator','Solar','Battery','None','Other'];
}

function sp_cbms_primary_income_source_options(): array {
    return ['Farming','Fishing','Employment','Business','Remittance','Pension','Livestock / poultry','Government assistance','Other'];
}

function sp_cbms_main_livelihood_options(): array {
    return ['Farmer','Farm worker','Fisherman','Vendor','Laborer','OFW','Government employee','Private employee','Self-employed','Homemaker','Tricycle driver','Other'];
}

function sp_cbms_income_band_options(): array {
    return ['Below 5,000','5,000-10,000','10,001-20,000','20,001-50,000','Above 50,000'];
}

function sp_cbms_water_source_options(): array {
    return ['Piped into dwelling','Piped into yard / plot','Public tap / standpipe','Protected well','Protected spring','Rainwater','Tanker truck / peddler','Bottled water','Unprotected well','Surface water','Other'];
}

function sp_cbms_toilet_type_options(): array {
    return ['Flush to sewer / septic tank','Flush to pit latrine','Ventilated improved pit latrine','Pit latrine with slab','Pit latrine without slab','Composting toilet','No toilet / open defecation','Other'];
}

function sp_cbms_waste_disposal_options(): array {
    return ['Collected by garbage truck','Burning','Burying','Composting','Open dumping','Segregation / recycling','Throwing in pit','Other'];
}

function sp_cbms_drainage_status_options(): array {
    return ['Good drainage','Needs cleaning','Flood-prone','Near creek / river','Open canal nearby','Other'];
}

function sp_cbms_value_is_other(?string $value, array $options): bool {
    $value = trim((string)$value);
    if ($value === '') return false;
    return !in_array($value, $options, true);
}

function sp_cbms_select_value(?string $value, array $options): string {
    $value = trim((string)$value);
    if ($value === '') return '';
    return in_array($value, $options, true) ? $value : 'Other';
}

function sp_cbms_other_value(?string $value, array $options): string {
    $value = trim((string)$value);
    if ($value === '' || in_array($value, $options, true)) return '';
    return $value;
}

function sp_cbms_normalize_other(array $payload, string $key): ?string {
    $value = trim((string)($payload[$key] ?? ''));
    $other = trim((string)($payload[$key . '_other'] ?? ''));
    if ($value === 'Other') {
        return $other !== '' ? $other : 'Other';
    }
    return $value !== '' ? $value : null;
}

function save_household_beneficiary_flags(mysqli $conn, int $householdId, array $payload, int $userId = 0): bool {
    ensure_module_family_support_schema($conn);
    if ($householdId <= 0 || !table_exists($conn, 'household_beneficiary_flags')) return false;
    $bool = static fn(string $key): int => !empty($payload[$key]) ? 1 : 0;
    $priorityLevel = trim((string)($payload['priority_level'] ?? '')) ?: null;
    $priorityNotes = trim((string)($payload['priority_notes'] ?? '')) ?: null;
    $exists = fetch_one($conn, 'SELECT beneficiary_flag_id FROM household_beneficiary_flags WHERE household_id=' . (int)$householdId . ' LIMIT 1');
    if ($exists) {
        $stmt = $conn->prepare('UPDATE household_beneficiary_flags SET is_4ps=?, has_senior=?, has_pwd=?, has_solo_parent=?, has_pregnant_member=?, has_philhealth=?, receives_lgu_assistance=?, priority_level=?, priority_notes=?, updated_by=? WHERE household_id=? LIMIT 1');
        if (!$stmt) return false;
        $is4ps = $bool('is_4ps');
        $hasSenior = $bool('has_senior');
        $hasPwd = $bool('has_pwd');
        $hasSolo = $bool('has_solo_parent');
        $hasPregnant = $bool('has_pregnant_member');
        $hasPhilhealth = $bool('has_philhealth');
        $receivesLgu = $bool('receives_lgu_assistance');
        $stmt->bind_param('iiiiiiissii', $is4ps, $hasSenior, $hasPwd, $hasSolo, $hasPregnant, $hasPhilhealth, $receivesLgu, $priorityLevel, $priorityNotes, $userId, $householdId);
    } else {
        $stmt = $conn->prepare('INSERT INTO household_beneficiary_flags (household_id, is_4ps, has_senior, has_pwd, has_solo_parent, has_pregnant_member, has_philhealth, receives_lgu_assistance, priority_level, priority_notes, updated_by) VALUES (?,?,?,?,?,?,?,?,?,?,?)');
        if (!$stmt) return false;
        $is4ps = $bool('is_4ps');
        $hasSenior = $bool('has_senior');
        $hasPwd = $bool('has_pwd');
        $hasSolo = $bool('has_solo_parent');
        $hasPregnant = $bool('has_pregnant_member');
        $hasPhilhealth = $bool('has_philhealth');
        $receivesLgu = $bool('receives_lgu_assistance');
        $stmt->bind_param('iiiiiiiissi', $householdId, $is4ps, $hasSenior, $hasPwd, $hasSolo, $hasPregnant, $hasPhilhealth, $receivesLgu, $priorityLevel, $priorityNotes, $userId);
    }
    $ok = $stmt->execute();
    $stmt->close();
    if ($ok && $userId > 0) app_log($conn, $userId, 'HOUSEHOLDS', 'UPDATE', $householdId, 'Updated special program beneficiary flags for household #' . $householdId);
    return $ok;
}

function fetch_household_beneficiary_flags(mysqli $conn, int $householdId): ?array {
    if ($householdId <= 0 || !table_exists($conn, 'household_beneficiary_flags')) return null;
    return fetch_one($conn, 'SELECT * FROM household_beneficiary_flags WHERE household_id=' . (int)$householdId . ' LIMIT 1');
}

function fetch_cbms_household_profile(mysqli $conn, int $householdId): ?array {
    if (!table_exists($conn, 'cbms_household_profiles')) return null;
    return fetch_one($conn, 'SELECT * FROM cbms_household_profiles WHERE household_id=' . $householdId . ' LIMIT 1');
}

function fetch_cbms_assets(mysqli $conn, int $householdId): array {
    $pets = table_exists($conn, 'cbms_pets') ? fetch_all_assoc($conn, 'SELECT * FROM cbms_pets WHERE household_id=' . $householdId . ' ORDER BY ' . (column_exists($conn,'cbms_pets','pet_type')?'pet_type':'household_id')) : [];
    $vehicles = table_exists($conn, 'cbms_vehicles') ? fetch_all_assoc($conn, 'SELECT * FROM cbms_vehicles WHERE household_id=' . $householdId . ' ORDER BY ' . (column_exists($conn,'cbms_vehicles','vehicle_type')?'vehicle_type':'household_id')) : [];
    return ['pets' => $pets, 'vehicles' => $vehicles];
}

function fetch_cbms_richer_sections(mysqli $conn, int $householdId): array {
    ensure_module_family_support_schema($conn);
    return [
        'housing' => table_exists($conn, 'cbms_housing_profiles') ? fetch_one($conn, 'SELECT * FROM cbms_housing_profiles WHERE household_id=' . $householdId . ' LIMIT 1') : null,
        'livelihood' => table_exists($conn, 'cbms_livelihood_profiles') ? fetch_one($conn, 'SELECT * FROM cbms_livelihood_profiles WHERE household_id=' . $householdId . ' LIMIT 1') : null,
        'sanitation' => table_exists($conn, 'cbms_sanitation_profiles') ? fetch_one($conn, 'SELECT * FROM cbms_sanitation_profiles WHERE household_id=' . $householdId . ' LIMIT 1') : null,
        'assets' => table_exists($conn, 'cbms_asset_records') ? fetch_all_assoc($conn, 'SELECT * FROM cbms_asset_records WHERE household_id=' . $householdId . ' ORDER BY asset_name') : [],
    ];
}

function fetch_special_program_data(mysqli $conn, int $householdId): array {
    return [
        'crops' => table_exists($conn, 'crops') ? fetch_all_assoc($conn, "SELECT crop_name,variety,tree_count,current_condition,fruiting_status FROM crops WHERE household_id={$householdId} ORDER BY crop_name") : [],
        'interviews' => table_exists($conn, 'interviews') ? fetch_all_assoc($conn, "SELECT interview_date,compliance_status,remarks FROM interviews WHERE household_id={$householdId} ORDER BY interview_id DESC LIMIT 10") : [],
        'monitoring' => table_exists($conn, 'monitoring_visits') ? fetch_all_assoc($conn, "SELECT monitoring_date,crop_condition,fruiting_status,harvest_kg FROM monitoring_visits WHERE household_id={$householdId} ORDER BY monitoring_id DESC LIMIT 10") : [],
        'attendance' => (table_exists($conn, 'event_attendance') && table_exists($conn, 'events')) ? fetch_all_assoc($conn, "SELECT e.event_name,e.event_date,a.attendance_status FROM event_attendance a JOIN events e ON e.event_id=a.event_id WHERE a.household_id={$householdId} ORDER BY a.attendance_id DESC LIMIT 10") : [],
    ];
}

function fetch_assistance_history_shared(mysqli $conn, int $householdId): array {
    if (!table_exists($conn, 'assistance_records')) return [];
    $moduleCol = column_exists($conn, 'assistance_records', 'module_name') ? 'module_name' : (column_exists($conn, 'assistance_records', 'source_module') ? 'source_module' : "'' AS module_name");
    return fetch_all_assoc($conn, "SELECT assistance_type,assistance_status,assistance_date,{$moduleCol} FROM assistance_records WHERE household_id={$householdId} ORDER BY assistance_id DESC LIMIT 15");
}

function fetch_household_timeline(mysqli $conn, int $householdId, int $limit = 12): array {
    $items = [];
    if (table_exists($conn, 'audit_logs')) {
        foreach (fetch_all_assoc($conn, "SELECT created_at,module_name,action_name,description FROM audit_logs WHERE record_id={$householdId} ORDER BY created_at DESC LIMIT 20") as $row) {
            $items[] = ['date'=>$row['created_at'],'title'=>$row['action_name'] ?: 'Audit update','meta'=>trim(($row['module_name'] ?: 'System') . ' · ' . ($row['description'] ?: 'Record updated'))];
        }
    }
    if (table_exists($conn, 'interviews')) {
        foreach (fetch_all_assoc($conn, "SELECT interview_date,compliance_status FROM interviews WHERE household_id={$householdId} ORDER BY interview_id DESC LIMIT 10") as $row) {
            $items[] = ['date'=>$row['interview_date'],'title'=>'Interview saved','meta'=>($row['compliance_status'] ?: 'Special Program')];
        }
    }
    if (table_exists($conn, 'monitoring_visits')) {
        foreach (fetch_all_assoc($conn, "SELECT monitoring_date,crop_condition FROM monitoring_visits WHERE household_id={$householdId} ORDER BY monitoring_id DESC LIMIT 10") as $row) {
            $items[] = ['date'=>$row['monitoring_date'],'title'=>'Monitoring recorded','meta'=>($row['crop_condition'] ?: 'Field visit')];
        }
    }
    if (table_exists($conn, 'assistance_records')) {
        foreach (fetch_all_assoc($conn, "SELECT assistance_date,assistance_type,assistance_status FROM assistance_records WHERE household_id={$householdId} ORDER BY assistance_id DESC LIMIT 10") as $row) {
            $items[] = ['date'=>$row['assistance_date'],'title'=>'Assistance update','meta'=>trim(($row['assistance_type'] ?: 'Support') . ' · ' . ($row['assistance_status'] ?: 'Planned'))];
        }
    }
    if (table_exists($conn, 'cbms_pets')) {
        $petDateExpr = column_exists($conn,'cbms_pets','updated_at') ? 'updated_at' : (column_exists($conn,'cbms_pets','created_at') ? 'created_at' : 'NULL');
        $petTypeExpr = column_exists($conn,'cbms_pets','pet_type') ? 'pet_type' : (column_exists($conn,'cbms_pets','animal_type') ? 'animal_type' : "'Pet'");
        $petQtyExpr = column_exists($conn,'cbms_pets','quantity') ? 'quantity' : '1';
        foreach (fetch_all_assoc($conn, "SELECT {$petDateExpr} AS event_date, {$petTypeExpr} AS item_type, {$petQtyExpr} AS item_qty FROM cbms_pets WHERE household_id={$householdId} ORDER BY household_id DESC LIMIT 5") as $row) {
            $items[] = ['date'=>$row['event_date'] ?: null,'title'=>'CBMS pet record','meta'=>trim((string)($row['item_type'] ?? 'Pet') . ' · ' . (string)($row['item_qty'] ?? 1))];
        }
    }
    if (table_exists($conn, 'cbms_vehicles')) {
        $vehicleDateExpr = column_exists($conn,'cbms_vehicles','updated_at') ? 'updated_at' : (column_exists($conn,'cbms_vehicles','created_at') ? 'created_at' : 'NULL');
        $vehicleTypeExpr = column_exists($conn,'cbms_vehicles','vehicle_type') ? 'vehicle_type' : "'Vehicle'";
        $vehicleQtyExpr = column_exists($conn,'cbms_vehicles','quantity') ? 'quantity' : '1';
        foreach (fetch_all_assoc($conn, "SELECT {$vehicleDateExpr} AS event_date, {$vehicleTypeExpr} AS item_type, {$vehicleQtyExpr} AS item_qty FROM cbms_vehicles WHERE household_id={$householdId} ORDER BY household_id DESC LIMIT 5") as $row) {
            $items[] = ['date'=>$row['event_date'] ?: null,'title'=>'CBMS vehicle record','meta'=>trim((string)($row['item_type'] ?? 'Vehicle') . ' · ' . (string)($row['item_qty'] ?? 1))];
        }
    }
    $items = array_values(array_filter($items, fn($i) => !empty($i['date'])));
    usort($items, fn($a, $b) => strcmp((string)$b['date'], (string)$a['date']));
    return array_slice($items, 0, $limit);
}

function compute_module_completeness(mysqli $conn, array $house): array {
    $hid = (int)($house['household_id'] ?? 0);
    $special = 0; $specialTotal = 4;
    if (!empty($house['member_count'])) $special++;
    if (($house['crop_count'] ?? 0) > 0) $special++;
    if (table_exists($conn, 'interviews') && (int)scalar($conn, "SELECT COUNT(*) FROM interviews WHERE household_id={$hid}", 0) > 0) $special++;
    if (table_exists($conn, 'monitoring_visits') && (int)scalar($conn, "SELECT COUNT(*) FROM monitoring_visits WHERE household_id={$hid}", 0) > 0) $special++;

    $benef = 0; $benefTotal = 4;
    if (!empty($house['member_count'])) $benef++;
    if (table_exists($conn, 'beneficiary_profiles') && (int)scalar($conn, "SELECT COUNT(*) FROM beneficiary_profiles WHERE household_id={$hid}", 0) > 0) $benef++;
    if (table_exists($conn, 'beneficiary_profiles') && column_exists($conn,'beneficiary_profiles','sector_tags') && (int)scalar($conn, "SELECT COUNT(*) FROM beneficiary_profiles WHERE household_id={$hid} AND COALESCE(TRIM(sector_tags),'')<>''", 0) > 0) $benef++;
    if (table_exists($conn, 'assistance_records') && (int)scalar($conn, "SELECT COUNT(*) FROM assistance_records WHERE household_id={$hid}", 0) > 0) $benef++;

    $cbms = 0; $cbmsTotal = 5;
    if (table_exists($conn, 'cbms_household_profiles') && fetch_one($conn, 'SELECT household_id FROM cbms_household_profiles WHERE household_id=' . $hid . ' LIMIT 1')) $cbms++;
    if (table_exists($conn, 'cbms_housing_profiles') && fetch_one($conn, 'SELECT household_id FROM cbms_housing_profiles WHERE household_id=' . $hid . ' LIMIT 1')) $cbms++;
    if (table_exists($conn, 'cbms_livelihood_profiles') && fetch_one($conn, 'SELECT household_id FROM cbms_livelihood_profiles WHERE household_id=' . $hid . ' LIMIT 1')) $cbms++;
    if (table_exists($conn, 'cbms_sanitation_profiles') && fetch_one($conn, 'SELECT household_id FROM cbms_sanitation_profiles WHERE household_id=' . $hid . ' LIMIT 1')) $cbms++;
    if (($house['pet_count'] ?? 0) > 0 || ($house['vehicle_count'] ?? 0) > 0 || (table_exists($conn,'cbms_asset_records') && (int)scalar($conn, "SELECT COUNT(*) FROM cbms_asset_records WHERE household_id={$hid}", 0) > 0)) $cbms++;

    return [
        'special_program' => (int)round(($special / $specialTotal) * 100),
        'beneficiaries' => (int)round(($benef / $benefTotal) * 100),
        'cbms' => (int)round(($cbms / $cbmsTotal) * 100),
    ];
}

function household_matches_audience(array $house, string $audience): bool {
    $audience = trim($audience);
    if ($audience === '') return true;
    $tags = detect_household_tags(db_conn(), $house); // db_conn same session, cheap enough
    $members = $house['members'] ?? [];
    return match ($audience) {
        'farmers' => in_array('Farmer', $tags, true) || in_array('Crop Household', $tags, true),
        'pwd' => in_array('PWD', $tags, true),
        'senior_citizen' => in_array('Senior', $tags, true),
        'youth' => in_array('Youth', $tags, true),
        'solo_parent' => count($members) > 0 && count(array_filter($members, fn($m) => stripos((string)($m['relationship_to_head'] ?? ''), 'spouse') !== false)) === 0,
        'unemployed' => count(array_filter($members, fn($m) => stripos((string)($m['employment_status'] ?? ''), 'unemployed') !== false)) > 0,
        default => false,
    };
}

function fetch_household_event_preview(mysqli $conn, array $house, int $limit = 8): array {
    if (!table_exists($conn, 'events')) return [];
    $hid = (int)($house['household_id'] ?? 0);
    $barangayId = isset($house['barangay_id']) ? (int)$house['barangay_id'] : 0;
    $audCol = column_exists($conn, 'events', 'target_profile_filter') ? 'target_profile_filter' : "'' AS target_profile_filter";
    $rows = fetch_all_assoc($conn, "SELECT event_id,event_name,event_date,barangay_id,{$audCol} FROM events WHERE COALESCE(event_status,'Scheduled') IN ('Scheduled','Ongoing') ORDER BY event_date ASC LIMIT 50");
    $matches = [];
    $hasCrops = ((int)($house['crop_count'] ?? 0)) > 0;
    $hasPets = ((int)($house['pet_count'] ?? 0)) > 0;
    $hasVehicles = ((int)($house['vehicle_count'] ?? 0)) > 0;
    $beneficiaryProfiles = fetch_beneficiary_profiles_for_household($conn, $hid);
    $indigent = false;
    foreach ($beneficiaryProfiles as $bp) {
        if (stripos((string)($bp['indigent_status'] ?? ''), 'indigent') !== false || stripos((string)($bp['priority_level'] ?? ''), 'low') !== false) { $indigent = true; break; }
    }
    foreach ($rows as $row) {
        $reasons = [];
        if (!empty($row['barangay_id']) && $barangayId > 0 && (int)$row['barangay_id'] !== $barangayId) continue;
        $audience = trim((string)($row['target_profile_filter'] ?? ''));
        if ($audience !== '' && !household_matches_audience($house, $audience)) continue;
        if ($audience !== '') $reasons[] = 'Audience: ' . strtoupper(str_replace('_', ' ', $audience));
        if ($hasCrops) $reasons[] = 'Has crops';
        if ($hasPets) $reasons[] = 'Has pets';
        if ($hasVehicles) $reasons[] = 'Has vehicles';
        if ($indigent) $reasons[] = 'Indigent candidate';
        if (!$reasons) $reasons[] = 'Open household profile';
        $matches[] = [
            'event_id' => (int)$row['event_id'],
            'event_name' => (string)$row['event_name'],
            'event_date' => (string)($row['event_date'] ?? ''),
            'reasons' => array_slice(array_values(array_unique($reasons)), 0, 3),
            'attendance_url' => app_url('modules/agri/attendance/index.php?event_id=' . (int)$row['event_id']),
        ];
        if (count($matches) >= $limit) break;
    }
    return $matches;
}

function module_quick_actions(string $moduleCode, int $householdId): array {
    return match ($moduleCode) {
        'beneficiaries' => [
            ['label'=>'Update beneficiary data','href'=>app_url('modules/beneficiaries/families/view.php?id=' . $householdId . '#beneficiary-records')],
            ['label'=>'Open assistance history','href'=>app_url('modules/beneficiaries/families/view.php?id=' . $householdId . '#assistance-history')],
            ['label'=>'Family 360','href'=>app_url('modules/mayor/families/view.php?id=' . $householdId)],
        ],
        'cbms' => [
            ['label'=>'Update housing section','href'=>app_url('modules/cbms/families/view.php?id=' . $householdId . '#cbms-housing')],
            ['label'=>'Update livelihood section','href'=>app_url('modules/cbms/families/view.php?id=' . $householdId . '#cbms-livelihood')],
            ['label'=>'Add pets or vehicles','href'=>app_url('modules/cbms/families/view.php?id=' . $householdId . '#cbms-assets')],
        ],
        'mayor' => [
            ['label'=>'Open Special Program view','href'=>app_url('modules/agri/households/view.php?id=' . $householdId)],
            ['label'=>'Open Beneficiaries view','href'=>app_url('modules/beneficiaries/families/view.php?id=' . $householdId)],
            ['label'=>'Open CBMS view','href'=>app_url('modules/cbms/families/view.php?id=' . $householdId)],
        ],
        default => [
            ['label'=>'Open program checklist','href'=>app_url('modules/agri/households/view.php?id=' . $householdId . '#golden-household')],
            ['label'=>'Open validation queue','href'=>app_url('modules/agri/validation/index.php')],
            ['label'=>'Open compliance page','href'=>app_url('modules/agri/compliance/index.php?household_id=' . $householdId)],
        ],
    };
}

function render_badges(array $labels, string $color = 'slate'): string {
    if (!$labels) return '<div class="text-sm text-slate-500">No tags yet.</div>';
    $html = '<div class="flex flex-wrap gap-2">';
    foreach ($labels as $label) {
        $html .= '<span class="app-badge app-badge-' . e($color) . '">' . e((string)$label) . '</span>';
    }
    return $html . '</div>';
}

function render_quick_actions(array $actions): string {
    if (!$actions) return '';
    $html = '<div class="flex flex-wrap gap-3">';
    foreach ($actions as $action) {
        $html .= '<a class="app-btn-outline" href="' . e((string)$action['href']) . '">' . e((string)$action['label']) . '</a>';
    }
    return $html . '</div>';
}
