<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
$conn = db_conn();
app_require('app/includes/auth.php');
app_require('app/includes/module_platform.php');
set_current_platform_module('cbms');
require_role(['cbms','cbms_staff','mayor','developer','admin']);
app_require('app/includes/header.php');
$cards = [
    ['label'=>'Total Households','value'=>total_household_groups($conn),'hint'=>'Grouped household records'],
    ['label'=>'Total Families','value'=>total_family_units($conn),'hint'=>'Family units inside households'],
    ['label'=>'Total Members','value'=>scalar($conn,"SELECT COUNT(*) FROM family_members WHERE is_active=1"),'hint'=>'Detailed family members'],
    ['label'=>'Households with Crops','value'=>scalar($conn,"SELECT COUNT(DISTINCT household_id) FROM crops"),'hint'=>'CBMS can view crop ownership'],
    ['label'=>'PWD','value'=>scalar($conn,"SELECT COUNT(*) FROM family_members WHERE is_active=1 AND (COALESCE(disability,'') <> '' OR COALESCE(member_tags,'') LIKE '%PWD%')"),'hint'=>'Persons with disability'],
    ['label'=>'Unemployed','value'=>scalar($conn,"SELECT COUNT(*) FROM family_members WHERE is_active=1 AND COALESCE(employment_status,'') IN ('Unemployed','Not Employed')"),'hint'=>'Employment monitoring'],
    ['label'=>'Low Income Tagged','value'=>scalar($conn,"SELECT COUNT(*) FROM family_members WHERE is_active=1 AND COALESCE(average_monthly_income,0) > 0 AND COALESCE(average_monthly_income,0) <= 5000"),'hint'=>'Members tagged with low monthly income'],
];
echo nav_cards($cards);
?>
<section class="app-action-panel">
    <div class="app-section-head">
        <div>
            <div class="app-section-kicker">CBMS Workspace</div>
            <h2 class="app-section-title">Detailed family and community view</h2>
            <p class="app-section-subtitle">Use this for deep household data, members, livelihoods, assets, animals, and community-level reporting.</p>
        </div>
    </div>
    <div class="app-action-grid">
        <a href="<?= app_url('modules/agri/households/index.php') ?>" class="app-action-card tone-green"><span class="app-action-icon">🏠</span><div class="app-action-title">Household records</div><div class="app-action-text">Open the complete family registry.</div></a>
        <a href="<?= app_url('modules/agri/family_reports/index.php') ?>" class="app-action-card tone-blue"><span class="app-action-icon">🗂️</span><div class="app-action-title">Detailed reports</div><div class="app-action-text">Use family and barangay views for CBMS reporting.</div></a>
        <a href="<?= app_url('modules/agri/reports/index.php') ?>" class="app-action-card tone-amber"><span class="app-action-icon">📈</span><div class="app-action-title">Municipal summary</div><div class="app-action-text">Generate rollup outputs for planning and profiling.</div></a>
    </div>
</section>
<?= app_dashboard_insights_panel($conn, 'CBMS dashboard snapshot', 'Quick charts for records, queues, rules, and the current municipal situation based on the shared database.') ?>
<?php app_require('app/includes/footer.php'); ?>
