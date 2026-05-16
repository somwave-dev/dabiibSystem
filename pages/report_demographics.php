<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/report_bootstrap.php';

[$boundFrom, $boundTo] = clinic_reports_bounds();
$allPatients = clinic_sp_rows('sp_patients_list');

if ((string) ($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="demographics-' . date('Y-m-d') . '.csv"');
    $demo = clinic_report_demographics($allPatients, $boundFrom, $boundTo);
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Segment', 'Key', 'Count']);
    foreach ($demo['by_sex'] as $k => $c) {
        fputcsv($out, ['Sex', $k, $c]);
    }
    foreach ($demo['by_age'] as $k => $c) {
        fputcsv($out, ['Age group', $k, $c]);
    }
    foreach ($demo['by_type'] as $k => $c) {
        fputcsv($out, ['Patient type', $k, $c]);
    }
    fclose($out);
    exit;
}

$demoData = clinic_report_demographics($allPatients, $boundFrom, $boundTo);

clinic_reports_page_shell_start('Patient Demographics', 'Registration date filter applies when From/To dates are set.');
clinic_report_date_filter_form();
clinic_report_action_bar();
?>

<div class="row g-3 mb-4">
    <?php clinic_metric_card('Patients in view', $demoData['total'], 'ti-users', 'primary', 'After date filter on registration'); ?>
</div>

<div class="row g-3">
    <div class="col-md-4">
        <div class="card report-data-card h-100">
            <div class="card-header bg-white border-bottom-0 pt-3"><h6 class="mb-0">Sex</h6></div>
            <div class="card-body table-responsive pt-0">
                <table class="table table-sm clinic-table mb-0"><tbody>
                    <?php foreach ($demoData['by_sex'] as $k => $c): ?>
                        <tr><td><?php echo clinic_h($k); ?></td><td class="text-end fw-semibold"><?php echo (int) $c; ?></td></tr>
                    <?php endforeach; ?>
                </tbody></table>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card report-data-card h-100">
            <div class="card-header bg-white border-bottom-0 pt-3"><h6 class="mb-0">Age group</h6></div>
            <div class="card-body table-responsive pt-0">
                <table class="table table-sm clinic-table mb-0"><tbody>
                    <?php foreach ($demoData['by_age'] as $k => $c): ?>
                        <tr><td><?php echo clinic_h($k); ?></td><td class="text-end fw-semibold"><?php echo (int) $c; ?></td></tr>
                    <?php endforeach; ?>
                </tbody></table>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card report-data-card h-100">
            <div class="card-header bg-white border-bottom-0 pt-3"><h6 class="mb-0">Patient type</h6></div>
            <div class="card-body table-responsive pt-0">
                <table class="table table-sm clinic-table mb-0"><tbody>
                    <?php foreach ($demoData['by_type'] as $k => $c): ?>
                        <tr><td><?php echo clinic_h($k); ?></td><td class="text-end fw-semibold"><?php echo (int) $c; ?></td></tr>
                    <?php endforeach; ?>
                </tbody></table>
            </div>
        </div>
    </div>
</div>

<?php clinic_page_end(); ?>
