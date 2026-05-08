<?php
require_once dirname(__DIR__, 3) . '/app/bootstrap.php';

$conn = db_conn();
app_require('app/includes/session.php');
require_once app_path('app/config/database.php');
app_require('modules/users/auth/auth_page_helpers.php');
app_require('app/includes/auth.php');
app_require('app/includes/module_platform.php');

$appName = system_title($conn);
$logoUrl = system_logo_url($conn);
$loaderText = system_loader_text($conn);
$browserTitle = login_browser_title($conn);
$heroTitle = login_hero_title($conn);
$heroBody = login_hero_body($conn);
$cardTitle = login_card_title($conn);
$cardSubtitle = login_card_subtitle($conn);
$panelCaption = login_panel_caption($conn);
$badgeLabel = login_badge_label($conn);
$featureOneTitle = login_feature_one_title($conn);
$featureOneBody = login_feature_one_body($conn);
$featureTwoTitle = login_feature_two_title($conn);
$featureTwoBody = login_feature_two_body($conn);
$accessNote = login_access_note($conn);
$loginSubmitLabel = login_submit_label($conn);
$familyAccessTitle = family_access_title($conn);
$familyAccessDescription = family_access_description($conn);
$familyAccessButtonLabel = family_access_button_label($conn);

if (isset($_SESSION['user_id'])) { redirect_by_role($_SESSION['role_code'] ?? 'task_force'); }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string)post('username'));
    $password = (string)post('password');
    $stmt = $conn->prepare("SELECT u.user_id, u.full_name, u.username, u.password_hash, r.role_name FROM users u LEFT JOIN roles r ON r.role_id = u.role_id WHERE u.username = ? AND u.is_active = 1 LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $ok = false;
        if ($user) {
            $dbHash = $user['password_hash'] ?? '';
            $ok = password_verify($password, $dbHash) || hash('sha256', $password) === $dbHash;
        }
        if ($ok) {
            $_SESSION['user_id'] = (int)$user['user_id'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role_code'] = role_code_from_name((string)($user['role_name'] ?? 'task_force'));
            set_current_platform_module(role_default_workspace($_SESSION['role_code']));
            refresh_session_user($conn, (int)$user['user_id']);
            $update = $conn->prepare('UPDATE users SET last_login_at = NOW() WHERE user_id = ?');
            if ($update) { $update->bind_param('i', $_SESSION['user_id']); $update->execute(); $update->close(); }
            app_log($conn, $_SESSION['user_id'], 'AUTH', 'LOGIN_SUCCESS', $_SESSION['user_id'], 'User logged in to unified role-based workspace');
            redirect_by_role($_SESSION['role_code']);
        }
        $error = 'Invalid username or password.';
    } else {
        $error = 'Login query failed. Please check your database connection settings.';
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="h-full antialiased">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($browserTitle) ?></title>
    <script>(function(){const t=localStorage.getItem('theme');const d=window.matchMedia('(prefers-color-scheme: dark)').matches;if(t==='dark'||(!t&&d))document.documentElement.classList.add('dark');})();</script>
    <script src="https://cdn.tailwindcss.com"></script><script>tailwind.config={darkMode:'class'}</script><script src="https://unpkg.com/lucide@latest"></script>
    <link rel="stylesheet" href="<?= e(app_url('assets/css/app.css')) ?>">
</head>
<body class="h-full text-slate-800 dark:text-slate-100 transition-colors duration-300">
<div id="appLoader" class="app-loader-overlay">
    <div class="app-loader-mark">
        <div class="app-loader-ring"></div>
        <div class="app-loader-logo-wrap"><img src="<?= e($logoUrl) ?>" alt="LGU logo" class="app-loader-logo" onerror="this.onerror=null;this.src='<?= e(app_url('assets/img/image.jpg')) ?>';"></div>
    </div>
    <div class="app-loader-text"><?= e($loaderText) ?></div>
</div>

<div class="min-h-screen grid lg:grid-cols-[1.04fr_0.96fr]">
    <div class="hidden lg:flex items-center px-10 xl:px-20 py-10">
        <div class="max-w-2xl">
            <div class="inline-flex items-center gap-4 rounded-full border border-slate-200 bg-white/80 px-4 py-3 shadow-sm">
                <img src="<?= e($logoUrl) ?>" alt="Logo" class="h-12 w-12 rounded-2xl object-cover" onerror="this.onerror=null;this.src='<?= e(app_url('assets/img/image.jpg')) ?>';">
                <div>
                    <div class="font-semibold text-slate-900"><?= e($appName) ?></div>
                    <div class="text-sm text-slate-500"><?= e($panelCaption) ?></div>
                </div>
            </div>

            <h1 class="mt-8 text-5xl font-black tracking-tight text-slate-900 leading-tight"><?= e($heroTitle) ?></h1>
            <p class="mt-5 text-lg leading-8 text-slate-600"><?= e($heroBody) ?></p>

            <div class="mt-10 grid gap-4 sm:grid-cols-2">
                <div class="rounded-3xl border border-slate-200 bg-white/82 p-5 shadow-sm">
                    <div class="font-semibold text-slate-900"><?= e($featureOneTitle) ?></div>
                    <div class="mt-2 text-sm leading-6 text-slate-500"><?= e($featureOneBody) ?></div>
                </div>
                <div class="rounded-3xl border border-slate-200 bg-white/82 p-5 shadow-sm">
                    <div class="font-semibold text-slate-900"><?= e($featureTwoTitle) ?></div>
                    <div class="mt-2 text-sm leading-6 text-slate-500"><?= e($featureTwoBody) ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="flex items-center justify-center px-4 py-10 sm:px-6 lg:px-10">
        <div class="w-full max-w-lg rounded-[2rem] border border-slate-200 bg-white/92 p-6 sm:p-8 shadow-2xl backdrop-blur">
            <div class="flex items-start justify-between gap-3">
                <div class="flex items-start gap-4">
                    <img src="<?= e($logoUrl) ?>" alt="Logo" class="h-14 w-14 rounded-2xl object-cover" onerror="this.onerror=null;this.src='<?= e(app_url('assets/img/image.jpg')) ?>';">
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-[0.25em] text-emerald-700"><?= e($badgeLabel) ?></div>
                        <h2 class="text-3xl font-black text-slate-900"><?= e($cardTitle) ?></h2>
                        <p class="mt-2 text-sm leading-6 text-slate-500"><?= e($cardSubtitle) ?></p>
                    </div>
                </div>
                <button type="button" onclick="document.documentElement.classList.toggle('dark');localStorage.setItem('theme',document.documentElement.classList.contains('dark')?'dark':'light');" class="icon-button"><i data-lucide="moon-star" class="w-5 h-5 block dark:hidden"></i><i data-lucide="sun-medium" class="w-5 h-5 hidden dark:block"></i></button>
            </div>

            <?php if ($error): ?><div class="app-toast app-toast-error mt-6"><div class="font-semibold"><?= e($error) ?></div></div><?php endif; ?>

            <form method="POST" class="mt-6 space-y-5 app-form-loader">
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2">Username</label>
                    <input type="text" name="username" value="<?= e($_POST['username'] ?? '') ?>" required class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100 transition">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2">Password</label>
                    <div class="relative">
                        <input type="password" id="login_password" name="password" required class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 pr-12 outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100 transition">
                        <button type="button" data-password-toggle-target="login_password" class="password-toggle-button absolute inset-y-0 right-3 inline-flex items-center justify-center"><i data-lucide="eye" class="w-5 h-5"></i></button>
                    </div>
                </div>

                <div class="grid gap-4 md:grid-cols-[1fr_auto] items-end">
                    <div class="text-sm text-slate-500"><?= e($accessNote) ?></div>
                    <button type="submit" class="app-btn-primary login-submit-btn w-full md:w-auto min-w-[220px]">
                        <svg class="login-submit-btn__icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M15 3H10a4 4 0 0 0-4 4v10a4 4 0 0 0 4 4h5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M21 12H11" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="m18 9 3 3-3 3" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        <span><?= e($loginSubmitLabel) ?></span>
                    </button>
                </div>

                <?php if (family_scan_enabled($conn)): ?>
                <div class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 p-4">
                    <div class="flex items-center justify-between gap-3 flex-wrap">
                        <div>
                            <div class="font-semibold text-slate-900"><?= e($familyAccessTitle) ?></div>
                            <div class="text-sm text-slate-500 mt-1"><?= e($familyAccessDescription) ?></div>
                        </div>
                        <a href="<?= e(app_url('modules/family/scan.php')) ?>" class="app-btn-outline"><?= e($familyAccessButtonLabel) ?></a>
                    </div>
                </div>
                <?php endif; ?>
            </form>
        </div>
    </div>
</div>
<script src="<?= e(app_url('assets/js/app.js')) ?>"></script>
</body></html>
