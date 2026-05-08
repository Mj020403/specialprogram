<?php
require_once dirname(__DIR__, 3) . '/app/bootstrap.php';
require_once __DIR__ . '/report_builder.php';

$conn = db_conn();
app_require('app/includes/auth.php');
require_role(['task_force','admin','mayor']);
app_require('app/includes/header.php');

$barangays = fetch_all_assoc($conn, "SELECT barangay_id, barangay_name FROM barangays ORDER BY barangay_name");
$filters = report_build_filters($conn);
$barangay = (int)$filters['barangay_id'];
$status = (string)$filters['qualification_status'];
$profileFilter = (string)$filters['profile_filter'];
$detailMode = (string)$filters['detail_mode'];
$profileFilterOptions = report_profile_filter_options();
$conditionMap = report_condition_map();
$familyProfileFilters = report_family_profile_filters();
$selectedColumns = report_selected_columns_from_request();
$availableColumns = report_available_columns();
$householdWhereSql = report_household_where_sql($conn, $filters);
$filteredHouseholdsSubquery = "SELECT h.household_id FROM households h LEFT JOIN household_qualification hq ON hq.household_id = h.household_id LEFT JOIN cbms_household_profiles chp ON chp.household_id = h.household_id LEFT JOIN household_beneficiary_flags hbf ON hbf.household_id = h.household_id{$householdWhereSql}";

$families = report_fetch_rows($conn, $filters, 250);

$statusRows = fetch_all_assoc($conn, "SELECT COALESCE(hq.qualification_status,'Unclassified') AS qualification_status, COUNT(*) AS total
    FROM households h
    LEFT JOIN household_qualification hq ON hq.household_id = h.household_id
    {$householdWhereSql}
    GROUP BY COALESCE(hq.qualification_status,'Unclassified')
    ORDER BY total DESC");
$statusLabels = [];
$statusData = [];
foreach ($statusRows as $row) {
    $statusLabels[] = $row['qualification_status'] ?: 'Unclassified';
    $statusData[] = (int)$row['total'];
}

$barangaySummarySql = "SELECT b.barangay_name,
    COUNT(h.household_id) AS households,
    SUM(CASE WHEN hq.qualification_status='For Validation' THEN 1 ELSE 0 END) AS validation_count,
    SUM(CASE WHEN hq.qualification_status='Needs Support' THEN 1 ELSE 0 END) AS support_count,
    AVG(COALESCE(hq.score,0)) AS avg_score,
    SUM(CASE WHEN EXISTS (
        SELECT 1 FROM family_members fm
        WHERE fm.household_id = h.household_id
          AND COALESCE(fm.is_active,1)=1
          AND (
              LOWER(COALESCE(fm.occupation,'')) LIKE '%farmer%'
              OR LOWER(COALESCE(fm.member_tags,'')) LIKE '%farmer%'
          )
    ) THEN 1 ELSE 0 END) AS farmer_households,
    SUM(CASE WHEN EXISTS (
        SELECT 1 FROM family_members fm
        WHERE fm.household_id = h.household_id
          AND COALESCE(fm.is_active,1)=1
          AND (
              LOWER(COALESCE(fm.member_tags,'')) LIKE '%pwd%'
              OR LOWER(COALESCE(fm.member_status,'')) LIKE '%pwd%'
              OR LOWER(COALESCE(fm.disability,'')) <> ''
          )
    ) THEN 1 ELSE 0 END) AS pwd_households,
    SUM(CASE WHEN EXISTS (
        SELECT 1 FROM family_members fm
        WHERE fm.household_id = h.household_id
          AND COALESCE(fm.is_active,1)=1
          AND (COALESCE(fm.member_tags,'') LIKE '%Senior Citizen%' OR COALESCE(fm.age,0) >= 60)
    ) THEN 1 ELSE 0 END) AS senior_households
FROM barangays b
LEFT JOIN households h ON h.barangay_id = b.barangay_id AND COALESCE(h.record_status,'active')='active'
LEFT JOIN household_qualification hq ON hq.household_id = h.household_id";
if ($status !== '' || ($profileFilter !== '' && isset($conditionMap[$profileFilter]))) {
    $barangaySummarySql .= " WHERE 1=1";
    if ($status !== '') {
        $barangaySummarySql .= " AND hq.qualification_status='" . $conn->real_escape_string($status) . "'";
    }
    if ($profileFilter !== '' && isset($conditionMap[$profileFilter])) {
        $barangaySummarySql .= " AND EXISTS (SELECT 1 FROM family_members fx WHERE fx.household_id = h.household_id AND COALESCE(fx.is_active,1)=1 AND {$conditionMap[$profileFilter]})";
    }
}
$barangaySummarySql .= " GROUP BY b.barangay_id, b.barangay_name ORDER BY households DESC, b.barangay_name ASC";
$barangaySummary = fetch_all_assoc($conn, $barangaySummarySql);
$barangaySummary = array_values(array_filter($barangaySummary, static fn($row) => (int)$row['households'] > 0));
$topBarangays = array_slice($barangaySummary, 0, 12);
$barangayLabels = array_map(static fn($r) => $r['barangay_name'], $topBarangays);
$barangayHouseholds = array_map(static fn($r) => (int)$r['households'], $topBarangays);
$barangayValidation = array_map(static fn($r) => (int)$r['validation_count'], $topBarangays);
$barangaySupport = array_map(static fn($r) => (int)$r['support_count'], $topBarangays);
$barangayAvgScore = array_map(static fn($r) => round((float)$r['avg_score'], 1), $topBarangays);
$barangayFarmers = array_map(static fn($r) => (int)$r['farmer_households'], $topBarangays);
$barangayPwd = array_map(static fn($r) => (int)$r['pwd_households'], $topBarangays);
$barangaySenior = array_map(static fn($r) => (int)$r['senior_households'], $topBarangays);

$largestHouseholds = fetch_all_assoc($conn, "SELECT h.household_id, h.household_head_name, COALESCE(b.barangay_name, 'No barangay') AS barangay_name, COUNT(fm.member_id) AS member_count
    FROM households h
    LEFT JOIN barangays b ON b.barangay_id = h.barangay_id
    LEFT JOIN family_members fm ON fm.household_id = h.household_id AND COALESCE(fm.is_active,1)=1
    LEFT JOIN household_qualification hq ON hq.household_id = h.household_id
    {$householdWhereSql}
    GROUP BY h.household_id, h.household_head_name, b.barangay_name
    HAVING member_count > 0
    ORDER BY member_count DESC, h.household_head_name ASC
    LIMIT 12");
$largestHouseholdLabels = array_map(static fn($r) => trim(($r['household_head_name'] ?: 'Unnamed household') . ' · ' . ($r['barangay_name'] ?: 'No barangay')), $largestHouseholds);
$largestHouseholdData = array_map(static fn($r) => (int)$r['member_count'], $largestHouseholds);
$oversizedHouseholds = array_values(array_filter($largestHouseholds, static fn($r) => (int)$r['member_count'] >= 15));

$cbmsLiteCount = (int)scalar($conn, "SELECT COUNT(*) FROM households h WHERE h.household_id IN ({$filteredHouseholdsSubquery}) AND EXISTS (SELECT 1 FROM cbms_household_profiles chp WHERE chp.household_id=h.household_id)", 0);
$priorityHighCount = (int)scalar($conn, "SELECT COUNT(*) FROM households h LEFT JOIN household_beneficiary_flags hbf ON hbf.household_id=h.household_id WHERE h.household_id IN ({$filteredHouseholdsSubquery}) AND COALESCE(hbf.priority_level,'') IN ('High','Urgent')", 0);
$assistedCount = (int)scalar($conn, "SELECT COUNT(*) FROM households h LEFT JOIN household_beneficiary_flags hbf ON hbf.household_id=h.household_id WHERE h.household_id IN ({$filteredHouseholdsSubquery}) AND COALESCE(hbf.receives_lgu_assistance,0)=1", 0);

$profileMixRows = [
    ['label' => 'Farmers', 'value' => (int)scalar($conn, "SELECT COUNT(*) FROM households h WHERE h.household_id IN ({$filteredHouseholdsSubquery}) AND EXISTS (SELECT 1 FROM family_members fx WHERE fx.household_id=h.household_id AND COALESCE(fx.is_active,1)=1 AND {$conditionMap['farmers']})", 0)],
    ['label' => 'PWD', 'value' => (int)scalar($conn, "SELECT COUNT(*) FROM households h WHERE h.household_id IN ({$filteredHouseholdsSubquery}) AND EXISTS (SELECT 1 FROM family_members fx WHERE fx.household_id=h.household_id AND COALESCE(fx.is_active,1)=1 AND {$conditionMap['pwd']})", 0)],
    ['label' => 'Senior', 'value' => (int)scalar($conn, "SELECT COUNT(*) FROM households h WHERE h.household_id IN ({$filteredHouseholdsSubquery}) AND EXISTS (SELECT 1 FROM family_members fx WHERE fx.household_id=h.household_id AND COALESCE(fx.is_active,1)=1 AND {$conditionMap['senior_citizen']})", 0)],
    ['label' => 'OFW', 'value' => (int)scalar($conn, "SELECT COUNT(*) FROM households h WHERE h.household_id IN ({$filteredHouseholdsSubquery}) AND EXISTS (SELECT 1 FROM family_members fx WHERE fx.household_id=h.household_id AND COALESCE(fx.is_active,1)=1 AND {$conditionMap['ofw']})", 0)],
    ['label' => 'Youth', 'value' => (int)scalar($conn, "SELECT COUNT(*) FROM households h WHERE h.household_id IN ({$filteredHouseholdsSubquery}) AND EXISTS (SELECT 1 FROM family_members fx WHERE fx.household_id=h.household_id AND COALESCE(fx.is_active,1)=1 AND {$conditionMap['youth']})", 0)],
];
$profileMixLabels = array_map(fn($r) => $r['label'], $profileMixRows);
$profileMixData = array_map(fn($r) => $r['value'], $profileMixRows);

$filteredRows = count($families);
$filteredMembers = 0;
foreach ($families as $family) {
    $filteredMembers += (int)($family['member_count'] ?? 0);
}
$qualifiedCount = 0;
$validationCount = 0;
$needsSupportCount = 0;
foreach ($families as $family) {
    $q = (string)($family['qualification_status'] ?? '');
    if (in_array($q, ['Highly Qualified','Qualified'], true)) $qualifiedCount++;
    if ($q === 'For Validation') $validationCount++;
    if ($q === 'Needs Support') $needsSupportCount++;
}
$avgScore = $filteredRows ? round(array_sum(array_map(static fn($f) => (float)($f['score'] ?? 0), $families)) / $filteredRows, 1) : 0;
$monitoringOverdue = (int)scalar($conn, "SELECT COUNT(*) FROM households h WHERE h.household_id IN ({$filteredHouseholdsSubquery}) AND NOT EXISTS (
    SELECT 1 FROM monitoring_visits m WHERE m.household_id=h.household_id AND m.monitoring_date >= DATE_SUB(CURDATE(), INTERVAL 180 DAY)
)", 0);

$reportTabs = [
    ['label' => 'All families', 'href' => app_url('modules/agri/reports/index.php'), 'active' => ($status === '')],
    ['label' => 'Needs support', 'href' => app_url('modules/agri/reports/index.php?qualification_status=Needs+Support'), 'active' => ($status === 'Needs Support')],
    ['label' => 'For validation', 'href' => app_url('modules/agri/reports/index.php?qualification_status=For+Validation'), 'active' => ($status === 'For Validation')],
    ['label' => 'Qualified', 'href' => app_url('modules/agri/reports/index.php?qualification_status=Qualified'), 'active' => ($status === 'Qualified')],
];

$kpis = [
    ['label' => 'Filtered households', 'value' => $filteredRows, 'hint' => 'Rows in the current report scope'],
    ['label' => 'Population in scope', 'value' => $filteredMembers, 'hint' => 'Active population inside the filtered rows'],
    ['label' => 'For validation', 'value' => $validationCount, 'hint' => 'Profiles still waiting for field review'],
    ['label' => 'Average score', 'value' => number_format($avgScore, 1), 'hint' => 'Qualification score across filtered households'],
];

$focusCards = [
    ['label' => 'Monitoring overdue', 'value' => number_format($monitoringOverdue), 'hint' => 'Households with no visit in the last 180 days'],
    ['label' => 'Needs support', 'value' => number_format($needsSupportCount), 'hint' => 'Families still in support queue'],
    ['label' => 'CBMS-lite encoded', 'value' => number_format($cbmsLiteCount), 'hint' => 'Households with CBMS-lite Special Program profiling'],
    ['label' => 'High priority', 'value' => number_format($priorityHighCount), 'hint' => 'Households tagged High or Urgent'],
    ['label' => 'With LGU assistance', 'value' => number_format($assistedCount), 'hint' => 'Households already receiving LGU assistance'],
    ['label' => 'Qualified / ready', 'value' => number_format($qualifiedCount), 'hint' => 'Qualified or highly qualified households'],
    ['label' => 'Needs data review', 'value' => count($oversizedHouseholds), 'hint' => 'Households with 15+ people that may need regrouping review'],
];

$baseExportQuery = report_export_query($filters, $selectedColumns);
$reportsPageTitle = system_reports_page_title($conn);
$reportsPageDescription = system_reports_page_description($conn);
$reportsExportNote = system_reports_export_note($conn);
$selectedColumnLabels = array_values(array_map(static fn($column) => $availableColumns[$column]['label'] ?? $column, $selectedColumns));
?>
<section class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
    <div class="rounded-[2rem] border border-emerald-100 bg-gradient-to-br from-emerald-50 via-white to-slate-50 p-6">
        <div class="flex flex-col gap-5 xl:flex-row xl:items-start xl:justify-between">
            <div class="max-w-3xl">
                <div class="text-sm font-semibold uppercase tracking-[0.18em] text-emerald-700">Reports command center</div>
                <h2 class="mt-2 text-4xl font-black tracking-tight text-slate-900"><?= e($reportsPageTitle) ?></h2>
                <p class="mt-3 text-base leading-7 text-slate-600"><?= e($reportsPageDescription) ?> This workspace is now organized as a guided flow: choose the scope, preview the results, then print or export without redoing the setup.</p>
                <div class="mt-4 flex flex-wrap gap-2">
                    <?php foreach ($reportTabs as $tab): ?>
                        <a href="<?= e($tab['href']) ?>" class="<?= $tab['active'] ? 'rounded-2xl border border-emerald-300 bg-emerald-600 px-5 py-3 font-semibold text-white shadow-sm' : 'app-btn-outline' ?>"><?= e($tab['label']) ?></a>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="grid gap-3 sm:grid-cols-2 xl:w-[31rem]">
                <a href="<?= app_url('modules/agri/reports/operational_summary.php') . ($baseExportQuery ? '?' . $baseExportQuery : '') ?>" target="_blank" class="rounded-[1.5rem] border border-slate-200 bg-white px-5 py-4 text-left shadow-sm transition hover:-translate-y-0.5 hover:border-emerald-300">
                    <div class="text-sm font-semibold text-slate-900">Operational summary</div>
                    <div class="mt-1 text-sm text-slate-500">Leadership-ready print view with the municipality totals and barangay breakdown.</div>
                </a>
                <a href="<?= app_url('modules/agri/reports/print.php') . ($baseExportQuery ? '?' . $baseExportQuery : '') ?>" target="_blank" class="rounded-[1.5rem] border border-slate-200 bg-white px-5 py-4 text-left shadow-sm transition hover:-translate-y-0.5 hover:border-emerald-300">
                    <div class="text-sm font-semibold text-slate-900">Print / PDF view</div>
                    <div class="mt-1 text-sm text-slate-500">Clean print layout that follows your selected columns automatically.</div>
                </a>
                <a href="<?= app_url('modules/agri/reports/export_excel.php') . ($baseExportQuery ? '?' . $baseExportQuery : '') ?>" class="rounded-[1.5rem] border border-slate-200 bg-white px-5 py-4 text-left shadow-sm transition hover:-translate-y-0.5 hover:border-emerald-300">
                    <div class="text-sm font-semibold text-slate-900">Excel export</div>
                    <div class="mt-1 text-sm text-slate-500">Spreadsheet-ready export using the same active filters and builder columns.</div>
                </a>
                <a href="<?= app_url('modules/agri/reports/export_csv.php') . ($baseExportQuery ? '?' . $baseExportQuery : '') ?>" class="rounded-[1.5rem] border border-slate-200 bg-white px-5 py-4 text-left shadow-sm transition hover:-translate-y-0.5 hover:border-emerald-300">
                    <div class="text-sm font-semibold text-slate-900">CSV export</div>
                    <div class="mt-1 text-sm text-slate-500">Lightweight export for uploads, cross-checking, or quick data sharing.</div>
                </a>
                <div class="sm:col-span-2 rounded-[1.5rem] border border-dashed border-emerald-300 bg-white/80 px-5 py-4 text-sm leading-6 text-slate-600">
                    <span class="font-semibold text-slate-900">Auto-ready:</span> <?= e($reportsExportNote) ?> Every export action above already uses the current barangay, status, profile, and column settings.
                </div>
            </div>
        </div>
    </div>

    <div class="mt-5 grid gap-5 2xl:grid-cols-[1.2fr_0.8fr] xl:grid-cols-1">
        <section class="rounded-[1.75rem] border border-slate-200 bg-slate-50 p-5">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <div class="text-sm text-slate-500">Step 1</div>
                    <h3 class="text-2xl font-black text-slate-900">Choose report scope</h3>
                    <p class="mt-1 text-sm text-slate-500">Set the barangay, status, profile, and detail level once. The preview, operational print, and exports below will follow this selection.</p>
                </div>
                <a href="<?= e(app_url('modules/agri/reports/index.php')) ?>" class="app-btn-outline">Reset filters</a>
            </div>
            <form method="GET" class="mt-4 grid gap-3 md:grid-cols-2">
                <label class="block">
                    <div class="mb-2 text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Barangay</div>
                    <select name="barangay_id" class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3">
                        <option value="">All barangays</option>
                        <?php foreach($barangays as $b): ?>
                            <option value="<?= $b['barangay_id'] ?>" <?= $barangay === (int)$b['barangay_id'] ? 'selected' : '' ?>><?= e($b['barangay_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="block">
                    <div class="mb-2 text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Qualification status</div>
                    <select name="qualification_status" class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3">
                        <option value="">All qualification statuses</option>
                        <?php foreach(['Highly Qualified','Qualified','For Validation','Needs Support','Not Qualified'] as $s): ?>
                            <option value="<?= e($s) ?>" <?= $status === $s ? 'selected' : '' ?>><?= e($s) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="block">
                    <div class="mb-2 text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Profile focus</div>
                    <select name="profile_filter" class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3">
                        <?php foreach($profileFilterOptions as $value => $label): ?>
                            <option value="<?= e($value) ?>" <?= $profileFilter === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="block">
                    <div class="mb-2 text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">View mode</div>
                    <select name="detail_mode" class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3">
                        <option value="detailed" <?= $detailMode === 'detailed' ? 'selected' : '' ?>>Detailed rows</option>
                        <option value="summary" <?= $detailMode === 'summary' ? 'selected' : '' ?>>Summary-first</option>
                    </select>
                </label>
                <?php foreach ($selectedColumns as $column): ?><input type="hidden" name="columns[]" value="<?= e($column) ?>"><?php endforeach; ?>
                <div class="md:col-span-2 flex flex-wrap gap-3 pt-1">
                    <button class="app-btn-primary">Apply filters</button>
                    <a href="<?= app_url('modules/agri/reports/print.php') . ($baseExportQuery ? '?' . $baseExportQuery : '') ?>" target="_blank" class="app-btn-outline">Quick print current view</a>
                </div>
            </form>
        </section>

        <section class="rounded-[1.75rem] border border-slate-200 bg-slate-50 p-5">
            <div class="text-sm text-slate-500">Step 2</div>
            <h3 class="text-2xl font-black text-slate-900">Builder status</h3>
            <p class="mt-1 text-sm text-slate-500">You do not need to guess what will appear in the exports. These are the columns currently active in the package.</p>
            <div class="mt-4 rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-600">
                <span class="font-semibold text-slate-900">Selected columns:</span> <?= count($selectedColumns) ?>
            </div>
            <div class="mt-4 flex max-h-[190px] flex-wrap gap-2 overflow-y-auto pr-1">
                <?php foreach ($selectedColumnLabels as $label): ?>
                    <span class="rounded-full border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700"><?= e($label) ?></span>
                <?php endforeach; ?>
            </div>
            <div class="mt-4 rounded-2xl border border-dashed border-slate-300 bg-white px-4 py-3 text-sm text-slate-500">
                Tip: keep only the columns you really need for the mayor, print pack, or validation team. Fewer columns usually print better.
            </div>
        </section>
    </div>

    <div class="mt-5 rounded-[1.75rem] border border-slate-200 bg-slate-50 p-5 report-builder-shell">
        <div class="flex flex-col gap-3 xl:flex-row xl:items-start xl:justify-between">
            <div>
                <div class="text-sm text-slate-500">Step 3</div>
                <h3 class="text-2xl font-black text-slate-900">Tune the report package</h3>
                <p class="mt-1 text-sm text-slate-500">Switch columns on or off below, then refresh the workspace once. The builder controls the preview table, print view, and export files together.</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-500">Preview rows: <span class="font-bold text-slate-900"><?= count($families) ?></span></div>
        </div>
        <form method="GET" class="mt-4">
            <input type="hidden" name="barangay_id" value="<?= $barangay ?: '' ?>">
            <input type="hidden" name="qualification_status" value="<?= e($status) ?>">
            <input type="hidden" name="profile_filter" value="<?= e($profileFilter) ?>">
            <input type="hidden" name="detail_mode" value="<?= e($detailMode) ?>">
            <div class="grid gap-3 md:grid-cols-2 2xl:grid-cols-5 xl:grid-cols-4">
                <?php foreach ($availableColumns as $key => $meta): ?>
                    <label class="flex items-center gap-3 rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 shadow-sm">
                        <input type="checkbox" name="columns[]" value="<?= e($key) ?>" <?= in_array($key, $selectedColumns, true) ? 'checked' : '' ?>>
                        <span><?= e($meta['label']) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
            <div class="mt-4 flex flex-wrap gap-3">
                <button class="app-btn-primary">Update preview and exports</button>
                <a href="<?= e(app_url('modules/agri/reports/operational_summary.php') . ($baseExportQuery ? '?' . $baseExportQuery : '')) ?>" target="_blank" class="app-btn-outline">Open operational view</a>
                <a href="<?= e(app_url('modules/agri/reports/print.php') . ($baseExportQuery ? '?' . $baseExportQuery : '')) ?>" target="_blank" class="app-btn-outline">Open print preview</a>
            </div>
        </form>
    </div>

    <div class="mt-5 grid gap-4 xl:grid-cols-4">
        <?php foreach ($kpis as $card): ?>
            <div class="rounded-[1.75rem] border border-slate-200 bg-slate-50 p-5">
                <div class="text-sm text-slate-500"><?= e($card['label']) ?></div>
                <div class="mt-3 text-4xl font-black tracking-tight text-emerald-900"><?= e((string)$card['value']) ?></div>
                <div class="mt-2 text-sm text-slate-500"><?= e($card['hint']) ?></div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="mt-4 grid gap-4 xl:grid-cols-4">
        <?php foreach ($focusCards as $card): ?>
            <div class="rounded-[1.75rem] border border-slate-200 bg-slate-50 p-5">
                <div class="text-sm text-slate-500"><?= e($card['label']) ?></div>
                <div class="mt-3 text-4xl font-black tracking-tight text-emerald-900"><?= e((string)$card['value']) ?></div>
                <div class="mt-2 text-sm text-slate-500"><?= e($card['hint']) ?></div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="mt-5 grid gap-5 2xl:grid-cols-2 xl:grid-cols-1">
        <section class="rounded-[1.75rem] border border-slate-200 bg-slate-50 p-5">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <div class="text-sm text-slate-500">Qualification overview</div>
                    <h3 class="text-xl font-black text-slate-900">Status mix</h3>
                    <p class="mt-1 text-sm text-slate-500">A quick visual of which status is dominating the current report scope.</p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-right">
                    <div class="text-xs uppercase tracking-[0.18em] text-slate-400">Filtered rows</div>
                    <div class="mt-1 text-2xl font-black text-emerald-900"><?= number_format($filteredRows) ?></div>
                </div>
            </div>
            <div class="mt-4 rounded-3xl border border-slate-200 bg-white p-4">
                <div class="h-[320px]"><canvas id="statusChart"></canvas></div>
            </div>
        </section>

        <section class="rounded-[1.75rem] border border-slate-200 bg-slate-50 p-5">
            <div>
                <div class="text-sm text-slate-500">Profile coverage</div>
                <h3 class="text-xl font-black text-slate-900">Farmer, PWD, senior, OFW, youth mix</h3>
                <p class="mt-1 text-sm text-slate-500">See how the current filtered scope is distributed across the main LGU support groups.</p>
            </div>
            <div class="mt-4 rounded-3xl border border-slate-200 bg-white p-4">
                <div class="h-[320px]"><canvas id="profileMixChart"></canvas></div>
            </div>
        </section>
    </div>

    <div class="mt-5 grid gap-5 2xl:grid-cols-2 xl:grid-cols-1">
        <section class="rounded-[1.75rem] border border-slate-200 bg-slate-50 p-5">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <div class="text-sm text-slate-500">Barangay comparison</div>
                    <h3 class="text-xl font-black text-slate-900">Households by barangay</h3>
                    <p class="mt-1 text-sm text-slate-500">Horizontal ranking makes it easier to compare which barangays are carrying the largest household loads.</p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-500">Top <?= count($topBarangays) ?> barangays</div>
            </div>
            <div class="mt-4 rounded-3xl border border-slate-200 bg-white p-4">
                <div class="h-[320px] overflow-y-auto pr-2">
                    <div class="min-h-[560px]"><canvas id="barangayHouseholdsChart"></canvas></div>
                </div>
            </div>
        </section>

        <section class="rounded-[1.75rem] border border-slate-200 bg-slate-50 p-5">
            <div>
                <div class="text-sm text-slate-500">Operational queue</div>
                <h3 class="text-xl font-black text-slate-900">Validation and support load</h3>
                <p class="mt-1 text-sm text-slate-500">Stacked bars show which barangays need review work now and which still need support follow-up.</p>
            </div>
            <div class="mt-4 rounded-3xl border border-slate-200 bg-white p-4">
                <div class="h-[320px] overflow-y-auto pr-2">
                    <div class="min-h-[560px]"><canvas id="barangayQueueChart"></canvas></div>
                </div>
            </div>
        </section>
    </div>

    <div class="mt-5 grid gap-5 2xl:grid-cols-[1.2fr_0.95fr] xl:grid-cols-1">
        <section class="rounded-[1.75rem] border border-slate-200 bg-slate-50 p-5">
            <div>
                <div class="text-sm text-slate-500">Import review</div>
                <h3 class="text-xl font-black text-slate-900">Households with the largest population</h3>
                <p class="mt-1 text-sm text-slate-500">Use this chart to spot households that may have been grouped incorrectly during import.</p>
            </div>
            <div class="mt-4 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                Review anything with <span class="font-bold">15+ people</span>.
            </div>
            <div class="mt-4 rounded-3xl border border-slate-200 bg-white p-4">
                <div class="h-[320px] overflow-x-auto">
                    <div class="min-w-[820px] h-full"><canvas id="largestHouseholdsChart"></canvas></div>
                </div>
            </div>
        </section>

        <section class="rounded-[1.75rem] border border-slate-200 bg-slate-50 p-5">
            <div>
                <div class="text-sm text-slate-500">Executive watchlist</div>
                <h3 class="text-xl font-black text-slate-900">Needs review</h3>
                <p class="mt-1 text-sm text-slate-500">These households crossed the review threshold and should be checked against the source workbook.</p>
            </div>
            <div class="mt-4 max-h-[320px] space-y-3 overflow-y-auto pr-2">
                <?php if ($oversizedHouseholds): ?>
                    <?php foreach ($oversizedHouseholds as $suspect): ?>
                        <a href="<?= e(app_url('modules/agri/households/view.php?id=' . (int)$suspect['household_id'])) ?>" class="flex items-center justify-between gap-3 rounded-2xl border border-amber-200 bg-white px-4 py-3 text-sm hover:border-amber-300">
                            <div class="min-w-0">
                                <div class="truncate font-semibold text-slate-900"><?= e($suspect['household_head_name']) ?></div>
                                <div class="text-xs text-slate-500"><?= e($suspect['barangay_name']) ?></div>
                            </div>
                            <span class="app-badge app-badge-amber whitespace-nowrap"><?= (int)$suspect['member_count'] ?> people</span>
                        </a>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">No oversized households found in the current data.</div>
                <?php endif; ?>
            </div>
        </section>
    </div>

    <div class="mt-5 overflow-hidden rounded-[1.75rem] border border-slate-200 bg-white">
        <div class="border-b border-slate-200 px-5 py-4">
            <div class="text-sm text-slate-500">Detailed report rows</div>
            <h3 class="text-2xl font-black text-slate-900">Household report table</h3>
            <p class="mt-1 text-sm text-slate-500">This table follows your builder settings, so the same selected columns can be printed or exported without rework.</p>
        </div>
        <div class="max-h-[55vh] overflow-auto">
            <table class="min-w-full text-sm">
                <thead class="sticky top-0 z-10 bg-slate-50">
                    <tr>
                        <?php foreach ($selectedColumns as $column): ?>
                            <th class="px-4 py-3 <?= (($availableColumns[$column]['align'] ?? 'left') === 'right') ? 'text-right' : 'text-left' ?> font-semibold text-slate-600"><?= e($availableColumns[$column]['label']) ?></th>
                        <?php endforeach; ?>
                        <th class="px-4 py-3 text-right font-semibold text-slate-600">Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach($families as $f): ?>
                    <tr class="border-t border-slate-200 align-top hover:bg-slate-50">
                        <?php foreach ($selectedColumns as $column): ?>
                            <td class="px-4 py-3 <?= (($availableColumns[$column]['align'] ?? 'left') === 'right') ? 'text-right' : 'text-left' ?> <?= $column === 'household_head_name' ? 'font-semibold text-slate-900' : 'text-slate-600' ?>">
                                <?php if ($column === 'qualification_status'): ?>
                                    <?= format_status_badge((string)($f[$column] ?? '')) ?>
                                <?php else: ?>
                                    <?= e(report_cell_display($column, $f)) ?>
                                <?php endif; ?>
                            </td>
                        <?php endforeach; ?>
                        <td class="px-4 py-3 text-right"><a href="<?= app_url('modules/agri/households/view.php?id=' . (int)$f['household_id']) ?>" class="app-btn-outline whitespace-nowrap">Open</a></td>
                    </tr>
                <?php endforeach; if(!$families): ?>
                    <tr><td colspan="<?= count($selectedColumns) + 1 ?>" class="px-4 py-8 text-center text-slate-500">No report data yet.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const reportPalette = {
    green: 'rgba(34, 197, 94, 0.85)',
    greenSoft: 'rgba(34, 197, 94, 0.18)',
    amber: 'rgba(245, 158, 11, 0.85)',
    amberSoft: 'rgba(245, 158, 11, 0.18)',
    slate: 'rgba(100, 116, 139, 0.85)',
    slateSoft: 'rgba(100, 116, 139, 0.18)',
    blue: 'rgba(59, 130, 246, 0.85)',
    blueSoft: 'rgba(59, 130, 246, 0.18)',
    red: 'rgba(239, 68, 68, 0.85)',
    redSoft: 'rgba(239, 68, 68, 0.18)',
    purple: 'rgba(139, 92, 246, 0.85)',
    yellow: 'rgba(234, 179, 8, 0.85)'
};

const chartLinks = {
    barangayBase: <?= json_encode(app_url('modules/agri/households/index.php')) ?>,
    householdBase: <?= json_encode(app_url('modules/agri/households/view.php?id=')) ?>
};

const commonOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { labels: { boxWidth: 14, usePointStyle: true, pointStyle: 'circle' } }
    },
    scales: {
        x: { grid: { color: 'rgba(148,163,184,0.18)' }, ticks: { color: '#475569' } },
        y: { grid: { color: 'rgba(148,163,184,0.18)' }, ticks: { color: '#475569' } }
    }
};

new Chart(document.getElementById('statusChart'), {
    type: 'doughnut',
    data: {
        labels: <?= json_encode($statusLabels) ?>,
        datasets: [{
            data: <?= json_encode($statusData) ?>,
            backgroundColor: [reportPalette.green, reportPalette.amber, reportPalette.blue, reportPalette.slate, reportPalette.red, reportPalette.purple],
            borderWidth: 0,
            hoverOffset: 6
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { position: 'bottom', labels: { boxWidth: 14, usePointStyle: true, pointStyle: 'circle' } }
        }
    }
});

new Chart(document.getElementById('profileMixChart'), {
    type: 'polarArea',
    data: {
        labels: <?= json_encode($profileMixLabels) ?>,
        datasets: [{
            data: <?= json_encode($profileMixData) ?>,
            backgroundColor: [reportPalette.greenSoft, reportPalette.blueSoft, reportPalette.amberSoft, 'rgba(139,92,246,0.18)', 'rgba(234,179,8,0.18)'],
            borderColor: [reportPalette.green, reportPalette.blue, reportPalette.amber, reportPalette.purple, reportPalette.yellow],
            borderWidth: 1.5
        }]
    },
    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } }, scales: { r: { ticks: { backdropColor: 'transparent' } } } }
});

new Chart(document.getElementById('largestHouseholdsChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($largestHouseholdLabels) ?>,
        datasets: [{
            label: 'Population',
            data: <?= json_encode($largestHouseholdData) ?>,
            backgroundColor: <?= json_encode(array_map(static fn($n) => $n >= 15 ? 'rgba(245, 158, 11, 0.85)' : 'rgba(59, 130, 246, 0.75)', $largestHouseholdData)) ?>,
            borderColor: <?= json_encode(array_map(static fn($n) => $n >= 15 ? 'rgba(217, 119, 6, 1)' : 'rgba(37, 99, 235, 1)', $largestHouseholdData)) ?>,
            borderWidth: 1,
            borderRadius: 10
        }]
    },
    options: {
        ...commonOptions,
        indexAxis: 'y',
        plugins: { legend: { display: false } },
        onClick: (evt, elements, chart) => {
            if (!elements.length) return;
            const idx = elements[0].index;
            const householdIds = <?= json_encode(array_map(static fn($r) => (int)$r['household_id'], $largestHouseholds)) ?>;
            if (householdIds[idx]) window.location = chartLinks.householdBase + householdIds[idx];
        },
        scales: {
            x: { beginAtZero: true, grid: { color: 'rgba(148,163,184,0.18)' }, ticks: { color: '#475569' } },
            y: { grid: { display: false }, ticks: { color: '#475569' } }
        }
    }
});

new Chart(document.getElementById('barangayHouseholdsChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($barangayLabels) ?>,
        datasets: [{
            label: 'Households',
            data: <?= json_encode($barangayHouseholds) ?>,
            backgroundColor: reportPalette.greenSoft,
            borderColor: reportPalette.green,
            borderWidth: 1.5,
            borderRadius: 10
        }]
    },
    options: {
        ...commonOptions,
        indexAxis: 'y',
        plugins: { legend: { display: false } },
        onClick: (evt, elements) => {
            if (!elements.length) return;
            const barangayIds = <?= json_encode(array_map(static fn($r) => (int)($r['barangay_id'] ?? 0), $topBarangays)) ?>;
            if (barangayIds[elements[0].index]) window.location = chartLinks.barangayBase + '?barangay_id=' + barangayIds[elements[0].index];
        }
    }
});

new Chart(document.getElementById('barangayQueueChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($barangayLabels) ?>,
        datasets: [
            { label: 'For validation', data: <?= json_encode($barangayValidation) ?>, backgroundColor: reportPalette.amber, borderRadius: 8 },
            { label: 'Needs support', data: <?= json_encode($barangaySupport) ?>, backgroundColor: reportPalette.red, borderRadius: 8 },
            { label: 'Average score', data: <?= json_encode($barangayAvgScore) ?>, type: 'line', borderColor: reportPalette.blue, backgroundColor: reportPalette.blueSoft, yAxisID: 'y1', tension: 0.25, pointRadius: 3 }
        ]
    },
    options: {
        ...commonOptions,
        indexAxis: 'y',
        scales: {
            x: { stacked: true, beginAtZero: true, grid: { color: 'rgba(148,163,184,0.18)' }, ticks: { color: '#475569' } },
            y: { stacked: true, grid: { display: false }, ticks: { color: '#475569' } },
            y1: { display: false, min: 0, max: 100 }
        }
    }
});
</script>
<?php app_require('app/includes/footer.php'); ?>
