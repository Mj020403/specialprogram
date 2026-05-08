<?php
require_once dirname(__DIR__, 3) . '/app/bootstrap.php';

$conn = db_conn();require_once app_path('app/config/database.php');
app_require('app/includes/auth.php');
app_require('app/includes/flash.php');

require_role(['staff', 'records_officer', 'approver', 'super_admin', 'system_admin', 'admin']);
$flash = get_flash();

$user_id = $_SESSION['user_id'];

$status = trim($_GET['status'] ?? '');
$date_from = trim($_GET['date_from'] ?? '');
$date_to = trim($_GET['date_to'] ?? '');

$sql = "
    SELECT d.*, dc.name AS category_name
    FROM documents d
    LEFT JOIN document_categories dc ON dc.id = d.category_id
    WHERE d.creator_user_id = ?
      AND d.deleted_at IS NULL
";

$params = [$user_id];
$types = "i";

if ($status !== '') {
    $sql .= " AND d.status = ? ";
    $params[] = $status;
    $types .= "s";
}

if ($date_from !== '') {
    $sql .= " AND DATE(d.created_at) >= ? ";
    $params[] = $date_from;
    $types .= "s";
}

if ($date_to !== '') {
    $sql .= " AND DATE(d.created_at) <= ? ";
    $params[] = $date_to;
    $types .= "s";
}

$sql .= " ORDER BY d.id DESC ";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

app_require('app/includes/header.php');
?>

<div class="container">
    <h1>My Documents</h1>
    <p><a href="/harvest/staff/dashboard.php">← Back to Dashboard</a></p>

    <?php if ($flash): ?>
        <div style="margin-bottom:15px;padding:12px;border-radius:8px;background:<?= $flash['type']==='success' ? '#dcfce7' : '#fee2e2' ?>;color:<?= $flash['type']==='success' ? '#166534' : '#991b1b' ?>;">
            <?= htmlspecialchars($flash['message']) ?>
        </div>
    <?php endif; ?>

    <p>
        <a href="/harvest/staff/documents/create.php" style="display:inline-block;padding:10px 14px;background:#1d4ed8;color:#fff;text-decoration:none;border-radius:8px;">+ Create Document</a>
    </p>

    <form method="GET" style="margin-bottom:20px;">
        <label>Status</label>
        <select name="status" style="width:100%;padding:10px;margin-bottom:12px;">
            <option value="">All Statuses</option>
            <?php foreach (['draft','submitted','in_review','approved','for_revision','rejected','archived','cancelled'] as $s): ?>
                <option value="<?= $s ?>" <?= ($status === $s) ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
            <?php endforeach; ?>
        </select>

        <label>Date From</label>
        <input type="date" name="date_from" value="<?= htmlspecialchars($date_from) ?>" style="width:100%;padding:10px;margin-bottom:12px;">

        <label>Date To</label>
        <input type="date" name="date_to" value="<?= htmlspecialchars($date_to) ?>" style="width:100%;padding:10px;margin-bottom:12px;">

        <button type="submit" style="padding:10px 16px;background:#1d4ed8;color:#fff;border:none;border-radius:8px;">Filter</button>
    </form>

    <table border="1" cellpadding="10" cellspacing="0" width="100%" style="border-collapse:collapse;">
        <thead style="background:#eff6ff;">
            <tr>
                <th>ID</th>
                <th>Document No.</th>
                <th>Title</th>
                <th>Category</th>
                <th>Status</th>
                <th>Version</th>
                <th>Created</th>
                <th width="120">Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($result->num_rows > 0): ?>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?= (int)$row['id'] ?></td>
                        <td><?= htmlspecialchars($row['document_number'] ?? '') ?></td>
                        <td><?= htmlspecialchars($row['title'] ?? '') ?></td>
                        <td><?= htmlspecialchars($row['category_name'] ?? '') ?></td>
                        <td><?= htmlspecialchars($row['status'] ?? '') ?></td>
                        <td><?= (int)($row['current_version_no'] ?? 0) ?></td>
                        <td><?= htmlspecialchars($row['created_at'] ?? '') ?></td>
                        <td><a href="/harvest/staff/documents/view.php?id=<?= (int)$row['id'] ?>">Open</a></td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="8" style="text-align:center;">No documents found.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php app_require('app/includes/footer.php'); ?>