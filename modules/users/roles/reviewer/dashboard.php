<?php
require_once dirname(__DIR__, 4) . '/app/bootstrap.php';

$conn = db_conn();require_once app_path('app/config/database.php');
app_require('app/includes/auth.php');
app_require('app/includes/ui.php');

require_role(['reviewer']);
$user_id = (int)$_SESSION['user_id'];

$pendingTasks = (int)$conn->query("SELECT COUNT(*) AS total FROM document_workflow_tasks WHERE assigned_user_id = {$user_id} AND status = 'pending'")->fetch_assoc()['total'];
$notifCount = (int)$conn->query("SELECT COUNT(*) AS total FROM notifications WHERE user_id = {$user_id} AND is_read = 0")->fetch_assoc()['total'];
$docCount = (int)$conn->query("SELECT COUNT(*) AS total FROM documents WHERE deleted_at IS NULL")->fetch_assoc()['total'];

$recentNotifs = $conn->query("
    SELECT title, message, is_read, created_at, id
    FROM notifications
    WHERE user_id = {$user_id}
    ORDER BY created_at DESC
    LIMIT 5
");

app_require('app/includes/header.php');

page_card_start('Reviewer Dashboard', 'A simple reviewer home with the upgraded layout and shared system components.');
?>

<div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-8">
    <?php ui_stat_card('Pending Tasks', $pendingTasks, 'clipboard-list', 'blue', 'Assigned workflow steps'); ?>
    <?php ui_stat_card('Unread Notifications', $notifCount, 'bell-ring', 'amber', 'Unread notices'); ?>
    <?php ui_stat_card('Documents Available', $docCount, 'files', 'indigo', 'Searchable records'); ?>
</div>

<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
    <?php ui_quick_link('/harvest/search/index.php', 'Search Documents', 'Use filters and status badges to find records quickly.', 'search'); ?>
    <?php ui_quick_link('/harvest/modules/agri/notifications/index.php', 'Notifications', 'Catch up on unread updates.', 'bell'); ?>
    <?php ui_quick_link('/harvest/modules/users/account/profile/index.php', 'Profile', 'Update your personal account details.', 'user-circle-2'); ?>
</div>

<div class="rounded-3xl bg-white dark:bg-slate-900 p-6 shadow-sm ring-1 ring-slate-200 dark:ring-slate-800">
    <h2 class="text-xl font-semibold text-slate-900 dark:text-white">Recent notifications</h2>
    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Your latest updates in the refreshed UI.</p>

    <div class="mt-5 space-y-3">
        <?php if ($recentNotifs && $recentNotifs->num_rows > 0): ?>
            <?php while ($row = $recentNotifs->fetch_assoc()): ?>
                <div class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-slate-50/80 dark:bg-slate-800/70 p-4">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <div class="font-semibold text-slate-900 dark:text-white"><?= htmlspecialchars($row['title']) ?></div>
                            <div class="mt-1 text-sm leading-6 text-slate-500 dark:text-slate-400"><?= htmlspecialchars($row['message']) ?></div>
                        </div>
                        <div class="text-right">
                            <div><?= ui_status_badge((int)$row['is_read'] === 1 ? 'read' : 'unread') ?></div>
                            <div class="mt-2 text-xs text-slate-400"><?= htmlspecialchars($row['created_at']) ?></div>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <?php ui_empty_state('No notifications yet', 'When the system sends you updates, they will show here.', 'bell-off'); ?>
        <?php endif; ?>
    </div>
</div>

<?= app_dashboard_insights_panel($conn, 'Reviewer dashboard snapshot', 'Live charts for review work, queues, rules, and the current municipal situation.') ?>
<?php
page_card_end();
app_require('app/includes/footer.php');
?>