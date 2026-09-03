<?php
require_once __DIR__ . '/../includes/advanced_components.php';

try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        clinic_check_csrf();
        if (clinic_post_string('action') === 'collect_payment') {
            clinic_require_can(13, 'insert');

            $payPatientId = clinic_post_int('Patient_ID');
            if ($payPatientId < 1) {
                throw new RuntimeException('Select a patient first.');
            }
            $payAccountId = clinic_post_int('Account_ID');
            $tender = round((float) clinic_post_float('Amount'), 2);
            if ($tender <= 0) {
                throw new RuntimeException('Enter the amount received.');
            }
            // Multi-select invoices: distribute the received amount across the
            // chosen open invoices (oldest first). Supports partial payments.
            $rawIds = $_POST['selected_invoices'] ?? [];
            if (!is_array($rawIds)) {
                $rawIds = [];
            }
            $selectedIds = [];
            foreach ($rawIds as $rid) {
                $rid = (int) $rid;
                if ($rid > 0) {
                    $selectedIds[$rid] = true;
                }
            }
            if ($selectedIds === []) {
                throw new RuntimeException('Select at least one invoice to pay.');
            }
            $openCharges = array_values(array_filter(
                clinic_sp_rows('sp_charges_list', [$payPatientId, 'Unpaid']),
                static fn (array $r): bool => isset($selectedIds[(int) ($r['Charge_ID'] ?? 0)])
            ));
            usort($openCharges, static fn (array $a, array $b): int =>
                strcmp((string) ($a['Charge_Date'] ?? ''), (string) ($b['Charge_Date'] ?? ''))
                    ?: ((int) ($a['Charge_ID'] ?? 0) <=> (int) ($b['Charge_ID'] ?? 0)));

            $targetDue = 0.0;
            foreach ($openCharges as $oc) {
                $targetDue += (float) ($oc['Due'] ?? 0);
            }
            $applied = round(min($tender, max($targetDue, 0.0)), 2);
            $change = round($tender - $applied, 2);
            if ($applied <= 0) {
                throw new RuntimeException('There is nothing to pay for the selected invoice(s).');
            }
            $detailsNote = trim((string) clinic_post_string('Details'));

            $remaining = $applied;
            $lastIndex = count($openCharges) - 1;
            foreach ($openCharges as $index => $oc) {
                if ($remaining <= 0.001) {
                    break;
                }
                $due = (float) ($oc['Due'] ?? 0);
                $pay = round(min($remaining, $due), 2);
                if ($pay <= 0.001) {
                    continue;
                }
                $remaining = round($remaining - $pay, 2);
                $details = $detailsNote !== ''
                    ? $detailsNote
                    : 'Payment for: ' . (string) ($oc['Description'] ?? ('Invoice #' . (int) ($oc['Charge_ID'] ?? 0)));
                $lastChange = ($index === $lastIndex || $remaining <= 0.001) ? $change : 0.00;
                clinic_sp_one('sp_collect_payment_invoice', [
                    $payPatientId,
                    $payAccountId,
                    $pay,
                    clinic_post_string('Payment_Method') ?: 'Cash',
                    clinic_post_string('Transaction_Ref'),
                    clinic_current_user_id(),
                    (int) ($oc['Charge_ID'] ?? 0),
                    $details,
                    $lastChange,
                ]);
            }

            $message = 'Payment of ' . clinic_money($applied) . ' collected and balances updated.';
            if ($change > 0.01) {
                $message .= ' Change to return: ' . clinic_money($change) . '.';
            }
            clinic_flash($message);
            clinic_redirect('payments.php');
        }
    }
} catch (Throwable $e) {
    clinic_flash($e->getMessage(), 'danger');
    $redirPid = (int) ($_GET['patient_id'] ?? 0);
    clinic_redirect($redirPid > 0 ? 'payments.php?patient_id=' . $redirPid : 'payments.php');
}

$patients = clinic_sp_rows('sp_patients_list');
$accounts = clinic_sp_rows('sp_accounts_list');
$payments = clinic_sp_rows('sp_payments_list');

// ---- Summary metrics (mirrors the Receipts/Transactions dashboard) ----
$summary = [
    'billed'      => 0.0,
    'collected'   => 0.0,
    'outstanding' => 0.0,
    'txn_count'   => count($payments),
    'fully_paid'  => 0,
    'partial'     => 0,
];
$connS = $GLOBALS['conn'] ?? null;
if ($connS instanceof mysqli) {
    if ($rS = $connS->query('SELECT COALESCE(SUM(Amount),0) t FROM charges')) {
        $summary['billed'] = (float) $rS->fetch_assoc()['t'];
    }
    if ($rS = $connS->query('SELECT COALESCE(SUM(Amount),0) t FROM payments')) {
        $summary['collected'] = (float) $rS->fetch_assoc()['t'];
    }
    if ($rS = $connS->query('SELECT COALESCE(SUM(GREATEST(Amount - COALESCE(Paid_Amount,0),0)),0) t FROM charges WHERE Status = "Unpaid"')) {
        $summary['outstanding'] = (float) $rS->fetch_assoc()['t'];
    }
    if ($rS = $connS->query("SELECT COUNT(*) c FROM charges WHERE Status = 'Paid'")) {
        $summary['fully_paid'] = (int) $rS->fetch_assoc()['c'];
    }
    if ($rS = $connS->query("SELECT COUNT(*) c FROM charges WHERE Status = 'Unpaid' AND COALESCE(Paid_Amount,0) > 0")) {
        $summary['partial'] = (int) $rS->fetch_assoc()['c'];
    }
}

$patientId = (int) ($_GET['patient_id'] ?? 0);
$profile = $patientId > 0 ? clinic_sp_one('sp_patient_profile', [$patientId], 'i') : null;
$openCharges = [];
$openTotal = 0.0;
if ($profile) {
    $openCharges = clinic_sp_rows('sp_charges_list', [$patientId, 'Unpaid']);
    foreach ($openCharges as $oc) {
        $openTotal += (float) ($oc['Due'] ?? 0);
    }
}

// AJAX: open invoices for the selected patient (instant dropdown fill).
if ((string) ($_GET['ajax'] ?? '') === 'invoices') {
    header('Content-Type: application/json; charset=utf-8');
    $ajaxPid = (int) ($_GET['patient_id'] ?? 0);
    $ajaxProfile = $ajaxPid > 0 ? clinic_sp_one('sp_patient_profile', [$ajaxPid], 'i') : null;
    $ajaxCharges = [];
    $ajaxTotal = 0.0;
    if ($ajaxProfile) {
        foreach (clinic_sp_rows('sp_charges_list', [$ajaxPid, 'Unpaid']) as $oc) {
            $ajaxCharges[] = [
                'Charge_ID'   => (int) ($oc['Charge_ID'] ?? 0),
                'Description' => (string) ($oc['Description'] ?? 'Charge'),
                'Category'    => (string) ($oc['Category'] ?? ''),
                'Charge_Date' => (string) ($oc['Charge_Date'] ?? ''),
                'Due'         => (float) ($oc['Due'] ?? 0),
            ];
            $ajaxTotal += (float) ($oc['Due'] ?? 0);
        }
    }
    echo json_encode([
        'ok'      => $ajaxProfile !== null,
        'name'    => $ajaxProfile ? (string) ($ajaxProfile['Full_Name'] ?? '') : '',
        'balance' => $ajaxProfile ? (float) ($ajaxProfile['Current_Balance'] ?? 0) : 0,
        'total'   => round($ajaxTotal, 2),
        'charges' => $ajaxCharges,
    ]);
    exit;
}

clinic_page_start('Payment Collection', 'Collect patient payments, update account balance, and reduce patient debt.');
?>
<style>
    /* Receipt modal: non-select inputs are 1/3 (col-4); selects/full rows stay wide. */
    #payForm { display: flex; flex-wrap: wrap; gap: .6rem 1rem; align-items: flex-start; }
    #payForm .mb-3 { margin-bottom: 0 !important; }
    #payForm .w-full { flex: 0 0 100%; }
    #payForm .w-third { flex: 0 0 calc(33.333% - .9rem); min-width: 0; }
    #payForm .badges-wrap { flex: 0 0 100%; }
    #payForm button[type="submit"] { flex: 0 0 100%; }
    @media (max-width: 767px) { #payForm .w-third { flex-basis: 100%; } }
</style>
<!-- Summary metrics -->
<div class="row mb-4 g-3">
    <div class="col-xl-3 col-md-6">
        <div class="card clinic-card h-100"><div class="card-body d-flex align-items-center gap-3">
            <div class="rounded-3 d-inline-flex align-items-center justify-content-center" style="width:3rem;height:3rem;background:rgba(13,110,253,.12);color:#0d6efd;"><i class="ti ti-file-invoice"></i></div>
            <div><div class="text-muted small text-uppercase">Total Billed</div><div class="fs-5 fw-bold"><?php echo clinic_money($summary['billed']); ?></div></div>
        </div></div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="card clinic-card h-100"><div class="card-body d-flex align-items-center gap-3">
            <div class="rounded-3 d-inline-flex align-items-center justify-content-center" style="width:3rem;height:3rem;background:rgba(25,135,84,.12);color:#198754;"><i class="ti ti-cash"></i></div>
            <div><div class="text-muted small text-uppercase">Total Collected</div><div class="fs-5 fw-bold text-success"><?php echo clinic_money($summary['collected']); ?></div></div>
        </div></div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="card clinic-card h-100"><div class="card-body d-flex align-items-center gap-3">
            <div class="rounded-3 d-inline-flex align-items-center justify-content-center" style="width:3rem;height:3rem;background:rgba(220,53,69,.12);color:#dc3545;"><i class="ti ti-report-money"></i></div>
            <div><div class="text-muted small text-uppercase">Outstanding</div><div class="fs-5 fw-bold text-danger"><?php echo clinic_money($summary['outstanding']); ?></div></div>
        </div></div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="card clinic-card h-100"><div class="card-body d-flex align-items-center gap-3">
            <div class="rounded-3 d-inline-flex align-items-center justify-content-center" style="width:3rem;height:3rem;background:rgba(255,193,7,.16);color:#b98a00;"><i class="ti ti-receipt-2"></i></div>
            <div><div class="text-muted small text-uppercase">Transactions</div><div class="fs-5 fw-bold"><?php echo number_format($summary['txn_count']); ?></div></div>
        </div></div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="card clinic-card h-100"><div class="card-body d-flex align-items-center gap-3">
            <div class="rounded-3 d-inline-flex align-items-center justify-content-center" style="width:3rem;height:3rem;background:rgba(25,135,84,.12);color:#198754;"><i class="ti ti-check"></i></div>
            <div><div class="text-muted small text-uppercase">Fully Paid</div><div class="fs-5 fw-bold text-success"><?php echo number_format($summary['fully_paid']); ?></div></div>
        </div></div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="card clinic-card h-100"><div class="card-body d-flex align-items-center gap-3">
            <div class="rounded-3 d-inline-flex align-items-center justify-content-center" style="width:3rem;height:3rem;background:rgba(255,193,7,.16);color:#b98a00;"><i class="ti ti-adjustments"></i></div>
            <div><div class="text-muted small text-uppercase">Partial</div><div class="fs-5 fw-bold"><?php echo number_format($summary['partial']); ?></div></div>
        </div></div>
    </div>
</div>

<div class="row g-3">
    <div class="col-xl-4">
        <div class="card clinic-card">
            <div class="card-header"><h5 class="mb-0">Collect Payment</h5></div>
            <div class="card-body">
          
                <form method="post" id="payForm">
                    <input type="hidden" name="csrf_token" value="<?php echo clinic_h(clinic_csrf_token()); ?>">
                    <input type="hidden" name="action" value="collect_payment">
                    <div class="mb-3 w-full" style="order:-2">
                        <label class="form-label">Patient</label>
                        <select class="form-select js-searchable" name="Patient_ID" id="payPatientSelect" required data-placeholder="Search patient by id, name or phone">
                            <option value=""></option>
                            <?php foreach ($patients as $pat): ?>
                            <?php $patId = (int) ($pat['Patient_ID'] ?? 0); $patName = (string) ($pat['Full_Name'] ?? ''); $patTel = (string) ($pat['Phone_Number'] ?? ''); ?>
                            <option value="<?php echo $patId; ?>" data-tel="<?php echo clinic_h($patTel); ?>"<?php echo ((int) $patientId === $patId) ? ' selected' : ''; ?>>
                                #<?php echo $patId; ?> — <?php echo clinic_h($patName !== '' ? $patName : ('Patient #' . $patId)); ?><?php echo $patTel !== '' ? ' · ' . clinic_h($patTel) : ''; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Type to search by patient id, name or phone number.</div>
                    </div>
                    <div class="mb-3 w-full" style="order:-1">
                        <label class="form-label fw-semibold d-flex justify-content-between align-items-center flex-wrap gap-1">
                            <span>Select Invoices <small class="text-muted fw-normal">(Multi-select)</small></span>
                            <span class="small text-muted fw-normal">Balance: <strong id="payBalanceInline" class="text-primary"><?php echo $profile ? clinic_money($profile['Current_Balance']) : '—'; ?></strong></span>
                        </label>
                        <select class="form-select border border-secondary" name="selected_invoices[]" id="payChargeSelect" multiple size="5" <?php echo $profile ? '' : 'disabled'; ?>>
                            <?php foreach ($openCharges as $oc): ?>
                            <option value="<?php echo (int) ($oc['Charge_ID'] ?? 0); ?>" data-due="<?php echo (float) ($oc['Due'] ?? 0); ?>">
                                #<?php echo (int) ($oc['Charge_ID'] ?? 0); ?> — <?php echo clinic_h((string) ($oc['Description'] ?? 'Invoice')); ?> (<?php echo clinic_h((string) ($oc['Category'] ?? '-')); ?>) · <?php echo clinic_h((string) ($oc['Charge_Date'] ?? '')); ?> · <?php echo clinic_money((float) ($oc['Due'] ?? 0)); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text" id="payInvoiceHint"><?php echo $profile ? 'Select one or more invoices to pay (Ctrl/click or use the keyboard).' : 'Choose a patient first to load their open invoices.'; ?></div>
                    </div>
                    <div class="mb-3 w-third" style="order:1">
                        <label class="form-label">Amount received</label>
                        <input class="form-control" type="number" step="0.01" min="0.01" name="Amount" id="payAmount" required>
                    </div>
                    <div class="badges-wrap d-flex flex-wrap gap-2 mb-3" style="order:0">
                        <span class="badge text-bg-light border" id="payApplies">Applies: <?php echo clinic_money(0); ?></span>
                        <span class="badge text-bg-success" id="payChange" style="display:none;">Change: <?php echo clinic_money(0); ?></span>
                    </div>
                    <div class="w-full mb-3" style="order:3">
                        <label class="form-label fw-semibold">Invoice Summary</label>
                        <div id="payInvoiceSummary" class="bg-light p-2 rounded border small text-muted">
                            <span id="payInvoiceSummaryText">No invoices selected yet.</span>
                        </div>
                    </div>
                    <div class="mb-3 w-third" style="order:1"><label class="form-label">Account</label><select class="form-select" name="Account_ID" required><?php clinic_select_options($accounts, 'Account_ID', 'Account_Name'); ?></select></div>
                    <div class="mb-3 w-third" style="order:1"><label class="form-label">Method</label><select class="form-select" name="Payment_Method"><option>Cash</option><option>EVC Plus</option><option>eDahab</option><option>Bank</option></select></div>
                    <div class="mb-3 w-third" style="order:1">
                        <label class="form-label">Transaction reference</label>
                        <input class="form-control" name="Transaction_Ref" placeholder="e.g. Txn-<?php echo date('ymd'); ?>-…">
                    </div>
                    <div class="mb-3 w-third" style="order:1">
                        <label class="form-label">Details</label>
                        <textarea class="form-control" name="Details" rows="2" placeholder="Optional note — defaults to the selected invoice details."></textarea>
                    </div>
                    <button class="btn btn-success w-100" style="order:4">Collect Payment</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-12">
        <div class="card clinic-card">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h5 class="mb-0"><i class="ti ti-list-details me-1 text-primary"></i>Payments List</h5>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#mdl_receipt"><i class="ti ti-plus me-1"></i>Add New Payment</button>
                    <span class="badge text-bg-light border"><?php echo number_format($summary['txn_count']); ?> transactions · <?php echo clinic_money($summary['collected']); ?> collected</span>
                </div>
            </div>
            <div class="card-body table-responsive">
                <table class="table table-hover align-middle datatable clinic-table">
                    <thead><tr><th>Patient</th><th>Account</th><th>Invoice</th><th>Amount</th><th>Change</th><th>Method</th><th>Reference</th><th>Date</th><th>Details</th></tr></thead>
                    <tbody>
                        <?php foreach ($payments as $row): ?>
                        <tr>
                            <td class="fw-semibold"><?php echo clinic_h($row['Patient_Name'] ?? '-'); ?></td>
                            <td><?php echo clinic_h($row['Account_Name'] ?? '-'); ?></td>
                            <td><?php echo (int) ($row['Charge_ID'] ?? 0) > 0 ? '#' . (int) $row['Charge_ID'] : '-'; ?></td>
                            <td><?php echo clinic_money($row['Amount']); ?></td>
                            <td><?php echo (float) ($row['Change_Given'] ?? 0) > 0 ? clinic_money($row['Change_Given']) : '-'; ?></td>
                            <td><?php echo clinic_h($row['Payment_Method']); ?></td>
                            <td><?php echo clinic_h($row['Transaction_Ref'] ?? '-'); ?></td>
                            <td><?php echo clinic_h($row['Payment_Date']); ?></td>
                            <td class="small text-muted"><?php echo clinic_h((string) ($row['Details'] ?? '')); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<script>
(function () {
    'use strict';
    var chargeSel = document.getElementById('payChargeSelect');
    var amount = document.getElementById('payAmount');
    var appliesEl = document.getElementById('payApplies');
    var changeEl = document.getElementById('payChange');
    var hint = document.getElementById('payInvoiceHint');
    var patientSel = document.getElementById('payPatientSelect');
    var fmtMoney = function (n) { return '$' + Number(n).toFixed(2); };
    var sumText = document.getElementById('payInvoiceSummaryText');
    if (!chargeSel || !amount) { return; }

    function selectedSum() {
        var s = 0;
        Array.prototype.forEach.call(chargeSel.selectedOptions, function (o) {
            var v = o.getAttribute('data-due');
            if (v !== null) { s += parseFloat(v); }
        });
        return s;
    }

    function recompute() {
        var given = parseFloat(amount.value);
        if (isNaN(given) || given < 0) { given = 0; }
        var target = selectedSum();
        var applies = Math.min(given, target);
        var change = given - applies;
        appliesEl.textContent = 'Applies: $' + applies.toFixed(2) + (target > 0 ? ' of $' + target.toFixed(2) : '');
        if (change > 0.004) {
            changeEl.style.display = '';
            changeEl.textContent = 'Change to return: $' + change.toFixed(2);
        } else {
            changeEl.style.display = 'none';
        }
        if (sumText) {
            var cnt = chargeSel.selectedOptions.length;
            var selDue = selectedSum();
            var summary = cnt + ' invoice' + (cnt === 1 ? '' : 's') + ' selected · Due ' + fmtMoney(selDue)
                + ' · Applies ' + fmtMoney(applies);
            if (change > 0.004) { summary += ' · Change ' + fmtMoney(change); }
            sumText.textContent = summary;
        }
    }

    function destroySel2() {
        if (window.jQuery && jQuery.fn.select2) { try { jQuery(chargeSel).select2('destroy'); } catch (e) {} }
    }
    function initSel2() {
        if (window.jQuery && jQuery.fn.select2 && !chargeSel.disabled) {
            try {
                jQuery(chargeSel).select2({
                    dropdownParent: jQuery(chargeSel).closest('.modal').length ? jQuery(chargeSel).closest('.modal') : jQuery(document.body),
                    width: '100%'
                });
            } catch (e) {}
        }
    }
    function refreshInvoices(patientId) {
        if (!patientId) {
            destroySel2(); chargeSel.innerHTML = ''; chargeSel.disabled = true;
            if (hint) { hint.textContent = 'Choose a patient first to load their open invoices.'; }
            if (sumText) { sumText.textContent = 'Choose a patient first to load their invoices.'; }
            recompute(); return;
        }
        fetch('payments.php?ajax=invoices&patient_id=' + encodeURIComponent(patientId), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                destroySel2();
                chargeSel.innerHTML = '';
                if (!data || !data.ok) {
                    chargeSel.disabled = true;
                    if (hint) { hint.textContent = 'Patient not found.'; }
                    recompute(); return;
                }
                var nameEl = document.getElementById('payPatientName');
                var balEl = document.getElementById('payPatientBalance');
                var listEl = document.getElementById('payOpenList');
                var totalEl = document.getElementById('payOpenTotal');
                if (nameEl) { nameEl.textContent = data.name; }
                if (balEl) {
                    balEl.textContent = 'Balance ' + fmtMoney(data.balance);
                    balEl.className = 'badge ' + (data.balance > 0 ? 'text-bg-danger' : 'text-bg-success');
                }
                var balInline = document.getElementById('payBalanceInline');
                if (balInline) { balInline.textContent = fmtMoney(data.balance); }
                if (listEl) {
                    listEl.innerHTML = '';
                    var chargeList = data.charges || [];
                    if (chargeList.length === 0) {
                        var msgLi = document.createElement('li');
                        msgLi.className = 'text-muted';
                        msgLi.textContent = 'No open charges — this patient has a clean bill.';
                        listEl.appendChild(msgLi);
                    }
                    chargeList.forEach(function (c) {
                        var li = document.createElement('li');
                        li.className = 'd-flex justify-content-between border-bottom py-1';
                        var span = document.createElement('span');
                        span.textContent = c.Description + (c.Category ? ' (' + c.Category + ')' : '');
                        var strong = document.createElement('strong');
                        strong.textContent = fmtMoney(c.Due);
                        li.appendChild(span); li.appendChild(strong);
                        listEl.appendChild(li);
                    });
                }
                if (totalEl) { totalEl.textContent = fmtMoney(data.total); }
                (data.charges || []).forEach(function (c) {
                    var o = document.createElement('option');
                    o.value = String(c.Charge_ID);
                    o.setAttribute('data-due', c.Due.toFixed(2));
                    o.textContent = '#' + c.Charge_ID + ' — ' + c.Description + (c.Category ? ' (' + c.Category + ')' : '') + ' · ' + (c.Charge_Date || '') + ' · ' + fmtMoney(c.Due);
                    chargeSel.appendChild(o);
                });
                if (data.charges.length > 0) {
                    chargeSel.disabled = false;
                    if (hint) { hint.textContent = 'Select one or more invoices to pay (multi-select).'; }
                    // Convenience: all open invoices start selected; amount fills with the total.
                    Array.prototype.forEach.call(chargeSel.options, function (o) { o.selected = true; });
                    amount.value = data.total.toFixed(2);
                } else {
                    chargeSel.disabled = true;
                    if (hint) { hint.textContent = 'No open invoices for this patient.'; }
                    amount.value = '';
                }
                initSel2();
                recompute();
            })
            .catch(function () { if (hint) { hint.textContent = 'Could not load invoices.'; } });
    }

    chargeSel.addEventListener('change', function () {
        if (amount.value === '') { amount.value = selectedSum().toFixed(2); } // auto-fill when empty
        recompute();
    });
    amount.addEventListener('input', recompute);
    if (patientSel) {
        patientSel.addEventListener('change', function () { refreshInvoices(patientSel.value); });
    }
    if (!chargeSel.disabled && chargeSel.options.length > 0) {
        Array.prototype.forEach.call(chargeSel.options, function (o) { o.selected = true; });
        amount.value = selectedSum().toFixed(2);
    }
    recompute();

    // Convert the inline collect form into the Receipt modal (sample layout).
    (function moveToReceiptModal() {
        var card = document.querySelector('#payPatientSelect');
        if (!card) { return; }
        card = card.closest('.card.clinic-card');
        var col = card ? card.closest('.col-xl-4') : null;
        if (!card || !col) { return; }
        col.style.display = 'none'; // collect lives in the modal now

        var modal = document.createElement('div');
        modal.className = 'modal fade';
        modal.id = 'mdl_receipt';
        modal.setAttribute('tabindex', '-1');
        modal.setAttribute('aria-hidden', 'true');
        modal.setAttribute('data-bs-backdrop', 'static');
        modal.setAttribute('data-bs-keyboard', 'false');

        var dialog = document.createElement('div');
        dialog.className = 'modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable';
        var content = document.createElement('div');
        content.className = 'modal-content';
        content.appendChild(card);
        dialog.appendChild(content);
        modal.appendChild(dialog);
        document.body.appendChild(modal);

        var head = card.querySelector('.card-header');
        if (head) {
            head.style.display = 'flex';
            head.style.justifyContent = 'space-between';
            head.style.alignItems = 'center';
            var close = document.createElement('button');
            close.type = 'button';
            close.className = 'btn-close';
            close.setAttribute('data-bs-dismiss', 'modal');
            close.setAttribute('aria-label', 'Close');
            head.appendChild(close);
        }
        var body = card.querySelector('.card-body');
        if (body) {
            var foot = document.createElement('div');
            foot.className = 'modal-footer border-top pt-3';
            var cancel = document.createElement('button');
            cancel.type = 'button';
            cancel.className = 'btn btn-light border';
            cancel.textContent = 'Cancel';
            cancel.setAttribute('data-bs-dismiss', 'modal');
            foot.appendChild(cancel);
            body.appendChild(foot);
        }
    })();
})();
</script>
<?php clinic_page_end(); ?>
