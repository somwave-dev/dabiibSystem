<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/report_bootstrap.php';

[$boundFrom, $boundTo] = clinic_reports_bounds();
$bucket = (string) ($_GET['bucket'] ?? 'month');
if (!in_array($bucket, ['day', 'week', 'month'], true)) {
    $bucket = 'month';
}

$visits = clinic_sp_rows('sp_visits_list');
$rows = clinic_report_doctor_workload_rows($visits, $boundFrom, $boundTo, $bucket);

if ((string) ($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="doctor-workload-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Period', 'Doctor_ID', 'Doctor', 'Visit count']);
    foreach ($rows as $r) {
        fputcsv($out, [$r['period'], $r['Doctor_ID'], $r['doctor'], $r['visit_count']]);
    }
    fclose($out);
    exit;
}

$ttl = array_sum(array_map(static fn ($r) => (int) ($r['visit_count'] ?? 0), $rows));

clinic_reports_page_shell_start('Doctor Workload', 'Visit volume per doctor aggregated by day, ISO week, or month.');
?>

<div class="report-actions-bar mb-4">
    <a class="btn btn-outline-secondary btn-sm" href="reports.php"><i class="ti ti-layout-grid-add me-1"></i>All reports</a>
    <div class="report-actions-push">
        <a class="btn btn-primary btn-sm" href="<?php echo clinic_h('report_doctor_workload.php?' . http_build_query(array_merge(array_filter($_GET, static fn ($v) => $v !== '' && $v !== null), ['export' => 'csv']))); ?>"><i class="ti ti-file-spreadsheet me-1"></i>Download CSV</a>
        <button class="btn btn-light border btn-sm" type="button" onclick="window.print()"><i class="ti ti-printer me-1"></i>Print</button>
    </div>
</div>

<div class="card report-filter-sheet mb-4">
    <div class="card-body">
        <form class="row g-3 align-items-end" method="get" action="report_doctor_workload.php">
            <div class="col-sm-6 col-md-3">
                <label class="form-label small fw-semibold text-muted">From</label>
                <input class="form-control" type="date" name="date_from" value="<?php echo clinic_h((string) ($_GET['date_from'] ?? '')); ?>">
            </div>
            <div class="col-sm-6 col-md-3">
                <label class="form-label small fw-semibold text-muted">To</label>
                <input class="form-control" type="date" name="date_to" value="<?php echo clinic_h((string) ($_GET['date_to'] ?? '')); ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold text-muted">Bucket</label>
                <select class="form-select" name="bucket">
                    <option value="day"<?php echo $bucket === 'day' ? ' selected' : ''; ?>>Day</option>
                    <option value="week"<?php echo $bucket === 'week' ? ' selected' : ''; ?>>Week (ISO)</option>
                    <option value="month"<?php echo $bucket === 'month' ? ' selected' : ''; ?>>Month</option>
                </select>
            </div>
            <div class="col-auto d-flex gap-2"><button class="btn btn-dark" type="submit">Apply</button><a class="btn btn-outline-secondary" href="report_doctor_workload.php">Reset</a></div>
        </form>
    </div>
</div>

<div class="row g-3 mb-4">
    <?php clinic_metric_card('Visit rows counted', $ttl, 'ti-stethoscope', 'success', ''); ?>
    <?php clinic_metric_card('Cells', count($rows), 'ti-chart-grid-dots', 'primary', ''); ?>
</div>

<div class="card report-data-card">
    <div class="card-header bg-white border-0 pt-3"><h5 class="mb-0">Workload matrix</h5></div>
    <div class="card-body table-responsive">
        <table class="table table-hover clinic-table align-middle">
            <thead><tr><th>Period</th><th>Doctor</th><th class="text-end">Visits</th></tr></thead>
            <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><code><?php echo clinic_h((string) ($r['period'] ?? '')); ?></code></td>
                        <td><?php echo clinic_h((string) ($r['doctor'] ?? '')); ?></td>
                        <td class="text-end fw-semibold"><?php echo (int) ($r['visit_count'] ?? 0); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php if ($rows === []): ?>
            <div class="alert alert-light border mb-0">No doctor-attributed visits in range.</div>
        <?php endif; ?>
    </div>
</div>

<?php clinic_page_end(); ?>
