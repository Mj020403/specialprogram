<?php
require_once dirname(__DIR__, 4) . '/app/bootstrap.php';

$conn = db_conn();app_require('app/includes/auth.php'); require_role(['mayor','developer','admin']);
ensure_golden_household_schema($conn);
app_require('app/includes/header.php');
$summary = fetch_one($conn, "SELECT * FROM v_mayor_dashboard_summary") ?: [];
$action = executive_action_summary($conn);
$highRiskCount = (int)scalar($conn, "SELECT COUNT(*) FROM household_qualification WHERE qualification_status='High Risk'", 0);

echo nav_cards([
 ['label'=>'Total Households','value'=>total_household_groups($conn),'hint'=>'Grouped households by barangay + HH base'],
 ['label'=>'Total People in Matag-ob','value'=>(int)scalar($conn, "SELECT COUNT(*) FROM family_members WHERE is_active=1", 0),'hint'=>'Total encoded residents in the municipality'],
 ['label'=>'Total Members','value'=>(int)scalar($conn, "SELECT COUNT(*) FROM family_members WHERE is_active=1", 0),'hint'=>'All active people encoded in the system'],
 ['label'=>'Golden HH candidates','value'=>count(array_filter(golden_household_candidates($conn, 50), fn($row) => !empty($row['golden_eligible']))),'hint'=>'Checklist-based shortlist'],
 ['label'=>'High Risk / Needs Support','value'=>$highRiskCount + (int)($summary['needs_support_count'] ?? 0),'hint'=>'Mayor help list'],
]);

$alerts = fetch_all_assoc($conn, "SELECT title, message, severity, created_at FROM notifications ORDER BY notification_id DESC LIMIT 6");
$attention = fetch_all_assoc($conn, "SELECT h.household_id,h.household_head_name,h.household_code,h.household_size,b.barangay_name,q.qualification_status,q.score,q.explanation FROM households h JOIN barangays b ON b.barangay_id=h.barangay_id LEFT JOIN household_qualification q ON q.household_id=h.household_id WHERE q.qualification_status IN ('High Risk','Needs Support','Not Qualified','For Validation') OR q.qualification_status IS NULL ORDER BY COALESCE(q.score,0) ASC, h.household_id DESC LIMIT 8");
$bestBarangays = fetch_all_assoc($conn, "SELECT b.barangay_id, b.barangay_name, COUNT(DISTINCT h.household_id) total_households,
SUM(CASE WHEN COALESCE(sp.approved_programs,0) >= 2 AND COALESCE(ev.events_attended,0) >= 3 AND COALESCE(v.open_violations,0) = 0 AND COALESCE(v.total_violations,0) <= 1 THEN 1 ELSE 0 END) golden_total
FROM barangays b
LEFT JOIN households h ON h.barangay_id=b.barangay_id AND COALESCE(h.record_status,'active') <> 'deleted'
LEFT JOIN (SELECT household_id, COUNT(*) approved_programs FROM household_special_programs WHERE application_status IN ('Approved','Active','Completed','Pending Release') GROUP BY household_id) sp ON sp.household_id=h.household_id
LEFT JOIN (SELECT household_id, COUNT(DISTINCT event_id) events_attended FROM event_attendance WHERE attendance_status IN ('Present','Late') GROUP BY household_id) ev ON ev.household_id=h.household_id
LEFT JOIN (SELECT household_id, COUNT(*) total_violations, SUM(CASE WHEN violation_status='Open' THEN 1 ELSE 0 END) open_violations FROM household_violations GROUP BY household_id) v ON v.household_id=h.household_id
GROUP BY b.barangay_id, b.barangay_name HAVING COUNT(DISTINCT h.household_id) > 0 ORDER BY golden_total DESC, total_households DESC LIMIT 5");
$weakBarangays = fetch_all_assoc($conn, "SELECT b.barangay_id, b.barangay_name, COUNT(DISTINCT h.household_id) total_households,
SUM(CASE WHEN COALESCE(v.open_violations,0) > 0 THEN 1 ELSE 0 END) attention_total
FROM barangays b
LEFT JOIN households h ON h.barangay_id=b.barangay_id AND COALESCE(h.record_status,'active') <> 'deleted'
LEFT JOIN (SELECT household_id, SUM(CASE WHEN violation_status='Open' THEN 1 ELSE 0 END) open_violations FROM household_violations GROUP BY household_id) v ON v.household_id=h.household_id
GROUP BY b.barangay_id, b.barangay_name HAVING COUNT(DISTINCT h.household_id) > 0 ORDER BY attention_total DESC, total_households DESC LIMIT 5");
$goldenCandidates = golden_household_candidates($conn, 6);
$groupExpr = household_sql_group_key_expr('h', 'fhead');
$familyExpr = household_sql_family_key_expr('h', 'fhead');
$barangayInsights = fetch_all_assoc($conn, "
    SELECT
        b.barangay_id,
        b.barangay_name,
        COUNT(DISTINCT " . $familyExpr . ") AS total_families,
        COUNT(DISTINCT fm.member_id) AS total_members,
        COUNT(DISTINCT CASE WHEN fm.member_id IS NOT NULL AND UPPER(TRIM(COALESCE(fm.sex,'')))='MALE' THEN fm.member_id END) AS total_male,
        COUNT(DISTINCT CASE WHEN fm.member_id IS NOT NULL AND UPPER(TRIM(COALESCE(fm.sex,'')))='FEMALE' THEN fm.member_id END) AS total_female,
        COUNT(DISTINCT CASE WHEN fm.member_id IS NOT NULL AND (
            UPPER(TRIM(COALESCE(fm.occupation,'')))='FARMER'
            OR COALESCE(fm.member_tags,'') LIKE '%Farmer%'
        ) THEN fm.member_id END) AS total_farmers,
        COUNT(DISTINCT CASE WHEN fm.member_id IS NOT NULL AND (COALESCE(fm.disability,'') <> '' OR COALESCE(fm.member_tags,'') LIKE '%PWD%') THEN fm.member_id END) AS total_pwd,
        COUNT(DISTINCT CASE WHEN COALESCE(q.qualification_status,'For Validation') IN ('High Risk','Needs Support','For Validation') THEN " . $groupExpr . " END) AS support_queue
    FROM barangays b
    LEFT JOIN households h ON h.barangay_id = b.barangay_id AND COALESCE(h.record_status,'active') <> 'deleted'
    LEFT JOIN family_members fhead ON fhead.household_id = h.household_id AND fhead.is_household_head = 1
    LEFT JOIN family_members fm ON fm.household_id = h.household_id AND fm.is_active = 1
    LEFT JOIN household_qualification q ON q.household_id = h.household_id
    GROUP BY b.barangay_id, b.barangay_name
    ORDER BY b.barangay_name ASC
");
$runtimeBarangayGrouping = household_family_runtime_map($conn)['barangays'];
foreach ($barangayInsights as &$row) {
    $barangayId = (int)($row['barangay_id'] ?? 0);
    if (isset($runtimeBarangayGrouping[$barangayId])) {
        $row['total_households'] = $runtimeBarangayGrouping[$barangayId]['total_households'];
        $row['total_families'] = $runtimeBarangayGrouping[$barangayId]['total_families'];
    } else {
        $row['total_households'] = 0;
    }
}
unset($row);
foreach ($bestBarangays as &$row) {
    $barangayId = (int)($row['barangay_id'] ?? 0);
    if (isset($runtimeBarangayGrouping[$barangayId])) $row['total_households'] = $runtimeBarangayGrouping[$barangayId]['total_households'];
}
unset($row);
$attAll = fetch_all_assoc($conn, "
    SELECT b.barangay_name, COUNT(a.attendance_id) AS total
    FROM barangays b
    LEFT JOIN households h ON h.barangay_id = b.barangay_id
    LEFT JOIN event_attendance a ON a.household_id = h.household_id AND a.attendance_status IN ('Present','Late')
    GROUP BY b.barangay_id, b.barangay_name
    ORDER BY b.barangay_name ASC
");

$barangayLabels=[];$householdData=[];$familyData=[];$memberData=[];$maleData=[];$femaleData=[];$farmerData=[];$pwdData=[];$queueData=[];$pwdBarangays=[];
foreach($barangayInsights as $r){
    $barangayLabels[] = $r['barangay_name'];
    $householdData[] = (int)($r['total_households'] ?? 0);
    $familyData[] = (int)$r['total_families'];
    $memberData[] = (int)$r['total_members'];
    $maleData[] = (int)$r['total_male'];
    $femaleData[] = (int)$r['total_female'];
    $farmerData[] = (int)$r['total_farmers'];
    $queueData[] = (int)($r['support_queue'] ?? $r['needs_help'] ?? 0);
    $pwdData[] = (int)$r['total_pwd'];
    if ((int)$r['total_pwd'] > 0) { $pwdBarangays[$r['barangay_name']] = (int)$r['total_pwd']; }
}
$attLabels=[];$attData=[]; foreach($attAll as $r){$attLabels[]=$r['barangay_name'];$attData[]=(int)$r['total'];}
$topFarmerBarangay = '';
$topFarmerCount = 0;
foreach ($barangayInsights as $r) {
    if ((int)$r['total_farmers'] > $topFarmerCount) {
        $topFarmerCount = (int)$r['total_farmers'];
        $topFarmerBarangay = $r['barangay_name'];
    }
}
$farmerSummaryCards = [
    ['label' => 'Total households', 'value' => array_sum($householdData), 'hint' => 'Household records grouped by barangay'],
    ['label' => 'Total members', 'value' => array_sum($memberData), 'hint' => 'All active people in family records'],
    ['label' => 'Total male', 'value' => array_sum($maleData), 'hint' => 'Active male members'],
    ['label' => 'Total female', 'value' => array_sum($femaleData), 'hint' => 'Active female members'],
];
arsort($pwdBarangays);
$pwdFocus = array_slice($pwdBarangays, 0, 12, true);
$mayorTitle = mayor_dashboard_title($conn);
$mayorDescription = mayor_dashboard_description($conn);
?>
<section class="rounded-[2rem] border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-6 shadow-sm mb-6">
<div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
  <div class="max-w-3xl">
    <div class="text-sm text-slate-500">Mayor workspace</div>
    <h1 class="text-3xl font-black text-slate-900 dark:text-white"><?= e($mayorTitle) ?></h1>
    <p class="mt-2 text-sm text-slate-500"><?= e($mayorDescription) ?></p>
  </div>
  <div class="app-action-grid w-full xl:max-w-4xl">
    <a href="<?= e(app_url('modules/agri/reports/index.php')) ?>" class="app-action-card rounded-[1.5rem] border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900 p-5"><div class="text-sm text-slate-500">Reports</div><div class="mt-2 text-xl font-black">Open reports builder</div><div class="mt-2 text-sm text-slate-500">Filter barangays, choose columns, and export print-ready summaries.</div></a>
    <a href="<?= e(app_url('modules/agri/action_center.php')) ?>" class="app-action-card rounded-[1.5rem] border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900 p-5"><div class="text-sm text-slate-500">Action queue</div><div class="mt-2 text-xl font-black">Review households needing help</div><div class="mt-2 text-sm text-slate-500">See high-risk, validation, and support cases in one place.</div></a>
    <a href="<?= e(app_url('modules/agri/households/index.php')) ?>" class="app-action-card rounded-[1.5rem] border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900 p-5"><div class="text-sm text-slate-500">Households</div><div class="mt-2 text-xl font-black">Open household records</div><div class="mt-2 text-sm text-slate-500">See actual household data, QR cards, family members, and program records.</div></a>
  </div>
</div>
</section>
<div class="grid gap-6 xl:grid-cols-[1.15fr_0.85fr]">
<section class="rounded-[2rem] border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-6 shadow-sm mayor-dashboard-hero">
<div class="flex items-center justify-between gap-3 flex-wrap"><div><div class="text-sm text-slate-500 dark:text-slate-400">Golden Household shortlist</div><h2 class="text-2xl font-black text-slate-900 dark:text-white">Households ready for Himorasak recognition</h2><p class="mt-1 text-sm text-slate-500">Checklist-based view using programs, events, and household rule compliance.</p></div><a href="<?= e(app_url('modules/agri/programs/index.php')) ?>" class="app-btn-outline">Open Golden HH center</a></div>
<div class="mt-5"><div class="app-family-grid"><?php foreach($goldenCandidates as $row): ?><article class="app-family-card"><div class="app-family-card-media"><img src="<?= e(household_profile_photo($conn, (int)$row['household_id'])) ?>" alt="Family photo" class="app-family-card-photo"></div><div class="app-family-card-body"><div class="app-family-card-topline"><span class="app-family-card-brgy"><?= e($row['barangay_name'] ?? 'Barangay') ?></span></div><h3 class="app-family-card-title"><?= e($row['household_head_name']) ?></h3><div class="app-family-card-meta"><span><?= e($row['household_code']) ?></span><span><?= (int)$row['household_size'] ?> members</span></div><div class="mt-3"><?= format_status_badge($row['golden_status']) ?></div><div class="mt-3 text-xs text-slate-500">Programs <?= (int)$row['approved_programs'] ?> · Events <?= (int)$row['events_attended'] ?> · Open violations <?= (int)$row['open_violations'] ?></div><div class="app-family-card-actions"><a href="<?= e(app_url('modules/agri/households/view.php?id=' . (int)$row['household_id'] . '#golden-household')) ?>" class="app-btn-outline">Open family</a><a href="<?= e(app_url('modules/agri/qr/print_household.php?household_id=' . (int)$row['household_id'])) ?>" target="_blank" class="app-btn-outline">QR card</a></div></div></article><?php endforeach; if(!$goldenCandidates): ?><div class="text-sm text-slate-500">No Golden Household candidates yet.</div><?php endif; ?></div></div>
</section>
<section class="space-y-4">
<div class="rounded-[2rem] border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-6 shadow-sm"><div class="flex items-center justify-between gap-3"><div><div class="text-sm text-slate-500 dark:text-slate-400">Decision support</div><div class="mt-1 text-sm text-slate-500">Current queues and situations that need follow-through from the LGU team.</div></div><a href="<?= e(app_url('modules/agri/action_center.php')) ?>" class="app-btn-outline">Open action center</a></div><div class="mt-4 grid gap-3 sm:grid-cols-2"><div class="rounded-2xl border border-slate-200 dark:border-slate-800 p-4"><div class="text-xs text-slate-500">Pending orientation</div><div class="mt-2 text-2xl font-black"><?= (int)safe_table_count($conn, 'household_special_programs', "application_status='Pending Orientation'") ?></div></div><div class="rounded-2xl border border-slate-200 dark:border-slate-800 p-4"><div class="text-xs text-slate-500">Pending validation</div><div class="mt-2 text-2xl font-black"><?= (int)safe_table_count($conn, 'household_special_programs', "application_status='Pending Validation'") ?></div></div><div class="rounded-2xl border border-slate-200 dark:border-slate-800 p-4"><div class="text-xs text-slate-500">Open violations</div><div class="mt-2 text-2xl font-black"><?= (int)safe_table_count($conn, 'household_violations', "violation_status='Open'") ?></div></div><div class="rounded-2xl border border-slate-200 dark:border-slate-800 p-4"><div class="text-xs text-slate-500">High-risk families</div><div class="mt-2 text-2xl font-black"><?= count($action['high_risk'] ?? []) ?></div></div></div></div>
<div class="rounded-[2rem] border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-6 shadow-sm"><div class="text-sm text-slate-500 dark:text-slate-400">Attention alerts</div><div class="mt-1 text-sm text-slate-500">Recent notifications and flagged system activity.</div><div class="mt-4 space-y-3"><?php foreach($alerts as $a): ?><div class="rounded-2xl border border-slate-200 dark:border-slate-800 p-4"><div class="flex items-center justify-between gap-3"><div class="font-semibold"><?= e($a['title']) ?></div><div><?= format_status_badge($a['severity']) ?></div></div><div class="mt-2 text-sm text-slate-600 dark:text-slate-300"><?= e($a['message']) ?></div></div><?php endforeach; if(!$alerts): ?><div class="text-sm text-slate-500">No alerts yet.</div><?php endif; ?></div></div>
</section>
</div>

<section class="rounded-[2rem] border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-6 shadow-sm mt-6">
    <div class="flex items-center justify-between gap-3 flex-wrap">
        <div>
            <div class="text-sm text-slate-500">Barangay insight hub</div>
            <h2 class="text-2xl font-black">Households, members, farmers, PWD, and queue</h2>
            <p class="mt-1 text-sm text-slate-500">Switch the view instantly to compare the actual household and people counts in every barangay.</p>
        </div>
        <div class="flex flex-wrap gap-2" id="mayorInsightToggles">
            <button type="button" data-view="households" class="rounded-2xl border border-slate-200 dark:border-slate-800 px-4 py-2 text-sm font-semibold bg-slate-900 text-white dark:bg-white dark:text-slate-900">Households</button>
            <button type="button" data-view="families" class="rounded-2xl border border-slate-200 dark:border-slate-800 px-4 py-2 text-sm font-semibold">Families</button>
            <button type="button" data-view="members" class="rounded-2xl border border-slate-200 dark:border-slate-800 px-4 py-2 text-sm font-semibold">Members</button>
            <button type="button" data-view="male" class="rounded-2xl border border-slate-200 dark:border-slate-800 px-4 py-2 text-sm font-semibold">Male</button>
            <button type="button" data-view="female" class="rounded-2xl border border-slate-200 dark:border-slate-800 px-4 py-2 text-sm font-semibold">Female</button>
            <button type="button" data-view="farmers" class="rounded-2xl border border-slate-200 dark:border-slate-800 px-4 py-2 text-sm font-semibold">Farmers</button>
            <button type="button" data-view="pwd" class="rounded-2xl border border-slate-200 dark:border-slate-800 px-4 py-2 text-sm font-semibold">PWD</button>
            <button type="button" data-view="queue" class="rounded-2xl border border-slate-200 dark:border-slate-800 px-4 py-2 text-sm font-semibold">Need help</button>
        </div>
    </div>
    <div class="mt-5 grid gap-3 md:grid-cols-4"><?php foreach($farmerSummaryCards as $card): ?><div class="rounded-3xl border border-slate-200 dark:border-slate-800 p-4"><div class="text-xs text-slate-500"><?= e($card['label']) ?></div><div class="mt-2 text-2xl font-black break-words"><?= e((string)$card['value']) ?></div><div class="mt-1 text-sm text-slate-500"><?= e($card['hint']) ?></div></div><?php endforeach; ?></div>
    <div class="mt-5"><canvas id="mayorBarangayInsightChart" height="130"></canvas></div>
</section>

<section class="rounded-[2rem] border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-5 shadow-sm mt-6">
    <div class="flex items-center justify-between gap-3 flex-wrap">
        <div>
            <div class="text-sm text-slate-500">Executive quick actions</div>
            <h2 class="text-2xl font-black">Open the areas used in presentations</h2>
        </div>
        <div class="text-sm text-slate-500">Faster navigation for the mayor view</div>
    </div>
    <div class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-4">
        <a href="<?= e(app_url('modules/agri/reports/index.php')) ?>" class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900 px-4 py-4 font-semibold">Open report builder</a>
        <a href="<?= e(app_url('modules/agri/action_center.php')) ?>" class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900 px-4 py-4 font-semibold">Open action center</a>
        <a href="<?= e(app_url('modules/agri/households/index.php')) ?>" class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900 px-4 py-4 font-semibold">Browse households</a>
        <a href="<?= e(app_url('modules/agri/programs/index.php')) ?>" class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900 px-4 py-4 font-semibold">Open programs</a>
    </div>
</section>

<div class="grid gap-6 xl:grid-cols-3 mt-6">
<section class="rounded-[2rem] border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-6 shadow-sm"><div class="text-sm text-slate-500">Households by barangay</div><div class="mt-4"><canvas id="householdChart" height="180"></canvas></div></section>
<section class="rounded-[2rem] border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-6 shadow-sm"><div class="text-sm text-slate-500">Farmers by barangay</div><div class="mt-4"><canvas id="farmerChart" height="180"></canvas></div></section>
<section class="rounded-[2rem] border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-6 shadow-sm"><div class="text-sm text-slate-500">PWD by barangay</div><div class="mt-4"><canvas id="pwdChart" height="180"></canvas></div></section>
<section class="rounded-[2rem] border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-6 shadow-sm"><div class="text-sm text-slate-500">Attendance by barangay</div><div class="mt-4"><canvas id="attendanceChart" height="180"></canvas></div></section>
</div>
<div class="grid gap-6 xl:grid-cols-3 mt-6">
<section class="rounded-[2rem] border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-6 shadow-sm xl:col-span-2"><div class="text-sm text-slate-500">PWD focus barangays</div><div class="mt-4 flex flex-wrap gap-2"><?php if($pwdFocus): foreach($pwdFocus as $name => $count): ?><span class="inline-flex rounded-full border border-slate-200 dark:border-slate-700 px-3 py-2 text-sm font-semibold"><?= e($name) ?> · <?= (int)$count ?></span><?php endforeach; else: ?><div class="text-sm text-slate-500">No PWD-tagged members found yet.</div><?php endif; ?></div></section>
<section class="rounded-[2rem] border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-6 shadow-sm"><div class="text-sm text-slate-500">Members by sex</div><div class="mt-4"><canvas id="sexChart" height="180"></canvas></div></section>
</div>
<div class="grid gap-6 xl:grid-cols-2 mt-6">
<section class="rounded-[2rem] border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-6 shadow-sm">
    <div class="text-sm text-slate-500">Barangay leadership board</div>
    <h2 class="text-2xl font-black">Top barangays</h2>
    <div class="mt-5 space-y-3"><?php foreach($bestBarangays as $row): ?><div class="rounded-3xl border border-slate-200 dark:border-slate-800 p-4 flex items-center justify-between gap-3"><div><div class="font-semibold"><?= e($row['barangay_name']) ?></div><div class="text-sm text-slate-500"><?= e((string)$row['total_households']) ?> households tracked</div></div><div class="text-right"><div class="text-xs text-slate-500">Golden HH count</div><div class="text-2xl font-black"><?= e((string)$row['golden_total']) ?></div></div></div><?php endforeach; if(!$bestBarangays): ?><div class="text-sm text-slate-500">No barangay ranking available yet.</div><?php endif; ?></div>
</section>
<section class="rounded-[2rem] border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-6 shadow-sm">
    <div class="text-sm text-slate-500">Executive watchlist</div>
    <h2 class="text-2xl font-black">Barangays needing help</h2>
    <div class="mt-5 space-y-3"><?php foreach($weakBarangays as $row): ?><div class="rounded-3xl border border-slate-200 dark:border-slate-800 p-4 flex items-center justify-between gap-3"><div><div class="font-semibold"><?= e($row['barangay_name']) ?></div><div class="text-sm text-slate-500"><?= e((string)$row['attention_total']) ?> households need rule follow-up</div></div><div class="text-right"><div class="text-xs text-slate-500">Open violation households</div><div class="text-2xl font-black"><?= e((string)$row['attention_total']) ?></div></div></div><?php endforeach; if(!$weakBarangays): ?><div class="text-sm text-slate-500">No barangay watchlist available yet.</div><?php endif; ?></div>
</section>
</div>
<section class="rounded-[2rem] border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-6 shadow-sm mt-6"><div class="flex items-center justify-between gap-3 flex-wrap"><div><div class="text-sm text-slate-500">Mayor attention queue</div><h2 class="text-2xl font-black text-slate-900 dark:text-white">Families needing help</h2></div><a href="<?= e(app_url('modules/agri/action_center.php')) ?>" class="app-btn-outline">Open action center</a></div><div class="mt-5 divide-y divide-slate-200 dark:divide-slate-800 overflow-hidden rounded-[1.75rem] border border-slate-200 dark:border-slate-800"><?php foreach($attention as $row): ?><div class="flex flex-col gap-4 p-4 md:flex-row md:items-center md:justify-between"><div class="min-w-0"><div class="flex items-center gap-2 flex-wrap"><div class="font-black"><?= e($row['household_head_name']) ?></div><?= format_status_badge($row['qualification_status'] ?? 'For Validation') ?></div><div class="text-sm text-slate-500 mt-1"><?= e($row['barangay_name']) ?> · <?= e($row['household_code']) ?> · <?= (int)$row['household_size'] ?> members</div><div class="text-sm text-slate-500 mt-2"><?= e($row['explanation'] ?: 'Needs executive review and follow-up.') ?></div></div><div class="flex flex-wrap gap-2"><a href="/harvest/modules/agri/households/view.php?id=<?= (int)$row['household_id'] ?>" class="rounded-xl border px-3 py-2 font-semibold">Open</a><a href="/harvest/modules/agri/qr/print_household.php?household_id=<?= (int)$row['household_id'] ?>" target="_blank" class="rounded-xl border px-3 py-2 font-semibold">QR</a></div></div><?php endforeach; if(!$attention): ?><div class="px-4 py-6 text-center text-slate-500">No households need attention right now.</div><?php endif; ?></div></section>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const mayorInsightLabels = <?= json_encode($barangayLabels) ?>;
const mayorInsightViews = {
  households: {label:'Households', data: <?= json_encode($householdData) ?>},
  families: {label:'Families', data: <?= json_encode($familyData) ?>},
  members: {label:'Members', data: <?= json_encode($memberData) ?>},
  male: {label:'Male', data: <?= json_encode($maleData) ?>},
  female: {label:'Female', data: <?= json_encode($femaleData) ?>},
  farmers: {label:'Farmers', data: <?= json_encode($farmerData) ?>},
  queue: {label:'Need help', data: <?= json_encode($queueData) ?>},
  pwd: {label:'PWD', data: <?= json_encode($pwdData) ?>}
};
function rankedView(viewKey){
  const raw = mayorInsightViews[viewKey] || mayorInsightViews.households;
  const pairs = mayorInsightLabels.map((label, i) => ({label, value: Number(raw.data[i] || 0)}));
  pairs.sort((a,b) => b.value - a.value || a.label.localeCompare(b.label));
  return { label: raw.label, labels: pairs.map(x => x.label), data: pairs.map(x => x.value) };
}
const initialMayorRank = rankedView('households');
const mayorChart = new Chart(document.getElementById('mayorBarangayInsightChart'), {
  type:'bar',
  data:{labels: initialMayorRank.labels, datasets:[{label: initialMayorRank.label, data: initialMayorRank.data}]},
  options:{responsive:true, plugins:{legend:{display:false}}, scales:{y:{beginAtZero:true}}}
});
document.querySelectorAll('#mayorInsightToggles [data-view]').forEach(btn => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('#mayorInsightToggles [data-view]').forEach(other => {
      other.classList.remove('bg-slate-900','text-white','dark:bg-white','dark:text-slate-900');
    });
    btn.classList.add('bg-slate-900','text-white','dark:bg-white','dark:text-slate-900');
    const view = mayorInsightViews[btn.dataset.view];
    mayorChart.data.datasets[0].label = view.label;
    mayorChart.data.datasets[0].data = view.data;
    mayorChart.update();
  });
});
new Chart(document.getElementById('householdChart'), {type:'bar',data:{labels:<?= json_encode($barangayLabels) ?>,datasets:[{label:'Households',data:<?= json_encode($householdData) ?>}]},options:{plugins:{legend:{display:false}},responsive:true,scales:{y:{beginAtZero:true}}}});
new Chart(document.getElementById('farmerChart'), {type:'bar',data:{labels:<?= json_encode($barangayLabels) ?>,datasets:[{label:'Farmers',data:<?= json_encode($farmerData) ?>}]},options:{plugins:{legend:{display:false}},responsive:true,scales:{y:{beginAtZero:true}}}});
new Chart(document.getElementById('pwdChart'), {type:'bar',data:{labels:<?= json_encode($barangayLabels) ?>,datasets:[{label:'PWD',data:<?= json_encode($pwdData) ?>}]},options:{plugins:{legend:{display:false}},responsive:true,scales:{y:{beginAtZero:true}}}});
new Chart(document.getElementById('attendanceChart'), {type:'bar',data:{labels:<?= json_encode($attLabels) ?>,datasets:[{label:'Attendance',data:<?= json_encode($attData) ?>}]},options:{plugins:{legend:{display:false}},responsive:true,scales:{y:{beginAtZero:true}}}});
new Chart(document.getElementById('sexChart'), {type:'bar',data:{labels:<?= json_encode($barangayLabels) ?>,datasets:[{label:'Male',data:<?= json_encode($maleData) ?>},{label:'Female',data:<?= json_encode($femaleData) ?>}]},options:{responsive:true,scales:{y:{beginAtZero:true}}}});
</script>
<?= app_dashboard_insights_panel($conn, 'Mayor rules and database summary', 'Live charts that show the current household, program, rule, and municipal situation for executive review.') ?>
<?php app_require('app/includes/footer.php'); ?>
