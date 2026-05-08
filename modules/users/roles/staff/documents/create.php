<?php
require_once dirname(__DIR__, 5) . '/app/bootstrap.php';

$conn = db_conn();require_once app_path('app/config/database.php');
app_require('app/includes/auth.php');
app_require('app/includes/flash.php');
app_require('app/includes/ui.php');

require_role(['staff', 'records_officer', 'super_admin', 'system_admin', 'admin']);
$flash = get_flash();
$categories = $conn->query('SELECT id, name FROM document_categories WHERE is_active = 1 ORDER BY name ASC');
$departments = $conn->query('SELECT id, name FROM departments WHERE is_active = 1 ORDER BY name ASC');

app_require('app/includes/header.php');
page_card_start('Create Document', 'Create a draft, upload the first file version immediately, or submit it right away in one step.');
flash_message($flash);
?>

<form action="/harvest/staff/documents/store.php" method="POST" enctype="multipart/form-data" class="space-y-6 app-form-loader">
    <input type="hidden" name="submit_mode" id="submit_mode" value="draft">

    <div class="grid gap-6 lg:grid-cols-[1.1fr_0.9fr]">
        <div class="rounded-3xl bg-white dark:bg-slate-900 p-6 ring-1 ring-slate-200 dark:ring-slate-800">
            <h2 class="text-xl font-semibold mb-5">Document Details</h2>
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-semibold mb-2">Title</label>
                    <input type="text" name="title" required class="<?= ui_input_class() ?>">
                </div>
                <div>
                    <label class="block text-sm font-semibold mb-2">Description</label>
                    <textarea name="description" rows="6" class="<?= ui_textarea_class() ?>"></textarea>
                </div>
                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="block text-sm font-semibold mb-2">Category</label>
                        <select name="category_id" required class="<?= ui_select_class() ?>">
                            <option value="">Select category</option>
                            <?php while ($c = $categories->fetch_assoc()): ?>
                                <option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold mb-2">Originating Department</label>
                        <select name="originating_department_id" required class="<?= ui_select_class() ?>">
                            <option value="">Select department</option>
                            <?php while ($d = $departments->fetch_assoc()): ?>
                                <option value="<?= (int)$d['id'] ?>"><?= htmlspecialchars($d['name']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="block text-sm font-semibold mb-2">Confidentiality</label>
                        <select name="confidentiality" class="<?= ui_select_class() ?>">
                            <option value="internal">Internal</option>
                            <option value="public">Public</option>
                            <option value="confidential">Confidential</option>
                            <option value="restricted">Restricted</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold mb-2">Priority</label>
                        <select name="priority_level" class="<?= ui_select_class() ?>">
                            <option value="low">Low</option>
                            <option value="normal" selected>Normal</option>
                            <option value="high">High</option>
                            <option value="urgent">Urgent</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <div class="rounded-3xl bg-white dark:bg-slate-900 p-6 ring-1 ring-slate-200 dark:ring-slate-800">
            <h2 class="text-xl font-semibold mb-5">File Upload</h2>
            <div class="space-y-4">
                <div class="rounded-2xl bg-slate-50 dark:bg-slate-950 p-4 ring-1 ring-slate-200 dark:ring-slate-800 text-sm text-slate-600 dark:text-slate-300 leading-6">
                    Uploading the first file now is best. That way the file becomes version 1, the preview is visible, and you can submit immediately in one click.
                </div>

                <label for="document_file" class="app-upload-dropzone block cursor-pointer" data-dropzone="document_file">
                    <input type="file" id="document_file" name="document_file" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png,.webp,.gif">
                    <div class="dropzone-content text-center">
                        <div class="inline-flex h-14 w-14 items-center justify-center rounded-2xl bg-blue-50 text-blue-600 dark:bg-blue-900/30 dark:text-blue-300">
                            <i data-lucide="upload-cloud" class="w-7 h-7"></i>
                        </div>
                        <div class="mt-4 text-base font-semibold text-slate-900 dark:text-white">Drag and drop a file here</div>
                        <div class="mt-2 text-sm text-slate-500 dark:text-slate-400">or click to browse from your device</div>

                        <div class="mt-4 flex justify-center">
                            <div class="app-upload-preview" data-file-preview-wrapper hidden>
                                <img src="" alt="Selected file preview" data-file-preview-image class="app-upload-preview-image hidden">
                                <div class="app-upload-preview-fallback" data-file-preview-fallback>
                                    <i data-lucide="file-text" class="w-8 h-8"></i>
                                </div>
                            </div>
                        </div>

                        <div class="mt-4 inline-flex max-w-full items-center gap-2 rounded-full bg-slate-100 dark:bg-slate-800 px-4 py-2 text-sm text-slate-600 dark:text-slate-300">
                            <i data-lucide="file-text" class="w-4 h-4"></i>
                            <span data-file-name>No file selected yet</span>
                            <span class="text-slate-400">•</span>
                            <span data-file-meta>PDF, DOCX, XLSX, scans</span>
                        </div>
                    </div>
                </label>

                <div class="rounded-2xl border border-dashed border-slate-300 dark:border-slate-700 p-4 text-sm text-slate-500 dark:text-slate-400 space-y-2">
                    <div class="font-semibold text-slate-900 dark:text-white">Helpful tips</div>
                    <ul class="list-disc pl-5 space-y-1">
                        <li>Save Draft keeps the document editable even if you are not ready to route it yet.</li>
                        <li>Save and Submit creates the document and automatically sends it to workflow right away.</li>
                        <li>The uploaded file becomes version 1 automatically.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <div class="flex flex-wrap gap-3">
        <button type="submit" onclick="document.getElementById('submit_mode').value='draft'" class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-5 py-3 font-semibold text-white shadow-sm hover:bg-blue-700 transition">
            <i data-lucide="save" class="w-4 h-4"></i>
            <span>Save Draft</span>
        </button>
        <button type="submit" onclick="document.getElementById('submit_mode').value='submit'" class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-5 py-3 font-semibold text-white shadow-sm hover:bg-emerald-700 transition">
            <i data-lucide="send" class="w-4 h-4"></i>
            <span>Save and Submit</span>
        </button>
        <?php action_button('/harvest/staff/documents/index.php', 'Back to My Documents', 'arrow-left', 'secondary'); ?>
    </div>
</form>

<?php page_card_end(); app_require('app/includes/footer.php'); ?>
