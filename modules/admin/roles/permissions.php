<?php
require_once dirname(__DIR__, 3) . '/app/bootstrap.php';

$conn = db_conn();
require_once app_path('app/config/database.php');
app_require('app/includes/auth.php');
app_require('app/includes/flash.php');
app_require('app/includes/ui.php');

require_role(['developer','super_admin', 'system_admin', 'admin']);

$roleId = (int)($_GET['id'] ?? 0);
$roleIdCol = column_exists($conn, 'roles', 'role_id') ? 'role_id' : 'id';
$roleNameCol = column_exists($conn, 'roles', 'role_name') ? 'role_name' : 'name';
$roleCodeCol = column_exists($conn, 'roles', 'code') ? 'code' : $roleNameCol;
$flash = get_flash();

$role = null;
if (table_exists($conn, 'roles') && $roleId > 0) {
    $stmt = $conn->prepare("SELECT * FROM roles WHERE {$roleIdCol} = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('i', $roleId);
        $stmt->execute();
        $role = $stmt->get_result()->fetch_assoc() ?: null;
        $stmt->close();
    }
}

if (!$role) {
    set_flash('error', 'Role not found.');
    header('Location: /harvest/modules/admin/roles/index.php');
    exit;
}

$hasDirect = table_exists($conn, 'permissions') && table_exists($conn, 'role_permissions');
$hasFeatureMatrix = table_exists($conn, 'module_features') && table_exists($conn, 'role_feature_permissions');
$currentPermissions = [];
$rows = [];
$mode = 'setup';

if ($hasDirect) {
    $mode = 'direct';
    $rows = fetch_all_assoc($conn, 'SELECT * FROM permissions ORDER BY code ASC');
    $stmt = $conn->prepare('SELECT permission_id FROM role_permissions WHERE role_id = ?');
    if ($stmt) {
        $stmt->bind_param('i', $roleId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) { $currentPermissions[] = (int)$r['permission_id']; }
        $stmt->close();
    }
} elseif ($hasFeatureMatrix) {
    $mode = 'feature';
    $sql = "SELECT mf.feature_id AS id, mf.feature_code AS code, mf.feature_name AS name, mf.route_path AS description,
                   COALESCE(rfp.can_view,0) AS can_view, COALESCE(rfp.can_create,0) AS can_create,
                   COALESCE(rfp.can_update,0) AS can_update, COALESCE(rfp.can_delete,0) AS can_delete,
                   COALESCE(rfp.can_export,0) AS can_export
            FROM module_features mf
            LEFT JOIN role_feature_permissions rfp
              ON rfp.feature_id = mf.feature_id AND rfp.role_id = {$roleId}
            ORDER BY mf.module_id ASC, mf.sort_order ASC, mf.feature_name ASC";
    $rows = fetch_all_assoc($conn, $sql);
}

app_require('app/includes/header.php');
page_card_start('Role Access View', 'See what this role can touch without breaking when the newer permission tables are still missing.');
flash_message($flash);
?>

<div class="flex flex-wrap gap-3 mb-6">
    <?php action_button('/harvest/modules/admin/roles/index.php', 'Back to Roles', 'arrow-left', 'secondary'); ?>
</div>

<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
    <?php ui_stat_card('Role', ucwords(strtolower(str_replace('_', ' ', (string)($role[$roleNameCol] ?? '')))), 'shield-check', 'emerald', 'Current role record'); ?>
    <?php ui_stat_card('Access mode', $mode === 'direct' ? 'Permission table' : ($mode === 'feature' ? 'Feature matrix' : 'Setup needed'), 'key-round', $mode === 'setup' ? 'amber' : 'blue', 'The page adapts to the schema available in your database'); ?>
    <?php ui_stat_card('Entries', count($rows), 'list-checks', 'slate', 'Rows visible on this access page'); ?>
</div>

<div class="rounded-3xl bg-white dark:bg-slate-900 p-6 shadow-sm ring-1 ring-slate-200 dark:ring-slate-800">
    <div class="mb-5">
        <div class="text-sm text-slate-500">Role</div>
        <div class="mt-1 text-xl font-bold text-slate-900 dark:text-white"><?= e((string)($role[$roleNameCol] ?? '')) ?> <span class="text-sm font-medium text-slate-500">(<?= e((string)($role[$roleCodeCol] ?? '')) ?>)</span></div>
    </div>

    <?php if ($mode === 'setup'): ?>
        <?php ui_empty_state('Permission setup still needed', 'This database does not yet have either permissions + role_permissions or module_features + role_feature_permissions. The page is safe now, but editing needs the admin upgrade SQL.', 'key-round'); ?>
    <?php elseif ($mode === 'direct'): ?>
        <form action="/harvest/modules/admin/roles/save_permissions.php" method="POST" class="space-y-5">
            <input type="hidden" name="role_id" value="<?= $roleId ?>">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr>
                            <th class="px-3 py-3 text-left">Allow</th>
                            <th class="px-3 py-3 text-left">Permission Code</th>
                            <th class="px-3 py-3 text-left">Name</th>
                            <th class="px-3 py-3 text-left">Description</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($rows as $perm): ?>
                        <tr class="border-t border-slate-100 dark:border-slate-800">
                            <td class="px-3 py-3"><input type="checkbox" name="permission_ids[]" value="<?= (int)$perm['id'] ?>" <?= in_array((int)$perm['id'], $currentPermissions, true) ? 'checked' : '' ?>></td>
                            <td class="px-3 py-3 font-medium"><?= e((string)$perm['code']) ?></td>
                            <td class="px-3 py-3"><?= e((string)$perm['name']) ?></td>
                            <td class="px-3 py-3 text-slate-600 dark:text-slate-300"><?= e((string)($perm['description'] ?? '')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php ui_primary_button('Save permissions', 'save'); ?>
        </form>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr>
                        <th class="px-3 py-3 text-left">Feature</th>
                        <th class="px-3 py-3 text-left">Code</th>
                        <th class="px-3 py-3 text-left">Route</th>
                        <th class="px-3 py-3 text-left">View</th>
                        <th class="px-3 py-3 text-left">Create</th>
                        <th class="px-3 py-3 text-left">Update</th>
                        <th class="px-3 py-3 text-left">Delete</th>
                        <th class="px-3 py-3 text-left">Export</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $feature): ?>
                    <tr class="border-t border-slate-100 dark:border-slate-800">
                        <td class="px-3 py-3 font-medium"><?= e((string)$feature['name']) ?></td>
                        <td class="px-3 py-3"><?= e((string)$feature['code']) ?></td>
                        <td class="px-3 py-3 text-slate-600 dark:text-slate-300"><?= e((string)($feature['description'] ?? '')) ?></td>
                        <td class="px-3 py-3"><?= ui_status_badge((int)$feature['can_view'] === 1 ? 'active' : 'inactive') ?></td>
                        <td class="px-3 py-3"><?= ui_status_badge((int)$feature['can_create'] === 1 ? 'active' : 'inactive') ?></td>
                        <td class="px-3 py-3"><?= ui_status_badge((int)$feature['can_update'] === 1 ? 'active' : 'inactive') ?></td>
                        <td class="px-3 py-3"><?= ui_status_badge((int)$feature['can_delete'] === 1 ? 'active' : 'inactive') ?></td>
                        <td class="px-3 py-3"><?= ui_status_badge((int)$feature['can_export'] === 1 ? 'active' : 'inactive') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php
page_card_end();
app_require('app/includes/footer.php');
?>
