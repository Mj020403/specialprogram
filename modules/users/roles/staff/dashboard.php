<?php
require_once dirname(__DIR__, 4) . '/app/bootstrap.php';

$conn = db_conn();require_once app_path('app/config/database.php');
app_require('app/includes/auth.php');
app_require('app/includes/ui.php');

require_role(['staff']);
app_require('app/includes/header.php');

$user_id = (int)$_SESSION['user_id'];

$count_docs = (int)$conn->query("SELECT COUNT(*) AS total FROM documents WHERE creator_user_id = {$user_id}")->fetch_assoc()['total'];
$count_draft = (int)$conn->query("SELECT COUNT(*) AS total FROM documents WHERE creator_user_id = {$user_id} AND status = 'draft'")->fetch_assoc()['total'];
$count_submitted = (int)$conn->query("SELECT COUNT(*) AS total FROM documents WHERE creator_user_id = {$user_id} AND status IN ('submitted','in_review')")->fetch_assoc()['total'];
$count_revision = (int)$conn->query("SELECT COUNT(*) AS total FROM documents WHERE creator_user_id = {$user_id} AND status = 'for_revision'")->fetch_assoc()['total'];
$count_notif = (int)$conn->query("SELECT COUNT(*) AS total FROM notifications WHERE user_id = {$user_id} AND is_read = 0")->fetch_assoc()['total'];

$recentStmt = $conn->prepare("
    SELECT id, document_number, title, status, current_version_no, created_at
    FROM documents
    WHERE creator_user_id = ?
    ORDER BY id DESC
    LIMIT 6
");
$recentStmt->bind_param("i", $user_id);
$recentStmt->execute();
$recentDocs = $recentStmt->get_result();
$recentStmt->close();

page_card_start('Staff Dashboard', 'A polished workspace for creating, tracking, and managing your documents.');
?>

<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-5 gap-4 mb-8">
    <?php ui_stat_card('Total Documents', $count_docs, 'files', 'blue', 'Everything you created'); ?>
    <?php ui_stat_card('Drafts', $count_draft, 'file-text', 'amber', 'Ready to continue'); ?>
    <?php ui_stat_card('Submitted / In Review', $count_submitted, 'send', 'indigo', 'Waiting for workflow actions'); ?>
    <?php ui_stat_card('For Revision', $count_revision, 'refresh-ccw', 'rose', 'Needs your update'); ?>
    <?php ui_stat_card('Unread Notifications', $count_notif, 'bell-ring', 'emerald', 'Recent alerts and updates'); ?>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4 mb-8">
    <?php ui_quick_link('/harvest/staff/documents/create.php', 'Create / Upload Document', 'Start a new draft and optionally upload the first file immediately.', 'file-plus'); ?>
    <?php ui_quick_link('/harvest/staff/documents/index.php', 'My Documents', 'Browse, filter, and manage your document list.', 'folder-open'); ?>
    <?php ui_quick_link('/harvest/shared/index.php', 'Shared With Me', 'Open files sent directly to you.', 'share-2'); ?>
    <?php ui_quick_link('/harvest/search/index.php', 'Search', 'Find documents faster with filters and status badges.', 'search'); ?>
</div>

<div class="grid grid-cols-1 xl:grid-cols-[1.35fr_0.65fr] gap-6">
    <div class="rounded-3xl bg-white dark:bg-slate-900 p-6 shadow-sm ring-1 ring-slate-200 dark:ring-slate-800">
        <div class="flex items-center justify-between gap-3 mb-5">
            <div>
                <h2 class="text-xl font-semibold text-slate-900 dark:text-white">Recent documents</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Your latest drafts and workflow submissions.</p>
            </div>
            <a href="/harvest/staff/documents/index.php" class="text-sm font-semibold text-blue-600 dark:text-blue-300">View all</a>
        </div>

        <?php if ($recentDocs->num_rows > 0): ?>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr>
                            <th>Document No.</th>
                            <th>Title</th>
                            <th>Status</th>
                            <th>Version</th>
                            <th>Created</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $recentDocs->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['document_number'] ?? '-') ?></td>
                                <td class="font-medium text-slate-900 dark:text-white"><?= htmlspecialchars($row['title']) ?></td>
                                <td><?= ui_status_badge($row['status']) ?></td>
                                <td>v<?= (int)$row['current_version_no'] ?></td>
                                <td><?= htmlspecialchars($row['created_at']) ?></td>
                                <td><a href="/harvest/staff/documents/view.php?id=<?= (int)$row['id'] ?>">Open</a></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <?php ui_empty_state('No documents yet', 'Start by creating your first document draft and it will appear here.', 'file-plus'); ?>
        <?php endif; ?>
    </div>

    <div class="space-y-6">
        <div class="rounded-3xl bg-white dark:bg-slate-900 p-6 shadow-sm ring-1 ring-slate-200 dark:ring-slate-800">
            <h2 class="text-xl font-semibold text-slate-900 dark:text-white">What is already upgraded</h2>
            <ul class="mt-4 space-y-3 text-sm leading-6 text-slate-600 dark:text-slate-300">
                <li class="flex gap-3"><i data-lucide="moon-star" class="w-4 h-4 mt-1 text-blue-600"></i><span>Theme toggle with dark mode memory.</span></li>
                <li class="flex gap-3"><i data-lucide="panel-left" class="w-4 h-4 mt-1 text-blue-600"></i><span>Responsive sidebar with better navigation and profile dropdown.</span></li>
                <li class="flex gap-3"><i data-lucide="table-properties" class="w-4 h-4 mt-1 text-blue-600"></i><span>Refreshed tables, cards, forms, and badges across the app.</span></li>
                <li class="flex gap-3"><i data-lucide="sparkles" class="w-4 h-4 mt-1 text-blue-600"></i><span>Fancy but cleaner layout so the system feels consistent.</span></li>
                <li class="flex gap-3"><i data-lucide="image" class="w-4 h-4 mt-1 text-blue-600"></i><span>Users can now upload real profile photos that appear around the system.</span></li>
            </ul>
        </div>

        <div class="rounded-3xl bg-slate-900 text-white p-6 shadow-xl">
            <div class="text-sm text-slate-300">Need a fast action?</div>
            <div class="mt-2 text-2xl font-bold">Keep your workflow moving.</div>
            <p class="mt-3 text-sm leading-6 text-slate-300">Create, upload, or review your files from the updated navigation without digging through cluttered UI.</p>
            <div class="mt-5 flex flex-wrap gap-3">
                <?php action_button('/harvest/staff/documents/create.php', 'New Draft', 'file-plus'); ?>
                <?php action_button('/harvest/modules/agri/notifications/index.php', 'Notifications', 'bell', 'secondary'); ?>
            </div>
        </div>
    </div>
</div>

<?= app_dashboard_insights_panel($conn, 'Staff dashboard snapshot', 'Live charts for users, queues, compliance, and the current municipal situation.') ?>
<?php
page_card_end();
app_require('app/includes/footer.php');
?>