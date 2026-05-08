<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';

$conn = db_conn();
app_require('app/includes/auth.php');
require_role(['developer','admin']);
app_require('app/includes/header.php');

$totalUsers = safe_table_count($conn, 'users');
$activeUsers = safe_table_count($conn, 'users', 'is_active = 1');
$totalRoles = safe_table_count($conn, 'roles');
$totalDepartments = safe_table_count($conn, 'departments');
$workflowCount = safe_table_count($conn, 'workflow_templates');
$pendingProfiles = safe_table_count($conn, 'family_profile_requests', "status = 'pending'");
$securityQueue = safe_table_count($conn, 'user_activity_logs', "action = 'login_failed' AND created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)");
$importBatches = safe_table_count($conn, 'import_logs');
$missingAdminTables = [];
foreach (['departments','workflow_templates','document_categories','import_logs'] as $tbl) {
    if (!table_exists($conn, $tbl)) $missingAdminTables[] = $tbl;
}

echo nav_cards([
 ['label'=>'System Users','value'=>$totalUsers,'hint'=>'All user accounts registered in the platform'],
 ['label'=>'Active Users','value'=>$activeUsers,'hint'=>'Accounts currently marked active'],
 ['label'=>'Roles','value'=>$totalRoles,'hint'=>'Role definitions and permission groups'],
 ['label'=>'Departments','value'=>$totalDepartments,'hint'=>table_exists($conn, 'departments') ? 'Organizational units available in assignments' : 'Setup table missing'],
 ['label'=>'Workflow Sets','value'=>$workflowCount,'hint'=>table_exists($conn, 'workflow_templates') ? 'Configured workflow templates' : 'Setup table missing'],
 ['label'=>'Pending Approvals','value'=>$pendingProfiles,'hint'=>'Family portal requests waiting for review'],
 ['label'=>'Security Queue','value'=>$securityQueue,'hint'=>'Recent failed login attempts to review'],
 ['label'=>'Import Batches','value'=>$importBatches,'hint'=>table_exists($conn, 'import_logs') ? 'Logged import runs available for audit' : 'Setup table missing'],
]);
?>
<?php if ($missingAdminTables): ?>
<div class="rounded-[2rem] border border-amber-300 bg-amber-50 px-5 py-4 text-amber-900">
    <div class="font-black">Developer setup still needs a few admin tables</div>
    <div class="mt-1 text-sm">Missing: <?= e(implode(', ', $missingAdminTables)) ?>. Use <code>database/system_admin_upgrade_v28.sql</code> to enable all developer modules without editing code.</div>
</div>
<?php endif; ?>
<div class="grid gap-6 xl:grid-cols-3">
    <section class="rounded-[2rem] border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-6 shadow-sm xl:col-span-2">
        <div class="text-sm text-slate-500 dark:text-slate-400">Developer control center</div>
        <h1 class="mt-2 text-3xl font-black tracking-tight">Manage the system, not the household records</h1>
        <p class="mt-3 max-w-3xl text-sm text-slate-500 dark:text-slate-400">This workspace is focused on accounts, permissions, branding, security, workflows, import audit, and system settings. Household and family operations stay in the operational roles.</p>
        <div class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <a href="<?= e(app_url('modules/admin/users/index.php')) ?>" class="rounded-[1.5rem] border border-slate-200 dark:border-slate-800 p-5 hover:border-emerald-300 transition"><div class="font-black text-xl">Users</div><div class="mt-2 text-sm text-slate-500 dark:text-slate-400">Create, approve, activate, and organize system accounts.</div></a>
            <a href="<?= e(app_url('modules/admin/roles/index.php')) ?>" class="rounded-[1.5rem] border border-slate-200 dark:border-slate-800 p-5 hover:border-emerald-300 transition"><div class="font-black text-xl">Roles and permissions</div><div class="mt-2 text-sm text-slate-500 dark:text-slate-400">Shape access by role instead of editing pages one by one.</div></a>
            <a href="<?= e(app_url('modules/admin/settings/branding.php')) ?>" class="rounded-[1.5rem] border border-slate-200 dark:border-slate-800 p-5 hover:border-emerald-300 transition"><div class="font-black text-xl">Branding workspace</div><div class="mt-2 text-sm text-slate-500 dark:text-slate-400">Change logo, titles, login texts, report labels, and UI copy.</div></a>
            <a href="<?= e(app_url('modules/admin/settings/index.php')) ?>" class="rounded-[1.5rem] border border-slate-200 dark:border-slate-800 p-5 hover:border-emerald-300 transition"><div class="font-black text-xl">System settings</div><div class="mt-2 text-sm text-slate-500 dark:text-slate-400">Adjust portal controls, upload limits, and core behavior switches.</div></a>
            <a href="<?= e(app_url('modules/admin/security/index.php')) ?>" class="rounded-[1.5rem] border border-slate-200 dark:border-slate-800 p-5 hover:border-emerald-300 transition"><div class="font-black text-xl">Security desk</div><div class="mt-2 text-sm text-slate-500 dark:text-slate-400">Review failed logins, suspicious activity, and account safeguards.</div></a>
            <a href="<?= e(app_url('modules/admin/system_health/index.php')) ?>" class="rounded-[1.5rem] border border-slate-200 dark:border-slate-800 p-5 hover:border-emerald-300 transition"><div class="font-black text-xl">System health</div><div class="mt-2 text-sm text-slate-500 dark:text-slate-400">Check missing tables, module readiness, and setup status safely.</div></a>
        </div>
    </section>
    <section class="rounded-[2rem] border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-6 shadow-sm">
        <div class="text-sm text-slate-500 dark:text-slate-400">Quick admin path</div>
        <div class="mt-2 text-2xl font-black">Most used tools</div>
        <div class="mt-5 space-y-3">
            <a href="<?= e(app_url('modules/admin/profile_requests/index.php')) ?>" class="flex items-start justify-between gap-3 rounded-2xl border border-slate-200 dark:border-slate-800 p-4 hover:border-emerald-300 transition"><div><div class="font-bold">Profile approvals</div><div class="mt-1 text-sm text-slate-500 dark:text-slate-400">Approve submitted family profile changes.</div></div><div class="text-xl font-black"><?= (int)$pendingProfiles ?></div></a>
            <a href="<?= e(app_url('modules/admin/departments/index.php')) ?>" class="flex items-start justify-between gap-3 rounded-2xl border border-slate-200 dark:border-slate-800 p-4 hover:border-emerald-300 transition"><div><div class="font-bold">Departments</div><div class="mt-1 text-sm text-slate-500 dark:text-slate-400"><?= table_exists($conn, 'departments') ? 'Organize role assignments and system structure.' : 'Module ready, waiting for admin setup table.' ?></div></div><div class="text-xl font-black"><?= (int)$totalDepartments ?></div></a>
            <a href="<?= e(app_url('modules/admin/workflows/index.php')) ?>" class="flex items-start justify-between gap-3 rounded-2xl border border-slate-200 dark:border-slate-800 p-4 hover:border-emerald-300 transition"><div><div class="font-bold">Workflows</div><div class="mt-1 text-sm text-slate-500 dark:text-slate-400"><?= table_exists($conn, 'workflow_templates') ? 'Maintain steps and process templates.' : 'Module ready, waiting for workflow tables.' ?></div></div><div class="text-xl font-black"><?= (int)$workflowCount ?></div></a>
            <a href="<?= e(app_url('modules/agri/import/index.php')) ?>" class="flex items-start justify-between gap-3 rounded-2xl border border-slate-200 dark:border-slate-800 p-4 hover:border-emerald-300 transition"><div><div class="font-bold">Import center</div><div class="mt-1 text-sm text-slate-500 dark:text-slate-400">Run validation previews and monitor import history.</div></div><div class="text-xl font-black"><?= (int)$importBatches ?></div></a>
            <a href="<?= e(app_url('modules/agri/reports/index.php')) ?>" class="flex items-start justify-between gap-3 rounded-2xl border border-slate-200 dark:border-slate-800 p-4 hover:border-emerald-300 transition"><div><div class="font-bold">Reports</div><div class="mt-1 text-sm text-slate-500 dark:text-slate-400">Build exports and executive summaries.</div></div><div class="text-xl font-black">PDF</div></a>
        </div>
    </section>
</div>
<?= app_dashboard_insights_panel($conn, 'System and database snapshot', 'Live charts for users, queues, rules, and the current system situation across the database.') ?>
<?php app_require('app/includes/footer.php'); ?>
