<?php
require_once dirname(__DIR__, 3) . '/app/bootstrap.php';
$conn = db_conn();
app_require('app/includes/auth.php');
require_role(['task_force','mayor','admin']);
ensure_decision_support_schema($conn);
$user = current_user();
$householdId = (int)($_GET['household_id'] ?? $_POST['household_id'] ?? 0);
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($user['role'], ['task_force','admin'], true)) {
    $newId = save_assistance_record($conn, $_POST, (int)$user['id']);
    if ($newId > 0 && !empty($_FILES['evidence_file']['name'])) {
        save_household_document($conn, (int)($_POST['household_id'] ?? 0), [
            'document_type' => 'Assistance Evidence',
            'title' => trim((string)($_POST['assistance_type'] ?? 'Assistance')) . ' evidence',
            'notes' => trim((string)($_POST['outcome_notes'] ?? '')),
        ], $_FILES['evidence_file'], (int)$user['id']);
    }
    if ($newId > 0) {
        set_flash('success', 'Assistance record saved.');
        $householdId = (int)($_POST['household_id'] ?? $householdId);
        header('Location: ' . app_url('modules/agri/assistance/index.php' . ($householdId ? '?household_id=' . $householdId : '')));
        exit;
    }
    set_flash('error', 'Unable to save assistance record.');
}
$where = $householdId > 0 ? " WHERE a.household_id=" . $householdId : "";
$rows = fetch_all_assoc($conn, "SELECT a.*, h.household_head_name, h.household_code, b.barangay_name
    FROM assistance_records a
    JOIN households h ON h.household_id=a.household_id
    LEFT JOIN barangays b ON b.barangay_id=h.barangay_id
    $where
    ORDER BY a.assistance_date DESC, a.assistance_id DESC LIMIT 120");
$households = fetch_all_assoc($conn, "SELECT household_id, household_head_name, household_code FROM households ORDER BY household_head_name");
$selected = $householdId > 0 ? get_household_snapshot($conn, $householdId) : null;
app_require('app/includes/header.php');
echo nav_cards([
    ['label'=>'Assistance records','value'=>count($rows),'hint'=>'Interventions logged in the system'],
    ['label'=>'Delivered','value'=>(int)scalar($conn, "SELECT COUNT(*) FROM assistance_records WHERE assistance_status IN ('Delivered','Completed')", 0),'hint'=>'Completed help records'],
    ['label'=>'Scheduled','value'=>(int)scalar($conn, "SELECT COUNT(*) FROM assistance_records WHERE assistance_status IN ('Planned','Scheduled')", 0),'hint'=>'Upcoming interventions'],
    ['label'=>'Families served','value'=>(int)scalar($conn, "SELECT COUNT(DISTINCT household_id) FROM assistance_records", 0),'hint'=>'Unique families with assistance history'],
]);
?>
<div class="grid gap-6 xl:grid-cols-[0.9fr_1.1fr]">
<section class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
    <div class="flex items-center justify-between gap-3">
        <div>
            <div class="text-sm text-slate-500">Intervention tracker</div>
            <h2 class="text-2xl font-black">Assistance / intervention records</h2>
        </div>
        <?php if ($selected): ?><a href="<?= e(app_url('modules/agri/households/view.php?id='.(int)$selected['household_id'])) ?>" class="app-btn-outline">Open family</a><?php endif; ?>
    </div>
    <?php if (in_array($user['role'], ['task_force','admin'], true)): ?>
    <form method="POST" enctype="multipart/form-data" class="mt-5 grid gap-4 md:grid-cols-2">
        <div class="md:col-span-2">
            <label class="block text-sm font-semibold mb-2">Family</label>
            <select name="household_id" class="app-control" required>
                <option value="">Select family</option>
                <?php foreach ($households as $house): ?>
                    <option value="<?= (int)$house['household_id'] ?>" <?= $householdId === (int)$house['household_id'] ? 'selected' : '' ?>><?= e(($house['household_head_name'] ?: 'Unnamed') . ' · ' . ($house['household_code'] ?: '-')) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div><label class="block text-sm font-semibold mb-2">Date</label><input type="date" name="assistance_date" value="<?= e(date('Y-m-d')) ?>" class="app-control" required></div>
        <div><label class="block text-sm font-semibold mb-2">Type</label><select name="assistance_type" class="app-control"><?php foreach (assistance_type_options() as $opt): ?><option><?= e($opt) ?></option><?php endforeach; ?></select></div>
        <div><label class="block text-sm font-semibold mb-2">Status</label><select name="assistance_status" class="app-control"><?php foreach (assistance_status_options() as $opt): ?><option><?= e($opt) ?></option><?php endforeach; ?></select></div>
        <div><label class="block text-sm font-semibold mb-2">Provider</label><input name="provider_name" class="app-control" placeholder="LGU / NGO / Office"></div>
        <div><label class="block text-sm font-semibold mb-2">Amount / value</label><input type="number" step="0.01" name="amount_value" class="app-control" value="0"></div>
        <div><label class="block text-sm font-semibold mb-2">Next follow-up</label><input type="date" name="next_followup_date" class="app-control"></div>
        <div class="md:col-span-2"><label class="block text-sm font-semibold mb-2">Description</label><textarea name="description" rows="2" class="app-control" placeholder="Seedlings, training, fertilizer, etc."></textarea></div>
        <div class="md:col-span-2"><label class="block text-sm font-semibold mb-2">Outcome / notes</label><textarea name="outcome_notes" rows="2" class="app-control" placeholder="Result or next action"></textarea></div>
        <div class="md:col-span-2"><label class="block text-sm font-semibold mb-2">Evidence file</label><input type="file" name="evidence_file" class="app-control" accept=".pdf,.png,.jpg,.jpeg,.webp,.doc,.docx"></div>
        <div class="md:col-span-2"><button class="app-btn-primary">Save assistance record</button></div>
    </form>
    <?php else: ?>
        <div class="mt-4 text-sm text-slate-500">Mayor view is read-only. Use this page to review what support each family already received.</div>
    <?php endif; ?>
</section>
<section class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
    <div class="flex items-center justify-between gap-3">
        <div>
            <div class="text-sm text-slate-500">Operational view</div>
            <h2 class="text-2xl font-black"><?= $selected ? e($selected['head_name'] ?: $selected['household_head_name']) . ' assistance history' : 'Latest assistance records' ?></h2>
        </div>
        <form method="GET" class="flex gap-2 items-center">
            <select name="household_id" class="app-control min-w-[240px]">
                <option value="">All families</option>
                <?php foreach ($households as $house): ?>
                    <option value="<?= (int)$house['household_id'] ?>" <?= $householdId === (int)$house['household_id'] ? 'selected' : '' ?>><?= e(($house['household_head_name'] ?: 'Unnamed') . ' · ' . ($house['household_code'] ?: '-')) ?></option>
                <?php endforeach; ?>
            </select>
            <button class="app-btn-outline">Filter</button>
        </form>
    </div>
    <?php if ($selected): ?>
        <div class="mt-4 rounded-3xl border border-slate-200 p-4 flex gap-4 items-center">
            <img src="<?= e($selected['photo_url'] ?: app_url('assets/img/image.jpg')) ?>" class="h-16 w-16 rounded-2xl object-cover border" alt="Head photo">
            <div class="text-sm text-slate-600">
                <div class="font-semibold text-slate-900"><?= e($selected['head_name'] ?: $selected['household_head_name']) ?></div>
                <div><?= e($selected['household_code'] ?: '-') ?> · <?= e($selected['barangay_name'] ?: '-') ?></div>
                <div>Members: <?= (int)($selected['household_size'] ?? 0) ?> · QR: <?= e($selected['qr_reference'] ?: 'Pending') ?></div>
            </div>
        </div>
    <?php endif; ?>
    <div class="mt-5 overflow-hidden rounded-3xl border border-slate-200">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50"><tr><th class="px-4 py-3 text-left">Date</th><th class="px-4 py-3 text-left">Family</th><th class="px-4 py-3 text-left">Type</th><th class="px-4 py-3 text-left">Status</th><th class="px-4 py-3 text-left">Provider</th><th class="px-4 py-3 text-right">Value</th><th class="px-4 py-3 text-left">Evidence</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
                <tr class="border-t border-slate-200">
                    <td class="px-4 py-3"><?= e($row['assistance_date']) ?></td>
                    <td class="px-4 py-3"><div class="font-semibold"><?= e($row['household_head_name']) ?></div><div class="text-xs text-slate-500"><?= e(($row['household_code'] ?: '-') . ' · ' . ($row['barangay_name'] ?: '-')) ?></div></td>
                    <td class="px-4 py-3"><?= e($row['assistance_type']) ?></td>
                    <td class="px-4 py-3"><?= format_status_badge($row['assistance_status']) ?></td>
                    <td class="px-4 py-3"><?= e($row['provider_name'] ?: '-') ?></td>
                    <td class="px-4 py-3 text-right"><?= number_format((float)$row['amount_value'], 2) ?></td><td class="px-4 py-3"><?php $doc = fetch_one($conn, "SELECT file_path,title FROM household_documents WHERE household_id=".(int)$row['household_id']." AND document_type='Assistance Evidence' ORDER BY document_id DESC LIMIT 1"); ?><?php if($doc): ?><a href="<?= e(app_url('public/' . ltrim($doc['file_path'], '/'))) ?>" target="_blank" class="app-btn-outline text-xs">Open</a><?php else: ?><span class="text-xs text-slate-400">None</span><?php endif; ?></td>
                </tr>
                <?php if (!empty($row['description']) || !empty($row['outcome_notes'])): ?>
                <tr class="border-t border-slate-100 bg-slate-50/60"><td></td><td colspan="5" class="px-4 py-3 text-xs text-slate-600"><strong>Description:</strong> <?= e($row['description'] ?: '-') ?><?php if (!empty($row['outcome_notes'])): ?> · <strong>Outcome:</strong> <?= e($row['outcome_notes']) ?><?php endif; ?></td></tr>
                <?php endif; ?>
            <?php endforeach; if (!$rows): ?><tr><td colspan="7" class="px-4 py-6 text-center text-slate-500">No assistance records yet.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
</div>
<?php app_require('app/includes/footer.php'); ?>