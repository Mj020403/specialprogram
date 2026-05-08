<?php
require_once dirname(__DIR__, 5) . '/app/bootstrap.php';

$conn = db_conn();require_once app_path('app/config/database.php');
app_require('app/includes/auth.php');
app_require('app/includes/flash.php');
app_require('app/includes/ui.php');

require_login();

$id = (int)($_GET['id'] ?? 0);
$flash = get_flash();

$stmt = $conn->prepare("
    SELECT d.*, dc.name AS category_name, dep.name AS department_name
    FROM documents d
    LEFT JOIN document_categories dc ON dc.id = d.category_id
    LEFT JOIN departments dep ON dep.id = d.originating_department_id
    WHERE d.id = ?
    LIMIT 1
");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$document = $result->fetch_assoc();
$stmt->close();

if (!$document) {
    die("Document not found.");
}

app_record_document_access($conn, $id, (int)($_SESSION['user_id'] ?? 0), 'viewed_document');

$versions_stmt = $conn->prepare("
    SELECT *
    FROM document_versions
    WHERE document_id = ?
    ORDER BY version_no DESC
");
$versions_stmt->bind_param("i", $id);
$versions_stmt->execute();
$versions = $versions_stmt->get_result();
$versions_stmt->close();

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

$shares_stmt = $conn->prepare("
    SELECT ds.*, 
           su.email AS shared_by_email,
           tu.email AS target_user_email,
           td.name AS target_department_name
    FROM document_shares ds
    LEFT JOIN users su ON su.id = ds.shared_by_user_id
    LEFT JOIN users tu ON tu.id = ds.target_user_id
    LEFT JOIN departments td ON td.id = ds.target_department_id
    WHERE ds.document_id = ? AND ds.revoked_at IS NULL
    ORDER BY ds.created_at DESC
");
$shares_stmt->bind_param("i", $id);
$shares_stmt->execute();
$shares = $shares_stmt->get_result();
$shares_stmt->close();

$share_users = $conn->query("SELECT id, email FROM users WHERE is_active = 1 ORDER BY email ASC");
$share_departments = $conn->query("SELECT id, name FROM departments WHERE is_active = 1 ORDER BY name ASC");

app_require('app/includes/header.php');

page_card_start('Document Details', 'Review document information, versions, comments, and sharing.');
flash_message($flash);

$statusClass = match ($document['status']) {
    'draft' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300',
    'submitted', 'in_review' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300',
    'approved' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300',
    'for_revision' => 'bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-300',
    'archived' => 'bg-slate-200 text-slate-700 dark:bg-slate-800 dark:text-slate-300',
    default => 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300',
};
?>

<div class="flex flex-wrap gap-3 mb-6">
    <?php action_button('/harvest/staff/documents/index.php', 'Back to Documents', 'arrow-left', 'secondary'); ?>
    <?php action_button('/harvest/workflow/timeline.php?document_id=' . (int)$document['id'], 'Workflow Timeline', 'git-branch', 'secondary'); ?>
    <?php action_button('/harvest/documents/print.php?id=' . (int)$document['id'], 'Print View', 'printer', 'secondary'); ?>

    <?php if (in_array($document['status'], ['draft', 'for_revision'], true) && (int)$document['creator_user_id'] === (int)$_SESSION['user_id']): ?>
        <?php action_button('/harvest/staff/documents/edit.php?id=' . (int)$document['id'], 'Edit Draft', 'pencil', 'secondary'); ?>
        <?php action_button('/harvest/staff/documents/upload.php?id=' . (int)$document['id'], 'Upload Version', 'upload', 'secondary'); ?>
        <?php action_button('/harvest/staff/documents/submit.php?id=' . (int)$document['id'], 'Submit Document', 'send', 'primary'); ?>
        <?php if ($document['status'] === 'draft'): ?>
            <?php action_button('/harvest/staff/documents/delete.php?id=' . (int)$document['id'], 'Delete Draft', 'trash-2', 'danger'); ?>
        <?php endif; ?>
    <?php endif; ?>

    <?php if (in_array($_SESSION['role_code'] ?? '', ['super_admin', 'system_admin', 'admin', 'records_officer'], true) && in_array($document['status'], ['approved', 'rejected', 'cancelled'], true)): ?>
        <?php action_button('/harvest/documents/archive.php?id=' . (int)$document['id'], 'Archive', 'archive', 'secondary'); ?>
    <?php endif; ?>

    <?php if (in_array($_SESSION['role_code'] ?? '', ['super_admin', 'system_admin', 'admin', 'records_officer'], true) && $document['status'] === 'archived'): ?>
        <?php action_button('/harvest/documents/restore.php?id=' . (int)$document['id'], 'Restore', 'rotate-ccw', 'success'); ?>
    <?php endif; ?>
</div>

<div class="grid grid-cols-1 xl:grid-cols-3 gap-6 mb-6">
    <div class="xl:col-span-2 rounded-3xl bg-white dark:bg-slate-900 p-6 shadow-sm ring-1 ring-slate-200 dark:ring-slate-800">
        <div class="flex flex-wrap items-start justify-between gap-4 mb-5">
            <div>
                <h2 class="text-2xl font-bold text-slate-900 dark:text-white"><?= htmlspecialchars($document['title']) ?></h2>
                <p class="text-slate-500 dark:text-slate-400 mt-2">Document Number: <?= htmlspecialchars($document['document_number'] ?? '-') ?></p>
                <p class="text-slate-500 dark:text-slate-400 mt-1">Public Tracking: <?= htmlspecialchars($document['public_tracking_code'] ?? '-') ?></p>
            </div>
            <span class="inline-flex rounded-full px-3 py-1 text-sm font-semibold <?= $statusClass ?>">
                <?= htmlspecialchars($document['status']) ?>
            </span>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-5 text-sm">
            <div class="rounded-2xl bg-slate-50 dark:bg-slate-800 p-4">
                <div class="text-slate-500 dark:text-slate-400">Category</div>
                <div class="mt-1 font-semibold"><?= htmlspecialchars($document['category_name'] ?? '') ?></div>
            </div>
            <div class="rounded-2xl bg-slate-50 dark:bg-slate-800 p-4">
                <div class="text-slate-500 dark:text-slate-400">Department</div>
                <div class="mt-1 font-semibold"><?= htmlspecialchars($document['department_name'] ?? '') ?></div>
            </div>
            <div class="rounded-2xl bg-slate-50 dark:bg-slate-800 p-4">
                <div class="text-slate-500 dark:text-slate-400">Confidentiality</div>
                <div class="mt-1 font-semibold"><?= htmlspecialchars($document['confidentiality'] ?? '') ?></div>
            </div>
            <div class="rounded-2xl bg-slate-50 dark:bg-slate-800 p-4">
                <div class="text-slate-500 dark:text-slate-400">Current Version</div>
                <div class="mt-1 font-semibold"><?= (int)$document['current_version_no'] ?></div>
            </div>
            <div class="rounded-2xl bg-slate-50 dark:bg-slate-800 p-4">
                <div class="text-slate-500 dark:text-slate-400">Priority</div>
                <div class="mt-1 font-semibold"><?= htmlspecialchars($document['priority_level'] ?? 'normal') ?></div>
            </div>
        </div>

        <div class="mt-6">
            <h3 class="text-lg font-semibold mb-3">Description</h3>
            <div class="rounded-2xl bg-slate-50 dark:bg-slate-800 p-4 text-slate-700 dark:text-slate-200 leading-7">
                <?= nl2br(htmlspecialchars($document['description'] ?? 'No description provided.')) ?>
            </div>
        </div>
    </div>

    <div class="rounded-3xl bg-white dark:bg-slate-900 p-6 shadow-sm ring-1 ring-slate-200 dark:ring-slate-800">
        <h3 class="text-xl font-semibold mb-4">Versions</h3>

        <div class="space-y-3">
            <?php if ($versions->num_rows > 0): ?>
                <?php while ($v = $versions->fetch_assoc()): ?>
                    <div class="rounded-2xl border border-slate-200 dark:border-slate-700 p-4 hover:bg-slate-50 dark:hover:bg-slate-800 transition">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="font-semibold text-slate-900 dark:text-white">Version <?= (int)$v['version_no'] ?></div>
                                <div class="text-sm text-slate-500 dark:text-slate-400 mt-1 break-all"><?= htmlspecialchars($v['original_file_name']) ?></div>
                                <div class="text-xs text-slate-400 mt-1"><?= htmlspecialchars($v['created_at']) ?></div>
                            </div>
                        </div>

                        <div class="mt-4 flex flex-wrap gap-2">
                            <a href="/harvest/files/preview.php?id=<?= (int)$v['id'] ?>" target="_blank"
                               class="inline-flex items-center gap-2 rounded-xl bg-indigo-50 dark:bg-indigo-900/30 px-3 py-2 text-indigo-700 dark:text-indigo-300 hover:bg-indigo-100 dark:hover:bg-indigo-900/50 transition">
                                <i data-lucide="eye" class="w-4 h-4"></i>
                                Preview
                            </a>

                            <a href="/harvest/files/download.php?id=<?= (int)$v['id'] ?>"
                               class="inline-flex items-center gap-2 rounded-xl bg-blue-50 dark:bg-blue-900/30 px-3 py-2 text-blue-700 dark:text-blue-300 hover:bg-blue-100 dark:hover:bg-blue-900/50 transition">
                                <i data-lucide="download" class="w-4 h-4"></i>
                                Download
                            </a>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="rounded-2xl bg-slate-50 dark:bg-slate-800 p-4 text-slate-500 dark:text-slate-400">
                    No file versions uploaded yet.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 xl:grid-cols-2 gap-6 mb-6">
    <div class="rounded-3xl bg-white dark:bg-slate-900 p-6 shadow-sm ring-1 ring-slate-200 dark:ring-slate-800">
        <h3 class="text-xl font-semibold mb-4">Comments</h3>

        <form action="/harvest/comments/store.php" method="POST" class="mb-5">
            <input type="hidden" name="document_id" value="<?= (int)$document['id'] ?>">
            <input type="hidden" name="redirect_to" value="/harvest/staff/documents/view.php?id=<?= (int)$document['id'] ?>">
            <textarea name="comment_text" rows="4" required placeholder="Write a comment..."
                class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-4 py-3 outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-100"></textarea>
            <button type="submit"
                class="mt-3 inline-flex items-center gap-2 rounded-2xl bg-blue-600 px-5 py-3 font-semibold text-white shadow-sm hover:bg-blue-700 transition">
                <i data-lucide="message-square-plus" class="w-4 h-4"></i>
                Add Comment
            </button>
        </form>

        <div class="space-y-3">
            <?php if ($comments->num_rows > 0): ?>
                <?php while ($c = $comments->fetch_assoc()): ?>
                    <div class="rounded-2xl border border-slate-200 dark:border-slate-700 p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <div class="font-semibold text-slate-900 dark:text-white"><?= htmlspecialchars($c['email'] ?? '') ?></div>
                                <div class="text-sm text-slate-500 dark:text-slate-400 mt-1"><?= htmlspecialchars($c['created_at']) ?></div>
                            </div>
                            <?php if ((int)$c['author_user_id'] === (int)$_SESSION['user_id']): ?>
                                <a href="/harvest/comments/delete.php?id=<?= (int)$c['id'] ?>&redirect_to=<?= urlencode('/harvest/staff/documents/view.php?id=' . (int)$document['id']) ?>"
                                   onclick="return confirm('Delete this comment?');"
                                   class="text-rose-600 hover:text-rose-800 text-sm font-medium">
                                    Delete
                                </a>
                            <?php endif; ?>
                        </div>
                        <div class="mt-3 text-slate-700 dark:text-slate-200 leading-7">
                            <?= nl2br(htmlspecialchars($c['comment_text'])) ?>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="rounded-2xl bg-slate-50 dark:bg-slate-800 p-4 text-slate-500 dark:text-slate-400">
                    No comments yet.
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="rounded-3xl bg-white dark:bg-slate-900 p-6 shadow-sm ring-1 ring-slate-200 dark:ring-slate-800">
        <h3 class="text-xl font-semibold mb-4">Share Document</h3>

        <form action="/harvest/shares/store.php" method="POST" class="mb-6">
            <input type="hidden" name="document_id" value="<?= (int)$document['id'] ?>">
            <input type="hidden" name="redirect_to" value="/harvest/staff/documents/view.php?id=<?= (int)$document['id'] ?>">

            <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Share Type</label>
            <select name="share_type" id="share_type" onchange="toggleShareTarget()"
                class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-4 py-3 mb-4 outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-100">
                <option value="user">User</option>
                <option value="department">Department</option>
            </select>

            <div id="share_user_box">
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Target User</label>
                <select name="target_user_id"
                    class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-4 py-3 mb-4 outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-100">
                    <option value="">Select user</option>
                    <?php while ($u = $share_users->fetch_assoc()): ?>
                        <option value="<?= (int)$u['id'] ?>"><?= htmlspecialchars($u['email']) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div id="share_department_box" style="display:none;">
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Target Department</label>
                <select name="target_department_id"
                    class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-4 py-3 mb-4 outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-100">
                    <option value="">Select department</option>
                    <?php while ($d = $share_departments->fetch_assoc()): ?>
                        <option value="<?= (int)$d['id'] ?>"><?= htmlspecialchars($d['name']) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="space-y-2 mb-4 text-sm">
                <label class="flex items-center gap-2">
                    <input type="checkbox" name="can_view" value="1" checked class="rounded">
                    <span>Can View</span>
                </label>
                <label class="flex items-center gap-2">
                    <input type="checkbox" name="can_edit" value="1" class="rounded">
                    <span>Can Edit</span>
                </label>
            </div>

            <button type="submit"
                class="inline-flex items-center gap-2 rounded-2xl bg-blue-600 px-5 py-3 font-semibold text-white shadow-sm hover:bg-blue-700 transition">
                <i data-lucide="share-2" class="w-4 h-4"></i>
                Share
            </button>
        </form>

        <div class="space-y-3">
            <?php if ($shares->num_rows > 0): ?>
                <?php while ($s = $shares->fetch_assoc()): ?>
                    <div class="rounded-2xl border border-slate-200 dark:border-slate-700 p-4">
                        <div class="font-semibold text-slate-900 dark:text-white">
                            <?php if (!empty($s['target_user_email'])): ?>
                                User: <?= htmlspecialchars($s['target_user_email']) ?>
                            <?php else: ?>
                                Department: <?= htmlspecialchars($s['target_department_name'] ?? '') ?>
                            <?php endif; ?>
                        </div>
                        <div class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                            Shared by <?= htmlspecialchars($s['shared_by_email'] ?? '') ?> on <?= htmlspecialchars($s['created_at']) ?>
                        </div>
                        <div class="mt-2 text-sm text-slate-700 dark:text-slate-200">
                            Permissions:
                            <?= (int)$s['can_view'] === 1 ? 'View' : '' ?>
                            <?= (int)$s['can_edit'] === 1 ? ' / Edit' : '' ?>
                        </div>
                        <a href="/harvest/shares/revoke.php?id=<?= (int)$s['id'] ?>&redirect_to=<?= urlencode('/harvest/staff/documents/view.php?id=' . (int)$document['id']) ?>"
                           onclick="return confirm('Revoke this share?');"
                           class="inline-flex mt-3 text-rose-600 hover:text-rose-800 text-sm font-medium">
                            Revoke
                        </a>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="rounded-2xl bg-slate-50 dark:bg-slate-800 p-4 text-slate-500 dark:text-slate-400">
                    No active shares.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function toggleShareTarget() {
    const type = document.getElementById('share_type').value;
    document.getElementById('share_user_box').style.display = type === 'user' ? 'block' : 'none';
    document.getElementById('share_department_box').style.display = type === 'department' ? 'block' : 'none';
}
</script>

<?php
page_card_end();
app_require('app/includes/footer.php');
?>