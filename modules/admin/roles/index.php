<?php
require_once dirname(__DIR__, 3) . '/app/bootstrap.php';

$conn = db_conn();
require_once app_path('app/config/database.php');
app_require('app/includes/auth.php');
app_require('app/includes/flash.php');
app_require('app/includes/ui.php');

require_role(['developer','super_admin', 'system_admin', 'admin']);
$flash = get_flash();

$setupMessage = optional_table_message($conn, ['roles']);
$roleIdCol = column_exists($conn, 'roles', 'role_id') ? 'role_id' : 'id';
$roleNameCol = column_exists($conn, 'roles', 'role_name') ? 'role_name' : 'name';
$roleCodeCol = column_exists($conn, 'roles', 'code') ? 'code' : $roleNameCol;
$hasRolePermissions = table_exists($conn, 'role_permissions');
$hasRoleFeaturePermissions = table_exists($conn, 'role_feature_permissions');

$rows = [];
if (!$setupMessage) {
    $sql = "SELECT r.*, 0 AS permission_count FROM roles r ORDER BY r.`{$roleNameCol}` ASC";
    $rows = fetch_all_assoc($conn, $sql);
    foreach ($rows as &$row) {
        $rid = (int)($row[$roleIdCol] ?? 0);
        if ($hasRolePermissions) {
            $row['permission_count'] = (int)safe_table_count($conn, 'role_permissions', 'role_id = ' . $rid, 0);
        } elseif ($hasRoleFeaturePermissions) {
            $row['permission_count'] = (int)safe_table_count($conn, 'role_feature_permissions', 'role_id = ' . $rid, 0);
        } else {
            $row['permission_count'] = 0;
        }
    }
    unset($row);
}

app_require('app/includes/header.php');
page_card_start('Manage Roles', 'Review roles, capability counts, and the current permission wiring without leaving the developer workspace.');
flash_message($flash);
if ($setupMessage) {
    echo '<div class="mb-4 rounded-2xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">' . e($setupMessage) . '</div>';
}
?>

<div class="flex flex-wrap gap-3 mb-6">
    <?php action_button('/harvest/modules/admin/dashboard.php', 'Back to Dashboard', 'arrow-left', 'secondary'); ?>
</div>

<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
    <?php ui_stat_card('Roles', count($rows), 'shield-check', 'emerald', 'Role records available in the system'); ?>
    <?php ui_stat_card('Permission wiring', $hasRolePermissions ? 'Direct table' : ($hasRoleFeaturePermissions ? 'Feature matrix' : 'Setup needed'), 'key-round', 'blue', 'How this build currently stores role access'); ?>
    <?php ui_stat_card('Editable mode', $hasRolePermissions ? 'Ready' : 'Limited', 'sliders-horizontal', $hasRolePermissions ? 'emerald' : 'amber', $hasRolePermissions ? 'Per-permission editing is enabled.' : 'Page stays readable even before full setup.'); ?>
</div>

<div class="rounded-3xl bg-white dark:bg-slate-900 p-6 shadow-sm ring-1 ring-slate-200 dark:ring-slate-800">
    <?php if ($setupMessage): ?>
        <?php ui_empty_state('Role module needs setup', 'The roles table is missing, so the developer role center cannot load yet.', 'shield-x'); ?>
    <?php elseif (!empty($rows)): ?>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr>
                        <th class="px-3 py-3 text-left">ID</th>
                        <th class="px-3 py-3 text-left">Code</th>
                        <th class="px-3 py-3 text-left">Name</th>
                        <th class="px-3 py-3 text-left">Description</th>
                        <th class="px-3 py-3 text-left">Access Entries</th>
                        <th class="px-3 py-3 text-left">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <?php
                            $rid = (int)($row[$roleIdCol] ?? 0);
                            $name = (string)($row[$roleNameCol] ?? '');
                            $code = (string)($row[$roleCodeCol] ?? $name);
                        ?>
                        <tr class="border-t border-slate-100 dark:border-slate-800 align-top">
                            <td class="px-3 py-3"><?= $rid ?></td>
                            <td class="px-3 py-3 font-medium"><?= e(strtoupper($code)) ?></td>
                            <td class="px-3 py-3 font-semibold text-slate-900 dark:text-white"><?= e(ucwords(strtolower(str_replace('_', ' ', $name)))) ?></td>
                            <td class="px-3 py-3 text-slate-600 dark:text-slate-300"><?= e((string)($row['description'] ?? 'No description')) ?></td>
                            <td class="px-3 py-3"><?= (int)($row['permission_count'] ?? 0) ?></td>
                            <td class="px-3 py-3">
                                <?php if ($hasRolePermissions || $hasRoleFeaturePermissions): ?>
                                    <a class="font-semibold text-emerald-700 hover:text-emerald-800" href="/harvest/modules/admin/roles/permissions.php?id=<?= $rid ?>">Open access view</a>
                                <?php else: ?>
                                    <span class="text-slate-400">Setup needed</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <?php ui_empty_state('No roles found', 'Create or seed roles first so the developer workspace can manage access.', 'shield-check'); ?>
    <?php endif; ?>
</div>

<?php
page_card_end();
app_require('app/includes/footer.php');
?>
