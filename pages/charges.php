<?php
require_once __DIR__ . '/../includes/advanced_components.php';

const CLINIC_CHARGE_CATEGORIES = ['Consultation', 'Lab', 'Pharmacy', 'Nursing', 'Medicines', 'Other'];

try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        clinic_check_csrf();
        $action = clinic_post_string('action');

        if ($action === 'add_charge') {
            $patientId = clinic_post_int('Patient_ID');
            $amount = clinic_post_float('Amount');
            $category = clinic_post_string('Category') ?: 'Other';
            $description = clinic_post_string('Description');
            if ($patientId < 1) {
                throw new RuntimeException('Select a patient for the charge.');
            }
            if ($description === '') {
                $description = $category !== '' ? $category . ' fee' : 'Service';
            }
            if ($amount < 0) {
                throw new RuntimeException('Amount cannot be negative.');
            }
            $performerId = clinic_post_int('Performed_By');
            if ($performerId < 1) {
                $performerId = clinic_current_user_id() ?? 0;
            }
            $earningUserId = clinic_post_int('Earning_User_ID');
            if ($earningUserId < 1) {
                $earningUserId = $performerId; // earnings default to whoever performed the service
            }
            $earningAmount = clinic_post_float('Earning_Amount');

            clinic_sp_exec('sp_charge_add', [
                $patientId,
                'Manual',
                0,
                $description,
                $category,
                $amount,
                $performerId > 0 ? $performerId : null,
                $earningUserId > 0 ? $earningUserId : null,
                $earningAmount,
            ]);
            clinic_flash('Charge added to the patient bill.');
            clinic_redirect('charges.php?patient_id=' . $patientId);
        }

        if ($action === 'delete_charge') {
            $chargeId = clinic_post_int('Charge_ID');
            if ($chargeId < 1) {
                throw new RuntimeException('Invalid charge.');
            }
            global $conn;
            $stmt = $conn->prepare('SELECT Patient_ID, Paid_Amount FROM charges WHERE Charge_ID = ?');
            $stmt->bind_param('i', $chargeId);
            $stmt->execute();
            $res = $stmt->get_result();
            $charge = $res->fetch_assoc();
            $stmt->close();
            if (!$charge) {
                throw new RuntimeException('Charge not found.');
            }
            if ((float) ($charge['Paid_Amount'] ?? 0) > 0) {
                throw new RuntimeException('A charge with a payment on it cannot be deleted.');
            }
            $stmt = $conn->prepare('DELETE FROM earnings WHERE Charge_ID = ?');
            $stmt->bind_param('i', $chargeId);
            $stmt->execute();
            $stmt->close();
            $stmt = $conn->prepare('DELETE FROM charges WHERE Charge_ID = ?');
            $stmt->bind_param('i', $chargeId);
            $stmt->execute();
            $stmt->close();
            clinic_sp_exec('sp_sync_patient_balance', [(int) $charge['Patient_ID']]);
            clinic_flash('Charge removed and patient balance updated.');
            clinic_redirect('charges.php');
        }
    }
} catch (Throwable $e) {
    clinic_flash($e->getMessage(), 'danger');
    clinic_redirect('charges.php');
}

$patients = clinic_sp_rows('sp_patients_list');
$users = clinic_sp_rows('sp_users_list');
$filterPatientId = (int) ($_GET['patient_id'] ?? 0);
$filterStatus = (string) ($_GET['status'] ?? '');
$charges = clinic_sp_rows('sp_charges_list', [$filterPatientId, $filterStatus]);

$totalAmount = 0.0;
$totalPaid = 0.0;
$totalDue = 0.0;
$unpaidCount = 0;
foreach ($charges as $c) {
    $totalAmount += (float) ($c['Amount'] ?? 0);
    $totalPaid += (float) ($c['Paid_Amount'] ?? 0);
    $due = (float) ($c['Due'] ?? 0);
    $totalDue += $due;
    if ($due > 0) {
        $unpaidCount++;
    }
}

clinic_page_start('Charges & Bills', 'Every service a patient receives adds a charge; payments clear the bill.');
?><div class="row g-3 mb-4">
    <?php clinic_metric_card('Total Billed', clinic_money($totalAmount), 'ti-receipt-2', 'primary', count($charges) . ' charge(s)'); ?>
    <?php clinic_metric_card('Unpaid (Debt)', clinic_money($totalDue), 'ti-wallet', 'danger', $unpaidCount . ' open charge(s)'); ?>
    <?php clinic_metric_card('Collected', clinic_money($totalPaid), 'ti-circle-check', 'success', 'Against these charges'); ?>
</div>

<div class="card clinic-card mb-3">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
            <h5 class="mb-0">Charges</h5>
            <div class="text-muted small">Consultation fees are added automatically when an appointment is completed. Add other services here.</div>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a class="btn btn-outline-success btn-sm" href="payments.php<?php echo $filterPatientId > 0 ? '?patient_id=' . $filterPatientId : ''; ?>"><i class="ti ti-cash me-1"></i>Collect Payment</a>
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#chargeModal"><i class="ti ti-plus me-1"></i>Add Charge</button>
        </div>
    </div>
    <div class="card-body pt-2">
        <form class="d-flex flex-wrap align-items-end gap-2 mb-3" method="get">
            <div>
                <label class="form-label small mb-1">Patient</label>
                <select class="form-select" name="patient_id" style="min-width:220px;">
                    <option value="0">All patients</option>
                    <?php clinic_select_options($patients, 'Patient_ID', 'Full_Name', $filterPatientId); ?>
                </select>
            </div>
            <div>
                <label class="form-label small mb-1">Status</label>
                <select class="form-select" name="status">
                    <option value="">All statuses</option>
                    <option value="Unpaid"<?php echo $filterStatus === 'Unpaid' ? ' selected' : ''; ?>>Unpaid</option>
                    <option value="Paid"<?php echo $filterStatus === 'Paid' ? ' selected' : ''; ?>>Paid</option>
                </select>
            </div>
            <button class="btn btn-light border" type="submit"><i class="ti ti-filter me-1"></i>Filter</button>
        </form>
        <div class="table-responsive">
            <table class="table table-hover align-middle clinic-table mb-0<?php echo $charges === [] ? '' : ' datatable'; ?>">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Patient</th>
                        <th>Description</th>
                        <th>Category</th>
                        <th class="text-end">Amount</th>
                        <th class="text-end">Paid</th>
                        <th class="text-end">Due</th>
                        <th>Performed By</th>
                        <th class="text-center">Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>                    <?php foreach ($charges as $c): ?>
                    <?php $due = (float) ($c['Due'] ?? 0); ?>
                    <tr>
                        <td class="text-nowrap small"><?php echo clinic_h((string) ($c['Charge_Date'] ?? '')); ?></td>
                        <td class="fw-semibold"><?php echo clinic_h((string) ($c['Patient_Name'] ?? '—')); ?></td>
                        <td>
                            <?php echo clinic_h((string) ($c['Description'] ?? '-')); ?>
                            <?php if (!empty($c['Source_Type']) && (string) $c['Source_Type'] !== 'Manual'): ?>
                            <span class="badge text-bg-light border ms-1"><?php echo clinic_h($c['Source_Type']); ?>#<?php echo (int) ($c['Source_ID'] ?? 0); ?></span>
                            <?php endif; ?>
                        </td>
                        <td><span class="badge text-bg-light border"><?php echo clinic_h((string) ($c['Category'] ?? '-')); ?></span></td>
                        <td class="text-end"><?php echo clinic_money((float) ($c['Amount'] ?? 0)); ?></td>
                        <td class="text-end text-success"><?php echo clinic_money((float) ($c['Paid_Amount'] ?? 0)); ?></td>
                        <td class="text-end <?php echo $due > 0 ? 'text-danger fw-semibold' : ''; ?>"><?php echo clinic_money($due); ?></td>
                        <td class="small"><?php echo $c['Performed_By_Name'] ? '@' . clinic_h($c['Performed_By_Name']) : '—'; ?></td>
                        <td class="text-center">
                            <?php if ($due > 0): ?><span class="badge text-bg-warning">Unpaid</span>
                            <?php else: ?><span class="badge text-bg-success">Paid</span><?php endif; ?>
                        </td>
                        <td class="text-end">
                            <?php if ((float) ($c['Paid_Amount'] ?? 0) === 0.0): ?>
                            <form class="d-inline" method="post" onsubmit="return confirm('Delete this charge?');">
                                <input type="hidden" name="csrf_token" value="<?php echo clinic_h(clinic_csrf_token()); ?>">
                                <input type="hidden" name="action" value="delete_charge">
                                <input type="hidden" name="Charge_ID" value="<?php echo (int) ($c['Charge_ID'] ?? 0); ?>">
                                <button class="btn btn-sm btn-outline-danger btn-icon" title="Delete"><i class="ti ti-trash"></i></button>
                            </form>
                            <?php else: ?><span class="text-muted small">—</span><?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if ($charges === []): ?>
                    <tr><td class="text-center text-muted py-4" colspan="10">No charges found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<!-- Add charge modal -->
<div class="modal fade" id="chargeModal" tabindex="-1" aria-labelledby="chargeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <form method="post" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="chargeModalLabel"><i class="ti ti-receipt me-1"></i>Add Charge</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?php echo clinic_h(clinic_csrf_token()); ?>">
                <input type="hidden" name="action" value="add_charge">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Patient <span class="text-danger">*</span></label>
                        <select class="form-select" name="Patient_ID" required>
                            <option value="">-- Select patient --</option>
                            <?php clinic_select_options($patients, 'Patient_ID', 'Full_Name', $filterPatientId ?: null); ?>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Category</label>
                        <select class="form-select" name="Category">
                            <?php foreach (CLINIC_CHARGE_CATEGORIES as $cat): ?>
                            <option value="<?php echo clinic_h($cat); ?>"><?php echo clinic_h($cat); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12 mb-3">
                        <label class="form-label">Description <span class="text-danger">*</span></label>
                        <input class="form-control" name="Description" required placeholder="e.g. X-ray chest, Dressing, Injection set, Consultation top-up">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Amount <span class="text-danger">*</span></label>
                        <input class="form-control" type="number" step="0.01" min="0" name="Amount" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Performed by</label>
                        <select class="form-select" name="Performed_By">
                            <option value="0">Me (current user)</option>
                            <?php clinic_select_options($users, 'User_ID', 'Username'); ?>
                        </select>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Earnings for performer</label>
                        <input class="form-control" type="number" step="0.01" min="0" name="Earning_Amount" value="0">
                        <div class="form-text">Optional amount owed to the person who did this service (e.g. doctor commission).</div>
                    </div>
                    <div class="col-12">
                        <div class="form-text">Consultation fees for completed appointments are added automatically. Use this form for any other billable service.</div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-danger" data-bs-dismiss="modal"><i class="ti ti-x me-1"></i>Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="ti ti-device-floppy me-1"></i>Add Charge</button>
            </div>
        </form>
    </div>
</div>

<?php clinic_page_end(); ?>