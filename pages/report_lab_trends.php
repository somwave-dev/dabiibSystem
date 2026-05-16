<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/report_bootstrap.php';

[$boundFrom, $boundTo] = clinic_reports_bounds();
$labResults = clinic_sp_rows('sp_lab_results_list');

if ((string) ($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="lab-trends-' . date('Y-m-d') . '.csv"');
    $trend = clinic_report_lab_trends($labResults, $boundFrom, $boundTo);
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Test', 'Completed count', 'Share %']);
    foreach ($trend['by_test'] as $r) {
        fputcsv($out, [$r['Test_Name'], $r['count'], $r['share_pct']]);
    }
    fclose($out);
    exit;
}

$labTrend = clinic_report_lab_trends($labResults, $boundFrom, $boundTo);
$ttl = array_sum(array_map(static fn ($x) => $x['total'], $labTrend['by_month']));

clinic_reports_page_shell_start('Lab Tests & Trends', 'Completed results with a recorded timestamp — keep lab completion dates up to date.');
clinic_report_date_filter_form();
clinic_report_action_bar();
?>

<div class="row g-3 mb-4">
    <?php clinic_metric_card('Completed events', $ttl, 'ti-microscope', 'primary', 'In range'); ?>
</div>

<div class="card report-data-card mb-4">
    <div class="card-header bg-white border-0 pt-3"><h5 class="mb-0">Volume by test</h5></div>
    <div class="card-body table-responsive">
        <table class="table table-hover clinic-table align-middle">
            <thead><tr><th>Test</th><th class="text-end">Count</th><th class="text-end">Share</th></tr></thead>
            <tbody>
                <?php foreach ($labTrend['by_test'] as $r): ?>
                    <tr>
                        <td><?php echo clinic_h($r['Test_Name']); ?></td>
                        <td class="text-end"><?php echo (int) $r['count']; ?></td>
                        <td class="text-end"><?php echo clinic_h((string) $r['share_pct']); ?>%</td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card report-data-card">
    <div class="card-header bg-white border-0 pt-3"><h5 class="mb-0">By calendar month</h5></div>
    <div class="card-body table-responsive">
        <table class="table table-sm clinic-table align-middle mb-0">
            <thead><tr><th>Month</th><th class="text-end">Completed</th></tr></thead>
            <tbody>
                <?php foreach ($labTrend['by_month'] as $m): ?>
                    <tr><td><?php echo clinic_h($m['month']); ?></td><td class="text-end"><?php echo (int) $m['total']; ?></td></tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php clinic_page_end(); ?>
