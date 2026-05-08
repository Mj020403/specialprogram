<?php
require_once dirname(__DIR__, 3) . '/app/bootstrap.php';
$conn = db_conn();
app_require('app/includes/auth.php');
app_require('app/includes/module_platform.php');
app_require('app/includes/helpers/module_family_views.php');
set_current_platform_module('beneficiaries');
require_module_access(['task_force','mayor','admin','developer'], 'beneficiaries');
$q = trim((string)getv('q'));
$rows = fetch_module_household_list($conn, 'beneficiaries', $q);
$cards = [
    ['label'=>'Families','value'=>count($rows),'hint'=>'Shared households visible to Beneficiaries'],
    ['label'=>'With beneficiary data','value'=>count(array_filter($rows, fn($r)=>((int)$r['beneficiary_count'])>0)),'hint'=>'Already classified'],
    ['label'=>'PWD / Senior hints','value'=>count(array_filter($rows, fn($r)=>((int)$r['member_count'])>0)),'hint'=>'Use family view for suggested tags'],
];
app_require('app/includes/header.php');
echo nav_cards($cards);
?>
<div class="grid gap-6 xl:grid-cols-[0.95fr_1.05fr]">
<section class="rounded-[2rem] border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-6 shadow-sm">
    <div class="text-sm text-slate-500">Beneficiaries</div>
    <h2 class="text-3xl font-black">Family records</h2>
    <p class="mt-2 text-sm text-slate-500">Open a family to see only beneficiary-related classifications, recommendations, assistance, and social targeting data.</p>
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
                        <?php if ((int)$row['beneficiary_count'] > 0): ?><span class="app-badge app-badge-emerald">Has beneficiary record</span><?php else: ?><span class="app-badge app-badge-amber">Needs beneficiary profile</span><?php endif; ?>
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
    <h3 class="text-2xl font-black">Only beneficiary data shows here</h3>
    <div class="mt-4 space-y-3 text-sm text-slate-600 dark:text-slate-300">
        <p>This view reuses the same family records from the shared database, but it only opens the sections that belong to the Beneficiaries module.</p>
        <p>You will see classifications like indigent status, priority level, sector tags, recommendations, and assistance history. Crop records, pets, vehicles, and CBMS-only details stay out of this module view.</p>
    </div>
</section>
</div>
<?php app_require('app/includes/footer.php'); ?>
