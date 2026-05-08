<?php
require_once dirname(__DIR__, 3) . '/app/bootstrap.php';
$conn = db_conn();
app_require('app/includes/auth.php');
require_role(['task_force','admin','mayor']);
ensure_family_upgrade_schema($conn);
$q = trim((string)($_GET['q'] ?? ''));
$barangayFilter = (int)($_GET['barangay_id'] ?? 0);
$statusFilter = trim((string)($_GET['qualification_status'] ?? ''));
$profileFilter = trim((string)($_GET['profile_filter'] ?? ''));
$recordStatusFilter = trim((string)($_GET['record_status'] ?? 'active')) ?: 'active';
$rows = fetch_all_assoc($conn, household_search_sql($conn, $q, $barangayFilter, $statusFilter, $recordStatusFilter, $profileFilter));
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=harvest_filtered_families.csv');
$out = fopen('php://output', 'w');
fputcsv($out, ['Household Code','Head of Family','Barangay','Contact','Members','Record Status','Qualification','Score','Farming Household','Main Livelihood','Income Band','4Ps','Senior','PWD','Solo Parent','Pregnant','PhilHealth','LGU Assistance','Priority Level','Member Summary']);
foreach ($rows as $row) {
    fputcsv($out, [
        $row['household_code'] ?? '',
        $row['household_head_name'] ?? '',
        $row['barangay_name'] ?? '',
        $row['contact_number'] ?? '',
        $row['household_size'] ?? 0,
        $row['record_status'] ?? 'active',
        $row['qualification_status'] ?? '',
        $row['score'] ?? '',
        !empty($row['farming_household']) ? 'Yes' : 'No',
        $row['main_livelihood'] ?? '',
        $row['monthly_income_band'] ?? '',
        !empty($row['is_4ps']) ? 'Yes' : 'No',
        !empty($row['has_senior']) ? 'Yes' : 'No',
        !empty($row['has_pwd']) ? 'Yes' : 'No',
        !empty($row['has_solo_parent']) ? 'Yes' : 'No',
        !empty($row['has_pregnant_member']) ? 'Yes' : 'No',
        !empty($row['has_philhealth']) ? 'Yes' : 'No',
        !empty($row['receives_lgu_assistance']) ? 'Yes' : 'No',
        $row['priority_level'] ?? '',
        $row['member_summary'] ?? '',
    ]);
}
fclose($out);
exit;
