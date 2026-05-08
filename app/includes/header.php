<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once __DIR__ . '/session.php';
require_once app_path('app/config/database.php');
$conn = db_conn();
require_once __DIR__ . '/app_helpers.php';
require_once __DIR__ . '/navigation.php';

$current_role = $_SESSION['role_code'] ?? '';
$current_uri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '';
$home_link = get_home_by_role($current_role);
$nav_groups = get_navigation_groups_by_role($current_role);
if (!is_array($nav_groups)) { $nav_groups = []; }
$flash = get_flash();
$conn = $conn ?? ($GLOBALS['conn'] ?? db_conn());
if ($conn instanceof mysqli) { ensure_family_upgrade_schema($conn); ensure_user_account_schema($conn); }
$navAttention = navigation_attention_counts($conn, $current_role);
$navAttentionDetails = navigation_attention_details($conn, $current_role);
$notif_count = $navAttention['notifications'] ?? 0;
$family_updates_count = $navAttention['family_updates'] ?? 0;
$profileApprovalsCount = $navAttention['profile_approvals'] ?? 0;
$securityPendingCount = $navAttention['security'] ?? 0;
$accountPendingCount = $navAttention['account'] ?? 0;
$mobileMenuAttention = ($navAttention['tools'] ?? 0) + ($navAttention['governance'] ?? 0);
$currentUser = current_user();
$display_name = $currentUser['name'] ?? ($_SESSION['username'] ?? 'Guest');
$user_avatar = user_avatar_url($currentUser['avatar_path'] ?? null);
$system_logo_url = system_logo_url($conn);
$app_name = system_title($conn);
$app_subtitle = system_subtitle($conn);
$app_loader_text = system_loader_text($conn);
$searchPlaceholder = function_exists('system_header_search_placeholder') ? system_header_search_placeholder($conn) : 'Search family member, head, code, or contact';
$searchPlaceholder = ($current_role === 'developer' || $current_role === 'admin') ? 'Search users, roles, departments, or logs' : $searchPlaceholder;
$enableHouseholdAutocomplete = !in_array($current_role, ['developer', 'admin'], true);
$page_title = ucwords(str_replace(['_', '-'], ' ', basename($current_uri, '.php')));
$role_title = role_label($current_role);
$role_nav = function_exists('navigation_role_config') ? navigation_role_config($current_role) : [];
$search_action = $role_nav['search'] ?? app_url('modules/agri/households/index.php');
$mobile_family_link = $role_nav['mobile_primary'] ?? app_url('modules/agri/households/index.php');
$mobile_tool_link = $role_nav['mobile_tool'] ?? app_url('modules/agri/qr/scan.php');
?>
<!DOCTYPE html>
<html lang="en" class="h-full antialiased">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(system_browser_page_title($conn, $page_title)) ?></title>
    <script>
        (function () {
            const savedTheme = localStorage.getItem('theme');
            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            if (savedTheme === 'dark' || (!savedTheme && prefersDark)) document.documentElement.classList.add('dark');
        })();
    </script>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config={darkMode:'class',theme:{extend:{boxShadow:{soft:'0 12px 28px rgba(33,61,40,.08)'}}}};</script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="stylesheet" href="<?= e(app_url('assets/css/app.css')) ?>">
</head>
<body class="h-full text-slate-800 dark:text-slate-100 transition-colors duration-300">
<?php $flat_nav = get_navigation_by_role($current_role); ?>
<div id="appLoader" class="app-loader-overlay">
    <div class="app-loader-mark"><div class="app-loader-ring"></div><div class="app-loader-logo-wrap"><img src="<?= e($system_logo_url) ?>" alt="LGU logo" class="app-loader-logo" onerror="this.onerror=null;this.src='<?= e(app_url('assets/img/image.jpg')) ?>';"></div></div>
    <div class="app-loader-text"><?= e($app_loader_text) ?></div>
</div>
<?php if (isset($_SESSION['user_id'])): ?>
<div class="min-h-screen app-shell">
    <header class="app-header sticky top-0 z-40 border-b">
        <div class="app-header-inner px-4 sm:px-6 lg:px-8 py-3">
            <div class="flex items-center gap-3 min-w-0">
                <a href="<?= e($home_link) ?>" class="flex items-center gap-3 min-w-0 shrink-0">
                    <span class="app-brand-mark"><img src="<?= e($system_logo_url) ?>" alt="Logo" class="h-full w-full object-cover" onerror="this.onerror=null;this.src='<?= e(app_url('assets/img/image.jpg')) ?>';"></span>
                    <span class="min-w-0 hidden sm:block">
                        <span class="block app-brand-title truncate"><?= e($app_name) ?></span><span class="block text-xs text-slate-500 truncate"><?= e($role_title) ?></span>
                    </span>
                </a>
            </div>
            <form action="<?= e($search_action) ?>" method="GET" class="app-global-search hidden md:flex" <?= $enableHouseholdAutocomplete ? 'data-household-autocomplete-form' : '' ?>>
                <i data-lucide="search" class="w-4 h-4 shrink-0"></i>
                <input type="text" name="q" placeholder="<?= e($searchPlaceholder) ?>" autocomplete="off" <?= $enableHouseholdAutocomplete ? 'data-household-autocomplete-input data-autocomplete-min="1"' : '' ?>>
            </form>
            <div class="app-header-actions">
                <nav class="app-top-nav hidden md:flex" aria-label="Primary navigation">
                    <a href="<?= e($home_link) ?>" class="app-top-nav-item <?= str_contains($current_uri, $home_link) ? 'is-active' : '' ?>" title="Dashboard" aria-label="Dashboard">
                        <i data-lucide="layout-dashboard" class="w-5 h-5"></i><span class="app-tooltip">Dashboard</span>
                    </a>
                    <?php foreach ($nav_groups as $idx => $group): 
                        $groupItems = (is_array($group) && isset($group['items']) && is_array($group['items'])) ? $group['items'] : [];
                        if (!$groupItems) continue;
                        $groupActive = false; foreach($groupItems as $gi){ if (!empty($gi['href']) && str_contains($current_uri, $gi['href'])) { $groupActive = true; break; } }
                        $menuId = 'navDrop'.$idx;
                        $groupAttention = navigation_group_attention($groupItems, $navAttentionDetails, (string)($group['label'] ?? 'menu'));
                        $groupCount = (int)($groupAttention['count'] ?? 0);
                        $groupNeedsAttention = $groupCount > 0;
                        $groupTooltip = (string)($group['label'] ?? 'Menu');
                        ?>
                        <div class="app-dropdown-wrap" data-dropdown-wrap>
                            <button type="button" class="app-top-nav-item <?= $groupActive ? 'is-active' : '' ?> <?= $groupNeedsAttention ? 'has-attention' : '' ?>" onclick="toggleDropdown('<?= $menuId ?>', this)" title="<?= e($groupTooltip) ?>" aria-label="<?= e($groupTooltip) ?>" aria-expanded="false" aria-controls="<?= $menuId ?>">
                                <i data-lucide="<?= e($group['icon']) ?>" class="w-5 h-5"></i>
                                <?php if ($groupNeedsAttention): ?><span class="app-top-nav-badge app-top-nav-badge-attention"><?= $groupCount ?></span><?php endif; ?>
                                <span class="app-tooltip"><?= e($groupTooltip) ?></span>
                            </button>
                            <div id="<?= $menuId ?>" class="app-dropdown-menu hidden" data-dropdown-menu>
                                <div class="app-dropdown-heading">
                                    <i data-lucide="<?= e($group['icon']) ?>" class="w-4 h-4"></i>
                                    <span><?= e($group['label']) ?></span>
                                </div>
                                <?php foreach($groupItems as $item): 
                                    $itemAttention = navigation_item_attention($navAttentionDetails, (string)($item['href'] ?? ''));
                                    $itemCount = (int)($itemAttention['count'] ?? 0);
                                    $itemNeedsAttention = $itemCount > 0;
                                    $itemHint = (string)($itemAttention['hint'] ?? 'Open menu');
                                    $itemEmptyHint = (string)($itemAttention['empty_hint'] ?? 'Nothing pending');
                                ?>
                                    <a href="<?= e($item['href']) ?>" class="app-dropdown-link <?= str_contains($current_uri, $item['href']) ? 'is-active' : '' ?> <?= $itemNeedsAttention ? 'has-attention' : '' ?>" title="<?= e($item['label'] . ' · ' . $itemHint) ?>">
                                        <i data-lucide="<?= e($item['icon']) ?>" class="w-4 h-4"></i>
                                        <span class="app-dropdown-copy">
                                            <span class="app-dropdown-title"><?= e($item['label']) ?></span>
                                        </span>
                                        <?php if ($itemNeedsAttention): ?><span class="app-inline-badge app-inline-badge-attention"><?= $itemCount ?></span><?php endif; ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                                    </nav>
                <div class="app-mobile-quicknav md:hidden" aria-label="Quick navigation">
                    <a href="<?= e($home_link) ?>" class="app-top-nav-item <?= str_contains($current_uri, $home_link) ? 'is-active' : '' ?>" title="Dashboard" aria-label="Dashboard"><i data-lucide="layout-dashboard" class="w-5 h-5"></i></a>
                    <a href="<?= e($mobile_family_link) ?>" class="app-top-nav-item <?= str_contains($current_uri, $mobile_family_link) ? 'is-active' : '' ?>" title="Primary records" aria-label="Primary records"><i data-lucide="house" class="w-5 h-5"></i></a>
                    <a href="<?= e($mobile_tool_link) ?>" class="app-top-nav-item <?= str_contains($current_uri, $mobile_tool_link) ? 'is-active' : '' ?>" title="Module Tool" aria-label="Module Tool"><i data-lucide="scan-qr-code" class="w-5 h-5"></i></a>
                    <button type="button" class="app-top-nav-item <?= $mobileMenuAttention > 0 ? 'has-attention' : '' ?>" onclick="toggleMobileNav()" title="<?= e($mobileMenuAttention > 0 ? ('Menu needs attention · ' . $mobileMenuAttention . ' pending item' . ($mobileMenuAttention === 1 ? '' : 's')) : 'Menu') ?>" aria-label="<?= e($mobileMenuAttention > 0 ? ('Menu needs attention · ' . $mobileMenuAttention . ' pending item' . ($mobileMenuAttention === 1 ? '' : 's')) : 'Menu') ?>" aria-controls="mobileNavDrawer" aria-expanded="false" id="mobileNavToggle"><i data-lucide="menu" class="w-5 h-5"></i><?php if ($mobileMenuAttention > 0): ?><span class="app-top-nav-badge app-top-nav-badge-attention"><?= $mobileMenuAttention ?></span><?php endif; ?></button>
                </div>
                <div class="app-dropdown-wrap" data-dropdown-wrap>
                    <button type="button" class="app-user-trigger" onclick="toggleDropdown('profileMenu', this)" aria-expanded="false" aria-controls="profileMenu">
                        <img src="<?= e($user_avatar) ?>" alt="Profile" onerror="this.onerror=null;this.src='<?= e(app_url('assets/img/image.jpg')) ?>';" class="app-user-avatar">
                    </button>
                    <div id="profileMenu" class="app-dropdown-menu app-profile-menu hidden" data-dropdown-menu>
                        <div class="app-profile-card">
                            <div class="flex items-center gap-3">
                                <img src="<?= e($user_avatar) ?>" alt="Profile" onerror="this.onerror=null;this.src='<?= e(app_url('assets/img/image.jpg')) ?>';" class="h-12 w-12 rounded-2xl object-cover border border-slate-200 dark:border-slate-800">
                                <div>
                                    <div class="font-bold text-slate-900 dark:text-white"><?= e($display_name) ?></div>
                                    <div class="text-xs text-slate-500"><?= e(role_label($current_role)) ?></div>
                                </div>
                            </div>
                            <div class="mt-3 text-xs text-slate-500"><?= e($currentUser['position_title'] ?? 'System account') ?></div>
                            <?php if (!empty($currentUser['email'])): ?><div class="mt-1 text-xs text-slate-500"><?= e($currentUser['email']) ?></div><?php endif; ?>
                            <div class="mt-4 space-y-3">
                                <div class="app-menu-section">
                                    <div class="app-menu-section-label"><i data-lucide="user-round" class="w-4 h-4"></i><span>Account</span></div>
                                    <div class="grid gap-2">
                                        <a href="<?= e(app_url('modules/users/account/profile/index.php')) ?>" class="app-dropdown-link"><i data-lucide="user-cog" class="w-4 h-4"></i><span>Edit profile</span></a>
                                        <a href="<?= e(app_url('modules/users/account/change_password.php')) ?>" class="app-dropdown-link"><i data-lucide="key-round" class="w-4 h-4"></i><span>Change password</span></a>
                                        <a href="<?= e(app_url('modules/users/account/activity.php')) ?>" class="app-dropdown-link"><i data-lucide="history" class="w-4 h-4"></i><span>Account activity</span></a>
                                    </div>
                                </div>
                                <div class="app-menu-section">
                                    <div class="app-menu-section-label"><i data-lucide="door-open" class="w-4 h-4"></i><span>Session</span></div>
                                    <div class="grid gap-2">
                                        <a href="<?= e(app_url('modules/users/auth/logout.php')) ?>" class="app-dropdown-link"><i data-lucide="log-out" class="w-4 h-4"></i><span>Log out</span></a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <button type="button" onclick="toggleTheme()" class="icon-button" title="Toggle theme" aria-label="Toggle theme"><i data-lucide="moon-star" class="w-5 h-5 block dark:hidden"></i><i data-lucide="sun-medium" class="w-5 h-5 hidden dark:block"></i></button>
            </div>
        </div>
        <div class="px-4 sm:px-6 lg:px-8 pb-3 md:hidden">
            <form action="<?= e($search_action) ?>" method="GET" class="app-global-search mobile-only" <?= $enableHouseholdAutocomplete ? 'data-household-autocomplete-form' : '' ?>>
                <i data-lucide="search" class="w-4 h-4 shrink-0"></i>
                <input type="text" name="q" placeholder="<?= e($searchPlaceholder) ?>" autocomplete="off" <?= $enableHouseholdAutocomplete ? 'data-household-autocomplete-input data-autocomplete-min="1"' : '' ?>>
            </form>
        </div>
    </header>
    <div id="mobileNavOverlay" class="app-mobile-overlay hidden" onclick="closeMobileNav()"></div>
    <aside id="mobileNavDrawer" class="app-mobile-drawer md:hidden" aria-hidden="true">
        <div class="app-mobile-drawer-head">
            <div>
                <div class="app-kicker">Quick access</div>
                <div class="text-lg font-black"><?= e($display_name) ?></div>
                <div class="text-sm app-page-user"><?= e(role_label($current_role)) ?></div>
            </div>
            <button type="button" class="icon-button" onclick="closeMobileNav()" aria-label="Close menu"><i data-lucide="x" class="w-5 h-5"></i></button>
        </div>
        <nav class="app-mobile-nav-list">
            <a href="<?= e($home_link) ?>" class="app-mobile-nav-link <?= str_contains($current_uri, $home_link) ? 'is-active' : '' ?>">
                <span class="app-mobile-nav-icon"><i data-lucide="layout-dashboard" class="w-5 h-5"></i></span>
                <span class="app-mobile-nav-copy"><span class="app-mobile-nav-title">Dashboard</span></span>
            </a>
            <?php foreach ($nav_groups as $group): ?>
                <?php $groupItems = (is_array($group) && isset($group['items']) && is_array($group['items'])) ? $group['items'] : []; if (!$groupItems) continue; ?>
                <div class="app-mobile-nav-section-label"><?= e($group['label'] ?? 'Menu') ?></div>
                <?php foreach ($groupItems as $item): ?>
                    <?php $mobileAttention = navigation_item_attention($navAttentionDetails, (string)($item['href'] ?? '')); $mobileCount = (int)($mobileAttention['count'] ?? 0); ?>
                    <a href="<?= e($item['href']) ?>" class="app-mobile-nav-link <?= str_contains($current_uri, $item['href']) ? 'is-active' : '' ?> <?= $mobileCount > 0 ? 'has-attention' : '' ?>" title="<?= e(($item['label'] ?? 'Menu') . ' · ' . ($mobileAttention['hint'] ?? 'Open menu')) ?>">
                        <span class="app-mobile-nav-icon <?= $mobileCount > 0 ? 'has-attention' : '' ?>"><i data-lucide="<?= e($item['icon']) ?>" class="w-5 h-5"></i></span>
                        <span class="app-mobile-nav-copy"><span class="app-mobile-nav-title"><?= e($item['label']) ?></span></span>
                        <?php if ($mobileCount > 0): ?><span class="app-inline-badge app-inline-badge-attention"><?= $mobileCount ?></span><?php endif; ?>
                    </a>
                <?php endforeach; ?>
            <?php endforeach; ?>
<?php if (($current_role ?? '') === 'developer' || ($current_role ?? '') === 'admin'): ?>
                <div class="app-mobile-nav-section-label">Developer tools</div>
                <a href="<?= e(app_url('modules/admin/security/index.php')) ?>" class="app-mobile-nav-link <?= str_contains($current_uri, app_url('modules/admin/security/')) ? 'is-active' : '' ?> <?= $securityPendingCount > 0 ? 'has-attention' : '' ?>" title="<?= e($securityPendingCount > 0 ? ($securityPendingCount . ' security item' . ($securityPendingCount === 1 ? '' : 's') . ' need attention') : 'No pending security items') ?>">
                    <span class="app-mobile-nav-icon <?= $securityPendingCount > 0 ? 'has-attention' : '' ?>"><i data-lucide="shield-alert" class="w-5 h-5"></i></span>
                    <span>Security desk</span>
                    <?php if ($securityPendingCount > 0): ?><span class="app-inline-badge app-inline-badge-attention"><?= $securityPendingCount ?></span><?php endif; ?>
                </a>
                <div class="app-mobile-nav-section-label">System</div>
                <a href="<?= e(app_url('modules/admin/settings/index.php')) ?>" class="app-mobile-nav-link <?= str_contains($current_uri, app_url('modules/admin/settings/index.php')) ? 'is-active' : '' ?>">
                    <span class="app-mobile-nav-icon"><i data-lucide="sliders-horizontal" class="w-5 h-5"></i></span>
                    <span>System settings</span>
                </a>
                <a href="<?= e(app_url('modules/admin/settings/branding.php')) ?>" class="app-mobile-nav-link <?= str_contains($current_uri, app_url('modules/admin/settings/branding.php')) ? 'is-active' : '' ?>">
                    <span class="app-mobile-nav-icon"><i data-lucide="palette" class="w-5 h-5"></i></span>
                    <span>System branding</span>
                </a>
            <?php endif; ?>
            <div class="app-mobile-nav-section-label">Session</div>
            <a href="<?= e(app_url('modules/users/auth/logout.php')) ?>" class="app-mobile-nav-link">
                <span class="app-mobile-nav-icon"><i data-lucide="log-out" class="w-5 h-5"></i></span>
                <span>Logout</span>
            </a>
        </nav>
    </aside>
    <main class="app-main px-4 sm:px-6 lg:px-8 py-6 space-y-6">
        <?php if ($flash): ?>
            <div class="app-toast <?= $flash['type'] === 'success' ? 'app-toast-success' : 'app-toast-error' ?>" data-auto-dismiss="3500"><div class="font-semibold"><?= e($flash['message']) ?></div></div>
        <?php endif; ?>
<?php endif; ?>
