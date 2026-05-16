<?php
require_once __DIR__ . '/../includes/advanced_components.php';

try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        clinic_check_csrf();
        $action = clinic_post_string('action');

        if ($action === 'save_patient') {
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
$typeFilter = (string) ($_GET['type'] ?? '');
$search = trim((string) ($_GET['q'] ?? ''));
$viewMode = (string) ($_GET['view'] ?? 'cards');
if (!in_array($viewMode, ['cards', 'table'], true)) {
    $viewMode = 'cards';
}

if ($typeFilter !== '') {
    $patients = array_values(array_filter($patients, static fn ($row) => ($row['Patient_Type'] ?? '') === $typeFilter));
}
if ($search !== '') {
    $patients = array_values(array_filter($patients, static function ($row) use ($search) {
        return stripos((string) ($row['Full_Name'] ?? ''), $search) !== false
            || stripos((string) ($row['Phone_Number'] ?? ''), $search) !== false;
    }));
}

clinic_page_start('Patient Desk', 'Search, register, open profile, start a visit, and collect payment from one workflow screen.');
?>
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
    .patient-view-toggle {
        background: var(--white);
        border: 1px solid var(--border-color);
        border-radius: 10px;
        display: inline-flex;
        gap: .25rem;
        padding: .25rem;
    }
    .patient-view-toggle .btn {
        align-items: center;
        border: 0;
        display: inline-flex;
        height: 34px;
        justify-content: center;
        padding: 0;
        width: 34px;
    }
    .patient-view-toggle .btn.active {
        background: var(--primary-transparent);
        color: var(--primary);
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
        <input type="hidden" name="view" value="<?php echo clinic_h($viewMode); ?>">
        <input class="form-control" type="search" name="q" value="<?php echo clinic_h($search); ?>" placeholder="Search patient or phone">
        <select class="form-select" name="type">
            <option value="">All types</option>
            <option value="Bille"<?php echo $typeFilter === 'Bille' ? ' selected' : ''; ?>>Bille</option>
            <option value="Maalinle"<?php echo $typeFilter === 'Maalinle' ? ' selected' : ''; ?>>Maalinle</option>
        </select>
        <button class="btn btn-light border" type="submit"><i class="ti ti-filter me-1"></i>Filter</button>
    </form>
    <div class="patient-toolbar-actions">
        <div class="patient-view-toggle" aria-label="Patient view mode">
            <a class="btn <?php echo $viewMode === 'table' ? 'active' : ''; ?>" title="Table view" href="patients.php?<?php echo http_build_query(['q' => $search, 'type' => $typeFilter, 'view' => 'table']); ?>">
                <i class="ti ti-list"></i>
            </a>
            <a class="btn <?php echo $viewMode === 'cards' ? 'active' : ''; ?>" title="Card view" href="patients.php?<?php echo http_build_query(['q' => $search, 'type' => $typeFilter, 'view' => 'cards']); ?>">
                <i class="ti ti-layout-grid"></i>
            </a>
        </div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#patientModal" data-patient-mode="new"><i class="ti ti-user-plus me-1"></i>New Patient</button>
    </div>
</div>

<?php if ($viewMode === 'table'): ?>
<div class="card clinic-card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Patient List</h5>
        <span class="badge text-bg-light"><?php echo count($patients); ?> patients</span>
    </div>
    <div class="card-body table-responsive">
        <table class="table table-hover align-middle datatable clinic-table">
            <thead>
                <tr>
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
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <span class="patient-table-avatar"><?php echo clinic_h(substr((string) ($patient['Full_Name'] ?? 'P'), 0, 1)); ?></span>
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
                        <a class="btn btn-sm btn-outline-primary" href="patients.php?profile_id=<?php echo (int) $patient['Patient_ID']; ?>&view=table">Profile</a>
                        <button
                            class="btn btn-sm btn-light border"
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
                        >Edit</button>
                        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#visitModal" data-patient="<?php echo (int) $patient['Patient_ID']; ?>" data-name="<?php echo clinic_h($patient['Full_Name']); ?>">Start Visit</button>
                        <form method="post" class="d-inline patient-delete-form" data-patient-name="<?php echo clinic_h($patient['Full_Name'] ?? ''); ?>">
                            <input type="hidden" name="csrf_token" value="<?php echo clinic_h(clinic_csrf_token()); ?>">
                            <input type="hidden" name="action" value="delete_patient">
                            <input type="hidden" name="Patient_ID" value="<?php echo (int) $patient['Patient_ID']; ?>">
                            <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php if ($patients === []): ?><div class="alert alert-light border text-center mb-0">No patients found.</div><?php endif; ?>
    </div>
</div>
<?php else: ?>
<div class="row g-3">
    <?php foreach ($patients as $patient): ?>
    <?php $balance = (float) ($patient['Current_Balance'] ?? 0); ?>
    <div class="col-xxl-4 col-xl-6">
        <div class="card patient-card h-100">
            <div class="card-body">
                <div class="d-flex gap-3 align-items-start">
                    <span class="clinic-patient-avatar bg-primary bg-opacity-10 text-primary"><?php echo clinic_h(substr((string) ($patient['Full_Name'] ?? 'P'), 0, 1)); ?></span>
                    <div class="flex-grow-1">
                        <div class="d-flex justify-content-between align-items-start gap-2">
                            <div>
                                <h6 class="fw-bold mb-1"><?php echo clinic_h($patient['Full_Name'] ?? '-'); ?></h6>
                                <div class="text-muted small mb-2"><i class="ti ti-phone me-1"></i><?php echo clinic_h($patient['Phone_Number'] ?? 'No phone'); ?></div>
                            </div>
                            <span class="badge text-bg-<?php echo $patient['Patient_Type'] === 'Bille' ? 'info' : 'secondary'; ?>"><?php echo clinic_h($patient['Patient_Type'] ?? '-'); ?></span>
                        </div>
                        <div class="d-flex flex-wrap gap-2 mb-3">
                            <span class="clinic-workflow-pill small">Sex: <strong><?php echo clinic_h($patient['Sex'] ?? '-'); ?></strong></span>
                            <span class="clinic-workflow-pill small">Age: <strong><?php echo clinic_h($patient['Age_Group'] ?? '-'); ?></strong></span>
                            <span class="clinic-workflow-pill small">Balance: <strong class="<?php echo $balance > 0 ? 'text-danger' : 'text-success'; ?>"><?php echo clinic_money($balance); ?></strong></span>
                            <span class="clinic-workflow-pill small">Credit: <?php echo clinic_money($patient['Credit_Limit'] ?? 0); ?></span>
                        </div>
                        <div class="patient-card-actions">
                            <a class="btn btn-sm btn-outline-primary" href="patients.php?profile_id=<?php echo (int) $patient['Patient_ID']; ?>">Profile</a>
                            <button
                                class="btn btn-sm btn-light border"
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
                            >Edit</button>
                            <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#visitModal" data-patient="<?php echo (int) $patient['Patient_ID']; ?>" data-name="<?php echo clinic_h($patient['Full_Name']); ?>">Start Visit</button>
                            <form method="post" class="d-inline patient-delete-form" data-patient-name="<?php echo clinic_h($patient['Full_Name'] ?? ''); ?>">
                                <input type="hidden" name="csrf_token" value="<?php echo clinic_h(clinic_csrf_token()); ?>">
                                <input type="hidden" name="action" value="delete_patient">
                                <input type="hidden" name="Patient_ID" value="<?php echo (int) $patient['Patient_ID']; ?>">
                                <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    <?php if ($patients === []): ?><div class="col-12"><div class="alert alert-light border text-center">No patients found.</div></div><?php endif; ?>
</div>
<?php endif; ?>

<div class="modal fade" id="patientModal" tabindex="-1">
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
                </div>
            </div>
            <div class="modal-footer"><button class="btn btn-light" type="button" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" id="patientModalSubmit">Save Patient</button></div>
        </form>
    </div>
</div>

<div class="modal fade" id="visitModal" tabindex="-1">
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
            <div class="modal-footer"><button class="btn btn-light" type="button" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Create Visit</button></div>
        </form>
    </div>
</div>

<?php if ($profile): ?>
<?php
$activityEvents = array_values(array_filter($timeline, static fn ($event) => (string) ($event['event_type'] ?? '') !== 'Payment'));
$transactionEvents = array_values(array_filter($timeline, static fn ($event) => (string) ($event['event_type'] ?? '') === 'Payment'));
$profileCloseUrl = 'patients.php?' . http_build_query(['q' => $search, 'type' => $typeFilter, 'view' => $viewMode]);
?>
<div class="modal fade patient-profile-modal" id="profileModal" tabindex="-1">
    <div class="modal-dialog modal-fullscreen-lg-down modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <a class="btn btn-sm btn-light border" href="<?php echo clinic_h($profileCloseUrl); ?>"><i class="ti ti-chevron-left me-1"></i>Patients</a>
                <a class="btn-close" href="<?php echo clinic_h($profileCloseUrl); ?>"></a>
            </div>
            <div class="modal-body">
                <div class="patient-profile-hero p-3 mb-3">
                    <div class="patient-profile-hero-content d-flex justify-content-between align-items-center flex-wrap gap-3">
                        <div class="d-flex align-items-center gap-3">
                            <span class="patient-profile-photo"><?php echo clinic_h(substr((string) ($profile['Full_Name'] ?? 'P'), 0, 1)); ?></span>
                            <div>
                                <div class="small text-primary fw-bold">#PT<?php echo str_pad((string) (int) ($profile['Patient_ID'] ?? 0), 4, '0', STR_PAD_LEFT); ?></div>
                                <h4 class="fw-bold mb-1"><?php echo clinic_h($profile['Full_Name'] ?? '-'); ?></h4>
                                <div class="text-muted small">
                                    <?php echo clinic_h($profile['Phone_Number'] ?? 'No phone'); ?>
                                    <span class="mx-2">-</span>
                                    Last visited: <?php echo clinic_h($profile['last_visit_date'] ?: '-'); ?>
                                </div>
                            </div>
                        </div>
                        <div class="d-flex flex-wrap gap-2">
                            <button
                                class="btn btn-light border"
                                type="button"
                                data-bs-toggle="modal"
                                data-bs-target="#patientModal"
                                data-patient-mode="edit"
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
                            >
                                <i class="ti ti-edit me-1"></i>Edit
                            </button>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#visitModal" data-patient="<?php echo (int) $profile['Patient_ID']; ?>" data-name="<?php echo clinic_h($profile['Full_Name']); ?>">
                                <i class="ti ti-calendar-plus me-1"></i>Start Visit
                            </button>
                            <form method="post" class="patient-delete-form" data-patient-name="<?php echo clinic_h($profile['Full_Name'] ?? ''); ?>">
                                <input type="hidden" name="csrf_token" value="<?php echo clinic_h(clinic_csrf_token()); ?>">
                                <input type="hidden" name="action" value="delete_patient">
                                <input type="hidden" name="Patient_ID" value="<?php echo (int) $profile['Patient_ID']; ?>">
                                <button class="btn btn-outline-danger" type="submit"><i class="ti ti-trash me-1"></i>Delete</button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-lg-5">
                        <div class="patient-info-card h-100 p-3">
                            <h6 class="fw-bold mb-3"><i class="ti ti-user-circle me-1"></i>About</h6>
                            <div class="row">
                                <div class="col-sm-6 patient-info-item">
                                    <span class="patient-info-icon"><i class="ti ti-phone"></i></span>
                                    <div><div class="small text-muted">Phone</div><strong><?php echo clinic_h($profile['Phone_Number'] ?? '-'); ?></strong></div>
                                </div>
                                <div class="col-sm-6 patient-info-item">
                                    <span class="patient-info-icon"><i class="ti ti-id"></i></span>
                                    <div><div class="small text-muted">Patient Type</div><strong><?php echo clinic_h($profile['Patient_Type'] ?? '-'); ?></strong></div>
                                </div>
                                <div class="col-sm-6 patient-info-item">
                                    <span class="patient-info-icon"><i class="ti ti-gender-bigender"></i></span>
                                    <div><div class="small text-muted">Sex</div><strong><?php echo clinic_h($profile['Sex'] ?? '-'); ?></strong></div>
                                </div>
                                <div class="col-sm-6 patient-info-item">
                                    <span class="patient-info-icon"><i class="ti ti-user-heart"></i></span>
                                    <div><div class="small text-muted">Age</div><strong><?php echo clinic_h($profile['Age_Group'] ?? '-'); ?></strong></div>
                                </div>
                                <div class="col-sm-6 patient-info-item">
                                    <span class="patient-info-icon"><i class="ti ti-users"></i></span>
                                    <div><div class="small text-muted">Relationship</div><strong><?php echo clinic_h($profile['Relationship'] ?? '-'); ?></strong></div>
                                </div>
                                <div class="col-sm-6 patient-info-item">
                                    <span class="patient-info-icon"><i class="ti ti-user-shield"></i></span>
                                    <div><div class="small text-muted">Guarantor</div><strong><?php echo clinic_h($profile['Guarantor_Name'] ?? '-'); ?></strong></div>
                                </div>
                                <div class="col-sm-6 patient-info-item">
                                    <span class="patient-info-icon"><i class="ti ti-calendar"></i></span>
                                    <div><div class="small text-muted">Registered</div><strong><?php echo clinic_h($profile['Created_At'] ?? '-'); ?></strong></div>
                                </div>
                                <div class="col-sm-6 patient-info-item">
                                    <span class="patient-info-icon"><i class="ti ti-credit-card"></i></span>
                                    <div><div class="small text-muted">Credit Limit</div><strong><?php echo clinic_money($profile['Credit_Limit'] ?? 0); ?></strong></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-7">
                        <div class="patient-info-card h-100 p-3">
                            <h6 class="fw-bold mb-3"><i class="ti ti-chart-dots me-1"></i>Patient Summary</h6>
                            <div class="row">
                                <div class="col-md-6 patient-info-item">
                                    <span class="patient-info-icon"><i class="ti ti-wallet"></i></span>
                                    <div><div class="small text-muted">Balance</div><strong class="<?php echo (float) ($profile['Current_Balance'] ?? 0) > 0 ? 'text-danger' : 'text-success'; ?>"><?php echo clinic_money($profile['Current_Balance'] ?? 0); ?></strong></div>
                                </div>
                                <div class="col-md-6 patient-info-item">
                                    <span class="patient-info-icon"><i class="ti ti-stethoscope"></i></span>
                                    <div><div class="small text-muted">Visits</div><strong><?php echo (int) ($profile['visit_count'] ?? 0); ?></strong></div>
                                </div>
                                <div class="col-md-6 patient-info-item">
                                    <span class="patient-info-icon"><i class="ti ti-cash"></i></span>
                                    <div><div class="small text-muted">Payments</div><strong><?php echo (int) ($profile['payment_count'] ?? 0); ?> / <?php echo clinic_money($profile['total_paid'] ?? 0); ?></strong></div>
                                </div>
                                <div class="col-md-6 patient-info-item">
                                    <span class="patient-info-icon"><i class="ti ti-clock"></i></span>
                                    <div><div class="small text-muted">Last Visit</div><strong><?php echo clinic_h($profile['last_visit_date'] ?: '-'); ?></strong></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <ul class="nav nav-tabs patient-profile-tabs mb-3" role="tablist">
                    <li class="nav-item" role="presentation"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#profileActivity" type="button">Appointments</button></li>
                    <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#profileTransactions" type="button">Transactions</button></li>
                </ul>
                <div class="tab-content">
                    <div class="tab-pane fade show active" id="profileActivity">
                        <div class="patient-info-card table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead><tr><th>Date & Time</th><th>Type</th><th>Doctor / Related</th><th>Status</th></tr></thead>
                                <tbody>
                                    <?php foreach ($activityEvents as $event): ?>
                                    <tr>
                                        <td><?php echo clinic_h($event['event_at'] ?? '-'); ?></td>
                                        <td><strong><?php echo clinic_h($event['event_type'] ?? '-'); ?> #<?php echo (int) ($event['event_id'] ?? 0); ?></strong><div class="small text-muted"><?php echo clinic_h($event['description'] ?? ''); ?></div></td>
                                        <td><?php echo clinic_h($event['related_name'] ?? '-'); ?></td>
                                        <td><span class="badge text-bg-light">Recorded</span></td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if ($activityEvents === []): ?><tr><td class="text-center text-muted py-4" colspan="4">No appointments or activity found.</td></tr><?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="profileTransactions">
                        <div class="patient-info-card table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead><tr><th>Date & Time</th><th>Description</th><th>Account</th><th>Status</th></tr></thead>
                                <tbody>
                                    <?php foreach ($transactionEvents as $event): ?>
                                    <tr>
                                        <td><?php echo clinic_h($event['event_at'] ?? '-'); ?></td>
                                        <td><strong>Payment #<?php echo (int) ($event['event_id'] ?? 0); ?></strong><div class="small text-muted"><?php echo clinic_h($event['description'] ?? ''); ?></div></td>
                                        <td><?php echo clinic_h($event['related_name'] ?? '-'); ?></td>
                                        <td><span class="badge text-bg-success">Paid</span></td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if ($transactionEvents === []): ?><tr><td class="text-center text-muted py-4" colspan="4">No transactions found.</td></tr><?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    bootstrap.Modal.getOrCreateInstance(document.getElementById('profileModal')).show();
});
</script>
<?php endif; ?>

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

        if (confirm('Delete ' + patientName + '?')) {
            form.dataset.confirmed = '1';
            form.submit();
        }
    });
});
</script>
<?php clinic_page_end(); ?>
