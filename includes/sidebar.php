<?php
require_once __DIR__ . '/../config/procedures.php';

$assetBase = $GLOBALS['asset_base'] ?? '';
$appBase = $GLOBALS['app_base'] ?? '';
$scriptName = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
$currentBasename = basename($scriptName);
$requestUriPath = trim((string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH), '/');
$requestSegments = array_values(array_filter(explode('/', strtolower($requestUriPath))));

if (!function_exists('clinic_sidebar_normalize_href')) {
    function clinic_sidebar_normalize_href(string $href): string
    {
        $href = trim($href);
        if ($href === '' || preg_match('#^https?://#i', $href)) {
            return $href;
        }

        $parts = parse_url($href);
        $path = ltrim((string) ($parts['path'] ?? $href), '/');
        $query = isset($parts['query']) && $parts['query'] !== '' ? '?' . $parts['query'] : '';

        $known = [
            'privileges.php' => 'pages/user_privileges.php',
        ];
        if (isset($known[$path])) {
            return $known[$path] . $query;
        }

        if (file_exists(__DIR__ . '/../' . $path)) {
            return $path . $query;
        }

        $pagePath = 'pages/' . basename($path);
        if (file_exists(__DIR__ . '/../' . $pagePath)) {
            return $pagePath . $query;
        }

        return $path . $query;
    }
}

if (!function_exists('clinic_sidebar_href_exists')) {
    function clinic_sidebar_href_exists(string $href): bool
    {
        if ($href === '' || preg_match('#^https?://#i', $href)) {
            return $href !== '';
        }

        $path = ltrim((string) (parse_url($href, PHP_URL_PATH) ?? $href), '/');
        return file_exists(__DIR__ . '/../' . $path);
    }
}

if (!function_exists('clinic_sidebar_link_active')) {
    function clinic_sidebar_link_active(string $href, string $currentBasename, array $requestSegments): bool
    {
        $parts = parse_url($href);
        $path = ltrim((string) ($parts['path'] ?? ''), '/');
        if ($path === '') {
            return false;
        }

        $hrefBase = basename($path);
        if ($hrefBase !== $currentBasename) {
            return false;
        }

        $hrefSegments = array_values(array_filter(explode('/', strtolower($path))));
        $depth = count($hrefSegments);
        if ($depth >= 2) {
            $suffix = array_slice($requestSegments, -$depth);
            if ($suffix !== $hrefSegments) {
                return false;
            }
        }

        $q = isset($parts['query']) ? $parts['query'] : '';
        if ($q === '') {
            return true;
        }

        parse_str($q, $want);
        foreach ($want as $key => $value) {
            if (!isset($_GET[$key]) || (string) $_GET[$key] !== (string) $value) {
                return false;
            }
        }

        return true;
    }
}

if (!function_exists('clinic_sidebar_fallback_sections')) {
    /**
     * @return array<string, list<array{name:string, icon:string, items:list<array{label:string, href:string}>}>>
     */
    function clinic_sidebar_fallback_sections(): array
    {
        return [
            'Main Menu' => [
                [
                    'name' => 'Dashboard',
                    'icon' => 'ti-layout-dashboard',
                    'items' => [
                        ['Overview', 'index.php'],
                    ],
                ],
            ],
            'Clinic' => [
                [
                    'name' => 'Reception',
                    'icon' => 'ti-user-heart',
                    'items' => [
                        ['Patients', 'pages/patients.php'],
                        ['Appointments', 'pages/appointments.php'],
                        ['Visits', 'pages/visits.php'],
                        ['Doctors', 'pages/doctors.php'],
                    ],
                ],
                [
                    'name' => 'Nursing',
                    'icon' => 'ti-stethoscope',
                    'items' => [
                        ['Nursing Records', 'pages/nursing_records.php'],
                        ['Nursing Services', 'pages/nursing_services.php'],
                    ],
                ],
                [
                    'name' => 'Laboratory',
                    'icon' => 'ti-microscope',
                    'items' => [
                        ['Lab Results', 'pages/lab_results.php'],
                        ['Lab Tests', 'pages/lab_tests.php'],
                    ],
                ],
                [
                    'name' => 'Pharmacy',
                    'icon' => 'ti-medicine',
                    'items' => [
                        ['Pharmacy POS', 'pages/pharmacy_sales.php'],
                        ['Prescriptions', 'pages/prescriptions.php'],
                        ['Medicines', 'pages/medicines.php'],
                    ],
                ],
            ],
            'Finance & Accounts' => [
                [
                    'name' => 'Finance',
                    'icon' => 'ti-wallet',
                    'items' => [
                        ['Payments', 'pages/payments.php'],
                        ['Accounts', 'pages/accounts.php'],
                        ['Account Transfers', 'pages/account_transfers.php'],
                    ],
                ],
            ],
            'Administration' => [
                [
                    'name' => 'System',
                    'icon' => 'ti-settings',
                    'items' => [
                        ['Users', 'pages/users.php'],
                        ['Roles', 'pages/roles.php'],
                        ['User Privileges', 'pages/user_privileges.php'],
                        ['SMS Logs', 'pages/sms_logs.php'],
                        ['Menus', 'menues.php?tab=menu'],
                        ['Submenus', 'menues.php?tab=sub&all=1'],
                        ['Staff Directory', 'pages/staff.php'],
                    ],
                ],
            ],
            'Reports' => [
                [
                    'name' => 'Analytics',
                    'icon' => 'ti-chart-bar',
                    'items' => [
                        ['Reports hub', 'pages/reports.php'],
                        ['Finance reports', 'pages/reports_finance.php'],
                        ['Operations & staff reports', 'pages/reports_operations.php'],
                        ['Clinical reports', 'pages/reports_clinical.php'],
                        ['Pharmacy reports', 'pages/reports_pharmacy.php'],
                        ['Administration reports', 'pages/reports_administration.php'],
                        ['Patient debt', 'pages/report_patient_debt.php'],
                        ['Accounts receivable', 'pages/report_accounts_receivable.php'],
                        ['Guarantor liability', 'pages/report_guarantor_liability.php'],
                        ['Payment methods', 'pages/report_payment_methods.php'],
                        ['Account transfers audit', 'pages/report_account_transfers_audit.php'],
                        ['Doctor commissions', 'pages/report_doctor_commissions.php'],
                        ['Doctor workload', 'pages/report_doctor_workload.php'],
                        ['Revenue by category', 'pages/report_revenue_category.php'],
                        ['Cash flow', 'pages/report_cash_flow.php'],
                        ['Nursing utilization', 'pages/report_nursing_utilization.php'],
                        ['Lab volume & revenue', 'pages/report_lab_volume_revenue.php'],
                        ['Demographics', 'pages/report_demographics.php'],
                        ['Appointments', 'pages/report_appointment_attendance.php'],
                        ['Lab trends', 'pages/report_lab_trends.php'],
                        ['Visit frequency', 'pages/report_visit_frequency.php'],
                        ['Most prescribed', 'pages/report_most_prescribed.php'],
                        ['Pharmacy leakage', 'pages/report_prescription_leakage.php'],
                        ['Expiring medicines', 'pages/report_expiring_medicines.php'],
                        ['Top medicines', 'pages/report_top_medicines.php'],
                        ['Low stock', 'pages/report_low_stock.php'],
                        ['Users & login', 'pages/report_user_activity.php'],
                        ['SMS log', 'pages/report_sms_delivery.php'],
                    ],
                ],
            ],
        ];
    }
}

/**
 * @param list<array{label:string, href:string}> $itemsIn
 *
 * @return list<array{label:string, href:string}>
 */
$filterItems = static function (array $itemsIn): array {
    $out = [];
    $seenHref = [];

    foreach ($itemsIn as $row) {
        $label = trim((string) ($row['label'] ?? ''));
        $href = clinic_sidebar_normalize_href(trim((string) ($row['href'] ?? '')));
        if ($label === '' || !clinic_sidebar_href_exists($href)) {
            continue;
        }
        if (isset($seenHref[$href])) {
            continue;
        }
        $seenHref[$href] = true;
        $out[] = ['label' => $label, 'href' => $href];
    }

    return $out;
};

$sidebarSections = [];
try {
    $menus = array_values(array_filter(
        clinic_sp_rows('sp_menues_list'),
        static fn ($menu) => ($menu['status'] ?? 'active') === 'active'
    ));
    usort($menus, static function ($a, $b) {
        $ao = (int) ($a['sort_order'] ?? 0);
        $bo = (int) ($b['sort_order'] ?? 0);
        if ($ao !== $bo) {
            return $ao <=> $bo;
        }
        return ((int) ($a['menu_id'] ?? 0)) <=> ((int) ($b['menu_id'] ?? 0));
    });

    $submenus = array_values(array_filter(
        clinic_sp_rows('sp_submenues_list'),
        static fn ($submenu) => ($submenu['status'] ?? 'active') === 'active'
    ));
    usort($submenus, static function ($a, $b) {
        $ao = (int) ($a['sort_order'] ?? 0);
        $bo = (int) ($b['sort_order'] ?? 0);
        if ($ao !== $bo) {
            return $ao <=> $bo;
        }
        return ((int) ($a['submenu_id'] ?? 0)) <=> ((int) ($b['submenu_id'] ?? 0));
    });

    $menusById = [];
    foreach ($menus as $menu) {
        $menusById[(int) ($menu['menu_id'] ?? 0)] = $menu;
    }

    $subsByMenu = [];
    foreach ($submenus as $submenu) {
        $menuId = (int) ($submenu['menu_id'] ?? 0);
        if ($menuId < 1 || !isset($menusById[$menuId])) {
            continue;
        }
        $subsByMenu[$menuId][] = $submenu;
    }

    $groupOrder = [];
    $groupBuckets = [];

    foreach ($menus as $menu) {
        $mid = (int) ($menu['menu_id'] ?? 0);
        if ($mid < 1 || !isset($subsByMenu[$mid])) {
            continue;
        }

        $rawItems = [];
        $seenHref = [];
        foreach ($subsByMenu[$mid] as $submenu) {
            $label = trim((string) ($submenu['submenu_name'] ?? ''));
            $href = clinic_sidebar_normalize_href((string) ($submenu['menu_url'] ?? ''));
            if ($label === '' || !clinic_sidebar_href_exists($href)) {
                continue;
            }
            if (isset($seenHref[$href])) {
                continue;
            }
            $seenHref[$href] = true;
            $rawItems[] = ['label' => $label, 'href' => $href];
        }

        if ($rawItems === []) {
            continue;
        }

        $groupTitle = trim((string) ($menu['menu_group'] ?? ''));
        if ($groupTitle === '') {
            $groupTitle = 'Main Menu';
        }

        $iconRaw = trim((string) ($menu['icon'] ?? ''));
        if ($iconRaw === '') {
            $iconRaw = 'ti-circle-dot';
        }
        if (!str_contains($iconRaw, 'ti-')) {
            $iconRaw = 'ti-' . ltrim($iconRaw, '-');
        }

        $menuName = trim((string) ($menu['menu_name'] ?? ''));
        if ($menuName === '') {
            $menuName = 'Menu';
        }

        if (!isset($groupBuckets[$groupTitle])) {
            $groupBuckets[$groupTitle] = [];
            $groupOrder[] = $groupTitle;
        }

        $groupBuckets[$groupTitle][] = [
            'name' => $menuName,
            'icon' => $iconRaw,
            'items' => $rawItems,
        ];
    }

    foreach ($groupOrder as $title) {
        $sidebarSections[$title] = $groupBuckets[$title];
    }
} catch (Throwable $e) {
    $sidebarSections = [];
}

if ($sidebarSections === []) {
    $mapped = clinic_sidebar_fallback_sections();
    foreach ($mapped as $title => $menus) {
        $clean = [];
        foreach ($menus as $block) {
            $items = $filterItems($block['items']);
            if ($items === []) {
                continue;
            }
            $clean[] = [
                'name' => $block['name'],
                'icon' => $block['icon'],
                'items' => $items,
            ];
        }
        if ($clean !== []) {
            $sidebarSections[$title] = $clean;
        }
    }
}
?>
<div class="sidebar" id="sidebar">
    <div class="sidebar-logo">
        <div>
            <a href="<?php echo htmlspecialchars($appBase, ENT_QUOTES, 'UTF-8'); ?>index.php" class="logo logo-normal">
                <img src="<?php echo htmlspecialchars($assetBase, ENT_QUOTES, 'UTF-8'); ?>assets/img/logo.svg" alt="Logo">
            </a>
            <a href="<?php echo htmlspecialchars($appBase, ENT_QUOTES, 'UTF-8'); ?>index.php" class="logo-small">
                <img src="<?php echo htmlspecialchars($assetBase, ENT_QUOTES, 'UTF-8'); ?>assets/img/logo-small.svg" alt="Logo">
            </a>
            <a href="<?php echo htmlspecialchars($appBase, ENT_QUOTES, 'UTF-8'); ?>index.php" class="dark-logo">
                <img src="<?php echo htmlspecialchars($assetBase, ENT_QUOTES, 'UTF-8'); ?>assets/img/logo-white.svg" alt="Logo">
            </a>
        </div>
        <button class="sidenav-toggle-btn btn border-0 p-0 active" id="toggle_btn">
            <i class="ti ti-arrow-left text-body"></i>
        </button>
        <button class="sidebar-close">
            <i class="ti ti-x align-middle"></i>
        </button>
    </div>

    <div class="sidebar-inner" data-simplebar="">
        <div id="sidebar-menu" class="sidebar-menu clinic-dynamic-sidebar">
            

            <style>
                #sidebar-menu.clinic-dynamic-sidebar > ul > li > ul > li.submenu > a.clinic-submenu-toggle {
                    align-items: center;
                    gap: 0.5rem;
                }
                #sidebar-menu.clinic-dynamic-sidebar > ul > li > ul > li > a.sidebar-single-link {
                    align-items: center;
                    gap: 0.5rem;
                }
                #sidebar-menu.clinic-dynamic-sidebar > ul > li > ul > li.submenu > a.clinic-submenu-toggle.clinic-menu-card {
                    border-radius: var(--border-radius-lg, 0.5rem);
                    border: 1px solid var(--menu-item-border, #e9edf4);
                    background: var(--white, #fff);
                    padding: 0.6rem 0.85rem !important;
                    margin-bottom: 0.25rem;
                    font-weight: 500;
                }
                #sidebar-menu.clinic-dynamic-sidebar > ul > li > ul > li.submenu > a.clinic-submenu-toggle.clinic-menu-card i {
                    color: var(--primary, #0d6efd);
                }
                #sidebar-menu.clinic-dynamic-sidebar > ul > li > ul > li.submenu > a.clinic-submenu-toggle.clinic-menu-card > span:first-of-type {
                    color: var(--primary, #0d6efd);
                }
                #sidebar-menu.clinic-dynamic-sidebar > ul > li > ul > li > a.sidebar-single-link.clinic-menu-card {
                    border-radius: var(--border-radius-lg, 0.5rem);
                    border: 1px solid var(--menu-item-border, #e9edf4);
                    background: var(--white, #fff);
                    padding: 0.6rem 0.85rem !important;
                    margin-bottom: 0.25rem;
                    font-weight: 500;
                }
                #sidebar-menu.clinic-dynamic-sidebar > ul > li > ul > li > a.sidebar-single-link.clinic-menu-card i {
                    color: var(--primary, #0d6efd);
                }
                [data-bs-theme="dark"] #sidebar-menu.clinic-dynamic-sidebar > ul > li > ul > li.submenu > a.clinic-submenu-toggle.clinic-menu-card,
                [data-bs-theme="dark"] #sidebar-menu.clinic-dynamic-sidebar > ul > li > ul > li > a.sidebar-single-link.clinic-menu-card {
                    background: var(--sidebar-bg, transparent);
                    border-color: rgba(255, 255, 255, 0.08);
                }
                [dir="rtl"] #sidebar-menu.clinic-dynamic-sidebar > ul > li > ul > li.submenu > a.clinic-submenu-toggle,
                [dir="rtl"] #sidebar-menu.clinic-dynamic-sidebar > ul > li > ul > li > a.sidebar-single-link {
                    flex-direction: row-reverse;
                }
            </style>

            <ul>
                <?php foreach ($sidebarSections as $groupTitle => $menus): ?>
                    <li class="menu-title"><span><?php echo htmlspecialchars($groupTitle, ENT_QUOTES, 'UTF-8'); ?></span></li>
                    <li>
                        <ul>
                            <?php foreach ($menus as $menuBlock): ?>
                                <?php
                                $mName = (string) $menuBlock['name'];
                                $mIcon = (string) $menuBlock['icon'];
                                $items = $menuBlock['items'];
                                $itemCount = count($items);
                                ?>
                                <?php if ($itemCount === 1):
                                    $only = $items[0];
                                    $singleIsCurrent = clinic_sidebar_link_active($only['href'], $currentBasename, $requestSegments);
                                    $onlyActive = $singleIsCurrent ? ' active' : '';
                                    $singleCard = $singleIsCurrent ? ' clinic-menu-card' : '';
                                    ?>
                                    <li>
                                        <a class="d-flex sidebar-single-link<?php echo $onlyActive, $singleCard; ?>" href="<?php echo htmlspecialchars($appBase . $only['href'], ENT_QUOTES, 'UTF-8'); ?>">
                                            <i class="ti <?php echo htmlspecialchars($mIcon, ENT_QUOTES, 'UTF-8'); ?>"></i>
                                            <span><?php echo htmlspecialchars($mName, ENT_QUOTES, 'UTF-8'); ?></span>
                                        </a>
                                    </li>
                                <?php else:
                                    $anyActive = false;
                                    foreach ($items as $it) {
                                        if (clinic_sidebar_link_active($it['href'], $currentBasename, $requestSegments)) {
                                            $anyActive = true;
                                            break;
                                        }
                                    }
                                    $toggleClasses = 'd-flex clinic-submenu-toggle';
                                    $toggleClasses .= $anyActive ? ' active subdrop clinic-menu-card' : '';
                                    ?>
                                    <li class="submenu">
                                        <a href="javascript:void(0);" class="<?php echo $toggleClasses; ?>">
                                            <i class="ti <?php echo htmlspecialchars($mIcon, ENT_QUOTES, 'UTF-8'); ?>"></i>
                                            <span><?php echo htmlspecialchars($mName, ENT_QUOTES, 'UTF-8'); ?></span>
                                            <span class="menu-arrow ms-auto flex-shrink-0"></span>
                                        </a>
                                        <ul <?php echo $anyActive ? ' style="display:block;"' : ''; ?>>
                                            <?php foreach ($items as $it):
                                                $childActive = clinic_sidebar_link_active($it['href'], $currentBasename, $requestSegments) ? ' active' : '';
                                                ?>
                                                <li>
                                                    <a class="<?php echo trim($childActive); ?>" href="<?php echo htmlspecialchars($appBase . $it['href'], ENT_QUOTES, 'UTF-8'); ?>">
                                                        <?php echo htmlspecialchars($it['label'], ENT_QUOTES, 'UTF-8'); ?>
                                                    </a>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </li>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </ul>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
</div>
