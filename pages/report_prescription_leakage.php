<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/report_bootstrap.php';

[$boundFrom, $boundTo] = clinic_reports_bounds();
$windowDays = max(7, min(365, (int) ($_GET['window_days'] ?? 90)));

$prescriptions = clinic_sp_rows('sp_prescriptions_list');
$pharmacySales = clinic_sp_rows('sp_pharmacy_sales_list');
$visits = clinic_doctor_scoped_list('sp_visits_list');

$leak = clinic_report_unfulfilled_prescriptions($prescriptions, $pharmacySales, $visits, $boundFrom, $boundTo, $windowDays);

if ((string) ($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="prescription-leakage-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Rx ID', 'Patient', 'Medicine', 'Visit date', 'Clinic qty after visit', 'Note']);
    foreach ($leak as $row) {
        fputcsv($out, [$row['Prescription_ID'], $row['Patient_Name'], $row['Medicine_Name'], $row['Visit_Date'], $row['Sale_Qty_After'], $row['Gap']]);
    }
    fclose($out);
    exit;
}

clinic_reports_page_shell_start('Pharmacy Leakage', 'Prescriptions with no clinic POS sale within the window after the visit.');

$pharmCatCrumb = clinic_reports_parent_crumb_for_report('report_prescription_leakage.php');
?>

<div class="report-actions-bar mb-4">
    <div class="d-flex flex-wrap align-items-center gap-2">
        <a class="btn btn-outline-secondary btn-sm" href="reports.php"><i class="ti ti-layout-grid-add me-1"></i>Reports hub</a>
        <?php if ($pharmCatCrumb !== null && ($pharmCatCrumb['href'] ?? '') !== ''): ?>
            <a class="btn btn-outline-secondary btn-sm" href="<?php echo clinic_h((string) $pharmCatCrumb['href']); ?>"><i class="ti ti-folder me-1"></i><?php echo clinic_h((string) ($pharmCatCrumb['label'] ?? '')); ?></a>
        <?php endif; ?>
    </div>
    <div class="report-actions-push">
        <a class="btn btn-primary btn-sm" href="<?php echo clinic_h('report_prescription_leakage.php?' . http_build_query(array_merge(array_filter($_GET, static fn ($v) => $v !== '' && $v !== null), ['export' => 'csv']))); ?>"><i class="ti ti-file-spreadsheet me-1"></i>Download CSV</a>
        <button class="btn btn-light border btn-sm" type="button" onclick="window.print()"><i class="ti ti-printer me-1"></i>Print</button>
    </div>
</div>

<div class="card report-filter-sheet mb-4">
    <div class="card-body">
        <form class="row g-3 align-items-end" method="get" action="report_prescription_leakage.php">
            <div class="col-sm-6 col-md-3">
                <label class="form-label small fw-semibold text-muted">From</label>
                <input class="form-control" type="date" name="date_from" value="<?php echo clinic_h((string) ($_GET['date_from'] ?? '')); ?>">
            </div>
            <div class="col-sm-6 col-md-3">
                <label class="form-label small fw-semibold text-muted">To</label>
                <input class="form-control" type="date" name="date_to" value="<?php echo clinic_h((string) ($_GET['date_to'] ?? '')); ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold text-muted">Match window (days)</label>
                <input class="form-control" type="number" name="window_days" min="7" max="365" value="<?php echo (int) $windowDays; ?>">
            </div>
            <div class="col-auto d-flex gap-2"><button class="btn btn-dark" type="submit">Apply</button><a class="btn btn-outline-secondary" href="report_prescription_leakage.php">Reset</a></div>
        </form>
        <p class="small text-muted mt-3 mb-0">Heuristic: each prescription line expects ≥ 1 pharmacy sale at this clinic for same patient/drug after visit.</p>
    </div>
</div>

<div class="row g-3 mb-4">
    <?php clinic_metric_card('Suspected leakage rows', count($leak), 'ti-alert-circle', 'warning', 'Window ' . (int) $windowDays . ' days'); ?>
</div>

<div class="card report-data-card">
    <div class="card-header bg-white border-0 pt-3"><h5 class="mb-0">Unmatched prescriptions</h5></div>
    <div class="card-body table-responsive">
        <table class="table table-hover clinic-table align-middle">
            <thead><tr><th>Patient</th><th>Medicine</th><th>Visit date</th><th class="text-end">Qty at clinic POS</th><th class="small">Gap</th></tr></thead>
            <tbody>
                <?php foreach ($leak as $row): ?>
                    <tr>
                        <td><?php echo clinic_h($row['Patient_Name']); ?></td>
                        <td><?php echo clinic_h($row['Medicine_Name']); ?></td>
                        <td><?php echo clinic_h($row['Visit_Date']); ?></td>
                        <td class="text-end"><?php echo (int) ($row['Sale_Qty_After'] ?? 0); ?></td>
                        <td class="small text-muted"><?php echo clinic_h($row['Gap']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php if ($leak === []): ?>
            <div class="alert alert-success border mb-0">No leakage rows detected with current filters.</div>
        <?php endif; ?>
    </div>
</div>

<?php clinic_page_end(); ?>
