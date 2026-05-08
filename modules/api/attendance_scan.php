<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';

$conn = db_conn();app_require('app/includes/auth.php');
require_role(['task_force','admin']);
require_once app_path('app/config/database.php');
app_require('app/includes/app_helpers.php');

header('Content-Type: application/json; charset=utf-8');

try {
    $user = current_user();
    sync_all_event_statuses($conn);

    $eventId = (int)($_POST['event_id'] ?? $_GET['event_id'] ?? 0);
    $qr = trim((string)($_POST['qr'] ?? $_GET['qr'] ?? ''));
    $status = trim((string)($_POST['attendance_status'] ?? $_GET['attendance_status'] ?? 'Present')) ?: 'Present';
    $method = trim((string)($_POST['method'] ?? $_GET['method'] ?? 'QR Scan')) ?: 'QR Scan';
    $notes = trim((string)($_POST['notes'] ?? $_GET['notes'] ?? '')) ?: null;

    if ($eventId <= 0 || $qr === '') {
        echo json_encode(['ok' => false, 'message' => 'Missing event or QR reference.']);
        exit;
    }

    $stmt = $conn->prepare("SELECT q.qr_id,q.qr_reference,q.qr_type,q.household_id,h.household_head_name,h.household_code FROM qr_codes q LEFT JOIN households h ON h.household_id=q.household_id WHERE q.qr_reference=? LIMIT 1");
    if (!$stmt) {
        echo json_encode(['ok' => false, 'message' => 'Could not prepare QR lookup: ' . $conn->error]);
        exit;
    }
    $stmt->bind_param('s', $qr);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        echo json_encode(['ok' => false, 'message' => 'QR not found.']);
        exit;
    }
    if (($row['qr_type'] ?? '') !== 'HOUSEHOLD' || (int)($row['household_id'] ?? 0) <= 0) {
        echo json_encode(['ok' => false, 'message' => 'This QR is not a household QR.']);
        exit;
    }

    $eligibility = function_exists('check_event_household_eligibility') ? check_event_household_eligibility($conn, $eventId, (int)$row['household_id']) : ['ok' => true];
    if (!$eligibility['ok']) {
        log_qr_rejection($conn, (int)$row['qr_id'], (int)$user['id'], 'Attendance Rejected', (string)($eligibility['message'] ?? 'Household failed attendance eligibility check'));
        echo json_encode(['ok' => false, 'message' => (string)($eligibility['message'] ?? 'This household is not eligible for this event.')]);
        exit;
    }

    log_qr_scan($conn, (int)$row['qr_id'], (int)$user['id'], 'Attendance Accepted', 'QR passed event attendance validation and will be saved');
    $save = save_event_attendance($conn, $eventId, (int)$row['household_id'], (int)$user['id'], $status, date('Y-m-d H:i:s'), null, $method, $notes);
    if (!$save['ok']) {
        echo json_encode($save);
        exit;
    }

    $snapshot = get_household_snapshot($conn, (int)$row['household_id']);
    $head = household_head_snapshot($conn, (int)$row['household_id']);
    echo json_encode([
        'ok' => true,
        'message' => $save['message'],
        'data' => [
            'event_id' => $eventId,
            'household_id' => (int)$row['household_id'],
            'household_head_name' => $row['household_head_name'] ?? '',
            'head_name' => $head['head_name'] ?? ($row['household_head_name'] ?? ''),
            'photo_url' => $head['photo_url'] ?? household_profile_photo($conn, (int)$row['household_id'], null),
            'household_code' => $row['household_code'] ?? '',
            'qr_reference' => $row['qr_reference'] ?? $qr,
            'invited_barangay' => (string)scalar($conn, "SELECT COALESCE(b.barangay_name, 'All barangays') FROM events e LEFT JOIN barangays b ON b.barangay_id=e.barangay_id WHERE e.event_id=" . $eventId . " LIMIT 1", 'All barangays'),
            'qualification_status' => $save['qualification']['qualification_status'] ?? ($snapshot['qualification_status'] ?? 'For Validation'),
            'score' => $save['qualification']['score'] ?? ($snapshot['score'] ?? 0),
            'attendance_count' => $save['attendance_count'] ?? 0,
            'snapshot' => $snapshot,
        ],
    ]);
} catch (Throwable $e) {
    http_response_code(200);
    echo json_encode([
        'ok' => false,
        'message' => 'Attendance auto-save error: ' . $e->getMessage(),
    ]);
}
