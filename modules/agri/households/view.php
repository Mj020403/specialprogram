<?php
require_once dirname(__DIR__, 3) . '/app/bootstrap.php';
$conn = db_conn();
app_require('app/includes/auth.php');
app_require('app/includes/module_platform.php');
app_require('app/includes/helpers/module_family_views.php');
require_role(['task_force','mayor','admin','developer']);
ensure_family_upgrade_schema($conn);
ensure_module_family_support_schema($conn);
ensure_golden_household_schema($conn);
$user = current_user();
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$action = $_SERVER['REQUEST_METHOD'] === 'POST' ? (string)post('action') : '';
if ($id <= 0) { app_require('app/includes/header.php'); echo '<div class="app-toast app-toast-error">Invalid household.</div>'; app_require('app/includes/footer.php'); exit; }


if (!function_exists('next_household_letter_suffix')) {
    function next_household_letter_suffix(array $usedSuffixes): string {
        $normalized = [];
        foreach ($usedSuffixes as $suffix) {
            $suffix = strtoupper(trim((string)$suffix));
            if ($suffix !== '' && preg_match('/^[A-Z]+$/', $suffix)) $normalized[$suffix] = true;
        }
        for ($n = 1; $n <= 702; $n++) {
            $value = $n;
            $label = '';
            while ($value > 0) {
                $value--;
                $label = chr(65 + ($value % 26)) . $label;
                $value = intdiv($value, 26);
            }
            if (!isset($normalized[$label])) return $label;
        }
        return 'Z';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (in_array($action, ['archive_household','reactivate_household','delete_household'], true)) {
        $reason = trim((string)post('status_reason'));
        $targetStatus = $action === 'archive_household' ? 'archived' : ($action === 'reactivate_household' ? 'active' : 'deleted');
        if (!household_lifecycle_allowed($user, $action === 'delete_household' ? 'delete' : ($action === 'reactivate_household' ? 'reactivate' : 'archive'))) {
            set_flash('error', 'You do not have permission for this family action.');
        } elseif (set_household_record_status($conn, $id, $targetStatus, (int)$user['id'], $reason !== '' ? $reason : null)) {
            set_flash('success', 'Family record updated successfully.');
        } else {
            set_flash('error', 'Unable to update family record status.');
        }
        header('Location: ' . app_url('modules/agri/households/view.php?id=' . $id)); exit;
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = post('action');
    if (in_array($user['role'], ['task_force','admin'], true) && $action === 'add_member') {
        try {
            $memberId = save_household_member($conn, $id, $_POST, $_FILES['member_photo'] ?? null, (int)$user['id']);
        } catch (Throwable $e) {
            $memberId = 0;
        }
        if ($memberId > 0) {
            set_flash('success', 'Family member added successfully.');
            create_notification($conn, 'Family member added', 'A new member was added to household #' . $id . '.', 'Low', (int)$user['id'], $id, null, 'Qualification Updated');
        } else {
            set_flash('error', 'Unable to add family member. Please check the required member details and try again.');
        }
        header('Location: ' . app_url('modules/agri/households/view.php?id=' . $id)); exit;
    }

    if (in_array($user['role'], ['task_force','admin'], true) && $action === 'edit_member') {
        $editMemberId = (int)post('member_id');
        try {
            $memberId = update_household_member($conn, $id, $editMemberId, $_POST, $_FILES['member_photo'] ?? null, (int)$user['id']);
        } catch (Throwable $e) {
            $memberId = 0;
        }
        if ($memberId > 0) {
            set_flash('success', 'Family member updated successfully.');
        } else {
            set_flash('error', 'Unable to update family member. Please review the member details and try again.');
        }
        header('Location: ' . app_url('modules/agri/households/view.php?id=' . $id . '#members')); exit;
    }

    if (in_array($user['role'], ['task_force','admin'], true) && $action === 'create_related_family') {
        $sourceHouse = fetch_one($conn, "SELECT * FROM households WHERE household_id=" . $id . " LIMIT 1");
        $groupContextCreate = household_group_context($conn, $id);
        $head = trim((string)post('related_household_head_name'));
        $barangay = (int)($sourceHouse['barangay_id'] ?? 0);
        $contact = trim((string)post('related_contact_number'));
        $officialHhNo = trim((string)post('related_official_hh_no'));
        $baseNo = trim((string)($groupContextCreate['base_no'] ?? ''));
        if ($officialHhNo === '' && $baseNo !== '') {
            $usedSuffixes = [];
            foreach ((array)($groupContextCreate['related_families'] ?? []) as $familyRow) {
                $suffix = household_hh_suffix((string)($familyRow['official_hh_no'] ?? ''));
                if ($suffix !== null) $usedSuffixes[] = $suffix;
            }
            $officialHhNo = $baseNo . '-' . next_household_letter_suffix($usedSuffixes);
        }
        if ($head === '') {
            set_flash('error', 'Head of family is required for the additional family record.');
        } elseif ($barangay <= 0) {
            set_flash('error', 'Source household has no barangay.');
        } else {
            $stmt = $conn->prepare("INSERT INTO households (barangay_id, household_head_name, sex, birthdate, age, contact_number, purok_sitio, full_address, area_sqm, area_hectares, household_size, program_participation_count, is_active_farmer, is_fruit_planter, remarks, created_by, registered_hh_no, official_hh_no, source_hh_no, hh_base_no, hh_suffix, hh_is_excel_supplied, household_group_key, household_cluster_key, source_block_label, source_sheet_name, source_family_key) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $sex = post('related_sex') ?: null;
            $birthdate = post('related_birthdate') ?: null;
            $age = calculate_age_from_birthdate($birthdate);
            if ($age === null && post('related_age') !== '') $age = (int)post('related_age');
            $contactValue = $contact !== '' ? $contact : null;
            $purok = trim((string)post('related_purok_sitio')) ?: ($sourceHouse['purok_sitio'] ?? null);
            $address = trim((string)post('related_full_address')) ?: ($sourceHouse['full_address'] ?? null);
            $areaSqm = isset($sourceHouse['area_sqm']) && $sourceHouse['area_sqm'] !== '' ? (float)$sourceHouse['area_sqm'] : null;
            $areaHa = isset($sourceHouse['area_hectares']) && $sourceHouse['area_hectares'] !== '' ? (float)$sourceHouse['area_hectares'] : null;
            $size = 1;
            $ppc = 0;
            $active = (int)($sourceHouse['is_active_farmer'] ?? 0);
            $planter = (int)($sourceHouse['is_fruit_planter'] ?? 0);
            $remarks = trim((string)post('related_remarks')) ?: null;
            $uid = (int)$user['id'];
            $registeredHhNo = $officialHhNo !== '' ? $officialHhNo : null;
            $baseValue = $officialHhNo !== '' ? household_hh_base_no($officialHhNo) : ($baseNo !== '' ? $baseNo : null);
            $suffixValue = $officialHhNo !== '' ? household_hh_suffix($officialHhNo) : null;
            $groupKey = (string)($sourceHouse['household_group_key'] ?? '');
            if ($groupKey === '') {
                $groupKey = ($baseValue !== null && $baseValue !== '' && $suffixValue) ? ('BRGY|' . $barangay . '|BASE|' . strtoupper($baseValue)) : ('HH|' . $id);
            }
            $clusterKey = $sourceHouse['household_cluster_key'] ?? null;
            $blockLabel = $sourceHouse['source_block_label'] ?? null;
            $sheetName = $sourceHouse['source_sheet_name'] ?? null;
            $sourceFamilyKey = ($groupKey ?: ('HH|' . $id)) . '|MANUAL|' . time();
            $ok = false;
            if ($stmt) {
                $stmt->bind_param('isssisssddiiiisisssssisisss', $barangay, $head, $sex, $birthdate, $age, $contactValue, $purok, $address, $areaSqm, $areaHa, $size, $ppc, $active, $planter, $remarks, $uid, $registeredHhNo, $registeredHhNo, $registeredHhNo, $baseValue, $suffixValue, $registeredHhNo ? 1 : 0, $groupKey, $clusterKey, $blockLabel, $sheetName, $sourceFamilyKey);
                $ok = $stmt->execute();
                $newHouseholdId = (int)$stmt->insert_id;
                $stmt->close();
            }
            if (!empty($ok) && $newHouseholdId > 0) {
                ensure_household_assets($conn, $newHouseholdId, $uid);
                $headMemberId = upsert_household_head_member($conn, $newHouseholdId, [
                    'full_name' => $head,
                    'sex' => $sex,
                    'birthdate' => $birthdate,
                    'age' => $age,
                    'contact_number' => $contactValue,
                    'civil_status' => post('related_civil_status') ?: null,
                    'occupation' => post('related_occupation') ?: null,
                    'education_level' => post('related_education_level') ?: null,
                    'member_status' => 'Living in household',
                    'remarks' => $remarks,
                ], $_FILES['related_head_photo'] ?? null);
                if ($headMemberId > 0 && column_exists($conn, 'households', 'head_member_id')) {
                    $stmt2 = $conn->prepare("UPDATE households SET head_member_id=?, profile_photo_path=(SELECT member_photo_path FROM family_members WHERE member_id=? LIMIT 1) WHERE household_id=?");
                    if ($stmt2) { $stmt2->bind_param('iii', $headMemberId, $headMemberId, $newHouseholdId); $stmt2->execute(); $stmt2->close(); }
                }
                sync_household_auto_fields($conn, $newHouseholdId);
                refresh_household_qualification_php($conn, $newHouseholdId);
                create_notification($conn, 'Additional family profile', 'A new family was added under the same household grouping.', 'Low', $uid, $newHouseholdId, null, 'Qualification Updated');
                set_flash('success', 'Additional family saved inside this household grouping.');
                header('Location: ' . app_url('modules/agri/households/view.php?id=' . $newHouseholdId)); exit;
            }
            set_flash('error', 'Unable to add another family inside this household.');
        }
        header('Location: ' . app_url('modules/agri/households/view.php?id=' . $id . '#family-grouping')); exit;
    }
    if (in_array($user['role'], ['task_force','admin'], true) && $action === 'update_head') {
        $existingHeadId = (int)scalar($conn, "SELECT member_id FROM family_members WHERE household_id={$id} AND is_household_head=1 LIMIT 1", 0);
        $headId = upsert_household_head_member($conn, $id, [
            'full_name' => post('household_head_name'),
            'sex' => post('sex') ?: null,
            'birthdate' => post('birthdate') ?: null,
            'age' => calculate_age_from_birthdate(post('birthdate') ?: null) ?? (post('age') !== '' ? (int)post('age') : null),
            'contact_number' => post('contact_number') ?: null,
            'civil_status' => post('civil_status') ?: null,
            'occupation' => post('occupation') ?: null,
            'education_level' => post('education_level') ?: null,
            'member_status' => 'Living in household',
            'remarks' => post('remarks') ?: null,
            'place_of_birth' => post('place_of_birth') ?: null,
            'citizenship' => post('citizenship') ?: null,
            'language_spoken' => post('language_spoken') ?: null,
            'religious_affiliation' => post('religious_affiliation') ?: null,
            'employment_status' => post('employment_status') ?: null,
            'ofw_details' => post('ofw_details') ?: null,
            'current_skill' => post('current_skill') ?: null,
            'desired_skill' => post('desired_skill') ?: null,
            'unemployed_current_skill' => post('unemployed_current_skill') ?: null,
            'unemployed_desired_skill' => post('unemployed_desired_skill') ?: null,
            'average_monthly_income' => post('average_monthly_income') !== '' ? (float)post('average_monthly_income') : null,
            'emerging_diseases' => post('emerging_diseases') ?: null,
            'disability' => post('disability') ?: null,
        ], $_FILES['head_photo'] ?? null, $existingHeadId ?: null);
        $stmt = $conn->prepare("UPDATE households SET household_head_name=?, sex=?, birthdate=?, age=?, contact_number=?, full_address=?, purok_sitio=?, remarks=?, profile_photo_path=(SELECT member_photo_path FROM family_members WHERE member_id=? LIMIT 1), updated_by=? WHERE household_id=?");
        if ($stmt) {
            $head = trim((string)post('household_head_name'));
            $sex = post('sex') ?: null;
            $birthdate = post('birthdate') ?: null;
            $age = calculate_age_from_birthdate($birthdate);
            if ($age === null && post('age') !== '') $age = (int)post('age');
            $contact = post('contact_number') ?: null;
            $address = post('full_address') ?: null;
            $purok = post('purok_sitio') ?: null;
            $remarks = post('remarks') ?: null;
            $uid = (int)$user['id'];
            $stmt->bind_param('sssissssiii', $head, $sex, $birthdate, $age, $contact, $address, $purok, $remarks, $headId, $uid, $id);
            $stmt->execute();
            $stmt->close();
        }
        sync_household_auto_fields($conn, $id);
        set_flash('success', 'Head of family profile updated.');
        header('Location: ' . app_url('modules/agri/households/view.php?id=' . $id)); exit;
    }

    if (in_array($user['role'], ['task_force','admin'], true) && $action === 'save_sp_housing') {
        $housingPayload = [
            'housing_type' => sp_cbms_normalize_other($_POST, 'housing_type'),
            'tenure_status' => sp_cbms_normalize_other($_POST, 'tenure_status'),
            'roof_material' => sp_cbms_normalize_other($_POST, 'roof_material'),
            'wall_material' => sp_cbms_normalize_other($_POST, 'wall_material'),
            'electricity_source' => sp_cbms_normalize_other($_POST, 'electricity_source'),
            'notes' => post('notes') ?: null,
        ];
        $ok = save_cbms_profile_row($conn, 'cbms_housing_profiles', $id, $housingPayload, (int)$user['id']);
        if ($ok) {
            save_cbms_profile_row($conn, 'cbms_household_profiles', $id, [
                'housing_type' => $housingPayload['housing_type'],
                'tenure_status' => $housingPayload['tenure_status'],
                'housing_materials' => trim((string)($housingPayload['roof_material'] ?? '') . ' / ' . (string)($housingPayload['wall_material'] ?? ''), ' /'),
                'electricity_source' => $housingPayload['electricity_source'],
                'notes' => $housingPayload['notes'],
            ], (int)$user['id']);
            set_flash('success', 'Special Program housing profile saved.');
        } else {
            set_flash('error', 'Unable to save the housing profile.');
        }
        header('Location: ' . app_url('modules/agri/households/view.php?id=' . $id . '#sp-cbms')); exit;
    }

    if (in_array($user['role'], ['task_force','admin'], true) && $action === 'save_sp_livelihood') {
        $livelihoodPayload = [
            'primary_income_source' => sp_cbms_normalize_other($_POST, 'primary_income_source'),
            'main_livelihood' => sp_cbms_normalize_other($_POST, 'main_livelihood'),
            'monthly_income_band' => sp_cbms_normalize_other($_POST, 'monthly_income_band'),
            'employment_notes' => post('employment_notes') ?: null,
            'notes' => post('special_program_notes') ?: null,
        ];
        $ok = save_cbms_profile_row($conn, 'cbms_livelihood_profiles', $id, $livelihoodPayload, (int)$user['id']);
        if ($ok) {
            save_cbms_profile_row($conn, 'cbms_household_profiles', $id, [
                'livelihood_summary' => $livelihoodPayload['main_livelihood'],
                'main_livelihood' => $livelihoodPayload['main_livelihood'],
                'monthly_income_band' => $livelihoodPayload['monthly_income_band'],
                'monthly_household_income' => post('monthly_household_income') !== '' ? (float)post('monthly_household_income') : null,
                'farming_household' => post('farming_household') ? 1 : 0,
                'farm_area_hectares' => post('farm_area_hectares') !== '' ? (float)post('farm_area_hectares') : null,
                'fruit_tree_count_estimate' => post('fruit_tree_count_estimate') !== '' ? (int)post('fruit_tree_count_estimate') : null,
                'special_program_notes' => post('special_program_notes') ?: null,
            ], (int)$user['id']);
            set_flash('success', 'Special Program livelihood profile saved.');
        } else {
            set_flash('error', 'Unable to save the livelihood profile.');
        }
        header('Location: ' . app_url('modules/agri/households/view.php?id=' . $id . '#sp-cbms')); exit;
    }

    if (in_array($user['role'], ['task_force','admin'], true) && $action === 'save_sp_sanitation') {
        $sanitationPayload = [
            'water_source' => sp_cbms_normalize_other($_POST, 'water_source'),
            'toilet_type' => sp_cbms_normalize_other($_POST, 'toilet_type'),
            'waste_disposal' => sp_cbms_normalize_other($_POST, 'waste_disposal'),
            'drainage_status' => sp_cbms_normalize_other($_POST, 'drainage_status'),
            'notes' => post('notes') ?: null,
        ];
        $ok = save_cbms_profile_row($conn, 'cbms_sanitation_profiles', $id, $sanitationPayload, (int)$user['id']);
        if ($ok) {
            save_cbms_profile_row($conn, 'cbms_household_profiles', $id, [
                'water_source' => $sanitationPayload['water_source'],
                'toilet_type' => $sanitationPayload['toilet_type'],
                'waste_disposal_method' => $sanitationPayload['waste_disposal'],
                'notes' => $sanitationPayload['notes'],
            ], (int)$user['id']);
            set_flash('success', 'Special Program basic living conditions saved.');
        } else {
            set_flash('error', 'Unable to save the living conditions profile.');
        }
        header('Location: ' . app_url('modules/agri/households/view.php?id=' . $id . '#sp-cbms')); exit;
    }

    if (in_array($user['role'], ['task_force','admin'], true) && $action === 'save_sp_flags') {
        $ok = save_household_beneficiary_flags($conn, $id, $_POST, (int)$user['id']);
        if ($ok) {
            save_cbms_profile_row($conn, 'cbms_household_profiles', $id, [
                'poverty_status' => post('priority_level') ?: null,
                'special_program_notes' => post('priority_notes') ?: null,
            ], (int)$user['id']);
            set_flash('success', 'Special Program priority flags saved.');
        } else {
            set_flash('error', 'Unable to save the priority flags.');
        }
        header('Location: ' . app_url('modules/agri/households/view.php?id=' . $id . '#sp-cbms')); exit;
    }
}
    if (in_array($user['role'], ['task_force','admin'], true) && $action === 'apply_special_program') {
        $ok = apply_household_program($conn, $id, (int)post('program_id'), post('item_id') !== '' ? (int)post('item_id') : null, trim((string)post('target_notes')), (int)$user['id'], [
            'applicant_contact' => post('applicant_contact'),
            'land_location' => post('land_location'),
            'land_area_text' => post('land_area_text'),
            'ownership_type' => post('ownership_type'),
            'orientation_status' => post('orientation_status'),
            'intake_notes' => post('intake_notes'),
        ]);
        set_flash($ok ? 'success' : 'error', $ok ? 'Special program application saved as pending.' : 'Unable to save the special program application.');
        header('Location: ' . app_url('modules/agri/households/view.php?id=' . $id . '#golden-household')); exit;
    }

    if (in_array($user['role'], ['task_force','admin'], true) && $action === 'review_special_program') {
        $ok = review_household_program($conn, (int)post('application_id'), (string)post('review_status'), trim((string)post('validation_notes')), (int)$user['id']);
        set_flash($ok ? 'success' : 'error', $ok ? 'Program application reviewed.' : 'Unable to review the program application.');
        header('Location: ' . app_url('modules/agri/households/view.php?id=' . $id . '#golden-household')); exit;
    }

    if (in_array($user['role'], ['task_force','admin'], true) && $action === 'save_rule_checklist') {
        $ok = save_household_rule_checklist($conn, $id, (array)($_POST['rule_checked'] ?? []), (array)($_POST['rule_notes'] ?? []), (int)$user['id']);
        set_flash($ok ? 'success' : 'error', $ok ? 'Household rule checklist updated.' : 'Unable to update the household rule checklist.');
        header('Location: ' . app_url('modules/agri/households/view.php?id=' . $id . '#golden-household')); exit;
    }

    if (in_array($user['role'], ['task_force','admin'], true) && $action === 'add_household_violation') {
        $ok = add_household_violation($conn, $id, (int)post('violation_type_id'), (string)post('observed_on'), trim((string)post('violation_remarks')), (int)$user['id']);
        set_flash($ok ? 'success' : 'error', $ok ? 'Household violation recorded.' : 'Unable to record the violation.');
        header('Location: ' . app_url('modules/agri/households/view.php?id=' . $id . '#golden-household')); exit;
    }

    if (in_array($user['role'], ['task_force','admin'], true) && $action === 'resolve_household_violation') {
        $ok = resolve_household_violation($conn, (int)post('violation_id'), (int)$user['id']);
        set_flash($ok ? 'success' : 'error', $ok ? 'Violation marked as resolved.' : 'Unable to resolve the violation.');
        header('Location: ' . app_url('modules/agri/households/view.php?id=' . $id . '#golden-household')); exit;
    }

app_require('app/includes/header.php');
?>
<style>
.household-view details > summary{list-style:none;}
.household-view details > summary::-webkit-details-marker{display:none;}
.household-view .section-card{border-radius:2rem;border:1px solid rgb(226 232 240);background:#fff;box-shadow:0 1px 2px rgba(15,23,42,.04);}
.household-view .soft-card{border-radius:1rem;border:1px solid rgb(226 232 240);background:rgba(248,250,252,.92);}
.household-view input,.household-view select,.household-view textarea{border-color:rgb(203 213 225);}
.household-view .stack-gap > * + *{margin-top:1rem;}
</style>
<?php
$stmt = $conn->prepare("SELECT h.*,b.barangay_name,q.score,q.qualification_status,q.explanation,q.last_evaluated_at FROM households h LEFT JOIN barangays b ON b.barangay_id=h.barangay_id LEFT JOIN household_qualification q ON q.household_id=h.household_id WHERE h.household_id=? LIMIT 1");
$stmt->bind_param('i',$id); $stmt->execute(); $house = $stmt->get_result()->fetch_assoc(); $stmt->close();
if (!$house) { echo '<div class="app-toast app-toast-error">Household not found.</div>'; app_require('app/includes/footer.php'); exit; }
$members = fetch_all_assoc($conn, "SELECT member_id, full_name, relationship_to_head, sex, birthdate, age, civil_status, contact_number, email_address, occupation, education_level, member_status, member_photo_path, is_primary_farmer, is_household_head, member_tags, remarks, place_of_birth, weight_kg, height_cm, citizenship, language_spoken, religious_affiliation, employment_status, ofw_details, current_skill, desired_skill, unemployed_current_skill, unemployed_desired_skill, average_monthly_income, emerging_diseases, disability FROM family_members WHERE household_id={$id} AND is_active=1 ORDER BY is_household_head DESC, full_name");
$crops = table_exists($conn, 'crops') ? fetch_all_assoc($conn, "SELECT crop_id,crop_code,crop_name,variety,tree_count,qr_reference,current_condition,fruiting_status FROM crops WHERE household_id={$id} ORDER BY crop_id DESC") : [];
$interviews = table_exists($conn, 'interviews') ? fetch_all_assoc($conn, "SELECT interview_date,register_no,current_number_of_trees,program_participation_count,compliance_status,remarks FROM interviews WHERE household_id={$id} ORDER BY interview_id DESC LIMIT 20") : [];
$monitoring = table_exists($conn, 'monitoring_visits') ? fetch_all_assoc($conn, "SELECT monitoring_date,tree_count_observed,fruiting_status,crop_condition,harvest_kg,monitoring_method,notes FROM monitoring_visits WHERE household_id={$id} ORDER BY monitoring_id DESC LIMIT 20") : [];
$attendance = (table_exists($conn, 'event_attendance') && table_exists($conn, 'events')) ? fetch_all_assoc($conn, "SELECT e.event_name,e.event_date,a.attendance_status,a.time_in,a.time_out,a.method FROM event_attendance a JOIN events e ON e.event_id=a.event_id WHERE a.household_id={$id} ORDER BY a.attendance_id DESC LIMIT 20") : [];
$specialPrograms = household_program_applications($conn, $id);
$violationLogs = household_violation_logs($conn, $id);
$goldenSummary = household_golden_summary($conn, $id);
$nextAction = 'Add a program request to begin the workflow.';
if (!empty($specialPrograms)) {
    $firstProgram = $specialPrograms[0];
    $progName = trim(((string)($firstProgram['program_name'] ?? 'Program')) . (!empty($firstProgram['item_name']) ? ' · ' . (string)$firstProgram['item_name'] : ''));
    $appStatus = (string)($firstProgram['application_status'] ?? '');
    $orientationStatus = (string)($firstProgram['orientation_status'] ?? '');
    if ($appStatus === 'Pending Orientation') {
        $nextAction = $progName . ' still needs orientation attendance before the household can be visited.';
    } elseif ($appStatus === 'Pending Validation') {
        $nextAction = $progName . ' is ready for field validation in the actual household.';
    } elseif ($appStatus === 'Approved') {
        $nextAction = $progName . ' is approved and can now be marked active once implementation starts.';
    } elseif ($appStatus === 'Active') {
        $nextAction = $progName . ' is active. Continue compliance checking and record any violations.';
    } elseif ($appStatus === 'Completed') {
        $nextAction = $progName . ' is completed. Keep household compliance and participation records updated.';
    }
}
$programCatalog = golden_programs($conn);
$programItemsCatalog = golden_program_items($conn);
$violationTypeCatalog = golden_violation_types($conn);
$ruleChecklistRows = household_rule_checklist($conn, $id);
$qrRows = table_exists($conn, 'qr_codes') ? fetch_all_assoc($conn, "SELECT qr_reference,qr_type,total_scans,last_scanned_at FROM qr_codes WHERE household_id={$id} ORDER BY qr_id DESC") : [];
$headMember = null; foreach ($members as $m) { if (!empty($m['is_household_head'])) { $headMember = $m; break; } }
$editMemberId = isset($_GET['edit_member']) ? (int)$_GET['edit_member'] : 0;
$editMember = null;
if ($editMemberId > 0) { foreach ($members as $m) { if ((int)$m['member_id'] === $editMemberId && empty($m['is_household_head'])) { $editMember = $m; break; } } }
$caseSummary = household_case_summary($conn, $id);
$moduleHouse = fetch_household_shared_summary($conn, $id);
$moduleTimeline = fetch_household_timeline($conn, $id, 8);
$timelineItems = array_slice(family_timeline($conn, $id), 0, 6);
$moduleCompleteness = $moduleHouse ? compute_module_completeness($conn, $moduleHouse) : ['special_program'=>0,'beneficiaries'=>0,'cbms'=>0];
$moduleEventPreview = $moduleHouse ? fetch_household_event_preview($conn, $moduleHouse, 5) : [];
$moduleQuickActions = module_quick_actions('special_program', $id);
$latestAssistance = table_exists($conn, 'assistance_records') ? fetch_one($conn, "SELECT assistance_type, assistance_status, assistance_date FROM assistance_records WHERE household_id={$id} ORDER BY assistance_id DESC LIMIT 1") : null;
$spCbmsProfile = fetch_cbms_household_profile($conn, $id) ?: [];
$spCbmsRicher = fetch_cbms_richer_sections($conn, $id);
$spHousing = array_merge($spCbmsProfile, $spCbmsRicher['housing'] ?? []);
$spLivelihood = array_merge($spCbmsProfile, $spCbmsRicher['livelihood'] ?? []);
$spSanitation = array_merge($spCbmsProfile, $spCbmsRicher['sanitation'] ?? []);
$spFlags = fetch_household_beneficiary_flags($conn, $id) ?: [];
$housingTypeOptions = sp_cbms_housing_type_options();
$tenureStatusOptions = sp_cbms_tenure_status_options();
$roofMaterialOptions = sp_cbms_roof_material_options();
$wallMaterialOptions = sp_cbms_wall_material_options();
$electricitySourceOptions = sp_cbms_electricity_source_options();
$primaryIncomeOptions = sp_cbms_primary_income_source_options();
$mainLivelihoodOptions = sp_cbms_main_livelihood_options();
$incomeBandOptions = sp_cbms_income_band_options();
$waterSourceOptions = sp_cbms_water_source_options();
$toiletTypeOptions = sp_cbms_toilet_type_options();
$wasteDisposalOptions = sp_cbms_waste_disposal_options();
$drainageStatusOptions = sp_cbms_drainage_status_options();
$recordStatus = household_record_status($conn, $id);
$pendingActions = household_pending_actions($conn, $id);
$groupContext = household_group_context($conn, $id);
$officialHhNo = $groupContext['official_hh_no'] ?? null;
$householdType = (($groupContext['family_count'] ?? 1) > 1) ? 'Multi-family household' : 'Single-family household';
$groupingSource = !empty($groupContext['base_no']) ? 'Numeric-letter suffix' : (!empty($house['source_block_label']) ? 'Letter-only cluster' : 'Standalone HH block');
$cards = [
 ['label'=>'Program status','value'=>$goldenSummary['status'] ?? 'Needs Coaching','hint'=>'Current household workflow result'],
 ['label'=>'Programs in workflow','value'=>count($specialPrograms),'hint'=>'Requests, approvals, and active entries'],
 ['label'=>'Events attended','value'=>count($attendance),'hint'=>'Saved event participation history'],
 ['label'=>'Rules compliant','value'=>(int)($goldenSummary['rules_checked'] ?? 0) . '/' . (int)($goldenSummary['rules_total'] ?? 0),'hint'=>'Checklist items completed'],
];
?>
<div class="household-view grid gap-6">
<section class="rounded-[2rem] border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-6 shadow-sm space-y-5">
    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
            <div class="text-sm text-slate-500">Household profile</div>
            <h2 class="text-2xl font-extrabold tracking-tight"><?= e($house['household_head_name']) ?></h2>
            <div class="mt-2 text-sm text-slate-500"><?= e($house['household_code']) ?><?php if ($officialHhNo): ?> · HH No. <?= e($officialHhNo) ?><?php endif; ?> · <?= e($house['barangay_name']) ?> · Population <?= e((string)count($members)) ?></div>
            <div class="mt-3 flex flex-wrap gap-2"><span class="app-badge app-badge-sky"><?= e($householdType) ?></span><span class="app-badge app-badge-slate">Grouping: <?= e($groupingSource) ?></span><?php if (!empty($groupContext['base_no'])): ?><span class="app-badge app-badge-emerald">HH base <?= e((string)$groupContext['base_no']) ?></span><?php endif; ?></div>
        </div>
        <div class="app-print-actions app-no-print flex flex-wrap items-center gap-2 justify-start md:justify-end">
            <?= format_status_badge(ucfirst($recordStatus)) ?>
            <span class="app-badge app-badge-slate">Use the top menus for programs, attendance, compliance, and print.</span>
            <?php if ($recordStatus === 'active' && household_lifecycle_allowed($user, 'archive')): ?>
                <form method="POST" class="contents" onsubmit="return confirm('Archive this family?');">
                    <input type="hidden" name="action" value="archive_household">
                    <input type="hidden" name="status_reason" value="Archived from family profile">
                    <button class="app-btn-outline">Archive</button>
                </form>
            <?php endif; ?>
            <?php if ($recordStatus === 'archived' && household_lifecycle_allowed($user, 'reactivate')): ?>
                <form method="POST" class="contents" onsubmit="return confirm('Reactivate this family?');">
                    <input type="hidden" name="action" value="reactivate_household">
                    <button class="app-btn-primary">Reactivate</button>
                </form>
            <?php endif; ?>
            <?php if ($recordStatus !== 'deleted' && household_lifecycle_allowed($user, 'delete')): ?>
                <form method="POST" class="contents" onsubmit="return confirm('Mark this family as deleted?');">
                    <input type="hidden" name="action" value="delete_household">
                    <input type="hidden" name="status_reason" value="Deleted from family profile">
                    <button class="app-btn-outline">Delete</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
    <div class="grid gap-4 md:grid-cols-[auto_1fr] items-start">
        <img src="<?= e(member_photo_url($headMember['member_photo_path'] ?? $house['profile_photo_path'] ?? null)) ?>" alt="Head photo" class="h-28 w-28 rounded-[2rem] object-cover border border-slate-200 dark:border-slate-800">
        <div class="grid gap-3 md:grid-cols-2">
            <div class="rounded-3xl border border-slate-200 dark:border-slate-800 p-4 min-w-0"><div class="text-sm text-slate-500">Contact</div><div class="mt-1 font-semibold break-words"><?= e($house['contact_number'] ?: '-') ?></div><div class="mt-3 text-sm text-slate-500">Address</div><div class="mt-1 font-semibold break-words"><?= e($house['full_address'] ?: '-') ?></div><div class="mt-3 text-sm text-slate-500">HH No. from source</div><div class="mt-1 font-semibold break-words"><?= e($officialHhNo ?: 'Blank in source file') ?></div></div>
            <div class="rounded-3xl border border-slate-200 dark:border-slate-800 p-4 min-w-0"><div class="text-sm text-slate-500">Birthdate / Age</div><div class="mt-1 font-semibold break-words"><?= e($house['birthdate'] ?: '-') ?><?= (($houseAge = calculate_age_from_birthdate($house['birthdate'] ?? null) ?? (!empty($house['age']) ? (int)$house['age'] : null))) !== null ? ' · '.e((string)$houseAge).' yrs old' : '' ?></div><div class="mt-3 grid gap-3 sm:grid-cols-2"><div><div class="text-sm text-slate-500">Families under this household</div><div class="mt-1 text-xl font-black"><?= e((string)($groupContext['family_count'] ?? 1)) ?></div></div><div><div class="text-sm text-slate-500">Population in this whole household</div><div class="mt-1 text-xl font-black"><?= e((string)($groupContext['member_count'] ?? count($members))) ?></div></div><div><div class="text-sm text-slate-500">Population in this family</div><div class="mt-1 text-xl font-black"><?= e((string)count($members)) ?></div></div><?php if (($groupContext['family_count'] ?? 1) > 1): ?><div><div class="text-sm text-slate-500">Related families listed</div><div class="mt-1 text-xl font-black"><?= e((string)count($groupContext['related_families'] ?? [])) ?></div></div><?php endif; ?></div></div>
        </div>
    </div>
    <div class="rounded-3xl border border-slate-200 dark:border-slate-800 p-4">
        <div class="text-sm text-slate-500">Family record status</div>
        <div class="mt-2"><?= format_status_badge($house['qualification_status'] ?: 'For Validation') ?></div>
        <div class="mt-3 text-sm text-slate-600 dark:text-slate-300"><?= e($house['explanation'] ?: 'No quick household notes saved yet.') ?></div>
        <div class="mt-4 grid gap-3 sm:grid-cols-2 text-sm">
            <div><strong>Dependents:</strong> <?= (int)($caseSummary['dependents'] ?? 0) ?></div>
            <div><strong>Seniors:</strong> <?= (int)($caseSummary['seniors'] ?? 0) ?></div>
            <div><strong>PWD:</strong> <?= (int)($caseSummary['pwd'] ?? 0) ?></div>
            <div><strong>Students:</strong> <?= (int)($caseSummary['students'] ?? 0) ?></div>
        </div>
        <?php if ($latestAssistance): ?><div class="mt-3 text-xs text-slate-500">Latest assistance: <?= e($latestAssistance['assistance_type']) ?> · <?= e($latestAssistance['assistance_status']) ?> · <?= e($latestAssistance['assistance_date']) ?></div><?php endif; ?>
    </div>
    </section>
<section class="space-y-4">
    <?= automation_tip('Family builder restored', 'Use this side to review the family tree, add the remaining members, and keep the household record complete right after creating the HH.') ?>
    <div class="rounded-[2rem] border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-6 shadow-sm">
        <div class="text-sm text-slate-500">Family tree preview</div>
        <h3 class="text-2xl font-black">Household members</h3>
        <div class="mt-4"><?= household_family_tree_html($members) ?></div>
    </div>
    <?php if (in_array($user['role'], ['task_force','admin'], true)): ?>
    <div id="family-grouping" class="rounded-[2rem] border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-6 shadow-sm">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <div class="text-sm text-slate-500">Household grouping</div>
                <h3 class="text-2xl font-black">Add another family inside this household</h3>
                <p class="mt-2 text-sm text-slate-500 max-w-3xl">Use this when one household has 2 or more separate families. The new family will stay under the same household grouping, barangay, and address context, but it gets its own family record and head of family.</p>
            </div>
            <div class="rounded-2xl bg-slate-50 dark:bg-slate-900 px-4 py-3 text-sm text-slate-500">
                <div><strong>Current HH base:</strong> <?= e($groupContext['base_no'] ?: ($officialHhNo ?: 'Not set')) ?></div>
                <div><strong>Families already linked:</strong> <?= (int)($groupContext['family_count'] ?? 1) ?></div>
            </div>
        </div>
        <form method="POST" enctype="multipart/form-data" class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <input type="hidden" name="action" value="create_related_family">
            <div class="xl:col-span-3"><label class="block text-sm font-semibold mb-2">Head of family</label><input name="related_household_head_name" required class="w-full rounded-2xl border px-4 py-3" placeholder="Enter head of family"></div>
            <div><label class="block text-sm font-semibold mb-2">HH No. for this family</label><input name="related_official_hh_no" value="<?= e((!empty($groupContext['base_no']) ? ((string)$groupContext['base_no'] . '-' . next_household_letter_suffix(array_map(static fn($r) => (string)household_hh_suffix((string)($r['official_hh_no'] ?? '')), (array)($groupContext['related_families'] ?? [])))) : '')) ?>" class="w-full rounded-2xl border px-4 py-3" placeholder="Example: 20-B"></div>
            <div><label class="block text-sm font-semibold mb-2">Contact number</label><input name="related_contact_number" class="w-full rounded-2xl border px-4 py-3" placeholder="Optional"></div>
            <div><label class="block text-sm font-semibold mb-2">Purok / Sitio</label><input name="related_purok_sitio" value="<?= e((string)($house['purok_sitio'] ?? '')) ?>" class="w-full rounded-2xl border px-4 py-3"></div>
            <div><label class="block text-sm font-semibold mb-2">Sex</label><select name="related_sex" class="w-full rounded-2xl border px-4 py-3"><option value="">Not set</option><?php foreach(sex_options() as $opt): ?><option value="<?= e($opt) ?>"><?= e($opt) ?></option><?php endforeach; ?></select></div>
            <div><label class="block text-sm font-semibold mb-2">Civil status</label><select name="related_civil_status" class="w-full rounded-2xl border px-4 py-3"><option value="">Not set</option><?php foreach(civil_status_options() as $opt): ?><option value="<?= e($opt) ?>"><?= e($opt) ?></option><?php endforeach; ?></select></div>
            <div><label class="block text-sm font-semibold mb-2">Occupation</label><select name="related_occupation" class="w-full rounded-2xl border px-4 py-3"><option value="">Not set</option><?php foreach(occupation_options() as $opt): ?><option value="<?= e($opt) ?>"><?= e($opt) ?></option><?php endforeach; ?></select></div>
            <div><label class="block text-sm font-semibold mb-2">Birthdate</label><input type="date" name="related_birthdate" data-birthdate-field data-age-target="related-age" class="w-full rounded-2xl border px-4 py-3"></div>
            <div><label class="block text-sm font-semibold mb-2">Age</label><input type="number" id="related-age" name="related_age" readonly class="w-full rounded-2xl border bg-slate-50 dark:bg-slate-900 px-4 py-3"></div>
            <div><label class="block text-sm font-semibold mb-2">Education</label><select name="related_education_level" class="w-full rounded-2xl border px-4 py-3"><option value="">Not set</option><?php foreach(education_level_options() as $opt): ?><option value="<?= e($opt) ?>"><?= e($opt) ?></option><?php endforeach; ?></select></div>
            <div><label class="block text-sm font-semibold mb-2">Head photo</label><input type="file" name="related_head_photo" accept="image/*" capture="environment" class="w-full rounded-2xl border px-4 py-3"></div>
            <div class="md:col-span-2 xl:col-span-3"><label class="block text-sm font-semibold mb-2">Address</label><input name="related_full_address" value="<?= e((string)($house['full_address'] ?? '')) ?>" class="w-full rounded-2xl border px-4 py-3"></div>
            <div class="md:col-span-2 xl:col-span-3"><label class="block text-sm font-semibold mb-2">Remarks</label><textarea name="related_remarks" rows="3" class="w-full rounded-2xl border px-4 py-3" placeholder="Optional notes for this family record"></textarea></div>
            <div class="md:col-span-2 xl:col-span-3 flex flex-wrap gap-3"><button class="app-btn-primary">Add family inside household</button></div>
        </form>
    </div>
    <?php endif; ?>

    <div class="rounded-[2rem] border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-6 shadow-sm">
        <div class="flex items-start justify-between gap-3 flex-wrap">
            <div><div class="text-sm text-slate-500">Related families in the same household</div><h3 class="text-2xl font-black">HH base <?= e($groupContext['base_no'] ?? '-') ?></h3></div>
            <div class="rounded-2xl bg-slate-50 dark:bg-slate-900 px-4 py-3 text-sm text-slate-500"><div><strong><?= e((string)($groupContext['family_count'] ?? 1)) ?></strong> families</div><div><strong><?= e((string)($groupContext['member_count'] ?? count($members))) ?></strong> household members</div></div>
        </div>
        <div class="mt-4 space-y-3"><?php foreach(($groupContext['related_families'] ?? [['household_id'=>$id,'household_head_name'=>$house['household_head_name'],'official_hh_no'=>$officialHhNo,'household_code'=>$house['household_code'],'member_count'=>count($members)]]) as $familyRow): ?><div class="rounded-2xl border border-slate-200 dark:border-slate-800 p-4 flex flex-col gap-3 md:flex-row md:items-center md:justify-between"><div class="min-w-0"><div class="font-semibold break-words"><?= e($familyRow['official_hh_no'] ?: 'No HH No.') ?> · <?= e($familyRow['household_head_name'] ?: 'Unnamed family') ?></div><div class="text-sm text-slate-500 break-words"><?= (int)($familyRow['member_count'] ?? 0) ?> member(s)<?php if (!empty($familyRow['household_code'])): ?> · <?= e($familyRow['household_code']) ?><?php endif; ?></div></div><?php if ((int)$familyRow['household_id'] === $id): ?><span class="app-badge app-badge-emerald shrink-0">Current</span><?php else: ?><a href="<?= e(app_url('modules/agri/households/view.php?id=' . (int)$familyRow['household_id'])) ?>" class="app-btn-outline text-sm shrink-0">Open</a><?php endif; ?></div><?php endforeach; ?></div>
    </div>

    <div class="rounded-[2rem] border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-6 shadow-sm">
        <div class="text-sm text-slate-500">QR references</div>
        <div class="mt-4 space-y-3"><?php foreach($qrRows as $qr): ?><div class="rounded-2xl border border-slate-200 dark:border-slate-800 p-4"><div class="font-semibold"><?= e($qr['qr_reference']) ?></div><div class="mt-1 text-sm text-slate-500"><?= e($qr['qr_type']) ?> · scans: <?= e((string)$qr['total_scans']) ?> · last: <?= e($qr['last_scanned_at'] ?: 'Never') ?></div><div class="mt-3 flex flex-wrap gap-2"><a href="<?= e(app_url('modules/agri/qr/scan.php?context=lookup')) ?>" class="app-btn-outline text-sm">Scan</a><a href="<?= e(app_url('modules/agri/households/print.php?id=' . $id)) ?>" class="app-btn-outline text-sm">Print profile</a></div></div><?php endforeach; if(!$qrRows): ?><div class="text-sm text-slate-500">No QR entries yet.</div><?php endif; ?></div>
    </div>
    <div class="rounded-[2rem] border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-6 shadow-sm">
        <div class="text-sm text-slate-500">Recent family timeline</div>
        <h3 class="text-2xl font-black">Latest updates</h3>
        <div class="mt-4 space-y-3"><?php foreach($timelineItems as $item): ?><div class="rounded-2xl border border-slate-200 dark:border-slate-800 p-4"><div class="flex items-center justify-between gap-2"><div class="font-semibold"><?= e($item['title']) ?></div><div class="text-xs text-slate-500"><?= e(date('M d, Y', strtotime((string)$item['date']))) ?></div></div><div class="mt-1 text-sm text-slate-500"><?= e($item['meta']) ?></div></div><?php endforeach; if(!$timelineItems): ?><div class="text-sm text-slate-500">No activity timeline yet.</div><?php endif; ?></div>
    </div>
</section>
</div>

<div class="grid gap-6 xl:grid-cols-[0.95fr_1.05fr] mt-6">
<?php if (in_array($user['role'], ['task_force','admin'], true)): ?>
<section class="rounded-[2rem] border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-6 shadow-sm">
    <div class="flex items-start justify-between gap-4 flex-wrap">
        <div>
            <div class="text-sm text-slate-500"><?= $editMember ? 'Edit family member' : 'Add family member' ?></div>
            <h3 class="text-2xl font-black"><?= $editMember ? 'Update member profile' : 'Member profiling' ?></h3>
        </div>
        <?php if ($editMember): ?>
            <a href="<?= e(app_url('modules/agri/households/view.php?id=' . $id . '#member-form')) ?>" class="app-btn-outline">Cancel edit</a>
        <?php endif; ?>
    </div>
    <form id="member-form" method="POST" enctype="multipart/form-data" class="mt-4 grid gap-3 md:grid-cols-2">
        <input type="hidden" name="action" value="<?= $editMember ? 'edit_member' : 'add_member' ?>">
        <?php if ($editMember): ?><input type="hidden" name="member_id" value="<?= (int)$editMember['member_id'] ?>"><?php endif; ?>
        <div class="md:col-span-2"><label class="block text-sm font-semibold mb-2">Full name</label><input name="full_name" required value="<?= e($editMember['full_name'] ?? '') ?>" class="w-full rounded-2xl border px-4 py-3"></div>
        <div><label class="block text-sm font-semibold mb-2">Relationship to head</label><select name="relationship_to_head" class="w-full rounded-2xl border px-4 py-3"><?php foreach(family_member_relationship_options() as $opt): if ($opt === 'Head') continue; ?><option value="<?= e($opt) ?>" <?= (($editMember['relationship_to_head'] ?? '') === $opt) ? 'selected' : '' ?>><?= e($opt) ?></option><?php endforeach; ?></select></div>
        <div><label class="block text-sm font-semibold mb-2">Member status</label><select name="member_status" class="w-full rounded-2xl border px-4 py-3"><?php foreach(family_member_status_options() as $opt): ?><option value="<?= e($opt) ?>" <?= (($editMember['member_status'] ?? 'Living in household') === $opt) ? 'selected' : '' ?>><?= e($opt) ?></option><?php endforeach; ?></select></div>
        <div><label class="block text-sm font-semibold mb-2">Sex</label><select name="sex" class="w-full rounded-2xl border px-4 py-3"><option value="">Not set</option><?php foreach(sex_options() as $opt): ?><option value="<?= e($opt) ?>" <?= (($editMember['sex'] ?? '') === $opt) ? 'selected' : '' ?>><?= e($opt) ?></option><?php endforeach; ?></select></div>
        <div><label class="block text-sm font-semibold mb-2">Civil status</label><select name="civil_status" class="w-full rounded-2xl border px-4 py-3"><option value="">Not set</option><?php foreach(civil_status_options() as $opt): ?><option value="<?= e($opt) ?>" <?= (($editMember['civil_status'] ?? '') === $opt) ? 'selected' : '' ?>><?= e($opt) ?></option><?php endforeach; ?></select></div>
        <div><label class="block text-sm font-semibold mb-2">Birthdate</label><input type="date" name="birthdate" value="<?= e($editMember['birthdate'] ?? '') ?>" data-birthdate-field data-age-target="member-age" class="w-full rounded-2xl border px-4 py-3"></div>
        <div><label class="block text-sm font-semibold mb-2">Age</label><input type="number" id="member-age" name="age" readonly value="<?= e((string)(calculate_age_from_birthdate($editMember['birthdate'] ?? null) ?? ($editMember['age'] ?? ''))) ?>" class="w-full rounded-2xl border bg-slate-50 dark:bg-slate-900 px-4 py-3"></div>
        <div><label class="block text-sm font-semibold mb-2">Contact number</label><input name="contact_number" value="<?= e($editMember['contact_number'] ?? '') ?>" class="w-full rounded-2xl border px-4 py-3"></div>
        <div><label class="block text-sm font-semibold mb-2">Email</label><input type="email" name="email_address" value="<?= e($editMember['email_address'] ?? '') ?>" class="w-full rounded-2xl border px-4 py-3"></div>
        <div><label class="block text-sm font-semibold mb-2">Occupation</label><select name="occupation" class="w-full rounded-2xl border px-4 py-3"><option value="">Not set</option><?php foreach(occupation_options() as $opt): ?><option value="<?= e($opt) ?>" <?= (($editMember['occupation'] ?? '') === $opt) ? 'selected' : '' ?>><?= e($opt) ?></option><?php endforeach; ?></select></div>
        <div><label class="block text-sm font-semibold mb-2">Education level</label><select name="education_level" class="w-full rounded-2xl border px-4 py-3"><option value="">Not set</option><?php foreach(education_level_options() as $opt): ?><option value="<?= e($opt) ?>" <?= (($editMember['education_level'] ?? '') === $opt) ? 'selected' : '' ?>><?= e($opt) ?></option><?php endforeach; ?></select></div>
        <div><label class="block text-sm font-semibold mb-2">Place of birth</label><input name="place_of_birth" value="<?= e($editMember['place_of_birth'] ?? '') ?>" class="w-full rounded-2xl border px-4 py-3"></div>
        <div><label class="block text-sm font-semibold mb-2">Citizenship</label><select name="citizenship" class="w-full rounded-2xl border px-4 py-3"><option value="">Not set</option><?php foreach(household_citizenship_options() as $opt): ?><option value="<?= e($opt) ?>" <?= (($editMember['citizenship'] ?? '') === $opt) ? 'selected' : '' ?>><?= e($opt) ?></option><?php endforeach; ?></select></div>
        <div><label class="block text-sm font-semibold mb-2">Language spoken</label><select name="language_spoken" class="w-full rounded-2xl border px-4 py-3"><option value="">Not set</option><?php foreach(household_language_options() as $opt): ?><option value="<?= e($opt) ?>" <?= (($editMember['language_spoken'] ?? '') === $opt) ? 'selected' : '' ?>><?= e($opt) ?></option><?php endforeach; ?></select></div>
        <div><label class="block text-sm font-semibold mb-2">Religious affiliation</label><select name="religious_affiliation" class="w-full rounded-2xl border px-4 py-3"><option value="">Not set</option><?php foreach(household_religion_options() as $opt): ?><option value="<?= e($opt) ?>" <?= (($editMember['religious_affiliation'] ?? '') === $opt) ? 'selected' : '' ?>><?= e($opt) ?></option><?php endforeach; ?></select></div>
        <div><label class="block text-sm font-semibold mb-2">Employment status</label><select name="employment_status" class="w-full rounded-2xl border px-4 py-3"><option value="">Not set</option><?php foreach(household_employment_status_options() as $opt): ?><option value="<?= e($opt) ?>" <?= (($editMember['employment_status'] ?? '') === $opt) ? 'selected' : '' ?>><?= e($opt) ?></option><?php endforeach; ?></select></div>
        <div><label class="block text-sm font-semibold mb-2">OFW / years / location</label><input name="ofw_details" value="<?= e($editMember['ofw_details'] ?? '') ?>" class="w-full rounded-2xl border px-4 py-3"></div>
        <div><label class="block text-sm font-semibold mb-2">Current skill</label><input name="current_skill" value="<?= e($editMember['current_skill'] ?? '') ?>" class="w-full rounded-2xl border px-4 py-3"></div>
        <div><label class="block text-sm font-semibold mb-2">Additional skill to acquire</label><input name="desired_skill" value="<?= e($editMember['desired_skill'] ?? '') ?>" class="w-full rounded-2xl border px-4 py-3"></div>
        <div><label class="block text-sm font-semibold mb-2">Unemployed current skill</label><input name="unemployed_current_skill" value="<?= e($editMember['unemployed_current_skill'] ?? '') ?>" class="w-full rounded-2xl border px-4 py-3"></div>
        <div><label class="block text-sm font-semibold mb-2">Unemployed desired skill</label><input name="unemployed_desired_skill" value="<?= e($editMember['unemployed_desired_skill'] ?? '') ?>" class="w-full rounded-2xl border px-4 py-3"></div>
        <div><label class="block text-sm font-semibold mb-2">Average monthly income</label><input type="number" step="0.01" name="average_monthly_income" value="<?= e((string)($editMember['average_monthly_income'] ?? '')) ?>" class="w-full rounded-2xl border px-4 py-3"></div>
        <div><label class="block text-sm font-semibold mb-2">Emerging diseases</label><select name="emerging_diseases" class="w-full rounded-2xl border px-4 py-3"><option value="">Not set</option><?php foreach(household_disease_options() as $opt): ?><option value="<?= e($opt) ?>" <?= (($editMember['emerging_diseases'] ?? '') === $opt) ? 'selected' : '' ?>><?= e($opt) ?></option><?php endforeach; ?></select></div>
        <div><label class="block text-sm font-semibold mb-2">Disability</label><select name="disability" class="w-full rounded-2xl border px-4 py-3"><option value="">Not set</option><?php foreach(household_disability_options() as $opt): ?><option value="<?= e($opt) ?>" <?= (($editMember['disability'] ?? '') === $opt) ? 'selected' : '' ?>><?= e($opt) ?></option><?php endforeach; ?></select></div>
        <div><label class="block text-sm font-semibold mb-2">Photo</label><input type="file" name="member_photo" accept="image/*" capture="environment" class="w-full rounded-2xl border px-4 py-3"><?php if (!empty($editMember['member_photo_path'])): ?><div class="mt-2 flex items-center gap-3"><img src="<?= e(member_photo_url($editMember['member_photo_path'])) ?>" class="h-12 w-12 rounded-xl object-cover border" alt="Current member photo"><span class="text-xs text-slate-500">Current photo</span></div><?php endif; ?></div>
        <div class="flex items-end pb-2"><label class="inline-flex items-center gap-2"><input type="checkbox" name="is_primary_farmer" <?= !empty($editMember['is_primary_farmer']) ? 'checked' : '' ?>> <span>Primary farmer</span></label></div>
        <div class="md:col-span-2"><label class="block text-sm font-semibold mb-2">Member tags</label><div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3 rounded-2xl border px-4 py-3"><?php $existingTags = member_tags_array($editMember['member_tags'] ?? null); foreach(member_tag_options() as $opt): ?><label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" name="member_tags[]" value="<?= e($opt) ?>" <?= in_array($opt, $existingTags, true) ? 'checked' : '' ?>> <span><?= e($opt) ?></span></label><?php endforeach; ?></div></div>
        <div class="md:col-span-2"><label class="block text-sm font-semibold mb-2">Remarks</label><textarea name="remarks" rows="3" class="w-full rounded-2xl border px-4 py-3"><?= e($editMember['remarks'] ?? '') ?></textarea></div>
        <div class="md:col-span-2"><button class="app-btn-primary"><?= $editMember ? 'Update member' : 'Add member' ?></button></div>
    </form>
</section>
<?php endif; ?>
<section id="members" class="rounded-[2rem] border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-6 shadow-sm">
    <div class="text-sm text-slate-500">Family registry</div>
    <h3 class="text-xl font-extrabold tracking-tight">Family members</h3>
    <div class="mt-4 space-y-3">
        <?php foreach($members as $member): ?>
            <div class="rounded-3xl border border-slate-200 dark:border-slate-800 p-4 flex gap-4 items-start">
                <img src="<?= e(member_photo_url($member['member_photo_path'] ?? null)) ?>" class="h-16 w-16 rounded-2xl object-cover border" alt="Member photo">
                <div class="flex-1">
                    <div class="flex flex-wrap items-center gap-2"><div class="font-semibold"><?= e($member['full_name']) ?></div><?php if (!empty($member['is_household_head'])): ?><span class="app-badge app-badge-emerald">Head</span><?php endif; ?><?php if (!empty($member['is_primary_farmer'])): ?><span class="app-badge app-badge-sky">Farmer</span><?php endif; ?></div>
                    <div class="mt-1 text-sm text-slate-500"><?= e($member['relationship_to_head'] ?: 'Member') ?><?= !empty($member['sex']) ? ' · ' . e($member['sex']) : '' ?><?= (($memberAge = calculate_age_from_birthdate($member['birthdate'] ?? null) ?? (!empty($member['age']) ? (int)$member['age'] : null))) !== null ? ' · ' . e((string)$memberAge) . ' yrs' : '' ?></div>
                    <div class="mt-1 text-sm text-slate-500"><?= e($member['occupation'] ?: 'No occupation set') ?><?= !empty($member['education_level']) ? ' · ' . e($member['education_level']) : '' ?></div>
                    <div class="mt-1 text-xs text-slate-500"><?= e($member['member_status'] ?: 'Living in household') ?><?= !empty($member['contact_number']) ? ' · ' . e($member['contact_number']) : '' ?></div>
                    <div class="mt-1 text-xs text-slate-500"><?= e($member['place_of_birth'] ?: 'No place of birth') ?><?= !empty($member['citizenship']) ? ' · ' . e($member['citizenship']) : '' ?><?= !empty($member['language_spoken']) ? ' · ' . e($member['language_spoken']) : '' ?></div>
                    <div class="mt-2 text-xs"><?= member_tags_badges($member['member_tags'] ?? null) ?></div>
                    <?php if (in_array($user['role'], ['task_force','admin'], true) && empty($member['is_household_head'])): ?>
                        <div class="mt-3"><a href="<?= e(app_url('modules/agri/households/view.php?id=' . $id . '&edit_member=' . (int)$member['member_id'] . '#member-form')) ?>" class="app-btn-outline text-sm">Edit member</a></div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; if(!$members): ?><div class="text-sm text-slate-500">No family members encoded yet.</div><?php endif; ?>
    </div>
</section>
</div>
<div class="grid gap-6 xl:grid-cols-2 mt-6">
<section class="rounded-[2rem] border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-6 shadow-sm"><div class="text-sm text-slate-500">Crop registry</div><h3 class="text-xl font-extrabold tracking-tight">Registered crops</h3><div class="mt-5 overflow-hidden rounded-3xl border border-slate-200 dark:border-slate-800"><table class="min-w-full text-sm"><thead class="bg-slate-50 dark:bg-slate-900"><tr><th class="px-4 py-3 text-left">Crop</th><th class="px-4 py-3 text-left">Trees</th><th class="px-4 py-3 text-left">Condition</th><th class="px-4 py-3 text-left">QR</th></tr></thead><tbody><?php foreach($crops as $c): ?><tr class="border-t border-slate-200 dark:border-slate-800"><td class="px-4 py-3 font-semibold"><?= e($c['crop_name']) ?></td><td class="px-4 py-3"><?= e((string)$c['tree_count']) ?></td><td class="px-4 py-3"><?= format_status_badge($c['current_condition']) ?></td><td class="px-4 py-3"><?= e($c['qr_reference'] ?: '-') ?></td></tr><?php endforeach; if(!$crops): ?><tr><td colspan="4" class="px-4 py-6 text-center text-slate-500">No crops yet.</td></tr><?php endif; ?></tbody></table></div></section>




<script>
(function(){
  function syncAge(birthInput, ageInput){
    if(!birthInput || !ageInput) return;
    if(!birthInput.value){ ageInput.value=''; return; }
    const now = new Date();
    const dob = new Date(birthInput.value);
    if (isNaN(dob.getTime()) || dob > now) { ageInput.value=''; return; }
    let years = now.getFullYear() - dob.getFullYear();
    const monthDiff = now.getMonth() - dob.getMonth();
    if (monthDiff < 0 || (monthDiff === 0 && now.getDate() < dob.getDate())) years--;
    ageInput.value = years >= 0 ? years : '';
  }
  document.querySelectorAll('[data-birthdate-field]').forEach(function(input){
    const targetId = input.getAttribute('data-age-target');
    const ageInput = targetId ? document.getElementById(targetId) : null;
    input.addEventListener('change', function(){ syncAge(input, ageInput); });
    input.addEventListener('input', function(){ syncAge(input, ageInput); });
    syncAge(input, ageInput);
  });
  function syncOtherField(select){
    var otherName = select.getAttribute('data-other-select');
    if(!otherName) return;
    var form = select.closest('form');
    if(!form) return;
    var otherInput = form.querySelector('[name="' + otherName + '"]');
    if(!otherInput) return;
    if(select.value === 'Other'){
      otherInput.classList.remove('hidden');
    } else {
      otherInput.classList.add('hidden');
      otherInput.value = '';
    }
  }
  document.querySelectorAll('select[data-other-select]').forEach(function(select){
    select.addEventListener('change', function(){ syncOtherField(select); });
    syncOtherField(select);
  });
})();
</script>
<script>
(function(){
  const programSelect = document.getElementById('special-program-select');
  const itemSelect = document.getElementById('special-program-item-select');
  if (!programSelect || !itemSelect) return;
  const options = Array.from(itemSelect.querySelectorAll('option[data-program-id]'));
  function filterItems(){
    const pid = programSelect.value;
    options.forEach(opt => { opt.hidden = !!pid && opt.dataset.programId !== pid; });
    if (itemSelect.selectedOptions.length && itemSelect.selectedOptions[0].hidden) itemSelect.value='';
  }
  programSelect.addEventListener('change', filterItems);
  filterItems();
})();
</script>
<?php app_require('app/includes/footer.php'); ?>
