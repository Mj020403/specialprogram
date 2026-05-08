<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once app_path('app/includes/app_helpers.php');
require_once app_path('app/includes/helpers/households.php');
header('Content-Type: application/json; charset=utf-8');

$conn = db_conn();
ensure_family_upgrade_schema($conn);

$id = isset($_GET['id']) ? (int)($_GET['id']) : 0;
if ($id > 0) {
    $row = fetch_one($conn, "SELECT h.household_id,h.household_code,h.reference_no,h.household_head_name,h.contact_number,h.program_participation_count,h.household_size,h.full_address,b.barangay_name,q.qualification_status,(SELECT qr_reference FROM qr_codes WHERE household_id=h.household_id AND qr_type='HOUSEHOLD' ORDER BY qr_id DESC LIMIT 1) AS qr_reference FROM households h LEFT JOIN barangays b ON b.barangay_id=h.barangay_id LEFT JOIN household_qualification q ON q.household_id=h.household_id WHERE h.household_id={$id} LIMIT 1");
    if (!$row) { echo json_encode(['ok' => false, 'data' => null]); exit; }
    $head = household_head_snapshot($conn, $id);
    $row['head_name'] = $head['head_name'] ?? ($row['household_head_name'] ?? '');
    $row['photo_url'] = $head['photo_url'] ?? household_profile_photo($conn, $id, null);
    $row['pending_actions'] = household_pending_actions($conn, $id);
    $row['active_crops'] = fetch_all_assoc($conn, "SELECT crop_name, tree_count FROM crops WHERE household_id={$id} AND crop_status='Active' ORDER BY crop_name");
    $row['total_trees'] = (int)scalar($conn, "SELECT COALESCE(SUM(tree_count),0) FROM crops WHERE household_id={$id} AND crop_status='Active'", 0);
    $row['family_members'] = fetch_all_assoc($conn, "SELECT full_name, relationship_to_head, member_photo_path FROM family_members WHERE household_id={$id} AND is_active=1 ORDER BY is_household_head DESC, full_name");
    $row['latest_monitoring'] = fetch_one($conn, "SELECT monitoring_date,crop_condition,fruiting_status FROM monitoring_visits WHERE household_id={$id} ORDER BY monitoring_id DESC LIMIT 1");
    $row['completion'] = ['overall' => min(100, (int)((($row['household_size'] ?? 0) > 0 ? 40 : 0) + (!empty($row['active_crops']) ? 30 : 0) + (!empty($row['qualification_status']) ? 30 : 0)))];
    $latest = fetch_one($conn, "SELECT register_no, intended_number_of_trees, current_number_of_trees, compliance_status, primary_concern, source_of_livelihood, water_source, farm_location_notes, remarks, allowed_fruit_backyard, hh_planter_program, fruit_planting_backyard_program FROM interviews WHERE household_id={$id} ORDER BY interview_id DESC LIMIT 1");
    if ($latest) $row['latest_interview'] = $latest;
    echo json_encode(['ok' => true, 'data' => $row]);
    exit;
}

$q = trim((string)($_GET['q'] ?? ''));
if ($q === '') { echo json_encode(['results' => []]); exit; }

// Use the exact same search logic as the working household list page.
$sql = household_search_sql($conn, $q, 0, '', 'active', '');
$sql .= ' LIMIT 30';
$rows = fetch_all_assoc($conn, $sql);

$results = [];
foreach ($rows as $row) {
    $hid = (int)($row['household_id'] ?? 0);
    $results[] = [
        'household_id' => $hid,
        'household_code' => $row['household_code'] ?? '',
        'household_head_name' => $row['household_head_name'] ?? '',
        'contact_number' => $row['contact_number'] ?? '',
        'full_address' => $row['full_address'] ?? '',
        'purok_sitio' => $row['purok_sitio'] ?? '',
        'barangay_name' => $row['barangay_name'] ?? '',
        'member_preview' => $row['member_summary'] ?? ($row['member_names'] ?? ''),
        'member_count' => (int)($row['household_size'] ?? 0),
        'photo_url' => household_profile_photo($conn, $hid, $row['profile_photo_path'] ?? null),
    ];
}

echo json_encode(['results' => $results, 'query' => $q]);
