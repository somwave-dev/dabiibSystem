<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/report_bootstrap.php';

[$boundFrom, $boundTo] = clinic_reports_bounds();
$prescriptions = clinic_sp_rows('sp_prescriptions_list');
$visits = clinic_doctor_scoped_list('sp_visits_list');
$visitDatesById = [];
foreach ($visits as $v) {
    $vid = (int) ($v['Visit_ID'] ?? 0);
    if ($vid > 0) {
        $visitDatesById[$vid] = (string) ($v['Visit_Date'] ?? '');
    }
}

$rows = clinic_report_most_prescribed($prescriptions, $boundFrom, $boundTo, $visitDatesById);

if ((string) ($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="most-prescribed-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Medicine ID', 'Medicine', 'Prescription lines']);
    foreach ($rows as $r) {
        fputcsv($out, [$r['medicine_id'], $r['medicine_name'], $r['prescription_count']]);
    }
    fclose($out);
    exit;
}

$lines = array_sum(array_map(static fn ($r) => (int) ($r['prescription_count'] ?? 0), $rows));

clinic_reports_page_shell_start('Most Prescribed', 'Frequency of prescription rows by medicine (from clinical prescribing, not POS).');
clinic_report_date_filter_form();
clinic_report_action_bar();
?>

<div class="row g-3 mb-4">
    <?php clinic_metric_card('Unique medicines', count($rows), 'ti-pill', 'primary', ''); ?>
    <?php clinic_metric_card('Prescription lines', $lines, 'ti-file-text', 'success', 'In visit date range'); ?>
</div>

<div class="card report-data-card">
    <div class="card-header bg-white border-0 pt-3"><h5 class="mb-0">Ranking</h5></div>
    <div class="card-body table-responsive">
        <table class="table table-hover clinic-table align-middle">
            <thead><tr><th>Medicine</th><th class="text-end">Lines</th></tr></thead>
            <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><?php echo clinic_h((string) ($r['medicine_name'] ?? '')); ?></td>
                        <td class="text-end fw-semibold"><?php echo (int) ($r['prescription_count'] ?? 0); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php if ($rows === []): ?>
            <div class="alert alert-light border mb-0">No prescriptions linked to visits in range.</div>
        <?php endif; ?>
    </div>
</div>

<?php clinic_page_end(); ?>
