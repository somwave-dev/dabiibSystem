<?php
require_once __DIR__ . '/../includes/advanced_components.php';

function clinic_lab_result_details(string $option, string $details): string
{
    $option = trim($option);
    $details = trim($details);
    if (!in_array($option, ['Positive', 'Negative'], true)) {
        throw new RuntimeException('Choose Positive or Negative.');
    }

    return 'Result: ' . $option . ($details !== '' ? PHP_EOL . $details : '');
}

function clinic_lab_is_closed_status(string $status): bool
{
    return in_array($status, ['Completed', 'Cancelled', 'Canceled'], true);
}

function clinic_lab_is_past_visit_date(string $visitDate): bool
{
    $visitDate = trim($visitDate);
    if ($visitDate === '') {
        return false;
    }

    $timestamp = strtotime($visitDate);
    return $timestamp !== false && date('Y-m-d', $timestamp) < date('Y-m-d');
}

try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        clinic_check_csrf();
        $recordedByUserId = clinic_current_user_id() ?? 0;
        if (clinic_post_string('action') === 'complete_lab') {
            $resultDetails = clinic_lab_result_details(clinic_post_string('Result_Option'), clinic_post_string('Result_Details'));
            clinic_sp_one('sp_complete_lab_result', [clinic_post_int('Result_ID'), $resultDetails, $recordedByUserId]);
            clinic_flash('Lab result completed.');
            clinic_redirect('lab_results.php?patient_id=' . clinic_post_int('Patient_ID') . '&status=' . urlencode(clinic_post_string('Return_Status')));
        }
        if (clinic_post_string('action') === 'complete_labs_bulk') {
            $resultIds = $_POST['Result_ID'] ?? [];
            $options = $_POST['Result_Option'] ?? [];
            $details = $_POST['Result_Details'] ?? [];
            if (!is_array($resultIds)) {
                $resultIds = [$resultIds];
            }
            if (!is_array($options)) {
                $options = [$options];
            }
            if (!is_array($details)) {
                $details = [$details];
            }

            $completed = 0;
            foreach ($resultIds as $index => $resultId) {
                $resultId = (int) $resultId;
                $option = trim((string) ($options[$index] ?? ''));
                $detail = trim((string) ($details[$index] ?? ''));
                if ($resultId < 1 || $option === '') {
                    continue;
                }
                clinic_sp_one('sp_complete_lab_result', [$resultId, clinic_lab_result_details($option, $detail), $recordedByUserId]);
                $completed++;
            }

            if ($completed === 0) {
                throw new RuntimeException('Enter at least one lab result.');
            }

            clinic_flash($completed . ' lab result' . ($completed === 1 ? '' : 's') . ' completed.');
            clinic_redirect('lab_results.php?patient_id=' . clinic_post_int('Patient_ID') . '&status=' . urlencode(clinic_post_string('Return_Status')));
        }
    }
} catch (Throwable $e) {
    clinic_flash($e->getMessage(), 'danger');
    clinic_redirect('lab_results.php');
}

$visits = clinic_doctor_scoped_list('sp_visits_list');
$visitMap = [];
foreach ($visits as $visit) {
    $visitMap[(int) $visit['Visit_ID']] = $visit;
}

$labTestMap = [];
foreach (clinic_sp_rows('sp_lab_tests_list') as $test) {
    $labTestMap[(int) ($test['Test_ID'] ?? 0)] = $test;
}

$allRows = clinic_doctor_scoped_list('sp_lab_results_list');
foreach ($allRows as &$row) {
    $visit = $visitMap[(int) ($row['Visit_ID'] ?? 0)] ?? [];
    $test = $labTestMap[(int) ($row['Test_ID'] ?? 0)] ?? [];
    $row['Patient_ID'] = (int) ($visit['Patient_ID'] ?? 0);
    $row['Doctor_Name'] = (string) ($visit['Doctor_Name'] ?? '');
    $row['Visit_Date'] = (string) ($visit['Visit_Date'] ?? '');
    $row['Price'] = (float) ($test['Price'] ?? 0);
}
unset($row);

$allRows = array_values(array_filter($allRows, static function ($row): bool {
    $status = (string) ($row['Status'] ?? 'Pending');
    $visitDate = (string) ($row['Visit_Date'] ?? '');
    return !(clinic_lab_is_closed_status($status) && clinic_lab_is_past_visit_date($visitDate));
}));

$status = (string) ($_GET['status'] ?? 'Pending');
$rows = $status !== ''
    ? array_values(array_filter($allRows, static fn ($row) => ($row['Status'] ?? '') === $status))
    : $allRows;

$patientCards = [];
foreach ($rows as $row) {
    $patientId = (int) ($row['Patient_ID'] ?? 0);
    if ($patientId < 1) {
        continue;
    }
    if (!isset($patientCards[$patientId])) {
        $patientCards[$patientId] = [
            'Patient_ID' => $patientId,
            'Patient_Name' => (string) ($row['Patient_Name'] ?? 'Patient'),
            'total' => 0,
            'pending' => 0,
            'completed' => 0,
            'latest_visit' => (int) ($row['Visit_ID'] ?? 0),
        ];
    }
    $patientCards[$patientId]['total']++;
    if (clinic_lab_is_closed_status((string) ($row['Status'] ?? 'Pending'))) {
        $patientCards[$patientId]['completed']++;
    } else {
        $patientCards[$patientId]['pending']++;
    }
}

$selectedPatientId = (int) ($_GET['patient_id'] ?? 0);
if ($selectedPatientId < 1 || !isset($patientCards[$selectedPatientId])) {
    $selectedPatientId = (int) array_key_first($patientCards);
}

$selectedRows = array_values(array_filter($rows, fn ($row) => (int) ($row['Patient_ID'] ?? 0) === $selectedPatientId));
$selectedPatient = $patientCards[$selectedPatientId] ?? null;
$selectedPatientProfile = $selectedPatientId > 0 ? clinic_sp_one('sp_patient_profile', [$selectedPatientId], 'i') : null;
$selectedPendingRows = array_values(array_filter($selectedRows, static fn ($row) => !clinic_lab_is_closed_status((string) ($row['Status'] ?? 'Pending'))));
$selectedCompletedRows = array_values(array_filter($selectedRows, static fn ($row) => (string) ($row['Status'] ?? '') === 'Completed'));
$completedSubtotal = array_sum(array_map(static fn ($row) => (float) ($row['Price'] ?? 0), $selectedCompletedRows));
$totalPatients = count($patientCards);
$totalTests = count($rows);
$pendingTests = count(array_filter($rows, static fn ($row) => !clinic_lab_is_closed_status((string) ($row['Status'] ?? 'Pending'))));
$completedTests = max($totalTests - $pendingTests, 0);

clinic_page_start('Lab Results', 'Enter results per test. Completed rows show who recorded the result and when.');
?>
<style>
    .lab-desk {
        background: linear-gradient(135deg, var(--primary-transparent), var(--white));
        border: 1px solid var(--border-color);
        border-radius: 24px;
        padding: 1rem;
    }
    .lab-stat {
        background: var(--white);
        border: 1px solid var(--border-color);
        border-radius: 18px;
        padding: 1rem;
        box-shadow: var(--box-shadow-sm);
    }
    .lab-stat-icon {
        align-items: center;
        background: var(--primary-transparent);
        border-radius: 14px;
        color: var(--primary);
        display: inline-flex;
        height: 44px;
        justify-content: center;
        width: 44px;
    }
    .lab-patient-list {
        max-height: 660px;
        overflow: auto;
        padding-right: .25rem;
    }
    .lab-patient-card {
        background: var(--white);
        border: 1px solid var(--border-color);
        border-radius: 18px;
        color: inherit;
        display: block;
        padding: 1rem;
        text-decoration: none;
        transition: transform .12s, box-shadow .12s;
    }
    .lab-patient-card:hover {
        box-shadow: var(--box-shadow);
        color: inherit;
        transform: translateY(-2px);
    }
    .lab-patient-card.active {
        background: linear-gradient(135deg, var(--primary), rgba(var(--primary-rgb), .78));
        border-color: var(--primary);
        box-shadow: 0 16px 38px rgba(var(--primary-rgb), .24);
        color: #fff;
    }
    .lab-patient-avatar {
        align-items: center;
        background: var(--primary-transparent);
        border-radius: 50%;
        color: var(--primary);
        display: inline-flex;
        font-weight: 800;
        height: 42px;
        justify-content: center;
        width: 42px;
    }
    .lab-patient-card.active .lab-patient-avatar {
        background: rgba(255, 255, 255, .2);
        color: #fff;
    }
    .lab-test-panel {
        background: var(--white);
        border: 1px solid var(--border-color);
        border-radius: 18px;
        padding: 1rem;
        box-shadow: var(--box-shadow-sm);
        transition: transform .12s, box-shadow .12s;
    }
    .lab-test-panel:hover {
        box-shadow: var(--box-shadow);
        transform: translateY(-2px);
    }
    .lab-test-icon {
        align-items: center;
        background: var(--primary-transparent);
        border-radius: 14px;
        color: var(--primary);
        display: inline-flex;
        height: 44px;
        justify-content: center;
        width: 44px;
    }
    .lab-selected-hero {
        background: var(--light);
        border: 1px solid var(--border-color);
        border-radius: 18px;
        padding: 1rem;
    }
    .lab-result-box {
        background: var(--light);
        border: 1px dashed var(--border-color);
        border-radius: 14px;
        min-height: 82px;
        padding: .75rem;
    }
    .lab-test-status {
        align-self: flex-start;
        flex-shrink: 0;
    }
    .lab-test-status .badge {
        border-radius: 999px;
        line-height: 1;
        padding: .45rem .65rem;
        white-space: nowrap;
    }
    .completed-print-area {
        display: none;
    }
    .lab-report-paper {
        background: #fff;
        color: #1d2a3b;
        font-family: Arial, sans-serif;
        margin: 0 auto;
        max-width: 900px;
        padding: 0 20px 28px;
    }
    .lab-report-top {
        background: #264f91;
        color: #fff;
        margin: 0 -20px 28px;
        padding: 34px 42px;
    }
    .lab-report-top h1 {
        font-size: 27px;
        font-weight: 800;
        margin: 0 0 12px;
        text-transform: uppercase;
    }
    .lab-report-chips {
        display: flex;
        flex-wrap: wrap;
        gap: 14px;
        margin-top: 22px;
    }
    .lab-report-chip {
        background: rgba(255, 255, 255, .16);
        border-radius: 999px;
        padding: 8px 16px;
    }
    .lab-report-chip.success {
        background: #d7f7df;
        color: #215c36;
        font-weight: 700;
        margin-left: auto;
    }
    .lab-report-section {
        border-left: 4px solid #2d72aa;
        font-size: 17px;
        font-weight: 800;
        margin: 28px 0 14px;
        padding: 8px 0 8px 18px;
        text-transform: uppercase;
    }
    .lab-report-card {
        background: #f8fbff;
        border: 1px solid #dbe5f0;
        border-radius: 16px;
        padding: 22px 28px;
    }
    .lab-report-info {
        display: grid;
        gap: 18px 46px;
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
    .lab-report-label {
        color: #6b7a90;
        font-size: 11px;
        font-weight: 800;
        letter-spacing: .04em;
        text-transform: uppercase;
    }
    .lab-report-value {
        font-size: 15px;
        font-weight: 800;
        margin-top: 5px;
    }
    .lab-report-table {
        border: 1px solid #dbe5f0;
        border-radius: 14px;
        overflow: hidden;
    }
    .lab-report-table table {
        border-collapse: collapse;
        width: 100%;
    }
    .lab-report-table th {
        background: #eef3f9;
        font-size: 13px;
        font-weight: 800;
        padding: 14px 12px;
        text-align: left;
    }
    .lab-report-table td {
        border-top: 1px solid #e2e8f0;
        padding: 14px 12px;
        vertical-align: top;
    }
    .lab-report-result {
        color: #00a996;
        font-weight: 800;
    }
    .lab-report-pill {
        background: #dff6df;
        border-radius: 999px;
        color: #238238;
        display: inline-block;
        font-size: 12px;
        font-weight: 800;
        padding: 5px 12px;
    }
    .lab-report-total {
        background: #f8fbff;
        border: 1px solid #dbe5f0;
        border-radius: 16px;
        margin-top: 26px;
        padding: 22px 28px;
    }
    .lab-report-total-row {
        display: flex;
        justify-content: space-between;
        padding: 8px 0;
    }
    .lab-report-grand {
        border-top: 2px solid #dbe5f0;
        color: #193b78;
        font-size: 18px;
        font-weight: 900;
        margin-top: 10px;
        padding-top: 14px;
    }
    @media print {
        body * {
            visibility: hidden !important;
        }
        #completedPrintArea,
        #completedPrintArea * {
            visibility: visible !important;
        }
        #completedPrintArea {
            background: #fff;
            color: #1d2a3b;
            display: block !important;
            font-size: 14px;
            inset: 0;
            padding: 0;
            position: absolute;
            width: 100%;
        }
        @page {
            margin: 10mm;
        }
    }
</style>

<div class="lab-desk mb-3">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div>
            <h4 class="fw-bold mb-1">Lab Result Desk</h4>
            <div class="text-muted">Choose a patient, review tests, and enter results quickly.</div>
        </div>
        <div class="btn-group">
            <a class="btn btn-<?php echo $status === 'Pending' ? 'primary' : 'light border'; ?>" href="lab_results.php?status=Pending">Pending</a>
            <a class="btn btn-<?php echo $status === 'Completed' ? 'primary' : 'light border'; ?>" href="lab_results.php?status=Completed">Completed</a>
            <a class="btn btn-<?php echo $status === '' ? 'primary' : 'light border'; ?>" href="lab_results.php?status=">All</a>
        </div>
    </div>
    <div class="row g-3">
        <div class="col-md-4"><div class="lab-stat d-flex justify-content-between align-items-center"><div><div class="text-muted">Patients</div><h3 class="fw-bold mb-0"><?php echo $totalPatients; ?></h3></div><span class="lab-stat-icon"><i class="ti ti-users fs-22"></i></span></div></div>
        <div class="col-md-4"><div class="lab-stat d-flex justify-content-between align-items-center"><div><div class="text-muted">Pending Tests</div><h3 class="fw-bold text-warning mb-0"><?php echo $pendingTests; ?></h3></div><span class="lab-stat-icon"><i class="ti ti-hourglass fs-22"></i></span></div></div>
        <div class="col-md-4"><div class="lab-stat d-flex justify-content-between align-items-center"><div><div class="text-muted">Completed</div><h3 class="fw-bold text-success mb-0"><?php echo $completedTests; ?></h3></div><span class="lab-stat-icon"><i class="ti ti-circle-check fs-22"></i></span></div></div>
    </div>
</div>

<div class="row g-3">
    <div class="col-xl-4">
        <div class="card clinic-card h-100">
            <div class="card-header">
                <h5 class="mb-1">Patients With Lab Requests</h5>
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-white"><i class="ti ti-search"></i></span>
                    <input class="form-control" id="patientSearch" placeholder="Search patient">
                </div>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2 lab-patient-list" id="patientCards">
                    <?php foreach ($patientCards as $patient): ?>
                    <a class="lab-patient-card <?php echo (int) $patient['Patient_ID'] === $selectedPatientId ? 'active' : ''; ?>" data-name="<?php echo clinic_h(strtolower($patient['Patient_Name'])); ?>" href="lab_results.php?patient_id=<?php echo (int) $patient['Patient_ID']; ?>&status=<?php echo urlencode($status); ?>">
                        <div class="d-flex align-items-center gap-3">
                            <span class="lab-patient-avatar"><?php echo clinic_h(substr($patient['Patient_Name'], 0, 1)); ?></span>
                            <div class="flex-grow-1">
                                <div class="fw-bold"><?php echo clinic_h($patient['Patient_Name']); ?></div>
                                <div class="small <?php echo (int) $patient['Patient_ID'] === $selectedPatientId ? 'text-white-50' : 'text-muted'; ?>">
                                    <?php echo (int) $patient['total']; ?> test(s) / <?php echo (int) $patient['completed']; ?> completed
                                </div>
                            </div>
                            <span class="badge <?php echo (int) $patient['Patient_ID'] === $selectedPatientId ? 'text-bg-light' : 'text-bg-warning'; ?>"><?php echo (int) $patient['pending']; ?></span>
                        </div>
                    </a>
                    <?php endforeach; ?>
                    <?php if ($patientCards === []): ?>
                    <div class="text-center text-muted py-5">No lab requests found for this filter.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-8">
        <div class="card clinic-card h-100">
            <div class="card-header">
                <div class="lab-selected-hero d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <div class="d-flex align-items-center gap-3">
                        <span class="lab-patient-avatar"><?php echo clinic_h(substr((string) ($selectedPatient['Patient_Name'] ?? 'P'), 0, 1)); ?></span>
                        <div>
                            <h5 class="mb-0"><?php echo clinic_h($selectedPatient['Patient_Name'] ?? 'Select Patient'); ?></h5>
                            <div class="small text-muted">Patient lab tests and result entry.</div>
                        </div>
                    </div>
                    <?php if ($selectedPatient): ?>
                    <div class="d-flex gap-2">
                        <?php if ($selectedCompletedRows !== []): ?>
                        <button class="btn btn-sm btn-outline-primary" type="button" id="printCompletedResults">
                            <i class="ti ti-printer me-1"></i> Print Completed
                        </button>
                        <?php endif; ?>
                        <?php if ($selectedPendingRows !== []): ?>
                        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#bulkResultModal">
                            <i class="ti ti-list-check me-1"></i> Enter All Results
                        </button>
                        <?php endif; ?>
                        <span class="badge text-bg-primary"><?php echo count($selectedRows); ?> test(s)</span>
                        <span class="badge text-bg-warning"><?php echo (int) $selectedPatient['pending']; ?> pending</span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body">
                <?php if ($selectedRows !== []): ?>
                <div class="row g-3">
                    <?php foreach ($selectedRows as $row): ?>
                    <div class="col-md-6">
                        <div class="lab-test-panel h-100">
                            <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                                <div class="d-flex gap-2">
                                    <span class="lab-test-icon"><i class="ti ti-microscope fs-22"></i></span>
                                    <div>
                                    <h6 class="fw-bold mb-1"><?php echo clinic_h($row['Test_Name'] ?? '-'); ?></h6>
                                    <div class="small text-muted">Visit #<?php echo (int) ($row['Visit_ID'] ?? 0); ?> <?php echo clinic_h($row['Visit_Date'] ? ' - ' . $row['Visit_Date'] : ''); ?></div>
                                    </div>
                                </div>
                                <span class="lab-test-status"><?php echo clinic_status_badge((string) ($row['Status'] ?? 'Pending')); ?></span>
                            </div>
                            <div class="lab-result-box mb-3">
                                <div class="small text-muted">Result</div>
                                <div><?php echo clinic_h($row['Result_Details'] ?: 'No result entered yet.'); ?></div>
                            </div>
                            <?php $rowStatus = (string) ($row['Status'] ?? 'Pending'); ?>
                            <?php if ($rowStatus === 'Completed'): ?>
                                <?php
                                $recordedAtRaw = (string) ($row['Recorded_At'] ?? '');
                                $recordedAtFmt = '';
                                if ($recordedAtRaw !== '') {
                                    $ts = strtotime($recordedAtRaw);
                                    $recordedAtFmt = $ts !== false ? date('d M Y, H:i', $ts) : '';
                                }
                                ?>
                                <div class="alert alert-light border py-2 px-3 mb-3">
                                    <div class="small text-muted text-uppercase fw-semibold mb-1">Recorded by</div>
                                    <div class="fw-bold"><?php echo clinic_h((string) ($row['Recorded_By_Username'] ?? '') ?: '—'); ?></div>
                                    <?php if ($recordedAtFmt !== ''): ?>
                                        <div class="small text-muted mt-1"><?php echo clinic_h($recordedAtFmt); ?></div>
                                    <?php endif; ?>
                                    <div class="small text-muted mt-2 mb-0" lang="so">Userka natiijada soo geliyey.</div>
                                </div>
                            <?php endif; ?>
                            <div class="d-flex justify-content-between align-items-center gap-2">
                                <a class="btn btn-sm btn-light border" href="visits.php?visit_id=<?php echo (int) ($row['Visit_ID'] ?? 0); ?>">Visit</a>
                                <?php if (!clinic_lab_is_closed_status($rowStatus)): ?>
                                <button
                                    class="btn btn-sm btn-primary"
                                    data-bs-toggle="modal"
                                    data-bs-target="#resultModal"
                                    data-id="<?php echo (int) $row['Result_ID']; ?>"
                                    data-patient-id="<?php echo (int) $selectedPatientId; ?>"
                                    data-patient="<?php echo clinic_h($row['Patient_Name'] ?? ''); ?>"
                                    data-test="<?php echo clinic_h($row['Test_Name'] ?? ''); ?>"
                                    data-visit="<?php echo (int) ($row['Visit_ID'] ?? 0); ?>"
                                    data-detail="<?php echo clinic_h((string) ($row['Result_Details'] ?? '')); ?>"
                                >Enter Result</button>
                                <?php elseif ($rowStatus === 'Completed'): ?>
                                <button
                                    class="btn btn-sm btn-outline-primary"
                                    data-bs-toggle="modal"
                                    data-bs-target="#resultModal"
                                    data-id="<?php echo (int) $row['Result_ID']; ?>"
                                    data-patient-id="<?php echo (int) $selectedPatientId; ?>"
                                    data-patient="<?php echo clinic_h($row['Patient_Name'] ?? ''); ?>"
                                    data-test="<?php echo clinic_h($row['Test_Name'] ?? ''); ?>"
                                    data-visit="<?php echo (int) ($row['Visit_ID'] ?? 0); ?>"
                                    data-detail="<?php echo clinic_h((string) ($row['Result_Details'] ?? '')); ?>"
                                >Update Result</button>
                                <?php else: ?>
                                <button class="btn btn-sm btn-outline-secondary" type="button" disabled><?php echo clinic_h($rowStatus); ?></button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="text-center text-muted py-5">Select a patient card to view lab tests.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
$firstCompleted = $selectedCompletedRows[0] ?? [];
$requestDate = (string) ($firstCompleted['Visit_Date'] ?? date('Y-m-d H:i'));
$reportNo = 'REQ-' . str_pad((string) ((int) ($firstCompleted['Visit_ID'] ?? $selectedPatientId)), 5, '0', STR_PAD_LEFT);
?>
<div class="completed-print-area lab-report-paper" id="completedPrintArea">
    <div class="lab-report-top">
        <h1><i class="ti ti-microscope"></i> Laboratory Test Report</h1>
        <div>Clinical Diagnostic Center</div>
        <div class="lab-report-chips">
            <span class="lab-report-chip"><?php echo clinic_h($reportNo); ?></span>
            <span class="lab-report-chip"><?php echo clinic_h(date('d M Y, h:i A', strtotime($requestDate) ?: time())); ?></span>
            <span class="lab-report-chip">Reported: <?php echo clinic_h(date('d M Y')); ?></span>
            <span class="lab-report-chip success">Completed</span>
        </div>
    </div>

    <div class="lab-report-card">
        <div class="lab-report-section" style="margin-top:0;">Patient Information</div>
        <div class="lab-report-info">
            <div>
                <div class="lab-report-label">Full Name</div>
                <div class="lab-report-value"><?php echo clinic_h($selectedPatientProfile['Full_Name'] ?? $selectedPatient['Patient_Name'] ?? '-'); ?></div>
            </div>
            <div>
                <div class="lab-report-label">Patient Type</div>
                <div class="lab-report-value"><?php echo clinic_h($selectedPatientProfile['Patient_Type'] ?? '-'); ?></div>
            </div>
            <div>
                <div class="lab-report-label">Phone</div>
                <div class="lab-report-value"><?php echo clinic_h($selectedPatientProfile['Phone_Number'] ?? '-'); ?></div>
            </div>
            <div>
                <div class="lab-report-label">Requesting Doctor</div>
                <div class="lab-report-value"><?php echo clinic_h($firstCompleted['Doctor_Name'] ?? '-'); ?></div>
            </div>
            <div>
                <div class="lab-report-label">Last Visit</div>
                <div class="lab-report-value"><?php echo clinic_h($selectedPatientProfile['last_visit_date'] ?? $requestDate); ?></div>
            </div>
            <div>
                <div class="lab-report-label">Patient Balance</div>
                <div class="lab-report-value"><?php echo clinic_money($selectedPatientProfile['Current_Balance'] ?? 0); ?></div>
            </div>
        </div>
    </div>

    <?php if ($selectedCompletedRows !== []): ?>
    <div class="lab-report-section">Laboratory Results</div>
    <div class="lab-report-table">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Test Name</th>
                    <th>Result</th>
                    <th>Recorded by</th>
                    <th>Recorded at</th>
                    <th>Normal Range</th>
                    <th>Reference</th>
                    <th>Remarks</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($selectedCompletedRows as $index => $row): ?>
                <?php
                $prtRa = (string) ($row['Recorded_At'] ?? '');
                $prtRaFmt = '';
                if ($prtRa !== '') {
                    $prtTs = strtotime($prtRa);
                    $prtRaFmt = $prtTs !== false ? date('d M Y, H:i', $prtTs) : '';
                }
                ?>
                <tr>
                    <td><?php echo $index + 1; ?></td>
                    <td><strong><?php echo clinic_h($row['Test_Name'] ?? '-'); ?></strong></td>
                    <td class="lab-report-result"><?php echo nl2br(clinic_h($row['Result_Details'] ?: '-')); ?></td>
                    <td><?php echo clinic_h((string) ($row['Recorded_By_Username'] ?? '') ?: '—'); ?></td>
                    <td><?php echo clinic_h($prtRaFmt ?: '—'); ?></td>
                    <td>Normal</td>
                    <td><span class="lab-report-pill">Normal</span></td>
                    <td>-</td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="lab-report-total">
        <div class="lab-report-total-row">
            <span>Subtotal:</span>
            <strong><?php echo clinic_money($completedSubtotal); ?></strong>
        </div>
        <div class="lab-report-total-row">
            <span>Tax (0%):</span>
            <strong><?php echo clinic_money(0); ?></strong>
        </div>
        <div class="lab-report-total-row lab-report-grand">
            <span>Total Amount:</span>
            <span><?php echo clinic_money($completedSubtotal); ?></span>
        </div>
    </div>
    <?php else: ?>
    <p>No completed lab results to print.</p>
    <?php endif; ?>
</div>

<div class="modal fade" id="bulkResultModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <form method="post" class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title">Enter All Lab Results</h5>
                    <div class="small text-muted"><?php echo clinic_h($selectedPatient['Patient_Name'] ?? 'Selected patient'); ?></div>
                </div>
                <button class="btn-close" type="button" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?php echo clinic_h(clinic_csrf_token()); ?>">
                <input type="hidden" name="action" value="complete_labs_bulk">
                <input type="hidden" name="Patient_ID" value="<?php echo (int) $selectedPatientId; ?>">
                <input type="hidden" name="Return_Status" value="<?php echo clinic_h($status); ?>">

                <?php if ($selectedPendingRows !== []): ?>
                <div class="row g-3">
                    <?php foreach ($selectedPendingRows as $index => $row): ?>
                    <div class="col-md-6">
                        <div class="lab-test-panel h-100">
                            <input type="hidden" name="Result_ID[]" value="<?php echo (int) $row['Result_ID']; ?>">
                            <div class="d-flex gap-2 mb-3">
                                <span class="lab-test-icon"><i class="ti ti-microscope fs-22"></i></span>
                                <div>
                                    <h6 class="fw-bold mb-1"><?php echo clinic_h($row['Test_Name'] ?? '-'); ?></h6>
                                    <div class="small text-muted">Visit #<?php echo (int) ($row['Visit_ID'] ?? 0); ?> <?php echo clinic_h($row['Visit_Date'] ? ' - ' . $row['Visit_Date'] : ''); ?></div>
                                </div>
                            </div>
                            <label class="form-label" for="bulkOption<?php echo $index; ?>">Result option</label>
                            <select class="form-select mb-3" id="bulkOption<?php echo $index; ?>" name="Result_Option[]" required>
                                <option value="">Choose result</option>
                                <option value="Positive">Positive</option>
                                <option value="Negative">Negative</option>
                            </select>
                            <label class="form-label" for="bulkResult<?php echo $index; ?>">Result details</label>
                            <textarea class="form-control" id="bulkResult<?php echo $index; ?>" name="Result_Details[]" rows="4"><?php echo clinic_h((string) ($row['Result_Details'] ?? '')); ?></textarea>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="text-center text-muted py-5">No pending lab results for this patient.</div>
                <?php endif; ?>
            </div>
            <div class="modal-footer">
                <button class="btn btn-light" type="button" data-bs-dismiss="modal">Cancel</button>
                <?php if ($selectedPendingRows !== []): ?>
                <button class="btn btn-primary">Save All Results</button>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="resultModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="post" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Enter Lab Result</h5>
                <button class="btn-close" type="button" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?php echo clinic_h(clinic_csrf_token()); ?>">
                <input type="hidden" name="action" value="complete_lab">
                <input type="hidden" name="Result_ID" id="resultId">
                <input type="hidden" name="Patient_ID" id="resultPatientId">
                <input type="hidden" name="Return_Status" value="<?php echo clinic_h($status); ?>">
                <div class="alert alert-light border">
                    <strong id="resultPatient"></strong>
                    <div class="small text-muted" id="resultTest"></div>
                </div>
                <label class="form-label" for="resultOption">Result option</label>
                <select class="form-select mb-3" name="Result_Option" id="resultOption" required>
                    <option value="">Choose result</option>
                    <option value="Positive">Positive</option>
                    <option value="Negative">Negative</option>
                </select>
                <label class="form-label">Result details</label>
                <textarea class="form-control" name="Result_Details" id="resultDetails" rows="5"></textarea>
            </div>
            <div class="modal-footer">
                <button class="btn btn-light" type="button" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-primary">Save Result</button>
            </div>
        </form>
    </div>
</div>

<script>
document.getElementById('resultModal').addEventListener('show.bs.modal', function (event) {
    var btn = event.relatedTarget;
    document.getElementById('resultId').value = btn.getAttribute('data-id') || '';
    document.getElementById('resultPatientId').value = btn.getAttribute('data-patient-id') || '';
    document.getElementById('resultPatient').textContent = btn.getAttribute('data-patient') || '';
    document.getElementById('resultTest').textContent = (btn.getAttribute('data-test') || '') + ' / Visit #' + (btn.getAttribute('data-visit') || '');
    var detail = btn.getAttribute('data-detail') || '';
    var option = '';
    if (detail.indexOf('Result: Positive') === 0) {
        option = 'Positive';
        detail = detail.replace(/^Result: Positive\s*/, '');
    } else if (detail.indexOf('Result: Negative') === 0) {
        option = 'Negative';
        detail = detail.replace(/^Result: Negative\s*/, '');
    }
    document.getElementById('resultOption').value = option;
    document.getElementById('resultDetails').value = detail;
});

document.getElementById('patientSearch').addEventListener('input', function () {
    var value = this.value.toLowerCase();
    document.querySelectorAll('#patientCards .lab-patient-card').forEach(function (card) {
        card.style.display = card.getAttribute('data-name').indexOf(value) === -1 ? 'none' : '';
    });
});

var printCompletedButton = document.getElementById('printCompletedResults');
if (printCompletedButton) {
    printCompletedButton.addEventListener('click', function () {
        window.print();
    });
}
</script>
<?php clinic_page_end(); ?>
