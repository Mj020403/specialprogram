<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/app_helpers.php';
require_once __DIR__ . '/module_platform.php';
$conn = db_conn();
apply_runtime_db_fixes($conn);
ensure_harvest_schema($conn);
ensure_family_upgrade_schema($conn);
ensure_user_account_schema($conn);

function require_login(): void {
    if (!isset($_SESSION['user_id'])) {
        header('Location: ' . app_url('modules/users/auth/login.php'));
        exit;
    }
}

function role_dashboard_map(): array {
    return [
        'task_force' => app_url('modules/users/roles/task_force/dashboard.php'),
        'special_program' => app_url('modules/users/roles/task_force/dashboard.php'),
        'staff' => app_url('modules/users/roles/task_force/dashboard.php'),
        'records' => app_url('modules/users/roles/task_force/dashboard.php'),
        'department_head' => app_url('modules/users/roles/task_force/dashboard.php'),
        'reviewer' => app_url('modules/users/roles/task_force/dashboard.php'),
        'approver' => app_url('modules/users/roles/task_force/dashboard.php'),
        'executive' => app_url('modules/users/roles/task_force/dashboard.php'),
        'auditor' => app_url('modules/users/roles/task_force/dashboard.php'),
        'beneficiaries' => app_url('modules/beneficiaries/dashboard.php'),
        'beneficiary_staff' => app_url('modules/beneficiaries/dashboard.php'),
        'cbms' => app_url('modules/cbms/dashboard.php'),
        'cbms_staff' => app_url('modules/cbms/dashboard.php'),
        'mayor' => app_url('modules/users/roles/mayor/dashboard.php'),
        'developer' => app_url('modules/admin/dashboard.php'),
        'admin' => app_url('modules/admin/dashboard.php'),
    ];
}

function role_default_workspace(string $role): string {
    return match ($role) {
        'beneficiaries', 'beneficiary_staff' => 'beneficiaries',
        'cbms', 'cbms_staff' => 'cbms',
        'mayor' => 'mayor',
        'developer', 'admin' => 'developer',
        default => 'special_program',
    };
}

function redirect_by_role(string $role): void {
    $role = trim(strtolower($role));
    $map = role_dashboard_map();
    set_current_platform_module(role_default_workspace($role));
    header('Location: ' . ($map[$role] ?? $map['task_force']));
    exit;
}

function require_role(array $roles): void {
    require_login();
    $role = $_SESSION['role_code'] ?? '';
    if (!in_array($role, $roles, true)) {
        http_response_code(403);
        echo '<h1>403 Forbidden</h1><p>Access denied.</p>';
        exit;
    }
}
