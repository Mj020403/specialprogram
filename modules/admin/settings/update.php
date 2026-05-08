<?php
require_once dirname(__DIR__, 3) . '/app/bootstrap.php';

$conn = db_conn();
require_once app_path('app/config/database.php');
app_require('app/includes/auth.php');
app_require('app/includes/flash.php');
app_require('app/includes/helpers/core.php');
app_require('app/includes/helpers/branding.php');
app_require('modules/family/portal_helpers.php');

require_role(['developer', 'super_admin', 'system_admin', 'admin']);
ensure_family_portal_control_settings($conn);
$isDeveloper = current_user_is_developer();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . app_url('modules/admin/settings/index.php'));
    exit;
}

$updates = [
    'system_title' => ['value' => trim((string)post('system_title')), 'description' => 'System title shown across HARVEST.'],
    'system_subtitle' => ['value' => trim((string)post('system_subtitle')), 'description' => 'System subtitle shown across HARVEST.'],
    'documents.max_upload_size_mb' => ['value' => trim((string)post('documents_max_upload_size_mb')), 'description' => 'Maximum upload size in megabytes.'],
    'dashboard.overdue_days_threshold' => ['value' => trim((string)post('dashboard_overdue_days_threshold')), 'description' => 'Number of days before tasks become overdue.'],
];

if ($isDeveloper) {
    foreach (['family_portal_enabled', 'family_scan_enabled', 'family_dashboard_enabled', 'family_submission_enabled'] as $key) {
        $updates[$key] = [
            'value' => post($key) === '0' ? '0' : '1',
            'description' => 'Developer control for the family portal.',
        ];
    }
}

foreach ($updates as $key => $payload) {
    $value = $payload['value'];
    if ($value === '' && in_array($key, ['system_title', 'system_subtitle'], true)) {
        continue;
    }
    set_app_setting($conn, $key, (string)$value, $payload['description']);
}

set_flash('success', $isDeveloper ? 'System settings and family portal controls were updated.' : 'System settings were updated.');
header('Location: ' . app_url('modules/admin/settings/index.php'));
exit;
