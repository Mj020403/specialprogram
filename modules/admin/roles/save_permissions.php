<?php
require_once dirname(__DIR__, 3) . '/app/bootstrap.php';

$conn = db_conn();require_once app_path('app/config/database.php');
app_require('app/includes/auth.php');
app_require('app/includes/flash.php');

require_role(['developer','super_admin', 'system_admin', 'admin']);

if (!table_exists($conn, 'role_permissions')) {
    set_flash('error', 'Role permissions table is not ready yet. Run the admin upgrade SQL first.');
    header("Location: /harvest/modules/admin/roles/index.php");
    exit;
}

$role_id = (int)($_POST['role_id'] ?? 0);
$permission_ids = $_POST['permission_ids'] ?? [];

if ($role_id <= 0) {
    set_flash('error', 'Invalid role.');
    header("Location: /harvest/modules/admin/roles/index.php");
    exit;
}

$conn->begin_transaction();

try {
    $stmt = $conn->prepare("DELETE FROM role_permissions WHERE role_id = ?");
    $stmt->bind_param("i", $role_id);
    $stmt->execute();
    $stmt->close();

    if (!empty($permission_ids) && is_array($permission_ids)) {
        $stmt = $conn->prepare("
            INSERT INTO role_permissions (role_id, permission_id, created_at)
            VALUES (?, ?, NOW())
        ");

        foreach ($permission_ids as $permission_id) {
            $permission_id = (int)$permission_id;
            if ($permission_id > 0) {
                $stmt->bind_param("ii", $role_id, $permission_id);
                $stmt->execute();
            }
        }
        $stmt->close();
    }

    $admin_id = (int)$_SESSION['user_id'];
    $desc = "Updated permissions for role ID {$role_id}";

    $stmt = $conn->prepare("
        INSERT INTO activity_logs (user_id, action_code, entity_type, entity_id, description, created_at)
        VALUES (?, 'role_permissions_updated', 'roles', ?, ?, NOW())
    ");
    $stmt->bind_param("iis", $admin_id, $role_id, $desc);
    $stmt->execute();
    $stmt->close();

    $conn->commit();

    set_flash('success', 'Role permissions updated successfully.');
    header("Location: /harvest/modules/admin/roles/permissions.php?id=" . $role_id);
    exit;

} catch (Exception $e) {
    $conn->rollback();
    set_flash('error', 'Failed to update permissions: ' . $e->getMessage());
    header("Location: /harvest/modules/admin/roles/permissions.php?id=" . $role_id);
    exit;
}