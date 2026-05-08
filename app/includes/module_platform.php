<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';

function platform_module_definitions(): array {
    return [
        'special_program' => [
            'code' => 'special_program',
            'name' => 'Special Program',
            'description' => 'Household records, interviews, crops, monitoring, events, attendance, and field operations.',
            'icon' => '🌾',
            'dashboard' => app_url('modules/users/roles/task_force/dashboard.php'),
        ],
        'beneficiaries' => [
            'code' => 'beneficiaries',
            'name' => 'Beneficiaries',
            'description' => 'People-focused records and beneficiary reporting inside the same shared system.',
            'icon' => '👥',
            'dashboard' => app_url('modules/beneficiaries/dashboard.php'),
        ],
        'cbms' => [
            'code' => 'cbms',
            'name' => 'CBMS',
            'description' => 'Detailed household, family, livelihood, and community profiling views.',
            'icon' => '🏘️',
            'dashboard' => app_url('modules/cbms/dashboard.php'),
        ],
        'mayor' => [
            'code' => 'mayor',
            'name' => 'Mayor',
            'description' => 'Executive decision-support workspace with view-focused access.',
            'icon' => '🏛️',
            'dashboard' => app_url('modules/users/roles/mayor/dashboard.php'),
        ],
        'developer' => [
            'code' => 'developer',
            'name' => 'Developer',
            'description' => 'System governance, user access, settings, security, and maintenance.',
            'icon' => '🛠️',
            'dashboard' => app_url('modules/admin/dashboard.php'),
        ],
    ];
}

function platform_modules(): array {
    return platform_module_definitions();
}

function platform_module(string $code): ?array {
    $modules = platform_module_definitions();
    return $modules[$code] ?? null;
}

function current_platform_module_code(): string {
    $code = (string)($_SESSION['current_module'] ?? 'special_program');
    return platform_module($code) ? $code : 'special_program';
}

function current_platform_module(): array {
    return platform_module(current_platform_module_code()) ?? platform_module('special_program');
}

function set_current_platform_module(string $code): void {
    if (platform_module($code)) {
        $_SESSION['current_module'] = $code;
    }
}

function platform_select_login_url(string $code): string {
    return app_url('modules/users/auth/login.php');
}

function role_allowed_modules(string $role): array {
    return match ($role) {
        'developer', 'admin' => ['special_program', 'beneficiaries', 'cbms', 'mayor', 'developer'],
        'mayor' => ['mayor'],
        'beneficiaries', 'beneficiary_staff' => ['beneficiaries'],
        'cbms', 'cbms_staff' => ['cbms'],
        default => ['special_program'],
    };
}

function role_can_access_module(string $role, string $module): bool {
    return in_array($module, role_allowed_modules($role), true);
}

function require_module_access(?array $roles = null, ?string $moduleCode = null): void {
    require_login();
    $role = (string)($_SESSION['role_code'] ?? '');
    if (is_array($roles) && !in_array($role, $roles, true)) {
        http_response_code(403);
        echo '<h1>403 Forbidden</h1><p>Access denied.</p>';
        exit;
    }
    $moduleCode = $moduleCode ?: current_platform_module_code();
    if (!role_can_access_module($role, $moduleCode) && !in_array($role, ['developer','admin'], true)) {
        http_response_code(403);
        echo '<h1>403 Forbidden</h1><p>You do not have access to this workspace.</p>';
        exit;
    }
}
