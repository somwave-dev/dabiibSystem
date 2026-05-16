<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/report_bootstrap.php';

[$boundFrom, $boundTo] = clinic_reports_bounds();
$labResults = clinic_sp_rows('sp_lab_results_list');
$labTests = clinic_sp_rows('sp_lab_tests_list');
$prices = clinic_report_lab_prices($labTests);
$rows = clinic_report_lab_volume_revenue($labResults, $prices, $boundFrom, $boundTo);

if ((string) ($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="lab-volume-revenue-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Test ID', 'Test', 'Completed count', 'Revenue']);
    foreach ($rows as $r) {
        fputcsv($out, [$r['test_id'], $r['test_name'], $r['count'], number_format($r['revenue'], 2, '.', '')]);
    }
    fclose($out);
    exit;
}

$tests = array_sum(array_map(static fn ($r) => (int) ($r['count'] ?? 0), $rows));
$rev = array_sum(array_map(static fn ($r) => (float) ($r['revenue'] ?? 0), $rows));

clinic_reports_page_shell_start('Lab Volume & Revenue', 'Completed results with Recorded_At — revenue = count × lab test list price.');
clinic_report_date_filter_form();
clinic_report_action_bar();
?>

<div class="row g-3 mb-4">
    <?php clinic_metric_card('Completed tests', $tests, 'ti-microscope', 'primary', ''); ?>
    <?php clinic_metric_card('Catalogue revenue', clinic_money($rev), 'ti-currency-dollar', 'success', ''); ?>
</div>

<div class="card report-data-card">
    <div class="card-header bg-white border-0 pt-3"><h5 class="mb-0">Per test type</h5></div>
    <div class="card-body table-responsive">
        <table class="table table-hover clinic-table align-middle">
            <thead><tr><th>Test</th><th class="text-end">Count</th><th class="text-end">Revenue</th></tr></thead>
            <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><?php echo clinic_h((string) ($r['test_name'] ?? '')); ?></td>
                        <td class="text-end"><?php echo (int) ($r['count'] ?? 0); ?></td>
                        <td class="text-end fw-semibold"><?php echo clinic_money((float) ($r['revenue'] ?? 0)); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php if ($rows === []): ?>
            <div class="alert alert-light border mb-0">No completed tests with recorded dates in range.</div>
        <?php endif; ?>
    </div>
</div>

<?php clinic_page_end(); ?>
