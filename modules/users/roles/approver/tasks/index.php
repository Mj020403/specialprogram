<?php
require_once dirname(__DIR__, 5) . '/app/bootstrap.php';

$conn = db_conn();require_once app_path('app/config/database.php');
app_require('app/includes/auth.php');
app_require('app/includes/flash.php');
app_require('app/includes/ui.php');

require_role(['approver', 'super_admin', 'system_admin', 'admin']);
$flash = get_flash();
$user_id = (int)$_SESSION['user_id'];

$stmt = $conn->prepare("
    SELECT
        t.id,
        t.task_title,
        t.status,
        t.due_at,
        t.created_at,
        d.document_number,
        d.title,
        d.status AS document_status
    FROM document_workflow_tasks t
    JOIN documents d ON d.id = t.document_id
    WHERE t.assigned_user_id = ?
      AND t.status = 'pending'
    ORDER BY t.created_at ASC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

app_require('app/includes/header.php');

page_card_start('Approval Tasks', 'Review the documents currently waiting for your decision.');
flash_message($flash);
?>

<div class="flex flex-wrap gap-3 mb-6">
    <?php action_button('/harvest/approver/dashboard.php', 'Back to Dashboard', 'arrow-left', 'secondary'); ?>
</div>

<div class="rounded-3xl bg-white dark:bg-slate-900 p-6 shadow-sm ring-1 ring-slate-200 dark:ring-slate-800">
    <?php if ($result->num_rows > 0): ?>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr>
                        <th>Task ID</th>
                        <th>Task</th>
                        <th>Document No.</th>
                        <th>Title</th>
                        <th>Task Status</th>
                        <th>Document Status</th>
                        <th>Due</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?= (int)$row['id'] ?></td>
                            <td><?= htmlspecialchars($row['task_title'] ?: 'Workflow Task') ?></td>
                            <td><?= htmlspecialchars($row['document_number'] ?? '-') ?></td>
                            <td class="font-medium text-slate-900 dark:text-white"><?= htmlspecialchars($row['title']) ?></td>
                            <td><?= ui_status_badge($row['status']) ?></td>
                            <td><?= ui_status_badge($row['document_status']) ?></td>
                            <td><?= htmlspecialchars($row['due_at'] ?: '-') ?></td>
                            <td><a href="/harvest/approver/tasks/view.php?id=<?= (int)$row['id'] ?>">Open</a></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <?php ui_empty_state('No tasks found', 'Your current queue is empty. New workflow assignments will show here.', 'clipboard-check'); ?>
    <?php endif; ?>
</div>

<?php
page_card_end();
app_require('app/includes/footer.php');
?>