<?php
declare(strict_types=1);

/**
 * Dashboard data layer — every metric below comes straight from the live
 * database (schema matches db/clinic.sql). Never throws: any failing query
 * degrades to a safe default so the dashboard always renders.
 */

require_once dirname(__DIR__, 2) . '/config/procedures.php';

if (!function_exists('clinic_dash_rows')) {
    function clinic_dash_rows(string $sql, array $params = [], string $types = ''): array
    {
        global $conn;

        try {
            if (!isset($conn) || !($conn instanceof mysqli)) {
                return [];
            }
            $stmt = $conn->prepare($sql);
            if ($stmt === false) {
                return [];
            }
            if ($params !== []) {
                $stmt->bind_param($types !== '' ? $types : str_repeat('s', count($params)), ...$params);
            }
            $stmt->execute();
            $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            return $rows;
        } catch (Throwable $e) {
            return [];
        }
    }

    function clinic_dash_val(string $sql, array $params = [], string $types = ''): mixed
    {
        $rows = clinic_dash_rows($sql, $params, $types);
        if ($rows === [] || !is_array($rows[0])) {
            return null;
        }

        return reset($rows[0]);
    }

    function clinic_dash_int(string $sql, array $params = [], string $types = ''): int
    {
        return (int) (clinic_dash_val($sql, $params, $types) ?? 0);
    }

    function clinic_dash_float(string $sql, array $params = [], string $types = ''): float
    {
        return (float) (clinic_dash_val($sql, $params, $types) ?? 0);
    }

    /**
     * Build a [label => value] series for the last 7 days (oldest → newest).
     * $table must be a safe identifier from a fixed map (no user input).
     */
    function clinic_dash_daily_series(string $dateCol, string $table, string $valueExpr, array $extra = []): array
    {
        $labels = [];
        $values = [];
        $map = [];
        for ($i = 6; $i >= 0; $i--) {
            $d = date('Y-m-d', strtotime("-$i days"));
            $labels[] = date('D', strtotime($d));
            $map[$d] = 0;
        }

        $sql = 'SELECT DATE(' . $dateCol . ') AS d, ' . $valueExpr . ' AS v FROM `' . $table . '`'
            . ($extra['join'] ?? '')
            . ' WHERE ' . $dateCol . ' >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)'
            . ($extra['where'] ?? '')
            . ' GROUP BY DATE(' . $dateCol . ')';

        foreach (clinic_dash_rows($sql) as $row) {
            $day = (string) ($row['d'] ?? '');
            if (isset($map[$day])) {
                $map[$day] = (float) ($row['v'] ?? 0);
            }
        }

        return ['labels' => $labels, 'values' => array_values($map)];
    }

    /**
     * All dashboard data in one array. Role/doctor aware so sections can be
     * rendered per user later.
     */
    function clinic_dashboard_data(): array
    {
        $roleId = (int) ($_SESSION['role_id'] ?? $_SESSION['Role_ID'] ?? 1);
        $userId = (int) ($_SESSION['user_no'] ?? $_SESSION['User_ID'] ?? 0);

        // Doctor context: which Doctor_ID belongs to the signed-in user?
        $doctorId = (int) clinic_dash_val('SELECT Doctor_ID FROM doctors WHERE User_ID = ? AND deleted = 0 LIMIT 1', [$userId], 'i');

        $data = [
            'role' => $roleId,
            'isAdmin' => $roleId === 1,
            'isDoctor' => $roleId === 2,
            'isReception' => $roleId === 3,
            'isPharmacist' => $roleId === 4,
            'isLabTech' => $roleId === 5,
            'isNurse' => $roleId === 6,
            'doctorId' => $doctorId,
            'kpis' => [],
            'appointmentsToday' => [],
            'appointmentsByStatus' => [],
            'appointmentsByDoctor' => [],
            'revenueTrend' => [],
            'revenueByMethod' => [],
            'patientTrend' => [],
            'patientGender' => ['Male' => 0, 'Female' => 0],
            'patientAge' => ['Child' => 0, 'Adult' => 0],
            'patientType' => ['Credit' => 0, 'Walk-in' => 0],
            'recentPatients' => [],
            'recentPayments' => [],
            'recentLab' => [],
            'recentPrescriptions' => [],
            'recentVisits' => [],
            'topMedicines' => [],
            'topLabTests' => [],
            'pharmacyTrend' => [],
            'nursingByService' => [],
            'nursingToday' => 0,
            'nursingTotal' => 0,
            'doctorWorkload' => [],
            'lowStock' => [],
            'expired' => [],
            'criticalLab' => [],
            'activity' => [],
            'accounts' => [],
        ];
/*__DATA2__*/
        // ---------- KPIs ----------
        $data['kpis'] = [
            'patients_total'  => clinic_dash_int('SELECT COUNT(*) FROM patients WHERE deleted = 0'),
            'patients_today'  => clinic_dash_int('SELECT COUNT(*) FROM patients WHERE deleted = 0 AND DATE(Created_At) = CURDATE()'),
            'appointments_today' => clinic_dash_int('SELECT COUNT(*) FROM appointments WHERE DATE(Appointment_Date) = CURDATE()'),
            'appointments_pending' => clinic_dash_int("SELECT COUNT(*) FROM appointments WHERE Status = 'Pending'"),
            'visits_today'    => clinic_dash_int('SELECT COUNT(*) FROM visits WHERE DATE(Visit_Date) = CURDATE()'),
            'lab_pending'     => clinic_dash_int("SELECT COUNT(*) FROM lab_results WHERE Status = 'Pending'"),
            'lab_completed'   => clinic_dash_int("SELECT COUNT(*) FROM lab_results WHERE Status = 'Completed'"),
            'lab_critical'    => clinic_dash_int("SELECT COUNT(*) FROM lab_results WHERE Status = 'Completed' AND (Result_Details LIKE '%Positive%' OR Result_Details LIKE '%sareey%' OR Result_Details LIKE '%Sareey%' OR Result_Details LIKE '%caabuq%')"),
            'pharmacy_today'  => clinic_dash_float("SELECT COALESCE(SUM(Total_Price),0) FROM pharmacy_sales WHERE DATE(Sale_Date) = CURDATE()"),
            'prescriptions_today' => clinic_dash_int('SELECT COUNT(*) FROM prescriptions pr JOIN visits v ON v.Visit_ID = pr.Visit_ID WHERE DATE(v.Visit_Date) = CURDATE()'),
            'revenue_today'   => clinic_dash_float("SELECT COALESCE(SUM(Amount),0) FROM payments WHERE DATE(Payment_Date) = CURDATE()"),
            'revenue_week'    => clinic_dash_float('SELECT COALESCE(SUM(Amount),0) FROM payments WHERE Payment_Date >= DATE_SUB(NOW(), INTERVAL 7 DAY)'),
            'revenue_month'   => clinic_dash_float('SELECT COALESCE(SUM(Amount),0) FROM payments WHERE Payment_Date >= DATE_SUB(NOW(), INTERVAL 30 DAY)'),
            'doctors_total'   => clinic_dash_int('SELECT COUNT(*) FROM doctors'),
            'doctors_active'  => clinic_dash_int('SELECT COUNT(*) FROM doctors WHERE deleted = 0'),
            'staff_total'     => clinic_dash_int("SELECT COUNT(*) FROM staff WHERE status = 'active'"),
            'staff_active'    => clinic_dash_int("SELECT COUNT(*) FROM staff WHERE status = 'active'"),
            'low_stock_count' => clinic_dash_int('SELECT COUNT(*) FROM medicines WHERE deleted = 0 AND Stock_Quantity <= 100'),
            'expired_count'   => clinic_dash_int('SELECT COUNT(*) FROM medicines WHERE deleted = 0 AND Expiry_Date IS NOT NULL AND Expiry_Date < CURDATE()'),
            'patient_debt'    => clinic_dash_float('SELECT COALESCE(SUM(Current_Balance),0) FROM patients WHERE deleted = 0'),
            'accounts_total'  => clinic_dash_float('SELECT COALESCE(SUM(Current_Balance),0) FROM accounts'),
        ];

        // ---------- Appointments (today) ----------
        $apptWhere = 'WHERE DATE(a.Appointment_Date) = CURDATE()';
        $apptParams = [];
        $apptTypes = '';
        if ($doctorId > 0) {
            $apptWhere .= ' AND a.Doctor_ID = ?';
            $apptParams[] = $doctorId;
            $apptTypes = 'i';
        }
        $data['appointmentsToday'] = clinic_dash_rows(
            "SELECT a.Appointment_ID, a.Appointment_Date, a.Status, a.Patient_ID, a.Doctor_ID,
                    p.Full_Name AS Patient_Name, d.Full_Name AS Doctor_Name
             FROM appointments a
             LEFT JOIN patients p ON p.Patient_ID = a.Patient_ID
             LEFT JOIN doctors d ON d.Doctor_ID = a.Doctor_ID
             $apptWhere
             ORDER BY a.Appointment_Date ASC LIMIT 8",
            $apptParams,
            $apptTypes
        );

        foreach (clinic_dash_rows(
            "SELECT a.Status, COUNT(*) AS c FROM appointments a $apptWhere GROUP BY a.Status",
            $apptParams,
            $apptTypes
        ) as $row) {
            $data['appointmentsByStatus'][(string) $row['Status']] = (int) $row['c'];
        }

        foreach (clinic_dash_rows(
            "SELECT d.Full_Name AS Doctor_Name, COUNT(*) AS c
             FROM appointments a
             LEFT JOIN doctors d ON d.Doctor_ID = a.Doctor_ID
             WHERE DATE(a.Appointment_Date) = CURDATE()
             GROUP BY d.Full_Name, d.Doctor_ID
             ORDER BY c DESC LIMIT 6",
            [],
            ''
        ) as $row) {
            $data['appointmentsByDoctor'][] = [
                'doctor' => (string) ($row['Doctor_Name'] ?? 'Unassigned'),
                'count' => (int) $row['c'],
            ];
        }
/*__DATA3__*/
        // ---------- Revenue & pharmacy trends (7 days) ----------
        $data['revenueTrend'] = clinic_dash_daily_series('Payment_Date', 'payments', 'COALESCE(SUM(Amount),0)');
        $data['pharmacyTrend'] = clinic_dash_daily_series('Sale_Date', 'pharmacy_sales', 'COALESCE(SUM(Total_Price),0)');
        $data['visitsTrend'] = clinic_dash_daily_series('Visit_Date', 'visits', 'COUNT(*)');

        foreach (clinic_dash_rows(
            "SELECT Payment_Method, COALESCE(SUM(Amount),0) AS total FROM payments GROUP BY Payment_Method ORDER BY total DESC"
        ) as $row) {
            $data['revenueByMethod'][(string) $row['Payment_Method']] = (float) $row['total'];
        }

        // ---------- Patients ----------
        $data['patientTrend'] = clinic_dash_daily_series('Created_At', 'patients', 'COUNT(*)', ['where' => ' AND deleted = 0']);

        foreach (clinic_dash_rows('SELECT Sex, COUNT(*) AS c FROM patients WHERE deleted = 0 GROUP BY Sex') as $row) {
            $data['patientGender'][(string) $row['Sex']] = (int) $row['c'];
        }
        foreach (clinic_dash_rows('SELECT Age_Group, COUNT(*) AS c FROM patients WHERE deleted = 0 GROUP BY Age_Group') as $row) {
            $data['patientAge'][(string) $row['Age_Group']] = (int) $row['c'];
        }
        foreach (clinic_dash_rows('SELECT Patient_Type, COUNT(*) AS c FROM patients WHERE deleted = 0 GROUP BY Patient_Type') as $row) {
            $data['patientType'][(string) $row['Patient_Type']] = (int) $row['c'];
        }

        $data['recentPatients'] = clinic_dash_rows(
            'SELECT Patient_ID, Full_Name, Phone_Number, Sex, Age_Group, Patient_Type, Current_Balance, Created_At
             FROM patients WHERE deleted = 0 ORDER BY Created_At DESC, Patient_ID DESC LIMIT 6'
        );

        // ---------- Recent payments ----------
        $data['recentPayments'] = clinic_dash_rows(
            'SELECT p.Payment_ID, p.Amount, p.Payment_Method, p.Payment_Date, pat.Full_Name AS Patient_Name, a.Account_Name
             FROM payments p
             LEFT JOIN patients pat ON pat.Patient_ID = p.Patient_ID
             LEFT JOIN accounts a ON a.Account_ID = p.Account_ID
             ORDER BY p.Payment_Date DESC, p.Payment_ID DESC LIMIT 6'
        );

        // ---------- Recent lab results ----------
        $data['recentLab'] = clinic_dash_rows(
            "SELECT lr.Result_ID, lr.Status, lr.Result_Details, lt.Test_Name, p.Full_Name AS Patient_Name, d.Full_Name AS Doctor_Name
             FROM lab_results lr
             LEFT JOIN visits v ON v.Visit_ID = lr.Visit_ID
             LEFT JOIN lab_tests lt ON lt.Test_ID = lr.Test_ID
             LEFT JOIN patients p ON p.Patient_ID = v.Patient_ID
             LEFT JOIN doctors d ON d.Doctor_ID = v.Doctor_ID
             ORDER BY lr.Result_ID DESC LIMIT 6"
        );

        // ---------- Recent prescriptions ----------
        $data['recentPrescriptions'] = clinic_dash_rows(
            'SELECT pr.Prescription_ID, pr.Dosage, m.Medicine_Name, p.Full_Name AS Patient_Name, d.Full_Name AS Doctor_Name, v.Visit_Date
             FROM prescriptions pr
             LEFT JOIN visits v ON v.Visit_ID = pr.Visit_ID
             LEFT JOIN medicines m ON m.Medicine_ID = pr.Medicine_ID
             LEFT JOIN patients p ON p.Patient_ID = v.Patient_ID
             LEFT JOIN doctors d ON d.Doctor_ID = v.Doctor_ID
             ORDER BY pr.Prescription_ID DESC LIMIT 6'
        );

        // ---------- Recent visits ----------
        $data['recentVisits'] = clinic_dash_rows(
            'SELECT v.Visit_ID, v.Visit_Date, v.Notes, p.Full_Name AS Patient_Name, d.Full_Name AS Doctor_Name
             FROM visits v
             LEFT JOIN patients p ON p.Patient_ID = v.Patient_ID
             LEFT JOIN doctors d ON d.Doctor_ID = v.Doctor_ID
             ORDER BY v.Visit_Date DESC, v.Visit_ID DESC LIMIT 6'
        );

        // ---------- Recent pharmacy sales ----------
        $data['recentPharmacySales'] = clinic_dash_rows(
            'SELECT ps.Sale_ID, ps.Quantity, ps.Total_Price, ps.Sale_Date, m.Medicine_Name, p.Full_Name AS Patient_Name
             FROM pharmacy_sales ps
             LEFT JOIN medicines m ON m.Medicine_ID = ps.Medicine_ID
             LEFT JOIN patients p ON p.Patient_ID = ps.Patient_ID
             ORDER BY ps.Sale_Date DESC, ps.Sale_ID DESC LIMIT 6'
        );

        // ---------- Recent nursing records ----------
        $data['recentNursing'] = clinic_dash_rows(
            'SELECT nr.Record_ID, nr.Record_Date, ns.Service_Name, p.Full_Name AS Patient_Name
             FROM nursing_records nr
             LEFT JOIN visits v ON v.Visit_ID = nr.Visit_ID
             LEFT JOIN nursing_services ns ON ns.Service_ID = nr.Service_ID
             LEFT JOIN patients p ON p.Patient_ID = v.Patient_ID
             ORDER BY nr.Record_Date DESC, nr.Record_ID DESC LIMIT 6'
        );
/*__DATA4__*/
        // ---------- Pharmacy ----------
        $data['topMedicines'] = clinic_dash_rows(
            "SELECT m.Medicine_Name, COALESCE(SUM(ps.Quantity),0) AS qty, COALESCE(SUM(ps.Total_Price),0) AS total
             FROM pharmacy_sales ps
             LEFT JOIN medicines m ON m.Medicine_ID = ps.Medicine_ID
             GROUP BY m.Medicine_ID, m.Medicine_Name
             ORDER BY qty DESC LIMIT 6"
        );

        $data['lowStock'] = clinic_dash_rows(
            'SELECT Medicine_ID, Medicine_Name, Stock_Quantity, Price, Expiry_Date
             FROM medicines WHERE deleted = 0 AND Stock_Quantity <= 100
             ORDER BY Stock_Quantity ASC LIMIT 8'
        );

        $data['expired'] = clinic_dash_rows(
            'SELECT Medicine_ID, Medicine_Name, Stock_Quantity, Expiry_Date
             FROM medicines WHERE deleted = 0 AND Expiry_Date IS NOT NULL AND Expiry_Date < CURDATE()
             ORDER BY Expiry_Date ASC LIMIT 8'
        );

        // ---------- Lab ----------
        $data['topLabTests'] = clinic_dash_rows(
            'SELECT lt.Test_Name, COUNT(*) AS c
             FROM lab_results lr
             LEFT JOIN lab_tests lt ON lt.Test_ID = lr.Test_ID
             GROUP BY lt.Test_ID, lt.Test_Name
             ORDER BY c DESC LIMIT 6'
        );

        $data['criticalLab'] = clinic_dash_rows(
            "SELECT lr.Result_ID, lr.Result_Details, lt.Test_Name, p.Full_Name AS Patient_Name
             FROM lab_results lr
             LEFT JOIN visits v ON v.Visit_ID = lr.Visit_ID
             LEFT JOIN lab_tests lt ON lt.Test_ID = lr.Test_ID
             LEFT JOIN patients p ON p.Patient_ID = v.Patient_ID
             WHERE lr.Status = 'Completed'
               AND (lr.Result_Details LIKE '%Positive%' OR lr.Result_Details LIKE '%sareey%' OR lr.Result_Details LIKE '%Sareey%' OR lr.Result_Details LIKE '%caabuq%')
             ORDER BY lr.Result_ID DESC LIMIT 6"
        );

        // ---------- Nursing ----------
        $data['nursingTotal'] = clinic_dash_int('SELECT COUNT(*) FROM nursing_records');
        $data['nursingToday'] = clinic_dash_int('SELECT COUNT(*) FROM nursing_records WHERE DATE(Record_Date) = CURDATE()');
        $data['nursingByService'] = clinic_dash_rows(
            'SELECT ns.Service_Name, COUNT(*) AS c
             FROM nursing_records nr
             LEFT JOIN nursing_services ns ON ns.Service_ID = nr.Service_ID
             GROUP BY ns.Service_ID, ns.Service_Name
             ORDER BY c DESC LIMIT 6'
        );

        // ---------- Doctors / workload ----------
        $data['doctorWorkload'] = clinic_dash_rows(
            'SELECT d.Full_Name AS Doctor_Name, d.Specialization,
                    COUNT(DISTINCT v.Visit_ID) AS visits,
                    COUNT(DISTINCT a.Appointment_ID) AS appointments
             FROM doctors d
             LEFT JOIN visits v ON v.Doctor_ID = d.Doctor_ID
             LEFT JOIN appointments a ON a.Doctor_ID = d.Doctor_ID
             WHERE d.deleted = 0
             GROUP BY d.Doctor_ID, d.Full_Name, d.Specialization
             ORDER BY visits DESC LIMIT 8'
        );

        // ---------- Accounts ----------
        $data['accounts'] = clinic_dash_rows(
            'SELECT Account_ID, Account_Name, Current_Balance FROM accounts ORDER BY Current_Balance DESC LIMIT 6'
        );

        // ---------- System activity (audit log) ----------
        $data['activity'] = clinic_dash_rows(
            'SELECT log_id, user_id, username, action, entity, entity_id, details, ip_address, created_at
             FROM audit_logs ORDER BY created_at DESC, log_id DESC LIMIT 12'
        );

        // ---------- Report snapshot ----------
        $data['snapshot'] = [
            'revenue_today'  => $data['kpis']['revenue_today'],
            'revenue_month'  => $data['kpis']['revenue_month'],
            'patients_today' => $data['kpis']['patients_today'],
            'patients_total' => $data['kpis']['patients_total'],
            'lab_completed'  => $data['kpis']['lab_completed'],
            'lab_pending'    => $data['kpis']['lab_pending'],
            'prescriptions'  => $data['kpis']['prescriptions_today'],
            'outstanding'    => $data['kpis']['patient_debt'],
            'pharmacy'       => $data['kpis']['pharmacy_today'],
        ];



        return $data;
    }
}
