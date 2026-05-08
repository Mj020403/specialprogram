<?php
require_once dirname(__DIR__, 3) . '/app/bootstrap.php';
app_require('app/includes/auth.php');
require_login();
$conn = db_conn();
ensure_user_account_schema($conn);
$user = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current = (string)post('current_password');
    $new = (string)post('new_password');
    $confirm = (string)post('confirm_password');
    if ($new !== $confirm) {
        set_flash('error', 'New password and confirmation do not match.');
    } else {
        $error = null;
        if (change_user_password($conn, (int)$user['id'], $current, $new, $error)) {
            set_flash('success', 'Password changed successfully.');
        } else {
            set_flash('error', $error ?: 'Unable to change password.');
        }
    }
    header('Location: ' . app_url('modules/users/account/change_password.php'));
    exit;
}

app_require('app/includes/header.php');
?>
<section class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm max-w-3xl">
    <div class="flex items-center justify-between gap-3 flex-wrap">
        <div>
            <div class="text-sm text-slate-500">Account security</div>
            <h2 class="text-3xl font-black">Change password</h2>
        </div>
        <a href="<?= e(app_url('modules/users/account/activity.php')) ?>" class="app-btn-outline">View account activity</a>
    </div>
    <form method="POST" class="mt-6 grid gap-4 md:grid-cols-2">
        <div class="md:col-span-2 rounded-2xl bg-emerald-50 px-4 py-3 text-sm text-emerald-900 border border-emerald-100">
            Use at least 8 characters. A stronger password is easier to protect.
        </div>
        <div class="md:col-span-2">
            <label class="block text-sm font-semibold mb-2">Current password</label>
            <div class="relative"><input type="password" name="current_password" required class="w-full rounded-2xl border border-slate-300 px-4 py-3 pr-12" data-password-field><button type="button" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-500" data-password-toggle>Show</button></div>
        </div>
        <div>
            <label class="block text-sm font-semibold mb-2">New password</label>
            <div class="relative"><input type="password" name="new_password" required class="w-full rounded-2xl border border-slate-300 px-4 py-3 pr-12" data-password-field><button type="button" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-500" data-password-toggle>Show</button></div>
        </div>
        <div>
            <label class="block text-sm font-semibold mb-2">Confirm password</label>
            <div class="relative"><input type="password" name="confirm_password" required class="w-full rounded-2xl border border-slate-300 px-4 py-3 pr-12" data-password-field><button type="button" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-500" data-password-toggle>Show</button></div>
        </div>
        <div class="md:col-span-2 flex justify-end">
            <button class="app-btn-primary">Save new password</button>
        </div>
    </form>
</section>
<script>
document.querySelectorAll('[data-password-toggle]').forEach(function(btn){
    btn.addEventListener('click', function(){
        const input = btn.parentElement.querySelector('[data-password-field]');
        if (!input) return;
        const isPassword = input.type === 'password';
        input.type = isPassword ? 'text' : 'password';
        btn.textContent = isPassword ? 'Hide' : 'Show';
    });
});
</script>
<?php app_require('app/includes/footer.php'); ?>
