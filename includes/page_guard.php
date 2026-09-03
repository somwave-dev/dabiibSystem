<?php
/**
 * Page-level privilege guard (included at the end of advanced_components.php).
 *
 * A logged-in, non-admin user may only open a page whose submenu they have
 * can_view for. Pages that don't map to any submenu stay open, and the
 * account/profile page stays reachable so users can manage their own login.
 * Admin (role 1) always passes. Never throws.
 */

/**
 * App base path (root-relative) derived from the current script, so the
 * dashboard/logout links always point at the real files at the app root —
 * never at /pages/index.php.
 *
 * Examples:
 *   /dabiibSystem/pages/visits.php  -> /dabiibSystem
 *   /dabiibSystem/index.php         -> /dabiibSystem
 *   /pages/visits.php               -> ''   (deployed at domain root)
 */
function clinic_page_guard_app_base(): string
{
    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
    $dir = rtrim((string) dirname($script), '/');
    $dir = str_replace('\\', '/', $dir); // Windows dirname() can return '\' for root-level paths
    $dir = rtrim($dir, '/');
    if ($dir === '' || $dir === '.') {
        return '';
    }
    if (basename($dir) === 'pages') {
        $dir = rtrim((string) dirname($dir), '/');
        $dir = str_replace('\\', '/', $dir);
        $dir = rtrim($dir, '/');
    }

    return $dir === '' || $dir === '.' ? '' : $dir;
}

function clinic_page_guard_deny_and_exit(): void
{
    if (headers_sent()) {
        exit;
    }
    http_response_code(403);
    header('Content-Type: text/html; charset=utf-8');
    $appBase = clinic_page_guard_app_base();
    $dashUrl = $appBase . '/index.php';
    $logoutUrl = $appBase . '/logout.php';
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>Access denied</title>'
        . '<style>body{font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;background:#f4f6f9;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0}'
        . '.box{max-width:420px;width:100%;margin:16px;background:#fff;border:1px solid #e2e8f0;border-radius:18px;padding:36px 28px;text-align:center;box-shadow:0 18px 50px rgba(15,23,42,.10)}'
        . '.ic{width:64px;height:64px;margin:0 auto 16px;background:#fee2e2;border-radius:50%;display:flex;align-items:center;justify-content:center;color:#dc2626;font-size:30px;font-weight:900}'
        . 'h1{font-size:20px;margin:0 0 8px;color:#0f172a}p{color:#64748b;font-size:14px;line-height:1.6;margin:0 0 20px}'
        . 'a{display:inline-block;padding:10px 18px;border-radius:10px;text-decoration:none;font-size:14px;font-weight:600}'
        . '.btn-go{background:#4f6df5;color:#fff;margin-right:6px}.btn-out{background:#fff;color:#475569;border:1px solid #e2e8f0}'
        . '</style></head><body><div class="box"><div class="ic">!</div>'
        . '<h1>No access to this page</h1>'
        . '<p>Your account has not been granted permission for this section. Contact the administrator to request access.</p>'
        . '<a class="btn-go" href="' . htmlspecialchars($dashUrl, ENT_QUOTES, 'UTF-8') . '">Go to Dashboard</a>'
        . '<a class="btn-out" href="' . htmlspecialchars($logoutUrl, ENT_QUOTES, 'UTF-8') . '">Log out</a>'
        . '</div></body></html>';
    exit;
}

function clinic_page_guard_matching_submenu_ids(): array
{
    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $base = strtolower(basename($script));
    if ($base === '') {
        return [];
    }

    global $conn;
    if (!isset($conn) || !($conn instanceof mysqli)) {
        return [];
    }

    $ids = [];
    try {
        $res = $conn->query("SELECT submenu_id, menu_url FROM submenues WHERE deleted = 0 AND status = 'active'");
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $urlPath = (string) parse_url((string) ($row['menu_url'] ?? ''), PHP_URL_PATH);
                if ($urlPath === '') {
                    $urlPath = (string) ($row['menu_url'] ?? '');
                }
                if (strtolower(basename($urlPath)) === $base) {
                    $ids[] = (int) ($row['submenu_id'] ?? 0);
                }
            }
            $res->free();
        }
    } catch (Throwable $e) {
        return [];
    }

    return array_values(array_unique(array_filter($ids)));
}

/* Run the guard when a page includes advanced_components.php (web only). */
if (PHP_SAPI !== 'cli') {
    $__guardRole = (int) ($_SESSION['role_id'] ?? $_SESSION['Role_ID'] ?? 0);
    if (!empty($_SESSION['logged_in']) && $__guardRole !== 1) {
        $__guardBase = strtolower(basename(str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''))));
        $__guardExempt = [
            'login.php', 'logout.php', 'forgot-password.php', 'reset-password-link.php',
            'activate-account.php', 'profile.php', 'update_order.php', 'index.php',
        ];
        if (!in_array($__guardBase, $__guardExempt, true)) {
            $__guardMatches = clinic_page_guard_matching_submenu_ids();
            if ($__guardMatches !== []) {
                $__guardAllowed = [];
                try {
                    global $conn;
                    $__uid = (int) ($_SESSION['user_no'] ?? $_SESSION['User_ID'] ?? 0);
                    if ($__uid > 0 && isset($conn) && ($conn instanceof mysqli)) {
                        $st = $conn->prepare('SELECT submenu_id FROM user_privileges WHERE User_ID = ? AND can_view = 1');
                        $st->bind_param('i', $__uid);
                        $st->execute();
                        $r = $st->get_result();
                        while ($rr = $r->fetch_assoc()) {
                            $__guardAllowed[(int) ($rr['submenu_id'] ?? 0)] = true;
                        }
                        $st->close();
                    }
                } catch (Throwable $e) {
                    $__guardAllowed = [];
                }
                $__guardOk = false;
                foreach ($__guardMatches as $__mid) {
                    if (!empty($__guardAllowed[$__mid])) {
                        $__guardOk = true;
                        break;
                    }
                }
                if (!$__guardOk) {
                    clinic_page_guard_deny_and_exit();
                }
            }
        }
    }
}
