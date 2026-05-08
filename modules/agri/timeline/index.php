<?php
require_once dirname(__DIR__, 3) . '/app/bootstrap.php';
$conn = db_conn();
app_require('app/includes/auth.php');
require_role(['task_force','mayor','admin']);
ensure_decision_support_schema($conn);
$householdId = (int)($_GET['household_id'] ?? 0);
$households = fetch_all_assoc($conn, "SELECT household_id, household_head_name, household_code FROM households ORDER BY household_head_name");
$timeline = $householdId > 0 ? family_timeline($conn, $householdId) : [];
$selected = $householdId > 0 ? get_household_snapshot($conn, $householdId) : null;
app_require('app/includes/header.php');
echo nav_cards([
    ['label'=>'Timeline entries','value'=>count($timeline),'hint'=>'Chronological family activity records'],
    ['label'=>'QR scans','value'=>(int)scalar($conn, "SELECT COALESCE(SUM(total_scans),0) FROM qr_codes WHERE qr_type='HOUSEHOLD'", 0),'hint'=>'All family QR activity'],
    ['label'=>'Assistance logs','value'=>(int)scalar($conn, table_exists($conn,'assistance_records') ? "SELECT COUNT(*) FROM assistance_records" : "SELECT 0", 0),'hint'=>'Interventions already tracked'],
    ['label'=>'Monitoring logs','value'=>(int)scalar($conn, "SELECT COUNT(*) FROM monitoring_visits", 0),'hint'=>'Field visits recorded'],
]);
?>
<div class="grid gap-6 xl:grid-cols-[0.85fr_1.15fr]">
<section class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
    <div class="text-sm text-slate-500">Story view</div>
    <h2 class="text-2xl font-black">Family timeline</h2>
    <p class="mt-2 text-sm text-slate-500">Open one household and instantly understand members, program requests, validation, compliance, attendance, assistance, and QR activity in one place.</p>
    <form method="GET" class="mt-5 space-y-4">
        <div>
            <label class="block text-sm font-semibold mb-2">Select family</label>
            <select name="household_id" class="app-control">
                <option value="">Choose a family</option>
                <?php foreach ($households as $house): ?>
                    <option value="<?= (int)$house['household_id'] ?>" <?= $householdId === (int)$house['household_id'] ? 'selected' : '' ?>><?= e(($house['household_head_name'] ?: 'Unnamed') . ' · ' . ($house['household_code'] ?: '-')) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button class="app-btn-primary">Open timeline</button>
    </form>
    <?php if ($selected): $case = household_case_summary($conn, $householdId); ?>
    <div class="mt-6 rounded-3xl border border-slate-200 p-5">
        <div class="flex gap-4 items-center">
            <img src="<?= e($selected['photo_url'] ?: app_url('assets/img/image.jpg')) ?>" class="h-20 w-20 rounded-[1.5rem] object-cover border" alt="Head photo">
            <div>
                <div class="text-sm text-slate-500"><?= e($selected['barangay_name'] ?: '-') ?></div>
                <div class="text-xl font-black"><?= e($selected['head_name'] ?: $selected['household_head_name']) ?></div>
                <div class="text-sm text-slate-500"><?= e($selected['household_code'] ?: '-') ?> · QR <?= e($selected['qr_reference'] ?: 'Pending') ?></div>
            </div>
        </div>
        <div class="mt-4 grid gap-3 sm:grid-cols-2 text-sm">
            <div><strong>Members:</strong> <?= (int)($case['members'] ?? 0) ?></div>
            <div><strong>Dependents:</strong> <?= (int)($case['dependents'] ?? 0) ?></div>
            <div><strong>Seniors:</strong> <?= (int)($case['seniors'] ?? 0) ?></div>
            <div><strong>Farmers:</strong> <?= (int)($case['farmers'] ?? 0) ?></div>
            <div><strong>Score:</strong> <?= e((string)($selected['score'] ?? 0)) ?></div>
            <div><strong>Status:</strong> <?= format_status_badge($selected['qualification_status'] ?? 'For Validation') ?></div>
        </div>
        <div class="mt-4 flex flex-wrap gap-2">
            <a href="<?= e(app_url('modules/agri/households/view.php?id=' . $householdId)) ?>" class="app-btn-outline">Open family</a>
            <a href="<?= e(app_url('modules/agri/households/print.php?id=' . $householdId)) ?>" class="app-btn-outline">Print profile</a>
            <a href="<?= e(app_url('modules/agri/assistance/index.php?household_id=' . $householdId)) ?>" class="app-btn-outline">Assistance</a>
        </div>
    </div>
    <?php endif; ?>
</section>
<section class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
    <div class="text-sm text-slate-500">Chronological activity</div>
    <h2 class="text-2xl font-black"><?= $selected ? e($selected['head_name'] ?: $selected['household_head_name']) . ' timeline' : 'Pick a family first' ?></h2>
    <div class="mt-6 space-y-4">
        <?php foreach ($timeline as $item): ?>
            <div class="rounded-3xl border border-slate-200 p-4 flex gap-4 items-start">
                <div class="h-12 w-12 rounded-2xl bg-slate-100 flex items-center justify-center shrink-0">
                    <i data-lucide="<?= e($item['icon']) ?>" class="w-5 h-5 text-slate-700"></i>
                </div>
                <div class="flex-1">
                    <div class="flex flex-wrap gap-2 items-center justify-between">
                        <div class="font-semibold"><?= e($item['title']) ?></div>
                        <div class="text-xs text-slate-500"><?= e(date('M d, Y h:i A', strtotime((string)$item['date']))) ?></div>
                    </div>
                    <div class="mt-1 text-sm text-slate-600"><?= e($item['meta']) ?></div>
                </div>
            </div>
        <?php endforeach; if (!$timeline): ?><div class="rounded-3xl border border-dashed border-slate-300 p-10 text-center text-slate-500">No family selected yet, or no timeline activity has been recorded for this household.</div><?php endif; ?>
    </div>
</section>
</div>
<?php app_require('app/includes/footer.php'); ?>