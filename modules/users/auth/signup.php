<?php
require_once dirname(__DIR__, 3) . '/app/bootstrap.php';

$conn = db_conn();
app_require('app/includes/session.php');
require_once app_path('app/config/database.php');
app_require('modules/users/auth/auth_page_helpers.php');
app_require('app/includes/auth.php');

$appName = system_title($conn);
$appSubtitle = system_subtitle($conn);
$logoUrl = system_logo_url($conn);
$message = '';
$error = '';

if (isset($_SESSION['user_id'])) { redirect_by_role($_SESSION['role_code'] ?? 'admin'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim((string)post('full_name'));
    $username = trim((string)post('username'));
    $password = (string)post('password');
    $confirm = (string)post('confirm_password');
    $email = trim((string)post('email'));
    $contact = trim((string)post('contact_number'));
    $position = trim((string)post('position_title'));
    $desiredRole = strtoupper(trim((string)post('desired_role')));
    if ($desiredRole === '') $desiredRole = 'TASK_FORCE';
    if ($desiredRole === 'DEVELOPER') $desiredRole = 'TASK_FORCE';

    $avatarPath = null;
    if (!empty($_FILES['avatar']['name'])) {
        $avatarPath = upload_user_avatar($_FILES['avatar']);
    }

    if ($fullName === '' || $username === '' || $password === '') {
        $error = 'Please complete the required signup details.';
    } elseif ($password !== $confirm) {
        $error = 'Password confirmation does not match.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } else {
        $ok = submit_signup_request($conn, [
            'full_name' => $fullName,
            'username' => $username,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'email' => $email,
            'contact_number' => $contact,
            'position_title' => $position,
            'desired_role' => $desiredRole,
            'avatar_path' => $avatarPath,
        ]);
        if ($ok) {
            $message = 'Signup submitted successfully. Please wait for developer approval.';
        } else {
            $error = 'Unable to submit signup request. Username may already exist or be pending.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="h-full antialiased">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign up - <?= e($appName) ?></title>
    <script>(function(){const t=localStorage.getItem('theme');const d=window.matchMedia('(prefers-color-scheme: dark)').matches;if(t==='dark'||(!t&&d))document.documentElement.classList.add('dark');})();</script>
    <script src="https://cdn.tailwindcss.com"></script><script>tailwind.config={darkMode:'class'}</script><script src="https://unpkg.com/lucide@latest"></script>
    <link rel="stylesheet" href="<?= e(app_url('assets/css/app.css')) ?>">
</head>
<body class="min-h-screen bg-slate-50 text-slate-800">
<div class="min-h-screen px-4 py-8 sm:px-6 lg:px-8">
    <div class="mx-auto max-w-6xl grid gap-8 lg:grid-cols-[0.92fr_1.08fr] items-start">
        <section class="rounded-[2rem] border border-slate-200 bg-white p-8 shadow-sm">
            <div class="flex items-center gap-4">
                <img src="<?= e($logoUrl) ?>" alt="Logo" class="h-16 w-16 rounded-2xl object-cover" onerror="this.onerror=null;this.src='<?= e(app_url('assets/img/image.jpg')) ?>';">
                <div>
                    <div class="text-sm text-slate-500"><?= e($appName) ?></div>
                    <h1 class="text-4xl font-black text-slate-900">Create your account request</h1>
                </div>
            </div>
            <p class="mt-5 text-lg leading-8 text-slate-600">Submit your details once. The developer account will review and approve the request before the account becomes active.</p>

            <div class="mt-8 grid gap-4 sm:grid-cols-2">
                <div class="rounded-3xl border border-slate-200 bg-slate-50 p-5">
                    <div class="font-semibold text-slate-900">Approval required</div>
                    <div class="mt-2 text-sm leading-6 text-slate-500">Every signup is checked before activation to keep account access clean and controlled.</div>
                </div>
                <div class="rounded-3xl border border-slate-200 bg-slate-50 p-5">
                    <div class="font-semibold text-slate-900">Profile ready</div>
                    <div class="mt-2 text-sm leading-6 text-slate-500">You can upload a photo now and it will be kept with your signup request.</div>
                </div>
            </div>

            <div class="mt-8 rounded-3xl border border-dashed border-slate-200 p-5 bg-emerald-50">
                <div class="text-sm font-semibold text-emerald-800">Before you submit</div>
                <ul class="mt-3 space-y-2 text-sm text-emerald-900">
                    <li>• Use a unique username</li>
                    <li>• Use a password with at least 8 characters</li>
                    <li>• Choose only the role you truly need</li>
                </ul>
            </div>
        </section>

        <section class="rounded-[2rem] border border-slate-200 bg-white p-6 sm:p-8 shadow-sm">
            <div class="flex items-center justify-between gap-3 flex-wrap">
                <div>
                    <div class="text-sm text-slate-500">Account request form</div>
                    <h2 class="text-3xl font-black text-slate-900">Sign up</h2>
                </div>
                <a href="<?= e(app_url('modules/users/auth/login.php')) ?>" class="app-btn-outline">Back to login</a>
            </div>

            <?php if ($message): ?><div class="mt-5 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-emerald-900"><?= e($message) ?></div><?php endif; ?>
            <?php if ($error): ?><div class="mt-5 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-rose-900"><?= e($error) ?></div><?php endif; ?>

            <form method="POST" enctype="multipart/form-data" class="mt-6 grid gap-4 md:grid-cols-2">
                <div class="md:col-span-2 flex items-center gap-4 rounded-[1.5rem] border border-slate-200 bg-slate-50 p-4">
                    <img src="<?= e(app_url('assets/img/image.jpg')) ?>" alt="Avatar preview" id="signupAvatarPreview" class="h-20 w-20 rounded-[1.5rem] object-cover border border-slate-200 bg-white">
                    <div>
                        <div class="font-bold text-lg">Profile photo preview</div>
                        <div class="text-sm text-slate-500">Choose an image and preview it before you submit.</div>
                    </div>
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-semibold mb-2">Full name</label>
                    <input type="text" name="full_name" value="<?= e($_POST['full_name'] ?? '') ?>" required class="w-full rounded-2xl border border-slate-300 px-4 py-3">
                </div>
                <div>
                    <label class="block text-sm font-semibold mb-2">Username</label>
                    <input type="text" name="username" value="<?= e($_POST['username'] ?? '') ?>" required class="w-full rounded-2xl border border-slate-300 px-4 py-3">
                </div>
                <div>
                    <label class="block text-sm font-semibold mb-2">Desired role</label>
                    <select name="desired_role" class="w-full rounded-2xl border border-slate-300 px-4 py-3">
                        <option value="TASK_FORCE">Task Force</option>
                        <option value="MAYOR">Mayor</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold mb-2">Email</label>
                    <input type="email" name="email" value="<?= e($_POST['email'] ?? '') ?>" class="w-full rounded-2xl border border-slate-300 px-4 py-3">
                </div>
                <div>
                    <label class="block text-sm font-semibold mb-2">Contact number</label>
                    <input type="text" name="contact_number" value="<?= e($_POST['contact_number'] ?? '') ?>" class="w-full rounded-2xl border border-slate-300 px-4 py-3">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-semibold mb-2">Position title</label>
                    <input type="text" name="position_title" value="<?= e($_POST['position_title'] ?? '') ?>" class="w-full rounded-2xl border border-slate-300 px-4 py-3">
                </div>
                <div>
                    <label class="block text-sm font-semibold mb-2">Password</label>
                    <div class="relative">
                        <input type="password" id="signup_password" name="password" required class="w-full rounded-2xl border border-slate-300 px-4 py-3 pr-12">
                        <button type="button" data-password-toggle-target="signup_password" class="password-toggle-button absolute inset-y-0 right-3 inline-flex items-center justify-center"><i data-lucide="eye" class="w-5 h-5"></i></button>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-semibold mb-2">Confirm password</label>
                    <div class="relative">
                        <input type="password" id="signup_confirm_password" name="confirm_password" required class="w-full rounded-2xl border border-slate-300 px-4 py-3 pr-12">
                        <button type="button" data-password-toggle-target="signup_confirm_password" class="password-toggle-button absolute inset-y-0 right-3 inline-flex items-center justify-center"><i data-lucide="eye" class="w-5 h-5"></i></button>
                    </div>
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-semibold mb-2">Profile photo</label>
                    <input type="file" name="avatar" id="signupAvatarInput" accept="image/*" class="w-full rounded-2xl border border-slate-300 px-4 py-3">
                </div>
                <div class="md:col-span-2 flex items-center justify-between gap-3 flex-wrap pt-2">
                    <div class="text-sm text-slate-500">Your request will stay pending until the developer account approves it.</div>
                    <button class="app-btn-primary">Submit for approval</button>
                </div>
            </form>
        </section>
    </div>
</div>
<script src="<?= e(app_url('assets/js/app.js')) ?>"></script>
<script>
(function(){
    const input = document.getElementById('signupAvatarInput');
    const preview = document.getElementById('signupAvatarPreview');
    if (input && preview) {
        input.addEventListener('change', function(){
            const file = input.files && input.files[0];
            if (!file) return;
            const reader = new FileReader();
            reader.onload = function(e){ preview.src = e.target.result; };
            reader.readAsDataURL(file);
        });
    }
})();
</script>
</body></html>
