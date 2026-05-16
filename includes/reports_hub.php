<?php
declare(strict_types=1);

/**
 * Aggregations and date helpers for standalone report pages (`pages/report_*.php`).
 */

/** @var list<string> */
const CLINIC_REPORT_VIEWS = [
    'debt',
    'doctor_commissions',
    'revenue_category',
    'cash_flow',
    'demographics',
    'appointment_attendance',
    'diseases_lab',
    'expiring_medicines',
    'top_medicines',
    'low_stock',
    'user_activity',
    'sms_delivery',
];

function clinic_reports_normalize_view(string $raw): string
{
    $raw = trim($raw);
    $aliases = [
        '' => 'debt',
        'finance' => 'revenue_category',
        'lab' => 'diseases_lab',
        'pharmacy' => 'top_medicines',
    ];

    return $aliases[$raw] ?? (in_array($raw, CLINIC_REPORT_VIEWS, true) ? $raw : 'debt');
}

/**
 * @return array{0:?int,1:?int}
 */
function clinic_reports_bounds(): array
{
    $df = trim((string) ($_GET['date_from'] ?? ''));
    $dt = trim((string) ($_GET['date_to'] ?? ''));
    $from = $df !== '' ? strtotime($df . ' 00:00:00') : null;
    $to = $dt !== '' ? strtotime($dt . ' 23:59:59') : null;
    if ($from !== false && $to !== false && $from !== null && $to !== null && $from > $to) {
        return [$to, $from];
    }

    return [$from === false ? null : $from, $to === false ? null : $to];
}

function clinic_reports_datetime_in_bounds(string $datetime, ?int $from, ?int $to): bool
{
    if ($from === null && $to === null) {
        return true;
    }
    $t = strtotime($datetime);
    if ($t === false) {
        return false;
    }
    if ($from !== null && $t < $from) {
        return false;
    }
    if ($to !== null && $t > $to) {
        return false;
    }

    return true;
}

/**
 * Consultation attribution: visits × doctor consultation fee when doctor is assigned.
 *
 * @param list<array<string,mixed>> $visits
 * @param list<array<string,mixed>> $doctors
 *
 * @return list<array{Doctor_ID:int,Doctor_Name:string,Specialization:string,Consultation_Fee:float,visit_count:int,revenue_estimate:float}>
 */
function clinic_report_doctor_commission_rows(array $visits, array $doctors, ?int $from, ?int $to): array
{
    $feeByDoctor = [];
    foreach ($doctors as $doc) {
        $id = (int) ($doc['Doctor_ID'] ?? 0);
        if ($id < 1) {
            continue;
        }
        $feeByDoctor[$id] = [
            'Doctor_ID' => $id,
            'Doctor_Name' => (string) ($doc['Full_Name'] ?? ''),
            'Specialization' => (string) ($doc['Specialization'] ?? ''),
            'Consultation_Fee' => (float) ($doc['Consultation_Fee'] ?? 0),
            'visit_count' => 0,
        ];
    }

    foreach ($visits as $v) {
        $docId = (int) ($v['Doctor_ID'] ?? 0);
        if ($docId < 1 || !isset($feeByDoctor[$docId])) {
            continue;
        }
        if (!clinic_reports_datetime_in_bounds((string) ($v['Visit_Date'] ?? ''), $from, $to)) {
            continue;
        }
        $feeByDoctor[$docId]['visit_count']++;
    }

    $out = [];
    foreach ($feeByDoctor as $row) {
        $row['revenue_estimate'] = $row['visit_count'] * $row['Consultation_Fee'];
        if ($row['visit_count'] > 0 || $row['Doctor_Name'] !== '') {
            $out[] = $row;
        }
    }

    usort($out, static fn ($a, $b) => $b['revenue_estimate'] <=> $a['revenue_estimate']);

    return $out;
}

/**
 * @param list<array<string,mixed>> $services
 *
 * @return array<int,float> Service_ID => Price
 */
function clinic_report_service_prices(array $services): array
{
    $m = [];
    foreach ($services as $s) {
        $id = (int) ($s['Service_ID'] ?? 0);
        if ($id > 0) {
            $m[$id] = (float) ($s['Price'] ?? 0);
        }
    }

    return $m;
}

/**
 * @param list<array<string,mixed>> $labTests rows with Test_ID, Price
 *
 * @return array<int,float>
 */
function clinic_report_lab_prices(array $labTests): array
{
    $m = [];
    foreach ($labTests as $t) {
        $id = (int) ($t['Test_ID'] ?? 0);
        if ($id > 0) {
            $m[$id] = (float) ($t['Price'] ?? 0);
        }
    }

    return $m;
}

/**
 * Revenue indicators (avoid double-booking POS with patient balances; each line is operational proxy).
 *
 * @return array{rows:list<array{category:string,amount:float,hint:string}>,payment_methods:list<array{method:string,count:int,amount:float}>}
 */
function clinic_report_revenue_category(
    array $pharmacySales,
    array $payments,
    array $labResults,
    array $labPrices,
    array $nursingRecords,
    array $servicePrices,
    array $visits,
    array $doctors,
    ?int $from,
    ?int $to
): array {
    $consultationFee = [];
    foreach ($doctors as $d) {
        $consultationFee[(int) ($d['Doctor_ID'] ?? 0)] = (float) ($d['Consultation_Fee'] ?? 0);
    }

    $pharmacyTotal = 0.0;
    $paymentsTotal = 0.0;
    $paymentBuckets = [];

    foreach ($payments as $p) {
        if (!clinic_reports_datetime_in_bounds((string) ($p['Payment_Date'] ?? ''), $from, $to)) {
            continue;
        }
        $amt = (float) ($p['Amount'] ?? 0);
        $paymentsTotal += $amt;
        $meth = (string) ($p['Payment_Method'] ?? 'Unknown');
        if (!isset($paymentBuckets[$meth])) {
            $paymentBuckets[$meth] = ['method' => $meth, 'count' => 0, 'amount' => 0.0];
        }
        $paymentBuckets[$meth]['count']++;
        $paymentBuckets[$meth]['amount'] += $amt;
    }

    foreach ($pharmacySales as $sale) {
        if (!clinic_reports_datetime_in_bounds((string) ($sale['Sale_Date'] ?? ''), $from, $to)) {
            continue;
        }
        $pharmacyTotal += (float) ($sale['Total_Price'] ?? 0);
    }

    $labTotal = 0.0;
    foreach ($labResults as $lr) {
        if (($lr['Status'] ?? '') !== 'Completed') {
            continue;
        }
        $at = (string) ($lr['Recorded_At'] ?? '');
        $when = $at !== '' ? $at : '';
        if ($when === '') {
            continue;
        }
        if (!clinic_reports_datetime_in_bounds($when, $from, $to)) {
            continue;
        }
        $tid = (int) ($lr['Test_ID'] ?? 0);
        $labTotal += $labPrices[$tid] ?? 0.0;
    }

    $nursingTotal = 0.0;
    foreach ($nursingRecords as $nr) {
        if (!clinic_reports_datetime_in_bounds((string) ($nr['Record_Date'] ?? ''), $from, $to)) {
            continue;
        }
        $sid = (int) ($nr['Service_ID'] ?? 0);
        $nursingTotal += $servicePrices[$sid] ?? 0.0;
    }

    $consultTotal = 0.0;
    foreach ($visits as $v) {
        $docId = (int) ($v['Doctor_ID'] ?? 0);
        if ($docId < 1) {
            continue;
        }
        if (!clinic_reports_datetime_in_bounds((string) ($v['Visit_Date'] ?? ''), $from, $to)) {
            continue;
        }
        $consultTotal += $consultationFee[$docId] ?? 0.0;
    }

    $rows = [
        [
            'category' => 'Consultation visits (Σ fee per visit)',
            'amount' => $consultTotal,
            'hint' => 'Attributed using each doctor consultation fee × completed visits.',
        ],
        [
            'category' => 'Pharmacy POS sales',
            'amount' => $pharmacyTotal,
            'hint' => 'Sum of pharmacy line totals.',
        ],
        [
            'category' => 'Payments desk (collections)',
            'amount' => $paymentsTotal,
            'hint' => 'Cash and mobile money logged at payments.',
        ],
        [
            'category' => 'Laboratory completed (catalogue price)',
            'amount' => $labTotal,
            'hint' => 'Uses test list price where result status is Completed and recorded date is within range.',
        ],
        [
            'category' => 'Nursing services (catalogue)',
            'amount' => $nursingTotal,
            'hint' => 'Uses service price × nursing records in range.',
        ],
    ];

    return [
        'rows' => $rows,
        'payment_methods' => array_values($paymentBuckets),
    ];
}

/**
 * @return list<array{Sent_Date:string,Type:string,Detail:string,Amount:string,Debit_Credit:string}>
 */
function clinic_report_cash_flow_ledger(array $payments, array $transfers, ?int $from, ?int $to): array
{
    $ledger = [];

    foreach ($payments as $p) {
        if (!clinic_reports_datetime_in_bounds((string) ($p['Payment_Date'] ?? ''), $from, $to)) {
            continue;
        }
        $patient = (string) ($p['Patient_Name'] ?? '');
        $account = (string) ($p['Account_Name'] ?? '');
        $ledger[] = [
            'ts' => strtotime((string) ($p['Payment_Date'] ?? '')) ?: 0,
            'date' => (string) ($p['Payment_Date'] ?? ''),
            'type' => 'Payment received',
            'detail' => trim($patient !== '' ? $patient : 'Patient') . ($account !== '' ? ' → ' . $account : ''),
            'amount' => (float) ($p['Amount'] ?? 0),
            'flow' => 'in',
        ];
    }

    foreach ($transfers as $t) {
        if (!clinic_reports_datetime_in_bounds((string) ($t['Transfer_Date'] ?? ''), $from, $to)) {
            continue;
        }
        $fromName = (string) ($t['From_Account_Name'] ?? '');
        $toName = (string) ($t['To_Account_Name'] ?? '');
        $ledger[] = [
            'ts' => strtotime((string) ($t['Transfer_Date'] ?? '')) ?: 0,
            'date' => (string) ($t['Transfer_Date'] ?? ''),
            'type' => 'Account transfer',
            'detail' => $fromName . ' → ' . $toName,
            'amount' => (float) ($t['Amount'] ?? 0),
            'flow' => 'transfer',
        ];
    }

    usort($ledger, static fn ($a, $b) => $b['ts'] <=> $a['ts']);

    $norm = [];
    foreach ($ledger as $row) {
        $norm[] = [
            'Sent_Date' => $row['date'],
            'Type' => $row['type'],
            'Detail' => $row['detail'],
            'Amount' => '$' . number_format($row['amount'], 2),
            'Debit_Credit' => $row['flow'] === 'in' ? 'Inflow' : 'Transfer',
        ];
    }

    return $norm;
}

/**
 * Patient demographics breakdown.
 *
 * @param list<array<string,mixed>> $patients
 *
 * @return array{total:int,by_sex:array<string,int>,by_age:array<string,int>,by_type:array<string,int>}
 */
function clinic_report_demographics(array $patients, ?int $from, ?int $to): array
{
    $total = 0;
    $bySex = [];
    $byAge = [];
    $byType = [];

    foreach ($patients as $p) {
        $created = trim((string) ($p['Created_At'] ?? ''));
        if ($from !== null || $to !== null) {
            if ($created === '' || !clinic_reports_datetime_in_bounds($created, $from, $to)) {
                continue;
            }
        }

        $total++;
        $s = (string) ($p['Sex'] ?? 'Unknown');
        $a = (string) ($p['Age_Group'] ?? 'Unknown');
        $ty = (string) ($p['Patient_Type'] ?? 'Unknown');
        $bySex[$s] = ($bySex[$s] ?? 0) + 1;
        $byAge[$a] = ($byAge[$a] ?? 0) + 1;
        $byType[$ty] = ($byType[$ty] ?? 0) + 1;
    }

    return [
        'total' => $total,
        'by_sex' => $bySex,
        'by_age' => $byAge,
        'by_type' => $byType,
    ];
}

/**
 * @return array{counts:array<string,int>,scheduled:int,completed:int,cancelled:int,pending:int,completion_rate:?float,recent:list<array<string,mixed>>}
 */
function clinic_report_appointment_attendance(array $appointments, ?int $from, ?int $to): array
{
    $filtered = [];
    foreach ($appointments as $a) {
        if (!clinic_reports_datetime_in_bounds((string) ($a['Appointment_Date'] ?? ''), $from, $to)) {
            continue;
        }
        $filtered[] = $a;
    }

    $counts = ['Pending' => 0, 'Completed' => 0, 'Cancelled' => 0];
    foreach ($filtered as $a) {
        $st = (string) ($a['Status'] ?? 'Pending');
        if (!isset($counts[$st])) {
            $counts[$st] = 0;
        }
        $counts[$st]++;
    }

    $completed = $counts['Completed'] ?? 0;
    $cancelled = $counts['Cancelled'] ?? 0;
    $pending = $counts['Pending'] ?? 0;
    $scheduled = count($filtered);
    $completionDen = $completed + $cancelled + $pending;

    return [
        'counts' => $counts,
        'scheduled' => $scheduled,
        'completed' => $completed,
        'cancelled' => $cancelled,
        'pending' => $pending,
        'completion_rate' => $completionDen > 0 ? round(100 * $completed / $completionDen, 1) : null,
        'recent' => $filtered,
    ];
}

/**
 * Lab volume by test (proxy for clinical demand); optional month bucket.
 *
 * @return array{by_test:list<array{Test_Name:string,count:int,share_pct:float}>,by_month:list<array{month:string,total:int}>}
 */
function clinic_report_lab_trends(array $labResults, ?int $from, ?int $to): array
{
    $counts = [];
    $monthly = [];

    foreach ($labResults as $lr) {
        if (($lr['Status'] ?? '') !== 'Completed') {
            continue;
        }
        $name = trim((string) ($lr['Test_Name'] ?? 'Unknown'));
        $at = (string) ($lr['Recorded_At'] ?? '');
        if ($at === '') {
            continue;
        }
        if (!clinic_reports_datetime_in_bounds($at, $from, $to)) {
            continue;
        }
        $counts[$name] = ($counts[$name] ?? 0) + 1;
        $ym = date('Y-m', strtotime($at) ?: time());
        if (!isset($monthly[$ym])) {
            $monthly[$ym] = 0;
        }
        $monthly[$ym]++;
    }

    arsort($counts);
    $totalLab = array_sum($counts);
    $rows = [];
    foreach ($counts as $name => $cnt) {
        $rows[] = [
            'Test_Name' => $name,
            'count' => $cnt,
            'share_pct' => $totalLab > 0 ? round(100 * $cnt / $totalLab, 1) : 0.0,
        ];
    }

    ksort($monthly);
    $months = [];
    foreach ($monthly as $m => $cnt) {
        $months[] = ['month' => $m, 'total' => $cnt];
    }

    return ['by_test' => $rows, 'by_month' => $months];
}

/**
 * @return list<array<string,mixed>>
 */
function clinic_report_expiring_medicines(array $medicines, int $daysAhead): array
{
    $cutoff = strtotime('+' . $daysAhead . ' days');
    if ($cutoff === false) {
        return [];
    }

    $out = [];
    foreach ($medicines as $m) {
        $exp = (string) ($m['Expiry_Date'] ?? '');
        if ($exp === '') {
            continue;
        }
        $expTs = strtotime($exp . ' 23:59:59');
        if ($expTs === false) {
            continue;
        }
        if ($expTs <= $cutoff) {
            $out[] = $m + ['Days_Until_Expiry' => (int) floor(($expTs - time()) / 86400)];
        }
    }

    usort($out, static fn ($a, $b) => ((int) strtotime((string) ($a['Expiry_Date'] ?? '') ?: 'now')) <=> ((int) strtotime((string) ($b['Expiry_Date'] ?? '') ?: 'now')));

    return $out;
}

/**
 * @return list<array{Medicine_Name:string,qty:int,revenue:float}>
 */
function clinic_report_top_medicines(array $pharmacySales, array $medicineNames, ?int $from, ?int $to, int $limit = 50): array
{
    $nameById = [];
    foreach ($medicineNames as $m) {
        $nameById[(int) ($m['Medicine_ID'] ?? 0)] = (string) ($m['Medicine_Name'] ?? '');
    }

    $agg = [];
    foreach ($pharmacySales as $s) {
        if (!clinic_reports_datetime_in_bounds((string) ($s['Sale_Date'] ?? ''), $from, $to)) {
            continue;
        }
        $mid = (int) ($s['Medicine_ID'] ?? 0);
        if (!isset($agg[$mid])) {
            $agg[$mid] = ['qty' => 0, 'revenue' => 0.0];
        }
        $agg[$mid]['qty'] += (int) ($s['Quantity'] ?? 0);
        $agg[$mid]['revenue'] += (float) ($s['Total_Price'] ?? 0);
    }

    $rows = [];
    foreach ($agg as $mid => $vals) {
        $rows[] = [
            'Medicine_Name' => $nameById[$mid] ?? ('#' . $mid),
            'qty' => $vals['qty'],
            'revenue' => $vals['revenue'],
        ];
    }

    usort($rows, static fn ($a, $b) => $b['revenue'] <=> $a['revenue']);

    return array_slice($rows, 0, max(5, min(200, $limit)));
}

/**
 * Outstanding balance summed by accountable guarantor (Guarantor_ID on debtor patient rows).
 *
 * @param list<array<string,mixed>> $patients
 *
 * @return list<array{guarantor_id:int,guarantor_name:string,dependent_count:int,total_outstanding:float}>
 */
function clinic_report_guarantor_liability(array $patients): array
{
    $groups = [];

    foreach ($patients as $p) {
        $bal = (float) ($p['Current_Balance'] ?? 0);
        if ($bal <= 0) {
            continue;
        }

        $gid = (int) ($p['Guarantor_ID'] ?? 0);
        if ($gid > 0) {
            $key = 'g:' . $gid;
            $gname = (string) ($p['Guarantor_Name'] ?? 'Guarantor #' . $gid);
        } else {
            $key = 'self';
            $gname = 'Patients without linked guarantor';
        }

        if (!isset($groups[$key])) {
            $groups[$key] = [
                'guarantor_id' => $gid,
                'guarantor_name' => $gname,
                'dependent_count' => 0,
                'total_outstanding' => 0.0,
            ];
        }

        $groups[$key]['dependent_count']++;
        $groups[$key]['total_outstanding'] += $bal;

        $gn = trim((string) ($p['Guarantor_Name'] ?? ''));
        if ($gid > 0 && $gn !== '') {
            $groups[$key]['guarantor_name'] = $gn;
        }
    }

    $out = array_values($groups);
    usort($out, static fn ($a, $b) => $b['total_outstanding'] <=> $a['total_outstanding']);

    return $out;
}

function clinic_reports_bucket_period(string $bucket, string $datetime): string
{
    $d = date_create($datetime);
    if ($d === false) {
        $d = new DateTime();
    }

    return match ($bucket) {
        'day' => $d->format('Y-m-d'),
        'week' => $d->format('o') . '-W' . $d->format('W'),
        default => $d->format('Y-m'),
    };
}

/**
 * Payments grouped by calendar period and Payment_Method.
 *
 * @param list<array<string,mixed>> $payments
 *
 * @return list<array{period:string,method:string,count:int,amount:float}>
 */
function clinic_report_payment_methods_breakdown(array $payments, ?int $from, ?int $to, string $bucket = 'month'): array
{
    if (!in_array($bucket, ['day', 'month'], true)) {
        $bucket = 'month';
    }

    $rows = [];
    foreach ($payments as $py) {
        $dt = (string) ($py['Payment_Date'] ?? '');
        if ($dt === '' || !clinic_reports_datetime_in_bounds($dt, $from, $to)) {
            continue;
        }
        $period = clinic_reports_bucket_period($bucket, $dt);
        $method = (string) ($py['Payment_Method'] ?? 'Unknown');
        $key = $period . '|' . $method;
        if (!isset($rows[$key])) {
            $rows[$key] = ['period' => $period, 'method' => $method, 'count' => 0, 'amount' => 0.0];
        }
        $rows[$key]['count']++;
        $rows[$key]['amount'] += (float) ($py['Amount'] ?? 0);
    }

    $list = array_values($rows);
    usort($list, static function ($a, $b): int {
        $p = strcmp((string) $a['period'], (string) $b['period']);
        if ($p !== 0) {
            return -$p;
        }

        return $b['amount'] <=> $a['amount'];
    });

    return $list;
}

/**
 * Doctor visit counts grouped by bucket (day/week/month).
 *
 * @param list<array<string,mixed>> $visits
 *
 * @return list<array{period:string,doctor:string,Doctor_ID:int,visit_count:int}>
 */
function clinic_report_doctor_workload_rows(array $visits, ?int $from, ?int $to, string $bucket = 'month'): array
{
    if (!in_array($bucket, ['day', 'week', 'month'], true)) {
        $bucket = 'month';
    }

    $cells = [];

    foreach ($visits as $v) {
        $dt = (string) ($v['Visit_Date'] ?? '');
        if ($dt === '' || !clinic_reports_datetime_in_bounds($dt, $from, $to)) {
            continue;
        }
        $docId = (int) ($v['Doctor_ID'] ?? 0);
        if ($docId < 1) {
            continue;
        }
        $period = clinic_reports_bucket_period($bucket, $dt);
        $doctor = trim((string) ($v['Doctor_Name'] ?? 'Doctor #' . $docId));
        $key = $period . '|' . $docId;
        if (!isset($cells[$key])) {
            $cells[$key] = [
                'period' => $period,
                'doctor' => $doctor,
                'Doctor_ID' => $docId,
                'visit_count' => 0,
            ];
        }
        $cells[$key]['visit_count']++;
    }

    $out = array_values($cells);
    usort($out, static function ($a, $b): int {
        $c = strcmp((string) $b['period'], (string) $a['period']);
        if ($c !== 0) {
            return $c;
        }

        return $b['visit_count'] <=> $a['visit_count'];
    });

    return $out;
}

/**
 * @param list<array<string,mixed>> $records nursing_records rows with Service_Name, Service_ID, Record_Date
 * @param array<int,float>         $servicePrices
 *
 * @return list<array{service_id:int,service_name:string,count:int,revenue:float}>
 */
function clinic_report_nursing_utilization(array $records, array $servicePrices, ?int $from, ?int $to): array
{
    $agg = [];

    foreach ($records as $nr) {
        $dt = (string) ($nr['Record_Date'] ?? '');
        if ($dt === '' || !clinic_reports_datetime_in_bounds($dt, $from, $to)) {
            continue;
        }
        $sid = (int) ($nr['Service_ID'] ?? 0);
        $name = trim((string) ($nr['Service_Name'] ?? 'Service #' . $sid));
        if (!isset($agg[$sid])) {
            $agg[$sid] = ['service_id' => $sid, 'service_name' => $name, 'count' => 0, 'revenue' => 0.0];
        }
        $agg[$sid]['count']++;
        $agg[$sid]['revenue'] += $servicePrices[$sid] ?? 0.0;
        if ($name !== '') {
            $agg[$sid]['service_name'] = $name;
        }
    }

    $rows = array_values($agg);
    usort($rows, static fn ($a, $b) => $b['revenue'] <=> $a['revenue']);

    return $rows;
}

/**
 * Lab completed volume × catalogue price per test.
 *
 * @param list<array<string,mixed>> $labResults
 * @param array<int,float>          $labPrices
 *
 * @return list<array{test_id:int,test_name:string,count:int,revenue:float}>
 */
function clinic_report_lab_volume_revenue(array $labResults, array $labPrices, ?int $from, ?int $to): array
{
    $counts = [];

    foreach ($labResults as $lr) {
        if (($lr['Status'] ?? '') !== 'Completed') {
            continue;
        }
        $when = trim((string) ($lr['Recorded_At'] ?? ''));
        if ($when === '' || !clinic_reports_datetime_in_bounds($when, $from, $to)) {
            continue;
        }
        $tid = (int) ($lr['Test_ID'] ?? 0);
        $tname = trim((string) ($lr['Test_Name'] ?? 'Test #' . $tid));

        if (!isset($counts[$tid])) {
            $counts[$tid] = [
                'test_id' => $tid,
                'test_name' => $tname,
                'count' => 0,
                'price' => $labPrices[$tid] ?? 0.0,
            ];
        }
        $counts[$tid]['count']++;
        if ($tname !== '') {
            $counts[$tid]['test_name'] = $tname;
        }
    }

    $rows = [];

    foreach ($counts as $c) {
        $rows[] = [
            'test_id' => $c['test_id'],
            'test_name' => $c['test_name'],
            'count' => $c['count'],
            'revenue' => $c['count'] * $c['price'],
        ];
    }

    usort($rows, static fn ($a, $b) => $b['revenue'] <=> $a['revenue']);

    return $rows;
}

/**
 * @param list<array<string,mixed>> $prescriptions from sp_prescriptions_list
 *
 * @return list<array{medicine_id:int,medicine_name:string,prescription_count:int}>
 */
function clinic_report_most_prescribed(array $prescriptions, ?int $from, ?int $to, array $visitDatesById): array
{
    $agg = [];

    foreach ($prescriptions as $pr) {
        $vid = (int) ($pr['Visit_ID'] ?? 0);
        $vd = $visitDatesById[$vid] ?? '';
        if ($from !== null || $to !== null) {
            if ($vd === '' || !clinic_reports_datetime_in_bounds($vd, $from, $to)) {
                continue;
            }
        }

        $mid = (int) ($pr['Medicine_ID'] ?? 0);
        $mname = trim((string) ($pr['Medicine_Name'] ?? 'Medicine #' . $mid));
        if (!isset($agg[$mid])) {
            $agg[$mid] = ['medicine_id' => $mid, 'medicine_name' => $mname, 'prescription_count' => 0];
        }
        $agg[$mid]['prescription_count']++;
        if ($mname !== '') {
            $agg[$mid]['medicine_name'] = $mname;
        }
    }

    $rows = array_values($agg);
    usort($rows, static fn ($a, $b) => $b['prescription_count'] <=> $a['prescription_count']);

    return $rows;
}

/**
 * Prescription lines with no matching in-house pharmacy sale within the window after the visit.
 *
 * @param list<array<string,mixed>> $prescriptions
 * @param list<array<string,mixed>> $pharmacySales
 * @param list<array<string,mixed>> $visits
 *
 * @return list<array{Prescription_ID:int,Patient_Name:string,Medicine_Name:string,Visit_Date:string,Sale_Qty_After:int,Gap:string}>
 */
function clinic_report_unfulfilled_prescriptions(
    array $prescriptions,
    array $pharmacySales,
    array $visits,
    ?int $from,
    ?int $to,
    int $windowDays = 90
): array {
    $visitById = [];
    foreach ($visits as $v) {
        $vid = (int) ($v['Visit_ID'] ?? 0);
        if ($vid > 0) {
            $visitById[$vid] = $v;
        }
    }

    $out = [];

    foreach ($prescriptions as $pr) {
        $vid = (int) ($pr['Visit_ID'] ?? 0);
        $v = $visitById[$vid] ?? null;
        if ($v === null) {
            continue;
        }
        $visitDate = (string) ($v['Visit_Date'] ?? '');
        if ($visitDate === '') {
            continue;
        }
        if ($from !== null || $to !== null) {
            if (!clinic_reports_datetime_in_bounds($visitDate, $from, $to)) {
                continue;
            }
        }

        $visitTs = strtotime($visitDate);
        if ($visitTs === false) {
            continue;
        }
        $windowEnd = $visitTs + ($windowDays * 86400);
        $patientId = (int) ($v['Patient_ID'] ?? 0);
        $medicineId = (int) ($pr['Medicine_ID'] ?? 0);
        if ($patientId < 1 || $medicineId < 1) {
            continue;
        }

        $sold = 0;
        foreach ($pharmacySales as $s) {
            if ((int) ($s['Patient_ID'] ?? 0) !== $patientId || (int) ($s['Medicine_ID'] ?? 0) !== $medicineId) {
                continue;
            }
            $saleTs = strtotime((string) ($s['Sale_Date'] ?? ''));
            if ($saleTs === false || $saleTs < $visitTs || $saleTs > $windowEnd) {
                continue;
            }
            $sold += (int) ($s['Quantity'] ?? 0);
        }

        if ($sold < 1) {
            $out[] = [
                'Prescription_ID' => (int) ($pr['Prescription_ID'] ?? 0),
                'Patient_Name' => (string) ($pr['Patient_Name'] ?? ''),
                'Medicine_Name' => (string) ($pr['Medicine_Name'] ?? ''),
                'Visit_Date' => $visitDate,
                'Sale_Qty_After' => $sold,
                'Gap' => 'No clinic POS sale within ' . $windowDays . ' days after visit',
            ];
        }
    }

    return $out;
}

/**
 * @param list<array<string,mixed>> $visits
 *
 * @return list<array{Patient_ID:int,Patient_Name:string,visit_count:int}>
 */
function clinic_report_visit_frequency(array $visits, ?int $from, ?int $to, int $limit = 100): array
{
    $counts = [];

    foreach ($visits as $v) {
        $dt = (string) ($v['Visit_Date'] ?? '');
        if ($dt === '' || !clinic_reports_datetime_in_bounds($dt, $from, $to)) {
            continue;
        }
        $pid = (int) ($v['Patient_ID'] ?? 0);
        if ($pid < 1) {
            continue;
        }
        $pname = trim((string) ($v['Patient_Name'] ?? 'Patient #' . $pid));
        if (!isset($counts[$pid])) {
            $counts[$pid] = ['Patient_ID' => $pid, 'Patient_Name' => $pname, 'visit_count' => 0];
        }
        $counts[$pid]['visit_count']++;
        if ($pname !== '') {
            $counts[$pid]['Patient_Name'] = $pname;
        }
    }

    $rows = array_values($counts);
    usort($rows, static fn ($a, $b) => $b['visit_count'] <=> $a['visit_count']);

    return array_slice($rows, 0, max(10, min(500, $limit)));
}
