<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/report_bootstrap.php';

[$boundFrom, $boundTo] = clinic_reports_bounds();
$statusFilter = (string) ($_GET['status'] ?? '');

$charges = clinic_sp_rows('sp_charges_list', [0, $statusFilter]);

$billedInRange = 0.0;
$countInRange = 0;
$collectedInRange = 0.0;
$outstanding = 0.0;
$openCount = 0;
$byCategory = [];
$byPerformer = [];
$debtorMap = [];
foreach ($charges as $c) {
    $amt = (float) ($c['Amount'] ?? 0);
    $paid = (float) ($c['Paid_Amount'] ?? 0);
    $due = (float) ($c['Due'] ?? 0);
    $date = (string) ($c['Charge_Date'] ?? '');
    $paidAt = (string) ($c['Paid_At'] ?? '');
    $inRange = clinic_reports_datetime_in_bounds($date, $boundFrom, $boundTo);
    $paidInRange = $paidAt !== '' && clinic_reports_datetime_in_bounds($paidAt, $boundFrom, $boundTo);
    if ($inRange) {
        $billedInRange += $amt;
        $countInRange++;
    }
    if ($paidInRange) {
        $collectedInRange += $paid;
    }
    $outstanding += $due;
    if ($due > 0) {
        $openCount++;
    }
    $cat = (string) ($c['Category'] ?? 'Other');
    if ($inRange) {
        $byCategory[$cat] = ($byCategory[$cat] ?? 0.0) + $amt;
    }
    $perf = (string) ($c['Performed_By_Name'] ?? '');
    if ($inRange) {
        $key = $perf !== '' ? $perf : '(unassigned)';
        $byPerformer[$key] = ($byPerformer[$key] ?? 0.0) + $amt;
    }
    if ($due > 0) {
        $pkey = (int) ($c['Patient_ID'] ?? 0);
        $pname = (string) ($c['Patient_Name'] ?? 'Patient #' . $pkey);
        if (!isset($debtorMap[$pkey])) {
            $debtorMap[$pkey] = ['patient_id' => $pkey, 'name' => $pname, 'due' => 0.0, 'count' => 0];
        }
        $debtorMap[$pkey]['due'] += $due;
        $debtorMap[$pkey]['count']++;
    }
}
arsort($byCategory);
arsort($byPerformer);
$debtors = array_values($debtorMap);
usort($debtors, static fn ($a, $b) => $b['due'] <=> $a['due']);
$debtors = array_slice($debtors, 0, 8);

$qsBase = array_merge(
    array_filter($_GET, static fn ($v) => $v !== '' && $v !== null),
    ['export' => 'csv']
);

if ((string) ($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="charges-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Date', 'Patient', 'Description', 'Category', 'Amount', 'Paid', 'Due', 'Performed By', 'Status']);
    foreach ($charges as $c) {
        fputcsv($out, [
            (string) ($c['Charge_Date'] ?? ''),
            (string) ($c['Patient_Name'] ?? ''),
            (string) ($c['Description'] ?? ''),
            (string) ($c['Category'] ?? ''),
            number_format((float) ($c['Amount'] ?? 0), 2, '.', ''),
            number_format((float) ($c['Paid_Amount'] ?? 0), 2, '.', ''),
            number_format((float) ($c['Due'] ?? 0), 2, '.', ''),
            (string) ($c['Performed_By_Name'] ?? ''),
            (float) ($c['Due'] ?? 0) > 0 ? 'Unpaid' : 'Paid',
        ]);
    }
    fclose($out);
    exit;
}

clinic_reports_page_shell_start('Charges & Bills Report', 'Billed services, what was collected, what is still owed, and who performed it.');
?>
<div class="report-actions-bar mb-4">
    <a class="btn btn-outline-secondary btn-sm" href="reports.php"><i class="ti ti-layout-grid-add me-1"></i>All reports</a>
    <div class="report-actions-push">
        <a class="btn btn-primary btn-sm" href="<?php echo clinic_h('report_charges.php?' . http_build_query($qsBase)); ?>"><i class="ti ti-file-spreadsheet me-1"></i>Download CSV</a>
        <button class="btn btn-light border btn-sm" type="button" onclick="window.print()"><i class="ti ti-printer me-1"></i>Print</button>
    </div>
</div>

<div class="card report-filter-sheet mb-4">
    <div class="card-body">
        <form class="row g-3 align-items-end" method="get" action="report_charges.php">
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
                <label class="form-label small fw-semibold text-muted">Status</label>
                <select class="form-select" name="status">
                    <option value="">All charges</option>
                    <option value="Unpaid"<?php echo $statusFilter === 'Unpaid' ? ' selected' : ''; ?>>Unpaid</option>
                    <option value="Paid"<?php echo $statusFilter === 'Paid' ? ' selected' : ''; ?>>Paid</option>
                </select>
            </div>
            <div class="col-auto d-flex gap-2">
                <button class="btn btn-dark" type="submit">Apply</button>
                <a class="btn btn-outline-secondary" href="report_charges.php">Reset</a>
            </div>
        </form>
        <p class="small text-muted mt-3 mb-0">A charge is created for every billable service (consultations are added automatically when an appointment is completed).</p>
    </div>
</div>

<div class="row g-3 mb-4">
    <?php clinic_metric_card('Billed (range)', clinic_money($billedInRange), 'ti-receipt-2', 'primary', $countInRange . ' charge(s) in range'); ?>
    <?php clinic_metric_card('Collected (range)', clinic_money($collectedInRange), 'ti-circle-check', 'success', 'Paid portions in range'); ?>
    <?php clinic_metric_card('Outstanding Now', clinic_money($outstanding), 'ti-wallet', 'danger', $openCount . ' open charge(s)'); ?>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-6">
        <div class="card report-data-card h-100">
            <div class="card-header bg-white border-0 pt-3"><h5 class="mb-0">Billed by Category</h5></div>
            <div class="card-body table-responsive">
                <table class="table table-hover clinic-table align-middle">
                    <thead><tr><th>Category</th><th class="text-end">Charged (range)</th></tr></thead>
                    <tbody>
                        <?php $catMax = $byCategory === [] ? 1.0 : max($byCategory); ?>
                        <?php foreach ($byCategory as $cat => $total): ?>
                        <tr>
                            <td>
                                <div class="d-flex justify-content-between">
                                    <span><?php echo clinic_h($cat); ?></span>
                                    <strong><?php echo clinic_money($total); ?></strong>
                                </div>
                                <div class="progress mt-1" style="height:6px;">
                                    <div class="progress-bar bg-primary" style="width: <?php echo $catMax > 0 ? round($total * 100 / $catMax) : 0; ?>%;"></div>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if ($byCategory === []): ?><tr><td class="text-muted py-3 text-center">No charges in range.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card report-data-card h-100">
            <div class="card-header bg-white border-0 pt-3"><h5 class="mb-0">By Provider (performed by)</h5></div>
            <div class="card-body table-responsive">
                <table class="table table-hover clinic-table align-middle">
                    <thead><tr><th>Provider</th><th class="text-end">Charged (range)</th></tr></thead>
                    <tbody>
                        <?php foreach ($byPerformer as $perf => $total): ?>
                        <tr>
                            <td><?php echo $perf === '(unassigned)' ? '<span class="text-muted">' . clinic_h($perf) . '</span>' : '@' . clinic_h($perf); ?></td>
                            <td class="text-end fw-semibold"><?php echo clinic_money($total); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if ($byPerformer === []): ?><tr><td class="text-muted py-3 text-center">No performed charges in range.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<div class="card report-data-card">
    <div class="card-header bg-white border-0 pt-3"><h5 class="mb-0">Top Open Bills</h5></div>
    <div class="card-body table-responsive">
        <table class="table table-hover clinic-table align-middle">
            <thead><tr><th>Patient</th><th class="text-end">Open charges</th><th class="text-end">Outstanding</th></tr></thead>
            <tbody>
                <?php foreach ($debtors as $d): ?>
                <tr>
                    <td>
                        <?php echo clinic_h($d['name']); ?>
                        <a class="small ms-2" href="charges.php?patient_id=<?php echo (int) $d['patient_id']; ?>">view bill</a>
                        <a class="small ms-2" href="payments.php?patient_id=<?php echo (int) $d['patient_id']; ?>">collect</a>
                    </td>
                    <td class="text-end"><?php echo (int) $d['count']; ?></td>
                    <td class="text-end fw-semibold text-danger"><?php echo clinic_money($d['due']); ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if ($debtors === []): ?><tr><td class="text-muted py-3 text-center">No open bills.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php clinic_page_end(); ?>