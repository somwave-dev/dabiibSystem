<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/report_bootstrap.php';

$expiryDaysAhead = max(1, min(730, (int) ($_GET['days_ahead'] ?? 90)));
$medicines = clinic_sp_rows('sp_medicines_list');

if ((string) ($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="expiring-medicines-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Medicine', 'Stock', 'Price', 'Expiry', 'Days to expiry']);
    foreach (clinic_report_expiring_medicines($medicines, $expiryDaysAhead) as $m) {
        fputcsv($out, [(string) ($m['Medicine_Name'] ?? ''), (int) ($m['Stock_Quantity'] ?? 0), number_format((float) ($m['Price'] ?? 0), 2, '.', ''), (string) ($m['Expiry_Date'] ?? ''), (int) ($m['Days_Until_Expiry'] ?? 0)]);
    }
    fclose($out);
    exit;
}

$expiring = clinic_report_expiring_medicines($medicines, $expiryDaysAhead);

clinic_reports_page_shell_start('Expiring Medicines', 'SKU list where expiry falls on or before today + horizon (includes overdue lots).');

$preserveQs = http_build_query(array_filter(['days_ahead' => (string) $expiryDaysAhead], static fn ($x) => $x !== ''));
?>
<div class="report-actions-bar mb-4">
    <a class="btn btn-outline-secondary btn-sm" href="reports.php"><i class="ti ti-layout-grid-add me-1"></i>All reports</a>
    <div class="report-actions-push">
        <a class="btn btn-primary btn-sm" href="report_expiring_medicines.php?<?php echo clinic_h($preserveQs !== '' ? $preserveQs . '&export=csv' : 'export=csv'); ?>"><i class="ti ti-file-spreadsheet me-1"></i>Download CSV</a>
        <button class="btn btn-light border btn-sm" type="button" onclick="window.print()"><i class="ti ti-printer me-1"></i>Print</button>
    </div>
</div>

<div class="card report-filter-sheet mb-4">
    <div class="card-body">
        <form class="row g-3 align-items-end" method="get" action="report_expiring_medicines.php">
            <div class="col-md-4 col-lg-3">
                <label class="form-label small fw-semibold text-muted">Horizon (days)</label>
                <input class="form-control" type="number" name="days_ahead" min="1" max="730" value="<?php echo (int) $expiryDaysAhead; ?>">
            </div>
            <div class="col-auto"><button class="btn btn-dark" type="submit">Apply</button></div>
        </form>
    </div>
</div>

<div class="row g-3 mb-4">
    <?php clinic_metric_card('SKU count', count($expiring), 'ti-hourglass-low', 'warning', 'Within ' . (int) $expiryDaysAhead . ' days'); ?>
</div>

<div class="card report-data-card">
    <div class="card-header bg-white border-0 pt-3"><h5 class="mb-0">Expiring lines</h5></div>
    <div class="card-body table-responsive">
        <table class="table table-hover clinic-table align-middle">
            <thead><tr><th>Medicine</th><th class="text-end">Stock</th><th class="text-end">Price</th><th>Expiry</th><th class="text-end">Days</th></tr></thead>
            <tbody>
                <?php foreach ($expiring as $m): ?>
                    <tr>
                        <td><?php echo clinic_h((string) ($m['Medicine_Name'] ?? '')); ?></td>
                        <td class="text-end"><?php echo (int) ($m['Stock_Quantity'] ?? 0); ?></td>
                        <td class="text-end"><?php echo clinic_money((float) ($m['Price'] ?? 0)); ?></td>
                        <td><?php echo clinic_h((string) ($m['Expiry_Date'] ?? '')); ?></td>
                        <td class="text-end"><?php echo (int) ($m['Days_Until_Expiry'] ?? 0); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php if ($expiring === []): ?>
            <div class="alert alert-success border mb-0">No stock expiring in this window (or expiry dates not set).</div>
        <?php endif; ?>
    </div>
</div>

<?php clinic_page_end(); ?>
