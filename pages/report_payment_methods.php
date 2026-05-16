<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/report_bootstrap.php';

[$boundFrom, $boundTo] = clinic_reports_bounds();
$bucket = (string) ($_GET['bucket'] ?? 'month');
if (!in_array($bucket, ['day', 'month'], true)) {
    $bucket = 'month';
}

$payments = clinic_sp_rows('sp_payments_list');
$rows = clinic_report_payment_methods_breakdown($payments, $boundFrom, $boundTo, $bucket);

if ((string) ($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="payment-methods-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Period', 'Method', 'Count', 'Amount']);
    foreach ($rows as $r) {
        fputcsv($out, [$r['period'], $r['method'], $r['count'], number_format($r['amount'], 2, '.', '')]);
    }
    fclose($out);
    exit;
}

$methods = [];
foreach ($rows as $r) {
    $m = (string) ($r['method'] ?? '');
    if (!isset($methods[$m])) {
        $methods[$m] = 0.0;
    }
    $methods[$m] += (float) ($r['amount'] ?? 0);
}
arsort($methods);

clinic_reports_page_shell_start('Payment Methods', 'Desk collections split by EVC, eDahab, cash, bank and calendar period.');
?>

<div class="report-actions-bar mb-4">
    <a class="btn btn-outline-secondary btn-sm" href="reports.php"><i class="ti ti-layout-grid-add me-1"></i>All reports</a>
    <div class="report-actions-push">
        <a class="btn btn-primary btn-sm" href="<?php echo clinic_h('report_payment_methods.php?' . http_build_query(array_merge(array_filter($_GET, static fn ($v) => $v !== '' && $v !== null), ['export' => 'csv']))); ?>"><i class="ti ti-file-spreadsheet me-1"></i>Download CSV</a>
        <button class="btn btn-light border btn-sm" type="button" onclick="window.print()"><i class="ti ti-printer me-1"></i>Print</button>
    </div>
</div>

<div class="card report-filter-sheet mb-4">
    <div class="card-body">
        <form class="row g-3 align-items-end" method="get" action="report_payment_methods.php">
            <?php $df = (string) ($_GET['date_from'] ?? ''); $dt = (string) ($_GET['date_to'] ?? ''); ?>
            <div class="col-sm-6 col-md-3">
                <label class="form-label small fw-semibold text-muted">From</label>
                <input class="form-control" type="date" name="date_from" value="<?php echo clinic_h($df); ?>">
            </div>
            <div class="col-sm-6 col-md-3">
                <label class="form-label small fw-semibold text-muted">To</label>
                <input class="form-control" type="date" name="date_to" value="<?php echo clinic_h($dt); ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold text-muted">Granularity</label>
                <select class="form-select" name="bucket">
                    <option value="month"<?php echo $bucket === 'month' ? ' selected' : ''; ?>>Monthly</option>
                    <option value="day"<?php echo $bucket === 'day' ? ' selected' : ''; ?>>Daily</option>
                </select>
            </div>
            <div class="col-auto d-flex gap-2">
                <button class="btn btn-dark" type="submit">Apply</button>
                <a class="btn btn-outline-secondary" href="report_payment_methods.php">Reset</a>
            </div>
        </form>
        <p class="small text-muted mt-3 mb-0">Shows payment rows only — not pharmacy credit sales.</p>
    </div>
</div>

<div class="row g-3 mb-4">
    <?php foreach (array_slice($methods, 0, 4, true) as $name => $amt): ?>
        <?php clinic_metric_card($name, clinic_money($amt), 'ti-wallet', 'success', 'Across selected periods'); ?>
    <?php endforeach; ?>
</div>

<div class="card report-data-card">
    <div class="card-header bg-white border-0 pt-3"><h5 class="mb-0">Period × method</h5></div>
    <div class="card-body table-responsive">
        <table class="table table-hover clinic-table align-middle">
            <thead><tr><th>Period</th><th>Method</th><th class="text-end">Count</th><th class="text-end">Amount</th></tr></thead>
            <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><code><?php echo clinic_h((string) ($r['period'] ?? '')); ?></code></td>
                        <td><?php echo clinic_h((string) ($r['method'] ?? '')); ?></td>
                        <td class="text-end"><?php echo (int) ($r['count'] ?? 0); ?></td>
                        <td class="text-end fw-semibold"><?php echo clinic_money((float) ($r['amount'] ?? 0)); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php if ($rows === []): ?>
            <div class="alert alert-light border mb-0">No payments in range.</div>
        <?php endif; ?>
    </div>
</div>

<?php clinic_page_end(); ?>
