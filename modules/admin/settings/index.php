<?php
require_once dirname(__DIR__, 3) . '/app/bootstrap.php';

$conn = db_conn();
require_once app_path('app/config/database.php');
app_require('app/includes/auth.php');
app_require('app/includes/flash.php');
app_require('app/includes/ui.php');
app_require('app/includes/helpers/core.php');
app_require('app/includes/helpers/branding.php');
app_require('modules/family/portal_helpers.php');

require_role(['developer', 'super_admin', 'system_admin', 'admin']);
ensure_family_portal_control_settings($conn);
$flash = get_flash();
$isDeveloper = current_user_is_developer();

app_require('app/includes/header.php');
page_card_start('System Settings', 'Control branding, uploads, notifications, role experience, and family portal access.');
flash_message($flash);
?>
<div class="mb-6 grid gap-4 xl:grid-cols-3">
    <div class="rounded-[1.5rem] border border-slate-200 bg-white p-5 shadow-sm">
        <div class="text-sm text-slate-500">Branding quick links</div>
        <div class="mt-2 text-xl font-black">No-code system identity</div>
        <p class="mt-2 text-sm text-slate-500">Change logo, login labels, report titles, browser titles, and mayor-facing headings from one workspace.</p>
    </div>
    <div class="rounded-[1.5rem] border border-slate-200 bg-white p-5 shadow-sm">
        <div class="text-sm text-slate-500">Developer goal</div>
        <div class="mt-2 text-xl font-black">Less code touching</div>
        <p class="mt-2 text-sm text-slate-500">Use settings first before editing PHP files directly for titles, labels, and presentation copy.</p>
    </div>
    <div class="rounded-[1.5rem] border border-slate-200 bg-white p-5 shadow-sm">
        <div class="text-sm text-slate-500">Next place to review</div>
        <div class="mt-2 text-xl font-black">Role dashboards and forms</div>
        <p class="mt-2 text-sm text-slate-500">Shared layout spacing is now wider, but each role page can still be tuned one by one.</p>
    </div>
</div>

<div class="mb-6 flex flex-wrap gap-3">
    <a href="<?= e(app_url('modules/admin/settings/branding.php')) ?>" class="app-btn-primary">Open Branding Workspace</a>
    <a href="<?= e(app_url('modules/admin/users/index.php')) ?>" class="app-btn-outline">Manage Users</a>
</div>

<form action="<?= e(app_url('modules/admin/settings/update.php')) ?>" method="POST" class="space-y-8">
    <div class="rounded-3xl bg-white dark:bg-slate-900 p-6 shadow-sm ring-1 ring-slate-200 dark:ring-slate-800">
        <h2 class="text-xl font-semibold mb-5">General</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            <div class="md:col-span-2">
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Application Name</label>
                <input type="text" name="system_title" value="<?= e(app_setting($conn, 'system_title', 'HARVEST System')) ?>" class="<?= ui_input_class() ?>">
            </div>
            <div class="md:col-span-2">
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Application Subtitle</label>
                <input type="text" name="system_subtitle" value="<?= e(app_setting($conn, 'system_subtitle', 'Matag-ob Monitoring and Decision Support')) ?>" class="<?= ui_input_class() ?>">
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Maximum Upload Size (MB)</label>
                <input type="number" name="documents_max_upload_size_mb" value="<?= e(app_setting($conn, 'documents.max_upload_size_mb', '25')) ?>" class="<?= ui_input_class() ?>">
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Overdue Task Threshold (days)</label>
                <input type="number" name="dashboard_overdue_days_threshold" value="<?= e(app_setting($conn, 'dashboard.overdue_days_threshold', '3')) ?>" class="<?= ui_input_class() ?>">
            </div>
        </div>
    </div>

    <?php if ($isDeveloper): ?>
    <div class="rounded-3xl bg-white dark:bg-slate-900 p-6 shadow-sm ring-1 ring-slate-200 dark:ring-slate-800">
        <div class="flex items-start justify-between gap-4 flex-wrap mb-5">
            <div>
                <h2 class="text-xl font-semibold">Family Portal Controls</h2>
                <p class="text-sm text-slate-500 mt-1">Developer-only switches for the entire family portal. Turn off Scan QR, dashboards, or submissions without deleting code.</p>
            </div>
            <div class="text-sm text-slate-500">Developer control only</div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            <?php
            $toggles = [
                'family_portal_enabled' => ['Master family portal', 'Turn the entire family portal on or off.'],
                'family_scan_enabled' => ['Show Scan QR', 'Show or hide the Scan QR card on the login page.'],
                'family_dashboard_enabled' => ['Allow family dashboard', 'Allow or block dashboard pages such as Family Dashboard, My Crops, and Timeline.'],
                'family_submission_enabled' => ['Allow family submissions', 'Allow or block harvest, crop, and photo submissions from family accounts.'],
            ];
            foreach ($toggles as $key => [$label, $hint]): ?>
                <div class="rounded-2xl border border-slate-200 p-4">
                    <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2"><?= e($label) ?></label>
                    <select name="<?= e($key) ?>" class="<?= ui_select_class() ?>">
                        <option value="1" <?= app_setting($conn, $key, '1') === '1' ? 'selected' : '' ?>>Enabled</option>
                        <option value="0" <?= app_setting($conn, $key, '1') === '0' ? 'selected' : '' ?>>Disabled</option>
                    </select>
                    <p class="mt-2 text-sm text-slate-500"><?= e($hint) ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="flex flex-wrap gap-3">
        <?php ui_primary_button('Save Settings', 'save'); ?>
    </div>
</form>

<?php
page_card_end();
app_require('app/includes/footer.php');
?>
