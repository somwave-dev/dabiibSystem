<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/report_bootstrap.php';

[$boundFrom, $boundTo] = clinic_reports_bounds();
$transfers = clinic_sp_rows('sp_account_transfers_list');

$filtered = array_values(array_filter($transfers, static function (array $t) use ($boundFrom, $boundTo): bool {
    return clinic_reports_datetime_in_bounds((string) ($t['Transfer_Date'] ?? ''), $boundFrom, $boundTo);
}));

if ((string) ($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="account-transfers-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Date', 'From', 'To', 'Amount', 'User']);
    foreach ($filtered as $t) {
        fputcsv($out, [
            (string) ($t['Transfer_Date'] ?? ''),
            (string) ($t['From_Account_Name'] ?? ''),
            (string) ($t['To_Account_Name'] ?? ''),
            number_format((float) ($t['Amount'] ?? 0), 2, '.', ''),
            (string) ($t['Username'] ?? ''),
        ]);
    }
    fclose($out);
    exit;
}

$volume = array_sum(array_map(static fn ($t) => (float) ($t['Amount'] ?? 0), $filtered));

clinic_reports_page_shell_start('Account Transfers', 'Internal movements between clinic accounts (cash box, bank, etc.).');
clinic_report_date_filter_form();
clinic_report_action_bar();
?>

<div class="row g-3 mb-4">
    <?php clinic_metric_card('Transfers in range', count($filtered), 'ti-arrows-transfer-up', 'primary', ''); ?>
    <?php clinic_metric_card('Amount moved', clinic_money($volume), 'ti-cash-move', 'warning', 'Sum of amounts'); ?>
</div>

<div class="card report-data-card">
    <div class="card-header bg-white border-0 pt-3"><h5 class="mb-0">Audit trail</h5></div>
    <div class="card-body table-responsive">
        <table class="table table-hover clinic-table align-middle">
            <thead><tr><th>Date</th><th>From</th><th>To</th><th class="text-end">Amount</th><th>Recorded by</th></tr></thead>
            <tbody>
                <?php foreach ($filtered as $t): ?>
                    <tr>
                        <td><?php echo clinic_h((string) ($t['Transfer_Date'] ?? '')); ?></td>
                        <td><?php echo clinic_h((string) ($t['From_Account_Name'] ?? '')); ?></td>
                        <td><?php echo clinic_h((string) ($t['To_Account_Name'] ?? '')); ?></td>
                        <td class="text-end fw-semibold"><?php echo clinic_money((float) ($t['Amount'] ?? 0)); ?></td>
                        <td><?php echo clinic_h((string) ($t['Username'] ?? '—')); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php if ($filtered === []): ?>
            <div class="alert alert-light border mb-0">No transfers in date range.</div>
        <?php endif; ?>
    </div>
</div>

<?php clinic_page_end(); ?>
