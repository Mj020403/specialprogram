<?php
require_once dirname(__DIR__, 3) . '/app/bootstrap.php';
$conn = db_conn();
app_require('app/includes/auth.php');
app_require('app/includes/module_platform.php');
app_require('app/includes/helpers/module_family_views.php');
require_role(['task_force','admin','mayor']);
ensure_family_upgrade_schema($conn);
$q = trim((string)($_GET['q'] ?? ''));
$barangayFilter = (int)($_GET['barangay_id'] ?? 0);
$statusFilter = trim((string)($_GET['qualification_status'] ?? ''));
$profileFilter = trim((string)($_GET['profile_filter'] ?? ''));
$recordStatusFilter = trim((string)($_GET['record_status'] ?? 'active')) ?: 'active';
$rows = fetch_all_assoc($conn, household_search_sql($conn, $q, $barangayFilter, $statusFilter, $recordStatusFilter, $profileFilter));
?><!DOCTYPE html><html><head><meta charset="UTF-8"><title>Filtered families</title><style>body{font-family:Arial,sans-serif;color:#111;padding:24px}h1{margin:0 0 6px}.meta{color:#555;font-size:13px;margin-bottom:14px}.chip{display:inline-block;border:1px solid #d1d5db;border-radius:999px;padding:6px 10px;font-size:12px;margin-right:6px;margin-top:6px}table{width:100%;border-collapse:collapse;margin-top:20px;font-size:12px}th,td{border:1px solid #ccc;padding:8px;text-align:left}th{background:#f3f4f6}</style></head><body onload="window.print()"><h1>Filtered families</h1><div class="meta">Operational export based on the same filters applied inside the Families module.</div><div><?php if($barangayFilter>0): ?><span class="chip">Barangay filter applied</span><?php endif; ?><?php if($statusFilter!==''): ?><span class="chip">Status: <?= e($statusFilter) ?></span><?php endif; ?><?php if($profileFilter!==''): ?><span class="chip">Profile: <?= e(str_replace('_',' ', $profileFilter)) ?></span><?php endif; ?><?php if($recordStatusFilter!==''): ?><span class="chip">Record: <?= e($recordStatusFilter) ?></span><?php endif; ?></div><table><thead><tr><th>Household Code</th><th>Head of Family</th><th>Barangay</th><th>Contact</th><th>Members</th><th>Record Status</th><th>Qualification</th><th>Score</th><th>Farming</th><th>4Ps</th><th>Priority</th><th>LGU Aid</th></tr></thead><tbody><?php foreach($rows as $row): ?><tr><td><?= e($row['household_code']) ?></td><td><?= e($row['household_head_name']) ?></td><td><?= e($row['barangay_name']) ?></td><td><?= e($row['contact_number']) ?></td><td><?= e((string)$row['household_size']) ?></td><td><?= e($row['record_status']) ?></td><td><?= e($row['qualification_status']) ?></td><td><?= e((string)$row['score']) ?></td><td><?= !empty($row['farming_household']) ? 'Yes' : 'No' ?></td><td><?= !empty($row['is_4ps']) ? 'Yes' : 'No' ?></td><td><?= e($row['priority_level'] ?? '') ?></td><td><?= !empty($row['receives_lgu_assistance']) ? 'Yes' : 'No' ?></td></tr><?php endforeach; if(!$rows): ?><tr><td colspan="12">No matching families found.</td></tr><?php endif; ?></tbody></table></body></html>
