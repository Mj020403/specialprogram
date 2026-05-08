<?php
require_once dirname(__DIR__, 3) . '/app/bootstrap.php';
$conn = db_conn();
app_require('app/includes/auth.php');
app_require('app/includes/module_platform.php');
app_require('app/includes/helpers/module_family_views.php');
set_current_platform_module('mayor');
require_module_access(['mayor','admin','developer'], 'mayor');
$q = trim((string)getv('q'));
$rows = fetch_module_household_list($conn, 'mayor', $q);
$cards = [
    ['label'=>'Families','value'=>count($rows),'hint'=>'Shared records across all modules'],
    ['label'=>'Crop households','value'=>count(array_filter($rows, fn($r)=>((int)$r['crop_count'])>0)),'hint'=>'Special Program data present'],
    ['label'=>'With beneficiary records','value'=>count(array_filter($rows, fn($r)=>((int)$r['beneficiary_count'])>0)),'hint'=>'Beneficiaries data present'],
    ['label'=>'With CBMS assets','value'=>count(array_filter($rows, fn($r)=>((int)$r['pet_count'])>0 || ((int)$r['vehicle_count'])>0)),'hint'=>'Pets or vehicles recorded'],
];
app_require('app/includes/header.php');
echo nav_cards($cards);
?>
<div class="grid gap-6 xl:grid-cols-[0.95fr_1.05fr]">
<section class="rounded-[2rem] border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-6 shadow-sm">
    <div class="text-sm text-slate-500">Mayor</div>
    <h2 class="text-3xl font-black">All family profiles</h2>
    <p class="mt-2 text-sm text-slate-500">The Mayor sees the full household profile across Special Program, Beneficiaries, and CBMS.</p>
    <form method="GET" class="mt-5 flex gap-3">
        <input type="text" name="q" value="<?= e($q) ?>" placeholder="Search household head or family member" class="flex-1 rounded-2xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-4 py-3">
        <button class="app-btn-outline">Search</button>
    </form>
    <div class="mt-5 space-y-3 max-h-[70vh] overflow-y-auto">
        <?php foreach ($rows as $row): ?>
        <a href="<?= e($row['open_url']) ?>" class="block rounded-3xl border border-slate-200 dark:border-slate-800 p-4 hover:border-emerald-300">
            <div class="flex items-start gap-3">
                <img src="<?= e($row['photo_url']) ?>" alt="Family" class="h-14 w-14 rounded-2xl object-cover border border-slate-200 dark:border-slate-800">
                <div class="min-w-0 flex-1">
                    <div class="font-black text-lg"><?= e($row['household_head_name']) ?></div>
                    <div class="text-sm text-slate-500"><?= e(($row['barangay_name'] ?: 'No barangay') . ' · ' . ($row['household_code'] ?: 'No HH code')) ?></div>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <span class="app-badge app-badge-emerald"><?= (int)$row['crop_count'] ?> crops</span>
                        <span class="app-badge app-badge-amber"><?= (int)$row['beneficiary_count'] ?> beneficiary</span>
                        <span class="app-badge app-badge-sky"><?= (int)$row['pet_count'] ?> pets</span>
                        <span class="app-badge app-badge-slate"><?= (int)$row['vehicle_count'] ?> vehicles</span>
                    </div>
                </div>
            </div>
        </a>
        <?php endforeach; if (!$rows): ?>
        <div class="text-sm text-slate-500">No family matches found.</div>
        <?php endif; ?>
    </div>
</section>
<section class="space-y-6">
    <div class="rounded-[2rem] border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-6 shadow-sm">
        <div class="text-sm text-slate-500">Executive family review</div>
        <h3 class="text-2xl font-black">Cross-module visibility</h3>
        <div class="mt-4 space-y-3 text-sm text-slate-600 dark:text-slate-300">
            <p>This module shows everything attached to one family: crops and monitoring from Special Program, classifications from Beneficiaries, and pets, vehicles, housing, sanitation, and assets from CBMS.</p>
            <p>Use this page when you need the full family picture before approving support, reviewing programs, or deciding who should be invited to an event.</p>
        </div>
    </div>
    <div class="rounded-[2rem] border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-6 shadow-sm">
        <div class="text-sm text-slate-500">What the Mayor can review here</div>
        <div class="mt-4 grid gap-3 md:grid-cols-2 text-sm text-slate-600 dark:text-slate-300">
            <div class="rounded-2xl border border-slate-200 dark:border-slate-800 p-4">Full family 360 view</div>
            <div class="rounded-2xl border border-slate-200 dark:border-slate-800 p-4">Shared assistance history</div>
            <div class="rounded-2xl border border-slate-200 dark:border-slate-800 p-4">Potential event invites</div>
            <div class="rounded-2xl border border-slate-200 dark:border-slate-800 p-4">CBMS housing, livelihood, sanitation, assets</div>
        </div>
    </div>
</section>
</div>
<?php app_require('app/includes/footer.php'); ?>
