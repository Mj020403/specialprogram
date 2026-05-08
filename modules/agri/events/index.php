<?php
require_once dirname(__DIR__, 3) . '/app/bootstrap.php';

$conn = db_conn();app_require('app/includes/auth.php'); require_role(['task_force','admin','mayor']);
require_once app_path('app/config/database.php'); app_require('app/includes/app_helpers.php');
$user = current_user();
sync_all_event_statuses($conn);
ensure_golden_household_schema($conn);
$programOptions = golden_programs($conn);

$prefillEventType = trim((string)($_GET['event_type'] ?? ''));
if (!in_array($prefillEventType, ['General','Orientation','Seminar','Training','Monitoring','Awarding'], true)) $prefillEventType = 'General';
$prefillProgramId = (int)($_GET['program_id'] ?? 0);
$prefillHouseholdId = (int)($_GET['household_id'] ?? 0);
$prefillName = trim((string)($_GET['event_name'] ?? ''));
if ($prefillName === '' && $prefillEventType === 'Orientation' && $prefillProgramId > 0) {
    foreach ($programOptions as $opt) {
        if ((int)($opt['program_id'] ?? 0) === $prefillProgramId) {
            $prefillName = golden_event_auto_title($conn, $prefillProgramId, $prefillEventType ?: 'Orientation', date('Y-m-d'));
            break;
        }
    }
}

$eventAudienceOptions = [
    '' => 'All households',
    'farmers' => 'Farmers only',
    'pwd' => 'PWD only',
    'senior_citizen' => 'Senior Citizen only',
    'solo_parent' => 'Solo Parent only',
    'ofw' => 'OFW only',
    'unemployed' => 'Unemployed only',
    'pregnant' => 'Pregnant only',
    'breastfeeding' => 'Breastfeeding Mother only',
    'youth' => 'Youth only',
];
$eventsHasTargetProfileFilter = column_exists($conn, 'events', 'target_profile_filter');
$eventsHasTargetProfileLabel = column_exists($conn, 'events', 'target_profile_label');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($user['role'], ['task_force','admin'], true)) {
    $name = trim((string)post('event_name'));
    $date = post('event_date');
    $start = post('start_time');
    $end = post('end_time');
    $barangay = post('barangay_id') !== '' ? (int)post('barangay_id') : null;
    $audience = trim((string)post('target_profile_filter'));
    if (!array_key_exists($audience, $eventAudienceOptions)) $audience = '';
    $targetPrograms = array_values(array_filter(array_map('intval', (array)($_POST['target_program_ids'] ?? []))));
    $eventType = trim((string)post('event_type')) ?: 'General';
    if (!in_array($eventType, ['General','Orientation','Seminar','Training','Monitoring','Awarding'], true)) $eventType = 'General';
    if ($eventType !== 'General') {
        $audience = '';
    } else {
        $targetPrograms = [];
    }
    if ($name !== '' && $date !== '' && $start !== '' && $end !== '') {
        $venue = trim((string)post('venue')) ?: null;
        $desc = trim((string)post('description')) ?: null;
        $uid = (int)$user['id'];
        $targetLabel = $audience !== '' ? ($eventAudienceOptions[$audience] ?? 'Selected audience') : 'All households';
        $descWithAudience = trim(($desc ?: '') . ($audience !== '' ? "\n\nTarget audience: " . $targetLabel : ''));
        $targetProfileValue = $audience !== '' ? $audience : null;
        $targetLabelValue = $audience !== '' ? $targetLabel : null;
        $hasEventType = column_exists($conn, 'events', 'event_type');
        if ($eventsHasTargetProfileFilter && $eventsHasTargetProfileLabel && $hasEventType) {
            $stmt = $conn->prepare("INSERT INTO events (event_name, event_type, barangay_id, venue, description, event_date, start_time, end_time, event_status, created_by, target_profile_filter, target_profile_label) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Scheduled', ?, ?, ?)");
        } elseif ($eventsHasTargetProfileFilter && $eventsHasTargetProfileLabel) {
            $stmt = $conn->prepare("INSERT INTO events (event_name, barangay_id, venue, description, event_date, start_time, end_time, event_status, created_by, target_profile_filter, target_profile_label) VALUES (?, ?, ?, ?, ?, ?, ?, 'Scheduled', ?, ?, ?)");
        } elseif ($hasEventType) {
            $stmt = $conn->prepare("INSERT INTO events (event_name, event_type, barangay_id, venue, description, event_date, start_time, end_time, event_status, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Scheduled', ?)");
        } else {
            $stmt = $conn->prepare("INSERT INTO events (event_name, barangay_id, venue, description, event_date, start_time, end_time, event_status, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, 'Scheduled', ?)");
        }
        if ($stmt) {
            if ($eventsHasTargetProfileFilter && $eventsHasTargetProfileLabel && $hasEventType) {
                $stmt->bind_param('ssisssssiss', $name, $eventType, $barangay, $venue, $descWithAudience, $date, $start, $end, $uid, $targetProfileValue, $targetLabelValue);
            } elseif ($eventsHasTargetProfileFilter && $eventsHasTargetProfileLabel) {
                $stmt->bind_param('sisssssiss', $name, $barangay, $venue, $descWithAudience, $date, $start, $end, $uid, $targetProfileValue, $targetLabelValue);
            } elseif ($hasEventType) {
                $stmt->bind_param('ssisssssi', $name, $eventType, $barangay, $venue, $descWithAudience, $date, $start, $end, $uid);
            } else {
                $stmt->bind_param('sisssssi', $name, $barangay, $venue, $descWithAudience, $date, $start, $end, $uid);
            }
            $stmt->execute();
            $id = $stmt->insert_id;
            $stmt->close();
            ensure_event_code($conn, $id);
            if (function_exists('golden_save_event_program_targets')) { golden_save_event_program_targets($conn, $id, $targetPrograms); }
            $inviteCount = function_exists('golden_event_program_candidates') ? count(golden_event_program_candidates($conn, $id)) : 0;
            sync_all_event_statuses($conn);
            $notifTitle = in_array($eventType, ['Orientation','Seminar'], true) ? ($eventType . ' event created') : 'Upcoming event created';
            create_notification($conn, $notifTitle, 'Event "' . $name . '" is now ready for attendance scanning. Auto invited households: ' . $inviteCount . '.', 'Low', $uid, null, null, in_array($eventType, ['Orientation','Seminar'], true) ? 'Program Workflow' : 'Upcoming Event');
            app_log($conn, $uid, 'EVENTS', 'CREATE', $id, 'Event created');
            set_flash('success', 'Event saved. Matching households were auto-invited based on the selected program and workflow stage.');
        }
    }
    header('Location: /harvest/modules/agri/events/index.php'); exit;
}

$barangays = fetch_all_assoc($conn, "SELECT barangay_id, barangay_name FROM barangays ORDER BY barangay_name");
$eventTargetFilterSelect = $eventsHasTargetProfileFilter ? 'e.target_profile_filter' : "''";
$eventTargetLabelSelect = $eventsHasTargetProfileLabel ? 'e.target_profile_label' : "''";
$eventTypeSelect = column_exists($conn, 'events', 'event_type') ? 'e.event_type' : "'General'";
$rows = fetch_all_assoc($conn, "SELECT e.event_id,e.event_code,e.event_name,{$eventTypeSelect} AS event_type,b.barangay_name,e.event_date,e.start_time,e.end_time,e.event_status,COALESCE(DATE_FORMAT(e.attendance_closed_at, '%Y-%m-%d %H:%i'),'') AS attendance_closed_at,{$eventTargetFilterSelect} AS target_profile_filter,{$eventTargetLabelSelect} AS target_profile_label,(SELECT COUNT(*) FROM event_attendance ea WHERE ea.event_id=e.event_id) AS attendance_total,(SELECT COUNT(*) FROM event_attendance ea WHERE ea.event_id=e.event_id AND ea.attendance_status IN ('Present','Late')) AS attendance_present FROM events e LEFT JOIN barangays b ON b.barangay_id=e.barangay_id ORDER BY FIELD(e.event_status,'Ongoing','Scheduled','Completed','Cancelled'), e.event_date DESC,e.event_id DESC LIMIT 100");
app_require('app/includes/header.php');
?>
<div class="grid gap-6">
<?php if(in_array($user['role'],['task_force','admin'],true)): ?>
<section class="rounded-[2rem] border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-6 shadow-sm">
    <div class="text-sm text-slate-500">Event automation</div><?php if ($prefillHouseholdId > 0 || $prefillProgramId > 0): ?><div class="mt-3 rounded-2xl bg-emerald-50 dark:bg-emerald-950/30 p-3 text-sm text-emerald-700 dark:text-emerald-300">Creating an event from a household workflow shortcut. After attendance is recorded, orientation-ready households move automatically to field validation.</div><?php endif; ?>
    <h2 class="text-2xl font-black">Create event</h2>
    <div class="mt-4 flex flex-wrap gap-2">
        <a href="<?= e(app_url('modules/agri/events/index.php?event_type=Orientation')) ?>" class="app-btn-outline text-sm">Orientation preset</a>
        <a href="<?= e(app_url('modules/agri/events/index.php?event_type=Seminar')) ?>" class="app-btn-outline text-sm">Seminar preset</a>
        <a href="<?= e(app_url('modules/agri/events/index.php?event_type=Training')) ?>" class="app-btn-outline text-sm">Training preset</a>
        <a href="<?= e(app_url('modules/agri/events/index.php?event_type=Monitoring')) ?>" class="app-btn-outline text-sm">Monitoring preset</a>
        <a href="<?= e(app_url('modules/agri/events/index.php?event_type=Awarding')) ?>" class="app-btn-outline text-sm">Awarding preset</a>
    </div>
    <div class="mt-4 rounded-3xl bg-blue-50 dark:bg-blue-950/30 p-4 text-sm text-slate-600 dark:text-slate-300">Event status updates automatically based on the schedule. After saving, the event is immediately ready for QR attendance mode. Choose one invited barangay to restrict attendance, or leave it on all barangays invited.</div>
    <form method="POST" class="mt-5 grid gap-4 lg:grid-cols-2">
        <div class="md:col-span-2"><label class="block text-sm font-semibold mb-2">Event name</label><input name="event_name" value="<?= e($prefillName) ?>" required class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-4 py-3" placeholder="Tree distribution, barangay visit, orientation..."></div>
        <div><label class="block text-sm font-semibold mb-2">Event type</label><select name="event_type" id="event_type" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-4 py-3"><?php foreach (['General','Orientation','Seminar','Training','Monitoring','Awarding'] as $etype): ?><option value="<?= e($etype) ?>" <?= $prefillEventType === $etype ? 'selected' : '' ?>><?= e($etype) ?></option><?php endforeach; ?></select></div>
        <div><label class="block text-sm font-semibold mb-2">Invited barangay</label><select name="barangay_id" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-4 py-3"><option value="">All barangays invited</option><?php foreach($barangays as $barangay): ?><option value="<?= (int)$barangay['barangay_id'] ?>"><?= e($barangay['barangay_name']) ?></option><?php endforeach; ?></select></div>
        <div><label class="block text-sm font-semibold mb-2">Venue</label><input name="venue" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-4 py-3"></div>
        <div id="audience_filter_group"><label class="block text-sm font-semibold mb-2">Audience filter</label><select name="target_profile_filter" id="target_profile_filter" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-4 py-3"><?php foreach($eventAudienceOptions as $value=>$label): ?><option value="<?= e($value) ?>"><?= e($label) ?></option><?php endforeach; ?></select><p class="mt-2 text-xs text-slate-500">Use this for general events that target household profiles like farmers, senior citizens, or youth.</p></div>
        <div id="program_target_group" class="md:col-span-2">
            <label class="block text-sm font-semibold mb-2">Program checklist target</label>
            <p class="mb-3 text-xs text-slate-500">For orientation and seminar, choose the program so the system can auto-invite all beneficiaries in the matching pending stage.</p>
            <div class="rounded-2xl border border-slate-300 dark:border-slate-700 p-4 grid gap-3 md:grid-cols-2">
                <label class="flex items-start gap-3 rounded-2xl border border-slate-200 dark:border-slate-800 p-3"><input type="checkbox" checked disabled><span class="text-sm"><strong>All programs</strong><br><span class="text-slate-500">For Orientation or Seminar, choose exactly one program. Leave unchecked only for general events.</span></span></label>
                <?php foreach($programOptions as $program): ?>
                    <label class="flex items-start gap-3 rounded-2xl border border-slate-200 dark:border-slate-800 p-3">
                        <input type="checkbox" name="target_program_ids[]" value="<?= (int)$program['program_id'] ?>" <?= $prefillProgramId === (int)$program['program_id'] ? 'checked' : '' ?>>
                        <span class="text-sm"><strong><?= e($program['program_name']) ?></strong><br><span class="text-slate-500">Only households with approved checklist entries under this program can be marked attended.</span></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>
        <div><label class="block text-sm font-semibold mb-2">Date</label><input type="date" name="event_date" value="<?= date('Y-m-d') ?>" required class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-4 py-3"></div>
        <div><label class="block text-sm font-semibold mb-2">Start time</label><input type="time" name="start_time" value="<?= date('H:00') ?>" required class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-4 py-3"></div>
        <div><label class="block text-sm font-semibold mb-2">End time</label><input type="time" name="end_time" value="<?= date('H:00', strtotime('+2 hours')) ?>" required class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-4 py-3"></div>
        <div class="md:col-span-2"><label class="block text-sm font-semibold mb-2">Description</label><textarea name="description" rows="3" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-4 py-3" placeholder="State the purpose, target households, and field notes."></textarea></div>
        <div class="md:col-span-2 flex flex-wrap gap-3"><button class="app-btn-primary">Create event</button><a href="/harvest/modules/agri/attendance/index.php" class="app-btn-outline">Open attendance</a></div>
    </form>
</section>
<?php endif; ?>
<section class="rounded-[2rem] border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-6 shadow-sm">
    <div class="text-sm text-slate-500">Scheduled events</div>
    <h2 class="text-2xl font-black">Event list</h2>
    <div class="mt-5 overflow-hidden rounded-3xl border border-slate-200 dark:border-slate-800">
        <table class="min-w-full text-sm app-table-compact">
            <thead class="bg-slate-50 dark:bg-slate-900"><tr><th class="px-4 py-3 text-left whitespace-nowrap">Event</th><th class="px-4 py-3 text-left whitespace-nowrap">Type</th><th class="px-4 py-3 text-left whitespace-nowrap">Barangay</th><th class="px-4 py-3 text-left whitespace-nowrap">Audience</th><th class="px-4 py-3 text-left whitespace-nowrap">Program target</th><th class="px-4 py-3 text-left whitespace-nowrap">Date</th><th class="px-4 py-3 text-left whitespace-nowrap">Invited / Present</th><th class="px-4 py-3 text-left whitespace-nowrap">Attendance</th><th class="px-4 py-3 text-right whitespace-nowrap">Action</th></tr></thead>
            <tbody>
            <?php foreach($rows as $row): ?>
                <tr class="border-t border-slate-200 dark:border-slate-800">
                    <td class="px-4 py-3 font-semibold min-w-[220px]"><?= e(trim(($row['event_code'] ?: '') . ' ' . $row['event_name'])) ?></td>
                    <td class="px-4 py-3"><?= e($row['event_type'] ?: 'General') ?></td>
                    <td class="px-4 py-3"><?= e($row['barangay_name'] ?: 'All barangays') ?></td>
                    <td class="px-4 py-3"><?= e($row['target_profile_label'] ?: 'All households') ?></td>
                    <td class="px-4 py-3"><?= e(function_exists('golden_event_target_program_label') ? golden_event_target_program_label($conn, (int)$row['event_id']) : 'All programs') ?></td>
                    <td class="px-4 py-3"><?= e($row['event_date']) ?></td>
                    <td class="px-4 py-3"><?php $invitedCount = function_exists('golden_event_program_candidates') ? count(golden_event_program_candidates($conn, (int)$row['event_id'])) : 0; ?><?= (int)$invitedCount ?> invited / <?= (int)$row['attendance_present'] ?> present</td>
                    <td class="px-4 py-3"><?= $row['attendance_closed_at'] ? "Closed" : "Open" ?></td>
                    <td class="px-4 py-3 text-right"><a href="/harvest/modules/agri/attendance/index.php?event_id=<?= (int)$row['event_id'] ?>" class="app-btn-outline">Attendance</a></td>
                </tr>
            <?php endforeach; if(!$rows): ?><tr><td colspan="9" class="px-4 py-6 text-center text-slate-500">No events yet.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
</div>
<script>
(function(){
    const eventType = document.getElementById('event_type');
    const audienceGroup = document.getElementById('audience_filter_group');
    const audienceSelect = document.getElementById('target_profile_filter');
    const programGroup = document.getElementById('program_target_group');
    const programCheckboxes = Array.from(document.querySelectorAll('input[name="target_program_ids[]"]'));
    const eventNameInput = document.querySelector('input[name="event_name"]');
    const eventDateInput = document.querySelector('input[name="event_date"]');
    if (!eventType || !audienceGroup || !programGroup) return;

    function syncEventTargetMode() {
        const isGeneral = eventType.value === 'General';
        const needsProgram = eventType.value === 'Orientation' || eventType.value === 'Seminar';
        audienceGroup.style.display = isGeneral ? '' : 'none';
        programGroup.style.display = isGeneral ? 'none' : '';

        if (audienceSelect) {
            audienceSelect.disabled = !isGeneral;
            if (!isGeneral) audienceSelect.value = '';
        }

        programCheckboxes.forEach(function(box) {
            box.disabled = isGeneral;
            if (isGeneral) box.checked = false;
        });
        if (needsProgram && eventNameInput && !eventNameInput.value.trim()) {
            const checked = programCheckboxes.find((box) => box.checked);
            const label = checked ? checked.parentElement.textContent.trim().split('
')[0].trim() : 'Program';
            eventNameInput.value = label + ' ' + eventType.value;
        }
    }

    eventType.addEventListener('change', syncEventTargetMode);
    syncEventTargetMode();
})();
</script>
<?php app_require('app/includes/footer.php'); ?>
