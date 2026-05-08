<?php
require_once dirname(__DIR__, 3) . '/app/bootstrap.php';
app_require('app/includes/auth.php');
require_role(['developer','admin']);
$conn = db_conn();
ensure_user_account_schema($conn);
$user = current_user();
$tempPasswordNotice = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)post('action');
    if ($action === 'decide_reset') {
        $result = approve_password_reset_request($conn, (int)post('request_id'), (int)$user['id'], (string)post('decision'), trim((string)post('notes')));
        if ($result && ($result['decision'] ?? '') === 'Approved') {
            $tempPasswordNotice = "Temporary password for " . ($result['user']['username'] ?? 'user') . ": " . ($result['temp_password'] ?? '');
            set_flash('success', $tempPasswordNotice);
        } elseif ($result) {
            set_flash('success', 'Password reset request processed.');
        } else {
            set_flash('error', 'Unable to process password reset request.');
        }
    }
    if ($action === 'decide_signup') {
        if (approve_signup_request($conn, (int)post('signup_request_id'), (int)$user['id'], (string)post('decision'), trim((string)post('notes')))) {
            set_flash('success', 'Signup request processed.');
        } else {
            set_flash('error', 'Unable to process signup request.');
        }
    }
    if ($action === 'save_permissions') {
        $roleId = (int)post('role_id');
        $flags = [];
        foreach (['can_manage_users','can_interview','can_monitor','can_manage_events','can_take_attendance','can_view_dashboard','can_view_reports','can_export_data','can_scan_qr'] as $flag) {
            $flags[$flag] = (string)post($flag) === '1';
        }
        if (update_role_permissions($conn, $roleId, $flags)) {
            log_account_activity($conn, null, (int)$user['id'], 'permissions_updated', 'Updated role permissions.', ['role_id' => $roleId]);
            set_flash('success', 'Role permissions updated.');
        } else {
            set_flash('error', 'Unable to update permissions.');
        }
    }
    header('Location: ' . app_url('modules/admin/security/index.php'));
    exit;
}

$requests = fetch_all_assoc($conn, "SELECT r.*, u.username, u.full_name FROM password_reset_requests r JOIN users u ON u.user_id=r.user_id ORDER BY FIELD(r.request_status,'Pending','Approved','Rejected'), r.created_at DESC");
$signupRequests = table_exists($conn, 'signup_requests') ? fetch_all_assoc($conn, "SELECT * FROM signup_requests ORDER BY FIELD(request_status,'Pending','Approved','Rejected'), created_at DESC") : [];
$roles = role_permissions_rows($conn);
app_require('app/includes/header.php');
?>
<div class="grid gap-6 xl:grid-cols-[1.1fr_0.9fr]">
    <section class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex items-center justify-between gap-3 flex-wrap">
            <div>
                <div class="text-sm text-slate-500">Developer security desk</div>
                <h2 class="text-3xl font-black">Password reset approvals</h2>
            </div>
            <div class="app-badge app-badge-amber"><?= pending_password_reset_count($conn) ?> pending</div>
        </div>
        <div class="mt-6 space-y-4">
            <?php foreach ($requests as $req): ?>
                <div class="rounded-[1.5rem] border border-slate-200 p-4">
                    <div class="flex items-center justify-between gap-3 flex-wrap">
                        <div>
                            <div class="font-bold text-lg"><?= e($req['full_name']) ?> <span class="text-sm text-slate-500">(<?= e($req['username']) ?>)</span></div>
                            <div class="text-sm text-slate-500">Requested: <?= e(date('M d, Y h:i A', strtotime((string)$req['created_at']))) ?></div>
                            <?php if (!empty($req['reason'])): ?><div class="mt-2 text-sm"><?= e($req['reason']) ?></div><?php endif; ?>
                        </div>
                        <div class="app-inline-badge"><?= e((string)$req['request_status']) ?></div>
                    </div>
                    <?php if (($req['request_status'] ?? '') === 'Pending'): ?>
                    <form method="POST" class="mt-4 grid gap-3 md:grid-cols-[1fr_auto_auto]">
                        <input type="hidden" name="action" value="decide_reset">
                        <input type="hidden" name="request_id" value="<?= (int)$req['reset_request_id'] ?>">
                        <input type="text" name="notes" placeholder="Developer notes" class="rounded-2xl border border-slate-300 px-4 py-3">
                        <button name="decision" value="Approved" class="rounded-2xl bg-emerald-700 px-5 py-3 font-semibold text-white">Approve & issue temp password</button>
                        <button name="decision" value="Rejected" class="rounded-2xl border border-rose-200 bg-rose-50 px-5 py-3 font-semibold text-rose-700">Reject</button>
                    </form>
                    <?php elseif (($req['request_status'] ?? '') === 'Approved' && !empty($req['temp_password_plain'])): ?>
                        <div class="mt-4 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                            Temporary password issued: <strong><?= e($req['temp_password_plain']) ?></strong>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
            <?php if (!$requests): ?><div class="text-slate-500">No password reset requests yet.</div><?php endif; ?>
        </div>
    </section>

    <section class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
        <div>
            <div class="text-sm text-slate-500">Role access</div>
            <h2 class="text-3xl font-black">Permissions matrix</h2>
        </div>
        <div class="mt-6 space-y-4">
            <?php foreach ($roles as $role): ?>
                <form method="POST" class="rounded-[1.5rem] border border-slate-200 p-4">
                    <input type="hidden" name="action" value="save_permissions">
                    <input type="hidden" name="role_id" value="<?= (int)$role['role_id'] ?>">
                    <div class="font-bold text-lg"><?= e($role['role_name']) ?></div>
                    <div class="mt-4 grid grid-cols-2 gap-3 text-sm">
                        <?php foreach (['can_manage_users'=>'Manage users','can_interview'=>'Interviews','can_monitor'=>'Monitoring','can_manage_events'=>'Events','can_take_attendance'=>'Attendance','can_view_dashboard'=>'Dashboard','can_view_reports'=>'Reports','can_export_data'=>'Export','can_scan_qr'=>'Scan QR'] as $key => $label): ?>
                            <label class="flex items-center gap-2 rounded-xl border border-slate-200 px-3 py-2">
                                <input type="checkbox" name="<?= e($key) ?>" value="1" <?= !empty($role[$key]) ? 'checked' : '' ?>>
                                <span><?= e($label) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <button class="mt-4 app-btn-primary">Save permissions</button>
                </form>
            <?php endforeach; ?>
        </div>
    </section>
</div>
<?php app_require('app/includes/footer.php'); ?>
