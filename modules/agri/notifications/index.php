<?php
require_once dirname(__DIR__, 3) . '/app/bootstrap.php';

$conn = db_conn();app_require('app/includes/auth.php');
require_role(['task_force','admin','mayor']);
require_once app_path('app/config/database.php');
app_require('app/includes/app_helpers.php');

if (isset($_GET['mark']) && $_GET['mark'] === 'all') {
    if (table_exists($conn, 'notifications')) {
        $conn->query("UPDATE notifications SET is_read = 1, read_at = COALESCE(read_at, NOW()) WHERE is_read = 0");
    }
    header('Location: /harvest/modules/agri/notifications/index.php');
    exit;
}

app_require('app/includes/header.php');

$filter = $_GET['filter'] ?? 'all';
$where = $filter === 'unread' ? 'WHERE is_read = 0' : '';
$rows = table_exists($conn, 'notifications')
    ? fetch_all_assoc($conn, "SELECT notification_id, title, message, severity, is_read, created_at FROM notifications {$where} ORDER BY notification_id DESC LIMIT 100")
    : [];
$totalCount = table_exists($conn, 'notifications') ? (int)scalar($conn, "SELECT COUNT(*) FROM notifications", 0) : 0;
$unreadCount = table_exists($conn, 'notifications') ? (int)scalar($conn, "SELECT COUNT(*) FROM notifications WHERE is_read = 0", 0) : 0;
$highCount = table_exists($conn, 'notifications') ? (int)scalar($conn, "SELECT COUNT(*) FROM notifications WHERE severity IN ('High','Critical')", 0) : 0;
?>
<section class="rounded-[2rem] border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-6 shadow-sm">
<div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
    <div>
        <div class="text-sm text-slate-500">Smart notification system</div>
        <h2 class="text-2xl font-black">Alerts and follow-ups</h2>
        <p class="mt-2 text-sm text-slate-500">Keep field operations focused with unread alerts, high-priority cases, and quick cleanup.</p>
    </div>
    <div class="flex flex-wrap gap-2">
        <a href="/harvest/modules/agri/notifications/index.php?filter=all" class="<?= $filter === 'all' ? 'app-btn-primary' : 'app-btn-outline' ?>">All</a>
        <a href="/harvest/modules/agri/notifications/index.php?filter=unread" class="<?= $filter === 'unread' ? 'app-btn-primary' : 'app-btn-outline' ?>">Unread</a>
        <a href="/harvest/modules/agri/notifications/index.php?mark=all" class="app-btn-outline">Mark all read</a>
    </div>
</div>
<div class="mt-5 grid gap-4 md:grid-cols-3">
    <div class="app-soft-card p-4"><div class="text-sm text-slate-500">All notifications</div><div class="mt-2 text-3xl font-black"><?= $totalCount ?></div></div>
    <div class="app-soft-card p-4"><div class="text-sm text-slate-500">Unread</div><div class="mt-2 text-3xl font-black"><?= $unreadCount ?></div></div>
    <div class="app-soft-card p-4"><div class="text-sm text-slate-500">High priority</div><div class="mt-2 text-3xl font-black"><?= $highCount ?></div></div>
</div>
<div class="mt-5 space-y-4"><?php foreach($rows as $r): ?><div class="rounded-3xl border border-slate-200 dark:border-slate-800 p-5"><div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"><div class="font-semibold"><?= e($r['title']) ?></div><div class="flex items-center gap-2"><?= format_status_badge($r['severity']) ?><?= !empty($r['is_read']) ? format_status_badge('read') : format_status_badge('unread') ?></div></div><div class="mt-2 text-sm text-slate-600 dark:text-slate-300"><?= e($r['message']) ?></div><div class="mt-2 text-xs text-slate-500"><?= e($r['created_at']) ?></div></div><?php endforeach; if(!$rows): ?><div class="text-sm text-slate-500">No notifications available.</div><?php endif; ?></div>
</section>
<?php app_require('app/includes/footer.php'); ?>
