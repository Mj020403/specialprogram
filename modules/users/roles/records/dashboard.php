<?php
require_once dirname(__DIR__, 4) . '/app/bootstrap.php';

$conn = db_conn();require_once app_path('app/config/database.php');
app_require('app/includes/auth.php');
app_require('app/includes/ui.php');

require_role(['records_officer']);
$user_id = (int)$_SESSION['user_id'];

$pendingTasks = (int)$conn->query("SELECT COUNT(*) AS total FROM document_workflow_tasks WHERE assigned_user_id = {$user_id} AND status = 'pending'")->fetch_assoc()['total'];
$overdueTasks = (int)$conn->query("SELECT COUNT(*) AS total FROM document_workflow_tasks WHERE assigned_user_id = {$user_id} AND status = 'pending' AND due_at IS NOT NULL AND due_at < NOW()")->fetch_assoc()['total'];
$workflowDocs = (int)$conn->query("SELECT COUNT(*) AS total FROM documents WHERE status IN ('submitted', 'in_review') AND deleted_at IS NULL")->fetch_assoc()['total'];
$returnedDocs = (int)$conn->query("SELECT COUNT(*) AS total FROM documents WHERE status = 'for_revision' AND deleted_at IS NULL")->fetch_assoc()['total'];

$listStmt = $conn->prepare("\n    SELECT t.id, t.task_title, t.status, t.due_at, d.document_number, d.title, d.status AS document_status\n    FROM document_workflow_tasks t\n    JOIN documents d ON d.id = t.document_id\n    WHERE t.assigned_user_id = ? AND t.status = 'pending'\n    ORDER BY t.created_at ASC\n    LIMIT 8\n");
$listStmt->bind_param('i', $user_id);
$listStmt->execute();
$taskRows = $listStmt->get_result();
$listStmt->close();

app_require('app/includes/header.php');
page_card_start('Records Officer Dashboard', 'Track incoming workflow items, overdue actions, and the document queue without extra clutter.');
?>

<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-8">
    <?php ui_stat_card('Pending Tasks', $pendingTasks, 'clipboard-list', 'blue', 'Assigned to you'); ?>
    <?php ui_stat_card('Overdue', $overdueTasks, 'alarm-clock', 'rose', 'Handle these first'); ?>
    <?php ui_stat_card('Docs in Workflow', $workflowDocs, 'git-branch', 'indigo', 'Submitted or under review'); ?>
    <?php ui_stat_card('Returned for Revision', $returnedDocs, 'refresh-ccw', 'amber', 'Waiting for updates'); ?>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4 mb-8">
    <?php ui_quick_link('/harvest/records/tasks/index.php', 'Pending Tasks', 'Open the full task queue with cleaner tables.', 'clipboard-check'); ?>
    <?php ui_quick_link('/harvest/workflow/timeline.php', 'Workflow Timeline', 'Trace movement and status updates in one place.', 'git-branch'); ?>
    <?php ui_quick_link('/harvest/department/index.php', 'Department Documents', 'Review files shared to your department.', 'building-2'); ?>
    <?php ui_quick_link('/harvest/modules/agri/reports/index.php', 'Reports', 'See workflow and category reporting.', 'bar-chart-3'); ?>
</div>

<div class="rounded-3xl bg-white dark:bg-slate-900 p-6 shadow-sm ring-1 ring-slate-200 dark:ring-slate-800">
    <div class="flex items-center justify-between gap-3 mb-5">
        <div>
            <h2 class="text-xl font-semibold text-slate-900 dark:text-white">Current queue</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Everything assigned to you that still needs action.</p>
        </div>
        <a href="/harvest/records/tasks/index.php" class="text-sm font-semibold text-blue-600 dark:text-blue-300">Open tasks</a>
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
                            <td><?= htmlspecialchars($row['task_title'] ?: 'Workflow Task') ?></td>
                            <td><?= htmlspecialchars($row['document_number'] ?? '-') ?></td>
                            <td class="font-medium text-slate-900 dark:text-white"><?= htmlspecialchars($row['title']) ?></td>
                            <td><?= ui_status_badge($row['status']) ?></td>
                            <td><?= htmlspecialchars($row['due_at'] ?: '-') ?></td>
                            <td><a href="/harvest/records/tasks/view.php?id=<?= (int)$row['id'] ?>">Review</a></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <?php ui_empty_state('Queue is clear', 'There are no pending records tasks assigned to you right now.', 'check-check'); ?>
    <?php endif; ?>
</div>

<?= app_dashboard_insights_panel($conn, 'Records dashboard snapshot', 'Live charts for records, pending work, rules, and the current municipal situation.') ?>
<?php
page_card_end();
app_require('app/includes/footer.php');
?>
