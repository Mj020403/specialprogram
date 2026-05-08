<?php
require_once dirname(__DIR__, 3) . '/app/bootstrap.php';

$conn = db_conn();
app_require('app/includes/auth.php');
require_role(['task_force','admin','mayor','developer']);
app_require('app/includes/app_helpers.php');

$reportTitle = function_exists('operational_report_title') ? operational_report_title($conn) : (system_title($conn) . ' Operational Dashboard Report');
$reportSubtitle = system_subtitle($conn);
$generatedAt = date('F j, Y g:i A');
$user = current_user();
$filter = trim((string)($_GET['filter'] ?? 'all'));
$allowedFilters = [
    'all' => 'Full view',
    'households' => 'Households',
    'families' => 'Families',
    'population' => 'Population',
    'farmers' => 'Farmers',
    'pwd' => 'PWD',
    'active_program' => 'Active program',
    'pending_program' => 'Pending program',
    'needs_action' => 'Needs action',
];
if (!isset($allowedFilters[$filter])) { $filter = 'all'; }
$groupExpr = household_sql_group_key_expr('h', 'fhead');
$familyExpr = household_sql_family_key_expr('h', 'fhead');
$needsActionCondition = "COALESCE(q.qualification_status,'For Validation') IN ('High Risk','Needs Support','For Validation')";
$activeProgramExists = "EXISTS (SELECT 1 FROM household_special_programs sp WHERE sp.household_id = h.household_id AND COALESCE(sp.application_status,'') IN ('Approved','Active','Pending Release'))";
$pendingProgramExists = "EXISTS (SELECT 1 FROM household_special_programs sp WHERE sp.household_id = h.household_id AND COALESCE(sp.application_status,'') IN ('Pending Validation','Pending First Validation','Pending Orientation','Pending Final Validation'))";

$summary = fetch_one($conn, "
    SELECT
        COUNT(DISTINCT " . $familyExpr . ") AS total_families,
        COUNT(DISTINCT CASE WHEN fm.member_id IS NOT NULL THEN fm.member_id END) AS total_population,
        COUNT(DISTINCT CASE WHEN fm.member_id IS NOT NULL AND (
            UPPER(TRIM(COALESCE(fm.occupation,'')))='FARMER'
            OR COALESCE(fm.member_tags,'') LIKE '%Farmer%'
            OR COALESCE(fm.is_primary_farmer,0)=1
        ) THEN fm.member_id END) AS total_farmers,
        COUNT(DISTINCT CASE WHEN fm.member_id IS NOT NULL AND (COALESCE(fm.disability,'') <> '' OR COALESCE(fm.member_tags,'') LIKE '%PWD%') THEN fm.member_id END) AS total_pwd,
        COUNT(DISTINCT CASE WHEN fm.member_id IS NOT NULL AND fm.sex='Male' THEN fm.member_id END) AS total_male,
        COUNT(DISTINCT CASE WHEN fm.member_id IS NOT NULL AND fm.sex='Female' THEN fm.member_id END) AS total_female,
        COUNT(DISTINCT CASE WHEN {$activeProgramExists} THEN " . $groupExpr . " END) AS active_program,
        COUNT(DISTINCT CASE WHEN {$pendingProgramExists} THEN " . $groupExpr . " END) AS pending_program,
        COUNT(DISTINCT CASE WHEN {$needsActionCondition} THEN " . $groupExpr . " END) AS needs_action
    FROM households h
    LEFT JOIN family_members fhead ON fhead.household_id = h.household_id AND fhead.is_household_head = 1
    LEFT JOIN family_members fm ON fm.household_id = h.household_id AND fm.is_active = 1
    LEFT JOIN household_qualification q ON q.household_id = h.household_id
    WHERE COALESCE(h.record_status,'active') <> 'deleted'
") ?: [];

$runtimeGrouping = household_family_runtime_map($conn);
$summary['total_families'] = count($runtimeGrouping['families']);
$summary['total_households'] = count($runtimeGrouping['households']);

$summaryCards = [
    ['label' => 'Households', 'value' => (int)($summary['total_households'] ?? 0), 'tone' => 'neutral'],
    ['label' => 'Families', 'value' => (int)($summary['total_families'] ?? 0), 'tone' => 'neutral'],
    ['label' => 'Population', 'value' => (int)($summary['total_population'] ?? 0), 'tone' => 'neutral'],
    ['label' => 'Farmers', 'value' => (int)($summary['total_farmers'] ?? 0), 'tone' => 'neutral'],
    ['label' => 'PWD', 'value' => (int)($summary['total_pwd'] ?? 0), 'tone' => 'neutral'],
    ['label' => 'Male', 'value' => (int)($summary['total_male'] ?? 0), 'tone' => 'neutral'],
    ['label' => 'Female', 'value' => (int)($summary['total_female'] ?? 0), 'tone' => 'neutral'],
    ['label' => 'Active Program', 'value' => (int)($summary['active_program'] ?? 0), 'tone' => 'good'],
    ['label' => 'Pending Program', 'value' => (int)($summary['pending_program'] ?? 0), 'tone' => 'warn'],
    ['label' => 'Needs Action', 'value' => (int)($summary['needs_action'] ?? 0), 'tone' => 'danger'],
];

$barangayRows = fetch_all_assoc($conn, "
    SELECT
        b.barangay_id,
        b.barangay_name,
        COUNT(DISTINCT " . $familyExpr . ") AS total_families,
        COUNT(DISTINCT CASE WHEN fm.member_id IS NOT NULL THEN fm.member_id END) AS total_population,
        COUNT(DISTINCT CASE WHEN fm.member_id IS NOT NULL AND (
            UPPER(TRIM(COALESCE(fm.occupation,'')))='FARMER'
            OR COALESCE(fm.member_tags,'') LIKE '%Farmer%'
            OR COALESCE(fm.is_primary_farmer,0)=1
        ) THEN fm.member_id END) AS total_farmers,
        COUNT(DISTINCT CASE WHEN fm.member_id IS NOT NULL AND (COALESCE(fm.disability,'') <> '' OR COALESCE(fm.member_tags,'') LIKE '%PWD%') THEN fm.member_id END) AS total_pwd,
        COUNT(DISTINCT CASE WHEN fm.member_id IS NOT NULL AND fm.sex='Male' THEN fm.member_id END) AS total_male,
        COUNT(DISTINCT CASE WHEN fm.member_id IS NOT NULL AND fm.sex='Female' THEN fm.member_id END) AS total_female,
        COUNT(DISTINCT CASE WHEN {$activeProgramExists} THEN " . $groupExpr . " END) AS active_program,
        COUNT(DISTINCT CASE WHEN {$pendingProgramExists} THEN " . $groupExpr . " END) AS pending_program,
        COUNT(DISTINCT CASE WHEN {$needsActionCondition} THEN " . $groupExpr . " END) AS needs_action
    FROM barangays b
    LEFT JOIN households h ON h.barangay_id = b.barangay_id AND COALESCE(h.record_status,'active') <> 'deleted'
    LEFT JOIN family_members fhead ON fhead.household_id = h.household_id AND fhead.is_household_head = 1
    LEFT JOIN family_members fm ON fm.household_id = h.household_id AND fm.is_active = 1
    LEFT JOIN household_qualification q ON q.household_id = h.household_id
    GROUP BY b.barangay_id, b.barangay_name
    ORDER BY b.barangay_name ASC
");
$runtimeBarangayGrouping = household_family_runtime_map($conn)['barangays'];
foreach ($barangayRows as &$row) {
    $barangayId = (int)($row['barangay_id'] ?? 0);
    if (isset($runtimeBarangayGrouping[$barangayId])) {
        $row['total_households'] = $runtimeBarangayGrouping[$barangayId]['total_households'];
        $row['total_families'] = $runtimeBarangayGrouping[$barangayId]['total_families'];
    } else {
        $row['total_households'] = 0;
    }
}
unset($row);

$filteredBarangayRows = array_values(array_filter($barangayRows, static function(array $row) use ($filter): bool {
    return match ($filter) {
        'households' => ((int)($row['total_households'] ?? 0)) > 0,
        'families' => ((int)($row['total_families'] ?? 0)) > 0,
        'population' => ((int)($row['total_population'] ?? 0)) > 0,
        'farmers' => ((int)($row['total_farmers'] ?? 0)) > 0,
        'pwd' => ((int)($row['total_pwd'] ?? 0)) > 0,
        'active_program' => ((int)($row['active_program'] ?? 0)) > 0,
        'pending_program' => ((int)($row['pending_program'] ?? 0)) > 0,
        'needs_action' => ((int)($row['needs_action'] ?? 0)) > 0,
        default => true,
    };
}));
$reportFilterLabel = $allowedFilters[$filter];
$operationalPageTitle = system_operational_page_title($conn);
$operationalPageDescription = system_operational_page_description($conn);
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($reportTitle) ?></title>
    <style>
        :root {
            --border: #d6d3d1;
            --text: #1c1917;
            --muted: #57534e;
            --accent: #215c38;
            --accent-soft: #f2f8f3;
            --soft: #fafaf9;
            --danger: #b42318;
            --danger-soft: #fff1f1;
            --warn: #b54708;
            --warn-soft: #fffaeb;
            --good: #027a48;
            --good-soft: #ecfdf3;
        }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Arial, Helvetica, sans-serif; color: var(--text); background: #f5f5f4; }
        .page { max-width: 1440px; margin: 24px auto; background: #fff; border: 1px solid var(--border); border-radius: 28px; padding: 28px; }
        .toolbar, .hero, .hero-actions, .chips, .section-head { display: flex; gap: 12px; flex-wrap: wrap; }
        .toolbar { justify-content: space-between; align-items: center; margin-bottom: 16px; }
        .btn { display:inline-flex; align-items:center; justify-content:center; gap:8px; text-decoration:none; border:1px solid var(--border); color:var(--text); padding:12px 16px; border-radius:16px; font-weight:700; background:#fff; }
        .btn-primary { background: var(--accent); color:#fff; border-color:var(--accent); }
        .hero { justify-content: space-between; align-items: flex-start; border:1px solid #e7e5e4; background:linear-gradient(135deg,#f8faf7,#ffffff); border-radius:24px; padding:22px; }
        .eyebrow { font-size:12px; font-weight:700; letter-spacing:.08em; text-transform:uppercase; color:var(--accent); }
        .hero h1 { margin:6px 0 0; font-size:34px; line-height:1.05; }
        .hero p { margin:10px 0 0; color:var(--muted); max-width:800px; line-height:1.55; }
        .hero-meta { min-width: 260px; border:1px solid #e7e5e4; background:#fff; border-radius:20px; padding:16px; color:var(--muted); font-size:13px; }
        .hero-actions { margin-top: 14px; }
        .chip { display:inline-flex; align-items:center; border:1px solid var(--border); border-radius:999px; padding:10px 14px; color:var(--text); text-decoration:none; font-size:12px; font-weight:700; background:#fff; }
        .chip.active { background: var(--accent); color:#fff; border-color: var(--accent); }
        .cards { display:grid; grid-template-columns:repeat(5,minmax(0,1fr)); gap:14px; margin-top:18px; }
        .card { border:1px solid var(--border); border-radius:20px; padding:16px; background: var(--soft); }
        .card.good { background: var(--good-soft); border-color: #a6f4c5; }
        .card.warn { background: var(--warn-soft); border-color: #fedf89; }
        .card.danger { background: var(--danger-soft); border-color: #fecdca; }
        .card-label { font-size:12px; color: var(--muted); text-transform:uppercase; letter-spacing:0.04em; }
        .card-value { margin-top:10px; font-size:34px; font-weight:800; }
        .section-head { justify-content:space-between; align-items:end; margin:30px 0 12px; }
        .section-title { margin:0; font-size:24px; }
        .section-copy { margin:6px 0 0; color: var(--muted); font-size: 14px; }
        .table-wrap { overflow-x:auto; border:1px solid #e7e5e4; border-radius:20px; }
        table { width:100%; border-collapse:collapse; min-width: 1100px; }
        th, td { border-bottom:1px solid #ece7e1; padding:12px 14px; font-size:13px; vertical-align:top; }
        th { background:#f8faf7; text-align:left; position:sticky; top:0; z-index:1; }
        td.num, th.num { text-align:right; }
        tbody tr:nth-child(even) td { background:#fcfcfb; }
        tbody tr:hover td { background:#f6faf7; }
        .notes { margin-top:14px; color: var(--muted); font-size: 12px; line-height:1.5; }
        @media (max-width: 1100px) { .cards { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
        @media (max-width: 760px) { .page { margin:0; border-radius:0; padding:18px; } .cards { grid-template-columns: repeat(2, minmax(0, 1fr)); } .hero { flex-direction: column; } }
        @media print {
            @page { size: landscape; margin: 9mm; }
            body { background:#fff; }
            .page { margin:0; border:0; border-radius:0; padding:0; max-width:none; }
            .toolbar, .hero-actions, .chips { display:none; }
            .hero { border:0; padding:0 0 12px; }
            .table-wrap { border:0; border-radius:0; }
            th, td { padding:7px 8px; font-size:10px; }
            .card { break-inside:avoid; }
        }
    </style>
</head>
<body>
<div class="page">
    <div class="toolbar">
        <a href="<?= app_url('modules/agri/reports/index.php') ?>" class="btn">Back to reports</a>
        <button type="button" onclick="window.print()" class="btn btn-primary">Print operational report</button>
    </div>

    <section class="hero">
        <div>
            <div class="eyebrow">Operational report</div>
            <h1><?= e($operationalPageTitle) ?></h1>
            <p><?= e($operationalPageDescription) ?> This version is cleaned up for leadership review, with only the numbers that matter most for fast decisions.</p>
            <div class="hero-actions chips">
                <?php foreach ($allowedFilters as $filterKey => $filterLabel): ?>
                    <a class="chip <?= $filter === $filterKey ? 'active' : '' ?>" href="?filter=<?= urlencode($filterKey) ?>"><?= e($filterLabel) ?></a>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="hero-meta">
            <div><strong>Generated:</strong> <?= e($generatedAt) ?></div>
            <div><strong>User:</strong> <?= e((string)($user['name'] ?? 'System')) ?></div>
            <div><strong>Role:</strong> <?= e(role_label((string)($user['role'] ?? 'task_force'))) ?></div>
            <div><strong>View:</strong> <?= e($reportFilterLabel) ?></div>
        </div>
    </section>

    <div class="cards">
        <?php foreach ($summaryCards as $card): ?>
            <div class="card <?= e($card['tone']) ?>">
                <div class="card-label"><?= e($card['label']) ?></div>
                <div class="card-value"><?= e((string)$card['value']) ?></div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="section-head">
        <div>
            <h2 class="section-title">Barangay breakdown</h2>
            <p class="section-copy">A clean row-per-barangay comparison using only the municipality summary columns you asked for.</p>
        </div>
    </div>

    <div class="table-wrap"><table>
        <thead>
            <tr>
                <th>Barangay</th>
                <th class="num">Households</th>
                <th class="num">Families</th>
                <th class="num">Population</th>
                <th class="num">Farmers</th>
                <th class="num">PWD</th>
                <th class="num">Male</th>
                <th class="num">Female</th>
                <th class="num">Active Program</th>
                <th class="num">Pending Program</th>
                <th class="num">Needs Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($filteredBarangayRows as $row): ?>
                <tr>
                    <td><?= e($row['barangay_name']) ?></td>
                    <td class="num"><?= (int)($row['total_households'] ?? 0) ?></td>
                    <td class="num"><?= (int)($row['total_families'] ?? 0) ?></td>
                    <td class="num"><?= (int)($row['total_population'] ?? 0) ?></td>
                    <td class="num"><?= (int)($row['total_farmers'] ?? 0) ?></td>
                    <td class="num"><?= (int)($row['total_pwd'] ?? 0) ?></td>
                    <td class="num"><?= (int)($row['total_male'] ?? 0) ?></td>
                    <td class="num"><?= (int)($row['total_female'] ?? 0) ?></td>
                    <td class="num"><?= (int)($row['active_program'] ?? 0) ?></td>
                    <td class="num"><?= (int)($row['pending_program'] ?? 0) ?></td>
                    <td class="num"><?= (int)($row['needs_action'] ?? 0) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table></div>

    <div class="notes">
        Population counts are based on active family-member records linked to current households. Active Program uses Approved or Active applications, Pending Program uses Pending Validation or Pending Orientation, and Needs Action follows the current qualification follow-up queue.
    </div>
</div>
</body>
</html>
