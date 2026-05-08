<?php
require_once dirname(__DIR__, 3) . '/app/bootstrap.php';
$conn = db_conn();
app_require('app/includes/auth.php');
require_role(['developer','admin']);
app_require('app/includes/header.php');
$checks = [
    'users' => 'Core accounts',
    'roles' => 'Role and permission base',
    'households' => 'Operational households',
    'family_members' => 'Operational members',
    'departments' => 'Developer structure',
    'document_categories' => 'Workflow categories',
    'workflow_templates' => 'Workflow templates',
    'import_logs' => 'Import audit trail',
    'system_branding' => 'Branding settings',
];
?>
<div class="rounded-[2rem] border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-6 shadow-sm">
    <div class="text-sm text-slate-500 dark:text-slate-400">Developer diagnostics</div>
    <h1 class="mt-2 text-3xl font-black">System health</h1>
    <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">This page checks the tables your upgraded system expects, so missing setup does not surprise you during a demo.</p>
    <div class="mt-6 overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead><tr><th class="px-3 py-3 text-left">Table</th><th class="px-3 py-3 text-left">Purpose</th><th class="px-3 py-3 text-left">Status</th></tr></thead>
            <tbody>
            <?php foreach ($checks as $table => $purpose): ?>
                <tr class="border-t border-slate-100 dark:border-slate-800">
                    <td class="px-3 py-3 font-semibold"><?= e($table) ?></td>
                    <td class="px-3 py-3"><?= e($purpose) ?></td>
                    <td class="px-3 py-3"><?= table_exists($conn, $table) ? '<span class="inline-flex rounded-full bg-emerald-100 px-3 py-1 text-xs font-bold text-emerald-700">Ready</span>' : '<span class="inline-flex rounded-full bg-amber-100 px-3 py-1 text-xs font-bold text-amber-800">Missing</span>' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="mt-5 rounded-2xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">If any developer table is missing, run <code>database/system_admin_upgrade_v28.sql</code> once in phpMyAdmin.</div>
</div>
<?php app_require('app/includes/footer.php'); ?>
