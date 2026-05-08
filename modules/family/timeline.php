<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
$conn = db_conn();
app_require('app/includes/session.php');
require_once app_path('app/config/database.php');
app_require('modules/family/portal_helpers.php');
ensure_family_portal_schema($conn);
require_family_dashboard_enabled($conn);
$family = require_family_portal_login($conn);
$householdId = (int)$family['household_id'];
$timeline = family_recent_timeline($conn, $householdId, 40);
$pointsHistory = family_points_history($conn, $householdId, 40);
$logoUrl = system_logo_url($conn);
$appName = system_title($conn);
?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Timeline - <?= e($appName) ?></title><script src="https://cdn.tailwindcss.com"></script><link rel="stylesheet" href="<?= e(app_url('assets/css/app.css')) ?>"></head>
<body class="min-h-screen bg-slate-50 text-slate-800">
<header class="border-b border-slate-200 bg-white"><div class="mx-auto max-w-7xl px-4 py-4 sm:px-6 lg:px-8 flex items-center justify-between gap-4"><div class="flex items-center gap-4"><img src="<?= e($logoUrl) ?>" alt="Logo" class="h-14 w-14 rounded-2xl object-cover"><div><div class="text-2xl font-black text-emerald-950"><?= e($appName) ?></div><div class="text-sm text-slate-500">Family timeline</div></div></div><div class="flex gap-2"><a href="<?= e(app_url('modules/family/dashboard.php')) ?>" class="app-btn-outline">Back to dashboard</a><a href="<?= e(app_url('modules/family/crops.php')) ?>" class="app-btn-outline">My crops</a></div></div></header>
<div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8 grid gap-6 xl:grid-cols-2">
<section class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm"><div class="text-sm text-slate-500">Activity timeline</div><h1 class="text-3xl font-black text-slate-900">Household timeline</h1><div class="mt-6 space-y-4"><?php foreach ($timeline as $item): ?><div class="rounded-2xl border border-slate-200 p-4"><div class="flex items-center justify-between gap-3 flex-wrap"><div class="font-bold text-slate-900"><?= e($item['title']) ?></div><span class="app-inline-badge"><?= e($item['tag']) ?></span></div><div class="mt-2 text-slate-600"><?= e($item['details']) ?></div><?php if (!empty($item['event_at'])): ?><div class="mt-2 text-sm text-slate-500"><?= e(date('M d, Y h:i A', strtotime((string)$item['event_at']))) ?></div><?php endif; ?></div><?php endforeach; ?><?php if (!$timeline): ?><div class="text-slate-500">No timeline entries yet.</div><?php endif; ?></div></section>
<section class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm"><div class="text-sm text-slate-500">Scoring history</div><h2 class="text-3xl font-black text-slate-900">Awarded points</h2><div class="mt-6 space-y-4"><?php foreach ($pointsHistory as $log): ?><div class="rounded-2xl border border-slate-200 p-4"><div class="flex items-center justify-between gap-3"><div class="font-bold text-slate-900"><?= e($log['remarks'] ?: ucfirst(str_replace('_', ' ', (string)$log['source_type']))) ?></div><div class="text-lg font-black text-emerald-700">+<?= e(rtrim(rtrim(number_format((float)$log['points_awarded'], 2, '.', ''), '0'), '.')) ?></div></div><div class="mt-2 text-sm text-slate-500"><?= e(date('M d, Y h:i A', strtotime((string)$log['awarded_at']))) ?></div></div><?php endforeach; ?><?php if (!$pointsHistory): ?><div class="text-slate-500">No points awarded yet.</div><?php endif; ?></div></section>
</div></body></html>
