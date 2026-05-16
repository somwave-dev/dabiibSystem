<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/report_bootstrap.php';

$lowStockThreshold = max(1, (int) ($_GET['threshold'] ?? 100));
$medicines = clinic_sp_rows('sp_medicines_list');

if ((string) ($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="low-stock-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Medicine', 'Stock', 'Note', 'Expiry']);
    foreach ($medicines as $m) {
        if ((int) ($m['Stock_Quantity'] ?? 0) > $lowStockThreshold) {
            continue;
        }
        fputcsv($out, [(string) ($m['Medicine_Name'] ?? ''), (int) ($m['Stock_Quantity'] ?? 0), '≤ ' . $lowStockThreshold, (string) ($m['Expiry_Date'] ?? '')]);
    }
    fclose($out);
    exit;
}

$lowRows = [];
foreach ($medicines as $m) {
    if ((int) ($m['Stock_Quantity'] ?? 0) <= $lowStockThreshold) {
        $lowRows[] = $m;
    }
}
usort($lowRows, static fn ($a, $b) => ((int) ($a['Stock_Quantity'] ?? 0)) <=> ((int) ($b['Stock_Quantity'] ?? 0)));

clinic_reports_page_shell_start('Low Stock Alert', 'Compares on-hand quantity to a maximum safe level (default 100).');

$preserveQs = http_build_query(array_filter(['threshold' => (string) $lowStockThreshold], static fn ($x) => $x !== ''));
?>
<div class="report-actions-bar mb-4">
    <a class="btn btn-outline-secondary btn-sm" href="reports.php"><i class="ti ti-layout-grid-add me-1"></i>All reports</a>
    <div class="report-actions-push">
        <a class="btn btn-primary btn-sm" href="report_low_stock.php?<?php echo clinic_h($preserveQs !== '' ? $preserveQs . '&export=csv' : 'export=csv'); ?>"><i class="ti ti-file-spreadsheet me-1"></i>Download CSV</a>
        <button class="btn btn-light border btn-sm" type="button" onclick="window.print()"><i class="ti ti-printer me-1"></i>Print</button>
    </div>
</div>

<div class="card report-filter-sheet mb-4">
    <div class="card-body">
        <form class="row g-3 align-items-end" method="get" action="report_low_stock.php">
            <div class="col-md-4 col-lg-3">
                <label class="form-label small fw-semibold text-muted">Max stock threshold</label>
                <input class="form-control" type="number" name="threshold" min="1" value="<?php echo (int) $lowStockThreshold; ?>">
            </div>
            <div class="col-auto"><button class="btn btn-dark" type="submit">Apply</button></div>
        </form>
    </div>
</div>

<div class="row g-3 mb-4">
    <?php clinic_metric_card('SKU at risk', count($lowRows), 'ti-packages', 'danger', '≤ ' . (int) $lowStockThreshold); ?>
</div>

<div class="card report-data-card">
    <div class="card-header bg-white border-0 pt-3"><h5 class="mb-0">Items to reorder</h5></div>
    <div class="card-body table-responsive">
        <table class="table table-hover clinic-table align-middle">
            <thead><tr><th>Medicine</th><th class="text-end">Stock</th><th>Expiry</th></tr></thead>
            <tbody>
                <?php foreach ($lowRows as $m): ?>
                    <tr>
                        <td><?php echo clinic_h((string) ($m['Medicine_Name'] ?? '')); ?></td>
                        <td class="text-end"><span class="badge text-bg-danger"><?php echo (int) ($m['Stock_Quantity'] ?? 0); ?></span></td>
                        <td><?php echo clinic_h((string) ($m['Expiry_Date'] ?? '')); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php if ($lowRows === []): ?>
            <div class="alert alert-success border mb-0">All SKUs above the threshold.</div>
        <?php endif; ?>
    </div>
</div>

<?php clinic_page_end(); ?>
