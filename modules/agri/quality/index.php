<?php
require_once dirname(__DIR__, 3) . '/app/bootstrap.php';
$conn = db_conn();
app_require('app/includes/auth.php');
require_role(['task_force','mayor','admin']);
ensure_decision_support_schema($conn);
$issues = data_quality_issues($conn);
$summary = ['High'=>0,'Medium'=>0,'Low'=>0];
foreach ($issues as $issue) { $summary[$issue['severity']] = ($summary[$issue['severity']] ?? 0) + 1; }
app_require('app/includes/header.php');
echo nav_cards([
    ['label'=>'Total issues','value'=>count($issues),'hint'=>'Data cleanup queue'],
    ['label'=>'High priority','value'=>$summary['High'] ?? 0,'hint'=>'Must fix soon'],
    ['label'=>'Medium priority','value'=>$summary['Medium'] ?? 0,'hint'=>'Needs review'],
    ['label'=>'Low priority','value'=>$summary['Low'] ?? 0,'hint'=>'Optional polish items'],
]);
?>
<section class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
    <div class="text-sm text-slate-500">Data quality center</div>
    <h2 class="text-2xl font-black">Records needing cleanup</h2>
    <p class="mt-2 text-sm text-slate-500">This page helps the team keep the database reliable: missing QR, missing head links, no photo, missing birthdate, missing interview, and weak profile details.</p>
    <div class="mt-5 overflow-hidden rounded-3xl border border-slate-200">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50"><tr><th class="px-4 py-3 text-left">Severity</th><th class="px-4 py-3 text-left">Family / member</th><th class="px-4 py-3 text-left">Issue</th><th class="px-4 py-3 text-right">Action</th></tr></thead>
            <tbody>
            <?php foreach ($issues as $issue): ?>
                <tr class="border-t border-slate-200">
                    <td class="px-4 py-3"><?= format_status_badge($issue['severity']) ?></td>
                    <td class="px-4 py-3 font-semibold"><?= e($issue['household']) ?></td>
                    <td class="px-4 py-3"><?= e($issue['issue']) ?></td>
                    <td class="px-4 py-3 text-right"><?php if (!empty($issue['household_id'])): ?><a href="<?= e(app_url('modules/agri/households/view.php?id=' . (int)$issue['household_id'])) ?>" class="app-btn-outline">Open family</a><?php endif; ?></td>
                </tr>
            <?php endforeach; if (!$issues): ?><tr><td colspan="4" class="px-4 py-6 text-center text-slate-500">No major data issues found right now.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php app_require('app/includes/footer.php'); ?>