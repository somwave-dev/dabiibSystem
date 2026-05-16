<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/report_bootstrap.php';

$allPatients = clinic_sp_rows('sp_patients_list');
$rows = clinic_report_guarantor_liability($allPatients);

if ((string) ($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="guarantor-liability-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Guarantor ID', 'Responsible party', 'Debtor accounts', 'Total outstanding']);
    foreach ($rows as $r) {
        fputcsv($out, [(int) ($r['guarantor_id'] ?? 0), (string) ($r['guarantor_name'] ?? ''), (int) ($r['dependent_count'] ?? 0), number_format((float) ($r['total_outstanding'] ?? 0), 2, '.', '')]);
    }
    fclose($out);
    exit;
}

$grand = array_sum(array_map(static fn ($r) => (float) ($r['total_outstanding'] ?? 0), $rows));
$accCounts = array_sum(array_map(static fn ($r) => (int) ($r['dependent_count'] ?? 0), $rows));

clinic_reports_page_shell_start('Guarantor Liability', 'Sums outstanding patient balances by linked guarantor account (from Guarantor_ID).');
clinic_report_action_bar();
?>

<div class="row g-3 mb-4">
    <?php clinic_metric_card('Groups', count($rows), 'ti-users-group', 'primary', 'Guarantor or self bucket'); ?>
    <?php clinic_metric_card('Debtor accounts', $accCounts, 'ti-user', 'warning', 'Patients with balance'); ?>
    <?php clinic_metric_card('Total outstanding', clinic_money($grand), 'ti-cash-off', 'danger', 'Displayed groups'); ?>
</div>

<div class="card report-data-card">
    <div class="card-header bg-white border-0 pt-3"><h5 class="mb-0">Liability by guarantor</h5></div>
    <div class="card-body table-responsive">
        <table class="table table-hover clinic-table align-middle">
            <thead><tr><th class="text-end">ID</th><th>Responsible party</th><th class="text-end">Debtor accounts</th><th class="text-end">Total AR</th></tr></thead>
            <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td class="text-end text-muted"><?php echo (int) ($r['guarantor_id'] ?? 0); ?></td>
                        <td class="fw-semibold"><?php echo clinic_h((string) ($r['guarantor_name'] ?? '')); ?></td>
                        <td class="text-end"><?php echo (int) ($r['dependent_count'] ?? 0); ?></td>
                        <td class="text-end"><strong class="text-danger"><?php echo clinic_money((float) ($r['total_outstanding'] ?? 0)); ?></strong></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php if ($rows === []): ?>
            <div class="alert alert-light border mb-0">No outstanding balances on file.</div>
        <?php endif; ?>
    </div>
</div>

<?php clinic_page_end(); ?>
