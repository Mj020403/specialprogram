<?php
require_once dirname(__DIR__, 5) . '/app/bootstrap.php';

$conn = db_conn();require_once app_path('app/config/database.php');
app_require('app/includes/auth.php');
app_require('app/includes/flash.php');

require_login();

$id = (int)($_GET['id'] ?? 0);
$user_id = $_SESSION['user_id'];

$stmt = $conn->prepare("
    SELECT *
    FROM documents
    WHERE id = ? AND creator_user_id = ? AND status IN ('draft','for_revision')
    LIMIT 1
");
$stmt->bind_param("ii", $id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
$document = $result->fetch_assoc();
$stmt->close();

if (!$document) {
    set_flash('error', 'Draft document not found or cannot be edited.');
    header("Location: /harvest/staff/documents/index.php");
    exit;
}

$categories = $conn->query("SELECT id, name FROM document_categories WHERE is_active = 1 ORDER BY name ASC");
$departments = $conn->query("SELECT id, name FROM departments WHERE is_active = 1 ORDER BY name ASC");

app_require('app/includes/header.php');
?>

<div class="container">
    <h1>Edit Draft Document</h1>
    <p><a href="/harvest/staff/documents/view.php?id=<?= (int)$document['id'] ?>">← Back to Document</a></p>

    <form action="/harvest/staff/documents/update.php" method="POST">
        <input type="hidden" name="id" value="<?= (int)$document['id'] ?>">

        <label>Title</label>
        <input type="text" name="title" value="<?= htmlspecialchars($document['title']) ?>" required style="width:100%;padding:10px;margin-bottom:12px;">

        <label>Description</label>
        <textarea name="description" rows="5" style="width:100%;padding:10px;margin-bottom:12px;"><?= htmlspecialchars($document['description'] ?? '') ?></textarea>

        <label>Category</label>
        <select name="category_id" required style="width:100%;padding:10px;margin-bottom:12px;">
            <?php while ($c = $categories->fetch_assoc()): ?>
                <option value="<?= (int)$c['id'] ?>" <?= (int)$document['category_id'] === (int)$c['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($c['name']) ?>
                </option>
            <?php endwhile; ?>
        </select>

        <label>Originating Department</label>
        <select name="originating_department_id" required style="width:100%;padding:10px;margin-bottom:12px;">
            <?php while ($d = $departments->fetch_assoc()): ?>
                <option value="<?= (int)$d['id'] ?>" <?= (int)$document['originating_department_id'] === (int)$d['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($d['name']) ?>
                </option>
            <?php endwhile; ?>
        </select>

        <label>Confidentiality</label>
        <select name="confidentiality" style="width:100%;padding:10px;margin-bottom:20px;">
            <?php foreach (['public','internal','confidential','restricted'] as $conf): ?>
                <option value="<?= $conf ?>" <?= $document['confidentiality'] === $conf ? 'selected' : '' ?>>
                    <?= ucfirst($conf) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <button type="submit" style="padding:12px 18px;background:#1d4ed8;color:#fff;border:none;border-radius:8px;">Update Draft</button>
    </form>
</div>

<?php app_require('app/includes/footer.php'); ?>