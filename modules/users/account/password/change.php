<?php
require_once dirname(__DIR__, 4) . '/app/bootstrap.php';
app_require('app/includes/auth.php');
app_require('app/includes/flash.php');
app_require('app/includes/ui.php');

require_login();
$flash = get_flash();

app_require('app/includes/header.php');

page_card_start('Change Password', 'Update your password with the refreshed security form and visibility toggles.');
flash_message($flash);
?>

<form action="/harvest/password/update.php" method="POST" class="space-y-8">
    <div class="grid grid-cols-1 xl:grid-cols-[1fr_0.48fr] gap-6">
        <div class="rounded-3xl bg-white dark:bg-slate-900 p-6 shadow-sm ring-1 ring-slate-200 dark:ring-slate-800">
            <h2 class="text-xl font-semibold mb-5 text-slate-900 dark:text-white">Password Details</h2>

            <div class="grid grid-cols-1 gap-5 max-w-2xl">
                <div>
                    <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Current Password</label>
                    <div class="relative">
                        <input type="password" id="current_password" name="current_password" required class="<?= ui_input_class() ?> pr-12">
                        <button type="button" data-password-toggle-target="current_password" class="password-toggle-button absolute inset-y-0 right-3 inline-flex items-center justify-center">
                            <i data-lucide="eye" class="w-5 h-5"></i>
                        </button>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">New Password</label>
                    <div class="relative">
                        <input type="password" id="new_password" name="new_password" required class="<?= ui_input_class() ?> pr-12">
                        <button type="button" data-password-toggle-target="new_password" class="password-toggle-button absolute inset-y-0 right-3 inline-flex items-center justify-center">
                            <i data-lucide="eye" class="w-5 h-5"></i>
                        </button>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Confirm New Password</label>
                    <div class="relative">
                        <input type="password" id="confirm_new_password" name="confirm_new_password" required class="<?= ui_input_class() ?> pr-12">
                        <button type="button" data-password-toggle-target="confirm_new_password" class="password-toggle-button absolute inset-y-0 right-3 inline-flex items-center justify-center">
                            <i data-lucide="eye" class="w-5 h-5"></i>
                        </button>
                    </div>
                </div>
            </div>

            <div class="mt-6 flex flex-wrap gap-3">
                <?php ui_primary_button('Update Password', 'key-round'); ?>
            </div>
        </div>

        <aside class="rounded-3xl bg-slate-900 text-white p-6 shadow-xl">
            <h2 class="text-xl font-semibold">Security tips</h2>
            <ul class="mt-4 space-y-3 text-sm leading-6 text-slate-300">
                <li class="flex gap-3"><i data-lucide="shield" class="w-4 h-4 mt-1 text-blue-300"></i><span>Use a password that is hard to guess and not reused elsewhere.</span></li>
                <li class="flex gap-3"><i data-lucide="sparkles" class="w-4 h-4 mt-1 text-blue-300"></i><span>Mix uppercase, lowercase, numbers, and symbols if your policy allows it.</span></li>
                <li class="flex gap-3"><i data-lucide="eye-off" class="w-4 h-4 mt-1 text-blue-300"></i><span>You can use the eye buttons to reveal each field before submitting.</span></li>
            </ul>
        </aside>
    </div>
</form>

<?php
page_card_end();
app_require('app/includes/footer.php');
?>