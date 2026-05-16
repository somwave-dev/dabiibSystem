<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/report_bootstrap.php';

[$boundFrom, $boundTo] = clinic_reports_bounds();
$payments = clinic_sp_rows('sp_payments_list');
$visits = clinic_sp_rows('sp_visits_list');
$doctors = clinic_sp_rows('sp_doctors_list');
$pharmacySales = clinic_sp_rows('sp_pharmacy_sales_list');
$labResults = clinic_sp_rows('sp_lab_results_list');
$labTests = clinic_sp_rows('sp_lab_tests_list');
$nursingRecords = clinic_sp_rows('sp_nursing_records_list');
$nursingServices = clinic_sp_rows('sp_nursing_services_list');

$labPrices = clinic_report_lab_prices($labTests);
$servicePrices = clinic_report_service_prices($nursingServices);

if ((string) ($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="revenue-category-' . date('Y-m-d') . '.csv"');
    $rev = clinic_report_revenue_category($pharmacySales, $payments, $labResults, $labPrices, $nursingRecords, $servicePrices, $visits, $doctors, $boundFrom, $boundTo);
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Category', 'Amount', 'Hint']);
    foreach ($rev['rows'] as $r) {
        fputcsv($out, [$r['category'], number_format($r['amount'], 2, '.', ''), $r['hint']]);
    }
    fputcsv($out, []);
    fputcsv($out, ['Payment method', 'Count', 'Amount']);
    foreach ($rev['payment_methods'] as $p) {
        fputcsv($out, [$p['method'], $p['count'], number_format($p['amount'], 2, '.', '')]);
    }
    fclose($out);
    exit;
}

$revData = clinic_report_revenue_category($pharmacySales, $payments, $labResults, $labPrices, $nursingRecords, $servicePrices, $visits, $doctors, $boundFrom, $boundTo);
$sumCat = array_sum(array_map(static fn ($r) => $r['amount'], $revData['rows']));

clinic_reports_page_shell_start('Revenue by Category', 'Parallel indicators — not a deduplicated P&L. Use as directional signals.');
clinic_report_date_filter_form();
clinic_report_action_bar();
?>

<div class="row g-3 mb-4">
    <?php clinic_metric_card('Sum of lines', clinic_money($sumCat), 'ti-sum', 'warning', 'Categories add different revenue concepts'); ?>
</div>

<div class="card report-data-card mb-4">
    <div class="card-header bg-white border-0 pt-3"><h5 class="mb-0">Category mix</h5></div>
    <div class="card-body table-responsive">
        <table class="table table-hover clinic-table align-middle">
            <thead><tr><th>Category</th><th class="text-end">Amount</th><th>Notes</th></tr></thead>
            <tbody>
                <?php foreach ($revData['rows'] as $r): ?>
                    <tr>
                        <td><?php echo clinic_h($r['category']); ?></td>
                        <td class="text-end fw-semibold"><?php echo clinic_money($r['amount']); ?></td>
                        <td class="small text-muted"><?php echo clinic_h($r['hint']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card report-data-card">
    <div class="card-header bg-white border-0 pt-3"><h5 class="mb-0">Collections by payment method</h5></div>
    <div class="card-body table-responsive">
        <table class="table table-hover clinic-table align-middle">
            <thead><tr><th>Method</th><th class="text-end">Transactions</th><th class="text-end">Amount</th></tr></thead>
            <tbody>
                <?php foreach ($revData['payment_methods'] as $p): ?>
                    <tr>
                        <td><?php echo clinic_h($p['method']); ?></td>
                        <td class="text-end"><?php echo (int) $p['count']; ?></td>
                        <td class="text-end"><?php echo clinic_money($p['amount']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php clinic_page_end(); ?>
