<?php
require_once dirname(__DIR__, 3) . '/app/bootstrap.php';
$conn = db_conn();
app_require('app/includes/auth.php');
app_require('app/includes/module_platform.php');
app_require('app/includes/helpers/module_family_views.php');
require_role(['task_force','mayor','admin','developer']);
ensure_family_upgrade_schema($conn);
ensure_module_family_support_schema($conn);
ensure_golden_household_schema($conn);
$user = current_user();

$selectedHouseholdId = (int)($_GET['selected_household_id'] ?? $_POST['household_id'] ?? 0);
$redirectBase = app_url('modules/agri/cbms_lite/index.php');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && $selectedHouseholdId > 0) {
    $action = trim((string)($_POST['action'] ?? ''));
    $ok = false;
    $msg = 'Update saved.';
    $err = 'Unable to save the CBMS-lite update.';

    if (in_array($action, ['save_sp_housing','save_sp_livelihood','save_sp_sanitation','save_sp_flags'], true) && !in_array($user['role'], ['task_force','admin','developer'], true)) {
        set_flash('error', 'You do not have permission to update CBMS-lite profiles.');
        redirect_to('modules/agri/cbms_lite/index.php?selected_household_id=' . $selectedHouseholdId);
    }

    if ($action === 'save_sp_housing') {
        $housingPayload = [
            'housing_type' => sp_cbms_normalize_other($_POST, 'housing_type'),
            'tenure_status' => sp_cbms_normalize_other($_POST, 'tenure_status'),
            'roof_material' => sp_cbms_normalize_other($_POST, 'roof_material'),
            'wall_material' => sp_cbms_normalize_other($_POST, 'wall_material'),
            'electricity_source' => sp_cbms_normalize_other($_POST, 'electricity_source'),
            'notes' => trim((string)($_POST['notes'] ?? '')) ?: null,
        ];
        $ok = save_cbms_profile_row($conn, 'cbms_housing_profiles', $selectedHouseholdId, $housingPayload, (int)($user['id'] ?? 0));
        if ($ok) {
            save_cbms_profile_row($conn, 'cbms_household_profiles', $selectedHouseholdId, [
                'housing_type' => $housingPayload['housing_type'],
                'tenure_status' => $housingPayload['tenure_status'],
                'housing_materials' => trim((string)($housingPayload['roof_material'] ?? '') . ' / ' . (string)($housingPayload['wall_material'] ?? ''), ' /'),
                'electricity_source' => $housingPayload['electricity_source'],
                'internet_access' => trim((string)($_POST['internet_access'] ?? '')) ?: null,
                'notes' => $housingPayload['notes'],
            ], (int)($user['id'] ?? 0));
            $msg = 'Housing profile saved.';
        }
    } elseif ($action === 'save_sp_livelihood') {
        $livelihoodPayload = [
            'primary_income_source' => sp_cbms_normalize_other($_POST, 'primary_income_source'),
            'main_livelihood' => sp_cbms_normalize_other($_POST, 'main_livelihood'),
            'monthly_income_band' => sp_cbms_normalize_other($_POST, 'monthly_income_band'),
            'employment_notes' => trim((string)($_POST['employment_notes'] ?? '')) ?: null,
            'notes' => trim((string)($_POST['special_program_notes'] ?? '')) ?: null,
        ];
        $ok = save_cbms_profile_row($conn, 'cbms_livelihood_profiles', $selectedHouseholdId, $livelihoodPayload, (int)($user['id'] ?? 0));
        if ($ok) {
            save_cbms_profile_row($conn, 'cbms_household_profiles', $selectedHouseholdId, [
                'livelihood_summary' => $livelihoodPayload['main_livelihood'],
                'main_livelihood' => $livelihoodPayload['main_livelihood'],
                'monthly_income_band' => $livelihoodPayload['monthly_income_band'],
                'monthly_household_income' => ($_POST['monthly_household_income'] ?? '') !== '' ? (float)$_POST['monthly_household_income'] : null,
                'poverty_status' => trim((string)($_POST['poverty_status'] ?? '')) ?: null,
                'crop_summary' => trim((string)($_POST['crop_summary'] ?? '')) ?: null,
                'farming_household' => !empty($_POST['farming_household']) ? 1 : 0,
                'farm_area_hectares' => ($_POST['farm_area_hectares'] ?? '') !== '' ? (float)$_POST['farm_area_hectares'] : null,
                'fruit_tree_count_estimate' => ($_POST['fruit_tree_count_estimate'] ?? '') !== '' ? (int)$_POST['fruit_tree_count_estimate'] : null,
                'special_program_notes' => trim((string)($_POST['special_program_notes'] ?? '')) ?: null,
            ], (int)($user['id'] ?? 0));
            $msg = 'Livelihood profile saved.';
        }
    } elseif ($action === 'save_sp_sanitation') {
        $sanitationPayload = [
            'water_source' => sp_cbms_normalize_other($_POST, 'water_source'),
            'toilet_type' => sp_cbms_normalize_other($_POST, 'toilet_type'),
            'waste_disposal' => sp_cbms_normalize_other($_POST, 'waste_disposal'),
            'drainage_status' => sp_cbms_normalize_other($_POST, 'drainage_status'),
            'notes' => trim((string)($_POST['sanitation_notes'] ?? '')) ?: null,
        ];
        $ok = save_cbms_profile_row($conn, 'cbms_sanitation_profiles', $selectedHouseholdId, $sanitationPayload, (int)($user['id'] ?? 0));
        if ($ok) {
            save_cbms_profile_row($conn, 'cbms_household_profiles', $selectedHouseholdId, [
                'water_source' => $sanitationPayload['water_source'],
                'toilet_type' => $sanitationPayload['toilet_type'],
                'waste_disposal_method' => $sanitationPayload['waste_disposal'],
                'notes' => $sanitationPayload['notes'],
            ], (int)($user['id'] ?? 0));
            $msg = 'Water, toilet, and waste profile saved.';
        }
    } elseif ($action === 'save_sp_flags') {
        $ok = save_household_beneficiary_flags($conn, $selectedHouseholdId, $_POST, (int)($user['id'] ?? 0));
        $msg = 'Beneficiary flags saved.';
        $err = 'Unable to save beneficiary flags.';
    } elseif ($action === 'save_cbms_asset') {
        $kind = trim((string)($_POST['kind'] ?? ''));
        $recordId = (int)($_POST['record_id'] ?? 0);
        $newId = save_cbms_asset_record($conn, $kind, $selectedHouseholdId, $_POST, (int)($user['id'] ?? 0), $recordId);
        $ok = $newId > 0;
        $msg = ucfirst($kind) . ' record saved.';
        $err = 'Unable to save the ' . $kind . ' record.';
    } elseif ($action === 'delete_cbms_asset') {
        $kind = trim((string)($_POST['kind'] ?? ''));
        $recordId = (int)($_POST['record_id'] ?? 0);
        $ok = delete_cbms_asset_record($conn, $kind, $selectedHouseholdId, $recordId, (int)($user['id'] ?? 0));
        $msg = ucfirst($kind) . ' record removed.';
        $err = 'Unable to remove that record.';
    }

    if ($action !== '') {
        set_flash($ok ? 'success' : 'error', $ok ? $msg : $err);
        redirect_to('modules/agri/cbms_lite/index.php?selected_household_id=' . $selectedHouseholdId);
    }
}

$selectedHousehold = null;
$selectedPrograms = [];
$spCbmsProfile = [];
$spCbmsRicher = ['housing'=>[], 'livelihood'=>[], 'sanitation'=>[], 'assets'=>[]];
$spHousing = [];
$spLivelihood = [];
$spSanitation = [];
$spFlags = [];
$cbmsAssets = ['pets'=>[], 'vehicles'=>[]];
$timeline = [];
$specialProgramData = ['crops'=>[], 'attendance'=>[], 'monitoring'=>[], 'interviews'=>[]];

if ($selectedHouseholdId > 0) {
    $selectedHousehold = fetch_one($conn, "SELECT h.household_id, h.household_code, h.household_head_name, h.contact_number, h.full_address, b.barangay_name, (SELECT qr_reference FROM qr_codes WHERE household_id=h.household_id AND qr_type='HOUSEHOLD' ORDER BY qr_id DESC LIMIT 1) AS qr_reference FROM households h LEFT JOIN barangays b ON b.barangay_id=h.barangay_id WHERE h.household_id={$selectedHouseholdId} LIMIT 1");
    if ($selectedHousehold) {
        $selectedPrograms = function_exists('household_program_applications') ? household_program_applications($conn, $selectedHouseholdId) : [];
        $spCbmsProfile = fetch_cbms_household_profile($conn, $selectedHouseholdId) ?: [];
        $spCbmsRicher = fetch_cbms_richer_sections($conn, $selectedHouseholdId);
        $spHousing = array_merge($spCbmsProfile, $spCbmsRicher['housing'] ?? []);
        $spLivelihood = array_merge($spCbmsProfile, $spCbmsRicher['livelihood'] ?? []);
        $spSanitation = array_merge($spCbmsProfile, $spCbmsRicher['sanitation'] ?? []);
        $spFlags = fetch_household_beneficiary_flags($conn, $selectedHouseholdId) ?: [];
        $cbmsAssets = fetch_cbms_assets($conn, $selectedHouseholdId);
        $timeline = fetch_household_timeline($conn, $selectedHouseholdId, 8);
        $specialProgramData = fetch_special_program_data($conn, $selectedHouseholdId);
    }
}

$housingTypeOptions = sp_cbms_housing_type_options();
$tenureStatusOptions = sp_cbms_tenure_status_options();
$roofMaterialOptions = sp_cbms_roof_material_options();
$wallMaterialOptions = sp_cbms_wall_material_options();
$electricitySourceOptions = sp_cbms_electricity_source_options();
$primaryIncomeOptions = sp_cbms_primary_income_source_options();
$mainLivelihoodOptions = sp_cbms_main_livelihood_options();
$incomeBandOptions = sp_cbms_income_band_options();
$waterSourceOptions = sp_cbms_water_source_options();
$toiletTypeOptions = sp_cbms_toilet_type_options();
$wasteDisposalOptions = sp_cbms_waste_disposal_options();
$drainageStatusOptions = sp_cbms_drainage_status_options();

$cards = [
    ['label'=>'CBMS-lite encoded','value'=>(int)scalar($conn, "SELECT COUNT(*) FROM cbms_household_profiles", 0),'hint'=>'Households with at least one encoded basic CBMS-lite profile'],
    ['label'=>'Beneficiary flags','value'=>(int)scalar($conn, "SELECT COUNT(*) FROM household_beneficiary_flags", 0),'hint'=>'Households with social and priority flags'],
    ['label'=>'Pets / livestock','value'=>(int)scalar($conn, "SELECT COUNT(*) FROM cbms_pets", 0),'hint'=>'Rows of pet and livestock records'],
    ['label'=>'Vehicles + assets','value'=>(int)scalar($conn, "SELECT COALESCE((SELECT COUNT(*) FROM cbms_vehicles),0)+COALESCE((SELECT COUNT(*) FROM cbms_asset_records),0)", 0),'hint'=>'Vehicle and asset records already saved'],
];

app_require('app/includes/header.php');
echo nav_cards($cards);

function cbmsLiteSelect(string $name, array $options, ?string $value, string $otherValue, string $label): void {
    $selected = sp_cbms_select_value($value, $options);
    echo '<label class="block text-sm font-semibold mb-2">' . e($label) . '</label>';
    echo '<select name="' . e($name) . '" data-other-select="' . e($name . '_other') . '" class="w-full rounded-2xl border px-4 py-3">';
    echo '<option value="">Select</option>';
    foreach ($options as $opt) {
        echo '<option value="' . e($opt) . '"' . ($selected === $opt ? ' selected' : '') . '>' . e($opt) . '</option>';
    }
    echo '</select>';
    echo '<input type="text" name="' . e($name . '_other') . '" value="' . e($otherValue) . '" placeholder="Specify other ' . e(strtolower($label)) . '" class="mt-2 w-full rounded-2xl border px-4 py-3 ' . ($selected === 'Other' ? '' : 'hidden') . '">';
}
?>
<section class="rounded-[2rem] border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-6 shadow-sm mt-6">
    <div class="flex items-center justify-between gap-3 flex-wrap">
        <div>
            <div class="text-sm text-slate-500">Matag-ob Platform · Special Program</div>
            <h2 class="text-3xl font-black">CBMS-lite encoding center</h2>
            <p class="mt-2 text-sm text-slate-500">Scan the QR or search the household first, then fill the basic CBMS parts in one place.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="<?= e(app_url('modules/agri/households/index.php')) ?>" class="app-btn-outline">Households</a>
            <a href="<?= e(app_url('modules/agri/reports/index.php')) ?>" class="app-btn-outline">Reports</a>
            <a href="<?= e(app_url('modules/agri/import/index.php')) ?>" class="app-btn-primary">Import</a>
        </div>
    </div>

    <div class="grid gap-6 xl:grid-cols-2 mt-5">
        <div class="space-y-5">
            <div class="rounded-[1.5rem] border border-slate-200 dark:border-slate-800 p-5">
                <div class="flex items-center justify-between gap-3 flex-wrap">
                    <h3 class="text-xl font-black">Scan household QR</h3>
                    <button type="button" id="open-cbms-qr-scanner" class="app-btn-outline text-sm">Open scanner</button>
                </div>
                <div class="mt-4 flex gap-2 flex-wrap">
                    <input id="cbms-qr-input" type="text" class="flex-1 min-w-[220px] rounded-2xl border px-4 py-3" placeholder="QR-HH-005298 or scanner input" value="<?= e((string)($_GET['qr'] ?? '')) ?>">
                    <button type="button" id="cbms-qr-find" class="app-btn-primary">Find QR</button>
                </div>
                <div id="cbms-scanner-wrap" class="hidden mt-4 rounded-[1.5rem] border border-slate-200 dark:border-slate-800 p-4 space-y-3">
                    <video id="cbms-qr-video" class="w-full rounded-2xl bg-slate-950 aspect-video" autoplay muted playsinline></video>
                    <div class="flex gap-2 flex-wrap">
                        <button type="button" id="close-cbms-qr-scanner" class="app-btn-outline text-sm">Close scanner</button>
                        <label class="app-btn-outline text-sm cursor-pointer">Scan from image<input id="cbms-qr-image" type="file" accept="image/*" class="hidden"></label>
                    </div>
                    <div id="cbms-scanner-note" class="text-sm text-slate-500">Allow camera access, then point it at the household QR.</div>
                </div>
            </div>

            <div class="rounded-[1.5rem] border border-slate-200 dark:border-slate-800 p-5">
                <h3 class="text-xl font-black">Manual household search</h3>
                <div class="mt-4 flex gap-2 flex-wrap">
                    <input id="cbms-search-input" type="text" class="flex-1 min-w-[240px] rounded-2xl border px-4 py-3" placeholder="Household code, head, member, contact, barangay">
                    <button type="button" id="cbms-search-button" class="app-btn-outline">Search</button>
                </div>
                <div class="mt-3 text-sm text-slate-500">Tip: type part of the name like <strong>Marilou</strong>, <strong>Villarmino</strong>, or the barangay.</div>
                <div id="cbms-search-results" class="mt-4 space-y-3"></div>
            </div>
        </div>

        <div class="rounded-[1.5rem] border border-slate-200 dark:border-slate-800 p-5">
            <div class="text-sm text-slate-500">Selected household</div>
            <?php if ($selectedHousehold): ?>
                <div class="mt-2 flex items-start justify-between gap-3 flex-wrap">
                    <div>
                        <h3 class="text-2xl font-black"><?= e($selectedHousehold['household_head_name']) ?></h3>
                        <div class="mt-1 text-slate-500"><?= e($selectedHousehold['household_code']) ?> · <?= e($selectedHousehold['barangay_name'] ?? 'No barangay') ?></div>
                    </div>
                    <?php if (!empty($selectedHousehold['qr_reference'])): ?>
                        <span class="app-badge app-badge-slate">QR: <?= e($selectedHousehold['qr_reference']) ?></span>
                    <?php endif; ?>
                </div>
                <div class="mt-4 flex flex-wrap gap-2 text-sm">
                    <span class="app-badge app-badge-slate">Contact: <?= e($selectedHousehold['contact_number'] ?: '-') ?></span>
                    <span class="app-badge app-badge-slate">Address: <?= e($selectedHousehold['full_address'] ?: '-') ?></span>
                    <a href="<?= e(app_url('modules/agri/households/view.php?id=' . (int)$selectedHousehold['household_id'] . '#sp-cbms')) ?>" class="app-btn-outline text-sm">Open full CBMS view</a>
                </div>
                <div class="mt-5 grid gap-4 sm:grid-cols-2 text-sm">
                    <div><div class="text-slate-500">Housing type</div><div class="font-semibold"><?= e($spHousing['housing_type'] ?? '-') ?></div></div>
                    <div><div class="text-slate-500">Tenure / ownership</div><div class="font-semibold"><?= e($spHousing['tenure_status'] ?? '-') ?></div></div>
                    <div><div class="text-slate-500">Water source</div><div class="font-semibold"><?= e($spSanitation['water_source'] ?? '-') ?></div></div>
                    <div><div class="text-slate-500">Toilet type</div><div class="font-semibold"><?= e($spSanitation['toilet_type'] ?? '-') ?></div></div>
                    <div><div class="text-slate-500">Electricity source</div><div class="font-semibold"><?= e($spHousing['electricity_source'] ?? '-') ?></div></div>
                    <div><div class="text-slate-500">Internet access</div><div class="font-semibold"><?= e($spCbmsProfile['internet_access'] ?? '-') ?></div></div>
                </div>
                <?php if ($selectedPrograms): ?>
                    <div class="mt-4">
                        <div class="text-sm font-semibold mb-2">Special program workflow tags</div>
                        <div class="flex flex-wrap gap-2"><?php foreach ($selectedPrograms as $programRow): ?><span class="app-badge app-badge-slate"><?= e($programRow['program_name']) ?><?php if (!empty($programRow['item_name'])): ?> · <?= e($programRow['item_name']) ?><?php endif; ?> — <?= e($programRow['application_status']) ?></span><?php endforeach; ?></div>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="mt-3 rounded-[1.5rem] border border-dashed border-slate-300 p-6 text-slate-500">No household selected yet. Scan a QR or use the manual search, then choose a result.</div>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php if ($selectedHousehold): ?>
<div class="grid gap-6 xl:grid-cols-2 mt-6">
    <section class="rounded-[2rem] border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-6 shadow-sm">
        <div class="text-sm text-slate-500">Block 1</div>
        <h3 class="text-2xl font-black">Housing profile</h3>
        <form method="post" class="mt-4 grid gap-4 md:grid-cols-2">
            <input type="hidden" name="action" value="save_sp_housing">
            <input type="hidden" name="household_id" value="<?= (int)$selectedHouseholdId ?>">
            <div><?php cbmsLiteSelect('housing_type', $housingTypeOptions, $spHousing['housing_type'] ?? null, sp_cbms_other_value($spHousing['housing_type'] ?? null, $housingTypeOptions), 'Housing type'); ?></div>
            <div><?php cbmsLiteSelect('tenure_status', $tenureStatusOptions, $spHousing['tenure_status'] ?? null, sp_cbms_other_value($spHousing['tenure_status'] ?? null, $tenureStatusOptions), 'Tenure / ownership'); ?></div>
            <div><?php cbmsLiteSelect('roof_material', $roofMaterialOptions, $spHousing['roof_material'] ?? null, sp_cbms_other_value($spHousing['roof_material'] ?? null, $roofMaterialOptions), 'Roof material'); ?></div>
            <div><?php cbmsLiteSelect('wall_material', $wallMaterialOptions, $spHousing['wall_material'] ?? null, sp_cbms_other_value($spHousing['wall_material'] ?? null, $wallMaterialOptions), 'Wall material'); ?></div>
            <div><?php cbmsLiteSelect('electricity_source', $electricitySourceOptions, $spHousing['electricity_source'] ?? null, sp_cbms_other_value($spHousing['electricity_source'] ?? null, $electricitySourceOptions), 'Electricity source'); ?></div>
            <div>
                <label class="block text-sm font-semibold mb-2">Internet access</label>
                <input type="text" name="internet_access" value="<?= e($spCbmsProfile['internet_access'] ?? '') ?>" placeholder="Mobile data, Wi-Fi, none" class="w-full rounded-2xl border px-4 py-3">
            </div>
            <div class="md:col-span-2">
                <label class="block text-sm font-semibold mb-2">Housing notes</label>
                <textarea name="notes" rows="3" class="w-full rounded-2xl border px-4 py-3" placeholder="Construction condition, ownership remarks, relocation notes"><?= e($spHousing['notes'] ?? '') ?></textarea>
            </div>
            <div class="md:col-span-2"><button class="app-btn-primary">Save housing block</button></div>
        </form>
    </section>

    <section class="rounded-[2rem] border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-6 shadow-sm">
        <div class="text-sm text-slate-500">Block 2</div>
        <h3 class="text-2xl font-black">Water, toilet, and waste</h3>
        <form method="post" class="mt-4 grid gap-4 md:grid-cols-2">
            <input type="hidden" name="action" value="save_sp_sanitation">
            <input type="hidden" name="household_id" value="<?= (int)$selectedHouseholdId ?>">
            <div><?php cbmsLiteSelect('water_source', $waterSourceOptions, $spSanitation['water_source'] ?? null, sp_cbms_other_value($spSanitation['water_source'] ?? null, $waterSourceOptions), 'Water source'); ?></div>
            <div><?php cbmsLiteSelect('toilet_type', $toiletTypeOptions, $spSanitation['toilet_type'] ?? null, sp_cbms_other_value($spSanitation['toilet_type'] ?? null, $toiletTypeOptions), 'Toilet type'); ?></div>
            <div><?php cbmsLiteSelect('waste_disposal', $wasteDisposalOptions, $spSanitation['waste_disposal'] ?? null, sp_cbms_other_value($spSanitation['waste_disposal'] ?? null, $wasteDisposalOptions), 'Waste disposal'); ?></div>
            <div><?php cbmsLiteSelect('drainage_status', $drainageStatusOptions, $spSanitation['drainage_status'] ?? null, sp_cbms_other_value($spSanitation['drainage_status'] ?? null, $drainageStatusOptions), 'Drainage status'); ?></div>
            <div class="md:col-span-2">
                <label class="block text-sm font-semibold mb-2">Sanitation notes</label>
                <textarea name="sanitation_notes" rows="3" class="w-full rounded-2xl border px-4 py-3" placeholder="Drainage, flood, water safety, toilet condition"><?= e($spSanitation['notes'] ?? '') ?></textarea>
            </div>
            <div class="md:col-span-2"><button class="app-btn-primary">Save sanitation block</button></div>
        </form>
    </section>

    <section class="rounded-[2rem] border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-6 shadow-sm">
        <div class="text-sm text-slate-500">Block 3</div>
        <h3 class="text-2xl font-black">Livelihood and income</h3>
        <form method="post" class="mt-4 grid gap-4 md:grid-cols-2">
            <input type="hidden" name="action" value="save_sp_livelihood">
            <input type="hidden" name="household_id" value="<?= (int)$selectedHouseholdId ?>">
            <div><?php cbmsLiteSelect('primary_income_source', $primaryIncomeOptions, $spLivelihood['primary_income_source'] ?? null, sp_cbms_other_value($spLivelihood['primary_income_source'] ?? null, $primaryIncomeOptions), 'Primary income source'); ?></div>
            <div><?php cbmsLiteSelect('main_livelihood', $mainLivelihoodOptions, $spLivelihood['main_livelihood'] ?? null, sp_cbms_other_value($spLivelihood['main_livelihood'] ?? null, $mainLivelihoodOptions), 'Main livelihood'); ?></div>
            <div><?php cbmsLiteSelect('monthly_income_band', $incomeBandOptions, $spLivelihood['monthly_income_band'] ?? null, sp_cbms_other_value($spLivelihood['monthly_income_band'] ?? null, $incomeBandOptions), 'Monthly income band'); ?></div>
            <div>
                <label class="block text-sm font-semibold mb-2">Monthly household income</label>
                <input type="number" step="0.01" name="monthly_household_income" value="<?= e((string)($spCbmsProfile['monthly_household_income'] ?? '')) ?>" class="w-full rounded-2xl border px-4 py-3" placeholder="0.00">
            </div>
            <div>
                <label class="block text-sm font-semibold mb-2">Poverty status</label>
                <input type="text" name="poverty_status" value="<?= e($spCbmsProfile['poverty_status'] ?? '') ?>" class="w-full rounded-2xl border px-4 py-3" placeholder="Poor, near poor, non-poor">
            </div>
            <div>
                <label class="block text-sm font-semibold mb-2">Crop summary</label>
                <input type="text" name="crop_summary" value="<?= e($spCbmsProfile['crop_summary'] ?? '') ?>" class="w-full rounded-2xl border px-4 py-3" placeholder="Corn, coconut, banana, mixed garden">
            </div>
            <div class="md:col-span-2 grid gap-4 sm:grid-cols-3">
                <label class="rounded-2xl border p-4 flex items-center gap-3"><input type="checkbox" name="farming_household" value="1" <?= !empty($spCbmsProfile['farming_household']) ? 'checked' : '' ?>> <span><span class="font-semibold block">Farming household</span><span class="text-xs text-slate-500">Mark if farming is an active livelihood.</span></span></label>
                <div><label class="block text-sm font-semibold mb-2">Farm area hectares</label><input type="number" step="0.01" name="farm_area_hectares" value="<?= e((string)($spCbmsProfile['farm_area_hectares'] ?? '')) ?>" class="w-full rounded-2xl border px-4 py-3" placeholder="0.00"></div>
                <div><label class="block text-sm font-semibold mb-2">Fruit tree estimate</label><input type="number" name="fruit_tree_count_estimate" value="<?= e((string)($spCbmsProfile['fruit_tree_count_estimate'] ?? '')) ?>" class="w-full rounded-2xl border px-4 py-3" placeholder="0"></div>
            </div>
            <div class="md:col-span-2">
                <label class="block text-sm font-semibold mb-2">Employment notes</label>
                <textarea name="employment_notes" rows="3" class="w-full rounded-2xl border px-4 py-3" placeholder="Seasonal work, odd jobs, farmer labor, transport service"><?= e($spLivelihood['employment_notes'] ?? '') ?></textarea>
            </div>
            <div class="md:col-span-2">
                <label class="block text-sm font-semibold mb-2">Special program notes</label>
                <textarea name="special_program_notes" rows="3" class="w-full rounded-2xl border px-4 py-3" placeholder="Target for seedlings, livestock support, food aid, urgent assistance"><?= e($spCbmsProfile['special_program_notes'] ?? '') ?></textarea>
            </div>
            <div class="md:col-span-2"><button class="app-btn-primary">Save livelihood block</button></div>
        </form>
    </section>

    <section class="rounded-[2rem] border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-6 shadow-sm">
        <div class="text-sm text-slate-500">Block 4</div>
        <h3 class="text-2xl font-black">Beneficiary and priority flags</h3>
        <form method="post" class="mt-4 grid gap-4 md:grid-cols-2">
            <input type="hidden" name="action" value="save_sp_flags">
            <input type="hidden" name="household_id" value="<?= (int)$selectedHouseholdId ?>">
            <label class="rounded-2xl border p-4 flex items-center gap-3"><input type="checkbox" name="is_4ps" value="1" <?= !empty($spFlags['is_4ps']) ? 'checked' : '' ?>> <span class="font-semibold">4Ps household</span></label>
            <label class="rounded-2xl border p-4 flex items-center gap-3"><input type="checkbox" name="has_senior" value="1" <?= !empty($spFlags['has_senior']) ? 'checked' : '' ?>> <span class="font-semibold">Has senior citizen</span></label>
            <label class="rounded-2xl border p-4 flex items-center gap-3"><input type="checkbox" name="has_pwd" value="1" <?= !empty($spFlags['has_pwd']) ? 'checked' : '' ?>> <span class="font-semibold">Has PWD member</span></label>
            <label class="rounded-2xl border p-4 flex items-center gap-3"><input type="checkbox" name="has_solo_parent" value="1" <?= !empty($spFlags['has_solo_parent']) ? 'checked' : '' ?>> <span class="font-semibold">Has solo parent</span></label>
            <label class="rounded-2xl border p-4 flex items-center gap-3"><input type="checkbox" name="has_pregnant_member" value="1" <?= !empty($spFlags['has_pregnant_member']) ? 'checked' : '' ?>> <span class="font-semibold">Has pregnant member</span></label>
            <label class="rounded-2xl border p-4 flex items-center gap-3"><input type="checkbox" name="has_philhealth" value="1" <?= !empty($spFlags['has_philhealth']) ? 'checked' : '' ?>> <span class="font-semibold">With PhilHealth</span></label>
            <label class="rounded-2xl border p-4 flex items-center gap-3 md:col-span-2"><input type="checkbox" name="receives_lgu_assistance" value="1" <?= !empty($spFlags['receives_lgu_assistance']) ? 'checked' : '' ?>> <span class="font-semibold">Receives LGU assistance</span></label>
            <div>
                <label class="block text-sm font-semibold mb-2">Priority level</label>
                <select name="priority_level" class="w-full rounded-2xl border px-4 py-3">
                    <option value="">Select priority</option>
                    <?php foreach (['Urgent','High','Medium','Low','Monitor'] as $priority): ?>
                        <option value="<?= e($priority) ?>" <?= (($spFlags['priority_level'] ?? '') === $priority) ? 'selected' : '' ?>><?= e($priority) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-semibold mb-2">Priority notes</label>
                <textarea name="priority_notes" rows="3" class="w-full rounded-2xl border px-4 py-3" placeholder="Why this family needs priority attention"><?= e($spFlags['priority_notes'] ?? '') ?></textarea>
            </div>
            <div class="md:col-span-2"><button class="app-btn-primary">Save beneficiary flags</button></div>
        </form>
    </section>
</div>

<div class="grid gap-6 xl:grid-cols-3 mt-6">
    <section class="rounded-[2rem] border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-6 shadow-sm">
        <div class="text-sm text-slate-500">Block 5</div>
        <h3 class="text-2xl font-black">Pets / livestock</h3>
        <form method="post" class="mt-4 space-y-3">
            <input type="hidden" name="action" value="save_cbms_asset">
            <input type="hidden" name="household_id" value="<?= (int)$selectedHouseholdId ?>">
            <input type="hidden" name="kind" value="pet">
            <input type="text" name="item_type" class="w-full rounded-2xl border px-4 py-3" placeholder="Chicken, dog, goat, pig">
            <input type="text" name="animal_name" class="w-full rounded-2xl border px-4 py-3" placeholder="Optional animal name / line">
            <div class="grid grid-cols-2 gap-3">
                <input type="number" min="1" name="quantity" value="1" class="w-full rounded-2xl border px-4 py-3" placeholder="Qty">
                <input type="text" name="notes" class="w-full rounded-2xl border px-4 py-3" placeholder="Notes">
            </div>
            <button class="app-btn-primary w-full">Add pet / livestock</button>
        </form>
        <div class="mt-4 space-y-3">
            <?php foreach (($cbmsAssets['pets'] ?? []) as $row): ?>
                <div class="rounded-2xl border p-3">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <div class="font-semibold"><?= e(($row['pet_type'] ?? $row['animal_type'] ?? 'Pet')) ?><?php if (!empty($row['animal_name'])): ?> · <?= e($row['animal_name']) ?><?php endif; ?></div>
                            <div class="text-sm text-slate-500">Qty: <?= (int)($row['pet_count'] ?? $row['quantity'] ?? 1) ?><?= !empty($row['notes']) ? ' · ' . e($row['notes']) : '' ?></div>
                        </div>
                        <form method="post" onsubmit="return confirm('Remove this pet/livestock record?');">
                            <input type="hidden" name="action" value="delete_cbms_asset"><input type="hidden" name="household_id" value="<?= (int)$selectedHouseholdId ?>"><input type="hidden" name="kind" value="pet"><input type="hidden" name="record_id" value="<?= (int)($row['pet_id'] ?? 0) ?>"><button class="app-btn-outline text-sm">Delete</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; if (empty($cbmsAssets['pets'])): ?><div class="text-sm text-slate-500">No pets or livestock saved yet.</div><?php endif; ?>
        </div>
    </section>

    <section class="rounded-[2rem] border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-6 shadow-sm">
        <div class="text-sm text-slate-500">Block 6</div>
        <h3 class="text-2xl font-black">Vehicles</h3>
        <form method="post" class="mt-4 space-y-3">
            <input type="hidden" name="action" value="save_cbms_asset">
            <input type="hidden" name="household_id" value="<?= (int)$selectedHouseholdId ?>">
            <input type="hidden" name="kind" value="vehicle">
            <input type="text" name="item_type" class="w-full rounded-2xl border px-4 py-3" placeholder="Motorcycle, tricycle, car, truck">
            <div class="grid grid-cols-2 gap-3">
                <input type="text" name="vehicle_brand" class="w-full rounded-2xl border px-4 py-3" placeholder="Brand">
                <input type="text" name="vehicle_model" class="w-full rounded-2xl border px-4 py-3" placeholder="Model">
                <input type="number" name="year_model" class="w-full rounded-2xl border px-4 py-3" placeholder="Year">
                <input type="text" name="plate_number" class="w-full rounded-2xl border px-4 py-3" placeholder="Plate no.">
                <input type="text" name="color" class="w-full rounded-2xl border px-4 py-3" placeholder="Color">
                <input type="number" min="1" name="quantity" value="1" class="w-full rounded-2xl border px-4 py-3" placeholder="Qty">
            </div>
            <input type="text" name="notes" class="w-full rounded-2xl border px-4 py-3" placeholder="Notes">
            <button class="app-btn-primary w-full">Add vehicle</button>
        </form>
        <div class="mt-4 space-y-3">
            <?php foreach (($cbmsAssets['vehicles'] ?? []) as $row): ?>
                <div class="rounded-2xl border p-3">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <div class="font-semibold"><?= e($row['vehicle_type'] ?? 'Vehicle') ?></div>
                            <div class="text-sm text-slate-500">Qty: <?= (int)($row['vehicle_count'] ?? $row['quantity'] ?? 1) ?><?= ($meta = cbms_vehicle_details_meta($row)) ? ' · ' . e($meta) : '' ?><?= !empty($row['notes']) ? ' · ' . e($row['notes']) : '' ?></div>
                        </div>
                        <form method="post" onsubmit="return confirm('Remove this vehicle record?');">
                            <input type="hidden" name="action" value="delete_cbms_asset"><input type="hidden" name="household_id" value="<?= (int)$selectedHouseholdId ?>"><input type="hidden" name="kind" value="vehicle"><input type="hidden" name="record_id" value="<?= (int)($row['vehicle_id'] ?? 0) ?>"><button class="app-btn-outline text-sm">Delete</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; if (empty($cbmsAssets['vehicles'])): ?><div class="text-sm text-slate-500">No vehicles saved yet.</div><?php endif; ?>
        </div>
    </section>

    <section class="rounded-[2rem] border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-6 shadow-sm">
        <div class="text-sm text-slate-500">Block 7</div>
        <h3 class="text-2xl font-black">Other assets</h3>
        <form method="post" class="mt-4 space-y-3">
            <input type="hidden" name="action" value="save_cbms_asset">
            <input type="hidden" name="household_id" value="<?= (int)$selectedHouseholdId ?>">
            <input type="hidden" name="kind" value="asset">
            <input type="text" name="item_type" class="w-full rounded-2xl border px-4 py-3" placeholder="Refrigerator, rice mill share, generator">
            <div class="grid grid-cols-2 gap-3">
                <input type="text" name="asset_category" class="w-full rounded-2xl border px-4 py-3" placeholder="Category">
                <input type="text" name="asset_brand" class="w-full rounded-2xl border px-4 py-3" placeholder="Brand">
                <input type="text" name="asset_model" class="w-full rounded-2xl border px-4 py-3" placeholder="Model">
                <input type="number" min="1" name="quantity" value="1" class="w-full rounded-2xl border px-4 py-3" placeholder="Qty">
            </div>
            <input type="text" name="notes" class="w-full rounded-2xl border px-4 py-3" placeholder="Notes">
            <button class="app-btn-primary w-full">Add asset</button>
        </form>
        <div class="mt-4 space-y-3">
            <?php foreach (($spCbmsRicher['assets'] ?? []) as $row): ?>
                <div class="rounded-2xl border p-3">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <div class="font-semibold"><?= e($row['asset_name'] ?? 'Asset') ?></div>
                            <div class="text-sm text-slate-500">Qty: <?= (int)($row['quantity'] ?? 1) ?><?= ($meta = cbms_asset_details_meta($row)) ? ' · ' . e($meta) : '' ?><?= !empty($row['notes']) ? ' · ' . e($row['notes']) : '' ?></div>
                        </div>
                        <form method="post" onsubmit="return confirm('Remove this asset record?');">
                            <input type="hidden" name="action" value="delete_cbms_asset"><input type="hidden" name="household_id" value="<?= (int)$selectedHouseholdId ?>"><input type="hidden" name="kind" value="asset"><input type="hidden" name="record_id" value="<?= (int)($row['asset_id'] ?? 0) ?>"><button class="app-btn-outline text-sm">Delete</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; if (empty($spCbmsRicher['assets'])): ?><div class="text-sm text-slate-500">No extra assets saved yet.</div><?php endif; ?>
        </div>
    </section>
</div>

<div class="grid gap-6 xl:grid-cols-2 mt-6">
    <section class="rounded-[2rem] border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-6 shadow-sm">
        <div class="text-sm text-slate-500">Block 8</div>
        <h3 class="text-2xl font-black">Quick summary and recent context</h3>
        <div class="mt-4 grid gap-3 sm:grid-cols-2 text-sm">
            <div><strong>Current livelihood:</strong> <?= e($spLivelihood['main_livelihood'] ?? '-') ?></div>
            <div><strong>Monthly income band:</strong> <?= e($spLivelihood['monthly_income_band'] ?? '-') ?></div>
            <div><strong>Monthly income:</strong> <?= e((($spCbmsProfile['monthly_household_income'] ?? '') !== '' && ($spCbmsProfile['monthly_household_income'] ?? null) !== null) ? number_format((float)$spCbmsProfile['monthly_household_income'], 2) : '-') ?></div>
            <div><strong>Priority:</strong> <?= e($spFlags['priority_level'] ?? '-') ?></div>
            <div><strong>Farm area:</strong> <?= e((($spCbmsProfile['farm_area_hectares'] ?? '') !== '' && ($spCbmsProfile['farm_area_hectares'] ?? null) !== null) ? ((string)$spCbmsProfile['farm_area_hectares']) . ' ha' : '-') ?></div>
            <div><strong>Fruit tree estimate:</strong> <?= e((string)($spCbmsProfile['fruit_tree_count_estimate'] ?? '-')) ?></div>
            <div class="sm:col-span-2"><strong>Flags:</strong>
                <?= !empty($spFlags['is_4ps']) ? '<span class="app-badge app-badge-blue">4Ps</span> ' : '' ?>
                <?= !empty($spFlags['has_senior']) ? '<span class="app-badge app-badge-amber">Senior</span> ' : '' ?>
                <?= !empty($spFlags['has_pwd']) ? '<span class="app-badge app-badge-rose">PWD</span> ' : '' ?>
                <?= !empty($spFlags['has_solo_parent']) ? '<span class="app-badge app-badge-violet">Solo Parent</span> ' : '' ?>
                <?= !empty($spFlags['has_pregnant_member']) ? '<span class="app-badge app-badge-emerald">Pregnant</span> ' : '' ?>
                <?= !empty($spFlags['has_philhealth']) ? '<span class="app-badge">PhilHealth</span> ' : '' ?>
                <?= !empty($spFlags['receives_lgu_assistance']) ? '<span class="app-badge app-badge-amber">LGU Assistance</span>' : '' ?>
                <?php if (empty($spFlags['is_4ps']) && empty($spFlags['has_senior']) && empty($spFlags['has_pwd']) && empty($spFlags['has_solo_parent']) && empty($spFlags['has_pregnant_member']) && empty($spFlags['has_philhealth']) && empty($spFlags['receives_lgu_assistance'])): ?><span class="text-slate-500">No CBMS-lite flags yet.</span><?php endif; ?>
            </div>
            <div class="sm:col-span-2"><strong>Program notes:</strong> <?= e($spCbmsProfile['special_program_notes'] ?? ($spFlags['priority_notes'] ?? '-')) ?></div>
        </div>
        <div class="mt-5">
            <div class="text-sm font-semibold mb-2">Recent family timeline</div>
            <div class="space-y-3"><?php foreach ($timeline as $item): ?><div class="rounded-2xl border p-3"><div class="flex items-center justify-between gap-3"><div class="font-semibold"><?= e($item['title']) ?></div><div class="text-xs text-slate-500"><?= e(date('M d, Y', strtotime((string)$item['date']))) ?></div></div><div class="mt-1 text-sm text-slate-500"><?= e($item['meta']) ?></div></div><?php endforeach; if (!$timeline): ?><div class="text-sm text-slate-500">No timeline yet.</div><?php endif; ?></div>
        </div>
    </section>

    <section class="rounded-[2rem] border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-6 shadow-sm">
        <div class="text-sm text-slate-500">Related context</div>
        <h3 class="text-2xl font-black">Crops and attendance snapshot</h3>
        <div class="mt-4 space-y-4">
            <div>
                <div class="text-sm font-semibold mb-2">Crop records</div>
                <div class="space-y-2"><?php foreach (($specialProgramData['crops'] ?? []) as $row): ?><div class="rounded-2xl border p-3 text-sm"><strong><?= e($row['crop_name']) ?></strong> · <?= (int)($row['tree_count'] ?? 0) ?> trees<?php if (!empty($row['current_condition']) || !empty($row['fruiting_status'])): ?> · <?= e(trim(($row['current_condition'] ?? '-') . ' / ' . ($row['fruiting_status'] ?? '-'), ' /')) ?><?php endif; ?></div><?php endforeach; if (empty($specialProgramData['crops'])): ?><div class="text-sm text-slate-500">No crop records yet.</div><?php endif; ?></div>
            </div>
            <div>
                <div class="text-sm font-semibold mb-2">Event attendance</div>
                <div class="space-y-2"><?php foreach (($specialProgramData['attendance'] ?? []) as $row): ?><div class="rounded-2xl border p-3 text-sm"><strong><?= e($row['event_name']) ?></strong> · <?= e($row['event_date']) ?> · <?= e($row['attendance_status']) ?></div><?php endforeach; if (empty($specialProgramData['attendance'])): ?><div class="text-sm text-slate-500">No attendance history yet.</div><?php endif; ?></div>
            </div>
        </div>
    </section>
</div>
<?php endif; ?>

<script>
(function(){
  function syncOtherField(select){
    var otherName = select.getAttribute('data-other-select');
    if(!otherName) return;
    var form = select.closest('form');
    if(!form) return;
    var otherInput = form.querySelector('[name="' + otherName + '"]');
    if(!otherInput) return;
    if(select.value === 'Other'){
      otherInput.classList.remove('hidden');
    } else {
      otherInput.classList.add('hidden');
      otherInput.value = '';
    }
  }
  document.querySelectorAll('select[data-other-select]').forEach(function(select){
    select.addEventListener('change', function(){ syncOtherField(select); });
    syncOtherField(select);
  });

  const buildTargetUrl = (hid) => '<?= e($redirectBase) ?>?selected_household_id=' + encodeURIComponent(hid);
  const searchInput = document.getElementById('cbms-search-input');
  const searchButton = document.getElementById('cbms-search-button');
  const searchResults = document.getElementById('cbms-search-results');
  async function doSearch(){
    const q = (searchInput?.value || '').trim();
    if(!q){ if(searchResults) searchResults.innerHTML=''; return; }
    if(searchResults) searchResults.innerHTML = '<div class="text-sm text-slate-500">Searching…</div>';
    const res = await fetch('<?= e(app_url('modules/api/household_lookup.php')) ?>?q=' + encodeURIComponent(q), {credentials:'same-origin'});
    const data = await res.json();
    const rows = Array.isArray(data.results) ? data.results : [];
    if(!searchResults) return;
    if(!rows.length){ searchResults.innerHTML = '<div class="text-sm text-slate-500">No households found.</div>'; return; }
    searchResults.innerHTML = rows.map(function(row){
      return '<a href="' + buildTargetUrl(row.household_id) + '" class="block rounded-2xl border border-slate-200 dark:border-slate-800 p-4 hover:border-emerald-400">'
        + '<div class="font-semibold">' + (row.household_head_name || 'Unknown household') + '</div>'
        + '<div class="text-sm text-slate-500 mt-1">' + (row.household_code || '-') + ' · ' + (row.barangay_name || '-') + '</div>'
        + '<div class="text-sm text-slate-500 mt-1">' + (row.contact_number || '-') + '</div>'
        + '</a>';
    }).join('');
  }
  searchButton?.addEventListener('click', doSearch);
  searchInput?.addEventListener('keydown', function(ev){ if(ev.key === 'Enter'){ ev.preventDefault(); doSearch(); } });

  const qrInput = document.getElementById('cbms-qr-input');
  const qrFind = document.getElementById('cbms-qr-find');
  async function findQr(qrValue){
    const qr = (qrValue || '').trim();
    if(!qr) return;
    qrFind.disabled = true;
    qrFind.textContent = 'Finding...';
    try {
      const res = await fetch('<?= e(app_url('modules/api/qr_lookup.php')) ?>?qr=' + encodeURIComponent(qr) + '&action=Lookup', {credentials:'same-origin'});
      const data = await res.json();
      if(data && data.ok && data.data && data.data.household_id){
        window.location.href = buildTargetUrl(data.data.household_id);
        return;
      }
      alert('QR not found or not linked to a household yet.');
    } catch (err) {
      alert('Unable to lookup the QR right now.');
    } finally {
      qrFind.disabled = false;
      qrFind.textContent = 'Find QR';
    }
  }
  qrFind?.addEventListener('click', function(){ findQr(qrInput?.value || ''); });
  qrInput?.addEventListener('keydown', function(ev){ if(ev.key === 'Enter'){ ev.preventDefault(); findQr(qrInput?.value || ''); } });

  const scannerWrap = document.getElementById('cbms-scanner-wrap');
  const openScannerBtn = document.getElementById('open-cbms-qr-scanner');
  const closeScannerBtn = document.getElementById('close-cbms-qr-scanner');
  const scannerNote = document.getElementById('cbms-scanner-note');
  const video = document.getElementById('cbms-qr-video');
  const imageInput = document.getElementById('cbms-qr-image');
  let mediaStream = null;
  let detector = null;
  let scanTimer = null;

  async function stopScanner(){
    if(scanTimer){ clearInterval(scanTimer); scanTimer = null; }
    if(mediaStream){ mediaStream.getTracks().forEach(track => track.stop()); mediaStream = null; }
    if(video) video.srcObject = null;
    scannerWrap?.classList.add('hidden');
  }
  async function scanFromVideoFrame(){
    if(!video || !detector || video.readyState < 2) return;
    try {
      const codes = await detector.detect(video);
      if(codes && codes.length && codes[0].rawValue){
        await stopScanner();
        findQr(codes[0].rawValue);
      }
    } catch (err) {}
  }
  openScannerBtn?.addEventListener('click', async function(){
    scannerWrap?.classList.remove('hidden');
    if(!('BarcodeDetector' in window)){
      if(scannerNote) scannerNote.textContent = 'Live scanner is not supported in this browser. You can still paste or type the QR text.';
      return;
    }
    try {
      detector = new BarcodeDetector({formats:['qr_code']});
      mediaStream = await navigator.mediaDevices.getUserMedia({video:{facingMode:'environment'}});
      if(video) video.srcObject = mediaStream;
      if(scannerNote) scannerNote.textContent = 'Point the camera at the household QR.';
      scanTimer = window.setInterval(scanFromVideoFrame, 700);
    } catch (err) {
      if(scannerNote) scannerNote.textContent = 'Camera access failed. You can still type or paste the QR text.';
    }
  });
  closeScannerBtn?.addEventListener('click', stopScanner);
  imageInput?.addEventListener('change', async function(ev){
    const file = ev.target.files && ev.target.files[0];
    if(!file || !('BarcodeDetector' in window)) return;
    try {
      detector = detector || new BarcodeDetector({formats:['qr_code']});
      const bitmap = await createImageBitmap(file);
      const codes = await detector.detect(bitmap);
      if(codes && codes.length && codes[0].rawValue){
        findQr(codes[0].rawValue);
        return;
      }
      alert('No QR detected in the image.');
    } catch (err) {
      alert('Unable to scan the uploaded image.');
    }
  });
})();
</script>
<?php app_require('app/includes/footer.php'); ?>
