<?php
require_once dirname(__DIR__, 3) . '/app/bootstrap.php';

$conn = db_conn();require_once app_path('app/config/database.php');
app_require('app/includes/auth.php');

require_login();

$id = (int)($_GET['id'] ?? 0);

$stmt = $conn->prepare("
    SELECT
        d.*,
        dc.name AS category_name,
        dep.name AS department_name,
        u.email AS creator_email
    FROM documents d
    LEFT JOIN document_categories dc ON dc.id = d.category_id
    LEFT JOIN departments dep ON dep.id = d.originating_department_id
    LEFT JOIN users u ON u.id = d.creator_user_id
    WHERE d.id = ? AND d.deleted_at IS NULL
    LIMIT 1
");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$document = $result->fetch_assoc();
$stmt->close();

if (!$document) {
    die('Document not found.');
}

$comments_stmt = $conn->prepare("
    SELECT dc.*, u.email
    FROM document_comments dc
    LEFT JOIN users u ON u.id = dc.author_user_id
    WHERE dc.document_id = ? AND dc.deleted_at IS NULL
    ORDER BY dc.created_at ASC
");
$comments_stmt->bind_param("i", $id);
$comments_stmt->execute();
$comments = $comments_stmt->get_result();
$comments_stmt->close();

$approvals_stmt = $conn->prepare("
    SELECT da.*, u.email
    FROM document_approvals da
    LEFT JOIN users u ON u.id = da.decided_by_user_id
    WHERE da.document_id = ?
    ORDER BY da.decided_at ASC
");
$approvals_stmt->bind_param("i", $id);
$approvals_stmt->execute();
$approvals = $approvals_stmt->get_result();
$approvals_stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Print Document</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 30px; color: #111; }
        h1, h2, h3 { margin-bottom: 10px; }
        .section { margin-bottom: 24px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        table, th, td { border: 1px solid #444; }
        th, td { padding: 8px; text-align: left; }
        .print-btn { margin-bottom: 20px; }
        @media print {
            .print-btn { display: none; }
        }
    </style>
</head>
<body>
    <button class="print-btn" onclick="window.print()">Print</button>

    <h1>Document Details</h1>

    <div class="section">
        <p><strong>Document No:</strong> <?= htmlspecialchars($document['document_number'] ?? '') ?></p>
        <p><strong>Title:</strong> <?= htmlspecialchars($document['title']) ?></p>
        <p><strong>Category:</strong> <?= htmlspecialchars($document['category_name'] ?? '') ?></p>
        <p><strong>Department:</strong> <?= htmlspecialchars($document['department_name'] ?? '') ?></p>
        <p><strong>Creator:</strong> <?= htmlspecialchars($document['creator_email'] ?? '') ?></p>
        <p><strong>Status:</strong> <?= htmlspecialchars($document['status']) ?></p>
        <p><strong>Confidentiality:</strong> <?= htmlspecialchars($document['confidentiality']) ?></p>
        <p><strong>Version:</strong> <?= (int)$document['current_version_no'] ?></p>
        <p><strong>Created At:</strong> <?= htmlspecialchars($document['created_at']) ?></p>
        <p><strong>Description:</strong><br><?= nl2br(htmlspecialchars($document['description'] ?? '')) ?></p>
    </div>

    <div class="section">
        <h3>Comments</h3>
        <table>
            <thead>
                <tr>
                    <th>Author</th>
                    <th>Comment</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($comments->num_rows > 0): ?>
                    <?php while ($row = $comments->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['email'] ?? '') ?></td>
                            <td><?= htmlspecialchars($row['comment_text']) ?></td>
                            <td><?= htmlspecialchars($row['created_at']) ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="3">No comments found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="section">
        <h3>Approvals / Decisions</h3>
        <table>
            <thead>
                <tr>
                    <th>Decision</th>
                    <th>By</th>
                    <th>Remarks</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($approvals->num_rows > 0): ?>
                    <?php while ($row = $approvals->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['decision']) ?></td>
                            <td><?= htmlspecialchars($row['email'] ?? '') ?></td>
                            <td><?= htmlspecialchars($row['remarks'] ?? '') ?></td>
                            <td><?= htmlspecialchars($row['decided_at']) ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="4">No approval records found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>
</html>