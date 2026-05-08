<?php
require_once dirname(__DIR__, 3) . '/app/bootstrap.php';
$conn = db_conn();
app_require('app/includes/auth.php');
require_role(['task_force','mayor','developer','admin']);
ensure_family_portal_schema($conn);
$user = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($user['role'], ['task_force','developer','admin'], true)) {
    $action = (string)post('action');
    if ($action === 'review_update') {
        $decision = (string)post('decision');
        if (review_family_update($conn, (int)post('update_id'), (int)$user['id'], $decision, trim((string)post('review_notes')))) {
            set_flash('success', 'Family submission reviewed successfully.');
        } else {
            set_flash('error', 'Unable to review the family submission.');
        }
        $qs = $_SERVER['QUERY_STRING'] ?? '';
        header('Location: ' . app_url('modules/agri/family_updates/index.php' . ($qs !== '' ? ('?' . $qs) : '')));
        exit;
    }
}

$flash = get_flash();
$status = trim((string)getv('status', 'Pending'));
$barangayId = (int)getv('barangay_id');
$type = trim((string)getv('type'));
$search = trim((string)getv('q'));
$where = [];
if (in_array($status, ['Pending','Approved','Rejected','Needs Revision'], true)) {
    $where[] = "fu.reviewed_status='" . $conn->real_escape_string($status) . "'";
}
if ($barangayId > 0) {
    $where[] = 'h.barangay_id=' . $barangayId;
}
if ($type !== '') {
    $where[] = "fu.update_type='" . $conn->real_escape_string($type) . "'";
}
if ($search !== '') {
    $term = $conn->real_escape_string($search);
    $where[] = "(h.household_head_name LIKE '%{$term}%' OR h.household_code LIKE '%{$term}%' OR fu.title LIKE '%{$term}%' OR fu.notes LIKE '%{$term}%' OR c.crop_name LIKE '%{$term}%')";
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$updates = fetch_all_assoc($conn, "SELECT fu.*, h.household_head_name, h.household_code, b.barangay_name, c.crop_name, c.variety, u.full_name AS reviewer_name,
    COALESCE(q.score,0) AS qualification_score, COALESCE(q.qualification_status,'For Validation') AS qualification_status
    FROM family_portal_updates fu
    JOIN households h ON h.household_id=fu.household_id
    LEFT JOIN barangays b ON b.barangay_id=h.barangay_id
    LEFT JOIN crops c ON c.crop_id=fu.crop_id
    LEFT JOIN users u ON u.user_id=fu.reviewed_by
    LEFT JOIN household_qualification q ON q.household_id=h.household_id
    {$whereSql}
    ORDER BY FIELD(fu.reviewed_status,'Pending','Needs Revision','Approved','Rejected'), fu.submitted_at DESC");
$summary = family_updates_summary($conn);
$barangays = fetch_all_assoc($conn, "SELECT barangay_id, barangay_name FROM barangays ORDER BY barangay_name ASC");
$typeOptions = family_update_type_options();
$pointsAwardedTotal = (float)scalar($conn, "SELECT COALESCE(SUM(points_awarded),0) FROM family_portal_updates WHERE reviewed_status='Approved'", 0);
$qualifiedReady = (int)scalar($conn, "SELECT COUNT(*) FROM household_qualification WHERE qualification_status IN ('Qualified','Highly Qualified')", 0);
$pendingCropLinked = (int)scalar($conn, "SELECT COUNT(*) FROM family_portal_updates WHERE reviewed_status='Pending' AND update_type IN ('Harvest Update','Crop Update')", 0);

app_require('app/includes/header.php');
?>
<section class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
    <div class="flex items-center justify-between gap-4 flex-wrap">
        <div>
            <div class="text-sm text-slate-500">Family portal moderation</div>
            <h1 class="text-3xl font-black text-slate-900">Family updates</h1>
            <p class="mt-2 text-slate-500">Review harvest and crop updates submitted by families. Approved crop-linked updates add points to qualification.</p>
        </div>
        <div class="flex gap-2 flex-wrap">
            <a href="<?= e(app_url('modules/agri/family_reports/index.php')) ?>" class="app-btn-outline">Reports</a>
            <a href="<?= e(app_url('modules/admin/qualification_rules/index.php')) ?>" class="app-btn-outline">Rules</a>
            <a href="<?= e(app_url('modules/agri/family_updates/index.php?status=Pending')) ?>" class="app-btn-outline">Pending</a>
            <a href="<?= e(app_url('modules/agri/family_updates/index.php?status=Approved')) ?>" class="app-btn-outline">Approved</a>
            <a href="<?= e(app_url('modules/agri/family_updates/index.php?status=Needs+Revision')) ?>" class="app-btn-outline">Needs revision</a>
            <a href="<?= e(app_url('modules/agri/family_updates/index.php?status=Rejected')) ?>" class="app-btn-outline">Rejected</a>
        </div>
    </div>

    <?php if ($flash): ?><div class="mt-4 rounded-2xl border <?= $flash['type']==='success' ? 'border-emerald-200 bg-emerald-50 text-emerald-900' : 'border-rose-200 bg-rose-50 text-rose-900' ?> px-4 py-3"><?= e($flash['message']) ?></div><?php endif; ?>

    <div class="mt-6 grid gap-4 md:grid-cols-4 xl:grid-cols-7">
        <div class="rounded-[1.5rem] border border-slate-200 bg-slate-50 p-4"><div class="text-sm text-slate-500">Pending</div><div class="mt-2 text-3xl font-black text-amber-600"><?= $summary['pending'] ?></div></div>
        <div class="rounded-[1.5rem] border border-slate-200 bg-slate-50 p-4"><div class="text-sm text-slate-500">Approved</div><div class="mt-2 text-3xl font-black text-emerald-700"><?= $summary['approved'] ?></div></div>
        <div class="rounded-[1.5rem] border border-slate-200 bg-slate-50 p-4"><div class="text-sm text-slate-500">Needs revision</div><div class="mt-2 text-3xl font-black text-amber-700"><?= $summary['needs_revision'] ?></div></div>
        <div class="rounded-[1.5rem] border border-slate-200 bg-slate-50 p-4"><div class="text-sm text-slate-500">Rejected</div><div class="mt-2 text-3xl font-black text-rose-700"><?= $summary['rejected'] ?></div></div>
        <div class="rounded-[1.5rem] border border-slate-200 bg-slate-50 p-4"><div class="text-sm text-slate-500">Crop-linked pending</div><div class="mt-2 text-3xl font-black text-sky-700"><?= $pendingCropLinked ?></div></div>
        <div class="rounded-[1.5rem] border border-slate-200 bg-slate-50 p-4"><div class="text-sm text-slate-500">Points awarded</div><div class="mt-2 text-3xl font-black text-emerald-700"><?= e(rtrim(rtrim(number_format($pointsAwardedTotal, 2, '.', ''), '0'), '.')) ?></div></div>
        <div class="rounded-[1.5rem] border border-slate-200 bg-slate-50 p-4"><div class="text-sm text-slate-500">Qualified households</div><div class="mt-2 text-3xl font-black text-emerald-800"><?= $qualifiedReady ?></div></div>
    </div>

    <form method="GET" class="mt-6 grid gap-4 md:grid-cols-4 xl:grid-cols-5">
        <div>
            <label class="block text-sm font-semibold mb-2">Status</label>
            <select name="status" class="w-full rounded-2xl border border-slate-300 px-4 py-3">
                <?php foreach (['Pending','Approved','Needs Revision','Rejected'] as $opt): ?>
                    <option value="<?= e($opt) ?>"<?= $status === $opt ? ' selected' : '' ?>><?= e($opt) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-sm font-semibold mb-2">Barangay</label>
            <select name="barangay_id" class="w-full rounded-2xl border border-slate-300 px-4 py-3">
                <option value="0">All barangays</option>
                <?php foreach ($barangays as $barangay): ?>
                    <option value="<?= (int)$barangay['barangay_id'] ?>"<?= $barangayId === (int)$barangay['barangay_id'] ? ' selected' : '' ?>><?= e($barangay['barangay_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-sm font-semibold mb-2">Submission type</label>
            <select name="type" class="w-full rounded-2xl border border-slate-300 px-4 py-3">
                <option value="">All types</option>
                <?php foreach ($typeOptions as $value => $label): ?>
                    <option value="<?= e($value) ?>"<?= $type === $value ? ' selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="xl:col-span-2">
            <label class="block text-sm font-semibold mb-2">Search</label>
            <div class="flex gap-2">
                <input type="text" name="q" value="<?= e($search) ?>" placeholder="Family head, code, crop, title, notes" class="w-full rounded-2xl border border-slate-300 px-4 py-3">
                <button class="app-btn-primary">Filter</button>
            </div>
        </div>
    </form>

    <div class="mt-6 space-y-4">
        <?php foreach ($updates as $item): ?>
        <?php $awardPreview = family_submission_award_preview($conn, $item); ?>
        <article class="rounded-[1.5rem] border border-slate-200 p-4">
            <div class="grid gap-4 xl:grid-cols-[1.15fr_0.85fr]">
                <div class="flex items-start gap-4">
                    <?php if (!empty($item['photo_path'])): ?>
                        <div>
                            <img src="<?= e(family_submission_photo_url($item['photo_path'])) ?>" alt="Submitted photo" class="h-44 w-44 rounded-[1.25rem] object-cover border border-slate-200 bg-slate-50" onerror="this.onerror=null;this.src='<?= e(family_submission_placeholder_url()) ?>';">
                            <div class="mt-2 flex items-center justify-between text-sm text-slate-500"><span>1 file</span><a class="font-semibold text-emerald-700" href="<?= e(family_submission_photo_url($item['photo_path'])) ?>" target="_blank">Preview</a></div>
                        </div>
                    <?php else: ?>
                        <div class="h-44 w-44 rounded-[1.25rem] border border-dashed border-slate-200 bg-slate-50 flex items-center justify-center text-slate-400 text-sm">No photo</div>
                    <?php endif; ?>
                    <div class="max-w-3xl">
                        <div class="flex items-center gap-2 flex-wrap">
                            <h2 class="text-xl font-black text-slate-900"><?= e($item['title'] ?: $item['update_type']) ?></h2>
                            <span class="app-inline-badge"><?= e($item['reviewed_status']) ?></span>
                            <span class="app-inline-badge"><?= e($item['update_type']) ?></span>
                            <?= format_status_badge($item['qualification_status']) ?>
                        </div>
                        <div class="mt-1 text-sm text-slate-500"><?= e($item['household_head_name']) ?> family<?= !empty($item['household_code']) ? ' · ' . e($item['household_code']) : '' ?><?= !empty($item['barangay_name']) ? ' · ' . e($item['barangay_name']) : '' ?></div>
                        <div class="mt-1 text-sm text-slate-500">Submitted <?= e(date('M d, Y h:i A', strtotime((string)$item['submitted_at']))) ?><?php if (!empty($item['activity_date'])): ?> · Activity date <?= e(date('M d, Y', strtotime((string)$item['activity_date']))) ?><?php endif; ?></div>
                        <?php if (!empty($item['crop_name'])): ?><div class="mt-1 text-sm text-slate-500">Crop: <?= e($item['crop_name']) ?><?= !empty($item['variety']) ? ' · ' . e($item['variety']) : '' ?></div><?php endif; ?>
                        <?php if (!empty($item['quantity_value'])): ?><div class="mt-1 text-sm text-slate-500">Quantity: <?= e(rtrim(rtrim(number_format((float)$item['quantity_value'], 2, '.', ''), '0'), '.')) ?> <?= e($item['quantity_unit'] ?: 'kg') ?></div><?php endif; ?>
                        <div class="mt-1 text-sm text-slate-500">Current qualification score: <?= e(rtrim(rtrim(number_format((float)$item['qualification_score'], 2, '.', ''), '0'), '.')) ?></div>
                        <?php if ((float)($item['points_awarded'] ?? 0) > 0): ?><div class="mt-1 text-sm font-semibold text-emerald-700">Points awarded: +<?= e(rtrim(rtrim(number_format((float)$item['points_awarded'], 2, '.', ''), '0'), '.')) ?></div><?php endif; ?>
                        <?php if (($item['reviewed_status'] ?? '') === 'Pending'): ?><div class="mt-1 text-sm text-amber-700">Projected award on approval: +<?= e(rtrim(rtrim(number_format((float)$awardPreview['points'], 2, '.', ''), '0'), '.')) ?><?= $awardPreview['reason'] !== '' ? ' · ' . e($awardPreview['reason']) : '' ?></div><?php endif; ?>
                        <?php if (!empty($item['notes'])): ?><p class="mt-3 text-slate-700"><?= e($item['notes']) ?></p><?php endif; ?>
                        <?php if (!empty($item['review_notes'])): ?><div class="mt-3 rounded-xl bg-slate-50 px-3 py-2 text-sm text-slate-600"><strong>Feedback:</strong> <?= e($item['review_notes']) ?></div><?php endif; ?>
                        <?php if (!empty($item['reviewer_name']) || !empty($item['reviewed_at'])): ?><div class="mt-2 text-sm text-slate-500">Reviewed by <?= e($item['reviewer_name'] ?: 'Staff') ?><?= !empty($item['reviewed_at']) ? ' · ' . e(date('M d, Y h:i A', strtotime((string)$item['reviewed_at']))) : '' ?></div><?php endif; ?>
                    </div>
                </div>

                <?php if (in_array($user['role'], ['task_force','developer','admin'], true) && ($item['reviewed_status'] ?? '') === 'Pending'): ?>
                <form method="POST" class="rounded-[1.5rem] border border-slate-200 p-4">
                    <input type="hidden" name="action" value="review_update">
                    <input type="hidden" name="update_id" value="<?= (int)$item['update_id'] ?>">
                    <div class="text-sm font-semibold text-slate-900">Staff action</div>
                    <p class="mt-2 text-slate-500">Add a clear comment so the family knows what happened next.</p>
                    <?php if ($awardPreview['reason'] !== ''): ?><div class="mt-3 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900"><?= e($awardPreview['reason']) ?></div><?php endif; ?>
                    <label class="mt-4 block text-sm font-semibold text-slate-700">Comment</label>
                    <textarea name="review_notes" rows="5" placeholder="Write review notes, guidance, or the reason for rejection." class="mt-2 w-full rounded-2xl border border-slate-300 px-4 py-3"></textarea>
                    <div class="mt-4 grid gap-2 sm:grid-cols-3">
                        <button name="decision" value="Approved" class="rounded-2xl bg-emerald-700 px-4 py-3 font-semibold text-white">Approve</button>
                        <button name="decision" value="Needs Revision" class="rounded-2xl bg-amber-500 px-4 py-3 font-semibold text-white">Needs revision</button>
                        <button name="decision" value="Rejected" class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 font-semibold text-rose-700">Reject</button>
                    </div>
                </form>
                <?php endif; ?>
            </div>
        </article>
        <?php endforeach; ?>
        <?php if (!$updates): ?><div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-6 text-slate-500">No family updates found for this filter.</div><?php endif; ?>
    </div>
</section>
<?php app_require('app/includes/footer.php'); ?>
