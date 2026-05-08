<?php

function e(?string $value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }

function post(string $key, $default = '') { return $_POST[$key] ?? $default; }

function getv(string $key, $default = '') { return $_GET[$key] ?? $default; }

function set_flash(string $type, string $message): void { $_SESSION['flash'] = ['type' => $type, 'message' => $message]; }

function get_flash(): ?array { $flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']); return $flash; }

function table_exists(mysqli $conn, string $table): bool {
    $table = $conn->real_escape_string($table);
    $res = $conn->query("SHOW TABLES LIKE '{$table}'");
    return $res instanceof mysqli_result && $res->num_rows > 0;
}

function column_exists(mysqli $conn, string $table, string $column): bool {
    static $cache = [];
    $key = $table . '.' . $column;
    if (array_key_exists($key, $cache)) return $cache[$key];
    if (!table_exists($conn, $table)) return $cache[$key] = false;
    $stmt = $conn->prepare("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1");
    if (!$stmt) return $cache[$key] = false;
    $stmt->bind_param('ss', $table, $column);
    $stmt->execute();
    $res = $stmt->get_result();
    $ok = $res instanceof mysqli_result && $res->num_rows > 0;
    $stmt->close();
    return $cache[$key] = $ok;
}


function safe_table_count(mysqli $conn, string $table, string $where = '1=1', $default = 0) {
    if (!table_exists($conn, $table)) return $default;
    $sql = "SELECT COUNT(*) FROM `{$table}` WHERE {$where}";
    return scalar($conn, $sql, $default);
}

function optional_table_message(mysqli $conn, array $tables): ?string {
    $missing = [];
    foreach ($tables as $table) {
        if (!table_exists($conn, $table)) $missing[] = $table;
    }
    if (!$missing) return null;
    return 'Missing optional table' . (count($missing) === 1 ? '' : 's') . ': ' . implode(', ', $missing) . '. Run the admin upgrade SQL to enable this module fully.';
}

function fetch_all_assoc(mysqli $conn, string $sql): array {
    $result = $conn->query($sql);
    if (!($result instanceof mysqli_result)) {
        return [];
    }
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    $result->free();
    return $rows;
}

function fetch_one(mysqli $conn, string $sql): ?array {
    $result = $conn->query($sql);
    return ($result instanceof mysqli_result) ? ($result->fetch_assoc() ?: null) : null;
}

function scalar(mysqli $conn, string $sql, $default = 0) {
    $row = fetch_one($conn, $sql);
    if (!$row) return $default;
    $vals = array_values($row);
    return $vals[0] ?? $default;
}

function app_log(mysqli $conn, ?int $userId, string $module, string $action, ?int $recordId = null, string $description = ''): void {
    if (!table_exists($conn, 'audit_logs')) return;
    $stmt = $conn->prepare("INSERT INTO audit_logs (user_id, module_name, action_name, record_id, description, ip_address, user_agent, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
    if ($stmt) {
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
        $stmt->bind_param('ississs', $userId, $module, $action, $recordId, $description, $ip, $ua);
        $stmt->execute();
        $stmt->close();
    }
}

function current_user(): array {
    static $cached = null;
    if ($cached !== null) return $cached;
    $userId = (int)($_SESSION['user_id'] ?? 0);
    $base = [
        'id' => $userId ?: null,
        'name' => $_SESSION['full_name'] ?? 'Guest',
        'username' => $_SESSION['username'] ?? '',
        'role' => $_SESSION['role_code'] ?? '',
        'email' => $_SESSION['email'] ?? '',
        'contact_number' => $_SESSION['contact_number'] ?? '',
        'position_title' => $_SESSION['position_title'] ?? '',
        'avatar_path' => $_SESSION['avatar_path'] ?? null,
        'profile_status' => $_SESSION['profile_status'] ?? 'approved',
    ];
    if ($userId > 0) {
        $conn = $GLOBALS['conn'] ?? null;
        if ($conn instanceof mysqli && table_exists($conn, 'users')) {
            $stmt = $conn->prepare("SELECT u.full_name,u.username,u.email,u.contact_number,u.position_title,u.avatar_path,u.profile_status,r.role_name FROM users u LEFT JOIN roles r ON r.role_id=u.role_id WHERE u.user_id=? LIMIT 1");
            if ($stmt) {
                $stmt->bind_param('i', $userId);
                $stmt->execute();
                $row = $stmt->get_result()->fetch_assoc() ?: [];
                $stmt->close();
                if ($row) {
                    $base['name'] = $row['full_name'] ?? $base['name'];
                    $base['username'] = $row['username'] ?? $base['username'];
                    $base['email'] = $row['email'] ?? '';
                    $base['contact_number'] = $row['contact_number'] ?? '';
                    $base['position_title'] = $row['position_title'] ?? '';
                    $base['avatar_path'] = $row['avatar_path'] ?? null;
                    $base['profile_status'] = $row['profile_status'] ?? 'approved';
                    $base['role'] = role_code_from_name((string)($row['role_name'] ?? $base['role']));
                }
            }
        }
    }
    return $cached = $base;
}

function role_label(string $role): string {
    return match ($role) {
        'task_force', 'special_program', 'staff', 'records', 'department_head', 'reviewer', 'approver', 'executive', 'auditor' => 'Task Force',
        'beneficiaries', 'beneficiary_staff' => 'Beneficiaries',
        'cbms', 'cbms_staff' => 'CBMS',
        'mayor' => 'Mayor',
        'developer', 'admin' => 'Developer',
        default => ucwords(str_replace('_', ' ', $role)),
    };
}

function role_code_from_name(string $roleName): string {
    $role = strtolower(trim($roleName));
    return match ($role) {
        'task_force', 'special_program', 'staff', 'records_officer', 'records', 'department_head', 'reviewer', 'approver', 'executive', 'auditor', 'special program' => 'task_force',
        'beneficiaries', 'beneficiary_staff', 'beneficiary staff' => 'beneficiaries',
        'cbms', 'cbms_staff', 'cbms staff' => 'cbms',
        'mayor' => 'mayor',
        'developer', 'admin' => 'developer',
        default => $role ?: 'task_force',
    };
}

function format_status_badge($status): string {
    $status = $status === null ? 'For Validation' : (string)$status;
    $s = strtolower(trim($status));
    $map = [
        'good' => 'emerald', 'active' => 'emerald', 'completed' => 'emerald', 'qualified' => 'emerald', 'highly qualified' => 'emerald', 'present' => 'emerald',
        'bad' => 'rose', 'not qualified' => 'rose', 'cancelled' => 'rose',
        'needs rehab' => 'amber', 'needs support' => 'amber', 'high risk' => 'red', 'for validation' => 'sky', 'scheduled' => 'sky', 'ongoing' => 'sky', 'late' => 'amber',
        'fruiting' => 'emerald', 'not fruiting' => 'slate', 'unknown' => 'slate', 'draft' => 'slate', 'archived' => 'slate', 'excused' => 'slate', 'needs revision' => 'amber', 'pending' => 'amber', 'approved' => 'emerald', 'rejected' => 'rose',
    ];
    $color = $map[$s] ?? 'slate';
    return '<span class="app-badge app-badge-' . $color . '">' . e($status) . '</span>';
}

function nav_cards(array $cards): string {
    $html = '<div class="app-kpi-grid">';
    foreach ($cards as $card) {
        $tag = !empty($card['href']) ? 'a' : 'div';
        $attrs = !empty($card['href'])
            ? ' href="' . e($card['href']) . '" class="app-kpi-card block hover:-translate-y-0.5 transition"'
            : ' class="app-kpi-card"';
        $html .= '<' . $tag . $attrs . '>';
        $html .= '<div class="app-kpi-label">' . e($card['label']) . '</div>';
        $html .= '<div class="app-kpi-value">' . e((string)$card['value']) . '</div>';
        if (!empty($card['hint'])) $html .= '<div class="app-kpi-hint">' . e($card['hint']) . '</div>';
        if (!empty($card['cta'])) $html .= '<div class="mt-3 text-xs font-semibold text-emerald-700">' . e($card['cta']) . '</div>';
        $html .= '</' . $tag . '>';
    }
    return $html . '</div>';
}


function app_dashboard_insights_data(mysqli $conn): array {
    $totalHouseholds = function_exists('total_household_groups') ? (int) total_household_groups($conn) : (int) safe_table_count($conn, 'households');
    $totalFamilies = function_exists('total_family_units') ? (int) total_family_units($conn) : $totalHouseholds;
    $totalMembers = (int) safe_table_count($conn, 'family_members', 'is_active = 1');
    $totalEvents = (int) safe_table_count($conn, 'events');
    $totalAttendance = (int) safe_table_count($conn, 'event_attendance', "attendance_status IN ('Present','Late')");
    $totalAssistance = (int) safe_table_count($conn, 'assistance_records');
    $totalDocuments = (int) safe_table_count($conn, 'household_documents');
    $programRows = table_exists($conn, 'household_special_programs')
        ? fetch_all_assoc($conn, "SELECT application_status, COUNT(*) total FROM household_special_programs GROUP BY application_status ORDER BY total DESC")
        : [];
    $programLabels = [];
    $programData = [];
    foreach ($programRows as $row) {
        $programLabels[] = $row['application_status'] ?: 'Unspecified';
        $programData[] = (int) ($row['total'] ?? 0);
    }

    $situationMap = [
        'Pending First Validation' => (int) safe_table_count($conn, 'household_special_programs', "application_status='Pending First Validation'"),
        'Pending Orientation' => (int) safe_table_count($conn, 'household_special_programs', "application_status='Pending Orientation'"),
        'Pending Final Validation' => (int) safe_table_count($conn, 'household_special_programs', "application_status='Pending Final Validation'"),
        'Pending Seminar' => (int) safe_table_count($conn, 'household_special_programs', "application_status='Pending Seminar'"),
        'Pending Release' => (int) safe_table_count($conn, 'household_special_programs', "application_status='Pending Release'"),
        'Active' => (int) safe_table_count($conn, 'household_special_programs', "application_status='Active'"),
        'Completed' => (int) safe_table_count($conn, 'household_special_programs', "application_status='Completed'"),
        'Open Violations' => (int) safe_table_count($conn, 'household_violations', "violation_status='Open'"),
    ];
    if (table_exists($conn, 'household_qualification')) {
        $situationMap['Needs Support'] = (int) safe_table_count($conn, 'household_qualification', "qualification_status IN ('High Risk','Needs Support','For Validation')");
    }

    $coverageLabels = ['Households', 'Families', 'Members', 'Events', 'Attendance', 'Assistance', 'Documents'];
    $coverageData = [$totalHouseholds, $totalFamilies, $totalMembers, $totalEvents, $totalAttendance, $totalAssistance, $totalDocuments];

    $ruleRows = [];
    if (table_exists($conn, 'barangays') && table_exists($conn, 'households')) {
        $ruleRows = fetch_all_assoc($conn, "
            SELECT b.barangay_name,
                COUNT(DISTINCT h.household_id) AS total_households,
                COUNT(DISTINCT CASE WHEN sp.application_status IN ('Approved','Active','Completed') THEN h.household_id END) AS program_ready,
                COUNT(DISTINCT CASE WHEN hv.violation_status='Open' THEN h.household_id END) AS open_violations,
                COUNT(DISTINCT CASE WHEN ea.attendance_status IN ('Present','Late') THEN h.household_id END) AS attended_households
            FROM barangays b
            LEFT JOIN households h ON h.barangay_id=b.barangay_id AND COALESCE(h.record_status,'active') <> 'deleted'
            LEFT JOIN household_special_programs sp ON sp.household_id=h.household_id
            LEFT JOIN household_violations hv ON hv.household_id=h.household_id
            LEFT JOIN event_attendance ea ON ea.household_id=h.household_id
            GROUP BY b.barangay_id, b.barangay_name
            HAVING COUNT(DISTINCT h.household_id) > 0
            ORDER BY b.barangay_name ASC
            LIMIT 12
        ");
    }

    return [
        'summary' => [
            'households' => $totalHouseholds,
            'families' => $totalFamilies,
            'members' => $totalMembers,
            'events' => $totalEvents,
            'attendance' => $totalAttendance,
            'assistance' => $totalAssistance,
            'documents' => $totalDocuments,
        ],
        'program_labels' => $programLabels,
        'program_data' => $programData,
        'situation_labels' => array_keys($situationMap),
        'situation_data' => array_values($situationMap),
        'coverage_labels' => $coverageLabels,
        'coverage_data' => $coverageData,
        'rule_rows' => $ruleRows,
    ];
}

function app_dashboard_insights_panel(mysqli $conn, string $title = 'Municipal database snapshot', string $subtitle = 'Live charts from the actual database so users can quickly see rules, queues, progress, and the current situation.'): string {
    static $chartAssetsIncluded = false;
    $data = app_dashboard_insights_data($conn);
    $uid = 'dash_' . substr(md5(uniqid('', true)), 0, 10);
    $ruleLabels = [];
    $ruleProgramReady = [];
    $ruleViolations = [];
    $ruleAttendance = [];
    foreach (($data['rule_rows'] ?? []) as $row) {
        $ruleLabels[] = $row['barangay_name'] ?? 'Unknown';
        $ruleProgramReady[] = (int) ($row['program_ready'] ?? 0);
        $ruleViolations[] = (int) ($row['open_violations'] ?? 0);
        $ruleAttendance[] = (int) ($row['attended_households'] ?? 0);
    }

    ob_start();
    ?>
    <section class="rounded-[2rem] border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-6 shadow-sm mt-6">
        <div class="flex items-start justify-between gap-4 flex-wrap">
            <div>
                <div class="text-sm text-slate-500">Dashboard intelligence</div>
                <h2 class="text-2xl font-black"><?= e($title) ?></h2>
                <p class="mt-2 text-sm text-slate-500 max-w-4xl"><?= e($subtitle) ?></p>
            </div>
            <div class="flex flex-wrap gap-2 text-xs text-slate-500">
                <span class="rounded-full border border-slate-200 dark:border-slate-800 px-3 py-1">Rules view</span>
                <span class="rounded-full border border-slate-200 dark:border-slate-800 px-3 py-1">Program queues</span>
                <span class="rounded-full border border-slate-200 dark:border-slate-800 px-3 py-1">Database situation</span>
            </div>
        </div>
        <div class="grid gap-6 xl:grid-cols-3 mt-6">
            <div class="rounded-[1.5rem] border border-slate-200 dark:border-slate-800 p-4">
                <div class="text-sm text-slate-500">Database summary</div>
                <div class="mt-3 h-[260px]"><canvas id="<?= e($uid) ?>_coverage"></canvas></div>
            </div>
            <div class="rounded-[1.5rem] border border-slate-200 dark:border-slate-800 p-4">
                <div class="text-sm text-slate-500">Program status flow</div>
                <div class="mt-3 h-[260px]"><canvas id="<?= e($uid) ?>_programs"></canvas></div>
            </div>
            <div class="rounded-[1.5rem] border border-slate-200 dark:border-slate-800 p-4">
                <div class="text-sm text-slate-500">Rules and current situation</div>
                <div class="mt-3 h-[260px]"><canvas id="<?= e($uid) ?>_situation"></canvas></div>
            </div>
        </div>
        <?php if ($ruleLabels): ?>
        <div class="rounded-[1.5rem] border border-slate-200 dark:border-slate-800 p-4 mt-6">
            <div class="text-sm text-slate-500">Barangay readiness and compliance</div>
            <div class="mt-3 h-[320px]"><canvas id="<?= e($uid) ?>_barangay"></canvas></div>
        </div>
        <?php endif; ?>
    </section>
    <?php if (!$chartAssetsIncluded): $chartAssetsIncluded = true; ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <?php endif; ?>
    <script>
    (function(){
        if (typeof Chart === 'undefined') return;
        const textColor = document.documentElement.classList.contains('dark') ? '#e5e7eb' : '#0f172a';
        const gridColor = document.documentElement.classList.contains('dark') ? 'rgba(148,163,184,0.18)' : 'rgba(15,23,42,0.08)';
        const legendOpts = { labels: { color: textColor } };
        new Chart(document.getElementById('<?= e($uid) ?>_coverage'), {
            type: 'bar',
            data: { labels: <?= json_encode($data['coverage_labels']) ?>, datasets: [{ label: 'Records', data: <?= json_encode($data['coverage_data']) ?> }] },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { color: textColor }, grid: { color: gridColor } }, x: { ticks: { color: textColor }, grid: { display: false } } } }
        });
        new Chart(document.getElementById('<?= e($uid) ?>_programs'), {
            type: 'doughnut',
            data: { labels: <?= json_encode($data['program_labels']) ?>, datasets: [{ data: <?= json_encode($data['program_data']) ?> }] },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: Object.assign({ position: 'bottom' }, legendOpts) } }
        });
        new Chart(document.getElementById('<?= e($uid) ?>_situation'), {
            type: 'bar',
            data: { labels: <?= json_encode($data['situation_labels']) ?>, datasets: [{ label: 'Current situation', data: <?= json_encode($data['situation_data']) ?> }] },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { color: textColor }, grid: { color: gridColor } }, x: { ticks: { color: textColor }, grid: { display: false } } } }
        });
        <?php if ($ruleLabels): ?>
        new Chart(document.getElementById('<?= e($uid) ?>_barangay'), {
            type: 'bar',
            data: {
                labels: <?= json_encode($ruleLabels) ?>,
                datasets: [
                    { label: 'Program-ready households', data: <?= json_encode($ruleProgramReady) ?> },
                    { label: 'Open violations', data: <?= json_encode($ruleViolations) ?> },
                    { label: 'Attended households', data: <?= json_encode($ruleAttendance) ?> }
                ]
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: Object.assign({ position: 'bottom' }, legendOpts) }, scales: { y: { beginAtZero: true, ticks: { color: textColor }, grid: { color: gridColor } }, x: { ticks: { color: textColor }, grid: { display: false } } } }
        });
        <?php endif; ?>
    })();
    </script>
    <?php
    return (string) ob_get_clean();
}

function automation_tip(string $title, string $body): string {
    return '';
}

function app_setting(mysqli $conn, string $key, string $default = ''): string {
    if (!table_exists($conn, 'system_settings')) return $default;
    $stmt = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key=? LIMIT 1");
    if (!$stmt) return $default;
    $stmt->bind_param('s', $key);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res instanceof mysqli_result ? $res->fetch_assoc() : null;
    $stmt->close();
    return $row['setting_value'] ?? $default;
}

function setting_enabled(mysqli $conn, string $key, bool $default = false): bool {
    $raw = strtolower(trim(app_setting($conn, $key, $default ? '1' : '0')));
    return in_array($raw, ['1', 'true', 'yes', 'on', 'enabled'], true);
}

function current_user_is_developer(): bool {
    $user = current_user();
    return (($user['role'] ?? '') === 'developer');
}

function apply_runtime_db_fixes(mysqli $conn): void {
    static $done = false;
    if ($done) return;
    $done = true;
    foreach (['trg_events_after_insert','trg_households_after_insert','trg_crops_after_insert'] as $trigger) {
        @$conn->query("DROP TRIGGER IF EXISTS `{$trigger}`");
    }
}


function uploaded_asset_url(?string $path, array $preferredDirs = [], ?string $placeholderUrl = null): string {
    $path = trim((string)$path);
    if ($path === '') return $placeholderUrl ?? app_url('assets/img/image.jpg');
    if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://') || str_starts_with($path, 'data:')) return $path;

    $normalized = urldecode(str_replace('\\', '/', $path));
    $normalized = preg_replace('#^[A-Za-z]:/#', '', $normalized);
    $normalized = ltrim($normalized, '/');
    $normalized = preg_replace('#^harvest/#', '', $normalized);
    if (str_contains($normalized, '/public/')) {
        $normalized = substr($normalized, strpos($normalized, '/public/') + 8);
    }
    if (str_starts_with($normalized, 'public/')) {
        $normalized = substr($normalized, 7);
    }

    $basename = basename($normalized);
    $candidates = [];
    $push = static function(string $candidate) use (&$candidates): void {
        $candidate = trim(str_replace('\\', '/', $candidate), '/');
        if ($candidate !== '' && !in_array($candidate, $candidates, true)) {
            $candidates[] = $candidate;
        }
    };

    $push($normalized);
    $push('public/' . $normalized);
    foreach ($preferredDirs as $dir) {
        $dir = trim(str_replace('\\', '/', (string)$dir), '/');
        if ($dir === '') continue;
        $push($dir . '/' . $basename);
        $push('public/' . $dir . '/' . $basename);
        $dirPath = app_path($dir);
        $publicDirPath = app_path('public/' . $dir);
        foreach ([$dirPath, $publicDirPath] as $scanDir) {
            if (!is_dir($scanDir)) continue;
            $matches = glob(rtrim($scanDir, '/') . '/*' . $basename);
            if (is_array($matches)) {
                foreach ($matches as $match) {
                    $normalizedMatch = trim(str_replace('\\', '/', str_replace(app_path(''), '', $match)), '/');
                    if ($normalizedMatch !== '') $push($normalizedMatch);
                }
            }
        }
    }

    foreach ($candidates as $candidate) {
        $candidatePath = str_starts_with($candidate, 'public/') ? $candidate : $candidate;
        if (is_file(app_path($candidatePath))) {
            $urlPath = str_starts_with($candidate, 'public/') ? substr($candidate, 7) : $candidate;
            return app_url($urlPath);
        }
    }

    if ($normalized !== '') {
        return app_url($normalized);
    }

    return $placeholderUrl ?? app_url('assets/img/image.jpg');
}

function family_submission_placeholder_url(): string {
    return 'data:image/svg+xml;utf8,' . rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" width="640" height="480" viewBox="0 0 640 480"><rect width="640" height="480" rx="28" fill="#f5f5f0"/><path d="M0 372l138-126 110 92 82-74 120 108H0z" fill="#dce8d6"/><circle cx="188" cy="154" r="42" fill="#eadfbb"/><rect x="38" y="40" width="564" height="400" rx="24" fill="none" stroke="#b8c6b2" stroke-width="10" stroke-dasharray="18 18"/><text x="320" y="410" text-anchor="middle" font-family="Arial, sans-serif" font-size="28" fill="#5b6a55">No family photo found</text></svg>');
}

function user_avatar_url(?string $path = null): string {
    return uploaded_asset_url($path, ['uploads/profile_pictures', 'uploads/households', 'uploads/family_members']);
}

function family_submission_photo_url(?string $path = null): string {
    $path = trim((string)$path);
    if ($path === '') return family_submission_placeholder_url();
    if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://') || str_starts_with($path, 'data:')) return $path;

    $normalized = urldecode(str_replace('\\', '/', $path));
    $normalized = preg_replace('#^[A-Za-z]:/#', '', $normalized);
    $normalized = ltrim($normalized, '/');
    $normalized = preg_replace('#^harvest/#', '', $normalized);
    if (str_contains($normalized, '/public/')) {
        $normalized = substr($normalized, strpos($normalized, '/public/') + 8);
    }
    if (str_starts_with($normalized, 'public/')) {
        $normalized = substr($normalized, 7);
    }

    $basename = basename($normalized);
    $candidates = [];
    $push = static function(string $candidate) use (&$candidates): void {
        $candidate = trim(str_replace('\\', '/', $candidate), '/');
        if ($candidate !== '' && !in_array($candidate, $candidates, true)) {
            $candidates[] = $candidate;
        }
    };

    if (str_starts_with($normalized, 'uploads/family_portal/') || str_starts_with($normalized, 'uploads/family_updates/') || str_starts_with($normalized, 'uploads/submissions/')) {
        $push($normalized);
        $push('public/' . $normalized);
    } else {
        foreach (['uploads/family_portal', 'uploads/family_updates', 'uploads/submissions'] as $dir) {
            $push($dir . '/' . $basename);
            $push('public/' . $dir . '/' . $basename);
        }
    }

    foreach ($candidates as $candidate) {
        if (is_file(app_path($candidate))) {
            $urlPath = str_starts_with($candidate, 'public/') ? substr($candidate, 7) : $candidate;
            return app_url($urlPath);
        }
    }

    return family_submission_placeholder_url();
}

function unread_notification_count(mysqli $conn): int {
    return table_exists($conn, 'notifications') ? (int)scalar($conn, "SELECT COUNT(*) FROM notifications WHERE COALESCE(is_read, 0) = 0", 0) : 0;
}

function pending_family_updates_count(mysqli $conn): int {
    if (!table_exists($conn, 'family_portal_updates')) return 0;
    return (int)scalar($conn, "SELECT COUNT(*) FROM family_portal_updates WHERE LOWER(TRIM(COALESCE(reviewed_status, 'Pending'))) = 'pending'", 0);
}

function pending_profile_security_count(mysqli $conn): int {
    return pending_password_reset_count($conn) + pending_signup_request_count($conn);
}

function navigation_attention_counts(mysqli $conn, string $role = ''): array {
    $notifications = unread_notification_count($conn);
    $familyUpdates = in_array($role, ['task_force', 'mayor', 'developer', 'admin'], true) ? pending_family_updates_count($conn) : 0;
    $profileApprovals = in_array($role, ['developer', 'admin'], true) ? pending_profile_request_count($conn) : 0;
    $security = in_array($role, ['developer', 'admin'], true) ? pending_profile_security_count($conn) : 0;
    $pendingOrientation = function_exists('golden_orientation_queue_count') && in_array($role, ['task_force', 'admin', 'mayor'], true) ? golden_orientation_queue_count($conn) : 0;
    $pendingValidation = function_exists('golden_validation_queue_count') && in_array($role, ['task_force', 'admin', 'mayor'], true) ? golden_validation_queue_count($conn) : 0;
    $openViolations = function_exists('golden_open_violation_count') && in_array($role, ['task_force', 'admin', 'mayor'], true) ? golden_open_violation_count($conn) : 0;
    return [
        'notifications' => $notifications,
        'family_updates' => $familyUpdates,
        'profile_approvals' => $profileApprovals,
        'security' => $security,
        'pending_orientation' => $pendingOrientation,
        'pending_validation' => $pendingValidation,
        'open_violations' => $openViolations,
        'account' => $profileApprovals + $security,
        'tools' => $notifications + $familyUpdates + $pendingOrientation + $pendingValidation + $openViolations,
        'governance' => $profileApprovals + $security,
    ];
}

function attention_hint_text(int $count, string $noun, string $emptyText, string $suffix): string {
    return $count > 0 ? $count . ' ' . $noun . ($count === 1 ? '' : 's') . ' ' . $suffix : $emptyText;
}

function navigation_attention_details(mysqli $conn, string $role = ''): array {
    $counts = navigation_attention_counts($conn, $role);
    return [
        '/harvest/modules/agri/notifications/index.php' => [
            'label' => 'Notifications',
            'count' => (int)($counts['notifications'] ?? 0),
            'hint' => attention_hint_text((int)($counts['notifications'] ?? 0), 'unread notification', 'No unread notifications', 'need checking'),
            'empty_hint' => 'No unread notifications',
        ],
        '/harvest/modules/agri/family_updates/index.php' => [
            'label' => 'Family Updates',
            'count' => (int)($counts['family_updates'] ?? 0),
            'hint' => attention_hint_text((int)($counts['family_updates'] ?? 0), 'family update', 'No pending family updates', 'waiting for review'),
            'empty_hint' => 'No pending family updates',
        ],
        '/harvest/modules/agri/programs/index.php' => [
            'label' => 'Program Requests',
            'count' => (int)(($counts['pending_orientation'] ?? 0) + ($counts['pending_validation'] ?? 0)),
            'hint' => ((int)(($counts['pending_orientation'] ?? 0) + ($counts['pending_validation'] ?? 0)) > 0) ? ('Needs action · orientation ' . (int)($counts['pending_orientation'] ?? 0) . ', validation ' . (int)($counts['pending_validation'] ?? 0)) : 'No pending program workflow items',
            'empty_hint' => 'No pending program workflow items',
        ],
        '/harvest/modules/agri/validation/index.php' => [
            'label' => 'Households for Visit',
            'count' => (int)($counts['pending_validation'] ?? 0),
            'hint' => attention_hint_text((int)($counts['pending_validation'] ?? 0), 'household', 'No households waiting for field validation', 'ready for validation'),
            'empty_hint' => 'No households waiting for field validation',
        ],
        '/harvest/modules/agri/events/index.php' => [
            'label' => 'Events',
            'count' => (int)($counts['pending_orientation'] ?? 0),
            'hint' => attention_hint_text((int)($counts['pending_orientation'] ?? 0), 'household', 'No households waiting for orientation', 'waiting for orientation'),
            'empty_hint' => 'No households waiting for orientation',
        ],
        '/harvest/modules/agri/compliance/index.php' => [
            'label' => 'Rules & Violations',
            'count' => (int)($counts['open_violations'] ?? 0),
            'hint' => attention_hint_text((int)($counts['open_violations'] ?? 0), 'open violation', 'No open violations', 'need action'),
            'empty_hint' => 'No open violations',
        ],
        '/harvest/modules/admin/profile_requests/index.php' => [
            'label' => 'Profile Approvals',
            'count' => (int)($counts['profile_approvals'] ?? 0),
            'hint' => attention_hint_text((int)($counts['profile_approvals'] ?? 0), 'profile request', 'No pending profile approvals', 'waiting for approval'),
            'empty_hint' => 'No pending profile approvals',
        ],
        '/harvest/modules/admin/security/index.php' => [
            'label' => 'Security',
            'count' => (int)($counts['security'] ?? 0),
            'hint' => attention_hint_text((int)($counts['security'] ?? 0), 'security item', 'No pending security items', 'need attention'),
            'empty_hint' => 'No pending security items',
        ],
    ];
}

function navigation_item_attention(array $attentionDetails, string $href): array {
    return $attentionDetails[$href] ?? ['label' => '', 'count' => 0, 'hint' => 'Open menu'];
}

function navigation_group_attention(array $groupItems, array $attentionDetails, string $groupLabel = 'menu'): array {
    $total = 0;
    $parts = [];
    foreach ($groupItems as $item) {
        $href = (string)($item['href'] ?? '');
        if ($href === '') continue;
        $detail = navigation_item_attention($attentionDetails, $href);
        $count = (int)($detail['count'] ?? 0);
        if ($count <= 0) continue;
        $label = (string)($detail['label'] ?: ($item['label'] ?? 'Item'));
        $parts[] = $label . ' (' . $count . ')';
        $total += $count;
    }
    if ($total > 0) {
        return [
            'count' => $total,
            'hint' => ucfirst((string)$groupLabel) . ': ' . implode(', ', $parts) . '. Counts clear automatically when the action is completed.',
        ];
    }
    return [
        'count' => 0,
        'hint' => 'Open ' . strtolower((string)$groupLabel) . ' menu',
    ];
}

function upload_user_avatar(array $file): ?string {
    return upload_image_file($file, 'public/uploads/profile_pictures');
}

function calculate_age_from_birthdate(?string $birthdate): ?int {
    $birthdate = trim((string)$birthdate);
    if ($birthdate === '') return null;
    try {
        $dob = new DateTime($birthdate);
        $today = new DateTime('today');
        if ($dob > $today) return null;
        return $dob->diff($today)->y;
    } catch (Throwable $e) {
        return null;
    }
}

function upload_image_file(array $file, string $dir = 'public/uploads/family_members'): ?string {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || empty($file['tmp_name'])) return null;
    $allowed = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']) ?: '';
    if (!isset($allowed[$mime])) return null;
    if (($file['size'] ?? 0) > 5 * 1024 * 1024) return null;
    $targetDir = app_path($dir);
    @mkdir($targetDir, 0777, true);
    $name = date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.' . $allowed[$mime];
    $target = $targetDir . '/' . $name;
    if (!move_uploaded_file($file['tmp_name'], $target)) return null;
    return str_replace('public/', '', trim($dir, '/')) . '/' . $name;
}

function split_person_name(string $fullName): array {
    $fullName = trim(preg_replace('/\s+/', ' ', $fullName));
    if ($fullName === '') return [null, null, null, null];
    $parts = explode(' ', $fullName);
    $first = array_shift($parts);
    $last = count($parts) ? array_pop($parts) : null;
    $middle = count($parts) ? implode(' ', $parts) : null;
    return [$first, $middle, $last, null];
}

function build_qr_data_uri(string $value, int $size = 220): string {
    $value = trim($value);
    if ($value === '') return '';
    $url = 'https://api.qrserver.com/v1/create-qr-code/?size=' . max(80, min(600, $size)) . 'x' . max(80, min(600, $size)) . '&data=' . rawurlencode($value);
    return $url;
}

function member_photo_url(?string $path): string {
    if (!$path) return app_url('assets/img/image.jpg');
    return app_url('public/' . ltrim($path, '/'));
}

function upload_document_file(array $file): ?string {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || empty($file['tmp_name'])) return null;
    if (($file['size'] ?? 0) > 10 * 1024 * 1024) return null;
    $targetDir = app_path('public/uploads/documents');
    @mkdir($targetDir, 0777, true);
    $ext = strtolower(pathinfo($file['name'] ?? 'file.bin', PATHINFO_EXTENSION) ?: 'bin');
    $name = date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.' . preg_replace('/[^a-z0-9]+/i', '', $ext);
    $target = $targetDir . '/' . $name;
    if (!move_uploaded_file($file['tmp_name'], $target)) return null;
    return 'uploads/documents/' . $name;
}

