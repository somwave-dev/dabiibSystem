<?php
declare(strict_types=1);
/**
 * Demo/operational seed for the Dabiib HMS dashboard.
 * Populates realistic data dated to the last 7 days so every dashboard row
 * is filled. All values come from the DB — nothing is hardcoded in HTML.
 * Idempotent: each module only seeds when it is (nearly) empty.
 */
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$db = new mysqli('127.0.0.1', 'root', '', 'dabiibsystem');
$db->set_charset('utf8mb4');

function q(string $table): int { global $db; return (int) $db->query("SELECT COUNT(*) AS c FROM " . $table)->fetch_assoc()['c']; }
function esc(string $s): string { return str_replace(["'", '\\'], ["''", '\\\\'], $s); }

$out = [];

// ============ PATIENTS ============
$recentPatients = q("patients WHERE Created_At >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
$pIds = [];
if ($recentPatients < 5) {
    $names = [
        ['Abdirahman Warsame', 'Male', 'Adult', 'Credit'],
        ['Khadija Isse', 'Female', 'Adult', 'Walk-in'],
        ['Mohamed Abukar', 'Male', 'Child', 'Walk-in'],
        ['Asha Abdullahi', 'Female', 'Adult', 'Credit'],
        ['Hassan Moallim', 'Male', 'Adult', 'Walk-in'],
        ['Fadumo Osman', 'Female', 'Child', 'Walk-in'],
        ['Ali Shire', 'Male', 'Adult', 'Credit'],
        ['Sahra Warsame', 'Female', 'Adult', 'Walk-in'],
        ['Omar Jibril', 'Male', 'Child', 'Walk-in'],
        ['Maryan Dalmar', 'Female', 'Adult', 'Credit'],
        ['Jama Kulmiye', 'Male', 'Adult', 'Walk-in'],
        ['Amina Farah', 'Female', 'Adult', 'Credit'],
    ];
    foreach ($names as $i => $n) {
        $phone = '06155' . str_pad((string) (700 + $i), 4, '0', STR_PAD_LEFT);
        $bal = ($n[3] === 'Credit') ? (5 + $i * 3) . '.00' : '0.00';
        $daysAgo = 6 - ($i % 7);
        $hours = ($i * 3) % 10;
        $sql = "INSERT INTO patients (Full_Name, Phone_Number, Sex, Age_Group, Patient_Type, Current_Balance, Created_At, deleted)
                VALUES ('" . esc($n[0]) . "', '$phone', '{$n[1]}', '{$n[2]}', '{$n[3]}', $bal, NOW() - INTERVAL $daysAgo DAY - INTERVAL $hours HOUR, 0)";
        $db->query($sql);
        $pIds[] = (int) $db->insert_id;
    }
    $out[] = 'patients: +' . count($names);
} else {
    $r = $db->query("SELECT Patient_ID FROM patients WHERE Created_At >= DATE_SUB(NOW(), INTERVAL 7 DAY) ORDER BY Patient_ID DESC LIMIT 12");
    while ($x = $r->fetch_assoc()) $pIds[] = (int) $x['Patient_ID'];
}
if ($pIds === []) {
    $r = $db->query('SELECT Patient_ID FROM patients ORDER BY Patient_ID DESC LIMIT 12');
    while ($x = $r->fetch_assoc()) $pIds[] = (int) $x['Patient_ID'];
}

// ============ VISITS ============
$vIds = [];
$recentVisits = q("visits WHERE Visit_Date >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
if ($recentVisits < 5) {
    for ($i = 0; $i < 10; $i++) {
        $pid = $pIds[$i % count($pIds)];
        $doc = $i % 4 === 0 ? 'NULL' : (string) (($i % 4) + 1);
        $daysAgo = $i < 6 ? 0 : ($i - 5);
        $hours = ($i * 2 + 8) % 12;
        $notes = 'Visit ' . ($i + 1) . ' — ' . ['fever and cough', 'follow-up review', 'stomach pain', 'routine check-up', 'headache and fatigue', 'blood pressure check', 'diabetes follow-up', 'malaria symptoms', 'wound dressing', 'antenatal care'][$i];
        $sql = "INSERT INTO visits (Patient_ID, Doctor_ID, Visit_Date, Notes, Created_By)
                VALUES ($pid, $doc, NOW() - INTERVAL $daysAgo DAY - INTERVAL $hours HOUR, '" . esc($notes) . "', 1)";
        $db->query($sql);
        $vIds[] = (int) $db->insert_id;
    }
    $out[] = 'visits: +10';
} else {
    $r = $db->query("SELECT Visit_ID FROM visits WHERE Visit_Date >= DATE_SUB(NOW(), INTERVAL 7 DAY) ORDER BY Visit_ID DESC LIMIT 10");
    while ($x = $r->fetch_assoc()) $vIds[] = (int) $x['Visit_ID'];
}
if ($vIds === []) {
    $r = $db->query('SELECT Visit_ID FROM visits ORDER BY Visit_ID DESC LIMIT 10');
    while ($x = $r->fetch_assoc()) $vIds[] = (int) $x['Visit_ID'];
}
echo 'PART1 pIds=' . count($pIds) . ' vIds=' . count($vIds) . "\n";
/*__SEED_B__*/

// ============ APPOINTMENTS ============
$recentAppts = q("appointments WHERE DATE(Appointment_Date) = CURDATE()");
if ($recentAppts < 3) {
    $statuses = ['Pending', 'Completed', 'Cancelled'];
    for ($i = 0; $i < 8; $i++) {
        $pid = $pIds[$i % count($pIds)];
        $doc = ($i % 4) + 1;
        $hour = 8 + $i;
        $sql = "INSERT INTO appointments (Patient_ID, Doctor_ID, Appointment_Date, Status)
                VALUES ($pid, $doc, CURDATE() + INTERVAL $hour HOUR, '{$statuses[$i % 3]}')";
        $db->query($sql);
    }
    for ($i = 0; $i < 6; $i++) {
        $pid = $pIds[($i + 3) % count($pIds)];
        $doc = ($i % 3) + 1;
        $daysAgo = ($i % 6) + 1;
        $sql = "INSERT INTO appointments (Patient_ID, Doctor_ID, Appointment_Date, Status)
                VALUES ($pid, $doc, CURDATE() - INTERVAL $daysAgo DAY + INTERVAL " . (8 + $i % 8) . " HOUR, 'Completed')";
        $db->query($sql);
    }
    $out[] = 'appointments: +14';
}

// ============ PAYMENTS ============
$recentPayments = q("payments WHERE Payment_Date >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
if ($recentPayments < 5) {
    $methods = ['EVC Plus', 'Cash', 'eDahab', 'Bank'];
    $amounts = [50, 25, 15, 80, 35, 10, 60, 20, 45, 30, 12, 70];
    for ($i = 0; $i < 12; $i++) {
        $pid = $pIds[$i % count($pIds)];
        $acc = ($i % 5) + 1;
        $daysAgo = $i % 7;
        $mins = ($i * 13) % 60;
        $sql = "INSERT INTO payments (Patient_ID, Account_ID, Amount, Payment_Method, Transaction_Ref, Payment_Date, User_ID)
                VALUES ($pid, $acc, {$amounts[$i]}.00, '{$methods[$i % 4]}', NULL,
                        NOW() - INTERVAL $daysAgo DAY - INTERVAL $mins MINUTE, 1)";
        $db->query($sql);
    }
    $out[] = 'payments: +12';
}

// ============ PHARMACY SALES ============
$recentPharm = q("pharmacy_sales WHERE Sale_Date >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
if ($recentPharm < 5) {
    $meds = [1, 2, 3, 4, 5, 6];
    $qtys = [5, 10, 4, 8, 3, 6, 7, 12, 2, 9, 6, 11];
    for ($i = 0; $i < 12; $i++) {
        $med = $meds[$i % 6];
        $qty = $qtys[$i];
        $price = (float) $db->query("SELECT Price FROM medicines WHERE Medicine_ID = $med")->fetch_assoc()['Price'];
        $total = round($price * $qty, 2);
        $pid = $pIds[$i % count($pIds)];
        $daysAgo = $i % 7;
        $mins = ($i * 17) % 60;
        $sql = "INSERT INTO pharmacy_sales (Patient_ID, Medicine_ID, Quantity, Total_Price, Sale_Date, User_ID)
                VALUES ($pid, $med, $qty, $total, NOW() - INTERVAL $daysAgo DAY - INTERVAL $mins MINUTE, 1)";
        $db->query($sql);
    }
    $out[] = 'pharmacy_sales: +12';
}
/*__SEED_C__*/

// ============ LAB RESULTS ============
$recentLab = q("lab_results lr JOIN visits v ON v.Visit_ID = lr.Visit_ID WHERE v.Visit_Date >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
if ($recentLab < 10) {
    $tests = [1, 2, 3, 4, 5, 6];
    $details = ['Result: Negative', 'Result: Positive', 'WBC waa caadi', 'Result: Negative', 'Heerka sokorta waa sareeyaa', 'Result: Positive'];
    for ($i = 0; $i < 10; $i++) {
        $vid = $vIds[$i % count($vIds)];
        $tid = $tests[$i % 6];
        $completed = $i < 6;
        $det = $completed ? "'" . esc($details[$i % 6]) . "'" : 'NULL';
        $sql = "INSERT INTO lab_results (Visit_ID, Test_ID, Result_Details, Status, Created_By)
                VALUES ($vid, $tid, $det, '" . ($completed ? 'Completed' : 'Pending') . "', 1)";
        $db->query($sql);
    }
    $out[] = 'lab_results: +10';
}

// ============ PRESCRIPTIONS ============
$recentRx = q("prescriptions pr JOIN visits v ON v.Visit_ID = pr.Visit_ID WHERE v.Visit_Date >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
if ($recentRx < 10) {
    $meds = [1, 2, 3, 4, 5, 6];
    for ($i = 0; $i < 8; $i++) {
        $vid = $vIds[$i % count($vIds)];
        $sql = "INSERT INTO prescriptions (Visit_ID, Medicine_ID, Dosage, Created_By)
                VALUES ($vid, {$meds[$i % 6]}, '1 x 3 daily for 5 days', 1)";
        $db->query($sql);
    }
    $out[] = 'prescriptions: +8';
}

// ============ NURSING RECORDS ============
$recentNursing = q("nursing_records WHERE Record_Date >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
if ($recentNursing < 3) {
    for ($i = 0; $i < 8; $i++) {
        $vid = $vIds[$i % count($vIds)];
        $svc = ($i % 5) + 1;
        $daysAgo = $i % 7;
        $sql = "INSERT INTO nursing_records (Visit_ID, Service_ID, Medicine_Used, Administered_By, Record_Date)
                VALUES ($vid, $svc, NULL, 6, NOW() - INTERVAL $daysAgo DAY - INTERVAL " . (($i * 5) % 60) . " MINUTE)";
        $db->query($sql);
    }
    $out[] = 'nursing_records: +8';
}

// ============ AUDIT LOGS ============
$auditCount = q('audit_logs');
if ($auditCount < 4) {
    $events = [
        ['Login', 'User logged in successfully', 'user', 1],
        ['Staff updated', 'Staff record saved: Admin Ayaan (#1)', 'staff', 1],
        ['User created', 'Created user account: hodan_rec', 'user', 4],
        ['Password reset requested', 'Reset code sent for user dr_xasan', 'user', 2],
        ['Login', 'User logged in successfully', 'user', 6],
    ];
    foreach ($events as $i => $e) {
        $sql = "INSERT INTO audit_logs (user_id, username, action, entity, entity_id, details, ip_address, created_at)
                VALUES ({$e[3]}, 'maamule', '" . esc($e[0]) . "', '" . esc($e[2]) . "', {$e[3]}, '" . esc($e[1]) . "', '127.0.0.1', NOW() - INTERVAL " . ($i * 3) . " HOUR)";
        $db->query($sql);
    }
    $out[] = 'audit_logs: +5';
}

// ============ NOTIFICATIONS ============
$notifCount = q('notifications');
if ($notifCount < 3) {
    $rows = [
        ['info', 'Welcome to Dabiib HMS', 'The executive dashboard is live with real-time data.', 'pages/dashboard.php', 0],
        ['warning', 'Medicines low on stock', 'Some medicines are below their reorder level — review the pharmacy.', 'pages/medicines.php', 1],
        ['success', 'New visit completed', 'A follow-up visit was completed for a patient.', 'pages/visits.php', 0],
    ];
    foreach ($rows as $i => $r) {
        $uid = $r[4] > 0 ? (string) $r[4] : 'NULL';
        $sql = "INSERT INTO notifications (user_id, type, title, message, link, is_read, created_at)
                VALUES ($uid, '{$r[0]}', '" . esc($r[1]) . "', '" . esc($r[2]) . "', '" . esc($r[3]) . "', 0, NOW() - INTERVAL " . ($i * 2) . " HOUR)";
        $db->query($sql);
    }
    $out[] = 'notifications: +3';
}

echo implode("\n", $out) . "\nDONE\n";


