<?php
require_once dirname(__DIR__, 5) . '/app/bootstrap.php';

$conn = db_conn();require_once app_path('app/config/database.php');
app_require('app/includes/auth.php');
app_require('app/includes/flash.php');
app_require('app/includes/ui.php');

require_role(['staff', 'records_officer', 'approver', 'super_admin', 'system_admin', 'admin']);
$flash = get_flash();
$user_id = (int)$_SESSION['user_id'];
$status = trim($_GET['status'] ?? '');
$date_from = trim($_GET['date_from'] ?? '');
$date_to = trim($_GET['date_to'] ?? '');

$sql = "SELECT d.*, dc.name AS category_name FROM documents d LEFT JOIN document_categories dc ON dc.id = d.category_id WHERE d.creator_user_id = ? AND d.deleted_at IS NULL";
$params = [$user_id];
$types = 'i';
if ($status !== '') { $sql .= ' AND d.status = ?'; $params[] = $status; $types .= 's'; }
if ($date_from !== '') { $sql .= ' AND DATE(d.created_at) >= ?'; $params[] = $date_from; $types .= 's'; }
if ($date_to !== '') { $sql .= ' AND DATE(d.created_at) <= ?'; $params[] = $date_to; $types .= 's'; }
$sql .= ' ORDER BY d.id DESC';
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

app_require('app/includes/header.php');
page_card_start('My Documents', 'Manage drafts, upload versions, and track document progress in one place.');
flash_message($flash);
?>

<div class="flex flex-wrap gap-3 mb-6">
    <?php action_button('/harvest/staff/documents/create.php', 'Create Document', 'file-plus'); ?>
    <?php action_button('/harvest/staff/dashboard.php', 'Back to Dashboard', 'arrow-left', 'secondary'); ?>
</div>

<div class="grid gap-6 xl:grid-cols-[0.95fr_2.05fr]">
    <div class="rounded-3xl bg-white dark:bg-slate-900 p-6 shadow-sm ring-1 ring-slate-200 dark:ring-slate-800 h-fit">
        <h2 class="text-xl font-semibold mb-5">Filter Documents</h2>
        <form method="GET" class="space-y-4">
            <div>
                <label class="block text-sm font-semibold mb-2">Status</label>
                <select name="status" class="<?= ui_select_class() ?>">
                    <option value="">All Statuses</option>
                    <?php foreach (['draft','submitted','in_review','approved','for_revision','rejected','archived','cancelled'] as $s): ?>
                        <option value="<?= $s ?>" <?= ($status === $s) ? 'selected' : '' ?>><?= ucwords(str_replace('_', ' ', $s)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-semibold mb-2">Date From</label>
                <input type="date" name="date_from" value="<?= htmlspecialchars($date_from) ?>" class="<?= ui_input_class() ?>">
            </div>
            <div>
                <label class="block text-sm font-semibold mb-2">Date To</label>
                <input type="date" name="date_to" value="<?= htmlspecialchars($date_to) ?>" class="<?= ui_input_class() ?>">
            </div>
            <div class="flex flex-wrap gap-3">
                <?php ui_primary_button('Apply Filters', 'filter'); ?>
                <?php action_button('/harvest/staff/documents/index.php', 'Reset', 'rotate-ccw', 'secondary'); ?>
            </div>
        </form>
    </div>

    <div class="rounded-3xl bg-white dark:bg-slate-900 p-6 shadow-sm ring-1 ring-slate-200 dark:ring-slate-800">
        <?php if ($result->num_rows > 0): ?>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Document</th>
                            <th>Status</th>
                            <th>Version</th>
                            <th>Created</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?= (int)$row['id'] ?></td>
                                <td>
                                    <div class="min-w-[240px]">
                                        <div class="font-semibold text-slate-900 dark:text-white"><?= htmlspecialchars($row['title'] ?? '') ?></div>
                                        <div class="text-xs text-slate-500 dark:text-slate-400 mt-1"><?= htmlspecialchars($row['category_name'] ?? 'Uncategorized') ?></div>
                                        <div class="text-xs text-slate-400 dark:text-slate-500 mt-1"><?= htmlspecialchars($row['document_number'] ?? 'No document number yet') ?></div>
                                    </div>
                                </td>
                                <td><?= ui_status_badge($row['status'] ?? 'draft') ?></td>
                                <td><?= (int)($row['current_version_no'] ?? 0) ?></td>
                                <td><?= htmlspecialchars($row['created_at'] ?? '') ?></td>
                                <td><a class="text-blue-600 hover:text-blue-800 font-medium" href="/harvest/staff/documents/view.php?id=<?= (int)$row['id'] ?>">Open</a></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <?php ui_empty_state('No documents found', 'Create a document draft or upload the first file to start your workflow.', 'files'); ?>
        <?php endif; ?>
    </div>
</div>

<?php page_card_end(); app_require('app/includes/footer.php'); ?>
