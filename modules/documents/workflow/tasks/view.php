<?php
require_once dirname(__DIR__, 4) . '/app/bootstrap.php';

$conn = db_conn();require_once app_path('app/config/database.php');
app_require('app/includes/auth.php');
app_require('app/includes/flash.php');

require_role(['approver', 'super_admin', 'system_admin', 'admin']);
$flash = get_flash();

$id = (int)($_GET['id'] ?? 0);

$stmt = $conn->prepare("
    SELECT
        t.*,
        d.title,
        d.document_number,
        d.description,
        d.status AS document_status,
        d.creator_user_id,
        d.id AS document_id
    FROM document_workflow_tasks t
    JOIN documents d ON d.id = t.document_id
    WHERE t.id = ?
    LIMIT 1
");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$task = $result->fetch_assoc();
$stmt->close();

if (!$task) {
    die("Task not found.");
}

$versions_stmt = $conn->prepare("
    SELECT *
    FROM document_versions
    WHERE document_id = ?
    ORDER BY version_no DESC
");
$versions_stmt->bind_param("i", $task['document_id']);
$versions_stmt->execute();
$versions = $versions_stmt->get_result();
$versions_stmt->close();

app_require('app/includes/header.php');
?>

<div class="container">
    <h1>Approve Document</h1>
    <p><a href="/harvest/approver/tasks/index.php">← Back to Approval Tasks</a></p>

    <?php if ($flash): ?>
        <div style="margin-bottom:15px;padding:12px;border-radius:8px;background:<?= $flash['type']==='success' ? '#dcfce7' : '#fee2e2' ?>;color:<?= $flash['type']==='success' ? '#166534' : '#991b1b' ?>;">
            <?= htmlspecialchars($flash['message']) ?>
        </div>
    <?php endif; ?>

    <p><strong>Document No:</strong> <?= htmlspecialchars($task['document_number'] ?? '') ?></p>
    <p><strong>Title:</strong> <?= htmlspecialchars($task['title']) ?></p>
    <p><strong>Status:</strong> <?= htmlspecialchars($task['document_status']) ?></p>
    <p><strong>Description:</strong><br><?= nl2br(htmlspecialchars($task['description'] ?? '')) ?></p>

    <h3>Versions</h3>
    <ul>
        <?php while ($v = $versions->fetch_assoc()): ?>
            <li>
                Version <?= (int)$v['version_no'] ?> -
                <?= htmlspecialchars($v['original_file_name']) ?> -
                <a href="/harvest/<?= htmlspecialchars($v['file_path']) ?>" target="_blank">Open File</a>
            </li>
        <?php endwhile; ?>
    </ul>

    <hr>

    <form action="/harvest/workflow/approve.php" method="POST" style="margin-bottom:20px;">
        <input type="hidden" name="task_id" value="<?= (int)$task['id'] ?>">
        <textarea name="remarks" rows="4" placeholder="Optional remarks" style="width:100%;padding:10px;margin-bottom:12px;"></textarea>
        <button type="submit" style="padding:12px 18px;background:#16a34a;color:#fff;border:none;border-radius:8px;">Final Approve</button>
    </form>

    <form action="/harvest/workflow/return.php" method="POST">
        <input type="hidden" name="task_id" value="<?= (int)$task['id'] ?>">
        <textarea name="remarks" rows="4" required placeholder="Reason for revision" style="width:100%;padding:10px;margin-bottom:12px;"></textarea>
        <button type="submit" style="padding:12px 18px;background:#dc2626;color:#fff;border:none;border-radius:8px;">Return for Revision</button>
    </form>
</div>

<?php app_require('app/includes/footer.php'); ?>