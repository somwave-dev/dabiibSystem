<?php
require_once __DIR__ . '/../includes/advanced_components.php';

try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        clinic_check_csrf();
        if (clinic_post_string('action') === 'collect_payment') {
            clinic_sp_one('sp_collect_payment', [
                clinic_post_int('Patient_ID'),
                clinic_post_int('Account_ID'),
                clinic_post_float('Amount'),
                clinic_post_string('Payment_Method') ?: 'Cash',
                clinic_post_string('Transaction_Ref'),
                clinic_current_user_id(),
            ]);
            clinic_flash('Payment collected and balances updated.');
            clinic_redirect('payments.php?patient_id=' . clinic_post_int('Patient_ID'));
        }
    }
} catch (Throwable $e) {
    clinic_flash($e->getMessage(), 'danger');
    clinic_redirect('payments.php');
}

$patients = clinic_sp_rows('sp_patients_list');
$accounts = clinic_sp_rows('sp_accounts_list');
$payments = clinic_sp_rows('sp_payments_list');
$patientId = (int) ($_GET['patient_id'] ?? 0);
$profile = $patientId > 0 ? clinic_sp_one('sp_patient_profile', [$patientId], 'i') : null;

clinic_page_start('Payment Collection', 'Collect patient payments, update account balance, and reduce patient debt.');
?>
<div class="row g-3">
    <div class="col-xl-4">
        <div class="card clinic-card">
            <div class="card-header"><h5 class="mb-0">Collect Payment</h5></div>
            <div class="card-body">
                <?php if ($profile): ?>
                <div class="alert alert-light border">
                    <div class="fw-semibold"><?php echo clinic_h($profile['Full_Name']); ?></div>
                    <div class="small text-muted">Current balance: <strong><?php echo clinic_money($profile['Current_Balance']); ?></strong></div>
                </div>
                <?php endif; ?>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?php echo clinic_h(clinic_csrf_token()); ?>">
                    <input type="hidden" name="action" value="collect_payment">
                    <div class="mb-3"><label class="form-label">Patient</label><select class="form-select" name="Patient_ID" required><?php clinic_select_options($patients, 'Patient_ID', 'Full_Name', $patientId ?: null); ?></select></div>
                    <div class="mb-3"><label class="form-label">Account</label><select class="form-select" name="Account_ID" required><?php clinic_select_options($accounts, 'Account_ID', 'Account_Name'); ?></select></div>
                    <div class="mb-3"><label class="form-label">Amount</label><input class="form-control" type="number" step="0.01" name="Amount" required></div>
                    <div class="mb-3"><label class="form-label">Method</label><select class="form-select" name="Payment_Method"><option>Cash</option><option>EVC Plus</option><option>eDahab</option><option>Bank</option></select></div>
                    <div class="mb-3"><label class="form-label">Transaction reference</label><input class="form-control" name="Transaction_Ref"></div>
                    <button class="btn btn-success w-100">Collect Payment</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-xl-8">
        <div class="card clinic-card">
            <div class="card-header"><h5 class="mb-0">Recent Payments</h5></div>
            <div class="card-body table-responsive">
                <table class="table table-hover align-middle datatable clinic-table">
                    <thead><tr><th>Patient</th><th>Account</th><th>Amount</th><th>Method</th><th>Reference</th><th>Date</th></tr></thead>
                    <tbody>
                        <?php foreach ($payments as $row): ?>
                        <tr>
                            <td class="fw-semibold"><?php echo clinic_h($row['Patient_Name'] ?? '-'); ?></td>
                            <td><?php echo clinic_h($row['Account_Name'] ?? '-'); ?></td>
                            <td><?php echo clinic_money($row['Amount']); ?></td>
                            <td><?php echo clinic_h($row['Payment_Method']); ?></td>
                            <td><?php echo clinic_h($row['Transaction_Ref'] ?? '-'); ?></td>
                            <td><?php echo clinic_h($row['Payment_Date']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php clinic_page_end(); ?>
