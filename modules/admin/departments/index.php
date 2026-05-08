<?php
require_once dirname(__DIR__, 3) . '/app/bootstrap.php';

$conn = db_conn();require_once app_path('app/config/database.php');
app_require('app/includes/auth.php');
app_require('app/includes/flash.php');
app_require('app/includes/ui.php');

require_role(['developer','super_admin', 'system_admin', 'admin']);
$flash = get_flash();

$setupMessage = optional_table_message($conn, ['departments']);
$result = null;
if (!$setupMessage) {
    $sql = "
        SELECT d.*, p.name AS parent_name
        FROM departments d
        LEFT JOIN departments p ON p.id = d.parent_department_id
        ORDER BY d.name ASC
    ";
    $result = $conn->query($sql);
}

app_require('app/includes/header.php');

page_card_start('Manage Departments', 'Department management now follows the same modern admin shell.');
flash_message($flash);
if ($setupMessage) { echo '<div class="mb-4 rounded-2xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">'.e($setupMessage).'</div>'; }
?>

<div class="flex flex-wrap gap-3 mb-6">
    <?php action_button('/harvest/modules/admin/dashboard.php', 'Back to Dashboard', 'arrow-left', 'secondary'); ?>
    <?php action_button('/harvest/modules/admin/departments/create.php', 'Add Department', 'building-2'); ?>
</div>

<div class="rounded-3xl bg-white dark:bg-slate-900 p-6 shadow-sm ring-1 ring-slate-200 dark:ring-slate-800">
    <?php if ($setupMessage): ?>
        <?php ui_empty_state('Department module needs setup', 'Run database/system_admin_upgrade_v28.sql so this page can manage departments safely.', 'building-2'); ?>
    <?php elseif ($result && $result->num_rows > 0): ?>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Code</th>
                        <th>Name</th>
                        <th>Parent</th>
                        <th>Description</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?= (int)$row['id'] ?></td>
                            <td><?= htmlspecialchars($row['code']) ?></td>
                            <td class="font-medium text-slate-900 dark:text-white"><?= htmlspecialchars($row['name']) ?></td>
                            <td><?= htmlspecialchars($row['parent_name'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($row['description'] ?? '-') ?></td>
                            <td><?= ui_status_badge((int)$row['is_active'] === 1 ? 'active' : 'inactive') ?></td>
                            <td>
                                <div class="flex flex-wrap gap-2">
                                    <a href="/harvest/modules/admin/departments/edit.php?id=<?= (int)$row['id'] ?>">Edit</a>
                                    <a href="/harvest/modules/admin/departments/toggle_status.php?id=<?= (int)$row['id'] ?>" onclick="return confirm('Change department status?');">
                                        <?= (int)$row['is_active'] === 1 ? 'Deactivate' : 'Activate' ?>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <?php ui_empty_state('No departments found', 'Create departments so users and workflows can be assigned properly.', 'building-2'); ?>
    <?php endif; ?>
</div>

<?php
page_card_end();
app_require('app/includes/footer.php');
?>