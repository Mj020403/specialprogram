<?php
require_once dirname(__DIR__, 5) . '/app/bootstrap.php';

$conn = db_conn();require_once app_path('app/config/database.php');
app_require('app/includes/auth.php');
app_require('app/includes/flash.php');
app_require('app/includes/ui.php');

require_role(['department_head']);
$flash = get_flash();
$id = (int)($_GET['id'] ?? 0);

$stmt = $conn->prepare("\n    SELECT\n        t.*,\n        d.title,\n        d.document_number,\n        d.description,\n        d.status AS document_status,\n        d.creator_user_id,\n        d.id AS document_id,\n        d.current_version_no\n    FROM document_workflow_tasks t\n    JOIN documents d ON d.id = t.document_id\n    WHERE t.id = ?\n    LIMIT 1\n");
$stmt->bind_param("i", $id);
$stmt->execute();
$task = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$task) {
    set_flash('error', 'Task not found.');
    header('Location: /harvest/department_head/tasks/index.php');
    exit;
}

$versionsStmt = $conn->prepare("SELECT * FROM document_versions WHERE document_id = ? ORDER BY version_no DESC");
$versionsStmt->bind_param("i", $task['document_id']);
$versionsStmt->execute();
$versions = $versionsStmt->get_result();
$versionsStmt->close();

$commentsStmt = $conn->prepare("\n    SELECT dc.*, u.email\n    FROM document_comments dc\n    LEFT JOIN users u ON u.id = dc.author_user_id\n    WHERE dc.document_id = ? AND dc.deleted_at IS NULL\n    ORDER BY dc.created_at ASC\n");
$commentsStmt->bind_param("i", $task['document_id']);
$commentsStmt->execute();
$comments = $commentsStmt->get_result();
$commentsStmt->close();

$approvalImageSelect = app_profile_image_available($conn) ? 'up.profile_image_path' : 'NULL AS profile_image_path';
$historySql = "\n    SELECT\n        da.decision,\n        da.remarks,\n        da.decided_at,\n        u.email,\n        up.first_name,\n        up.last_name,\n        {$approvalImageSelect}\n    FROM document_approvals da\n    LEFT JOIN users u ON u.id = da.decided_by_user_id\n    LEFT JOIN user_profiles up ON up.user_id = u.id\n    WHERE da.document_id = ?\n    ORDER BY da.decided_at DESC\n    LIMIT 8\n";
$historyStmt = $conn->prepare($historySql);
$historyStmt->bind_param('i', $task['document_id']);
$historyStmt->execute();
$history = $historyStmt->get_result();
$historyStmt->close();

app_require('app/includes/header.php');
page_card_start('Department Head Review', 'Review the task, inspect versions, and return clear revision guidance when needed.');
flash_message($flash);
?>

<div class="flex flex-wrap gap-3 mb-6">
    <?php action_button('/harvest/department_head/tasks/index.php', 'Back to Tasks', 'arrow-left', 'secondary'); ?>
    <?php action_button('/harvest/workflow/timeline.php?q=' . urlencode($task['document_number'] ?? $task['title']), 'Open Timeline', 'git-branch', 'secondary'); ?>
</div>

<div class="grid grid-cols-1 xl:grid-cols-[1.2fr_0.8fr] gap-6 mb-6">
    <div class="space-y-6">
        <div class="rounded-3xl bg-white dark:bg-slate-900 p-6 shadow-sm ring-1 ring-slate-200 dark:ring-slate-800">
            <div class="flex flex-wrap items-start justify-between gap-4 mb-5">
                <div>
                    <h2 class="text-2xl font-bold text-slate-900 dark:text-white"><?= htmlspecialchars($task['title']) ?></h2>
                    <p class="text-slate-500 dark:text-slate-400 mt-2">Document Number: <?= htmlspecialchars($task['document_number'] ?? '-') ?></p>
                </div>
                <span><?= ui_status_badge($task['document_status']) ?></span>
            </div>

            <div class="grid gap-4 md:grid-cols-3 mb-5">
                <div class="rounded-2xl bg-slate-50 dark:bg-slate-950 p-4 ring-1 ring-slate-200 dark:ring-slate-800">
                    <div class="text-xs uppercase tracking-[0.22em] text-slate-400">Task Step</div>
                    <div class="mt-2 text-lg font-bold text-slate-900 dark:text-white">#<?= (int)($task['step_no'] ?? 0) ?></div>
                </div>
                <div class="rounded-2xl bg-slate-50 dark:bg-slate-950 p-4 ring-1 ring-slate-200 dark:ring-slate-800">
                    <div class="text-xs uppercase tracking-[0.22em] text-slate-400">Due Date</div>
                    <div class="mt-2 text-sm font-semibold text-slate-900 dark:text-white"><?= htmlspecialchars($task['due_at'] ?: 'No due date') ?></div>
                </div>
                <div class="rounded-2xl bg-slate-50 dark:bg-slate-950 p-4 ring-1 ring-slate-200 dark:ring-slate-800">
                    <div class="text-xs uppercase tracking-[0.22em] text-slate-400">Current Version</div>
                    <div class="mt-2 text-lg font-bold text-slate-900 dark:text-white">v<?= (int)($task['current_version_no'] ?? 0) ?></div>
                </div>
            </div>

            <div class="rounded-2xl bg-slate-50 dark:bg-slate-800 p-4 text-slate-700 dark:text-slate-200 leading-7">
                <?= nl2br(htmlspecialchars($task['description'] ?? 'No description provided.')) ?>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <form action="/harvest/workflow/approve.php" method="POST" class="rounded-3xl border border-emerald-200 dark:border-emerald-900 bg-emerald-50 dark:bg-emerald-900/20 p-5 app-form-loader">
                <input type="hidden" name="task_id" value="<?= (int)$task['id'] ?>">
                <h3 class="text-lg font-semibold text-emerald-700 dark:text-emerald-300 mb-3">Approve / Forward</h3>
                <p class="text-sm text-emerald-700/80 dark:text-emerald-200/80 mb-3">Use remarks when you want the next reviewer to see context.</p>
                <textarea name="remarks" rows="4" placeholder="Optional remarks" class="<?= ui_textarea_class() ?>"></textarea>
                <button type="submit" class="mt-3 inline-flex items-center gap-2 rounded-2xl bg-emerald-600 px-5 py-3 font-semibold text-white shadow-sm hover:bg-emerald-700 transition">
                    <i data-lucide="check-check" class="w-4 h-4"></i>
                    Approve / Forward
                </button>
            </form>

            <form action="/harvest/workflow/return.php" method="POST" class="rounded-3xl border border-rose-200 dark:border-rose-900 bg-rose-50 dark:bg-rose-900/20 p-5 app-form-loader">
                <input type="hidden" name="task_id" value="<?= (int)$task['id'] ?>">
                <h3 class="text-lg font-semibold text-rose-700 dark:text-rose-300 mb-3">Return for Revision</h3>
                <p class="text-sm text-rose-700/80 dark:text-rose-200/80 mb-3">Write clear revision notes so the document owner knows exactly what to fix.</p>
                <textarea name="remarks" rows="4" required placeholder="Reason for revision" class="<?= ui_textarea_class() ?>"></textarea>
                <button type="submit" class="mt-3 inline-flex items-center gap-2 rounded-2xl bg-rose-600 px-5 py-3 font-semibold text-white shadow-sm hover:bg-rose-700 transition">
                    <i data-lucide="corner-up-left" class="w-4 h-4"></i>
                    Return for Revision
                </button>
            </form>
        </div>
    </div>

    <div class="space-y-6">
        <div class="rounded-3xl bg-white dark:bg-slate-900 p-6 shadow-sm ring-1 ring-slate-200 dark:ring-slate-800">
            <h3 class="text-xl font-semibold mb-4">Versions</h3>
            <div class="space-y-3">
                <?php if ($versions->num_rows > 0): ?>
                    <?php while ($v = $versions->fetch_assoc()): ?>
                        <div class="rounded-2xl border border-slate-200 dark:border-slate-700 p-4 hover:bg-slate-50 dark:hover:bg-slate-800 transition">
                            <div class="font-semibold text-slate-900 dark:text-white">Version <?= (int)$v['version_no'] ?></div>
                            <div class="text-sm text-slate-500 dark:text-slate-400 mt-1 break-all"><?= htmlspecialchars($v['original_file_name']) ?></div>
                            <div class="text-xs text-slate-400 mt-1"><?= htmlspecialchars($v['created_at']) ?></div>
                            <div class="mt-4 flex flex-wrap gap-2">
                                <a href="/harvest/files/preview.php?id=<?= (int)$v['id'] ?>" target="_blank" class="inline-flex items-center gap-1 rounded-xl bg-indigo-50 dark:bg-indigo-900/30 px-3 py-2 text-indigo-700 dark:text-indigo-300 hover:bg-indigo-100 dark:hover:bg-indigo-900/50 transition">
                                    <i data-lucide="eye" class="w-4 h-4"></i>
                                    Preview
                                </a>
                                <a href="/harvest/files/download.php?id=<?= (int)$v['id'] ?>" class="inline-flex items-center gap-1 rounded-xl bg-blue-50 dark:bg-blue-900/30 px-3 py-2 text-blue-700 dark:text-blue-300 hover:bg-blue-100 dark:hover:bg-blue-900/50 transition">
                                    <i data-lucide="download" class="w-4 h-4"></i>
                                    Download
                                </a>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <?php ui_empty_state('No versions uploaded', 'File versions will appear here once available.', 'files'); ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="rounded-3xl bg-white dark:bg-slate-900 p-6 shadow-sm ring-1 ring-slate-200 dark:ring-slate-800">
            <h3 class="text-xl font-semibold mb-4">Approval Activity</h3>
            <div class="space-y-4">
                <?php if ($history->num_rows > 0): ?>
                    <?php while ($row = $history->fetch_assoc()): ?>
                        <?php $actorName = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')) ?: ($row['email'] ?? 'System User'); ?>
                        <div class="rounded-2xl border border-slate-200 dark:border-slate-700 p-4">
                            <div class="flex items-start gap-3">
                                <img src="<?= htmlspecialchars(app_user_avatar($row['profile_image_path'] ?? null)) ?>" alt="<?= htmlspecialchars($actorName) ?>" class="h-11 w-11 rounded-2xl object-cover">
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <div class="font-semibold text-slate-900 dark:text-white"><?= htmlspecialchars($actorName) ?></div>
                                        <span><?= ui_status_badge($row['decision']) ?></span>
                                    </div>
                                    <div class="mt-1 text-xs text-slate-400"><?= htmlspecialchars($row['decided_at'] ?? '-') ?></div>
                                    <?php if (!empty($row['remarks'])): ?>
                                        <div class="mt-3 text-sm leading-6 text-slate-600 dark:text-slate-300"><?= nl2br(htmlspecialchars($row['remarks'])) ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <?php ui_empty_state('No decisions yet', 'Approval actions will appear here once someone forwards, approves, or returns the document.', 'history'); ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="rounded-3xl bg-white dark:bg-slate-900 p-6 shadow-sm ring-1 ring-slate-200 dark:ring-slate-800">
    <h3 class="text-xl font-semibold mb-4">Comments</h3>

    <form action="/harvest/comments/store.php" method="POST" class="mb-5 app-form-loader">
        <input type="hidden" name="document_id" value="<?= (int)$task['document_id'] ?>">
        <input type="hidden" name="redirect_to" value="/harvest/department_head/tasks/view.php?id=<?= (int)$task['id'] ?>">
        <textarea name="comment_text" rows="4" required placeholder="Write a comment..." class="<?= ui_textarea_class() ?>"></textarea>
        <button type="submit" class="mt-3 inline-flex items-center gap-2 rounded-2xl bg-blue-600 px-5 py-3 font-semibold text-white shadow-sm hover:bg-blue-700 transition">
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
                            <a href="/harvest/comments/delete.php?id=<?= (int)$c['id'] ?>&redirect_to=<?= urlencode('/harvest/department_head/tasks/view.php?id=' . (int)$task['id']) ?>"
                               onclick="return confirm('Delete this comment?');"
                               class="text-rose-600 hover:text-rose-800 text-sm font-medium">
                                Delete
                            </a>
                        <?php endif; ?>
                    </div>
                    <div class="mt-3 text-slate-700 dark:text-slate-200 leading-7"><?= nl2br(htmlspecialchars($c['comment_text'])) ?></div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <?php ui_empty_state('No comments yet', 'Use comments to coordinate review notes on this task.', 'message-square'); ?>
        <?php endif; ?>
    </div>
</div>

<?php
page_card_end();
app_require('app/includes/footer.php');
