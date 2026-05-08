<?php
require_once dirname(__DIR__, 4) . '/app/bootstrap.php';

$conn = db_conn();require_once app_path('app/config/database.php');
app_require('app/includes/session.php');
app_require('app/includes/ui.php');
app_require('app/includes/app_helpers.php');
app_require('app/includes/auth.php');

$isLoggedIn = isset($_SESSION['user_id']);
$currentRole = $_SESSION['role_code'] ?? null;
$canManage = $isLoggedIn && in_array($currentRole, ['mayor', 'executive', 'super_admin', 'system_admin', 'admin', 'records_officer'], true);
$message = null;
$messageType = 'success';
$lookupResult = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (($_POST['action'] ?? '') === 'submit_request') {
        $name = trim($_POST['citizen_name'] ?? '');
        $email = trim($_POST['contact_email'] ?? '');
        $phone = trim($_POST['contact_phone'] ?? '');
        $subject = trim($_POST['request_subject'] ?? '');
        $details = trim($_POST['request_details'] ?? '');
        $priority = trim($_POST['priority_level'] ?? 'normal');

        if ($name === '' || $subject === '') {
            $message = 'Citizen name and request subject are required.';
            $messageType = 'error';
        } else {
            $referenceNo = 'REQ-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
            $stmt = $conn->prepare('INSERT INTO public_service_requests (reference_no, citizen_name, contact_email, contact_phone, request_subject, request_details, status, priority_level, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, \'received\', ?, NOW(), NOW())');
            $stmt->bind_param('sssssss', $referenceNo, $name, $email, $phone, $subject, $details, $priority);
            $stmt->execute();
            $requestId = $stmt->insert_id;
            $stmt->close();
            app_log_activity($conn, $isLoggedIn ? (int)$_SESSION['user_id'] : null, 'public_request_created', 'public_service_requests', $requestId, 'Citizen portal request submitted: ' . $referenceNo);
            $message = 'Request submitted successfully. Tracking reference: ' . $referenceNo;
            $messageType = 'success';
        }
    }

    if ($canManage && ($_POST['action'] ?? '') === 'update_status') {
        $requestId = (int)($_POST['request_id'] ?? 0);
        $newStatus = trim($_POST['status'] ?? 'received');
        $allowedStatuses = ['received', 'under_review', 'endorsed', 'in_progress', 'completed', 'closed'];
        if ($requestId > 0 && in_array($newStatus, $allowedStatuses, true)) {
            $stmt = $conn->prepare('UPDATE public_service_requests SET status = ?, updated_at = NOW() WHERE id = ?');
            $stmt->bind_param('si', $newStatus, $requestId);
            $stmt->execute();
            $stmt->close();
            app_log_activity($conn, (int)$_SESSION['user_id'], 'public_request_updated', 'public_service_requests', $requestId, 'Citizen request status updated to ' . $newStatus);
            $message = 'Citizen request status updated.';
            $messageType = 'success';
        }
    }
}

$trackingCode = trim($_GET['tracking_code'] ?? $_POST['tracking_code'] ?? '');
if ($trackingCode !== '') {
    $stmt = $conn->prepare('SELECT psr.*, dep.name AS department_name FROM public_service_requests psr LEFT JOIN departments dep ON dep.id = psr.assigned_department_id WHERE psr.reference_no = ? LIMIT 1');
    $stmt->bind_param('s', $trackingCode);
    $stmt->execute();
    $lookupResult = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

$recentRequests = null;
if ($canManage) {
    $recentRequests = $conn->query('SELECT psr.*, dep.name AS department_name FROM public_service_requests psr LEFT JOIN departments dep ON dep.id = psr.assigned_department_id ORDER BY psr.created_at DESC LIMIT 12');
}

if ($isLoggedIn) {
    app_require('app/includes/header.php');
    page_card_start('Citizen Service Portal', 'Public request intake, tracking, and leadership monitoring in one page.');
} else {
?><!DOCTYPE html>
<html lang="en" class="h-full antialiased">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Citizen Service Portal</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="/harvest/assets/css/app.css">
</head>
<body class="min-h-screen text-slate-800 dark:text-slate-100 transition-colors duration-300">
<div class="max-w-6xl mx-auto px-4 py-10">
    <section class="page-card overflow-hidden">
        <div class="page-card-header px-6 py-5 md:px-7 md:py-6">
            <h1 class="text-2xl md:text-3xl font-bold text-slate-900 dark:text-white">Citizen Service Portal</h1>
            <p class="mt-2 text-slate-500 dark:text-slate-400 max-w-3xl">Submit requests and track status using a public reference number.</p>
        </div>
        <div class="p-6 md:p-7">
<?php }

if ($message):
    flash_message(['type' => $messageType, 'message' => $message]);
endif;
?>

<div class="grid grid-cols-1 xl:grid-cols-2 gap-6 mb-6">
    <div class="rounded-3xl bg-white dark:bg-slate-900 p-6 shadow-sm ring-1 ring-slate-200 dark:ring-slate-800">
        <h2 class="text-xl font-semibold text-slate-900 dark:text-white">Submit a request</h2>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Citizens can file concerns, endorsements, and service requests here.</p>
        <form method="POST" class="mt-5 space-y-4">
            <input type="hidden" name="action" value="submit_request">
            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold mb-2">Full Name</label>
                    <input type="text" name="citizen_name" class="<?= ui_input_class() ?>" required>
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
                <div>
                    <label class="block text-sm font-semibold mb-2">Email</label>
                    <input type="email" name="contact_email" class="<?= ui_input_class() ?>">
                </div>
                <div>
                    <label class="block text-sm font-semibold mb-2">Phone</label>
                    <input type="text" name="contact_phone" class="<?= ui_input_class() ?>">
                </div>
            </div>
            <div>
                <label class="block text-sm font-semibold mb-2">Subject</label>
                <input type="text" name="request_subject" class="<?= ui_input_class() ?>" required>
            </div>
            <div>
                <label class="block text-sm font-semibold mb-2">Details</label>
                <textarea name="request_details" rows="5" class="<?= ui_textarea_class() ?>" placeholder="Describe the request, concern, or document needed."></textarea>
            </div>
            <button type="submit" class="inline-flex items-center gap-2 rounded-2xl bg-blue-600 px-5 py-3 font-semibold text-white shadow-sm hover:bg-blue-700 transition">Submit Request</button>
        </form>
    </div>

    <div class="rounded-3xl bg-white dark:bg-slate-900 p-6 shadow-sm ring-1 ring-slate-200 dark:ring-slate-800">
        <h2 class="text-xl font-semibold text-slate-900 dark:text-white">Track a request</h2>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Enter the public reference number to check status.</p>
        <form method="GET" class="mt-5">
            <label class="block text-sm font-semibold mb-2">Tracking Reference</label>
            <div class="search-inline">
                <input type="text" name="tracking_code" value="<?= htmlspecialchars($trackingCode) ?>" placeholder="REQ-YYYYMMDD-XXXXXX" class="search-inline-input">
                <button type="submit" class="search-inline-button"><i data-lucide="search" class="w-4 h-4"></i></button>
            </div>
        </form>

        <?php if ($trackingCode !== ''): ?>
            <div class="mt-5 rounded-2xl border border-slate-200 dark:border-slate-700 p-4">
                <?php if ($lookupResult): ?>
                    <div class="flex items-center justify-between gap-3 flex-wrap">
                        <div>
                            <div class="text-sm text-slate-500 dark:text-slate-400">Reference</div>
                            <div class="font-semibold text-slate-900 dark:text-white"><?= htmlspecialchars($lookupResult['reference_no']) ?></div>
                        </div>
                        <div><?= ui_status_badge((string)$lookupResult['status']) ?></div>
                    </div>
                    <div class="mt-4 grid sm:grid-cols-2 gap-4 text-sm">
                        <div><span class="text-slate-500 dark:text-slate-400">Citizen:</span> <?= htmlspecialchars($lookupResult['citizen_name']) ?></div>
                        <div><span class="text-slate-500 dark:text-slate-400">Priority:</span> <?= htmlspecialchars(ucfirst($lookupResult['priority_level'])) ?></div>
                        <div><span class="text-slate-500 dark:text-slate-400">Subject:</span> <?= htmlspecialchars($lookupResult['request_subject']) ?></div>
                        <div><span class="text-slate-500 dark:text-slate-400">Department:</span> <?= htmlspecialchars($lookupResult['department_name'] ?: 'Pending assignment') ?></div>
                        <div><span class="text-slate-500 dark:text-slate-400">Created:</span> <?= htmlspecialchars($lookupResult['created_at']) ?></div>
                        <div><span class="text-slate-500 dark:text-slate-400">Updated:</span> <?= htmlspecialchars($lookupResult['updated_at'] ?: $lookupResult['created_at']) ?></div>
                    </div>
                    <div class="mt-4 rounded-2xl bg-slate-50 dark:bg-slate-950 p-4 text-sm leading-7 text-slate-600 dark:text-slate-300">
                        <?= nl2br(htmlspecialchars($lookupResult['request_details'] ?: 'No details provided.')) ?>
                    </div>
                <?php else: ?>
                    <div class="text-sm text-rose-600 dark:text-rose-300">No request found for that tracking reference.</div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($canManage): ?>
<div class="overflow-hidden rounded-3xl bg-white dark:bg-slate-900 shadow-sm ring-1 ring-slate-200 dark:ring-slate-800">
    <div class="px-6 py-5 border-b border-slate-200 dark:border-slate-800">
        <h2 class="text-xl font-semibold text-slate-900 dark:text-white">Manage citizen requests</h2>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Internal view for the mayor, records, and administrators.</p>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 dark:bg-slate-800">
                <tr>
                    <th class="px-4 py-3 font-semibold">Reference</th>
                    <th class="px-4 py-3 font-semibold">Citizen</th>
                    <th class="px-4 py-3 font-semibold">Subject</th>
                    <th class="px-4 py-3 font-semibold">Priority</th>
                    <th class="px-4 py-3 font-semibold">Status</th>
                    <th class="px-4 py-3 font-semibold">Created</th>
                    <th class="px-4 py-3 font-semibold">Update</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                <?php if ($recentRequests && $recentRequests->num_rows > 0): while ($row = $recentRequests->fetch_assoc()): ?>
                    <tr>
                        <td class="px-4 py-3 font-medium"><?= htmlspecialchars($row['reference_no']) ?></td>
                        <td class="px-4 py-3"><?= htmlspecialchars($row['citizen_name']) ?></td>
                        <td class="px-4 py-3"><?= htmlspecialchars($row['request_subject']) ?></td>
                        <td class="px-4 py-3"><?= ui_status_badge((string)$row['priority_level']) ?></td>
                        <td class="px-4 py-3"><?= ui_status_badge((string)$row['status']) ?></td>
                        <td class="px-4 py-3"><?= htmlspecialchars($row['created_at']) ?></td>
                        <td class="px-4 py-3">
                            <form method="POST" class="flex gap-2 items-center">
                                <input type="hidden" name="action" value="update_status">
                                <input type="hidden" name="request_id" value="<?= (int)$row['id'] ?>">
                                <select name="status" class="<?= ui_select_class() ?> min-w-[150px]">
                                    <?php foreach (['received','under_review','endorsed','in_progress','completed','closed'] as $status): ?>
                                        <option value="<?= $status ?>" <?= $row['status'] === $status ? 'selected' : '' ?>><?= ucwords(str_replace('_', ' ', $status)) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-white font-semibold hover:bg-blue-700 transition">Save</button>
                            </form>
                        </td>
                    </tr>
                <?php endwhile; else: ?>
                    <tr><td colspan="7" class="px-4 py-8 text-center text-slate-500 dark:text-slate-400">No citizen requests available yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php if ($isLoggedIn): ?>
<?php page_card_end(); app_require('app/includes/footer.php'); ?>
<?php else: ?>
        </div>
    </section>
</div>
<script src="https://unpkg.com/lucide@latest"></script>
<script>window.lucide && window.lucide.createIcons();</script>
</body>
</html>
<?php endif; ?>
