<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/report_bootstrap.php';

[$boundFrom, $boundTo] = clinic_reports_bounds();
$payments = clinic_sp_rows('sp_payments_list');
$transfers = clinic_sp_rows('sp_account_transfers_list');
$accounts = clinic_sp_rows('sp_accounts_list');

if ((string) ($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="cash-flow-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Date', 'Type', 'Detail', 'Amount', 'Flow']);
    foreach (clinic_report_cash_flow_ledger($payments, $transfers, $boundFrom, $boundTo) as $row) {
        fputcsv($out, [$row['Sent_Date'], $row['Type'], $row['Detail'], $row['Amount'], $row['Debit_Credit']]);
    }
    fclose($out);
    exit;
}

$cfLedger = clinic_report_cash_flow_ledger($payments, $transfers, $boundFrom, $boundTo);
$inflow = 0.0;
foreach ($payments as $p) {
    if (clinic_reports_datetime_in_bounds((string) ($p['Payment_Date'] ?? ''), $boundFrom, $boundTo)) {
        $inflow += (float) ($p['Amount'] ?? 0);
    }
}

clinic_reports_page_shell_start('Cash Flow & Accounts', 'Receipts and internal transfers in range, plus current account balances.');
clinic_report_date_filter_form();
clinic_report_action_bar();
?>

<div class="row g-3 mb-4">
    <?php clinic_metric_card('Payment inflows', clinic_money($inflow), 'ti-arrow-down-circle', 'success', 'Selected range'); ?>
    <?php clinic_metric_card('Ledger lines', count($cfLedger), 'ti-list-details', 'primary', 'Payments + transfers'); ?>
</div>

<div class="card report-data-card mb-4">
    <div class="card-header bg-white border-0 pt-3"><h5 class="mb-0">Account balances (now)</h5></div>
    <div class="card-body table-responsive">
        <table class="table table-sm clinic-table align-middle">
            <thead><tr><th>Account</th><th class="text-end">Balance</th></tr></thead>
            <tbody>
                <?php foreach ($accounts as $a): ?>
                    <tr>
                        <td><?php echo clinic_h($a['Account_Name'] ?? ''); ?></td>
                        <td class="text-end"><?php echo clinic_money((float) ($a['Current_Balance'] ?? 0)); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card report-data-card">
    <div class="card-header bg-white border-0 pt-3"><h5 class="mb-0">Movement ledger</h5></div>
    <div class="card-body table-responsive">
        <table class="table table-hover clinic-table align-middle">
            <thead><tr><th>Date</th><th>Type</th><th>Detail</th><th class="text-end">Amount</th><th>Flow</th></tr></thead>
            <tbody>
                <?php foreach ($cfLedger as $row): ?>
                    <tr>
                        <td><?php echo clinic_h($row['Sent_Date']); ?></td>
                        <td><?php echo clinic_h($row['Type']); ?></td>
                        <td><?php echo clinic_h($row['Detail']); ?></td>
                        <td class="text-end"><?php echo clinic_h($row['Amount']); ?></td>
                        <td><?php echo clinic_h($row['Debit_Credit']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php if ($cfLedger === []): ?>
            <div class="alert alert-light border mb-0">No payments or transfers in this range.</div>
        <?php endif; ?>
    </div>
</div>

<?php clinic_page_end(); ?>
