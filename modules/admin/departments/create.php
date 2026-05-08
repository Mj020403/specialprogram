<?php
require_once dirname(__DIR__, 3) . '/app/bootstrap.php';

$conn = db_conn();require_once app_path('app/config/database.php');
app_require('app/includes/auth.php');
app_require('app/includes/ui.php');

require_role(['developer','super_admin', 'system_admin', 'admin']);

$parents = $conn->query("SELECT id, name FROM departments ORDER BY name ASC");

app_require('app/includes/header.php');

page_card_start('Create Department', 'Add a new department or office unit.');
?>

<div class="mb-6">
    <?php action_button('/harvest/modules/admin/departments/index.php', 'Back to Departments', 'arrow-left', 'secondary'); ?>
</div>

<form action="/harvest/modules/admin/departments/store.php" method="POST" class="space-y-8">
    <div class="rounded-3xl bg-white dark:bg-slate-900 p-6 shadow-sm ring-1 ring-slate-200 dark:ring-slate-800">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            <div>
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Code</label>
                <input type="text" name="code" required class="<?= ui_input_class() ?>">
            </div>

            <div>
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Name</label>
                <input type="text" name="name" required class="<?= ui_input_class() ?>">
            </div>

            <div class="md:col-span-2">
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Parent Department</label>
                <select name="parent_department_id" class="<?= ui_select_class() ?>">
                    <option value="">None</option>
                    <?php while ($row = $parents->fetch_assoc()): ?>
                        <option value="<?= (int)$row['id'] ?>"><?= htmlspecialchars($row['name']) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="md:col-span-2">
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Description</label>
                <textarea name="description" rows="5" class="<?= ui_textarea_class() ?>"></textarea>
            </div>

            <div>
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Status</label>
                <select name="is_active" class="<?= ui_select_class() ?>">
                    <option value="1">Active</option>
                    <option value="0">Inactive</option>
                </select>
            </div>
        </div>
    </div>

    <div class="flex flex-wrap gap-3">
        <?php ui_primary_button('Save Department', 'save'); ?>
    </div>
</form>

<?php
page_card_end();
app_require('app/includes/footer.php');
?>