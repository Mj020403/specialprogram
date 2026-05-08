<?php
require_once dirname(__DIR__, 4) . '/app/bootstrap.php';

$conn = db_conn();require_once app_path('app/config/database.php');
app_require('app/includes/auth.php');
app_require('app/includes/ui.php');

require_role(['approver']);
$user_id = (int)$_SESSION['user_id'];

$pendingTasks = (int)$conn->query("SELECT COUNT(*) AS total FROM document_workflow_tasks WHERE assigned_user_id = {$user_id} AND status = 'pending'")->fetch_assoc()['total'];
$dueSoon = (int)$conn->query("SELECT COUNT(*) AS total FROM document_workflow_tasks WHERE assigned_user_id = {$user_id} AND status = 'pending' AND due_at IS NOT NULL AND due_at <= DATE_ADD(NOW(), INTERVAL 3 DAY)")->fetch_assoc()['total'];
$completedTasks = (int)$conn->query("SELECT COUNT(*) AS total FROM document_workflow_tasks WHERE assigned_user_id = {$user_id} AND status = 'completed'")->fetch_assoc()['total'];
$notifCount = (int)$conn->query("SELECT COUNT(*) AS total FROM notifications WHERE user_id = {$user_id} AND is_read = 0")->fetch_assoc()['total'];

$listStmt = $conn->prepare("\n    SELECT t.id, t.task_title, t.status, t.due_at, d.document_number, d.title, d.status AS document_status\n    FROM document_workflow_tasks t\n    JOIN documents d ON d.id = t.document_id\n    WHERE t.assigned_user_id = ? AND t.status = 'pending'\n    ORDER BY t.created_at ASC\n    LIMIT 8\n");
$listStmt->bind_param('i', $user_id);
$listStmt->execute();
$taskRows = $listStmt->get_result();
$listStmt->close();

$recentStmt = $conn->prepare("\n    SELECT da.decision, da.decided_at, d.document_number, d.title\n    FROM document_approvals da\n    JOIN documents d ON d.id = da.document_id\n    WHERE da.decided_by_user_id = ?\n    ORDER BY da.decided_at DESC\n    LIMIT 5\n");
$recentStmt->bind_param('i', $user_id);
$recentStmt->execute();
$recentActions = $recentStmt->get_result();
$recentStmt->close();

app_require('app/includes/header.php');
page_card_start('Approver Dashboard', 'Review approval tasks faster, keep an eye on due dates, and track your latest decisions.');
?>

<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-8">
    <?php ui_stat_card('Pending Approvals', $pendingTasks, 'check-check', 'blue', 'Waiting for your review'); ?>
    <?php ui_stat_card('Due Soon', $dueSoon, 'clock-3', 'amber', 'Needs action within 3 days'); ?>
    <?php ui_stat_card('Completed Reviews', $completedTasks, 'badge-check', 'emerald', 'Already processed by you'); ?>
    <?php ui_stat_card('Unread Notifications', $notifCount, 'bell-ring', 'indigo', 'Recent alerts'); ?>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4 mb-8">
    <?php ui_quick_link('/harvest/approver/tasks/index.php', 'Approval Tasks', 'Open your pending approval list and act fast.', 'clipboard-list'); ?>
    <?php ui_quick_link('/harvest/workflow/timeline.php', 'Workflow Timeline', 'Trace the movement of documents across the process.', 'git-branch'); ?>
    <?php ui_quick_link('/harvest/shared/index.php', 'Shared With Me', 'Check files shared directly to your account.', 'share-2'); ?>
    <?php ui_quick_link('/harvest/modules/agri/notifications/index.php', 'Notifications', 'See unread workflow notices and updates.', 'bell'); ?>
</div>

<div class="grid grid-cols-1 xl:grid-cols-[1.15fr_0.85fr] gap-6">
    <div class="rounded-3xl bg-white dark:bg-slate-900 p-6 shadow-sm ring-1 ring-slate-200 dark:ring-slate-800">
        <div class="flex items-center justify-between gap-3 mb-5">
            <div>
                <h2 class="text-xl font-semibold text-slate-900 dark:text-white">Approval queue</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Tasks assigned specifically to you.</p>
            </div>
            <a href="/harvest/approver/tasks/index.php" class="text-sm font-semibold text-blue-600 dark:text-blue-300">View queue</a>
        </div>

        <?php if ($taskRows->num_rows > 0): ?>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr>
                            <th>Task</th>
                            <th>Document No.</th>
                            <th>Title</th>
                            <th>Status</th>
                            <th>Due</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $taskRows->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['task_title'] ?: 'Approval Task') ?></td>
                                <td><?= htmlspecialchars($row['document_number'] ?? '-') ?></td>
                                <td class="font-medium text-slate-900 dark:text-white"><?= htmlspecialchars($row['title']) ?></td>
                                <td><?= ui_status_badge($row['status']) ?></td>
                                <td><?= htmlspecialchars($row['due_at'] ?: '-') ?></td>
                                <td><a href="/harvest/approver/tasks/view.php?id=<?= (int)$row['id'] ?>">Open</a></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <?php ui_empty_state('Nothing pending', 'You have no approval tasks assigned at the moment.', 'badge-check'); ?>
        <?php endif; ?>
    </div>

    <div class="rounded-3xl bg-white dark:bg-slate-900 p-6 shadow-sm ring-1 ring-slate-200 dark:ring-slate-800">
        <h2 class="text-xl font-semibold text-slate-900 dark:text-white">Recent decisions</h2>
        <div class="mt-5 space-y-4">
            <?php if ($recentActions->num_rows > 0): ?>
                <?php while ($row = $recentActions->fetch_assoc()): ?>
                    <div class="rounded-2xl bg-slate-50 dark:bg-slate-950 p-4 ring-1 ring-slate-200 dark:ring-slate-800">
                        <div class="flex items-center justify-between gap-2">
                            <div class="font-semibold text-slate-900 dark:text-white"><?= htmlspecialchars($row['document_number'] ?? 'Document') ?></div>
                            <span><?= ui_status_badge($row['decision']) ?></span>
                        </div>
                        <div class="mt-2 text-sm text-slate-600 dark:text-slate-300"><?= htmlspecialchars($row['title']) ?></div>
                        <div class="mt-2 text-xs text-slate-400"><?= htmlspecialchars($row['decided_at']) ?></div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <?php ui_empty_state('No decisions yet', 'Your approval history will appear here after you act on tasks.', 'history'); ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?= app_dashboard_insights_panel($conn, 'Approver dashboard snapshot', 'Live charts for requests, rules, and the current municipal situation to support faster decisions.') ?>
<?php
page_card_end();
app_require('app/includes/footer.php');
?>
