<?php
require_once dirname(__DIR__, 3) . '/app/bootstrap.php';

$conn = db_conn();require_once app_path('app/config/database.php');
app_require('app/includes/auth.php');
app_require('app/includes/flash.php');
app_require('app/includes/ui.php');

require_role(['developer','super_admin', 'system_admin', 'admin']);

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    $_SESSION['flash'] = ['type' => 'error', 'message' => 'Invalid department ID.'];
    header("Location: /harvest/admin/departments/index.php");
    exit;
}

$stmt = $conn->prepare("SELECT * FROM departments WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$department = $result->fetch_assoc();
$stmt->close();

if (!$department) {
    $_SESSION['flash'] = ['type' => 'error', 'message' => 'Department not found.'];
    header("Location: /harvest/admin/departments/index.php");
    exit;
}

$parents = $conn->query("SELECT id, name FROM departments WHERE id != {$id} ORDER BY name ASC");

app_require('app/includes/header.php');

page_card_start('Edit Department', 'Update department details and hierarchy.');
?>

<div class="mb-6">
    <?php action_button('/harvest/admin/departments/index.php', 'Back to Departments', 'arrow-left', 'secondary'); ?>
</div>

<form action="/harvest/admin/departments/update.php" method="POST" class="space-y-8">
    <input type="hidden" name="id" value="<?= (int)$department['id'] ?>">

    <div class="rounded-3xl bg-white dark:bg-slate-900 p-6 shadow-sm ring-1 ring-slate-200 dark:ring-slate-800">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            <div>
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Code</label>
                <input type="text" name="code" value="<?= htmlspecialchars($department['code']) ?>" required class="<?= ui_input_class() ?>">
            </div>

            <div>
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Name</label>
                <input type="text" name="name" value="<?= htmlspecialchars($department['name']) ?>" required class="<?= ui_input_class() ?>">
            </div>

            <div class="md:col-span-2">
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Parent Department</label>
                <select name="parent_department_id" class="<?= ui_select_class() ?>">
                    <option value="">None</option>
                    <?php while ($row = $parents->fetch_assoc()): ?>
                        <option value="<?= (int)$row['id'] ?>" <?= ((int)$department['parent_department_id'] === (int)$row['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($row['name']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="md:col-span-2">
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Description</label>
                <textarea name="description" rows="5" class="<?= ui_textarea_class() ?>"><?= htmlspecialchars($department['description'] ?? '') ?></textarea>
            </div>

            <div>
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Status</label>
                <select name="is_active" class="<?= ui_select_class() ?>">
                    <option value="1" <?= (int)$department['is_active'] === 1 ? 'selected' : '' ?>>Active</option>
                    <option value="0" <?= (int)$department['is_active'] === 0 ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>
        </div>
    </div>

    <div class="flex flex-wrap gap-3">
        <?php ui_primary_button('Update Department', 'save'); ?>
    </div>
</form>

<?php
page_card_end();
app_require('app/includes/footer.php');
?>