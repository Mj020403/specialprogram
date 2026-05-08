<?php
require_once dirname(__DIR__, 3) . '/app/bootstrap.php';

$conn = db_conn();require_once app_path('app/config/database.php');
app_require('app/includes/auth.php');
app_require('app/includes/flash.php');

require_role(['developer','super_admin', 'system_admin', 'admin']);
$flash = get_flash();

$result = $conn->query("SELECT * FROM document_categories ORDER BY name ASC");

app_require('app/includes/header.php');
?>

<div class="container">
    <h1>Document Categories</h1>
    <p><a href="/harvest/modules/admin/dashboard.php">← Back to Dashboard</a></p>

    <?php if ($flash): ?>
        <div style="margin-bottom:15px;padding:12px;border-radius:8px;background:<?= $flash['type']==='success' ? '#dcfce7' : '#fee2e2' ?>;color:<?= $flash['type']==='success' ? '#166534' : '#991b1b' ?>;">
            <?= htmlspecialchars($flash['message']) ?>
        </div>
    <?php endif; ?>

    <p>
        <a href="/harvest/admin/categories/create.php" style="display:inline-block;padding:10px 14px;background:#1d4ed8;color:#fff;text-decoration:none;border-radius:8px;">+ Add Category</a>
    </p>

    <table border="1" cellpadding="10" cellspacing="0" width="100%" style="border-collapse:collapse;">
        <thead style="background:#eff6ff;">
            <tr>
                <th>ID</th>
                <th>Code</th>
                <th>Name</th>
                <th>Requires Workflow</th>
                <th>Retention Years</th>
                <th>Status</th>
                <th width="100">Action</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?= (int)$row['id'] ?></td>
                    <td><?= htmlspecialchars($row['code']) ?></td>
                    <td><?= htmlspecialchars($row['name']) ?></td>
                    <td><?= (int)$row['requires_workflow'] === 1 ? 'Yes' : 'No' ?></td>
                    <td><?= htmlspecialchars($row['default_retention_years'] ?? '') ?></td>
                    <td><?= (int)$row['is_active'] === 1 ? 'Active' : 'Inactive' ?></td>
                    <td><a href="/harvest/admin/categories/edit.php?id=<?= (int)$row['id'] ?>">Edit</a></td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>

<?php app_require('app/includes/footer.php'); ?>