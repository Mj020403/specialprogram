<?php
require_once dirname(__DIR__, 3) . '/app/bootstrap.php';
$conn = db_conn();
app_require('app/includes/auth.php');
app_require('app/includes/module_platform.php');
app_require('app/includes/helpers/module_family_views.php');
set_current_platform_module('cbms');
require_module_access(['task_force','mayor','admin','developer'], 'cbms');
ensure_module_family_support_schema($conn);
$user = current_user();
$id = (int)getv('id');
if ($id <= 0) {
    app_require('app/includes/header.php');
    echo '<div class="app-toast app-toast-error">Invalid family.</div>';
    app_require('app/includes/footer.php');
    exit;
}
$house = fetch_household_shared_summary($conn, $id);
if (!$house) {
    app_require('app/includes/header.php');
    echo '<div class="app-toast app-toast-error">Family not found.</div>';
    app_require('app/includes/footer.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string)post('action'));
    $redirectAnchor = '#cbms-assets';
    $ok = false;
    if ($action === 'save_cbms_housing') {
        $redirectAnchor = '#cbms-housing';
        $ok = save_cbms_profile_row($conn, 'cbms_housing_profiles', $id, $_POST, (int)($user['id'] ?? 0));
        if ($ok) {
            save_cbms_profile_row($conn, 'cbms_household_profiles', $id, $_POST, (int)($user['id'] ?? 0));
            set_flash('success', 'Housing section saved.');
        } else {
            set_flash('error', 'Unable to save the housing section.');
        }
    } elseif ($action === 'save_cbms_livelihood') {
        $redirectAnchor = '#cbms-livelihood';
        $ok = save_cbms_profile_row($conn, 'cbms_livelihood_profiles', $id, $_POST, (int)($user['id'] ?? 0));
        if ($ok) {
            save_cbms_profile_row($conn, 'cbms_household_profiles', $id, [
                'livelihood_summary' => trim((string)post('main_livelihood')),
            ], (int)($user['id'] ?? 0));
            set_flash('success', 'Livelihood section saved.');
        } else {
            set_flash('error', 'Unable to save the livelihood section.');
        }
    } elseif ($action === 'save_cbms_sanitation') {
        $redirectAnchor = '#cbms-sanitation';
        $ok = save_cbms_profile_row($conn, 'cbms_sanitation_profiles', $id, $_POST, (int)($user['id'] ?? 0));
        if ($ok) {
            save_cbms_profile_row($conn, 'cbms_household_profiles', $id, $_POST, (int)($user['id'] ?? 0));
            set_flash('success', 'Sanitation section saved.');
        } else {
            set_flash('error', 'Unable to save the sanitation section.');
        }
    } elseif (in_array($action, ['save_cbms_pet', 'save_cbms_vehicle', 'save_cbms_asset'], true)) {
        $kind = $action === 'save_cbms_pet' ? 'pet' : ($action === 'save_cbms_vehicle' ? 'vehicle' : 'asset');
        $recordId = (int)post('record_id');
        $savedId = save_cbms_asset_record($conn, $kind, $id, $_POST, (int)($user['id'] ?? 0), $recordId);
        if ($savedId > 0) {
            if ($kind === 'vehicle') {
                save_cbms_profile_row($conn, 'cbms_household_profiles', $id, [
                    'vehicle_count' => household_table_count_or_sum($conn, 'cbms_vehicles', $id),
                ], (int)($user['id'] ?? 0));
            }
            set_flash('success', ucfirst($kind) . ($recordId > 0 ? ' record updated.' : ' record added.'));
        } else {
            set_flash('error', 'Unable to save the ' . $kind . ' record. Please check the form values.');
        }
    } elseif (in_array($action, ['delete_cbms_pet', 'delete_cbms_vehicle', 'delete_cbms_asset'], true)) {
        $kind = $action === 'delete_cbms_pet' ? 'pet' : ($action === 'delete_cbms_vehicle' ? 'vehicle' : 'asset');
        $recordId = (int)post('record_id');
        $ok = delete_cbms_asset_record($conn, $kind, $id, $recordId, (int)($user['id'] ?? 0));
        if ($ok) {
            if ($kind === 'vehicle') {
                save_cbms_profile_row($conn, 'cbms_household_profiles', $id, [
                    'vehicle_count' => household_table_count_or_sum($conn, 'cbms_vehicles', $id),
                ], (int)($user['id'] ?? 0));
            }
            set_flash('success', ucfirst($kind) . ' record removed.');
        } else {
            set_flash('error', 'Unable to remove that ' . $kind . ' record.');
        }
    }
    header('Location: ' . app_url('modules/cbms/families/view.php?id=' . $id . $redirectAnchor));
    exit;
}

$house = fetch_household_shared_summary($conn, $id);
$cbmsProfile = fetch_cbms_household_profile($conn, $id);
$assets = fetch_cbms_assets($conn, $id);
$richer = fetch_cbms_richer_sections($conn, $id);
$timeline = fetch_household_timeline($conn, $id, 10);
$completeness = compute_module_completeness($conn, $house);
$actions = module_quick_actions('cbms', $id);
$cards = [
    ['label'=>'Family members','value'=>$house['member_count'],'hint'=>'Shared household members'],
    ['label'=>'Pets / livestock','value'=>$house['pet_count'],'hint'=>'CBMS records'],
    ['label'=>'Vehicles','value'=>$house['vehicle_count'],'hint'=>'CBMS records'],
    ['label'=>'CBMS readiness','value'=>$completeness['cbms'] . '%','hint'=>'Household profile completeness'],
];

$editKind = trim((string)getv('edit_kind'));
$editId = (int)getv('edit_id');
$editRecord = in_array($editKind, ['pet', 'vehicle', 'asset'], true) && $editId > 0 ? fetch_cbms_edit_record($conn, $editKind, $id, $editId) : null;
$petEdit = $editKind === 'pet' ? ($editRecord ?: []) : [];
$vehicleEdit = $editKind === 'vehicle' ? ($editRecord ?: []) : [];
$assetEdit = $editKind === 'asset' ? ($editRecord ?: []) : [];
$housingData = array_merge($cbmsProfile ?: [], $richer['housing'] ?: []);
$livelihoodData = $richer['livelihood'] ?: [];
$sanitationData = array_merge($cbmsProfile ?: [], $richer['sanitation'] ?: []);

$petOptions = [
    'Dog', 'Cat', 'Chicken', 'Duck', 'Goat', 'Pig', 'Cow', 'Carabao', 'Horse', 'Sheep', 'Rabbit', 'Pigeon', 'Turkey', 'Goose'
];
$vehicleOptions = [
    'Bicycle', 'Motorcycle', 'Tricycle', 'E-bike', 'E-trike', 'Sidecar', 'Jeep', 'Jeepney', 'Multicab', 'Van', 'SUV', 'Pickup', 'Sedan', 'Truck', 'Mini truck', 'Hand tractor', 'Kuliglig', 'Farm cart', 'Boat'
];
$assetGroups = [
    'Home appliances' => ['Electric fan', 'Television', 'Refrigerator', 'Washing machine', 'Rice cooker', 'Gas stove', 'Induction cooker'],
    'Electronics' => ['Mobile phone', 'Smartphone', 'Tablet', 'Laptop', 'Desktop computer', 'Printer', 'Wi-Fi router'],
    'Furniture and household' => ['Bed', 'Cabinet', 'Dining table', 'Sofa', 'Water container'],
    'Farm tools and equipment' => ['Sprayer', 'Grass cutter', 'Chainsaw', 'Shovel', 'Hoe', 'Wheelbarrow', 'Water pump', 'Generator', 'Solar dryer'],
    'Livelihood assets' => ['Sari-sari store items', 'Freezer', 'Sewing machine', 'Vulcanizing tools', 'Cooking tools']
];
$assetOptionsFlat = [];
foreach ($assetGroups as $groupOptions) {
    foreach ($groupOptions as $groupOption) {
        $assetOptionsFlat[] = $groupOption;
    }
}

app_require('app/includes/header.php');
echo nav_cards($cards);
?>
<div class="space-y-6">
<section class="space-y-6">
    <div class="rounded-[2rem] border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-6 shadow-sm">
        <div class="flex items-start gap-4 flex-wrap sm:flex-nowrap">
            <img src="<?= e($house['photo_url']) ?>" alt="Family" class="h-24 w-24 rounded-[2rem] object-cover border border-slate-200 dark:border-slate-800">
            <div class="min-w-0 flex-1">
                <div class="text-sm text-slate-500">CBMS family view</div>
                <h2 class="text-4xl font-black"><?= e($house['household_head_name']) ?></h2>
                <div class="mt-2 text-slate-500"><?= e(($house['household_code'] ?: 'No HH code') . ' · ' . ($house['barangay_name'] ?: 'No barangay')) ?></div>
                <div class="mt-4"><?= render_badges(detect_household_tags($conn, $house), 'sky') ?></div>
            </div>
        </div>
        <div class="mt-6 grid gap-4 md:grid-cols-2">
            <div class="rounded-3xl border border-slate-200 dark:border-slate-800 p-4"><div class="text-sm text-slate-500">Contact</div><div class="mt-2 font-semibold"><?= e($house['contact_number'] ?: '-') ?></div></div>
            <div class="rounded-3xl border border-slate-200 dark:border-slate-800 p-4"><div class="text-sm text-slate-500">Address</div><div class="mt-2 font-semibold"><?= e($house['full_address'] ?: $house['purok_sitio'] ?: '-') ?></div></div>
        </div>
    </div>

    <div class="rounded-[2rem] border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-6 shadow-sm">
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

    <div class="rounded-[2rem] border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-6 shadow-sm">
        <div class="text-sm text-slate-500">Module quick actions</div>
        <h3 class="text-2xl font-black">CBMS actions for this family</h3>
        <div class="mt-4"><?= render_quick_actions($actions) ?></div>
    </div>

    <div class="rounded-[2rem] border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-6 shadow-sm" id="cbms-housing">
        <div class="text-sm text-slate-500">CBMS household profile</div>
        <h3 class="text-2xl font-black">Housing and household context</h3>
        <?php if ($housingData): ?>
        <div class="mt-4 grid gap-4 md:grid-cols-2">
            <?php foreach ([
                'housing_type'=>'Housing type','roof_material'=>'Roof material','wall_material'=>'Wall material','tenure_status'=>'Tenure status',
                'electricity_source'=>'Electricity source','water_source'=>'Water source','toilet_type'=>'Toilet type','notes'=>'Notes'
            ] as $key=>$label): if (cbms_profile_form_value($housingData, [], $key) === '') continue; ?>
            <div class="rounded-2xl border border-slate-200 dark:border-slate-800 p-4"><div class="text-sm text-slate-500"><?= e($label) ?></div><div class="mt-1 font-semibold"><?= e(cbms_profile_form_value($housingData, [], $key)) ?></div></div>
            <?php endforeach; ?>
        </div>
        <?php else: ?><div class="mt-4 text-sm text-slate-500">No housing profile yet.</div><?php endif; ?>
        <details class="mt-5 rounded-3xl border border-slate-200 dark:border-slate-800 p-4">
            <summary class="cursor-pointer font-semibold">Add or update housing section</summary>
            <form method="POST" class="mt-4 grid gap-3 md:grid-cols-2">
                <input type="hidden" name="action" value="save_cbms_housing">
                <?php foreach (['housing_type'=>'Housing type','roof_material'=>'Roof material','wall_material'=>'Wall material','tenure_status'=>'Tenure status','electricity_source'=>'Electricity source','water_source'=>'Water source','toilet_type'=>'Toilet type'] as $key=>$label): ?>
                <div><label class="block text-sm font-semibold mb-2"><?= e($label) ?></label><input name="<?= e($key) ?>" value="<?= e(cbms_profile_form_value($housingData, [], $key)) ?>" class="w-full rounded-2xl border px-4 py-3"></div>
                <?php endforeach; ?>
                <div class="md:col-span-2"><label class="block text-sm font-semibold mb-2">Notes</label><textarea name="notes" rows="3" class="w-full rounded-2xl border px-4 py-3"><?= e(cbms_profile_form_value($housingData, [], 'notes')) ?></textarea></div>
                <div class="md:col-span-2"><button class="app-btn-primary">Save housing section</button></div>
            </form>
        </details>
    </div>

    <div class="rounded-[2rem] border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-6 shadow-sm" id="cbms-livelihood">
        <div class="text-sm text-slate-500">Livelihood profile</div>
        <h3 class="text-2xl font-black">Income and livelihood context</h3>
        <?php if ($livelihoodData): ?>
        <div class="mt-4 grid gap-4 md:grid-cols-2">
            <?php foreach (['primary_income_source'=>'Primary income source','main_livelihood'=>'Main livelihood','monthly_income_band'=>'Monthly income band','employment_notes'=>'Employment notes','notes'=>'Notes'] as $key=>$label): if (cbms_profile_form_value($livelihoodData, [], $key) === '') continue; ?>
            <div class="rounded-2xl border border-slate-200 dark:border-slate-800 p-4"><div class="text-sm text-slate-500"><?= e($label) ?></div><div class="mt-1 font-semibold"><?= e(cbms_profile_form_value($livelihoodData, [], $key)) ?></div></div>
            <?php endforeach; ?>
        </div>
        <?php else: ?><div class="mt-4 text-sm text-slate-500">No livelihood section yet.</div><?php endif; ?>
        <details class="mt-5 rounded-3xl border border-slate-200 dark:border-slate-800 p-4">
            <summary class="cursor-pointer font-semibold">Add or update livelihood section</summary>
            <form method="POST" class="mt-4 grid gap-3 md:grid-cols-2">
                <input type="hidden" name="action" value="save_cbms_livelihood">
                <div><label class="block text-sm font-semibold mb-2">Primary income source</label><input name="primary_income_source" value="<?= e(cbms_profile_form_value($livelihoodData, [], 'primary_income_source')) ?>" class="w-full rounded-2xl border px-4 py-3"></div>
                <div><label class="block text-sm font-semibold mb-2">Main livelihood</label><input name="main_livelihood" value="<?= e(cbms_profile_form_value($livelihoodData, [], 'main_livelihood')) ?>" class="w-full rounded-2xl border px-4 py-3"></div>
                <div><label class="block text-sm font-semibold mb-2">Monthly income band</label><input name="monthly_income_band" value="<?= e(cbms_profile_form_value($livelihoodData, [], 'monthly_income_band')) ?>" class="w-full rounded-2xl border px-4 py-3"></div>
                <div><label class="block text-sm font-semibold mb-2">Employment notes</label><input name="employment_notes" value="<?= e(cbms_profile_form_value($livelihoodData, [], 'employment_notes')) ?>" class="w-full rounded-2xl border px-4 py-3"></div>
                <div class="md:col-span-2"><label class="block text-sm font-semibold mb-2">Notes</label><textarea name="notes" rows="3" class="w-full rounded-2xl border px-4 py-3"><?= e(cbms_profile_form_value($livelihoodData, [], 'notes')) ?></textarea></div>
                <div class="md:col-span-2"><button class="app-btn-primary">Save livelihood section</button></div>
            </form>
        </details>
    </div>

    <div class="rounded-[2rem] border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-6 shadow-sm" id="cbms-assets">
        <div>
            <div class="text-sm text-slate-500">Assets and mobility</div>
            <h3 class="text-2xl font-black">Pets, vehicles, and household assets</h3>
            <div class="mt-1 text-sm text-slate-500">Use dropdowns for faster entry, then fill in the extra details only when needed.</div>
            <div class="mt-4 inline-flex rounded-2xl border border-slate-200 dark:border-slate-800 px-4 py-3 text-sm text-slate-500">Pets: <strong class="ml-1"><?= e((string)$house['pet_count']) ?></strong> · Vehicles: <strong class="mx-1"><?= e((string)$house['vehicle_count']) ?></strong> · Other assets: <strong class="ml-1"><?= e((string)count($richer['assets'])) ?></strong></div>
        </div>

        <div class="mt-5 grid gap-4 lg:grid-cols-3">
            <div class="rounded-3xl border border-slate-200 dark:border-slate-800 p-4 bg-slate-50/60 dark:bg-slate-900/30">
                <div class="font-semibold mb-2">Saved pets / livestock</div>
                <?php foreach ($assets['pets'] as $row): $rid = (int)($row['pet_id'] ?? 0); ?>
                <div class="mb-3 rounded-2xl border border-slate-200 dark:border-slate-800 p-3 bg-white dark:bg-slate-950">
                    <div class="font-semibold"><?= e(cbms_display_type($row, 'Pet')) ?></div>
                    <div class="text-sm text-slate-500">Quantity: <?= e((string)cbms_asset_quantity($row)) ?><?= !empty($row['animal_name']) ? ' · Name: ' . e((string)$row['animal_name']) : '' ?></div>
                    <?php if (!empty($row['notes'])): ?><div class="text-sm text-slate-500 mt-1"><?= e((string)$row['notes']) ?></div><?php endif; ?>
                    <div class="mt-3 flex gap-2 flex-wrap">
                        <a class="app-btn-outline" href="<?= e(app_url('modules/cbms/families/view.php?id=' . $id . '&edit_kind=pet&edit_id=' . $rid . '#cbms-assets')) ?>">Edit</a>
                        <form method="POST" class="contents" onsubmit="return confirm('Remove this pet or livestock record?');">
                            <input type="hidden" name="action" value="delete_cbms_pet">
                            <input type="hidden" name="record_id" value="<?= e((string)$rid) ?>">
                            <button class="app-btn-outline">Delete</button>
                        </form>
                    </div>
                </div>
                <?php endforeach; if (!$assets['pets']): ?><div class="text-sm text-slate-500">No pet or livestock records yet.</div><?php endif; ?>
            </div>
            <div class="rounded-3xl border border-slate-200 dark:border-slate-800 p-4 bg-slate-50/60 dark:bg-slate-900/30">
                <div class="font-semibold mb-2">Saved vehicles</div>
                <?php foreach ($assets['vehicles'] as $row): $rid = (int)($row['cbms_vehicle_id'] ?? 0); ?>
                <div class="mb-3 rounded-2xl border border-slate-200 dark:border-slate-800 p-3 bg-white dark:bg-slate-950">
                    <div class="font-semibold"><?= e(cbms_display_type($row, 'Vehicle')) ?></div>
                    <div class="text-sm text-slate-500">Quantity: <?= e((string)cbms_asset_quantity($row)) ?></div>
                    <?php if (cbms_vehicle_details_meta($row) !== ''): ?><div class="text-sm text-slate-500 mt-1"><?= e(cbms_vehicle_details_meta($row)) ?></div><?php endif; ?>
                    <?php if (!empty($row['notes'])): ?><div class="text-sm text-slate-500 mt-1"><?= e((string)$row['notes']) ?></div><?php endif; ?>
                    <div class="mt-3 flex gap-2 flex-wrap">
                        <a class="app-btn-outline" href="<?= e(app_url('modules/cbms/families/view.php?id=' . $id . '&edit_kind=vehicle&edit_id=' . $rid . '#cbms-assets')) ?>">Edit</a>
                        <form method="POST" class="contents" onsubmit="return confirm('Remove this vehicle record?');">
                            <input type="hidden" name="action" value="delete_cbms_vehicle">
                            <input type="hidden" name="record_id" value="<?= e((string)$rid) ?>">
                            <button class="app-btn-outline">Delete</button>
                        </form>
                    </div>
                </div>
                <?php endforeach; if (!$assets['vehicles']): ?><div class="text-sm text-slate-500">No vehicle records yet.</div><?php endif; ?>
            </div>
            <div class="rounded-3xl border border-slate-200 dark:border-slate-800 p-4 bg-slate-50/60 dark:bg-slate-900/30">
                <div class="font-semibold mb-2">Saved other assets</div>
                <?php foreach ($richer['assets'] as $row): $rid = (int)($row['asset_id'] ?? 0); ?>
                <div class="mb-3 rounded-2xl border border-slate-200 dark:border-slate-800 p-3 bg-white dark:bg-slate-950">
                    <div class="font-semibold"><?= e((string)($row['asset_name'] ?? 'Asset')) ?></div>
                    <div class="text-sm text-slate-500">Quantity: <?= e((string)cbms_asset_quantity($row)) ?></div>
                    <?php if (cbms_asset_details_meta($row) !== ''): ?><div class="text-sm text-slate-500 mt-1"><?= e(cbms_asset_details_meta($row)) ?></div><?php endif; ?>
                    <?php if (!empty($row['notes'])): ?><div class="text-sm text-slate-500 mt-1"><?= e((string)$row['notes']) ?></div><?php endif; ?>
                    <div class="mt-3 flex gap-2 flex-wrap">
                        <a class="app-btn-outline" href="<?= e(app_url('modules/cbms/families/view.php?id=' . $id . '&edit_kind=asset&edit_id=' . $rid . '#cbms-assets')) ?>">Edit</a>
                        <form method="POST" class="contents" onsubmit="return confirm('Remove this asset record?');">
                            <input type="hidden" name="action" value="delete_cbms_asset">
                            <input type="hidden" name="record_id" value="<?= e((string)$rid) ?>">
                            <button class="app-btn-outline">Delete</button>
                        </form>
                    </div>
                </div>
                <?php endforeach; if (!$richer['assets']): ?><div class="text-sm text-slate-500">No other asset records yet.</div><?php endif; ?>
            </div>
        </div>

        <div class="mt-5 space-y-4">
            <details class="rounded-3xl border border-slate-200 dark:border-slate-800 p-4" <?= $editKind === 'pet' ? 'open' : '' ?>>
                <summary class="cursor-pointer font-semibold"><?= $petEdit ? 'Edit pet / livestock' : 'Add pet / livestock' ?></summary>
                <form method="POST" class="mt-4 grid gap-3 md:grid-cols-2">
                    <input type="hidden" name="action" value="save_cbms_pet">
                    <input type="hidden" name="record_id" value="<?= e((string)($petEdit['pet_id'] ?? 0)) ?>">
                    <div><label class="block text-sm font-semibold mb-2">Pet or livestock type</label><select name="item_type" class="w-full rounded-2xl border px-4 py-3" required><option value="">Select type</option><?php $selectedPet = cbms_asset_record_label($petEdit, ''); foreach ($petOptions as $option): ?><option value="<?= e($option) ?>" <?= $selectedPet === $option ? 'selected' : '' ?>><?= e($option) ?></option><?php endforeach; ?></select></div>
                    <div><label class="block text-sm font-semibold mb-2">If other, specify</label><input name="item_type_other" value="<?= e(!in_array(cbms_asset_record_label($petEdit, ''), $petOptions, true) ? cbms_asset_record_label($petEdit, '') : '') ?>" placeholder="Example: Peacock" class="w-full rounded-2xl border px-4 py-3"></div>
                    <div><label class="block text-sm font-semibold mb-2">Name (optional)</label><input name="animal_name" value="<?= e((string)($petEdit['animal_name'] ?? '')) ?>" class="w-full rounded-2xl border px-4 py-3"></div>
                    <div><label class="block text-sm font-semibold mb-2">Quantity</label><input type="number" min="1" name="quantity" value="<?= e((string)cbms_asset_quantity($petEdit)) ?>" class="w-full rounded-2xl border px-4 py-3"></div>
                    <div class="md:col-span-2"><label class="block text-sm font-semibold mb-2">Notes</label><textarea name="notes" rows="3" class="w-full rounded-2xl border px-4 py-3"><?= e((string)($petEdit['notes'] ?? '')) ?></textarea></div>
                    <div class="md:col-span-2 flex gap-2 flex-wrap"><button class="app-btn-primary"><?= $petEdit ? 'Update pet / livestock' : 'Add pet / livestock' ?></button><?php if ($petEdit): ?><a class="app-btn-outline" href="<?= e(app_url('modules/cbms/families/view.php?id=' . $id . '#cbms-assets')) ?>">Cancel</a><?php endif; ?></div>
                </form>
            </details>

            <details class="rounded-3xl border border-slate-200 dark:border-slate-800 p-4" <?= $editKind === 'vehicle' ? 'open' : '' ?>>
                <summary class="cursor-pointer font-semibold"><?= $vehicleEdit ? 'Edit vehicle' : 'Add vehicle' ?></summary>
                <form method="POST" class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                    <input type="hidden" name="action" value="save_cbms_vehicle">
                    <input type="hidden" name="record_id" value="<?= e((string)($vehicleEdit['cbms_vehicle_id'] ?? 0)) ?>">
                    <div class="xl:col-span-3"><label class="block text-sm font-semibold mb-2">Vehicle type</label><select name="item_type" class="w-full rounded-2xl border px-4 py-3" required><option value="">Select vehicle</option><?php $selectedVehicle = cbms_asset_record_label($vehicleEdit, ''); foreach ($vehicleOptions as $option): ?><option value="<?= e($option) ?>" <?= $selectedVehicle === $option ? 'selected' : '' ?>><?= e($option) ?></option><?php endforeach; ?></select></div>
                    <div class="xl:col-span-3"><label class="block text-sm font-semibold mb-2">If other, specify</label><input name="item_type_other" value="<?= e(!in_array(cbms_asset_record_label($vehicleEdit, ''), $vehicleOptions, true) ? cbms_asset_record_label($vehicleEdit, '') : '') ?>" placeholder="Example: Delivery cart" class="w-full rounded-2xl border px-4 py-3"></div>
                    <div><label class="block text-sm font-semibold mb-2">Brand</label><input name="vehicle_brand" value="<?= e((string)($vehicleEdit['vehicle_brand'] ?? '')) ?>" placeholder="Honda, Yamaha, Toyota" class="w-full rounded-2xl border px-4 py-3"></div>
                    <div><label class="block text-sm font-semibold mb-2">Model / variant</label><input name="vehicle_model" value="<?= e((string)($vehicleEdit['vehicle_model'] ?? '')) ?>" placeholder="Raider, Vios, HiAce" class="w-full rounded-2xl border px-4 py-3"></div>
                    <div><label class="block text-sm font-semibold mb-2">Year model</label><input name="year_model" value="<?= e((string)($vehicleEdit['year_model'] ?? '')) ?>" placeholder="2020" class="w-full rounded-2xl border px-4 py-3"></div>
                    <div><label class="block text-sm font-semibold mb-2">Plate number</label><input name="plate_number" value="<?= e((string)($vehicleEdit['plate_number'] ?? '')) ?>" placeholder="ABC 1234" class="w-full rounded-2xl border px-4 py-3"></div>
                    <div><label class="block text-sm font-semibold mb-2">Color</label><input name="color" value="<?= e((string)($vehicleEdit['color'] ?? '')) ?>" class="w-full rounded-2xl border px-4 py-3"></div>
                    <div><label class="block text-sm font-semibold mb-2">Quantity</label><input type="number" min="1" name="quantity" value="<?= e((string)cbms_asset_quantity($vehicleEdit)) ?>" class="w-full rounded-2xl border px-4 py-3"></div>
                    <div><label class="block text-sm font-semibold mb-2">Ownership status</label><input name="ownership_status" value="<?= e((string)($vehicleEdit['ownership_status'] ?? '')) ?>" placeholder="Owned, Shared, Borrowed" class="w-full rounded-2xl border px-4 py-3"></div>
                    <div><label class="block text-sm font-semibold mb-2">Registration status</label><input name="registration_status" value="<?= e((string)($vehicleEdit['registration_status'] ?? '')) ?>" placeholder="Registered, Expired, N/A" class="w-full rounded-2xl border px-4 py-3"></div>
                    <div class="md:col-span-2 xl:col-span-3"><label class="block text-sm font-semibold mb-2">Notes</label><textarea name="notes" rows="3" class="w-full rounded-2xl border px-4 py-3"><?= e((string)($vehicleEdit['notes'] ?? '')) ?></textarea></div>
                    <div class="md:col-span-2 xl:col-span-3 flex gap-2 flex-wrap"><button class="app-btn-primary"><?= $vehicleEdit ? 'Update vehicle' : 'Add vehicle' ?></button><?php if ($vehicleEdit): ?><a class="app-btn-outline" href="<?= e(app_url('modules/cbms/families/view.php?id=' . $id . '#cbms-assets')) ?>">Cancel</a><?php endif; ?></div>
                </form>
            </details>

            <details class="rounded-3xl border border-slate-200 dark:border-slate-800 p-4" <?= $editKind === 'asset' ? 'open' : '' ?>>
                <summary class="cursor-pointer font-semibold"><?= $assetEdit ? 'Edit other asset' : 'Add other asset' ?></summary>
                <form method="POST" class="mt-4 grid gap-3 md:grid-cols-2">
                    <input type="hidden" name="action" value="save_cbms_asset">
                    <input type="hidden" name="record_id" value="<?= e((string)($assetEdit['asset_id'] ?? 0)) ?>">
                    <div class="md:col-span-2"><label class="block text-sm font-semibold mb-2">Asset type</label><select name="item_type" class="w-full rounded-2xl border px-4 py-3" required><option value="">Select asset</option><?php $selectedAsset = cbms_asset_record_label($assetEdit, ''); foreach ($assetGroups as $groupLabel => $groupOptions): ?><optgroup label="<?= e($groupLabel) ?>"><?php foreach ($groupOptions as $option): ?><option value="<?= e($option) ?>" <?= $selectedAsset === $option ? 'selected' : '' ?>><?= e($option) ?></option><?php endforeach; ?></optgroup><?php endforeach; ?></select></div>
                    <div class="md:col-span-2"><label class="block text-sm font-semibold mb-2">If other, specify</label><input name="item_type_other" value="<?= e(!in_array(cbms_asset_record_label($assetEdit, ''), $assetOptionsFlat, true) ? cbms_asset_record_label($assetEdit, '') : '') ?>" placeholder="Example: Solar dryer" class="w-full rounded-2xl border px-4 py-3"></div>
                    <div><label class="block text-sm font-semibold mb-2">Asset category</label><input name="asset_category" value="<?= e((string)($assetEdit['asset_category'] ?? '')) ?>" placeholder="Home appliance, Farm tool, Electronics" class="w-full rounded-2xl border px-4 py-3"></div>
                    <div><label class="block text-sm font-semibold mb-2">Brand (optional)</label><input name="asset_brand" value="<?= e((string)($assetEdit['asset_brand'] ?? '')) ?>" class="w-full rounded-2xl border px-4 py-3"></div>
                    <div><label class="block text-sm font-semibold mb-2">Model / details</label><input name="asset_model" value="<?= e((string)($assetEdit['asset_model'] ?? '')) ?>" class="w-full rounded-2xl border px-4 py-3"></div>
                    <div><label class="block text-sm font-semibold mb-2">Quantity</label><input type="number" min="1" name="quantity" value="<?= e((string)cbms_asset_quantity($assetEdit)) ?>" class="w-full rounded-2xl border px-4 py-3"></div>
                    <div class="md:col-span-2"><label class="block text-sm font-semibold mb-2">Notes</label><textarea name="notes" rows="3" class="w-full rounded-2xl border px-4 py-3"><?= e((string)($assetEdit['notes'] ?? '')) ?></textarea></div>
                    <div class="md:col-span-2 flex gap-2 flex-wrap"><button class="app-btn-primary"><?= $assetEdit ? 'Update asset' : 'Add asset' ?></button><?php if ($assetEdit): ?><a class="app-btn-outline" href="<?= e(app_url('modules/cbms/families/view.php?id=' . $id . '#cbms-assets')) ?>">Cancel</a><?php endif; ?></div>
                </form>
            </details>
        </div>
    </div>

    <div class="rounded-[2rem] border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-6 shadow-sm" id="cbms-sanitation">
        <div class="text-sm text-slate-500">Sanitation</div>
        <h3 class="text-2xl font-black">Water, toilet, and waste handling</h3>
        <?php if ($sanitationData): ?>
        <div class="mt-4 grid gap-4 md:grid-cols-2">
            <?php foreach (['water_source'=>'Water source','toilet_type'=>'Toilet type','waste_disposal'=>'Waste disposal','drainage_status'=>'Drainage status','notes'=>'Notes'] as $key=>$label): if (cbms_profile_form_value($sanitationData, [], $key) === '') continue; ?>
            <div class="rounded-2xl border border-slate-200 dark:border-slate-800 p-4"><div class="text-sm text-slate-500"><?= e($label) ?></div><div class="mt-1 font-semibold"><?= e(cbms_profile_form_value($sanitationData, [], $key)) ?></div></div>
            <?php endforeach; ?>
        </div>
        <?php else: ?><div class="mt-4 text-sm text-slate-500">No sanitation section yet.</div><?php endif; ?>
        <details class="mt-5 rounded-3xl border border-slate-200 dark:border-slate-800 p-4">
            <summary class="cursor-pointer font-semibold">Add or update sanitation section</summary>
            <form method="POST" class="mt-4 grid gap-3 md:grid-cols-2">
                <input type="hidden" name="action" value="save_cbms_sanitation">
                <div><label class="block text-sm font-semibold mb-2">Water source</label><input name="water_source" value="<?= e(cbms_profile_form_value($sanitationData, [], 'water_source')) ?>" class="w-full rounded-2xl border px-4 py-3"></div>
                <div><label class="block text-sm font-semibold mb-2">Toilet type</label><input name="toilet_type" value="<?= e(cbms_profile_form_value($sanitationData, [], 'toilet_type')) ?>" class="w-full rounded-2xl border px-4 py-3"></div>
                <div><label class="block text-sm font-semibold mb-2">Waste disposal</label><input name="waste_disposal" value="<?= e(cbms_profile_form_value($sanitationData, [], 'waste_disposal')) ?>" class="w-full rounded-2xl border px-4 py-3"></div>
                <div><label class="block text-sm font-semibold mb-2">Drainage status</label><input name="drainage_status" value="<?= e(cbms_profile_form_value($sanitationData, [], 'drainage_status')) ?>" class="w-full rounded-2xl border px-4 py-3"></div>
                <div class="md:col-span-2"><label class="block text-sm font-semibold mb-2">Notes</label><textarea name="notes" rows="3" class="w-full rounded-2xl border px-4 py-3"><?= e(cbms_profile_form_value($sanitationData, [], 'notes')) ?></textarea></div>
                <div class="md:col-span-2"><button class="app-btn-primary">Save sanitation section</button></div>
            </form>
        </details>
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
