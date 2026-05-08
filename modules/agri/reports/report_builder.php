<?php

function report_profile_filter_options(): array {
    return [
        '' => 'All profiles',
        'farmers' => 'Farmers',
        'farming_household' => 'Farming households',
        '4ps' => '4Ps households',
        'pwd' => 'PWD',
        'senior_citizen' => 'Senior Citizen',
        'solo_parent' => 'Solo Parent',
        'philhealth' => 'With PhilHealth',
        'lgu_assistance' => 'With LGU assistance',
        'priority_high' => 'High / urgent priority',
        'priority_medium' => 'Medium priority',
        'ofw' => 'OFW',
        'unemployed' => 'Unemployed',
        'pregnant' => 'Pregnant',
        'breastfeeding' => 'Breastfeeding Mother',
        'youth' => 'Youth',
    ];
}

function report_condition_map(): array {
    return [
        'farmers' => "(UPPER(TRIM(COALESCE(fx.occupation,'')))='FARMER' OR COALESCE(fx.member_tags,'') LIKE '%Farmer%')",
        'pwd' => "(COALESCE(fx.disability,'') <> '' OR COALESCE(fx.member_tags,'') LIKE '%PWD%' OR COALESCE(fx.member_status,'') LIKE '%PWD%')",
        'senior_citizen' => "(COALESCE(fx.member_tags,'') LIKE '%Senior Citizen%' OR COALESCE(fx.age,0) >= 60)",
        'solo_parent' => "COALESCE(fx.member_tags,'') LIKE '%Solo Parent%'",
        'ofw' => "(COALESCE(fx.member_tags,'') LIKE '%OFW%' OR COALESCE(fx.ofw_details,'') <> '')",
        'unemployed' => "(COALESCE(fx.member_tags,'') LIKE '%Unemployed%' OR UPPER(TRIM(COALESCE(fx.occupation,'')))='UNEMPLOYED' OR UPPER(TRIM(COALESCE(fx.employment_status,'')))='UNEMPLOYED')",
        'pregnant' => "COALESCE(fx.member_tags,'') LIKE '%Pregnant%'",
        'breastfeeding' => "COALESCE(fx.member_tags,'') LIKE '%Breastfeeding Mother%'",
        'youth' => "(COALESCE(fx.member_tags,'') LIKE '%Youth%' OR (COALESCE(fx.age,0) BETWEEN 15 AND 30))",
        'farming_household' => "COALESCE(chp.farming_household,0)=1",
        '4ps' => "COALESCE(hbf.is_4ps,0)=1",
        'philhealth' => "COALESCE(hbf.has_philhealth,0)=1",
        'lgu_assistance' => "COALESCE(hbf.receives_lgu_assistance,0)=1",
        'priority_high' => "COALESCE(hbf.priority_level,'') IN ('High','Urgent')",
        'priority_medium' => "COALESCE(hbf.priority_level,'')='Medium'",
    ];
}

function report_family_profile_filters(): array {
    return ['farmers','pwd','senior_citizen','solo_parent','ofw','unemployed','pregnant','breastfeeding','youth'];
}

function report_available_columns(): array {
    return [
        'household_code' => ['label' => 'Household Code', 'align' => 'left'],
        'household_head_name' => ['label' => 'Household Head', 'align' => 'left'],
        'barangay_name' => ['label' => 'Barangay', 'align' => 'left'],
        'member_count' => ['label' => 'Population', 'align' => 'right'],
        'total_tree_count' => ['label' => 'Trees', 'align' => 'right'],
        'total_harvest_kg' => ['label' => 'Harvest KG', 'align' => 'right'],
        'qualification_status' => ['label' => 'Qualification', 'align' => 'left'],
        'score' => ['label' => 'Score', 'align' => 'right'],
        'latest_monitoring_date' => ['label' => 'Latest Monitoring', 'align' => 'left'],
        'total_events_attended' => ['label' => 'Events', 'align' => 'right'],
        'farming_household' => ['label' => 'Farming Household', 'align' => 'left'],
        'main_livelihood' => ['label' => 'Main Livelihood', 'align' => 'left'],
        'monthly_income_band' => ['label' => 'Income Band', 'align' => 'left'],
        'is_4ps' => ['label' => '4Ps', 'align' => 'left'],
        'has_senior' => ['label' => 'Senior', 'align' => 'left'],
        'has_pwd' => ['label' => 'PWD', 'align' => 'left'],
        'has_solo_parent' => ['label' => 'Solo Parent', 'align' => 'left'],
        'has_pregnant_member' => ['label' => 'Pregnant', 'align' => 'left'],
        'has_philhealth' => ['label' => 'PhilHealth', 'align' => 'left'],
        'receives_lgu_assistance' => ['label' => 'LGU Assistance', 'align' => 'left'],
        'priority_level' => ['label' => 'Priority', 'align' => 'left'],
    ];
}

function report_default_columns(): array {
    return ['household_code','household_head_name','barangay_name','member_count','total_tree_count','total_harvest_kg','qualification_status','latest_monitoring_date'];
}

function report_selected_columns_from_request(): array {
    $available = report_available_columns();
    $requested = $_GET['columns'] ?? report_default_columns();
    if (!is_array($requested)) {
        $requested = [$requested];
    }
    $selected = [];
    foreach ($requested as $column) {
        $column = trim((string)$column);
        if ($column !== '' && isset($available[$column]) && !in_array($column, $selected, true)) {
            $selected[] = $column;
        }
    }
    return $selected ?: report_default_columns();
}

function report_build_filters(mysqli $conn): array {
    $barangay = (int)($_GET['barangay_id'] ?? 0);
    $status = trim((string)($_GET['qualification_status'] ?? ''));
    $profileFilter = trim((string)($_GET['profile_filter'] ?? ''));
    $detailMode = trim((string)($_GET['detail_mode'] ?? 'detailed'));
    if (!in_array($detailMode, ['summary','detailed'], true)) {
        $detailMode = 'detailed';
    }
    return [
        'barangay_id' => $barangay,
        'qualification_status' => $status,
        'profile_filter' => $profileFilter,
        'detail_mode' => $detailMode,
        'generated_at' => date('Y-m-d H:i:s'),
    ];
}

function report_household_where_sql(mysqli $conn, array $filters): string {
    $conditionMap = report_condition_map();
    $familyProfileFilters = report_family_profile_filters();
    $where = ["COALESCE(h.record_status,'active')='active'"];
    if (($filters['barangay_id'] ?? 0) > 0) {
        $where[] = 'h.barangay_id=' . (int)$filters['barangay_id'];
    }
    if (($filters['qualification_status'] ?? '') !== '') {
        $where[] = "hq.qualification_status='" . $conn->real_escape_string((string)$filters['qualification_status']) . "'";
    }
    $profileFilter = (string)($filters['profile_filter'] ?? '');
    if ($profileFilter !== '' && isset($conditionMap[$profileFilter])) {
        if (in_array($profileFilter, $familyProfileFilters, true)) {
            $where[] = "EXISTS (SELECT 1 FROM family_members fx WHERE fx.household_id = h.household_id AND COALESCE(fx.is_active,1)=1 AND {$conditionMap[$profileFilter]})";
        } else {
            $where[] = $conditionMap[$profileFilter];
        }
    }
    return ' WHERE ' . implode(' AND ', $where);
}

function report_fetch_rows(mysqli $conn, array $filters, ?int $limit = null): array {
    $whereSql = report_household_where_sql($conn, $filters);
    $limitSql = $limit !== null ? (' LIMIT ' . max(1, (int)$limit)) : '';
    $sql = "SELECT v.household_id, v.household_code, v.household_head_name, v.barangay_name, v.member_count, v.total_tree_count, v.total_harvest_kg, v.qualification_status, v.score, v.latest_monitoring_date, v.total_events_attended,
        COALESCE(chp.farming_household,0) AS farming_household,
        COALESCE(chp.main_livelihood,'') AS main_livelihood,
        COALESCE(chp.monthly_income_band,'') AS monthly_income_band,
        COALESCE(hbf.is_4ps,0) AS is_4ps,
        COALESCE(hbf.has_senior,0) AS has_senior,
        COALESCE(hbf.has_pwd,0) AS has_pwd,
        COALESCE(hbf.has_solo_parent,0) AS has_solo_parent,
        COALESCE(hbf.has_pregnant_member,0) AS has_pregnant_member,
        COALESCE(hbf.has_philhealth,0) AS has_philhealth,
        COALESCE(hbf.receives_lgu_assistance,0) AS receives_lgu_assistance,
        COALESCE(hbf.priority_level,'') AS priority_level
        FROM v_family_consolidated v
        LEFT JOIN cbms_household_profiles chp ON chp.household_id = v.household_id
        LEFT JOIN household_beneficiary_flags hbf ON hbf.household_id = v.household_id
        WHERE v.household_id IN (
            SELECT h.household_id
            FROM households h
            LEFT JOIN household_qualification hq ON hq.household_id = h.household_id
            LEFT JOIN cbms_household_profiles chp ON chp.household_id = h.household_id
            LEFT JOIN household_beneficiary_flags hbf ON hbf.household_id = h.household_id
            {$whereSql}
        )
        ORDER BY v.score DESC, v.household_head_name ASC{$limitSql}";
    return fetch_all_assoc($conn, $sql);
}

function report_summary_cards(array $rows): array {
    $households = count($rows);
    $members = 0;
    $trees = 0;
    $harvest = 0.0;
    $qualified = 0;
    foreach ($rows as $row) {
        $members += (int)($row['member_count'] ?? 0);
        $trees += (int)($row['total_tree_count'] ?? 0);
        $harvest += (float)($row['total_harvest_kg'] ?? 0);
        $status = strtolower(trim((string)($row['qualification_status'] ?? '')));
        if (in_array($status, ['qualified','highly qualified'], true)) {
            $qualified++;
        }
    }
    return [
        ['label' => 'Households', 'value' => number_format($households)],
        ['label' => 'Population', 'value' => number_format($members)],
        ['label' => 'Trees', 'value' => number_format($trees)],
        ['label' => 'Harvest KG', 'value' => number_format($harvest, 2)],
        ['label' => 'Qualified', 'value' => number_format($qualified)],
    ];
}

function report_cell_display(string $column, array $row): string {
    $value = $row[$column] ?? '';
    return match ($column) {
        'farming_household','is_4ps','has_senior','has_pwd','has_solo_parent','has_pregnant_member','has_philhealth','receives_lgu_assistance' => !empty($value) ? 'Yes' : 'No',
        'score' => $value === '' ? '' : rtrim(rtrim(number_format((float)$value, 2, '.', ''), '0'), '.'),
        'total_harvest_kg' => $value === '' ? '' : number_format((float)$value, 2),
        'member_count','total_tree_count','total_events_attended' => (string)(int)$value,
        'latest_monitoring_date' => $value ?: 'Not yet monitored',
        default => (string)$value,
    };
}

function report_export_filename(string $extension): string {
    return 'harvest_advanced_report_' . date('Ymd_His') . '.' . $extension;
}

function report_export_query(array $filters, array $columns): string {
    $query = [
        'barangay_id' => $filters['barangay_id'] ?: null,
        'qualification_status' => $filters['qualification_status'] ?: null,
        'profile_filter' => $filters['profile_filter'] ?: null,
        'detail_mode' => $filters['detail_mode'] ?: 'detailed',
        'columns' => $columns,
    ];
    return http_build_query($query);
}
