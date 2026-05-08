<?php
require_once dirname(__DIR__, 3) . '/app/bootstrap.php';

$conn = db_conn();app_require('app/includes/auth.php'); require_role(['task_force','admin','mayor']);
require_once app_path('app/config/database.php'); app_require('app/includes/app_helpers.php');
$user = current_user();
$prefillHousehold = (int)($_GET['household_id'] ?? 0);
$officialCrops = official_crop_rows($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (post('action') === 'save_official_crop' && in_array($user['role'], ['mayor','admin'], true)) {
        $name = trim((string)post('crop_name'));
        if ($name !== '') {
            ensure_harvest_schema($conn);
            $stmt = $conn->prepare("INSERT INTO official_crops (crop_name, is_active, created_by) VALUES (?,1,?) ON DUPLICATE KEY UPDATE is_active=1");
            if ($stmt) {
                $uid = (int)$user['id'];
                $stmt->bind_param('si', $name, $uid);
                $stmt->execute();
                $stmt->close();
                set_flash('success', 'Official crop saved. It will now appear in profiling, crop registry, and monitoring.');
            }
        } else {
            set_flash('error', 'Enter a crop name.');
        }
        header('Location: /harvest/modules/agri/crops/index.php'); exit;
    }
    if (post('action') === 'toggle_official_crop' && in_array($user['role'], ['mayor','admin'], true)) {
        $id = (int)post('official_crop_id');
        if ($id > 0) {
            $conn->query("UPDATE official_crops SET is_active = CASE WHEN is_active=1 THEN 0 ELSE 1 END WHERE crop_id={$id}");
            set_flash('success', 'Official crop list updated.');
        }
        header('Location: /harvest/modules/agri/crops/index.php'); exit;
    }

    if (post('action') === 'save_household_crop' && in_array($user['role'], ['task_force','admin'], true)) {
        $household = (int)post('household_id');
        $officialCropId = (int)post('official_crop_id');
        $uid = (int)$user['id'];
        $cropMap = [];
        foreach (official_crop_rows($conn) as $r) $cropMap[(int)$r['crop_id']] = $r['crop_name'];
        $cropName = $cropMap[$officialCropId] ?? '';
        if ($household > 0 && $cropName !== '') {
            $existing = (int)scalar($conn, "SELECT COUNT(*) FROM crops WHERE household_id={$household} AND crop_name='" . $conn->real_escape_string($cropName) . "' AND crop_status='Active'", 0);
            if ($existing > 0) {
                set_flash('error', 'That crop is already registered for this household.');
                header('Location: /harvest/modules/agri/crops/index.php?household_id=' . $household); exit;
            }
            $stmt = $conn->prepare("INSERT INTO crops (household_id, crop_name, variety, plot_name, tree_count, planted_date, expected_fruiting_date, area_sqm, current_condition, fruiting_status, crop_status, remarks, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Active', ?, ?)");
            $variety = trim((string)post('variety')) ?: null;
            $plotName = trim((string)post('plot_name')) ?: null;
            $treeCount = max(0, (int)post('tree_count'));
            $plantedDate = post('planted_date') ?: null;
            $expectedFruitingDate = post('expected_fruiting_date') ?: null;
            $areaSqm = post('area_sqm') !== '' ? (float)post('area_sqm') : null;
            $condition = post('current_condition') ?: 'For Validation';
            $fruiting = post('fruiting_status') ?: 'Unknown';
            $remarks = trim((string)post('remarks')) ?: null;
            if ($stmt) {
                $stmt->bind_param('isssissdsssi', $household, $cropName, $variety, $plotName, $treeCount, $plantedDate, $expectedFruitingDate, $areaSqm, $condition, $fruiting, $remarks, $uid);
                $stmt->execute();
                $id = $stmt->insert_id;
                $stmt->close();
                ensure_crop_assets($conn, $id, $household, $uid);
                sync_household_auto_fields($conn, $household);
                refresh_household_qualification_php($conn, $household);
                set_flash('success', 'Household crop saved from the mayor-approved crop list.');
                header('Location: /harvest/modules/agri/households/view.php?id=' . $household); exit;
            }
        } else {
            set_flash('error', 'Select a household and an official crop first.');
        }
        header('Location: /harvest/modules/agri/crops/index.php'); exit;
    }
}

$households = fetch_all_assoc($conn, "SELECT household_id, household_head_name, household_code, area_sqm FROM households ORDER BY household_head_name");
$rows = fetch_all_assoc($conn, "SELECT c.crop_id,c.crop_name,c.plot_name,c.tree_count,c.current_condition,c.fruiting_status,c.qr_reference,h.household_id,h.household_head_name FROM crops c JOIN households h ON h.household_id=c.household_id ORDER BY c.crop_id DESC LIMIT 100");
$allOfficialRows = fetch_all_assoc($conn, "SELECT crop_id, crop_name, is_active FROM official_crops ORDER BY crop_name");
app_require('app/includes/header.php');
?>
<div class="grid gap-6 xl:grid-cols-[0.95fr_1.05fr]">
<?php if(in_array($user['role'],['mayor','admin'],true)): ?>
<section class="rounded-[2rem] border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-6 shadow-sm">
    <div class="text-sm text-slate-500">Mayor crop control</div>
    <h2 class="text-2xl font-black">Official municipal crop list</h2>
    <div class="mt-4 rounded-3xl bg-blue-50 dark:bg-blue-950/30 p-4 text-sm text-slate-600 dark:text-slate-300">Only crops added here will appear to the Task Force in profiling, crop registry, and monitoring. If the mayor does not add a crop, it will not show anywhere else.</div>
    <form method="POST" class="mt-5 flex gap-3">
        <input type="hidden" name="action" value="save_official_crop">
        <input name="crop_name" placeholder="Example: Guava, Apple, Cacao" class="flex-1 rounded-2xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-4 py-3">
        <button class="app-btn-primary">Add crop</button>
    </form>
    <div class="mt-5 space-y-3">
        <?php foreach($allOfficialRows as $row): ?>
            <form method="POST" class="flex items-center justify-between rounded-2xl border border-slate-200 dark:border-slate-800 px-4 py-3">
                <div><div class="font-semibold"><?= e($row['crop_name']) ?></div><div class="text-sm text-slate-500"><?= $row['is_active'] ? 'Visible to Task Force forms' : 'Hidden from Task Force forms' ?></div></div>
                <div class="flex items-center gap-3">
                    <?= $row['is_active'] ? '<span class="app-badge app-badge-emerald">Active</span>' : '<span class="app-badge app-badge-slate">Hidden</span>' ?>
                    <input type="hidden" name="action" value="toggle_official_crop"><input type="hidden" name="official_crop_id" value="<?= (int)$row['crop_id'] ?>">
                    <button class="app-btn-outline" type="submit"><?= $row['is_active'] ? 'Hide' : 'Show' ?></button>
                </div>
            </form>
        <?php endforeach; if(!$allOfficialRows): ?><div class="text-sm text-slate-500">No crops added yet.</div><?php endif; ?>
    </div>
</section>
<?php elseif(in_array($user['role'],['task_force','admin'],true)): ?>
<section class="rounded-[2rem] border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-6 shadow-sm">
    <div class="text-sm text-slate-500">Crop registry</div>
    <h2 class="text-2xl font-black">Register actual household crop</h2>
    <div class="mt-4 rounded-3xl bg-blue-50 dark:bg-blue-950/30 p-4 text-sm text-slate-600 dark:text-slate-300">Task Force can only use crops from the official municipal crop list. If the mayor has not added a crop yet, it will not appear here.</div>
    <?php if(!$officialCrops): ?>
        <div class="mt-5 rounded-2xl border border-amber-200 bg-amber-50 dark:border-amber-900 dark:bg-amber-950/30 p-4 text-sm">No official crop has been added yet. Ask the mayor to add the crop list first.</div>
    <?php else: ?>
    <form method="POST" class="mt-5 grid gap-4 md:grid-cols-2" id="cropForm">
        <input type="hidden" name="action" value="save_household_crop">
        <div class="md:col-span-2">
            <label class="block text-sm font-semibold mb-2">Household</label>
            <select name="household_id" id="cropHouseholdId" required class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-4 py-3">
                <option value="">Select household</option>
                <?php foreach($households as $household): ?><option value="<?= (int)$household['household_id'] ?>" data-area="<?= e((string)$household['area_sqm']) ?>" <?= $prefillHousehold === (int)$household['household_id'] ? 'selected' : '' ?>><?= e($household['household_head_name'].' - '.$household['household_code']) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-sm font-semibold mb-2">Official crop</label>
            <select name="official_crop_id" required class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-4 py-3"><option value="">Select crop</option><?php foreach($officialCrops as $crop): ?><option value="<?= (int)$crop['crop_id'] ?>"><?= e($crop['crop_name']) ?></option><?php endforeach; ?></select>
        </div>
        <div>
            <label class="block text-sm font-semibold mb-2">Variety</label>
            <input name="variety" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-4 py-3">
        </div>
        <div>
            <label class="block text-sm font-semibold mb-2">Plot name</label>
            <input name="plot_name" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-4 py-3" placeholder="Backyard, lower field, north side...">
        </div>
        <div>
            <label class="block text-sm font-semibold mb-2">Tree count</label>
            <input type="number" min="0" name="tree_count" value="1" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-4 py-3">
        </div>
        <div>
            <label class="block text-sm font-semibold mb-2">Area (sqm)</label>
            <input type="number" step="0.01" name="area_sqm" id="cropAreaSqm" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-4 py-3" placeholder="Optional crop area">
        </div>
        <div>
            <label class="block text-sm font-semibold mb-2">Condition</label>
            <select name="current_condition" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-4 py-3"><option>For Validation</option><option>Good</option><option>Bad</option><option>Needs Rehab</option></select>
        </div>
        <div>
            <label class="block text-sm font-semibold mb-2">Fruiting status</label>
            <select name="fruiting_status" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-4 py-3"><option>Unknown</option><option>Fruiting</option><option>Not Fruiting</option></select>
        </div>
        <div>
            <label class="block text-sm font-semibold mb-2">Planted date</label>
            <input type="date" name="planted_date" id="plantedDate" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-4 py-3">
        </div>
        <div>
            <label class="block text-sm font-semibold mb-2">Expected fruiting</label>
            <input type="date" name="expected_fruiting_date" id="expectedFruitingDate" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-4 py-3">
        </div>
        <div class="md:col-span-2"><label class="block text-sm font-semibold mb-2">Remarks</label><textarea name="remarks" rows="3" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-4 py-3"></textarea></div>
        <div class="md:col-span-2 flex flex-wrap gap-3"><button class="app-btn-primary">Save crop</button></div>
    </form>
    <?php endif; ?>
</section>
<?php endif; ?>
<section class="rounded-[2rem] border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-6 shadow-sm">
    <div class="text-sm text-slate-500">Crop registry list</div>
    <h2 class="text-2xl font-black">Saved crops</h2>
    <div class="mt-5 overflow-hidden rounded-3xl border border-slate-200 dark:border-slate-800">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 dark:bg-slate-900"><tr><th class="px-4 py-3 text-left">Crop</th><th class="px-4 py-3 text-left">Household</th><th class="px-4 py-3 text-left">QR ref</th><th class="px-4 py-3 text-left">Condition</th><th class="px-4 py-3 text-left">Fruiting</th><th class="px-4 py-3 text-right">Trees</th></tr></thead>
            <tbody>
            <?php foreach($rows as $row): ?><tr class="border-t border-slate-200 dark:border-slate-800"><td class="px-4 py-3 font-semibold"><?= e($row['crop_name']) ?><?php if($row['plot_name']): ?><div class="text-xs text-slate-500"><?= e($row['plot_name']) ?></div><?php endif; ?></td><td class="px-4 py-3"><a href="/harvest/modules/agri/households/view.php?id=<?= (int)$row['household_id'] ?>" class="hover:underline"><?= e($row['household_head_name']) ?></a></td><td class="px-4 py-3"><?= e($row['qr_reference']) ?></td><td class="px-4 py-3"><?= format_status_badge($row['current_condition']) ?></td><td class="px-4 py-3"><?= format_status_badge($row['fruiting_status']) ?></td><td class="px-4 py-3 text-right"><?= e((string)$row['tree_count']) ?></td></tr><?php endforeach; if(!$rows): ?><tr><td colspan="6" class="px-4 py-6 text-center text-slate-500">No crops yet.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
</div>
<script>
(function(){
  const householdSelect = document.getElementById('cropHouseholdId');
  const areaInput = document.getElementById('cropAreaSqm');
  const plantedDate = document.getElementById('plantedDate');
  const expectedDate = document.getElementById('expectedFruitingDate');
  function syncHouseholdArea(){ if(!householdSelect || !areaInput) return; const opt = householdSelect.options[householdSelect.selectedIndex]; if(opt && opt.dataset.area && !areaInput.value){ areaInput.value = opt.dataset.area; } }
  function syncFruitingDate(){ if(!plantedDate || !expectedDate || !plantedDate.value || expectedDate.value) return; const d = new Date(plantedDate.value + 'T00:00:00'); d.setMonth(d.getMonth() + 12); expectedDate.value = d.toISOString().slice(0,10); }
  householdSelect && householdSelect.addEventListener('change', syncHouseholdArea);
  plantedDate && plantedDate.addEventListener('change', syncFruitingDate);
  syncHouseholdArea();
})();
</script>
<?php app_require('app/includes/footer.php'); ?>
