<?php
require_once __DIR__ . '/../config/procedures.php';

if (!function_exists('clinic_h')) {
    function clinic_h(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

function clinic_money(mixed $value): string
{
    return '$' . number_format((float) $value, 2);
}

/**
 * Resolve a stored asset path into a URL usable from /pages (or root) context.
 * Handles empty values, absolute URLs and root-relative paths.
 */
function clinic_asset_url(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }
    if (preg_match('#^[a-z][a-z0-9+.-]*://#i', $value) || str_starts_with($value, '/')) {
        return $value;
    }
    $base = (string) ($GLOBALS['asset_base'] ?? '../');

    return $base . ltrim($value, '/');
}

/**
 * Render an avatar: a photo <img> when an image is stored, otherwise a
 * colored circle with the first letter of the name.
 *
 * @param string $class Size/rounding class shared by both <img> and <span>.
 */
function clinic_avatar(?string $image, string $name, string $class = 'clinic-avatar'): string
{
    $image = trim((string) $image);

    // A stored value only counts as a real photo when the local file exists.
    // Placeholders / missing files (e.g. "default-user.png") fall back to a letter avatar.
    if ($image !== '' && !preg_match('#^[a-z][a-z0-9+.-]*://#i', $image) && !str_starts_with($image, '/')) {
        if (!is_file(dirname(__DIR__) . '/' . ltrim($image, '/'))) {
            $image = '';
        }
    }

    $url = clinic_asset_url($image);
    $safeName = clinic_h($name);
    if ($url !== '') {
        return '<img class="' . clinic_h($class) . ' clinic-avatar-img" src="' . clinic_h($url) . '" alt="' . $safeName . '" loading="lazy">';
    }

    $initial = mb_strtoupper(mb_substr(trim((string) $name) ?: '?', 0, 1), 'UTF-8');

    return '<span class="' . clinic_h($class) . ' clinic-avatar-letter">' . clinic_h($initial) . '</span>';
}

/**
 * Avatar of the user currently in the session — read fresh from the users
 * table on every request so photo changes appear immediately (no stale
 * session snapshot). Falls back to the session value, then '' (letter avatar).
 */
function clinic_current_user_avatar(): string
{
    $userId = (int) ($_SESSION['user_no'] ?? $_SESSION['User_ID'] ?? $_SESSION['user_id'] ?? 0);
    $image = '';
    if ($userId > 0) {
        $connA = $GLOBALS['conn'] ?? null;
        if ($connA instanceof mysqli) {
            try {
                $stmtA = $connA->prepare('SELECT image FROM users WHERE User_ID = ? AND deleted = 0 LIMIT 1');
                $stmtA->bind_param('i', $userId);
                $stmtA->execute();
                $resA = $stmtA->get_result();
                if ($rowA = $resA->fetch_assoc()) {
                    $image = trim((string) ($rowA['image'] ?? ''));
                }
                $stmtA->close();
            } catch (Throwable $e) {
                $image = '';
            }
        }
    }
    if ($image === '' || $image === 'default-user.png') {
        $image = trim((string) ($_SESSION['user_image'] ?? ''));
    }
    if ($image === '' || $image === 'default-user.png') {
        $image = '';
    }
    $_SESSION['user_image'] = $image;

    return $image;
}

/**
 * Validate and store an uploaded avatar image. Returns the stored web path
 * (relative, e.g. "storage/uploads/avatars/20260827-abc.png").
 */
function clinic_handle_avatar_upload(string $fileKey): string
{
    $file = $_FILES[$fileKey] ?? null;
    if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Upload failed — no image was received.');
    }
    if ((int) $file['size'] > 2 * 1024 * 1024) {
        throw new RuntimeException('Image is larger than the 2MB limit.');
    }

    $ext = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    if (!in_array($ext, $allowed, true)) {
        throw new RuntimeException('Unsupported image type ".'.$ext.'". Allowed: '.implode(', ', $allowed).'.');
    }

    $tmp = (string) ($file['tmp_name'] ?? '');
    if (!is_uploaded_file($tmp)) {
        throw new RuntimeException('Invalid upload source.');
    }

    $mimeOk = false;
    if (function_exists('finfo_open')) {
        $mime = (string) (new finfo(FILEINFO_MIME_TYPE))->file($tmp);
        $mimeOk = in_array($mime, ['image/png', 'image/jpeg', 'image/gif', 'image/webp'], true);
    }
    if (!$mimeOk && @getimagesize($tmp) === false) {
        throw new RuntimeException('Uploaded file is not a valid image.');
    }

    $dir = dirname(__DIR__) . '/storage/uploads/avatars';
    if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
        throw new RuntimeException('Could not create the upload directory.');
    }

    $filename = date('Ymd') . '-' . substr(bin2hex(random_bytes(6)), 0, 12) . '.' . $ext;
    if (!move_uploaded_file($tmp, $dir . '/' . $filename)) {
        throw new RuntimeException('Could not save the uploaded image.');
    }

    return 'storage/uploads/avatars/' . $filename;
}

function clinic_post_string(string $key): string
{
    return trim((string) ($_POST[$key] ?? ''));
}

function clinic_post_int(string $key): int
{
    return (int) ($_POST[$key] ?? 0);
}

function clinic_post_float(string $key): float
{
    return (float) ($_POST[$key] ?? 0);
}

function clinic_csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return (string) $_SESSION['csrf_token'];
}

function clinic_check_csrf(): void
{
    $sent = (string) ($_POST['csrf_token'] ?? '');
    if ($sent === '' || !hash_equals(clinic_csrf_token(), $sent)) {
        throw new RuntimeException('Invalid form token. Refresh and try again.');
    }
}

function clinic_flash(?string $message = null, string $type = 'success'): ?string
{
    if ($message !== null) {
        $_SESSION['flash'] = ['message' => $message, 'type' => $type];
        return null;
    }

    if (empty($_SESSION['flash'])) {
        return null;
    }

    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);

    $type = (string) ($flash['type'] ?? 'success');
    $message = (string) ($flash['message'] ?? '');
    if ($message === '') {
        return null;
    }

    $contexts = ['primary', 'secondary', 'success', 'danger', 'warning', 'info', 'light', 'dark'];
    $context = in_array($type, $contexts, true) ? $type : ($type === 'danger' ? 'danger' : ($type === 'warning' ? 'warning' : 'success'));
    $title = $context === 'danger' ? 'Error' : ($context === 'warning' ? 'Warning' : ($context === 'success' ? 'Success' : ucfirst($context)));

    return '<div class="alert alert-' . $context . ' alert-dismissible fade show border border-' . $context . '" role="alert">'
        . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>'
        . '<strong>' . clinic_h($title) . ' - </strong> ' . clinic_h($message) . '</div>';
}

function clinic_redirect(string $path = ''): never
{
    $target = $path !== '' ? $path : basename((string) ($_SERVER['SCRIPT_NAME'] ?? 'index.php'));
    header('Location: ' . $target, true, 303);
    exit;
}

function clinic_page_start(string $title, string $subtitle = ''): void
{
    $GLOBALS['asset_base'] = '../';
    $GLOBALS['app_base'] = '../';
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php require __DIR__ . '/head.php'; ?>
    <title><?php echo clinic_h($title); ?> - Clinic</title>
    <style>
        .clinic-hero { background: linear-gradient(135deg, rgba(13,110,253,.08), rgba(255,255,255,0)); border: 1px solid rgba(0,0,0,.06); border-radius: 1rem; padding: 1.25rem; }
        .clinic-card { border: 1px solid rgba(0,0,0,.07); border-radius: .9rem; box-shadow: 0 8px 24px rgba(15,23,42,.04); }
        .clinic-metric-icon { width: 2.75rem; height: 2.75rem; display: inline-flex; align-items: center; justify-content: center; border-radius: .8rem; }
        .clinic-table th { font-size: .72rem; letter-spacing: .04em; text-transform: uppercase; color: #6c757d; }
        .clinic-patient-avatar { width: 2.75rem; height: 2.75rem; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-weight: 700; }
        .clinic-workflow-pill { border: 1px solid rgba(0,0,0,.08); background: #fff; border-radius: 999px; padding: .4rem .75rem; }
    </style>
</head>
<body>
    <div class="main-wrapper">
        <?php require __DIR__ . '/header.php'; ?>
        <?php require __DIR__ . '/sidebar.php'; ?>
        <div class="page-wrapper">
            <div class="content pb-0">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                    <div>
                        <h3 class="fw-bold mb-0"><?php echo clinic_h($title); ?></h3>
                        <?php if ($subtitle !== ''): ?><p class="text-muted small mb-0 mt-1"><?php echo clinic_h($subtitle); ?></p><?php endif; ?>
                    </div>
                    <a class="btn btn-light border btn-sm" href="../index.php"><i class="ti ti-layout-dashboard me-1"></i>Dashboard</a>
                </div>
                <?php echo clinic_flash() ?? ''; ?>
    <?php
}

function clinic_page_end(): void
{
    ?>
            </div>
            <?php require __DIR__ . '/footer.php'; ?>
        </div>
    </div>
    <?php require __DIR__ . '/plugins.php'; ?>
</body>
</html>
    <?php
}

function clinic_metric_card(string $label, mixed $value, string $icon, string $color = 'primary', string $hint = ''): void
{
    ?>
    <div class="col-xl-3 col-md-6">
        <div class="card clinic-card h-100">
            <div class="card-body p-3 d-flex align-items-center gap-3">
                <span class="clinic-metric-icon bg-<?php echo clinic_h($color); ?>-subtle text-<?php echo clinic_h($color); ?>">
                    <i class="ti <?php echo clinic_h($icon); ?> fs-24"></i>
                </span>
                <div class="min-w-0">
                    <div class="text-muted small fw-semibold text-uppercase" style="letter-spacing:.05em"><?php echo clinic_h($label); ?></div>
                    <div class="fs-4 fw-bold text-body mt-1"><?php echo clinic_h($value); ?></div>
                    <?php if ($hint !== ''): ?><div class="small text-muted mt-1 text-truncate"><?php echo clinic_h($hint); ?></div><?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php
}

function clinic_status_badge(string $status): string
{
    $map = [
        'Pending' => 'warning',
        'Completed' => 'success',
        'Cancelled' => 'secondary',
        'active' => 'success',
        'inactive' => 'secondary',
    ];
    $color = $map[$status] ?? 'primary';

    return '<span class="badge text-bg-' . clinic_h($color) . '">' . clinic_h($status) . '</span>';
}

function clinic_select_options(array $rows, string $valueKey, string $labelKey, mixed $selected = null, bool $showIdPrefix = true): void
{
    foreach ($rows as $row) {
        $value = (string) ($row[$valueKey] ?? '');
        $label = (string) ($row[$labelKey] ?? $value);
        // Show "#ID — Name" so every searchable dropdown (Select2) can be
        // found by both the record id and the name.
        if ($showIdPrefix && $value !== '' && $labelKey !== $valueKey && strpos($label, '#' . $value) !== 0) {
            $label = '#' . $value . ' — ' . $label;
        }
        $isSelected = ((string) $selected === $value) ? ' selected' : '';
        echo '<option value="' . clinic_h($value) . '"' . $isSelected . '>' . clinic_h($label) . '</option>';
    }
}

/**
 * Write a row into the audit_logs table. Never throws: auditing must not
 * break the request that triggered it.
 */
function clinic_audit_log(string $action, string $details = '', ?string $entity = null, ?int $entityId = null, ?int $userId = null): void
{
    global $conn;

    try {
        if (!isset($conn) || !($conn instanceof mysqli)) {
            return;
        }
        if ($userId === null) {
            $userId = (int) ($_SESSION['user_no'] ?? $_SESSION['User_ID'] ?? $_SESSION['user_id'] ?? 0);
        }
        $userId = $userId > 0 ? $userId : null;
        $username = trim((string) ($_SESSION['username'] ?? ''));
        $entityId = ($entityId !== null && $entityId > 0) ? $entityId : null;
        $details = mb_substr(trim($details), 0, 2000);
        $ip = substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);

        $stmt = $conn->prepare(
            'INSERT INTO audit_logs (user_id, username, action, entity, entity_id, details, ip_address)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->bind_param('isssiss', $userId, $username, $action, $entity, $entityId, $details, $ip);
        $stmt->execute();
        $stmt->close();
    } catch (Throwable $e) {
        // keep going
    }
}

/**
 * Insert a notification row. user_id NULL means "everyone". Returns the new id (0 on failure).
 */
function clinic_notify(string $title, string $message, string $type = 'info', ?string $link = null, ?int $userId = null): int
{
    global $conn;

    try {
        if (!isset($conn) || !($conn instanceof mysqli)) {
            return 0;
        }
        $userId = ($userId !== null && $userId > 0) ? $userId : null;
        $type = in_array($type, ['info', 'success', 'warning', 'danger'], true) ? $type : 'info';
        $title = mb_substr(trim($title), 0, 150);
        $message = mb_substr(trim($message), 0, 2000);
        $link = (trim((string) $link) === '') ? null : mb_substr(trim((string) $link), 0, 200);

        $stmt = $conn->prepare(
            'INSERT INTO notifications (user_id, type, title, message, link) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->bind_param('issss', $userId, $type, $title, $message, $link);
        $stmt->execute();
        $id = (int) $stmt->insert_id;
        $stmt->close();

        return $id;
    } catch (Throwable $e) {
        return 0;
    }
}

/**
 * Count unread notifications for the current user (or broadcast-only rows when
 * the session has no user). Never throws.
 */
function clinic_unread_notifications(?int $userId = null): int
{
    global $conn;

    try {
        if (!isset($conn) || !($conn instanceof mysqli)) {
            return 0;
        }
        $uid = $userId ?? (int) ($_SESSION['user_no'] ?? $_SESSION['User_ID'] ?? 0);
        if ($uid > 0) {
            $stmt = $conn->prepare('SELECT COUNT(*) AS c FROM notifications WHERE is_read = 0 AND (user_id IS NULL OR user_id = ?)');
            $stmt->bind_param('i', $uid);
        } else {
            $stmt = $conn->prepare('SELECT COUNT(*) AS c FROM notifications WHERE is_read = 0 AND user_id IS NULL');
        }
        $stmt->execute();
        $c = (int) (($stmt->get_result()->fetch_assoc() ?? [])['c'] ?? 0);
        $stmt->close();

        return $c;
    } catch (Throwable $e) {
        return 0;
    }
}



/* Page-level privilege guard (non-admin users only open pages they can_view). */
require_once __DIR__ . '/page_guard.php';

/**
 * Submenu ids the current user may view (can_view). Admin users always see
 * everything — callers that need an admin shortcut use clinic_module_granted().
 */
function clinic_granted_submenu_ids(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $cache = [];
    $uid = (int) ($_SESSION['user_no'] ?? $_SESSION['User_ID'] ?? 0);
    global $conn;
    if ($uid > 0 && isset($conn) && ($conn instanceof mysqli)) {
        try {
            $st = $conn->prepare('SELECT submenu_id FROM user_privileges WHERE User_ID = ? AND can_view = 1');
            $st->bind_param('i', $uid);
            $st->execute();
            $res = $st->get_result();
            while ($row = $res->fetch_assoc()) {
                $cache[(int) ($row['submenu_id'] ?? 0)] = true;
            }
            $st->close();
        } catch (Throwable $e) {
            $cache = [];
        }
    }

    return $cache;
}

/**
 * True when the current user may open at least one of the given submenu ids.
 * Role 1 (admin) is always granted.
 */
function clinic_module_granted(array $submenuIds): bool
{
    if ((int) ($_SESSION['role_id'] ?? $_SESSION['Role_ID'] ?? 0) === 1) {
        return true;
    }
    $granted = clinic_granted_submenu_ids();
    foreach ($submenuIds as $id) {
        if (!empty($granted[(int) $id])) {
            return true;
        }
    }

    return false;
}

/**
 * Full per-submenu privilege state for the current user (view/insert/update/
 * delete/status). Returns null when the user has no row for that page.
 */
function clinic_privilege_row(int $submenuId): ?array
{
    $uid = (int) ($_SESSION['user_no'] ?? $_SESSION['User_ID'] ?? 0);
    static $cache = [];
    $cacheKey = $uid . ':' . $submenuId;
    if (array_key_exists($cacheKey, $cache)) {
        return $cache[$cacheKey];
    }
    $row = null;
    global $conn;
    if ($uid > 0 && isset($conn) && ($conn instanceof mysqli)) {
        try {
            $st = $conn->prepare(
                'SELECT can_view, can_insert, can_update, can_delete, can_status
                 FROM user_privileges WHERE User_ID = ? AND submenu_id = ? LIMIT 1'
            );
            $st->bind_param('ii', $uid, $submenuId);
            $st->execute();
            $res = $st->get_result();
            $row = $res->fetch_assoc() ?: null;
            $st->close();
        } catch (Throwable $e) {
            $row = null;
        }
    }
    $cache[$cacheKey] = $row;

    return $row;
}

/**
 * Single permission flag on a page: view|insert|update|delete|status.
 * Admin (role 1) always passes for view/insert/update/delete/status.
 */
function clinic_can(int $submenuId, string $flag): bool
{
    if (!in_array($flag, ['view', 'insert', 'update', 'delete', 'status'], true)) {
        return false;
    }
    if ((int) ($_SESSION['role_id'] ?? $_SESSION['Role_ID'] ?? 0) === 1) {
        return true;
    }
    $row = clinic_privilege_row($submenuId);

    return $row !== null && !empty($row['can_' . $flag]);
}

/**
 * Workflow/action permission on a page, e.g. clinic_can_action(4, 'complete').
 * Admin (role 1) always passes. Action definitions live in privilege_actions;
 * grants live in user_privilege_actions.
 */
function clinic_can_action(int $submenuId, string $actionKey): bool
{
    if ((int) ($_SESSION['role_id'] ?? $_SESSION['Role_ID'] ?? 0) === 1) {
        return true;
    }
    $uid = (int) ($_SESSION['user_no'] ?? $_SESSION['User_ID'] ?? 0);
    static $actionCache = [];
    $cacheKey = $uid . ':' . $submenuId;
    if (!isset($actionCache[$cacheKey])) {
        $actionCache[$cacheKey] = [];
        global $conn;
        if ($uid > 0 && isset($conn) && ($conn instanceof mysqli)) {
            try {
                $st = $conn->prepare(
                    'SELECT pa.action_key, upa.granted
                     FROM user_privilege_actions upa
                     JOIN privilege_actions pa ON pa.action_id = upa.action_id
                     WHERE upa.User_ID = ? AND pa.submenu_id = ?'
                );
                $st->bind_param('ii', $uid, $submenuId);
                $st->execute();
                $res = $st->get_result();
                while ($r = $res->fetch_assoc()) {
                    if (!empty($r['granted'])) {
                        $actionCache[$cacheKey][(string) $r['action_key']] = true;
                    }
                }
                $st->close();
            } catch (Throwable $e) {
                $actionCache[$cacheKey] = [];
            }
        }
    }

    return !empty($actionCache[$cacheKey][(string) $actionKey]);
}

/**
 * Server-side denial helpers — throw so the surrounding try/catch turns it
 * into a clear error message and the operation never reaches the database.
 */
function clinic_require_can(int $submenuId, string $flag): void
{
    if (!clinic_can($submenuId, $flag)) {
        throw new RuntimeException('You do not have permission to perform this action on that page.');
    }
}

function clinic_require_action(int $submenuId, string $actionKey, string $label = 'status change'): void
{
    if (!clinic_can_action($submenuId, $actionKey)) {
        throw new RuntimeException('You do not have permission to perform this ' . $label . '.');
    }
}

/**
 * Dashboard widget → module submenu map. Tile keys that aren't in the static
 * list resolve their module through the dashboard_widgets table (module_key).
 */
function clinic_widget_module_ids(string $widgetKey): array
{
    static $staticMap = [
        'dash_patients'     => [2],
        'dash_appointments' => [3],
        'dash_visits'       => [4],
        'dash_lab'          => [8, 9],
        'dash_pharmacy'     => [10, 11, 12],
        'dash_nursing'      => [6, 7],
        'dash_finance'      => [13, 14, 15, 43],
        'dash_doctors'      => [5],
        'dash_activity'     => [41],
        'dash_reports'      => [29, 33, 34, 35, 36, 37, 44],
    ];
    if (isset($staticMap[$widgetKey])) {
        return $staticMap[$widgetKey];
    }

    $moduleMap = [
        'patients'     => [2],
        'appointments' => [3],
        'visits'       => [4],
        'lab'          => [8, 9],
        'pharmacy'     => [10, 11, 12],
        'nursing'      => [6, 7],
        'finance'      => [13, 14, 15, 43],
        'doctors'      => [5],
        'activity'     => [41],
        'reports'      => [29, 33, 34, 35, 36, 37, 44],
    ];

    static $moduleCache = [];
    if (!array_key_exists($widgetKey, $moduleCache)) {
        $moduleCache[$widgetKey] = null;
        global $conn;
        if (isset($conn) && ($conn instanceof mysqli)) {
            try {
                $st = $conn->prepare('SELECT module_key FROM dashboard_widgets WHERE widget_key = ? LIMIT 1');
                $st->bind_param('s', $widgetKey);
                $st->execute();
                $res = $st->get_result();
                if ($row = $res->fetch_assoc()) {
                    $moduleCache[$widgetKey] = (string) ($row['module_key'] ?? '');
                }
                $st->close();
            } catch (Throwable $e) {
                $moduleCache[$widgetKey] = null;
            }
        }
    }

    $moduleKey = $moduleCache[$widgetKey] ?? '';

    return $moduleKey !== '' && isset($moduleMap[$moduleKey]) ? $moduleMap[$moduleKey] : [];
}

/**
 * Dashboard widget/card access. Admin sees everything. When the admin has
 * stored an explicit per-user grant (user_dashboard_widgets) that wins;
 * otherwise the widget follows the module's can_view (same behaviour as
 * before), so existing users never lose cards they already have.
 */
function clinic_can_widget(string $widgetKey): bool
{
    if ((int) ($_SESSION['role_id'] ?? $_SESSION['Role_ID'] ?? 0) === 1) {
        return true;
    }
    $uid = (int) ($_SESSION['user_no'] ?? $_SESSION['User_ID'] ?? 0);
    static $cache = [];
    if (isset($cache[$uid . ':' . $widgetKey])) {
        return $cache[$uid . ':' . $widgetKey];
    }
    $granted = null;
    global $conn;
    if ($uid > 0 && isset($conn) && ($conn instanceof mysqli)) {
        try {
            $st = $conn->prepare('SELECT granted FROM user_dashboard_widgets WHERE User_ID = ? AND widget_key = ? LIMIT 1');
            $st->bind_param('is', $uid, $widgetKey);
            $st->execute();
            $res = $st->get_result();
            if ($row = $res->fetch_assoc()) {
                $granted = (int) ($row['granted'] ?? 0) === 1;
            }
            $st->close();
        } catch (Throwable $e) {
            $granted = null;
        }
    }
    if ($granted === null) {
        $granted = clinic_module_granted(clinic_widget_module_ids($widgetKey));
    }
    $cache[$uid . ':' . $widgetKey] = $granted;

    return $granted;
}

/**
 * True when the current user may see at least one widget from the given set.
 */
function clinic_widget_any(array $widgetKeys): bool
{
    foreach ($widgetKeys as $key) {
        if (clinic_can_widget($key)) {
            return true;
        }
    }

    return false;
}

/**
 * ── Doctor data scope ───────────────────────────────────────────────
 * A logged-in doctor (role 2 / role_name "doctor") only ever sees data
 * that belongs to the doctor profile linked to his user account.
 */

function clinic_is_doctor_scoped_user(): bool
{
    if ((int) ($_SESSION['role_id'] ?? $_SESSION['Role_ID'] ?? 0) === 1) {
        return false;
    }

    return (int) ($_SESSION['role_id'] ?? $_SESSION['Role_ID'] ?? 0) === 2
        || strtolower(trim((string) ($_SESSION['role_name'] ?? ''))) === 'doctor';
}

/**
 * Doctor_ID linked to the current user's account (via doctors.User_ID or
 * staff.User_ID), or null when not a doctor / not linked.
 */
function clinic_current_doctor_id(): ?int
{
    if (!clinic_is_doctor_scoped_user()) {
        return null;
    }
    $uid = (int) ($_SESSION['user_no'] ?? $_SESSION['User_ID'] ?? 0);
    if ($uid < 1) {
        return null;
    }
    global $conn;
    static $cache = [];
    if (array_key_exists($uid, $cache)) {
        return $cache[$uid];
    }
    $doctorId = null;
    if (isset($conn) && ($conn instanceof mysqli)) {
        try {
            $st = $conn->prepare(
                'SELECT d.Doctor_ID FROM doctors d
                 LEFT JOIN staff s ON s.Staff_ID = d.Staff_ID
                 WHERE COALESCE(d.User_ID, s.User_ID) = ? AND d.deleted = 0
                 LIMIT 1'
            );
            $st->bind_param('i', $uid);
            $st->execute();
            $res = $st->get_result();
            if ($row = $res->fetch_assoc()) {
                $doctorId = (int) ($row['Doctor_ID'] ?? 0) > 0 ? (int) $row['Doctor_ID'] : null;
            }
            $st->close();
        } catch (Throwable $e) {
            $doctorId = null;
        }
    }
    $cache[$uid] = $doctorId;

    return $doctorId;
}

/**
 * True when the appointment row belongs to the given doctor.
 */
function clinic_appointment_is_doctor(int $doctorId, array $appointment): bool
{
    return $doctorId > 0 && (int) ($appointment['Doctor_ID'] ?? 0) === $doctorId;
}

/**
 * Server-side guard: throws when the current doctor tries to touch another
 * doctor's appointment. Safe for reception/admin (they are never scoped).
 */
function clinic_ensure_own_appointment(int $appointmentId): void
{
    if (!clinic_is_doctor_scoped_user()) {
        return;
    }
    $doctorId = clinic_current_doctor_id();
    if ($doctorId === null || $doctorId < 1) {
        throw new RuntimeException('Your user is not linked to a doctor profile.');
    }
    if ($appointmentId < 1) {
        throw new RuntimeException('Appointment was not found.');
    }
    $appointment = clinic_sp_one('sp_appointments_get', [$appointmentId], 'i');
    if (!$appointment) {
        throw new RuntimeException('Appointment was not found.');
    }
    if (!clinic_appointment_is_doctor($doctorId, $appointment)) {
        throw new RuntimeException('You can only manage your own appointments.');
    }
}

/**
 * Patient ids this doctor can work with (patients that ever had an
 * appointment or a visit with the doctor).
 */
function clinic_doctor_allowed_patient_ids(int $doctorId): array
{
    $ids = [];
    global $conn;
    if ($doctorId > 0 && isset($conn) && ($conn instanceof mysqli)) {
        try {
            $st = $conn->prepare(
                'SELECT Patient_ID FROM appointments WHERE Doctor_ID = ? AND deleted = 0
                 UNION SELECT Patient_ID FROM visits WHERE Doctor_ID = ? AND deleted = 0'
            );
            $st->bind_param('ii', $doctorId, $doctorId);
            $st->execute();
            $res = $st->get_result();
            while ($row = $res->fetch_assoc()) {
                $ids[(int) ($row['Patient_ID'] ?? 0)] = true;
            }
            $st->close();
        } catch (Throwable $e) {
            $ids = [];
        }
    }
    unset($ids[0]);

    return array_keys($ids);
}

/**
 * Load one of the shared list stored procedures and scope it to the current
 * doctor. Non-doctor users (reception, nurse, lab tech, admin) always get
 * the full list, so this is safe to use anywhere patient/visit/lab rows are
 * displayed.
 */
function clinic_doctor_scoped_list(string $proc): array
{
    $rows = clinic_sp_rows($proc);
    if (!clinic_is_doctor_scoped_user()) {
        return $rows;
    }
    $doctorId = clinic_current_doctor_id();
    if ($doctorId === null || $doctorId < 1) {
        return [];
    }

    if ($proc === 'sp_visits_list' || $proc === 'sp_appointments_list') {
        return array_values(array_filter($rows, static fn (array $r): bool => (int) ($r['Doctor_ID'] ?? 0) === $doctorId));
    }

    if ($proc === 'sp_patients_list') {
        $allowedSet = array_flip(clinic_doctor_allowed_patient_ids($doctorId));

        return array_values(array_filter($rows, static fn (array $r): bool => isset($allowedSet[(int) ($r['Patient_ID'] ?? 0)])));
    }

    if ($proc === 'sp_lab_results_list') {
        global $conn;
        $visitSet = [];
        if (isset($conn) && ($conn instanceof mysqli)) {
            try {
                $st = $conn->prepare('SELECT Visit_ID FROM visits WHERE Doctor_ID = ? AND deleted = 0');
                $st->bind_param('i', $doctorId);
                $st->execute();
                $res = $st->get_result();
                while ($r = $res->fetch_assoc()) {
                    $visitSet[(int) ($r['Visit_ID'] ?? 0)] = true;
                }
                $st->close();
            } catch (Throwable $e) {
                $visitSet = [];
            }
        }

        return array_values(array_filter($rows, static fn (array $r): bool => isset($visitSet[(int) ($r['Visit_ID'] ?? 0)])));
    }

    return $rows;
}





