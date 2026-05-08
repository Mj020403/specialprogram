<?php
require_once dirname(__DIR__, 3) . '/app/bootstrap.php';
$conn = db_conn();
app_require('app/includes/auth.php');
require_role(['task_force','admin','developer','mayor']);
ensure_golden_household_schema($conn);

$openViolations = golden_violation_queue($conn, 'Open', 100);
$resolvedViolations = golden_violation_queue($conn, 'Resolved', 50);
$ruleCoverage = fetch_all_assoc($conn, "
    SELECT h.household_id, h.household_head_name, h.household_code, b.barangay_name,
      SUM(CASE WHEN rc.is_checked=1 THEN 1 ELSE 0 END) AS checked_items,
      COUNT(rt.checklist_type_id) AS total_items,
      (SELECT COUNT(*) FROM household_violations hv WHERE hv.household_id=h.household_id AND hv.violation_status='Open') AS open_violations
    FROM households h
    JOIN barangays b ON b.barangay_id=h.barangay_id
    CROSS JOIN household_rule_checklist_types rt
    LEFT JOIN household_rule_checklists rc ON rc.household_id=h.household_id AND rc.checklist_type_id=rt.checklist_type_id
    WHERE rt.is_active=1 AND COALESCE(h.record_status,'active') <> 'deleted'
    GROUP BY h.household_id, h.household_head_name, h.household_code, b.barangay_name
    ORDER BY checked_items DESC, open_violations ASC, h.household_head_name ASC
    LIMIT 100
");

app_require('app/includes/header.php');
echo nav_cards([
    ['label'=>'Open Violations','value'=>safe_table_count($conn, 'household_violations', "violation_status='Open'"),'hint'=>'Current issues to resolve'],
    ['label'=>'Resolved Violations','value'=>safe_table_count($conn, 'household_violations', "violation_status='Resolved'"),'hint'=>'Already settled records'],
    ['label'=>'Households with checklist records','value'=>safe_table_count($conn, 'household_rule_checklists'),'hint'=>'Rule compliance entries already saved'],
    ['label'=>'Checklist item types','value'=>safe_table_count($conn, 'household_rule_checklist_types'),'hint'=>'Common rule items used during visits'],
]);
?>
<section class="rounded-[2rem] border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-6 shadow-sm mt-6">
  <div class="flex items-start justify-between gap-4 flex-wrap">
    <div>
      <div class="text-sm text-slate-500">Compliance and violations</div>
      <h1 class="text-3xl font-black">Checklist first, incidents second</h1>
      <p class="mt-2 text-sm text-slate-500 max-w-4xl">Rule compliance is saved as a checklist. Violations stay in a separate incident list with date and notes. This gives the mayor a cleaner household summary with no points and clear household behavior tracking.</p>
    </div>
    <a href="<?= e(app_url('modules/agri/reports/index.php')) ?>" class="app-btn-outline">Open reports</a>
  </div>
</section>
<div class="grid gap-6 xl:grid-cols-2 mt-6">
  <section class="rounded-[2rem] border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-6 shadow-sm">
    <div class="text-sm text-slate-500">Rule compliance summary</div>
    <h2 class="text-2xl font-black mt-1">Household checklist coverage</h2>
    <div class="mt-5 overflow-x-auto rounded-[1.5rem] border border-slate-200 dark:border-slate-800">
      <table class="min-w-full text-sm">
        <thead class="bg-slate-50 dark:bg-slate-900">
          <tr>
            <th class="px-4 py-3 text-left">Household</th>
            <th class="px-4 py-3 text-left">Barangay</th>
            <th class="px-4 py-3 text-right">Checklist</th>
            <th class="px-4 py-3 text-right">Open violations</th>
            <th class="px-4 py-3 text-right">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($ruleCoverage as $row): ?>
            <tr class="border-t border-slate-200 dark:border-slate-800">
              <td class="px-4 py-3 font-semibold"><?= e($row['household_head_name']) ?><div class="text-xs text-slate-500"><?= e($row['household_code']) ?></div></td>
              <td class="px-4 py-3"><?= e($row['barangay_name']) ?></td>
              <td class="px-4 py-3 text-right"><?= (int)$row['checked_items'] ?>/<?= (int)$row['total_items'] ?></td>
              <td class="px-4 py-3 text-right"><?= (int)$row['open_violations'] ?></td>
              <td class="px-4 py-3 text-right"><a href="<?= e(app_url('modules/agri/households/view.php?id=' . (int)$row['household_id'] . '#golden-household')) ?>" class="app-btn-outline text-sm">Open household</a></td>
            </tr>
          <?php endforeach; if (!$ruleCoverage): ?>
            <tr><td colspan="5" class="px-4 py-6 text-center text-slate-500">No checklist records yet.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>
  <section class="rounded-[2rem] border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-6 shadow-sm">
    <div class="text-sm text-slate-500">Incident list</div>
    <h2 class="text-2xl font-black mt-1">Open violations</h2>
    <div class="mt-5 divide-y divide-slate-200 dark:divide-slate-800 rounded-[1.5rem] border border-slate-200 dark:border-slate-800 overflow-hidden">
      <?php foreach ($openViolations as $row): ?>
        <div class="p-4 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
          <div>
            <div class="font-semibold"><?= e($row['household_head_name']) ?> · <?= e($row['violation_name']) ?></div>
            <div class="text-sm text-slate-500"><?= e($row['barangay_name']) ?> · Observed <?= e($row['observed_on']) ?></div>
            <?php if (!empty($row['remarks'])): ?><div class="text-sm text-slate-500 mt-1"><?= e($row['remarks']) ?></div><?php endif; ?>
          </div>
          <a href="<?= e(app_url('modules/agri/households/view.php?id=' . (int)$row['household_id'] . '#golden-household')) ?>" class="app-btn-outline text-sm">Open household</a>
        </div>
      <?php endforeach; if (!$openViolations): ?>
        <div class="px-4 py-6 text-center text-emerald-700">No open violations right now.</div>
      <?php endif; ?>
    </div>
    <?php if ($resolvedViolations): ?>
      <div class="mt-5 text-sm text-slate-500">Recently resolved</div>
      <div class="mt-3 space-y-2 text-sm">
        <?php foreach (array_slice($resolvedViolations, 0, 5) as $row): ?>
          <div class="rounded-2xl border border-slate-200 dark:border-slate-800 px-4 py-3"><?= e($row['household_head_name']) ?> · <?= e($row['violation_name']) ?> · resolved <?= e($row['resolved_on'] ?: $row['observed_on']) ?></div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>
</div>
<?php app_require('app/includes/footer.php'); ?>
