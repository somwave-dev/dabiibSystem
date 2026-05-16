<?php
declare(strict_types=1);

require_once __DIR__ . '/advanced_components.php';
require_once __DIR__ . '/reports_hub.php';

/**
 * Catalog for the reports hub and cross-links (file is basename under pages/).
 *
 * @return list<array{slug:string, group:string, hub_icon:string, hub_blurb:string, items:list<array{file:string, title:string, icon:string, blurb:string}>}>
 */
function clinic_reports_catalog(): array
{
    return [
        [
            'slug' => 'finance',
            'group' => 'Finance',
            'hub_icon' => 'ti-building-bank',
            'hub_blurb' => 'Revenue, cash, AR, guarantors, payments, and internal transfers.',
            'items' => [
                ['file' => 'report_doctor_commissions.php', 'title' => 'Doctor Commissions', 'icon' => 'ti-currency-dollar', 'blurb' => 'Attributed consultation revenue from visits.'],
                ['file' => 'report_revenue_category.php', 'title' => 'Revenue by Category', 'icon' => 'ti-chart-pie', 'blurb' => 'Operational revenue indicators across departments.'],
                ['file' => 'report_cash_flow.php', 'title' => 'Cash Flow & Accounts', 'icon' => 'ti-building-bank', 'blurb' => 'Collections, transfers, and current balances.'],
                ['file' => 'report_patient_debt.php', 'title' => 'Patient Debt', 'icon' => 'ti-report-money', 'blurb' => 'Outstanding patient balances and collection actions.'],
                ['file' => 'report_accounts_receivable.php', 'title' => 'Accounts Receivable', 'icon' => 'ti-file-invoice', 'blurb' => 'Patients with balance due and credit limits.'],
                ['file' => 'report_guarantor_liability.php', 'title' => 'Guarantor Liability', 'icon' => 'ti-user-shield', 'blurb' => 'Outstanding debt grouped by guarantor.'],
                ['file' => 'report_payment_methods.php', 'title' => 'Payment Methods', 'icon' => 'ti-credit-card', 'blurb' => 'Collections by EVC, eDahab, cash, or bank.'],
                ['file' => 'report_account_transfers_audit.php', 'title' => 'Account Transfers', 'icon' => 'ti-arrows-exchange', 'blurb' => 'Audit trail of internal account movements.'],
            ],
        ],
        [
            'slug' => 'operations',
            'group' => 'Operations & staff',
            'hub_icon' => 'ti-stethoscope',
            'hub_blurb' => 'Doctor visit volume, nursing services, and lab throughput with revenue.',
            'items' => [
                ['file' => 'report_doctor_workload.php', 'title' => 'Doctor Workload', 'icon' => 'ti-stethoscope', 'blurb' => 'Visit volume per doctor by day, week, or month.'],
                ['file' => 'report_nursing_utilization.php', 'title' => 'Nursing Utilization', 'icon' => 'ti-nurse', 'blurb' => 'Service mix and revenue from nursing records.'],
                ['file' => 'report_lab_volume_revenue.php', 'title' => 'Lab Volume & Revenue', 'icon' => 'ti-flask-2', 'blurb' => 'Per-test counts and catalogue revenue.'],
            ],
        ],
        [
            'slug' => 'clinical',
            'group' => 'Clinical & patient',
            'hub_icon' => 'ti-heart-plus',
            'hub_blurb' => 'Demographics, appointments, lab demand, visits, and prescribing patterns.',
            'items' => [
                ['file' => 'report_demographics.php', 'title' => 'Patient Demographics', 'icon' => 'ti-users-group', 'blurb' => 'Population mix by sex, age band, and type.'],
                ['file' => 'report_appointment_attendance.php', 'title' => 'Appointment Attendance', 'icon' => 'ti-calendar-check', 'blurb' => 'Statuses, throughput, and schedule detail.'],
                ['file' => 'report_lab_trends.php', 'title' => 'Lab Tests & Trends', 'icon' => 'ti-microscope', 'blurb' => 'Completed test volume — demand proxy for workups.'],
                ['file' => 'report_visit_frequency.php', 'title' => 'Visit Frequency', 'icon' => 'ti-repeat', 'blurb' => 'Patients with the most visits in a period.'],
                ['file' => 'report_most_prescribed.php', 'title' => 'Most Prescribed', 'icon' => 'ti-pill', 'blurb' => 'Prescribing frequency by medicine (clinical).'],
            ],
        ],
        [
            'slug' => 'pharmacy',
            'group' => 'Pharmacy & inventory',
            'hub_icon' => 'ti-pill',
            'hub_blurb' => 'Stock levels, expiry, POS sales, and prescription vs pharmacy alignment.',
            'items' => [
                ['file' => 'report_expiring_medicines.php', 'title' => 'Expiring Medicines', 'icon' => 'ti-hourglass-low', 'blurb' => 'Stock nearing expiry within your horizon.'],
                ['file' => 'report_top_medicines.php', 'title' => 'Top Selling Medicines', 'icon' => 'ti-shopping-cart', 'blurb' => 'POS volume and revenue by SKU.'],
                ['file' => 'report_low_stock.php', 'title' => 'Low Stock Alert', 'icon' => 'ti-packages', 'blurb' => 'Items below your quantity threshold.'],
                ['file' => 'report_prescription_leakage.php', 'title' => 'Pharmacy Leakage', 'icon' => 'ti-droplet-half-2', 'blurb' => 'Prescriptions without matching in-house sales.'],
            ],
        ],
        [
            'slug' => 'administration',
            'group' => 'Administration',
            'hub_icon' => 'ti-settings',
            'hub_blurb' => 'User access and messaging logs for compliance and support.',
            'items' => [
                ['file' => 'report_user_activity.php', 'title' => 'Users & Sessions', 'icon' => 'ti-user-search', 'blurb' => 'Staff accounts with last recorded login.'],
                ['file' => 'report_sms_delivery.php', 'title' => 'SMS Log', 'icon' => 'ti-message-dots', 'blurb' => 'Message types and outbound history.'],
            ],
        ],
    ];
}

/**
 * @return array{slug:string, group:string, hub_icon:string, hub_blurb:string, items:list<array{file:string, title:string, icon:string, blurb:string}>}|null
 */
function clinic_reports_catalog_section_by_slug(string $slug): ?array
{
    foreach (clinic_reports_catalog() as $section) {
        if (($section['slug'] ?? '') === $slug) {
            return $section;
        }
    }

    return null;
}

/**
 * Parent breadcrumb for a report basename (report_*.php), linking to its category landing page.
 *
 * @return array{href:string, label:string}|null
 */
function clinic_reports_parent_crumb_for_report(string $reportBasename): ?array
{
    foreach (clinic_reports_catalog() as $section) {
        foreach ($section['items'] as $item) {
            if ($item['file'] === $reportBasename) {
                $slug = (string) ($section['slug'] ?? '');
                if ($slug === '') {
                    return null;
                }

                return [
                    'href' => 'reports_' . $slug . '.php',
                    'label' => (string) ($section['group'] ?? ''),
                ];
            }
        }
    }

    return null;
}

/**
 * Renders one category landing page (grid of reports in that category).
 */
function clinic_reports_category_page(string $slug): void
{
    $section = clinic_reports_catalog_section_by_slug($slug);
    if ($section === null) {
        header('Location: reports.php', true, 302);
        exit;
    }

    $group = (string) ($section['group'] ?? 'Reports');
    $count = count($section['items']);
    $lead = $count === 1 ? '1 report in this category.' : $count . ' reports in this category.';

    clinic_page_start($group . ' reports | Reports', (string) ($section['hub_blurb'] ?? ''));
    ?>
    <link rel="stylesheet" href="<?php echo clinic_h($GLOBALS['asset_base'] ?? '../'); ?>assets/css/report-pages.css?v=2">
    <nav class="report-breadcrumb-wrap" aria-label="breadcrumb">
        <ol class="breadcrumb report-breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="reports.php">Reports hub</a></li>
            <li class="breadcrumb-item active" aria-current="page"><?php echo clinic_h($group); ?></li>
        </ol>
    </nav>

    <div class="report-hub report-hub--category">
        <div class="report-hub-intro">
            <div class="report-hub-title"><?php echo clinic_h($group); ?></div>
            <p class="report-hub-muted mb-0"><?php echo clinic_h($lead); ?> Open any card for filters, CSV export, and print.</p>
        </div>
        <div class="report-hub-grid">
            <?php foreach ($section['items'] as $item): ?>
                <a class="report-hub-card" href="<?php echo clinic_h($item['file']); ?>">
                    <span class="report-hub-card-icon"><i class="ti <?php echo clinic_h($item['icon']); ?>"></i></span>
                    <h3><?php echo clinic_h($item['title']); ?></h3>
                    <p><?php echo clinic_h($item['blurb']); ?></p>
                    <span class="report-hub-card-open">Open report<i class="ti ti-arrow-right"></i></span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <?php
    clinic_page_end();
}

/**
 * Opens page shell: title in tab/hero/breadcrumb and optional muted subtitle line.
 *
 * @param list<string>|null $headExtra optional raw HTML fragments after css link (rare).
 */
function clinic_reports_page_shell_start(string $title, string $subtitle = '', ?array $headExtra = null, ?array $breadcrumbParentOverride = null): void
{
    $GLOBALS['_clinic_report_self_file'] = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));

    clinic_page_start($title . ' | Reports', $subtitle);
    ?>
    <link rel="stylesheet" href="<?php echo clinic_h($GLOBALS['asset_base'] ?? '../'); ?>assets/css/report-pages.css?v=2">
    <?php
    if ($headExtra !== null) {
        echo implode("\n", $headExtra);
    }
    $parent = $breadcrumbParentOverride ?? clinic_reports_parent_crumb_for_report($GLOBALS['_clinic_report_self_file']);
    ?>
    <nav class="report-breadcrumb-wrap" aria-label="breadcrumb">
        <ol class="breadcrumb report-breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="reports.php">Reports hub</a></li>
            <?php if ($parent !== null && ($parent['href'] ?? '') !== '' && ($parent['label'] ?? '') !== ''): ?>
                <li class="breadcrumb-item"><a href="<?php echo clinic_h($parent['href']); ?>"><?php echo clinic_h($parent['label']); ?></a></li>
            <?php endif; ?>
            <li class="breadcrumb-item active" aria-current="page"><?php echo clinic_h($title); ?></li>
        </ol>
    </nav>
    <?php
}

/**
 * @param array<string,mixed> $queryParams merged into CSV link (overrides $_GET)
 */
function clinic_report_action_bar(array $queryParams = []): void
{
    $qs = $_GET;
    unset($qs['export']);
    foreach ($queryParams as $k => $v) {
        $qs[$k] = $v;
    }
    $qs = array_filter($qs, static fn ($v) => $v !== '' && $v !== null);
    $csvQs = array_merge($qs, ['export' => 'csv']);
    $csvHref = htmlspecialchars(basename((string) ($_SERVER['SCRIPT_NAME'] ?? '')), ENT_QUOTES, 'UTF-8') . '?' . http_build_query($csvQs);

    $selfBase = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $catCrumb = clinic_reports_parent_crumb_for_report($selfBase);
    ?>
    <div class="report-actions-bar mb-4">
        <div class="d-flex flex-wrap align-items-center gap-2">
            <a class="btn btn-outline-secondary btn-sm" href="reports.php"><i class="ti ti-layout-grid-add me-1"></i>Reports hub</a>
            <?php if ($catCrumb !== null && ($catCrumb['href'] ?? '') !== ''): ?>
                <a class="btn btn-outline-secondary btn-sm" href="<?php echo htmlspecialchars($catCrumb['href'], ENT_QUOTES, 'UTF-8'); ?>"><i class="ti ti-folder me-1"></i><?php echo htmlspecialchars((string) ($catCrumb['label'] ?? 'Category'), ENT_QUOTES, 'UTF-8'); ?></a>
            <?php endif; ?>
        </div>
        <div class="report-actions-push">
            <a class="btn btn-primary btn-sm" href="<?php echo $csvHref; ?>"><i class="ti ti-file-spreadsheet me-1"></i>Download CSV</a>
            <button class="btn btn-light border btn-sm" type="button" onclick="window.print()"><i class="ti ti-printer me-1"></i>Print</button>
        </div>
    </div>
    <?php
}

/**
 * Standard date-range filter form.
 *
 * @param array<string,scalar> $preserve hidden fields besides date_from/date_to
 */
function clinic_report_date_filter_form(array $preserve = []): void
{
    $self = htmlspecialchars(basename((string) ($_SERVER['SCRIPT_NAME'] ?? '')), ENT_QUOTES, 'UTF-8');
    ?>
    <div class="card report-filter-sheet mb-4">
        <div class="card-body">
            <form class="row g-3 align-items-end" method="get" action="<?php echo $self; ?>">
                <?php foreach ($preserve as $name => $val): ?>
                    <input type="hidden" name="<?php echo clinic_h((string) $name); ?>" value="<?php echo clinic_h((string) $val); ?>">
                <?php endforeach; ?>
                <div class="col-sm-6 col-md-4 col-lg-3">
                    <label class="form-label small fw-semibold text-muted">From</label>
                    <input class="form-control" type="date" name="date_from" value="<?php echo clinic_h((string) ($_GET['date_from'] ?? '')); ?>">
                </div>
                <div class="col-sm-6 col-md-4 col-lg-3">
                    <label class="form-label small fw-semibold text-muted">To</label>
                    <input class="form-control" type="date" name="date_to" value="<?php echo clinic_h((string) ($_GET['date_to'] ?? '')); ?>">
                </div>
                <div class="col-auto d-flex gap-2">
                    <button class="btn btn-dark" type="submit"><i class="ti ti-filter me-1"></i>Apply</button>
                    <a class="btn btn-outline-secondary" href="<?php echo $self; ?>">Reset range</a>
                </div>
            </form>
            <p class="small text-muted mt-3 mb-0">Leave dates empty to include all records.</p>
        </div>
    </div>
    <?php
}
