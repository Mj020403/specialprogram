<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
$conn = db_conn();
app_require('app/includes/auth.php');
app_require('app/includes/module_platform.php');
set_current_platform_module('beneficiaries');
require_role(['beneficiaries','beneficiary_staff','mayor','developer','admin']);
app_require('app/includes/header.php');
$cards = [
    ['label'=>'Total Households','value'=>total_household_groups($conn),'hint'=>'Grouped households in the shared municipal database'],
    ['label'=>'Total Families','value'=>total_family_units($conn),'hint'=>'Family units inside households'],
    ['label'=>'Total Members','value'=>scalar($conn,"SELECT COUNT(*) FROM family_members WHERE is_active=1"),'hint'=>'Active people records'],
    ['label'=>'Male','value'=>scalar($conn,"SELECT COUNT(*) FROM family_members WHERE is_active=1 AND sex='Male'"),'hint'=>'Male members'],
    ['label'=>'Female','value'=>scalar($conn,"SELECT COUNT(*) FROM family_members WHERE is_active=1 AND sex='Female'"),'hint'=>'Female members'],
    ['label'=>'PWD','value'=>scalar($conn,"SELECT COUNT(*) FROM family_members WHERE is_active=1 AND (COALESCE(disability,'') <> '' OR COALESCE(member_tags,'') LIKE '%PWD%')"),'hint'=>'PWD and disability-tagged members'],
    ['label'=>'Senior Citizens','value'=>scalar($conn,"SELECT COUNT(*) FROM family_members WHERE is_active=1 AND age >= 60"),'hint'=>'Age 60 and above'],
];
echo nav_cards($cards);
?>
<section class="app-action-panel">
    <div class="app-section-head">
        <div>
            <div class="app-section-kicker">Beneficiaries Workspace</div>
            <h2 class="app-section-title">People-focused dashboard</h2>
            <p class="app-section-subtitle">This module focuses on people and sectors only. Crop monitoring and interview operations are hidden here.</p>
        </div>
    </div>
    <div class="app-action-grid">
        <a href="<?= app_url('modules/agri/households/index.php') ?>" class="app-action-card tone-green"><span class="app-action-icon">👨‍👩‍👧</span><div class="app-action-title">Open households</div><div class="app-action-text">View family records and member lists.</div></a>
        <a href="<?= app_url('modules/agri/reports/index.php') ?>" class="app-action-card tone-blue"><span class="app-action-icon">📊</span><div class="app-action-title">Sector reports</div><div class="app-action-text">Generate demographic and beneficiary reports.</div></a>
        <a href="<?= app_url('modules/agri/assistance/index.php') ?>" class="app-action-card tone-amber"><span class="app-action-icon">🤝</span><div class="app-action-title">Assistance</div><div class="app-action-text">Track beneficiary-focused assistance and support.</div></a>
    </div>
</section>
<?= app_dashboard_insights_panel($conn, 'Beneficiary dashboard snapshot', 'Quick charts for population, programs, rules, and the overall beneficiary situation pulled from the live database.') ?>
<?php app_require('app/includes/footer.php'); ?>
