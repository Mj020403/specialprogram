<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
$conn = db_conn();
app_require('app/includes/auth.php');
require_role(['task_force','mayor','admin']);
ensure_decision_support_schema($conn);
$summary = executive_action_summary($conn);
$counts = [
    'overdue' => count($summary['overdue_monitoring'] ?? []),
    'followup' => count($summary['followup_due'] ?? []),
    'interviews' => count($summary['interview_backlog'] ?? []),
    'risk' => count($summary['high_risk'] ?? []),
];
app_require('app/includes/header.php');
echo nav_cards([
    ['label'=>'Monitoring overdue','value'=>$counts['overdue'],'hint'=>'No field visit in the last 180 days'],
    ['label'=>'Follow-up due','value'=>$counts['followup'],'hint'=>'Assistance due within 14 days'],
    ['label'=>'Program request backlog','value'=>$counts['interviews'],'hint'=>'Households needing program review or field validation'],
    ['label'=>'High-risk families','value'=>$counts['risk'],'hint'=>'Needs support, validation, or urgent review'],
]);
function action_table(array $rows, string $empty, callable $renderer): string {
    if (!$rows) return '<div class="rounded-3xl border border-dashed border-slate-300 p-8 text-center text-slate-500">'.e($empty).'</div>';
    $html = '<div class="space-y-3">';
    foreach ($rows as $row) $html .= $renderer($row);
    return $html . '</div>';
}
?>
<section class="rounded-[2rem] border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-6 shadow-sm">
    <div class="flex items-center justify-between gap-3 flex-wrap">
        <div>
            <div class="text-sm text-slate-500">Mayor decision support</div>
            <h2 class="text-2xl font-black">Action center</h2>
            <p class="mt-2 text-sm text-slate-500">One page for the active households that need action now: follow-up, pending assistance, program review backlog, and high-risk records. Archived or deleted families should be handled from the family profile.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="<?= e(app_url('modules/agri/reports/index.php?qualification_status=Needs+Support')) ?>" class="app-btn-outline">Needs support report</a>
            <a href="<?= e(app_url('modules/agri/quality/index.php')) ?>" class="app-btn-outline">Data quality</a>
        </div>
    </div>
</section>
<div class="mt-6 grid gap-6 xl:grid-cols-2">
    <section class="rounded-[2rem] border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-6 shadow-sm">
        <div class="text-sm text-slate-500">Field work backlog</div>
        <h3 class="text-2xl font-black">Monitoring overdue</h3>
        <div class="mt-5">
            <?= action_table($summary['overdue_monitoring'] ?? [], 'No overdue monitoring households right now.', function(array $row): string { ob_start(); ?>
                <div class="rounded-3xl border border-slate-200 dark:border-slate-800 p-4 flex items-center justify-between gap-4">
                    <div>
                        <div class="font-semibold"><?= e($row['household_head_name']) ?></div>
                        <div class="text-sm text-slate-500"><?= e(($row['barangay_name'] ?: '-') . ' · ' . ($row['household_code'] ?: '-')) ?></div>
                        <div class="mt-2 flex flex-wrap gap-2 items-center"><?= format_status_badge($row['qualification_status'] ?: 'For Validation') ?><span class="text-xs text-slate-500">Last monitoring: <?= e($row['last_monitoring_date'] ?: 'Never') ?></span></div>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <a href="<?= e(app_url('modules/agri/households/view.php?id=' . (int)$row['household_id'])) ?>" class="app-btn-outline">Open</a>
                        <a href="<?= e(app_url('modules/agri/monitoring/index.php?household_id=' . (int)$row['household_id'])) ?>" class="app-btn-outline">Monitor</a>
                    </div>
                </div>
            <?php return ob_get_clean(); }) ?>
        </div>
    </section>

    <section class="rounded-[2rem] border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-6 shadow-sm">
        <div class="text-sm text-slate-500">Intervention tracking</div>
        <h3 class="text-2xl font-black">Assistance follow-up due</h3>
        <div class="mt-5">
            <?= action_table($summary['followup_due'] ?? [], 'No assistance follow-up is due in the next 14 days.', function(array $row): string { ob_start(); ?>
                <div class="rounded-3xl border border-slate-200 dark:border-slate-800 p-4 flex items-center justify-between gap-4">
                    <div>
                        <div class="font-semibold"><?= e($row['household_head_name']) ?></div>
                        <div class="text-sm text-slate-500"><?= e(($row['barangay_name'] ?: '-') . ' · ' . ($row['household_code'] ?: '-')) ?></div>
                        <div class="mt-2 text-sm text-slate-600"><?= e($row['assistance_type']) ?> · <?= e($row['assistance_status']) ?> · Follow-up <?= e($row['next_followup_date']) ?></div>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <span class="app-badge <?= ((int)$row['days_remaining'] < 0) ? 'app-badge-red' : 'app-badge-amber' ?>"><?= ((int)$row['days_remaining'] < 0) ? 'Overdue ' . abs((int)$row['days_remaining']) . 'd' : ((int)$row['days_remaining']) . 'd left' ?></span>
                        <a href="<?= e(app_url('modules/agri/assistance/index.php?household_id=' . (int)$row['household_id'])) ?>" class="app-btn-outline">Open</a>
                    </div>
                </div>
            <?php return ob_get_clean(); }) ?>
        </div>
    </section>

    <section class="rounded-[2rem] border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-6 shadow-sm">
        <div class="text-sm text-slate-500">Validation queue</div>
        <h3 class="text-2xl font-black">Families with no completed interview</h3>
        <div class="mt-5">
            <?= action_table($summary['interview_backlog'] ?? [], 'All families already have completed interviews.', function(array $row): string { ob_start(); ?>
                <div class="rounded-3xl border border-slate-200 dark:border-slate-800 p-4 flex items-center justify-between gap-4">
                    <div>
                        <div class="font-semibold"><?= e($row['household_head_name']) ?></div>
                        <div class="text-sm text-slate-500"><?= e(($row['barangay_name'] ?: '-') . ' · ' . ($row['household_code'] ?: '-')) ?></div>
                        <div class="mt-2 flex items-center gap-2"><?= format_status_badge($row['qualification_status'] ?: 'For Validation') ?><span class="text-xs text-slate-500">Score <?= e((string)($row['score'] ?? 0)) ?></span></div>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <a href="<?= e(app_url('modules/agri/interviews/index.php?household_id=' . (int)$row['household_id'])) ?>" class="app-btn-outline">Review request</a>
                    </div>
                </div>
            <?php return ob_get_clean(); }) ?>
        </div>
    </section>

    <section class="rounded-[2rem] border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-6 shadow-sm">
        <div class="text-sm text-slate-500">Risk visibility</div>
        <h3 class="text-2xl font-black">High-risk or support-priority families</h3>
        <div class="mt-5">
            <?= action_table($summary['high_risk'] ?? [], 'No high-risk families found right now.', function(array $row): string { ob_start(); $reasons = qualification_reason_tags($row['explanation'] ?? ''); ?>
                <div class="rounded-3xl border border-slate-200 dark:border-slate-800 p-4">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <div class="font-semibold"><?= e($row['household_head_name']) ?></div>
                            <div class="text-sm text-slate-500"><?= e(($row['barangay_name'] ?: '-') . ' · ' . ($row['household_code'] ?: '-')) ?></div>
                            <div class="mt-2 flex items-center gap-2 flex-wrap"><?= format_status_badge($row['qualification_status'] ?: 'For Validation') ?><span class="text-xs text-slate-500">Score <?= e((string)($row['score'] ?? 0)) ?></span></div>
                            <?php if ($reasons): ?><div class="mt-3 flex flex-wrap gap-2"><?php foreach ($reasons as $reason): ?><span class="app-badge app-badge-amber"><?= e($reason) ?></span><?php endforeach; ?></div><?php endif; ?>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <a href="<?= e(app_url('modules/agri/households/view.php?id=' . (int)$row['household_id'])) ?>" class="app-btn-outline">Open</a>
                            <a href="<?= e(app_url('modules/agri/timeline/index.php?household_id=' . (int)$row['household_id'])) ?>" class="app-btn-outline">Timeline</a>
                        </div>
                    </div>
                </div>
            <?php return ob_get_clean(); }) ?>
        </div>
    </section>
</div>
<?php app_require('app/includes/footer.php'); ?>
