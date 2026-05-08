<?php

require_once __DIR__ . '/core.php';

function upload_system_logo(array $file): ?string {
    return upload_image_file($file, 'public/uploads/system');
}

function system_logo_path(mysqli $conn): ?string {
    $value = app_setting($conn, 'system_logo_path', '');
    return $value !== '' ? $value : null;
}

function system_logo_url(mysqli $conn): string {
    $path = system_logo_path($conn);
    if ($path && trim($path) !== '') return user_avatar_url($path);
    return app_url('assets/img/logo.png');
}

function update_system_logo(mysqli $conn, array $file, int $actorUserId): bool {
    $uploaded = upload_system_logo($file);
    if (!$uploaded) return false;
    if (table_exists($conn, 'system_settings')) {
        $safe = $conn->real_escape_string($uploaded);
        $exists = (int)scalar($conn, "SELECT COUNT(*) FROM system_settings WHERE setting_key='system_logo_path'", 0);
        if ($exists > 0) {
            $ok = $conn->query("UPDATE system_settings SET setting_value='{$safe}', updated_at=NOW() WHERE setting_key='system_logo_path'");
        } else {
            $ok = $conn->query("INSERT INTO system_settings (setting_key, setting_value, updated_at) VALUES ('system_logo_path', '{$safe}', NOW())");
        }
        if ($ok) {
            log_account_activity($conn, null, $actorUserId, 'system_logo_updated', 'Updated the system logo.', ['path' => $uploaded]);
            create_notification($conn, 'System logo updated', 'The system logo was updated by the developer account.', 'Low', null, null, null, 'System Settings');
            return true;
        }
    }
    return false;
}

function set_app_setting(mysqli $conn, string $key, string $value, string $description = ''): bool {
    if (!table_exists($conn, 'system_settings')) return false;
    $safeKey = $conn->real_escape_string($key);
    $safeVal = $conn->real_escape_string($value);
    $safeDesc = $conn->real_escape_string($description);
    $exists = (int)scalar($conn, "SELECT COUNT(*) FROM system_settings WHERE setting_key='{$safeKey}'", 0);
    if ($exists > 0) {
        return (bool)$conn->query("UPDATE system_settings SET setting_value='{$safeVal}', description=IF(description IS NULL OR description='', '{$safeDesc}', description), updated_at=NOW() WHERE setting_key='{$safeKey}'");
    }
    return (bool)$conn->query("INSERT INTO system_settings (setting_key, setting_value, description, updated_at) VALUES ('{$safeKey}', '{$safeVal}', '{$safeDesc}', NOW())");
}

function system_title(mysqli $conn): string {
    return app_setting($conn, 'system_title', 'HARVEST System');
}

function system_subtitle(mysqli $conn): string {
    return app_setting($conn, 'system_subtitle', 'Matag-ob Monitoring and Decision Support');
}

function system_loader_text(mysqli $conn): string {
    return app_setting($conn, 'system_loader_text', 'Loading ' . system_title($conn) . ' workspace...');
}

function system_report_title(mysqli $conn): string {
    return app_setting($conn, 'system_report_title', system_title($conn) . ' Consolidated Family Report');
}

function system_report_subtitle(mysqli $conn): string {
    return app_setting($conn, 'system_report_subtitle', 'Harvest Assistance for Resource Validation, Evaluation, and Strategic Tracking');
}

function login_intro_badge_text(mysqli $conn): string {
    return app_setting($conn, 'login_intro_badge_text', system_title($conn));
}

function login_intro_description(mysqli $conn): string {
    return app_setting($conn, 'login_intro_description', 'Agricultural resource validation, evaluation, and strategic tracking in one platform.');
}

function login_hero_title(mysqli $conn): string {
    return app_setting($conn, 'login_hero_title', 'A smarter digital platform for agricultural validation, monitoring, and tracking.');
}

function login_hero_body(mysqli $conn): string {
    return app_setting($conn, 'login_hero_body', 'Built from your old project style, upgraded with resource profiling, crop QR lookup, automated evaluation, notifications, responsive dashboards, and dark mode.');
}

function login_card_title(mysqli $conn): string {
    return app_setting($conn, 'login_card_title', 'Welcome back');
}

function login_card_subtitle(mysqli $conn): string {
    return app_setting($conn, 'login_card_subtitle', 'Sign in to access your automation dashboard.');
}



function branding_setting(mysqli $conn, string $key, string $default = ''): string {
    return app_setting($conn, $key, $default);
}

function system_header_search_placeholder(mysqli $conn): string {
    return branding_setting($conn, 'system_header_search_placeholder', 'Search family member, head, code, or contact');
}

function login_browser_title(mysqli $conn): string {
    return branding_setting($conn, 'login_browser_title', 'Login - ' . system_title($conn));
}

function login_panel_caption(mysqli $conn): string {
    return branding_setting($conn, 'login_panel_caption', 'Unified role-based municipal system');
}

function login_badge_label(mysqli $conn): string {
    return branding_setting($conn, 'login_badge_label', 'Unified Login');
}

function login_feature_one_title(mysqli $conn): string {
    return branding_setting($conn, 'login_feature_one_title', 'Login first');
}

function login_feature_one_body(mysqli $conn): string {
    return branding_setting($conn, 'login_feature_one_body', 'Users no longer choose a module first. The system reads the account role and opens the correct workspace automatically.');
}

function login_feature_two_title(mysqli $conn): string {
    return branding_setting($conn, 'login_feature_two_title', 'Household-first data');
}

function login_feature_two_body(mysqli $conn): string {
    return branding_setting($conn, 'login_feature_two_body', 'Households, families, and members stay in one shared database, with HH numbers coming only from source Excel data.');
}

function login_access_note(mysqli $conn): string {
    return branding_setting($conn, 'login_access_note', 'Task Force, CBMS, Mayor, Beneficiaries, and Developer use this same sign-in page.');
}

function operational_report_title(mysqli $conn): string {
    return branding_setting($conn, 'operational_report_title', system_title($conn) . ' Operational Dashboard Report');
}

function operational_report_subtitle(mysqli $conn): string {
    return branding_setting($conn, 'operational_report_subtitle', system_report_subtitle($conn));
}


function system_search_placeholder(mysqli $conn): string {
    return system_header_search_placeholder($conn);
}

function system_browser_title_suffix(mysqli $conn): string {
    return branding_setting($conn, 'system_browser_title_suffix', system_title($conn));
}

function system_browser_page_title(mysqli $conn, string $pageTitle = ''): string {
    $suffix = trim(system_browser_title_suffix($conn));
    $pageTitle = trim($pageTitle);
    if ($pageTitle === '') return $suffix !== '' ? $suffix : system_title($conn);
    if ($suffix === '') return $pageTitle;
    return $pageTitle . ' - ' . $suffix;
}

function system_reports_page_title(mysqli $conn): string {
    return branding_setting($conn, 'system_reports_page_title', 'Operational family reports');
}

function system_reports_page_description(mysqli $conn): string {
    return branding_setting($conn, 'system_reports_page_description', 'Review qualification load, compare barangays, spot suspicious household sizes, and export a cleaner report package for field validation.');
}

function system_reports_export_note(mysqli $conn): string {
    return branding_setting($conn, 'system_reports_export_note', 'Exports follow the same barangay, status, and profile filters shown below.');
}

function system_operational_page_title(mysqli $conn): string {
    return branding_setting($conn, 'system_operational_page_title', 'Operational dashboard print report');
}

function system_operational_page_description(mysqli $conn): string {
    return branding_setting($conn, 'system_operational_page_description', 'Printable executive summary for filtered households, barangays, and qualification status.');
}

function mayor_dashboard_title(mysqli $conn): string {
    return branding_setting($conn, 'mayor_dashboard_title', 'Mayor decision dashboard');
}

function mayor_dashboard_description(mysqli $conn): string {
    return branding_setting($conn, 'mayor_dashboard_description', 'Executive view of households, interventions, barangay insights, and support queues.');
}

function login_submit_label(mysqli $conn): string {
    return branding_setting($conn, 'login_submit_label', 'Sign In');
}

function family_access_title(mysqli $conn): string {
    return branding_setting($conn, 'family_access_title', 'Family access');
}

function family_access_description(mysqli $conn): string {
    return branding_setting($conn, 'family_access_description', 'Families can scan or enter their QR reference to open their own dashboard.');
}

function family_access_button_label(mysqli $conn): string {
    return branding_setting($conn, 'family_access_button_label', 'Scan QR');
}
