<?php
require_once dirname(__DIR__, 3) . '/app/bootstrap.php';

$conn = db_conn();require_once app_path('app/config/database.php');
app_require('app/includes/auth.php');
app_require('app/includes/ui.php');

require_login();

$document_id = (int)($_GET['document_id'] ?? 0);
$q = trim($_GET['q'] ?? '');
$status = trim($_GET['status'] ?? '');
$category_id = (int)($_GET['category_id'] ?? 0);

$categories = $conn->query("SELECT id, name FROM document_categories WHERE is_active = 1 ORDER BY name ASC");

$sql = "
    SELECT d.id, d.document_number, d.public_tracking_code, d.title, d.status, d.current_version_no, d.created_at,
           dc.name AS category_name, dep.name AS department_name,
           (SELECT COUNT(*) FROM activity_logs al WHERE al.entity_type = 'documents' AND al.entity_id = d.id) AS activity_count,
           (SELECT COUNT(*) FROM document_access_logs dal WHERE dal.document_id = d.id) AS access_count
    FROM documents d
    LEFT JOIN document_categories dc ON dc.id = d.category_id
    LEFT JOIN departments dep ON dep.id = d.originating_department_id
    WHERE d.deleted_at IS NULL
";
$params = [];
$types = '';
if ($document_id > 0) {
    $sql .= ' AND d.id = ?';
    $params[] = $document_id;
    $types .= 'i';
}
if ($q !== '') {
    $sql .= " AND (d.document_number LIKE ? OR d.title LIKE ? OR d.description LIKE ? OR d.public_tracking_code LIKE ?)";
    $searchLike = '%' . $q . '%';
    array_push($params, $searchLike, $searchLike, $searchLike, $searchLike);
    $types .= 'ssss';
}
if ($status !== '') {
    $sql .= ' AND d.status = ?';
    $params[] = $status;
    $types .= 's';
}
if ($category_id > 0) {
    $sql .= ' AND d.category_id = ?';
    $params[] = $category_id;
    $types .= 'i';
}
$sql .= ' ORDER BY d.id DESC LIMIT 20';
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

$selectedDoc = null;
$taskTimeline = null;
$activityTimeline = null;
$accessTimeline = null;
if ($document_id > 0) {
    $docStmt = $conn->prepare('SELECT d.*, dc.name AS category_name, dep.name AS department_name FROM documents d LEFT JOIN document_categories dc ON dc.id = d.category_id LEFT JOIN departments dep ON dep.id = d.originating_department_id WHERE d.id = ? LIMIT 1');
    $docStmt->bind_param('i', $document_id);
    $docStmt->execute();
    $selectedDoc = $docStmt->get_result()->fetch_assoc();
    $docStmt->close();

    $taskStmt = $conn->prepare('SELECT t.*, u.email AS assigned_email FROM document_workflow_tasks t LEFT JOIN users u ON u.id = t.assigned_user_id WHERE t.document_id = ? ORDER BY t.step_no ASC, t.created_at ASC');
    $taskStmt->bind_param('i', $document_id);
    $taskStmt->execute();
    $taskTimeline = $taskStmt->get_result();
    $taskStmt->close();

    $activityStmt = $conn->prepare("SELECT al.*, u.email FROM activity_logs al LEFT JOIN users u ON u.id = al.user_id WHERE al.entity_type = 'documents' AND al.entity_id = ? ORDER BY al.created_at DESC LIMIT 20");
    $activityStmt->bind_param('i', $document_id);
    $activityStmt->execute();
    $activityTimeline = $activityStmt->get_result();
    $activityStmt->close();

    $accessStmt = $conn->prepare('SELECT dal.*, u.email FROM document_access_logs dal LEFT JOIN users u ON u.id = dal.user_id WHERE dal.document_id = ? ORDER BY dal.created_at DESC LIMIT 20');
    $accessStmt->bind_param('i', $document_id);
    $accessStmt->execute();
    $accessTimeline = $accessStmt->get_result();
    $accessStmt->close();
}

app_require('app/includes/header.php');
page_card_start('Workflow Timeline & Audit Trail', 'Search documents, inspect workflow steps, and review access history for transparency and accountability.');
?>

<form method="GET" class="rounded-3xl bg-slate-50 dark:bg-slate-900 p-5 ring-1 ring-slate-200 dark:ring-slate-800 mb-6">
    <div class="grid grid-cols-1 xl:grid-cols-[1.1fr_0.9fr_auto] gap-4 items-end">
        <div>
            <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Search</label>
            <div class="search-inline"><input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Search document number, title, description, or tracking code" class="search-inline-input"><button type="submit" class="search-inline-button"><i data-lucide="search" class="w-4 h-4"></i></button></div>
        </div>
        <div class="grid sm:grid-cols-2 gap-4">
            <div><label class="block text-sm font-semibold mb-2">Status</label><select name="status" class="<?= ui_select_class() ?>"><option value="">All Statuses</option><?php foreach (['draft','submitted','in_review','approved','for_revision','rejected','archived','cancelled'] as $s): ?><option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option><?php endforeach; ?></select></div>
            <div><label class="block text-sm font-semibold mb-2">Category</label><select name="category_id" class="<?= ui_select_class() ?>"><option value="">All Categories</option><?php while ($cat = $categories->fetch_assoc()): ?><option value="<?= (int)$cat['id'] ?>" <?= $category_id === (int)$cat['id'] ? 'selected' : '' ?>><?= htmlspecialchars($cat['name']) ?></option><?php endwhile; ?></select></div>
        </div>
        <div><a href="/harvest/workflow/timeline.php" class="inline-flex items-center gap-2 rounded-xl bg-white dark:bg-slate-950 px-5 py-3 font-semibold text-slate-700 dark:text-slate-200 ring-1 ring-slate-300 dark:ring-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800 transition"><i data-lucide="rotate-ccw" class="w-4 h-4"></i>Reset</a></div>
    </div>
</form>

<div class="overflow-hidden rounded-3xl bg-white dark:bg-slate-900 shadow-sm ring-1 ring-slate-200 dark:ring-slate-800 mb-6">
    <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead><tr class="text-left text-slate-600 dark:text-slate-300"><th class="px-4 py-3 font-semibold">Document</th><th class="px-4 py-3 font-semibold">Tracking</th><th class="px-4 py-3 font-semibold">Department</th><th class="px-4 py-3 font-semibold">Status</th><th class="px-4 py-3 font-semibold">Activities</th><th class="px-4 py-3 font-semibold">Access Logs</th><th class="px-4 py-3 font-semibold">Action</th></tr></thead>
            <tbody>
                <?php if ($result->num_rows > 0): while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td class="px-4 py-3"><div class="font-medium"><?= htmlspecialchars(($row['document_number'] ?: '-') . ' - ' . $row['title']) ?></div><div class="text-xs text-slate-400 mt-1"><?= htmlspecialchars($row['category_name'] ?: '-') ?></div></td>
                        <td class="px-4 py-3"><?= htmlspecialchars($row['public_tracking_code'] ?: '-') ?></td>
                        <td class="px-4 py-3"><?= htmlspecialchars($row['department_name'] ?: '-') ?></td>
                        <td class="px-4 py-3"><?= ui_status_badge((string)$row['status']) ?></td>
                        <td class="px-4 py-3"><?= (int)$row['activity_count'] ?></td>
                        <td class="px-4 py-3"><?= (int)$row['access_count'] ?></td>
                        <td class="px-4 py-3"><a href="/harvest/workflow/timeline.php?document_id=<?= (int)$row['id'] ?>" class="inline-flex items-center gap-1 rounded-lg px-3 py-2 text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-950/30 font-medium transition"><i data-lucide="list-tree" class="w-4 h-4"></i>Open Timeline</a></td>
                    </tr>
                <?php endwhile; else: ?>
                    <tr><td colspan="7" class="px-4 py-10 text-center text-slate-500 dark:text-slate-400">No matching documents found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($selectedDoc): ?>
<div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
    <div class="xl:col-span-1 rounded-3xl bg-white dark:bg-slate-900 p-6 shadow-sm ring-1 ring-slate-200 dark:ring-slate-800"><h3 class="text-xl font-semibold mb-4">Selected Document</h3><div class="space-y-3 text-sm"><div><span class="text-slate-500 dark:text-slate-400">Document:</span> <?= htmlspecialchars(($selectedDoc['document_number'] ?: '-') . ' - ' . $selectedDoc['title']) ?></div><div><span class="text-slate-500 dark:text-slate-400">Tracking:</span> <?= htmlspecialchars($selectedDoc['public_tracking_code'] ?: '-') ?></div><div><span class="text-slate-500 dark:text-slate-400">Department:</span> <?= htmlspecialchars($selectedDoc['department_name'] ?: '-') ?></div><div><span class="text-slate-500 dark:text-slate-400">Category:</span> <?= htmlspecialchars($selectedDoc['category_name'] ?: '-') ?></div><div><span class="text-slate-500 dark:text-slate-400">Status:</span> <?= ui_status_badge((string)$selectedDoc['status']) ?></div></div></div>
    <div class="xl:col-span-2 rounded-3xl bg-white dark:bg-slate-900 p-6 shadow-sm ring-1 ring-slate-200 dark:ring-slate-800"><h3 class="text-xl font-semibold mb-4">Workflow steps</h3><div class="space-y-4"><?php if ($taskTimeline && $taskTimeline->num_rows > 0): while ($task = $taskTimeline->fetch_assoc()): ?><div class="rounded-2xl border border-slate-200 dark:border-slate-700 p-4"><div class="flex flex-wrap items-center justify-between gap-3"><div><div class="font-semibold text-slate-900 dark:text-white">Step <?= (int)$task['step_no'] ?> - <?= htmlspecialchars($task['task_title']) ?></div><div class="mt-1 text-sm text-slate-500 dark:text-slate-400">Assigned to <?= htmlspecialchars($task['assigned_email'] ?: 'Not assigned') ?></div></div><div><?= ui_status_badge((string)$task['status']) ?></div></div><div class="mt-3 grid sm:grid-cols-3 gap-3 text-sm"><div><span class="text-slate-500 dark:text-slate-400">Created:</span> <?= htmlspecialchars($task['created_at']) ?></div><div><span class="text-slate-500 dark:text-slate-400">Due:</span> <?= htmlspecialchars($task['due_at'] ?: '-') ?></div><div><span class="text-slate-500 dark:text-slate-400">Completed:</span> <?= htmlspecialchars($task['completed_at'] ?: '-') ?></div></div></div><?php endwhile; else: ?><div class="text-sm text-slate-500 dark:text-slate-400">No workflow steps found for this document.</div><?php endif; ?></div></div>
</div>

<div class="grid grid-cols-1 xl:grid-cols-2 gap-6 mt-6">
    <div class="rounded-3xl bg-white dark:bg-slate-900 p-6 shadow-sm ring-1 ring-slate-200 dark:ring-slate-800"><h3 class="text-xl font-semibold mb-4">Activity History</h3><div class="space-y-3"><?php if ($activityTimeline && $activityTimeline->num_rows > 0): while ($row = $activityTimeline->fetch_assoc()): ?><div class="rounded-2xl border border-slate-200 dark:border-slate-700 p-4"><div class="flex items-center justify-between gap-3"><div class="font-semibold text-slate-900 dark:text-white"><?= htmlspecialchars($row['action_code']) ?></div><div class="text-xs text-slate-400"><?= htmlspecialchars($row['created_at']) ?></div></div><div class="mt-2 text-sm text-slate-600 dark:text-slate-300"><?= htmlspecialchars($row['description']) ?></div><div class="mt-2 text-xs text-slate-400">User: <?= htmlspecialchars($row['email'] ?: 'System') ?></div></div><?php endwhile; else: ?><div class="text-sm text-slate-500 dark:text-slate-400">No activity logs yet.</div><?php endif; ?></div></div>
    <div class="rounded-3xl bg-white dark:bg-slate-900 p-6 shadow-sm ring-1 ring-slate-200 dark:ring-slate-800"><h3 class="text-xl font-semibold mb-4">Access History</h3><div class="space-y-3"><?php if ($accessTimeline && $accessTimeline->num_rows > 0): while ($row = $accessTimeline->fetch_assoc()): ?><div class="rounded-2xl border border-slate-200 dark:border-slate-700 p-4"><div class="flex items-center justify-between gap-3"><div class="font-semibold text-slate-900 dark:text-white"><?= htmlspecialchars($row['action_code']) ?></div><div class="text-xs text-slate-400"><?= htmlspecialchars($row['created_at']) ?></div></div><div class="mt-2 text-xs text-slate-400">User: <?= htmlspecialchars($row['email'] ?: 'Guest/System') ?></div><div class="mt-1 text-xs text-slate-400">IP: <?= htmlspecialchars($row['ip_address'] ?: '-') ?></div></div><?php endwhile; else: ?><div class="text-sm text-slate-500 dark:text-slate-400">No access logs yet.</div><?php endif; ?></div></div>
</div>
<?php endif; ?>

<?php page_card_end(); app_require('app/includes/footer.php'); ?>
