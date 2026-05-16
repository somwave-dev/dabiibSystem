<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/report_bootstrap.php';

[$boundFrom, $boundTo] = clinic_reports_bounds();
$visits = clinic_sp_rows('sp_visits_list');
$rows = clinic_report_visit_frequency($visits, $boundFrom, $boundTo, 150);

if ((string) ($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="visit-frequency-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Patient ID', 'Patient', 'Visits in range']);
    foreach ($rows as $r) {
        fputcsv($out, [$r['Patient_ID'], $r['Patient_Name'], $r['visit_count']]);
    }
    fclose($out);
    exit;
}

clinic_reports_page_shell_start('Visit Frequency', 'Patients ranked by visit count in the selected period (repeat utilisation).');
clinic_report_date_filter_form();
clinic_report_action_bar();
?>

<div class="row g-3 mb-4">
    <?php $topVisit = isset($rows[0]) ? (int) ($rows[0]['visit_count'] ?? 0) : 0; ?>
    <?php clinic_metric_card('Tracked patients', count($rows), 'ti-users', 'primary', ''); ?>
    <?php clinic_metric_card('Peak visits', (string) $topVisit, 'ti-repeat', 'success', 'Highest count in ranking'); ?>
</div>

<div class="card report-data-card">
    <div class="card-header bg-white border-0 pt-3"><h5 class="mb-0">Return rate leaders</h5></div>
    <div class="card-body table-responsive">
        <table class="table table-hover clinic-table align-middle">
            <thead><tr><th>Patient</th><th class="text-end">Visits</th></tr></thead>
            <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td>
                            <span class="fw-semibold"><?php echo clinic_h((string) ($r['Patient_Name'] ?? '')); ?></span>
                            <span class="d-block small text-muted">#<?php echo (int) ($r['Patient_ID'] ?? 0); ?></span>
                        </td>
                        <td class="text-end"><span class="badge text-bg-primary fs-14"><?php echo (int) ($r['visit_count'] ?? 0); ?></span></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php if ($rows === []): ?>
            <div class="alert alert-light border mb-0">No visits in date range.</div>
        <?php endif; ?>
    </div>
</div>

<?php clinic_page_end(); ?>
