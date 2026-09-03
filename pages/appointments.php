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

function clinic_doctor_billing_user(int $doctorId): ?int
{
    if ($doctorId < 1) {
        return null;
    }
    global $conn;
    $uid = null;
    if ($conn instanceof mysqli) {
        $st = $conn->prepare('SELECT COALESCE(d.User_ID, s.User_ID) AS User_ID FROM doctors d LEFT JOIN staff s ON s.Staff_ID = d.Staff_ID WHERE d.Doctor_ID = ? LIMIT 1');
        $st->bind_param('i', $doctorId);
        $st->execute();
        $res = $st->get_result();
        if ($row = $res->fetch_assoc()) {
            $uid = (int) ($row['User_ID'] ?? 0) > 0 ? (int) $row['User_ID'] : null;
        }
        $st->close();
    }

    return $uid;
}

// Doctor data scope: a doctor only sees/manages his own appointments.
$apptIsDocScoped = clinic_is_doctor_scoped_user();
$apptDocScopeId = clinic_current_doctor_id();
$apptDocUnlinked = $apptIsDocScoped && $apptDocScopeId === null;

try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        clinic_check_csrf();
        $action = clinic_post_string('action');
        if ($action === 'save_appointment') {
            $appointmentId = clinic_post_int('Appointment_ID');
            clinic_require_can(3, $appointmentId > 0 ? 'update' : 'insert');
            if ($apptIsDocScoped && $appointmentId > 0) {
                clinic_ensure_own_appointment($appointmentId);
            }
            if ($apptIsDocScoped && $apptDocScopeId === null) {
                throw new RuntimeException('Your user is not linked to a doctor profile.');
            }
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

            $newDoctorId = clinic_post_int('Doctor_ID');
            if ($apptIsDocScoped) {
                // A doctor can only ever book/manage for his own profile.
                $newDoctorId = (int) $apptDocScopeId;
                if (!in_array($patientId, clinic_doctor_allowed_patient_ids($apptDocScopeId), true)) {
                    throw new RuntimeException('You can only book appointments for your own patients.');
                }
            }
            $needsVoid = false;
            if ($appointmentId > 0 && (string) ($existingAppointment['Status'] ?? '') === 'Pending') {
                $needsVoid = ((int) $newDoctorId !== (int) ($existingAppointment['Doctor_ID'] ?? 0)) || $appointmentStatus === 'Cancelled';
            }
            clinic_sp_exec('sp_appointments_save', [$appointmentId, $patientId, $newDoctorId, str_replace('T', ' ', clinic_post_string('Appointment_Date')), $appointmentStatus]);

            $newAppointmentId = 0;
            if ($appointmentId < 1 && ($result = $conn->query('SELECT LAST_INSERT_ID() AS Appointment_ID'))) {
                $row = $result->fetch_assoc();
                $newAppointmentId = (int) ($row['Appointment_ID'] ?? 0);
                $result->free();
            }

            // Billing: booking a slot charges the consultation fee right away;
            // cancelling (or switching the doctor) voids/refunds the old charge.
            $billingAppointmentId = $appointmentId > 0 ? $appointmentId : $newAppointmentId;
            if ($appointmentId > 0 && $needsVoid) {
                clinic_sp_exec('sp_void_appointment_charge', [$appointmentId, clinic_current_user_id()]);
            }
            if ($appointmentStatus !== 'Cancelled' && $billingAppointmentId > 0 && $patientId > 0 && $newDoctorId > 0) {
                clinic_sp_exec('sp_ensure_consultation_charge', [$patientId, $newDoctorId, clinic_doctor_billing_user($newDoctorId), 'Appointment', $billingAppointmentId]);
            }
            clinic_flash($appointmentId > 0 ? 'Appointment updated.' : 'Appointment saved.');
            if ($appointmentId > 0) {
                clinic_redirect('appointments.php' . clinic_appointment_return_query());
            }
            clinic_redirect($newAppointmentId > 0 ? 'appointments.php?print_appointment=' . $newAppointmentId : 'appointments.php');
        }
        if ($action === 'delete_appointment') {
            $appointmentId = clinic_post_int('Appointment_ID');
            clinic_require_can(3, 'delete');
            if ($appointmentId < 1) {
                throw new RuntimeException('Appointment was not found.');
            }
            if ($apptIsDocScoped) {
                clinic_ensure_own_appointment($appointmentId);
            }

            $existingAppointment = clinic_sp_one('sp_appointments_get', [$appointmentId], 'i');
            if (!$existingAppointment) {
                throw new RuntimeException('Appointment was not found.');
            }
            if ((string) ($existingAppointment['Status'] ?? '') === 'Completed') {
                throw new RuntimeException('Completed appointments cannot be deleted.');
            }

            // Delete = cancel: void/refund the booking fee that was charged.
            clinic_sp_exec('sp_void_appointment_charge', [$appointmentId, clinic_current_user_id()]);
            clinic_sp_exec('sp_appointments_delete', [$appointmentId], 'i');
            clinic_flash('Appointment deleted.');
            clinic_redirect('appointments.php' . clinic_appointment_return_query());
        }
        if ($action === 'set_status') {
            $row = clinic_sp_one('sp_appointments_get', [clinic_post_int('Appointment_ID')], 'i');
            if ($row) {
                if ($apptIsDocScoped) {
                    clinic_ensure_own_appointment((int) $row['Appointment_ID']);
                }
                $newStatus = clinic_post_string('Status');
                clinic_require_can(3, 'status');
                $statusActionMap = [
                    'confirmed'    => 'confirm',
                    'cancelled'    => 'cancel',
                    'started'      => 'start',
                    'in progress'  => 'in_progress',
                    'completed'    => 'complete',
                ];
                $statusActionKey = $statusActionMap[strtolower((string) $newStatus)] ?? '';
                if ($statusActionKey !== '' && (string) ($row['Status'] ?? '') !== (string) $newStatus) {
                    clinic_require_action(3, $statusActionKey);
                }
                clinic_sp_exec('sp_appointments_save', [(int) $row['Appointment_ID'], (int) $row['Patient_ID'], (int) $row['Doctor_ID'], (string) $row['Appointment_Date'], $newStatus]);

                // Billing: completing keeps the booking fee; cancelling refunds it.
                if ($newStatus === 'Completed' && (string) ($row['Status'] ?? '') !== 'Completed') {
                    $cPid = (int) ($row['Patient_ID'] ?? 0);
                    $cDid = (int) ($row['Doctor_ID'] ?? 0);
                    if ($cPid > 0 && $cDid > 0) {
                        clinic_sp_exec('sp_ensure_consultation_charge', [$cPid, $cDid, clinic_doctor_billing_user($cDid), 'Appointment', (int) $row['Appointment_ID']]);
                    }
                }
                if ($newStatus === 'Cancelled' && (string) ($row['Status'] ?? '') !== 'Cancelled') {
                    clinic_sp_exec('sp_void_appointment_charge', [(int) $row['Appointment_ID'], clinic_current_user_id()]);
                }
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

// Doctor scope: keep only rows that belong to the logged-in doctor.
if ($apptIsDocScoped) {
    if ($apptDocUnlinked) {
        $appointments = [];
        $patients = [];
        $doctors = [];
        clinic_flash('Your user is not linked to a doctor profile — linked doctors see only their own appointments.', 'warning');
    } else {
        $appointments = array_values(array_filter($appointments, static fn (array $r): bool => (int) ($r['Doctor_ID'] ?? 0) === $apptDocScopeId));
        $allowedPatientSet = array_flip(clinic_doctor_allowed_patient_ids($apptDocScopeId));
        $patients = array_values(array_filter($patients, static fn (array $p): bool => isset($allowedPatientSet[(int) ($p['Patient_ID'] ?? 0)])));
        $doctors = array_values(array_filter($doctors, static fn (array $d): bool => (int) ($d['Doctor_ID'] ?? 0) === $apptDocScopeId));
    }
}

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

// Billing enrichment: doctor consultation fee + patient balance + billed state.
$doctorFee = [];
foreach ($doctors as $doc) {
    $doctorFee[(int) ($doc['Doctor_ID'] ?? 0)] = (float) ($doc['Consultation_Fee'] ?? 0);
}
$patientBalance = [];
foreach (clinic_sp_rows('sp_patients_list') as $p) {
    $patientBalance[(int) ($p['Patient_ID'] ?? 0)] = (float) ($p['Current_Balance'] ?? 0);
}
$apptCharge = [];
if ($billRes = $conn->query("SELECT Source_ID, Amount, Paid_Amount FROM charges WHERE Source_Type = 'Appointment'")) {
    while ($billRow = $billRes->fetch_assoc()) {
        $apptCharge[(int) ($billRow['Source_ID'] ?? 0)] = $billRow;
    }
    $billRes->free();
}
foreach ($appointments as &$appt) {
    $appt['Consultation_Fee'] = $doctorFee[(int) ($appt['Doctor_ID'] ?? 0)] ?? 0.0;
    $appt['Patient_Balance'] = $patientBalance[(int) ($appt['Patient_ID'] ?? 0)] ?? 0.0;
    $appt['Bill'] = $apptCharge[(int) ($appt['Appointment_ID'] ?? 0)] ?? null;
}
unset($appt);

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

    /* ---- Appointments board view ---- */
    .appt-view-switch .btn.active {
        background: var(--primary, #4f6df5);
        border-color: var(--primary, #4f6df5);
        color: #fff;
    }
    #apptBoard {
        display: none;
        gap: 1rem;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        align-items: start;
    }
    body.appt-mode-board #apptBoard {
        display: grid;
    }
    body.appt-mode-board .appt-list-area {
        display: none !important;
    }
    .appt-board-col {
        background: var(--white, #fff);
        border: 1px solid var(--border-color, #e2e8f0);
        border-radius: 16px;
        overflow: hidden;
        display: flex;
        flex-direction: column;
    }
    .appt-board-col-head {
        padding: .7rem 1rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: .5rem;
        font-weight: 700;
        font-size: .8rem;
        letter-spacing: .03em;
        border-bottom: 1px solid var(--border-color, #e2e8f0);
        text-transform: uppercase;
    }
    .appt-board-col-head.pending { background: rgba(255, 193, 7, .14); color: #8a6d00; }
    .appt-board-col-head.completed { background: rgba(25, 135, 84, .12); color: #146c43; }
    .appt-board-col-head.cancelled { background: rgba(108, 117, 125, .12); color: #565e64; }
    [data-bs-theme="dark"] .appt-board-col-head.pending { color: #ffca2c; }
    [data-bs-theme="dark"] .appt-board-col-head.completed { color: #75c798; }
    [data-bs-theme="dark"] .appt-board-col-head.cancelled { color: #a8b0bd; }
    .appt-board-col-body {
        padding: .75rem;
        display: flex;
        flex-direction: column;
        gap: .75rem;
        max-height: 70vh;
        overflow: auto;
    }
    .appt-board-card {
        background: var(--white, #fff);
        border: 1px solid var(--border-color, #e2e8f0);
        border-left: 4px solid #adb5bd;
        border-radius: 12px;
        padding: .7rem .8rem;
        box-shadow: var(--box-shadow-sm, 0 4px 12px rgba(0,0,0,.06));
    }
    .appt-board-card.is-pending { border-left-color: #ffc107; }
    .appt-board-card.is-completed { border-left-color: #198754; }
    .appt-board-card.is-cancelled { border-left-color: #6c757d; }
    .appt-board-time {
        font-size: .72rem;
        font-weight: 700;
        letter-spacing: .04em;
        text-transform: uppercase;
    }
</style>
<div class="appointment-toolbar">
    <?php
    $statusCounts = ['all' => 0, 'Pending' => 0, 'Completed' => 0, 'Cancelled' => 0];
    foreach ($allAppointments as $__a) {
        $__s = (string) ($__a['Status'] ?? 'Pending');
        $statusCounts['all']++;
        if (isset($statusCounts[$__s])) {
            $statusCounts[$__s]++;
        }
    }
    ?>
    <div class="btn-group btn-group-sm">
        <a class="btn btn-<?php echo $status === '' ? 'primary' : 'light border'; ?>" href="appointments.php">All <span class="badge rounded-pill ms-1 <?php echo $status === '' ? 'text-bg-light' : 'text-bg-secondary'; ?>"><?php echo (int) $statusCounts['all']; ?></span></a>
        <a class="btn btn-<?php echo $status === 'Pending' ? 'primary' : 'light border'; ?>" href="appointments.php?status=Pending">Pending <span class="badge rounded-pill ms-1 <?php echo $status === 'Pending' ? 'text-bg-light' : 'text-bg-warning'; ?>"><?php echo (int) $statusCounts['Pending']; ?></span></a>
        <a class="btn btn-<?php echo $status === 'Completed' ? 'primary' : 'light border'; ?>" href="appointments.php?status=Completed">Completed <span class="badge rounded-pill ms-1 <?php echo $status === 'Completed' ? 'text-bg-light' : 'text-bg-success'; ?>"><?php echo (int) $statusCounts['Completed']; ?></span></a>
        <a class="btn btn-<?php echo $status === 'Cancelled' ? 'primary' : 'light border'; ?>" href="appointments.php?status=Cancelled">Cancelled <span class="badge rounded-pill ms-1 <?php echo $status === 'Cancelled' ? 'text-bg-light' : 'text-bg-secondary'; ?>"><?php echo (int) $statusCounts['Cancelled']; ?></span></a>
    </div>
    <div class="appointment-toolbar-actions d-flex flex-wrap align-items-center gap-2">
        <div class="btn-group appt-view-switch" role="group" aria-label="View">
            <button type="button" class="btn btn-sm btn-light border active" id="apptViewListBtn" title="List view"><i class="ti ti-list me-1"></i>List</button>
            <button type="button" class="btn btn-sm btn-light border" id="apptViewBoardBtn" title="Board view"><i class="ti ti-layout-kanban me-1"></i>Board</button>
        </div>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#appointmentModal" data-appointment-mode="new"><i class="ti ti-calendar-plus me-1"></i>New Appointment</button>
    </div>
</div>

<div class="row g-3 mb-3 appt-list-area" id="apptQueueRow">
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

<div class="card clinic-card appt-list-area" id="apptListCard">
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
                    <td>
                        <?php echo clinic_h($row['Doctor_Name'] ?? '-'); ?>
                        <?php if ((float) ($row['Consultation_Fee'] ?? 0) > 0): ?>
                        <div class="small text-muted">Fee <?php echo clinic_money((float) $row['Consultation_Fee']); ?></div>
                        <?php endif; ?>
                    </td>
                    <td><?php echo clinic_h($row['Appointment_Date'] ?? '-'); ?></td>
                    <td>
                        <?php echo clinic_status_badge($appointmentStatus); ?>
                        <?php
                        $billRow = $row['Bill'] ?? null;
                        if ($appointmentStatus === 'Completed' && is_array($billRow)):
                            $bDue = (float) ($billRow['Amount'] ?? 0) - (float) ($billRow['Paid_Amount'] ?? 0);
                        ?>
                        <span class="badge d-block mt-1 <?php echo $bDue > 0 ? 'text-bg-danger' : 'text-bg-success'; ?>"><?php echo $bDue > 0 ? 'Fee unpaid' : 'Fee paid'; ?></span>
                        <?php endif; ?>
                    </td>
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
<?php
$boardGroups = ['Pending' => [], 'Completed' => [], 'Cancelled' => []];
foreach ($allAppointments as $__a) {
    $__s = (string) ($__a['Status'] ?? 'Pending');
    if (isset($boardGroups[$__s])) {
        $boardGroups[$__s][] = $__a;
    }
}
$boardCols = [
    'Pending' => ['pending', 'ti-clock', 'warning'],
    'Completed' => ['completed', 'ti-circle-check', 'success'],
    'Cancelled' => ['cancelled', 'ti-circle-x', 'secondary'],
];
?>
<div id="apptBoard">
    <?php foreach ($boardCols as $__st => $__col): ?>
    <div class="appt-board-col">
        <div class="appt-board-col-head <?php echo $__col[0]; ?>">
            <span><i class="ti <?php echo $__col[1]; ?> me-1"></i><?php echo $__st; ?></span>
            <span class="badge rounded-pill text-bg-<?php echo $__col[2]; ?>"><?php echo count($boardGroups[$__st]); ?></span>
        </div>
        <div class="appt-board-col-body">
            <?php if ($boardGroups[$__st] === []): ?>
                <p class="text-muted small text-center mb-0 py-3">No <?php echo strtolower($__st); ?> appointments.</p>
            <?php endif; ?>
            <?php foreach ($boardGroups[$__st] as $bc): ?>
                <?php
                $bcSt = strtolower($__st);
                $bcBill = $bc['Bill'] ?? null;
                $bcDue = is_array($bcBill) ? (float) ($bcBill['Amount'] ?? 0) - (float) ($bcBill['Paid_Amount'] ?? 0) : null;
                ?>
                <div class="appt-board-card is-<?php echo $bcSt; ?>">
                    <div class="d-flex justify-content-between align-items-center gap-2 mb-1">
                        <span class="appt-board-time">
                            <?php if ((string) ($bc['Queue_Day'] ?? '') === $todayKey): ?>#<?php echo str_pad((string) (int) ($bc['Queue_No'] ?? 0), 3, '0', STR_PAD_LEFT); ?> ·<?php endif; ?>
                            <?php echo clinic_h(substr((string) ($bc['Appointment_Date'] ?? ''), 11, 5)); ?>
                        </span>
                        <span class="small text-muted">APT-<?php echo str_pad((string) (int) ($bc['Appointment_ID'] ?? 0), 5, '0', STR_PAD_LEFT); ?></span>
                    </div>
                    <div class="fw-semibold text-truncate"><?php echo clinic_h($bc['Patient_Name'] ?? '-'); ?></div>
                    <div class="small text-muted mb-2 text-truncate">
                        <i class="ti ti-stethoscope me-1"></i><?php echo clinic_h($bc['Doctor_Name'] ?? 'No doctor'); ?>
                        <?php if ((float) ($bc['Consultation_Fee'] ?? 0) > 0): ?> · Fee <?php echo clinic_money((float) $bc['Consultation_Fee']); ?><?php endif; ?>
                    </div>
                    <?php if ($__st === 'Completed' && $bcDue !== null): ?>
                    <span class="badge mb-2 <?php echo $bcDue > 0 ? 'text-bg-danger' : 'text-bg-success'; ?>"><?php echo $bcDue > 0 ? 'Fee unpaid' : 'Fee paid'; ?></span>
                    <?php endif; ?>
                    <div class="d-flex flex-wrap gap-1 mt-1">
                        <?php if ($__st === 'Pending'): ?>
                        <a class="btn btn-sm btn-primary" href="visits.php?patient_id=<?php echo (int) ($bc['Patient_ID'] ?? 0); ?>&appointment_id=<?php echo (int) ($bc['Appointment_ID'] ?? 0); ?>"><i class="ti ti-stethoscope me-1"></i>Start</a>
                        <form method="post" class="d-inline">
                            <input type="hidden" name="csrf_token" value="<?php echo clinic_h(clinic_csrf_token()); ?>">
                            <input type="hidden" name="action" value="set_status">
                            <input type="hidden" name="Appointment_ID" value="<?php echo (int) $bc['Appointment_ID']; ?>">
                            <input type="hidden" name="Status" value="Completed">
                            <input type="hidden" name="return_status" value="<?php echo clinic_h($status); ?>">
                            <button class="btn btn-sm btn-outline-success" title="Complete"><i class="ti ti-check"></i></button>
                        </form>
                        <form method="post" class="d-inline">
                            <input type="hidden" name="csrf_token" value="<?php echo clinic_h(clinic_csrf_token()); ?>">
                            <input type="hidden" name="action" value="set_status">
                            <input type="hidden" name="Appointment_ID" value="<?php echo (int) $bc['Appointment_ID']; ?>">
                            <input type="hidden" name="Status" value="Cancelled">
                            <input type="hidden" name="return_status" value="<?php echo clinic_h($status); ?>">
                            <button class="btn btn-sm btn-outline-secondary" title="Cancel"><i class="ti ti-x"></i></button>
                        </form>
                        <?php endif; ?>
                        <a class="btn btn-sm btn-light border" title="Print ticket" href="appointments.php?print_appointment=<?php echo (int) ($bc['Appointment_ID'] ?? 0); ?>"><i class="ti ti-printer"></i></a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<script>
(function () {
    var boardBtn = document.getElementById('apptViewBoardBtn');
    var listBtn = document.getElementById('apptViewListBtn');
    if (!boardBtn || !listBtn) { return; }
    function setView(board) {
        document.body.classList.toggle('appt-mode-board', board);
        listBtn.classList.toggle('active', !board);
        boardBtn.classList.toggle('active', board);
    }
    boardBtn.addEventListener('click', function () { setView(true); });
    listBtn.addEventListener('click', function () { setView(false); });
})();
</script>
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
