<?php
require_once dirname(__DIR__, 3) . '/app/bootstrap.php';

$conn = db_conn();app_require('app/includes/auth.php'); require_role(['task_force','admin','mayor']);
require_once app_path('app/config/database.php'); app_require('app/includes/app_helpers.php');
$user = current_user();
sync_all_event_statuses($conn);
ensure_golden_household_schema($conn);

$prefillEvent = (int)($_GET['event_id'] ?? 0);
$prefillHousehold = (int)($_GET['household_id'] ?? 0);
$scannerMode = isset($_GET['rapid']) ? 1 : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($user['role'], ['task_force','admin'], true)) {
    $event = (int)post('event_id');
    $household = (int)post('household_id');
    $rapid = (int)post('rapid_mode', 0) === 1;
    $action = trim((string)post('action'));
    if ($action === 'close_attendance' && $event > 0) {
        $closed = function_exists('golden_close_event_attendance') ? golden_close_event_attendance($conn, $event, (int)$user['id']) : ['ok' => false, 'message' => 'Close attendance helper is missing.'];
        set_flash($closed['ok'] ? 'success' : 'error', $closed['message'] ?? 'Attendance was not closed.');
        header('Location: /harvest/modules/agri/attendance/index.php?event_id=' . $event); exit;
    }
    if ($event > 0 && $household > 0) {
        $eventRow = fetch_one($conn, "SELECT event_name,event_status FROM events WHERE event_id={$event} LIMIT 1");
        if ($eventRow && $eventRow['event_status'] === 'Cancelled') {
            set_flash('error', 'This event is cancelled. Attendance was not saved.');
        } else {
            $uid = (int)$user['id'];
            $status = post('attendance_status') ?: 'Present';
            $timeInInput = trim((string)post('time_in'));
            $timeOutInput = trim((string)post('time_out'));
            $timeIn = $timeInInput !== '' ? str_replace('T', ' ', $timeInInput) . (strlen($timeInInput) === 16 ? ':00' : '') : date('Y-m-d H:i:s');
            $timeOut = $timeOutInput !== '' ? str_replace('T', ' ', $timeOutInput) . (strlen($timeOutInput) === 16 ? ':00' : '') : null;
            $method = post('method') ?: 'Manual';
            $notes = trim((string)post('notes')) ?: null;
            $save = save_event_attendance($conn, $event, $household, $uid, $status, $timeIn, $timeOut, $method, $notes);
            set_flash($save['ok'] ? 'success' : 'error', $save['ok'] ? ($rapid ? 'Attendance saved. Rapid mode is ready for the next scan.' : 'Attendance saved.') : ($save['message'] ?? 'Attendance was not saved.'));
        }
    } else {
        set_flash('error', 'Select an event and household first.');
    }
    $next = '/harvest/modules/agri/attendance/index.php?event_id=' . $event . ($rapid ? '&rapid=1' : '');
    header('Location: ' . $next); exit;
}

$eventHasTargetProfileFilter = column_exists($conn, 'events', 'target_profile_filter');
$eventHasTargetProfileLabel = column_exists($conn, 'events', 'target_profile_label');
$eventTypeMeta = column_exists($conn, 'events', 'event_type') ? 'event_type' : "'General' AS event_type";
$events = fetch_all_assoc($conn, "SELECT event_id, event_name, event_date, event_status, event_code, barangay_id, {$eventTypeMeta} FROM events ORDER BY FIELD(event_status,'Ongoing','Scheduled','Completed','Cancelled'), event_date DESC, event_id DESC");
$householdsSql = "SELECT household_id, household_head_name, household_code FROM households";
$targetProgramIds = [];
$householdEligibilityHint = 'All households';
if ($prefillEvent > 0 && function_exists('golden_event_details')) {
    $eventWorkflow = golden_event_details($conn, $prefillEvent);
    if ($eventWorkflow) {
        $targetProgramIds = (array)($eventWorkflow['target_program_ids'] ?? []);
        $householdEligibilityHint = function_exists('golden_event_household_status_hint') ? golden_event_household_status_hint($conn, $prefillEvent) : 'All households';
        if (($eventWorkflow['workflow_rules']['event_type'] ?? 'General') !== 'General' && function_exists('golden_event_program_candidates')) {
            $eventCandidateRows = golden_event_program_candidates($conn, $prefillEvent);
            $candidateHouseholdIds = [];
            foreach ($eventCandidateRows as $candidateRow) {
                $candidateHouseholdIds[(int)($candidateRow['household_id'] ?? 0)] = true;
            }
            if ($candidateHouseholdIds) {
                $householdsSql = "SELECT household_id, household_head_name, household_code FROM households WHERE household_id IN (" . implode(',', array_map('intval', array_keys($candidateHouseholdIds))) . ")";
            } else {
                $householdsSql = "SELECT household_id, household_head_name, household_code FROM households WHERE 1=0";
            }
        } elseif ($targetProgramIds) {
            $householdsSql = "SELECT DISTINCT h.household_id, h.household_head_name, h.household_code FROM households h JOIN household_special_programs sp ON sp.household_id=h.household_id AND sp.application_status IN ('Approved','Active','Completed','Pending Release') WHERE sp.program_id IN (" . implode(',', array_map('intval', $targetProgramIds)) . ")";
        }
    }
}
$households = fetch_all_assoc($conn, $householdsSql . " ORDER BY household_head_name");
$rows = fetch_all_assoc($conn, "SELECT ea.event_id,e.event_name,e.event_date,h.household_id,h.household_head_name,ea.attendance_status,ea.time_in,ea.time_out,ea.method FROM event_attendance ea JOIN events e ON e.event_id=ea.event_id JOIN households h ON h.household_id=ea.household_id ORDER BY ea.attendance_id DESC LIMIT 120");
$eventSummary = $prefillEvent > 0 ? event_attendance_summary($conn, $prefillEvent) : ['total'=>0,'present'=>0,'late'=>0,'absent'=>0];
$invitedHouseholds = $prefillEvent > 0 && function_exists('golden_event_program_candidates') ? golden_event_program_candidates($conn, $prefillEvent) : [];
$invitedCount = count($invitedHouseholds);
$eventTargetFilterMeta = $eventHasTargetProfileFilter ? 'e.target_profile_filter' : "''";
$eventTargetLabelMeta = $eventHasTargetProfileLabel ? 'e.target_profile_label' : "''";
$selectedEventMeta = $prefillEvent > 0 ? fetch_one($conn, "SELECT e.event_name, e.event_date, e.event_status, e.venue, e.barangay_id, COALESCE(DATE_FORMAT(e.attendance_closed_at, '%Y-%m-%d %H:%i'),'') AS attendance_closed_at, {$eventTargetFilterMeta} AS target_profile_filter, {$eventTargetLabelMeta} AS target_profile_label, {$eventTypeMeta}, b.barangay_name FROM events e LEFT JOIN barangays b ON b.barangay_id=e.barangay_id WHERE event_id={$prefillEvent} LIMIT 1") : null;
app_require('app/includes/header.php');
?>
<div class="grid gap-6 xl:grid-cols-[0.95fr_1.05fr]">
<?php if (in_array($user['role'], ['task_force','admin'], true)): ?>
<section class="rounded-[2rem] border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-6 shadow-sm">
    <div class="text-sm text-slate-500">Attendance automation</div>
    <h2 class="text-2xl font-black">Record attendance with QR or search</h2>
    <div class="mt-4 rounded-3xl bg-blue-50 dark:bg-blue-950/30 p-4 text-sm text-slate-600 dark:text-slate-300">Best flow: select one event, turn on rapid mode, then scan household QR codes one after another. Orientation and Seminar events only accept beneficiaries that are currently in the matching pending stage. End attendance when the event is finished so absentees are marked automatically.</div>

    <?php if ($selectedEventMeta): ?>
    <div class="mt-4 grid gap-3 sm:grid-cols-4">
        <div class="rounded-2xl border p-4"><div class="text-xs uppercase tracking-[0.18em] text-slate-500">Event</div><div class="mt-2 font-black"><?= e($selectedEventMeta['event_name']) ?></div><div class="text-sm text-slate-500"><?= e($selectedEventMeta['event_date']) ?> · <?= e($selectedEventMeta['event_status']) ?></div><div class="mt-1 text-sm text-slate-500">Invited: <?= e($selectedEventMeta['barangay_name'] ?: 'All barangays') ?> · Type: <?= e($selectedEventMeta['event_type'] ?: 'General') ?> · Audience: <?= e($selectedEventMeta['target_profile_label'] ?: 'All households') ?> · Programs: <?= e(function_exists('golden_event_target_program_label') ? golden_event_target_program_label($conn, $prefillEvent) : 'All programs') ?></div></div>
        <div class="rounded-2xl border p-4"><div class="text-xs uppercase tracking-[0.18em] text-slate-500">Invited households</div><div class="mt-2 text-2xl font-black"><?= (int)$invitedCount ?></div></div>
        <div class="rounded-2xl border p-4"><div class="text-xs uppercase tracking-[0.18em] text-slate-500">Present / Late</div><div class="mt-2 text-2xl font-black"><?= (int)$eventSummary['present'] + (int)$eventSummary['late'] ?></div></div>
        <div class="rounded-2xl border p-4"><div class="text-xs uppercase tracking-[0.18em] text-slate-500">Absent / Closed</div><div class="mt-2 text-2xl font-black"><?= (int)$eventSummary['absent'] ?></div><div class="text-xs text-slate-500 mt-1"><?= !empty($selectedEventMeta['attendance_closed_at']) ? 'Closed ' . e($selectedEventMeta['attendance_closed_at']) : 'Still open' ?></div></div>
    </div>
    <?php endif; ?>

    <form method="POST" class="mt-5 grid gap-4 md:grid-cols-2" id="attendanceForm">
        <div class="md:col-span-2">
            <label class="block text-sm font-semibold mb-2">Event</label>
            <select name="event_id" id="attendanceEventId" required class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-4 py-3">
                <option value="">Select event</option>
                <?php foreach($events as $event): ?>
                    <option value="<?= (int)$event['event_id'] ?>" <?= $prefillEvent === (int)$event['event_id'] ? 'selected' : '' ?>><?= e(($event['event_code'] ?: $event['event_name']) . ' · ' . $event['event_date'] . ' · ' . $event['event_status'] . ' · ' . ((int)($event['barangay_id'] ?? 0) > 0 ? ((string)scalar($conn, 'SELECT barangay_name FROM barangays WHERE barangay_id=' . (int)$event['barangay_id'] . ' LIMIT 1', 'Selected barangay')) : 'All barangays')) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="md:col-span-2 flex flex-wrap items-center gap-4 rounded-2xl border p-4">
            <label class="inline-flex items-center gap-2 font-semibold"><input type="checkbox" id="rapidMode" name="rapid_mode" value="1" <?= isset($_GET['rapid']) ? 'checked' : '' ?>> Rapid QR mode</label>
            <span class="text-sm text-slate-500">When enabled, a successful QR lookup auto-saves attendance as soon as the family is found. If the event is targeted to selected programs, only approved or active households under those programs can be saved.</span>
        </div>

        <div class="md:col-span-2 grid gap-3 md:grid-cols-[1fr_auto]">
            <div>
                <label class="block text-sm font-semibold mb-2">Household QR reference</label>
                <input id="attendanceQr" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-4 py-3" placeholder="Scan or paste QR-HH-000001" autocomplete="off">
            </div>
            <div class="self-end">
                <a href="/harvest/modules/agri/qr/scan.php" id="attendanceScannerLink" class="app-btn-outline">Open scanner</a>
            </div>
        </div>

        <div class="md:col-span-2">
            <label class="block text-sm font-semibold mb-2">Manual household fallback</label><?php if ($prefillEvent > 0): ?><div class="mb-2 text-xs text-slate-500">Eligible list: <?= e($householdEligibilityHint) ?></div><?php endif; ?>
            <select name="household_id" id="attendanceHouseholdId" required class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-4 py-3">
                <option value="">Select household</option>
                <?php foreach($households as $household): ?>
                    <option value="<?= (int)$household['household_id'] ?>" <?= $prefillHousehold === (int)$household['household_id'] ? 'selected' : '' ?>><?= e($household['household_head_name'] . ' - ' . $household['household_code']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="md:col-span-2 rounded-3xl border border-dashed border-slate-300 dark:border-slate-700 p-4" id="attendanceSummary"><div class="text-sm text-slate-500">Scan a QR or select a household to load the family card.</div></div>

        <div>
            <label class="block text-sm font-semibold mb-2">Status</label>
            <select name="attendance_status" id="attendanceStatus" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-4 py-3"><option>Present</option><option>Late</option><option>Absent</option><option>Excused</option></select>
        </div>
        <div>
            <label class="block text-sm font-semibold mb-2">Method</label>
            <select name="method" id="attendanceMethod" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-4 py-3"><option>QR Scan</option><option>Manual</option></select>
        </div>
        <div>
            <label class="block text-sm font-semibold mb-2">Time In</label>
            <input type="datetime-local" name="time_in" id="attendanceTimeIn" value="<?= date('Y-m-d\TH:i') ?>" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-4 py-3">
        </div>
        <div>
            <label class="block text-sm font-semibold mb-2">Time Out</label>
            <input type="datetime-local" name="time_out" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-4 py-3">
        </div>
        <div class="md:col-span-2">
            <label class="block text-sm font-semibold mb-2">Notes</label>
            <textarea name="notes" rows="2" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-4 py-3" placeholder="Optional note like manual correction or same-day rescan"></textarea>
        </div>
        <div class="md:col-span-2 flex flex-wrap gap-3">
            <button class="app-btn-primary" id="attendanceSubmit">Save attendance</button>
            <?php if ($prefillEvent > 0): ?>
            <button type="submit" name="action" value="close_attendance" class="app-btn-outline" onclick="return confirm('Close attendance and mark all invited but unscanned beneficiaries as absent?')">End attendance</button>
            <?php endif; ?>
            <a href="/harvest/modules/agri/events/index.php" class="app-btn-outline">Manage events</a>
        </div>
    </form>
</section>
<?php endif; ?>

<section class="rounded-[2rem] border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-6 shadow-sm">
    <div class="text-sm text-slate-500">Attendance records</div>
    <h2 class="text-2xl font-black">Latest attendance</h2>
    <div class="mt-5 overflow-hidden rounded-3xl border border-slate-200 dark:border-slate-800">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 dark:bg-slate-900"><tr><th class="px-4 py-3 text-left">Event</th><th class="px-4 py-3 text-left">Date</th><th class="px-4 py-3 text-left">Household</th><th class="px-4 py-3 text-left">Status</th><th class="px-4 py-3 text-left">Method</th></tr></thead>
            <tbody>
            <?php foreach($rows as $row): ?>
                <tr class="border-t border-slate-200 dark:border-slate-800">
                    <td class="px-4 py-3 font-semibold"><?= e($row['event_name']) ?></td>
                    <td class="px-4 py-3"><?= e($row['event_date']) ?></td>
                    <td class="px-4 py-3"><a href="/harvest/modules/agri/households/view.php?id=<?= (int)$row['household_id'] ?>" class="font-semibold hover:underline"><?= e($row['household_head_name']) ?></a></td>
                    <td class="px-4 py-3"><?= format_status_badge($row['attendance_status']) ?></td>
                    <td class="px-4 py-3"><?= e($row['method']) ?></td>
                </tr>
            <?php endforeach; if(!$rows): ?>
                <tr><td colspan="5" class="px-4 py-6 text-center text-slate-500">No attendance records yet.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
</div>
<script>
(async function(){
  const qrInput = document.getElementById('attendanceQr');
  const hhSelect = document.getElementById('attendanceHouseholdId');
  const summary = document.getElementById('attendanceSummary');
  const eventSelect = document.getElementById('attendanceEventId');
  const methodSelect = document.getElementById('attendanceMethod');
  const statusSelect = document.getElementById('attendanceStatus');
  const timeInInput = document.getElementById('attendanceTimeIn');
  const form = document.getElementById('attendanceForm');
  const rapidMode = document.getElementById('rapidMode');
  let submitLock = false;
  const scannerLink = document.getElementById('attendanceScannerLink');

  function nowLocalString(){
    const d = new Date();
    d.setMinutes(d.getMinutes() - d.getTimezoneOffset());
    return d.toISOString().slice(0,16);
  }
  function resetTime(){ if(timeInInput) timeInInput.value = nowLocalString(); }
  function syncScannerLink(){
    if(!scannerLink) return;
    const url = new URL('/harvest/modules/agri/qr/scan.php', window.location.origin);
    url.searchParams.set('context', 'attendance');
    if (eventSelect && eventSelect.value) url.searchParams.set('event_id', eventSelect.value);
    if (rapidMode && rapidMode.checked) url.searchParams.set('rapid', '1');
    scannerLink.href = url.pathname + url.search;
  }

  async function loadByHousehold(id){
    if(!id){ summary.innerHTML = '<div class="text-sm text-slate-500">Scan a QR or select a household to load the family card.</div>'; return null; }
    const res = await fetch('/harvest/modules/api/household_lookup.php?id=' + encodeURIComponent(id));
    const json = await res.json();
    if(!json.ok){ summary.innerHTML = '<div class="text-sm text-rose-500">Household not found.</div>'; return null; }
    const d = json.data || {};
    const actions = (d.pending_actions || []).length ? d.pending_actions.join(', ') : 'Ready for attendance';
    const completion = d.completion || {};
    const photo = d.photo_url || '/harvest/public/assets/img/image.jpg';
    summary.innerHTML = `
      <div class="grid gap-4 md:grid-cols-[auto_1fr] md:items-start">
        <img src="${photo}" alt="Head photo" class="h-24 w-24 rounded-[1.5rem] object-cover border border-slate-200 dark:border-slate-800 bg-slate-100">
        <div class="grid gap-3 md:grid-cols-2">
          <div>
            <div class="text-sm text-slate-500">Selected household</div>
            <div class="mt-1 text-lg font-bold">${d.head_name || d.household_head_name || '-'}</div>
            <div class="text-sm text-slate-500">${d.household_code || '-'} · ${d.barangay_name || '-'}</div>
            <div class="mt-3 text-sm">Contact: ${d.contact_number || '-'}<br>QR: ${d.qr_reference || '-'}<br>Members: ${(d.family_members || []).length || d.household_size || 0}</div>
          </div>
          <div>
            <div class="text-sm text-slate-500">Operational status</div>
            <div class="mt-1 text-sm">Qualification: ${d.qualification_status || 'For Validation'}<br>Checklist status: ${actions}<br>Completion: ${completion.overall || 0}%</div>
          </div>
        </div>
      </div>`;
    return d;
  }

  async function lookupQr(){
    const qr = (qrInput.value || '').trim();
    if(!qr) return;
    summary.innerHTML = '<div class="text-sm text-slate-500">Looking up QR...</div>';
    const params = new URLSearchParams({ qr: qr, action: 'Attendance' });
    if (eventSelect && eventSelect.value) params.set('event_id', eventSelect.value);
    const res = await fetch('/harvest/modules/api/qr_lookup.php?' + params.toString());
    const json = await res.json();
    if(!json.ok){ summary.innerHTML = '<div class="text-sm text-rose-500">QR not found. Use manual household search.</div>'; return; }
    const d = json.data || {};
    if (d.event_eligibility && d.event_eligibility.ok === false) {
      hhSelect.value = '';
      methodSelect.value = 'QR Scan';
      summary.innerHTML = '<div class="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700"><div class="font-semibold">Attendance blocked</div><div class="mt-2">' + (d.event_eligibility.message || 'This household is not eligible for this event.') + '</div></div>';
      alert(d.event_eligibility.message || 'This household is not eligible for this event.');
      return;
    }
    if(d.household_id){
      hhSelect.value = d.household_id;
      methodSelect.value = 'QR Scan';
      resetTime();
      await loadByHousehold(d.household_id);
      if (rapidMode.checked && eventSelect.value && !submitLock) {
        submitLock = true;
        statusSelect.value = 'Present';
        setTimeout(() => form.submit(), 180);
      }
    }
  }

  qrInput && qrInput.addEventListener('change', lookupQr);
  qrInput && qrInput.addEventListener('blur', lookupQr);
  qrInput && qrInput.addEventListener('keydown', function(e){ if(e.key === 'Enter'){ e.preventDefault(); lookupQr(); } });
  hhSelect && hhSelect.addEventListener('change', ()=>{ methodSelect.value = 'Manual'; loadByHousehold(hhSelect.value); });
  eventSelect && eventSelect.addEventListener('change', ()=>{ syncScannerLink(); window.location = '/harvest/modules/agri/attendance/index.php?event_id=' + encodeURIComponent(eventSelect.value) + (rapidMode.checked ? '&rapid=1' : ''); });
  rapidMode && rapidMode.addEventListener('change', ()=>{ syncScannerLink(); if(eventSelect.value){ const url = new URL(window.location.href); url.searchParams.set('event_id', eventSelect.value); rapidMode.checked ? url.searchParams.set('rapid','1') : url.searchParams.delete('rapid'); window.history.replaceState({}, '', url); } });
  if (hhSelect.value) loadByHousehold(hhSelect.value);
  resetTime();
  syncScannerLink();
})();
</script>
<?php app_require('app/includes/footer.php'); ?>
