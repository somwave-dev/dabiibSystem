<?php
declare(strict_types=1);

/**
 * Small render helpers for the executive dashboard (includes/home.php).
 * All output is escaped; links point to the real pages.
 */

require_once __DIR__ . '/../advanced_components.php';

function clinic_dash_kpi(string $label, string $value, string $icon, string $color, string $href, string $hint = '', ?string $widgetKey = null): void
{
    if ($widgetKey !== null && !clinic_can_widget($widgetKey)) {
        return; // widget not granted: no markup, no data output
    }
    $color = in_array($color, ['primary', 'info', 'success', 'warning', 'danger', 'secondary'], true) ? $color : 'primary';
    $bg = [
        'primary' => 'bg-primary-subtle text-primary',
        'info' => 'bg-info-subtle text-info',
        'success' => 'bg-success-subtle text-success',
        'warning' => 'bg-warning-subtle text-warning',
        'danger' => 'bg-danger-subtle text-danger',
        'secondary' => 'bg-secondary-subtle text-secondary',
    ][$color];
    ?>
    <div class="dash-span-3">
        <a href="<?php echo clinic_h($href); ?>" class="text-decoration-none d-block h-100">
            <div class="card clinic-card h-100 p-3 shadow-sm">
                <div class="d-flex align-items-center gap-3">
                    <span class="rounded-3 d-inline-flex align-items-center justify-content-center <?php echo $bg; ?>" style="width:3rem;height:3rem;font-size:1.35rem;flex-shrink:0">
                        <i class="ti <?php echo clinic_h($icon); ?>"></i>
                    </span>
                    <div class="min-w-0">
                        <div class="text-muted small fw-semibold text-uppercase" style="letter-spacing:.04em"><?php echo clinic_h($label); ?></div>
                        <div class="fs-4 fw-bold lh-sm" style="color:var(--bs-body-color)"><?php echo clinic_h($value); ?></div>
                        <?php if ($hint !== ''): ?>
                            <div class="small text-muted text-truncate"><?php echo clinic_h($hint); ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </a>
    </div>
    <?php
}

function clinic_dash_card_open(string $title, string $href = '', string $hrefLabel = 'View all', string $icon = 'ti-chart-line'): void
{
    ?>
    <div class="card clinic-card h-100 dash-card">
        <div class="card-header d-flex align-items-center justify-content-between py-3">
            <h5 class="mb-0 small fw-bold text-uppercase" style="letter-spacing:.04em"><i class="ti <?php echo clinic_h($icon); ?> me-2 text-primary"></i><?php echo clinic_h($title); ?></h5>
            <?php if ($href !== ''): ?>
                <a href="<?php echo clinic_h($href); ?>" class="btn btn-sm btn-light border"><?php echo clinic_h($hrefLabel); ?></a>
            <?php endif; ?>
        </div>
        <div class="card-body">
    <?php
}

function clinic_dash_card_close(): void
{
    echo "</div></div>\n";
}
/*__UI3__*/

/*__UI2__*/

function clinic_dash_chart(string $id, string $title, string $subtitle = '', int $span = 6, string $size = 'md', ?string $widgetKey = null): void
{
    if ($widgetKey !== null && !clinic_can_widget($widgetKey)) {
        return; // chart not granted: container not rendered
    }
    $sizeClass = in_array($size, ['sm', 'md', 'lg'], true) ? $size : 'md';
    ?>
    <div class="dash-span-<?php echo (int) $span; ?>">
        <div class="card clinic-card h-100 dash-card">
            <div class="card-header d-flex align-items-center justify-content-between py-3">
                <h5 class="mb-0 small fw-bold text-uppercase" style="letter-spacing:.04em"><i class="ti ti-chart-line me-2 text-primary"></i><?php echo clinic_h($title); ?></h5>
                <?php if ($subtitle !== ''): ?><span class="badge text-bg-light"><?php echo clinic_h($subtitle); ?></span><?php endif; ?>
            </div>
            <div class="card-body">
                <div id="<?php echo clinic_h($id); ?>" class="dash-chart <?php echo $sizeClass; ?>"></div>
            </div>
        </div>
    </div>
    <?php
}

function clinic_dash_alert(string $label, string $count, string $icon, string $color, string $href): void
{
    $map = ['danger' => 'text-bg-danger', 'warning' => 'text-bg-warning', 'info' => 'text-bg-info', 'success' => 'text-bg-success'];
    ?>
    <a href="<?php echo clinic_h($href); ?>" class="text-decoration-none">
        <div class="dash-alert-row d-flex align-items-center justify-content-between rounded-3 px-3 py-2 mb-2 shadow-sm">
            <span class="small fw-semibold" style="color:var(--bs-body-color)"><i class="ti <?php echo clinic_h($icon); ?> me-2 text-danger"></i><?php echo clinic_h($label); ?></span>
            <span class="badge <?php echo $map[$color] ?? 'text-bg-danger'; ?> rounded-pill"><?php echo clinic_h($count); ?></span>
        </div>
    </a>
    <?php
}

function clinic_dash_activity_item(array $row): void
{
    $action = (string) ($row['action'] ?? '');
    $details = (string) ($row['details'] ?? '');
    $time = (string) ($row['created_at'] ?? '');
    $user = (string) ($row['username'] ?? '');

    $lower = strtolower($action);
    $icon = 'ti-circle';
    $color = 'text-primary';
    if (str_contains($lower, 'login')) {
        $icon = 'ti-login-2';
        $color = 'text-success';
    } elseif (str_contains($lower, 'delete')) {
        $icon = 'ti-trash';
        $color = 'text-danger';
    } elseif (str_contains($lower, 'create') || str_contains($lower, 'added') || str_contains($lower, 'activated')) {
        $icon = 'ti-plus';
        $color = 'text-primary';
    } elseif (str_contains($lower, 'update') || str_contains($lower, 'reset')) {
        $icon = 'ti-pencil';
        $color = 'text-warning';
    } elseif (str_contains($lower, 'password')) {
        $icon = 'ti-key';
        $color = 'text-secondary';
    }
    ?>
    <div class="d-flex gap-3 py-2 border-bottom">
        <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-body-secondary flex-shrink-0" style="width:2rem;height:2rem">
            <i class="ti <?php echo $icon; ?> <?php echo $color; ?>"></i>
        </span>
        <div class="flex-grow-1 min-w-0">
            <div class="small"><span class="fw-semibold"><?php echo clinic_h($action); ?></span><?php if ($user !== ''): ?> <span class="text-muted">· <?php echo clinic_h($user); ?></span><?php endif; ?></div>
            <?php if ($details !== ''): ?><div class="small text-muted text-truncate"><?php echo clinic_h($details); ?></div><?php endif; ?>
        </div>
        <div class="small text-muted text-nowrap flex-shrink-0"><?php echo clinic_h(date('H:i', strtotime($time ?: 'now'))); ?></div>
    </div>
    <?php
}

