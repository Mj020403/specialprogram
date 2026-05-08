<?php
require_once dirname(__DIR__, 3) . '/app/bootstrap.php';
$conn = db_conn();
app_require('app/includes/auth.php');
app_require('app/includes/module_platform.php');
app_require('app/includes/helpers/module_family_views.php');
set_current_platform_module('cbms');
require_module_access(['task_force','mayor','admin','developer'], 'cbms');
$q = trim((string)getv('q'));
$rows = fetch_module_household_list($conn, 'cbms', $q);
$cards = [
    ['label'=>'Families','value'=>count($rows),'hint'=>'Shared households visible to CBMS'],
    ['label'=>'With pets','value'=>count(array_filter($rows, fn($r)=>((int)$r['pet_count'])>0)),'hint'=>'Households with pets/livestock'],
    ['label'=>'With vehicles','value'=>count(array_filter($rows, fn($r)=>((int)$r['vehicle_count'])>0)),'hint'=>'Households with registered vehicles'],
];
app_require('app/includes/header.php');
echo nav_cards($cards);
?>
<div class="grid gap-6 xl:grid-cols-[0.95fr_1.05fr]">
<section class="rounded-[2rem] border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-6 shadow-sm">
    <div class="text-sm text-slate-500">CBMS</div>
    <h2 class="text-3xl font-black">Family profiles</h2>
    <p class="mt-2 text-sm text-slate-500">Open a family to see only CBMS data like housing, pets, vehicles, and community-based household details.</p>
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
                        <span class="app-badge app-badge-sky"><?= (int)$row['member_count'] ?> members</span>
                        <span class="app-badge app-badge-amber"><?= (int)$row['pet_count'] ?> pets</span>
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
<section class="rounded-[2rem] border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-6 shadow-sm">
    <div class="text-sm text-slate-500">How this module works</div>
    <h3 class="text-2xl font-black">Only CBMS data shows here</h3>
    <div class="mt-4 space-y-3 text-sm text-slate-600 dark:text-slate-300">
        <p>This view still uses the same family records from the shared database, but it focuses on CBMS-only sections.</p>
        <p>You will see pets, vehicles, housing and household context here. Crop and beneficiary office records stay out of this module view unless the Mayor opens the full family profile.</p>
    </div>
</section>
</div>
<?php app_require('app/includes/footer.php'); ?>
