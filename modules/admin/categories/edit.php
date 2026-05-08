<?php
require_once dirname(__DIR__, 3) . '/app/bootstrap.php';

$conn = db_conn();require_once app_path('app/config/database.php');
app_require('app/includes/auth.php');
app_require('app/includes/flash.php');
app_require('app/includes/ui.php');

require_role(['developer','super_admin', 'system_admin', 'admin']);

$id = (int)($_GET['id'] ?? 0);
$stmt = $conn->prepare("SELECT * FROM document_categories WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$stmt->close();

if (!$row) {
    $_SESSION['flash'] = ['type' => 'error', 'message' => 'Category not found.'];
    header("Location: /harvest/admin/categories/index.php");
    exit;
}

app_require('app/includes/header.php');

page_card_start('Edit Category', 'Update category information and workflow settings.');
?>

<div class="mb-6">
    <?php action_button('/harvest/admin/categories/index.php', 'Back to Categories', 'arrow-left', 'secondary'); ?>
</div>

<form action="/harvest/admin/categories/update.php" method="POST" class="space-y-8">
    <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">

    <div class="rounded-3xl bg-white dark:bg-slate-900 p-6 shadow-sm ring-1 ring-slate-200 dark:ring-slate-800">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            <div>
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Code</label>
                <input type="text" name="code" value="<?= htmlspecialchars($row['code']) ?>" required class="<?= ui_input_class() ?>">
            </div>

            <div>
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Name</label>
                <input type="text" name="name" value="<?= htmlspecialchars($row['name']) ?>" required class="<?= ui_input_class() ?>">
            </div>

            <div class="md:col-span-2">
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Description</label>
                <textarea name="description" rows="5" class="<?= ui_textarea_class() ?>"><?= htmlspecialchars($row['description'] ?? '') ?></textarea>
            </div>

            <div>
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Requires Workflow</label>
                <select name="requires_workflow" class="<?= ui_select_class() ?>">
                    <option value="1" <?= (int)$row['requires_workflow'] === 1 ? 'selected' : '' ?>>Yes</option>
                    <option value="0" <?= (int)$row['requires_workflow'] === 0 ? 'selected' : '' ?>>No</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Retention Years</label>
                <input type="number" name="default_retention_years" value="<?= htmlspecialchars($row['default_retention_years'] ?? '') ?>" class="<?= ui_input_class() ?>">
            </div>

            <div>
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Status</label>
                <select name="is_active" class="<?= ui_select_class() ?>">
                    <option value="1" <?= (int)$row['is_active'] === 1 ? 'selected' : '' ?>>Active</option>
                    <option value="0" <?= (int)$row['is_active'] === 0 ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>
        </div>
    </div>

    <div class="flex flex-wrap gap-3">
        <?php ui_primary_button('Update Category', 'save'); ?>
    </div>
</form>

<?php
page_card_end();
app_require('app/includes/footer.php');
?>