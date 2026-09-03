<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/report_bootstrap.php';

[$boundFrom, $boundTo] = clinic_reports_bounds();
$appointments = clinic_doctor_scoped_list('sp_appointments_list');

if ((string) ($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="appointments-' . date('Y-m-d') . '.csv"');
    $att = clinic_report_appointment_attendance($appointments, $boundFrom, $boundTo);
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Date', 'Patient', 'Doctor', 'Status']);
    foreach ($att['recent'] as $a) {
        fputcsv($out, [(string) ($a['Appointment_Date'] ?? ''), (string) ($a['Patient_Name'] ?? ''), (string) ($a['Doctor_Name'] ?? ''), (string) ($a['Status'] ?? '')]);
    }
    fclose($out);
    exit;
}

$attData = clinic_report_appointment_attendance($appointments, $boundFrom, $boundTo);

clinic_reports_page_shell_start('Appointment Attendance', 'Completion rate uses completed ÷ all outcomes in the filtered set.');
clinic_report_date_filter_form();
clinic_report_action_bar();
?>

<div class="row g-3 mb-4">
    <?php clinic_metric_card('In range', $attData['scheduled'], 'ti-calendar', 'primary', ''); ?>
    <?php clinic_metric_card('Completed', $attData['completed'], 'ti-check', 'success', ''); ?>
    <?php clinic_metric_card('Cancelled', $attData['cancelled'], 'ti-x', 'secondary', ''); ?>
    <?php clinic_metric_card('Completion rate', $attData['completion_rate'] !== null ? $attData['completion_rate'] . '%' : '—', 'ti-chart-line', 'info', ''); ?>
</div>

<div class="card report-data-card">
    <div class="card-header bg-white border-0 pt-3"><h5 class="mb-0">Scheduled rows</h5></div>
    <div class="card-body table-responsive">
        <table class="table table-hover clinic-table align-middle">
            <thead><tr><th>Date</th><th>Patient</th><th>Doctor</th><th>Status</th></tr></thead>
            <tbody>
                <?php foreach ($attData['recent'] as $a): ?>
                    <tr>
                        <td><?php echo clinic_h((string) ($a['Appointment_Date'] ?? '')); ?></td>
                        <td><?php echo clinic_h((string) ($a['Patient_Name'] ?? '')); ?></td>
                        <td><?php echo clinic_h((string) ($a['Doctor_Name'] ?? '')); ?></td>
                        <td><?php echo clinic_status_badge((string) ($a['Status'] ?? '')); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php if ($attData['recent'] === []): ?>
            <div class="alert alert-light border mb-0">No appointments in this range.</div>
        <?php endif; ?>
    </div>
</div>

<?php clinic_page_end(); ?>
