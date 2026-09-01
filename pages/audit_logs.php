<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/crud_page.php';

requireLogin();

$db = $GLOBALS['conn'] ?? null;
if (!($db instanceof mysqli)) {
    exit('Database connection unavailable.');
}

// ---------- filters ----------
$q           = trim((string) ($_GET['q'] ?? ''));
$actionFilter = trim((string) ($_GET['action'] ?? ''));
$from        = trim((string) ($_GET['from'] ?? ''));
$to          = trim((string) ($_GET['to'] ?? ''));
$page        = max(1, (int) ($_GET['page'] ?? 1));
$perPage     = 200;

$where  = [];
$params = [];
$types  = '';

if ($q !== '') {
    $where[] = '(action LIKE ? OR username LIKE ? OR details LIKE ? OR entity LIKE ? OR ip_address LIKE ?)';
    $like = '%' . $q . '%';
    $types .= 'sssss';
    array_push($params, $like, $like, $like, $like, $like);
}
if ($actionFilter !== '') {
    $where[] = 'action = ?';
    $types .= 's';
    $params[] = $actionFilter;
}
if ($from !== '' && strtotime($from) !== false) {
    $where[] = 'created_at >= ?';
    $types .= 's';
    $params[] = date('Y-m-d H:i:s', strtotime($from . ' 00:00:00'));
}
if ($to !== '' && strtotime($to) !== false) {
    $where[] = 'created_at <= ?';
    $types .= 's';
    $params[] = date('Y-m-d H:i:s', strtotime($to . ' 23:59:59'));
}
$whereSql = $where !== [] ? 'WHERE ' . implode(' AND ', $where) : '';

// ---------- totals for metric cards ----------
$metricTotals = $db->query("SELECT COUNT(*) AS total, SUM(created_at >= CURDATE()) AS today, COUNT(DISTINCT action) AS actions FROM audit_logs")->fetch_assoc();
$totalLogs  = (int) ($metricTotals['total'] ?? 0);
$todayLogs  = (int) ($metricTotals['today'] ?? 0);
$actionCnt  = (int) ($metricTotals['actions'] ?? 0);

// ---------- total (filtered) + paging ----------
$stmt = $db->prepare('SELECT COUNT(*) AS c FROM audit_logs ' . $whereSql);
if ($params !== []) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$filteredTotal = (int) (($stmt->get_result()->fetch_assoc() ?? [])['c'] ?? 0);
$stmt->close();

$pages = max(1, (int) ceil($filteredTotal / $perPage));
if ($page > $pages) {
    $page = $pages;
}

// ---------- rows ----------
$sql = 'SELECT * FROM audit_logs ' . $whereSql . ' ORDER BY created_at DESC, log_id DESC LIMIT ? OFFSET ?';
$rowParams = $params;
$rowTypes  = $types . 'ii';
$rowParams[] = $perPage;
$rowParams[] = ($page - 1) * $perPage;

$stmt = $db->prepare($sql);
$stmt->bind_param($rowTypes, ...$rowParams);
$stmt->execute();
$logs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ---------- distinct actions for the dropdown ----------
$actions = [];
foreach ($db->query('SELECT DISTINCT action FROM audit_logs ORDER BY action ASC')->fetch_all(MYSQLI_ASSOC) as $a) {
    $actions[] = (string) $a['action'];
}

function clinic_audit_badge(string $action): string
{
    $lower = strtolower($action);
    $color = 'primary';
    if (str_contains($lower, 'login')) {
        $color = 'info';
    } elseif (str_contains($lower, 'delete') || str_contains($lower, 'failed')) {
        $color = 'danger';
    } elseif (str_contains($lower, 'create') || str_contains($lower, 'added') || str_contains($lower, 'activated')) {
        $color = 'success';
    } elseif (str_contains($lower, 'update') || str_contains($lower, 'edit') || str_contains($lower, 'reset') || str_contains($lower, 'change')) {
        $color = 'warning';
    }

    return '<span class="badge text-bg-' . $color . '">' . clinic_h($action) . '</span>';
}

$qs = [];
foreach (['q' => $q, 'action' => $actionFilter, 'from' => $from, 'to' => $to] as $k => $v) {
    if ($v !== '') {
        $qs[$k] = $v;
    }
}
$buildQuery = static function (array $extra) use ($qs): string {
    $all = array_merge($qs, $extra);

    return 'audit_logs.php?' . http_build_query($all);
};

clinic_page_start('Audit Logs', 'Every event recorded by the system');
/*__AUDIT_HTML__*/
?>

<div class="row g-3 mb-4">
    <?php clinic_metric_card('Total Events', number_format($totalLogs), 'ti-history', 'primary', 'All recorded events'); ?>
    <?php clinic_metric_card('Today', number_format($todayLogs), 'ti-calendar-event', 'success', 'Events recorded today'); ?>
    <?php clinic_metric_card('Action Types', number_format($actionCnt), 'ti-tags', 'secondary', 'Distinct actions'); ?>
    <?php clinic_metric_card('Showing', number_format(count($logs)) . ' / ' . number_format($filteredTotal), 'ti-table', 'info', 'Current page / filtered total'); ?>
</div>

<div class="card clinic-card">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <h5 class="mb-0"><i class="ti ti-history me-2"></i>Audit Log</h5>
        <a class="btn btn-light border btn-sm" href="audit_logs.php"><i class="ti ti-refresh me-1"></i>Reset filters</a>
    </div>
    <div class="card-body">
        <form method="get" action="audit_logs.php" class="row g-2 mb-3">
            <div class="col-md-3">
                <input type="text" name="q" class="form-control" placeholder="Search user, action, details..." value="<?php echo clinic_h($q); ?>">
            </div>
            <div class="col-md-2">
                <select name="action" class="form-select">
                    <option value="">All actions</option>
                    <?php foreach ($actions as $act): ?>
                        <option value="<?php echo clinic_h($act); ?>"<?php echo $actionFilter === $act ? ' selected' : ''; ?>><?php echo clinic_h($act); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <input type="date" name="from" class="form-control" value="<?php echo clinic_h($from); ?>" title="From date">
            </div>
            <div class="col-md-2">
                <input type="date" name="to" class="form-control" value="<?php echo clinic_h($to); ?>" title="To date">
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm"><i class="ti ti-filter me-1"></i>Filter</button>
                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="this.closest('form').reset()"><i class="ti ti-x me-1"></i>Clear</button>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover align-middle datatable clinic-table">
                <thead>
                    <tr>
                        <th>Date / Time</th>
                        <th>User</th>
                        <th>Action</th>
                        <th>Entity</th>
                        <th>Details</th>
                        <th>IP</th>
                    </tr>
                </thead>
                <tbody>
/*__AUDIT_ROWS__*/
                    <?php if ($logs === []): ?>
                        <tr><td colspan="6" class="text-center text-muted py-4">No events found.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td class="text-nowrap"><i class="ti ti-clock me-1 text-muted"></i><?php echo clinic_h((string) ($log['created_at'] ?? '')); ?></td>
                            <td>
                                <?php if (!empty($log['username'])): ?>
                                    <span class="fw-semibold"><?php echo clinic_h($log['username']); ?></span>
                                    <?php if ((int) ($log['user_id'] ?? 0) > 0): ?>
                                        <span class="text-muted small">(#<?php echo (int) $log['user_id']; ?>)</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo clinic_audit_badge((string) ($log['action'] ?? '')); ?></td>
                            <td>
                                <?php
                                $ent = (string) ($log['entity'] ?? '');
                                if ($ent !== '') {
                                    echo clinic_h($ent) . ((int) ($log['entity_id'] ?? 0) > 0 ? ' #' . (int) $log['entity_id'] : '');
                                } else {
                                    echo '<span class="text-muted">—</span>';
                                }
                                ?>
                            </td>
                            <td class="text-muted"><?php echo clinic_h((string) ($log['details'] ?? '')); ?></td>
                            <td class="text-nowrap text-muted small"><?php echo clinic_h((string) ($log['ip_address'] ?? '')); ?></td>
                        </tr>
                    <?php endforeach; ?>

                </tbody>
            </table>
        </div>
/*__AUDIT_PAGER__*/
        <?php if ($pages > 1): ?>
            <nav class="mt-3" aria-label="Audit log pages">
                <ul class="pagination pagination-sm mb-0">
                    <li class="page-item<?php echo $page <= 1 ? ' disabled' : ''; ?>">
                        <a class="page-link" href="<?php echo clinic_h($buildQuery(['page' => $page - 1])); ?>">Prev</a>
                    </li>
                    <?php for ($i = 1; $i <= $pages; $i++): ?>
                        <li class="page-item<?php echo $i === $page ? ' active' : ''; ?>">
                            <a class="page-link" href="<?php echo clinic_h($buildQuery(['page' => $i])); ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item<?php echo $page >= $pages ? ' disabled' : ''; ?>">
                        <a class="page-link" href="<?php echo clinic_h($buildQuery(['page' => $page + 1])); ?>">Next</a>
                    </li>
                </ul>
            </nav>
        <?php endif; ?>

    </div>
</div>

<?php clinic_page_end(); ?>

