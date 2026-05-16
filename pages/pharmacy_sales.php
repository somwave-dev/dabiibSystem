<?php
require_once __DIR__ . '/../includes/advanced_components.php';

try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        clinic_check_csrf();
        if (clinic_post_string('action') === 'record_cart_sale') {
            $patientId = clinic_post_int('Patient_ID');
            $accountId = clinic_post_int('Account_ID');
            $paymentMethod = clinic_post_string('Payment_Method') ?: 'Cash';
            $received = clinic_post_float('Received');
            $medicineIds = $_POST['Medicine_ID'] ?? [];
            $quantities = $_POST['Quantity'] ?? [];
            $saleTotal = 0.0;
            $processedItems = 0;

            if ($patientId < 1 || !is_array($medicineIds) || count($medicineIds) === 0) {
                throw new RuntimeException('Select a patient and at least one medicine.');
            }

            $stockRows = clinic_sp_rows('sp_medicines_list');
            $medicineStock = [];
            foreach ($stockRows as $medicine) {
                $medicineStock[(int) $medicine['Medicine_ID']] = [
                    'name' => (string) $medicine['Medicine_Name'],
                    'stock' => (int) $medicine['Stock_Quantity'],
                ];
            }

            foreach ($medicineIds as $index => $medicineId) {
                $medicineId = (int) $medicineId;
                $qty = max(1, (int) ($quantities[$index] ?? 1));
                if ($medicineId < 1) {
                    continue;
                }
                if (!isset($medicineStock[$medicineId])) {
                    throw new RuntimeException('Selected medicine was not found.');
                }
                if ($qty > $medicineStock[$medicineId]['stock']) {
                    throw new RuntimeException($medicineStock[$medicineId]['name'] . ' has only ' . $medicineStock[$medicineId]['stock'] . ' in stock.');
                }
                $result = clinic_sp_one('sp_record_pharmacy_sale', [$patientId, $medicineId, $qty, clinic_current_user_id()]);
                $saleTotal += (float) ($result['Total_Price'] ?? 0);
                $processedItems++;
            }

            if ($processedItems === 0) {
                throw new RuntimeException('Add at least one medicine before saving.');
            }

            if ($accountId < 1) {
                throw new RuntimeException('Select the account that will receive the money.');
            }

            $paymentAmount = $received > 0 ? min($received, $saleTotal) : $saleTotal;
            clinic_sp_one('sp_collect_payment', [
                $patientId,
                $accountId,
                $paymentAmount,
                $paymentMethod,
                'PHARMACY-POS',
                clinic_current_user_id(),
            ]);

            clinic_flash('Purchase saved successfully.');
            clinic_redirect('pharmacy_sales.php?patient_id=' . $patientId);
        }
    }
} catch (Throwable $e) {
    clinic_flash($e->getMessage(), 'danger');
    clinic_redirect('pharmacy_sales.php');
}

$patients = clinic_sp_rows('sp_patients_list');
$medicines = clinic_sp_rows('sp_medicines_list');
$sales = clinic_sp_rows('sp_pharmacy_sales_list');
$accounts = clinic_sp_rows('sp_accounts_list');
$prescriptions = clinic_sp_rows('sp_prescriptions_list');
$visits = clinic_sp_rows('sp_visits_list');
$visitPatients = [];
foreach ($visits as $visit) {
    $visitPatients[(int) $visit['Visit_ID']] = (int) $visit['Patient_ID'];
}

$prescriptionOrders = [];
foreach ($prescriptions as $prescription) {
    $patientId = (int) ($prescription['Patient_ID'] ?? 0);
    if ($patientId < 1) {
        $patientId = $visitPatients[(int) ($prescription['Visit_ID'] ?? 0)] ?? 0;
    }
    if ($patientId < 1) {
        continue;
    }
    $prescription['Patient_ID'] = $patientId;
    $prescriptionOrders[] = $prescription;
}

$prescriptionPatients = [];
foreach ($prescriptionOrders as $prescription) {
    $patientId = (int) ($prescription['Patient_ID'] ?? 0);
    $medicineId = (int) ($prescription['Medicine_ID'] ?? 0);
    if ($patientId < 1 || $medicineId < 1) {
        continue;
    }
    if (!isset($prescriptionPatients[$patientId])) {
        $prescriptionPatients[$patientId] = [
            'Patient_ID' => $patientId,
            'Patient_Name' => (string) ($prescription['Patient_Name'] ?? 'Patient'),
            'Medicine_IDs' => [],
            'Medicine_Names' => [],
        ];
    }
    $prescriptionPatients[$patientId]['Medicine_IDs'][$medicineId] = $medicineId;
    $prescriptionPatients[$patientId]['Medicine_Names'][$medicineId] = (string) ($prescription['Medicine_Name'] ?? 'Medicine');
}
$prescriptionPatients = array_values($prescriptionPatients);

$requestedPatientId = (int) ($_GET['patient_id'] ?? 0);
$requestedMedicineId = (int) ($_GET['medicine_id'] ?? 0);
$autoSelectedFromPrescription = $requestedPatientId < 1 && $prescriptionOrders !== [];
$selectedPatientId = $requestedPatientId > 0
    ? $requestedPatientId
    : (int) ($prescriptionOrders[0]['Patient_ID'] ?? ($patients[0]['Patient_ID'] ?? 0));
$selectedPrescriptionMedicineId = $requestedMedicineId > 0
    ? $requestedMedicineId
    : (int) ($autoSelectedFromPrescription ? ($prescriptionOrders[0]['Medicine_ID'] ?? 0) : 0);
$selectedPrescriptionMedicineIds = $selectedPrescriptionMedicineId > 0 ? [$selectedPrescriptionMedicineId] : [];
if ($requestedMedicineId < 1) {
    foreach ($prescriptionPatients as $patientOrder) {
        if ((int) ($patientOrder['Patient_ID'] ?? 0) === $selectedPatientId) {
            $selectedPrescriptionMedicineIds = array_values(array_map('intval', $patientOrder['Medicine_IDs'] ?? []));
            break;
        }
    }
}
$selectedPatient = null;
foreach ($patients as $patient) {
    if ((int) $patient['Patient_ID'] === $selectedPatientId) {
        $selectedPatient = $patient;
        break;
    }
}

clinic_page_start('Pharmacy Point Of Sale (POS)', 'Select patient, add medicines to cart, receive payment, and save the purchase.');
?>
<style>
    .pos-shell { background: var(--light); border-radius: 30px; padding: 18px; min-height: 74vh; }
    .pos-left { border: 1px solid var(--border-color); background: var(--white); border-radius: 18px; min-height: 640px; padding: 16px; }
    .pos-cart { background: var(--white); border: 1px solid var(--border-color); border-radius: 18px; min-height: 640px; padding: 18px; box-shadow: var(--box-shadow); }
    .pos-order-pill { border: 1px solid var(--border-color); background: var(--white); border-radius: 999px; padding: .35rem .65rem; display: inline-flex; align-items: center; gap: .4rem; font-size: .78rem; }
    .pos-customer { background: var(--primary); color: #fff; border-radius: 14px; padding: .75rem; display: flex; align-items: center; gap: .75rem; }
    .pos-customer-avatar { width: 38px; height: 38px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; background: var(--primary-transparent); color: var(--primary); font-weight: 800; }
    .medicine-card { background: var(--white); border: 1px solid var(--border-color); border-radius: 14px; padding: .6rem; box-shadow: var(--box-shadow-sm); height: 100%; cursor: pointer; transition: transform .12s, box-shadow .12s; }
    .medicine-card:hover { transform: translateY(-2px); box-shadow: 0 16px 32px rgba(17,24,39,.1); }
    .medicine-img { height: 78px; border-radius: 12px; background: var(--primary-transparent); display: flex; align-items: center; justify-content: center; color: var(--primary); font-size: 2rem; margin-bottom: .55rem; }
    .medicine-selected { outline: 3px solid var(--primary); }
    .add-medicine-card { border: 2px dashed var(--border-color); border-radius: 14px; min-height: 183px; display: flex; align-items: center; justify-content: center; flex-direction: column; color: var(--heading-color); background: var(--light); }
    .cart-row { display: grid; grid-template-columns: 1fr auto auto auto; gap: .75rem; align-items: center; padding: .65rem 0; border-bottom: 1px solid var(--border-color); }
    .qty-control { background: var(--light); border-radius: 999px; display: inline-flex; align-items: center; gap: .45rem; padding: .15rem .4rem; }
    .qty-control button { border: 1px solid var(--border-color); background: var(--white); width: 24px; height: 24px; border-radius: 50%; font-weight: 800; }
    .totals-box { background: var(--light); color: var(--heading-color); border: 1px solid var(--border-color); border-radius: 18px; padding: 1rem; }
    .totals-box input { background: var(--white); border: 1px solid var(--border-color); text-align: right; font-weight: 700; }
    .pay-tab { border: 1px solid var(--border-color); background: var(--white); color: var(--heading-color); padding: .6rem 1rem; text-align: center; flex: 1; font-weight: 700; }
    .pay-tab:first-child { border-radius: 999px 0 0 999px; }
    .pay-tab:last-child { border-radius: 0 999px 999px 0; }
    .pay-tab.active { background: var(--primary); border-color: var(--primary); color: #fff; }
    .save-bar { background: var(--primary); color: #fff; border: 0; border-radius: 0 0 18px 18px; width: calc(100% + 36px); margin: 16px -18px -18px; padding: 1rem; font-weight: 800; }
    .pos-search { max-width: 320px; }
    .pos-theme-badge { background: var(--primary) !important; color: #fff !important; }
    .pos-theme-text { color: var(--primary) !important; }
    .print-receipt { display: none; }
    @media print {
        body * { visibility: hidden !important; }
        #printReceipt, #printReceipt * { visibility: visible !important; }
        #printReceipt { display: block !important; position: absolute; inset: 0 auto auto 0; width: 80mm; padding: 12px; color: #000; background: #fff; font-size: 12px; }
        #printReceipt table { width: 100%; border-collapse: collapse; }
        #printReceipt th, #printReceipt td { border-bottom: 1px dashed #999; padding: 5px 0; }
        #printReceipt .text-end { text-align: right; }
        #printReceipt .receipt-title { text-align: center; font-weight: 800; font-size: 16px; margin-bottom: 8px; }
        #printReceipt .receipt-meta { display: flex; justify-content: space-between; gap: 8px; margin-bottom: 4px; }
    }
</style>

<div class="pos-shell">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-3">
        <h2 class="fw-bold mb-0">Pharmacy Point Of Sale (POS)</h2>
        <div class="input-group pos-search bg-white rounded-pill overflow-hidden border">
            <span class="input-group-text bg-white border-0"><i class="ti ti-search"></i></span>
            <input class="form-control border-0" id="medicineSearch" placeholder="Search">
        </div>
    </div>

    <form method="post" id="posForm">
        <input type="hidden" name="csrf_token" value="<?php echo clinic_h(clinic_csrf_token()); ?>">
        <input type="hidden" name="action" value="record_cart_sale">

        <div class="row g-3">
            <div class="col-xl-8">
                <div class="pos-left">
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                        <strong class="me-2">NEXT ORDERS</strong>
                        <?php foreach (array_slice($prescriptionPatients, 0, 6) as $order): ?>
                            <?php $medicineNames = array_values($order['Medicine_Names'] ?? []); ?>
                            <a class="pos-order-pill text-decoration-none text-body" href="pharmacy_sales.php?patient_id=<?php echo (int) ($order['Patient_ID'] ?? 0); ?>">
                                <span class="pos-customer-avatar" style="width:24px;height:24px;font-size:.7rem"><?php echo clinic_h(substr((string) ($order['Patient_Name'] ?? 'P'), 0, 1)); ?></span>
                                <?php echo clinic_h($order['Patient_Name'] ?? 'Patient'); ?>
                                <span class="badge text-bg-info"><?php echo count($medicineNames); ?> medicine(s)</span>
                                <?php if ($medicineNames !== []): ?>
                                <span class="small text-muted"><?php echo clinic_h(implode(', ', array_slice($medicineNames, 0, 2))); ?><?php echo count($medicineNames) > 2 ? '...' : ''; ?></span>
                                <?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                        <?php if ($prescriptionPatients === []): ?>
                            <span class="text-muted small">No prescriptions waiting.</span>
                        <?php endif; ?>
                    </div>

                    <div class="pos-customer mb-3">
                        <span class="pos-customer-avatar"><?php echo clinic_h(substr((string) ($selectedPatient['Full_Name'] ?? 'P'), 0, 1)); ?></span>
                        <div class="flex-grow-1">
                            <select class="form-select form-select-sm bg-white text-dark border-0" name="Patient_ID" id="patientSelect">
                                <?php clinic_select_options($patients, 'Patient_ID', 'Full_Name', $selectedPatientId); ?>
                            </select>
                            <div class="small text-white opacity-75 mt-1">
                                <?php echo clinic_h($selectedPatient['Patient_Type'] ?? 'Customer'); ?>
                                <?php if (!empty($selectedPatient['Phone_Number'])): ?> / <?php echo clinic_h($selectedPatient['Phone_Number']); ?><?php endif; ?>
                            </div>
                        </div>
                        <?php if ($selectedPrescriptionMedicineIds !== []): ?>
                            <span class="badge text-bg-light"><?php echo count($selectedPrescriptionMedicineIds); ?> prescription medicine(s) added</span>
                        <?php else: ?>
                            <span class="badge text-bg-light">POS customer</span>
                        <?php endif; ?>
                    </div>

                    <div class="row g-3" id="medicineGrid">
                        <?php foreach ($medicines as $medicine): ?>
                        <?php $stock = (int) ($medicine['Stock_Quantity'] ?? 0); ?>
                        <div class="col-xl-3 col-md-4 col-sm-6 medicine-item" data-name="<?php echo clinic_h(strtolower((string) $medicine['Medicine_Name'])); ?>">
                            <div class="medicine-card" data-id="<?php echo (int) $medicine['Medicine_ID']; ?>" data-name="<?php echo clinic_h($medicine['Medicine_Name']); ?>" data-price="<?php echo clinic_h($medicine['Price']); ?>" data-stock="<?php echo $stock; ?>">
                                <div class="medicine-img"><i class="ti ti-pill"></i></div>
                                <div class="fw-bold text-truncate"><?php echo clinic_h($medicine['Medicine_Name']); ?></div>
                                <div class="small text-muted text-truncate"><?php echo clinic_h($medicine['Expiry_Date'] ? 'Exp: ' . $medicine['Expiry_Date'] : 'No expiry'); ?></div>
                                <div class="d-flex justify-content-between align-items-center mt-2">
                                    <strong><?php echo clinic_money($medicine['Price']); ?></strong>
                                    <span class="badge text-bg-<?php echo $stock <= 0 ? 'danger' : ($stock <= 100 ? 'warning' : 'success'); ?>"><?php echo $stock; ?></span>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <div class="col-xl-3 col-md-4 col-sm-6">
                            <a href="medicines.php" class="add-medicine-card text-decoration-none">
                                <i class="ti ti-plus fs-28 mb-2"></i>
                                <strong>Add Medicine</strong>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-4">
                <div class="pos-cart">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <strong>MEDICINE <span class="badge pos-theme-badge" id="cartCount">0</span></strong>
                        <div class="d-flex gap-2">
                            <button class="btn btn-sm btn-light border" type="button" id="printCart"><i class="ti ti-printer me-1"></i>Print</button>
                            <button class="btn btn-sm btn-light border" type="button" id="clearCart"><i class="ti ti-trash"></i></button>
                        </div>
                    </div>

                    <div id="cartRows" class="mb-4">
                        <div class="text-center text-muted py-5" id="emptyCart">Click medicine cards to add drugs.</div>
                    </div>

                    <div class="totals-box">
                        <div class="d-flex justify-content-between mb-2"><span>Sub Total</span><strong id="subTotalText">$0.00</strong></div>
                        <div class="row g-2 align-items-center mb-2">
                            <div class="col-5">Discount</div>
                            <div class="col-3"><input class="form-control form-control-sm" id="discountPercent" type="number" min="0" max="100" value="0" readonly title="Discount setup can be enabled later."> </div>
                            <div class="col-4"><input class="form-control form-control-sm" id="discountAmount" readonly value="0.00"></div>
                        </div>
                        <div class="d-flex justify-content-between pos-theme-text mb-2"><span>Receivable</span><strong id="receivableText">$0.00</strong></div>
                        <div class="row g-2 align-items-center mb-2">
                            <div class="col-5">Received</div>
                            <div class="col-7"><input class="form-control form-control-sm" name="Received" id="receivedInput" type="number" step="0.01" value="0.00"></div>
                        </div>
                        <div class="d-flex justify-content-between text-danger"><span>Total Due</span><strong id="dueText">$0.00</strong></div>
                    </div>

                    <div class="d-flex my-3" id="paymentTabs">
                        <button type="button" class="pay-tab active" data-method="Cash">CASH</button>
                        <button type="button" class="pay-tab" data-method="Bank">CARD</button>
                        <button type="button" class="pay-tab" data-method="EVC Plus">CODE</button>
                    </div>
                    <input type="hidden" name="Payment_Method" id="paymentMethod" value="Cash">

                    <label class="form-label small text-muted">Deposit To</label>
                    <select class="form-select rounded-pill mb-3" name="Account_ID" id="accountSelect" required>
                        <?php clinic_select_options($accounts, 'Account_ID', 'Account_Name'); ?>
                    </select>

                    <div id="cartInputs"></div>
                    <button class="save-bar" type="submit">SAVE <i class="ti ti-chevron-up float-end fs-22"></i></button>
                </div>
            </div>
        </div>
    </form>
</div>

<div id="printReceipt" class="print-receipt">
    <div class="receipt-title">Clinic Pharmacy Receipt</div>
    <div class="receipt-meta"><span>Date</span><strong id="printDate"></strong></div>
    <div class="receipt-meta"><span>Patient</span><strong id="printPatient"></strong></div>
    <div class="receipt-meta"><span>Payment</span><strong id="printPayment"></strong></div>
    <div class="receipt-meta"><span>Account</span><strong id="printAccount"></strong></div>
    <hr>
    <table>
        <thead>
            <tr><th>Medicine</th><th class="text-end">Qty</th><th class="text-end">Total</th></tr>
        </thead>
        <tbody id="printItems"></tbody>
    </table>
    <hr>
    <div class="receipt-meta"><span>Subtotal</span><strong id="printSubtotal"></strong></div>
    <div class="receipt-meta"><span>Discount</span><strong id="printDiscount"></strong></div>
    <div class="receipt-meta"><span>Received</span><strong id="printReceived"></strong></div>
    <div class="receipt-meta"><span>Due</span><strong id="printDue"></strong></div>
    <div style="text-align:center;margin-top:12px;">Thank you</div>
</div>

<script>
var cart = {};
var receivedEdited = false;

function money(value) {
    return '$' + Number(value || 0).toFixed(2);
}

function selectedText(id) {
    var select = document.getElementById(id);
    return select && select.selectedIndex >= 0 ? select.options[select.selectedIndex].text : '';
}

function renderCart() {
    var rows = document.getElementById('cartRows');
    var inputs = document.getElementById('cartInputs');
    var keys = Object.keys(cart);
    rows.innerHTML = '';
    inputs.innerHTML = '';
    document.getElementById('cartCount').textContent = keys.length;

    if (keys.length === 0) {
        rows.innerHTML = '<div class="text-center text-muted py-5" id="emptyCart">Click medicine cards to add drugs.</div>';
    }

    keys.forEach(function (id) {
        var item = cart[id];
        rows.insertAdjacentHTML('beforeend',
            '<div class="cart-row">' +
                '<div><strong>' + item.name + '</strong><div class="small text-muted">' + money(item.price) + '</div></div>' +
                '<div class="qty-control"><button type="button" data-step="-1" data-id="' + id + '">-</button><span>' + item.qty + '</span><button type="button" data-step="1" data-id="' + id + '">+</button></div>' +
                '<strong>' + money(item.price * item.qty) + '</strong>' +
                '<button class="btn btn-sm btn-link text-muted p-0" type="button" data-remove="' + id + '"><i class="ti ti-x"></i></button>' +
            '</div>'
        );
        inputs.insertAdjacentHTML('beforeend',
            '<input type="hidden" name="Medicine_ID[]" value="' + id + '">' +
            '<input type="hidden" name="Quantity[]" value="' + item.qty + '">'
        );
    });

    calculateTotals();
}

function calculateTotals() {
    var subtotal = Object.keys(cart).reduce(function (sum, id) {
        return sum + (cart[id].price * cart[id].qty);
    }, 0);
    var discountPercent = parseFloat(document.getElementById('discountPercent').value || '0');
    var discount = subtotal * (discountPercent / 100);
    var receivable = Math.max(subtotal - discount, 0);
    if (!receivedEdited) {
        document.getElementById('receivedInput').value = receivable.toFixed(2);
    }
    var received = parseFloat(document.getElementById('receivedInput').value || '0');
    var due = Math.max(receivable - received, 0);

    document.getElementById('subTotalText').textContent = money(subtotal);
    document.getElementById('discountAmount').value = discount.toFixed(2);
    document.getElementById('receivableText').textContent = money(receivable);
    document.getElementById('dueText').textContent = money(due);
}

document.querySelectorAll('.medicine-card').forEach(function (card) {
    card.addEventListener('click', function () {
        var id = card.getAttribute('data-id');
        var stock = parseInt(card.getAttribute('data-stock') || '0', 10);
        if (stock <= 0) {
            alert('This medicine is out of stock.');
            return;
        }
        if (!cart[id]) {
            cart[id] = {
                name: card.getAttribute('data-name'),
                price: parseFloat(card.getAttribute('data-price') || '0'),
                qty: 1,
                stock: stock
            };
            card.classList.add('medicine-selected');
        } else if (cart[id].qty < stock) {
            cart[id].qty++;
        }
        renderCart();
    });
});

document.getElementById('cartRows').addEventListener('click', function (event) {
    var stepBtn = event.target.closest('[data-step]');
    var removeBtn = event.target.closest('[data-remove]');
    if (stepBtn) {
        var id = stepBtn.getAttribute('data-id');
        var step = parseInt(stepBtn.getAttribute('data-step'), 10);
        cart[id].qty = Math.max(1, Math.min(cart[id].stock, cart[id].qty + step));
        renderCart();
    }
    if (removeBtn) {
        var removeId = removeBtn.getAttribute('data-remove');
        delete cart[removeId];
        var card = document.querySelector('.medicine-card[data-id="' + removeId + '"]');
        if (card) card.classList.remove('medicine-selected');
        renderCart();
    }
});

document.getElementById('clearCart').addEventListener('click', function () {
    cart = {};
    receivedEdited = false;
    document.querySelectorAll('.medicine-selected').forEach(function (card) { card.classList.remove('medicine-selected'); });
    renderCart();
});

document.getElementById('printCart').addEventListener('click', function () {
    if (Object.keys(cart).length === 0) {
        alert('Add at least one medicine before printing.');
        return;
    }

    var printItems = document.getElementById('printItems');
    printItems.innerHTML = '';
    Object.keys(cart).forEach(function (id) {
        var item = cart[id];
        var row = document.createElement('tr');
        var medicine = document.createElement('td');
        var qty = document.createElement('td');
        var total = document.createElement('td');
        medicine.textContent = item.name;
        qty.textContent = item.qty;
        qty.className = 'text-end';
        total.textContent = money(item.price * item.qty);
        total.className = 'text-end';
        row.appendChild(medicine);
        row.appendChild(qty);
        row.appendChild(total);
        printItems.appendChild(row);
    });

    document.getElementById('printDate').textContent = new Date().toLocaleString();
    document.getElementById('printPatient').textContent = selectedText('patientSelect');
    document.getElementById('printPayment').textContent = document.getElementById('paymentMethod').value;
    document.getElementById('printAccount').textContent = selectedText('accountSelect');
    document.getElementById('printSubtotal').textContent = document.getElementById('subTotalText').textContent;
    document.getElementById('printDiscount').textContent = document.getElementById('discountAmount').value;
    document.getElementById('printReceived').textContent = money(document.getElementById('receivedInput').value || 0);
    document.getElementById('printDue').textContent = document.getElementById('dueText').textContent;
    window.print();
});

document.getElementById('discountPercent').addEventListener('input', calculateTotals);
document.getElementById('receivedInput').addEventListener('input', function () {
    receivedEdited = true;
    calculateTotals();
});

document.querySelectorAll('.pay-tab').forEach(function (tab) {
    tab.addEventListener('click', function () {
        document.querySelectorAll('.pay-tab').forEach(function (el) { el.classList.remove('active'); });
        tab.classList.add('active');
        document.getElementById('paymentMethod').value = tab.getAttribute('data-method');
    });
});

document.getElementById('medicineSearch').addEventListener('input', function () {
    var value = this.value.toLowerCase();
    document.querySelectorAll('.medicine-item').forEach(function (item) {
        item.style.display = item.getAttribute('data-name').indexOf(value) === -1 ? 'none' : '';
    });
});

var selectedPrescriptionMedicineIds = <?php echo json_encode(array_values($selectedPrescriptionMedicineIds), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
selectedPrescriptionMedicineIds.forEach(function (medicineId, index) {
    var prescribedCard = document.querySelector('.medicine-card[data-id="' + medicineId + '"]');
    if (prescribedCard) {
        prescribedCard.click();
        if (index === 0) {
            prescribedCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }
});

document.getElementById('posForm').addEventListener('submit', function (event) {
    if (Object.keys(cart).length === 0) {
        event.preventDefault();
        alert('Add at least one medicine before saving.');
    }
});
</script>
<?php clinic_page_end(); ?>
