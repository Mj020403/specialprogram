<?php
require_once dirname(__DIR__, 3) . '/app/bootstrap.php';

$conn = db_conn();require_once app_path('app/config/database.php');
app_require('app/includes/auth.php');
app_require('app/includes/flash.php');

require_role(['developer','super_admin', 'system_admin', 'admin']);

$user_id = (int)($_GET['id'] ?? 0);

$stmt = $conn->prepare("
    SELECT u.id, u.email, up.first_name, up.last_name
    FROM users u
    LEFT JOIN user_profiles up ON up.user_id = u.id
    WHERE u.id = ?
    LIMIT 1
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$userResult = $stmt->get_result();
$user = $userResult->fetch_assoc();
$stmt->close();

if (!$user) {
    set_flash('error', 'User not found.');
    header("Location: /harvest/modules/admin/users/index.php");
    exit;
}

$departments = $conn->query("
    SELECT id, code, name
    FROM departments
    WHERE is_active = 1
    ORDER BY name ASC
");

$currentDepartments = [];
$currentPrimary = null;

$stmt = $conn->prepare("
    SELECT department_id, is_primary
    FROM user_department_assignments
    WHERE user_id = ?
      AND is_active = 1
      AND ended_at IS NULL
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $currentDepartments[] = (int)$row['department_id'];
    if ((int)$row['is_primary'] === 1) {
        $currentPrimary = (int)$row['department_id'];
    }
}
$stmt->close();

$flash = get_flash();

app_require('app/includes/header.php');
?>

<div class="container">
    <h1>User Department Assignments</h1>
    <p><a href="/harvest/modules/admin/users/index.php">← Back to Users</a></p>

    <?php if ($flash): ?>
        <div style="margin-bottom:15px;padding:12px;border-radius:8px;background:<?= $flash['type'] === 'success' ? '#dcfce7' : '#fee2e2' ?>;color:<?= $flash['type'] === 'success' ? '#166534' : '#991b1b' ?>;">
            <?= htmlspecialchars($flash['message']) ?>
        </div>
    <?php endif; ?>

    <p>
        <strong>User:</strong>
        <?= htmlspecialchars(trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''))) ?>
        (<?= htmlspecialchars($user['email']) ?>)
    </p>

    <form action="/harvest/admin/assignments/save_departments.php" method="POST">
        <input type="hidden" name="user_id" value="<?= (int)$user['id'] ?>">

        <table border="1" cellpadding="10" cellspacing="0" width="100%" style="border-collapse:collapse;">
            <thead style="background:#eff6ff;">
                <tr>
                    <th width="80">Assign</th>
                    <th width="90">Primary</th>
                    <th>Code</th>
                    <th>Name</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($dept = $departments->fetch_assoc()): ?>
                    <tr>
                        <td style="text-align:center;">
                            <input type="checkbox" name="department_ids[]" value="<?= (int)$dept['id'] ?>"
                                <?= in_array((int)$dept['id'], $currentDepartments, true) ? 'checked' : '' ?>>
                        </td>
                        <td style="text-align:center;">
                            <input type="radio" name="primary_department_id" value="<?= (int)$dept['id'] ?>"
                                <?= $currentPrimary === (int)$dept['id'] ? 'checked' : '' ?>>
                        </td>
                        <td><?= htmlspecialchars($dept['code']) ?></td>
                        <td><?= htmlspecialchars($dept['name']) ?></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>

        <br>
        <button type="submit" style="padding:12px 18px;background:#1d4ed8;color:#fff;border:none;border-radius:8px;">
            Save Department Assignments
        </button>
    </form>
</div>

<?php app_require('app/includes/footer.php'); ?>