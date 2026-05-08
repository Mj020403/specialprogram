<?php
require_once dirname(__DIR__, 3) . '/app/bootstrap.php';
$conn = db_conn();
app_require('app/includes/session.php');
app_require('modules/users/auth/auth_page_helpers.php');
app_require('app/includes/auth.php');
$message = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identity = trim((string)post('identity'));
    $reason = trim((string)post('reason'));
    $safeIdentity = $conn->real_escape_string($identity);
    $row = fetch_one($conn, "SELECT user_id, username, email FROM users WHERE is_active=1 AND (username='{$safeIdentity}' OR email='{$safeIdentity}') LIMIT 1");
    if ($row) {
        if (create_password_reset_request($conn, (int)$row['user_id'], (string)$identity, $reason)) {
            $message = 'Password reset request submitted. The developer account will review it.';
        } else {
            $error = 'Unable to submit your request right now.';
        }
    } else {
        $error = 'No active account matched that username or email.';
    }
}
?>
<!DOCTYPE html>
<html lang="en"><head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - HARVEST</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-50 flex items-center justify-center p-4">
    <div class="w-full max-w-xl rounded-[2rem] border border-slate-200 bg-white p-8 shadow-sm">
        <div class="text-sm text-slate-500">Account recovery</div>
        <h1 class="mt-1 text-3xl font-black text-slate-900">Request password reset</h1>
        <p class="mt-2 text-slate-600">Enter your username or email. The developer account will approve and issue a temporary password.</p>
        <?php if ($message): ?><div class="mt-4 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-emerald-900"><?= e($message) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="mt-4 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-rose-900"><?= e($error) ?></div><?php endif; ?>
        <form method="POST" class="mt-6 grid gap-4">
            <div>
                <label class="block text-sm font-semibold mb-2">Username or email</label>
                <input name="identity" required class="w-full rounded-2xl border border-slate-300 px-4 py-3">
            </div>
            <div>
                <label class="block text-sm font-semibold mb-2">Reason</label>
                <textarea name="reason" rows="3" class="w-full rounded-2xl border border-slate-300 px-4 py-3" placeholder="Optional reason or context"></textarea>
            </div>
            <div class="flex items-center justify-between gap-3">
                <a href="<?= e(app_url('modules/users/auth/login.php')) ?>" class="text-sm font-semibold text-slate-600">Back to login</a>
                <button class="rounded-2xl bg-emerald-700 px-5 py-3 text-white font-semibold">Submit request</button>
            </div>
        </form>
    </div>
</body></html>
