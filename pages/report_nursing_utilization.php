<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/report_bootstrap.php';

[$boundFrom, $boundTo] = clinic_reports_bounds();
$nursingRecords = clinic_sp_rows('sp_nursing_records_list');
$nursingServices = clinic_sp_rows('sp_nursing_services_list');
$prices = clinic_report_service_prices($nursingServices);
$rows = clinic_report_nursing_utilization($nursingRecords, $prices, $boundFrom, $boundTo);

if ((string) ($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="nursing-utilization-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Service ID', 'Service', 'Count', 'Revenue']);
    foreach ($rows as $r) {
        fputcsv($out, [$r['service_id'], $r['service_name'], $r['count'], number_format($r['revenue'], 2, '.', '')]);
    }
    fclose($out);
    exit;
}

$acts = array_sum(array_map(static fn ($r) => (int) ($r['count'] ?? 0), $rows));
$rev = array_sum(array_map(static fn ($r) => (float) ($r['revenue'] ?? 0), $rows));

clinic_reports_page_shell_start('Nursing Utilization', 'Nursing service mix and estimated revenue from catalogue prices × records.');
clinic_report_date_filter_form();
clinic_report_action_bar();
?>

<div class="row g-3 mb-4">
    <?php clinic_metric_card('Service events', $acts, 'ti-nurse', 'primary', 'In range'); ?>
    <?php clinic_metric_card('Attributed revenue', clinic_money($rev), 'ti-currency-dollar', 'success', 'Count × list price'); ?>
</div>

<div class="card report-data-card">
    <div class="card-header bg-white border-0 pt-3"><h5 class="mb-0">By service</h5></div>
    <div class="card-body table-responsive">
        <table class="table table-hover clinic-table align-middle">
            <thead><tr><th>Service</th><th class="text-end">Events</th><th class="text-end">Revenue</th></tr></thead>
            <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><?php echo clinic_h((string) ($r['service_name'] ?? '')); ?></td>
                        <td class="text-end"><?php echo (int) ($r['count'] ?? 0); ?></td>
                        <td class="text-end fw-semibold"><?php echo clinic_money((float) ($r['revenue'] ?? 0)); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php if ($rows === []): ?>
            <div class="alert alert-light border mb-0">No nursing records in range.</div>
        <?php endif; ?>
    </div>
</div>

<?php clinic_page_end(); ?>
