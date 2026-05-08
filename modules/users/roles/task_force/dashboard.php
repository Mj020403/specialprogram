<?php
require_once dirname(__DIR__, 4) . '/app/bootstrap.php';

$conn = db_conn();
app_require('app/includes/auth.php');
require_role(['task_force','developer','admin']);
ensure_golden_household_schema($conn);
app_require('app/includes/header.php');

$cards = [
    ['label'=>'Total Households','value'=>total_household_groups($conn),'hint'=>'Households ready for checklist tracking','href'=>app_url('modules/agri/households/index.php'),'cta'=>'Open households'],
    ['label'=>'Pending Validation','value'=>safe_table_count($conn, 'household_special_programs', "application_status IN ('Pending Validation','Pending First Validation','Pending Final Validation')"),'hint'=>'Program requests waiting for field visit','href'=>app_url('modules/agri/validation/index.php'),'cta'=>'Open queue'],
    ['label'=>'Approved Programs','value'=>safe_table_count($conn, 'household_special_programs', "application_status IN ('Approved','Pending Release')"),'hint'=>'Qualified and ready to start','href'=>app_url('modules/agri/programs/index.php?status=Approved'),'cta'=>'View approved'],
    ['label'=>'Active Programs','value'=>safe_table_count($conn, 'household_special_programs', "application_status='Active'"),'hint'=>'Programs already running in households','href'=>app_url('modules/agri/programs/index.php?status=Active'),'cta'=>'View active'],
    ['label'=>'Open Violations','value'=>safe_table_count($conn, 'household_violations', "violation_status='Open'"),'hint'=>'Rule issues recorded during household visits','href'=>app_url('modules/agri/compliance/index.php'),'cta'=>'Open compliance'],
    ['label'=>'Events This Month','value'=>table_exists($conn, 'events') ? scalar($conn, "SELECT COUNT(*) FROM events WHERE MONTH(event_date)=MONTH(CURDATE()) AND YEAR(event_date)=YEAR(CURDATE())", 0) : 0,'hint'=>'Training, assembly, and community activities','href'=>app_url('modules/agri/events/index.php'),'cta'=>'Open events'],
    ['label'=>'Golden HH Candidates','value'=>count(array_filter(golden_household_candidates($conn, 200), fn($row) => !empty($row['golden_eligible']))),'hint'=>'Strong households for mayor recognition','href'=>app_url('modules/agri/programs/index.php'),'cta'=>'Open shortlist'],
    ['label'=>'Completed Programs','value'=>safe_table_count($conn, 'household_special_programs', "application_status='Completed'"),'hint'=>'Finished household program cycles','href'=>app_url('modules/agri/programs/index.php?status=Completed'),'cta'=>'View completed'],
];
echo nav_cards($cards);

$programStatusRows = fetch_all_assoc($conn, "SELECT application_status, COUNT(*) total FROM household_special_programs GROUP BY application_status ORDER BY FIELD(application_status,'Pending First Validation','Pending Validation','Pending Orientation','Pending Final Validation','Pending Seminar','Pending Release','Approved','Active','Completed','Inactive','Declined','Rejected'), application_status");
$pendingPrograms = golden_program_queue($conn, 'Pending Validation', 8);
$openViolations = golden_violation_queue($conn, 'Open', 8);
$goldenHouseholds = golden_household_candidates($conn, 8);
$eventRows = table_exists($conn, 'events') ? fetch_all_assoc($conn, "SELECT event_id, event_name, event_date, venue, event_status FROM events ORDER BY event_date DESC LIMIT 8") : [];
$barangayReadiness = fetch_all_assoc($conn, "
    SELECT b.barangay_id, b.barangay_name,
        COUNT(DISTINCT h.household_id) AS total_households,
        COUNT(DISTINCT CASE WHEN sp.application_status IN ('Approved','Active','Completed','Pending Release') THEN h.household_id END) AS programmed_households,
        COUNT(DISTINCT CASE WHEN hv.violation_status='Open' THEN h.household_id END) AS households_with_open_violations,
        COUNT(DISTINCT CASE WHEN ea.attendance_status='Present' THEN h.household_id END) AS event_active_households
    FROM barangays b
    LEFT JOIN households h ON h.barangay_id=b.barangay_id AND COALESCE(h.record_status,'active') <> 'deleted'
    LEFT JOIN household_special_programs sp ON sp.household_id=h.household_id
    LEFT JOIN household_violations hv ON hv.household_id=h.household_id
    LEFT JOIN event_attendance ea ON ea.household_id=h.household_id
    GROUP BY b.barangay_id, b.barangay_name
    ORDER BY b.barangay_name ASC
");
?>
<section class="rounded-[2rem] border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-6 shadow-sm mt-6">
    <div class="flex items-start justify-between gap-4 flex-wrap">
        <div>
            <div class="text-sm text-slate-500">Task Force operations</div>
            <h2 class="text-3xl font-black">Checklist-based household program workflow</h2>
            <p class="mt-2 text-sm text-slate-500 max-w-4xl">This workspace now follows the mayor plan: households request programs first, requests stay pending until Task Force validates the actual household, then only approved or active programs become part of the official report. Events, compliance, and violations are tracked separately with no points.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="<?= e(app_url('modules/agri/programs/index.php')) ?>" class="app-btn-primary">Open program center</a>
            <a href="<?= e(app_url('modules/agri/validation/index.php')) ?>" class="app-btn-outline">Open validation queue</a>
        </div>
    </div>
    <div class="mt-5 app-action-grid">
        <a href="<?= app_url('modules/agri/households/index.php') ?>" class="app-action-card tone-green">
            <span class="app-action-icon">🏠</span>
            <div class="app-action-title">Households</div>
            <div class="app-action-text">Open household records and use the checklist inside every household profile.</div>
        </a>
        <a href="<?= app_url('modules/agri/programs/index.php') ?>" class="app-action-card tone-blue">
            <span class="app-action-icon">🌱</span>
            <div class="app-action-title">Programs</div>
            <div class="app-action-text">Add program requests, review status, and see approved, active, and completed entries.</div>
        </a>
        <a href="<?= app_url('modules/agri/validation/index.php') ?>" class="app-action-card tone-amber">
            <span class="app-action-icon">✔️</span>
            <div class="app-action-title">Validation Queue</div>
            <div class="app-action-text">See pending validation first before you go to the actual household.</div>
        </a>
        <a href="<?= app_url('modules/agri/events/index.php') ?>" class="app-action-card tone-violet">
            <span class="app-action-icon">📅</span>
            <div class="app-action-title">Events</div>
            <div class="app-action-text">Create barangay activities and target all households or selected programs only.</div>
        </a>
        <a href="<?= app_url('modules/agri/compliance/index.php') ?>" class="app-action-card tone-rose">
            <span class="app-action-icon">⚖️</span>
            <div class="app-action-title">Compliance & Violations</div>
            <div class="app-action-text">Track household rules separately from violation incident history.</div>
        </a>
        <a href="<?= app_url('modules/agri/reports/index.php') ?>" class="app-action-card tone-emerald">
            <span class="app-action-icon">📊</span>
            <div class="app-action-title">Reports</div>
            <div class="app-action-text">Show only validated and real household program records in reports.</div>
        </a>
    </div>
</section>

<div class="grid gap-6 xl:grid-cols-3 mt-6">
    <section class="rounded-[2rem] border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-6 shadow-sm">
        <div class="text-sm text-slate-500">Program pipeline</div>
        <h3 class="text-2xl font-black mt-1">Status flow</h3>
        <div class="mt-4 space-y-3">
            <?php foreach ($programStatusRows as $row): ?>
                <div class="flex items-center justify-between gap-3 rounded-2xl border border-slate-200 dark:border-slate-800 px-4 py-3">
                    <div class="font-semibold"><?= e($row['application_status']) ?></div>
                    <div class="text-xl font-black"><?= (int)$row['total'] ?></div>
                </div>
            <?php endforeach; if (!$programStatusRows): ?>
                <div class="text-sm text-slate-500">No household program requests yet.</div>
            <?php endif; ?>
        </div>
    </section>
    <section class="rounded-[2rem] border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-6 shadow-sm xl:col-span-2">
        <div class="flex items-center justify-between gap-3 flex-wrap">
            <div>
                <div class="text-sm text-slate-500">Pending work</div>
                <h3 class="text-2xl font-black">Households waiting for actual validation</h3>
            </div>
            <a href="<?= e(app_url('modules/agri/validation/index.php')) ?>" class="app-btn-outline">Open full queue</a>
        </div>
        <div class="mt-5 overflow-x-auto rounded-[1.5rem] border border-slate-200 dark:border-slate-800">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 dark:bg-slate-900">
                    <tr>
                        <th class="px-4 py-3 text-left">Household</th>
                        <th class="px-4 py-3 text-left">Barangay</th>
                        <th class="px-4 py-3 text-left">Requested program</th>
                        <th class="px-4 py-3 text-left">Applied</th>
                        <th class="px-4 py-3 text-right">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pendingPrograms as $row): ?>
                        <tr class="border-t border-slate-200 dark:border-slate-800">
                            <td class="px-4 py-3 font-semibold"><?= e($row['household_head_name']) ?></td>
                            <td class="px-4 py-3"><?= e($row['barangay_name']) ?></td>
                            <td class="px-4 py-3"><?= e($row['program_name']) ?><?php if (!empty($row['item_name'])): ?> · <?= e($row['item_name']) ?><?php endif; ?></td>
                            <td class="px-4 py-3"><?= e($row['date_applied'] ?: '-') ?></td>
                            <td class="px-4 py-3 text-right"><a href="<?= e(app_url('modules/agri/households/view.php?id=' . (int)$row['household_id'] . '#golden-household')) ?>" class="app-btn-outline text-sm">Validate in household</a></td>
                        </tr>
                    <?php endforeach; if (!$pendingPrograms): ?>
                        <tr><td colspan="5" class="px-4 py-6 text-center text-slate-500">No pending program requests right now.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>

<div class="grid gap-6 xl:grid-cols-2 mt-6">
    <section class="rounded-[2rem] border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-6 shadow-sm">
        <div class="flex items-center justify-between gap-3 flex-wrap">
            <div>
                <div class="text-sm text-slate-500">Rule issues</div>
                <h3 class="text-2xl font-black">Open violations</h3>
            </div>
            <a href="<?= e(app_url('modules/agri/compliance/index.php')) ?>" class="app-btn-outline">Open compliance page</a>
        </div>
        <div class="mt-5 divide-y divide-slate-200 dark:divide-slate-800 rounded-[1.5rem] border border-slate-200 dark:border-slate-800 overflow-hidden">
            <?php foreach ($openViolations as $row): ?>
                <div class="p-4 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <div>
                        <div class="font-semibold"><?= e($row['household_head_name']) ?> · <?= e($row['violation_name']) ?></div>
                        <div class="text-sm text-slate-500"><?= e($row['barangay_name']) ?> · Observed <?= e($row['observed_on']) ?></div>
                    </div>
                    <a href="<?= e(app_url('modules/agri/households/view.php?id=' . (int)$row['household_id'] . '#golden-household')) ?>" class="app-btn-outline text-sm">Open household</a>
                </div>
            <?php endforeach; if (!$openViolations): ?>
                <div class="px-4 py-6 text-center text-emerald-700">No open violations right now.</div>
            <?php endif; ?>
        </div>
    </section>
    <section class="rounded-[2rem] border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-6 shadow-sm">
        <div class="flex items-center justify-between gap-3 flex-wrap">
            <div>
                <div class="text-sm text-slate-500">Mayor-ready shortlist</div>
                <h3 class="text-2xl font-black">Strong Golden Household candidates</h3>
            </div>
            <a href="<?= e(app_url('modules/agri/programs/index.php')) ?>" class="app-btn-outline">Open shortlist</a>
        </div>
        <div class="mt-5 divide-y divide-slate-200 dark:divide-slate-800 rounded-[1.5rem] border border-slate-200 dark:border-slate-800 overflow-hidden">
            <?php foreach ($goldenHouseholds as $row): ?>
                <div class="p-4 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <div>
                        <div class="font-semibold"><?= e($row['household_head_name']) ?> · <?= e($row['barangay_name']) ?></div>
                        <div class="text-sm text-slate-500">Programs <?= (int)$row['approved_programs'] ?> · Events <?= (int)$row['events_attended'] ?> · Open violations <?= (int)$row['open_violations'] ?></div>
                    </div>
                    <div class="flex items-center gap-2 flex-wrap">
                        <?= format_status_badge($row['golden_status']) ?>
                        <a href="<?= e(app_url('modules/agri/households/view.php?id=' . (int)$row['household_id'] . '#golden-household')) ?>" class="app-btn-outline text-sm">Open</a>
                    </div>
                </div>
            <?php endforeach; if (!$goldenHouseholds): ?>
                <div class="px-4 py-6 text-center text-slate-500">No Golden Household shortlist yet.</div>
            <?php endif; ?>
        </div>
    </section>
</div>

<section class="rounded-[2rem] border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-6 shadow-sm mt-6">
    <div class="flex items-center justify-between gap-3 flex-wrap">
        <div>
            <div class="text-sm text-slate-500">Barangay snapshot</div>
            <h3 class="text-2xl font-black">Programs, events, and violations by barangay</h3>
        </div>
        <a href="<?= e(app_url('modules/agri/reports/index.php')) ?>" class="app-btn-outline">Open reports</a>
    </div>
    <div class="mt-5 overflow-x-auto rounded-[1.5rem] border border-slate-200 dark:border-slate-800">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 dark:bg-slate-900">
                <tr>
                    <th class="px-4 py-3 text-left">Barangay</th>
                    <th class="px-4 py-3 text-right">Households</th>
                    <th class="px-4 py-3 text-right">With approved/active programs</th>
                    <th class="px-4 py-3 text-right">Event-active households</th>
                    <th class="px-4 py-3 text-right">Open violations</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($barangayReadiness as $row): ?>
                    <tr class="border-t border-slate-200 dark:border-slate-800">
                        <td class="px-4 py-3 font-semibold"><?= e($row['barangay_name']) ?></td>
                        <td class="px-4 py-3 text-right"><?= (int)$row['total_households'] ?></td>
                        <td class="px-4 py-3 text-right"><?= (int)$row['programmed_households'] ?></td>
                        <td class="px-4 py-3 text-right"><?= (int)$row['event_active_households'] ?></td>
                        <td class="px-4 py-3 text-right"><?= (int)$row['households_with_open_violations'] ?></td>
                    </tr>
                <?php endforeach; if (!$barangayReadiness): ?>
                    <tr><td colspan="5" class="px-4 py-6 text-center text-slate-500">No barangay readiness data yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?= app_dashboard_insights_panel($conn, 'Task Force rules and situation summary', 'Charts that help users see program flow, compliance pressure, and the live database situation before they work the queues.') ?>
<?php app_require('app/includes/footer.php'); ?>
