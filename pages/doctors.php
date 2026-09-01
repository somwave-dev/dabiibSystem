<?php
require_once __DIR__ . '/../includes/advanced_components.php';
require_once __DIR__ . '/../includes/crud_page.php';

$doctorProfileId = (int) ($_GET['profile_id'] ?? 0);

if ($doctorProfileId > 0) {
    $doctor = clinic_sp_one('sp_doctors_profile', [$doctorProfileId], 'i');
    if ($doctor === null) {
        clinic_flash('Doctor not found.', 'danger');
        clinic_redirect('doctors.php');
    }
    $doctorAppointments = clinic_sp_rows('sp_doctor_appointments', [$doctorProfileId], 'i');

    clinic_page_start('Doctor Profile');

    $doctorName = (string) ($doctor['Full_Name'] ?? '');
    $doctorAvatar = (string) ($doctor['image'] ?? ($doctor['User_Image'] ?? ''));
    $specialization = (string) ($doctor['Specialization'] ?? '');
    $doctorFee = (float) ($doctor['Consultation_Fee'] ?? 0);
    $userEmail = (string) ($doctor['User_Email'] ?? '');
    $username = (string) ($doctor['Username'] ?? '');
    $appointmentCount = (int) ($doctor['appointment_count'] ?? 0);
    $visitCount = (int) ($doctor['visit_count'] ?? 0);
    $patientCount = (int) ($doctor['patient_count'] ?? 0);
    ?>
<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
    <h6 class="fw-bold mb-0 d-flex align-items-center">
        <a class="text-dark" href="doctors.php"><i class="ti ti-chevron-left me-1"></i>Doctors</a>
    </h6>
    <span class="badge text-bg-light border font-monospace">DOC-<?php echo str_pad((string) $doctorProfileId, 4, '0', STR_PAD_LEFT); ?></span>
</div>

<div class="card clinic-card mb-4">
    <div class="card-body d-flex align-items-center justify-content-between flex-wrap row-gap-3">
        <div class="d-flex align-items-center flex-sm-nowrap flex-wrap row-gap-3">
            <div class="me-3 flex-shrink-0"><?php echo clinic_avatar($doctorAvatar, $doctorName, 'clinic-avatar-xl'); ?></div>
            <div class="flex-fill">
                <div class="d-flex align-items-center mb-1 flex-wrap gap-2">
                    <h6 class="mb-0 fw-semibold"><?php echo clinic_h($doctorName); ?></h6>
                    <span class="badge border bg-white text-dark fw-medium"><i class="ti ti-point-filled me-1 text-info"></i><?php echo clinic_h($specialization ?: 'General'); ?></span>
                </div>
                <?php if ($username !== ''): ?><span class="d-block mb-3 fs-13 text-muted">Login account : <?php echo clinic_h($username); ?></span><?php endif; ?>
                <div class="d-flex align-items-center flex-wrap">
                    <p class="mb-0 fs-13"><i class="ti ti-building-hospital me-1"></i>Clinic : Dabiib System</p>
                    <span class="badge text-bg-success fw-medium ms-2"><i class="ti ti-point-filled me-1"></i>Active</span>
                </div>
            </div>
        </div>
        <div>
            <p class="mb-2">Consultation Charge</p>
            <h6 class="fs-18 fw-bold mb-3"><?php echo clinic_money($doctorFee); ?></h6>
            <a class="btn btn-primary" href="appointments.php?doctor_id=<?php echo (int) $doctorProfileId; ?>"><i class="ti ti-calendar-event me-1"></i>Book Appointment</a>
        </div>
    </div>
</div>
<div class="row g-3 mb-4">
    <?php clinic_metric_card('Appointments', $appointmentCount, 'ti-calendar-check', 'primary', 'Total booked'); ?>
    <?php clinic_metric_card('Visits', $visitCount, 'ti-stethoscope', 'info', 'Total consultations'); ?>
    <?php clinic_metric_card('Patients', $patientCount, 'ti-users', 'success', 'Unique patients seen'); ?>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card clinic-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="fw-bold mb-0"><i class="ti ti-calendar-event me-1"></i>Appointments</h5>
                <span class="badge text-bg-light"><?php echo count($doctorAppointments); ?> records</span>
            </div>
            <div class="card-body table-responsive">
                <table class="table table-hover align-middle clinic-table mb-0">
                    <thead>
                        <tr><th>Date &amp; Time</th><th>Patient</th><th>Status</th><th class="text-end">Actions</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($doctorAppointments as $ap): ?>
                        <tr>
                            <td><?php echo date('d M Y - h:i A', strtotime((string) ($ap['Appointment_Date'] ?? 'now'))); ?></td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <?php echo clinic_avatar('', (string) ($ap['Patient_Name'] ?? 'P'), 'clinic-avatar-sm'); ?>
                                    <strong><?php echo clinic_h($ap['Patient_Name'] ?? '-'); ?></strong>
                                </div>
                            </td>
                            <td><?php echo clinic_status_badge((string) ($ap['Status'] ?? 'Pending')); ?></td>
                            <td class="text-end">
                                <a class="btn btn-sm btn-light border btn-icon" title="Open appointment" href="appointments.php?status=all#app-<?php echo (int) ($ap['Appointment_ID'] ?? 0); ?>"><i class="ti ti-eye"></i></a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if ($doctorAppointments === []): ?><tr><td class="text-center text-muted py-4" colspan="4">No appointments found for this doctor.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="card clinic-card">
            <div class="card-header">
                <h5 class="fw-bold mb-0"><i class="ti ti-user-circle me-1"></i>About</h5>
            </div>
            <div class="card-body">
                <div class="d-flex align-items-center mb-3">
                    <span class="avatar rounded-circle bg-light text-dark fs-16 flex-shrink-0 me-2"><i class="ti ti-user-shield text-body"></i></span>
                    <div>
                        <h6 class="fw-semibold fs-13 mb-1">Specialization</h6>
                        <p class="mb-0"><?php echo clinic_h($specialization ?: '-'); ?></p>
                    </div>
                </div>
                <div class="d-flex align-items-center mb-3">
                    <span class="avatar rounded-circle bg-light text-dark fs-16 flex-shrink-0 me-2"><i class="ti ti-coin text-body"></i></span>
                    <div>
                        <h6 class="fw-semibold fs-13 mb-1">Consultation Fee</h6>
                        <p class="mb-0"><?php echo clinic_money($doctorFee); ?></p>
                    </div>
                </div>
                <div class="d-flex align-items-center mb-3">
                    <span class="avatar rounded-circle bg-light text-dark fs-16 flex-shrink-0 me-2"><i class="ti ti-mail text-body"></i></span>
                    <div>
                        <h6 class="fw-semibold fs-13 mb-1">Email Address</h6>
                        <p class="mb-0 text-break"><?php echo clinic_h($userEmail ?: '-'); ?></p>
                    </div>
                </div>
                <div class="d-flex align-items-center mb-3">
                    <span class="avatar rounded-circle bg-light text-dark fs-16 flex-shrink-0 me-2"><i class="ti ti-user text-body"></i></span>
                    <div>
                        <h6 class="fw-semibold fs-13 mb-1">Login Account</h6>
                        <p class="mb-0"><?php echo clinic_h($username ?: '-'); ?></p>
                    </div>
                </div>
                <div class="d-flex align-items-center mb-3">
                    <span class="avatar rounded-circle bg-light text-dark fs-16 flex-shrink-0 me-2"><i class="ti ti-calendar-check text-body"></i></span>
                    <div>
                        <h6 class="fw-semibold fs-13 mb-1">Appointments</h6>
                        <p class="mb-0"><?php echo $appointmentCount; ?></p>
                    </div>
                </div>
                <div class="d-flex align-items-center">
                    <span class="avatar rounded-circle bg-light text-dark fs-16 flex-shrink-0 me-2"><i class="ti ti-users text-body"></i></span>
                    <div>
                        <h6 class="fw-semibold fs-13 mb-1">Patients Seen</h6>
                        <p class="mb-0"><?php echo $patientCount; ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
    <?php
    clinic_page_end();
    exit;
}

clinic_render_crud_page('doctors');
