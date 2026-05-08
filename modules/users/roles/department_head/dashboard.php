<?php
require_once dirname(__DIR__, 4) . '/app/bootstrap.php';

$conn = db_conn();require_once app_path('app/config/database.php');
app_require('app/includes/auth.php');
app_require('app/includes/ui.php');

require_role(['department_head']);
$user_id = (int)$_SESSION['user_id'];

$pendingTasks = (int)$conn->query("SELECT COUNT(*) AS total FROM document_workflow_tasks WHERE assigned_user_id = {$user_id} AND status = 'pending'")->fetch_assoc()['total'];
$dueSoon = (int)$conn->query("SELECT COUNT(*) AS total FROM document_workflow_tasks WHERE assigned_user_id = {$user_id} AND status = 'pending' AND due_at IS NOT NULL AND due_at <= DATE_ADD(NOW(), INTERVAL 3 DAY)")->fetch_assoc()['total'];
$notifCount = (int)$conn->query("SELECT COUNT(*) AS total FROM notifications WHERE user_id = {$user_id} AND is_read = 0")->fetch_assoc()['total'];

$deptStmt = $conn->prepare("\n    SELECT COUNT(DISTINCT d.id) AS total\n    FROM documents d\n    LEFT JOIN document_shares ds ON ds.document_id = d.id AND ds.revoked_at IS NULL\n    LEFT JOIN user_department_assignments uda ON uda.user_id = ? AND uda.is_active = 1 AND uda.ended_at IS NULL\n    WHERE ds.target_department_id = uda.department_id\n");
$deptStmt->bind_param('i', $user_id);
$deptStmt->execute();
$departmentDocs = (int)($deptStmt->get_result()->fetch_assoc()['total'] ?? 0);
$deptStmt->close();

$tasks = $conn->prepare("\n    SELECT t.id, t.task_title, t.status, t.due_at, d.document_number, d.title\n    FROM document_workflow_tasks t\n    JOIN documents d ON d.id = t.document_id\n    WHERE t.assigned_user_id = ? AND t.status = 'pending'\n    ORDER BY t.created_at ASC\n    LIMIT 8\n");
$tasks->bind_param('i', $user_id);
$tasks->execute();
$taskRows = $tasks->get_result();
$tasks->close();

app_require('app/includes/header.php');
page_card_start('Department Head Dashboard', 'Handle departmental review steps with cleaner priorities, due-date visibility, and faster approval access.');
?>

<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-8">
    <?php ui_stat_card('Department Tasks', $pendingTasks, 'briefcase', 'blue', 'Pending items assigned to you'); ?>
    <?php ui_stat_card('Due Soon', $dueSoon, 'clock-3', 'amber', 'Needs action within 3 days'); ?>
    <?php ui_stat_card('Department Documents', $departmentDocs, 'building-2', 'indigo', 'Shared to your department'); ?>
    <?php ui_stat_card('Unread Notifications', $notifCount, 'bell-ring', 'emerald', 'Recent updates'); ?>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4 mb-8">
    <?php ui_quick_link('/harvest/department_head/tasks/index.php', 'Department Tasks', 'Open pending reviews that need your decision.', 'clipboard-list'); ?>
    <?php ui_quick_link('/harvest/workflow/timeline.php', 'Workflow Timeline', 'Follow movement and approvals of documents.', 'git-branch'); ?>
    <?php ui_quick_link('/harvest/department/index.php', 'Department Documents', 'Browse files shared with your department.', 'folder-tree'); ?>
    <?php ui_quick_link('/harvest/modules/users/account/profile/index.php', 'My Profile', 'Update your details and profile photo.', 'user-circle-2'); ?>
</div>

<div class="rounded-3xl bg-white dark:bg-slate-900 p-6 shadow-sm ring-1 ring-slate-200 dark:ring-slate-800">
    <div class="flex items-center justify-between gap-3 mb-5">
        <div>
            <h2 class="text-xl font-semibold text-slate-900 dark:text-white">Review queue</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Pending department reviews waiting for action.</p>
        </div>
        <a href="/harvest/department_head/tasks/index.php" class="text-sm font-semibold text-blue-600 dark:text-blue-300">Open tasks</a>
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
                            <td><?= htmlspecialchars($row['task_title'] ?: 'Department Review') ?></td>
                            <td><?= htmlspecialchars($row['document_number'] ?? '-') ?></td>
                            <td class="font-medium text-slate-900 dark:text-white"><?= htmlspecialchars($row['title']) ?></td>
                            <td><?= ui_status_badge($row['status']) ?></td>
                            <td><?= htmlspecialchars($row['due_at'] ?: '-') ?></td>
                            <td><a href="/harvest/department_head/tasks/view.php?id=<?= (int)$row['id'] ?>">Review</a></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <?php ui_empty_state('No department tasks yet', 'You do not have any pending department review tasks right now.', 'folder-open'); ?>
    <?php endif; ?>
</div>

<?= app_dashboard_insights_panel($conn, 'Department dashboard snapshot', 'Live charts for records, queues, rule pressure, and the current municipal situation.') ?>
<?php
page_card_end();
app_require('app/includes/footer.php');
?>
