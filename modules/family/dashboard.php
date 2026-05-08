<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
$conn = db_conn();
app_require('app/includes/session.php');
require_once app_path('app/config/database.php');
app_require('modules/family/portal_helpers.php');
ensure_family_portal_schema($conn);
require_family_dashboard_enabled($conn);
$family = require_family_portal_login($conn);
$householdId = (int)$family['household_id'];
$eligibleCrops = family_portal_crop_options($conn, $householdId);
$eligibleCropIds = array_map(static fn($row) => (int)$row['crop_id'], $eligibleCrops);
$typeOptions = family_dashboard_submission_options($conn, $householdId);
$submissionEnabled = family_submission_enabled($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'submit_update') {
    $title = trim((string)post('title'));
    $notes = trim((string)post('notes'));
    $type = trim((string)post('update_type')) ?: 'Field Photo';
    if (!isset($typeOptions[$type])) {
        $type = array_key_first($typeOptions) ?: 'Field Photo';
    }
    $cropId = (int)post('crop_id');
    $activityDate = trim((string)post('activity_date'));
    $quantityValueRaw = trim((string)post('quantity_value'));
    $quantityUnit = trim((string)post('quantity_unit')) ?: 'kg';
    $photo = !empty($_FILES['photo']['name']) ? upload_family_portal_photo($_FILES['photo']) : null;

    $errors = [];
    if (!$submissionEnabled) {
        $errors[] = 'Family submissions are currently turned off by the developer.';
    }
    if (family_update_requires_crop($type)) {
        if (!$eligibleCrops) {
            $errors[] = 'No registered active crop was found for this family. Crop and harvest updates are only allowed for your own registered crops.';
        } elseif (!in_array($cropId, $eligibleCropIds, true)) {
            $errors[] = 'Please choose one of your registered crops for this update.';
        }
    } else {
        $cropId = 0;
    }

    $activityDateSql = null;
    if ($activityDate !== '') {
        $dt = date_create($activityDate);
        if (!$dt) {
            $errors[] = 'Please provide a valid activity date.';
        } else {
            $activityDateSql = $dt->format('Y-m-d');
        }
    }

    $quantityValue = null;
    if ($quantityValueRaw !== '') {
        if (!is_numeric($quantityValueRaw) || (float)$quantityValueRaw < 0) {
            $errors[] = 'Quantity must be a valid positive number.';
        } else {
            $quantityValue = (float)$quantityValueRaw;
        }
    }

    if ($title === '' && $notes === '') {
        $errors[] = 'Please add a title or some notes for your update.';
    }
    if (in_array($type, ['Harvest Update', 'Field Photo'], true) && !$photo) {
        $errors[] = 'Please attach a photo for harvest updates and field photos.';
    }

    if (!$errors) {
        $stmt = $conn->prepare("INSERT INTO family_portal_updates (household_id, crop_id, update_type, title, notes, activity_date, quantity_value, quantity_unit, photo_path, submitted_at, reviewed_status, points_awarded) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 'Pending', 0)");
        if ($stmt) {
            $cropValue = $cropId > 0 ? $cropId : null;
            $quantityDb = $quantityValue;
            $stmt->bind_param('iissssdss', $householdId, $cropValue, $type, $title, $notes, $activityDateSql, $quantityDb, $quantityUnit, $photo);
            $stmt->execute();
            $stmt->close();
            set_flash('success', 'Family update submitted successfully. Wait for staff review to earn points toward qualification.');
        } else {
            set_flash('error', 'Unable to save your update right now.');
        }
    } else {
        set_flash('error', implode(' ', $errors));
    }
    header('Location: ' . app_url('modules/family/dashboard.php'));
    exit;
}

$flash = get_flash();
$members = fetch_all_assoc($conn, "SELECT full_name, relationship_to_head, occupation, age FROM family_members WHERE household_id=" . $householdId . " ORDER BY member_id ASC");
$crops = fetch_all_assoc($conn, "SELECT crop_id, crop_name, variety, tree_count, fruiting_status, current_condition, crop_status, planted_date, expected_fruiting_date, plot_name, area_sqm, remarks FROM crops WHERE household_id=" . $householdId . " ORDER BY crop_id DESC");
$monitoring = fetch_all_assoc($conn, "SELECT monitoring_date, harvest_kg, fruiting_status, crop_condition, notes FROM monitoring_visits WHERE household_id=" . $householdId . " ORDER BY monitoring_date DESC, monitoring_id DESC LIMIT 6");
$attendance = fetch_all_assoc($conn, "SELECT e.event_name, e.event_date, a.attendance_status, a.method FROM event_attendance a JOIN events e ON e.event_id=a.event_id WHERE a.household_id=" . $householdId . " ORDER BY e.event_date DESC LIMIT 6");
$familyUpdates = fetch_all_assoc($conn, "SELECT fu.*, c.crop_name, c.variety FROM family_portal_updates fu LEFT JOIN crops c ON c.crop_id=fu.crop_id WHERE fu.household_id=" . $householdId . " ORDER BY fu.submitted_at DESC LIMIT 8");
$familyNotifications = family_portal_notifications($conn, $householdId, 8);
$familyPointsTotal = household_family_points_total($conn, $householdId);
$progress = family_qualification_progress($conn, $householdId);
$pointsHistory = family_points_history($conn, $householdId, 8);
$timeline = family_recent_timeline($conn, $householdId, 8);
$pointsBreakdown = family_points_breakdown($conn, $householdId);
$logoUrl = system_logo_url($conn);
$appName = system_title($conn);
$approvedContributions = (int)scalar($conn, "SELECT COUNT(*) FROM family_portal_updates WHERE household_id={$householdId} AND reviewed_status='Approved'", 0);
?>
<!DOCTYPE html>
<html lang="en" class="h-full antialiased">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Family Dashboard - <?= e($appName) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="<?= e(app_url('assets/css/app.css')) ?>">
</head>
<body class="min-h-screen bg-slate-50 text-slate-800">
<header class="border-b border-slate-200 bg-white">
    <div class="mx-auto max-w-7xl px-4 py-4 sm:px-6 lg:px-8 flex items-center justify-between gap-4 flex-wrap">
        <div class="flex items-center gap-4 min-w-0">
            <img src="<?= e($logoUrl) ?>" alt="Logo" class="h-14 w-14 rounded-2xl object-cover" onerror="this.onerror=null;this.src='<?= e(app_url('assets/img/image.jpg')) ?>';">
            <div>
                <div class="text-2xl font-black text-emerald-950"><?= e($appName) ?></div>
                <div class="text-sm text-slate-500">Family portal</div>
            </div>
        </div>
        <div class="flex gap-2 flex-wrap">
            <a href="<?= e(app_url('modules/family/crops.php')) ?>" class="app-btn-outline">My crops</a>
            <a href="<?= e(app_url('modules/family/timeline.php')) ?>" class="app-btn-outline">Timeline</a>
            <a href="<?= e(app_url('modules/family/logout.php')) ?>" class="app-btn-outline">Log out</a>
        </div>
    </div>
</header>

<div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8 space-y-6">
    <?php if ($flash): ?><div class="rounded-2xl border <?= $flash['type']==='success'?'border-emerald-200 bg-emerald-50 text-emerald-900':'border-rose-200 bg-rose-50 text-rose-900' ?> px-4 py-3"><?= e($flash['message']) ?></div><?php endif; ?>

    <section class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex items-center justify-between gap-4 flex-wrap">
            <div class="flex items-center gap-4">
                <img src="<?= e(user_avatar_url($family['profile_photo_path'] ?? null)) ?>" alt="Family" class="h-20 w-20 rounded-[1.5rem] object-cover border border-slate-200">
                <div>
                    <div class="text-sm text-slate-500">Family dashboard</div>
                    <h1 class="text-4xl font-black text-slate-900"><?= e($family['household_head_name']) ?></h1>
                    <div class="mt-1 text-sm text-slate-500"><?= e($family['barangay_name'] ?? '') ?><?= !empty($family['household_code']) ? ' · ' . e($family['household_code']) : '' ?></div>
                </div>
            </div>
            <div class="grid gap-2 sm:grid-cols-3">
                <div class="rounded-2xl border border-slate-200 px-4 py-3"><div class="text-xs uppercase tracking-wide text-slate-500">Qualification status</div><div class="text-xl font-black text-emerald-800"><?= e($progress['qualification_status']) ?></div></div>
                <div class="rounded-2xl border border-slate-200 px-4 py-3"><div class="text-xs uppercase tracking-wide text-slate-500">Qualification score</div><div class="text-xl font-black text-slate-900"><?= e(rtrim(rtrim(number_format((float)$progress['total_score'], 2, '.', ''), '0'), '.')) ?></div></div>
                <div class="rounded-2xl border border-slate-200 px-4 py-3"><div class="text-xs uppercase tracking-wide text-slate-500">Contribution points</div><div class="text-xl font-black text-amber-700"><?= e(rtrim(rtrim(number_format($familyPointsTotal, 2, '.', ''), '0'), '.')) ?></div></div>
            </div>
        </div>
        <div class="mt-6 rounded-[1.5rem] border border-emerald-100 bg-emerald-50 p-5">
            <div class="flex items-center justify-between gap-4 flex-wrap">
                <div>
                    <div class="text-sm text-emerald-700">Qualification progress</div>
                    <div class="mt-1 text-2xl font-black text-emerald-950"><?= e($progress['remaining_points'] > 0 ? ('Need ' . rtrim(rtrim(number_format((float)$progress['remaining_points'], 2, '.', ''), '0'), '.') . ' more points') : 'Target reached') ?></div>
                    <div class="mt-1 text-sm text-emerald-700">Next target: <?= e(rtrim(rtrim(number_format((float)$progress['next_target'], 2, '.', ''), '0'), '.')) ?> total score</div>
                </div>
                <div class="min-w-[220px] flex-1 max-w-xl">
                    <div class="h-3 rounded-full bg-emerald-100 overflow-hidden"><div class="h-full rounded-full bg-emerald-600" style="width: <?= (int)$progress['progress_percent'] ?>%"></div></div>
                    <div class="mt-2 text-sm text-emerald-800"><?= (int)$progress['progress_percent'] ?>% of next target completed</div>
                </div>
            </div>
            <div class="mt-4 grid gap-2 md:grid-cols-3">
                <div class="rounded-xl bg-white/70 px-3 py-2 text-sm <?= $progress['requirements']['interview_completed'] ? 'text-emerald-800' : 'text-slate-600' ?>">Interview: <?= $progress['requirements']['interview_completed'] ? 'Completed' : 'Missing' ?></div>
                <div class="rounded-xl bg-white/70 px-3 py-2 text-sm <?= $progress['requirements']['monitoring_completed'] ? 'text-emerald-800' : 'text-slate-600' ?>">Monitoring: <?= $progress['requirements']['monitoring_completed'] ? 'Recorded' : 'Missing' ?></div>
                <div class="rounded-xl bg-white/70 px-3 py-2 text-sm <?= $progress['requirements']['approved_crop_or_harvest'] ? 'text-emerald-800' : 'text-slate-600' ?>">Approved crop or harvest: <?= $progress['requirements']['approved_crop_or_harvest'] ? 'Done' : 'Still needed' ?></div>
            </div>
            <?php if ($progress['missing_requirements']): ?>
                <div class="mt-4 rounded-xl bg-white px-4 py-3 text-sm text-slate-700"><strong>Missing requirements:</strong> <?= e(implode(' · ', $progress['missing_requirements'])) ?></div>
            <?php endif; ?>
        </div>
    </section>

    <section class="grid gap-4 md:grid-cols-5">
        <div class="rounded-[1.5rem] border border-slate-200 bg-white p-4 shadow-sm"><div class="text-sm text-slate-500">Family members</div><div class="mt-2 text-3xl font-black"><?= count($members) ?></div></div>
        <div class="rounded-[1.5rem] border border-slate-200 bg-white p-4 shadow-sm"><div class="text-sm text-slate-500">Registered crops</div><div class="mt-2 text-3xl font-black"><?= count($crops) ?></div></div>
        <div class="rounded-[1.5rem] border border-slate-200 bg-white p-4 shadow-sm"><div class="text-sm text-slate-500">Monitoring visits</div><div class="mt-2 text-3xl font-black"><?= count($monitoring) ?></div></div>
        <div class="rounded-[1.5rem] border border-slate-200 bg-white p-4 shadow-sm"><div class="text-sm text-slate-500">Attendance records</div><div class="mt-2 text-3xl font-black"><?= count($attendance) ?></div></div>
        <div class="rounded-[1.5rem] border border-slate-200 bg-white p-4 shadow-sm"><div class="text-sm text-slate-500">Approved contributions</div><div class="mt-2 text-3xl font-black"><?= $approvedContributions ?></div></div>
    </section>

    <div class="grid gap-6 xl:grid-cols-[1.08fr_0.92fr]">
        <section id="family-upload" class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
            <div class="text-sm text-slate-500">Family contribution</div>
            <h2 class="text-3xl font-black text-slate-900">Submit crop, harvest, or field updates</h2>
            <p class="mt-2 text-sm text-slate-500">Harvest and crop updates must match your own registered crops. Approved submissions add points to qualification.</p>
            <?php if (!$eligibleCrops): ?><div class="mt-4 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-amber-900">No active crop is registered for this family yet. You can still send field photos or family notes, but harvest and crop updates stay locked until a crop is registered.</div><?php endif; ?>
            <?php if (!$submissionEnabled): ?>
                <div class="mt-6 rounded-3xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                    The developer has temporarily turned off family submissions. You can still view your household dashboard and progress details.
                </div>
            <?php endif; ?>
            <form method="POST" enctype="multipart/form-data" class="mt-6 grid gap-4 md:grid-cols-2 <?= !$submissionEnabled ? 'opacity-60 pointer-events-none' : '' ?>">
                <input type="hidden" name="action" value="submit_update">
                <div>
                    <label class="block text-sm font-semibold mb-2">Update type</label>
                    <select id="update_type" name="update_type" class="w-full rounded-2xl border border-slate-300 px-4 py-3">
                        <?php foreach ($typeOptions as $value => $label): ?>
                            <option value="<?= e($value) ?>"><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold mb-2">Title</label>
                    <input type="text" name="title" class="w-full rounded-2xl border border-slate-300 px-4 py-3" placeholder="Harvest summary or crop condition">
                </div>
                <div id="crop_picker_wrap" class="md:col-span-2">
                    <label class="block text-sm font-semibold mb-2">Registered crop</label>
                    <select id="crop_id" name="crop_id" class="w-full rounded-2xl border border-slate-300 px-4 py-3">
                        <option value="">Select your crop</option>
                        <?php foreach ($eligibleCrops as $crop): ?>
                            <option value="<?= (int)$crop['crop_id'] ?>"><?= e($crop['crop_name']) ?><?= !empty($crop['variety']) ? ' · ' . e($crop['variety']) : '' ?><?= !empty($crop['plot_name']) ? ' · Plot ' . e($crop['plot_name']) : '' ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="mt-2 text-sm text-slate-500">Only your own registered crop can be selected for harvest and crop updates.</div>
                </div>
                <div>
                    <label class="block text-sm font-semibold mb-2">Activity date</label>
                    <input type="date" name="activity_date" class="w-full rounded-2xl border border-slate-300 px-4 py-3">
                </div>
                <div class="grid grid-cols-[1fr_140px] gap-3">
                    <div>
                        <label class="block text-sm font-semibold mb-2">Quantity</label>
                        <input type="number" step="0.01" min="0" name="quantity_value" class="w-full rounded-2xl border border-slate-300 px-4 py-3" placeholder="0.00">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold mb-2">Unit</label>
                        <input type="text" name="quantity_unit" value="kg" class="w-full rounded-2xl border border-slate-300 px-4 py-3">
                    </div>
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-semibold mb-2">Notes</label>
                    <textarea name="notes" rows="5" class="w-full rounded-2xl border border-slate-300 px-4 py-3" placeholder="Share your recent harvest, crop condition, or update for the task force."></textarea>
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-semibold mb-2">Photo</label>
                    <input type="file" name="photo" accept="image/*" class="w-full rounded-2xl border border-slate-300 px-4 py-3 bg-white">
                </div>
                <div class="md:col-span-2 flex justify-end">
                    <button class="app-btn-primary" <?= !$submissionEnabled ? 'disabled aria-disabled="true"' : '' ?>>Submit update</button>
                </div>
            </form>

            <div class="mt-8 grid gap-4 lg:grid-cols-2">
                <div>
                    <div class="text-sm text-slate-500">Recent family submissions</div>
                    <div class="mt-4 space-y-3">
                        <?php foreach ($familyUpdates as $item): ?>
                        <div class="rounded-2xl border border-slate-200 p-4">
                            <div class="flex items-start justify-between gap-4 flex-wrap">
                                <div class="max-w-2xl">
                                    <div class="font-bold text-slate-900"><?= e($item['title'] ?: $item['update_type']) ?></div>
                                    <div class="text-sm text-slate-500"><?= e(date('M d, Y h:i A', strtotime((string)$item['submitted_at']))) ?> · <?= e($item['reviewed_status']) ?><?php if (!empty($item['crop_name'])): ?> · <?= e($item['crop_name']) ?><?= !empty($item['variety']) ? ' · ' . e($item['variety']) : '' ?><?php endif; ?></div>
                                    <?php if (!empty($item['activity_date']) || !empty($item['quantity_value'])): ?>
                                        <div class="mt-1 text-sm text-slate-500">
                                            <?php if (!empty($item['activity_date'])): ?>Activity date <?= e(date('M d, Y', strtotime((string)$item['activity_date']))) ?><?php endif; ?>
                                            <?php if (!empty($item['quantity_value'])): ?><?= !empty($item['activity_date']) ? ' · ' : '' ?>Quantity <?= e(rtrim(rtrim(number_format((float)$item['quantity_value'], 2, '.', ''), '0'), '.')) ?> <?= e($item['quantity_unit'] ?: 'kg') ?><?php endif; ?>
                                            <?php if ((float)($item['points_awarded'] ?? 0) > 0): ?> · +<?= e(rtrim(rtrim(number_format((float)$item['points_awarded'], 2, '.', ''), '0'), '.')) ?> pts<?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($item['notes'])): ?><div class="mt-2 text-sm text-slate-600"><?= e($item['notes']) ?></div><?php endif; ?>
                                    <?php if (!empty($item['review_notes'])): ?><div class="mt-2 rounded-xl bg-slate-50 px-3 py-2 text-sm text-slate-600"><strong>Staff feedback:</strong> <?= e($item['review_notes']) ?></div><?php endif; ?>
                                </div>
                                <?php if (!empty($item['photo_path'])): ?><img src="<?= e(family_submission_photo_url($item['photo_path'])) ?>" alt="Update photo" class="h-20 w-20 rounded-2xl object-cover border border-slate-200" onerror="this.onerror=null;this.src='<?= e(family_submission_placeholder_url()) ?>';"><?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php if (!$familyUpdates): ?><div class="text-slate-500">No family updates submitted yet.</div><?php endif; ?>
                    </div>
                </div>
                <div>
                    <div class="text-sm text-slate-500">Points history</div>
                    <div class="mt-4 space-y-3">
                        <?php foreach ($pointsHistory as $log): ?>
                            <div class="rounded-2xl border border-slate-200 p-4">
                                <div class="flex items-center justify-between gap-3"><div class="font-bold text-slate-900"><?= e($log['remarks'] ?: ucfirst(str_replace('_', ' ', (string)$log['source_type']))) ?></div><div class="text-sm font-bold text-emerald-700">+<?= e(rtrim(rtrim(number_format((float)$log['points_awarded'], 2, '.', ''), '0'), '.')) ?></div></div>
                                <div class="mt-1 text-sm text-slate-500"><?= e(date('M d, Y h:i A', strtotime((string)$log['awarded_at']))) ?></div>
                            </div>
                        <?php endforeach; ?>
                        <?php if (!$pointsHistory): ?><div class="text-slate-500">No points awarded yet.</div><?php endif; ?>
                    </div>
                </div>
            </div>
        </section>

        <section class="space-y-6">
            <div class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
                <div class="text-sm text-slate-500">Members</div>
                <h2 class="text-2xl font-black text-slate-900">Family members</h2>
                <div class="mt-4 space-y-3">
                    <?php foreach ($members as $member): ?>
                    <div class="rounded-2xl border border-slate-200 p-4">
                        <div class="font-bold text-slate-900"><?= e($member['full_name']) ?></div>
                        <div class="text-sm text-slate-500"><?= e($member['relationship_to_head'] ?? '') ?><?= !empty($member['occupation']) ? ' · ' . e($member['occupation']) : '' ?><?= isset($member['age']) ? ' · Age ' . e((string)$member['age']) : '' ?></div>
                    </div>
                    <?php endforeach; ?>
                    <?php if (!$members): ?><div class="text-slate-500">No family members recorded yet.</div><?php endif; ?>
                </div>
            </div>

            <div class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex items-center justify-between gap-3 flex-wrap"><div><div class="text-sm text-slate-500">Crops</div><h2 class="text-2xl font-black text-slate-900">Registered crops</h2></div><a href="<?= e(app_url('modules/family/crops.php')) ?>" class="app-btn-outline">Open crop details</a></div>
                <div class="mt-4 space-y-3">
                    <?php foreach (array_slice($crops, 0, 4) as $crop): ?>
                    <div class="rounded-2xl border border-slate-200 p-4">
                        <div class="font-bold text-slate-900"><?= e($crop['crop_name']) ?><?= !empty($crop['variety']) ? ' · ' . e($crop['variety']) : '' ?></div>
                        <div class="text-sm text-slate-500"><?= e((string)$crop['tree_count']) ?> trees · <?= e($crop['fruiting_status']) ?> · <?= e($crop['current_condition']) ?><?= !empty($crop['plot_name']) ? ' · Plot ' . e($crop['plot_name']) : '' ?></div>
                    </div>
                    <?php endforeach; ?>
                    <?php if (!$crops): ?><div class="text-slate-500">No crops registered yet.</div><?php endif; ?>
                </div>
            </div>

            <div class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex items-center justify-between gap-3 flex-wrap"><div><div class="text-sm text-slate-500">Contribution scoring</div><h2 class="text-2xl font-black text-slate-900">Points breakdown</h2></div><a href="<?= e(app_url('modules/family/timeline.php')) ?>" class="app-btn-outline">Open full timeline</a></div>
                <div class="mt-4 space-y-3">
                    <?php foreach ($pointsBreakdown as $row): ?>
                        <div class="rounded-2xl border border-slate-200 p-4 flex items-center justify-between gap-3">
                            <div>
                                <div class="font-bold text-slate-900"><?= e(ucwords(str_replace('_', ' ', (string)$row['source_type']))) ?></div>
                                <div class="text-sm text-slate-500"><?= (int)$row['item_count'] ?> item(s)</div>
                            </div>
                            <div class="text-xl font-black text-emerald-700">+<?= e(rtrim(rtrim(number_format((float)$row['total_points'], 2, '.', ''), '0'), '.')) ?></div>
                        </div>
                    <?php endforeach; ?>
                    <?php if (!$pointsBreakdown): ?><div class="text-slate-500">No contribution points yet.</div><?php endif; ?>
                </div>
            </div>
        </section>
    </div>

    <div class="grid gap-6 xl:grid-cols-2">
        <section class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
            <div class="text-sm text-slate-500">Monitoring</div>
            <h2 class="text-2xl font-black text-slate-900">Recent monitoring visits</h2>
            <div class="mt-4 space-y-3">
                <?php foreach ($monitoring as $row): ?>
                <div class="rounded-2xl border border-slate-200 p-4">
                    <div class="font-bold text-slate-900"><?= e(date('M d, Y', strtotime((string)$row['monitoring_date']))) ?></div>
                    <div class="text-sm text-slate-500"><?= e($row['fruiting_status']) ?> · <?= e($row['crop_condition']) ?> · Harvest <?= e((string)$row['harvest_kg']) ?> kg</div>
                    <?php if (!empty($row['notes'])): ?><div class="mt-2 text-sm text-slate-600"><?= e($row['notes']) ?></div><?php endif; ?>
                </div>
                <?php endforeach; ?>
                <?php if (!$monitoring): ?><div class="text-slate-500">No monitoring records yet.</div><?php endif; ?>
            </div>
        </section>
        <section class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
            <div class="text-sm text-slate-500">Timeline</div>
            <h2 class="text-2xl font-black text-slate-900">Recent activity history</h2>
            <div class="mt-4 space-y-3">
                <?php foreach ($timeline as $item): ?>
                    <div class="rounded-2xl border border-slate-200 p-4">
                        <div class="flex items-center justify-between gap-3 flex-wrap"><div class="font-bold text-slate-900"><?= e($item['title']) ?></div><span class="app-inline-badge"><?= e($item['tag']) ?></span></div>
                        <div class="mt-2 text-sm text-slate-600"><?= e($item['details']) ?></div>
                        <?php if (!empty($item['event_at'])): ?><div class="mt-2 text-sm text-slate-500"><?= e(date('M d, Y h:i A', strtotime((string)$item['event_at']))) ?></div><?php endif; ?>
                    </div>
                <?php endforeach; ?>
                <?php if (!$timeline): ?><div class="text-slate-500">No activity history yet.</div><?php endif; ?>
            </div>
        </section>
    </div>

    <section id="family-notices" class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
        <div class="text-sm text-slate-500">Family notifications</div>
        <h2 class="text-2xl font-black text-slate-900">Recent notices and feedback</h2>
        <div class="mt-4 space-y-3">
            <?php foreach ($familyNotifications as $note): ?>
            <div class="rounded-2xl border border-slate-200 p-4">
                <div class="flex items-center justify-between gap-3 flex-wrap">
                    <div class="font-bold text-slate-900"><?= e($note['title']) ?></div>
                    <span class="app-inline-badge"><?= e($note['severity'] ?? 'Low') ?></span>
                </div>
                <div class="mt-2 text-slate-600"><?= e($note['message']) ?></div>
                <?php if (!empty($note['created_at'])): ?><div class="mt-2 text-sm text-slate-500"><?= e(date('M d, Y h:i A', strtotime((string)$note['created_at']))) ?></div><?php endif; ?>
            </div>
            <?php endforeach; ?>
            <?php if (!$familyNotifications): ?><div class="text-slate-500">No family notices yet.</div><?php endif; ?>
        </div>
    </section>
</div>
<script>
(function(){
    const typeEl = document.getElementById('update_type');
    const cropWrap = document.getElementById('crop_picker_wrap');
    const cropSel = document.getElementById('crop_id');
    const typesNeedCrop = ['Harvest Update', 'Crop Update'];
    function syncCropRequirement() {
        const needs = typesNeedCrop.includes(typeEl.value);
        cropWrap.style.display = needs ? '' : 'none';
        cropSel.required = needs;
        if (!needs) cropSel.value = '';
    }
    if (typeEl) {
        typeEl.addEventListener('change', syncCropRequirement);
        syncCropRequirement();
    }
})();
</script>
</body>
</html>
