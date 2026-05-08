<?php
require_once dirname(__DIR__, 3) . '/app/bootstrap.php';
$conn = db_conn();
app_require('app/includes/auth.php');
app_require('app/includes/module_platform.php');
app_require('app/includes/helpers/module_family_views.php');
set_current_platform_module('mayor');
require_module_access(['mayor','admin','developer'], 'mayor');
$id = (int)getv('id');
$house = fetch_household_shared_summary($conn, $id);
if (!$house) { app_require('app/includes/header.php'); echo '<div class="app-toast app-toast-error">Family not found.</div>'; app_require('app/includes/footer.php'); exit; }
$special = fetch_special_program_data($conn, $id);
$beneficiaryProfiles = fetch_beneficiary_profiles_for_household($conn, $id);
$cbmsProfile = fetch_cbms_household_profile($conn, $id);
$assets = fetch_cbms_assets($conn, $id);
$richer = fetch_cbms_richer_sections($conn, $id);
$assistance = fetch_assistance_history_shared($conn, $id);
$timeline = fetch_household_timeline($conn, $id, 12);
$completeness = compute_module_completeness($conn, $house);
$eventPreview = fetch_household_event_preview($conn, $house, 6);
$actions = module_quick_actions('mayor', $id);
$cards = [
    ['label'=>'Members','value'=>$house['member_count'],'hint'=>'Shared household members'],
    ['label'=>'Crops','value'=>$house['crop_count'],'hint'=>'Special Program'],
    ['label'=>'Beneficiary records','value'=>count($beneficiaryProfiles),'hint'=>'Beneficiaries'],
    ['label'=>'CBMS assets','value'=>$house['pet_count'] + $house['vehicle_count'],'hint'=>'Pets + vehicles'],
];
app_require('app/includes/header.php');
echo nav_cards($cards);
?>
<div class="grid gap-6 xl:grid-cols-[1fr_1fr]">
<section class="space-y-6">
    <div class="rounded-[2rem] border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-6 shadow-sm">
        <div class="flex items-start gap-4">
            <img src="<?= e($house['photo_url']) ?>" alt="Family" class="h-24 w-24 rounded-[2rem] object-cover border border-slate-200 dark:border-slate-800">
            <div class="min-w-0 flex-1">
                <div class="text-sm text-slate-500">Mayor family 360</div>
                <h2 class="text-4xl font-black"><?= e($house['household_head_name']) ?></h2>
                <div class="mt-2 text-slate-500"><?= e(($house['household_code'] ?: 'No HH code') . ' · ' . ($house['barangay_name'] ?: 'No barangay')) ?></div>
                <div class="mt-4"><?= render_badges(detect_household_tags($conn, $house), 'emerald') ?></div>
            </div>
        </div>
        <div class="mt-6 grid gap-4 md:grid-cols-2">
            <div class="rounded-3xl border border-slate-200 dark:border-slate-800 p-4"><div class="text-sm text-slate-500">Contact</div><div class="mt-2 font-semibold"><?= e($house['contact_number'] ?: '-') ?></div></div>
            <div class="rounded-3xl border border-slate-200 dark:border-slate-800 p-4"><div class="text-sm text-slate-500">Address</div><div class="mt-2 font-semibold"><?= e($house['full_address'] ?: $house['purok_sitio'] ?: '-') ?></div></div>
        </div>
        <div class="mt-6 rounded-3xl border border-slate-200 dark:border-slate-800 p-5 bg-slate-50/60 dark:bg-slate-900/40">
            <div class="text-sm text-slate-500">Module quick actions</div>
            <h3 class="text-2xl font-black">Jump to the right office view</h3>
            <div class="mt-4"><?= render_quick_actions($actions) ?></div>
        </div>
        <div class="mt-6 grid gap-4 md:grid-cols-3">
            <div class="rounded-2xl border border-slate-200 dark:border-slate-800 p-4"><div class="text-sm text-slate-500">Special Program completeness</div><div class="mt-2 text-2xl font-black"><?= (int)$completeness['special_program'] ?>%</div></div>
            <div class="rounded-2xl border border-slate-200 dark:border-slate-800 p-4"><div class="text-sm text-slate-500">Beneficiaries completeness</div><div class="mt-2 text-2xl font-black"><?= (int)$completeness['beneficiaries'] ?>%</div></div>
            <div class="rounded-2xl border border-slate-200 dark:border-slate-800 p-4"><div class="text-sm text-slate-500">CBMS completeness</div><div class="mt-2 text-2xl font-black"><?= (int)$completeness['cbms'] ?>%</div></div>
        </div>
    </div>

    <div class="rounded-[2rem] border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-6 shadow-sm">
        <div class="text-sm text-slate-500">Special Program</div>
        <h3 class="text-2xl font-black">Crops and field activity</h3>
        <div class="mt-4 overflow-x-auto">
            <table class="min-w-full text-sm"><thead><tr class="text-left text-slate-500"><th class="py-2 pr-4">Crop</th><th class="py-2 pr-4">Trees</th><th class="py-2 pr-4">Condition</th><th class="py-2 pr-4">Fruiting</th></tr></thead><tbody>
                <?php foreach ($special['crops'] as $row): ?><tr class="border-t border-slate-200 dark:border-slate-800"><td class="py-3 pr-4"><?= e($row['crop_name'] ?? '-') ?></td><td class="py-3 pr-4"><?= e((string)($row['tree_count'] ?? 0)) ?></td><td class="py-3 pr-4"><?= e($row['current_condition'] ?? '-') ?></td><td class="py-3 pr-4"><?= e($row['fruiting_status'] ?? '-') ?></td></tr><?php endforeach; if (!$special['crops']): ?><tr><td colspan="4" class="py-4 text-slate-500">No crop records yet.</td></tr><?php endif; ?>
            </tbody></table>
        </div>
        <div class="mt-5 grid gap-4 md:grid-cols-2">
            <div class="rounded-2xl border border-slate-200 dark:border-slate-800 p-4"><div class="font-semibold mb-2">Interviews</div><?php foreach ($special['interviews'] as $row): ?><div class="text-sm text-slate-500 mb-2"><?= e(($row['interview_date'] ?: '-') . ' · ' . ($row['compliance_status'] ?: '')) ?></div><?php endforeach; if (!$special['interviews']): ?><div class="text-sm text-slate-500">No interviews yet.</div><?php endif; ?></div>
            <div class="rounded-2xl border border-slate-200 dark:border-slate-800 p-4"><div class="font-semibold mb-2">Monitoring</div><?php foreach ($special['monitoring'] as $row): ?><div class="text-sm text-slate-500 mb-2"><?= e(($row['monitoring_date'] ?: '-') . ' · ' . ($row['crop_condition'] ?: '')) ?></div><?php endforeach; if (!$special['monitoring']): ?><div class="text-sm text-slate-500">No monitoring yet.</div><?php endif; ?></div>
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
<section class="space-y-6">
    <div class="rounded-[2rem] border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-6 shadow-sm">
        <div class="text-sm text-slate-500">Beneficiaries</div>
        <h3 class="text-2xl font-black">Classifications and recommendations</h3>
        <div class="mt-4 space-y-3">
            <?php foreach ($beneficiaryProfiles as $bp): ?><div class="rounded-2xl border border-slate-200 dark:border-slate-800 p-4"><div class="flex gap-2 flex-wrap"><?php foreach (array_filter([($bp['indigent_status'] ?? null), ($bp['priority_level'] ?? null)]) as $label): ?><span class="app-badge app-badge-amber"><?= e($label) ?></span><?php endforeach; ?></div><div class="mt-2 text-sm text-slate-500"><?= e($bp['sector_tags'] ?? 'No sector tags') ?></div><div class="mt-2 text-sm"><?= e($bp['recommendation'] ?? 'No recommendation') ?></div></div><?php endforeach; if (!$beneficiaryProfiles): ?><div class="text-sm text-slate-500">No beneficiary records yet.</div><?php endif; ?>
        </div>
    </div>

    <div class="rounded-[2rem] border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-6 shadow-sm">
        <div class="text-sm text-slate-500">CBMS</div>
        <h3 class="text-2xl font-black">Pets, vehicles, housing, livelihood, sanitation, and assets</h3>
        <div class="mt-4 grid gap-4 md:grid-cols-2">
            <div class="rounded-2xl border border-slate-200 dark:border-slate-800 p-4"><div class="font-semibold mb-2">Housing</div><div class="text-sm text-slate-500"><?= e(($richer['housing']['housing_type'] ?? $cbmsProfile['housing_type'] ?? 'Not set') . ' · ' . ($richer['housing']['roof_material'] ?? 'Roof not set')) ?></div></div>
            <div class="rounded-2xl border border-slate-200 dark:border-slate-800 p-4"><div class="font-semibold mb-2">Livelihood</div><div class="text-sm text-slate-500"><?= e(($richer['livelihood']['main_livelihood'] ?? 'Not set') . ' · ' . ($richer['livelihood']['monthly_income_band'] ?? 'Income not set')) ?></div></div>
            <div class="rounded-2xl border border-slate-200 dark:border-slate-800 p-4"><div class="font-semibold mb-2">Sanitation</div><div class="text-sm text-slate-500"><?= e(($richer['sanitation']['water_source'] ?? $cbmsProfile['water_source'] ?? 'Water not set') . ' · ' . ($richer['sanitation']['toilet_type'] ?? $cbmsProfile['toilet_type'] ?? 'Toilet not set')) ?></div></div>
            <div class="rounded-2xl border border-slate-200 dark:border-slate-800 p-4"><div class="font-semibold mb-2">Assets</div><div class="text-sm text-slate-500"><?= e(count($richer['assets'])) ?> extra assets · <?= e($house['pet_count']) ?> pets · <?= e($house['vehicle_count']) ?> vehicles</div></div>
        </div>
        <div class="mt-4 grid gap-4 md:grid-cols-2">
            <div class="rounded-2xl border border-slate-200 dark:border-slate-800 p-4"><div class="font-semibold mb-2">Pets / livestock</div><?php foreach ($assets['pets'] as $row): ?><div class="text-sm text-slate-500 mb-2"><?= e(cbms_display_type($row, 'Pet') . ' · ' . (string)($row['quantity'] ?? 1)) ?></div><?php endforeach; if (!$assets['pets']): ?><div class="text-sm text-slate-500">No pet records yet.</div><?php endif; ?></div>
            <div class="rounded-2xl border border-slate-200 dark:border-slate-800 p-4"><div class="font-semibold mb-2">Vehicles</div><?php foreach ($assets['vehicles'] as $row): ?><div class="text-sm text-slate-500 mb-2"><?= e(cbms_display_type($row, 'Vehicle') . ' · ' . (string)($row['quantity'] ?? 1)) ?></div><?php endforeach; if (!$assets['vehicles']): ?><div class="text-sm text-slate-500">No vehicle records yet.</div><?php endif; ?></div>
        </div>
    </div>

    <div class="rounded-[2rem] border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-6 shadow-sm">
        <div class="text-sm text-slate-500">Event invite preview</div>
        <h3 class="text-2xl font-black">Likely events for this family</h3>
        <div class="mt-4 space-y-3">
            <?php foreach ($eventPreview as $row): ?>
            <div class="rounded-2xl border border-slate-200 dark:border-slate-800 p-4"><div class="flex items-center justify-between gap-3"><div class="font-semibold"><?= e($row['event_name']) ?></div><div class="text-xs text-slate-500"><?= e($row['event_date'] ?: '-') ?></div></div><div class="mt-2"><?= render_badges($row['reasons'], 'emerald') ?></div><div class="mt-3"><a href="<?= e($row['attendance_url']) ?>" class="app-btn-outline">Open attendance</a></div></div>
            <?php endforeach; if (!$eventPreview): ?><div class="text-sm text-slate-500">No matching scheduled events right now.</div><?php endif; ?>
        </div>
    </div>

    <div class="rounded-[2rem] border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-6 shadow-sm">
        <div class="text-sm text-slate-500">Shared assistance</div>
        <h3 class="text-2xl font-black">Support history</h3>
        <div class="mt-4 overflow-x-auto"><table class="min-w-full text-sm"><thead><tr class="text-left text-slate-500"><th class="py-2 pr-4">Type</th><th class="py-2 pr-4">Status</th><th class="py-2 pr-4">Date</th></tr></thead><tbody><?php foreach ($assistance as $row): ?><tr class="border-t border-slate-200 dark:border-slate-800"><td class="py-3 pr-4"><?= e($row['assistance_type']) ?></td><td class="py-3 pr-4"><?= format_status_badge($row['assistance_status']) ?></td><td class="py-3 pr-4"><?= e($row['assistance_date'] ?: '-') ?></td></tr><?php endforeach; if (!$assistance): ?><tr><td colspan="3" class="py-4 text-slate-500">No assistance history yet.</td></tr><?php endif; ?></tbody></table></div>
    </div>
</section>
</div>
<?php app_require('app/includes/footer.php'); ?>
