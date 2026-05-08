<?php
require_once dirname(__DIR__, 3) . '/app/bootstrap.php';
$conn = db_conn();
app_require('app/includes/auth.php');
app_require('app/includes/module_platform.php');
app_require('app/includes/helpers/module_family_views.php');
require_role(['task_force','mayor','admin']);
ensure_decision_support_schema($conn);
$id = (int)($_GET['id'] ?? 0);
$house = $id > 0 ? get_household_snapshot($conn, $id) : [];
if (!$house) { app_require('app/includes/header.php'); echo '<div class="app-toast app-toast-error">Family not found.</div>'; app_require('app/includes/footer.php'); exit; }
$members = fetch_all_assoc($conn, "SELECT full_name, relationship_to_head, sex, birthdate, contact_number, occupation, education_level, member_tags, member_photo_path, is_household_head FROM family_members WHERE household_id=".(int)$id." AND is_active=1 ORDER BY is_household_head DESC, full_name");
$crops = fetch_all_assoc($conn, "SELECT crop_name, tree_count, current_condition, fruiting_status FROM crops WHERE household_id=".(int)$id." ORDER BY crop_name");
$attendance = fetch_all_assoc($conn, "SELECT e.event_name,e.event_date,a.attendance_status FROM event_attendance a JOIN events e ON e.event_id=a.event_id WHERE a.household_id=".(int)$id." ORDER BY e.event_date DESC LIMIT 8");
$timeline = array_slice(family_timeline($conn, $id), 0, 8);
$case = household_case_summary($conn, $id);
$cbmsProfile = fetch_cbms_household_profile($conn, $id) ?: [];
$spFlags = fetch_household_beneficiary_flags($conn, $id) ?: [];
$qr = $house['qr_reference'] ?? household_qr_reference($conn, $id);
$qrData = build_qr_data_uri($qr ?: ('HOUSEHOLD:' . $id), 220);
app_require('app/includes/header.php');
?>
<section class="rounded-[2rem] border border-slate-200 bg-white p-8 shadow-sm">
    <div class="flex items-center justify-between gap-4 print:hidden">
        <div>
            <div class="text-sm text-slate-500">Printable case profile</div>
            <h2 class="text-3xl font-black"><?= e($house['head_name'] ?: $house['household_head_name']) ?></h2>
        </div>
        <div class="flex gap-2">
            <a href="<?= e(app_url('modules/agri/households/view.php?id=' . $id)) ?>" class="app-btn-outline">Back</a>
            <button type="button" onclick="window.print()" class="app-btn-primary">Print / Save PDF</button>
        </div>
    </div>
    <div class="mt-6 grid gap-6 lg:grid-cols-[1.1fr_0.9fr]">
        <div class="rounded-3xl border border-slate-200 p-6">
            <div class="flex gap-4 items-center">
                <img src="<?= e($house['photo_url'] ?: app_url('assets/img/image.jpg')) ?>" class="h-24 w-24 rounded-[1.75rem] object-cover border" alt="Head photo">
                <div>
                    <div class="text-sm text-slate-500"><?= e($house['barangay_name'] ?: '-') ?></div>
                    <div class="text-2xl font-black"><?= e($house['head_name'] ?: $house['household_head_name']) ?></div>
                    <div class="text-sm text-slate-500"><?= e($house['household_code'] ?: '-') ?> · <?= e($house['contact_number'] ?: '-') ?></div>
                </div>
            </div>
            <div class="mt-5 grid gap-3 sm:grid-cols-2 text-sm">
                <div><strong>Members:</strong> <?= (int)($case['members'] ?? count($members)) ?></div>
                <div><strong>Dependents:</strong> <?= (int)($case['dependents'] ?? 0) ?></div>
                <div><strong>Seniors:</strong> <?= (int)($case['seniors'] ?? 0) ?></div>
                <div><strong>PWD:</strong> <?= (int)($case['pwd'] ?? 0) ?></div>
                <div><strong>Qualified:</strong> <?= format_status_badge($house['qualification_status'] ?? 'For Validation') ?></div>
                <div><strong>Score:</strong> <?= e((string)($house['score'] ?? 0)) ?></div>
                <div class="sm:col-span-2"><strong>Address:</strong> <?= e(($house['full_address'] ?? '-') . (($house['purok_sitio'] ?? '') ? ' · ' . $house['purok_sitio'] : '')) ?></div>
            </div>
        </div>
        <div class="rounded-3xl border border-slate-200 p-6 flex flex-col items-center justify-center">
            <img src="<?= e($qrData) ?>" class="h-56 w-56 object-contain" alt="Family QR">
            <div class="mt-3 font-semibold"><?= e($qr ?: 'QR pending') ?></div>
            <div class="text-sm text-slate-500">One QR per family for attendance, interview, and monitoring.</div>
        </div>
    </div>
    <div class="mt-6 grid gap-6 xl:grid-cols-2">
        <div class="rounded-3xl border border-slate-200 p-6">
            <div class="text-sm text-slate-500">Family composition</div>
            <h3 class="text-xl font-black">Household members</h3>
            <div class="mt-4 space-y-3">
                <?php foreach ($members as $member): ?>
                    <div class="flex gap-3 items-start rounded-2xl border border-slate-200 p-3">
                        <img src="<?= e(member_photo_url($member['member_photo_path'] ?? null)) ?>" class="h-12 w-12 rounded-xl object-cover border" alt="Member photo">
                        <div class="text-sm">
                            <div class="font-semibold"><?= e($member['full_name']) ?><?php if (!empty($member['is_household_head'])): ?> <span class="app-badge app-badge-emerald">Head</span><?php endif; ?></div>
                            <div class="text-slate-500"><?= e($member['relationship_to_head'] ?: 'Member') ?><?= !empty($member['occupation']) ? ' · ' . e($member['occupation']) : '' ?></div>
                            <div class="text-slate-500"><?= member_tags_badges($member['member_tags'] ?? null) ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="rounded-3xl border border-slate-200 p-6">
            <div class="text-sm text-slate-500">Activity digest</div>
            <h3 class="text-xl font-black">Latest family history</h3>
            <div class="mt-4 space-y-3">
                <?php foreach ($timeline as $item): ?>
                    <div class="rounded-2xl border border-slate-200 p-3">
                        <div class="flex items-center justify-between gap-3"><div class="font-semibold"><?= e($item['title']) ?></div><div class="text-xs text-slate-500"><?= e(date('M d, Y', strtotime((string)$item['date']))) ?></div></div>
                        <div class="mt-1 text-sm text-slate-500"><?= e($item['meta']) ?></div>
                    </div>
                <?php endforeach; if (!$timeline): ?><div class="text-sm text-slate-500">No activity yet.</div><?php endif; ?>
            </div>
        </div>
    </div>
    <div class="mt-6 grid gap-6 xl:grid-cols-2">
        <div class="rounded-3xl border border-slate-200 p-6">
            <div class="text-sm text-slate-500">CBMS-lite summary</div>
            <h3 class="text-xl font-black">Special Program family profile</h3>
            <div class="mt-4 grid gap-3 sm:grid-cols-2 text-sm">
                <div><strong>Housing type:</strong> <?= e($cbmsProfile['housing_type'] ?? '-') ?></div>
                <div><strong>Tenure:</strong> <?= e($cbmsProfile['tenure_status'] ?? '-') ?></div>
                <div><strong>Water source:</strong> <?= e($cbmsProfile['water_source'] ?? '-') ?></div>
                <div><strong>Toilet type:</strong> <?= e($cbmsProfile['toilet_type'] ?? '-') ?></div>
                <div><strong>Electricity:</strong> <?= e($cbmsProfile['electricity_source'] ?? '-') ?></div>
                <div><strong>Waste disposal:</strong> <?= e($cbmsProfile['waste_disposal_method'] ?? '-') ?></div>
                <div><strong>Farming household:</strong> <?= !empty($cbmsProfile['farming_household']) ? 'Yes' : 'No' ?></div>
                <div><strong>Farm area:</strong> <?= e(($cbmsProfile['farm_area_hectares'] ?? '') !== null && ($cbmsProfile['farm_area_hectares'] ?? '') !== '' ? ((string)$cbmsProfile['farm_area_hectares']) . ' ha' : '-') ?></div>
                <div><strong>Fruit tree estimate:</strong> <?= e((string)($cbmsProfile['fruit_tree_count_estimate'] ?? '-')) ?></div>
                <div><strong>Priority:</strong> <?= e($spFlags['priority_level'] ?? '-') ?></div>
                <div class="sm:col-span-2"><strong>Flags:</strong>
                    <?= !empty($spFlags['is_4ps']) ? '<span class="app-badge app-badge-blue">4Ps</span> ' : '' ?>
                    <?= !empty($spFlags['has_senior']) ? '<span class="app-badge app-badge-amber">Senior</span> ' : '' ?>
                    <?= !empty($spFlags['has_pwd']) ? '<span class="app-badge app-badge-rose">PWD</span> ' : '' ?>
                    <?= !empty($spFlags['has_solo_parent']) ? '<span class="app-badge app-badge-violet">Solo Parent</span> ' : '' ?>
                    <?= !empty($spFlags['has_pregnant_member']) ? '<span class="app-badge app-badge-emerald">Pregnant</span> ' : '' ?>
                    <?= !empty($spFlags['has_philhealth']) ? '<span class="app-badge">PhilHealth</span> ' : '' ?>
                    <?= !empty($spFlags['receives_lgu_assistance']) ? '<span class="app-badge app-badge-amber">LGU Assistance</span>' : '' ?>
                    <?php if (empty($spFlags['is_4ps']) && empty($spFlags['has_senior']) && empty($spFlags['has_pwd']) && empty($spFlags['has_solo_parent']) && empty($spFlags['has_pregnant_member']) && empty($spFlags['has_philhealth']) && empty($spFlags['receives_lgu_assistance'])): ?>
                        <span class="text-slate-500">No CBMS-lite flags recorded.</span>
                    <?php endif; ?>
                </div>
                <div class="sm:col-span-2"><strong>Program notes:</strong> <?= e($cbmsProfile['special_program_notes'] ?? ($spFlags['priority_notes'] ?? '-')) ?></div>
            </div>
        </div>
        <div class="rounded-3xl border border-slate-200 p-6">
            <div class="text-sm text-slate-500">Crop summary</div>
            <h3 class="text-xl font-black">Registered crops</h3>
            <div class="mt-4 overflow-hidden rounded-2xl border border-slate-200">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50"><tr><th class="px-3 py-2 text-left">Crop</th><th class="px-3 py-2 text-left">Trees</th><th class="px-3 py-2 text-left">Condition</th></tr></thead>
                    <tbody><?php foreach($crops as $row): ?><tr class="border-t border-slate-200"><td class="px-3 py-2"><?= e($row['crop_name']) ?></td><td class="px-3 py-2"><?= (int)$row['tree_count'] ?></td><td class="px-3 py-2"><?= e(($row['current_condition'] ?: '-') . ' · ' . ($row['fruiting_status'] ?: '-')) ?></td></tr><?php endforeach; if(!$crops): ?><tr><td colspan="3" class="px-3 py-4 text-center text-slate-500">No crop records.</td></tr><?php endif; ?></tbody>
                </table>
            </div>
        </div>
        <div class="rounded-3xl border border-slate-200 p-6 xl:col-span-2">
            <div class="text-sm text-slate-500">Participation summary</div>
            <h3 class="text-xl font-black">Events attended</h3>
            <div class="mt-4 overflow-hidden rounded-2xl border border-slate-200">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50"><tr><th class="px-3 py-2 text-left">Event</th><th class="px-3 py-2 text-left">Date</th><th class="px-3 py-2 text-left">Status</th></tr></thead>
                    <tbody><?php foreach($attendance as $row): ?><tr class="border-t border-slate-200"><td class="px-3 py-2"><?= e($row['event_name']) ?></td><td class="px-3 py-2"><?= e($row['event_date']) ?></td><td class="px-3 py-2"><?= e($row['attendance_status']) ?></td></tr><?php endforeach; if(!$attendance): ?><tr><td colspan="3" class="px-3 py-4 text-center text-slate-500">No attendance logs.</td></tr><?php endif; ?></tbody>
                </table>
            </div>
        </div>
    </div>
</section>
<?php app_require('app/includes/footer.php'); ?>