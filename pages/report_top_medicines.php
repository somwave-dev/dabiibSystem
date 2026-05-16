<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/report_bootstrap.php';

[$boundFrom, $boundTo] = clinic_reports_bounds();
$pharmacySales = clinic_sp_rows('sp_pharmacy_sales_list');
$medicines = clinic_sp_rows('sp_medicines_list');

if ((string) ($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="top-medicines-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Medicine', 'Units sold', 'Revenue']);
    foreach (clinic_report_top_medicines($pharmacySales, $medicines, $boundFrom, $boundTo, 100) as $r) {
        fputcsv($out, [$r['Medicine_Name'], $r['qty'], number_format($r['revenue'], 2, '.', '')]);
    }
    fclose($out);
    exit;
}

$topMeds = clinic_report_top_medicines($pharmacySales, $medicines, $boundFrom, $boundTo, 100);

clinic_reports_page_shell_start('Top Selling Medicines', 'Aggregated POS lines in the selected sale date range.');
clinic_report_date_filter_form();
clinic_report_action_bar();
?>

<div class="card report-data-card">
    <div class="card-header bg-white border-0 pt-3"><h5 class="mb-0">By revenue</h5></div>
    <div class="card-body table-responsive">
        <table class="table table-hover clinic-table align-middle">
            <thead><tr><th>Medicine</th><th class="text-end">Units</th><th class="text-end">Revenue</th></tr></thead>
            <tbody>
                <?php foreach ($topMeds as $r): ?>
                    <tr>
                        <td><?php echo clinic_h($r['Medicine_Name']); ?></td>
                        <td class="text-end"><?php echo (int) $r['qty']; ?></td>
                        <td class="text-end fw-semibold"><?php echo clinic_money($r['revenue']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php if ($topMeds === []): ?>
            <div class="alert alert-light border mb-0">No pharmacy sales in range.</div>
        <?php endif; ?>
    </div>
</div>

<?php clinic_page_end(); ?>
