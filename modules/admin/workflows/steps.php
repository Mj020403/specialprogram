<?php
require_once dirname(__DIR__, 3) . '/app/bootstrap.php';

$conn = db_conn();
require_once app_path('app/config/database.php');
app_require('app/includes/auth.php');
app_require('app/includes/flash.php');
app_require('app/includes/ui.php');

require_role(['developer','super_admin', 'system_admin', 'admin']);

$template_id = (int)($_GET['id'] ?? 0);
$flash = get_flash();
$template = null;
if (table_exists($conn, 'workflow_templates') && $template_id > 0) {
    $stmt = $conn->prepare('SELECT * FROM workflow_templates WHERE id = ? LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('i', $template_id);
        $stmt->execute();
        $template = $stmt->get_result()->fetch_assoc() ?: null;
        $stmt->close();
    }
}

if (!$template) {
    set_flash('error', 'Workflow template not found.');
    header('Location: /harvest/modules/admin/workflows/index.php');
    exit;
}

$stepTable = table_exists($conn, 'workflow_template_steps') ? 'workflow_template_steps' : (table_exists($conn, 'workflow_steps') ? 'workflow_steps' : null);
$setupMessage = $stepTable ? null : 'Missing optional table: workflow_template_steps. Run the updated admin upgrade SQL to enable workflow step management fully.';

$steps = [];
if ($stepTable === 'workflow_template_steps') {
    $steps = fetch_all_assoc($conn, "SELECT * FROM workflow_template_steps WHERE workflow_template_id = {$template_id} ORDER BY step_no ASC");
} elseif ($stepTable === 'workflow_steps') {
    $steps = fetch_all_assoc($conn, "SELECT id, workflow_template_id, step_name, step_order AS step_no, role_name AS assigned_role_code FROM workflow_steps WHERE workflow_template_id = {$template_id} ORDER BY step_order ASC");
}

$users = table_exists($conn, 'users') ? fetch_all_assoc($conn, 'SELECT user_id AS id, email FROM users WHERE is_active = 1 ORDER BY email ASC') : [];
$departments = table_exists($conn, 'departments') ? fetch_all_assoc($conn, 'SELECT id, name FROM departments WHERE is_active = 1 ORDER BY name ASC') : [];
$roles = [];
if (table_exists($conn, 'roles')) {
    if (column_exists($conn, 'roles', 'code')) {
        $roles = fetch_all_assoc($conn, 'SELECT code, name FROM roles ORDER BY name ASC');
    } else {
        $roles = fetch_all_assoc($conn, 'SELECT role_name AS code, role_name AS name FROM roles ORDER BY role_name ASC');
    }
}

app_require('app/includes/header.php');
page_card_start('Workflow Steps', 'Add assignment logic and timing rules to this workflow template without breaking when optional tables are still being set up.');
flash_message($flash);
if ($setupMessage) {
    echo '<div class="mb-4 rounded-2xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">' . e($setupMessage) . '</div>';
}
?>

<div class="flex flex-wrap gap-3 mb-6">
    <?php action_button('/harvest/modules/admin/workflows/index.php', 'Back to Workflows', 'arrow-left', 'secondary'); ?>
</div>

<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
    <?php ui_stat_card('Workflow', (string)$template['name'], 'git-branch-plus', 'emerald', 'Current template being configured'); ?>
    <?php ui_stat_card('Existing steps', count($steps), 'list-checks', 'blue', 'Steps already attached to this template'); ?>
    <?php ui_stat_card('Step storage', $stepTable === 'workflow_template_steps' ? 'Modern table' : ($stepTable === 'workflow_steps' ? 'Legacy table' : 'Setup needed'), 'database', $stepTable ? 'slate' : 'amber', 'This page adapts to the table available in your database'); ?>
</div>

<div class="rounded-3xl bg-white dark:bg-slate-900 p-6 shadow-sm ring-1 ring-slate-200 dark:ring-slate-800 mb-6">
    <div class="text-sm text-slate-500">Workflow</div>
    <div class="mt-1 text-xl font-bold text-slate-900 dark:text-white"><?= e((string)$template['name']) ?></div>
    <?php if (!empty($template['description'])): ?><p class="mt-2 text-sm text-slate-500 dark:text-slate-400"><?= e((string)$template['description']) ?></p><?php endif; ?>
</div>

<div class="rounded-3xl bg-white dark:bg-slate-900 p-6 shadow-sm ring-1 ring-slate-200 dark:ring-slate-800 mb-6">
    <h2 class="text-lg font-bold text-slate-900 dark:text-white mb-4">Existing Steps</h2>
    <?php if (!empty($steps)): ?>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr>
                        <th class="px-3 py-3 text-left">Step No</th>
                        <th class="px-3 py-3 text-left">Name</th>
                        <th class="px-3 py-3 text-left">Task Type</th>
                        <th class="px-3 py-3 text-left">Assignment</th>
                        <th class="px-3 py-3 text-left">Due Days</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($steps as $step): ?>
                    <tr class="border-t border-slate-100 dark:border-slate-800">
                        <td class="px-3 py-3"><?= (int)($step['step_no'] ?? 0) ?></td>
                        <td class="px-3 py-3 font-medium"><?= e((string)($step['step_name'] ?? '')) ?></td>
                        <td class="px-3 py-3"><?= e((string)($step['task_type'] ?? 'N/A')) ?></td>
                        <td class="px-3 py-3"><?= e((string)($step['assign_type'] ?? ($step['assigned_role_code'] ?? 'N/A'))) ?></td>
                        <td class="px-3 py-3"><?= e((string)($step['due_days'] ?? '-')) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <?php ui_empty_state('No steps yet', 'Add the first step below so this workflow can be used for routing.', 'list-checks'); ?>
    <?php endif; ?>
</div>

<div class="rounded-3xl bg-white dark:bg-slate-900 p-6 shadow-sm ring-1 ring-slate-200 dark:ring-slate-800">
    <h2 class="text-lg font-bold text-slate-900 dark:text-white mb-4">Add New Step</h2>
    <?php if ($stepTable !== 'workflow_template_steps'): ?>
        <?php ui_empty_state('Step editing is waiting for the new table', 'This template can be viewed safely, but adding steps needs workflow_template_steps from the updated SQL file.', 'database-zap'); ?>
    <?php else: ?>
        <form action="/harvest/modules/admin/workflows/save_steps.php" method="POST" class="space-y-5">
            <input type="hidden" name="workflow_template_id" value="<?= (int)$template['id'] ?>">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Step No</label>
                    <input type="number" name="step_no" required class="<?= ui_input_class() ?>">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Step Name</label>
                    <input type="text" name="step_name" required class="<?= ui_input_class() ?>">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Task Type</label>
                    <select name="task_type" class="<?= ui_select_class() ?>">
                        <option value="review">Review</option>
                        <option value="approve">Approve</option>
                        <option value="route">Route</option>
                        <option value="receive">Receive</option>
                        <option value="records_check">Records Check</option>
                        <option value="signoff">Signoff</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Assign Type</label>
                    <select name="assign_type" id="assign_type" class="<?= ui_select_class() ?>" onchange="toggleAssignmentFields()">
                        <option value="role">Role</option>
                        <option value="specific_user">Specific User</option>
                        <option value="department">Department</option>
                    </select>
                </div>
                <div id="assign_role_box" class="md:col-span-2">
                    <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Assigned Role</label>
                    <select name="assigned_role_code" class="<?= ui_select_class() ?>">
                        <option value="">Select role</option>
                        <?php foreach ($roles as $r): ?>
                            <option value="<?= e((string)$r['code']) ?>"><?= e((string)$r['name']) ?> (<?= e((string)$r['code']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div id="assign_user_box" class="md:col-span-2" style="display:none;">
                    <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Assigned User</label>
                    <select name="assigned_user_id" class="<?= ui_select_class() ?>">
                        <option value="">Select user</option>
                        <?php foreach ($users as $u): ?>
                            <option value="<?= (int)$u['id'] ?>"><?= e((string)$u['email']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div id="assign_department_box" class="md:col-span-2" style="display:none;">
                    <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Assigned Department</label>
                    <select name="assigned_department_id" class="<?= ui_select_class() ?>">
                        <option value="">Select department</option>
                        <?php foreach ($departments as $d): ?>
                            <option value="<?= (int)$d['id'] ?>"><?= e((string)$d['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Due Days</label>
                    <input type="number" name="due_days" value="3" required class="<?= ui_input_class() ?>">
                </div>
                <div class="flex items-end">
                    <label class="inline-flex items-center gap-2 text-sm font-medium text-slate-700 dark:text-slate-300"><input type="checkbox" name="is_required" value="1" checked> Required Step</label>
                </div>
            </div>
            <?php ui_primary_button('Add Step', 'plus'); ?>
        </form>
    <?php endif; ?>
</div>

<script>
function toggleAssignmentFields() {
    const type = document.getElementById('assign_type').value;
    document.getElementById('assign_role_box').style.display = type === 'role' ? 'block' : 'none';
    document.getElementById('assign_user_box').style.display = type === 'specific_user' ? 'block' : 'none';
    document.getElementById('assign_department_box').style.display = type === 'department' ? 'block' : 'none';
}
</script>

<?php
page_card_end();
app_require('app/includes/footer.php');
?>
