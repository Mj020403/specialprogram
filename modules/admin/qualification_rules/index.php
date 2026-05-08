<?php
require_once dirname(__DIR__, 3) . '/app/bootstrap.php';
$conn = db_conn();
app_require('app/includes/auth.php');
require_role(['developer','admin','mayor']);
app_require('app/includes/helpers/core.php');
app_require('modules/family/portal_helpers.php');
ensure_family_portal_schema($conn);
$user = current_user();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'save_rules') {
    foreach ((array)($_POST['rules'] ?? []) as $id => $row) {
        $ruleId = (int)$id;
        $points = isset($row['points_value']) && $row['points_value'] !== '' ? (float)$row['points_value'] : 0.0;
        $monthlyCap = isset($row['monthly_cap']) && $row['monthly_cap'] !== '' ? (int)$row['monthly_cap'] : null;
        $perCropCap = isset($row['per_crop_day_cap']) && $row['per_crop_day_cap'] !== '' ? (int)$row['per_crop_day_cap'] : null;
        $isActive = !empty($row['is_active']) ? 1 : 0;
        $stmt = $conn->prepare("UPDATE qualification_rules SET points_value=?, monthly_cap=?, per_crop_day_cap=?, is_active=? WHERE rule_id=?");
        if ($stmt) {
            $stmt->bind_param('diiii', $points, $monthlyCap, $perCropCap, $isActive, $ruleId);
            $stmt->execute();
            $stmt->close();
        }
    }
    app_log($conn, (int)$user['id'], 'qualification_rules', 'update', null, 'Updated qualification and contribution rules');
    set_flash('success', 'Qualification rules updated. New approvals will use the new values.');
    header('Location: ' . app_url('modules/admin/qualification_rules/index.php'));
    exit;
}
$flash = get_flash();
$rules = fetch_all_assoc($conn, "SELECT * FROM qualification_rules ORDER BY rule_label ASC");
app_require('app/includes/header.php');
?>
<section class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
    <div class="text-sm text-slate-500">Administration</div>
    <h1 class="text-3xl font-black text-slate-900">Qualification rules</h1>
    <p class="mt-2 text-slate-500">Adjust points, qualification thresholds, and duplicate-control caps without editing code.</p>
    <?php if ($flash): ?><div class="mt-4 rounded-2xl border <?= $flash['type']==='success' ? 'border-emerald-200 bg-emerald-50 text-emerald-900' : 'border-rose-200 bg-rose-50 text-rose-900' ?> px-4 py-3"><?= e($flash['message']) ?></div><?php endif; ?>
    <form method="POST" class="mt-6 space-y-4"><input type="hidden" name="action" value="save_rules">
        <?php foreach ($rules as $rule): ?>
            <div class="rounded-[1.5rem] border border-slate-200 p-5 grid gap-4 md:grid-cols-[1.6fr_140px_140px_160px_auto] items-end">
                <div>
                    <div class="text-lg font-black text-slate-900"><?= e($rule['rule_label']) ?></div>
                    <div class="mt-1 text-sm text-slate-500">Key: <?= e($rule['rule_key']) ?></div>
                    <?php if (!empty($rule['description'])): ?><div class="mt-2 text-sm text-slate-600"><?= e($rule['description']) ?></div><?php endif; ?>
                </div>
                <label class="block"><span class="block text-sm font-semibold mb-2">Points</span><input type="number" step="0.01" name="rules[<?= (int)$rule['rule_id'] ?>][points_value]" value="<?= e((string)$rule['points_value']) ?>" class="w-full rounded-2xl border border-slate-300 px-4 py-3"></label>
                <label class="block"><span class="block text-sm font-semibold mb-2">Monthly cap</span><input type="number" name="rules[<?= (int)$rule['rule_id'] ?>][monthly_cap]" value="<?= e($rule['monthly_cap'] !== null ? (string)$rule['monthly_cap'] : '') ?>" class="w-full rounded-2xl border border-slate-300 px-4 py-3" placeholder="None"></label>
                <label class="block"><span class="block text-sm font-semibold mb-2">Crop/day cap</span><input type="number" name="rules[<?= (int)$rule['rule_id'] ?>][per_crop_day_cap]" value="<?= e($rule['per_crop_day_cap'] !== null ? (string)$rule['per_crop_day_cap'] : '') ?>" class="w-full rounded-2xl border border-slate-300 px-4 py-3" placeholder="None"></label>
                <label class="inline-flex items-center gap-3 pb-3"><input type="checkbox" name="rules[<?= (int)$rule['rule_id'] ?>][is_active]" value="1" <?= (int)$rule['is_active'] === 1 ? 'checked' : '' ?>> <span class="text-sm font-semibold text-slate-700">Active</span></label>
            </div>
        <?php endforeach; ?>
        <div class="flex justify-end"><button class="app-btn-primary">Save rules</button></div>
    </form>
</section>
<?php app_require('app/includes/footer.php'); ?>
