<?php
require_once dirname(__DIR__, 5) . '/app/bootstrap.php';

$conn = db_conn();require_once app_path('app/config/database.php');
app_require('app/includes/auth.php');
app_require('app/includes/flash.php');
app_require('app/includes/ui.php');

require_login();
$flash = get_flash();
$id = (int)($_GET['id'] ?? 0);

$stmt = $conn->prepare('SELECT * FROM documents WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $id);
$stmt->execute();
$document = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$document) {
    set_flash('error', 'Document not found.');
    header('Location: /harvest/staff/documents/index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $uploadResult = app_store_document_file($conn, $id, $_FILES['document_file'] ?? [], (int)$_SESSION['user_id']);
    if (!($uploadResult['ok'] ?? false)) {
        set_flash('error', $uploadResult['message'] ?? 'File upload failed.');
        header('Location: /harvest/staff/documents/upload.php?id=' . $id);
        exit;
    }

    $user_id = (int)$_SESSION['user_id'];
    $version_no = (int)($uploadResult['version_no'] ?? 0);
    $desc = "Uploaded document version {$version_no} for document ID {$id}";
    $stmt = $conn->prepare("INSERT INTO activity_logs (user_id, action_code, entity_type, entity_id, description, created_at) VALUES (?, 'document_file_uploaded', 'documents', ?, ?, NOW())");
    $stmt->bind_param('iis', $user_id, $id, $desc);
    $stmt->execute();
    $stmt->close();

    set_flash('success', 'File uploaded successfully.');
    header('Location: /harvest/staff/documents/view.php?id=' . $id);
    exit;
}

app_require('app/includes/header.php');
page_card_start('Upload File / New Version', 'Add a new version so the latest file is ready for review, approval, and download.');
flash_message($flash);
?>

<div class="grid gap-6 lg:grid-cols-[1fr_0.9fr]">
    <div class="rounded-3xl bg-white dark:bg-slate-900 p-6 ring-1 ring-slate-200 dark:ring-slate-800">
        <h2 class="text-xl font-semibold mb-5">Upload File</h2>
        <form method="POST" enctype="multipart/form-data" class="space-y-4 app-form-loader">
            <label for="document_file" class="app-upload-dropzone block cursor-pointer" data-dropzone="document_file">
                <input type="file" id="document_file" name="document_file" required accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png,.webp,.gif">
                <div class="dropzone-content text-center">
                    <div class="inline-flex h-14 w-14 items-center justify-center rounded-2xl bg-blue-50 text-blue-600 dark:bg-blue-900/30 dark:text-blue-300">
                        <i data-lucide="file-up" class="w-7 h-7"></i>
                    </div>
                    <div class="mt-4 text-base font-semibold text-slate-900 dark:text-white">Drop the new version here</div>
                    <div class="mt-2 text-sm text-slate-500 dark:text-slate-400">or click to choose the replacement file</div>
                    <div class="mt-4 flex justify-center">
                        <div class="app-upload-preview" data-file-preview-wrapper hidden>
                            <img src="" alt="Selected file preview" data-file-preview-image class="app-upload-preview-image hidden">
                            <div class="app-upload-preview-fallback" data-file-preview-fallback>
                                <i data-lucide="file-text" class="w-8 h-8"></i>
                            </div>
                        </div>
                    </div>
                    <div class="mt-4 inline-flex max-w-full items-center gap-2 rounded-full bg-slate-100 dark:bg-slate-800 px-4 py-2 text-sm text-slate-600 dark:text-slate-300">
                        <i data-lucide="paperclip" class="w-4 h-4"></i>
                        <span data-file-name>No file selected yet</span>
                        <span class="text-slate-400">•</span>
                        <span data-file-meta>This will become the latest version</span>
                    </div>
                </div>
            </label>

            <div class="rounded-2xl bg-slate-50 dark:bg-slate-950 p-4 ring-1 ring-slate-200 dark:ring-slate-800 text-sm text-slate-500 dark:text-slate-400 leading-6">
                Best practice: upload a finalized revision here after comments are addressed. The latest upload becomes the current downloadable version.
            </div>

            <div class="flex flex-wrap gap-3">
                <?php ui_primary_button('Upload New Version', 'upload'); ?>
                <?php action_button('/harvest/staff/documents/view.php?id=' . $id, 'Back to Document', 'arrow-left', 'secondary'); ?>
            </div>
        </form>
    </div>

    <div class="rounded-3xl bg-white dark:bg-slate-900 p-6 ring-1 ring-slate-200 dark:ring-slate-800">
        <h2 class="text-xl font-semibold mb-5">Document Summary</h2>
        <div class="space-y-4 text-sm">
            <div class="rounded-2xl bg-slate-50 dark:bg-slate-950 p-4 ring-1 ring-slate-200 dark:ring-slate-800">
                <div class="text-slate-500 dark:text-slate-400">Document Number</div>
                <div class="mt-1 font-semibold text-slate-900 dark:text-white"><?= htmlspecialchars($document['document_number'] ?? '-') ?></div>
            </div>
            <div class="rounded-2xl bg-slate-50 dark:bg-slate-950 p-4 ring-1 ring-slate-200 dark:ring-slate-800">
                <div class="text-slate-500 dark:text-slate-400">Title</div>
                <div class="mt-1 font-semibold text-slate-900 dark:text-white"><?= htmlspecialchars($document['title']) ?></div>
            </div>
            <div class="rounded-2xl bg-slate-50 dark:bg-slate-950 p-4 ring-1 ring-slate-200 dark:ring-slate-800">
                <div class="text-slate-500 dark:text-slate-400">Current Status</div>
                <div class="mt-2"><?= ui_status_badge($document['status'] ?? 'draft') ?></div>
            </div>
            <div class="rounded-2xl border border-dashed border-slate-300 dark:border-slate-700 p-4 text-slate-500 dark:text-slate-400 leading-6">
                New uploads keep the history intact, so reviewers can still inspect previous versions when needed.
            </div>
        </div>
    </div>
</div>

<?php page_card_end(); app_require('app/includes/footer.php'); ?>
