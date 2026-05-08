<?php
require_once dirname(__DIR__, 3) . '/app/bootstrap.php';
$conn = db_conn();
app_require('app/includes/auth.php');
require_role(['task_force','admin','developer','mayor']);
ensure_golden_household_schema($conn);

$status = trim((string)($_GET['status'] ?? 'Pending First Validation'));
$allowed = ['Pending First Validation','Pending Final Validation','For Compliance','Declined','Pending Release','Active'];
if (!in_array($status, $allowed, true)) $status = 'Pending First Validation';
$rows = golden_program_queue($conn, $status, 100);

app_require('app/includes/header.php');
echo nav_cards([
    ['label'=>'Pending first validation','value'=>safe_table_count($conn, 'household_special_programs', "application_status='Pending First Validation'"),'hint'=>'Needs first farm validation'],
    ['label'=>'Pending final validation','value'=>safe_table_count($conn, 'household_special_programs', "application_status='Pending Final Validation'"),'hint'=>'Orientation attended, waiting for final visit'],
    ['label'=>'For compliance','value'=>safe_table_count($conn, 'household_special_programs', "application_status='For Compliance'"),'hint'=>'Needs fixes before next visit'],
    ['label'=>'Pending release','value'=>safe_table_count($conn, 'household_special_programs', "application_status='Pending Release'"),'hint'=>'Seminar attended and waiting for release'],
    ['label'=>'Active','value'=>safe_table_count($conn, 'household_special_programs', "application_status='Active'"),'hint'=>'Already released and ongoing'],
]);
?>
<section class="rounded-[2rem] border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-6 shadow-sm mt-6">
  <div class="flex items-start justify-between gap-4 flex-wrap">
    <div>
      <div class="text-sm text-slate-500">Program validation queue</div>
      <h1 class="text-3xl font-black">Validation queue</h1>
      <p class="mt-2 text-sm text-slate-500 max-w-4xl">Use this page only for actual farm visits and compliance decisions. Orientation and Seminar are now handled from Events. Final validation should only happen after orientation attendance is completed.</p>
    </div>
    <a href="<?= e(app_url('modules/agri/programs/index.php')) ?>" class="app-btn-primary">Open program center</a>
  </div>
  <div class="mt-4 flex flex-wrap gap-2">
    <?php foreach ($allowed as $opt): ?>
      <a href="<?= e(app_url('modules/agri/validation/index.php?status=' . urlencode($opt))) ?>" class="<?= $status === $opt ? 'app-btn-primary' : 'app-btn-outline' ?> text-sm"><?= e($opt) ?></a>
    <?php endforeach; ?>
  </div>
</section>
<section class="rounded-[2rem] border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-6 shadow-sm mt-6">
  <div class="overflow-x-auto rounded-[1.5rem] border border-slate-200 dark:border-slate-800">
    <table class="min-w-full text-sm">
      <thead class="bg-slate-50 dark:bg-slate-900">
        <tr>
          <th class="px-4 py-3 text-left">Household</th>
          <th class="px-4 py-3 text-left">Barangay</th>
          <th class="px-4 py-3 text-left">Program</th>
          <th class="px-4 py-3 text-left">Applied</th>
          <th class="px-4 py-3 text-left">Stage gates</th>
          <th class="px-4 py-3 text-left">Land / ownership</th>
          <th class="px-4 py-3 text-left">Validation schedule / notes</th>
          <th class="px-4 py-3 text-right">Action</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $row): ?>
          <tr class="border-t border-slate-200 dark:border-slate-800">
            <td class="px-4 py-3 font-semibold"><?= e($row['household_head_name']) ?><div class="text-xs text-slate-500"><?= e($row['household_code']) ?></div></td>
            <td class="px-4 py-3"><?= e($row['barangay_name']) ?></td>
            <td class="px-4 py-3"><?= e($row['program_name']) ?><?php if (!empty($row['item_name'])): ?> · <?= e($row['item_name']) ?><?php endif; ?></td>
            <td class="px-4 py-3"><?= e($row['date_applied'] ?: '-') ?><div class="text-xs text-slate-500"><?= e($row['applicant_contact'] ?: '-') ?></div></td>
            <td class="px-4 py-3 text-slate-500">Orientation: <?= e($row['orientation_attendance_status'] ?: 'Locked') ?><br>Seminar: <?= e($row['seminar_attendance_status'] ?: 'Locked') ?></td>
            <td class="px-4 py-3 text-slate-500">Loc: <?= e($row['land_location'] ?: '-') ?><br>Area: <?= e($row['land_area_text'] ?: '-') ?><br>Ownership: <?= e($row['ownership_type'] ?: '-') ?></td>
            <td class="px-4 py-3 text-slate-500">First visit: <?= e($row['scheduled_validation_date'] ?: '-') ?><br>Final visit: <?= e($row['final_validation_date'] ?: '-') ?><br><?= e(($row['validation_notes'] ?? '') ?: ($row['intake_notes'] ?? '') ?: ($row['target_notes'] ?? '') ?: '-') ?></td>
            <td class="px-4 py-3 text-right">
<div class="flex gap-2 justify-end">
<?php $validateUrl = app_url('modules/agri/validation/first_validation.php?id=' . (int)$row['household_id']); if ($status === 'Pending Final Validation') { $validateUrl = app_url('modules/agri/validation/final_validation.php?id=' . (int)$row['household_id']); } elseif ($status === 'Pending Release') { $validateUrl = app_url('modules/agri/validation/release.php?id=' . (int)$row['household_id']); } ?>
<a href="<?= e($validateUrl) ?>" class="app-btn-primary text-sm">📋 Validate</a>
<a href="<?= e(app_url('modules/agri/households/view.php?id=' . (int)$row['household_id'] . '#golden-household')) ?>" class="app-btn-outline text-sm">Open</a>
</div>
</td>
          </tr>
        <?php endforeach; if (!$rows): ?>
          <tr><td colspan="8" class="px-4 py-6 text-center text-slate-500">No records for this status yet.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>
<?php app_require('app/includes/footer.php'); ?>
