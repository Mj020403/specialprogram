<?php
require_once dirname(__DIR__, 3) . '/app/bootstrap.php';
app_require('app/includes/auth.php');
require_login();
$conn = db_conn();
ensure_user_account_schema($conn);
$user = current_user();
$targetUserId = (int)getv('user_id', (int)$user['id']);
if ($targetUserId !== (int)$user['id'] && !in_array(($user['role'] ?? ''), ['developer','admin'], true)) {
    $targetUserId = (int)$user['id'];
}
$targetRow = fetch_one($conn, "SELECT full_name, username FROM users WHERE user_id=" . $targetUserId . " LIMIT 1");
$rows = fetch_account_activity($conn, $targetUserId, 40);
app_require('app/includes/header.php');
?>
<section class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
    <div class="flex items-center justify-between gap-3 flex-wrap">
        <div>
            <div class="text-sm text-slate-500">Audit trail</div>
            <h2 class="text-3xl font-black">Account activity<?= !empty($targetRow['full_name']) ? ' · ' . e($targetRow['full_name']) : '' ?></h2>
        </div>
        <a href="<?= e(app_url('modules/users/account/change_password.php')) ?>" class="app-btn-outline">Change password</a>
    </div>
    <div class="mt-6 overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead><tr class="text-left text-slate-500 border-b"><th class="py-3 pr-4">When</th><th class="py-3 pr-4">Activity</th><th class="py-3 pr-4">Summary</th><th class="py-3">Actor</th></tr></thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="4" class="py-6 text-slate-500">No activity yet.</td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $row): ?>
                <tr class="border-b border-slate-100 align-top">
                    <td class="py-3 pr-4 whitespace-nowrap"><?= e(date('M d, Y h:i A', strtotime((string)$row['created_at']))) ?></td>
                    <td class="py-3 pr-4"><span class="app-inline-badge"><?= e(str_replace('_',' ', (string)$row['activity_type'])) ?></span></td>
                    <td class="py-3 pr-4"><?= e((string)$row['activity_summary']) ?></td>
                    <td class="py-3"><?= e((string)($row['actor_name'] ?? 'System')) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php app_require('app/includes/footer.php'); ?>
