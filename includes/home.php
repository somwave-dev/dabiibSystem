<?php
require_once __DIR__ . '/advanced_components.php';

$summary = clinic_sp_one('sp_dashboard_summary') ?? [];
$appointments = array_slice(clinic_sp_rows('sp_appointments_list'), 0, 6);
$labQueue = array_values(array_filter(clinic_sp_rows('sp_lab_results_list'), static fn ($row) => ($row['Status'] ?? '') === 'Pending'));
$lowStock = array_slice(clinic_sp_rows('sp_low_stock_medicines'), 0, 6);
$payments = array_slice(clinic_sp_rows('sp_payments_list'), 0, 6);
?>
<div class="content pb-0">
    <div class="d-flex align-items-sm-center justify-content-between flex-wrap gap-2 mb-4">
        <div>
            <h4 class="fw-bold mb-1">Clinic Command Center</h4>
            <p class="text-muted mb-0">Live overview for reception, lab, pharmacy, and finance workflows.</p>
        </div>
        <div class="d-flex align-items-center flex-wrap gap-2">
            <a href="pages/patients.php" class="btn btn-primary d-inline-flex align-items-center"><i class="ti ti-user-plus me-1"></i>Register Patient</a>
            <a href="pages/appointments.php" class="btn btn-outline-white bg-white d-inline-flex align-items-center"><i class="ti ti-calendar-plus me-1"></i>Appointments</a>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <?php clinic_metric_card('Patients', (int) ($summary['patients_total'] ?? 0), 'ti-users', 'primary', 'Registered patients'); ?>
        <?php clinic_metric_card('Today Appointments', (int) ($summary['appointments_today'] ?? 0), 'ti-calendar-check', 'info', 'Pending: ' . (int) ($summary['appointments_pending'] ?? 0)); ?>
        <?php clinic_metric_card('Pending Lab', (int) ($summary['lab_pending'] ?? 0), 'ti-microscope', 'warning', 'Awaiting results'); ?>
        <?php clinic_metric_card('Revenue Today', clinic_money($summary['revenue_today'] ?? 0), 'ti-cash', 'success', 'Week: ' . clinic_money($summary['revenue_week'] ?? 0)); ?>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-xl-8">
            <div class="card clinic-card h-100">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <h5 class="mb-0">Today / Recent Appointments</h5>
                    <a href="pages/appointments.php" class="btn btn-sm btn-light border">Open board</a>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle clinic-table mb-0">
                            <thead><tr><th>Patient</th><th>Doctor</th><th>Date</th><th>Status</th><th></th></tr></thead>
                            <tbody>
                                <?php foreach ($appointments as $row): ?>
                                <tr>
                                    <td class="fw-semibold"><?php echo clinic_h($row['Patient_Name'] ?? '-'); ?></td>
                                    <td><?php echo clinic_h($row['Doctor_Name'] ?? '-'); ?></td>
                                    <td><?php echo clinic_h($row['Appointment_Date'] ?? '-'); ?></td>
                                    <td><?php echo clinic_status_badge((string) ($row['Status'] ?? 'Pending')); ?></td>
                                    <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="pages/visits.php?patient_id=<?php echo (int) ($row['Patient_ID'] ?? 0); ?>">Start visit</a></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if ($appointments === []): ?><tr><td colspan="5" class="text-center text-muted">No appointments yet.</td></tr><?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="card clinic-card h-100">
                <div class="card-header"><h5 class="mb-0">Finance Snapshot</h5></div>
                <div class="card-body">
                    <div class="d-flex justify-content-between border-bottom pb-3 mb-3">
                        <span class="text-muted">Patient debt</span>
                        <strong><?php echo clinic_money($summary['patient_debt'] ?? 0); ?></strong>
                    </div>
                    <?php foreach ($payments as $row): ?>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <div class="fw-semibold"><?php echo clinic_h($row['Patient_Name'] ?? '-'); ?></div>
                            <div class="small text-muted"><?php echo clinic_h($row['Payment_Method'] ?? '-'); ?></div>
                        </div>
                        <span class="badge text-bg-success"><?php echo clinic_money($row['Amount'] ?? 0); ?></span>
                    </div>
                    <?php endforeach; ?>
                    <?php if ($payments === []): ?><p class="text-muted mb-0">No recent payments.</p><?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-xl-6">
            <div class="card clinic-card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Lab Queue</h5>
                    <a href="pages/lab_results.php" class="btn btn-sm btn-light border">Open lab</a>
                </div>
                <div class="card-body">
                    <?php foreach (array_slice($labQueue, 0, 5) as $row): ?>
                    <div class="d-flex align-items-center justify-content-between border-bottom pb-2 mb-2">
                        <div>
                            <div class="fw-semibold"><?php echo clinic_h($row['Patient_Name'] ?? '-'); ?></div>
                            <div class="small text-muted"><?php echo clinic_h($row['Test_Name'] ?? '-'); ?></div>
                        </div>
                        <?php echo clinic_status_badge((string) ($row['Status'] ?? 'Pending')); ?>
                    </div>
                    <?php endforeach; ?>
                    <?php if ($labQueue === []): ?><p class="text-muted mb-0">No pending lab results.</p><?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-xl-6">
            <div class="card clinic-card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Low Stock Medicines</h5>
                    <a href="pages/pharmacy_sales.php" class="btn btn-sm btn-light border">Open pharmacy</a>
                </div>
                <div class="card-body">
                    <?php foreach ($lowStock as $row): ?>
                    <div class="d-flex align-items-center justify-content-between border-bottom pb-2 mb-2">
                        <div>
                            <div class="fw-semibold"><?php echo clinic_h($row['Medicine_Name'] ?? '-'); ?></div>
                            <div class="small text-muted">Expires: <?php echo clinic_h($row['Expiry_Date'] ?? '-'); ?></div>
                        </div>
                        <span class="badge text-bg-warning"><?php echo (int) ($row['Stock_Quantity'] ?? 0); ?> left</span>
                    </div>
                    <?php endforeach; ?>
                    <?php if ($lowStock === []): ?><p class="text-muted mb-0">Stock levels look good.</p><?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
