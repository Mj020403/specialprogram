<?php
require_once dirname(__DIR__, 3) . '/app/bootstrap.php';

$conn = db_conn();require_once app_path('app/config/database.php');
app_require('app/includes/auth.php');
app_require('app/includes/ui.php');

require_login();

$user_id = (int)$_SESSION['user_id'];

$sql = "
    SELECT DISTINCT
        d.id,
        d.document_number,
        d.title,
        d.status,
        d.current_version_no,
        d.created_at,
        dc.name AS category_name
    FROM documents d
    LEFT JOIN document_categories dc ON dc.id = d.category_id
    LEFT JOIN document_shares ds ON ds.document_id = d.id AND ds.revoked_at IS NULL
    LEFT JOIN user_department_assignments uda 
        ON uda.user_id = ?
       AND uda.is_active = 1
       AND uda.ended_at IS NULL
    WHERE
        ds.target_user_id = ?
        OR ds.target_department_id = uda.department_id
    ORDER BY d.id DESC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $user_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

app_require('app/includes/header.php');

page_card_start('Department Documents', 'Browse files shared with your department using the upgraded table layout.');
?>

<div class="rounded-3xl bg-white dark:bg-slate-900 p-6 shadow-sm ring-1 ring-slate-200 dark:ring-slate-800">
    <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
        <div>
            <h2 class="text-xl font-semibold text-slate-900 dark:text-white">Available documents</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Documents shared directly or through department access.</p>
        </div>
        <a href="/harvest/search/index.php" class="text-sm font-semibold text-blue-600 dark:text-blue-300">Search documents</a>
    </div>

    <?php if ($result->num_rows > 0): ?>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Document No.</th>
                        <th>Title</th>
                        <th>Category</th>
                        <th>Status</th>
                        <th>Version</th>
                        <th>Date</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?= (int)$row['id'] ?></td>
                            <td><?= htmlspecialchars($row['document_number'] ?? '-') ?></td>
                            <td class="font-medium text-slate-900 dark:text-white"><?= htmlspecialchars($row['title']) ?></td>
                            <td><?= htmlspecialchars($row['category_name'] ?? '-') ?></td>
                            <td><?= ui_status_badge($row['status']) ?></td>
                            <td>v<?= (int)$row['current_version_no'] ?></td>
                            <td><?= htmlspecialchars($row['created_at']) ?></td>
                            <td><a href="/harvest/staff/documents/view.php?id=<?= (int)$row['id'] ?>">Open</a></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <?php ui_empty_state('No shared documents found', 'Once documents are shared with you or your department, they will show here.', 'search'); ?>
    <?php endif; ?>
</div>

<?php
page_card_end();
app_require('app/includes/footer.php');
?>