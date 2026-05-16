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

    return '<div class="alert alert-' . clinic_h($flash['type'] ?? 'success') . ' alert-dismissible fade show" role="alert">'
        . clinic_h($flash['message'] ?? '')
        . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
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
                <div class="clinic-hero d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
                    <div>
                        <h3 class="fw-bold mb-1"><?php echo clinic_h($title); ?></h3>
                        <?php if ($subtitle !== ''): ?><p class="text-muted mb-0"><?php echo clinic_h($subtitle); ?></p><?php endif; ?>
                    </div>
                    <div class="d-flex gap-2">
                        <a class="btn btn-light border" href="../index.php"><i class="ti ti-layout-dashboard me-1"></i>Dashboard</a>
                        <a class="btn btn-primary" href="patients.php"><i class="ti ti-user-heart me-1"></i>Patient desk</a>
                    </div>
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
            <div class="card-body d-flex justify-content-between gap-3">
                <div>
                    <p class="text-muted mb-1"><?php echo clinic_h($label); ?></p>
                    <h3 class="fw-bold mb-0"><?php echo clinic_h($value); ?></h3>
                    <?php if ($hint !== ''): ?><div class="small text-muted mt-2"><?php echo clinic_h($hint); ?></div><?php endif; ?>
                </div>
                <span class="clinic-metric-icon bg-<?php echo clinic_h($color); ?> bg-opacity-10 text-<?php echo clinic_h($color); ?>">
                    <i class="ti <?php echo clinic_h($icon); ?> fs-24"></i>
                </span>
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

