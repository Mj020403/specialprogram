<?php
require_once dirname(__DIR__, 3) . '/app/bootstrap.php';

$conn = db_conn();require_once app_path('app/config/database.php');
app_require('app/includes/auth.php');
app_require('app/includes/ui.php');

require_login();

$q = trim($_GET['q'] ?? '');
$status = trim($_GET['status'] ?? '');
$category_id = (int)($_GET['category_id'] ?? 0);
$date_from = trim($_GET['date_from'] ?? '');
$date_to = trim($_GET['date_to'] ?? '');
$department_id = (int)($_GET['department_id'] ?? 0);
$confidentiality = trim($_GET['confidentiality'] ?? '');
$priority = trim($_GET['priority'] ?? '');

$categories = $conn->query("SELECT id, name FROM document_categories WHERE is_active = 1 ORDER BY name ASC");
$departments = $conn->query("SELECT id, name FROM departments WHERE is_active = 1 ORDER BY name ASC");

$sql = "
    SELECT
        d.id,
        d.document_number,
        d.public_tracking_code,
        d.title,
        d.status,
        d.confidentiality,
        d.priority_level,
        d.current_version_no,
        d.created_at,
        dc.name AS category_name,
        dep.name AS department_name
    FROM documents d
    LEFT JOIN document_categories dc ON dc.id = d.category_id
    LEFT JOIN departments dep ON dep.id = d.originating_department_id
    WHERE d.deleted_at IS NULL
";

$params = [];
$types = "";

if ($q !== '') {
    $sql .= " AND (d.document_number LIKE ? OR d.public_tracking_code LIKE ? OR d.title LIKE ? OR d.description LIKE ?)";
    $like = "%" . $q . "%";
    array_push($params, $like, $like, $like, $like);
    $types .= "ssss";
}

if ($status !== '') {
    $sql .= " AND d.status = ?";
    $params[] = $status;
    $types .= "s";
}
if ($category_id > 0) {
    $sql .= " AND d.category_id = ?";
    $params[] = $category_id;
    $types .= "i";
}
if ($department_id > 0) {
    $sql .= " AND d.originating_department_id = ?";
    $params[] = $department_id;
    $types .= "i";
}
if ($confidentiality !== '') {
    $sql .= " AND d.confidentiality = ?";
    $params[] = $confidentiality;
    $types .= "s";
}
if ($priority !== '') {
    $sql .= " AND COALESCE(d.priority_level, 'normal') = ?";
    $params[] = $priority;
    $types .= "s";
}
if ($date_from !== '') {
    $sql .= " AND DATE(d.created_at) >= ?";
    $params[] = $date_from;
    $types .= "s";
}
if ($date_to !== '') {
    $sql .= " AND DATE(d.created_at) <= ?";
    $params[] = $date_to;
    $types .= "s";
}

$sql .= " ORDER BY d.id DESC";
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$totalMatches = $result->num_rows;
$stmt->close();

app_require('app/includes/header.php');
page_card_start('Advanced Document Search', 'Filter by keyword, department, confidentiality, priority, and date to find records faster.');
?>

<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
    <?php ui_stat_card('Matches', $totalMatches, 'search', 'blue', 'Results for current filters'); ?>
    <?php ui_stat_card('Approved', (int)$conn->query("SELECT COUNT(*) AS total FROM documents WHERE deleted_at IS NULL AND status = 'approved'")->fetch_assoc()['total'], 'badge-check', 'emerald', 'Across all documents'); ?>
    <?php ui_stat_card('Pending', (int)$conn->query("SELECT COUNT(*) AS total FROM documents WHERE deleted_at IS NULL AND status IN ('submitted','in_review')")->fetch_assoc()['total'], 'clock-3', 'amber', 'Under review'); ?>
    <?php ui_stat_card('Confidential', (int)$conn->query("SELECT COUNT(*) AS total FROM documents WHERE deleted_at IS NULL AND confidentiality = 'confidential'")->fetch_assoc()['total'], 'shield', 'rose', 'Protected records'); ?>
</div>

<form method="GET" class="rounded-3xl bg-slate-50 dark:bg-slate-900 p-5 ring-1 ring-slate-200 dark:ring-slate-800 mb-6">
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-4">
        <div class="xl:col-span-3">
            <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Keyword or Tracking Code</label>
            <div class="search-inline">
                <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Document number, title, description, or public tracking code" class="search-inline-input">
                <button type="submit" class="search-inline-button" aria-label="Search"><i data-lucide="search" class="w-4 h-4"></i></button>
            </div>
        </div>
        <div>
            <label class="block text-sm font-semibold mb-2">Status</label>
            <select name="status" class="<?= ui_select_class() ?>">
                <option value="">All Statuses</option>
                <?php foreach (['draft','submitted','in_review','approved','for_revision','rejected','archived','cancelled'] as $s): ?>
                    <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= ucwords(str_replace('_', ' ', $s)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-sm font-semibold mb-2">Category</label>
            <select name="category_id" class="<?= ui_select_class() ?>">
                <option value="">All Categories</option>
                <?php while ($cat = $categories->fetch_assoc()): ?>
                    <option value="<?= (int)$cat['id'] ?>" <?= $category_id === (int)$cat['id'] ? 'selected' : '' ?>><?= htmlspecialchars($cat['name']) ?></option>
                <?php endwhile; ?>
            </select>
        </div>
        <div>
            <label class="block text-sm font-semibold mb-2">Department</label>
            <select name="department_id" class="<?= ui_select_class() ?>">
                <option value="">All Departments</option>
                <?php while ($dep = $departments->fetch_assoc()): ?>
                    <option value="<?= (int)$dep['id'] ?>" <?= $department_id === (int)$dep['id'] ? 'selected' : '' ?>><?= htmlspecialchars($dep['name']) ?></option>
                <?php endwhile; ?>
            </select>
        </div>
        <div>
            <label class="block text-sm font-semibold mb-2">Confidentiality</label>
            <select name="confidentiality" class="<?= ui_select_class() ?>">
                <option value="">All Levels</option>
                <?php foreach (['public','internal','confidential','restricted'] as $item): ?>
                    <option value="<?= $item ?>" <?= $confidentiality === $item ? 'selected' : '' ?>><?= ucfirst($item) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-sm font-semibold mb-2">Priority</label>
            <select name="priority" class="<?= ui_select_class() ?>">
                <option value="">All Priorities</option>
                <?php foreach (['low','normal','high','urgent'] as $item): ?>
                    <option value="<?= $item ?>" <?= $priority === $item ? 'selected' : '' ?>><?= ucfirst($item) ?></option>
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
        <div class="flex flex-wrap gap-3 items-end">
            <button type="submit" class="inline-flex items-center gap-2 rounded-2xl bg-blue-600 px-5 py-3 font-semibold text-white shadow-sm hover:bg-blue-700 transition"><i data-lucide="filter" class="w-4 h-4"></i>Apply Filters</button>
            <a href="/harvest/search/index.php" class="inline-flex items-center gap-2 rounded-2xl bg-white dark:bg-slate-950 px-5 py-3 font-semibold text-slate-700 dark:text-slate-200 ring-1 ring-slate-300 dark:ring-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800 transition"><i data-lucide="rotate-ccw" class="w-4 h-4"></i>Reset</a>
        </div>
    </div>
</form>

<div class="overflow-hidden rounded-3xl bg-white dark:bg-slate-900 shadow-sm ring-1 ring-slate-200 dark:ring-slate-800">
    <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 dark:bg-slate-800">
                <tr class="text-left text-slate-600 dark:text-slate-300">
                    <th class="px-4 py-3 font-semibold">Document No.</th>
                    <th class="px-4 py-3 font-semibold">Tracking</th>
                    <th class="px-4 py-3 font-semibold">Title</th>
                    <th class="px-4 py-3 font-semibold">Department</th>
                    <th class="px-4 py-3 font-semibold">Category</th>
                    <th class="px-4 py-3 font-semibold">Priority</th>
                    <th class="px-4 py-3 font-semibold">Status</th>
                    <th class="px-4 py-3 font-semibold">Confidentiality</th>
                    <th class="px-4 py-3 font-semibold">Created</th>
                    <th class="px-4 py-3 font-semibold">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                <?php if ($result->num_rows > 0): while ($row = $result->fetch_assoc()): ?>
                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-800 transition">
                        <td class="px-4 py-3 font-medium"><?= htmlspecialchars($row['document_number'] ?: '-') ?></td>
                        <td class="px-4 py-3"><?= htmlspecialchars($row['public_tracking_code'] ?: '-') ?></td>
                        <td class="px-4 py-3"><?= htmlspecialchars($row['title']) ?></td>
                        <td class="px-4 py-3"><?= htmlspecialchars($row['department_name'] ?: '-') ?></td>
                        <td class="px-4 py-3"><?= htmlspecialchars($row['category_name'] ?: '-') ?></td>
                        <td class="px-4 py-3"><?= ui_status_badge((string)($row['priority_level'] ?: 'normal')) ?></td>
                        <td class="px-4 py-3"><?= ui_status_badge((string)$row['status']) ?></td>
                        <td class="px-4 py-3"><?= ui_status_badge((string)$row['confidentiality']) ?></td>
                        <td class="px-4 py-3"><?= htmlspecialchars($row['created_at']) ?></td>
                        <td class="px-4 py-3"><a href="/harvest/staff/documents/view.php?id=<?= (int)$row['id'] ?>" class="inline-flex items-center gap-1 text-blue-600 hover:text-blue-800 font-medium"><i data-lucide="arrow-up-right" class="w-4 h-4"></i>Open</a></td>
                    </tr>
                <?php endwhile; else: ?>
                    <tr><td colspan="10" class="px-4 py-10 text-center text-slate-500 dark:text-slate-400">No matching documents found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php page_card_end(); app_require('app/includes/footer.php'); ?>
