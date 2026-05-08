<?php
require_once dirname(__DIR__, 3) . '/app/bootstrap.php';

$conn = db_conn(); require_once app_path('app/config/database.php');
app_require('app/includes/auth.php');
app_require('app/includes/ui.php');
require_role(['developer','super_admin', 'system_admin', 'admin']);

$setupMessage = optional_table_message($conn, ['workflow_templates','document_categories']);
$result = null;
if (!$setupMessage) {
    $result = $conn->query("
        SELECT wt.*, dc.name AS category_name
        FROM workflow_templates wt
        LEFT JOIN document_categories dc ON dc.id = wt.category_id
        ORDER BY wt.name ASC
    ");
}

app_require('app/includes/header.php');
?>
<div class="rounded-[2rem] border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-6 shadow-sm space-y-5">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <div class="text-sm text-slate-500 dark:text-slate-400">Developer workflow control</div>
            <h1 class="mt-1 text-3xl font-black">Workflow templates</h1>
            <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Manage reusable approval or document flow templates from the developer workspace.</p>
        </div>
        <div class="flex flex-wrap gap-3">
            <a href="<?= e(app_url('modules/admin/dashboard.php')) ?>" class="inline-flex items-center gap-2 rounded-full border border-slate-300 px-4 py-2 font-semibold">Back to dashboard</a>
            <a href="<?= e(app_url('modules/admin/workflows/create.php')) ?>" class="inline-flex items-center gap-2 rounded-full bg-emerald-700 px-4 py-2 font-semibold text-white">Add workflow template</a>
        </div>
    </div>
    <?php if ($setupMessage): ?>
        <div class="rounded-2xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900"><?= e($setupMessage) ?></div>
        <?php ui_empty_state('Workflow module needs setup', 'Run database/system_admin_upgrade_v28.sql so workflow templates and document categories are available.', 'git-branch-plus'); ?>
    <?php elseif ($result && $result->num_rows > 0): ?>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr>
                        <th class="px-3 py-3 text-left">ID</th>
                        <th class="px-3 py-3 text-left">Name</th>
                        <th class="px-3 py-3 text-left">Category</th>
                        <th class="px-3 py-3 text-left">Status</th>
                        <th class="px-3 py-3 text-left">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr class="border-t border-slate-100 dark:border-slate-800">
                            <td class="px-3 py-3"><?= (int)$row['id'] ?></td>
                            <td class="px-3 py-3 font-semibold"><?= e($row['name']) ?></td>
                            <td class="px-3 py-3"><?= e($row['category_name'] ?? 'Uncategorized') ?></td>
                            <td class="px-3 py-3"><?= ui_status_badge((int)($row['is_active'] ?? 0) === 1 ? 'active' : 'inactive') ?></td>
                            <td class="px-3 py-3"><a class="font-semibold text-emerald-700" href="<?= e(app_url('modules/admin/workflows/steps.php?id='.(int)$row['id'])) ?>">Open steps</a></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <?php ui_empty_state('No workflow templates yet', 'Create your first workflow template for document or approval routing.', 'git-branch-plus'); ?>
    <?php endif; ?>
</div>
<?php app_require('app/includes/footer.php'); ?>
