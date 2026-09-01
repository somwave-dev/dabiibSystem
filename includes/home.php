<?php
/**
 * Dabiib HMS — Executive Operational Dashboard (CSS Grid layout).
 * Every value is computed live from the database (includes/dashboard/data.php).
 */
require_once __DIR__ . '/dashboard/data.php';
require_once __DIR__ . '/dashboard/ui.php';

$d = clinic_dashboard_data();
$k = $d['kpis'];

$isAdmin = $d['isAdmin'];

// ---------- role-based section visibility ----------
$sec = [
    'patients'     => $isAdmin || $d['isDoctor'] || $d['isReception'],
    'appointments' => $isAdmin || $d['isDoctor'] || $d['isReception'],
    'visits'       => $isAdmin || $d['isDoctor'] || $d['isReception'] || $d['isNurse'],
    'lab'          => $isAdmin || $d['isDoctor'] || $d['isNurse'] || $d['isLabTech'],
    'pharmacy'     => $isAdmin || $d['isPharmacist'],
    'nursing'      => $isAdmin || $d['isNurse'],
    'finance'      => $isAdmin || $d['isReception'],
    'doctors'      => $isAdmin,
    'reports'      => $isAdmin || $d['isDoctor'],
    'activity'     => $isAdmin,
];

$roleLabel = (string) ($_SESSION['role_name'] ?? '');
$siteName = trim((string) (clinic_dash_val("SELECT setting_value FROM system_settings WHERE setting_key = 'site_name' LIMIT 1") ?? ''));
if ($siteName === '') {
    $siteName = 'Dabiib HMS';
}
$title = $roleLabel !== '' ? $siteName . ' — ' . $roleLabel : $siteName . ' — Command Center';
?>
<style>
    /* ===== Dabiib dashboard grid (12-col, responsive) ===== */
    .dashboard-grid { display: grid; grid-template-columns: repeat(12, minmax(0, 1fr)); gap: 20px; align-items: stretch; margin-bottom: 20px; }
    .dash-span-3  { grid-column: span 3; }
    .dash-span-4  { grid-column: span 4; }
    .dash-span-5  { grid-column: span 5; }
    .dash-span-6  { grid-column: span 6; }
    .dash-span-7  { grid-column: span 7; }
    .dash-span-8  { grid-column: span 8; }
    .dash-span-12 { grid-column: span 12; }

    .dash-card { display: flex; flex-direction: column; height: 100%; }
    .dash-card > .card-body { flex: 1; min-height: 0; padding: 1rem 1.25rem; }
    .dash-card > .card-header { flex-shrink: 0; }

    .dash-chart { position: relative; width: 100%; }
    .dash-chart.sm { height: 240px; }
    .dash-chart.md { height: 280px; }
    .dash-chart.lg { height: 320px; }

    .dash-list-item { display: flex; align-items: center; justify-content: space-between; gap: .75rem; padding: .5rem 0; border-bottom: 1px solid rgba(0,0,0,.06); }
    .dash-list-item:last-child { border-bottom: 0; }
    .dash-list-title { font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .dash-list-sub  { font-size: .78rem; color: #6c757d; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

    .dash-stat-cell { border: 1px solid rgba(0,0,0,.07); border-radius: .65rem; padding: .6rem .5rem; text-align: center; background: #fff; }
    .dash-stat-cell .stat-v { font-size: 1.15rem; font-weight: 700; line-height: 1.15; }
    .dash-stat-cell .stat-l { font-size: .7rem; text-transform: uppercase; letter-spacing: .05em; color: #6c757d; }

    /* ===== dark mode overrides (theme-safe cards) ===== */
    [data-bs-theme="dark"] .clinic-card, [data-theme="dark"] .clinic-card { border-color: #303845; }
    [data-bs-theme="dark"] .dash-card > .card-header, [data-theme="dark"] .dash-card > .card-header { border-color: #303845; }
    [data-bs-theme="dark"] .dash-stat-cell, [data-theme="dark"] .dash-stat-cell { background: #181B22; border-color: #303845; }
    [data-bs-theme="dark"] .dash-stat-cell .stat-l, [data-theme="dark"] .dash-stat-cell .stat-l { color: #8E98A8; }
    [data-bs-theme="dark"] .dash-list-item, [data-theme="dark"] .dash-list-item { border-bottom-color: #303845; }
    [data-bs-theme="dark"] .dash-list-sub, [data-theme="dark"] .dash-list-sub { color: #8E98A8; }
    [data-bs-theme="dark"] .dash-alert-row, [data-theme="dark"] .dash-alert-row { background: #181B22; border: 1px solid #303845; }
    [data-bs-theme="dark"] .progress, [data-theme="dark"] .progress { background: #2d3340; }

    .dash-recent-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 20px; margin-bottom: 20px; }
    @media (max-width: 1199.98px) { .dash-recent-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    @media (max-width: 767.98px)  { .dash-recent-grid { grid-template-columns: 1fr; gap: 14px; } }

    .dash-alert-row { background: #fff; border: 1px solid rgba(0,0,0,.08); }

    @media (max-width: 1199.98px) {
        .dashboard-grid { grid-template-columns: repeat(6, minmax(0, 1fr)); }
        .dash-span-12, .dash-span-8, .dash-span-7, .dash-span-6 { grid-column: span 6; }
        .dash-span-5, .dash-span-4, .dash-span-3 { grid-column: span 3; }
    }
    @media (max-width: 767.98px) {
        .dashboard-grid { grid-template-columns: 1fr; gap: 14px; }
        .dash-span-3, .dash-span-4, .dash-span-5, .dash-span-6, .dash-span-7, .dash-span-8, .dash-span-12 { grid-column: span 1; }
    }
</style>
<div class="content pb-0">
    <div class="d-flex align-items-sm-center justify-content-between flex-wrap gap-2 mb-3">
        <div>
            <h4 class="fw-bold mb-0"><i class="ti ti-dashboard me-2 text-primary"></i><?php echo clinic_h($title); ?></h4>
            <p class="text-muted mb-0"><?php echo clinic_h(date('l, F j, Y')); ?> · Live operational overview</p>
        </div>
        <div class="d-flex align-items-center flex-wrap gap-2">
            <?php if ($sec['patients']): ?><a href="pages/patients.php" class="btn btn-primary d-inline-flex align-items-center"><i class="ti ti-user-plus me-1"></i>New Patient</a><?php endif; ?>
            <?php if ($sec['appointments']): ?><a href="pages/appointments.php" class="btn btn-outline-primary d-inline-flex align-items-center"><i class="ti ti-calendar-plus me-1"></i>New Appointment</a><?php endif; ?>
            <?php if ($sec['visits']): ?><a href="pages/visits.php" class="btn btn-outline-primary d-inline-flex align-items-center"><i class="ti ti-stethoscope me-1"></i>New Visit</a><?php endif; ?>
            <?php if ($sec['pharmacy']): ?><a href="pages/pharmacy_sales.php" class="btn btn-outline-primary d-inline-flex align-items-center"><i class="ti ti-shopping-cart me-1"></i>Pharmacy Sale</a><?php endif; ?>
            <?php if ($sec['finance']): ?><a href="pages/payments.php" class="btn btn-outline-primary d-inline-flex align-items-center"><i class="ti ti-cash me-1"></i>Payment Desk</a><?php endif; ?>
        </div>
    </div>

    <?php if ($isAdmin): ?>
        <?php echo clinic_flash() ?? ''; ?>
    <?php endif; ?>

    <!-- ============ KPI ROW ============ -->
    <div class="dashboard-grid">
        <?php if ($sec['patients']): clinic_dash_kpi('Patients', number_format($k['patients_total']), 'ti-users', 'primary', 'pages/patients.php', number_format($k['patients_today']) . ' new today'); endif; ?>
        <?php if ($sec['appointments']): clinic_dash_kpi('Appointments Today', number_format($k['appointments_today']), 'ti-calendar-check', 'info', 'pages/appointments.php', $k['appointments_pending'] . ' pending'); endif; ?>
        <?php if ($sec['visits']): clinic_dash_kpi('Visits Today', number_format($k['visits_today']), 'ti-stethoscope', 'success', 'pages/visits.php', 'across all doctors'); endif; ?>
        <?php if ($sec['lab']): clinic_dash_kpi('Pending Lab', number_format($k['lab_pending']), 'ti-microscope', 'warning', 'pages/lab_results.php', $k['lab_critical'] . ' critical'); endif; ?>
        <?php if ($sec['pharmacy']): clinic_dash_kpi('Pharmacy Today', clinic_money($k['pharmacy_today']), 'ti-medicine', 'secondary', 'pages/pharmacy_sales.php', $k['low_stock_count'] . ' low stock'); endif; ?>
        <?php if ($sec['finance']): clinic_dash_kpi('Revenue Today', clinic_money($k['revenue_today']), 'ti-cash', 'success', 'pages/payments.php', 'Week: ' . clinic_money($k['revenue_week'])); endif; ?>
        <?php if ($sec['doctors']): clinic_dash_kpi('Doctors', number_format($k['doctors_active']), 'ti-user-md', 'primary', 'pages/doctors.php', $k['doctors_total'] . ' total'); endif; ?>
        <?php if ($sec['doctors']): clinic_dash_kpi('Outstanding', clinic_money($k['patient_debt']), 'ti-report-money', 'danger', 'pages/accounts.php', 'patient balances'); endif; ?>
        <?php if ($d['isNurse']): clinic_dash_kpi('Nursing Today', number_format($d['nursingToday']), 'ti-nurse', 'info', 'pages/nursing_records.php', $d['nursingTotal'] . ' all-time records'); endif; ?>
    </div>
    <!-- ============ CHARTS ROW 1 ============ -->
    <div class="dashboard-grid">
        <?php if ($sec['finance']): ?>
            <?php clinic_dash_chart('chartRevenue', 'Revenue Trend — Last 7 Days', 'Payments', 7, 'lg'); ?>
            <?php clinic_dash_chart('chartRevenueDonut', 'Revenue by Payment Method', 'All time', 5, 'lg'); ?>
        <?php endif; ?>
        <?php if ($sec['patients']): ?>
            <?php clinic_dash_chart('chartPatients', 'New Patients — Last 7 Days', 'Registrations', 5, 'lg'); ?>
            <div class="dash-span-7">
                <div class="card clinic-card h-100 dash-card">
                    <div class="card-header d-flex align-items-center justify-content-between py-3">
                        <h5 class="mb-0 small fw-bold text-uppercase" style="letter-spacing:.04em"><i class="ti ti-summary me-2 text-primary"></i>Operations Summary</h5>
                        <span class="badge text-bg-light">Live</span>
                    </div>
                    <div class="card-body">
                        <div class="row g-2 text-center mb-3">
                            <?php if ($sec['visits']): ?>
                                <div class="col-6 col-md-3"><div class="dash-stat-cell"><div class="stat-v text-primary"><?php echo number_format($k['visits_today']); ?></div><div class="stat-l">Visits Today</div></div></div>
                            <?php endif; ?>
                            <?php if ($sec['appointments']): ?>
                                <div class="col-6 col-md-3"><div class="dash-stat-cell"><div class="stat-v text-info"><?php echo number_format($k['appointments_today']); ?></div><div class="stat-l">Appts Today</div></div></div>
                            <?php endif; ?>
                            <?php if ($sec['patients']): ?>
                                <div class="col-6 col-md-3"><div class="dash-stat-cell"><div class="stat-v text-success"><?php echo number_format($k['patients_today']); ?></div><div class="stat-l">New Patients</div></div></div>
                            <?php endif; ?>
                            <?php if ($sec['finance']): ?>
                                <div class="col-6 col-md-3"><div class="dash-stat-cell"><div class="stat-v text-warning"><?php echo clinic_money($k['revenue_today']); ?></div><div class="stat-l">Revenue Today</div></div></div>
                            <?php endif; ?>
                            <?php if ($sec['lab']): ?>
                                <div class="col-6 col-md-3"><div class="dash-stat-cell"><div class="stat-v text-danger"><?php echo number_format($k['lab_critical']); ?></div><div class="stat-l">Critical Lab</div></div></div>
                            <?php endif; ?>
                            <?php if ($sec['pharmacy']): ?>
                                <div class="col-6 col-md-3"><div class="dash-stat-cell"><div class="stat-v text-secondary"><?php echo clinic_money($k['pharmacy_today']); ?></div><div class="stat-l">Pharmacy Today</div></div></div>
                            <?php endif; ?>
                            <?php if ($sec['doctors']): ?>
                                <div class="col-6 col-md-3"><div class="dash-stat-cell"><div class="stat-v"><?php echo number_format($k['doctors_active']); ?></div><div class="stat-l">Active Doctors</div></div></div>
                            <?php endif; ?>
                            <?php if ($sec['doctors']): ?>
                                <div class="col-6 col-md-3"><div class="dash-stat-cell"><div class="stat-v text-danger"><?php echo clinic_money($k['patient_debt']); ?></div><div class="stat-l">Outstanding</div></div></div>
                            <?php endif; ?>
                        </div>
                        <div class="d-flex flex-wrap gap-2">
                            <span class="badge text-bg-primary"><?php echo number_format(count($d['recentVisits'])); ?> recent visits</span>
                            <span class="badge text-bg-info"><?php echo number_format(count($d['appointmentsToday'])); ?> today's appts</span>
                            <span class="badge text-bg-success"><?php echo number_format($k['lab_completed']); ?> lab completed</span>
                            <span class="badge text-bg-secondary"><?php echo number_format($k['prescriptions_today']); ?> rx today</span>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <?php if ($sec['patients']): ?>
        <!-- ============ PATIENT ANALYTICS ============ -->
        <div class="dashboard-grid">
            <?php clinic_dash_chart('chartGender', 'Patients by Gender', 'All patients', 4, 'sm'); ?>
            <?php clinic_dash_chart('chartAge', 'Patients by Age Group', 'Child vs Adult', 4, 'sm'); ?>
            <?php clinic_dash_chart('chartType', 'Patients by Type', 'Credit vs Walk-in', 4, 'sm'); ?>
        </div>
    <?php endif; ?>

    <?php if ($sec['appointments']): ?>
        <!-- ============ APPOINTMENTS ============ -->
        <div class="dashboard-grid">
            <div class="dash-span-7">
                <div class="card clinic-card h-100 dash-card">
                    <div class="card-header d-flex align-items-center justify-content-between py-3">
                        <h5 class="mb-0 small fw-bold text-uppercase" style="letter-spacing:.04em"><i class="ti ti-calendar me-2 text-primary"></i>Today's Appointments</h5>
                        <a href="pages/appointments.php" class="btn btn-sm btn-light border">Open board</a>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle clinic-table mb-0">
                                <thead><tr><th>Time</th><th>Patient</th><th>Doctor</th><th>Status</th><th></th></tr></thead>
                                <tbody>
                                    <?php foreach ($d['appointmentsToday'] as $row): ?>
                                        <tr>
                                            <td class="text-nowrap"><?php echo clinic_h(date('H:i', strtotime((string) ($row['Appointment_Date'] ?? 'now')))); ?></td>
                                            <td class="fw-semibold"><?php echo clinic_h($row['Patient_Name'] ?? '-'); ?></td>
                                            <td><?php echo clinic_h($row['Doctor_Name'] ?? '-'); ?></td>
                                            <td><?php echo clinic_status_badge((string) ($row['Status'] ?? 'Pending')); ?></td>
                                            <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="pages/visits.php?patient_id=<?php echo (int) ($row['Patient_ID'] ?? 0); ?>">Visit</a></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if ($d['appointmentsToday'] === []): ?><tr><td colspan="5" class="text-center text-muted py-3">No appointments today.</td></tr><?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <?php clinic_dash_chart('chartApptStatus', 'Appointment Status — Today', 'Breakdown', 5, 'md'); ?>
        </div>
    <?php endif; ?>
    <?php if ($sec['visits']): ?>
        <!-- ============ VISITS ============ -->
        <div class="dashboard-grid">
            <div class="dash-span-6">
                <div class="card clinic-card h-100 dash-card">
                    <div class="card-header d-flex align-items-center justify-content-between py-3">
                        <h5 class="mb-0 small fw-bold text-uppercase" style="letter-spacing:.04em"><i class="ti ti-stethoscope me-2 text-primary"></i>Recent Visits</h5>
                        <a href="pages/visits.php" class="btn btn-sm btn-light border">All visits</a>
                    </div>
                    <div class="card-body">
                        <?php foreach ($d['recentVisits'] as $row): ?>
                            <div class="dash-list-item">
                                <div class="min-w-0">
                                    <div class="dash-list-title"><?php echo clinic_h($row['Patient_Name'] ?? '-'); ?></div>
                                    <div class="dash-list-sub"><?php echo clinic_h($row['Doctor_Name'] ?? 'No doctor'); ?> · <?php echo clinic_h(date('M j, H:i', strtotime((string) ($row['Visit_Date'] ?? 'now')))); ?></div>
                                </div>
                                <a class="btn btn-sm btn-outline-secondary" href="pages/visits.php?visit_id=<?php echo (int) ($row['Visit_ID'] ?? 0); ?>"><i class="ti ti-arrow-right"></i></a>
                            </div>
                        <?php endforeach; ?>
                        <?php if ($d['recentVisits'] === []): ?><p class="text-muted mb-0 py-2">No visits recorded yet.</p><?php endif; ?>
                    </div>
                </div>
            </div>
            <?php clinic_dash_chart('chartDoctors', 'Appointments by Doctor — Today', 'Top doctors', 6, 'md'); ?>
        </div>
        <div class="dashboard-grid">
            <?php clinic_dash_chart('chartVisits', 'Visits Trend — Last 7 Days', 'Count', 6, 'md'); ?>
            <div class="dash-span-6">
                <div class="card clinic-card h-100 dash-card">
                    <div class="card-header d-flex align-items-center justify-content-between py-3">
                        <h5 class="mb-0 small fw-bold text-uppercase" style="letter-spacing:.04em"><i class="ti ti-timeline me-2 text-primary"></i>Visits Today</h5>
                        <a href="pages/visits.php" class="btn btn-sm btn-light border">All visits</a>
                    </div>
                    <div class="card-body d-flex flex-column justify-content-center">
                        <div class="text-center py-2">
                            <div class="display-5 fw-bold text-primary"><?php echo number_format($k['visits_today']); ?></div>
                            <div class="text-muted">visits recorded today</div>
                        </div>
                        <div class="d-flex flex-wrap gap-2 justify-content-center">
                            <span class="badge text-bg-primary"><?php echo number_format(count($d['recentVisits'])); ?> recent</span>
                            <span class="badge text-bg-info"><?php echo number_format($k['appointments_pending']); ?> pending appts</span>
                            <span class="badge text-bg-success"><?php echo number_format($k['lab_completed']); ?> lab completed</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
    <?php if ($sec['lab']): ?>
        <!-- ============ LABORATORY ============ -->
        <div class="dashboard-grid">
            <?php clinic_dash_chart('chartLabDonut', 'Lab Status', 'Pending vs Completed', 4, 'sm'); ?>
            <div class="dash-span-4">
                <div class="card clinic-card h-100 dash-card">
                    <div class="card-header d-flex align-items-center justify-content-between py-3">
                        <h5 class="mb-0 small fw-bold text-uppercase" style="letter-spacing:.04em"><i class="ti ti-microscope me-2 text-primary"></i>Top Lab Tests</h5>
                        <a href="pages/lab_tests.php" class="btn btn-sm btn-light border">Manage tests</a>
                    </div>
                    <div class="card-body d-flex flex-column justify-content-center">
                        <?php $maxTest = 0; foreach ($d['topLabTests'] as $t) { $maxTest = max($maxTest, (int) $t['c']); } ?>
                        <?php foreach ($d['topLabTests'] as $t): ?>
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <span class="small flex-shrink-0" style="width:8.5rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?php echo clinic_h($t['Test_Name'] ?? '-'); ?></span>
                                <div class="progress flex-grow-1" style="height:8px"><div class="progress-bar" style="width:<?php echo $maxTest > 0 ? (int) round(((int) $t['c'] / $maxTest) * 100) : 0; ?>%"></div></div>
                                <span class="badge text-bg-light"><?php echo (int) $t['c']; ?></span>
                            </div>
                        <?php endforeach; ?>
                        <?php if ($d['topLabTests'] === []): ?><p class="text-muted mb-0">No lab tests recorded.</p><?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="dash-span-4">
                <div class="card clinic-card h-100 dash-card">
                    <div class="card-header d-flex align-items-center justify-content-between py-3">
                        <h5 class="mb-0 small fw-bold text-uppercase" style="letter-spacing:.04em"><i class="ti ti-flask-2 me-2 text-primary"></i>Recent Lab Results</h5>
                        <a href="pages/lab_results.php" class="btn btn-sm btn-light border">Lab queue</a>
                    </div>
                    <div class="card-body">
                        <?php foreach ($d['recentLab'] as $row): ?>
                            <div class="dash-list-item">
                                <div class="min-w-0">
                                    <div class="dash-list-title"><?php echo clinic_h($row['Patient_Name'] ?? '-'); ?> <span class="text-muted small">· <?php echo clinic_h($row['Test_Name'] ?? ''); ?></span></div>
                                    <?php if (!empty($row['Result_Details'])): ?><div class="dash-list-sub"><?php echo clinic_h($row['Result_Details']); ?></div><?php endif; ?>
                                </div>
                                <?php echo clinic_status_badge((string) ($row['Status'] ?? 'Pending')); ?>
                            </div>
                        <?php endforeach; ?>
                        <?php if ($d['recentLab'] === []): ?><p class="text-muted mb-0">No lab results yet.</p><?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
    <?php if ($sec['pharmacy']): ?>
        <!-- ============ PHARMACY ============ -->
        <div class="dashboard-grid">
            <?php clinic_dash_chart('chartPharmacy', 'Pharmacy Sales — Last 7 Days', 'Revenue', 6, 'md'); ?>
            <div class="dash-span-6">
                <div class="card clinic-card h-100 dash-card">
                    <div class="card-header d-flex align-items-center justify-content-between py-3">
                        <h5 class="mb-0 small fw-bold text-uppercase" style="letter-spacing:.04em"><i class="ti ti-pill me-2 text-primary"></i>Top Selling Medicines</h5>
                        <a href="pages/medicines.php" class="btn btn-sm btn-light border">Medicines</a>
                    </div>
                    <div class="card-body d-flex flex-column justify-content-center">
                        <?php $maxMed = 0; foreach ($d['topMedicines'] as $m) { $maxMed = max($maxMed, (int) $m['qty']); } ?>
                        <?php foreach ($d['topMedicines'] as $m): ?>
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <span class="small flex-shrink-0" style="width:11rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?php echo clinic_h($m['Medicine_Name'] ?? '-'); ?></span>
                                <div class="progress flex-grow-1" style="height:8px"><div class="progress-bar bg-success" style="width:<?php echo $maxMed > 0 ? (int) round(((int) $m['qty'] / $maxMed) * 100) : 0; ?>%"></div></div>
                                <span class="badge text-bg-light"><?php echo (int) $m['qty']; ?> u</span>
                            </div>
                        <?php endforeach; ?>
                        <?php if ($d['topMedicines'] === []): ?><p class="text-muted mb-0">No sales yet.</p><?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="dashboard-grid">
            <div class="dash-span-6">
                <div class="card clinic-card h-100 dash-card">
                    <div class="card-header d-flex align-items-center justify-content-between py-3">
                        <h5 class="mb-0 small fw-bold text-uppercase" style="letter-spacing:.04em"><i class="ti ti-packages me-2 text-warning"></i>Low Stock Medicines</h5>
                        <a href="pages/medicines.php" class="btn btn-sm btn-light border">Medicines</a>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle clinic-table mb-0">
                                <thead><tr><th>Medicine</th><th class="text-end">Stock</th><th class="text-end">Price</th><th>Expiry</th></tr></thead>
                                <tbody>
                                    <?php foreach ($d['lowStock'] as $row): ?>
                                        <tr>
                                            <td class="fw-semibold"><?php echo clinic_h($row['Medicine_Name'] ?? '-'); ?></td>
                                            <td class="text-end"><span class="badge text-bg-warning"><?php echo (int) ($row['Stock_Quantity'] ?? 0); ?></span></td>
                                            <td class="text-end"><?php echo clinic_money($row['Price'] ?? 0); ?></td>
                                            <td><?php echo clinic_h((string) ($row['Expiry_Date'] ?? '-')); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if ($d['lowStock'] === []): ?><tr><td colspan="4" class="text-center text-muted py-3">Stock levels look good.</td></tr><?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="dash-span-6">
                <div class="card clinic-card h-100 dash-card">
                    <div class="card-header d-flex align-items-center justify-content-between py-3">
                        <h5 class="mb-0 small fw-bold text-uppercase" style="letter-spacing:.04em"><i class="ti ti-hourglass-low me-2 text-danger"></i>Expired Medicines</h5>
                        <a href="pages/medicines.php" class="btn btn-sm btn-light border">Review</a>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle clinic-table mb-0">
                                <thead><tr><th>Medicine</th><th class="text-end">Stock</th><th>Expiry</th></tr></thead>
                                <tbody>
                                    <?php foreach ($d['expired'] as $row): ?>
                                        <tr>
                                            <td class="fw-semibold"><?php echo clinic_h($row['Medicine_Name'] ?? '-'); ?></td>
                                            <td class="text-end"><span class="badge text-bg-danger"><?php echo (int) ($row['Stock_Quantity'] ?? 0); ?></span></td>
                                            <td><?php echo clinic_h((string) ($row['Expiry_Date'] ?? '-')); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if ($d['expired'] === []): ?><tr><td colspan="3" class="text-center text-muted py-3">No expired medicines.</td></tr><?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
    <?php if ($sec['nursing']): ?>
        <!-- ============ NURSING + DOCTORS ============ -->
        <div class="dashboard-grid">
            <div class="dash-span-6">
                <div class="card clinic-card h-100 dash-card">
                    <div class="card-header d-flex align-items-center justify-content-between py-3">
                        <h5 class="mb-0 small fw-bold text-uppercase" style="letter-spacing:.04em"><i class="ti ti-nurse me-2 text-primary"></i>Nursing Activity</h5>
                        <a href="pages/nursing_records.php" class="btn btn-sm btn-light border">Records</a>
                    </div>
                    <div class="card-body">
                        <div class="row g-2 text-center mb-3">
                            <div class="col-6"><div class="dash-stat-cell"><div class="stat-v text-primary"><?php echo number_format($d['nursingToday']); ?></div><div class="stat-l">Today</div></div></div>
                            <div class="col-6"><div class="dash-stat-cell"><div class="stat-v"><?php echo number_format($d['nursingTotal']); ?></div><div class="stat-l">All-time</div></div></div>
                        </div>
                        <?php $maxN = 0; foreach ($d['nursingByService'] as $n) { $maxN = max($maxN, (int) $n['c']); } ?>
                        <?php foreach ($d['nursingByService'] as $n): ?>
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <span class="small flex-shrink-0" style="width:10rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?php echo clinic_h($n['Service_Name'] ?? '-'); ?></span>
                                <div class="progress flex-grow-1" style="height:8px"><div class="progress-bar bg-info" style="width:<?php echo $maxN > 0 ? (int) round(((int) $n['c'] / $maxN) * 100) : 0; ?>%"></div></div>
                                <span class="badge text-bg-light"><?php echo (int) $n['c']; ?></span>
                            </div>
                        <?php endforeach; ?>
                        <?php if ($d['nursingByService'] === []): ?><p class="text-muted mb-0">No nursing records yet.</p><?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="dash-span-6">
                <div class="card clinic-card h-100 dash-card">
                    <div class="card-header d-flex align-items-center justify-content-between py-3">
                        <h5 class="mb-0 small fw-bold text-uppercase" style="letter-spacing:.04em"><i class="ti ti-user-md me-2 text-primary"></i>Doctor Workload</h5>
                        <a href="pages/doctors.php" class="btn btn-sm btn-light border">Doctors</a>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm align-middle clinic-table mb-0">
                                <thead><tr><th>Doctor</th><th>Specialization</th><th class="text-end">Visits</th><th class="text-end">Appts</th></tr></thead>
                                <tbody>
                                    <?php foreach ($d['doctorWorkload'] as $row): ?>
                                        <tr>
                                            <td class="fw-semibold"><?php echo clinic_h($row['Doctor_Name'] ?? '-'); ?></td>
                                            <td class="small text-muted"><?php echo clinic_h($row['Specialization'] ?? '-'); ?></td>
                                            <td class="text-end"><?php echo (int) $row['visits']; ?></td>
                                            <td class="text-end"><?php echo (int) $row['appointments']; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if ($d['doctorWorkload'] === []): ?><tr><td colspan="4" class="text-center text-muted py-2">No doctors yet.</td></tr><?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
    <?php if ($sec['finance']): ?>
        <!-- ============ FINANCE ============ -->
        <div class="dashboard-grid">
            <div class="dash-span-6">
                <div class="card clinic-card h-100 dash-card">
                    <div class="card-header d-flex align-items-center justify-content-between py-3">
                        <h5 class="mb-0 small fw-bold text-uppercase" style="letter-spacing:.04em"><i class="ti ti-wallet me-2 text-primary"></i>Recent Payments</h5>
                        <a href="pages/payments.php" class="btn btn-sm btn-light border">Payment desk</a>
                    </div>
                    <div class="card-body">
                        <?php foreach ($d['recentPayments'] as $row): ?>
                            <div class="dash-list-item">
                                <div class="min-w-0">
                                    <div class="dash-list-title"><?php echo clinic_h($row['Patient_Name'] ?? '-'); ?></div>
                                    <div class="dash-list-sub"><?php echo clinic_h((string) ($row['Payment_Method'] ?? '-')); ?><?php if (!empty($row['Account_Name'])): ?> · <?php echo clinic_h($row['Account_Name']); ?><?php endif; ?> · <?php echo clinic_h(date('M j, H:i', strtotime((string) ($row['Payment_Date'] ?? 'now')))); ?></div>
                                </div>
                                <span class="badge text-bg-success"><?php echo clinic_money($row['Amount'] ?? 0); ?></span>
                            </div>
                        <?php endforeach; ?>
                        <?php if ($d['recentPayments'] === []): ?><p class="text-muted mb-0 py-2">No payments recorded.</p><?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="dash-span-6">
                <div class="card clinic-card h-100 dash-card">
                    <div class="card-header d-flex align-items-center justify-content-between py-3">
                        <h5 class="mb-0 small fw-bold text-uppercase" style="letter-spacing:.04em"><i class="ti ti-building-bank me-2 text-primary"></i>Account Balances</h5>
                        <a href="pages/accounts.php" class="btn btn-sm btn-light border">Accounts</a>
                    </div>
                    <div class="card-body">
                        <?php foreach ($d['accounts'] as $row): ?>
                            <div class="dash-list-item">
                                <span class="fw-semibold"><?php echo clinic_h($row['Account_Name'] ?? '-'); ?></span>
                                <span class="badge text-bg-primary"><?php echo clinic_money($row['Current_Balance'] ?? 0); ?></span>
                            </div>
                        <?php endforeach; ?>
                        <?php if ($d['accounts'] === []): ?><p class="text-muted mb-0 py-2">No accounts.</p><?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
    <!-- ============ RECENT ACTIVITY CARDS ============ -->
    <div class="dash-recent-grid">
        <?php if ($sec['patients']): ?>
            <div class="card clinic-card h-100 dash-card">
                <div class="card-header d-flex align-items-center justify-content-between py-2"><h6 class="mb-0 small fw-bold text-uppercase"><i class="ti ti-user me-2 text-primary"></i>Recent Patients</h6><a href="pages/patients.php" class="small">View all</a></div>
                <div class="card-body">
                    <?php foreach (array_slice($d['recentPatients'], 0, 5) as $row): ?>
                        <div class="dash-list-item">
                            <div class="min-w-0"><div class="dash-list-title" style="font-size:.85rem"><?php echo clinic_h($row['Full_Name'] ?? '-'); ?></div><div class="dash-list-sub"><?php echo clinic_h((string) ($row['Patient_Type'] ?? '')); ?></div></div>
                            <span class="small text-muted text-nowrap"><?php echo clinic_h(date('H:i', strtotime((string) ($row['Created_At'] ?? 'now')))); ?></span>
                        </div>
                    <?php endforeach; ?>
                    <?php if ($d['recentPatients'] === []): ?><p class="text-muted small mb-0">No patients.</p><?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
        <?php if ($sec['appointments']): ?>
            <div class="card clinic-card h-100 dash-card">
                <div class="card-header d-flex align-items-center justify-content-between py-2"><h6 class="mb-0 small fw-bold text-uppercase"><i class="ti ti-calendar me-2 text-primary"></i>Recent Appointments</h6><a href="pages/appointments.php" class="small">Board</a></div>
                <div class="card-body">
                    <?php foreach (array_slice($d['appointmentsToday'], 0, 5) as $row): ?>
                        <div class="dash-list-item">
                            <div class="min-w-0"><div class="dash-list-title" style="font-size:.85rem"><?php echo clinic_h($row['Patient_Name'] ?? '-'); ?></div><div class="dash-list-sub"><?php echo clinic_h($row['Doctor_Name'] ?? '-'); ?></div></div>
                            <span class="small text-muted text-nowrap"><?php echo clinic_h(date('H:i', strtotime((string) ($row['Appointment_Date'] ?? 'now')))); ?></span>
                        </div>
                    <?php endforeach; ?>
                    <?php if ($d['appointmentsToday'] === []): ?><p class="text-muted small mb-0">No appointments.</p><?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
        <?php if ($sec['finance']): ?>
            <div class="card clinic-card h-100 dash-card">
                <div class="card-header d-flex align-items-center justify-content-between py-2"><h6 class="mb-0 small fw-bold text-uppercase"><i class="ti ti-cash me-2 text-primary"></i>Recent Payments</h6><a href="pages/payments.php" class="small">Desk</a></div>
                <div class="card-body">
                    <?php foreach (array_slice($d['recentPayments'], 0, 5) as $row): ?>
                        <div class="dash-list-item">
                            <div class="min-w-0"><div class="dash-list-title" style="font-size:.85rem"><?php echo clinic_h($row['Patient_Name'] ?? '-'); ?></div><div class="dash-list-sub"><?php echo clinic_h((string) ($row['Payment_Method'] ?? '-')); ?></div></div>
                            <span class="badge text-bg-success">+<?php echo clinic_money($row['Amount'] ?? 0); ?></span>
                        </div>
                    <?php endforeach; ?>
                    <?php if ($d['recentPayments'] === []): ?><p class="text-muted small mb-0">No payments.</p><?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
        <?php if ($sec['pharmacy']): ?>
            <div class="card clinic-card h-100 dash-card">
                <div class="card-header d-flex align-items-center justify-content-between py-2"><h6 class="mb-0 small fw-bold text-uppercase"><i class="ti ti-pill me-2 text-primary"></i>Recent Prescriptions</h6><a href="pages/prescriptions.php" class="small">Rx</a></div>
                <div class="card-body">
                    <?php foreach (array_slice($d['recentPrescriptions'], 0, 5) as $row): ?>
                        <div class="dash-list-item">
                            <div class="min-w-0"><div class="dash-list-title" style="font-size:.85rem"><?php echo clinic_h($row['Patient_Name'] ?? '-'); ?></div><div class="dash-list-sub"><?php echo clinic_h($row['Medicine_Name'] ?? ''); ?> · <?php echo clinic_h($row['Doctor_Name'] ?? '-'); ?></div></div>
                        </div>
                    <?php endforeach; ?>
                    <?php if ($d['recentPrescriptions'] === []): ?><p class="text-muted small mb-0">No prescriptions.</p><?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <div class="dash-recent-grid">
        <?php if ($sec['lab']): ?>
            <div class="card clinic-card h-100 dash-card">
                <div class="card-header d-flex align-items-center justify-content-between py-2"><h6 class="mb-0 small fw-bold text-uppercase"><i class="ti ti-flask-2 me-2 text-primary"></i>Recent Lab Results</h6><a href="pages/lab_results.php" class="small">Lab</a></div>
                <div class="card-body">
                    <?php foreach (array_slice($d['recentLab'], 0, 5) as $row): ?>
                        <div class="dash-list-item">
                            <div class="min-w-0"><div class="dash-list-title" style="font-size:.85rem"><?php echo clinic_h($row['Patient_Name'] ?? '-'); ?></div><div class="dash-list-sub"><?php echo clinic_h($row['Test_Name'] ?? ''); ?></div></div>
                            <?php echo clinic_status_badge((string) ($row['Status'] ?? 'Pending')); ?>
                        </div>
                    <?php endforeach; ?>
                    <?php if ($d['recentLab'] === []): ?><p class="text-muted small mb-0">No results.</p><?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
        <?php if ($sec['visits']): ?>
            <div class="card clinic-card h-100 dash-card">
                <div class="card-header d-flex align-items-center justify-content-between py-2"><h6 class="mb-0 small fw-bold text-uppercase"><i class="ti ti-stethoscope me-2 text-primary"></i>Recent Visits</h6><a href="pages/visits.php" class="small">All</a></div>
                <div class="card-body">
                    <?php foreach (array_slice($d['recentVisits'], 0, 5) as $row): ?>
                        <div class="dash-list-item">
                            <div class="min-w-0"><div class="dash-list-title" style="font-size:.85rem"><?php echo clinic_h($row['Patient_Name'] ?? '-'); ?></div><div class="dash-list-sub"><?php echo clinic_h($row['Doctor_Name'] ?? 'No doctor'); ?></div></div>
                            <span class="small text-muted text-nowrap"><?php echo clinic_h(date('H:i', strtotime((string) ($row['Visit_Date'] ?? 'now')))); ?></span>
                        </div>
                    <?php endforeach; ?>
                    <?php if ($d['recentVisits'] === []): ?><p class="text-muted small mb-0">No visits.</p><?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
        <?php if ($sec['pharmacy']): ?>
            <div class="card clinic-card h-100 dash-card">
                <div class="card-header d-flex align-items-center justify-content-between py-2"><h6 class="mb-0 small fw-bold text-uppercase"><i class="ti ti-shopping-cart me-2 text-primary"></i>Recent Pharmacy Sales</h6><a href="pages/pharmacy_sales.php" class="small">POS</a></div>
                <div class="card-body">
                    <?php foreach (array_slice($d['recentPharmacySales'], 0, 5) as $row): ?>
                        <div class="dash-list-item">
                            <div class="min-w-0"><div class="dash-list-title" style="font-size:.85rem"><?php echo clinic_h($row['Medicine_Name'] ?? '-'); ?></div><div class="dash-list-sub"><?php echo clinic_h($row['Patient_Name'] ?? '-'); ?></div></div>
                            <span class="badge text-bg-success"><?php echo clinic_money($row['Total_Price'] ?? 0); ?></span>
                        </div>
                    <?php endforeach; ?>
                    <?php if ($d['recentPharmacySales'] === []): ?><p class="text-muted small mb-0">No sales.</p><?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
        <?php if ($sec['nursing']): ?>
            <div class="card clinic-card h-100 dash-card">
                <div class="card-header d-flex align-items-center justify-content-between py-2"><h6 class="mb-0 small fw-bold text-uppercase"><i class="ti ti-nurse me-2 text-primary"></i>Recent Nursing</h6><a href="pages/nursing_records.php" class="small">Records</a></div>
                <div class="card-body">
                    <?php foreach (array_slice($d['recentNursing'], 0, 5) as $row): ?>
                        <div class="dash-list-item">
                            <div class="min-w-0"><div class="dash-list-title" style="font-size:.85rem"><?php echo clinic_h($row['Patient_Name'] ?? '-'); ?></div><div class="dash-list-sub"><?php echo clinic_h($row['Service_Name'] ?? ''); ?></div></div>
                            <span class="small text-muted text-nowrap"><?php echo clinic_h(date('H:i', strtotime((string) ($row['Record_Date'] ?? 'now')))); ?></span>
                        </div>
                    <?php endforeach; ?>
                    <?php if ($d['recentNursing'] === []): ?><p class="text-muted small mb-0">No records.</p><?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <?php if ($isAdmin): ?>
        <!-- ============ ALERTS + REPORTS SNAPSHOT ============ -->
        <div class="dashboard-grid">
            <div class="dash-span-5">
                <div class="card clinic-card h-100 dash-card">
                    <div class="card-header d-flex align-items-center justify-content-between py-3">
                        <h5 class="mb-0 small fw-bold text-uppercase" style="letter-spacing:.04em"><i class="ti ti-alert-triangle me-2 text-primary"></i>System Alerts</h5>
                        <a href="pages/audit_logs.php" class="btn btn-sm btn-light border">Audit log</a>
                    </div>
                    <div class="card-body">
                        <?php if ($k['lab_critical'] > 0) clinic_dash_alert('Critical lab results', number_format($k['lab_critical']), 'ti-microscope', 'danger', 'pages/lab_results.php'); ?>
                        <?php if ($k['low_stock_count'] > 0) clinic_dash_alert('Medicines low on stock', number_format($k['low_stock_count']), 'ti-packages', 'warning', 'pages/medicines.php'); ?>
                        <?php if ($k['expired_count'] > 0) clinic_dash_alert('Expired medicines', number_format($k['expired_count']), 'ti-hourglass-low', 'danger', 'pages/medicines.php'); ?>
                        <?php if ($k['lab_pending'] > 0) clinic_dash_alert('Pending lab tests', number_format($k['lab_pending']), 'ti-flask-2', 'warning', 'pages/lab_results.php'); ?>
                        <?php if ($k['patient_debt'] > 0) clinic_dash_alert('Outstanding patient debt', clinic_money($k['patient_debt']), 'ti-report-money', 'danger', 'pages/accounts.php'); ?>
                        <?php if ($k['appointments_pending'] > 0) clinic_dash_alert('Pending appointments', number_format($k['appointments_pending']), 'ti-calendar', 'info', 'pages/appointments.php'); ?>
                        <?php if ($k['lab_critical'] === 0 && $k['low_stock_count'] === 0 && $k['expired_count'] === 0): ?>
                            <div class="text-center text-muted py-3"><i class="ti ti-circle-check me-1 text-success"></i>No active alerts</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="dash-span-7">
                <div class="card clinic-card h-100 dash-card">
                    <div class="card-header d-flex align-items-center justify-content-between py-3">
                        <h5 class="mb-0 small fw-bold text-uppercase" style="letter-spacing:.04em"><i class="ti ti-chart-bar me-2 text-primary"></i>Reports Snapshot</h5>
                        <a href="pages/reports.php" class="btn btn-sm btn-light border">All reports</a>
                    </div>
                    <div class="card-body">
                        <div class="row g-2 mb-2">
                            <div class="col-6 col-md-3"><div class="dash-stat-cell"><div class="stat-v"><?php echo clinic_money($d['snapshot']['revenue_today']); ?></div><div class="stat-l">Revenue Today</div></div></div>
                            <div class="col-6 col-md-3"><div class="dash-stat-cell"><div class="stat-v"><?php echo clinic_money($d['snapshot']['revenue_month']); ?></div><div class="stat-l">Revenue Month</div></div></div>
                            <div class="col-6 col-md-3"><div class="dash-stat-cell"><div class="stat-v"><?php echo number_format($d['snapshot']['patients_today']); ?></div><div class="stat-l">Patients Today</div></div></div>
                            <div class="col-6 col-md-3"><div class="dash-stat-cell"><div class="stat-v"><?php echo number_format($d['snapshot']['patients_total']); ?></div><div class="stat-l">Patients Total</div></div></div>
                        </div>
                        <div class="row g-2 mb-2">
                            <div class="col-6 col-md-3"><div class="dash-stat-cell"><div class="stat-v"><?php echo number_format($d['snapshot']['lab_completed']); ?></div><div class="stat-l">Lab Completed</div></div></div>
                            <div class="col-6 col-md-3"><div class="dash-stat-cell"><div class="stat-v"><?php echo number_format($d['snapshot']['lab_pending']); ?></div><div class="stat-l">Lab Pending</div></div></div>
                            <div class="col-6 col-md-3"><div class="dash-stat-cell"><div class="stat-v"><?php echo number_format($d['snapshot']['prescriptions']); ?></div><div class="stat-l">Rx Today</div></div></div>
                            <div class="col-6 col-md-3"><div class="dash-stat-cell"><div class="stat-v"><?php echo clinic_money($d['snapshot']['outstanding']); ?></div><div class="stat-l">Outstanding</div></div></div>
                        </div>
                        <div class="d-flex flex-wrap gap-2">
                            <a class="btn btn-sm btn-outline-primary" href="pages/reports_clinical.php">Clinical</a>
                            <a class="btn btn-sm btn-outline-primary" href="pages/reports_finance.php">Finance</a>
                            <a class="btn btn-sm btn-outline-primary" href="pages/reports_pharmacy.php">Pharmacy</a>
                            <a class="btn btn-sm btn-outline-primary" href="pages/reports_operations.php">Operations</a>
                            <a class="btn btn-sm btn-outline-primary" href="pages/reports_administration.php">Administration</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
    <?php if ($sec['activity']): ?>
        <!-- ============ SYSTEM ACTIVITY + CRITICAL LAB ============ -->
        <div class="dashboard-grid">
            <div class="dash-span-8">
                <div class="card clinic-card h-100 dash-card">
                    <div class="card-header d-flex align-items-center justify-content-between py-3">
                        <h5 class="mb-0 small fw-bold text-uppercase" style="letter-spacing:.04em"><i class="ti ti-history me-2 text-primary"></i>System Activity Timeline</h5>
                        <a href="pages/audit_logs.php" class="btn btn-sm btn-light border">Audit log</a>
                    </div>
                    <div class="card-body">
                        <?php if ($d['activity'] !== []): ?>
                            <?php foreach (array_slice($d['activity'], 0, 10) as $row): ?>
                                <?php clinic_dash_activity_item($row); ?>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="text-muted mb-0 py-2">No activity recorded yet — events are logged as they happen.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="dash-span-4">
                <div class="card clinic-card h-100 dash-card">
                    <div class="card-header d-flex align-items-center justify-content-between py-3">
                        <h5 class="mb-0 small fw-bold text-uppercase" style="letter-spacing:.04em"><i class="ti ti-microscope me-2 text-danger"></i>Critical Lab Results</h5>
                        <a href="pages/lab_results.php" class="btn btn-sm btn-light border">Lab queue</a>
                    </div>
                    <div class="card-body">
                        <?php if ($d['criticalLab'] !== []): ?>
                            <?php foreach ($d['criticalLab'] as $row): ?>
                                <div class="dash-list-item">
                                    <div class="min-w-0">
                                        <div class="dash-list-title"><?php echo clinic_h($row['Patient_Name'] ?? '-'); ?> · <?php echo clinic_h($row['Test_Name'] ?? ''); ?></div>
                                        <div class="dash-list-sub text-danger"><?php echo clinic_h($row['Result_Details'] ?? ''); ?></div>
                                    </div>
                                    <span class="badge text-bg-danger">Critical</span>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="text-muted mb-0 py-2">No critical results.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (typeof ApexCharts === 'undefined') { return; }
            var themeDark = document.documentElement.getAttribute('data-bs-theme') === 'dark'
                || document.documentElement.getAttribute('data-theme') === 'dark';
            var grid = themeDark ? '#2d3340' : '#e9ecef';
            var lbl = themeDark ? '#a8b0bd' : '#6c757d';
            var baseOpts = { chart: { toolbar: { show: false }, fontFamily: 'inherit' }, dataLabels: { enabled: false }, stroke: { curve: 'smooth', width: 3 }, grid: { borderColor: grid } };
            function money(v) { return '$' + Number(v).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
<?php if ($sec['finance']): ?>
            new ApexCharts(document.querySelector('#chartRevenue'), Object.assign({}, baseOpts, {
                chart: Object.assign({}, baseOpts.chart, { type: 'area', height: '100%' }),
                series: [{ name: 'Payments', data: <?php echo json_encode($d['revenueTrend']['values']); ?> }],
                xaxis: { categories: <?php echo json_encode($d['revenueTrend']['labels']); ?>, labels: { style: { colors: lbl } } },
                yaxis: { labels: { style: { colors: lbl }, formatter: function (v) { return Math.round(v); } } },
                colors: ['#1971c2'], fill: { type: 'gradient', gradient: { opacityFrom: 0.35, opacityTo: 0.05 } },
                tooltip: { y: { formatter: money } }
            })).render();
            new ApexCharts(document.querySelector('#chartRevenueDonut'), Object.assign({}, baseOpts, {
                chart: Object.assign({}, baseOpts.chart, { type: 'donut', height: '100%' }),
                series: <?php echo json_encode(array_values($d['revenueByMethod'])); ?>,
                labels: <?php echo json_encode(array_map('strval', array_keys($d['revenueByMethod']))); ?>,
                colors: ['#1971c2', '#40c057', '#f59f00', '#868e96'],
                legend: { position: 'bottom', labels: { colors: lbl } },
                tooltip: { y: { formatter: money } }
            })).render();
<?php endif; ?>
<?php if ($sec['patients']): ?>
            new ApexCharts(document.querySelector('#chartPatients'), Object.assign({}, baseOpts, {
                chart: Object.assign({}, baseOpts.chart, { type: 'bar', height: '100%' }),
                series: [{ name: 'New patients', data: <?php echo json_encode($d['patientTrend']['values']); ?> }],
                xaxis: { categories: <?php echo json_encode($d['patientTrend']['labels']); ?>, labels: { style: { colors: lbl } } },
                yaxis: { labels: { style: { colors: lbl } } },
                colors: ['#2E37A4'], plotOptions: { bar: { borderRadius: 4, columnWidth: '45%' } }
            })).render();
            new ApexCharts(document.querySelector('#chartGender'), Object.assign({}, baseOpts, {
                chart: Object.assign({}, baseOpts.chart, { type: 'donut', height: '100%' }),
                series: [<?php echo (int) ($d['patientGender']['Male'] ?? 0); ?>, <?php echo (int) ($d['patientGender']['Female'] ?? 0); ?>],
                labels: ['Male', 'Female'],
                colors: ['#1971c2', '#e64980'],
                legend: { position: 'bottom', labels: { colors: lbl } }
            })).render();
            new ApexCharts(document.querySelector('#chartAge'), Object.assign({}, baseOpts, {
                chart: Object.assign({}, baseOpts.chart, { type: 'bar', height: '100%' }),
                series: [{ name: 'Patients', data: [<?php echo (int) ($d['patientAge']['Child'] ?? 0); ?>, <?php echo (int) ($d['patientAge']['Adult'] ?? 0); ?>] }],
                xaxis: { categories: ['Child', 'Adult'], labels: { style: { colors: lbl } } },
                yaxis: { labels: { style: { colors: lbl } } },
                colors: ['#0dcaf0'], plotOptions: { bar: { borderRadius: 4, columnWidth: '40%' } }
            })).render();
            new ApexCharts(document.querySelector('#chartType'), Object.assign({}, baseOpts, {
                chart: Object.assign({}, baseOpts.chart, { type: 'bar', height: '100%' }),
                series: [{ name: 'Patients', data: [<?php echo (int) ($d['patientType']['Credit'] ?? 0); ?>, <?php echo (int) ($d['patientType']['Walk-in'] ?? 0); ?>] }],
                xaxis: { categories: ['Credit', 'Walk-in'], labels: { style: { colors: lbl } } },
                yaxis: { labels: { style: { colors: lbl } } },
                colors: ['#40c057'], plotOptions: { bar: { borderRadius: 4, columnWidth: '40%' } }
            })).render();
<?php endif; ?>
<?php if ($sec['appointments']): ?>
            new ApexCharts(document.querySelector('#chartApptStatus'), Object.assign({}, baseOpts, {
                chart: Object.assign({}, baseOpts.chart, { type: 'bar', height: '100%' }),
                series: [{ name: 'Appointments', data: <?php echo json_encode(array_values($d['appointmentsByStatus'])); ?> }],
                xaxis: { categories: <?php echo json_encode(array_keys($d['appointmentsByStatus'])); ?>, labels: { style: { colors: lbl } } },
                yaxis: { labels: { style: { colors: lbl } } },
                colors: ['#0dcaf0'], plotOptions: { bar: { borderRadius: 4, columnWidth: '45%' } }
            })).render();
<?php endif; ?>
<?php if ($sec['visits']): ?>
            new ApexCharts(document.querySelector('#chartDoctors'), Object.assign({}, baseOpts, {
                chart: Object.assign({}, baseOpts.chart, { type: 'bar', height: '100%' }),
                series: [{ name: 'Appointments', data: <?php echo json_encode(array_column($d['appointmentsByDoctor'], 'count')); ?> }],
                xaxis: { categories: <?php echo json_encode(array_column($d['appointmentsByDoctor'], 'doctor')); ?>, labels: { style: { colors: lbl } } },
                yaxis: { labels: { style: { colors: lbl } } },
                colors: ['#2E37A4'], plotOptions: { bar: { borderRadius: 4, horizontal: true } }
            })).render();
            new ApexCharts(document.querySelector('#chartVisits'), Object.assign({}, baseOpts, {
                chart: Object.assign({}, baseOpts.chart, { type: 'bar', height: '100%' }),
                series: [{ name: 'Visits', data: <?php echo json_encode($d['visitsTrend']['values']); ?> }],
                xaxis: { categories: <?php echo json_encode($d['visitsTrend']['labels']); ?>, labels: { style: { colors: lbl } } },
                yaxis: { labels: { style: { colors: lbl } } },
                colors: ['#2E37A4'], plotOptions: { bar: { borderRadius: 4, columnWidth: '40%' } }
            })).render();
<?php endif; ?>
<?php if ($sec['lab']): ?>
            new ApexCharts(document.querySelector('#chartLabDonut'), Object.assign({}, baseOpts, {
                chart: Object.assign({}, baseOpts.chart, { type: 'donut', height: '100%' }),
                series: [<?php echo (int) $k['lab_completed']; ?>, <?php echo (int) $k['lab_pending']; ?>],
                labels: ['Completed', 'Pending'],
                colors: ['#40c057', '#f59f00'],
                legend: { position: 'bottom', labels: { colors: lbl } }
            })).render();
<?php endif; ?>
<?php if ($sec['pharmacy']): ?>
            new ApexCharts(document.querySelector('#chartPharmacy'), Object.assign({}, baseOpts, {
                chart: Object.assign({}, baseOpts.chart, { type: 'area', height: '100%' }),
                series: [{ name: 'Sales', data: <?php echo json_encode($d['pharmacyTrend']['values']); ?> }],
                xaxis: { categories: <?php echo json_encode($d['pharmacyTrend']['labels']); ?>, labels: { style: { colors: lbl } } },
                yaxis: { labels: { style: { colors: lbl } } },
                colors: ['#40c057'], fill: { type: 'gradient', gradient: { opacityFrom: 0.3, opacityTo: 0.05 } },
                tooltip: { y: { formatter: money } }
            })).render();
<?php endif; ?>


        });
    </script>
</div>












