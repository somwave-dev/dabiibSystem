<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/session.php';

requireLogin();

require_once __DIR__ . '/../includes/crud_page.php';
require_once __DIR__ . '/../config/codes.php';

// Only administrators can manage system settings.
if ((int) ($_SESSION['role_id'] ?? 0) !== 1) {
    $_SESSION['error'] = 'Only administrators can manage system settings.';
    header('Location: ../unauthorized.php', true, 302);
    exit;
}

$co = new Codes();
$db = $co->db;

/**
 * Turn a stored logo value (local relative path, root-relative path or URL)
 * into an src usable from the /pages context.
 */
function clinic_settings_logo_url(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }
    if (preg_match('#^[a-z][a-z0-9+.-]*://#i', $value) || str_starts_with($value, '/')) {
        return $value;
    }

    return '../' . ltrim($value, '/');
}

/**
 * Validate and store an uploaded logo file. Returns the stored web path.
 */
function clinic_settings_handle_upload(string $fileKey): string
{
    $file = $_FILES[$fileKey] ?? null;
    if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Upload failed — no file was received.');
    }
    if ((int) $file['size'] > 2 * 1024 * 1024) {
        throw new RuntimeException('Uploaded file is larger than the 2MB limit.');
    }

    $ext = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp', 'avif', 'ico'];
    if (!in_array($ext, $allowed, true)) {
        throw new RuntimeException('Unsupported file type ".'.$ext.'". Allowed: '.implode(', ', $allowed).'.');
    }

    $tmp = (string) ($file['tmp_name'] ?? '');
    if (!is_uploaded_file($tmp)) {
        throw new RuntimeException('Invalid upload source.');
    }

    // Content check — any real image is accepted, including transparent
    // (no-background) PNG / SVG / WebP logos and favicons.
    $mimeOk = false;
    if (function_exists('finfo_open')) {
        $mime = (string) (new finfo(FILEINFO_MIME_TYPE))->file($tmp);
        $mimeOk = in_array($mime, [
            'image/png', 'image/jpeg', 'image/gif', 'image/svg+xml',
            'image/webp', 'image/avif', 'image/x-icon', 'image/vnd.microsoft.icon',
        ], true);
    }
    if (!$mimeOk && $ext !== 'svg' && $ext !== 'ico' && $ext !== 'avif' && @getimagesize($tmp) === false) {
        throw new RuntimeException('Uploaded file is not a valid image.');
    }

    $dir = __DIR__ . '/../storage/uploads/settings';
    if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
        throw new RuntimeException('Could not create the upload directory.');
    }

    $filename = date('Ymd') . '-' . substr(bin2hex(random_bytes(6)), 0, 12) . '.' . $ext;
    $dest = $dir . '/' . $filename;
    if (!move_uploaded_file($tmp, $dest)) {
        throw new RuntimeException('Could not save the uploaded file.');
    }

    return 'storage/uploads/settings/' . $filename;
}

/**
 * Build a full .sql dump of every table (structure + records) for backup downloads.
 */
function clinic_settings_backup_sql(mysqli $db): string
{
    $sql = "-- Dabiib System Database Backup\n-- Generated: " . date('Y-m-d H:i:s') . "\n\nSET FOREIGN_KEY_CHECKS=0;\n\n";

    $res = $db->query('SHOW TABLES');
    $tables = [];
    while ($row = $res->fetch_row()) {
        $tables[] = (string) $row[0];
    }
    $res->free();

    foreach ($tables as $table) {
        $safeTable = str_replace('`', '``', $table);
        $cr = $db->query('SHOW CREATE TABLE `' . $safeTable . '`');
        if (!$cr) {
            continue;
        }
        $cRow = $cr->fetch_row();
        $cr->free();
        $sql .= "DROP TABLE IF EXISTS `" . $safeTable . "`;\n" . ($cRow[1] ?? '') . ";\n\n";

        $dr = $db->query('SELECT * FROM `' . $safeTable . '`');
        if (!$dr) {
            continue;
        }
        $fieldCount = $dr->field_count;
        while ($r = $dr->fetch_row()) {
            $parts = [];
            for ($i = 0; $i < $fieldCount; $i++) {
                $parts[] = $r[$i] === null ? 'NULL' : "'" . $db->real_escape_string((string) $r[$i]) . "'";
            }
            $sql .= "INSERT INTO `" . $safeTable . "` VALUES (" . implode(', ', $parts) . ");\n";
        }
        $dr->free();
        $sql .= "\n";
    }

    $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";

    return $sql;
}

$settingsConfig = [
    'clinic' => [
        'group' => 'Organization',
        'icon' => 'ti-building',
        'title' => 'Clinic Information',
        'subtitle' => 'Identity and contact details used across the whole system.',
        'fields' => [
            'clinic_name' => ['label' => 'Clinic Name', 'type' => 'text', 'col' => 6, 'placeholder' => 'e.g. Dabiib Clinic Center', 'help' => 'Used on the login page, emails and reports.'],
            'site_name' => ['label' => 'Site / System Name', 'type' => 'text', 'col' => 6, 'placeholder' => 'e.g. Dabiib System', 'help' => 'Browser title shown in the login page.'],
            'clinic_code' => ['label' => 'Registration / License No.', 'type' => 'text', 'col' => 6, 'placeholder' => 'e.g. MOH-2026-0001'],
            'clinic_phone' => ['label' => 'Phone Numbers', 'type' => 'text', 'col' => 6, 'placeholder' => '+252 61 000 0000'],
            'clinic_email' => ['label' => 'Contact Email', 'type' => 'email', 'col' => 6, 'placeholder' => 'info@clinic.so'],
            'clinic_website' => ['label' => 'Website', 'type' => 'url', 'col' => 6, 'placeholder' => 'https://clinic.so'],
            'clinic_city' => ['label' => 'City', 'type' => 'text', 'col' => 6, 'placeholder' => 'e.g. Mogadishu'],
            'clinic_country' => ['label' => 'Country', 'type' => 'text', 'col' => 6, 'placeholder' => 'e.g. Somalia'],
            'working_days' => ['label' => 'Working Days', 'type' => 'text', 'col' => 6, 'placeholder' => 'e.g. Mon – Sat'],
            'opening_time' => ['label' => 'Opening Time', 'type' => 'time', 'col' => 3, 'help' => 'Reception opens at'],
            'closing_time' => ['label' => 'Closing Time', 'type' => 'time', 'col' => 3, 'help' => 'Reception closes at'],
            'clinic_address' => ['label' => 'Address', 'type' => 'textarea', 'col' => 12, 'rows' => 2, 'placeholder' => 'Street, district, landmark…'],
            'clinic_description' => ['label' => 'Clinic Description', 'type' => 'textarea', 'col' => 12, 'rows' => 2, 'help' => 'Short description used in reports and documents.'],
            'site_footer' => ['label' => 'Footer Tagline', 'type' => 'text', 'col' => 6, 'placeholder' => 'Powered by SomWave Solutions', 'help' => 'Shown in the login page footer.'],
        ],
    ],

    'branding' => [
        'group' => 'Organization',
        'icon' => 'ti-palette',
        'title' => 'Branding & Logos',
        'subtitle' => 'Upload PNG, JPG, SVG, WebP or ICO (max 2MB) — transparent / no-background images supported. Click a tile to upload; each logo is reused everywhere — no duplicated uploads.',
        'fields' => [
            'site_logo' => ['label' => 'Primary Clinic Logo', 'type' => 'logo', 'col' => 4, 'help' => 'Header, login page & dashboard.'],
            'logo_light' => ['label' => 'Logo — Light Version', 'type' => 'logo', 'col' => 4, 'help' => 'For dark backgrounds (dark mode).'],
            'logo_dark' => ['label' => 'Logo — Dark Version', 'type' => 'logo', 'col' => 4, 'help' => 'For light backgrounds & printing.'],
            'favicon' => ['label' => 'Favicon / App Icon', 'type' => 'logo', 'col' => 4, 'accept' => '.png,.ico,.svg,.webp', 'help' => 'Browser tab icon — transparent PNG best.'],
            'document_logo' => ['label' => 'Document Logo', 'type' => 'logo', 'col' => 4, 'help' => 'Invoices, receipts, prescriptions, lab reports, letters.'],
           
            'clinic_stamp' => ['label' => 'Clinic Stamp', 'type' => 'logo', 'col' => 4, 'help' => 'Official stamp for printed documents.'],
            
        ],
    ],


    'general' => [
        'group' => 'Preferences',
        'icon' => 'ti-world',
        'title' => 'General System',
        'subtitle' => 'Currency, dates, timezone and language used by default.',
        'fields' => [
            'default_currency' => ['label' => 'Default Currency', 'type' => 'select', 'col' => 6, 'options' => ['USD' => 'USD — US Dollar ($)', 'SOS' => 'SOS — Somali Shilling (Sh)', 'GBP' => 'GBP — British Pound (£)', 'EUR' => 'EUR — Euro (€)', 'ETB' => 'ETB — Ethiopian Birr (Br)', 'KES' => 'KES — Kenyan Shilling (KSh)', 'SAR' => 'SAR — Saudi Riyal', 'AED' => 'AED — UAE Dirham']],
            'currency_symbol' => ['label' => 'Currency Symbol', 'type' => 'text', 'col' => 6, 'placeholder' => 'e.g. $ or Sh'],
            'date_format' => ['label' => 'Date Format', 'type' => 'select', 'col' => 6, 'options' => ['Y-m-d' => 'YYYY-MM-DD  (2026-08-27)', 'd/m/Y' => 'DD/MM/YYYY  (27/08/2026)', 'm/d/Y' => 'MM/DD/YYYY  (08/27/2026)', 'd M Y' => 'DD Mon YYYY  (27 Aug 2026)', 'F j, Y' => 'Month D, YYYY  (August 27, 2026)']],
            'time_format' => ['label' => 'Time Format', 'type' => 'select', 'col' => 6, 'options' => ['12' => '12-hour (10:30 AM)', '24' => '24-hour (22:30)']],
            'timezone' => ['label' => 'Timezone', 'type' => 'select', 'col' => 6, 'options' => ['UTC' => 'UTC (Coordinated Universal Time)', 'Africa/Mogadishu' => 'Africa/Mogadishu (UTC+3)', 'Africa/Nairobi' => 'Africa/Nairobi (UTC+3)', 'Africa/Addis_Ababa' => 'Africa/Addis_Ababa (UTC+3)', 'Africa/Kampala' => 'Africa/Kampala (UTC+3)', 'Europe/London' => 'Europe/London (UTC+0/+1)', 'Asia/Riyadh' => 'Asia/Riyadh (UTC+3)', 'Asia/Dubai' => 'Asia/Dubai (UTC+4)', 'America/New_York' => 'America/New_York (UTC-5/-4)']],
            'language' => ['label' => 'Default Language', 'type' => 'select', 'col' => 6, 'options' => ['en' => 'English', 'so' => 'Somali (Soomaali)', 'ar' => 'Arabic (العربية)']],
            'decimal_places' => ['label' => 'Decimal Places', 'type' => 'select', 'col' => 6, 'options' => ['0' => '0 (no decimals)', '1' => '1 decimal', '2' => '2 decimals', '3' => '3 decimals']],
            'number_format' => ['label' => 'Number Format', 'type' => 'select', 'col' => 6, 'options' => ['1,234.56' => '1,234.56', '1 234,56' => '1 234,56', '1234.56' => '1234.56']],
        ],
    ],

    'clinical' => [
        'group' => 'Preferences',
        'icon' => 'ti-stethoscope',
        'title' => 'Clinical Settings',
        'subtitle' => 'ID prefixes, numbering and default clinical behaviour.',
        'fields' => [
            'patient_id_prefix' => ['label' => 'Patient ID Prefix', 'type' => 'text', 'col' => 4, 'placeholder' => 'PT-', 'help' => 'PT-000001'],
            'doctor_id_prefix' => ['label' => 'Doctor ID Prefix', 'type' => 'text', 'col' => 4, 'placeholder' => 'DR-', 'help' => 'DR-000001'],
            'appointment_number_prefix' => ['label' => 'Appointment Prefix', 'type' => 'text', 'col' => 4, 'placeholder' => 'AP-', 'help' => 'AP-000001'],
            'visit_number_prefix' => ['label' => 'Visit Prefix', 'type' => 'text', 'col' => 4, 'placeholder' => 'VS-', 'help' => 'VS-000001'],
            'prescription_number_prefix' => ['label' => 'Prescription Prefix', 'type' => 'text', 'col' => 4, 'placeholder' => 'RX-', 'help' => 'RX-000001'],
            'invoice_number_prefix' => ['label' => 'Invoice Prefix', 'type' => 'text', 'col' => 4, 'placeholder' => 'INV-', 'help' => 'INV-000001'],
            'receipt_number_prefix' => ['label' => 'Receipt Prefix', 'type' => 'text', 'col' => 4, 'placeholder' => 'RCT-', 'help' => 'RCT-000001'],
            'lab_number_prefix' => ['label' => 'Lab Number Prefix', 'type' => 'text', 'col' => 4, 'placeholder' => 'LAB-', 'help' => 'LAB-000001'],
            'default_consultation_minutes' => ['label' => 'Consultation Duration (min)', 'type' => 'number', 'col' => 4, 'placeholder' => '15'],
            'default_visit_type' => ['label' => 'Default Visit Type', 'type' => 'select', 'col' => 8, 'options' => ['General' => 'General Consultation', 'Follow-up' => 'Follow-up', 'Emergency' => 'Emergency', 'Review' => 'Review', 'Other' => 'Other']],
        ],
    ],


    'billing' => [
        'group' => 'Preferences',
        'icon' => 'ti-wallet',
        'title' => 'Billing & Payment',
        'subtitle' => 'Invoices, receipts, tax and payment defaults.',
        'fields' => [
            'billing_enabled' => ['label' => 'Enable Billing', 'type' => 'switch', 'help' => 'Turn billing and payment recording on/off.'],
            'default_payment_method' => ['label' => 'Default Payment Method', 'type' => 'select', 'col' => 6, 'options' => ['cash' => 'Cash', 'card' => 'Card', 'bank_transfer' => 'Bank Transfer', 'mobile_money' => 'Mobile Money', 'insurance' => 'Insurance']],
            'tax_enabled' => ['label' => 'Enable Tax / VAT', 'type' => 'switch', 'help' => 'Apply tax to invoices automatically.'],
            'tax_percentage' => ['label' => 'Tax / VAT (%)', 'type' => 'number', 'col' => 6, 'placeholder' => '0', 'help' => 'e.g. 5 for 5% VAT.'],
            'insurance_enabled' => ['label' => 'Insurance Support', 'type' => 'switch', 'help' => 'Allow insurance claims in billing.'],
            'payment_due_days' => ['label' => 'Payment Due (days)', 'type' => 'number', 'col' => 6, 'placeholder' => '0', 'help' => '0 = due on the invoice date.'],
            'discount_rules' => ['label' => 'Discount Rules', 'type' => 'textarea', 'col' => 12, 'rows' => 2, 'placeholder' => 'e.g. 10% staff discount, 50% first visit…'],
        ],
    ],

    'appointments' => [
        'group' => 'Preferences',
        'icon' => 'ti-calendar',
        'title' => 'Appointments',
        'subtitle' => 'Scheduling defaults for the appointment board.',
        'fields' => [
            'appointments_enabled' => ['label' => 'Appointments Enabled', 'type' => 'switch'],
            'default_slot_duration' => ['label' => 'Default Slot Duration (min)', 'type' => 'number', 'col' => 6, 'placeholder' => '20'],
            'buffer_time' => ['label' => 'Buffer Time (min)', 'type' => 'number', 'col' => 6, 'placeholder' => '5', 'help' => 'Gap kept between two appointments.'],
            'max_daily_appointments' => ['label' => 'Max Appointments / Day', 'type' => 'number', 'col' => 6, 'placeholder' => '60'],
            'allow_walk_in' => ['label' => 'Allow Walk-in Patients', 'type' => 'switch'],
            'allow_same_day_booking' => ['label' => 'Allow Same-day Booking', 'type' => 'switch'],
            'appointment_reminder_enabled' => ['label' => 'Appointment Reminders', 'type' => 'switch'],
            'appointment_reminder_hours' => ['label' => 'Remind Before (hours)', 'type' => 'number', 'col' => 6, 'placeholder' => '24'],
        ],
    ],

    'pharmacy' => [
        'group' => 'Modules',
        'icon' => 'ti-medicine',
        'title' => 'Pharmacy',
        'subtitle' => 'Stock, expiry and dispensing behaviour.',
        'fields' => [
            'pharmacy_enabled' => ['label' => 'Pharmacy Module Enabled', 'type' => 'switch'],
            'stock_management' => ['label' => 'Stock Management', 'type' => 'switch'],
            'batch_tracking' => ['label' => 'Batch Tracking', 'type' => 'switch'],
            'expiry_tracking' => ['label' => 'Expiry Tracking', 'type' => 'switch'],
            'auto_stock_deduction' => ['label' => 'Auto Stock Deduction', 'type' => 'switch', 'help' => 'Deduct stock automatically when dispensing.'],
            'low_stock_threshold' => ['label' => 'Low Stock Threshold', 'type' => 'number', 'col' => 6, 'placeholder' => '10'],
            'near_expiry_days' => ['label' => 'Near-expiry Alert (days)', 'type' => 'number', 'col' => 6, 'placeholder' => '30'],
        ],
    ],


    'laboratory' => [
        'group' => 'Modules',
        'icon' => 'ti-microscope',
        'title' => 'Laboratory',
        'subtitle' => 'Lab workflow and result safety.',
        'fields' => [
            'laboratory_enabled' => ['label' => 'Laboratory Module Enabled', 'type' => 'switch'],
            'result_approval_required' => ['label' => 'Result Approval Required', 'type' => 'switch', 'help' => 'A supervisor must approve results before they are released.'],
            'critical_result_alert' => ['label' => 'Critical Result Alert', 'type' => 'switch', 'help' => 'Flag critical values immediately.'],
            'result_notification_enabled' => ['label' => 'Result Notifications', 'type' => 'switch', 'help' => 'Notify the requesting doctor when a result is ready.'],
        ],
    ],

    'notifications' => [
        'group' => 'Modules',
        'icon' => 'ti-bell',
        'title' => 'Notifications',
        'subtitle' => 'Channels and events that generate notifications.',
        'fields' => [
            'sms_enabled' => ['label' => 'SMS Notifications', 'type' => 'switch'],
            'email_enabled' => ['label' => 'Email Notifications', 'type' => 'switch'],
            'whatsapp_enabled' => ['label' => 'WhatsApp Notifications', 'type' => 'switch'],
            'notify_appointment' => ['label' => 'Appointment Confirmation', 'type' => 'switch'],
            'notify_payment' => ['label' => 'Payment Receipts', 'type' => 'switch'],
            'notify_prescription' => ['label' => 'New Prescriptions', 'type' => 'switch'],
            'notify_lab_result' => ['label' => 'Lab Results Ready', 'type' => 'switch'],
            'notify_patient_registration' => ['label' => 'Patient Registration', 'type' => 'switch'],
            'notify_low_stock' => ['label' => 'Low Stock Alerts', 'type' => 'switch'],
            'notify_system' => ['label' => 'System Alerts', 'type' => 'switch'],
        ],
    ],

    'printing' => [
        'group' => 'Documents',
        'icon' => 'ti-printer',
        'title' => 'Printing & Documents',
        'subtitle' => 'Defaults for printed documents and reports.',
        'fields' => [
            'paper_size' => ['label' => 'Paper Size', 'type' => 'select', 'col' => 6, 'options' => ['A4' => 'A4 (210 × 297 mm)', 'A5' => 'A5 (148 × 210 mm)', 'Letter' => 'Letter (8.5 × 11 in)', 'Thermal-80' => 'Thermal 80 mm', 'Thermal-58' => 'Thermal 58 mm']],
            'print_show_logo' => ['label' => 'Show Logo on Documents', 'type' => 'switch'],
            'print_show_address' => ['label' => 'Show Clinic Address', 'type' => 'switch'],
            'print_show_doctor_info' => ['label' => 'Show Doctor Information', 'type' => 'switch'],
            'document_footer_text' => ['label' => 'Document Footer Text', 'type' => 'textarea', 'col' => 12, 'rows' => 2, 'placeholder' => 'Thank you for visiting…'],
        ],
    ],

    'security' => [
        'group' => 'Security & Behaviour',
        'icon' => 'ti-shield-lock',
        'title' => 'Security',
        'subtitle' => 'Session, login and auditing rules.',
        'fields' => [
            'session_timeout_minutes' => ['label' => 'Session Timeout (minutes)', 'type' => 'number', 'col' => 6, 'placeholder' => '30'],
            'max_login_attempts' => ['label' => 'Max Login Attempts', 'type' => 'number', 'col' => 6, 'placeholder' => '5'],
            'two_factor_auth' => ['label' => 'Two-Factor Authentication (2FA)', 'type' => 'switch'],
            'auto_logout' => ['label' => 'Auto Logout Inactive Users', 'type' => 'switch'],
            'audit_logging' => ['label' => 'Audit Logging', 'type' => 'switch', 'help' => 'Record who changed what and when.'],
            'login_history' => ['label' => 'Login History Tracking', 'type' => 'switch'],
        ],
    ],

    'behavior' => [
        'group' => 'Security & Behaviour',
        'icon' => 'ti-robot',
        'title' => 'System Behaviour',
        'subtitle' => 'Business rules that control day-to-day workflows.',
        'fields' => [
            'require_patient_phone' => ['label' => 'Require Patient Phone', 'type' => 'switch'],
            'require_patient_address' => ['label' => 'Require Patient Address', 'type' => 'switch'],
            'require_emergency_contact' => ['label' => 'Require Emergency Contact', 'type' => 'switch'],
            'allow_duplicate_patients' => ['label' => 'Allow Duplicate Patients', 'type' => 'switch'],
            'require_doctor_approval' => ['label' => 'Require Doctor Approval', 'type' => 'switch', 'help' => 'Prescriptions need doctor approval before dispensing.'],
            'require_payment_before_service' => ['label' => 'Require Payment Before Service', 'type' => 'switch'],
            'allow_edit_completed_visits' => ['label' => 'Allow Editing Completed Visits', 'type' => 'switch'],
            'require_reason_for_edit' => ['label' => 'Require Reason for Editing Records', 'type' => 'switch'],
            'require_reason_for_cancellation' => ['label' => 'Require Reason for Cancellation', 'type' => 'switch'],
        ],
    ],

    'smtp' => [
        'group' => 'Email',
        'icon' => 'ti-mail',
        'title' => 'Email (SMTP)',
        'subtitle' => 'Outgoing mail server used by the system for OTPs and notifications.',
        'fields' => [
            'smtp_host' => ['label' => 'SMTP Host', 'type' => 'text', 'col' => 6, 'placeholder' => 'smtp.gmail.com'],
            'smtp_port' => ['label' => 'SMTP Port', 'type' => 'number', 'col' => 6, 'placeholder' => '587', 'help' => '465 for SSL, 587 for TLS.'],
            'smtp_user' => ['label' => 'SMTP Username', 'type' => 'text', 'col' => 6, 'placeholder' => 'you@example.com'],
            'smtp_pass' => ['label' => 'SMTP Password', 'type' => 'password', 'col' => 6, 'help' => 'Leave blank to keep the current password.'],
            'smtp_from_email' => ['label' => 'From Email', 'type' => 'email', 'col' => 6, 'placeholder' => 'no-reply@clinic.so'],
            'smtp_from_name' => ['label' => 'From Name', 'type' => 'text', 'col' => 6, 'placeholder' => 'Dabiib Clinic Center'],
        ],
    ],
];

/* Tab layout — which settings sections appear under each tab. */
$settingsTabs = [
    'general'   => ['title' => 'General',             'icon' => 'ti-sliders',     'sections' => ['general', 'clinic']],
    'branding'  => ['title' => 'Branding & Logos',    'icon' => 'ti-palette',     'sections' => ['branding']],
    'prefixes'  => ['title' => 'Prefixes',            'icon' => 'ti-barcode',     'sections' => ['clinical']],
    'finance'   => ['title' => 'Billing & Appts',     'icon' => 'ti-wallet',      'sections' => ['billing', 'appointments']],
    'modules'   => ['title' => 'Modules',             'icon' => 'ti-medicine',    'sections' => ['pharmacy', 'laboratory', 'notifications']],
    'documents' => ['title' => 'Printing & Security', 'icon' => 'ti-printer',     'sections' => ['printing', 'security', 'behavior']],
    'mail'      => ['title' => 'SMTP Mail',           'icon' => 'ti-mail',        'sections' => ['smtp']],
    'database'  => ['title' => 'Database',            'icon' => 'ti-database',    'sections' => []],
    'profile'   => ['title' => 'System Profile',      'icon' => 'ti-id-badge',    'sections' => []],
];

$error = '';
$notice = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        clinic_check_csrf();

        // Direct action: download a full SQL backup.
        if (!empty($_POST['backup_db'])) {
            $dump = clinic_settings_backup_sql($db);
            header('Content-Type: application/octet-stream');
            header('Content-Transfer-Encoding: Binary');
            header('Content-Disposition: attachment; filename="dabiibsystem_backup_' . date('Y-m-d_H-i-s') . '.sql"');
            echo $dump;
            exit;
        }

        $action = (string) ($_POST['action'] ?? '');

        if ($action === 'save') {
            // 1) Process uploaded logo files first.
            $uploaded = [];
            foreach ($settingsConfig as $section) {
                foreach ($section['fields'] as $key => $field) {
                    if (($field['type'] ?? '') !== 'logo') {
                        continue;
                    }
                    $fileKey = 'file_' . $key;
                    if (!empty($_FILES[$fileKey]) && (int) ($_FILES[$fileKey]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                        $uploaded[$key] = clinic_settings_handle_upload($fileKey);
                    }
                }
            }

            // 2) Persist every configured setting.
            $stmt = $db->prepare(
                'INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
            );

            foreach ($settingsConfig as $section) {
                foreach ($section['fields'] as $key => $field) {
                    $type = (string) ($field['type'] ?? 'text');

                    if ($type === 'logo') {
                        if (isset($uploaded[$key])) {
                            $value = $uploaded[$key];
                        } elseif (!empty($_POST['remove_' . $key])) {
                            $value = '';
                        } else {
                            continue; // keep the current value
                        }
                    } elseif ($type === 'password') {
                        $value = trim((string) ($_POST['setting_' . $key] ?? ''));
                        if ($value === '') {
                            continue; // keep the current password
                        }
                    } elseif ($type === 'switch') {
                        $value = isset($_POST['setting_' . $key]) ? '1' : '0';
                    } else {
                        $value = trim((string) ($_POST['setting_' . $key] ?? ''));
                    }

                    $value = ($value === '') ? null : (string) $value;
                    $stmt->bind_param('ss', $key, $value);
                    $stmt->execute();
                }
            }
            $stmt->close();

            header('Location: system_settings.php?saved=1', true, 303);
            exit;
        }

        if ($action === 'test_email') {
            // Persist the SMTP fields exactly as typed, then send a test mail.
            $stmt = $db->prepare(
                'INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
            );
            foreach (($settingsConfig['smtp']['fields'] ?? []) as $key => $field) {
                if (($field['type'] ?? 'text') === 'password') {
                    $val = trim((string) ($_POST['setting_' . $key] ?? ''));
                    if ($val === '') {
                        continue;
                    }
                } else {
                    $val = trim((string) ($_POST['setting_' . $key] ?? ''));
                }
                $val = ($val === '') ? null : (string) $val;
                $stmt->bind_param('ss', $key, $val);
                $stmt->execute();
            }
            $stmt->close();

            require_once __DIR__ . '/../config/mailer.php';
            $to = trim((string) ($_SESSION['user_email'] ?? ''));
            if ($to === '') {
                $error = 'Your account has no email address. Add one in User Settings, then try again.';
            } else {
                $co2 = new Codes();
                $siteName = $co2->siteName() !== '' ? $co2->siteName() : 'Dabiib System';
                $result = clinic_send_mail(
                    $to,
                    (string) ($_SESSION['username'] ?? 'Admin'),
                    $siteName . ' — SMTP Test Message',
                    '<div style="font-family:Arial,sans-serif;padding:24px"><h2>SMTP Test</h2><p>If you are reading this, your SMTP settings are working correctly.</p><p style="color:#888;font-size:12px">Sent by ' . clinic_h($siteName) . '</p></div>',
                    'SMTP Test - if you can read this, your SMTP settings are working correctly.'
                );
                header('Location: system_settings.php?smtp_test=' . ($result['ok'] ? '1' : '0'), true, 303);
                exit;
            }
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

if (isset($_GET['saved'])) {
    $notice = 'Settings saved successfully. All sections are up to date.';
}
if (isset($_GET['smtp_test'])) {
    if ((string) $_GET['smtp_test'] === '1') {
        $notice = 'Test email sent successfully. Check your inbox.';
    } else {
        $error = 'Test email could not be sent. Review the SMTP settings and try again.';
    }
}

$values = [];
$res = $db->query('SELECT setting_key, setting_value FROM system_settings');
while ($row = $res->fetch_assoc()) {
    $values[(string) $row['setting_key']] = (string) $row['setting_value'];
}
$settingsUpdatedAt = '';
$tsRes = $db->query('SELECT MAX(updated_at) AS latest FROM system_settings');
if ($tsRes) {
    $tsRow = $tsRes->fetch_assoc();
    $settingsUpdatedAt = (string) ($tsRow['latest'] ?? '');
    $tsRes->free();
}
$db->close();

$totalFields = 0;
foreach ($settingsConfig as $section) {
    $totalFields += count($section['fields']);
}

function clinic_settings_render_field(string $key, array $field, string $value): void
{
    $type = (string) ($field['type'] ?? 'text');
    $label = (string) ($field['label'] ?? $key);
    $col = (int) ($field['col'] ?? 12);
    $help = (string) ($field['help'] ?? '');
    $name = 'setting_' . $key;
    $id = 'setting_' . $key;
    $value = (string) $value;

    echo '<div class="col-md-' . $col . '">';

    switch ($type) {
        case 'switch':
            echo '<div class="clinic-switch form-check form-switch mt-2">';
            echo '<input class="form-check-input" type="checkbox" role="switch" id="' . clinic_h($id) . '" name="' . clinic_h($name) . '" value="1"' . ($value === '1' ? ' checked' : '') . '>';
            echo '<label class="form-check-label fw-semibold" for="' . clinic_h($id) . '">' . clinic_h($label) . '</label>';
            if ($help !== '') {
                echo '<div class="form-text small mb-0">' . clinic_h($help) . '</div>';
            }
            echo '</div>';
            break;

        case 'select':
            $matched = false;
            foreach (($field['options'] ?? []) as $optValue => $optLabel) {
                if ((string) $optValue === $value) {
                    $matched = true;
                    break;
                }
            }
            echo '<label class="form-label" for="' . clinic_h($id) . '">' . clinic_h($label) . '</label>';
            echo '<select class="form-select no-select2" id="' . clinic_h($id) . '" name="' . clinic_h($name) . '">';
            if (!$matched) {
                echo '<option value="" selected>— None —</option>';
            }
            foreach (($field['options'] ?? []) as $optValue => $optLabel) {
                $selected = (string) $optValue === $value ? ' selected' : '';
                echo '<option value="' . clinic_h($optValue) . '"' . $selected . '>' . clinic_h($optLabel) . '</option>';
            }
            echo '</select>';
            if ($help !== '') {
                echo '<div class="form-text">' . clinic_h($help) . '</div>';
            }
            break;

        case 'textarea':
            echo '<label class="form-label" for="' . clinic_h($id) . '">' . clinic_h($label) . '</label>';
            echo '<textarea class="form-control" id="' . clinic_h($id) . '" name="' . clinic_h($name) . '" rows="' . (int) ($field['rows'] ?? 3) . '" placeholder="' . clinic_h((string) ($field['placeholder'] ?? '')) . '">' . clinic_h($value) . '</textarea>';
            if ($help !== '') {
                echo '<div class="form-text">' . clinic_h($help) . '</div>';
            }
            break;

        case 'password':
            echo '<label class="form-label" for="' . clinic_h($id) . '">' . clinic_h($label) . '</label>';
            echo '<input type="password" class="form-control" id="' . clinic_h($id) . '" name="' . clinic_h($name) . '" placeholder="Leave blank to keep current" autocomplete="new-password">';
            if ($help !== '') {
                echo '<div class="form-text">' . clinic_h($help) . '</div>';
            }
            break;

        case 'logo':
            clinic_settings_render_logo($key, $field, $value);
            break;

        case 'number':
        case 'time':
            echo '<label class="form-label" for="' . clinic_h($id) . '">' . clinic_h($label) . '</label>';
            echo '<input type="' . clinic_h($type) . '" class="form-control" id="' . clinic_h($id) . '" name="' . clinic_h($name) . '" value="' . clinic_h($value) . '" placeholder="' . clinic_h((string) ($field['placeholder'] ?? '')) . '" step="' . ($type === 'time' ? '60' : 'any') . '">';
            if ($help !== '') {
                echo '<div class="form-text">' . clinic_h($help) . '</div>';
            }
            break;

        default:
            $inputType = (string) ($field['input_type'] ?? $type);
            echo '<label class="form-label" for="' . clinic_h($id) . '">' . clinic_h($label) . '</label>';
            echo '<input type="' . clinic_h($inputType) . '" class="form-control" id="' . clinic_h($id) . '" name="' . clinic_h($name) . '" value="' . clinic_h($value) . '" placeholder="' . clinic_h((string) ($field['placeholder'] ?? '')) . '">';
            if ($help !== '') {
                echo '<div class="form-text">' . clinic_h($help) . '</div>';
            }
            break;
    }

    echo '</div>';
}

function clinic_settings_render_logo(string $key, array $field, string $value): void
{
    $label = (string) ($field['label'] ?? $key);
    $help = (string) ($field['help'] ?? '');
    $accept = (string) ($field['accept'] ?? 'image/*');
    $src = clinic_settings_logo_url($value);
    $previewId = 'preview_' . $key;
    $wrapId = 'imgwrap_' . $key;

    echo '<div class="branding-item">';

    echo '<div class="branding-label">' . clinic_h($label) . '</div>';
    if ($help !== '') {
        echo '<div class="branding-desc">' . clinic_h($help) . '</div>';
    }

    // Clickable preview box (opens the file picker).
    echo '<div class="branding-preview-container" id="' . clinic_h($previewId) . '" role="button" tabindex="0" title="Click to upload or change the logo" aria-label="Upload ' . clinic_h($label) . '" data-file-input="file_' . clinic_h($key) . '">';
    echo '<span class="clinic-logo-img" id="' . clinic_h($wrapId) . '">';
    if ($src !== '') {
        echo '<img src="' . clinic_h($src) . '" alt="' . clinic_h($label) . '">';
    } else {
        echo '<i class="ti ti-photo"></i>';
    }
    echo '</span>';
    echo '<label class="clinic-logo-remove-badge' . ($src === '' ? ' d-none' : '') . '" id="remove_badge_' . clinic_h($key) . '" title="Remove logo">';
    echo '<input type="checkbox" class="clinic-logo-remove" id="remove_' . clinic_h($key) . '" name="remove_' . clinic_h($key) . '" value="1">';
    echo '<i class="ti ti-trash"></i>';
    echo '</label>';
    echo '</div>';

    echo '<input type="file" class="d-none clinic-logo-input" id="file_' . clinic_h($key) . '" name="file_' . clinic_h($key) . '" accept="' . clinic_h($accept) . '" data-preview="#' . clinic_h($wrapId) . '">';

    echo '<div class="branding-item-actions">';
    echo '<label for="file_' . clinic_h($key) . '" class="btn-upload-label"><i class="ti ti-upload"></i> Upload</label>';
    if ($src !== '') {
        echo '<button type="button" class="btn-view-label clinic-logo-view" id="viewbtn_' . clinic_h($key) . '" data-view-src="' . clinic_h($src) . '" title="View"><i class="ti ti-eye"></i></button>';
    }
    echo '</div>';
    echo '<div class="clinic-logo-status form-text small text-success" id="status_' . clinic_h($key) . '"></div>';
    echo '</div>';
}

function clinic_settings_render_nav(array $config): void
{
    $groups = [];
    foreach ($config as $key => $section) {
        $groupName = (string) ($section['group'] ?? 'Other');
        $groups[$groupName][] = [
            'key' => $key,
            'icon' => (string) ($section['icon'] ?? 'ti-settings'),
            'title' => (string) ($section['title'] ?? $key),
        ];
    }

    $first = true;
    foreach ($groups as $groupName => $items) {
        echo '<div class="settings-nav-group">';
        echo '<div class="settings-nav-label">' . clinic_h($groupName) . '</div>';
        foreach ($items as $item) {
            $linkClass = 'settings-nav-link' . ($first ? ' active' : '');
            $first = false;
            echo '<a class="' . $linkClass . '" href="#section-' . clinic_h($item['key']) . '" data-section="' . clinic_h($item['key']) . '">';
            echo '<i class="ti ' . clinic_h($item['icon']) . '"></i><span>' . clinic_h($item['title']) . '</span>';
            echo '</a>';
        }
        echo '</div>';
    }
}

function clinic_settings_render_section_card(string $sectionKey, array $section, array $values): void
{
    echo '<section class="settings-section mb-4" id="section-' . clinic_h($sectionKey) . '">';
    echo '<div class="card clinic-card">';
    echo '<div class="card-header d-flex align-items-center gap-3 border-0 bg-transparent pt-3">';
    echo '<span class="settings-section-icon"><i class="ti ' . clinic_h($section['icon']) . '"></i></span>';
    echo '<div><h5 class="mb-0 fw-bold">' . clinic_h($section['title']) . '</h5><small class="text-muted">' . clinic_h($section['subtitle']) . '</small></div>';
    echo '</div>';
    echo '<div class="card-body pt-2">';
    echo '<div class="row g-4">';
    foreach ($section['fields'] as $key => $field) {
        clinic_settings_render_field($key, $field, $values[$key] ?? '');
    }
    if ($sectionKey === 'smtp') {
        echo '<div class="col-12">';
        echo '<hr class="my-1">';
        echo '<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 pt-2">';
        echo '<div class="small text-muted">Sends a test message to your account email (<strong>' . clinic_h((string) ($_SESSION['user_email'] ?? 'not set')) . '</strong>) using the SMTP values above. Save first, then test.</div>';
        echo '<button type="submit" name="action" value="test_email" class="btn btn-outline-primary btn-sm"><i class="ti ti-send me-1"></i>Send Test Email</button>';
        echo '</div>';
        echo '</div>';
    }
    echo '</div>';
    echo '</div>';
    echo '</div>';
    echo '</section>';
}

$GLOBALS['asset_base'] = '../';
$GLOBALS['app_base'] = '../';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php require __DIR__ . '/../includes/head.php'; ?>
    <title>System Settings - Clinic</title>
    <style>
        .settings-layout { display: grid; grid-template-columns: 280px minmax(0, 1fr); gap: 1.5rem; align-items: start; }
        .settings-nav { position: sticky; top: 84px; max-height: calc(100vh - 104px); overflow-y: auto; padding: 1rem; border: 1px solid rgba(0, 0, 0, .07); border-radius: .9rem; box-shadow: 0 8px 24px rgba(15, 23, 42, .04); }
        .settings-nav-head { display: flex; align-items: center; gap: .6rem; padding: .35rem .5rem 1rem; border-bottom: 1px solid rgba(0, 0, 0, .06); }
        .settings-nav-head i { font-size: 1.35rem; color: var(--primary, #2E37A4); }
        .settings-nav-label { font-size: .68rem; letter-spacing: .08em; text-transform: uppercase; color: #9da4b0; font-weight: 700; margin: 1rem 0 .35rem; padding: 0 .5rem; }
        .settings-nav-label:first-of-type { margin-top: .25rem; }
        .settings-nav-link { position: relative; display: flex; align-items: center; gap: .6rem; padding: .52rem .6rem; border-radius: .6rem; color: var(--gray-600, #6C7688); font-size: .86rem; font-weight: 600; text-decoration: none; transition: all .15s ease; }
        .settings-nav-link i { font-size: 1.05rem; }
        .settings-nav-link:hover { background: var(--light, #F5F6F8); color: var(--primary, #2E37A4); }
        .settings-nav-link.active { background: rgba(46, 55, 164, .08); color: var(--primary, #2E37A4); font-weight: 700; }
        .settings-nav-link.active::before { content: ""; position: absolute; left: 0; top: 25%; bottom: 25%; width: 3px; border-radius: 3px; background: var(--primary, #2E37A4); }
        .settings-nav-link.active i { color: var(--primary, #2E37A4); }
        .settings-section { scroll-margin-top: 96px; }
        .settings-section-icon { width: 2.6rem; height: 2.6rem; border-radius: .8rem; display: inline-flex; align-items: center; justify-content: center; background: linear-gradient(135deg, var(--primary, #2E37A4), var(--secondary, #00D3C7)); color: #fff; font-size: 1.25rem; box-shadow: 0 6px 14px rgba(46, 55, 164, .22); flex-shrink: 0; }
        .branding-item { background: var(--bs-body-bg, #fff); border: 1px solid var(--bs-border-color, #cbd5e1); border-radius: .8rem; padding: 1rem 1rem .6rem; text-align: center; display: flex; flex-direction: column; align-items: center; height: 100%; position: relative; transition: all .2s ease; }
        .branding-item:hover { border-color: var(--primary, #2E37A4); box-shadow: 0 6px 16px rgba(15, 23, 42, .06); }
        .branding-label { font-size: .82rem; font-weight: 700; color: var(--bs-emphasis-color, #0f172a); margin-bottom: 2px; }
        .branding-desc { font-size: .7rem; color: var(--bs-secondary-color, #64748b); margin-bottom: 10px; }
        .branding-preview-container { width: 100%; height: 96px; display: flex; align-items: center; justify-content: center; background: var(--bs-tertiary-bg, #f1f5f9); border-radius: .6rem; margin-bottom: 12px; padding: 8px; overflow: hidden; cursor: pointer; position: relative; border: 1px dashed var(--bs-border-color, #cbd5e1); transition: border-color .15s ease, box-shadow .15s ease; }
        .branding-preview-container:hover, .branding-preview-container:focus-visible { border-color: var(--primary, #2E37A4); box-shadow: 0 0 0 3px rgba(46, 55, 164, .15); outline: none; }
        .clinic-logo-img { width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; }
        .clinic-logo-img img { max-width: 100%; max-height: 100%; object-fit: contain; padding: .25rem; }
        .clinic-logo-img i { font-size: 1.8rem; color: var(--bs-secondary-color, #cbd5e1); }
        .branding-item-actions { display: flex; align-items: center; gap: 6px; margin-top: 2px; }
        .btn-upload-label { background: var(--bs-tertiary-bg, #f1f5f9); color: var(--bs-emphasis-color, #475569); border: 1px solid var(--bs-border-color, #e2e8f0); border-radius: .5rem; padding: .38rem .85rem; font-size: .76rem; font-weight: 600; cursor: pointer; transition: all .15s ease; display: inline-flex; align-items: center; gap: 5px; }
        .btn-upload-label:hover { background: var(--primary, #2E37A4); color: #fff; border-color: var(--primary, #2E37A4); }
        .btn-view-label { background: var(--bs-tertiary-bg, #f1f5f9); color: var(--bs-emphasis-color, #475569); border: 1px solid var(--bs-border-color, #e2e8f0); border-radius: .5rem; width: 31px; height: 31px; display: inline-flex; align-items: center; justify-content: center; cursor: pointer; transition: all .15s ease; font-size: .85rem; }
        .btn-view-label:hover { background: var(--primary, #2E37A4); color: #fff; border-color: var(--primary, #2E37A4); }
        .clinic-logo-status { text-align: center; min-height: 1em; }
        .clinic-logo-remove-badge { position: absolute; top: -6px; right: -6px; width: 22px; height: 22px; border-radius: 50%; background: var(--bs-body-bg, #fff); border: 1px solid #dc3545; color: #dc3545; display: flex; align-items: center; justify-content: center; cursor: pointer; z-index: 3; transition: all .15s ease; }
        .clinic-logo-remove-badge:hover { background: #dc3545; color: #fff; box-shadow: 0 4px 10px rgba(220, 53, 69, .35); }
        .clinic-logo-remove-badge .clinic-logo-remove { position: absolute; opacity: 0; width: 0; height: 0; pointer-events: none; }
        .clinic-logo-remove-badge i { font-size: .72rem; }
        .settings-sections .form-control,
        .settings-sections .form-select,
        .settings-sections .clinic-logo-input {
            border: 1.5px solid var(--bs-border-color, #6c757d); /* border-secondary */
            border-radius: .55rem;
        }
        .settings-sections .form-control:focus,
        .settings-sections .form-select:focus,
        .settings-sections .clinic-logo-input:focus {
            border-color: var(--primary, #2E37A4);
            box-shadow: 0 0 0 .18rem rgba(46, 55, 164, .12);
        }
        .form-switch .form-check-input { width: 2.6em; height: 1.35em; cursor: pointer; }
        .form-switch .form-check-input:checked { background-color: var(--primary, #2E37A4); border-color: var(--primary, #2E37A4); }
        .settings-savebar { position: sticky; bottom: 16px; z-index: 1020; padding: .75rem 1.25rem; box-shadow: 0 14px 38px rgba(15, 23, 42, .18); border-radius: .9rem; margin-top: 1.5rem; }
        .settings-dirty-dot { width: 10px; height: 10px; border-radius: 50%; background: #27AE60; display: inline-block; flex-shrink: 0; transition: background .2s ease; }
        .settings-dirty-dot.dirty { background: #E2B93B; }
        .settings-cover { position: relative; display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap; background: linear-gradient(135deg, var(--primary, #2E37A4) 0%, var(--secondary, #00D3C7) 100%); border-radius: 1rem; padding: 1.75rem 2rem; color: #fff; box-shadow: 0 12px 30px rgba(46, 55, 164, .2); overflow: hidden; }
        .settings-cover::after { content: ""; position: absolute; right: -60px; top: -60px; width: 220px; height: 220px; border-radius: 50%; background: rgba(255, 255, 255, .06); }
        .settings-cover-icon { width: 3.2rem; height: 3.2rem; border-radius: .9rem; background: rgba(255, 255, 255, .14); color: #fff; display: inline-flex; align-items: center; justify-content: center; font-size: 1.6rem; flex-shrink: 0; }
        .settings-cover-title { font-weight: 800; font-size: 1.5rem; letter-spacing: -.3px; color: #fff; }
        .settings-cover p { opacity: .85; max-width: 700px; font-size: .88rem; color: #fff; margin-bottom: 0; }
        .settings-tabbar { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: .75rem; padding: .5rem .75rem; margin-bottom: 1.5rem; background: var(--bs-body-bg, #fff); border: 1px solid var(--bs-border-color, rgba(0, 0, 0, .07)); border-radius: .9rem; box-shadow: 0 8px 24px rgba(15, 23, 42, .04); }
        .settings-tabs { border-bottom: 0; gap: .25rem; flex-wrap: nowrap; overflow-x: auto; scrollbar-width: thin; -webkit-overflow-scrolling: touch; padding-bottom: 2px; }
        .settings-tabs::-webkit-scrollbar { height: 5px; }
        .settings-tabs::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 3px; }
        .settings-tabs::-webkit-scrollbar-track { background: transparent; }
        .settings-tabs .nav-link { border: 0; color: var(--bs-secondary-color, #6C7688); font-weight: 600; font-size: .8rem; padding: .5rem .7rem; border-radius: .55rem; transition: all .15s ease; white-space: nowrap; }
        .settings-tabs .nav-link i { font-size: 1rem; }
        .settings-tabs .nav-link:hover { color: var(--primary, #2E37A4); background: var(--light, #F5F6F8); }
        .settings-tabs .nav-link.active { background: rgba(46, 55, 164, .1); color: var(--primary, #2E37A4); font-weight: 700; }
        .settings-tab-content { animation: settingsFade .25s ease; }
        @keyframes settingsFade { from { opacity: 0; transform: translateY(4px); } to { opacity: 1; transform: none; } }
        .settings-info-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(230px, 1fr)); gap: 16px; }
        .settings-info-item { background: var(--bs-tertiary-bg, #f8fafc); border: 1px solid var(--bs-border-color, #eef1f6); border-radius: .8rem; padding: 1rem 1.1rem; display: flex; align-items: center; gap: .9rem; transition: all .2s ease; }
        .settings-info-item:hover { transform: translateY(-2px); box-shadow: 0 8px 16px rgba(15, 23, 42, .05); }
        .settings-info-icon { width: 42px; height: 42px; border-radius: .65rem; background: rgba(46, 55, 164, .08); color: var(--primary, #2E37A4); display: inline-flex; align-items: center; justify-content: center; font-size: 1.2rem; flex-shrink: 0; }
        .settings-info-label { font-size: .7rem; color: var(--bs-secondary-color, #8a94a6); text-transform: uppercase; font-weight: 700; letter-spacing: .04em; margin-bottom: 2px; }
        .settings-info-value { font-size: .92rem; color: var(--bs-body-color, #0f172a); font-weight: 600; }
        /* Theme-aware: the settings page follows the system theme via CSS variables. */
        .card.clinic-card { border-color: var(--bs-border-color, rgba(0, 0, 0, .07)); }
        @media (max-width: 991.98px) {
            .settings-layout { grid-template-columns: 1fr; }
            .settings-nav { position: static; max-height: none; }
        }
    </style>
</head>
<body>
<div class="main-wrapper">
    <?php require __DIR__ . '/../includes/header.php'; ?>
    <?php require __DIR__ . '/../includes/sidebar.php'; ?>

    <div class="page-wrapper">
        <div class="content pb-0">

            <?php if ($notice !== ''): ?>
                <div class="settings-alert alert alert-success alert-dismissible fade show border border-success" role="alert">
                    <i class="ti ti-circle-check me-1"></i><?php echo clinic_h($notice); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            <?php if ($error !== ''): ?>
                <div class="settings-alert alert alert-danger alert-dismissible fade show border border-danger" role="alert">
                    <i class="ti ti-alert-triangle me-1"></i><?php echo clinic_h($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <form method="post" action="system_settings.php" autocomplete="off" enctype="multipart/form-data" id="settingsForm">
                <input type="hidden" name="csrf_token" value="<?php echo clinic_h(clinic_csrf_token()); ?>">
                <input type="hidden" name="action" value="save">

                <!-- Cover banner -->
                <div class="settings-cover mb-4">
                    <div class="d-flex align-items-center gap-3">
                        <span class="settings-cover-icon"><i class="ti ti-settings-cog"></i></span>
                        <div>
                            <h2 class="settings-cover-title mb-1">System Settings</h2>
                            <p class="mb-0">Customize your clinic name, contact details, currency, branding images and module behaviour — saved centrally for the whole system.</p>
                        </div>
                    </div>
                    <span class="badge bg-white text-primary"><?php echo (int) $totalFields; ?> settings</span>
                </div>

                <!-- Tab bar + Save -->
                <div class="settings-tabbar">
                    <ul class="nav nav-tabs settings-tabs" id="settingsTabs" role="tablist">
                        <?php foreach ($settingsTabs as $tabKey => $tab): ?>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link<?php echo $tabKey === 'general' ? ' active' : ''; ?>" id="tab-<?php echo clinic_h($tabKey); ?>-btn" data-bs-toggle="tab" data-bs-target="#tab-<?php echo clinic_h($tabKey); ?>" type="button" role="tab" aria-selected="<?php echo $tabKey === 'general' ? 'true' : 'false'; ?>">
                                    <i class="ti <?php echo clinic_h($tab['icon']); ?> me-1"></i><?php echo clinic_h($tab['title']); ?>
                                </button>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <div class="d-flex align-items-center gap-2 flex-shrink-0">
                        <span class="small text-muted d-none d-md-inline" id="settingsSaveState"><i class="ti ti-circle-check text-success me-1"></i>All changes saved</span>
                        <button type="submit" class="btn btn-primary px-3" id="btnSaveSettings"><i class="ti ti-device-floppy me-1"></i>Save All Changes</button>
                    </div>
                </div>

                <!-- Tab panes -->
                <div class="tab-content settings-tab-content" id="settingsTabsContent">
                    <?php foreach ($settingsTabs as $tabKey => $tab): ?>
                        <div class="tab-pane fade<?php echo $tabKey === 'general' ? ' show active' : ''; ?>" id="tab-<?php echo clinic_h($tabKey); ?>" role="tabpanel" aria-labelledby="tab-<?php echo clinic_h($tabKey); ?>-btn">

                            <?php if ($tabKey === 'database'): ?>
                                <div class="row justify-content-center">
                                    <div class="col-md-6">
                                        <div class="card clinic-card text-center p-4">
                                            <div class="fs-1 text-primary mb-3"><i class="ti ti-cloud-download"></i></div>
                                            <h5 class="fw-bold mb-2">Backup Database</h5>
                                            <p class="text-muted small mb-4">Download every table (structure + records) as a <code>.sql</code> backup file.</p>
                                            <button type="submit" name="backup_db" value="1" class="btn btn-primary px-4"><i class="ti ti-download me-1"></i>Download SQL Backup</button>
                                        </div>
                                    </div>
                                </div>
                            <?php elseif ($tabKey === 'profile'): ?>
                                <div class="card clinic-card mb-4">
                                    <div class="card-header d-flex align-items-center gap-3 border-0 bg-transparent pt-3">
                                        <span class="settings-section-icon"><i class="ti ti-id-badge"></i></span>
                                        <div>
                                            <h5 class="mb-0 fw-bold">System Profile</h5>
                                            <small class="text-muted">A quick overview of the clinic identity stored in these settings.</small>
                                        </div>
                                    </div>
                                    <div class="card-body pt-2">
                                        <div class="settings-info-grid">
                                            <div class="settings-info-item"><span class="settings-info-icon"><i class="ti ti-building"></i></span><div><div class="settings-info-label">Clinic Name</div><div class="settings-info-value"><?php echo clinic_h(($values['clinic_name'] ?? '') !== '' ? $values['clinic_name'] : ($values['site_name'] ?? '—')); ?></div></div></div>
                                            <div class="settings-info-item"><span class="settings-info-icon"><i class="ti ti-currency-dollar"></i></span><div><div class="settings-info-label">Currency</div><div class="settings-info-value"><?php echo clinic_h(($values['currency_symbol'] ?? '') !== '' ? $values['currency_symbol'] : ($values['default_currency'] ?? '—')); ?></div></div></div>
                                            <div class="settings-info-item"><span class="settings-info-icon"><i class="ti ti-mail"></i></span><div><div class="settings-info-label">Contact Email</div><div class="settings-info-value"><?php echo clinic_h(($values['clinic_email'] ?? '') !== '' ? $values['clinic_email'] : '—'); ?></div></div></div>
                                            <div class="settings-info-item"><span class="settings-info-icon"><i class="ti ti-phone"></i></span><div><div class="settings-info-label">Hotline</div><div class="settings-info-value"><?php echo clinic_h(($values['clinic_phone'] ?? '') !== '' ? $values['clinic_phone'] : '—'); ?></div></div></div>
                                            <div class="settings-info-item"><span class="settings-info-icon"><i class="ti ti-map-pin"></i></span><div><div class="settings-info-label">Location</div><div class="settings-info-value"><?php $loc = trim(($values['clinic_city'] ?? '') . ' ' . ($values['clinic_country'] ?? '')); echo clinic_h($loc !== '' ? $loc : '—'); ?></div></div></div>
                                            <div class="settings-info-item"><span class="settings-info-icon"><i class="ti ti-calendar"></i></span><div><div class="settings-info-label">Last Settings Update</div><div class="settings-info-value"><?php echo clinic_h($settingsUpdatedAt !== '' ? date('d M Y, h:i A', strtotime($settingsUpdatedAt)) : '—'); ?></div></div></div>
                                        </div>
                                    </div>
                                </div>
                            <?php else: ?>
                                <?php foreach (($tab['sections'] ?? []) as $sectionKey): ?>
                                    <?php clinic_settings_render_section_card($sectionKey, $settingsConfig[$sectionKey], $values); ?>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="modal fade" id="logoViewModal" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title"><i class="ti ti-eye me-1"></i>Logo Preview</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body text-center">
                                <img id="logoViewImg" src="" alt="Logo preview" style="max-width:100%; max-height:70vh; object-fit:contain;">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal fade" id="logoCheckModal" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title"><i class="ti ti-search me-1"></i>Check Image</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <div class="text-center bg-white rounded border p-3 mb-3">
                                    <img id="logoCheckImg" src="" alt="Selected image" style="max-width:100%; max-height:42vh; object-fit:contain;">
                                </div>
                                <div id="logoCheckInfo" class="small text-muted"></div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-light" id="logoCheckCancel"><i class="ti ti-x me-1"></i>Cancel</button>
                                <button type="button" class="btn btn-primary" id="logoCheckConfirm"><i class="ti ti-circle-check me-1"></i>Use this image</button>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
        <?php require __DIR__ . '/../includes/footer.php'; ?>
    </div>
</div>
<?php require __DIR__ . '/../includes/plugins.php'; ?>
<script>

document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('settingsForm');
    var saveState = document.getElementById('settingsSaveState');
    var btnSave = document.getElementById('btnSaveSettings');
    var dirty = false;

    function markDirty() {
        if (dirty) { return; }
        dirty = true;
        if (saveState) { saveState.innerHTML = '<i class="ti ti-alert-triangle text-warning me-1"></i>Unsaved changes'; }
    }
    function markClean() {
        dirty = false;
        if (saveState) { saveState.innerHTML = '<i class="ti ti-circle-check text-success me-1"></i>All changes saved'; }
    }

    if (form) {
        form.addEventListener('input', markDirty);
        form.addEventListener('change', markDirty);
        form.addEventListener('submit', function (e) {
            var btn = document.activeElement;
            var btnName = btn ? btn.getAttribute('name') : '';
            // Direct actions (test email / backup download) submit without confirmation.
            if (btnName === 'action' || btnName === 'backup_db') { return; }
            if (!dirty) { e.preventDefault(); return; }
            e.preventDefault();
            if (window.Swal) {
                Swal.fire({
                    title: 'Save changes?',
                    text: 'Do you want to save the system configurations?',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#2E37A4',
                    cancelButtonColor: '#6C757D',
                    confirmButtonText: 'Yes, save it!',
                    cancelButtonText: 'Cancel'
                }).then(function (result) {
                    if (result.isConfirmed) {
                        markClean();
                        form.submit();
                    }
                });
            } else {
                markClean();
                form.submit();
            }
        });
    }

    markClean();

    window.addEventListener('beforeunload', function (e) {
        if (!dirty) { return undefined; }
        e.preventDefault();
        e.returnValue = '';
        return '';
    });

    // File selected → show a "check" modal before the image is used.
    var pendingCheck = null;
    document.querySelectorAll('.clinic-logo-input').forEach(function (input) {
        input.addEventListener('change', function () {
            var key = this.name.replace('file_', '');
            var wrapTarget = document.querySelector(this.getAttribute('data-preview'));
            var removeBox = document.getElementById('remove_' + key);
            var statusEl = document.getElementById('status_' + key);
            if (!this.files || !this.files[0]) { return; }

            var file = this.files[0];
            var sizeKb = (file.size / 1024).toFixed(1);
            var url = URL.createObjectURL(file);

            if (statusEl) {
                statusEl.textContent = 'Checking image…';
                statusEl.classList.remove('text-danger');
                statusEl.classList.add('text-success');
            }

            function openCheck(dims, extra, note) {
                pendingCheck = {
                    input: input, url: url, wrapTarget: wrapTarget,
                    removeBox: removeBox, statusEl: statusEl,
                    badge: document.getElementById('remove_badge_' + key),
                    sizeKb: sizeKb, dims: dims, extra: extra
                };
                var modalImg = document.getElementById('logoCheckImg');
                var infoEl = document.getElementById('logoCheckInfo');
                if (modalImg) { modalImg.src = url; }
                if (infoEl) {
                    infoEl.innerHTML = '<div class="fw-semibold text-dark mb-1">' + (file.name || 'Selected image') + '</div>'
                        + sizeKb + ' KB · ' + dims + (extra !== '' ? ' · ' + extra : '')
                        + (note !== '' ? '<div class="mt-1 text-muted">' + note + '</div>' : '')
                        + '<div class="mt-1">Click <strong>"Use this image"</strong> to keep it, or <strong>Cancel</strong> to pick another.</div>';
                }
                var modalEl = document.getElementById('logoCheckModal');
                if (modalEl && window.bootstrap) {
                    bootstrap.Modal.getOrCreateInstance(modalEl).show();
                }
            }

            var img = new Image();
            img.onload = function () {
                var dims = this.naturalWidth + '×' + this.naturalHeight;
                var extra = '';
                try {
                    var c = document.createElement('canvas');
                    c.width = this.naturalWidth;
                    c.height = this.naturalHeight;
                    var ctx = c.getContext('2d');
                    ctx.drawImage(this, 0, 0);
                    var pts = [
                        [0, 0], [this.naturalWidth - 1, 0], [0, this.naturalHeight - 1], [this.naturalWidth - 1, this.naturalHeight - 1],
                        [Math.floor(this.naturalWidth / 2), 0], [Math.floor(this.naturalWidth / 2), this.naturalHeight - 1],
                        [0, Math.floor(this.naturalHeight / 2)], [this.naturalWidth - 1, Math.floor(this.naturalHeight / 2)],
                        [Math.floor(this.naturalWidth / 2), Math.floor(this.naturalHeight / 2)]
                    ];
                    var hasAlpha = pts.some(function (p) {
                        try { return ctx.getImageData(p[0], p[1], 1, 1).data[3] === 0; }
                        catch (e) { return false; }
                    });
                    if (hasAlpha) { extra = 'Transparent (no bg) ✓'; }
                } catch (err) { extra = ''; }
                openCheck(dims, extra, '');
            };
            img.onerror = function () {
                openCheck('—', '', 'Preview is not available for this format.');
            };
            img.src = url;
        });
    });

    // Logo remove buttons
    document.querySelectorAll('.clinic-logo-remove').forEach(function (box) {
        box.addEventListener('change', function () {
            var key = this.id.replace('remove_', '');
            var target = document.getElementById('imgwrap_' + key);
            var fileInput = document.getElementById('file_' + key);
            var viewBtn = document.getElementById('viewbtn_' + key);
            var badge = document.getElementById('remove_badge_' + key);
            if (this.checked) {
                if (target) { target.innerHTML = '<i class="ti ti-photo"></i>'; }
                if (fileInput) { fileInput.value = ''; }
                if (viewBtn) { viewBtn.classList.add('d-none'); }
                if (badge) { badge.classList.add('d-none'); }
            }
        });
    });

    // Don't open the file picker when clicking the remove badge
    document.querySelectorAll('.clinic-logo-remove-badge').forEach(function (badge) {
        badge.addEventListener('click', function (e) { e.stopPropagation(); });
    });

    // Click the preview box to open the file picker
    document.querySelectorAll('[data-file-input]').forEach(function (frame) {
        frame.addEventListener('click', function () {
            var input = document.getElementById(this.getAttribute('data-file-input'));
            if (input) { input.click(); }
        });
        frame.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                this.click();
            }
        });
    });

    // View button — open the full-size logo in a modal viewer
    document.querySelectorAll('.clinic-logo-view').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            var img = document.getElementById('logoViewImg');
            if (img) { img.src = this.getAttribute('data-view-src'); }
            var modalEl = document.getElementById('logoViewModal');
            if (modalEl && window.bootstrap) {
                bootstrap.Modal.getOrCreateInstance(modalEl).show();
            }
        });
    });

    // Check modal — confirm or cancel the selected image
    var confirmBtn = document.getElementById('logoCheckConfirm');
    if (confirmBtn) {
        confirmBtn.addEventListener('click', function () {
            if (!pendingCheck) { return; }
            if (pendingCheck.wrapTarget) {
                pendingCheck.wrapTarget.innerHTML = '<img src="' + pendingCheck.url + '" alt="preview">';
            }
            if (pendingCheck.removeBox) { pendingCheck.removeBox.disabled = false; }
            if (pendingCheck.badge) { pendingCheck.badge.classList.remove('d-none'); }
            if (pendingCheck.statusEl) {
                pendingCheck.statusEl.textContent = '✓ ' + pendingCheck.sizeKb + ' KB · ' + pendingCheck.dims + (pendingCheck.extra !== '' ? ' · ' + pendingCheck.extra : '');
            }
            var modalEl = document.getElementById('logoCheckModal');
            if (modalEl && window.bootstrap) { bootstrap.Modal.getOrCreateInstance(modalEl).hide(); }
            pendingCheck = null;
        });
    }
    var cancelBtn = document.getElementById('logoCheckCancel');
    if (cancelBtn) {
        cancelBtn.addEventListener('click', function () {
            if (pendingCheck) {
                if (pendingCheck.input) { pendingCheck.input.value = ''; }
                URL.revokeObjectURL(pendingCheck.url);
                pendingCheck = null;
            }
            var modalEl = document.getElementById('logoCheckModal');
            if (modalEl && window.bootstrap) { bootstrap.Modal.getOrCreateInstance(modalEl).hide(); }
        });
    }
    var checkModalEl = document.getElementById('logoCheckModal');
    if (checkModalEl) {
        checkModalEl.addEventListener('hidden.bs.modal', function () {
            if (pendingCheck) {
                if (pendingCheck.input) { pendingCheck.input.value = ''; }
                URL.revokeObjectURL(pendingCheck.url);
                pendingCheck = null;
            }
        });
    }

    // Tabs are handled by Bootstrap (data-bs-toggle="tab").

    // Auto-dismiss alerts
    document.querySelectorAll('.settings-alert').forEach(function (alertEl) {
        setTimeout(function () {
            if (window.bootstrap && bootstrap.Alert) {
                bootstrap.Alert.getOrCreateInstance(alertEl).close();
            }
        }, 6000);
    });
});
</body>
</html>
