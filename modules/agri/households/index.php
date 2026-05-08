<?php
require_once dirname(__DIR__, 3) . '/app/bootstrap.php';
$conn = db_conn();
app_require('app/includes/auth.php');
require_role(['task_force','admin','mayor','developer']);
$user = current_user();
ensure_family_upgrade_schema($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($user['role'], ['task_force','admin'], true)) {
    $action = post('action');
    if ($action === 'create_household') {
        $head = trim((string)post('household_head_name'));
        $barangay = (int)post('barangay_id');
        $contact = trim((string)post('contact_number'));
        if ($head !== '' && $barangay > 0) {
            $duplicates = duplicate_household_matches($conn, $head, $barangay, $contact);
            if ($duplicates) {
                set_flash('error', 'Possible duplicate household found. Please search first before creating another record.');
                header('Location: ' . app_url('modules/agri/households/index.php?q=' . urlencode($head)));
                exit;
            }
            $stmt = $conn->prepare("INSERT INTO households (barangay_id, household_head_name, sex, birthdate, age, contact_number, purok_sitio, full_address, area_sqm, area_hectares, household_size, program_participation_count, is_active_farmer, is_fruit_planter, remarks, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $sex = post('sex') ?: null;
            $birthdate = post('birthdate') ?: null;
            $age = calculate_age_from_birthdate($birthdate);
            if ($age === null && post('age') !== '') $age = (int)post('age');
            $contactValue = $contact !== '' ? $contact : null;
            $purok = trim((string)post('purok_sitio')) ?: null;
            $address = trim((string)post('full_address')) ?: null;
            $areaSqm = post('area_sqm') !== '' ? (float)post('area_sqm') : null;
            $areaHa = post('area_hectares') !== '' ? (float)post('area_hectares') : ($areaSqm ? round($areaSqm / 10000, 4) : null);
            $ppc = 0;
            $active = post('is_active_farmer') ? 1 : 0;
            $planter = post('is_fruit_planter') ? 1 : 0;
            $remarks = trim((string)post('remarks')) ?: null;
            $uid = (int)$user['id'];
            $size = 1;
            if ($stmt) {
                $stmt->bind_param('isssisssddiiiisi', $barangay, $head, $sex, $birthdate, $age, $contactValue, $purok, $address, $areaSqm, $areaHa, $size, $ppc, $active, $planter, $remarks, $uid);
                $stmt->execute();
                $id = (int)$stmt->insert_id;
                $stmt->close();
                ensure_household_assets($conn, $id, $uid);
                $sourceHhNo = trim((string)post('official_hh_no'));
                if ($sourceHhNo !== '') {
                    $baseNo = household_hh_base_no($sourceHhNo);
                    $suffixNo = household_hh_suffix($sourceHhNo);
                    $groupKey = household_hh_has_letter_suffix($sourceHhNo) && $baseNo !== null && $baseNo !== '' ? ('BRGY|' . $barangay . '|BASE|' . strtoupper($baseNo)) : ('HH|' . $id);
                    $stmt = $conn->prepare("UPDATE households SET registered_hh_no=?, official_hh_no=?, source_hh_no=?, hh_base_no=?, hh_suffix=?, hh_is_excel_supplied=1, household_group_key=? WHERE household_id=?");
                    if ($stmt) { $stmt->bind_param('ssssssi', $sourceHhNo, $sourceHhNo, $sourceHhNo, $baseNo, $suffixNo, $groupKey, $id); $stmt->execute(); $stmt->close(); }
                }
                $headMemberId = upsert_household_head_member($conn, $id, [
                    'full_name' => $head,
                    'sex' => $sex,
                    'birthdate' => $birthdate,
                    'age' => $age,
                    'contact_number' => $contactValue,
                    'civil_status' => post('civil_status') ?: null,
                    'occupation' => post('occupation') ?: null,
                    'education_level' => post('education_level') ?: null,
                    'member_status' => 'Living in household',
                    'remarks' => $remarks,
                ], $_FILES['head_photo'] ?? null);
                if ($headMemberId > 0 && column_exists($conn, 'households', 'head_member_id')) {
                    $stmt = $conn->prepare("UPDATE households SET head_member_id=?, profile_photo_path=(SELECT member_photo_path FROM family_members WHERE member_id=? LIMIT 1) WHERE household_id=?");
                    if ($stmt) { $stmt->bind_param('iii', $headMemberId, $headMemberId, $id); $stmt->execute(); $stmt->close(); }
                }
                sync_household_auto_fields($conn, $id);
                refresh_household_qualification_php($conn, $id);
                create_notification($conn, 'New family profile', 'New household registered with head of family and ready for member profiling.', 'Low', $uid, $id, null, 'Qualification Updated');
                app_log($conn, $uid, 'HOUSEHOLDS', 'CREATE', $id, 'Created family profile with head member record');
                set_flash('success', 'Household record saved. You can now add family members and photos.');
                header('Location: ' . app_url('modules/agri/households/view.php?id=' . $id));
                exit;
            }
        } else {
            set_flash('error', 'Household head and barangay are required.');
        }
        header('Location: ' . app_url('modules/agri/households/index.php'));
        exit;
    }
}

$barangays = fetch_all_assoc($conn, "SELECT barangay_id, barangay_name FROM barangays ORDER BY barangay_name");
$q = trim((string)getv('q'));
$barangayFilter = (int)getv('barangay_id');
$statusFilter = trim((string)getv('qualification_status'));
$profileFilter = trim((string)getv('profile_filter'));
$recordStatusFilter = trim((string)getv('record_status')) ?: 'active';
if (!in_array($recordStatusFilter, ['active','archived','deleted','all'], true)) $recordStatusFilter = 'active';
$profileFilterOptions = [
    '' => 'All profiles',
    'farmers' => 'Farmers',
    'farming_household' => 'Farming households',
    '4ps' => '4Ps households',
    'pwd' => 'PWD',
    'senior_citizen' => 'Senior Citizen',
    'solo_parent' => 'Solo Parent',
    'philhealth' => 'With PhilHealth',
    'lgu_assistance' => 'With LGU assistance',
    'priority_high' => 'High / urgent priority',
    'priority_medium' => 'Medium priority',
    'ofw' => 'OFW',
    'unemployed' => 'Unemployed',
    'pregnant' => 'Pregnant',
    'breastfeeding' => 'Breastfeeding Mother',
    'youth' => 'Youth',
];
$rows = fetch_all_assoc($conn, household_search_sql($conn, $q, $barangayFilter, $statusFilter, $recordStatusFilter, $profileFilter));

$largestHouseholds = fetch_all_assoc($conn, "SELECT h.household_id, h.household_head_name, COALESCE(b.barangay_name, 'No barangay') AS barangay_name, COUNT(fm.member_id) AS member_count
    FROM households h
    LEFT JOIN barangays b ON b.barangay_id = h.barangay_id
    LEFT JOIN family_members fm ON fm.household_id = h.household_id AND COALESCE(fm.is_active, 1) = 1
    WHERE COALESCE(h.record_status,'active') <> 'deleted'
    GROUP BY h.household_id, h.household_head_name, b.barangay_name
    HAVING member_count > 0
    ORDER BY member_count DESC, h.household_head_name ASC
    LIMIT 12");
$largestHouseholdLabels = array_map(static fn($r) => trim(($r['household_head_name'] ?: 'Unnamed household') . ' · ' . ($r['barangay_name'] ?: 'No barangay')), $largestHouseholds);
$largestHouseholdData = array_map(static fn($r) => (int)$r['member_count'], $largestHouseholds);
$oversizedHouseholds = array_values(array_filter($largestHouseholds, static fn($r) => (int)$r['member_count'] >= 15));

$stats = [
    ['label'=>'Active family records', 'value'=>(int)scalar($conn, "SELECT COUNT(*) FROM households WHERE COALESCE(record_status,'active')='active'", 0), 'hint'=>'Family records visible in daily operations'],
    ['label'=>'Archived families', 'value'=>(int)scalar($conn, "SELECT COUNT(*) FROM households WHERE COALESCE(record_status,'active')='archived'", 0), 'hint'=>'Can be reactivated by Task Force or Mayor'],
    ['label'=>'Needs action', 'value'=>(int)scalar($conn, "SELECT COUNT(*) FROM households h WHERE COALESCE(h.record_status,'active')='active' AND ((NOT EXISTS (SELECT 1 FROM interviews i WHERE i.household_id=h.household_id AND i.status='Completed')) OR (NOT EXISTS (SELECT 1 FROM monitoring_visits m WHERE m.household_id=h.household_id AND m.monitoring_date >= DATE_SUB(CURDATE(), INTERVAL 180 DAY))))", 0), 'hint'=>'Missing interview or fresh monitoring'],
    ['label'=>'Qualified', 'value'=>(int)scalar($conn, "SELECT COUNT(*) FROM household_qualification WHERE qualification_status IN ('Qualified','Highly Qualified')", 0), 'hint'=>'Auto-evaluated households'],
    ['label'=>'CBMS-lite encoded', 'value'=>(int)scalar($conn, "SELECT COUNT(*) FROM cbms_household_profiles", 0), 'hint'=>'Households with Special Program CBMS-lite profile'],
];
app_require('app/includes/header.php');
?>
<div class="space-y-6">
<?= nav_cards($stats) ?>
<section class="rounded-[2rem] border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-6 shadow-sm">
    <div class="flex flex-col gap-4">
        <div class="flex flex-col gap-2 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <div class="text-sm text-slate-500">Household-first records</div>
                <h2 class="text-2xl font-black">Households and families</h2>
                <p class="mt-1 text-sm text-slate-500">One household can contain multiple families. HH numbers follow the Excel source only.</p>
            </div>
            <?php if (in_array($user['role'], ['task_force','admin'], true)): ?>
            <details class="group rounded-3xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900/40 open:pb-0">
                <summary class="list-none cursor-pointer select-none px-5 py-4">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <div class="text-sm text-slate-500">Household-first, source-driven HH numbering</div>
                            <div class="text-lg font-bold text-slate-900 dark:text-slate-100">Create household record</div>
                        </div>
                        <span class="inline-flex items-center rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-2 text-sm font-semibold text-slate-700 dark:text-slate-200 group-open:hidden">Open form</span>
                        <span class="hidden items-center rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-2 text-sm font-semibold text-slate-700 dark:text-slate-200 group-open:inline-flex">Hide form</span>
                    </div>
                </summary>
                <div class="border-t border-slate-200 dark:border-slate-800 px-5 pb-5 pt-4">
                    <div class="rounded-3xl bg-blue-50 dark:bg-blue-950/30 p-4 text-sm text-slate-600 dark:text-slate-300">Start with the head of family. After saving, you can add wife, husband, sons, daughters, cousins, and everyone living in the same house — each with their own personal details and photo.</div>
                    <form method="POST" enctype="multipart/form-data" class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        <input type="hidden" name="action" value="create_household">
                        <div class="xl:col-span-3"><label class="block text-sm font-semibold mb-2">Head of family</label><input name="household_head_name" required class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-4 py-3"></div>
                        <div><label class="block text-sm font-semibold mb-2">Barangay</label><select name="barangay_id" required class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-4 py-3"><option value="">Select barangay</option><?php foreach($barangays as $barangay): ?><option value="<?= (int)$barangay['barangay_id'] ?>"><?= e($barangay['barangay_name']) ?></option><?php endforeach; ?></select></div>
                        <div><label class="block text-sm font-semibold mb-2">Contact number</label><input name="contact_number" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-4 py-3"></div>
                        <div><label class="block text-sm font-semibold mb-2">Purok / Sitio</label><input name="purok_sitio" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-4 py-3"></div>
                        <div><label class="block text-sm font-semibold mb-2">HH No. from Excel</label><input name="official_hh_no" placeholder="Example: 20-A" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-4 py-3"></div>
                        <div><label class="block text-sm font-semibold mb-2">Sex</label><select name="sex" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-4 py-3"><option value="">Not set</option><?php foreach(sex_options() as $opt): ?><option value="<?= e($opt) ?>"><?= e($opt) ?></option><?php endforeach; ?></select></div>
                        <div><label class="block text-sm font-semibold mb-2">Civil status</label><select name="civil_status" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-4 py-3"><option value="">Not set</option><?php foreach(civil_status_options() as $opt): ?><option value="<?= e($opt) ?>"><?= e($opt) ?></option><?php endforeach; ?></select></div>
                        <div><label class="block text-sm font-semibold mb-2">Occupation</label><select name="occupation" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-4 py-3"><option value="">Not set</option><?php foreach(occupation_options() as $opt): ?><option value="<?= e($opt) ?>"><?= e($opt) ?></option><?php endforeach; ?></select></div>
                        <div><label class="block text-sm font-semibold mb-2">Birthdate</label><input type="date" name="birthdate" id="birthdate" data-birthdate-field data-age-target="age" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-4 py-3"></div>
                        <div><label class="block text-sm font-semibold mb-2">Age</label><input type="number" name="age" id="age" readonly class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 px-4 py-3"></div>
                        <div><label class="block text-sm font-semibold mb-2">Education</label><select name="education_level" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-4 py-3"><option value="">Not set</option><?php foreach(education_level_options() as $opt): ?><option value="<?= e($opt) ?>"><?= e($opt) ?></option><?php endforeach; ?></select></div>
                        <div><label class="block text-sm font-semibold mb-2">Area (sqm)</label><input type="number" step="0.01" name="area_sqm" id="areaSqm" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-4 py-3"></div>
                        <div><label class="block text-sm font-semibold mb-2">Area (hectares)</label><input type="number" step="0.0001" name="area_hectares" id="areaHa" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-4 py-3"></div>
                        <div><label class="block text-sm font-semibold mb-2">Head photo</label><input type="file" name="head_photo" accept="image/*" capture="environment" class="w-full rounded-2xl border border-dashed border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-4 py-3"></div>
                        <div class="md:col-span-2 xl:col-span-3"><label class="block text-sm font-semibold mb-2">Address</label><input name="full_address" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-4 py-3"></div>
                        <div class="md:col-span-2 xl:col-span-3"><label class="block text-sm font-semibold mb-2">Remarks</label><textarea name="remarks" rows="3" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-4 py-3"></textarea></div>
                        <div class="md:col-span-2 xl:col-span-3 flex flex-wrap gap-4"><label class="inline-flex items-center gap-2"><input type="checkbox" name="is_active_farmer" checked> <span>Active farmer</span></label><label class="inline-flex items-center gap-2"><input type="checkbox" name="is_fruit_planter" checked> <span>Fruit planter</span></label></div>
                        <div class="md:col-span-2 xl:col-span-3 flex flex-wrap gap-3"><button class="app-btn-primary">Save household & first family</button></div>
                    </form>
                </div>
            </details>
            <?php endif; ?>
        </div>
        <?php $householdExportQuery = http_build_query(['q' => $q ?: null, 'barangay_id' => $barangayFilter ?: null, 'profile_filter' => $profileFilter ?: null, 'qualification_status' => $statusFilter ?: null, 'record_status' => $recordStatusFilter ?: null]); ?>
        <div class="flex flex-wrap items-center justify-between gap-3">
            <form method="GET" class="grid flex-1 gap-3 lg:grid-cols-[1.2fr_1fr_1fr_1fr_1fr_auto]">
            <input type="text" name="q" value="<?= e($q) ?>" placeholder="Search head, member, code, or contact" class="rounded-2xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-4 py-3">
            <select name="barangay_id" class="rounded-2xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-4 py-3"><option value="0">All barangays</option><?php foreach($barangays as $barangay): ?><option value="<?= (int)$barangay['barangay_id'] ?>" <?= $barangayFilter === (int)$barangay['barangay_id'] ? 'selected' : '' ?>><?= e($barangay['barangay_name']) ?></option><?php endforeach; ?></select>
            <select name="profile_filter" class="rounded-2xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-4 py-3"><?php foreach($profileFilterOptions as $value=>$label): ?><option value="<?= e($value) ?>" <?= $profileFilter === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select>
            <select name="qualification_status" class="rounded-2xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-4 py-3"><option value="">All statuses</option><?php foreach(['Highly Qualified','Qualified','Needs Support','For Validation','Not Qualified'] as $status): ?><option value="<?= e($status) ?>" <?= $statusFilter === $status ? 'selected' : '' ?>><?= e($status) ?></option><?php endforeach; ?></select>
            <select name="record_status" class="rounded-2xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-4 py-3"><?php foreach(['active'=>'Active only','archived'=>'Archived only','deleted'=>'Deleted only','all'=>'All records'] as $value=>$label): ?><option value="<?= e($value) ?>" <?= $recordStatusFilter === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select>
            <button class="app-btn-outline">Filter</button>
            </form>
            <div class="flex flex-wrap gap-2">
                <a href="<?= e(app_url('modules/agri/households/export_csv.php') . ($householdExportQuery ? '?' . $householdExportQuery : '')) ?>" class="app-btn-outline">Export filtered CSV</a>
                <a href="<?= e(app_url('modules/agri/households/print_filtered.php') . ($householdExportQuery ? '?' . $householdExportQuery : '')) ?>" target="_blank" class="app-btn-outline">Print / Save PDF</a>
            </div>
        </div>
    </div>
    <?php if ($largestHouseholds): ?>
    <div class="mt-5 grid gap-5 xl:grid-cols-[1.4fr_0.9fr]">
        <section class="rounded-3xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900/40 p-5">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <div class="text-sm text-slate-500">Import review</div>
                    <h3 class="text-lg font-black">Households with the most members</h3>
                    <p class="mt-1 text-sm text-slate-500">Use this chart to spot households that may have been grouped incorrectly during import.</p>
                </div>
                <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-800/70 dark:bg-amber-950/30 dark:text-amber-200">
                    Review anything with <span class="font-bold">15+ members</span>.
                </div>
            </div>
            <div class="mt-4"><canvas id="largestHouseholdsChart" height="140"></canvas></div>
        </section>
        <section class="rounded-3xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900/40 p-5">
            <div class="text-sm text-slate-500">Suspiciously large households</div>
            <h3 class="text-lg font-black">Needs review</h3>
            <div class="mt-4 space-y-3">
                <?php if ($oversizedHouseholds): ?>
                    <?php foreach ($oversizedHouseholds as $suspect): ?>
                        <a href="<?= e(app_url('modules/agri/households/view.php?id=' . (int)$suspect['household_id'])) ?>" class="flex items-center justify-between gap-3 rounded-2xl border border-amber-200 bg-white px-4 py-3 text-sm hover:border-amber-300 dark:border-amber-800/70 dark:bg-slate-950/40">
                            <div class="min-w-0">
                                <div class="truncate font-semibold"><?= e($suspect['household_head_name']) ?></div>
                                <div class="text-xs text-slate-500"><?= e($suspect['barangay_name']) ?></div>
                            </div>
                            <span class="app-badge app-badge-amber whitespace-nowrap"><?= (int)$suspect['member_count'] ?> members</span>
                        </a>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900 dark:border-emerald-800/70 dark:bg-emerald-950/30 dark:text-emerald-200">No oversized households found in the current data.</div>
                <?php endif; ?>
            </div>
        </section>
    </div>
    <?php endif; ?>
    <div class="mt-5 overflow-x-auto overflow-y-auto max-h-[70vh] rounded-3xl border border-slate-200 dark:border-slate-800">
        <table class="min-w-full text-sm app-table-compact app-household-table household-results-table">
            <thead class="bg-slate-50 dark:bg-slate-900"><tr><th class="px-4 py-3 text-left w-[30%] min-w-[280px]">Family</th><th class="px-4 py-3 text-left w-[14%] min-w-[140px]">Status</th><th class="px-4 py-3 text-left w-[16%] min-w-[160px]">CBMS-lite</th><th class="px-4 py-3 text-left w-[16%] min-w-[170px]">Members / HH</th><th class="px-4 py-3 text-left w-[16%] min-w-[180px]">Open actions</th><th class="px-4 py-3 text-right w-[8%] min-w-[120px] whitespace-nowrap">Actions</th></tr></thead>
            <tbody>
            <?php foreach($rows as $row): $pendingActions = household_pending_actions($conn, (int)$row['household_id']); $groupContext = household_group_context($conn, (int)$row['household_id']); ?>
                <tr class="border-t border-slate-200 dark:border-slate-800 align-top">
                    <td class="px-4 py-4">
                        <div class="flex items-start gap-3 min-w-0">
                            <img src="<?= e(household_profile_photo($conn, (int)$row['household_id'], $row['profile_photo_path'] ?? null)) ?>" alt="Family photo" class="h-14 w-14 rounded-2xl object-cover border border-slate-200 dark:border-slate-800 shrink-0">
                            <div class="min-w-0">
                                <div class="font-semibold truncate"><?= e($row['household_head_name']) ?></div>
                                <div class="text-xs text-slate-500 mt-1"><?= e(($row['barangay_name'] ?: 'No barangay') . ' · ' . ($row['contact_number'] ?: 'No contact')) ?></div>
                                <?php if (!empty($row['member_summary'])): ?><div class="mt-2 text-xs text-slate-500 line-clamp-2">Members: <?= e($row['member_summary']) ?></div><?php endif; ?>
                            </div>
                        </div>
                    </td>
                    <td class="px-4 py-4">
                        <div class="flex flex-col gap-2">
                            <?= format_status_badge(ucfirst($row['record_status'] ?: 'active')) ?>
                            <?= format_status_badge($row['qualification_status'] ?: 'For Validation') ?>
                            <?php if (!empty($row['archive_reason'])): ?><div class="text-xs text-slate-500">Reason: <?= e($row['archive_reason']) ?></div><?php endif; ?>
                        </div>
                    </td>
                    <td class="px-4 py-4">
                        <div class="flex flex-wrap gap-2">
                            <?php if (!empty($row['farming_household'])): ?><span class="app-badge app-badge-emerald">Farming</span><?php endif; ?>
                            <?php if (!empty($row['is_4ps'])): ?><span class="app-badge app-badge-slate">4Ps</span><?php endif; ?>
                            <?php if (!empty($row['has_pwd'])): ?><span class="app-badge app-badge-amber">PWD</span><?php endif; ?>
                            <?php if (!empty($row['has_senior'])): ?><span class="app-badge app-badge-slate">Senior</span><?php endif; ?>
                            <?php if (!empty($row['has_solo_parent'])): ?><span class="app-badge app-badge-slate">Solo Parent</span><?php endif; ?>
                            <?php if (!empty($row['receives_lgu_assistance'])): ?><span class="app-badge app-badge-amber">LGU Assist</span><?php endif; ?>
                            <?php if (!empty($row['priority_level'])): ?><span class="app-badge app-badge-rose"><?= e($row['priority_level']) ?></span><?php endif; ?>
                            <?php if (empty($row['farming_household']) && empty($row['is_4ps']) && empty($row['has_pwd']) && empty($row['has_senior']) && empty($row['has_solo_parent']) && empty($row['receives_lgu_assistance']) && empty($row['priority_level'])): ?><span class="text-xs text-slate-400">No CBMS-lite flags yet</span><?php endif; ?>
                        </div>
                    </td>
                    <td class="px-4 py-4"><div class="font-semibold"><?= e((string)($row['household_size'] ?: household_member_count($conn, (int)$row['household_id']))) ?> family member(s)</div><div class="mt-1 text-xs text-slate-500"><?= e((string)($groupContext['family_count'] ?? 1)) ?> family unit(s) in HH<?php if (($groupContext['member_count'] ?? 0) > 0): ?> · <?= e((string)($groupContext['member_count'] ?? 0)) ?> total HH member(s)<?php endif; ?></div></td>
                    <td class="px-4 py-4">
                        <div class="flex flex-wrap gap-2">
                            <?php if ($pendingActions): ?>
                                <?php foreach (array_slice($pendingActions, 0, 3) as $action): ?><span class="app-badge app-badge-amber"><?= e($action) ?></span><?php endforeach; ?>
                                <?php if (count($pendingActions) > 3): ?><span class="app-badge app-badge-slate">+<?= count($pendingActions) - 3 ?> more</span><?php endif; ?>
                            <?php else: ?>
                                <span class="app-badge app-badge-emerald">Operationally Ready</span>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td class="px-4 py-4 text-right"><div class="flex flex-wrap justify-end gap-2"><a href="<?= e(app_url('modules/agri/households/view.php?id=' . (int)$row['household_id'])) ?>" class="app-btn-outline text-sm">Open</a><a href="<?= e(app_url('modules/agri/qr/print_household.php?household_id=' . (int)$row['household_id'])) ?>" target="_blank" class="app-btn-outline text-sm">QR</a></div></td>
                </tr>
            <?php endforeach; if(!$rows): ?>
                <tr><td colspan="6" class="px-4 py-6 text-center text-slate-500">No matching family records found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
</div>
<style>
.household-results-table{table-layout:fixed;}
.household-results-table th,.household-results-table td{vertical-align:top;word-break:break-word;}
.household-results-table th:last-child,.household-results-table td:last-child{white-space:nowrap;}
</style>

<?php if ($largestHouseholds): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
new Chart(document.getElementById('largestHouseholdsChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($largestHouseholdLabels) ?>,
        datasets: [{
            label: 'Members',
            data: <?= json_encode($largestHouseholdData) ?>,
            borderWidth: 1
        }]
    },
    options: {
        indexAxis: 'y',
        responsive: true,
        plugins: {
            legend: { display: false }
        },
        scales: {
            x: { beginAtZero: true, ticks: { precision: 0 } }
        }
    }
});
</script>
<?php endif; ?>
<script>
(function(){
    const birth = document.getElementById('birthdate');
    const age = document.getElementById('age');
    const sqm = document.getElementById('areaSqm');
    const ha = document.getElementById('areaHa');
    function syncAge(birthInput, ageInput){
        if(!birthInput || !ageInput) return;
        if(!birthInput.value){ ageInput.value=''; return; }
        const now=new Date(); const dob=new Date(birthInput.value);
        if (isNaN(dob.getTime()) || dob > now) { ageInput.value=''; return; }
        let years=now.getFullYear()-dob.getFullYear();
        const m=now.getMonth()-dob.getMonth();
        if(m<0 || (m===0 && now.getDate()<dob.getDate())) years--;
        ageInput.value=years>=0?years:'';
    }
    if (birth && age) {
        const update = function(){ syncAge(birth, age); };
        birth.addEventListener('change', update);
        birth.addEventListener('input', update);
        update();
    }
    if (sqm && ha) sqm.addEventListener('input', function(){ if(sqm.value){ ha.value=(Number(sqm.value)/10000).toFixed(4); } else { ha.value=''; } });
})();
</script>
<?php app_require('app/includes/footer.php'); ?>
