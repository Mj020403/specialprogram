<?php
require_once dirname(__DIR__, 3) . '/app/bootstrap.php';

$conn = db_conn();app_require('app/includes/auth.php'); require_role(['task_force','admin','mayor']);
require_once app_path('app/config/database.php'); app_require('app/includes/app_helpers.php');
$user = current_user();
$prefillId = (int)($_GET['household_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($user['role'], ['task_force','admin'], true)) {
    $household = (int)post('household_id');
    $crop = (int)post('crop_id');
    if ($household > 0 && $crop > 0) {
        $validCrop = (int)scalar($conn, "SELECT COUNT(*) FROM crops WHERE crop_id={$crop} AND household_id={$household} AND crop_status='Active'", 0);
        if ($validCrop <= 0) {
            set_flash('error', 'Select a crop that is already registered under the chosen household.');
            header('Location: /harvest/modules/agri/monitoring/index.php?household_id=' . $household); exit;
        }
        $stmt = $conn->prepare("INSERT INTO monitoring_visits (household_id, crop_id, monitored_by, monitoring_date, visit_time, tree_count_observed, fruiting_status, crop_condition, needs_rehabilitation, harvest_kg, monitoring_method, weather_condition, issue_observed, action_recommended, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $uid = (int)$user['id'];
        $date = post('monitoring_date') ?: date('Y-m-d');
        $time = post('visit_time') ?: null;
        $trees = max(0, (int)post('tree_count_observed'));
        $fruiting = post('fruiting_status') ?: 'Unknown';
        $condition = post('crop_condition') ?: 'For Validation';
        $rehab = post('needs_rehabilitation') ? 1 : 0;
        $harvest = (float)post('harvest_kg');
        $method = post('monitoring_method') ?: 'Manual Search';
        $weather = trim((string)post('weather_condition')) ?: null;
        $issue = trim((string)post('issue_observed')) ?: null;
        $action = trim((string)post('action_recommended')) ?: null;
        $notes = trim((string)post('notes')) ?: null;
        if ($stmt) {
            $stmt->bind_param('iiississidsssss', $household, $crop, $uid, $date, $time, $trees, $fruiting, $condition, $rehab, $harvest, $method, $weather, $issue, $action, $notes);
            $stmt->execute();
            $id = $stmt->insert_id;
            $stmt->close();
            sync_household_auto_fields($conn, $household);
            refresh_household_qualification_php($conn, $household);
            create_notification($conn, 'Monitoring captured', 'Monitoring saved for household #' . $household . '.', 'Low', $uid, $household, $crop, 'Needs Monitoring');
            if ($rehab || $condition === 'Needs Rehab') {
                create_notification($conn, 'Needs rehabilitation', 'Monitoring flagged a rehabilitation need for household #' . $household . '.', 'High', $uid, $household, $crop, 'Needs Rehab');
            }
            app_log($conn, $uid, 'MONITORING', 'CREATE', $id, 'Monitoring visit saved');
            set_flash('success', 'Monitoring saved for the selected household crop.');
            header('Location: /harvest/modules/agri/households/view.php?id=' . $household); exit;
        }
    }
    set_flash('error', 'Please select a valid household and registered crop.'); header('Location: /harvest/modules/agri/monitoring/index.php'); exit;
}

$households = fetch_all_assoc($conn, "SELECT household_id, household_head_name, household_code FROM households ORDER BY household_head_name");
$crops = fetch_all_assoc($conn, "SELECT crop_id, household_id, crop_name, qr_reference, tree_count, fruiting_status, current_condition FROM crops WHERE crop_status='Active' ORDER BY crop_name");
$rows = fetch_all_assoc($conn, "SELECT m.monitoring_date,h.household_id,h.household_head_name,c.crop_name,m.tree_count_observed,m.fruiting_status,m.crop_condition,m.harvest_kg,m.monitoring_method FROM monitoring_visits m JOIN households h ON h.household_id=m.household_id LEFT JOIN crops c ON c.crop_id=m.crop_id ORDER BY m.monitoring_id DESC LIMIT 100");
app_require('app/includes/header.php');
?>
<div class="grid gap-6 xl:grid-cols-[0.95fr_1.05fr]">
<?php if(in_array($user['role'],['task_force','admin'],true)): ?>
<section class="rounded-[2rem] border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-6 shadow-sm">
    <div class="text-sm text-slate-500">Monitoring workflow</div>
    <h2 class="text-2xl font-black">Monitor registered household crops</h2>
    <div class="mt-4 rounded-3xl bg-blue-50 dark:bg-blue-950/30 p-4 text-sm text-slate-600 dark:text-slate-300">Monitoring must follow the household record. First select or scan the household, then choose one of that household’s already-registered crops. If the family has no crop record yet, add it first in Crop Registry.</div>
    <form method="POST" class="mt-5 grid gap-4 md:grid-cols-2" id="monitoringForm">
        <div class="md:col-span-2 grid gap-3 md:grid-cols-[1fr_auto]">
            <div>
                <label class="block text-sm font-semibold mb-2">Household QR reference</label>
                <input id="monitorQrInput" placeholder="Scan or paste household QR" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-4 py-3">
            </div>
            <div class="self-end"><a href="/harvest/modules/agri/qr/scan.php" class="app-btn-outline">Open scanner</a></div>
        </div>
        <div class="md:col-span-2">
            <label class="block text-sm font-semibold mb-2">Search household</label>
            <input id="monitorSearch" list="monitorList" placeholder="Type household name or code" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-4 py-3">
            <datalist id="monitorList"><?php foreach($households as $household): ?><option data-id="<?= (int)$household['household_id'] ?>" value="<?= e($household['household_head_name'].' - '.$household['household_code']) ?>"></option><?php endforeach; ?></datalist>
            <input type="hidden" name="household_id" id="monitorHouseholdId" value="<?= $prefillId ?: '' ?>">
        </div>
        <div class="md:col-span-2 rounded-3xl border border-dashed border-slate-300 dark:border-slate-700 p-4" id="monitorSummary"><div class="text-sm text-slate-500">No household selected yet.</div></div>
        <div class="md:col-span-2">
            <label class="block text-sm font-semibold mb-2">Registered crop for this household</label>
            <select name="crop_id" id="cropSelect" required class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-4 py-3">
                <option value="">Select household first</option>
                <?php foreach($crops as $crop): ?><option value="<?= (int)$crop['crop_id'] ?>" data-household-id="<?= (int)$crop['household_id'] ?>" data-tree-count="<?= (int)$crop['tree_count'] ?>" data-fruiting="<?= e($crop['fruiting_status']) ?>" data-condition="<?= e($crop['current_condition']) ?>"><?= e($crop['crop_name'].' - '.($crop['qr_reference']?:'No QR yet')) ?></option><?php endforeach; ?>
            </select>
            <div class="mt-2 text-xs text-slate-500">Only crops already registered under the selected family are shown.</div>
        </div>
        <div>
            <label class="block text-sm font-semibold mb-2">Date</label>
            <input type="date" name="monitoring_date" id="monitoringDate" value="<?= date('Y-m-d') ?>" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-4 py-3">
        </div>
        <div>
            <label class="block text-sm font-semibold mb-2">Time</label>
            <input type="time" name="visit_time" value="<?= date('H:i') ?>" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-4 py-3">
        </div>
        <div>
            <label class="block text-sm font-semibold mb-2">Observed trees</label>
            <input type="number" name="tree_count_observed" id="treeObserved" value="0" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-4 py-3">
        </div>
        <div>
            <label class="block text-sm font-semibold mb-2">Harvest (kg)</label>
            <input type="number" step="0.01" name="harvest_kg" value="0" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-4 py-3">
        </div>
        <div>
            <label class="block text-sm font-semibold mb-2">Fruiting</label>
            <select name="fruiting_status" id="fruitingStatus" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-4 py-3"><option>Unknown</option><option>Fruiting</option><option>Not Fruiting</option></select>
        </div>
        <div>
            <label class="block text-sm font-semibold mb-2">Condition</label>
            <select name="crop_condition" id="cropCondition" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-4 py-3"><option>For Validation</option><option>Good</option><option>Bad</option><option>Needs Rehab</option></select>
        </div>
        <div>
            <label class="block text-sm font-semibold mb-2">Method</label>
            <select name="monitoring_method" id="monitorMethod" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-4 py-3"><option>Manual Search</option><option>QR Scan</option><option>Interview Follow-up</option><option>Event Follow-up</option></select>
        </div>
        <div>
            <label class="block text-sm font-semibold mb-2">Weather condition</label>
            <select name="weather_condition" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-4 py-3"><option value="">Not set</option><option>Sunny</option><option>Cloudy</option><option>Rainy</option><option>Windy</option></select>
        </div>
        <div class="flex items-center gap-2 pt-9"><input type="checkbox" name="needs_rehabilitation" id="needsRehab"> <span>Needs rehabilitation</span></div>
        <div class="md:col-span-2 rounded-2xl border p-4 text-sm text-slate-500" id="nextDueCard">Next suggested visit: 90 days after the monitoring date saved today.</div>
        <div class="md:col-span-2"><label class="block text-sm font-semibold mb-2">Issue observed</label><textarea name="issue_observed" rows="2" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-4 py-3"></textarea></div>
        <div class="md:col-span-2"><label class="block text-sm font-semibold mb-2">Recommended action</label><textarea name="action_recommended" rows="2" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-4 py-3"></textarea></div>
        <div class="md:col-span-2"><label class="block text-sm font-semibold mb-2">Notes</label><textarea name="notes" rows="3" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-4 py-3"></textarea></div>
        <div class="md:col-span-2 flex flex-wrap gap-3"><button class="app-btn-primary">Save monitoring</button></div>
    </form>
</section>
<?php endif; ?>
<section class="rounded-[2rem] border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-6 shadow-sm">
    <div class="text-sm text-slate-500">Monitoring history</div>
    <h2 class="text-2xl font-black">Recent monitoring records</h2>
    <div class="mt-5 overflow-hidden rounded-3xl border border-slate-200 dark:border-slate-800">
        <table class="min-w-full text-sm app-table-compact"><thead class="bg-slate-50 dark:bg-slate-900"><tr><th class="px-4 py-3 text-left">Date</th><th class="px-4 py-3 text-left">Household</th><th class="px-4 py-3 text-left">Crop</th><th class="px-4 py-3 text-left">Condition</th><th class="px-4 py-3 text-right">Harvest</th></tr></thead><tbody><?php foreach($rows as $row): ?><tr class="border-t border-slate-200 dark:border-slate-800"><td class="px-4 py-3"><?= e($row['monitoring_date']) ?></td><td class="px-4 py-3 font-semibold"><?= e($row['household_head_name']) ?></td><td class="px-4 py-3"><?= e($row['crop_name'] ?: '-') ?></td><td class="px-4 py-3"><?= format_status_badge($row['crop_condition']) ?></td><td class="px-4 py-3 text-right"><?= e((string)$row['harvest_kg']) ?> kg</td></tr><?php endforeach; if(!$rows): ?><tr><td colspan="5" class="px-4 py-6 text-center text-slate-500">No monitoring yet.</td></tr><?php endif; ?></tbody></table>
    </div>
</section>
</div>
<script>
(function(){
  const search = document.getElementById('monitorSearch');
  const qrInput = document.getElementById('monitorQrInput');
  const hiddenId = document.getElementById('monitorHouseholdId');
  const datalist = document.getElementById('monitorList');
  const summary = document.getElementById('monitorSummary');
  const cropSelect = document.getElementById('cropSelect');
  const treeObserved = document.getElementById('treeObserved');
  const fruitingStatus = document.getElementById('fruitingStatus');
  const cropCondition = document.getElementById('cropCondition');
  function filterCrops(){
    const hid = hiddenId.value;
    const opts = Array.from(cropSelect.querySelectorAll('option'));
    let visible = 0;
    opts.forEach((opt, idx) => {
      if(idx === 0){ opt.hidden = false; opt.textContent = hid ? 'Select registered crop' : 'Select household first'; return; }
      const show = hid && opt.dataset.householdId === hid;
      opt.hidden = !show;
      if(show) visible++;
    });
    cropSelect.value = '';
    if(!hid){ cropSelect.disabled = true; return; }
    cropSelect.disabled = visible === 0;
    if(visible === 0) cropSelect.options[0].textContent = 'No registered crop yet - add in Crop Registry first';
  }
  async function loadHousehold(id){
    if(!id){ summary.innerHTML = '<div class="text-sm text-slate-500">No household selected yet.</div>'; filterCrops(); return; }
    summary.innerHTML = '<div class="text-sm text-slate-500">Loading household...</div>';
    const res = await fetch('/harvest/modules/api/household_lookup.php?id=' + encodeURIComponent(id));
    const json = await res.json();
    if(!json.ok || !json.data){ summary.innerHTML = '<div class="text-sm text-rose-500">Household not found.</div>'; filterCrops(); return; }
    const d = json.data;
    const cropLines = (d.active_crops || []).map(c => `${c.crop_name} (${c.tree_count} trees)`).join(', ') || 'No registered crop yet';
    const photo = d.photo_url || '/harvest/public/assets/img/image.jpg';
    summary.innerHTML = `<div class="grid gap-4 md:grid-cols-[auto_1fr] md:items-start text-sm"><img src="${photo}" alt="Head photo" class="h-24 w-24 rounded-[1.5rem] object-cover border border-slate-200 dark:border-slate-800 bg-slate-100"><div class="grid gap-4 md:grid-cols-2"><div><div class="text-slate-500">Selected family</div><div class="mt-1 text-lg font-black">${d.head_name || d.household_head_name || '-'}</div><div class="mt-1">Code: ${d.household_code || '-'}<br>QR: ${d.qr_reference || '-'}<br>Barangay: ${d.barangay_name || '-'}<br>Members: ${(d.family_members || []).length || d.household_size || 0}</div></div><div><div class="text-slate-500">Monitoring-ready crops</div><div class="mt-1">${cropLines}<br>Total trees: ${d.total_trees || 0}<br>Latest monitoring: ${(d.latest_monitoring && d.latest_monitoring.monitoring_date) || '-'}</div></div></div></div>`;
    filterCrops();
  }
  function selectCrop(){
    const opt = cropSelect.options[cropSelect.selectedIndex];
    if(!opt || !opt.value) return;
    treeObserved.value = opt.dataset.treeCount || 0;
    fruitingStatus.value = opt.dataset.fruiting || 'Unknown';
    cropCondition.value = opt.dataset.condition || 'For Validation';
  }
  function bindSearch(){ const option = Array.from(datalist.options).find(o => o.value === search.value); if(option){ hiddenId.value = option.dataset.id || ''; loadHousehold(hiddenId.value); } }
  async function lookupQr(){ const qr = (qrInput.value || '').trim(); if(!qr) return; const res = await fetch('/harvest/modules/api/qr_lookup.php?qr=' + encodeURIComponent(qr) + '&action=Monitoring'); const json = await res.json(); if(json.ok && json.data && json.data.household_id){ hiddenId.value = json.data.household_id; search.value = `${json.data.household_head_name || ''} - ${json.data.household_code || ''}`; loadHousehold(hiddenId.value); } }
  search.addEventListener('change', bindSearch);
  qrInput.addEventListener('change', lookupQr);
  qrInput.addEventListener('blur', lookupQr);
  cropSelect.addEventListener('change', selectCrop);
  if(hiddenId.value){ loadHousehold(hiddenId.value); } else { filterCrops(); }
})();
</script>
<?php app_require('app/includes/footer.php'); ?>
