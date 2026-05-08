
<?php
require_once dirname(__DIR__, 3) . '/app/bootstrap.php';
app_require('app/includes/auth.php');
require_role(['developer','admin']);
$conn = db_conn();
ensure_user_account_schema($conn);
$user = current_user();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $requestId = (int)post('request_id');
    $decision = (string)post('decision');
    $notes = trim((string)post('notes'));
    if (approve_profile_request($conn, $requestId, (int)$user['id'], $decision, $notes)) {
        set_flash('success', 'Profile request ' . strtolower($decision) . ' successfully.');
    } else {
        set_flash('error', 'Unable to process profile request.');
    }
    header('Location: ' . app_url('modules/admin/profile_requests/index.php'));
    exit;
}
app_require('app/includes/header.php');
$rows = fetch_all_assoc($conn, "SELECT r.*, u.username FROM profile_update_requests r JOIN users u ON u.user_id=r.user_id ORDER BY FIELD(r.request_status,'Pending','Rejected','Approved'), r.created_at DESC");
?>
<section class="rounded-[2rem] border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-6 shadow-sm">
    <div class="flex items-center justify-between gap-3 flex-wrap">
        <div>
            <div class="text-sm text-slate-500">Developer note</div>
            <h2 class="text-3xl font-black">Legacy profile approvals</h2>
        </div>
        <div class="app-badge app-badge-amber"><?= pending_profile_request_count($conn) ?> pending</div>
    </div>
    <div class="mt-4 rounded-2xl border border-emerald-100 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">Mayor and Task Force users can now update their own profiles directly. This page remains only for older pending requests created before the direct-update change.</div>
    <div class="mt-6 space-y-4">
        <?php foreach ($rows as $row): ?>
            <div class="rounded-3xl border border-slate-200 dark:border-slate-800 p-5">
                <div class="flex items-start justify-between gap-4 flex-wrap">
                    <div class="flex items-center gap-4">
                        <img src="<?= e(user_avatar_url($row['avatar_path'] ?? null)) ?>" class="h-16 w-16 rounded-[1.25rem] object-cover border border-slate-200 dark:border-slate-800" alt="Avatar">
                        <div>
                            <div class="font-black text-lg"><?= e($row['full_name']) ?></div>
                            <div class="text-sm text-slate-500">@<?= e($row['username']) ?> · <?= e($row['position_title'] ?: 'No position title') ?></div>
                            <div class="mt-2"><?= format_status_badge($row['request_status']) ?></div>
                        </div>
                    </div>
                    <div class="text-sm text-slate-500">Requested <?= e($row['created_at']) ?></div>
                </div>
                <div class="mt-4 grid gap-4 md:grid-cols-2">
                    <div class="rounded-2xl border border-slate-200 dark:border-slate-800 p-4"><div class="text-xs text-slate-500">Email</div><div class="mt-1 font-semibold"><?= e($row['email']) ?></div></div>
                    <div class="rounded-2xl border border-slate-200 dark:border-slate-800 p-4"><div class="text-xs text-slate-500">Contact number</div><div class="mt-1 font-semibold"><?= e($row['contact_number']) ?></div></div>
                    <div class="md:col-span-2 rounded-2xl border border-slate-200 dark:border-slate-800 p-4"><div class="text-xs text-slate-500">Bio</div><div class="mt-1 text-sm text-slate-600 dark:text-slate-300"><?= e($row['bio']) ?></div></div>
                </div>
                <?php if ($row['request_status'] === 'Pending'): ?>
                <div class="mt-4 rounded-2xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900 px-4 py-3 text-sm text-slate-500">This request is preserved for history only. New personal profile changes now apply directly without developer approval.</div>
                <?php elseif (!empty($row['review_notes'])): ?>
                <div class="mt-4 text-sm text-slate-500">Review note: <?= e($row['review_notes']) ?></div>
                <?php endif; ?>
            </div>
        <?php endforeach; if (!$rows): ?>
            <div class="text-center text-slate-500 py-10">No legacy profile approval records found.</div>
        <?php endif; ?>
    </div>
</section>
<?php app_require('app/includes/footer.php'); ?>
