<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/report_bootstrap.php';

[$boundFrom, $boundTo] = clinic_reports_bounds();
$smsLogs = clinic_sp_rows('sp_sms_logs_list');

if ((string) ($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="sms-report-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Sent', 'Patient', 'Type', 'Message']);
    foreach ($smsLogs as $s) {
        if (!clinic_reports_datetime_in_bounds((string) ($s['Sent_Date'] ?? ''), $boundFrom, $boundTo)) {
            continue;
        }
        fputcsv($out, [(string) ($s['Sent_Date'] ?? ''), (string) ($s['Patient_Name'] ?? ''), (string) ($s['Message_Type'] ?? ''), (string) ($s['Message_Body'] ?? '')]);
    }
    fclose($out);
    exit;
}

$smsFiltered = array_values(array_filter($smsLogs, static fn ($s) => clinic_reports_datetime_in_bounds((string) ($s['Sent_Date'] ?? ''), $boundFrom, $boundTo)));
$byType = [];
foreach ($smsFiltered as $s) {
    $t = (string) ($s['Message_Type'] ?? '');
    $byType[$t] = ($byType[$t] ?? 0) + 1;
}

clinic_reports_page_shell_start('SMS Delivery Report', 'Filters on sent timestamp; finer delivery status depends on gateway logging.');
clinic_report_date_filter_form();
clinic_report_action_bar();
?>

<div class="row g-3 mb-4">
    <?php clinic_metric_card('Messages in range', count($smsFiltered), 'ti-message-dots', 'primary', ''); ?>
</div>

<div class="card report-data-card mb-4">
    <div class="card-header bg-white border-0 pt-3"><h5 class="mb-0">By type</h5></div>
    <div class="card-body table-responsive">
        <table class="table table-sm clinic-table align-middle mb-0">
            <thead><tr><th>Type</th><th class="text-end">Count</th></tr></thead>
            <tbody>
                <?php foreach ($byType as $name => $cnt): ?>
                    <tr><td><?php echo clinic_h($name); ?></td><td class="text-end"><?php echo (int) $cnt; ?></td></tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card report-data-card">
    <div class="card-header bg-white border-0 pt-3"><h5 class="mb-0">Chronological</h5></div>
    <div class="card-body table-responsive">
        <table class="table table-hover clinic-table align-middle">
            <thead><tr><th>Sent</th><th>Patient</th><th>Type</th><th>Message</th></tr></thead>
            <tbody>
                <?php foreach ($smsFiltered as $s): ?>
                    <tr>
                        <td><?php echo clinic_h((string) ($s['Sent_Date'] ?? '')); ?></td>
                        <td><?php echo clinic_h((string) ($s['Patient_Name'] ?? '—')); ?></td>
                        <td><?php echo clinic_h((string) ($s['Message_Type'] ?? '')); ?></td>
                        <td class="small"><?php echo clinic_h(substr((string) ($s['Message_Body'] ?? ''), 0, 160)); ?><?php echo strlen((string) ($s['Message_Body'] ?? '')) > 160 ? '…' : ''; ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php if ($smsFiltered === []): ?>
            <div class="alert alert-light border mb-0">No SMS records in range.</div>
        <?php endif; ?>
    </div>
</div>

<?php clinic_page_end(); ?>
