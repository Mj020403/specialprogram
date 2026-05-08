<?php
require_once dirname(__DIR__, 3) . '/app/bootstrap.php';
require_once __DIR__ . '/report_builder.php';

$conn = db_conn();
app_require('app/includes/auth.php');
require_role(['task_force','admin','mayor']);

$filters = report_build_filters($conn);
$columns = report_selected_columns_from_request();
$rows = report_fetch_rows($conn, $filters, null);
$available = report_available_columns();

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=' . report_export_filename('csv'));
$out = fopen('php://output', 'w');
fputcsv($out, array_map(static fn($key) => $available[$key]['label'], $columns));
foreach ($rows as $row) {
    $line = [];
    foreach ($columns as $column) {
        $line[] = report_cell_display($column, $row);
    }
    fputcsv($out, $line);
}
fclose($out);
exit;
