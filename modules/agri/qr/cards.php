<?php
require_once dirname(__DIR__, 3) . '/app/bootstrap.php';

$conn = db_conn();app_require('app/includes/auth.php'); require_role(['task_force','admin','mayor']);
require_once app_path('app/config/database.php');
app_require('app/includes/app_helpers.php');
$user = current_user();
if (isset($_GET['backfill']) && in_array($user['role'], ['task_force','admin'], true)) {
    @set_time_limit(0);
    $stats = backfill_all_qr_assets($conn, (int)$user['id']);
    create_notification($conn, 'QR Backfill Completed', 'Generated or refreshed household and crop QR references for bulk rollout.', 'Low', (int)$user['id']);
    set_flash('success', 'QR backfill finished for '.$stats['households'].' households and '.$stats['crops'].' crops.');
    header('Location: /harvest/modules/agri/qr/cards.php'); exit;
}
app_require('app/includes/header.php');
$barangays = fetch_all_assoc($conn, "SELECT barangay_id, barangay_name FROM barangays ORDER BY barangay_name");
$barangayFilter = (int)($_GET['barangay_id'] ?? 0);
$householdFilter = (int)($_GET['household_id'] ?? 0);
$sql = "SELECT h.household_id,h.household_code,h.household_head_name,h.profile_photo_path,h.household_size,h.purok_sitio,h.full_address,b.barangay_name,(SELECT qr_reference FROM qr_codes q WHERE q.household_id=h.household_id AND q.qr_type='HOUSEHOLD' ORDER BY q.qr_id DESC LIMIT 1) AS qr_reference FROM households h JOIN barangays b ON b.barangay_id=h.barangay_id";
$where = [];
if ($barangayFilter > 0) $where[] = "h.barangay_id=".$barangayFilter;
if ($householdFilter > 0) $where[] = "h.household_id=".$householdFilter;
if ($where) $sql .= " WHERE " . implode(" AND ", $where);
$sql .= " ORDER BY b.barangay_name, h.household_head_name LIMIT 300";
$rows = fetch_all_assoc($conn, $sql);
?>
<section class="rounded-[2rem] border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-6 shadow-sm space-y-5">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <div class="text-sm text-slate-500">Bulk QR rollout</div>
            <h2 class="text-2xl font-black">Household QR cards</h2>
            <p class="mt-2 text-sm text-slate-500">Print one QR per household so Task Force can scan attendance, interview, and monitoring without typing names repeatedly.</p>
        </div>
        <div class="flex flex-wrap gap-3">
            <?php if (in_array($user['role'], ['task_force','admin'], true)): ?>
                <a href="/harvest/modules/agri/qr/cards.php?backfill=1" class="app-btn-primary">Generate missing QR</a>
            <?php endif; ?>
            <button type="button" onclick="window.print()" class="app-btn-outline">Print cards</button>
        </div>
    </div>
    <form method="GET" class="grid gap-3 sm:grid-cols-[260px_auto]">
        <select name="barangay_id" class="rounded-2xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-4 py-3">
            <option value="">All barangays</option>
            <?php foreach($barangays as $b): ?>
                <option value="<?= (int)$b['barangay_id'] ?>" <?= $barangayFilter===(int)$b['barangay_id']?'selected':'' ?>><?= e($b['barangay_name']) ?></option>
            <?php endforeach; ?>
        </select>
        <button class="app-btn-outline max-w-[160px]">Filter</button>
    </form>
</section>
<div class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-3" id="qrCardGrid">
<?php foreach($rows as $r): $qrRef = $r['qr_reference'] ?: ('QR-HH-'.str_pad((string)$r['household_id'], 6, '0', STR_PAD_LEFT)); ?>
    <section class="qr-card-print rounded-[2rem] border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-5 shadow-sm">
        <div class="flex items-start justify-between gap-4">
            <div>
                <div class="text-xs uppercase tracking-[0.18em] text-slate-500">Matag-ob Family QR</div>
                <h3 class="mt-1 text-lg font-black"><?= e($r['household_head_name']) ?></h3>
                <div class="mt-1 text-sm text-slate-500"><?= e($r['household_code'] ?: '-') ?> · <?= e($r['barangay_name']) ?></div>
                <div class="text-sm text-slate-500"><?= e($r['purok_sitio'] ?: $r['full_address'] ?: '-') ?></div>
            </div>
            <a href="/harvest/modules/agri/households/view.php?id=<?= (int)$r['household_id'] ?>" class="rounded-xl border px-3 py-2 text-sm font-semibold">Open</a>
        </div>
        <div class="mt-4 flex flex-col gap-4 sm:flex-row sm:items-center">
            <div class="qr-code-box rounded-3xl border border-slate-200 dark:border-slate-700 bg-white p-3 mx-auto sm:mx-0" data-qr="<?= e($qrRef) ?>"></div>
            <div class="text-sm text-slate-600 dark:text-slate-300 min-w-0">
                <div class="flex items-center gap-3"><img src="<?= e(household_profile_photo($conn, (int)$r['household_id'], $r['profile_photo_path'] ?? null)) ?>" alt="Family photo" class="h-14 w-14 rounded-2xl object-cover border border-slate-200 dark:border-slate-800"><div><div class="font-semibold">One QR for the whole family</div><div class="text-xs text-slate-500"><?= e((string)($r['household_size'] ?: household_member_count($conn, (int)$r['household_id']))) ?> member(s)</div></div></div>
                <div class="mt-3">Scan for attendance, interview, and monitoring without typing the family name.</div>
                <div class="mt-3 rounded-2xl bg-slate-100 dark:bg-slate-900 px-3 py-2 font-semibold break-all"><?= e($qrRef) ?></div>
                <div class="mt-3 flex flex-wrap gap-2"><a href="/harvest/modules/agri/qr/print_household.php?household_id=<?= (int)$r['household_id'] ?>" target="_blank" class="rounded-xl border px-3 py-2 font-semibold">Download / Print</a><a href="/harvest/modules/agri/households/view.php?id=<?= (int)$r['household_id'] ?>" class="rounded-xl border px-3 py-2 font-semibold">Family profile</a></div>
            </div>
        </div>
    </section>
<?php endforeach; if(!$rows): ?>
    <div class="rounded-[2rem] border border-dashed p-8 text-slate-500">No households found for this filter.</div>
<?php endif; ?>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
document.querySelectorAll('.qr-code-box').forEach(function(el){
    const qr = el.getAttribute('data-qr');
    if (!qr || !window.QRCode) return;
    new QRCode(el, { text: qr, width: 132, height: 132 });
});
</script>
<style>
@media print {
    .app-header,.app-main > .flex:first-child,.app-toast,form,button,a[href="/harvest/modules/agri/qr/cards.php?backfill=1"]{display:none !important}
    .app-main{padding:0 !important}
    #qrCardGrid{display:grid;grid-template-columns:1fr 1fr;gap:10px}
    .qr-card-print{break-inside:avoid;box-shadow:none !important}
}
</style>
<?php app_require('app/includes/footer.php'); ?>
