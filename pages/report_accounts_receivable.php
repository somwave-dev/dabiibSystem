<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/report_bootstrap.php';

$allPatients = clinic_sp_rows('sp_patients_list');
$payments = clinic_sp_rows('sp_payments_list');

$search = trim((string) ($_GET['q'] ?? ''));
$typeFilter = (string) ($_GET['type'] ?? '');
if (!in_array($typeFilter, ['Bille', 'Maalinle'], true)) {
    $typeFilter = '';
}

$minBalance = (float) str_replace(',', '', (string) ($_GET['min_balance'] ?? '0'));
if ($minBalance < 0) {
    $minBalance = 0;
}

$paymentStats = [];
foreach ($payments as $payment) {
    $patientId = (int) ($payment['Patient_ID'] ?? 0);
    if ($patientId <= 0) {
        continue;
    }
    if (!isset($paymentStats[$patientId])) {
        $paymentStats[$patientId] = ['count' => 0, 'total_paid' => 0.0, 'last_payment_date' => null];
    }
    $paymentStats[$patientId]['count']++;
    $paymentStats[$patientId]['total_paid'] += (float) ($payment['Amount'] ?? 0);
    $paymentDate = (string) ($payment['Payment_Date'] ?? '');
    if ($paymentDate !== '' && ($paymentStats[$patientId]['last_payment_date'] === null || $paymentDate > $paymentStats[$patientId]['last_payment_date'])) {
        $paymentStats[$patientId]['last_payment_date'] = $paymentDate;
    }
}

$debtPatients = array_values(array_filter($allPatients, static fn (array $patient): bool => (float) ($patient['Current_Balance'] ?? 0) > 0));

foreach ($debtPatients as &$patient) {
    $patientId = (int) ($patient['Patient_ID'] ?? 0);
    $patient['Payment_Count'] = $paymentStats[$patientId]['count'] ?? 0;
    $patient['Total_Paid'] = $paymentStats[$patientId]['total_paid'] ?? 0.0;
    $patient['Last_Payment_Date'] = $paymentStats[$patientId]['last_payment_date'] ?? null;
}
unset($patient);

usort($debtPatients, static function (array $left, array $right): int {
    $balanceSort = (float) ($right['Current_Balance'] ?? 0) <=> (float) ($left['Current_Balance'] ?? 0);
    return $balanceSort !== 0 ? $balanceSort : strcasecmp((string) ($left['Full_Name'] ?? ''), (string) ($right['Full_Name'] ?? ''));
});

$filteredPatients = array_values(array_filter($debtPatients, static function (array $patient) use ($search, $typeFilter, $minBalance): bool {
    if ($typeFilter !== '' && (string) ($patient['Patient_Type'] ?? '') !== $typeFilter) {
        return false;
    }
    if ((float) ($patient['Current_Balance'] ?? 0) < $minBalance) {
        return false;
    }
    if ($search === '') {
        return true;
    }

    return stripos((string) ($patient['Full_Name'] ?? ''), $search) !== false
        || stripos((string) ($patient['Phone_Number'] ?? ''), $search) !== false
        || stripos((string) ($patient['Guarantor_Name'] ?? ''), $search) !== false;
}));

if ((string) ($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="accounts-receivable-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Patient ID', 'Patient', 'Phone', 'Type', 'Guarantor', 'Credit Limit', 'Balance AR', 'Total Paid', 'Last Payment']);
    foreach ($filteredPatients as $patient) {
        fputcsv($out, [
            (int) ($patient['Patient_ID'] ?? 0),
            (string) ($patient['Full_Name'] ?? ''),
            (string) ($patient['Phone_Number'] ?? ''),
            (string) ($patient['Patient_Type'] ?? ''),
            (string) ($patient['Guarantor_Name'] ?? ''),
            number_format((float) ($patient['Credit_Limit'] ?? 0), 2, '.', ''),
            number_format((float) ($patient['Current_Balance'] ?? 0), 2, '.', ''),
            number_format((float) ($patient['Total_Paid'] ?? 0), 2, '.', ''),
            (string) ($patient['Last_Payment_Date'] ?? ''),
        ]);
    }
    fclose($out);
    exit;
}

$totalDebt = array_sum(array_map(static fn (array $patient): float => (float) ($patient['Current_Balance'] ?? 0), $filteredPatients));
$patientsOwing = count($filteredPatients);
$overCreditLimit = count(array_filter($filteredPatients, static fn (array $patient): bool => (float) ($patient['Credit_Limit'] ?? 0) > 0 && (float) ($patient['Current_Balance'] ?? 0) > (float) ($patient['Credit_Limit'] ?? 0)));
$averageDebt = $patientsOwing > 0 ? $totalDebt / $patientsOwing : 0;

clinic_reports_page_shell_start('Accounts Receivable', 'Outstanding patient balances versus credit limits — AR desk view.');
$queryParams = array_filter(['q' => $search, 'type' => $typeFilter, 'min_balance' => $minBalance > 0 ? $minBalance : ''], static fn (mixed $v) => $v !== '' && $v !== null);
$csvQs = http_build_query($queryParams + ['export' => 'csv']);
?>

<div class="report-actions-bar mb-4">
    <a class="btn btn-outline-secondary btn-sm" href="reports.php"><i class="ti ti-layout-grid-add me-1"></i>All reports</a>
    <div class="report-actions-push">
        <a class="btn btn-primary btn-sm" href="report_accounts_receivable.php?<?php echo clinic_h($csvQs); ?>"><i class="ti ti-file-spreadsheet me-1"></i>Download CSV</a>
        <button class="btn btn-light border btn-sm" type="button" onclick="window.print()"><i class="ti ti-printer me-1"></i>Print</button>
    </div>
</div>

<div class="card report-filter-sheet mb-4">
    <div class="card-body">
        <form class="row g-3 align-items-end" method="get" action="report_accounts_receivable.php">
            <div class="col-md-5">
                <label class="form-label small fw-semibold text-muted">Search</label>
                <input class="form-control" type="search" name="q" value="<?php echo clinic_h($search); ?>" placeholder="Patient, phone, or guarantor">
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold text-muted">Type</label>
                <select class="form-select" name="type">
                    <option value="">All</option>
                    <option value="Bille"<?php echo $typeFilter === 'Bille' ? ' selected' : ''; ?>>Bille</option>
                    <option value="Maalinle"<?php echo $typeFilter === 'Maalinle' ? ' selected' : ''; ?>>Maalinle</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold text-muted">Min balance</label>
                <input class="form-control" type="number" step="0.01" min="0" name="min_balance" value="<?php echo $minBalance > 0 ? clinic_h($minBalance) : ''; ?>" placeholder="0">
            </div>
            <div class="col-auto d-flex gap-2">
                <button class="btn btn-dark" type="submit"><i class="ti ti-filter me-1"></i>Filter</button>
                <a class="btn btn-outline-secondary" href="report_accounts_receivable.php">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="row g-3 mb-4">
    <?php clinic_metric_card('Accounts with AR', $patientsOwing, 'ti-users', 'danger', 'Current_Balance &gt; 0'); ?>
    <?php clinic_metric_card('Total AR', clinic_money($totalDebt), 'ti-cash-banknote', 'warning', 'Sum of balances'); ?>
    <?php clinic_metric_card('Over credit limit', $overCreditLimit, 'ti-alert-triangle', 'primary', ''); ?>
    <?php clinic_metric_card('Average AR / account', clinic_money($averageDebt), 'ti-chart-dots', 'success', ''); ?>
</div>

<div class="card report-data-card">
    <div class="card-header bg-white border-0 pt-3 pb-0">
        <h5 class="mb-0">Receivable detail</h5>
        <div class="small text-muted mb-2">Generated <?php echo clinic_h(date('Y-m-d H:i')); ?></div>
    </div>
    <div class="card-body table-responsive">
        <table class="table table-hover align-middle clinic-table">
            <thead>
                <tr>
                    <th>Patient</th>
                    <th>Phone</th>
                    <th>Type</th>
                    <th>Guarantor</th>
                    <th>Credit limit</th>
                    <th>Outstanding</th>
                    <th>Paid lifetime</th>
                    <th>Last payment</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($filteredPatients as $patient): ?>
                    <?php
                    $balance = (float) ($patient['Current_Balance'] ?? 0);
                    $creditLimit = (float) ($patient['Credit_Limit'] ?? 0);
                    $overLimit = $creditLimit > 0 && $balance > $creditLimit;
                    ?>
                    <tr>
                        <td>
                            <span class="fw-semibold"><?php echo clinic_h($patient['Full_Name'] ?? '-'); ?></span>
                            <span class="d-block small text-muted">#<?php echo (int) ($patient['Patient_ID'] ?? 0); ?></span>
                        </td>
                        <td><?php echo clinic_h($patient['Phone_Number'] ?? '-'); ?></td>
                        <td><span class="badge text-bg-<?php echo ($patient['Patient_Type'] ?? '') === 'Bille' ? 'info' : 'secondary'; ?>"><?php echo clinic_h($patient['Patient_Type'] ?? '-'); ?></span></td>
                        <td><?php echo clinic_h($patient['Guarantor_Name'] ?? '-'); ?></td>
                        <td><?php echo clinic_money($creditLimit); ?> <?php if ($overLimit): ?><span class="badge text-bg-danger ms-1">Over limit</span><?php endif; ?></td>
                        <td><strong class="text-danger"><?php echo clinic_money($balance); ?></strong></td>
                        <td><?php echo clinic_money($patient['Total_Paid'] ?? 0); ?></td>
                        <td><?php echo clinic_h($patient['Last_Payment_Date'] ?? '-'); ?></td>
                        <td class="text-end">
                            <a class="btn btn-sm btn-light border" href="patients.php?profile_id=<?php echo (int) ($patient['Patient_ID'] ?? 0); ?>&view=table">Profile</a>
                            <a class="btn btn-sm btn-success" href="payments.php?patient_id=<?php echo (int) ($patient['Patient_ID'] ?? 0); ?>">Collect</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php if ($filteredPatients === []): ?>
            <div class="alert alert-light border text-center mb-0">No receivable balances match these filters.</div>
        <?php endif; ?>
    </div>
</div>

<?php clinic_page_end(); ?>
