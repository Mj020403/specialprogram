<?php
require_once dirname(__DIR__, 3) . '/app/bootstrap.php';

$conn = db_conn();
require_once app_path('app/config/database.php');
app_require('app/includes/auth.php');
app_require('app/includes/ui.php');
app_require('app/includes/flash.php');

require_role(['developer','super_admin', 'system_admin', 'admin']);

$flash = get_flash();
$setupMessage = optional_table_message($conn, ['workflow_templates']);
$categories = [];
if (table_exists($conn, 'document_categories')) {
    $categories = fetch_all_assoc($conn, 'SELECT id, name FROM document_categories WHERE is_active = 1 ORDER BY name ASC');
}

app_require('app/includes/header.php');
page_card_start('Create Workflow Template', 'Build a reusable routing pattern for approvals, reviews, or internal document movement.');
flash_message($flash);
if ($setupMessage) {
    echo '<div class="mb-4 rounded-2xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">' . e($setupMessage) . '</div>';
}
?>

<div class="mb-6">
    <?php action_button('/harvest/modules/admin/workflows/index.php', 'Back to Workflows', 'arrow-left', 'secondary'); ?>
</div>

<form action="/harvest/modules/admin/workflows/store.php" method="POST" class="space-y-6">
    <div class="rounded-3xl bg-white dark:bg-slate-900 p-6 shadow-sm ring-1 ring-slate-200 dark:ring-slate-800">
        <div class="grid grid-cols-1 gap-5">
            <div>
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Workflow Name</label>
                <input type="text" name="name" required class="<?= ui_input_class() ?>">
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Description</label>
                <textarea name="description" rows="5" class="<?= ui_textarea_class() ?>"></textarea>
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Category</label>
                <select name="category_id" class="<?= ui_select_class() ?>">
                    <option value="">No specific category</option>
                    <?php foreach ($categories as $row): ?>
                        <option value="<?= (int)$row['id'] ?>"><?= e((string)$row['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (empty($categories)): ?>
                    <p class="mt-2 text-sm text-amber-700">No active document categories yet. You can still save a workflow without a category.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="flex flex-wrap gap-3">
        <?php ui_primary_button('Save Workflow Template', 'save'); ?>
    </div>
</form>

<?php
page_card_end();
app_require('app/includes/footer.php');
?>
