<?php
require_once dirname(__DIR__, 4) . '/app/bootstrap.php';

$conn = db_conn();require_once app_path('app/config/database.php');
app_require('app/includes/auth.php');

require_role(['approver', 'super_admin', 'system_admin', 'admin']);

$user_id = $_SESSION['user_id'];

$stmt = $conn->prepare("
    SELECT
        t.*,
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
?>

<div class="container">
    <h1>Approver Tasks</h1>
    <p><a href="/harvest/approver/dashboard.php">← Back to Dashboard</a></p>

    <table border="1" cellpadding="10" cellspacing="0" width="100%" style="border-collapse:collapse;">
        <thead style="background:#eff6ff;">
            <tr>
                <th>Task ID</th>
                <th>Document No.</th>
                <th>Title</th>
                <th>Status</th>
                <th>Due</th>
                <th width="100">Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($result->num_rows > 0): ?>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?= (int)$row['id'] ?></td>
                        <td><?= htmlspecialchars($row['document_number'] ?? '') ?></td>
                        <td><?= htmlspecialchars($row['title']) ?></td>
                        <td><?= htmlspecialchars($row['status']) ?></td>
                        <td><?= htmlspecialchars($row['due_at'] ?? '') ?></td>
                        <td><a href="/harvest/approver/tasks/view.php?id=<?= (int)$row['id'] ?>">Open</a></td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="6" style="text-align:center;">No approval tasks.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php app_require('app/includes/footer.php'); ?>