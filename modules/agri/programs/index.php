<?php
require_once dirname(__DIR__, 3) . '/app/bootstrap.php';
$conn = db_conn();
app_require('app/includes/auth.php');
require_role(['task_force','mayor','admin','developer']);
ensure_golden_household_schema($conn);
$user = current_user();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && (string)($_POST['action'] ?? '') === 'apply_quick_program') {
    $householdId = (int)($_POST['household_id'] ?? 0);
    $programId = (int)($_POST['program_id'] ?? 0);
    $itemId = (int)($_POST['item_id'] ?? 0);
    $contact = trim((string)($_POST['applicant_contact'] ?? ''));
    $landLocation = trim((string)($_POST['land_location'] ?? ''));
    $landArea = trim((string)($_POST['land_area_text'] ?? ''));
    $ownership = trim((string)($_POST['ownership_type'] ?? ''));
    $orientation = trim((string)($_POST['orientation_status'] ?? 'For orientation'));
    $intakeNotes = trim((string)($_POST['intake_notes'] ?? ''));
    $notes = trim((string)($_POST['target_notes'] ?? 'Quick intake from program center'));

    if ($householdId <= 0 || $programId <= 0) {
        set_flash('error', 'Select a household and choose a program first.');
        redirect_to('modules/agri/programs/index.php');
    }

    $dupSql = "SELECT application_id, application_status FROM household_special_programs WHERE household_id={$householdId} AND program_id={$programId}" . ($itemId > 0 ? " AND item_id={$itemId}" : " AND (item_id IS NULL OR item_id=0)") . " AND application_status <> 'Inactive' ORDER BY application_id DESC LIMIT 1";
    $existing = fetch_one($conn, $dupSql);
    if ($existing) {
        set_flash('error', 'This household already has that program in the workflow (' . ($existing['application_status'] ?? 'Existing') . ').');
        redirect_to('modules/agri/programs/index.php?selected_household_id=' . $householdId);
    }

    $ok = apply_household_program($conn, $householdId, $programId, $itemId > 0 ? $itemId : null, $notes, (int)($user['user_id'] ?? 0), [
        'applicant_contact' => $contact,
        'land_location' => $landLocation,
        'land_area_text' => $landArea,
        'ownership_type' => $ownership,
        'scheduled_validation_date' => $scheduledValidationDate,
        'intake_notes' => $intakeNotes,
    ]);

    set_flash($ok ? 'success' : 'error', $ok ? 'Program request saved for the selected household.' : 'Could not save the program request.');
    redirect_to('modules/agri/programs/index.php?selected_household_id=' . $householdId);
}

$pendingOrientation = golden_program_queue($conn, 'Pending Orientation', 20);
$pendingPrograms = golden_program_queue($conn, 'Pending First Validation', 20);
$pendingFinalValidation = golden_program_queue($conn, 'Pending Final Validation', 20);
$pendingSeminar = golden_program_queue($conn, 'Pending Seminar', 20);
$pendingRelease = golden_program_queue($conn, 'Pending Release', 20);
$approvedPrograms = golden_program_queue($conn, 'Active', 12);
$openViolations = golden_violation_queue($conn, 'Open', 20);
$goldenHouseholds = golden_household_candidates($conn, 12);
$programCatalog = golden_programs($conn);
$programItemsCatalog = golden_program_items($conn);
$selectedHouseholdId = (int)($_GET['selected_household_id'] ?? 0);
$selectedHousehold = null;
$selectedPrograms = [];
if ($selectedHouseholdId > 0) {
    $selectedHousehold = fetch_one($conn, "SELECT h.household_id, h.household_code, h.household_head_name, h.contact_number, h.full_address, b.barangay_name, (SELECT qr_reference FROM qr_codes WHERE household_id=h.household_id AND qr_type='HOUSEHOLD' ORDER BY qr_id DESC LIMIT 1) AS qr_reference FROM households h LEFT JOIN barangays b ON b.barangay_id=h.barangay_id WHERE h.household_id={$selectedHouseholdId} LIMIT 1");
    if ($selectedHousehold) {
        $selectedPrograms = household_program_applications($conn, $selectedHouseholdId);
    }
}

app_require('app/includes/header.php');
echo nav_cards([
    ['label'=>'Pending first validation','value'=>safe_table_count($conn, 'household_special_programs', "application_status='Pending First Validation'"),'hint'=>'Waiting for first farm validation visit'],
    ['label'=>'Pending orientation','value'=>safe_table_count($conn, 'household_special_programs', "application_status='Pending Orientation'"),'hint'=>'Waiting to attend orientation event'],
    ['label'=>'Pending seminar','value'=>safe_table_count($conn, 'household_special_programs', "application_status='Pending Seminar'"),'hint'=>'Waiting to attend seminar event'],
    ['label'=>'Active household programs','value'=>safe_table_count($conn, 'household_special_programs', "application_status IN ('Approved','Active','Completed')"),'hint'=>'Active household program entries'],
    ['label'=>'Open violations','value'=>safe_table_count($conn, 'household_violations', "violation_status='Open'"),'hint'=>'Rule issues to settle'],
    ['label'=>'Golden Household candidates','value'=>count(array_filter($goldenHouseholds, fn($row) => !empty($row['golden_eligible']))),'hint'=>'Ready for Himorasak recognition'],
]);
?>
<section class="rounded-[2rem] border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-6 shadow-sm mt-6">
    <div class="flex items-center justify-between gap-3 flex-wrap">
        <div>
            <div class="text-sm text-slate-500">Operational workflow center</div>
            <h2 class="text-3xl font-black">Program intake, validation, events, release, and monitoring</h2>
            <p class="mt-2 text-sm text-slate-500">Start program requests here, then let validation, events, release, and monitoring continue the workflow step by step.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="<?= e(app_url('modules/agri/events/index.php')) ?>" class="app-btn-outline">Open events</a>
            <a href="<?= e(app_url('modules/agri/attendance/index.php')) ?>" class="app-btn-outline">Open attendance</a>
            <a href="<?= e(app_url('modules/agri/reports/index.php')) ?>" class="app-btn-primary">Open reports</a>
        </div>
    </div>
</section>

<section class="rounded-[2rem] border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-6 shadow-sm mt-6">
    <div class="flex items-center justify-between gap-3 flex-wrap">
        <div>
            <div class="text-sm text-slate-500">Quick intake</div>
            <h2 class="text-2xl font-black">Quick program enrollment</h2>
            <p class="mt-2 text-sm text-slate-500">Find the household by QR or manual search, then save the program request right here.</p>
        </div>
        <a href="<?= e(app_url('modules/agri/households/create.php')) ?>" class="app-btn-outline">Create household first</a>
    </div>
    <div class="grid gap-6 xl:grid-cols-2 mt-5">
        <div class="space-y-5">
            <div class="rounded-[1.5rem] border border-slate-200 dark:border-slate-800 p-5">
                <div class="flex items-center justify-between gap-3 flex-wrap">
                    <h3 class="text-xl font-black">Scan household QR</h3>
                    <button type="button" id="open-program-qr-scanner" class="app-btn-outline text-sm">Open scanner</button>
                </div>
                <div class="mt-4 flex gap-2 flex-wrap">
                    <input id="program-qr-input" type="text" class="flex-1 min-w-[220px] rounded-2xl border px-4 py-3" placeholder="QR-HH-005298 or scanner input" value="<?= e((string)($_GET['qr'] ?? '')) ?>">
                    <button type="button" id="program-qr-find" class="app-btn-primary">Find QR</button>
                </div>
                <div id="program-scanner-wrap" class="hidden mt-4 rounded-[1.5rem] border border-slate-200 dark:border-slate-800 p-4 space-y-3">
                    <video id="program-qr-video" class="w-full rounded-2xl bg-slate-950 aspect-video" autoplay muted playsinline></video>
                    <div class="flex gap-2 flex-wrap">
                        <button type="button" id="close-program-qr-scanner" class="app-btn-outline text-sm">Close scanner</button>
                        <label class="app-btn-outline text-sm cursor-pointer">Scan from image<input id="program-qr-image" type="file" accept="image/*" class="hidden"></label>
                    </div>
                    <div id="program-scanner-note" class="text-sm text-slate-500">Allow camera access, then point it at the household QR.</div>
                </div>
            </div>

            <div class="rounded-[1.5rem] border border-slate-200 dark:border-slate-800 p-5">
                <h3 class="text-xl font-black">Manual household search</h3>
                <div class="mt-4 flex gap-2 flex-wrap">
                    <input id="program-search-input" type="text" class="flex-1 min-w-[240px] rounded-2xl border px-4 py-3" placeholder="Household code, head, member, contact, barangay">
                    <button type="button" id="program-search-button" class="app-btn-outline">Search</button>
                </div>
                <div class="mt-3 text-sm text-slate-500">Tip: you can type part of the name like <strong>Marilou</strong>, <strong>Villarmino</strong>, or even the barangay.</div>
                <div id="program-search-results" class="mt-4 space-y-3"></div>
            </div>
        </div>

        <div class="rounded-[1.5rem] border border-slate-200 dark:border-slate-800 p-5">
            <div class="text-sm text-slate-500">Selected household</div>
            <?php if ($selectedHousehold): ?>
                <div class="mt-2 flex items-start justify-between gap-3 flex-wrap">
                    <div>
                        <h3 class="text-2xl font-black"><?= e($selectedHousehold['household_head_name']) ?></h3>
                        <div class="mt-1 text-slate-500"><?= e($selectedHousehold['household_code']) ?> · <?= e($selectedHousehold['barangay_name'] ?? 'No barangay') ?></div>
                    </div>
                    <?php if (!empty($selectedHousehold['qr_reference'])): ?>
                        <span class="app-badge app-badge-slate">QR: <?= e($selectedHousehold['qr_reference']) ?></span>
                    <?php endif; ?>
                </div>
                <div class="mt-5">
                    <div class="text-sm font-semibold mb-2">Current program statuses</div>
                    <div class="flex flex-wrap gap-2">
                        <?php if ($selectedPrograms): foreach ($selectedPrograms as $programRow): ?>
                            <span class="app-badge app-badge-slate"><?= e($programRow['program_name']) ?><?php if (!empty($programRow['item_name'])): ?> · <?= e($programRow['item_name']) ?><?php endif; ?> — <?= e($programRow['application_status']) ?></span>
                        <?php endforeach; else: ?>
                            <span class="app-badge app-badge-slate">No enrolled special programs yet.</span>
                        <?php endif; ?>
                    </div>
                </div>
                <form method="post" class="mt-6 grid gap-4 md:grid-cols-2">
                    <input type="hidden" name="action" value="apply_quick_program">
                    <input type="hidden" name="household_id" value="<?= (int)$selectedHousehold['household_id'] ?>">
                    <div>
                        <label class="block text-sm font-semibold mb-2">Program</label>
                        <select name="program_id" id="quick-program-select" class="w-full rounded-2xl border px-4 py-3" required>
                            <option value="">Choose program</option>
                            <?php foreach ($programCatalog as $program): ?>
                                <option value="<?= (int)$program['program_id'] ?>"><?= e($program['program_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold mb-2">Program item / variant</label>
                        <select name="item_id" id="quick-item-select" class="w-full rounded-2xl border px-4 py-3">
                            <option value="0">No specific item</option>
                            <?php foreach ($programItemsCatalog as $item): ?>
                                <option value="<?= (int)$item['item_id'] ?>" data-program-id="<?= (int)$item['program_id'] ?>"><?= e($item['item_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold mb-2">Applicant contact</label>
                        <input type="text" name="applicant_contact" class="w-full rounded-2xl border px-4 py-3" placeholder="0912..." value="<?= e((string)($selectedHousehold['contact_number'] ?? '')) ?>">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold mb-2">First validation date</label>
                        <input type="date" name="scheduled_validation_date" value="<?= e(date('Y-m-d')) ?>" class="w-full rounded-2xl border px-4 py-3">
                    </div>
                    <div id="gamefowl-criteria-card" class="md:col-span-2 hidden rounded-[1.5rem] border border-emerald-200 bg-emerald-50/60 p-5">
                        <div class="text-sm text-emerald-700">Gamefowl intake checklist</div>
                        <h3 class="text-xl font-black mt-1">Required before the task force schedules the farm visit</h3>
                        <div class="mt-3 grid gap-3 md:grid-cols-3 text-sm text-slate-700">
                            <div class="rounded-2xl border border-emerald-200 bg-white p-4"><strong>Land size</strong><br>At least 1 ka ektarya nga yuta</div>
                            <div class="rounded-2xl border border-emerald-200 bg-white p-4"><strong>Slope</strong><br>25-30 slope or bakilid (hanayhay)</div>
                            <div class="rounded-2xl border border-emerald-200 bg-white p-4"><strong>Budget readiness</strong><br>Naa gamay na budget para sa pagpa barug sa brooder barn</div>
                        </div>
                        <p class="mt-3 text-sm text-slate-600">Save only the intake and first validation date here. Orientation and seminar will be created later from the Events page.</p>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold mb-2">Land location</label>
                        <input type="text" name="land_location" class="w-full rounded-2xl border px-4 py-3" placeholder="Proper, Dike, Balagtas, etc.">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold mb-2">Land area / size</label>
                        <input type="text" name="land_area_text" class="w-full rounded-2xl border px-4 py-3" placeholder="1/4, 1/2, 1 ha, 3/4">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold mb-2">Ownership type</label>
                        <input type="text" name="ownership_type" class="w-full rounded-2xl border px-4 py-3" placeholder="Owned, tenant, inherited, leased">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold mb-2">Quick notes</label>
                        <input type="text" name="target_notes" class="w-full rounded-2xl border px-4 py-3" placeholder="Requested at office, field intake, etc.">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-semibold mb-2">Intake notes</label>
                        <textarea name="intake_notes" class="w-full rounded-2xl border px-4 py-3 min-h-[90px]" placeholder="Additional notes from intake sheet or interview"></textarea>
                    </div>
                    <div class="md:col-span-2 flex items-center justify-between gap-3 flex-wrap">
                        <div class="text-sm text-emerald-700">Household selected. Save the intake here. Orientation and seminar will be handled through Events later.</div>
                        <button type="submit" class="app-btn-primary">Save program request</button>
                    </div>
                </form>
            <?php else: ?>
                <div class="mt-3 rounded-[1.5rem] border border-dashed border-slate-300 p-6 text-slate-500">No household selected yet. Scan a QR or use the manual search, then choose a result.</div>
            <?php endif; ?>
        </div>
    </div>
</section>

<div class="grid gap-6 xl:grid-cols-3 mt-6">
    <section class="rounded-[2rem] border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-6 shadow-sm xl:col-span-2">
        <div class="flex items-center justify-between gap-3 flex-wrap">
            <div>
                <div class="text-sm text-slate-500">Award shortlist</div>
                <h2 class="text-2xl font-black">Golden Household candidates</h2>
            </div>
        </div>
        <div class="mt-5 overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="text-left text-slate-500">
                        <th class="px-4 py-3">Household</th>
                        <th class="px-4 py-3">Barangay</th>
                        <th class="px-4 py-3 text-right">Programs</th>
                        <th class="px-4 py-3 text-right">Events</th>
                        <th class="px-4 py-3 text-right">Open violations</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3 text-right">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($goldenHouseholds as $row): ?>
                    <tr class="border-t border-slate-200 dark:border-slate-800">
                        <td class="px-4 py-3 font-semibold"><?= e($row['household_head_name']) ?><div class="text-xs text-slate-500"><?= e($row['household_code']) ?></div></td>
                        <td class="px-4 py-3"><?= e($row['barangay_name']) ?></td>
                        <td class="px-4 py-3 text-right"><?= (int)$row['approved_programs'] ?></td>
                        <td class="px-4 py-3 text-right"><?= (int)$row['events_attended'] ?></td>
                        <td class="px-4 py-3 text-right"><?= (int)$row['open_violations'] ?></td>
                        <td class="px-4 py-3"><?= format_status_badge($row['golden_status']) ?></td>
                        <td class="px-4 py-3 text-right"><a href="<?= e(app_url('modules/agri/households/view.php?id=' . (int)$row['household_id'] . '#golden-household')) ?>" class="app-btn-outline text-sm">Open</a></td>
                    </tr>
                    <?php endforeach; if (!$goldenHouseholds): ?>
                    <tr><td colspan="7" class="px-4 py-6 text-center text-slate-500">No households available for the Golden Household checklist yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
    <section class="rounded-[2rem] border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-6 shadow-sm">
        <div class="text-sm text-slate-500">How the checklist works</div>
        <h2 class="text-2xl font-black mt-1">Qualification guide</h2>
        <div class="mt-4 space-y-3 text-sm text-slate-600 dark:text-slate-300">
            <div class="rounded-2xl border border-slate-200 dark:border-slate-800 p-4"><strong>Programs:</strong> household should have approved, active, or completed program checklist entries.</div>
            <div class="rounded-2xl border border-slate-200 dark:border-slate-800 p-4"><strong>Events:</strong> household should attend at least 3 events or trainings.</div>
            <div class="rounded-2xl border border-slate-200 dark:border-slate-800 p-4"><strong>Rules:</strong> household should have no open violations blocking the award.</div>
        </div>
    </section>
</div>

<div class="grid gap-6 xl:grid-cols-2 mt-6">
    <section class="rounded-[2rem] border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-6 shadow-sm">
        <div class="text-sm text-slate-500">Orientation queue</div>
        <h2 class="text-2xl font-black">Households waiting for orientation</h2><div class="mt-2 text-sm text-slate-500">After orientation attendance is recorded, the household automatically moves to <strong>Households for Visit</strong>.</div>
        <div class="mt-5 divide-y divide-slate-200 dark:divide-slate-800 rounded-[1.5rem] border border-slate-200 dark:border-slate-800 overflow-hidden">
            <?php foreach ($pendingOrientation as $row): ?>
                <div class="p-4 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <div>
                        <div class="font-semibold"><?= e($row['household_head_name']) ?> · <?= e($row['program_name']) ?><?php if (!empty($row['item_name'])): ?> · <?= e($row['item_name']) ?><?php endif; ?></div>
                        <div class="text-sm text-slate-500"><?= e($row['barangay_name']) ?> · Orientation: <?= e($row['orientation_status'] ?: 'Not yet scheduled') ?></div>
                    </div>
                    <?php
                        $programId = (int)($row['program_id'] ?? 0);
                        $householdId = (int)($row['household_id'] ?? 0);
                        $eventName = trim((string)(($row['program_name'] ?? 'Program') . ' Orientation'));
                        $orientationUrl = app_url('modules/agri/events/index.php?event_type=Orientation&program_id=' . $programId . '&household_id=' . $householdId . '&event_name=' . urlencode($eventName));
                    ?>
                    <div class="flex flex-wrap gap-2">
                        <?= format_status_badge($row['application_status'] ?? '') ?>
                        <a href="<?= e($orientationUrl) ?>" class="app-btn-outline text-sm">Schedule orientation</a>
                        <a href="<?= e(app_url('modules/agri/households/view.php?id=' . $householdId . '#golden-household')) ?>" class="app-btn-outline text-sm">Open household</a>
                    </div>
                </div>
            <?php endforeach; if (!$pendingOrientation): ?>
                <div class="px-4 py-6 text-center text-emerald-700">No households waiting for orientation.</div>
            <?php endif; ?>
        </div>
    </section>
    <section class="rounded-[2rem] border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-6 shadow-sm">
        <div class="text-sm text-slate-500">First validation queue</div>
        <h2 class="text-2xl font-black">Pending first farm validation</h2>
        <div class="mt-5 divide-y divide-slate-200 dark:divide-slate-800 rounded-[1.5rem] border border-slate-200 dark:border-slate-800 overflow-hidden">
            <?php foreach ($pendingPrograms as $row): ?>
                <div class="p-4 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <div>
                        <div class="font-semibold"><?= e($row['household_head_name']) ?> · <?= e($row['program_name']) ?><?php if (!empty($row['item_name'])): ?> · <?= e($row['item_name']) ?><?php endif; ?></div>
                        <div class="text-sm text-slate-500"><?= e($row['barangay_name']) ?> · Applied <?= e($row['date_applied']) ?> · First validation <?= e($row['scheduled_validation_date'] ?: 'Not set') ?></div>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <?= format_status_badge($row['application_status']) ?>
                        <a href="<?= e(app_url('modules/agri/households/view.php?id=' . (int)$row['household_id'] . '#golden-household')) ?>" class="app-btn-outline text-sm">Review in household</a>
                    </div>
                </div>
            <?php endforeach; if (!$pendingPrograms): ?>
                <div class="px-4 py-6 text-center text-slate-500">No households waiting for first validation.</div>
            <?php endif; ?>
        </div>
    </section>
    <section class="rounded-[2rem] border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-6 shadow-sm">
        <div class="text-sm text-slate-500">Event-driven queue</div>
        <h2 class="text-2xl font-black">Waiting for orientation or seminar event</h2>
        <div class="mt-5 divide-y divide-slate-200 dark:divide-slate-800 rounded-[1.5rem] border border-slate-200 dark:border-slate-800 overflow-hidden">
            <?php foreach (array_merge($pendingOrientation, $pendingSeminar) as $row): ?>
                <div class="p-4 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <div>
                        <div class="font-semibold"><?= e($row['household_head_name']) ?> · <?= e($row['program_name']) ?></div>
                        <div class="text-sm text-slate-500"><?= e($row['barangay_name']) ?> · Current stage <?= e($row['application_status']) ?></div>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <?= format_status_badge($row['application_status']) ?>
                        <a href="<?= e(app_url('modules/agri/events/index.php?event_type=' . urlencode($row['application_status'] === 'Pending Seminar' ? 'Seminar' : 'Orientation') . '&program_id=' . (int)$row['program_id'])) ?>" class="app-btn-outline text-sm">Create matching event</a>
                    </div>
                </div>
            <?php endforeach; if (!$pendingOrientation && !$pendingSeminar): ?>
                <div class="px-4 py-6 text-center text-emerald-700">No households are waiting for orientation or seminar right now.</div>
            <?php endif; ?>
        </div>
    </section>

    <section class="rounded-[2rem] border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-6 shadow-sm">
        <div class="text-sm text-slate-500">Release queue</div>
        <h2 class="text-2xl font-black">Ready for release after seminar attendance</h2>
        <div class="mt-5 divide-y divide-slate-200 dark:divide-slate-800 rounded-[1.5rem] border border-slate-200 dark:border-slate-800 overflow-hidden">
            <?php foreach ($pendingRelease as $row): ?>
                <div class="p-4 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <div>
                        <div class="font-semibold"><?= e($row['household_head_name']) ?> · <?= e($row['program_name']) ?></div>
                        <div class="text-sm text-slate-500"><?= e($row['barangay_name']) ?> · Approved chicks <?= e($row['approved_chicks_qty'] ?: '-') ?></div>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <?= format_status_badge($row['application_status']) ?>
                        <a href="<?= e(app_url('modules/agri/households/view.php?id=' . (int)$row['household_id'] . '#golden-household')) ?>" class="app-btn-outline text-sm">Open household</a>
                    </div>
                </div>
            <?php endforeach; if (!$pendingRelease): ?>
                <div class="px-4 py-6 text-center text-slate-500">No beneficiaries are waiting for release.</div>
            <?php endif; ?>
        </div>
    </section>

    <section class="rounded-[2rem] border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-6 shadow-sm">
        <div class="text-sm text-slate-500">Rule compliance queue</div>
        <h2 class="text-2xl font-black">Open household violations</h2>
        <div class="mt-5 divide-y divide-slate-200 dark:divide-slate-800 rounded-[1.5rem] border border-slate-200 dark:border-slate-800 overflow-hidden">
            <?php foreach ($openViolations as $row): ?>
                <div class="p-4 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <div>
                        <div class="font-semibold"><?= e($row['household_head_name']) ?> · <?= e($row['violation_name']) ?></div>
                        <div class="text-sm text-slate-500"><?= e($row['barangay_name']) ?> · Observed <?= e($row['observed_on']) ?></div>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <?= format_status_badge($row['violation_status']) ?>
                        <a href="<?= e(app_url('modules/agri/households/view.php?id=' . (int)$row['household_id'] . '#golden-household')) ?>" class="app-btn-outline text-sm">Open household</a>
                    </div>
                </div>
            <?php endforeach; if (!$openViolations): ?>
                <div class="px-4 py-6 text-center text-emerald-700">No open violations right now.</div>
            <?php endif; ?>
        </div>
    </section>
</div>
<?= app_dashboard_insights_panel($conn, 'Program center summary charts', 'Charts for program flow, rules, queues, and the live municipal situation so users can spot what needs action fast.') ?>
<script>
(() => {
    const searchInput = document.getElementById('program-search-input');
    const searchButton = document.getElementById('program-search-button');
    const searchResults = document.getElementById('program-search-results');
    const qrInput = document.getElementById('program-qr-input');
    const qrFind = document.getElementById('program-qr-find');
    const openScanner = document.getElementById('open-program-qr-scanner');
    const closeScanner = document.getElementById('close-program-qr-scanner');
    const scannerWrap = document.getElementById('program-scanner-wrap');
    const scannerNote = document.getElementById('program-scanner-note');
    const video = document.getElementById('program-qr-video');
    const imageInput = document.getElementById('program-qr-image');
    const programSelect = document.getElementById('quick-program-select');
    const itemSelect = document.getElementById('quick-item-select');
    let cameraStream = null;
    let detector = null;
    let scanTimer = null;
    const gamefowlCard = document.getElementById('gamefowl-criteria-card');

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, (char) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[char]));
    }

    function renderResults(results) {
        if (!searchResults) return;
        if (!results.length) {
            searchResults.innerHTML = '<div class="rounded-2xl border border-dashed border-slate-300 p-4 text-sm text-slate-500">No household matched that search yet. Try first name, last name, member name, purok, barangay, code, or contact number.</div>';
            return;
        }
        searchResults.innerHTML = `
            <div class="text-xs uppercase tracking-wide text-slate-500 px-1">Choose the correct household (${results.length} result${results.length === 1 ? '' : 's'})</div>
            <div class="space-y-3">${results.map((row) => {
                const href = <?= json_encode(app_url('modules/agri/programs/index.php')) ?> + '?selected_household_id=' + encodeURIComponent(row.household_id);
                const memberPreview = row.member_preview ? `<div class="text-xs text-slate-500 mt-2">Members: ${escapeHtml(row.member_preview)}</div>` : '';
                const address = [row.barangay_name || '', row.full_address || ''].filter(Boolean).join(' · ');
                const meta = [row.household_code || '', row.contact_number || '', row.member_count ? (row.member_count + ' member' + (Number(row.member_count) === 1 ? '' : 's')) : ''].filter(Boolean).join(' · ');
                return `<a href="${href}" class="block rounded-2xl border border-slate-200 p-4 hover:border-emerald-400 hover:bg-emerald-50/40 transition">
                    <div class="flex items-start justify-between gap-3 flex-wrap">
                        <div>
                            <div class="font-semibold text-lg">${escapeHtml(row.household_head_name || 'No household head')}</div>
                            <div class="text-sm text-slate-600 mt-1">${escapeHtml(meta || 'Select this household')}</div>
                            <div class="text-sm text-slate-500 mt-1">${escapeHtml(address || 'No address')}</div>
                            ${memberPreview}
                        </div>
                        <span class="app-badge app-badge-slate">Choose</span>
                    </div>
                </a>`;
            }).join('')}</div>`;
    }

    let searchController = null;
    async function runSearch() {
        const q = (searchInput?.value || '').trim();
        if (!q) {
            renderResults([]);
            return;
        }
        if (searchController) searchController.abort();
        searchController = new AbortController();
        if (searchButton) {
            searchButton.disabled = true;
            searchButton.textContent = 'Searching...';
        }
        searchResults.innerHTML = '<div class="text-sm text-slate-500">Searching households...</div>';
        try {
            const res = await fetch(<?= json_encode(app_url('modules/api/household_lookup.php')) ?> + '?q=' + encodeURIComponent(q), {
                credentials: 'same-origin',
                signal: searchController.signal,
                headers: { 'Accept': 'application/json' }
            });
            if (!res.ok) throw new Error('Search request failed');
            const data = await res.json();
            renderResults(Array.isArray(data.results) ? data.results : []);
        } catch (error) {
            if (error.name !== 'AbortError') {
                searchResults.innerHTML = '<div class="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700">Search failed or took too long. Try a shorter name, barangay, contact number, or household code.</div>';
            }
        } finally {
            if (searchButton) {
                searchButton.disabled = false;
                searchButton.textContent = 'Search';
            }
        }
    }

    async function findQr(qr) {
        if (!qr) return;
        const res = await fetch(<?= json_encode(app_url('modules/api/qr_lookup.php')) ?> + '?qr=' + encodeURIComponent(qr) + '&action=Lookup', {credentials: 'same-origin'});
        const data = await res.json();
        if (data && data.ok && data.data && data.data.household_id) {
            window.location.href = <?= json_encode(app_url('modules/agri/programs/index.php')) ?> + '?selected_household_id=' + encodeURIComponent(data.data.household_id) + '&qr=' + encodeURIComponent(qr);
            return;
        }
        alert((data && data.message) ? data.message : 'QR not found.');
    }

    async function startScanner() {
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            alert('Camera access is not available on this browser.');
            return;
        }
        scannerWrap?.classList.remove('hidden');
        try {
            cameraStream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' }, audio: false });
            video.srcObject = cameraStream;
            await video.play();
            if ('BarcodeDetector' in window) {
                detector = new BarcodeDetector({ formats: ['qr_code'] });
                scannerNote.textContent = 'Scanner is live. Point the camera at the household QR.';
                scanTimer = window.setInterval(async () => {
                    if (!detector || !video.videoWidth) return;
                    try {
                        const codes = await detector.detect(video);
                        if (codes && codes.length && codes[0].rawValue) {
                            stopScanner();
                            qrInput.value = codes[0].rawValue;
                            findQr(codes[0].rawValue);
                        }
                    } catch (err) {}
                }, 700);
            } else {
                scannerNote.textContent = 'Camera opened, but this browser has no built-in QR detector. You can still use your USB/mobile scanner or choose an image.';
            }
        } catch (err) {
            scannerWrap?.classList.add('hidden');
            alert('Could not open the camera. Check browser permission and HTTPS/localhost access.');
        }
    }

    function stopScanner() {
        if (scanTimer) {
            clearInterval(scanTimer);
            scanTimer = null;
        }
        if (cameraStream) {
            cameraStream.getTracks().forEach(track => track.stop());
            cameraStream = null;
        }
        if (video) video.srcObject = null;
    }

    let searchDebounce = null;
    searchButton?.addEventListener('click', runSearch);
    searchInput?.addEventListener('input', () => {
        const value = (searchInput.value || '').trim();
        if (searchDebounce) clearTimeout(searchDebounce);
        if (value.length < 2) {
            if (!value) renderResults([]);
            return;
        }
        searchDebounce = setTimeout(runSearch, 250);
    });
    searchInput?.addEventListener('keydown', (event) => { if (event.key === 'Enter') { event.preventDefault(); runSearch(); } });
    qrFind?.addEventListener('click', () => findQr((qrInput?.value || '').trim()));
    qrInput?.addEventListener('keydown', (event) => { if (event.key === 'Enter') { event.preventDefault(); findQr((qrInput?.value || '').trim()); } });
    openScanner?.addEventListener('click', startScanner);
    closeScanner?.addEventListener('click', () => { stopScanner(); scannerWrap?.classList.add('hidden'); });
    imageInput?.addEventListener('change', async (event) => {
        const file = event.target.files && event.target.files[0];
        if (!file) return;
        if (!('BarcodeDetector' in window)) {
            alert('Image QR decoding needs BarcodeDetector support in this browser.');
            return;
        }
        try {
            const bitmap = await createImageBitmap(file);
            const imageDetector = new BarcodeDetector({ formats: ['qr_code'] });
            const codes = await imageDetector.detect(bitmap);
            if (codes && codes.length && codes[0].rawValue) {
                qrInput.value = codes[0].rawValue;
                findQr(codes[0].rawValue);
            } else {
                alert('No QR code was detected in that image.');
            }
        } catch (err) {
            alert('Could not read that image QR.');
        }
    });
    programSelect?.addEventListener('change', () => {
        const programId = programSelect.value;
        if (!itemSelect) return;
        [...itemSelect.options].forEach((option, index) => {
            const owner = option.getAttribute('data-program-id');
            option.hidden = !!owner && owner !== programId;
            if (index > 0 && option.hidden && option.selected) itemSelect.value = '0';
        });
    });
})();
</script>
<?php app_require('app/includes/footer.php'); ?>
