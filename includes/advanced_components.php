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

function clinic_select_options(array $rows, string $valueKey, string $labelKey, mixed $selected = null): void
{
    foreach ($rows as $row) {
        $value = (string) ($row[$valueKey] ?? '');
        $label = (string) ($row[$labelKey] ?? $value);
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


