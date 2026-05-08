<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once __DIR__ . '/module_platform.php';

function get_home_by_role(string $role): string {
    return match ($role) {
        'beneficiaries', 'beneficiary_staff' => app_url('modules/beneficiaries/dashboard.php'),
        'cbms', 'cbms_staff' => app_url('modules/cbms/dashboard.php'),
        'mayor' => app_url('modules/users/roles/mayor/dashboard.php'),
        'developer', 'admin' => app_url('modules/admin/dashboard.php'),
        default => app_url('modules/users/roles/task_force/dashboard.php'),
    };
}

function role_navigation_map(): array {
    return [
        'task_force' => [
            'groups' => [
                ['label'=>'Households','icon'=>'house','items'=>[
                    ['label'=>'All households','href'=>app_url('modules/agri/households/index.php'),'icon'=>'house'],
                    ['label'=>'Households for Visit','href'=>app_url('modules/agri/validation/index.php'),'icon'=>'clipboard-check'],
                    ['label'=>'Families & Population','href'=>app_url('modules/agri/households/index.php?view=population'),'icon'=>'users'],
                ]],
                ['label'=>'Programs','icon'=>'sprout','items'=>[
                    ['label'=>'Program Requests','href'=>app_url('modules/agri/programs/index.php'),'icon'=>'sprout'],
                    ['label'=>'CBMS-lite','href'=>app_url('modules/agri/cbms_lite/index.php'),'icon'=>'database'],
                    ['label'=>'Rules & Violations','href'=>app_url('modules/agri/compliance/index.php'),'icon'=>'shield-alert'],
                    ['label'=>'Events','href'=>app_url('modules/agri/events/index.php'),'icon'=>'calendar-days'],
                ]],
                ['label'=>'Reports','icon'=>'chart-column','items'=>[
                    ['label'=>'Reports workspace','href'=>app_url('modules/agri/reports/index.php'),'icon'=>'chart-column'],
                    ['label'=>'Import center','href'=>app_url('modules/agri/import/index.php'),'icon'=>'upload'],
                ]],
                ['label'=>'Tools','icon'=>'wrench','items'=>[
                    ['label'=>'Attendance','href'=>app_url('modules/agri/attendance/index.php'),'icon'=>'badge-check'],
                    ['label'=>'QR Lookup','href'=>app_url('modules/agri/qr/scan.php'),'icon'=>'scan-qr-code'],
                    ['label'=>'QR Cards','href'=>app_url('modules/agri/qr/cards.php'),'icon'=>'qr-code'],
                    ['label'=>'Family Updates','href'=>app_url('modules/agri/family_updates/index.php'),'icon'=>'images'],
                    ['label'=>'Notifications','href'=>app_url('modules/agri/notifications/index.php'),'icon'=>'bell'],
                ]],
            ],
            'search' => app_url('modules/agri/households/index.php'),
            'mobile_primary' => app_url('modules/agri/households/index.php'),
            'mobile_tool' => app_url('modules/agri/programs/index.php'),
        ],
        'beneficiaries' => [
            'groups' => [
                ['label'=>'Households','icon'=>'house','items'=>[
                    ['label'=>'Households','href'=>app_url('modules/beneficiaries/families/index.php'),'icon'=>'house'],
                    ['label'=>'Population registry','href'=>app_url('modules/beneficiaries/families/index.php?view=population'),'icon'=>'users'],
                ]],
                ['label'=>'Programs','icon'=>'hand-heart','items'=>[
                    ['label'=>'Assistance','href'=>app_url('modules/agri/assistance/index.php'),'icon'=>'hand-heart'],
                ]],
                ['label'=>'Reports','icon'=>'chart-column','items'=>[
                    ['label'=>'Reports','href'=>app_url('modules/agri/reports/index.php'),'icon'=>'chart-column'],
                ]],
                ['label'=>'Tools','icon'=>'wrench','items'=>[
                    ['label'=>'Notifications','href'=>app_url('modules/agri/notifications/index.php'),'icon'=>'bell'],
                    ['label'=>'Profile','href'=>app_url('modules/users/account/profile/index.php'),'icon'=>'circle-user-round'],
                ]],
            ],
            'search' => app_url('modules/beneficiaries/families/index.php'),
            'mobile_primary' => app_url('modules/beneficiaries/families/index.php'),
            'mobile_tool' => app_url('modules/agri/reports/index.php'),
        ],
        'cbms' => [
            'groups' => [
                ['label'=>'Households','icon'=>'house','items'=>[
                    ['label'=>'Households','href'=>app_url('modules/cbms/families/index.php'),'icon'=>'house'],
                ]],
                ['label'=>'Reports','icon'=>'chart-column','items'=>[
                    ['label'=>'Detailed Reports','href'=>app_url('modules/agri/family_reports/index.php'),'icon'=>'files'],
                    ['label'=>'Municipal Summary','href'=>app_url('modules/agri/reports/index.php'),'icon'=>'chart-column'],
                ]],
                ['label'=>'Tools','icon'=>'wrench','items'=>[
                    ['label'=>'Notifications','href'=>app_url('modules/agri/notifications/index.php'),'icon'=>'bell'],
                    ['label'=>'Profile','href'=>app_url('modules/users/account/profile/index.php'),'icon'=>'circle-user-round'],
                ]],
            ],
            'search' => app_url('modules/cbms/families/index.php'),
            'mobile_primary' => app_url('modules/cbms/families/index.php'),
            'mobile_tool' => app_url('modules/agri/reports/index.php'),
        ],
        'mayor' => [
            'groups' => [
                ['label'=>'Households','icon'=>'house','items'=>[
                    ['label'=>'Households','href'=>app_url('modules/mayor/families/index.php'),'icon'=>'house'],
                    ['label'=>'Needs Action','href'=>app_url('modules/agri/action_center.php'),'icon'=>'siren'],
                ]],
                ['label'=>'Programs','icon'=>'sprout','items'=>[
                    ['label'=>'Golden HH','href'=>app_url('modules/agri/programs/index.php'),'icon'=>'award'],
                ]],
                ['label'=>'Reports','icon'=>'chart-column','items'=>[
                    ['label'=>'Reports','href'=>app_url('modules/agri/reports/index.php'),'icon'=>'chart-column'],
                ]],
                ['label'=>'Tools','icon'=>'wrench','items'=>[
                    ['label'=>'Profile','href'=>app_url('modules/users/account/profile/index.php'),'icon'=>'circle-user-round'],
                ]],
            ],
            'search' => app_url('modules/mayor/families/index.php'),
            'mobile_primary' => app_url('modules/mayor/families/index.php'),
            'mobile_tool' => app_url('modules/agri/reports/index.php'),
        ],
        'developer' => [
            'groups' => [
                ['label'=>'Households','icon'=>'house','items'=>[
                    ['label'=>'Data workspace','href'=>app_url('modules/agri/households/index.php'),'icon'=>'house'],
                    ['label'=>'Import center','href'=>app_url('modules/agri/import/index.php'),'icon'=>'upload'],
                ]],
                ['label'=>'Programs','icon'=>'sprout','items'=>[
                    ['label'=>'Qualification Rules','href'=>app_url('modules/admin/qualification_rules/index.php'),'icon'=>'list-checks'],
                    ['label'=>'Workflows','href'=>app_url('modules/admin/workflows/index.php'),'icon'=>'git-branch-plus'],
                ]],
                ['label'=>'Reports','icon'=>'chart-column','items'=>[
                    ['label'=>'Reports','href'=>app_url('modules/agri/reports/index.php'),'icon'=>'chart-column'],
                    ['label'=>'Logs','href'=>app_url('modules/admin/logs/index.php'),'icon'=>'logs'],
                ]],
                ['label'=>'Tools','icon'=>'settings-2','items'=>[
                    ['label'=>'System Settings','href'=>app_url('modules/admin/settings/index.php'),'icon'=>'sliders-horizontal'],
                    ['label'=>'Branding','href'=>app_url('modules/admin/settings/branding.php'),'icon'=>'image-up'],
                    ['label'=>'Users','href'=>app_url('modules/admin/users/index.php'),'icon'=>'users'],
                    ['label'=>'Roles','href'=>app_url('modules/admin/roles/index.php'),'icon'=>'key-round'],
                    ['label'=>'Security','href'=>app_url('modules/admin/security/index.php'),'icon'=>'shield-check'],
                    ['label'=>'Departments','href'=>app_url('modules/admin/departments/index.php'),'icon'=>'building-2'],
                ]],
            ],
            'search' => app_url('modules/admin/users/index.php'),
            'mobile_primary' => app_url('modules/admin/users/index.php'),
            'mobile_tool' => app_url('modules/admin/settings/index.php'),
        ],
    ];
}

function navigation_role_key(string $role): string {
    return match ($role) {
        'beneficiaries', 'beneficiary_staff' => 'beneficiaries',
        'cbms', 'cbms_staff' => 'cbms',
        'mayor' => 'mayor',
        'developer', 'admin' => 'developer',
        default => 'task_force',
    };
}

function get_navigation_by_role(string $role): array {
    $key = navigation_role_key($role);
    $map = role_navigation_map();
    $groups = $map[$key]['groups'] ?? [];
    $items = [['label'=>'Dashboard','href'=>get_home_by_role($role),'icon'=>'layout-dashboard']];
    foreach ($groups as $group) {
        foreach (($group['items'] ?? []) as $item) {
            $items[] = $item;
        }
    }
    return $items;
}

function get_navigation_groups_by_role(string $role): array {
    $key = navigation_role_key($role);
    $map = role_navigation_map();
    return $map[$key]['groups'] ?? [];
}

function navigation_role_config(string $role): array {
    $key = navigation_role_key($role);
    $map = role_navigation_map();
    return $map[$key] ?? $map['task_force'];
}
