<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';

$conn = db_conn();app_require('app/includes/session.php');
require_once app_path('app/config/database.php');
app_require('app/includes/app_helpers.php');
header('Content-Type: application/json; charset=utf-8');
if (!isset($_SESSION['user_id'])) { http_response_code(401); echo json_encode(['ok'=>false]); exit; }
$qr = trim((string)($_GET['qr'] ?? ''));
$action = trim((string)($_GET['action'] ?? 'Lookup'));
$eventId = (int)($_GET['event_id'] ?? 0);
if ($qr === '') { echo json_encode(['ok'=>false,'message'=>'Missing QR']); exit; }
$stmt = $conn->prepare("SELECT q.qr_id,q.qr_reference,q.qr_type,q.household_id,q.crop_id,q.total_scans,q.last_scanned_at,h.household_head_name,h.household_code,b.barangay_name,c.crop_name,c.crop_code,h.contact_number,h.full_address,qh.qualification_status FROM qr_codes q LEFT JOIN households h ON h.household_id=q.household_id LEFT JOIN barangays b ON b.barangay_id=h.barangay_id LEFT JOIN crops c ON c.crop_id=q.crop_id LEFT JOIN household_qualification qh ON qh.household_id=q.household_id WHERE q.qr_reference=? LIMIT 1");
$stmt->bind_param('s',$qr); $stmt->execute(); $row = $stmt->get_result()->fetch_assoc(); $stmt->close();
if ($row) {
    $userId = (int)($_SESSION['user_id'] ?? 0);
    $eligibility = null;
    if ($action === 'Attendance' && $eventId > 0 && !empty($row['household_id']) && function_exists('check_event_household_eligibility')) {
        $eligibility = check_event_household_eligibility($conn, $eventId, (int)$row['household_id']);
        if (!$eligibility['ok']) {
            log_qr_rejection($conn, (int)$row['qr_id'], $userId, 'Attendance Rejected', (string)($eligibility['message'] ?? 'Household failed attendance eligibility check'));
        } else {
            log_qr_scan($conn, (int)$row['qr_id'], $userId, $action, 'QR passed attendance pre-check');
        }
    } else {
        log_qr_scan($conn, (int)$row['qr_id'], $userId, $action, 'QR used through live lookup');
    }
    $snapshot = !empty($row['household_id']) ? get_household_snapshot($conn, (int)$row['household_id']) : [];
    if ($snapshot) {
        $head = household_head_snapshot($conn, (int)$row['household_id']);
        $row = array_merge($row, $snapshot, ['photo_url' => $head['photo_url'] ?? household_profile_photo($conn, (int)$row['household_id'], null), 'head_name' => $head['head_name'] ?? ($snapshot['household_head_name'] ?? ($row['household_head_name'] ?? ''))]);
    } else {
        $row['pending_actions'] = household_pending_actions($conn, (int)($row['household_id'] ?? 0));
    }
}
if (!empty($row) && isset($eligibility) && is_array($eligibility)) {
    $row['event_eligibility'] = [
        'ok' => (bool)($eligibility['ok'] ?? false),
        'message' => (string)($eligibility['message'] ?? ''),
    ];
}
echo json_encode(['ok'=>(bool)$row,'data'=>$row]);
