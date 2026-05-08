<?php
require_once dirname(__DIR__, 3) . '/app/bootstrap.php';
$conn = db_conn();
app_require('app/includes/auth.php');
app_require('app/includes/module_platform.php');
app_require('app/includes/helpers/module_family_views.php');
set_current_platform_module('beneficiaries');
require_module_access(['task_force','mayor','admin','developer'], 'beneficiaries');
$id = (int)getv('id');
$house = fetch_household_shared_summary($conn, $id);
if (!$house) { app_require('app/includes/header.php'); echo '<div class="app-toast app-toast-error">Family not found.</div>'; app_require('app/includes/footer.php'); exit; }
$user = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string)post('action'));
    if ($action === 'save_beneficiary_record') {
        $recordId = (int)post('record_id');
        $savedId = save_beneficiary_record($conn, $id, $_POST, (int)($user['id'] ?? 0), $recordId);
        if ($savedId > 0) {
            set_flash('success', $recordId > 0 ? 'Beneficiary record updated.' : 'Beneficiary record added.');
        } else {
            set_flash('error', 'Unable to save the beneficiary record.');
        }
        header('Location: ' . app_url('modules/beneficiaries/families/view.php?id=' . $id . '#beneficiary-records'));
        exit;
    } elseif ($action === 'delete_beneficiary_record') {
        $recordId = (int)post('record_id');
        if (delete_beneficiary_record($conn, $id, $recordId, (int)($user['id'] ?? 0))) {
            set_flash('success', 'Beneficiary record removed.');
        } else {
            set_flash('error', 'Unable to remove the beneficiary record.');
        }
        header('Location: ' . app_url('modules/beneficiaries/families/view.php?id=' . $id . '#beneficiary-records'));
        exit;
    }
}

$beneficiaryProfiles = fetch_beneficiary_profiles_for_household($conn, $id);
$assistance = fetch_assistance_history_shared($conn, $id);
$tags = detect_household_tags($conn, $house);
$timeline = fetch_household_timeline($conn, $id, 8);
$completeness = compute_module_completeness($conn, $house);
$actions = module_quick_actions('beneficiaries', $id);
$members = $house['members'] ?? [];
$sectorOptions = beneficiary_sector_type_options($conn);
$editId = (int)getv('edit_record');
$editRecord = null;
foreach ($beneficiaryProfiles as $row) { if ((int)($row['beneficiary_record_id'] ?? 0) === $editId) { $editRecord = $row; break; } }
$selectedTags = array_filter(array_map('trim', explode(',', (string)($editRecord['sector_tags'] ?? ''))));
$cards = [
    ['label'=>'Family members','value'=>$house['member_count'],'hint'=>'Shared household members'],
    ['label'=>'Beneficiary records','value'=>count($beneficiaryProfiles),'hint'=>'Household or member-linked'],
    ['label'=>'Assistance history','value'=>count($assistance),'hint'=>'Shared support history'],
    ['label'=>'Beneficiary readiness','value'=>$completeness['beneficiaries'] . '%','hint'=>'Classification completeness'],
];
app_require('app/includes/header.php');
echo nav_cards($cards);
?>
<div class="grid gap-6 xl:grid-cols-[1fr_1fr]">
<section class="rounded-[2rem] border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-6 shadow-sm">
    <div class="flex items-start gap-4">
        <img src="<?= e($house['photo_url']) ?>" alt="Family" class="h-24 w-24 rounded-[2rem] object-cover border border-slate-200 dark:border-slate-800">
        <div class="min-w-0 flex-1">
            <div class="text-sm text-slate-500">Beneficiary family view</div>
            <h2 class="text-4xl font-black"><?= e($house['household_head_name']) ?></h2>
            <div class="mt-2 text-slate-500"><?= e(($house['household_code'] ?: 'No HH code') . ' · ' . ($house['barangay_name'] ?: 'No barangay')) ?></div>
            <div class="mt-4"><?= render_badges($tags, 'amber') ?></div>
        </div>
    </div>
    <div class="mt-6 grid gap-4 md:grid-cols-2">
        <div class="rounded-3xl border border-slate-200 dark:border-slate-800 p-4"><div class="text-sm text-slate-500">Contact</div><div class="mt-2 font-semibold"><?= e($house['contact_number'] ?: '-') ?></div></div>
        <div class="rounded-3xl border border-slate-200 dark:border-slate-800 p-4"><div class="text-sm text-slate-500">Address</div><div class="mt-2 font-semibold"><?= e($house['full_address'] ?: $house['purok_sitio'] ?: '-') ?></div></div>
    </div>
    <div class="mt-6 rounded-3xl border border-slate-200 dark:border-slate-800 p-5 bg-slate-50/60 dark:bg-slate-900/40">
        <div class="text-sm text-slate-500">Module quick actions</div>
        <h3 class="text-2xl font-black">Beneficiary actions for this family</h3>
        <div class="mt-4"><?= render_quick_actions($actions) ?></div>
    </div>
    <div class="mt-6 rounded-3xl border border-slate-200 dark:border-slate-800 p-5">
        <div class="text-sm text-slate-500">Shared family members</div>
        <h3 class="text-2xl font-black">Complete family members</h3>
        <div class="mt-4 space-y-3">
            <?php foreach ($house['members'] as $member): ?>
            <div class="rounded-2xl border border-slate-200 dark:border-slate-800 p-4">
                <div class="flex items-center gap-2 flex-wrap"><div class="font-semibold"><?= e($member['full_name']) ?></div><?php if (!empty($member['is_household_head'])): ?><span class="app-badge app-badge-emerald">Head</span><?php endif; ?></div>
                <div class="mt-1 text-sm text-slate-500"><?= e(($member['relationship_to_head'] ?: 'Member') . ' · ' . ($member['occupation'] ?: 'No occupation set')) ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<section class="space-y-6">
    <div class="rounded-[2rem] border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-6 shadow-sm" id="beneficiary-records">
        <div class="text-sm text-slate-500">Beneficiary classifications</div>
        <h3 class="text-2xl font-black">Records in this module</h3>
        <details class="mt-4 rounded-3xl border border-slate-200 dark:border-slate-800 p-4">
            <summary class="cursor-pointer font-semibold"><?= $editRecord ? 'Edit beneficiary profile' : 'Add beneficiary profile' ?></summary>
            <form method="POST" class="mt-4 grid gap-3 md:grid-cols-2">
                <input type="hidden" name="action" value="save_beneficiary_record">
                <?php if ($editRecord): ?><input type="hidden" name="record_id" value="<?= (int)$editRecord['beneficiary_record_id'] ?>"><?php endif; ?>
                <div><label class="block text-sm font-semibold mb-2">Family member</label><select name="member_id" class="w-full rounded-2xl border px-4 py-3"><option value="0">Whole household</option><?php foreach ($members as $member): ?><option value="<?= (int)$member['member_id'] ?>" <?= ((int)($editRecord['member_id'] ?? 0) === (int)$member['member_id']) ? 'selected' : '' ?>><?= e($member['full_name']) ?><?= !empty($member['relationship_to_head']) ? ' · ' . e($member['relationship_to_head']) : '' ?></option><?php endforeach; ?></select></div>
                <div><label class="block text-sm font-semibold mb-2">Indigent status</label><select name="indigent_status" class="w-full rounded-2xl border px-4 py-3"><option value="">Choose status</option><?php foreach (beneficiary_indigent_status_options() as $opt): ?><option value="<?= e($opt) ?>" <?= (($editRecord['indigent_status'] ?? '') === $opt) ? 'selected' : '' ?>><?= e($opt) ?></option><?php endforeach; ?></select></div>
                <div><label class="block text-sm font-semibold mb-2">Priority level</label><select name="priority_level" class="w-full rounded-2xl border px-4 py-3"><option value="">Choose priority</option><?php foreach (beneficiary_priority_level_options() as $opt): ?><option value="<?= e($opt) ?>" <?= (($editRecord['priority_level'] ?? '') === $opt) ? 'selected' : '' ?>><?= e($opt) ?></option><?php endforeach; ?></select></div>
                <div><label class="block text-sm font-semibold mb-2">Recommendation</label><select name="recommendation" class="w-full rounded-2xl border px-4 py-3"><option value="">Choose recommendation</option><?php foreach (beneficiary_recommendation_options() as $opt): ?><option value="<?= e($opt) ?>" <?= (($editRecord['recommendation'] ?? '') === $opt) ? 'selected' : '' ?>><?= e($opt) ?></option><?php endforeach; ?></select></div>
                <div class="md:col-span-2"><label class="block text-sm font-semibold mb-2">Sector tags</label><div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3 rounded-2xl border px-4 py-3"><?php foreach ($sectorOptions as $opt): ?><label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" name="sector_tags[]" value="<?= e($opt['code']) ?>" <?= in_array($opt['code'], $selectedTags, true) ? 'checked' : '' ?>> <span><?= e($opt['name']) ?></span></label><?php endforeach; ?></div></div>
                <div class="md:col-span-2"><label class="block text-sm font-semibold mb-2">Notes</label><textarea name="notes" rows="3" class="w-full rounded-2xl border px-4 py-3"><?= e($editRecord['notes'] ?? '') ?></textarea></div>
                <div class="md:col-span-2 flex gap-2 flex-wrap"><button class="app-btn-primary"><?= $editRecord ? 'Update beneficiary record' : 'Add beneficiary record' ?></button><?php if ($editRecord): ?><a href="<?= e(app_url('modules/beneficiaries/families/view.php?id=' . $id . '#beneficiary-records')) ?>" class="app-btn-outline">Cancel</a><?php endif; ?></div>
            </form>
        </details>
        <div class="mt-4 space-y-3">
            <?php foreach ($beneficiaryProfiles as $bp): ?>
            <div class="rounded-2xl border border-slate-200 dark:border-slate-800 p-4">
                <div class="flex flex-wrap gap-2"><?php foreach (array_filter([($bp['indigent_status'] ?? null), ($bp['priority_level'] ?? null)]) as $label): ?><span class="app-badge app-badge-amber"><?= e($label) ?></span><?php endforeach; ?></div>
                <div class="mt-2 text-sm text-slate-500"><?= e($bp['sector_tags'] ?? 'No sector tags') ?></div>
                <div class="mt-2 text-sm"><?= e($bp['recommendation'] ?? 'No recommendation') ?></div>
                <?php if (!empty($bp['notes'])): ?><div class="mt-2 text-sm text-slate-500">Notes: <?= e($bp['notes']) ?></div><?php endif; ?>
                <div class="mt-3 flex gap-2 flex-wrap">
                    <a class="app-btn-outline text-sm" href="<?= e(app_url('modules/beneficiaries/families/view.php?id=' . $id . '&edit_record=' . (int)($bp['beneficiary_record_id'] ?? 0) . '#beneficiary-records')) ?>">Edit</a>
                    <?php if (!empty($bp['beneficiary_record_id'])): ?>
                    <form method="POST" class="contents" onsubmit="return confirm('Remove this beneficiary record?');">
                        <input type="hidden" name="action" value="delete_beneficiary_record">
                        <input type="hidden" name="record_id" value="<?= (int)$bp['beneficiary_record_id'] ?>">
                        <button class="app-btn-outline text-sm">Delete</button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; if (!$beneficiaryProfiles): ?>
            <div class="text-sm text-slate-500">No beneficiary record yet for this family.</div>
            <?php endif; ?>
        </div>
    </div>
    <div class="rounded-[2rem] border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-6 shadow-sm" id="assistance-history">
        <div class="text-sm text-slate-500">Support history</div>
        <h3 class="text-2xl font-black">Assistance related to this family</h3>
        <div class="mt-4 overflow-x-auto">
            <table class="min-w-full text-sm"><thead><tr class="text-left text-slate-500"><th class="py-2 pr-4">Type</th><th class="py-2 pr-4">Status</th><th class="py-2 pr-4">Date</th><th class="py-2 pr-4">Module</th></tr></thead><tbody>
                <?php foreach ($assistance as $row): ?>
                <tr class="border-t border-slate-200 dark:border-slate-800"><td class="py-3 pr-4"><?= e($row['assistance_type']) ?></td><td class="py-3 pr-4"><?= format_status_badge($row['assistance_status']) ?></td><td class="py-3 pr-4"><?= e($row['assistance_date'] ?: '-') ?></td><td class="py-3 pr-4"><?= e($row['module_name'] ?: 'Shared') ?></td></tr>
                <?php endforeach; if (!$assistance): ?>
                <tr><td colspan="4" class="py-4 text-slate-500">No assistance history yet.</td></tr>
                <?php endif; ?>
            </tbody></table>
        </div>
    </div>
    <div class="rounded-[2rem] border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-6 shadow-sm">
        <div class="text-sm text-slate-500">Shared family timeline</div>
        <h3 class="text-2xl font-black">Latest updates touching this family</h3>
        <div class="mt-4 space-y-3">
            <?php foreach ($timeline as $item): ?>
            <div class="rounded-2xl border border-slate-200 dark:border-slate-800 p-4"><div class="flex items-center justify-between gap-3"><div class="font-semibold"><?= e($item['title']) ?></div><div class="text-xs text-slate-500"><?= e(date('M d, Y', strtotime((string)$item['date']))) ?></div></div><div class="mt-1 text-sm text-slate-500"><?= e($item['meta']) ?></div></div>
            <?php endforeach; if (!$timeline): ?><div class="text-sm text-slate-500">No family timeline yet.</div><?php endif; ?>
        </div>
    </div>
</section>
</div>
<?php app_require('app/includes/footer.php'); ?>
