<?php
/**
 * Sidebar — Preadmin template, privilege-aware menu.
 * Only shows submenus the current user can_view (admin sees everything).
 */
require_once __DIR__ . '/../config/procedures.php';

global $conn; // make the mysqli connection visible even when included inside a function scope

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

/* Current user + admin check */
$currentUserNo = (int) ($_SESSION['user_no'] ?? $_SESSION['user_id'] ?? 0);
$isAdmin = (int) ($_SESSION['role_id'] ?? 0) === 1;

/* Sidebar logos (uploaded in System Settings → Branding & Logos) */
$sidebarLogo = '';      // primary logo (light theme)
$sidebarLogoDark = '';  // light version shown in dark mode
try {
    require_once __DIR__ . '/../config/codes.php';
    $sideCo = new Codes();
    // Wide/wordmark logo shown on the expanded LIGHT sidebar.
    $sidebarLogo = (string) $sideCo->setting('logo_dark');
    if ($sidebarLogo === '') {
        $sidebarLogo = (string) $sideCo->setting('site_logo');
    }
    // Light/white version shown when the sidebar is in DARK mode.
    $sidebarLogoDark = (string) $sideCo->setting('logo_light');
} catch (Throwable $e) {
    $sidebarLogo = '';
    $sidebarLogoDark = '';
}
foreach (['sidebarLogo', 'sidebarLogoDark'] as $logoVar) {
    if ($$logoVar !== '') {
        if (!preg_match('#^[a-z][a-z0-9+.-]*://#i', $$logoVar) && !str_starts_with($$logoVar, '/')) {
            $$logoVar = $assetBase . $$logoVar;
        }
    }
}

/* Privilege set: which submenu_ids can this user view? */
$allowedSubIds = [];
if (!$isAdmin && $currentUserNo > 0) {
    $stmt = $conn->prepare('SELECT submenu_id FROM user_privileges WHERE User_ID = ? AND can_view = 1');
    if ($stmt) {
        $stmt->bind_param('i', $currentUserNo);
        $stmt->execute();
        $pRes = $stmt->get_result();
        while ($pRow = $pRes->fetch_assoc()) {
            $allowedSubIds[(int) $pRow['submenu_id']] = true;
        }
        $pRes->free();
        $stmt->close();
    }
}

/* Load active menus + submenus */
$menus = [];
$res = $conn->query("SELECT menu_id, menu_name, icon FROM menues WHERE deleted = 0 AND status = 'active' ORDER BY sort_order ASC, menu_id ASC");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $menus[] = $row;
    }
    $res->free();
}

$subsByMenu = [];
$res = $conn->query("SELECT submenu_id, menu_id, submenu_name, menu_icon, menu_url FROM submenues WHERE deleted = 0 AND status = 'active' ORDER BY sort_order ASC, submenu_id ASC");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $subsByMenu[(int) $row['menu_id']][] = $row;
    }
    $res->free();
}

// A non-admin user only sees menus for submenus they can_view. Users with no
// granted privileges yet get an empty menu until an admin grants access.
$showMenus = $isAdmin;
if (!$showMenus) {
    foreach ($subsByMenu as $groupItems) {
        foreach ($groupItems as $s2) {
            if (isset($allowedSubIds[(int) ($s2['submenu_id'] ?? 0)])) {
                $showMenus = true;
                break 2;
            }
        }
    }
}
?>
<div class="sidebar" id="sidebar">
    <div class="sidebar-logo">
        <div>
            <a href="<?php echo htmlspecialchars($appBase, ENT_QUOTES, 'UTF-8'); ?>index.php" class="logo logo-normal">
                <?php if ($sidebarLogo !== ''): ?>
                    <img src="<?php echo htmlspecialchars($sidebarLogo, ENT_QUOTES, 'UTF-8'); ?>" alt="logo">
                <?php else: ?>
                    <img src="<?php echo htmlspecialchars($assetBase, ENT_QUOTES, 'UTF-8'); ?>assets/img/logo.svg" alt="logo">
                <?php endif; ?>
            </a>
            <a href="<?php echo htmlspecialchars($appBase, ENT_QUOTES, 'UTF-8'); ?>index.php" class="logo-small">
                <img src="<?php echo htmlspecialchars($assetBase, ENT_QUOTES, 'UTF-8'); ?>assets/img/logo-small.svg" alt="small logo">
            </a>
            <a href="<?php echo htmlspecialchars($appBase, ENT_QUOTES, 'UTF-8'); ?>index.php" class="dark-logo">
                <?php if ($sidebarLogoDark !== ''): ?>
                    <img src="<?php echo htmlspecialchars($sidebarLogoDark, ENT_QUOTES, 'UTF-8'); ?>" alt="dark logo">
                <?php else: ?>
                    <img src="<?php echo htmlspecialchars($assetBase, ENT_QUOTES, 'UTF-8'); ?>assets/img/logo-white.svg" alt="dark logo">
                <?php endif; ?>
            </a>
        </div>
        <button class="sidenav-toggle-btn btn border-0 p-0 active" id="toggle_btn"><i class="ti ti-arrow-left text-body"></i></button>
        <button class="sidebar-close"><i class="ti ti-x align-middle"></i></button>
    </div>
    <div class="sidebar-inner" data-simplebar="">
        <div id="sidebar-menu" class="sidebar-menu">
            <?php if (!$showMenus): ?>
            <div class="text-center text-muted small px-3 py-4">
                <i class="ti ti-lock d-block fs-4 mb-2"></i>
                No access yet.<br>Contact the administrator to be granted menu access.
            </div>
            <?php else: ?>
            <ul>
                <li class="menu-title"><span>Main Menu</span></li>
                <li>
                    <ul>
                        <?php foreach ($menus as $menu):
                            $mid = (int) $menu['menu_id'];
                            $items = [];
                            if (isset($subsByMenu[$mid])) {
                                foreach ($subsByMenu[$mid] as $sub) {
                                    $subId = (int) $sub['submenu_id'];
                                    if (!$isAdmin && empty($allowedSubIds[$subId])) {
                                        continue;
                                    }
                                    $href = clinic_sidebar_normalize_href((string) ($sub['menu_url'] ?? ''));
                                    $name = trim((string) ($sub['submenu_name'] ?? ''));
                                    if ($name === '' || !clinic_sidebar_href_exists($href)) {
                                        continue;
                                    }
                                    $items[] = [
                                        'name' => $name,
                                        'href' => $href,
                                        'icon' => trim((string) ($sub['menu_icon'] ?? '')),
                                    ];
                                }
                            }
                            if ($items === []) {
                                continue;
                            }
                            $mName = trim((string) ($menu['menu_name'] ?? ''));
                            $mIcon = trim((string) ($menu['icon'] ?? ''));
                            if ($mIcon === '') {
                                $mIcon = 'ti-circle-dot';
                            }
                            if (!str_contains($mIcon, 'ti-')) {
                                $mIcon = 'ti-' . ltrim($mIcon, '-');
                            }
                            $anyActive = false;
                            foreach ($items as $it) {
                                if (clinic_sidebar_link_active($it['href'], $currentBasename, $requestSegments)) {
                                    $anyActive = true;
                                    break;
                                }
                            }
                            ?>
                            <li class="submenu">
                                <a href="javascript:void(0);" class="d-flex clinic-submenu-toggle<?php echo $anyActive ? ' active subdrop clinic-menu-card' : ''; ?>">
                                    <i class="ti <?php echo htmlspecialchars($mIcon, ENT_QUOTES, 'UTF-8'); ?>"></i>
                                    <span><?php echo htmlspecialchars($mName, ENT_QUOTES, 'UTF-8'); ?></span>
                                    <span class="menu-arrow ms-auto flex-shrink-0"></span>
                                </a>
                                <ul<?php echo $anyActive ? ' style="display:block;"' : ''; ?>>
                                    <?php foreach ($items as $it):
                                        $childActive = clinic_sidebar_link_active($it['href'], $currentBasename, $requestSegments) ? ' active' : '';
                                        ?>
                                        <li>
                                            <a class="<?php echo trim($childActive); ?>" href="<?php echo htmlspecialchars($appBase . $it['href'], ENT_QUOTES, 'UTF-8'); ?>">
                                                <?php if ($it['icon'] !== ''): ?><i class="ti <?php echo htmlspecialchars($it['icon'], ENT_QUOTES, 'UTF-8'); ?> me-1"></i><?php endif; ?>
                                                <?php echo htmlspecialchars($it['name'], ENT_QUOTES, 'UTF-8'); ?>
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </li>
            </ul>
            <?php endif; ?>
        </div>
    </div>
</div>

