<?php
require_once __DIR__ . '/../includes/advanced_components.php';

function clinic_visit_is_admin_user(): bool
{
    $roleName = strtolower(trim((string) ($_SESSION['role_name'] ?? '')));
    $roleId = (int) ($_SESSION['role_id'] ?? 0);

    return $roleId === 1 || $roleName === 'admin';
}

function clinic_visit_doctor_id_for_user(array $doctors): ?int
{
    $userId = clinic_current_user_id();
    if ($userId === null) {
        return null;
    }

    foreach ($doctors as $doctor) {
        if ((int) ($doctor['User_ID'] ?? 0) === $userId) {
            return (int) ($doctor['Doctor_ID'] ?? 0);
        }
    }

    return null;
}

function clinic_visit_ensure_visit_scope(int $visitId, bool $isDoctorScoped, ?int $doctorId): void
{
    if (!$isDoctorScoped) {
        return;
    }
    if ($doctorId === null || $doctorId < 1) {
        throw new RuntimeException('Your user is not linked to a doctor profile.');
    }

    $visit = clinic_sp_one('sp_visits_get', [$visitId], 'i');
    if (!$visit || (int) ($visit['Doctor_ID'] ?? 0) !== $doctorId) {
        throw new RuntimeException('You can only access your own visits.');
    }
}

function clinic_visit_ensure_appointment_scope(int $appointmentId, bool $isDoctorScoped, ?int $doctorId): void
{
    if (!$isDoctorScoped || $appointmentId < 1) {
        return;
    }
    if ($doctorId === null || $doctorId < 1) {
        throw new RuntimeException('Your user is not linked to a doctor profile.');
    }

    $appointment = clinic_sp_one('sp_appointments_get', [$appointmentId], 'i');
    if (!$appointment || (int) ($appointment['Doctor_ID'] ?? 0) !== $doctorId) {
        throw new RuntimeException('You can only start visits for your own appointments.');
    }
}

function clinic_visit_ensure_patient_scope(int $patientId, bool $isDoctorScoped, ?int $doctorId): void
{
    if (!$isDoctorScoped) {
        return;
    }
    if ($doctorId === null || $doctorId < 1) {
        throw new RuntimeException('Your user is not linked to a doctor profile.');
    }

    foreach (clinic_sp_rows('sp_appointments_list') as $appointment) {
        if ((int) ($appointment['Doctor_ID'] ?? 0) === $doctorId && (int) ($appointment['Patient_ID'] ?? 0) === $patientId) {
            return;
        }
    }
    foreach (clinic_sp_rows('sp_visits_list') as $visit) {
        if ((int) ($visit['Doctor_ID'] ?? 0) === $doctorId && (int) ($visit['Patient_ID'] ?? 0) === $patientId) {
            return;
        }
    }

    throw new RuntimeException('You can only create visits for patients assigned to your appointments or visits.');
}

$doctorRowsForAccess = clinic_sp_rows('sp_doctors_list');
$currentDoctorId = clinic_visit_doctor_id_for_user($doctorRowsForAccess);
$isAdminUser = clinic_visit_is_admin_user();
$isDoctorScoped = !$isAdminUser && strtolower(trim((string) ($_SESSION['role_name'] ?? ''))) === 'doctor';

try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        clinic_check_csrf();
        $action = clinic_post_string('action');

        if ($action === 'create_visit') {
            $appointmentIdPost = clinic_post_int('Appointment_ID');
            clinic_visit_ensure_appointment_scope($appointmentIdPost, $isDoctorScoped, $currentDoctorId);
            clinic_visit_ensure_patient_scope(clinic_post_int('Patient_ID'), $isDoctorScoped, $currentDoctorId);
            $doctorIdPost = $isDoctorScoped ? (int) $currentDoctorId : clinic_post_int('Doctor_ID');
            $row = clinic_sp_one('sp_create_visit_with_actions', [
                clinic_post_int('Patient_ID'),
                $doctorIdPost,
                clinic_post_string('Notes'),
                $appointmentIdPost,
            ]);
            clinic_flash('Visit created successfully.');
            clinic_redirect('visits.php?visit_id=' . (int) ($row['Visit_ID'] ?? 0));
        }

        if ($action === 'add_lab') {
            clinic_visit_ensure_visit_scope(clinic_post_int('Visit_ID'), $isDoctorScoped, $currentDoctorId);
            $testIds = $_POST['Test_ID'] ?? [];
            if (!is_array($testIds)) {
                $testIds = [$testIds];
            }

            $added = 0;
            foreach (array_unique(array_map('intval', $testIds)) as $testId) {
                if ($testId < 1) {
                    continue;
                }
                clinic_sp_exec('sp_lab_results_save', [0, clinic_post_int('Visit_ID'), $testId, '', 'Pending']);
                $added++;
            }

            if ($added === 0) {
                throw new RuntimeException('Select at least one lab test.');
            }

            clinic_flash($added . ' lab request' . ($added === 1 ? '' : 's') . ' added to visit.');
            clinic_redirect('visits.php?visit_id=' . clinic_post_int('Visit_ID'));
        }

        if ($action === 'manage_lab_requests') {
            $visitIdPost = clinic_post_int('Visit_ID');
            clinic_visit_ensure_visit_scope($visitIdPost, $isDoctorScoped, $currentDoctorId);
            $testIds = $_POST['Test_ID'] ?? [];
            if (!is_array($testIds)) {
                $testIds = [$testIds];
            }

            $currentRows = clinic_sp_rows('sp_lab_results_list');
            $deletedNames = [];
            foreach ($currentRows as $row) {
                if ((int) ($row['Visit_ID'] ?? 0) !== $visitIdPost || ($row['Status'] ?? 'Pending') === 'Completed') {
                    continue;
                }
                $deletedNames[] = (string) ($row['Test_Name'] ?? 'Lab request');
                clinic_sp_exec('sp_lab_results_delete', [(int) $row['Result_ID']], 'i');
            }

            $added = 0;
            foreach (array_unique(array_map('intval', $testIds)) as $testId) {
                if ($testId < 1) {
                    continue;
                }
                clinic_sp_exec('sp_lab_results_save', [0, $visitIdPost, $testId, '', 'Pending']);
                $added++;
            }

            $deletedList = $deletedNames === [] ? '' : ' Deleted labs: ' . implode(', ', $deletedNames) . '.';
            clinic_flash($added > 0 ? 'Lab requests updated together.' . $deletedList : 'Pending lab requests deleted.' . $deletedList);
            clinic_redirect('visits.php?visit_id=' . $visitIdPost);
        }

        if ($action === 'update_lab') {
            $resultId = clinic_post_int('Result_ID');
            clinic_visit_ensure_visit_scope(clinic_post_int('Visit_ID'), $isDoctorScoped, $currentDoctorId);
            $currentLab = clinic_sp_one('sp_lab_results_get', [$resultId], 'i');
            if (!$currentLab) {
                throw new RuntimeException('Lab request was not found.');
            }
            if (($currentLab['Status'] ?? 'Pending') === 'Completed') {
                throw new RuntimeException('Completed lab results cannot be changed from the request screen.');
            }

            clinic_sp_exec('sp_lab_results_save', [
                $resultId,
                clinic_post_int('Visit_ID'),
                clinic_post_int('Test_ID'),
                (string) ($currentLab['Result_Details'] ?? ''),
                (string) ($currentLab['Status'] ?? 'Pending'),
            ]);
            clinic_flash('Lab request updated.');
            clinic_redirect('visits.php?visit_id=' . clinic_post_int('Visit_ID'));
        }

        if ($action === 'delete_lab') {
            $resultId = clinic_post_int('Result_ID');
            clinic_visit_ensure_visit_scope(clinic_post_int('Visit_ID'), $isDoctorScoped, $currentDoctorId);
            $currentLab = clinic_sp_one('sp_lab_results_get', [$resultId], 'i');
            if (!$currentLab) {
                throw new RuntimeException('Lab request was not found.');
            }
            if (($currentLab['Status'] ?? 'Pending') === 'Completed') {
                throw new RuntimeException('Completed lab results cannot be deleted from the request screen.');
            }

            clinic_sp_exec('sp_lab_results_delete', [$resultId], 'i');
            clinic_flash('Lab request deleted.');
            clinic_redirect('visits.php?visit_id=' . clinic_post_int('Visit_ID'));
        }

        if ($action === 'bulk_update_lab') {
            $visitIdPost = clinic_post_int('Visit_ID');
            clinic_visit_ensure_visit_scope($visitIdPost, $isDoctorScoped, $currentDoctorId);
            $testId = clinic_post_int('Test_ID');
            $resultIds = $_POST['Result_ID'] ?? [];
            if (!is_array($resultIds)) {
                $resultIds = [$resultIds];
            }

            $updated = 0;
            foreach (array_unique(array_map('intval', $resultIds)) as $resultId) {
                if ($resultId < 1 || $testId < 1) {
                    continue;
                }
                $currentLab = clinic_sp_one('sp_lab_results_get', [$resultId], 'i');
                if (!$currentLab || (int) ($currentLab['Visit_ID'] ?? 0) !== $visitIdPost || ($currentLab['Status'] ?? 'Pending') === 'Completed') {
                    continue;
                }
                clinic_sp_exec('sp_lab_results_save', [
                    $resultId,
                    $visitIdPost,
                    $testId,
                    (string) ($currentLab['Result_Details'] ?? ''),
                    (string) ($currentLab['Status'] ?? 'Pending'),
                ]);
                $updated++;
            }

            if ($updated === 0) {
                throw new RuntimeException('Select at least one pending lab request to update.');
            }

            clinic_flash($updated . ' lab request' . ($updated === 1 ? '' : 's') . ' updated.');
            clinic_redirect('visits.php?visit_id=' . $visitIdPost);
        }

        if ($action === 'bulk_delete_lab') {
            $visitIdPost = clinic_post_int('Visit_ID');
            clinic_visit_ensure_visit_scope($visitIdPost, $isDoctorScoped, $currentDoctorId);
            $resultIds = $_POST['Result_ID'] ?? [];
            if (!is_array($resultIds)) {
                $resultIds = [$resultIds];
            }

            $deleted = 0;
            foreach (array_unique(array_map('intval', $resultIds)) as $resultId) {
                if ($resultId < 1) {
                    continue;
                }
                $currentLab = clinic_sp_one('sp_lab_results_get', [$resultId], 'i');
                if (!$currentLab || (int) ($currentLab['Visit_ID'] ?? 0) !== $visitIdPost || ($currentLab['Status'] ?? 'Pending') === 'Completed') {
                    continue;
                }
                clinic_sp_exec('sp_lab_results_delete', [$resultId], 'i');
                $deleted++;
            }

            if ($deleted === 0) {
                throw new RuntimeException('Select at least one pending lab request to delete.');
            }

            clinic_flash($deleted . ' lab request' . ($deleted === 1 ? '' : 's') . ' deleted.');
            clinic_redirect('visits.php?visit_id=' . $visitIdPost);
        }

        if ($action === 'add_prescription') {
            clinic_visit_ensure_visit_scope(clinic_post_int('Visit_ID'), $isDoctorScoped, $currentDoctorId);
            $medicineIds = $_POST['Medicine_ID'] ?? [];
            $dosages = $_POST['Dosage'] ?? [];
            if (!is_array($medicineIds)) {
                $medicineIds = [$medicineIds];
            }
            if (!is_array($dosages)) {
                $dosages = [$dosages];
            }

            $added = 0;
            foreach ($medicineIds as $index => $medicineId) {
                $medicineId = (int) $medicineId;
                if ($medicineId < 1) {
                    continue;
                }
                clinic_sp_exec('sp_prescriptions_save', [
                    0,
                    clinic_post_int('Visit_ID'),
                    $medicineId,
                    trim((string) ($dosages[$index] ?? '')),
                ]);
                $added++;
            }

            if ($added === 0) {
                throw new RuntimeException('Select at least one medicine.');
            }

            clinic_flash($added . ' prescription' . ($added === 1 ? '' : 's') . ' added.');
            clinic_redirect('visits.php?visit_id=' . clinic_post_int('Visit_ID'));
        }

        if ($action === 'manage_prescriptions') {
            $visitIdPost = clinic_post_int('Visit_ID');
            clinic_visit_ensure_visit_scope($visitIdPost, $isDoctorScoped, $currentDoctorId);
            $medicineIds = $_POST['Medicine_ID'] ?? [];
            $dosages = $_POST['Dosage'] ?? [];
            if (!is_array($medicineIds)) {
                $medicineIds = [$medicineIds];
            }
            if (!is_array($dosages)) {
                $dosages = [$dosages];
            }

            $deletedNames = [];
            foreach (clinic_sp_rows('sp_prescriptions_list') as $row) {
                if ((int) ($row['Visit_ID'] ?? 0) !== $visitIdPost) {
                    continue;
                }
                $deletedNames[] = (string) ($row['Medicine_Name'] ?? 'Prescription');
                clinic_sp_exec('sp_prescriptions_delete', [(int) $row['Prescription_ID']], 'i');
            }

            $added = 0;
            foreach ($medicineIds as $index => $medicineId) {
                $medicineId = (int) $medicineId;
                if ($medicineId < 1) {
                    continue;
                }
                clinic_sp_exec('sp_prescriptions_save', [
                    0,
                    $visitIdPost,
                    $medicineId,
                    trim((string) ($dosages[$index] ?? '')),
                ]);
                $added++;
            }

            $deletedList = $deletedNames === [] ? '' : ' Deleted prescriptions: ' . implode(', ', $deletedNames) . '.';
            clinic_flash($added > 0 ? 'Prescriptions updated together.' . $deletedList : 'Prescriptions deleted.' . $deletedList);
            clinic_redirect('visits.php?visit_id=' . $visitIdPost);
        }

        if ($action === 'update_prescription') {
            $prescriptionId = clinic_post_int('Prescription_ID');
            clinic_visit_ensure_visit_scope(clinic_post_int('Visit_ID'), $isDoctorScoped, $currentDoctorId);
            $currentPrescription = clinic_sp_one('sp_prescriptions_get', [$prescriptionId], 'i');
            if (!$currentPrescription) {
                throw new RuntimeException('Prescription was not found.');
            }

            clinic_sp_exec('sp_prescriptions_save', [
                $prescriptionId,
                clinic_post_int('Visit_ID'),
                clinic_post_int('Medicine_ID'),
                clinic_post_string('Dosage'),
            ]);
            clinic_flash('Prescription updated.');
            clinic_redirect('visits.php?visit_id=' . clinic_post_int('Visit_ID'));
        }

        if ($action === 'delete_prescription') {
            $prescriptionId = clinic_post_int('Prescription_ID');
            clinic_visit_ensure_visit_scope(clinic_post_int('Visit_ID'), $isDoctorScoped, $currentDoctorId);
            $currentPrescription = clinic_sp_one('sp_prescriptions_get', [$prescriptionId], 'i');
            if (!$currentPrescription) {
                throw new RuntimeException('Prescription was not found.');
            }

            clinic_sp_exec('sp_prescriptions_delete', [$prescriptionId], 'i');
            clinic_flash('Prescription deleted.');
            clinic_redirect('visits.php?visit_id=' . clinic_post_int('Visit_ID'));
        }

        if ($action === 'add_nursing') {
            clinic_visit_ensure_visit_scope(clinic_post_int('Visit_ID'), $isDoctorScoped, $currentDoctorId);
            clinic_sp_exec('sp_nursing_records_save', [0, clinic_post_int('Visit_ID'), clinic_post_int('Service_ID'), clinic_post_string('Medicine_Used'), clinic_current_user_id(), '']);
            clinic_flash('Nursing service recorded.');
            clinic_redirect('visits.php?visit_id=' . clinic_post_int('Visit_ID'));
        }
    }
} catch (Throwable $e) {
    clinic_flash($e->getMessage(), 'danger');
    clinic_redirect('visits.php');
}

$visits = clinic_sp_rows('sp_visits_list');
$patients = clinic_sp_rows('sp_patients_list');
$doctors = $doctorRowsForAccess;
$appointmentsForAccess = clinic_sp_rows('sp_appointments_list');
$allowedPatientIds = [];

if ($isDoctorScoped) {
    if ($currentDoctorId === null || $currentDoctorId < 1) {
        $visits = [];
        $patients = [];
        $doctors = [];
        $appointmentsForAccess = [];
        clinic_flash('Your user is not linked to a doctor profile.', 'warning');
    } else {
        $visits = array_values(array_filter($visits, fn (array $row): bool => (int) ($row['Doctor_ID'] ?? 0) === $currentDoctorId));
        $doctors = array_values(array_filter($doctors, fn (array $row): bool => (int) ($row['Doctor_ID'] ?? 0) === $currentDoctorId));
        $appointmentsForAccess = array_values(array_filter($appointmentsForAccess, fn (array $row): bool => (int) ($row['Doctor_ID'] ?? 0) === $currentDoctorId));

        $allowedPatientIds = [];
        foreach ($visits as $row) {
            $allowedPatientIds[(int) ($row['Patient_ID'] ?? 0)] = true;
        }
        foreach ($appointmentsForAccess as $row) {
            $allowedPatientIds[(int) ($row['Patient_ID'] ?? 0)] = true;
        }
        unset($allowedPatientIds[0]);

        $patients = array_values(array_filter($patients, static fn (array $row): bool => isset($allowedPatientIds[(int) ($row['Patient_ID'] ?? 0)])));
    }
}

$labTests = clinic_sp_rows('sp_lab_tests_list');
$medicines = clinic_sp_rows('sp_medicines_list');
$services = clinic_sp_rows('sp_nursing_services_list');
$requestedVisitId = (int) ($_GET['visit_id'] ?? 0);
$allowedVisitIds = array_flip(array_map(static fn (array $row): int => (int) ($row['Visit_ID'] ?? 0), $visits));
$visitId = $requestedVisitId > 0 ? $requestedVisitId : (int) ($visits[0]['Visit_ID'] ?? 0);
if ($isDoctorScoped && $visitId > 0 && !isset($allowedVisitIds[$visitId])) {
    clinic_flash('You can only open your own visits.', 'warning');
    $visitId = (int) ($visits[0]['Visit_ID'] ?? 0);
}
$workspace = $visitId > 0 ? clinic_sp_one('sp_visit_workspace', [$visitId], 'i') : null;
if ($isDoctorScoped && $workspace && (int) ($workspace['Doctor_ID'] ?? 0) !== (int) $currentDoctorId) {
    $workspace = null;
}
$selectedPatient = (int) ($_GET['patient_id'] ?? 0);
$selectedAppointment = (int) ($_GET['appointment_id'] ?? 0);
if ($isDoctorScoped && $selectedAppointment > 0) {
    $selectedAppointmentRow = null;
    foreach ($appointmentsForAccess as $appointmentRow) {
        if ((int) ($appointmentRow['Appointment_ID'] ?? 0) === $selectedAppointment) {
            $selectedAppointmentRow = $appointmentRow;
            break;
        }
    }
    if (!$selectedAppointmentRow) {
        clinic_flash('You can only start visits for your own appointments.', 'warning');
        $selectedAppointment = 0;
        $selectedPatient = 0;
    } else {
        $selectedPatient = (int) ($selectedAppointmentRow['Patient_ID'] ?? $selectedPatient);
    }
}
$patientIdForWorkspace = $workspace ? (int) ($workspace['Patient_ID'] ?? 0) : 0;
$patientHistoryTimeline = [];
$patientHistoryProfile = null;
$patientHistoryAllowed = false;
if ($patientIdForWorkspace > 0) {
    $patientHistoryAllowed = !$isDoctorScoped || isset($allowedPatientIds[$patientIdForWorkspace]);
    if ($patientHistoryAllowed) {
        $patientHistoryProfile = clinic_sp_one('sp_patient_profile', [$patientIdForWorkspace], 'i');
        $patientHistoryTimeline = clinic_sp_rows('sp_patient_timeline', [$patientIdForWorkspace], 'i');
    }
}
$visitPatientProfileActivityEvents = [];
$visitPatientProfilePaymentEvents = [];
foreach ($patientHistoryTimeline as $ev) {
    if ((string) ($ev['event_type'] ?? '') === 'Payment') {
        $visitPatientProfilePaymentEvents[] = $ev;
    } else {
        $visitPatientProfileActivityEvents[] = $ev;
    }
}
$labRows = array_values(array_filter(clinic_sp_rows('sp_lab_results_list'), fn ($row) => (int) ($row['Visit_ID'] ?? 0) === $visitId));
$labTestMap = [];
foreach ($labTests as $test) {
    $labTestMap[(int) ($test['Test_ID'] ?? 0)] = $test;
}
foreach ($labRows as &$labRow) {
    $test = $labTestMap[(int) ($labRow['Test_ID'] ?? 0)] ?? [];
    $labRow['Price'] = (float) ($test['Price'] ?? 0);
}
unset($labRow);
$completedLabRows = array_values(array_filter($labRows, static fn ($row) => (string) ($row['Status'] ?? '') === 'Completed'));
$completedLabSubtotal = array_sum(array_map(static fn ($row) => (float) ($row['Price'] ?? 0), $completedLabRows));
$prescriptions = array_values(array_filter(clinic_sp_rows('sp_prescriptions_list'), fn ($row) => (int) ($row['Visit_ID'] ?? 0) === $visitId));
$nursing = array_values(array_filter(clinic_sp_rows('sp_nursing_records_list'), fn ($row) => (int) ($row['Visit_ID'] ?? 0) === $visitId));

clinic_page_start('Visit Workspace', 'One screen for clinical notes, lab, nursing, and prescriptions.');
?>
<style>
    .lab-pos-modal .lab-test-card {
        background: var(--white);
        border: 1px solid var(--border-color);
        border-radius: 14px;
        box-shadow: var(--box-shadow-sm);
        cursor: pointer;
        min-height: 132px;
        padding: 1rem;
        transition: transform .12s, box-shadow .12s;
    }
    .lab-pos-modal .lab-test-card:hover {
        box-shadow: var(--box-shadow);
        transform: translateY(-2px);
    }
    .lab-pos-modal .lab-test-card.selected {
        outline: 3px solid var(--primary);
    }
    .lab-pos-modal .lab-test-icon {
        align-items: center;
        background: var(--primary-transparent);
        border-radius: 12px;
        color: var(--primary);
        display: inline-flex;
        height: 42px;
        justify-content: center;
        width: 42px;
    }
    .lab-cart-box {
        background: var(--light);
        border: 1px solid var(--border-color);
        border-radius: 16px;
        min-height: 338px;
        padding: 1rem;
    }
    .lab-cart-row {
        align-items: center;
        border-bottom: 1px solid var(--border-color);
        display: flex;
        gap: .75rem;
        justify-content: space-between;
        padding: .75rem 0;
    }
    .lab-theme-badge {
        background: var(--primary) !important;
        color: #fff !important;
    }
    .visit-card-actions {
        display: grid;
        gap: .4rem;
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
    .visit-card-actions .btn {
        align-items: center;
        display: inline-flex;
        justify-content: center;
        min-width: 0;
        padding-inline: .45rem;
        white-space: nowrap;
    }
    .visit-card-actions .btn-full {
        grid-column: 1 / -1;
    }
    .lab-print-area {
        display: none;
    }
    .visit-lab-report {
        display: none;
    }
    .visit-report-paper {
        background: #fff;
        color: #1d2a3b;
        font-family: Arial, sans-serif;
        margin: 0 auto;
        max-width: 900px;
        padding: 0 20px 28px;
    }
    .visit-report-top {
        background: #264f91;
        color: #fff;
        margin: 0 -20px 28px;
        padding: 34px 42px;
    }
    .visit-report-top h1 {
        font-size: 27px;
        font-weight: 800;
        margin: 0 0 12px;
        text-transform: uppercase;
    }
    .visit-report-chips {
        display: flex;
        flex-wrap: wrap;
        gap: 14px;
        margin-top: 22px;
    }
    .visit-report-chip {
        background: rgba(255, 255, 255, .16);
        border-radius: 999px;
        padding: 8px 16px;
    }
    .visit-report-chip.success {
        background: #d7f7df;
        color: #215c36;
        font-weight: 700;
        margin-left: auto;
    }
    .visit-report-section {
        border-left: 4px solid #2d72aa;
        font-size: 17px;
        font-weight: 800;
        margin: 28px 0 14px;
        padding: 8px 0 8px 18px;
        text-transform: uppercase;
    }
    .visit-report-card,
    .visit-report-total {
        background: #f8fbff;
        border: 1px solid #dbe5f0;
        border-radius: 16px;
        padding: 22px 28px;
    }
    .visit-report-info {
        display: grid;
        gap: 18px 46px;
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
    .visit-report-label {
        color: #6b7a90;
        font-size: 11px;
        font-weight: 800;
        letter-spacing: .04em;
        text-transform: uppercase;
    }
    .visit-report-value {
        font-size: 15px;
        font-weight: 800;
        margin-top: 5px;
    }
    .visit-report-table {
        border: 1px solid #dbe5f0;
        border-radius: 14px;
        overflow: hidden;
    }
    .visit-report-table table {
        border-collapse: collapse;
        width: 100%;
    }
    .visit-report-table th {
        background: #eef3f9;
        font-size: 13px;
        font-weight: 800;
        padding: 14px 12px;
        text-align: left;
    }
    .visit-report-table td {
        border-top: 1px solid #e2e8f0;
        padding: 14px 12px;
        vertical-align: top;
    }
    .visit-report-result {
        color: #00a996;
        font-weight: 800;
    }
    .visit-report-pill {
        background: #dff6df;
        border-radius: 999px;
        color: #238238;
        display: inline-block;
        font-size: 12px;
        font-weight: 800;
        padding: 5px 12px;
    }
    .visit-report-total {
        margin-top: 26px;
    }
    .visit-report-total-row {
        display: flex;
        justify-content: space-between;
        padding: 8px 0;
    }
    .visit-report-grand {
        border-top: 2px solid #dbe5f0;
        color: #193b78;
        font-size: 18px;
        font-weight: 900;
        margin-top: 10px;
        padding-top: 14px;
    }
    .visit-patient-profile-modal .modal-content {
        background: var(--light);
        border: 0;
    }
    .visit-patient-profile-modal .patient-profile-hero {
        background: var(--white);
        border: 1px solid var(--border-color);
        border-radius: 16px;
        overflow: hidden;
        position: relative;
    }
    .visit-patient-profile-modal .patient-profile-hero::after {
        background: linear-gradient(135deg, rgba(var(--primary-rgb), .28), var(--primary-transparent));
        clip-path: polygon(35% 0, 100% 0, 78% 100%, 58% 100%);
        content: "";
        inset: 0 0 0 auto;
        position: absolute;
        width: 46%;
    }
    .visit-patient-profile-modal .patient-profile-photo {
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
    .visit-patient-profile-modal .patient-profile-hero-content {
        position: relative;
        z-index: 1;
    }
    .visit-patient-profile-modal .patient-info-card {
        background: var(--white);
        border: 1px solid var(--border-color);
        border-radius: 14px;
        box-shadow: var(--box-shadow-sm);
    }
    .visit-patient-profile-modal .patient-info-item {
        align-items: center;
        display: flex;
        gap: .75rem;
        padding: .65rem 0;
    }
    .visit-patient-profile-modal .patient-info-icon {
        align-items: center;
        background: var(--light);
        border-radius: 10px;
        color: var(--primary);
        display: inline-flex;
        height: 36px;
        justify-content: center;
        width: 36px;
    }
    .visit-patient-profile-modal .patient-profile-tabs .nav-link {
        border: 0;
        color: var(--heading-color);
        font-weight: 700;
        padding-inline: 0;
        margin-right: 1.5rem;
    }
    .visit-patient-profile-modal .patient-profile-tabs .nav-link.active {
        background: transparent;
        border-bottom: 2px solid var(--primary);
        color: var(--primary);
    }
    @media print {
        body * {
            visibility: hidden !important;
        }
        body.is-printing-lab-list #labPrintArea,
        body.is-printing-lab-list #labPrintArea *,
        body.is-printing-lab-report #visitLabReportArea,
        body.is-printing-lab-report #visitLabReportArea * {
            visibility: visible !important;
        }
        body.is-printing-lab-list #labPrintArea,
        body.is-printing-lab-report #visitLabReportArea {
            background: #fff;
            color: #1d2a3b;
            display: block !important;
            inset: 0;
            padding: 0;
            position: absolute;
            width: 100%;
        }
        body.is-printing-lab-list #labPrintArea {
            color: #000;
            font-size: 12px;
            padding: 16px;
        }
        #labPrintArea table {
            border-collapse: collapse;
            width: 100%;
        }
        #labPrintArea th, #labPrintArea td {
            border-bottom: 1px solid #ddd;
            padding: 7px 4px;
            text-align: left;
        }
        #labPrintArea .print-title {
            font-size: 18px;
            font-weight: 800;
            text-align: center;
        }
        #labPrintArea .print-meta {
            display: flex;
            justify-content: space-between;
            margin-bottom: 4px;
        }
        @page {
            margin: 10mm;
        }
    }
</style>
<?php if ($isDoctorScoped): ?>
<div class="alert alert-info d-flex align-items-center gap-2 border border-info">
    <i class="ti ti-stethoscope"></i>
    <div>
        You are viewing only appointments and visits assigned to
        <strong><?php echo clinic_h($doctors[0]['Full_Name'] ?? 'your doctor profile'); ?></strong>.
    </div>
</div>
<?php endif; ?>
<div class="row g-3">
    <div class="col-xl-4">
        <div class="card clinic-card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Visit Queue</h5>
                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#visitModal">New Visit</button>
            </div>
            <div class="list-group list-group-flush">
                <?php foreach ($visits as $row): ?>
                <a class="list-group-item list-group-item-action<?php echo (int) $row['Visit_ID'] === $visitId ? ' active' : ''; ?>" href="visits.php?visit_id=<?php echo (int) $row['Visit_ID']; ?>">
                    <div class="fw-semibold"><?php echo clinic_h($row['Patient_Name'] ?? '-'); ?></div>
                    <div class="small <?php echo (int) $row['Visit_ID'] === $visitId ? 'text-white-50' : 'text-muted'; ?>"><?php echo clinic_h($row['Doctor_Name'] ?? 'No doctor'); ?> - <?php echo clinic_h($row['Visit_Date'] ?? '-'); ?></div>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="col-xl-8">
        <?php if ($workspace): ?>
        <div class="card clinic-card mb-3">
            <div class="card-body">
                <div class="d-flex justify-content-between flex-wrap gap-3">
                    <div>
                        <h4 class="fw-bold mb-1"><?php echo clinic_h($workspace['Patient_Name']); ?></h4>
                        <div class="text-muted"><?php echo clinic_h($workspace['Phone_Number'] ?? ''); ?> - <?php echo clinic_h($workspace['Patient_Type'] ?? ''); ?></div>
                    </div>
                    <div class="text-end">
                        <div class="small text-muted">Balance</div>
                        <h4 class="<?php echo (float) $workspace['Current_Balance'] > 0 ? 'text-danger' : 'text-success'; ?>"><?php echo clinic_money($workspace['Current_Balance']); ?></h4>
                    </div>
                </div>
                <div class="row g-2 mt-3">
                    <div class="col-md-4"><span class="clinic-workflow-pill d-block">Doctor: <strong><?php echo clinic_h($workspace['Doctor_Name'] ?? '-'); ?></strong></span></div>
                    <div class="col-md-4"><span class="clinic-workflow-pill d-block">Labs: <strong><?php echo (int) $workspace['lab_count']; ?></strong></span></div>
                    <div class="col-md-4"><span class="clinic-workflow-pill d-block">Prescriptions: <strong><?php echo (int) $workspace['prescription_count']; ?></strong></span></div>
                </div>
                <div class="border rounded-3 p-3 mt-3 bg-light">
                    <div class="small text-muted mb-1">Clinical notes</div>
                    <?php echo clinic_h($workspace['Notes'] ?: 'No notes recorded.'); ?>
                </div>
            </div>
        </div>

        <?php if ($patientIdForWorkspace > 0 && $patientHistoryAllowed): ?>
        <div class="card clinic-card mb-3 border-secondary-subtle">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2 py-3">
                <div>
                    <h6 class="mb-0"><i class="ti ti-history me-1"></i> Patient history</h6>
                    <div class="small text-muted">Visits, labs, payments, and pharmacy — newest first.</div>
                    <div class="small text-muted">Patient history (visible to doctors).</div>
                </div>
                <?php if ($patientHistoryProfile): ?>
                <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#visitPatientProfileModal">
                    <i class="ti ti-user-heart me-1"></i> Full profile
                </button>
                <?php endif; ?>
            </div>
            <div class="card-body pt-0">
                <?php if ($patientHistoryProfile): ?>
                <div class="d-flex flex-wrap gap-3 small text-muted mb-3 pb-2 border-bottom">
                    <span>Total visits: <strong class="text-body"><?php echo (int) ($patientHistoryProfile['visit_count'] ?? 0); ?></strong></span>
                    <span>Last visit: <strong class="text-body"><?php echo clinic_h((string) ($patientHistoryProfile['last_visit_date'] ?? '—')); ?></strong></span>
                    <span>Balance: <strong class="text-body"><?php echo clinic_money($patientHistoryProfile['Current_Balance'] ?? 0); ?></strong></span>
                </div>
                <?php endif; ?>
                <?php if ($patientHistoryTimeline !== []): ?>
                <div class="table-responsive rounded-3 border" style="max-height: 340px;">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light sticky-top"><tr><th>When</th><th>Type</th><th>Details</th><th>Doctor / Related</th></tr></thead>
                        <tbody>
                            <?php foreach (array_slice($patientHistoryTimeline, 0, 50) as $ev): ?>
                            <tr>
                                <td class="text-nowrap small"><?php echo clinic_h((string) ($ev['event_at'] ?? '')); ?></td>
                                <td><span class="badge rounded-pill text-bg-light border"><?php echo clinic_h((string) ($ev['event_type'] ?? '')); ?></span></td>
                                <td class="small"><?php echo nl2br(clinic_h((string) ($ev['description'] ?? ''))); ?></td>
                                <td class="small text-muted"><?php echo clinic_h((string) ($ev['related_name'] ?? '')); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if (count($patientHistoryTimeline) > 50): ?>
                <p class="small text-muted mt-2 mb-0">Showing 50 of <?php echo count($patientHistoryTimeline); ?> events. Use <strong>Full profile</strong> above for the detailed modal.</p>
                <?php endif; ?>
                <?php else: ?>
                <p class="text-muted small mb-0">No past visits or transactions recorded for this patient yet.</p>
                <?php endif; ?>
            </div>
        </div>
        <?php elseif ($patientIdForWorkspace > 0 && $isDoctorScoped): ?>
        <div class="alert alert-warning py-2 small mb-3 border border-warning">
            Patient history is hidden because this patient is not linked to your appointments or visits.
        </div>
        <?php endif; ?>

        <div class="d-flex flex-wrap gap-2 mb-3">
            <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#labModal">Request Lab</button>
            <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#prescriptionModal">Add Prescription</button>
            <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#nursingModal">Nursing Service</button>
            <a class="btn btn-success" href="payments.php?patient_id=<?php echo (int) $workspace['Patient_ID']; ?>">Collect Payment</a>
        </div>

        <div class="row g-3">
            <div class="col-md-4"><div class="card clinic-card h-100"><div class="card-header">
                <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
                    <h6 class="mb-0">Labs</h6>
                    <div class="form-check mb-0">
                        <input class="form-check-input" type="checkbox" id="selectAllLabs">
                        <label class="form-check-label small" for="selectAllLabs">All</label>
                    </div>
                </div>
                <div class="visit-card-actions">
                    <button class="btn btn-sm btn-light border" type="button" id="printCurrentLabs"><i class="ti ti-printer me-1"></i>Print</button>
                    <?php if ($completedLabRows !== []): ?>
                    <button class="btn btn-sm btn-outline-primary" type="button" id="printCompletedLabResults"><i class="ti ti-file-report me-1"></i>Results</button>
                    <?php endif; ?>
                    <button class="btn btn-sm btn-light border" type="button" data-bs-toggle="modal" data-bs-target="#labModal" data-lab-mode="manage">Edit all</button>
                    <button class="btn btn-sm btn-light border text-danger" type="button" data-bs-toggle="modal" data-bs-target="#labModal" data-lab-mode="delete-all">Delete all</button>
                </div>
            </div><div class="card-body">
                <?php foreach ($labRows as $row): ?>
                <div class="border rounded-3 p-2 mb-2">
                    <div class="d-flex justify-content-between gap-2">
                        <div>
                            <?php if (($row['Status'] ?? 'Pending') !== 'Completed'): ?>
                            <div class="form-check mb-1">
                                <input
                                    class="form-check-input lab-row-check"
                                    type="checkbox"
                                    value="<?php echo (int) $row['Result_ID']; ?>"
                                    data-test-name="<?php echo clinic_h($row['Test_Name'] ?? ''); ?>"
                                    id="labCheck<?php echo (int) $row['Result_ID']; ?>"
                                >
                                <label class="form-check-label fw-semibold" for="labCheck<?php echo (int) $row['Result_ID']; ?>"><?php echo clinic_h($row['Test_Name'] ?? '-'); ?></label>
                            </div>
                            <?php else: ?>
                            <strong><?php echo clinic_h($row['Test_Name'] ?? '-'); ?></strong><br>
                            <?php endif; ?>
                            <?php echo clinic_status_badge((string) ($row['Status'] ?? 'Pending')); ?>
                        </div>
                        <?php if (($row['Status'] ?? 'Pending') !== 'Completed'): ?>
                        <div class="d-flex gap-1 align-items-start">
                            <button
                                class="btn btn-sm btn-light border"
                                type="button"
                                data-bs-toggle="modal"
                                data-bs-target="#editLabModal"
                                data-result-id="<?php echo (int) $row['Result_ID']; ?>"
                                data-test-id="<?php echo (int) $row['Test_ID']; ?>"
                                data-test-name="<?php echo clinic_h($row['Test_Name'] ?? ''); ?>"
                            ><i class="ti ti-edit"></i></button>
                            <form method="post" class="lab-delete-form">
                                <input type="hidden" name="csrf_token" value="<?php echo clinic_h(clinic_csrf_token()); ?>">
                                <input type="hidden" name="action" value="delete_lab">
                                <input type="hidden" name="Visit_ID" value="<?php echo (int) $visitId; ?>">
                                <input type="hidden" name="Result_ID" value="<?php echo (int) $row['Result_ID']; ?>">
                                <button class="btn btn-sm btn-light border text-danger" type="submit"><i class="ti ti-trash"></i></button>
                            </form>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if ($labRows === []): ?><p class="text-muted mb-0">No labs.</p><?php endif; ?>
            </div></div></div>
            <div class="col-md-4"><div class="card clinic-card h-100"><div class="card-header">
                <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
                    <h6 class="mb-0">Prescriptions</h6>
                    <span class="badge lab-theme-badge"><?php echo count($prescriptions); ?></span>
                </div>
                <div class="visit-card-actions">
                    <button class="btn btn-sm btn-light border" type="button" id="printCurrentRx"><i class="ti ti-printer me-1"></i>Print</button>
                    <button class="btn btn-sm btn-light border" type="button" data-bs-toggle="modal" data-bs-target="#prescriptionModal" data-rx-mode="manage">Edit all</button>
                    <button class="btn btn-sm btn-light border text-danger btn-full" type="button" data-bs-toggle="modal" data-bs-target="#prescriptionModal" data-rx-mode="delete-all">Delete all</button>
                </div>
            </div><div class="card-body">
                <?php foreach ($prescriptions as $row): ?>
                <div class="border rounded-3 p-2 mb-2">
                    <div class="d-flex justify-content-between gap-2">
                        <div>
                            <strong><?php echo clinic_h($row['Medicine_Name'] ?? '-'); ?></strong><br>
                            <span class="small text-muted"><?php echo clinic_h($row['Dosage'] ?? ''); ?></span>
                        </div>
                        <div class="d-flex gap-1 align-items-start">
                            <button
                                class="btn btn-sm btn-light border"
                                type="button"
                                data-bs-toggle="modal"
                                data-bs-target="#editPrescriptionModal"
                                data-prescription-id="<?php echo (int) $row['Prescription_ID']; ?>"
                                data-medicine-id="<?php echo (int) $row['Medicine_ID']; ?>"
                                data-medicine-name="<?php echo clinic_h($row['Medicine_Name'] ?? ''); ?>"
                                data-dosage="<?php echo clinic_h($row['Dosage'] ?? ''); ?>"
                            ><i class="ti ti-edit"></i></button>
                            <form method="post" class="rx-delete-form">
                                <input type="hidden" name="csrf_token" value="<?php echo clinic_h(clinic_csrf_token()); ?>">
                                <input type="hidden" name="action" value="delete_prescription">
                                <input type="hidden" name="Visit_ID" value="<?php echo (int) $visitId; ?>">
                                <input type="hidden" name="Prescription_ID" value="<?php echo (int) $row['Prescription_ID']; ?>">
                                <button class="btn btn-sm btn-light border text-danger" type="submit"><i class="ti ti-trash"></i></button>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if ($prescriptions === []): ?><p class="text-muted mb-0">No prescriptions.</p><?php endif; ?>
            </div></div></div>
            <div class="col-md-4"><div class="card clinic-card h-100"><div class="card-header"><h6 class="mb-0">Nursing</h6></div><div class="card-body">
                <?php foreach ($nursing as $row): ?><div class="mb-2"><strong><?php echo clinic_h($row['Service_Name'] ?? '-'); ?></strong><br><span class="small text-muted"><?php echo clinic_h($row['Record_Date'] ?? ''); ?></span></div><?php endforeach; ?>
                <?php if ($nursing === []): ?><p class="text-muted mb-0">No nursing records.</p><?php endif; ?>
            </div></div></div>
        </div>
        <?php else: ?>
        <div class="alert alert-light border text-center">No visit selected. Create a visit to begin.</div>
        <?php endif; ?>
    </div>
</div>

<?php if ($patientIdForWorkspace > 0 && $patientHistoryAllowed && $patientHistoryProfile): ?>
<?php $pf = $patientHistoryProfile; ?>
<div class="modal fade visit-patient-profile-modal patient-profile-modal" id="visitPatientProfileModal" tabindex="-1" aria-labelledby="visitPatientProfileModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-fullscreen-lg-down modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0 align-items-start">
                <div>
                    <h5 class="modal-title fw-bold" id="visitPatientProfileModalLabel">Patient profile</h5>
                    <div class="small text-muted">Patient profile — modal (no separate page).</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body pt-2">
                <div class="patient-profile-hero p-3 mb-3">
                    <div class="patient-profile-hero-content d-flex justify-content-between align-items-center flex-wrap gap-3">
                        <div class="d-flex align-items-center gap-3">
                            <span class="patient-profile-photo"><?php echo clinic_h(substr((string) ($pf['Full_Name'] ?? 'P'), 0, 1)); ?></span>
                            <div>
                                <div class="small text-primary fw-bold">#PT<?php echo str_pad((string) (int) ($pf['Patient_ID'] ?? 0), 4, '0', STR_PAD_LEFT); ?></div>
                                <h4 class="fw-bold mb-1"><?php echo clinic_h($pf['Full_Name'] ?? '-'); ?></h4>
                                <div class="text-muted small">
                                    <?php echo clinic_h($pf['Phone_Number'] ?? 'No phone'); ?>
                                    <span class="mx-2">-</span>
                                    Last visited: <?php echo clinic_h($pf['last_visit_date'] ?: '-'); ?>
                                </div>
                            </div>
                        </div>
                        <div class="d-flex flex-wrap gap-2">
                            <a class="btn btn-sm btn-light border" href="patients.php?profile_id=<?php echo (int) $pf['Patient_ID']; ?>">
                                <i class="ti ti-external-link me-1"></i> Patient Desk
                            </a>
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
                                    <div><div class="small text-muted">Phone</div><strong><?php echo clinic_h($pf['Phone_Number'] ?? '-'); ?></strong></div>
                                </div>
                                <div class="col-sm-6 patient-info-item">
                                    <span class="patient-info-icon"><i class="ti ti-gender-bigender"></i></span>
                                    <div><div class="small text-muted">Sex / Age</div><strong><?php echo clinic_h(($pf['Sex'] ?? '-') . ' · ' . ($pf['Age_Group'] ?? '-')); ?></strong></div>
                                </div>
                                <div class="col-sm-6 patient-info-item">
                                    <span class="patient-info-icon"><i class="ti ti-user-star"></i></span>
                                    <div><div class="small text-muted">Type</div><strong><?php echo clinic_h($pf['Patient_Type'] ?? '-'); ?></strong></div>
                                </div>
                                <div class="col-sm-6 patient-info-item">
                                    <span class="patient-info-icon"><i class="ti ti-users"></i></span>
                                    <div><div class="small text-muted">Guarantor</div><strong><?php echo clinic_h($pf['Guarantor_Name'] ?? '-'); ?></strong></div>
                                </div>
                                <div class="col-sm-6 patient-info-item">
                                    <span class="patient-info-icon"><i class="ti ti-calendar"></i></span>
                                    <div><div class="small text-muted">Registered</div><strong><?php echo clinic_h($pf['Created_At'] ?? '-'); ?></strong></div>
                                </div>
                                <div class="col-sm-6 patient-info-item">
                                    <span class="patient-info-icon"><i class="ti ti-credit-card"></i></span>
                                    <div><div class="small text-muted">Credit limit</div><strong><?php echo clinic_money($pf['Credit_Limit'] ?? 0); ?></strong></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-7">
                        <div class="patient-info-card h-100 p-3">
                            <h6 class="fw-bold mb-3"><i class="ti ti-chart-dots me-1"></i>Summary</h6>
                            <div class="row">
                                <div class="col-md-6 patient-info-item">
                                    <span class="patient-info-icon"><i class="ti ti-wallet"></i></span>
                                    <div><div class="small text-muted">Balance</div><strong class="<?php echo (float) ($pf['Current_Balance'] ?? 0) > 0 ? 'text-danger' : 'text-success'; ?>"><?php echo clinic_money($pf['Current_Balance'] ?? 0); ?></strong></div>
                                </div>
                                <div class="col-md-6 patient-info-item">
                                    <span class="patient-info-icon"><i class="ti ti-stethoscope"></i></span>
                                    <div><div class="small text-muted">Visits</div><strong><?php echo (int) ($pf['visit_count'] ?? 0); ?></strong></div>
                                </div>
                                <div class="col-md-6 patient-info-item">
                                    <span class="patient-info-icon"><i class="ti ti-cash"></i></span>
                                    <div><div class="small text-muted">Payments</div><strong><?php echo (int) ($pf['payment_count'] ?? 0); ?> / <?php echo clinic_money($pf['total_paid'] ?? 0); ?></strong></div>
                                </div>
                                <div class="col-md-6 patient-info-item">
                                    <span class="patient-info-icon"><i class="ti ti-clock"></i></span>
                                    <div><div class="small text-muted">Last visit</div><strong><?php echo clinic_h($pf['last_visit_date'] ?: '-'); ?></strong></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <ul class="nav nav-tabs patient-profile-tabs mb-3" role="tablist">
                    <li class="nav-item" role="presentation"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#visitPatientProfileActivity" type="button">Appointments</button></li>
                    <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#visitPatientProfileTransactions" type="button">Transactions</button></li>
                </ul>
                <div class="tab-content">
                    <div class="tab-pane fade show active" id="visitPatientProfileActivity">
                        <div class="patient-info-card table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead><tr><th>Date &amp; Time</th><th>Type</th><th>Doctor / Related</th><th>Status</th></tr></thead>
                                <tbody>
                                    <?php foreach ($visitPatientProfileActivityEvents as $event): ?>
                                    <tr>
                                        <td><?php echo clinic_h((string) ($event['event_at'] ?? '-')); ?></td>
                                        <td><strong><?php echo clinic_h((string) ($event['event_type'] ?? '-')); ?> #<?php echo (int) ($event['event_id'] ?? 0); ?></strong><div class="small text-muted"><?php echo clinic_h((string) ($event['description'] ?? '')); ?></div></td>
                                        <td><?php echo clinic_h((string) ($event['related_name'] ?? '-')); ?></td>
                                        <td><span class="badge text-bg-light">Recorded</span></td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if ($visitPatientProfileActivityEvents === []): ?><tr><td class="text-center text-muted py-4" colspan="4">No appointments or activity found.</td></tr><?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="visitPatientProfileTransactions">
                        <div class="patient-info-card table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead><tr><th>Date &amp; Time</th><th>Description</th><th>Account</th><th>Status</th></tr></thead>
                                <tbody>
                                    <?php foreach ($visitPatientProfilePaymentEvents as $event): ?>
                                    <tr>
                                        <td><?php echo clinic_h((string) ($event['event_at'] ?? '-')); ?></td>
                                        <td><strong>Payment #<?php echo (int) ($event['event_id'] ?? 0); ?></strong><div class="small text-muted"><?php echo clinic_h((string) ($event['description'] ?? '')); ?></div></td>
                                        <td><?php echo clinic_h((string) ($event['related_name'] ?? '-')); ?></td>
                                        <td><span class="badge text-bg-success">Paid</span></td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if ($visitPatientProfilePaymentEvents === []): ?><tr><td class="text-center text-muted py-4" colspan="4">No transactions found.</td></tr><?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="modal fade" id="visitModal" tabindex="-1">
    <div class="modal-dialog"><form method="post" class="modal-content">
        <div class="modal-header"><h5 class="modal-title">Create Visit</h5><button class="btn-close" type="button" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <input type="hidden" name="csrf_token" value="<?php echo clinic_h(clinic_csrf_token()); ?>"><input type="hidden" name="action" value="create_visit">
            <?php if ($selectedAppointment > 0): ?>
            <div class="alert alert-info py-2 border border-info">This visit is linked to appointment #<?php echo (int) $selectedAppointment; ?>. Creating the visit will mark that appointment as completed.</div>
            <?php endif; ?>
            <div class="mb-3"><label class="form-label">Patient</label><select class="form-select" name="Patient_ID" required><?php clinic_select_options($patients, 'Patient_ID', 'Full_Name', $selectedPatient); ?></select></div>
            <?php if ($isDoctorScoped): ?>
            <input type="hidden" name="Doctor_ID" value="<?php echo (int) ($currentDoctorId ?? 0); ?>">
            <div class="mb-3">
                <label class="form-label">Doctor</label>
                <div class="form-control bg-light d-flex align-items-center"><?php echo clinic_h($doctors[0]['Full_Name'] ?? 'Linked doctor'); ?></div>
            </div>
            <?php else: ?>
            <div class="mb-3"><label class="form-label">Doctor</label><select class="form-select" name="Doctor_ID"><option value="">No doctor</option><?php clinic_select_options($doctors, 'Doctor_ID', 'Full_Name'); ?></select></div>
            <?php endif; ?>
            <div class="mb-0"><label class="form-label">Notes</label><textarea class="form-control" name="Notes" rows="4"></textarea></div>
            <input type="hidden" name="Appointment_ID" value="<?php echo (int) $selectedAppointment; ?>">
        </div>
        <div class="modal-footer"><button class="btn btn-light" type="button" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Create</button></div>
    </form></div>
</div>

<?php if ($workspace): ?>
<div class="modal fade lab-pos-modal" id="labModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <form method="post" class="modal-content" id="labRequestForm">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title" id="labModalTitle">Lab Request Cart</h5>
                    <div class="small text-muted" id="labModalSubtitle"><?php echo clinic_h($workspace['Patient_Name']); ?> - add multiple tests in one request</div>
                </div>
                <button class="btn-close" type="button" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?php echo clinic_h(clinic_csrf_token()); ?>">
                <input type="hidden" name="action" value="add_lab" id="labRequestAction">
                <input type="hidden" name="Visit_ID" value="<?php echo (int) $visitId; ?>">
                <div class="alert alert-info d-none border border-info" id="labManageHint"></div>
                <div class="alert alert-danger d-none border border-danger" id="labDeletedPreview">
                    <strong>Deleted labs</strong>
                    <div class="small mt-1" id="labDeletedList"></div>
                </div>

                <div class="row g-3">
                    <div class="col-lg-8">
                        <div class="d-flex justify-content-between align-items-center gap-2 mb-3">
                            <strong>Available Lab Tests</strong>
                            <div class="input-group input-group-sm" style="max-width: 280px;">
                                <span class="input-group-text bg-white"><i class="ti ti-search"></i></span>
                                <input class="form-control" id="labTestSearch" placeholder="Search test">
                            </div>
                        </div>
                        <div class="row g-3" id="labTestGrid">
                            <?php foreach ($labTests as $test): ?>
                            <div class="col-xl-4 col-md-6 lab-test-item" data-name="<?php echo clinic_h(strtolower((string) $test['Test_Name'])); ?>">
                                <div class="lab-test-card" data-id="<?php echo (int) $test['Test_ID']; ?>" data-name="<?php echo clinic_h($test['Test_Name']); ?>" data-price="<?php echo clinic_h($test['Price']); ?>">
                                    <span class="lab-test-icon mb-3"><i class="ti ti-microscope fs-22"></i></span>
                                    <div class="fw-bold"><?php echo clinic_h($test['Test_Name']); ?></div>
                                    <div class="small text-muted">Lab investigation</div>
                                    <div class="fw-semibold mt-2"><?php echo clinic_money($test['Price']); ?></div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <div class="lab-cart-box">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <strong>Selected Tests <span class="badge lab-theme-badge" id="labCartCount">0</span></strong>
                                <div class="d-flex gap-1">
                                    <button class="btn btn-sm btn-light border" type="button" id="printLabCart"><i class="ti ti-printer"></i></button>
                                    <button class="btn btn-sm btn-light border" type="button" id="clearLabCart"><i class="ti ti-trash"></i></button>
                                </div>
                            </div>
                            <div id="labCartRows">
                                <div class="text-center text-muted py-5">Click tests to add them.</div>
                            </div>
                            <div class="border-top pt-3 mt-3 d-flex justify-content-between">
                                <span>Total Estimate</span>
                                <strong class="text-success" id="labCartTotal">$0.00</strong>
                            </div>
                            <div id="labCartInputs"></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-light" type="button" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-primary" type="submit" id="labRequestSubmit">Send Lab Request</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="editLabModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="post" class="modal-content" id="editLabForm">
            <div class="modal-header">
                <h5 class="modal-title">Update Lab Request</h5>
                <button class="btn-close" type="button" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?php echo clinic_h(clinic_csrf_token()); ?>">
                <input type="hidden" name="action" value="update_lab">
                <input type="hidden" name="Visit_ID" value="<?php echo (int) $visitId; ?>">
                <input type="hidden" name="Result_ID" id="editLabResultId">
                <div class="alert alert-light border">
                    Current request: <strong id="editLabCurrentTest">-</strong>
                </div>
                <label class="form-label">Change lab test</label>
                <select class="form-select" name="Test_ID" id="editLabTestId" required>
                    <?php clinic_select_options($labTests, 'Test_ID', 'Test_Name'); ?>
                </select>
            </div>
            <div class="modal-footer">
                <button class="btn btn-light" type="button" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-primary" type="submit">Update Request</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="bulkEditLabModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="post" class="modal-content" id="bulkEditLabForm">
            <div class="modal-header">
                <h5 class="modal-title">Bulk Update Lab Requests</h5>
                <button class="btn-close" type="button" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?php echo clinic_h(clinic_csrf_token()); ?>">
                <input type="hidden" name="action" value="bulk_update_lab">
                <input type="hidden" name="Visit_ID" value="<?php echo (int) $visitId; ?>">
                <div class="alert alert-light border">
                    Selected requests: <strong id="bulkEditLabCount">0</strong>
                    <div class="small text-muted" id="bulkEditLabNames"></div>
                </div>
                <label class="form-label">Apply this lab test to all selected</label>
                <select class="form-select" name="Test_ID" required>
                    <?php clinic_select_options($labTests, 'Test_ID', 'Test_Name'); ?>
                </select>
                <div id="bulkEditLabInputs"></div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-light" type="button" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-primary" type="submit">Update Selected</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="bulkDeleteLabModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="post" class="modal-content" id="bulkDeleteLabForm">
            <div class="modal-header">
                <h5 class="modal-title">Delete Selected Lab Requests</h5>
                <button class="btn-close" type="button" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?php echo clinic_h(clinic_csrf_token()); ?>">
                <input type="hidden" name="action" value="bulk_delete_lab">
                <input type="hidden" name="Visit_ID" value="<?php echo (int) $visitId; ?>">
                <div class="alert alert-danger border border-danger">
                    Delete <strong id="bulkDeleteLabCount">0</strong> selected pending lab request(s)?
                    <div class="small mt-1" id="bulkDeleteLabNames"></div>
                </div>
                <div id="bulkDeleteLabInputs"></div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-light" type="button" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-danger" type="submit">Delete Selected</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="editPrescriptionModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="post" class="modal-content" id="editPrescriptionForm">
            <div class="modal-header">
                <h5 class="modal-title">Update Prescription</h5>
                <button class="btn-close" type="button" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?php echo clinic_h(clinic_csrf_token()); ?>">
                <input type="hidden" name="action" value="update_prescription">
                <input type="hidden" name="Visit_ID" value="<?php echo (int) $visitId; ?>">
                <input type="hidden" name="Prescription_ID" id="editPrescriptionId">
                <div class="alert alert-light border">
                    Current medicine: <strong id="editPrescriptionCurrentMedicine">-</strong>
                </div>
                <label class="form-label">Medicine</label>
                <select class="form-select mb-3" name="Medicine_ID" id="editPrescriptionMedicineId" required>
                    <?php clinic_select_options($medicines, 'Medicine_ID', 'Medicine_Name'); ?>
                </select>
                <label class="form-label">Dosage</label>
                <input class="form-control" name="Dosage" id="editPrescriptionDosage" placeholder="Dosage e.g. 1 tab x 3 days">
            </div>
            <div class="modal-footer">
                <button class="btn btn-light" type="button" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-primary" type="submit">Update Prescription</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade lab-pos-modal" id="prescriptionModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <form method="post" class="modal-content" id="prescriptionForm">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title" id="rxModalTitle">Prescription Cart</h5>
                    <div class="small text-muted" id="rxModalSubtitle"><?php echo clinic_h($workspace['Patient_Name']); ?> - add multiple medicines in one prescription</div>
                </div>
                <button class="btn-close" type="button" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?php echo clinic_h(clinic_csrf_token()); ?>">
                <input type="hidden" name="action" value="add_prescription" id="rxRequestAction">
                <input type="hidden" name="Visit_ID" value="<?php echo (int) $visitId; ?>">
                <div class="alert alert-info d-none" id="rxManageHint"></div>
                <div class="alert alert-danger d-none" id="rxDeletedPreview">
                    <strong>Deleted prescriptions</strong>
                    <div class="small mt-1" id="rxDeletedList"></div>
                </div>

                <div class="row g-3">
                    <div class="col-lg-8">
                        <div class="d-flex justify-content-between align-items-center gap-2 mb-3">
                            <strong>Available Medicines</strong>
                            <div class="input-group input-group-sm" style="max-width: 280px;">
                                <span class="input-group-text bg-white"><i class="ti ti-search"></i></span>
                                <input class="form-control" id="medicineRxSearch" placeholder="Search medicine">
                            </div>
                        </div>
                        <div class="row g-3" id="medicineRxGrid">
                            <?php foreach ($medicines as $medicine): ?>
                            <?php $stock = (int) ($medicine['Stock_Quantity'] ?? 0); ?>
                            <div class="col-xl-4 col-md-6 medicine-rx-item" data-name="<?php echo clinic_h(strtolower((string) $medicine['Medicine_Name'])); ?>">
                                <div
                                    class="lab-test-card medicine-rx-card"
                                    data-id="<?php echo (int) $medicine['Medicine_ID']; ?>"
                                    data-name="<?php echo clinic_h($medicine['Medicine_Name']); ?>"
                                    data-price="<?php echo clinic_h($medicine['Price'] ?? 0); ?>"
                                    data-stock="<?php echo $stock; ?>"
                                >
                                    <span class="lab-test-icon mb-3"><i class="ti ti-pill fs-22"></i></span>
                                    <div class="fw-bold"><?php echo clinic_h($medicine['Medicine_Name']); ?></div>
                                    <div class="small text-muted">Stock: <?php echo $stock; ?> / <?php echo clinic_money($medicine['Price'] ?? 0); ?></div>
                                    <div class="fw-semibold mt-2"><?php echo clinic_h($medicine['Expiry_Date'] ? 'Exp: ' . $medicine['Expiry_Date'] : 'No expiry'); ?></div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <div class="lab-cart-box">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <strong>Selected Medicines <span class="badge lab-theme-badge" id="rxCartCount">0</span></strong>
                                <div class="d-flex gap-1">
                                    <button class="btn btn-sm btn-light border" type="button" id="printRxCart"><i class="ti ti-printer"></i></button>
                                    <button class="btn btn-sm btn-light border" type="button" id="clearRxCart"><i class="ti ti-trash"></i></button>
                                </div>
                            </div>
                            <div id="rxCartRows">
                                <div class="text-center text-muted py-5">Click medicines to add them.</div>
                            </div>
                            <div id="rxCartInputs"></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-light" type="button" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-primary" type="submit" id="rxRequestSubmit">Save Prescription</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="nursingModal" tabindex="-1"><div class="modal-dialog"><form method="post" class="modal-content">
    <div class="modal-header"><h5 class="modal-title">Record Nursing Service</h5><button class="btn-close" type="button" data-bs-dismiss="modal"></button></div>
    <div class="modal-body"><input type="hidden" name="csrf_token" value="<?php echo clinic_h(clinic_csrf_token()); ?>"><input type="hidden" name="action" value="add_nursing"><input type="hidden" name="Visit_ID" value="<?php echo (int) $visitId; ?>"><label class="form-label">Service</label><select class="form-select mb-3" name="Service_ID" required><?php clinic_select_options($services, 'Service_ID', 'Service_Name'); ?></select><label class="form-label">Medicine Used</label><input class="form-control" name="Medicine_Used"></div>
    <div class="modal-footer"><button class="btn btn-primary">Record</button></div>
</form></div></div>
<?php
$firstCompletedLab = $completedLabRows[0] ?? [];
$completedRequestDate = (string) ($firstCompletedLab['Visit_Date'] ?? $workspace['Visit_Date'] ?? date('Y-m-d H:i'));
$completedReportNo = 'REQ-' . str_pad((string) $visitId, 5, '0', STR_PAD_LEFT);
?>
<div id="visitLabReportArea" class="visit-lab-report visit-report-paper">
    <div class="visit-report-top">
        <h1><i class="ti ti-microscope"></i> Laboratory Test Report</h1>
        <div>Clinical Diagnostic Center</div>
        <div class="visit-report-chips">
            <span class="visit-report-chip"><?php echo clinic_h($completedReportNo); ?></span>
            <span class="visit-report-chip"><?php echo clinic_h(date('d M Y, h:i A', strtotime($completedRequestDate) ?: time())); ?></span>
            <span class="visit-report-chip">Reported: <?php echo clinic_h(date('d M Y')); ?></span>
            <span class="visit-report-chip success">Completed</span>
        </div>
    </div>

    <div class="visit-report-card">
        <div class="visit-report-section" style="margin-top:0;">Patient Information</div>
        <div class="visit-report-info">
            <div>
                <div class="visit-report-label">Full Name</div>
                <div class="visit-report-value"><?php echo clinic_h($workspace['Patient_Name'] ?? '-'); ?></div>
            </div>
            <div>
                <div class="visit-report-label">Patient Type</div>
                <div class="visit-report-value"><?php echo clinic_h($workspace['Patient_Type'] ?? '-'); ?></div>
            </div>
            <div>
                <div class="visit-report-label">Phone</div>
                <div class="visit-report-value"><?php echo clinic_h($workspace['Phone_Number'] ?? '-'); ?></div>
            </div>
            <div>
                <div class="visit-report-label">Requesting Doctor</div>
                <div class="visit-report-value"><?php echo clinic_h($workspace['Doctor_Name'] ?? '-'); ?></div>
            </div>
            <div>
                <div class="visit-report-label">Visit</div>
                <div class="visit-report-value">#<?php echo (int) $visitId; ?></div>
            </div>
            <div>
                <div class="visit-report-label">Visit Date</div>
                <div class="visit-report-value"><?php echo clinic_h($workspace['Visit_Date'] ?? $completedRequestDate); ?></div>
            </div>
        </div>
    </div>

    <?php if ($completedLabRows !== []): ?>
    <div class="visit-report-section">Laboratory Results</div>
    <div class="visit-report-table">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Test Name</th>
                    <th>Result</th>
                    <th>Normal Range</th>
                    <th>Reference</th>
                    <th>Remarks</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($completedLabRows as $index => $row): ?>
                <tr>
                    <td><?php echo $index + 1; ?></td>
                    <td><strong><?php echo clinic_h($row['Test_Name'] ?? '-'); ?></strong></td>
                    <td class="visit-report-result"><?php echo nl2br(clinic_h($row['Result_Details'] ?: '-')); ?></td>
                    <td>Normal</td>
                    <td><span class="visit-report-pill">Normal</span></td>
                    <td>-</td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="visit-report-total">
        <div class="visit-report-total-row">
            <span>Subtotal:</span>
            <strong><?php echo clinic_money($completedLabSubtotal); ?></strong>
        </div>
        <div class="visit-report-total-row">
            <span>Tax (0%):</span>
            <strong><?php echo clinic_money(0); ?></strong>
        </div>
        <div class="visit-report-total-row visit-report-grand">
            <span>Total Amount:</span>
            <span><?php echo clinic_money($completedLabSubtotal); ?></span>
        </div>
    </div>
    <?php else: ?>
    <p>No completed lab results to print.</p>
    <?php endif; ?>
</div>
<div id="labPrintArea" class="lab-print-area">
    <div class="print-title" id="labPrintTitle">Lab Requests</div>
    <hr>
    <div class="print-meta"><span>Patient</span><strong id="labPrintPatient"></strong></div>
    <div class="print-meta"><span>Visit</span><strong id="labPrintVisit"></strong></div>
    <div class="print-meta"><span>Date</span><strong id="labPrintDate"></strong></div>
    <div class="small mb-2" id="labPrintSubtitle"></div>
    <table>
        <thead>
            <tr><th>#</th><th>Item</th><th>Detail</th></tr>
        </thead>
        <tbody id="labPrintRows"></tbody>
    </table>
</div>
<script>
var labCart = {};
var labCartMode = 'add';
var rxCart = {};
var rxCartMode = 'add';
var currentLabPrintRows = <?php echo json_encode(array_values(array_map(static fn ($row) => [
    'name' => (string) ($row['Test_Name'] ?? 'Lab request'),
    'status' => (string) ($row['Status'] ?? 'Pending'),
], $labRows)), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
var currentRxPrintRows = <?php echo json_encode(array_values(array_map(static fn ($row) => [
    'name' => (string) ($row['Medicine_Name'] ?? 'Prescription'),
    'status' => (string) ($row['Dosage'] ?? ''),
], $prescriptions)), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
var existingPendingLabs = <?php echo json_encode(array_values(array_map(static fn ($row) => [
    'testId' => (int) ($row['Test_ID'] ?? 0),
    'name' => (string) ($row['Test_Name'] ?? 'Lab request'),
], array_filter($labRows, static fn ($row) => ($row['Status'] ?? 'Pending') !== 'Completed'))), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
var existingPrescriptions = <?php echo json_encode(array_values(array_map(static fn ($row) => [
    'medicineId' => (int) ($row['Medicine_ID'] ?? 0),
    'name' => (string) ($row['Medicine_Name'] ?? 'Prescription'),
    'dosage' => (string) ($row['Dosage'] ?? ''),
], $prescriptions)), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;

function labMoney(value) {
    return '$' + Number(value || 0).toFixed(2);
}

function htmlEscape(value) {
    return String(value || '').replace(/[&<>"']/g, function (char) {
        return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[char];
    });
}

function labSwal(options) {
    if (window.Swal) {
        return Swal.fire(options);
    }
    if (options.icon === 'warning' && options.showCancelButton) {
        return Promise.resolve({ isConfirmed: confirm(options.text || options.title || 'Continue?') });
    }
    alert(options.text || options.title || '');
    return Promise.resolve({ isConfirmed: true });
}

function confirmLabForm(form, options) {
    labSwal(Object.assign({
        showCancelButton: true,
        confirmButtonColor: 'var(--primary)',
        cancelButtonColor: '#6c757d'
    }, options)).then(function (result) {
        if (result.isConfirmed) {
            form.dataset.swalConfirmed = '1';
            form.submit();
        }
    });
}

function printLabItems(title, subtitle, items) {
    if (!items || items.length === 0) {
        alert('No lab items to print.');
        return;
    }

    var rows = document.getElementById('labPrintRows');
    rows.innerHTML = '';
    items.forEach(function (item, index) {
        var row = document.createElement('tr');
        var number = document.createElement('td');
        var name = document.createElement('td');
        var status = document.createElement('td');
        number.textContent = String(index + 1);
        name.textContent = item.name || 'Lab request';
        status.textContent = item.status || '';
        row.appendChild(number);
        row.appendChild(name);
        row.appendChild(status);
        rows.appendChild(row);
    });

    document.getElementById('labPrintTitle').textContent = title;
    document.getElementById('labPrintPatient').textContent = '<?php echo clinic_h($workspace['Patient_Name'] ?? 'Patient'); ?>';
    document.getElementById('labPrintVisit').textContent = '#<?php echo (int) $visitId; ?>';
    document.getElementById('labPrintDate').textContent = new Date().toLocaleString();
    document.getElementById('labPrintSubtitle').textContent = subtitle;
    document.body.classList.add('is-printing-lab-list');
    window.print();
    setTimeout(function () {
        document.body.classList.remove('is-printing-lab-list');
    }, 500);
}

function renderRxCart() {
    var rows = document.getElementById('rxCartRows');
    var keys = Object.keys(rxCart);
    rows.innerHTML = '';
    document.getElementById('rxCartCount').textContent = keys.length;

    if (keys.length === 0) {
        rows.innerHTML = '<div class="text-center text-muted py-5">Click medicines to add them.</div>';
        return;
    }

    keys.forEach(function (id) {
        var item = rxCart[id];
        rows.insertAdjacentHTML('beforeend',
            '<div class="lab-cart-row align-items-start">' +
                '<div class="flex-grow-1">' +
                    '<strong>' + htmlEscape(item.name) + '</strong>' +
                    '<div class="small text-muted mb-2">Stock: ' + htmlEscape(item.stock) + '</div>' +
                    '<input type="hidden" name="Medicine_ID[]" value="' + id + '">' +
                    '<input class="form-control form-control-sm rx-dosage-input" name="Dosage[]" data-id="' + id + '" placeholder="Dosage e.g. 1 tab x 3 days" value="' + htmlEscape(item.dosage || '') + '">' +
                '</div>' +
                '<button class="btn btn-sm btn-link text-muted p-0" type="button" data-remove-rx="' + id + '"><i class="ti ti-x"></i></button>' +
            '</div>'
        );
    });
}

function clearRxCart() {
    rxCart = {};
    document.querySelectorAll('.medicine-rx-card.selected').forEach(function (card) {
        card.classList.remove('selected');
    });
    renderRxCart();
}

function addMedicineCardToRxCart(card, dosage) {
    var id = card.getAttribute('data-id');
    rxCart[id] = {
        name: card.getAttribute('data-name'),
        stock: card.getAttribute('data-stock') || '0',
        dosage: dosage || ''
    };
    card.classList.add('selected');
}

function preloadRxCart(items) {
    clearRxCart();
    items.forEach(function (item) {
        var card = document.querySelector('.medicine-rx-card[data-id="' + item.medicineId + '"]');
        if (card) {
            addMedicineCardToRxCart(card, item.dosage || '');
        }
    });
    renderRxCart();
}

function renderDeletedRxItems() {
    var rows = document.getElementById('rxCartRows');
    rows.innerHTML = '';
    document.getElementById('rxCartCount').textContent = existingPrescriptions.length;

    if (existingPrescriptions.length === 0) {
        rows.innerHTML = '<div class="text-center text-muted py-5">No prescriptions to delete.</div>';
        return;
    }

    existingPrescriptions.forEach(function (item) {
        rows.insertAdjacentHTML('beforeend',
            '<div class="lab-cart-row">' +
                '<div><strong>' + htmlEscape(item.name) + '</strong><div class="small text-danger">Will be deleted</div><div class="small text-muted">' + htmlEscape(item.dosage || '') + '</div></div>' +
                '<span class="badge text-bg-danger">Delete</span>' +
            '</div>'
        );
    });
}

function clearLabCartSelection() {
    labCart = {};
    document.querySelectorAll('#labTestGrid .lab-test-card.selected').forEach(function (card) {
        card.classList.remove('selected');
    });
}

function addLabCardToCart(card) {
    var id = card.getAttribute('data-id');
    labCart[id] = {
        name: card.getAttribute('data-name'),
        price: parseFloat(card.getAttribute('data-price') || '0')
    };
    card.classList.add('selected');
}

function preloadLabCart(items) {
    clearLabCartSelection();
    items.forEach(function (item) {
        var card = document.querySelector('#labTestGrid .lab-test-card[data-id="' + item.testId + '"]');
        if (card) {
            addLabCardToCart(card);
        }
    });
    renderLabCart();
}

function renderLabCart() {
    var rows = document.getElementById('labCartRows');
    var inputs = document.getElementById('labCartInputs');
    var keys = Object.keys(labCart);
    var total = 0;
    rows.innerHTML = '';
    inputs.innerHTML = '';
    document.getElementById('labCartCount').textContent = keys.length;

    if (keys.length === 0) {
        rows.innerHTML = '<div class="text-center text-muted py-5">Click tests to add them.</div>';
    }

    keys.forEach(function (id) {
        var item = labCart[id];
        total += item.price;
        rows.insertAdjacentHTML('beforeend',
            '<div class="lab-cart-row">' +
                '<div><strong>' + item.name + '</strong><div class="small text-muted">' + labMoney(item.price) + '</div></div>' +
                '<button class="btn btn-sm btn-link text-muted p-0" type="button" data-remove-lab="' + id + '"><i class="ti ti-x"></i></button>' +
            '</div>'
        );
        inputs.insertAdjacentHTML('beforeend', '<input type="hidden" name="Test_ID[]" value="' + id + '">');
    });

    document.getElementById('labCartTotal').textContent = labMoney(total);
}

function renderDeletedLabItems() {
    var rows = document.getElementById('labCartRows');
    var inputs = document.getElementById('labCartInputs');
    rows.innerHTML = '';
    inputs.innerHTML = '';
    document.getElementById('labCartCount').textContent = existingPendingLabs.length;
    document.getElementById('labCartTotal').textContent = '$0.00';

    if (existingPendingLabs.length === 0) {
        rows.innerHTML = '<div class="text-center text-muted py-5">No pending lab requests to delete.</div>';
        return;
    }

    existingPendingLabs.forEach(function (item) {
        rows.insertAdjacentHTML('beforeend',
            '<div class="lab-cart-row">' +
                '<div><strong>' + item.name + '</strong><div class="small text-danger">Will be deleted</div></div>' +
                '<span class="badge text-bg-danger">Delete</span>' +
            '</div>'
        );
    });
}

document.querySelectorAll('#labTestGrid .lab-test-card').forEach(function (card) {
    card.addEventListener('click', function () {
        if (labCartMode === 'delete-all') {
            labSwal({
                icon: 'info',
                title: 'Delete mode',
                text: 'Delete mode only shows the lab items that will be deleted.'
            });
            return;
        }
        var id = card.getAttribute('data-id');
        if (labCart[id]) {
            delete labCart[id];
            card.classList.remove('selected');
        } else {
            addLabCardToCart(card);
        }
        renderLabCart();
    });
});

document.querySelectorAll('.medicine-rx-card').forEach(function (card) {
    card.addEventListener('click', function () {
        if (rxCartMode === 'delete-all') {
            labSwal({
                icon: 'info',
                title: 'Delete mode',
                text: 'Delete mode only shows prescriptions that will be deleted.'
            });
            return;
        }
        var id = card.getAttribute('data-id');
        if (rxCart[id]) {
            delete rxCart[id];
            card.classList.remove('selected');
        } else {
            addMedicineCardToRxCart(card, '');
        }
        renderRxCart();
    });
});

document.getElementById('rxCartRows').addEventListener('input', function (event) {
    var input = event.target.closest('.rx-dosage-input');
    if (!input || !rxCart[input.getAttribute('data-id')]) {
        return;
    }
    rxCart[input.getAttribute('data-id')].dosage = input.value;
});

document.getElementById('rxCartRows').addEventListener('click', function (event) {
    var removeBtn = event.target.closest('[data-remove-rx]');
    if (!removeBtn) {
        return;
    }
    var id = removeBtn.getAttribute('data-remove-rx');
    delete rxCart[id];
    var card = document.querySelector('.medicine-rx-card[data-id="' + id + '"]');
    if (card) {
        card.classList.remove('selected');
    }
    renderRxCart();
});

document.getElementById('clearRxCart').addEventListener('click', function () {
    if (rxCartMode === 'delete-all') {
        renderDeletedRxItems();
        return;
    }
    clearRxCart();
});

document.getElementById('medicineRxSearch').addEventListener('input', function () {
    var value = this.value.toLowerCase();
    document.querySelectorAll('.medicine-rx-item').forEach(function (item) {
        item.style.display = item.getAttribute('data-name').indexOf(value) === -1 ? 'none' : '';
    });
});

document.getElementById('prescriptionModal').addEventListener('show.bs.modal', function (event) {
    var mode = event.relatedTarget ? event.relatedTarget.getAttribute('data-rx-mode') : '';
    var action = document.getElementById('rxRequestAction');
    var hint = document.getElementById('rxManageHint');
    var deletedPreview = document.getElementById('rxDeletedPreview');
    var deletedList = document.getElementById('rxDeletedList');
    var title = document.getElementById('rxModalTitle');
    var subtitle = document.getElementById('rxModalSubtitle');
    var submit = document.getElementById('rxRequestSubmit');

    hint.classList.add('d-none');
    deletedPreview.classList.add('d-none');
    deletedList.textContent = '';
    submit.classList.remove('btn-danger');
    submit.classList.add('btn-primary');

    if (mode === 'manage') {
        rxCartMode = 'manage';
        action.value = 'manage_prescriptions';
        title.textContent = 'Edit All Prescriptions';
        subtitle.textContent = 'Current prescriptions are loaded. Add/remove medicines or change dosage, then save once.';
        hint.textContent = 'Saving will replace all prescriptions for this visit with the medicines in this cart.';
        hint.classList.remove('d-none');
        submit.textContent = 'Save Prescriptions';
        preloadRxCart(existingPrescriptions);
        return;
    }

    if (mode === 'delete-all') {
        rxCartMode = 'delete-all';
        action.value = 'manage_prescriptions';
        title.textContent = 'Delete All Prescriptions';
        subtitle.textContent = 'Review the prescriptions that will be deleted, then confirm once.';
        hint.textContent = 'All prescriptions listed in the cart will be deleted from this visit.';
        hint.classList.remove('d-none');
        deletedList.textContent = existingPrescriptions.length > 0
            ? existingPrescriptions.map(function (item) { return item.name; }).join(', ')
            : 'No prescriptions to delete.';
        deletedPreview.classList.remove('d-none');
        submit.textContent = 'Delete Prescriptions';
        submit.classList.remove('btn-primary');
        submit.classList.add('btn-danger');
        clearRxCart();
        renderDeletedRxItems();
        return;
    }

    rxCartMode = 'add';
    action.value = 'add_prescription';
    title.textContent = 'Prescription Cart';
    subtitle.textContent = '<?php echo clinic_h($workspace['Patient_Name']); ?> - add multiple medicines in one prescription';
    submit.textContent = 'Save Prescription';
    clearRxCart();
});

document.getElementById('prescriptionForm').addEventListener('submit', function (event) {
    if (this.dataset.swalConfirmed === '1') {
        return;
    }

    if (Object.keys(rxCart).length === 0 && rxCartMode !== 'delete-all') {
        event.preventDefault();
        labSwal({
            icon: 'warning',
            title: 'No medicine selected',
            text: 'Select at least one medicine before saving the prescription.'
        });
        return;
    }

    if (rxCartMode === 'delete-all' && existingPrescriptions.length === 0) {
        event.preventDefault();
        labSwal({
            icon: 'info',
            title: 'Nothing to delete',
            text: 'There are no prescriptions to delete.'
        });
        return;
    }

    event.preventDefault();
    if (rxCartMode === 'delete-all') {
        confirmLabForm(this, {
            icon: 'warning',
            title: 'Delete prescriptions?',
            text: 'This will delete the listed prescriptions from this visit.',
            confirmButtonText: 'Yes, delete',
            confirmButtonColor: '#dc3545'
        });
        return;
    }

    confirmLabForm(this, {
        icon: 'question',
        title: rxCartMode === 'manage' ? 'Save prescription changes?' : 'Save prescription?',
        text: rxCartMode === 'manage' ? 'This will replace all prescriptions with the medicines in the cart.' : 'This will add all selected medicines to the visit prescription.',
        confirmButtonText: rxCartMode === 'manage' ? 'Yes, save changes' : 'Yes, save'
    });
});

document.getElementById('labCartRows').addEventListener('click', function (event) {
    var removeBtn = event.target.closest('[data-remove-lab]');
    if (!removeBtn) {
        return;
    }
    var id = removeBtn.getAttribute('data-remove-lab');
    delete labCart[id];
    var card = document.querySelector('#labTestGrid .lab-test-card[data-id="' + id + '"]');
    if (card) {
        card.classList.remove('selected');
    }
    renderLabCart();
});

document.getElementById('clearLabCart').addEventListener('click', function () {
    if (labCartMode === 'delete-all') {
        renderDeletedLabItems();
        return;
    }
    clearLabCartSelection();
    renderLabCart();
});

document.getElementById('labTestSearch').addEventListener('input', function () {
    var value = this.value.toLowerCase();
    document.querySelectorAll('.lab-test-item').forEach(function (item) {
        item.style.display = item.getAttribute('data-name').indexOf(value) === -1 ? 'none' : '';
    });
});

document.getElementById('labRequestForm').addEventListener('submit', function (event) {
    if (this.dataset.swalConfirmed === '1') {
        return;
    }

    var action = document.getElementById('labRequestAction').value;
    if (Object.keys(labCart).length === 0 && action !== 'manage_lab_requests') {
        event.preventDefault();
        labSwal({
            icon: 'warning',
            title: 'No lab selected',
            text: 'Select at least one lab test before saving.'
        });
        return;
    }

    if (labCartMode === 'delete-all' && existingPendingLabs.length === 0) {
        event.preventDefault();
        labSwal({
            icon: 'info',
            title: 'Nothing to delete',
            text: 'There are no pending lab requests to delete.'
        });
        return;
    }

    event.preventDefault();
    if (labCartMode === 'delete-all') {
        confirmLabForm(this, {
            icon: 'warning',
            title: 'Delete pending lab requests?',
            text: 'This will delete the listed pending lab requests. Completed results will stay.',
            confirmButtonText: 'Yes, delete',
            confirmButtonColor: '#dc3545'
        });
        return;
    }

    confirmLabForm(this, {
        icon: 'question',
        title: labCartMode === 'manage' ? 'Save lab request changes?' : 'Save lab request?',
        text: labCartMode === 'manage' ? 'This will replace all pending requests with the tests in the cart.' : 'This will send the selected tests to the lab queue.',
        confirmButtonText: labCartMode === 'manage' ? 'Yes, save changes' : 'Yes, save'
    });
});

document.getElementById('printCurrentLabs').addEventListener('click', function () {
    printLabItems('Visit Lab Requests', 'Current lab requests for this visit.', currentLabPrintRows);
});

var printCompletedLabResults = document.getElementById('printCompletedLabResults');
if (printCompletedLabResults) {
    printCompletedLabResults.addEventListener('click', function () {
        document.body.classList.add('is-printing-lab-report');
        window.print();
        setTimeout(function () {
            document.body.classList.remove('is-printing-lab-report');
        }, 500);
    });
}

document.getElementById('printCurrentRx').addEventListener('click', function () {
    printLabItems('Visit Prescriptions', 'Current prescriptions for this visit.', currentRxPrintRows);
});

document.getElementById('printLabCart').addEventListener('click', function () {
    if (labCartMode === 'delete-all') {
        printLabItems('Deleted Lab Requests', 'These pending lab requests will be deleted.', existingPendingLabs.map(function (item) {
            return { name: item.name, status: 'Will be deleted' };
        }));
        return;
    }

    var items = Object.keys(labCart).map(function (id) {
        return {
            name: labCart[id].name,
            status: labCartMode === 'manage' ? 'Selected for save' : 'Requested'
        };
    });
    printLabItems(labCartMode === 'manage' ? 'Updated Lab Request Cart' : 'New Lab Request Cart', 'Lab tests currently in the cart.', items);
});

document.getElementById('printRxCart').addEventListener('click', function () {
    if (rxCartMode === 'delete-all') {
        printLabItems('Deleted Prescriptions', 'These prescriptions will be deleted.', existingPrescriptions.map(function (item) {
            return { name: item.name, status: item.dosage ? 'Will be deleted - ' + item.dosage : 'Will be deleted' };
        }));
        return;
    }

    var items = Object.keys(rxCart).map(function (id) {
        return {
            name: rxCart[id].name,
            status: rxCart[id].dosage || (rxCartMode === 'manage' ? 'Selected for save' : 'Prescribed')
        };
    });
    printLabItems(rxCartMode === 'manage' ? 'Updated Prescription Cart' : 'New Prescription Cart', 'Medicines currently in the prescription cart.', items);
});

document.getElementById('labModal').addEventListener('show.bs.modal', function (event) {
    var mode = event.relatedTarget ? event.relatedTarget.getAttribute('data-lab-mode') : '';
    var action = document.getElementById('labRequestAction');
    var hint = document.getElementById('labManageHint');
    var deletedPreview = document.getElementById('labDeletedPreview');
    var deletedList = document.getElementById('labDeletedList');
    var title = document.getElementById('labModalTitle');
    var subtitle = document.getElementById('labModalSubtitle');
    var submit = document.getElementById('labRequestSubmit');

    hint.classList.add('d-none');
    deletedPreview.classList.add('d-none');
    deletedList.textContent = '';
    submit.classList.remove('btn-danger');
    submit.classList.add('btn-primary');

    if (mode === 'manage') {
        labCartMode = 'manage';
        action.value = 'manage_lab_requests';
        title.textContent = 'Edit All Lab Requests';
        subtitle.textContent = 'Current pending requests are loaded. Add or remove tests, then save once.';
        hint.textContent = 'Saving will replace all pending lab requests for this visit with the tests in this cart. Completed results are not changed.';
        hint.classList.remove('d-none');
        submit.textContent = 'Save Lab Requests';
        preloadLabCart(existingPendingLabs);
        return;
    }

    if (mode === 'delete-all') {
        labCartMode = 'delete-all';
        action.value = 'manage_lab_requests';
        title.textContent = 'Delete All Pending Lab Requests';
        subtitle.textContent = 'Review the lab items that will be deleted, then confirm once.';
        hint.textContent = 'Completed lab results will stay. Only pending requests will be deleted.';
        hint.classList.remove('d-none');
        deletedList.textContent = existingPendingLabs.length > 0
            ? existingPendingLabs.map(function (item) { return item.name; }).join(', ')
            : 'No pending lab requests to delete.';
        deletedPreview.classList.remove('d-none');
        submit.textContent = 'Delete Pending Requests';
        submit.classList.remove('btn-primary');
        submit.classList.add('btn-danger');
        clearLabCartSelection();
        renderDeletedLabItems();
        return;
    }

    labCartMode = 'add';
    action.value = 'add_lab';
    title.textContent = 'Lab Request Cart';
    subtitle.textContent = '<?php echo clinic_h($workspace['Patient_Name']); ?> - add multiple tests in one request';
    submit.textContent = 'Send Lab Request';
    clearLabCartSelection();
    renderLabCart();
});

function selectedLabRequests() {
    return Array.prototype.slice.call(document.querySelectorAll('.lab-row-check:checked')).map(function (checkbox) {
        return {
            id: checkbox.value,
            name: checkbox.getAttribute('data-test-name') || 'Lab request'
        };
    });
}

function fillBulkLabForm(inputContainerId, countId, namesId) {
    var selected = selectedLabRequests();
    var container = document.getElementById(inputContainerId);
    container.innerHTML = '';
    selected.forEach(function (item) {
        container.insertAdjacentHTML('beforeend', '<input type="hidden" name="Result_ID[]" value="' + item.id + '">');
    });
    document.getElementById(countId).textContent = selected.length;
    document.getElementById(namesId).textContent = selected.map(function (item) { return item.name; }).join(', ');
}

document.getElementById('selectAllLabs').addEventListener('change', function () {
    var checked = this.checked;
    document.querySelectorAll('.lab-row-check').forEach(function (checkbox) {
        checkbox.checked = checked;
    });
});

document.querySelectorAll('.lab-row-check').forEach(function (checkbox) {
    checkbox.addEventListener('change', function () {
        var all = document.querySelectorAll('.lab-row-check');
        var checked = document.querySelectorAll('.lab-row-check:checked');
        document.getElementById('selectAllLabs').checked = all.length > 0 && all.length === checked.length;
    });
});

document.getElementById('bulkEditLabModal').addEventListener('show.bs.modal', function () {
    fillBulkLabForm('bulkEditLabInputs', 'bulkEditLabCount', 'bulkEditLabNames');
});

document.getElementById('bulkDeleteLabModal').addEventListener('show.bs.modal', function () {
    fillBulkLabForm('bulkDeleteLabInputs', 'bulkDeleteLabCount', 'bulkDeleteLabNames');
});

document.getElementById('bulkEditLabForm').addEventListener('submit', function (event) {
    if (this.dataset.swalConfirmed === '1') {
        return;
    }
    if (selectedLabRequests().length === 0) {
        event.preventDefault();
        labSwal({
            icon: 'warning',
            title: 'No request selected',
            text: 'Select at least one pending lab request to update.'
        });
        return;
    }
    event.preventDefault();
    confirmLabForm(this, {
        icon: 'question',
        title: 'Update selected lab requests?',
        text: 'The selected pending requests will be changed to the selected lab test.',
        confirmButtonText: 'Yes, update'
    });
});

document.getElementById('bulkDeleteLabForm').addEventListener('submit', function (event) {
    if (this.dataset.swalConfirmed === '1') {
        return;
    }
    if (selectedLabRequests().length === 0) {
        event.preventDefault();
        labSwal({
            icon: 'warning',
            title: 'No request selected',
            text: 'Select at least one pending lab request to delete.'
        });
        return;
    }
    event.preventDefault();
    confirmLabForm(this, {
        icon: 'warning',
        title: 'Delete selected lab requests?',
        text: 'The selected pending lab requests will be deleted.',
        confirmButtonText: 'Yes, delete',
        confirmButtonColor: '#dc3545'
    });
});

document.getElementById('editLabForm').addEventListener('submit', function (event) {
    if (this.dataset.swalConfirmed === '1') {
        return;
    }
    event.preventDefault();
    confirmLabForm(this, {
        icon: 'question',
        title: 'Update lab request?',
        text: 'This pending lab request will be changed to the selected test.',
        confirmButtonText: 'Yes, update'
    });
});

document.querySelectorAll('.lab-delete-form').forEach(function (form) {
    form.addEventListener('submit', function (event) {
        if (this.dataset.swalConfirmed === '1') {
            return;
        }
        event.preventDefault();
        confirmLabForm(this, {
            icon: 'warning',
            title: 'Delete lab request?',
            text: 'This pending lab request will be deleted.',
            confirmButtonText: 'Yes, delete',
            confirmButtonColor: '#dc3545'
        });
    });
});

document.getElementById('editPrescriptionForm').addEventListener('submit', function (event) {
    if (this.dataset.swalConfirmed === '1') {
        return;
    }
    event.preventDefault();
    confirmLabForm(this, {
        icon: 'question',
        title: 'Update prescription?',
        text: 'This prescription will be changed to the selected medicine and dosage.',
        confirmButtonText: 'Yes, update'
    });
});

document.querySelectorAll('.rx-delete-form').forEach(function (form) {
    form.addEventListener('submit', function (event) {
        if (this.dataset.swalConfirmed === '1') {
            return;
        }
        event.preventDefault();
        confirmLabForm(this, {
            icon: 'warning',
            title: 'Delete prescription?',
            text: 'This prescription will be deleted from the visit.',
            confirmButtonText: 'Yes, delete',
            confirmButtonColor: '#dc3545'
        });
    });
});

document.getElementById('editLabModal').addEventListener('show.bs.modal', function (event) {
    var button = event.relatedTarget;
    document.getElementById('editLabResultId').value = button.getAttribute('data-result-id') || '';
    document.getElementById('editLabTestId').value = button.getAttribute('data-test-id') || '';
    document.getElementById('editLabCurrentTest').textContent = button.getAttribute('data-test-name') || '-';
});

document.getElementById('editPrescriptionModal').addEventListener('show.bs.modal', function (event) {
    var button = event.relatedTarget;
    document.getElementById('editPrescriptionId').value = button.getAttribute('data-prescription-id') || '';
    document.getElementById('editPrescriptionMedicineId').value = button.getAttribute('data-medicine-id') || '';
    document.getElementById('editPrescriptionCurrentMedicine').textContent = button.getAttribute('data-medicine-name') || '-';
    document.getElementById('editPrescriptionDosage').value = button.getAttribute('data-dosage') || '';
});
</script>
<?php endif; ?>

<?php if ($selectedAppointment > 0): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var visitModal = document.getElementById('visitModal');
    if (visitModal && window.bootstrap) {
        bootstrap.Modal.getOrCreateInstance(visitModal).show();
    }
});
</script>
<?php endif; ?>

<?php clinic_page_end(); ?>
