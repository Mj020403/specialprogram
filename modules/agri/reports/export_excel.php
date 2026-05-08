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

header('Content-Type: application/vnd.ms-excel; charset=utf-8');
header('Content-Disposition: attachment; filename=' . report_export_filename('xls'));

echo "<html><head><meta charset=\"UTF-8\"></head><body>";
echo '<table border="1">';
echo '<thead><tr>';
foreach ($columns as $column) {
    echo '<th>' . e($available[$column]['label']) . '</th>';
}
echo '</tr></thead><tbody>';
foreach ($rows as $row) {
    echo '<tr>';
    foreach ($columns as $column) {
        echo '<td>' . e(report_cell_display($column, $row)) . '</td>';
    }
    echo '</tr>';
}
echo '</tbody></table>';
echo "</body></html>";
exit;
