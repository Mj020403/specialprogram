<?php
require_once dirname(__DIR__, 4) . '/app/bootstrap.php';

$conn = db_conn();require_once app_path('app/config/database.php');
app_require('app/includes/auth.php');
app_require('app/includes/ui.php');
app_require('app/includes/app_helpers.php');

require_role(['mayor', 'executive', 'super_admin', 'system_admin', 'admin']);

$summary = [
    'total_documents' => 0,
    'pending_tasks' => 0,
    'overdue_tasks' => 0,
    'approved_month' => 0,
    'public_requests' => 0,
    'open_requests' => 0,
];

$queries = [
    'total_documents' => "SELECT COUNT(*) AS total FROM documents WHERE deleted_at IS NULL",
    'pending_tasks' => "SELECT COUNT(*) AS total FROM document_workflow_tasks WHERE status = 'pending'",
    'overdue_tasks' => "SELECT COUNT(*) AS total FROM document_workflow_tasks WHERE status = 'pending' AND due_at IS NOT NULL AND due_at < NOW()",
    'approved_month' => "SELECT COUNT(*) AS total FROM documents WHERE status = 'approved' AND approved_at >= DATE_FORMAT(NOW(), '%Y-%m-01')",
    'public_requests' => "SELECT COUNT(*) AS total FROM public_service_requests",
    'open_requests' => "SELECT COUNT(*) AS total FROM public_service_requests WHERE status IN ('received','under_review','endorsed','in_progress')",
];

foreach ($queries as $key => $sql) {
    $res = $conn->query($sql);
    if ($res) {
        $summary[$key] = (int)($res->fetch_assoc()['total'] ?? 0);
    }
}

$statusRows = [];
$statusResult = $conn->query("SELECT status, COUNT(*) AS total FROM documents WHERE deleted_at IS NULL GROUP BY status ORDER BY total DESC");
while ($statusResult && $row = $statusResult->fetch_assoc()) {
    $statusRows[] = $row;
}

$deptRows = [];
$deptResult = $conn->query("SELECT COALESCE(dep.name, 'Unassigned') AS department_name, COUNT(*) AS total
    FROM documents d
    LEFT JOIN departments dep ON dep.id = d.originating_department_id
    WHERE d.deleted_at IS NULL
    GROUP BY dep.id, dep.name
    ORDER BY total DESC
    LIMIT 8");
while ($deptResult && $row = $deptResult->fetch_assoc()) {
    $deptRows[] = $row;
}

$slowRows = [];
$slowResult = $conn->query("SELECT COALESCE(dep.name, 'Unassigned') AS department_name,
       COUNT(*) AS overdue_total,
       AVG(TIMESTAMPDIFF(HOUR, due_at, NOW())) AS avg_hours_late
    FROM document_workflow_tasks t
    LEFT JOIN documents d ON d.id = t.document_id
    LEFT JOIN departments dep ON dep.id = d.originating_department_id
    WHERE t.status = 'pending' AND t.due_at IS NOT NULL AND t.due_at < NOW()
    GROUP BY dep.id, dep.name
    ORDER BY overdue_total DESC, avg_hours_late DESC
    LIMIT 6");
while ($slowResult && $row = $slowResult->fetch_assoc()) {
    $slowRows[] = $row;
}

$recentApprovals = $conn->query("SELECT d.document_number, d.title, d.approved_at, COALESCE(dep.name, 'Unassigned') AS department_name
    FROM documents d
    LEFT JOIN departments dep ON dep.id = d.originating_department_id
    WHERE d.status = 'approved' AND d.approved_at IS NOT NULL
    ORDER BY d.approved_at DESC
    LIMIT 8");

$citizenRequests = $conn->query("SELECT reference_no, citizen_name, request_subject, status, priority_level, created_at
    FROM public_service_requests
    ORDER BY created_at DESC
    LIMIT 8");

app_require('app/includes/header.php');
page_card_start('Executive Dashboard', 'A mayor-friendly view of document flow, departmental accountability, and citizen service activity.');
?>

<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-6 gap-4 mb-8">
    <?php ui_stat_card('Documents', $summary['total_documents'], 'files', 'blue', 'All tracked records'); ?>
    <?php ui_stat_card('Pending Tasks', $summary['pending_tasks'], 'clock-3', 'amber', 'Needs action'); ?>
    <?php ui_stat_card('Overdue', $summary['overdue_tasks'], 'triangle-alert', 'rose', 'Delayed workflow items'); ?>
    <?php ui_stat_card('Approved This Month', $summary['approved_month'], 'badge-check', 'emerald', 'Current monthly output'); ?>
    <?php ui_stat_card('Citizen Requests', $summary['public_requests'], 'users-round', 'indigo', 'Total submitted to portal'); ?>
    <?php ui_stat_card('Open Requests', $summary['open_requests'], 'inbox', 'slate', 'Still being processed'); ?>
</div>

<div class="grid grid-cols-1 xl:grid-cols-[1.2fr_0.8fr] gap-6 mb-8">
    <div class="rounded-3xl bg-white dark:bg-slate-900 p-6 shadow-sm ring-1 ring-slate-200 dark:ring-slate-800">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <h3 class="text-xl font-semibold text-slate-900 dark:text-white">Quick executive actions</h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Fast access to the pages that matter during meetings and status reviews.</p>
            </div>
        </div>
        <div class="mt-6 grid sm:grid-cols-2 xl:grid-cols-3 gap-4">
            <?php ui_quick_link('/harvest/modules/agri/reports/index.php', 'Performance Reports', 'Open department-level metrics, delays, and status charts.', 'bar-chart-3'); ?>
            <?php ui_quick_link('/harvest/search/index.php', 'Search Records', 'Find documents quickly with advanced filters.', 'search'); ?>
            <?php ui_quick_link('/harvest/workflow/timeline.php', 'Audit Timeline', 'Inspect document history and workflow activity.', 'git-branch'); ?>
            <?php ui_quick_link('/harvest/citizen/index.php', 'Citizen Portal', 'Monitor public requests and share tracking with constituents.', 'users-round'); ?>
            <?php ui_quick_link('/harvest/modules/agri/notifications/index.php', 'Alerts', 'Check overdue tasks, workflow messages, and urgent notices.', 'bell-ring'); ?>
            <?php ui_quick_link('/harvest/admin/logs/index.php', 'System Logs', 'Review detailed activity and access history.', 'shield-check'); ?>
        </div>
    </div>

    <div class="rounded-3xl bg-white dark:bg-slate-900 p-6 shadow-sm ring-1 ring-slate-200 dark:ring-slate-800">
        <h3 class="text-xl font-semibold text-slate-900 dark:text-white">Mayor view summary</h3>
        <div class="mt-5 space-y-4 text-sm leading-6 text-slate-600 dark:text-slate-300">
            <div class="rounded-2xl bg-slate-50 dark:bg-slate-950 p-4 ring-1 ring-slate-200 dark:ring-slate-800">
                <strong class="text-slate-900 dark:text-white">Top concern:</strong>
                <?= $summary['overdue_tasks'] > 0 ? htmlspecialchars($summary['overdue_tasks'] . ' workflow tasks are overdue and should be escalated.') : 'No overdue workflow tasks right now.' ?>
            </div>
            <div class="rounded-2xl bg-slate-50 dark:bg-slate-950 p-4 ring-1 ring-slate-200 dark:ring-slate-800">
                <strong class="text-slate-900 dark:text-white">Service pulse:</strong>
                <?= htmlspecialchars($summary['open_requests'] . ' citizen requests are still open.') ?>
            </div>
            <div class="rounded-2xl bg-slate-50 dark:bg-slate-950 p-4 ring-1 ring-slate-200 dark:ring-slate-800">
                <strong class="text-slate-900 dark:text-white">Productivity:</strong>
                <?= htmlspecialchars($summary['approved_month'] . ' documents were approved this month.') ?>
            </div>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 xl:grid-cols-2 gap-6 mb-8">
    <div class="rounded-3xl bg-white dark:bg-slate-900 p-6 shadow-sm ring-1 ring-slate-200 dark:ring-slate-800">
        <h3 class="text-xl font-semibold mb-4 text-slate-900 dark:text-white">Document status overview</h3>
        <canvas id="execStatusChart"></canvas>
    </div>
    <div class="rounded-3xl bg-white dark:bg-slate-900 p-6 shadow-sm ring-1 ring-slate-200 dark:ring-slate-800">
        <h3 class="text-xl font-semibold mb-4 text-slate-900 dark:text-white">Top departments by document volume</h3>
        <canvas id="execDeptChart"></canvas>
    </div>
</div>

<div class="grid grid-cols-1 xl:grid-cols-2 gap-6 mb-6">
    <div class="overflow-hidden rounded-3xl bg-white dark:bg-slate-900 shadow-sm ring-1 ring-slate-200 dark:ring-slate-800">
        <div class="px-6 py-5 border-b border-slate-200 dark:border-slate-800">
            <h3 class="text-xl font-semibold text-slate-900 dark:text-white">Departments needing attention</h3>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Shows where delayed processing is concentrated.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 dark:bg-slate-800">
                    <tr>
                        <th class="px-4 py-3 font-semibold">Department</th>
                        <th class="px-4 py-3 font-semibold">Overdue Tasks</th>
                        <th class="px-4 py-3 font-semibold">Avg. Hours Late</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    <?php if (!empty($slowRows)): foreach ($slowRows as $row): ?>
                        <tr>
                            <td class="px-4 py-3"><?= htmlspecialchars($row['department_name']) ?></td>
                            <td class="px-4 py-3 font-semibold"><?= (int)$row['overdue_total'] ?></td>
                            <td class="px-4 py-3"><?= (int)round((float)$row['avg_hours_late']) ?></td>
                        </tr>
                    <?php endforeach; else: ?>
                        <tr><td colspan="3" class="px-4 py-8 text-center text-slate-500 dark:text-slate-400">No overdue departments right now.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="overflow-hidden rounded-3xl bg-white dark:bg-slate-900 shadow-sm ring-1 ring-slate-200 dark:ring-slate-800">
        <div class="px-6 py-5 border-b border-slate-200 dark:border-slate-800">
            <h3 class="text-xl font-semibold text-slate-900 dark:text-white">Recent approvals</h3>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Latest completed documents across the LGU.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 dark:bg-slate-800">
                    <tr>
                        <th class="px-4 py-3 font-semibold">Document No.</th>
                        <th class="px-4 py-3 font-semibold">Title</th>
                        <th class="px-4 py-3 font-semibold">Department</th>
                        <th class="px-4 py-3 font-semibold">Approved</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    <?php if ($recentApprovals && $recentApprovals->num_rows > 0): while ($row = $recentApprovals->fetch_assoc()): ?>
                        <tr>
                            <td class="px-4 py-3 font-medium"><?= htmlspecialchars($row['document_number'] ?: '-') ?></td>
                            <td class="px-4 py-3"><?= htmlspecialchars($row['title']) ?></td>
                            <td class="px-4 py-3"><?= htmlspecialchars($row['department_name']) ?></td>
                            <td class="px-4 py-3"><?= htmlspecialchars($row['approved_at']) ?></td>
                        </tr>
                    <?php endwhile; else: ?>
                        <tr><td colspan="4" class="px-4 py-8 text-center text-slate-500 dark:text-slate-400">No approvals yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="overflow-hidden rounded-3xl bg-white dark:bg-slate-900 shadow-sm ring-1 ring-slate-200 dark:ring-slate-800">
    <div class="px-6 py-5 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between gap-3">
        <div>
            <h3 class="text-xl font-semibold text-slate-900 dark:text-white">Latest citizen requests</h3>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Public-facing portal submissions visible to leadership.</p>
        </div>
        <a href="/harvest/citizen/index.php" class="inline-flex items-center gap-2 rounded-2xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700 transition">Open portal</a>
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
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                <?php if ($citizenRequests && $citizenRequests->num_rows > 0): while ($row = $citizenRequests->fetch_assoc()): ?>
                    <tr>
                        <td class="px-4 py-3 font-medium"><?= htmlspecialchars($row['reference_no']) ?></td>
                        <td class="px-4 py-3"><?= htmlspecialchars($row['citizen_name']) ?></td>
                        <td class="px-4 py-3"><?= htmlspecialchars($row['request_subject']) ?></td>
                        <td class="px-4 py-3"><?= ui_status_badge((string)$row['priority_level']) ?></td>
                        <td class="px-4 py-3"><?= ui_status_badge((string)$row['status']) ?></td>
                        <td class="px-4 py-3"><?= htmlspecialchars($row['created_at']) ?></td>
                    </tr>
                <?php endwhile; else: ?>
                    <tr><td colspan="6" class="px-4 py-8 text-center text-slate-500 dark:text-slate-400">No citizen requests have been submitted yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
function execTextColor() {
    return document.documentElement.classList.contains('dark') ? '#cbd5e1' : '#475569';
}
function execGridColor() {
    return document.documentElement.classList.contains('dark') ? 'rgba(148,163,184,0.15)' : 'rgba(148,163,184,0.20)';
}
new Chart(document.getElementById('execStatusChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_map(fn($r) => ucwords(str_replace('_', ' ', $r['status'])), $statusRows)) ?>,
        datasets: [{ data: <?= json_encode(array_map(fn($r) => (int)$r['total'], $statusRows)) ?>, borderRadius: 10 }]
    },
    options: {
        plugins: { legend: { display: false } },
        scales: {
            x: { ticks: { color: execTextColor() }, grid: { color: execGridColor() } },
            y: { ticks: { color: execTextColor() }, grid: { color: execGridColor() } }
        }
    }
});
new Chart(document.getElementById('execDeptChart'), {
    type: 'doughnut',
    data: {
        labels: <?= json_encode(array_map(fn($r) => $r['department_name'], $deptRows)) ?>,
        datasets: [{ data: <?= json_encode(array_map(fn($r) => (int)$r['total'], $deptRows)) ?> }]
    },
    options: { plugins: { legend: { position: 'bottom', labels: { color: execTextColor() } } } }
});
</script>

<?php page_card_end(); app_require('app/includes/footer.php'); ?>
