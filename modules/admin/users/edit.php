<?php
require_once dirname(__DIR__, 3) . '/app/bootstrap.php';

$conn = db_conn();require_once app_path('app/config/database.php');
app_require('app/includes/auth.php');
app_require('app/includes/flash.php');
app_require('app/includes/ui.php');

require_role(['developer','super_admin', 'system_admin', 'admin']);
$flash = get_flash();
$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    set_flash('error', 'Invalid user ID.');
    header('Location: /harvest/modules/admin/users/index.php');
    exit;
}

$stmt = $conn->prepare("\n    SELECT\n        u.id, u.email, u.username, u.is_active,\n        up.employee_no, up.first_name, up.middle_name, up.last_name, up.suffix_name, up.phone, up.job_title, up.profile_image_path,\n        up.primary_department_id, up.employment_status, ura.role_id\n    FROM users u\n    LEFT JOIN user_profiles up ON up.user_id = u.id\n    LEFT JOIN user_role_assignments ura ON ura.user_id = u.id AND ura.is_active = 1 AND ura.ended_at IS NULL\n    WHERE u.id = ?\n    LIMIT 1\n");
$stmt->bind_param('i', $id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    set_flash('error', 'User not found.');
    header('Location: /harvest/modules/admin/users/index.php');
    exit;
}

$roles = $conn->query('SELECT id, name, code FROM roles ORDER BY name ASC');
$departments = $conn->query('SELECT id, name, code FROM departments WHERE is_active = 1 ORDER BY name ASC');

app_require('app/includes/header.php');
page_card_start('Edit User', 'Update the account, role, department, and avatar for this user.');
flash_message($flash);
?>

<form action="/harvest/admin/users/update.php" method="POST" enctype="multipart/form-data" class="space-y-6">
    <input type="hidden" name="id" value="<?= (int)$user['id'] ?>">

    <div class="grid gap-6 lg:grid-cols-[0.9fr_1.1fr]">
        <div class="rounded-3xl bg-white dark:bg-slate-900 p-6 ring-1 ring-slate-200 dark:ring-slate-800">
            <h2 class="text-xl font-semibold mb-5">Avatar & Account</h2>
            <div class="flex flex-col items-center text-center">
                <img id="adminUserAvatarPreview" src="<?= htmlspecialchars(app_user_avatar($user['profile_image_path'] ?? null)) ?>" alt="User avatar" class="h-28 w-28 rounded-[2rem] object-cover ring-2 ring-slate-200 dark:ring-slate-700 shadow-sm">
                <div class="mt-4 text-lg font-semibold text-slate-900 dark:text-white"><?= htmlspecialchars(trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?: ($user['username'] ?? 'User')) ?></div>
                <div class="mt-1 text-sm text-slate-500 dark:text-slate-400"><?= htmlspecialchars($user['email']) ?></div>
            </div>
            <div class="mt-6 space-y-4">
                <div>
                    <label class="block text-sm font-semibold mb-2">Profile Picture</label>
                    <input type="file" name="profile_image" accept="image/*" data-image-input-preview="adminUserAvatarPreview" class="<?= ui_input_class() ?> file:mr-4 file:rounded-xl file:border-0 file:bg-blue-600 file:px-4 file:py-2 file:font-semibold file:text-white hover:file:bg-blue-700">
                    <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Optional. Upload a photo to show beside the user's name across the system.</p>
                </div>
                <div>
                    <label class="block text-sm font-semibold mb-2">Email</label>
                    <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required class="<?= ui_input_class() ?>">
                </div>
                <div>
                    <label class="block text-sm font-semibold mb-2">Username</label>
                    <input type="text" name="username" value="<?= htmlspecialchars($user['username']) ?>" required class="<?= ui_input_class() ?>">
                </div>
                <div>
                    <label class="block text-sm font-semibold mb-2">New Password</label>
                    <input type="password" name="password" class="<?= ui_input_class() ?>">
                    <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Leave blank to keep the current password.</p>
                </div>
            </div>
        </div>

        <div class="rounded-3xl bg-white dark:bg-slate-900 p-6 ring-1 ring-slate-200 dark:ring-slate-800">
            <h2 class="text-xl font-semibold mb-5">Profile Information</h2>
            <div class="grid gap-4 md:grid-cols-2">
                <div><label class="block text-sm font-semibold mb-2">Employee Number</label><input type="text" name="employee_no" value="<?= htmlspecialchars($user['employee_no'] ?? '') ?>" class="<?= ui_input_class() ?>"></div>
                <div><label class="block text-sm font-semibold mb-2">Phone</label><input type="text" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" class="<?= ui_input_class() ?>"></div>
                <div><label class="block text-sm font-semibold mb-2">First Name</label><input type="text" name="first_name" value="<?= htmlspecialchars($user['first_name'] ?? '') ?>" required class="<?= ui_input_class() ?>"></div>
                <div><label class="block text-sm font-semibold mb-2">Middle Name</label><input type="text" name="middle_name" value="<?= htmlspecialchars($user['middle_name'] ?? '') ?>" class="<?= ui_input_class() ?>"></div>
                <div><label class="block text-sm font-semibold mb-2">Last Name</label><input type="text" name="last_name" value="<?= htmlspecialchars($user['last_name'] ?? '') ?>" required class="<?= ui_input_class() ?>"></div>
                <div><label class="block text-sm font-semibold mb-2">Suffix</label><input type="text" name="suffix_name" value="<?= htmlspecialchars($user['suffix_name'] ?? '') ?>" class="<?= ui_input_class() ?>"></div>
                <div class="md:col-span-2"><label class="block text-sm font-semibold mb-2">Job Title</label><input type="text" name="job_title" value="<?= htmlspecialchars($user['job_title'] ?? '') ?>" class="<?= ui_input_class() ?>"></div>
                <div>
                    <label class="block text-sm font-semibold mb-2">Role</label>
                    <select name="role_id" required class="<?= ui_select_class() ?>">
                        <option value="">Select role</option>
                        <?php while ($role = $roles->fetch_assoc()): ?>
                            <option value="<?= (int)$role['id'] ?>" <?= ((int)($user['role_id'] ?? 0) === (int)$role['id']) ? 'selected' : '' ?>><?= htmlspecialchars($role['name']) ?> (<?= htmlspecialchars($role['code']) ?>)</option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold mb-2">Primary Department</label>
                    <select name="department_id" required class="<?= ui_select_class() ?>">
                        <option value="">Select department</option>
                        <?php while ($dept = $departments->fetch_assoc()): ?>
                            <option value="<?= (int)$dept['id'] ?>" <?= ((int)($user['primary_department_id'] ?? 0) === (int)$dept['id']) ? 'selected' : '' ?>><?= htmlspecialchars($dept['name']) ?> (<?= htmlspecialchars($dept['code']) ?>)</option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-semibold mb-2">Employment Status</label>
                    <select name="employment_status" required class="<?= ui_select_class() ?>">
                        <?php foreach (['active' => 'Active', 'inactive' => 'Inactive', 'on_leave' => 'On Leave', 'retired' => 'Retired'] as $value => $label): ?>
                            <option value="<?= $value ?>" <?= (($user['employment_status'] ?? 'active') === $value) ? 'selected' : '' ?>><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <div class="flex gap-3">
        <?php ui_primary_button('Update User', 'save'); ?>
        <?php action_button('/harvest/modules/admin/users/index.php', 'Cancel', 'x', 'secondary'); ?>
    </div>
</form>

<?php page_card_end(); app_require('app/includes/footer.php'); ?>
