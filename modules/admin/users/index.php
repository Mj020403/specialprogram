
<?php
require_once dirname(__DIR__, 3) . '/app/bootstrap.php';
$conn = db_conn();
app_require('app/includes/auth.php');
require_role(['developer','admin']);
ensure_user_account_schema($conn);
$user = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)post('action');
    if ($action === 'create') {
        $full = trim((string)post('full_name'));
        $username = trim((string)post('username'));
        $password = (string)post('password');
        $roleName = strtoupper(trim((string)post('role_name')));
        $email = trim((string)post('email'));
        $contact = trim((string)post('contact_number'));
        $position = trim((string)post('position_title'));
        $avatarPath = null;
        if (!empty($_FILES['avatar']['name'])) $avatarPath = upload_user_avatar($_FILES['avatar']);
        if ($full !== '' && $username !== '' && $password !== '' && $roleName !== '') {
            $roleId = (int)scalar($conn, "SELECT role_id FROM roles WHERE role_name='" . $conn->real_escape_string($roleName) . "' LIMIT 1", 0);
            if ($roleId > 0) {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("INSERT INTO users (role_id, full_name, username, password_hash, email, contact_number, position_title, avatar_path, is_active, profile_status, created_by, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, 'approved', ?, NOW(), NOW())");
                if ($stmt) {
                    $stmt->bind_param('isssssssi', $roleId, $full, $username, $hash, $email, $contact, $position, $avatarPath, $user['id']);
                    if ($stmt->execute()) set_flash('success', 'Account created successfully.');
                    else set_flash('error', 'Unable to create account. Username may already exist.');
                    $stmt->close();
                }
            }
        } else set_flash('error', 'Please complete the required account fields.');
    }
    if ($action === 'toggle_status') {
        $targetId = (int)post('user_id');
        if ($targetId > 0 && $targetId !== (int)$user['id']) {
            $conn->query("UPDATE users SET is_active = IF(is_active=1,0,1), updated_at=NOW() WHERE user_id=" . $targetId);
            set_flash('success', 'Account status updated.');
        }
    }
    if ($action === 'delete') {
        $targetId = (int)post('user_id');
        if ($targetId > 0 && $targetId !== (int)$user['id']) {
            $conn->query("DELETE FROM users WHERE user_id=" . $targetId . " LIMIT 1");
            set_flash('success', 'Account deleted.');
        }
    }
    header('Location: ' . app_url('modules/admin/users/index.php'));
    exit;
}

app_require('app/includes/header.php');
$userStats = [
    'total' => (int)scalar($conn, "SELECT COUNT(*) FROM users", 0),
    'active' => (int)scalar($conn, "SELECT COUNT(*) FROM users WHERE is_active=1", 0),
    'pending_resets' => pending_password_reset_count($conn),
    'pending_signups' => pending_signup_request_count($conn),
];
$roles = fetch_all_assoc($conn, "SELECT role_name FROM roles WHERE role_name IN ('TASK_FORCE','MAYOR','DEVELOPER') ORDER BY FIELD(role_name,'DEVELOPER','TASK_FORCE','MAYOR')");
$rows = fetch_all_assoc($conn, "SELECT u.user_id,u.full_name,u.username,u.email,u.contact_number,u.position_title,u.avatar_path,u.is_active,u.profile_status,u.last_login_at,r.role_name FROM users u LEFT JOIN roles r ON r.role_id=u.role_id ORDER BY FIELD(r.role_name,'DEVELOPER','TASK_FORCE','MAYOR'), u.full_name ASC");
?>
<section class="mb-6 grid gap-4 md:grid-cols-4">
    <div class="rounded-[1.5rem] border border-slate-200 bg-white p-4 shadow-sm"><div class="text-sm text-slate-500">Accounts</div><div class="mt-2 text-3xl font-black"><?= $userStats['total'] ?></div></div>
    <div class="rounded-[1.5rem] border border-slate-200 bg-white p-4 shadow-sm"><div class="text-sm text-slate-500">Active</div><div class="mt-2 text-3xl font-black text-emerald-700"><?= $userStats['active'] ?></div></div>
    <div class="rounded-[1.5rem] border border-slate-200 bg-white p-4 shadow-sm"><div class="text-sm text-slate-500">Signup approvals</div><div class="mt-2 text-3xl font-black text-amber-600"><?= $userStats['pending_signups'] ?></div></div>
    <div class="rounded-[1.5rem] border border-slate-200 bg-white p-4 shadow-sm"><div class="text-sm text-slate-500">Reset requests</div><div class="mt-2 text-3xl font-black text-rose-600"><?= $userStats['pending_resets'] ?></div></div>
</section>
<div class="grid gap-6 xl:grid-cols-[0.92fr_1.08fr]">
<section class="rounded-[2rem] border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-6 shadow-sm">
    <div class="flex items-center justify-between gap-3 flex-wrap">
        <div><div class="text-sm text-slate-500">Developer tools</div><h2 class="text-3xl font-black">Create system account</h2></div>
        <div class="flex gap-2 flex-wrap"><a href="<?= e(app_url('modules/admin/security/index.php')) ?>" class="app-btn-outline">Security desk</a></div>
    </div>
    <form method="POST" enctype="multipart/form-data" class="mt-6 grid gap-4 md:grid-cols-2">
        <input type="hidden" name="action" value="create">
        <div class="md:col-span-2"><label class="block text-sm font-semibold mb-2">Full name</label><input name="full_name" required class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-4 py-3"></div>
        <div><label class="block text-sm font-semibold mb-2">Username</label><input name="username" required class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-4 py-3"></div>
        <div><label class="block text-sm font-semibold mb-2">Password</label><input name="password" required class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-4 py-3"></div>
        <div><label class="block text-sm font-semibold mb-2">Role</label><select name="role_name" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-4 py-3"><?php foreach($roles as $r): ?><option value="<?= e($r['role_name']) ?>"><?= e(role_label(role_code_from_name($r['role_name']))) ?></option><?php endforeach; ?></select></div>
        <div><label class="block text-sm font-semibold mb-2">Position title</label><input name="position_title" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-4 py-3"></div>
        <div><label class="block text-sm font-semibold mb-2">Email</label><input name="email" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-4 py-3"></div>
        <div><label class="block text-sm font-semibold mb-2">Contact number</label><input name="contact_number" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-4 py-3"></div>
        <div class="md:col-span-2"><label class="block text-sm font-semibold mb-2">Profile photo</label><input type="file" name="avatar" accept="image/*" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-4 py-3"></div>
        <div class="md:col-span-2"><button class="app-btn-primary">Create account</button></div>
    </form>
</section>
<section class="rounded-[2rem] border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-6 shadow-sm">
    <div class="text-sm text-slate-500">System users</div>
    <h2 class="text-3xl font-black">Task Force, Mayor, and Developer accounts</h2>
    <div class="mt-5 space-y-3">
        <?php foreach($rows as $row): ?>
            <div class="rounded-3xl border border-slate-200 dark:border-slate-800 p-4 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div class="flex items-center gap-4 min-w-0">
                    <img src="<?= e(user_avatar_url($row['avatar_path'] ?? null)) ?>" class="h-16 w-16 rounded-[1.25rem] object-cover border border-slate-200 dark:border-slate-800" alt="Avatar">
                    <div class="min-w-0">
                        <div class="font-black truncate"><?= e($row['full_name']) ?></div>
                        <div class="text-sm text-slate-500">@<?= e($row['username']) ?> · <?= e(role_label(role_code_from_name($row['role_name'] ?? ''))) ?></div>
                        <div class="text-sm text-slate-500"><?= e($row['position_title'] ?: 'No position title') ?><?= $row['email'] ? ' · '.e($row['email']) : '' ?></div>
                        <div class="mt-2 flex gap-2 flex-wrap"><?= $row['is_active'] ? format_status_badge('Active') : format_status_badge('Inactive') ?><?= format_status_badge($row['profile_status'] ?: 'approved') ?></div>
                    </div>
                </div>
                <div class="flex gap-2 flex-wrap">
                    <a href="<?= e(app_url('modules/users/account/activity.php')) ?>?user_id=<?= (int)$row['user_id'] ?>" class="app-btn-outline">Activity</a>
                    <?php if ((int)$row['user_id'] !== (int)$user['id']): ?>
                    <form method="POST"><input type="hidden" name="action" value="toggle_status"><input type="hidden" name="user_id" value="<?= (int)$row['user_id'] ?>"><button class="app-btn-outline"><?= $row['is_active'] ? 'Deactivate' : 'Activate' ?></button></form>
                    <form method="POST" onsubmit="return confirm('Delete this account?');"><input type="hidden" name="action" value="delete"><input type="hidden" name="user_id" value="<?= (int)$row['user_id'] ?>"><button class="app-btn-outline">Delete</button></form>
                    <?php else: ?>
                    <span class="app-badge app-badge-sky">Current account</span>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>
</div>
<?php app_require('app/includes/footer.php'); ?>
