<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/report_bootstrap.php';

[$boundFrom, $boundTo] = clinic_reports_bounds();
$visits = clinic_sp_rows('sp_visits_list');
$doctors = clinic_sp_rows('sp_doctors_list');

if ((string) ($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="doctor-commissions-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Doctor', 'Specialization', 'Fee', 'Visits', 'Consultation Revenue']);
    foreach (clinic_report_doctor_commission_rows($visits, $doctors, $boundFrom, $boundTo) as $row) {
        fputcsv($out, [$row['Doctor_Name'], $row['Specialization'], number_format($row['Consultation_Fee'], 2, '.', ''), $row['visit_count'], number_format($row['revenue_estimate'], 2, '.', '')]);
    }
    fclose($out);
    exit;
}

$rows = clinic_report_doctor_commission_rows($visits, $doctors, $boundFrom, $boundTo);
$withVisits = array_values(array_filter($rows, static fn ($r) => $r['visit_count'] > 0));
$totalRev = array_sum(array_map(static fn ($r) => $r['revenue_estimate'], $withVisits));
$totalVisitCount = array_sum(array_map(static fn ($r) => $r['visit_count'], $withVisits));

clinic_reports_page_shell_start('Doctor Commissions', 'Consultation revenue proxy: counted visits multiplied by each doctor consultation fee.');
clinic_report_date_filter_form();
clinic_report_action_bar();
?>

<div class="row g-3 mb-4">
    <?php clinic_metric_card('Consultation revenue', clinic_money($totalRev), 'ti-currency-dollar', 'primary', 'In selected date range'); ?>
    <?php clinic_metric_card('Doctor visits counted', $totalVisitCount, 'ti-stethoscope', 'success', 'Visits with assigned doctor'); ?>
</div>

<div class="card report-data-card">
    <div class="card-header bg-white border-0 pt-3"><h5 class="mb-0">Per doctor</h5></div>
    <div class="card-body table-responsive">
        <table class="table table-hover clinic-table align-middle">
            <thead><tr><th>Doctor</th><th>Specialization</th><th class="text-end">Fee</th><th class="text-end">Visits</th><th class="text-end">Revenue</th></tr></thead>
            <tbody>
                <?php foreach ($withVisits as $r): ?>
                    <tr>
                        <td><?php echo clinic_h($r['Doctor_Name']); ?></td>
                        <td><?php echo clinic_h($r['Specialization']); ?></td>
                        <td class="text-end"><?php echo clinic_money($r['Consultation_Fee']); ?></td>
                        <td class="text-end"><?php echo (int) $r['visit_count']; ?></td>
                        <td class="text-end fw-semibold"><?php echo clinic_money($r['revenue_estimate']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php if ($withVisits === []): ?>
            <div class="alert alert-light border mb-0">No doctor visits in this range.</div>
        <?php endif; ?>
    </div>
</div>

<?php clinic_page_end(); ?>
