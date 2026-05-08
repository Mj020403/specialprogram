<?php
require_once dirname(__DIR__, 4) . '/app/bootstrap.php';

$conn = db_conn();require_once app_path('app/config/database.php');
app_require('app/includes/auth.php');
app_require('app/includes/ui.php');

require_role(['auditor']);
$user_id = (int)$_SESSION['user_id'];

$logCount = (int)$conn->query("SELECT COUNT(*) AS total FROM activity_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetch_assoc()['total'];
$approvedCount = (int)$conn->query("SELECT COUNT(*) AS total FROM documents WHERE status = 'approved' AND deleted_at IS NULL")->fetch_assoc()['total'];
$archivedCount = (int)$conn->query("SELECT COUNT(*) AS total FROM documents WHERE status = 'archived' AND deleted_at IS NULL")->fetch_assoc()['total'];
$notifCount = (int)$conn->query("SELECT COUNT(*) AS total FROM notifications WHERE user_id = {$user_id} AND is_read = 0")->fetch_assoc()['total'];

$logs = $conn->query("
    SELECT description, action_code, entity_type, created_at
    FROM activity_logs
    ORDER BY id DESC
    LIMIT 8
");

app_require('app/includes/header.php');

page_card_start('Auditor Dashboard', 'A simplified overview for compliance checks, reports, and activity tracing.');
?>

<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-8">
    <?php ui_stat_card('Activity Logs (30 Days)', $logCount, 'scroll-text', 'blue', 'Recent traceability'); ?>
    <?php ui_stat_card('Approved Documents', $approvedCount, 'badge-check', 'emerald', 'Current approved records'); ?>
    <?php ui_stat_card('Archived Documents', $archivedCount, 'archive', 'slate', 'Stored and retained'); ?>
    <?php ui_stat_card('Unread Notifications', $notifCount, 'bell-ring', 'amber', 'Auditor alerts'); ?>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4 mb-8">
    <?php ui_quick_link('/harvest/admin/logs/index.php', 'Audit Logs', 'Inspect raw activity log entries in the system.', 'shield-check'); ?>
    <?php ui_quick_link('/harvest/modules/agri/reports/index.php', 'Reports', 'View workflow and category reporting.', 'bar-chart-3'); ?>
    <?php ui_quick_link('/harvest/search/index.php', 'Search', 'Search records by title, category, and status.', 'search'); ?>
    <?php ui_quick_link('/harvest/modules/agri/notifications/index.php', 'Notifications', 'See recent system notices.', 'bell'); ?>
</div>

<div class="rounded-3xl bg-white dark:bg-slate-900 p-6 shadow-sm ring-1 ring-slate-200 dark:ring-slate-800">
    <div class="flex items-center justify-between gap-3 mb-5">
        <div>
            <h2 class="text-xl font-semibold text-slate-900 dark:text-white">Latest activity trail</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Most recent actions recorded in the platform.</p>
        </div>
        <a href="/harvest/admin/logs/index.php" class="text-sm font-semibold text-blue-600 dark:text-blue-300">Open audit logs</a>
    </div>

    <?php if ($logs && $logs->num_rows > 0): ?>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr>
                        <th>Action</th>
                        <th>Entity</th>
                        <th>Description</th>
                        <th>Created</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $logs->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['action_code']) ?></td>
                            <td><?= htmlspecialchars($row['entity_type']) ?></td>
                            <td class="font-medium text-slate-900 dark:text-white"><?= htmlspecialchars($row['description']) ?></td>
                            <td><?= htmlspecialchars($row['created_at']) ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <?php ui_empty_state('No audit entries found', 'Once actions are logged, the latest trail will appear here.', 'shield'); ?>
    <?php endif; ?>
</div>

<?= app_dashboard_insights_panel($conn, 'Auditor dashboard snapshot', 'Live charts for database activity, queues, rules, and the current operational situation.') ?>
<?php
page_card_end();
app_require('app/includes/footer.php');
?>