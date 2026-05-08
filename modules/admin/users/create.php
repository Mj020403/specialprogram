<?php
require_once dirname(__DIR__, 3) . '/app/bootstrap.php';

$conn = db_conn();require_once app_path('app/config/database.php');
app_require('app/includes/auth.php');
app_require('app/includes/ui.php');

require_role(['developer','super_admin', 'system_admin', 'admin']);
$roles = $conn->query('SELECT id, name, code FROM roles ORDER BY name ASC');
$departments = $conn->query('SELECT id, name, code FROM departments WHERE is_active = 1 ORDER BY name ASC');

app_require('app/includes/header.php');
page_card_start('Add New User', 'Create a clean account record. You can also upload an avatar immediately.');
?>

<form action="/harvest/admin/users/store.php" method="POST" enctype="multipart/form-data" class="space-y-6">
    <div class="grid gap-6 lg:grid-cols-[0.9fr_1.1fr]">
        <div class="rounded-3xl bg-white dark:bg-slate-900 p-6 ring-1 ring-slate-200 dark:ring-slate-800">
            <h2 class="text-xl font-semibold mb-5">Avatar & Account</h2>
            <div class="flex justify-center mb-4"><img id="adminUserAvatarPreview" src="<?= htmlspecialchars(app_user_avatar()) ?>" alt="Avatar preview" class="app-avatar-preview"></div>
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-semibold mb-2">Profile Picture</label>
                    <input type="file" name="profile_image" accept="image/*" data-image-input-preview="adminUserAvatarPreview" class="<?= ui_input_class() ?> file:mr-4 file:rounded-xl file:border-0 file:bg-blue-600 file:px-4 file:py-2 file:font-semibold file:text-white hover:file:bg-blue-700">
                </div>
                <div><label class="block text-sm font-semibold mb-2">Email</label><input type="email" name="email" required class="<?= ui_input_class() ?>"></div>
                <div><label class="block text-sm font-semibold mb-2">Username</label><input type="text" name="username" required class="<?= ui_input_class() ?>"></div>
                <div><label class="block text-sm font-semibold mb-2">Password</label><input type="password" name="password" required class="<?= ui_input_class() ?>"></div>
            </div>
        </div>
        <div class="rounded-3xl bg-white dark:bg-slate-900 p-6 ring-1 ring-slate-200 dark:ring-slate-800">
            <h2 class="text-xl font-semibold mb-5">Profile Information</h2>
            <div class="grid gap-4 md:grid-cols-2">
                <div><label class="block text-sm font-semibold mb-2">Employee Number</label><input type="text" name="employee_no" class="<?= ui_input_class() ?>"></div>
                <div><label class="block text-sm font-semibold mb-2">Phone</label><input type="text" name="phone" class="<?= ui_input_class() ?>"></div>
                <div><label class="block text-sm font-semibold mb-2">First Name</label><input type="text" name="first_name" required class="<?= ui_input_class() ?>"></div>
                <div><label class="block text-sm font-semibold mb-2">Middle Name</label><input type="text" name="middle_name" class="<?= ui_input_class() ?>"></div>
                <div><label class="block text-sm font-semibold mb-2">Last Name</label><input type="text" name="last_name" required class="<?= ui_input_class() ?>"></div>
                <div><label class="block text-sm font-semibold mb-2">Suffix</label><input type="text" name="suffix_name" class="<?= ui_input_class() ?>"></div>
                <div class="md:col-span-2"><label class="block text-sm font-semibold mb-2">Job Title</label><input type="text" name="job_title" class="<?= ui_input_class() ?>"></div>
                <div><label class="block text-sm font-semibold mb-2">Role</label><select name="role_id" required class="<?= ui_select_class() ?>"><option value="">Select role</option><?php while ($role = $roles->fetch_assoc()): ?><option value="<?= (int)$role['id'] ?>"><?= htmlspecialchars($role['name']) ?> (<?= htmlspecialchars($role['code']) ?>)</option><?php endwhile; ?></select></div>
                <div><label class="block text-sm font-semibold mb-2">Primary Department</label><select name="department_id" required class="<?= ui_select_class() ?>"><option value="">Select department</option><?php while ($dept = $departments->fetch_assoc()): ?><option value="<?= (int)$dept['id'] ?>"><?= htmlspecialchars($dept['name']) ?> (<?= htmlspecialchars($dept['code']) ?>)</option><?php endwhile; ?></select></div>
                <div class="md:col-span-2"><label class="block text-sm font-semibold mb-2">Employment Status</label><select name="employment_status" required class="<?= ui_select_class() ?>"><option value="active">Active</option><option value="inactive">Inactive</option><option value="on_leave">On Leave</option><option value="retired">Retired</option></select></div>
            </div>
        </div>
    </div>
    <div class="flex gap-3">
        <?php ui_primary_button('Save User', 'save'); ?>
        <?php action_button('/harvest/modules/admin/users/index.php', 'Cancel', 'x', 'secondary'); ?>
    </div>
</form>

<?php page_card_end(); app_require('app/includes/footer.php'); ?>
