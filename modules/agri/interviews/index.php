<?php
require_once dirname(__DIR__, 3) . '/app/bootstrap.php';

$conn = db_conn();app_require('app/includes/auth.php'); require_role(['task_force','admin','mayor']);
require_once app_path('app/config/database.php'); app_require('app/includes/app_helpers.php');
$user = current_user();
$prefillId = (int)($_GET['household_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($user['role'], ['task_force','admin'], true)) {
    $household = (int)post('household_id');
    $status = post('status') === 'Draft' ? 'Draft' : 'Completed';
    if ($household > 0) {
        $snapshot = get_household_snapshot($conn, $household);
        $stmt = $conn->prepare("INSERT INTO interviews (household_id, interviewed_by, interview_date, register_no, allowed_fruit_backyard, hh_planter_program, fruit_planting_backyard_program, intended_number_of_trees, current_number_of_trees, program_participation_count, primary_concern, source_of_livelihood, water_source, farm_location_notes, compliance_status, remarks, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $uid = (int)$user['id'];
        $d = post('interview_date') ?: date('Y-m-d');
        $reg = trim((string)post('register_no')) ?: ($snapshot['reference_no'] ?: $snapshot['household_code'] ?: null);
        $afb = post('allowed_fruit_backyard') ? 1 : 0;
        $hh = post('hh_planter_program') ? 1 : 0;
        $fp = post('fruit_planting_backyard_program') ? 1 : 0;
        $intend = max(0, (int)post('intended_number_of_trees'));
        $curr = household_active_tree_count($conn, $household);
        $part = household_event_participation_count($conn, $household);
        $conc = trim((string)post('primary_concern')) ?: null;
        $live = trim((string)post('source_of_livelihood')) ?: null;
        $water = trim((string)post('water_source')) ?: null;
        $farmLocation = trim((string)post('farm_location_notes')) ?: null;
        $comp = post('compliance_status') ?: 'For Validation';
        $rem = trim((string)post('remarks')) ?: null;
        if ($stmt) {
            $stmt->bind_param('iissiiiiiisssssss', $household, $uid, $d, $reg, $afb, $hh, $fp, $intend, $curr, $part, $conc, $live, $water, $farmLocation, $comp, $rem, $status);
            $stmt->execute();
            $id = $stmt->insert_id;
            $stmt->close();
            sync_household_auto_fields($conn, $household);
            if ($status === 'Completed') {
                refresh_household_qualification_php($conn, $household);
                create_notification($conn, 'Interview completed', 'Interview captured for household #' . $household . '.', 'Low', $uid, $household, null, 'Missing Interview');
                app_log($conn, $uid, 'INTERVIEWS', 'CREATE', $id, 'Interview completed');
                set_flash('success', 'Interview saved. Program participation and current tree count were auto-computed from attendance and crop records.');
            } else {
                create_notification($conn, 'Interview draft saved', 'Draft interview is waiting for completion for household #' . $household . '.', 'Low', $uid, $household, null, 'Missing Interview');
                app_log($conn, $uid, 'INTERVIEWS', 'DRAFT', $id, 'Interview draft saved');
                set_flash('success', 'Interview draft saved.');
            }
            header('Location: /harvest/modules/agri/households/view.php?id=' . $household); exit;
        }
    }
    set_flash('error', 'Please select a valid household.'); header('Location: /harvest/modules/agri/interviews/index.php'); exit;
}

$households = fetch_all_assoc($conn, "SELECT household_id, household_head_name, household_code, contact_number FROM households ORDER BY household_head_name");
$rows = fetch_all_assoc($conn, "SELECT i.interview_id,i.interview_date,i.current_number_of_trees,i.program_participation_count,i.compliance_status,i.status,h.household_id,h.household_head_name,h.household_code FROM interviews i JOIN households h ON h.household_id=i.household_id ORDER BY i.interview_id DESC LIMIT 100");
app_require('app/includes/header.php');
?>
<div class="grid gap-6 xl:grid-cols-[1fr_0.95fr]">
<?php if(in_array($user['role'],['task_force','admin'],true)): ?>
<section class="rounded-[2rem] border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-6 shadow-sm">
    <div class="text-sm text-slate-500">Interview workflow</div>
    <h2 class="text-2xl font-black">Capture household profiling</h2>
    <div class="mt-4 rounded-3xl bg-blue-50 dark:bg-blue-950/30 p-4 text-sm text-slate-600 dark:text-slate-300">Correct flow: <strong>Family → Interview → Crops → Monitoring</strong>. Auto fields in this form are pulled from the saved household, attendance, and crop registry so the encoder only fills information that is not yet in the system.</div>

    <form method="POST" class="mt-5 grid gap-4 md:grid-cols-2" id="interviewForm">
        <div class="md:col-span-2 grid gap-3 md:grid-cols-[1fr_auto]">
            <div>
                <label class="block text-sm font-semibold mb-2">Household QR reference</label>
                <input id="householdQrInput" placeholder="Scan or paste household QR" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-4 py-3">
            </div>
            <div class="self-end"><a href="/harvest/modules/agri/qr/scan.php" class="app-btn-outline">Open scanner</a></div>
        </div>
        <div class="md:col-span-2">
            <label class="block text-sm font-semibold mb-2">Search registered household</label>
            <input id="householdSearch" list="householdList" placeholder="Type household name, code, or contact" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-4 py-3">
            <datalist id="householdList"><?php foreach($households as $household): ?><option data-id="<?= (int)$household['household_id'] ?>" value="<?= e($household['household_head_name'].' - '.$household['household_code'].' - '.($household['contact_number'] ?: '')) ?>"></option><?php endforeach; ?></datalist>
            <input type="hidden" name="household_id" id="householdId" value="<?= $prefillId ?: '' ?>">
        </div>
        <div class="md:col-span-2 rounded-3xl border border-dashed border-slate-300 dark:border-slate-700 p-4" id="householdSummary"><div class="text-sm text-slate-500">No household selected yet.</div></div>

        <div>
            <label class="block text-sm font-semibold mb-2">Interview date</label>
            <input type="date" name="interview_date" value="<?= date('Y-m-d') ?>" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-4 py-3">
        </div>
        <div>
            <label class="block text-sm font-semibold mb-2">Register no.</label>
            <input name="register_no" id="registerNo" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-4 py-3" placeholder="Auto-filled from household when available">
        </div>
        <div>
            <label class="block text-sm font-semibold mb-2">No. of fruit trees intended</label>
            <input type="number" min="0" name="intended_number_of_trees" id="intendedTrees" value="0" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-4 py-3">
        </div>
        <div>
            <label class="block text-sm font-semibold mb-2">Current number of trees <span class="text-xs text-slate-500">(auto from crop registry)</span></label>
            <input type="number" name="current_number_of_trees" id="currentTrees" value="0" readonly class="w-full rounded-2xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900 px-4 py-3 cursor-not-allowed">
        </div>
        <div>
            <label class="block text-sm font-semibold mb-2">Program participation count <span class="text-xs text-slate-500">(auto from attendance)</span></label>
            <input type="number" name="program_participation_count" id="participationCount" value="0" readonly class="w-full rounded-2xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900 px-4 py-3 cursor-not-allowed">
        </div>
        <div>
            <label class="block text-sm font-semibold mb-2">Compliance status</label>
            <select name="compliance_status" id="complianceStatus" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-4 py-3"><option>For Validation</option><option>Fully Compliant</option><option>Partially Compliant</option><option>Not Compliant</option></select>
        </div>
        <div>
            <label class="block text-sm font-semibold mb-2">Source of livelihood</label>
            <input name="source_of_livelihood" id="sourceOfLivelihood" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-4 py-3">
        </div>
        <div>
            <label class="block text-sm font-semibold mb-2">Water source</label>
            <input name="water_source" id="waterSource" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-4 py-3">
        </div>
        <div class="md:col-span-2">
            <label class="block text-sm font-semibold mb-2">Primary concern / farming issues</label>
            <textarea name="primary_concern" id="primaryConcern" rows="2" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-4 py-3"></textarea>
        </div>
        <div class="md:col-span-2">
            <label class="block text-sm font-semibold mb-2">Farm location notes</label>
            <textarea name="farm_location_notes" id="farmLocationNotes" rows="2" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-4 py-3"></textarea>
        </div>
        <div class="md:col-span-2 flex flex-wrap gap-4 rounded-2xl border p-4 bg-slate-50/70 dark:bg-slate-900/50">
            <label class="inline-flex items-center gap-2"><input type="checkbox" name="allowed_fruit_backyard" id="allowedFruitBackyard"> Allowed fruit (backyard)</label>
            <label class="inline-flex items-center gap-2"><input type="checkbox" name="hh_planter_program" id="hhPlanterProgram"> HH planter program</label>
            <label class="inline-flex items-center gap-2"><input type="checkbox" name="fruit_planting_backyard_program" id="fruitBackyardProgram"> Fruit backyard program</label>
        </div>
        <div class="md:col-span-2">
            <label class="block text-sm font-semibold mb-2">Remarks</label>
            <textarea name="remarks" id="remarksField" rows="3" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-4 py-3"></textarea>
        </div>
        <div class="md:col-span-2 rounded-2xl border border-emerald-200 bg-emerald-50 dark:border-emerald-900 dark:bg-emerald-950/30 p-4 text-sm text-slate-600 dark:text-slate-300">
            <div class="font-semibold text-emerald-800 dark:text-emerald-300">Auto fields in this interview</div>
            <div class="mt-2">Program participation count comes from Attendance. Current number of trees comes from Crop Registry. Register number defaults from the saved family record or QR-linked household code.</div>
        </div>
        <div class="md:col-span-2 flex flex-wrap gap-3">
            <button class="app-btn-primary">Save interview</button>
            <button type="submit" class="app-btn-outline" name="status" value="Draft">Save draft</button>
        </div>
    </form>
</section>
<?php endif; ?>
<section class="rounded-[2rem] border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-6 shadow-sm">
    <div class="text-sm text-slate-500">Interview history</div>
    <h2 class="text-2xl font-black">Recent interviews</h2>
    <div class="mt-4 text-sm text-slate-500">Use this list to reopen the family profile after encoding.</div>
    <div class="mt-5 overflow-hidden rounded-3xl border border-slate-200 dark:border-slate-800">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 dark:bg-slate-900"><tr><th class="px-4 py-3 text-left">Date</th><th class="px-4 py-3 text-left">Household</th><th class="px-4 py-3 text-left">Code</th><th class="px-4 py-3 text-right">Trees</th><th class="px-4 py-3 text-right">Events</th><th class="px-4 py-3 text-left">Status</th><th class="px-4 py-3 text-right">Action</th></tr></thead>
            <tbody>
            <?php foreach($rows as $row): ?>
                <tr class="border-t border-slate-200 dark:border-slate-800"><td class="px-4 py-3"><?= e($row['interview_date']) ?></td><td class="px-4 py-3 font-semibold"><?= e($row['household_head_name']) ?></td><td class="px-4 py-3"><?= e($row['household_code']) ?></td><td class="px-4 py-3 text-right"><?= (int)$row['current_number_of_trees'] ?></td><td class="px-4 py-3 text-right"><?= (int)$row['program_participation_count'] ?></td><td class="px-4 py-3"><?= format_status_badge($row['compliance_status']) ?></td><td class="px-4 py-3 text-right"><a href="/harvest/modules/agri/households/view.php?id=<?= (int)$row['household_id'] ?>" class="app-btn-outline">Open</a></td></tr>
            <?php endforeach; if(!$rows): ?><tr><td colspan="7" class="px-4 py-6 text-center text-slate-500">No interviews yet.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
</div>
<script>
(function(){
 const search = document.getElementById('householdSearch');
 const hiddenId = document.getElementById('householdId');
 const qrInput = document.getElementById('householdQrInput');
 const summary = document.getElementById('householdSummary');
 const datalist = document.getElementById('householdList');
 const fields = {
   registerNo: document.getElementById('registerNo'),
   intendedTrees: document.getElementById('intendedTrees'),
   currentTrees: document.getElementById('currentTrees'),
   participationCount: document.getElementById('participationCount'),
   complianceStatus: document.getElementById('complianceStatus'),
   primaryConcern: document.getElementById('primaryConcern'),
   sourceOfLivelihood: document.getElementById('sourceOfLivelihood'),
   waterSource: document.getElementById('waterSource'),
   farmLocationNotes: document.getElementById('farmLocationNotes'),
   remarksField: document.getElementById('remarksField'),
   allowedFruitBackyard: document.getElementById('allowedFruitBackyard'),
   hhPlanterProgram: document.getElementById('hhPlanterProgram'),
   fruitBackyardProgram: document.getElementById('fruitBackyardProgram')
 };
 function bindSearch(){
   const val = search.value;
   const option = Array.from(datalist.options).find(o => o.value === val);
   if(option){ hiddenId.value = option.dataset.id || ''; loadHousehold(hiddenId.value); }
 }
 async function loadHousehold(id){
   if(!id){ summary.innerHTML = '<div class="text-sm text-slate-500">No household selected yet.</div>'; return; }
   summary.innerHTML = '<div class="text-sm text-slate-500">Loading household...</div>';
   const res = await fetch('/harvest/modules/api/household_lookup.php?id=' + encodeURIComponent(id));
   const json = await res.json();
   if(!json.ok || !json.data){ summary.innerHTML = '<div class="text-sm text-rose-500">Household not found.</div>'; return; }
   const d = json.data;
   const latest = d.latest_interview || {};
   fields.registerNo.value = latest.register_no || d.reference_no || d.household_code || '';
   fields.currentTrees.value = Number(d.total_trees || 0);
   fields.participationCount.value = Number(d.program_participation_count || 0);
   fields.intendedTrees.value = latest.intended_number_of_trees || 0;
   fields.complianceStatus.value = latest.compliance_status || d.qualification_status || 'For Validation';
   fields.primaryConcern.value = latest.primary_concern || '';
   fields.sourceOfLivelihood.value = latest.source_of_livelihood || '';
   fields.waterSource.value = latest.water_source || '';
   fields.farmLocationNotes.value = latest.farm_location_notes || '';
   fields.remarksField.value = latest.remarks || '';
   fields.allowedFruitBackyard.checked = String(latest.allowed_fruit_backyard || '0') === '1';
   fields.hhPlanterProgram.checked = String(latest.hh_planter_program || '0') === '1';
   fields.fruitBackyardProgram.checked = String(latest.fruit_planting_backyard_program || '0') === '1';
   const cropLines = (d.active_crops || []).map(c => `${c.crop_name} (${c.tree_count} trees)`).join(', ') || 'No registered crops yet';
   const pending = (d.pending_actions || []).length ? d.pending_actions.join(', ') : 'Operationally Ready';
   const photo = d.photo_url || '/harvest/public/assets/img/image.jpg';
   summary.innerHTML = `
      <div class="grid gap-4 md:grid-cols-[auto_1fr] md:items-start text-sm">
        <img src="${photo}" alt="Head photo" class="h-24 w-24 rounded-[1.5rem] object-cover border border-slate-200 dark:border-slate-800 bg-slate-100">
        <div class="grid gap-4 md:grid-cols-2">
          <div>
            <div class="text-slate-500">Selected family</div>
            <div class="mt-1 text-lg font-black">${d.head_name || d.household_head_name || '-'}</div>
            <div class="mt-1">Code: ${d.household_code || '-'}<br>QR: ${d.qr_reference || '-'}<br>Barangay: ${d.barangay_name || '-'}<br>Contact: ${d.contact_number || '-'}</div>
          </div>
          <div>
            <div class="text-slate-500">Auto summary</div>
            <div class="mt-1">Events joined: ${d.program_participation_count || 0}<br>Total trees: ${d.total_trees || 0}<br>Crops: ${cropLines}<br>Pending actions: ${pending}</div>
          </div>
        </div>
      </div>`;
 }
 async function lookupQr(){
   const qr = (qrInput.value || '').trim(); if(!qr) return;
   const res = await fetch('/harvest/modules/api/qr_lookup.php?qr=' + encodeURIComponent(qr) + '&action=Interview');
   const json = await res.json();
   if(json.ok && json.data && json.data.household_id){ hiddenId.value = json.data.household_id; search.value = `${json.data.household_head_name || ''} - ${json.data.household_code || ''}`; loadHousehold(hiddenId.value); }
 }
 search.addEventListener('change', bindSearch);
 qrInput.addEventListener('change', lookupQr);
 qrInput.addEventListener('blur', lookupQr);
 if(hiddenId.value){ loadHousehold(hiddenId.value); }
})();
</script>
<?php app_require('app/includes/footer.php'); ?>
