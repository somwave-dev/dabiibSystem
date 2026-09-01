<?php
require_once __DIR__ . '/../includes/advanced_components.php';

try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        clinic_check_csrf();
        $action = clinic_post_string('action');

        if ($action === 'save_patient') {
            $image = clinic_post_string('Patient_Image_Current');
            if (!empty($_FILES['Patient_Image']) && (int) ($_FILES['Patient_Image']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                $image = clinic_handle_avatar_upload('Patient_Image');
            } elseif (clinic_post_string('Patient_Image_Remove') !== '') {
                $image = '';
            }
            clinic_sp_exec('sp_patients_save', [
                clinic_post_int('Patient_ID'),
                clinic_post_string('Full_Name'),
                clinic_post_string('Phone_Number'),
                clinic_post_string('Sex') ?: 'Male',
                clinic_post_string('Age_Group') ?: 'Adult',
                clinic_post_string('Patient_Type') ?: 'Maalinle',
                clinic_post_int('Guarantor_ID') ?: null,
                clinic_post_string('Relationship') ?: 'Self',
                clinic_post_float('Credit_Limit'),
                clinic_post_float('Current_Balance'),
                $image,
            ]);
            clinic_flash('Patient saved successfully.');
            clinic_redirect('patients.php');
        }

        if ($action === 'delete_patient') {
            clinic_sp_exec('sp_patients_delete', [clinic_post_int('Patient_ID')], 'i');
            clinic_flash('Patient deleted successfully.');
            clinic_redirect('patients.php');
        }

        if ($action === 'start_visit') {
            $row = clinic_sp_one('sp_create_visit_with_actions', [
                clinic_post_int('Patient_ID'),
                clinic_post_int('Doctor_ID'),
                clinic_post_string('Notes'),
                0,
            ]);
            clinic_flash('Visit started for patient.');
            clinic_redirect('visits.php?visit_id=' . (int) ($row['Visit_ID'] ?? 0));
        }

    }
} catch (Throwable $e) {
    clinic_flash($e->getMessage(), 'danger');
    clinic_redirect('patients.php');
}

$patients = clinic_sp_rows('sp_patients_list');
$doctors = clinic_sp_rows('sp_doctors_list');
$profileId = (int) ($_GET['profile_id'] ?? 0);
$profile = $profileId > 0 ? clinic_sp_one('sp_patient_profile', [$profileId], 'i') : null;
$timeline = $profileId > 0 ? clinic_sp_rows('sp_patient_timeline', [$profileId], 'i') : [];
$profileAppointments = $profileId > 0 ? clinic_sp_rows('sp_patient_appointments', [$profileId], 'i') : [];
$profilePayments = $profileId > 0 ? clinic_sp_rows('sp_patient_payments', [$profileId], 'i') : [];
$typeFilter = (string) ($_GET['type'] ?? '');
$search = trim((string) ($_GET['q'] ?? ''));

if ($typeFilter !== '') {
    $patients = array_values(array_filter($patients, static fn ($row) => ($row['Patient_Type'] ?? '') === $typeFilter));
}
if ($search !== '') {
    $patients = array_values(array_filter($patients, static function ($row) use ($search) {
        return stripos((string) ($row['Full_Name'] ?? ''), $search) !== false
            || stripos((string) ($row['Phone_Number'] ?? ''), $search) !== false;
    }));
}

clinic_page_start('Patient Desk');

$totalPatients = count($patients);
$totalBalance = array_sum(array_map(static fn ($p) => (float) ($p['Current_Balance'] ?? 0), $patients));
$billeCount = count(array_filter($patients, static fn ($p) => ($p['Patient_Type'] ?? '') === 'Bille'));
$maalinleCount = $totalPatients - $billeCount;
$debtors = count(array_filter($patients, static fn ($p) => (float) ($p['Current_Balance'] ?? 0) > 0));

$profileCloseUrl = 'patients.php?' . http_build_query(['q' => $search, 'type' => $typeFilter]);

if ($profile):
$profileBalance = (float) ($profile['Current_Balance'] ?? 0);
?>
<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
    <h6 class="fw-bold mb-0 d-flex align-items-center">
        <a class="text-dark" href="<?php echo clinic_h($profileCloseUrl); ?>"><i class="ti ti-chevron-left me-1"></i>Patients</a>
    </h6>
    <span class="badge text-bg-light border font-monospace">#PT<?php echo str_pad((string) (int) ($profile['Patient_ID'] ?? 0), 4, '0', STR_PAD_LEFT); ?></span>
</div>

<div class="card clinic-card mb-4">
    <div class="row align-items-end">
        <div class="col-xl-9 col-lg-8">
            <div class="d-sm-flex align-items-center p-3">
                <span class="me-3 flex-shrink-0"><?php echo clinic_avatar($profile['image'] ?? '', $profile['Full_Name'] ?? 'P', 'clinic-avatar-lg'); ?></span>
                <div>
                    <p class="text-primary mb-1 fw-semibold font-monospace">#PT<?php echo str_pad((string) (int) ($profile['Patient_ID'] ?? 0), 4, '0', STR_PAD_LEFT); ?></p>
                    <h4 class="fw-bold mb-1"><?php echo clinic_h($profile['Full_Name'] ?? '-'); ?></h4>
                    <div class="d-flex align-items-center flex-wrap">
                        <p class="mb-0 d-inline-flex align-items-center"><i class="ti ti-phone me-1 text-body"></i>Phone : <span class="text-body fw-semibold ms-1"><?php echo clinic_h($profile['Phone_Number'] ?? '-'); ?></span></p>
                        <span class="mx-2 text-muted">|</span>
                        <p class="mb-0 d-inline-flex align-items-center"><i class="ti ti-calendar-time me-1 text-body"></i>Last Visited : <span class="text-body fw-semibold ms-1"><?php echo clinic_h($profile['last_visit_date'] ?: '-'); ?></span></p>
                        <span class="mx-2 text-muted">|</span>
                        <p class="mb-0 d-inline-flex align-items-center"><i class="ti ti-wallet me-1 text-body"></i>Balance : <span class="<?php echo $profileBalance > 0 ? 'text-danger' : 'text-success'; ?> fw-semibold ms-1"><?php echo clinic_money($profileBalance); ?></span></p>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-lg-4">
            <div class="p-3 text-lg-end">
                <div class="d-flex flex-wrap gap-2 justify-content-lg-end mb-2">
                    <button class="btn btn-light border btn-icon" type="button" title="Edit" data-bs-toggle="modal" data-bs-target="#patientModal" data-patient-mode="edit"
                        data-patient-id="<?php echo (int) $profile['Patient_ID']; ?>"
                        data-full-name="<?php echo clinic_h($profile['Full_Name'] ?? ''); ?>"
                        data-phone="<?php echo clinic_h($profile['Phone_Number'] ?? ''); ?>"
                        data-sex="<?php echo clinic_h($profile['Sex'] ?? 'Male'); ?>"
                        data-age-group="<?php echo clinic_h($profile['Age_Group'] ?? 'Adult'); ?>"
                        data-patient-type="<?php echo clinic_h($profile['Patient_Type'] ?? 'Maalinle'); ?>"
                        data-guarantor-id="<?php echo (int) ($profile['Guarantor_ID'] ?? 0); ?>"
                        data-relationship="<?php echo clinic_h($profile['Relationship'] ?? 'Self'); ?>"
                        data-credit-limit="<?php echo clinic_h($profile['Credit_Limit'] ?? 0); ?>"
                        data-current-balance="<?php echo clinic_h($profile['Current_Balance'] ?? 0); ?>"
                        data-image="<?php echo clinic_h($profile['image'] ?? ''); ?>"
                    ><i class="ti ti-edit"></i></button>
                    <button class="btn btn-light border btn-icon" type="button" title="Start Visit" data-bs-toggle="modal" data-bs-target="#visitModal" data-patient="<?php echo (int) $profile['Patient_ID']; ?>" data-name="<?php echo clinic_h($profile['Full_Name'] ?? ''); ?>"><i class="ti ti-stethoscope"></i></button>
                    <form method="post" class="d-inline patient-delete-form" data-patient-name="<?php echo clinic_h($profile['Full_Name'] ?? ''); ?>">
                        <input type="hidden" name="csrf_token" value="<?php echo clinic_h(clinic_csrf_token()); ?>">
                        <input type="hidden" name="action" value="delete_patient">
                        <input type="hidden" name="Patient_ID" value="<?php echo (int) $profile['Patient_ID']; ?>">
                        <button class="btn btn-outline-danger btn-icon" type="submit" title="Delete"><i class="ti ti-trash"></i></button>
                    </form>
                </div>
                <a class="btn btn-primary" href="appointments.php?patient_id=<?php echo (int) $profile['Patient_ID']; ?>"><i class="ti ti-calendar-plus me-1"></i>Book Appointment</a>
            </div>
        </div>
    </div>
</div>
<div class="row g-3 mb-4">
    <div class="col-xl-5 d-flex">
        <div class="card clinic-card flex-fill w-100">
            <div class="card-header">
                <h5 class="fw-bold mb-0"><i class="ti ti-user-star me-1"></i>About</h5>
            </div>
            <div class="card-body pb-0">
                <div class="row">
                    <div class="col-sm-6 patient-info-item"><span class="patient-info-icon"><i class="ti ti-calendar-event"></i></span><div><div class="small text-muted">Age Group</div><strong><?php echo clinic_h($profile['Age_Group'] ?? '-'); ?></strong></div></div>
                    <div class="col-sm-6 patient-info-item"><span class="patient-info-icon"><i class="ti ti-droplet"></i></span><div><div class="small text-muted">Patient Type</div><strong><?php echo clinic_h($profile['Patient_Type'] ?? '-'); ?></strong></div></div>
                    <div class="col-sm-6 patient-info-item"><span class="patient-info-icon"><i class="ti ti-gender-bigender"></i></span><div><div class="small text-muted">Gender</div><strong><?php echo clinic_h($profile['Sex'] ?? '-'); ?></strong></div></div>
                    <div class="col-sm-6 patient-info-item"><span class="patient-info-icon"><i class="ti ti-phone"></i></span><div><div class="small text-muted">Phone</div><strong><?php echo clinic_h($profile['Phone_Number'] ?? '-'); ?></strong></div></div>
                    <div class="col-sm-6 patient-info-item"><span class="patient-info-icon"><i class="ti ti-users"></i></span><div><div class="small text-muted">Relationship</div><strong><?php echo clinic_h($profile['Relationship'] ?? '-'); ?></strong></div></div>
                    <div class="col-sm-6 patient-info-item"><span class="patient-info-icon"><i class="ti ti-user-shield"></i></span><div><div class="small text-muted">Guarantor</div><strong><?php echo clinic_h($profile['Guarantor_Name'] ?? '-'); ?></strong></div></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-7 d-flex">
        <div class="card clinic-card flex-fill w-100">
            <div class="card-header">
                <h5 class="fw-bold mb-0"><i class="ti ti-activity me-1"></i>Clinical &amp; Billing</h5>
            </div>
            <div class="card-body pb-0">
                <div class="row">
                    <div class="col-sm-4 patient-info-item"><span class="patient-info-icon"><i class="ti ti-stethoscope"></i></span><div><div class="small text-muted">Visits</div><strong><?php echo (int) ($profile['visit_count'] ?? 0); ?></strong></div></div>
                    <div class="col-sm-4 patient-info-item"><span class="patient-info-icon"><i class="ti ti-calendar-check"></i></span><div><div class="small text-muted">Appointments</div><strong><?php echo (int) ($profile['appointment_count'] ?? 0); ?></strong></div></div>
                    <div class="col-sm-4 patient-info-item"><span class="patient-info-icon"><i class="ti ti-cards"></i></span><div><div class="small text-muted">Payments</div><strong><?php echo (int) ($profile['payment_count'] ?? 0); ?></strong></div></div>
                    <div class="col-sm-4 patient-info-item"><span class="patient-info-icon"><i class="ti ti-currency-dollar"></i></span><div><div class="small text-muted">Total Paid</div><strong><?php echo clinic_money($profile['total_paid'] ?? 0); ?></strong></div></div>
                    <div class="col-sm-4 patient-info-item"><span class="patient-info-icon"><i class="ti ti-credit-card"></i></span><div><div class="small text-muted">Credit Limit</div><strong><?php echo clinic_money($profile['Credit_Limit'] ?? 0); ?></strong></div></div>
                    <div class="col-sm-4 patient-info-item"><span class="patient-info-icon"><i class="ti ti-wallet"></i></span><div><div class="small text-muted">Current Balance</div><strong class="<?php echo $profileBalance > 0 ? 'text-danger' : 'text-success'; ?>"><?php echo clinic_money($profileBalance); ?></strong></div></div>
                </div>
            </div>
        </div>
    </div>
</div>
<ul class="nav nav-tabs patient-profile-tabs mb-3" role="tablist">
    <li class="nav-item" role="presentation"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#profileAppointments" type="button">Appointments</button></li>
    <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#profileTransactions" type="button">Transactions</button></li>
</ul>
<div class="tab-content">
    <div class="tab-pane fade show active" id="profileAppointments">
        <div class="card clinic-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="fw-bold mb-0"><i class="ti ti-calendar-event me-1"></i>Appointments</h5>
                <span class="badge text-bg-light"><?php echo count($profileAppointments); ?> records</span>
            </div>
            <div class="card-body table-responsive">
                <table class="table table-hover align-middle clinic-table mb-0">
                    <thead>
                        <tr><th>Date &amp; Time</th><th>Doctor</th><th>Status</th><th class="text-end">Actions</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($profileAppointments as $ap): ?>
                        <tr>
                            <td><?php echo date('d M Y - h:i A', strtotime((string) ($ap['Appointment_Date'] ?? 'now'))); ?></td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="patient-table-avatar"><?php echo clinic_h(substr((string) ($ap['Doctor_Name'] ?? 'D'), 0, 1)); ?></span>
                                    <div>
                                        <strong><?php echo clinic_h($ap['Doctor_Name'] ?? '-'); ?></strong>
                                        <div class="small text-muted"><?php echo clinic_h($ap['Doctor_Specialization'] ?? ''); ?></div>
                                    </div>
                                </div>
                            </td>
                            <td><?php echo clinic_status_badge((string) ($ap['Status'] ?? 'Pending')); ?></td>
                            <td class="text-end">
                                <a class="btn btn-sm btn-light border btn-icon" title="Open appointment" href="appointments.php?status=all#app-<?php echo (int) ($ap['Appointment_ID'] ?? 0); ?>"><i class="ti ti-eye"></i></a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if ($profileAppointments === []): ?><tr><td class="text-center text-muted py-4" colspan="4">No appointments found for this patient.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="tab-pane fade" id="profileTransactions">
        <div class="card clinic-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="fw-bold mb-0"><i class="ti ti-receipt-2 me-1"></i>Transactions</h5>
                <span class="badge text-bg-light"><?php echo count($profilePayments); ?> records</span>
            </div>
            <div class="card-body table-responsive">
                <table class="table table-hover align-middle clinic-table mb-0">
                    <thead>
                        <tr><th>Transaction</th><th>Description</th><th>Paid Date</th><th>Method</th><th class="text-end">Amount</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($profilePayments as $py): ?>
                        <tr>
                            <td><span class="badge text-bg-light border font-monospace">#TXN-<?php echo str_pad((string) (int) ($py['Payment_ID'] ?? 0), 4, '0', STR_PAD_LEFT); ?></span></td>
                            <td><?php echo clinic_h($py['Account_Name'] ?? 'Payment'); ?></td>
                            <td><?php echo date('d M Y', strtotime((string) ($py['Payment_Date'] ?? 'now'))); ?></td>
                            <td><?php echo clinic_h($py['Payment_Method'] ?? '-'); ?></td>
                            <td class="text-end fw-semibold"><?php echo clinic_money($py['Amount'] ?? 0); ?></td>
                            <td><span class="badge text-bg-success">Paid</span></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if ($profilePayments === []): ?><tr><td class="text-center text-muted py-4" colspan="6">No transactions found for this patient.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php else: ?>
<div class="row g-3 mb-4">
    <?php clinic_metric_card('Total Patients', $totalPatients, 'ti-users', 'primary', 'All registered'); ?>
    <?php clinic_metric_card('Maalinle', $maalinleCount, 'ti-user', 'info', 'Outpatient / walk-in'); ?>
    <?php clinic_metric_card('Bille (Credit)', $billeCount, 'ti-wallet', 'warning', 'On-credit patients'); ?>
    <?php clinic_metric_card('Balance Owed', clinic_money($totalBalance), 'ti-currency-dollar', 'danger', $debtors . ' debtor(s)'); ?>
</div>
<style>
    .patient-toolbar {
        align-items: center;
        background: var(--white);
        border: 1px solid var(--border-color);
        border-radius: 16px;
        box-shadow: var(--box-shadow-sm);
        display: flex;
        gap: .75rem;
        justify-content: space-between;
        margin-bottom: 1rem;
        padding: .75rem;
    }
    .patient-filter-form {
        display: grid;
        flex: 1;
        gap: .5rem;
        grid-template-columns: minmax(260px, 1fr) 170px auto;
    }
    .patient-toolbar-actions {
        align-items: center;
        display: flex;
        flex-shrink: 0;
        gap: .5rem;
    }
    .patient-table-avatar {
        align-items: center;
        background: var(--primary-transparent);
        border-radius: 50%;
        color: var(--primary);
        display: inline-flex;
        font-weight: 800;
        height: 34px;
        justify-content: center;
        width: 34px;
    }
    .patient-card {
        border: 1px solid var(--border-color);
        border-radius: 18px;
        box-shadow: var(--box-shadow-sm);
        min-height: 154px;
        transition: transform .12s, box-shadow .12s;
    }
    .patient-card:hover {
        box-shadow: var(--box-shadow);
        transform: translateY(-2px);
    }
    .patient-card-actions {
        display: flex;
        flex-wrap: wrap;
        gap: .4rem;
    }
    .patient-card-actions .btn {
        align-items: center;
        display: inline-flex;
        justify-content: center;
        min-width: 62px;
        padding-inline: .6rem;
    }
    @media (max-width: 991.98px) {
        .patient-toolbar {
            align-items: stretch;
            flex-direction: column;
        }
        .patient-filter-form {
            grid-template-columns: 1fr;
        }
        .patient-toolbar-actions {
            justify-content: space-between;
        }
    }
    .patient-profile-modal .modal-content {
        background: var(--light);
        border: 0;
    }
    .patient-profile-hero {
        background: var(--white);
        border: 1px solid var(--border-color);
        border-radius: 16px;
        overflow: hidden;
        position: relative;
    }
    .patient-profile-hero::after {
        background: linear-gradient(135deg, rgba(var(--primary-rgb), .28), var(--primary-transparent));
        clip-path: polygon(35% 0, 100% 0, 78% 100%, 58% 100%);
        content: "";
        inset: 0 0 0 auto;
        position: absolute;
        width: 46%;
    }
    .patient-profile-photo {
        align-items: center;
        background: var(--primary-transparent);
        border-radius: 12px;
        color: var(--primary);
        display: inline-flex;
        font-size: 2rem;
        font-weight: 900;
        height: 96px;
        justify-content: center;
        width: 96px;
    }
    .patient-profile-hero-content {
        position: relative;
        z-index: 1;
    }
    .patient-info-card {
        background: var(--white);
        border: 1px solid var(--border-color);
        border-radius: 14px;
        box-shadow: var(--box-shadow-sm);
    }
    .patient-info-item {
        align-items: center;
        display: flex;
        gap: .75rem;
        padding: .65rem 0;
    }
    .patient-info-icon {
        align-items: center;
        background: var(--light);
        border-radius: 10px;
        color: var(--primary);
        display: inline-flex;
        height: 36px;
        justify-content: center;
        width: 36px;
    }
    .patient-profile-tabs .nav-link {
        border: 0;
        color: var(--heading-color);
        font-weight: 700;
        padding-inline: 0;
        margin-right: 1.5rem;
    }
    .patient-profile-tabs .nav-link.active {
        background: transparent;
        border-bottom: 2px solid var(--primary);
        color: var(--primary);
    }
</style>
<div class="patient-toolbar">
    <form class="patient-filter-form" method="get">

        <input class="form-control" type="search" name="q" value="<?php echo clinic_h($search); ?>" placeholder="Search patient or phone">
        <select class="form-select" name="type">
            <option value="">All types</option>
            <option value="Bille"<?php echo $typeFilter === 'Bille' ? ' selected' : ''; ?>>Bille</option>
            <option value="Maalinle"<?php echo $typeFilter === 'Maalinle' ? ' selected' : ''; ?>>Maalinle</option>
        </select>
        <button class="btn btn-light border" type="submit"><i class="ti ti-filter me-1"></i>Filter</button>
    </form>
    <div class="patient-toolbar-actions">

        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#patientModal" data-patient-mode="new"><i class="ti ti-user-plus me-1"></i>New Patient</button>
    </div>
</div>

<div class="card clinic-card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Patient List</h5>
        <span class="badge text-bg-light"><?php echo count($patients); ?> patients</span>
    </div>
    <div class="card-body table-responsive">
        <table class="table table-hover align-middle datatable clinic-table">
            <thead>
                <tr>
                    <th>PAT ID</th>
                    <th>Patient</th>
                    <th>Phone</th>
                    <th>Sex</th>
                    <th>Age</th>
                    <th>Type</th>
                    <th>Credit Limit</th>
                    <th>Balance</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($patients as $patient): ?>
                <?php $balance = (float) ($patient['Current_Balance'] ?? 0); ?>
                <tr>
                    <td><span class="badge text-bg-light border font-monospace">PAT-<?php echo str_pad((string) (int) ($patient['Patient_ID'] ?? 0), 6, '0', STR_PAD_LEFT); ?></span></td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <?php echo clinic_avatar($patient['image'] ?? '', $patient['Full_Name'] ?? 'P', 'clinic-avatar-sm'); ?>
                            <div class="fw-semibold"><?php echo clinic_h($patient['Full_Name'] ?? '-'); ?></div>
                        </div>
                    </td>
                    <td><?php echo clinic_h($patient['Phone_Number'] ?? 'No phone'); ?></td>
                    <td><?php echo clinic_h($patient['Sex'] ?? '-'); ?></td>
                    <td><?php echo clinic_h($patient['Age_Group'] ?? '-'); ?></td>
                    <td><span class="badge text-bg-<?php echo $patient['Patient_Type'] === 'Bille' ? 'info' : 'secondary'; ?>"><?php echo clinic_h($patient['Patient_Type'] ?? '-'); ?></span></td>
                    <td><?php echo clinic_money($patient['Credit_Limit'] ?? 0); ?></td>
                    <td><strong class="<?php echo $balance > 0 ? 'text-danger' : 'text-success'; ?>"><?php echo clinic_money($balance); ?></strong></td>
                    <td class="text-end">
                        <a class="btn btn-sm btn-outline-primary" href="patients.php?profile_id=<?php echo (int) $patient['Patient_ID']; ?>">Profile</a>
                        <button
                            class="btn btn-sm btn-light border d-inline"
                            type="button"
                            data-bs-toggle="modal"
                            data-bs-target="#patientModal"
                            data-patient-mode="edit"
                            data-patient-id="<?php echo (int) $patient['Patient_ID']; ?>"
                            data-full-name="<?php echo clinic_h($patient['Full_Name'] ?? ''); ?>"
                            data-phone="<?php echo clinic_h($patient['Phone_Number'] ?? ''); ?>"
                            data-sex="<?php echo clinic_h($patient['Sex'] ?? 'Male'); ?>"
                            data-age-group="<?php echo clinic_h($patient['Age_Group'] ?? 'Adult'); ?>"
                            data-patient-type="<?php echo clinic_h($patient['Patient_Type'] ?? 'Maalinle'); ?>"
                            data-guarantor-id="<?php echo (int) ($patient['Guarantor_ID'] ?? 0); ?>"
                            data-relationship="<?php echo clinic_h($patient['Relationship'] ?? 'Self'); ?>"
                            data-credit-limit="<?php echo clinic_h($patient['Credit_Limit'] ?? 0); ?>"
                            data-current-balance="<?php echo clinic_h($patient['Current_Balance'] ?? 0); ?>"
                            data-image="<?php echo clinic_h($patient['image'] ?? ''); ?>"
                         title="Edit"><i class="ti ti-pencil"></i></button>
                        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#visitModal" data-patient="<?php echo (int) $patient['Patient_ID']; ?>" data-name="<?php echo clinic_h($patient['Full_Name']); ?>">Start Visit</button>
                        <form method="post" class="d-inline patient-delete-form" data-patient-name="<?php echo clinic_h($patient['Full_Name'] ?? ''); ?>">
                            <input type="hidden" name="csrf_token" value="<?php echo clinic_h(clinic_csrf_token()); ?>">
                            <input type="hidden" name="action" value="delete_patient">
                            <input type="hidden" name="Patient_ID" value="<?php echo (int) $patient['Patient_ID']; ?>">
                            <button class="btn btn-sm btn-outline-danger btn-icon" type="submit" title="Delete"><i class="ti ti-trash"></i></button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php if ($patients === []): ?><div class="alert alert-light border text-center mb-0">No patients found.</div><?php endif; ?>
    </div>
</div>
<?php endif; ?>
<div class="modal fade" id="patientModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-lg">
        <form method="post" class="modal-content">
            <div class="modal-header"><h5 class="modal-title" id="patientModalTitle">Register patient</h5><button class="btn-close" data-bs-dismiss="modal" type="button"></button></div>
            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?php echo clinic_h(clinic_csrf_token()); ?>">
                <input type="hidden" name="action" value="save_patient">
                <input type="hidden" name="Patient_ID" value="0">
                <div class="row">
                    <div class="col-md-6 mb-3"><label class="form-label">Full name</label><input class="form-control" name="Full_Name" required></div>
                    <div class="col-md-6 mb-3"><label class="form-label">Phone</label><input class="form-control" name="Phone_Number"></div>
                    <div class="col-md-4 mb-3"><label class="form-label">Sex</label><select class="form-select" name="Sex"><option selected>Male</option><option>Female</option></select></div>
                    <div class="col-md-4 mb-3"><label class="form-label">Age</label><select class="form-select" name="Age_Group"><option>Child</option><option selected>Adult</option></select></div>
                    <div class="col-md-4 mb-3"><label class="form-label">Type</label><select class="form-select" name="Patient_Type"><option>Bille</option><option selected>Maalinle</option></select></div>
                    <div class="col-md-4 mb-3"><label class="form-label">Relationship</label><select class="form-select" name="Relationship"><option>Self</option><option>Child</option><option>Spouse</option><option>Other</option></select></div>
                    <div class="col-md-4 mb-3"><label class="form-label">Guarantor</label><select class="form-select" name="Guarantor_ID"><option value="">None</option><?php clinic_select_options(clinic_sp_rows('sp_patients_list'), 'Patient_ID', 'Full_Name'); ?></select></div>
                    <div class="col-md-6 mb-3"><label class="form-label">Credit limit</label><input class="form-control" type="number" step="0.01" name="Credit_Limit" value="0"></div>
                    <div class="col-md-6 mb-3"><label class="form-label">Opening balance</label><input class="form-control" type="number" step="0.01" name="Current_Balance" value="0"></div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Photo</label>
                        <input class="form-control" type="file" name="Patient_Image" accept="image/png,image/jpeg,image/gif,image/webp">
                        <input type="hidden" name="Patient_Image_Current" value="">
                        <input type="hidden" name="Patient_Image_Remove" value="">
                        <div class="form-check mt-2 d-none" id="patientImageRemoveWrap">
                            <input class="form-check-input" type="checkbox" id="patientImageRemoveCheck" onchange="var f=document.querySelector('input[name=Patient_Image_Remove]'); if(f){f.value=this.checked?'1':'';}">
                            <label class="form-check-label" for="patientImageRemoveCheck">Remove current photo</label>
                        </div>
                        <div class="form-text">Optional — JPEG, PNG, GIF, WebP (max 2MB).</div>
                    </div>
                </div>
            </div>
            <div class="modal-footer"><button class="btn btn-danger" type="button" data-bs-dismiss="modal"><i class="ti ti-x me-1"></i>Cancel</button><button class="btn btn-success" type="submit" id="patientModalUpdate" style="display:none;"><i class="ti ti-edit me-1"></i>Update Patient</button><button class="btn btn-primary" type="submit" id="patientModalSubmit"><i class="ti ti-device-floppy me-1"></i>Save Patient</button></div>
        </form>
    </div>
</div>

<div class="modal fade" id="visitModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog">
        <form method="post" class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Start visit <span class="text-muted" id="visitPatientName"></span></h5><button class="btn-close" data-bs-dismiss="modal" type="button"></button></div>
            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?php echo clinic_h(clinic_csrf_token()); ?>">
                <input type="hidden" name="action" value="start_visit">
                <input type="hidden" name="Patient_ID" id="visitPatientId">
                <label class="form-label">Doctor</label>
                <select class="form-select mb-3" name="Doctor_ID"><option value="">No doctor yet</option><?php clinic_select_options($doctors, 'Doctor_ID', 'Full_Name'); ?></select>
                <label class="form-label">Initial notes</label>
                <textarea class="form-control" name="Notes" rows="4"></textarea>
            </div>
            <div class="modal-footer"><button class="btn btn-danger" type="button" data-bs-dismiss="modal"><i class="ti ti-x me-1"></i>Cancel</button><button class="btn btn-primary" type="submit"><i class="ti ti-stethoscope me-1"></i>Create Visit</button></div>
        </form>
    </div>
</div>



<script>
document.addEventListener('show.bs.modal', function (event) {
    var trigger = event.reltedTarget;
    if (!trigger) return;a
    var patient = trigger.getAttribute('data-patient') || '';
    var name = trigger.getAttribute('data-name') || '';
    if (event.target.id === 'patientModal') {
        var form = event.target.querySelector('form');
        var title = document.getElementById('patientModalTitle');
        var submit = document.getElementById('patientModalSubmit');

        function setField(fieldName, value) {
            if (!form.elements[fieldName]) return;
            form.elements[fieldName].value = value || '';
            if (window.jQuery) {
                jQuery(form.elements[fieldName]).trigger('change');
            }
        }

        form.reset();
        setField('Patient_ID', '0');
        setField('Sex', 'Male');
        setField('Age_Group', 'Adult');
        setField('Patient_Type', 'Maalinle');
        setField('Relationship', 'Self');
        setField('Credit_Limit', '0');
        setField('Current_Balance', '0');
        title.textContent = 'Register patient';
        submit.textContent = 'Save Patient';

        var imgCur = form.elements['Patient_Image_Current'];
        var imgRem = form.elements['Patient_Image_Remove'];
        var imgWrap = document.getElementById('patientImageRemoveWrap');
        var imgCheck = document.getElementById('patientImageRemoveCheck');
        if (imgCur) { imgCur.value = ''; }
        if (imgRem) { imgRem.value = ''; }
        if (imgCheck) { imgCheck.checked = false; }
        if (imgWrap) { imgWrap.classList.add('d-none'); }

        if (trigger.getAttribute('data-patient-mode') === 'edit') {
            setField('Patient_ID', trigger.getAttribute('data-patient-id') || '0');
            setField('Full_Name', trigger.getAttribute('data-full-name') || '');
            setField('Phone_Number', trigger.getAttribute('data-phone') || '');
            setField('Sex', trigger.getAttribute('data-sex') || 'Male');
            setField('Age_Group', trigger.getAttribute('data-age-group') || 'Adult');
            setField('Patient_Type', trigger.getAttribute('data-patient-type') || 'Maalinle');
            setField('Guarantor_ID', trigger.getAttribute('data-guarantor-id') || '');
            setField('Relationship', trigger.getAttribute('data-relationship') || 'Self');
            setField('Credit_Limit', trigger.getAttribute('data-credit-limit') || '0');
            setField('Current_Balance', trigger.getAttribute('data-current-balance') || '0');
            if (imgCur) { imgCur.value = trigger.getAttribute('data-image') || ''; }
            if (imgRem) { imgRem.value = ''; }
            if (imgCheck) { imgCheck.checked = false; }
            if (imgWrap) { imgWrap.classList.toggle('d-none', !(imgCur && imgCur.value)); }
            title.textContent = 'Update patient';
            submit.textContent = 'Update Patient';
        }
    }
    if (event.target.id === 'visitModal') {
        document.getElementById('visitPatientId').value = patient;
        document.getElementById('visitPatientName').textContent = name ? '- ' + name : '';
    }
});

document.querySelectorAll('.patient-delete-form').forEach(function (form) {
    form.addEventListener('submit', function (event) {
        if (form.dataset.confirmed === '1') {
            return;
        }

        event.preventDefault();
        var patientName = form.getAttribute('data-patient-name') || 'this patient';
        if (window.Swal) {
            Swal.fire({
                icon: 'warning',
                title: 'Delete patient?',
                text: 'Are you sure you want to delete ' + patientName + '?',
                showCancelButton: true,
                confirmButtonText: 'Yes, delete',
                confirmButtonColor: '#dc3545'
            }).then(function (result) {
                if (result.isConfirmed) {
                    form.dataset.confirmed = '1';
                    form.submit();
                }
            });
            return;
        }
    });
});
</script>
<?php clinic_page_end(); ?>
