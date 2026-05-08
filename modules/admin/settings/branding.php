<?php
require_once dirname(__DIR__, 3) . '/app/bootstrap.php';
app_require('app/includes/auth.php');
require_role(['developer','admin']);
$conn = db_conn();
ensure_user_account_schema($conn);
$user = current_user();

$settingGroups = [
    'System identity' => [
        'system_title' => ['label' => 'System title', 'default' => 'HARVEST System'],
        'system_subtitle' => ['label' => 'System subtitle', 'default' => 'Matag-ob Monitoring and Decision Support'],
        'system_loader_text' => ['label' => 'Loader text', 'default' => 'Loading HARVEST System workspace...'],
        'system_browser_title_suffix' => ['label' => 'Browser title suffix', 'default' => 'HARVEST System'],
        'system_header_search_placeholder' => ['label' => 'Global search placeholder', 'default' => 'Search family member, head, code, or contact'],
    ],
    'Reports and exports' => [
        'system_report_title' => ['label' => 'Main report title', 'default' => 'HARVEST System Consolidated Family Report'],
        'system_report_subtitle' => ['label' => 'Main report subtitle', 'default' => 'Harvest Assistance for Resource Validation, Evaluation, and Strategic Tracking'],
        'operational_report_title' => ['label' => 'Operational report title', 'default' => 'HARVEST System Operational Dashboard Report'],
        'operational_report_subtitle' => ['label' => 'Operational report subtitle', 'default' => 'Harvest Assistance for Resource Validation, Evaluation, and Strategic Tracking'],
        'system_reports_page_title' => ['label' => 'Reports page heading', 'default' => 'Operational family reports'],
        'system_reports_page_description' => ['label' => 'Reports page intro', 'default' => 'Review qualification load, compare barangays, spot suspicious household sizes, and export a cleaner report package for field validation.', 'type' => 'textarea'],
        'system_reports_export_note' => ['label' => 'Reports export note', 'default' => 'Exports follow the same barangay, status, and profile filters shown below.', 'type' => 'textarea'],
        'system_operational_page_title' => ['label' => 'Operational print heading', 'default' => 'Operational dashboard print report'],
        'system_operational_page_description' => ['label' => 'Operational print intro', 'default' => 'Printable executive summary for filtered households, barangays, and qualification status.', 'type' => 'textarea'],
    ],
    'Login page' => [
        'login_browser_title' => ['label' => 'Browser tab title', 'default' => 'Login - HARVEST System'],
        'login_badge_label' => ['label' => 'Login badge label', 'default' => 'Unified Login'],
        'login_panel_caption' => ['label' => 'Login panel caption', 'default' => 'Unified role-based municipal system'],
        'login_intro_badge_text' => ['label' => 'Login intro badge text', 'default' => 'HARVEST System'],
        'login_intro_description' => ['label' => 'Login intro description', 'default' => 'Agricultural resource validation, evaluation, and strategic tracking in one platform.'],
        'login_hero_title' => ['label' => 'Login hero title', 'default' => 'A smarter digital platform for agricultural validation, monitoring, and tracking.', 'type' => 'textarea'],
        'login_hero_body' => ['label' => 'Login hero body', 'default' => 'Built from your old project style, upgraded with resource profiling, crop QR lookup, automated evaluation, notifications, responsive dashboards, and dark mode.', 'type' => 'textarea'],
        'login_card_title' => ['label' => 'Login card title', 'default' => 'Welcome back'],
        'login_card_subtitle' => ['label' => 'Login card subtitle', 'default' => 'Sign in to access your automation dashboard.'],
        'login_feature_one_title' => ['label' => 'Feature box 1 title', 'default' => 'Login first'],
        'login_feature_one_body' => ['label' => 'Feature box 1 body', 'default' => 'Users no longer choose a module first. The system reads the account role and opens the correct workspace automatically.', 'type' => 'textarea'],
        'login_feature_two_title' => ['label' => 'Feature box 2 title', 'default' => 'Household-first data'],
        'login_feature_two_body' => ['label' => 'Feature box 2 body', 'default' => 'Households, families, and members stay in one shared database, with HH numbers coming only from source Excel data.', 'type' => 'textarea'],
        'login_access_note' => ['label' => 'Login access note', 'default' => 'Task Force, CBMS, Mayor, Beneficiaries, and Developer use this same sign-in page.', 'type' => 'textarea'],
        'login_submit_label' => ['label' => 'Login button label', 'default' => 'Sign In'],
        'family_access_title' => ['label' => 'Family access card title', 'default' => 'Family access'],
        'family_access_description' => ['label' => 'Family access card description', 'default' => 'Families can scan or enter their QR reference to open their own dashboard.', 'type' => 'textarea'],
        'family_access_button_label' => ['label' => 'Family access button label', 'default' => 'Scan QR'],
    ],
    'Mayor workspace' => [
        'mayor_dashboard_title' => ['label' => 'Mayor dashboard heading', 'default' => 'Mayor decision dashboard'],
        'mayor_dashboard_description' => ['label' => 'Mayor dashboard intro', 'default' => 'Executive view of households, interventions, barangay insights, and support queues.', 'type' => 'textarea'],
    ],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $didChange = false;
    foreach ($settingGroups as $group => $fields) {
        foreach ($fields as $key => $meta) {
            $value = trim((string)post($key));
            $default = (string)($meta['default'] ?? '');
            $final = $value !== '' ? $value : $default;
            if (set_app_setting($conn, $key, $final, $meta['label'] ?? $key)) {
                $didChange = true;
            }
        }
    }

    if (!empty($_FILES['system_logo']['name'])) {
        if (update_system_logo($conn, $_FILES['system_logo'], (int)$user['id'])) {
            $didChange = true;
        } else {
            set_flash('error', 'Unable to update system logo.');
            header('Location: ' . app_url('modules/admin/settings/branding.php'));
            exit;
        }
    }

    set_flash('success', $didChange ? 'Branding workspace updated successfully.' : 'No changes were saved.');
    header('Location: ' . app_url('modules/admin/settings/branding.php'));
    exit;
}

app_require('app/includes/header.php');
$logoUrl = system_logo_url($conn);
?>
<section class="space-y-6">
    <div class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex items-center justify-between gap-3 flex-wrap">
            <div>
                <div class="text-sm text-slate-500">Developer branding workspace</div>
                <h2 class="text-3xl font-black">Control titles, labels, reports, and login branding</h2>
                <p class="mt-2 max-w-3xl text-sm text-slate-500">Everything here is meant to reduce code edits. Update the logo, browser title, login text, report title, and search placeholders from one place.</p>
            </div>
            <div class="flex gap-3 flex-wrap">
                <a href="<?= e(app_url('modules/admin/settings/index.php')) ?>" class="app-btn-outline">General settings</a>
                <a href="<?= e(app_url('modules/admin/users/index.php')) ?>" class="app-btn-outline">User tools</a>
            </div>
        </div>
    </div>

    <div class="grid gap-6 2xl:grid-cols-[380px_1fr] xl:grid-cols-[320px_1fr]">
        <aside class="space-y-6">
            <section class="rounded-[1.75rem] border border-slate-200 bg-slate-50 p-6 shadow-sm sticky top-24">
                <div class="text-sm text-slate-500">Live preview</div>
                <div class="mt-4 flex items-center gap-4">
                    <img src="<?= e($logoUrl) ?>" alt="Current logo" id="currentLogoPreview" class="h-24 w-24 rounded-[1.75rem] object-cover border border-slate-200 bg-white">
                    <div>
                        <div class="font-black text-xl"><?= e(system_title($conn)) ?></div>
                        <div class="text-sm text-slate-500"><?= e(system_subtitle($conn)) ?></div>
                    </div>
                </div>
                <div class="mt-5 grid gap-3">
                    <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3">
                        <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Login tab</div>
                        <div class="mt-1 font-semibold text-slate-900"><?= e(system_browser_page_title($conn, 'Login')) ?></div>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3">
                        <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Report title</div>
                        <div class="mt-1 font-semibold text-slate-900"><?= e(system_report_title($conn)) ?></div>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3">
                        <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Mayor heading</div>
                        <div class="mt-1 font-semibold text-slate-900"><?= e(mayor_dashboard_title($conn)) ?></div>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3">
                        <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Search placeholder</div>
                        <div class="mt-1 font-semibold text-slate-900"><?= e(system_header_search_placeholder($conn)) ?></div>
                    </div>
                </div>
            </section>
        </aside>

        <form method="POST" enctype="multipart/form-data" class="space-y-6">
            <section class="rounded-[1.75rem] border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex items-start justify-between gap-4 flex-wrap">
                    <div>
                        <div class="text-sm text-slate-500">System icon</div>
                        <h3 class="text-2xl font-black">Logo and visual identity</h3>
                    </div>
                    <div class="text-sm text-slate-500">Recommended: square PNG or JPG</div>
                </div>
                <div class="mt-5 grid gap-4 md:grid-cols-[180px_1fr] items-start">
                    <div class="rounded-[1.5rem] border border-dashed border-slate-300 bg-slate-50 p-4">
                        <img src="<?= e($logoUrl) ?>" alt="Logo preview" id="logoPreview" class="h-32 w-32 rounded-[1.5rem] object-cover border border-slate-200 bg-white">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold mb-2">Choose logo image</label>
                        <input type="file" name="system_logo" accept="image/*" id="systemLogoInput" class="w-full rounded-2xl border border-slate-300 px-4 py-3">
                        <div class="mt-2 text-xs text-slate-500">This updates the upper-left header logo and login page logo.</div>
                    </div>
                </div>
            </section>

            <?php foreach ($settingGroups as $groupTitle => $fields): ?>
                <section class="rounded-[1.75rem] border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="text-sm text-slate-500"><?= e($groupTitle) ?></div>
                    <h3 class="mt-1 text-2xl font-black"><?= e($groupTitle) ?></h3>
                    <div class="mt-5 branding-form-grid">
                        <?php foreach ($fields as $key => $meta):
                            $label = $meta['label'] ?? $key;
                            $default = (string)($meta['default'] ?? '');
                            $type = $meta['type'] ?? 'text';
                            $value = app_setting($conn, $key, $default);
                            $full = $type === 'textarea' || strlen($value) > 70;
                        ?>
                            <div class="<?= $full ? 'md:col-span-2' : '' ?>">
                                <label class="block text-sm font-semibold mb-2"><?= e($label) ?></label>
                                <?php if ($type === 'textarea'): ?>
                                    <textarea name="<?= e($key) ?>" rows="3" class="w-full rounded-2xl border border-slate-300 px-4 py-3"><?= e($value) ?></textarea>
                                <?php else: ?>
                                    <input type="text" name="<?= e($key) ?>" value="<?= e($value) ?>" class="w-full rounded-2xl border border-slate-300 px-4 py-3">
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endforeach; ?>

            <div class="flex justify-end">
                <button class="app-btn-primary">Save branding workspace</button>
            </div>
        </form>
    </div>
</section>
<script>
(function(){
    const input = document.getElementById('systemLogoInput');
    const preview = document.getElementById('logoPreview');
    const current = document.getElementById('currentLogoPreview');
    if (!input) return;
    input.addEventListener('change', function(){
        const file = this.files && this.files[0];
        if (!file) return;
        const url = URL.createObjectURL(file);
        if (preview) preview.src = url;
        if (current) current.src = url;
    });
})();
</script>
<?php app_require('app/includes/footer.php'); ?>
