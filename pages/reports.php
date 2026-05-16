<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/report_bootstrap.php';

/** Legacy deep links (?view=) → standalone report pages */
$legacyView = trim((string) ($_GET['view'] ?? ''));
if ($legacyView !== '') {
    $normalized = clinic_reports_normalize_view($legacyView);
    $map = [
        'debt' => 'report_patient_debt.php',
        'doctor_commissions' => 'report_doctor_commissions.php',
        'revenue_category' => 'report_revenue_category.php',
        'cash_flow' => 'report_cash_flow.php',
        'demographics' => 'report_demographics.php',
        'appointment_attendance' => 'report_appointment_attendance.php',
        'diseases_lab' => 'report_lab_trends.php',
        'expiring_medicines' => 'report_expiring_medicines.php',
        'top_medicines' => 'report_top_medicines.php',
        'low_stock' => 'report_low_stock.php',
        'user_activity' => 'report_user_activity.php',
        'sms_delivery' => 'report_sms_delivery.php',
    ];

    unset($_GET['view']);
    $suffix = '';
    $q = $_GET;
    $q = array_filter($q, static fn ($v) => $v !== '' && $v !== null);
    if ($q !== []) {
        $suffix = '?' . http_build_query($q);
    }

    header('Location: ' . (($map[$normalized] ?? 'report_patient_debt.php') . $suffix), true, 302);
    exit;
}

clinic_page_start('Reports hub', 'Browse by category — each section lists its reports on its own page.');
?>
<link rel="stylesheet" href="<?php echo clinic_h($GLOBALS['asset_base'] ?? '../'); ?>assets/css/report-pages.css?v=2">

<div class="report-hub">
    <div class="report-hub-intro">
        <div class="report-hub-title">Reports</div>
        <p class="report-hub-muted mb-0">Choose a category. Individual reports stay on focused pages with filters, CSV download, and print.</p>
    </div>

    <div class="report-hub-category-grid">
        <?php foreach (clinic_reports_catalog() as $section): ?>
            <?php
            $slug = (string) ($section['slug'] ?? '');
            if ($slug === '') {
                continue;
            }
            $n = count($section['items']);
            ?>
            <a class="report-hub-card report-hub-card-category" href="<?php echo clinic_h('reports_' . $slug . '.php'); ?>">
                <span class="report-hub-card-icon"><i class="ti <?php echo clinic_h((string) ($section['hub_icon'] ?? 'ti-chart-bar')); ?>"></i></span>
                <h3><?php echo clinic_h((string) ($section['group'] ?? '')); ?></h3>
                <p><?php echo clinic_h((string) ($section['hub_blurb'] ?? '')); ?></p>
                <span class="report-hub-card-count"><?php echo $n === 1 ? '1 report' : $n . ' reports'; ?></span>
                <span class="report-hub-card-open">Open category<i class="ti ti-arrow-right"></i></span>
            </a>
        <?php endforeach; ?>
    </div>
</div>

<?php clinic_page_end(); ?>
