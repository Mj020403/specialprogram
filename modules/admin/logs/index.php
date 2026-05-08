<?php
require_once dirname(__DIR__, 3) . '/app/bootstrap.php';

$conn = db_conn();require_once app_path('app/config/database.php');
app_require('app/includes/auth.php');
app_require('app/includes/ui.php');

require_role(['super_admin', 'system_admin', 'admin', 'auditor', 'mayor', 'executive']);

$action = trim($_GET['action'] ?? '');
$sql = "SELECT al.*, u.email FROM activity_logs al LEFT JOIN users u ON u.id = al.user_id WHERE 1=1";
$params = [];
$types = '';
if ($action !== '') {
    $sql .= ' AND al.action_code = ?';
    $params[] = $action;
    $types .= 's';
}
$sql .= ' ORDER BY al.created_at DESC LIMIT 300';
$stmt = $conn->prepare($sql);
if ($types !== '') {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

$loginAttempts = $conn->query("SELECT email, ip_address, attempted_at, is_success FROM security_login_attempts ORDER BY attempted_at DESC LIMIT 20");
$accessLogs = $conn->query("SELECT dal.*, u.email FROM document_access_logs dal LEFT JOIN users u ON u.id = dal.user_id ORDER BY dal.created_at DESC LIMIT 20");
$actions = $conn->query("SELECT DISTINCT action_code FROM activity_logs ORDER BY action_code ASC");

app_require('app/includes/header.php');
page_card_start('Audit Logs', 'Review user activity, document access, and login security events.');
?>

<form method="GET" class="rounded-3xl bg-slate-50 dark:bg-slate-900 p-5 ring-1 ring-slate-200 dark:ring-slate-800 mb-6">
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
        <div>
            <label class="block text-sm font-semibold mb-2">Action Filter</label>
            <select name="action" class="<?= ui_select_class() ?>">
                <option value="">All Actions</option>
                <?php while ($row = $actions->fetch_assoc()): ?>
                    <option value="<?= htmlspecialchars($row['action_code']) ?>" <?= $action === $row['action_code'] ? 'selected' : '' ?>><?= htmlspecialchars($row['action_code']) ?></option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="flex gap-3">
            <button type="submit" class="inline-flex items-center gap-2 rounded-2xl bg-blue-600 px-5 py-3 font-semibold text-white shadow-sm hover:bg-blue-700 transition"><i data-lucide="filter" class="w-4 h-4"></i>Apply</button>
            <a href="/harvest/admin/logs/index.php" class="inline-flex items-center gap-2 rounded-2xl bg-white dark:bg-slate-950 px-5 py-3 font-semibold text-slate-700 dark:text-slate-200 ring-1 ring-slate-300 dark:ring-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800 transition"><i data-lucide="rotate-ccw" class="w-4 h-4"></i>Reset</a>
        </div>
    </div>
</form>

<div class="grid grid-cols-1 xl:grid-cols-2 gap-6 mb-6">
    <div class="overflow-hidden rounded-3xl bg-white dark:bg-slate-900 shadow-sm ring-1 ring-slate-200 dark:ring-slate-800">
        <div class="px-6 py-5 border-b border-slate-200 dark:border-slate-800"><h2 class="text-xl font-semibold">Activity Logs</h2></div>
        <div class="overflow-x-auto"><table class="min-w-full text-sm"><thead class="bg-slate-50 dark:bg-slate-800"><tr><th class="px-4 py-3 font-semibold">Date</th><th class="px-4 py-3 font-semibold">User</th><th class="px-4 py-3 font-semibold">Action</th><th class="px-4 py-3 font-semibold">Entity</th><th class="px-4 py-3 font-semibold">Description</th></tr></thead><tbody class="divide-y divide-slate-100 dark:divide-slate-800"><?php while ($row = $result->fetch_assoc()): ?><tr><td class="px-4 py-3"><?= htmlspecialchars($row['created_at']) ?></td><td class="px-4 py-3"><?= htmlspecialchars($row['email'] ?: 'System') ?></td><td class="px-4 py-3"><?= ui_status_badge((string)$row['action_code']) ?></td><td class="px-4 py-3"><?= htmlspecialchars(($row['entity_type'] ?: '-') . ' #' . ($row['entity_id'] ?: '-')) ?></td><td class="px-4 py-3"><?= htmlspecialchars($row['description'] ?: '') ?></td></tr><?php endwhile; ?></tbody></table></div>
    </div>
    <div class="space-y-6">
        <div class="overflow-hidden rounded-3xl bg-white dark:bg-slate-900 shadow-sm ring-1 ring-slate-200 dark:ring-slate-800">
            <div class="px-6 py-5 border-b border-slate-200 dark:border-slate-800"><h2 class="text-xl font-semibold">Recent Login Attempts</h2></div>
            <div class="overflow-x-auto"><table class="min-w-full text-sm"><thead class="bg-slate-50 dark:bg-slate-800"><tr><th class="px-4 py-3 font-semibold">Time</th><th class="px-4 py-3 font-semibold">Email</th><th class="px-4 py-3 font-semibold">IP</th><th class="px-4 py-3 font-semibold">Result</th></tr></thead><tbody class="divide-y divide-slate-100 dark:divide-slate-800"><?php if ($loginAttempts && $loginAttempts->num_rows > 0): while ($row = $loginAttempts->fetch_assoc()): ?><tr><td class="px-4 py-3"><?= htmlspecialchars($row['attempted_at']) ?></td><td class="px-4 py-3"><?= htmlspecialchars($row['email']) ?></td><td class="px-4 py-3"><?= htmlspecialchars($row['ip_address']) ?></td><td class="px-4 py-3"><?= ui_status_badge((int)$row['is_success'] === 1 ? 'successful' : 'failed') ?></td></tr><?php endwhile; else: ?><tr><td colspan="4" class="px-4 py-8 text-center text-slate-500 dark:text-slate-400">No login attempt data yet.</td></tr><?php endif; ?></tbody></table></div>
        </div>
        <div class="overflow-hidden rounded-3xl bg-white dark:bg-slate-900 shadow-sm ring-1 ring-slate-200 dark:ring-slate-800">
            <div class="px-6 py-5 border-b border-slate-200 dark:border-slate-800"><h2 class="text-xl font-semibold">Document Access Logs</h2></div>
            <div class="overflow-x-auto"><table class="min-w-full text-sm"><thead class="bg-slate-50 dark:bg-slate-800"><tr><th class="px-4 py-3 font-semibold">Time</th><th class="px-4 py-3 font-semibold">User</th><th class="px-4 py-3 font-semibold">Document</th><th class="px-4 py-3 font-semibold">Action</th></tr></thead><tbody class="divide-y divide-slate-100 dark:divide-slate-800"><?php if ($accessLogs && $accessLogs->num_rows > 0): while ($row = $accessLogs->fetch_assoc()): ?><tr><td class="px-4 py-3"><?= htmlspecialchars($row['created_at']) ?></td><td class="px-4 py-3"><?= htmlspecialchars($row['email'] ?: 'Guest/System') ?></td><td class="px-4 py-3">#<?= (int)$row['document_id'] ?></td><td class="px-4 py-3"><?= htmlspecialchars($row['action_code']) ?></td></tr><?php endwhile; else: ?><tr><td colspan="4" class="px-4 py-8 text-center text-slate-500 dark:text-slate-400">No document access records yet.</td></tr><?php endif; ?></tbody></table></div>
        </div>
    </div>
</div>

<?php page_card_end(); app_require('app/includes/footer.php'); ?>
