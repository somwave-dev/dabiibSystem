<?php
require_once __DIR__ . '/../includes/advanced_components.php';

function clinic_appointment_day(array $appointment): string
{
    $rawDate = trim((string) ($appointment['Appointment_Date'] ?? ''));
    if ($rawDate === '') {
        return date('Y-m-d');
    }

    return substr(str_replace('T', ' ', $rawDate), 0, 10);
}

function clinic_appointment_return_query(): string
{
    $returnStatus = clinic_post_string('return_status');
    $returnParams = [];
    if ($returnStatus !== '') {
        $returnParams['status'] = $returnStatus;
    }

    return $returnParams === [] ? '' : '?' . http_build_query($returnParams);
}

try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        clinic_check_csrf();
        $action = clinic_post_string('action');
        if ($action === 'save_appointment') {
            $appointmentId = clinic_post_int('Appointment_ID');
            if ($appointmentId > 0) {
                $existingAppointment = clinic_sp_one('sp_appointments_get', [$appointmentId], 'i');
                if (!$existingAppointment) {
                    throw new RuntimeException('Appointment was not found.');
                }
                if ((string) ($existingAppointment['Status'] ?? '') === 'Completed') {
                    throw new RuntimeException('Completed appointments cannot be edited.');
                }
            }

            $patientId = clinic_post_int('Patient_ID');
            if ($patientId < 1 && clinic_post_string('Quick_Patient_Name') !== '') {
                $quickName = clinic_post_string('Quick_Patient_Name');
                $quickPhone = clinic_post_string('Quick_Patient_Phone');
                clinic_sp_exec('sp_patients_save', [
                    0,
                    $quickName,
                    $quickPhone,
                    clinic_post_string('Quick_Sex') ?: 'Male',
                    clinic_post_string('Quick_Age_Group') ?: 'Adult',
                    'Walk-in',
                    null,
                    'Self',
                    0.0,
                    0.0,
                ]);

                foreach (clinic_sp_rows('sp_patients_list') as $patient) {
                    if ((string) ($patient['Full_Name'] ?? '') === $quickName && (string) ($patient['Phone_Number'] ?? '') === $quickPhone) {
                        $patientId = (int) ($patient['Patient_ID'] ?? 0);
                        break;
                    }
                }
            }

            if ($patientId < 1) {
                throw new RuntimeException('Select a patient or quick-add a new Walk-in patient.');
            }

            $appointmentStatus = $appointmentId > 0 ? (clinic_post_string('Status') ?: 'Pending') : 'Pending';
            if (!in_array($appointmentStatus, ['Pending', 'Completed', 'Cancelled'], true)) {
                $appointmentStatus = 'Pending';
            }

            clinic_sp_exec('sp_appointments_save', [$appointmentId, $patientId, clinic_post_int('Doctor_ID'), str_replace('T', ' ', clinic_post_string('Appointment_Date')), $appointmentStatus]);
            $newAppointmentId = 0;
            if ($appointmentId < 1 && ($result = $conn->query('SELECT LAST_INSERT_ID() AS Appointment_ID'))) {
                $row = $result->fetch_assoc();
                $newAppointmentId = (int) ($row['Appointment_ID'] ?? 0);
                $result->free();
            }
            clinic_flash($appointmentId > 0 ? 'Appointment updated.' : 'Appointment saved.');
            if ($appointmentId > 0) {
                clinic_redirect('appointments.php' . clinic_appointment_return_query());
            }
            clinic_redirect($newAppointmentId > 0 ? 'appointments.php?print_appointment=' . $newAppointmentId : 'appointments.php');
        }
        if ($action === 'delete_appointment') {
            $appointmentId = clinic_post_int('Appointment_ID');
            if ($appointmentId < 1) {
                throw new RuntimeException('Appointment was not found.');
            }

            $existingAppointment = clinic_sp_one('sp_appointments_get', [$appointmentId], 'i');
            if (!$existingAppointment) {
                throw new RuntimeException('Appointment was not found.');
            }
            if ((string) ($existingAppointment['Status'] ?? '') === 'Completed') {
                throw new RuntimeException('Completed appointments cannot be deleted.');
            }

            clinic_sp_exec('sp_appointments_delete', [$appointmentId], 'i');
            clinic_flash('Appointment deleted.');
            clinic_redirect('appointments.php' . clinic_appointment_return_query());
        }
        if ($action === 'set_status') {
            $row = clinic_sp_one('sp_appointments_get', [clinic_post_int('Appointment_ID')], 'i');
            if ($row) {
                clinic_sp_exec('sp_appointments_save', [(int) $row['Appointment_ID'], (int) $row['Patient_ID'], (int) $row['Doctor_ID'], (string) $row['Appointment_Date'], clinic_post_string('Status')]);
            }
            clinic_flash('Appointment status updated.');
            clinic_redirect('appointments.php' . clinic_appointment_return_query());
        }
    }
} catch (Throwable $e) {
    clinic_flash($e->getMessage(), 'danger');
    clinic_redirect('appointments.php');
}

$appointments = clinic_sp_rows('sp_appointments_list');
$patients = clinic_sp_rows('sp_patients_list');
$doctors = clinic_sp_rows('sp_doctors_list');
$prefillPatientId = (int) ($_GET['patient_id'] ?? 0);
$prefillDoctorId = (int) ($_GET['doctor_id'] ?? 0);
$queueRows = $appointments;
usort($queueRows, static function (array $left, array $right): int {
    $dayCompare = strcmp(clinic_appointment_day($left), clinic_appointment_day($right));
    if ($dayCompare !== 0) {
        return $dayCompare;
    }

    return (int) ($left['Appointment_ID'] ?? 0) <=> (int) ($right['Appointment_ID'] ?? 0);
});

$dailyQueueCounts = [];
$queueByAppointment = [];
foreach ($queueRows as $row) {
    $dayKey = clinic_appointment_day($row);
    $dailyQueueCounts[$dayKey] = ($dailyQueueCounts[$dayKey] ?? 0) + 1;
    $queueByAppointment[(int) ($row['Appointment_ID'] ?? 0)] = [
        'day' => $dayKey,
        'number' => $dailyQueueCounts[$dayKey],
    ];
}

foreach ($appointments as &$appointment) {
    $queueInfo = $queueByAppointment[(int) ($appointment['Appointment_ID'] ?? 0)] ?? null;
    $appointment['Queue_Day'] = $queueInfo['day'] ?? clinic_appointment_day($appointment);
    $appointment['Queue_No'] = (int) ($queueInfo['number'] ?? 0);
}
unset($appointment);

$allAppointments = $appointments;
$printAppointmentId = (int) ($_GET['print_appointment'] ?? 0);
$printAppointment = null;
if ($printAppointmentId > 0) {
    foreach ($allAppointments as $appointment) {
        if ((int) ($appointment['Appointment_ID'] ?? 0) === $printAppointmentId) {
            $printAppointment = $appointment;
            break;
        }
    }
}

$todayKey = date('Y-m-d');
$todayAppointments = array_values(array_filter($appointments, static fn (array $row): bool => (string) ($row['Queue_Day'] ?? '') === $todayKey));
$todayPending = array_values(array_filter($todayAppointments, static fn (array $row): bool => (string) ($row['Status'] ?? 'Pending') === 'Pending'));
usort($todayPending, static fn (array $left, array $right): int => (int) ($left['Queue_No'] ?? 0) <=> (int) ($right['Queue_No'] ?? 0));
$nowServingQueue = $todayPending[0]['Queue_No'] ?? null;
$todayLastQueue = (int) ($dailyQueueCounts[$todayKey] ?? 0);
$todayCompletedCount = count(array_filter($todayAppointments, static fn (array $row): bool => (string) ($row['Status'] ?? '') === 'Completed'));
$todayCancelledCount = count(array_filter($todayAppointments, static fn (array $row): bool => (string) ($row['Status'] ?? '') === 'Cancelled'));
$status = (string) ($_GET['status'] ?? '');
if ($status !== '') {
    $appointments = array_values(array_filter($appointments, static fn ($row) => ($row['Status'] ?? '') === $status));
}

clinic_page_start('Appointment Board');

$todayTotalCount = count($todayAppointments);
$todayPendingCount = count($todayPending);
?>
<div class="row g-3 mb-4">
    <?php clinic_metric_card('Total Today', $todayTotalCount, 'ti-calendar', 'primary', 'Appointments today'); ?>
    <?php clinic_metric_card('Pending', $todayPendingCount, 'ti-clock', 'warning', 'Awaiting attendance'); ?>
    <?php clinic_metric_card('Completed', $todayCompletedCount, 'ti-circle-check', 'success', 'Done today'); ?>
    <?php clinic_metric_card('Cancelled', $todayCancelledCount, 'ti-circle-x', 'danger', 'Cancelled today'); ?>
</div>
<style>
    .appointment-modal .modal-content {
        border: 0;
        border-radius: 22px;
        box-shadow: 0 24px 70px rgba(15, 23, 42, .22);
        overflow: hidden;
    }
    .appointment-modal-header {
        background: #f8fafc;
        border-bottom: 1px solid rgba(15, 23, 42, .1);
        color: var(--bs-body-color);
        padding: 1.25rem 1.5rem;
    }
    .appointment-modal-header .modal-title {
        font-weight: 800;
        color: var(--bs-heading-color);
    }
    .appointment-form-card {
        background: #fff;
        border: 1px solid rgba(15, 23, 42, .08);
        border-radius: 18px;
        box-shadow: 0 10px 26px rgba(15, 23, 42, .06);
        padding: 1rem;
    }
    .appointment-section-title {
        align-items: center;
        display: flex;
        gap: .55rem;
        font-weight: 800;
        margin-bottom: .75rem;
    }
    .appointment-section-title span {
        align-items: center;
        background: rgba(0, 0, 0, .04);
        border-radius: 12px;
        color: var(--primary);
        display: inline-flex;
        height: 34px;
        justify-content: center;
        width: 34px;
    }
    .quick-patient-box {
        background: linear-gradient(135deg, rgba(0, 123, 255, .06), rgba(25, 135, 84, .06));
        border: 1px dashed rgba(0, 0, 0, .16);
        border-radius: 18px;
        display: none;
        padding: 1rem;
    }
    .quick-patient-box.active {
        display: block;
    }
    .quick-patient-toggle {
        border-radius: 999px;
        font-weight: 700;
    }
    .appointment-modal .form-control,
    .appointment-modal .form-select {
        border-radius: 12px;
        min-height: 44px;
    }
    .appointment-modal .modal-footer {
        background: #f8fafc;
        border-top: 1px solid rgba(15, 23, 42, .08);
        padding: 1rem 1.5rem;
    }
    .queue-summary-card {
        background: #fff;
        border: 1px solid rgba(15, 23, 42, .08);
        border-radius: 18px;
        box-shadow: 0 12px 30px rgba(15, 23, 42, .06);
        padding: 1rem;
    }
    .queue-summary-icon {
        align-items: center;
        background: linear-gradient(135deg, var(--primary), #5b7cfa);
        border-radius: 16px;
        color: #fff;
        display: inline-flex;
        height: 48px;
        justify-content: center;
        width: 48px;
    }
    .queue-number-badge {
        align-items: center;
        background: linear-gradient(135deg, var(--primary), #5b7cfa);
        border-radius: 16px;
        color: #fff;
        display: inline-flex;
        flex-direction: column;
        font-weight: 800;
        height: 64px;
        justify-content: center;
        min-width: 72px;
        padding: .4rem .65rem;
        box-shadow: 0 10px 22px rgba(55, 84, 219, .22);
    }
    .queue-number-badge small {
        font-size: .58rem;
        font-weight: 700;
        letter-spacing: .08em;
        opacity: .8;
        text-transform: uppercase;
    }
    .queue-number-badge strong {
        font-size: 1.25rem;
        line-height: 1;
    }
    .appointment-card {
        background: #fff;
        border: 1px solid rgba(15, 23, 42, .08);
        border-left: 4px solid var(--primary);
        border-radius: 20px;
        box-shadow: 0 12px 30px rgba(15, 23, 42, .06);
        height: 100%;
        transition: transform .15s ease, box-shadow .15s ease;
    }
    .appointment-card:hover {
        box-shadow: 0 18px 42px rgba(15, 23, 42, .10);
        transform: translateY(-2px);
    }
    .appointment-card-completed {
        border-left-color: #198754;
    }
    .appointment-card-cancelled {
        border-left-color: #6c757d;
        opacity: .86;
    }
    .appointment-card-head {
        align-items: flex-start;
        display: flex;
        gap: .8rem;
        justify-content: space-between;
        margin-bottom: 1rem;
    }
    .appointment-patient-block {
        align-items: center;
        display: flex;
        gap: .75rem;
        min-width: 0;
    }
    .appointment-avatar {
        align-items: center;
        background: linear-gradient(135deg, rgba(13, 110, 253, .12), rgba(91, 124, 250, .18));
        border-radius: 16px;
        color: var(--primary);
        display: inline-flex;
        flex: 0 0 46px;
        font-weight: 800;
        height: 46px;
        justify-content: center;
        text-transform: uppercase;
        width: 46px;
    }
    .appointment-patient-name {
        font-size: 1rem;
        font-weight: 800;
        line-height: 1.2;
        margin-bottom: .25rem;
    }
    .min-w-0 {
        min-width: 0;
    }
    .appointment-meta {
        background: #f8fafc;
        border: 1px solid rgba(15, 23, 42, .06);
        border-radius: 14px;
        padding: .75rem;
    }
    .appointment-meta-row {
        align-items: center;
        display: flex;
        gap: .45rem;
        min-width: 0;
    }
    .appointment-meta-row + .appointment-meta-row {
        margin-top: .55rem;
    }
    .appointment-meta-row i {
        color: var(--primary);
        flex: 0 0 auto;
    }
    .appointment-actions {
        display: grid;
        gap: .5rem;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        margin-top: 1rem;
    }
    .appointment-actions .btn,
    .appointment-actions form,
    .appointment-actions form button {
        width: 100%;
    }
    .appointment-actions .btn {
        border-radius: 10px;
        font-weight: 700;
    }
    .appointment-actions .btn-primary {
        box-shadow: 0 8px 18px rgba(13, 110, 253, .18);
    }
    .appointment-toolbar {
        align-items: center;
        display: flex;
        gap: .75rem;
        justify-content: space-between;
        margin-bottom: 1rem;
    }
    .appointment-toolbar-actions {
        align-items: center;
        display: flex;
        flex-wrap: wrap;
        gap: .5rem;
    }
    .appointment-table-avatar {
        align-items: center;
        background: rgba(13, 110, 253, .12);
        border-radius: 12px;
        color: var(--primary);
        display: inline-flex;
        font-weight: 800;
        height: 36px;
        justify-content: center;
        width: 36px;
    }
    .appointment-table-queue {
        background: rgba(13, 110, 253, .10);
        border-radius: 999px;
        color: var(--primary);
        display: inline-flex;
        font-weight: 800;
        padding: .35rem .65rem;
    }
    .appointment-table-actions {
        align-items: center;
        display: flex;
        flex-wrap: wrap;
        gap: .35rem;
        justify-content: flex-end;
    }
    @media (max-width: 575.98px) {
        .appointment-actions {
            grid-template-columns: 1fr;
        }
    }
    @media (max-width: 991.98px) {
        .appointment-toolbar {
            align-items: stretch;
            flex-direction: column;
        }
        .appointment-toolbar-actions {
            justify-content: space-between;
        }
    }
    #appointmentTicketPrintArea {
        display: none;
    }
    .appointment-ticket {
        background: #fff;
        color: #111827;
        font-family: Arial, sans-serif;
        margin: 0 auto;
        max-width: 320px;
        padding: 18px;
        text-align: center;
    }
    .appointment-ticket .ticket-brand {
        border-bottom: 1px dashed #9ca3af;
        margin-bottom: 14px;
        padding-bottom: 12px;
    }
    .appointment-ticket .ticket-queue {
        border: 2px solid #111827;
        border-radius: 18px;
        margin: 12px auto;
        padding: 12px;
    }
    .appointment-ticket .ticket-queue small {
        display: block;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: .12em;
        text-transform: uppercase;
    }
    .appointment-ticket .ticket-queue strong {
        display: block;
        font-size: 48px;
        line-height: 1;
    }
    .appointment-ticket .ticket-row {
        border-bottom: 1px dashed #d1d5db;
        display: flex;
        font-size: 13px;
        justify-content: space-between;
        padding: 7px 0;
        text-align: left;
    }
    .appointment-ticket .ticket-row strong {
        text-align: right;
    }
    @media print {
        body.is-printing-appointment .main-wrapper * {
            visibility: hidden !important;
        }
        body.is-printing-appointment #appointmentTicketPrintArea,
        body.is-printing-appointment #appointmentTicketPrintArea * {
            visibility: visible !important;
        }
        body.is-printing-appointment #appointmentTicketPrintArea {
            display: block !important;
            left: 0;
            position: absolute;
            top: 0;
            width: 100%;
        }
        body.is-printing-appointment {
            background: #fff !important;
        }
        @page {
            margin: 8mm;
            size: 80mm auto;
        }
    }
</style>
<div class="appointment-toolbar">
    <div class="btn-group">
        <a class="btn btn-<?php echo $status === '' ? 'primary' : 'light border'; ?>" href="appointments.php?<?php echo http_build_query([]); ?>">All</a>
        <a class="btn btn-<?php echo $status === 'Pending' ? 'primary' : 'light border'; ?>" href="appointments.php?<?php echo http_build_query(['status' => 'Pending']); ?>">Pending</a>
        <a class="btn btn-<?php echo $status === 'Completed' ? 'primary' : 'light border'; ?>" href="appointments.php?<?php echo http_build_query(['status' => 'Completed']); ?>">Completed</a>
        <a class="btn btn-<?php echo $status === 'Cancelled' ? 'primary' : 'light border'; ?>" href="appointments.php?<?php echo http_build_query(['status' => 'Cancelled']); ?>">Cancelled</a>
    </div>
    <div class="appointment-toolbar-actions">
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#appointmentModal" data-appointment-mode="new">New Appointment</button>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-xl-3 col-md-6">
        <div class="queue-summary-card h-100 d-flex align-items-center gap-3">
            <span class="queue-summary-icon"><i class="ti ti-list-numbers fs-22"></i></span>
            <div>
                <div class="text-muted small">Today Tickets</div>
                <h4 class="fw-bold mb-0"><?php echo str_pad((string) $todayLastQueue, 3, '0', STR_PAD_LEFT); ?></h4>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="queue-summary-card h-100 d-flex align-items-center gap-3">
            <span class="queue-summary-icon"><i class="ti ti-speakerphone fs-22"></i></span>
            <div>
                <div class="text-muted small">Now Serving</div>
                <h4 class="fw-bold mb-0"><?php echo $nowServingQueue ? '#' . str_pad((string) $nowServingQueue, 3, '0', STR_PAD_LEFT) : '-'; ?></h4>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="queue-summary-card h-100 d-flex align-items-center gap-3">
            <span class="queue-summary-icon"><i class="ti ti-circle-plus fs-22"></i></span>
            <div>
                <div class="text-muted small">Next Ticket</div>
                <h4 class="fw-bold mb-0">#<?php echo str_pad((string) ($todayLastQueue + 1), 3, '0', STR_PAD_LEFT); ?></h4>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="queue-summary-card h-100">
            <div class="text-muted small mb-2">Today Status</div>
            <div class="d-flex flex-wrap gap-2">
                <span class="badge text-bg-warning">Pending <?php echo count($todayPending); ?></span>
                <span class="badge text-bg-success">Completed <?php echo $todayCompletedCount; ?></span>
                <span class="badge text-bg-secondary">Cancelled <?php echo $todayCancelledCount; ?></span>
            </div>
        </div>
    </div>
</div>

<div class="card clinic-card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Appointment List</h5>
        <span class="badge text-bg-light"><?php echo count($appointments); ?> appointments</span>
    </div>
    <div class="card-body table-responsive">
        <table class="table table-hover align-middle datatable clinic-table">
            <thead>
                <tr>
                    <th>Queue</th>
                    <th>Patient</th>
                    <th>Doctor</th>
                    <th>App Date</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($appointments as $row): ?>
                <?php $appointmentStatus = (string) ($row['Status'] ?? 'Pending'); ?>
                <tr>
                    <td><span class="appointment-table-queue">#<?php echo str_pad((string) (int) ($row['Queue_No'] ?? 0), 3, '0', STR_PAD_LEFT); ?></span></td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <span class="appointment-table-avatar"><?php echo clinic_h(substr((string) ($row['Patient_Name'] ?? 'P'), 0, 1)); ?></span>
                            <div>
                                <div class="fw-semibold"><?php echo clinic_h($row['Patient_Name'] ?? '-'); ?></div>
                                <div class="small text-muted">APT-<?php echo str_pad((string) (int) ($row['Appointment_ID'] ?? 0), 5, '0', STR_PAD_LEFT); ?></div>
                            </div>
                        </div>
                    </td>
                    <td><?php echo clinic_h($row['Doctor_Name'] ?? '-'); ?></td>
                    <td><?php echo clinic_h($row['Appointment_Date'] ?? '-'); ?></td>
                    <td><?php echo clinic_status_badge($appointmentStatus); ?></td>
                    <td>
                        <div class="appointment-table-actions">
                            <?php if ($appointmentStatus === 'Pending'): ?>
                            <a class="btn btn-sm btn-primary" href="visits.php?patient_id=<?php echo (int) ($row['Patient_ID'] ?? 0); ?>&appointment_id=<?php echo (int) ($row['Appointment_ID'] ?? 0); ?>">Start Visit</a>
                            <a class="btn btn-sm btn-light border" href="appointments.php?<?php echo http_build_query(['print_appointment' => (int) ($row['Appointment_ID'] ?? 0), 'status' => $status]); ?>"><i class="ti ti-printer me-1"></i>Print</a>
                            <button class="btn btn-sm btn-light border" type="button" data-bs-toggle="modal" data-bs-target="#appointmentModal" data-appointment-mode="edit" data-appointment-id="<?php echo (int) ($row['Appointment_ID'] ?? 0); ?>" data-patient-id="<?php echo (int) ($row['Patient_ID'] ?? 0); ?>" data-doctor-id="<?php echo (int) ($row['Doctor_ID'] ?? 0); ?>" data-appointment-date="<?php echo clinic_h($row['Appointment_Date'] ?? ''); ?>" data-status="<?php echo clinic_h($appointmentStatus); ?>" title="Edit"><i class="ti ti-pencil"></i></button>
                            <form method="post" class="d-inline"><input type="hidden" name="csrf_token" value="<?php echo clinic_h(clinic_csrf_token()); ?>"><input type="hidden" name="action" value="set_status"><input type="hidden" name="Appointment_ID" value="<?php echo (int) $row['Appointment_ID']; ?>"><input type="hidden" name="Status" value="Completed"><input type="hidden" name="return_status" value="<?php echo clinic_h($status); ?>"><button class="btn btn-sm btn-outline-success">Complete</button></form>
                            <form method="post" class="d-inline"><input type="hidden" name="csrf_token" value="<?php echo clinic_h(clinic_csrf_token()); ?>"><input type="hidden" name="action" value="set_status"><input type="hidden" name="Appointment_ID" value="<?php echo (int) $row['Appointment_ID']; ?>"><input type="hidden" name="Status" value="Cancelled"><input type="hidden" name="return_status" value="<?php echo clinic_h($status); ?>"><button class="btn btn-sm btn-outline-secondary">Cancel</button></form>
                            <form method="post" class="d-inline appointment-delete-form" data-patient-name="<?php echo clinic_h($row['Patient_Name'] ?? ''); ?>"><input type="hidden" name="csrf_token" value="<?php echo clinic_h(clinic_csrf_token()); ?>"><input type="hidden" name="action" value="delete_appointment"><input type="hidden" name="Appointment_ID" value="<?php echo (int) $row['Appointment_ID']; ?>"><input type="hidden" name="return_status" value="<?php echo clinic_h($status); ?>"><button class="btn btn-sm btn-outline-danger btn-icon" title="Delete"><i class="ti ti-trash"></i></button></form>
                            <?php elseif ($appointmentStatus === 'Completed'): ?>
                            <a class="btn btn-sm btn-light border" href="appointments.php?<?php echo http_build_query(['print_appointment' => (int) ($row['Appointment_ID'] ?? 0), 'status' => $status]); ?>"><i class="ti ti-printer me-1"></i>Print Ticket</a>
                            <span class="btn btn-sm btn-light border disabled">Completed locked</span>
                            <?php else: ?>
                            <a class="btn btn-sm btn-light border" href="appointments.php?<?php echo http_build_query(['print_appointment' => (int) ($row['Appointment_ID'] ?? 0), 'status' => $status]); ?>"><i class="ti ti-printer me-1"></i>Print Ticket</a>
                            <button class="btn btn-sm btn-light border" type="button" data-bs-toggle="modal" data-bs-target="#appointmentModal" data-appointment-mode="edit" data-appointment-id="<?php echo (int) ($row['Appointment_ID'] ?? 0); ?>" data-patient-id="<?php echo (int) ($row['Patient_ID'] ?? 0); ?>" data-doctor-id="<?php echo (int) ($row['Doctor_ID'] ?? 0); ?>" data-appointment-date="<?php echo clinic_h($row['Appointment_Date'] ?? ''); ?>" data-status="<?php echo clinic_h($appointmentStatus); ?>" title="Edit"><i class="ti ti-pencil"></i></button>
                            <form method="post" class="d-inline appointment-delete-form" data-patient-name="<?php echo clinic_h($row['Patient_Name'] ?? ''); ?>"><input type="hidden" name="csrf_token" value="<?php echo clinic_h(clinic_csrf_token()); ?>"><input type="hidden" name="action" value="delete_appointment"><input type="hidden" name="Appointment_ID" value="<?php echo (int) $row['Appointment_ID']; ?>"><input type="hidden" name="return_status" value="<?php echo clinic_h($status); ?>"><button class="btn btn-sm btn-outline-danger btn-icon" title="Delete"><i class="ti ti-trash"></i></button></form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php if ($appointments === []): ?><div class="alert alert-light border text-center mb-0">No appointments found.</div><?php endif; ?>
    </div>
</div><?php if ($printAppointment): ?>
<div id="appointmentTicketPrintArea">
    <div class="appointment-ticket">
        <div class="ticket-brand">
            <h3 class="mb-1">Clinic Appointment</h3>
            <div>Patient Queue Ticket</div>
        </div>
        <div class="ticket-queue">
            <small>Queue Number</small>
            <strong>#<?php echo str_pad((string) (int) ($printAppointment['Queue_No'] ?? 0), 3, '0', STR_PAD_LEFT); ?></strong>
        </div>
        <div class="ticket-row"><span>Patient</span><strong><?php echo clinic_h($printAppointment['Patient_Name'] ?? '-'); ?></strong></div>
        <div class="ticket-row"><span>Doctor</span><strong><?php echo clinic_h($printAppointment['Doctor_Name'] ?? 'No doctor'); ?></strong></div>
        <div class="ticket-row"><span>Date</span><strong><?php echo clinic_h($printAppointment['Appointment_Date'] ?? '-'); ?></strong></div>
        <div class="ticket-row"><span>Status</span><strong><?php echo clinic_h($printAppointment['Status'] ?? 'Pending'); ?></strong></div>
        <div class="ticket-row"><span>Ticket ID</span><strong>APT-<?php echo str_pad((string) (int) ($printAppointment['Appointment_ID'] ?? 0), 5, '0', STR_PAD_LEFT); ?></strong></div>
        <p class="small mt-3 mb-0">Please keep this ticket and wait for your queue number.</p>
    </div>
</div>
<?php endif; ?>

<div class="modal fade appointment-modal" id="appointmentModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <form method="post" class="modal-content">
            <div class="modal-header appointment-modal-header">
                <h5 class="modal-title" id="appointmentModalTitle">New Appointment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 bg-light">
                <input type="hidden" name="csrf_token" value="<?php echo clinic_h(clinic_csrf_token()); ?>">
                <input type="hidden" name="action" value="save_appointment">
                <input type="hidden" name="Appointment_ID" id="appointmentId" value="0">
                <input type="hidden" name="return_status" value="<?php echo clinic_h($status); ?>">
                <div class="appointment-form-card mb-3">
                    <div class="appointment-section-title">
                        <span><i class="ti ti-user-heart"></i></span>
                        Patient Details
                    </div>
                    <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
                        <label class="form-label mb-0">Search existing patient</label>
                        <button class="btn btn-sm btn-light border quick-patient-toggle" type="button" id="toggleQuickPatient">
                            <i class="ti ti-user-plus me-1"></i>New Patient?
                        </button>
                    </div>
                    <div class="row g-3">
                        <div class="col-12">
                            <select class="form-select" name="Patient_ID" id="appointmentPatientSelect">
                                <option value="">-- Select patient --</option>
                                <?php clinic_select_options($patients, 'Patient_ID', 'Full_Name'); ?>
                            </select>
                            <div class="form-text">If the patient is missing, use the quick-add section below.</div>
                        </div>
                        <div class="col-12">
                            <div class="quick-patient-box" id="quickPatientBox">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <div>
                                        <strong>Quick Add Walk-in Patient</strong>
                                        <div class="text-muted small">Only the basic information is needed for a same-day patient.</div>
                                    </div>
                                    <span class="badge text-bg-info">Walk-in</span>
                                </div>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Full name</label>
                                        <input class="form-control" name="Quick_Patient_Name" id="quickPatientName" placeholder="Patient full name">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Phone</label>
                                        <input class="form-control" name="Quick_Patient_Phone" placeholder="Phone number">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Sex</label>
                                        <select class="form-select" name="Quick_Sex">
                                            <option>Male</option>
                                            <option>Female</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Age</label>
                                        <select class="form-select" name="Quick_Age_Group">
                                            <option>Child</option>
                                            <option selected>Adult</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="appointment-form-card">
                    <div class="appointment-section-title">
                        <span><i class="ti ti-calendar-time"></i></span>
                        Appointment Details
                    </div>
                    <div class="alert alert-info border border-info py-2 mb-3">
                        Queue ticket is assigned by appointment date and starts again from <strong>#001</strong> every new day.
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Doctor</label>
                            <select class="form-select" name="Doctor_ID" id="appointmentDoctorSelect"><?php clinic_select_options($doctors, 'Doctor_ID', 'Full_Name'); ?></select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Date</label>
                            <input class="form-control" type="datetime-local" name="Appointment_Date" id="appointmentDateInput" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Status</label>
                            <input type="hidden" name="Status" id="appointmentStatusInput" value="Pending">
                            <div class="form-control d-flex align-items-center bg-white">
                                <span class="badge text-bg-warning" id="appointmentStatusBadge">Pending by default</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-danger" type="button" data-bs-dismiss="modal"><i class="ti ti-x me-1"></i>Cancel</button>
                <button class="btn btn-success" type="submit" id="appointmentBtnUpdate" style="display:none;"><i class="ti ti-edit me-1"></i>Update Appointment</button>
                <button class="btn btn-primary px-4" type="submit" id="appointmentSubmitButton"><i class="ti ti-device-floppy me-1"></i>Save Appointment</button>
            </div>
        </form>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var toggle = document.getElementById('toggleQuickPatient');
    var box = document.getElementById('quickPatientBox');
    var patientSelect = document.getElementById('appointmentPatientSelect');
    var quickName = document.getElementById('quickPatientName');
    var appointmentModal = document.getElementById('appointmentModal');
    var appointmentId = document.getElementById('appointmentId');
    var appointmentDoctor = document.getElementById('appointmentDoctorSelect');
    var appointmentDate = document.getElementById('appointmentDateInput');
    var appointmentStatus = document.getElementById('appointmentStatusInput');
    var appointmentStatusBadge = document.getElementById('appointmentStatusBadge');
    var appointmentTitle = document.getElementById('appointmentModalTitle');
    var appointmentSubtitle = document.getElementById('appointmentModalSubtitle');
    var appointmentSubmitButton = document.getElementById('appointmentSubmitButton');
    var shouldPrintAppointment = <?php echo $printAppointment ? 'true' : 'false'; ?>;
    var prefillPatientId = <?php echo $prefillPatientId > 0 ? $prefillPatientId : 0; ?>;
    var prefillDoctorId = <?php echo $prefillDoctorId > 0 ? $prefillDoctorId : 0; ?>;

    function syncQuickPatientState() {
        var isQuick = box.classList.contains('active');
        if (quickName) {
            quickName.required = isQuick;
        }
        if (patientSelect) {
            patientSelect.required = !isQuick;
            if (isQuick) {
                patientSelect.value = '';
                if (window.jQuery) {
                    jQuery(patientSelect).trigger('change');
                }
            }
        }
    }

    function triggerSelectChange(select) {
        if (select && window.jQuery) {
            jQuery(select).trigger('change');
        }
    }

    function formatDateForInput(value) {
        return (value || '').replace(' ', 'T').slice(0, 16);
    }

    function setStatusBadge(value, isEdit) {
        var statusClass = value === 'Completed' ? 'success' : (value === 'Cancelled' ? 'secondary' : 'warning');
        appointmentStatusBadge.className = 'badge text-bg-' + statusClass;
        appointmentStatusBadge.textContent = isEdit ? value : 'Pending by default';
    }

    if (toggle && box) {
        toggle.addEventListener('click', function () {
            box.classList.toggle('active');
            syncQuickPatientState();
        });
        syncQuickPatientState();
    }

    if (appointmentModal) {
        appointmentModal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            var mode = button ? (button.getAttribute('data-appointment-mode') || 'new') : 'new';
            var isEdit = mode === 'edit';
            var form = appointmentModal.querySelector('form');
            if (form) {
                form.reset();
            }

            appointmentId.value = isEdit ? (button.getAttribute('data-appointment-id') || '0') : '0';
            appointmentStatus.value = isEdit ? (button.getAttribute('data-status') || 'Pending') : 'Pending';
            appointmentTitle.textContent = isEdit ? 'Edit Appointment' : 'New Appointment';
            if (appointmentSubtitle) {
                appointmentSubtitle.textContent = isEdit ? 'Update patient, doctor, or appointment date.' : 'Book an appointment or quickly register a new Walk-in patient.';
            }
            var btnUpdate = document.getElementById('appointmentBtnUpdate');
            appointmentSubmitButton.style.display = isEdit ? 'none' : '';
            if (btnUpdate) { btnUpdate.style.display = isEdit ? '' : 'none'; }
            setStatusBadge(appointmentStatus.value, isEdit);

            if (box) {
                box.classList.remove('active');
            }
            if (toggle) {
                toggle.classList.toggle('d-none', isEdit);
            }

            if (isEdit) {
                patientSelect.value = button.getAttribute('data-patient-id') || '';
                appointmentDoctor.value = button.getAttribute('data-doctor-id') || '';
                appointmentDate.value = formatDateForInput(button.getAttribute('data-appointment-date') || '');
            } else if (prefillPatientId > 0) {
                patientSelect.value = String(prefillPatientId);
                if (toggle) {
                    toggle.classList.add('d-none');
                }
            } else if (prefillDoctorId > 0) {
                appointmentDoctor.value = String(prefillDoctorId);
            }

            triggerSelectChange(patientSelect);
            triggerSelectChange(appointmentDoctor);
            syncQuickPatientState();
        });

        if ((prefillPatientId > 0 || prefillDoctorId > 0) && window.bootstrap) {
            bootstrap.Modal.getOrCreateInstance(appointmentModal).show();
        }
    }

    document.querySelectorAll('.appointment-delete-form').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (this.dataset.confirmed === '1') {
                return;
            }
            event.preventDefault();
            var patientName = this.getAttribute('data-patient-name') || 'this appointment';
            if (window.Swal) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Delete appointment?',
                    text: 'Delete appointment for ' + patientName + '?',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, delete',
                    confirmButtonColor: '#dc3545'
                }).then((result) => {
                    if (result.isConfirmed) {
                        this.dataset.confirmed = '1';
                        this.submit();
                    }
                });
                return;
            }
        });
    });

    if (shouldPrintAppointment) {
        setTimeout(function () {
            document.body.classList.add('is-printing-appointment');
            window.print();
            if (window.history && window.history.replaceState) {
                var url = new URL(window.location.href);
                url.searchParams.delete('print_appointment');
                window.history.replaceState({}, document.title, url.toString());
            }
        }, 450);
        window.addEventListener('afterprint', function () {
            document.body.classList.remove('is-printing-appointment');
        });
    }
});
</script>
<?php clinic_page_end(); ?>
