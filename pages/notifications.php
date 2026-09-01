<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/crud_page.php';

requireLogin();

$db = $GLOBALS['conn'] ?? null;
if (!($db instanceof mysqli)) {
    exit('Database connection unavailable.');
}

$userId = (int) ($_SESSION['user_no'] ?? $_SESSION['User_ID'] ?? 0);
$tab = ($_GET['view'] ?? 'all') === 'unread' ? 'unread' : 'all';

// ---------- POST actions ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'mark_read') {
        $id = (int) ($_POST['notification_id'] ?? 0);
        if ($id > 0) {
            $stmt = $db->prepare('UPDATE notifications SET is_read = 1 WHERE notification_id = ? AND (user_id IS NULL OR user_id = ?)');
            $stmt->bind_param('ii', $id, $userId);
            $stmt->execute();
            $stmt->close();
        }
        header('Location: notifications.php?view=' . $tab, true, 303);
        exit;
    }
    if ($action === 'mark_all_read') {
        $stmt = $db->prepare('UPDATE notifications SET is_read = 1 WHERE is_read = 0 AND (user_id IS NULL OR user_id = ?)');
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $stmt->close();
        header('Location: notifications.php?view=all', true, 303);
        exit;
    }
    if ($action === 'clear_all') {
        $stmt = $db->prepare('DELETE FROM notifications WHERE (user_id IS NULL OR user_id = ?)');
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $stmt->close();
        header('Location: notifications.php', true, 303);
        exit;
    }
}

// ---------- counts ----------
$stmt = $db->prepare('SELECT COUNT(*) AS total, SUM(is_read = 0) AS unread FROM notifications WHERE user_id IS NULL OR user_id = ?');
$stmt->bind_param('i', $userId);
$stmt->execute();
$counts = $stmt->get_result()->fetch_assoc() ?? [];
$stmt->close();
$totalCount  = (int) ($counts['total'] ?? 0);
$unreadCount = (int) ($counts['unread'] ?? 0);

// ---------- rows ----------
$where  = 'user_id IS NULL OR user_id = ?';
$params = [$userId];
$types  = 'i';
if ($tab === 'unread') {
    $where .= ' AND is_read = 0';
}
$sql = 'SELECT * FROM notifications WHERE ' . $where . ' ORDER BY created_at DESC, notification_id DESC LIMIT 200';
$stmt = $db->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

function clinic_notif_icon(string $type): string
{
    return [
        'success' => 'ti-circle-check',
        'warning' => 'ti-alert-triangle',
        'danger'  => 'ti-alert-octagon',
        'info'    => 'ti-info-circle',
    ][$type] ?? 'ti-info-circle';
}

function clinic_notif_color(string $type): string
{
    return [
        'success' => 'text-bg-success',
        'warning' => 'text-bg-warning',
        'danger'  => 'text-bg-danger',
        'info'    => 'text-bg-info',
    ][$type] ?? 'text-bg-info';
}

function clinic_time_ago(string $datetime): string
{
    $t = strtotime($datetime);
    if ($t === false) {
        return $datetime;
    }
    $diff = time() - $t;
    if ($diff < 60) {
        return 'just now';
    }
    if ($diff < 3600) {
        return (int) floor($diff / 60) . ' min ago';
    }
    if ($diff < 86400) {
        return (int) floor($diff / 3600) . ' hr ago';
    }
    if ($diff < 604800) {
        return (int) floor($diff / 86400) . ' days ago';
    }

    return date('M j, Y', $t);
}

clinic_page_start('Notifications', 'Alerts and events that need your attention');
/*__NOTIF_HTML__*/
?>

<div class="row g-3 mb-4">
    <?php clinic_metric_card('Total Notifications', number_format($totalCount), 'ti-bell', 'primary', 'All alerts'); ?>
    <?php clinic_metric_card('Unread', number_format($unreadCount), 'ti-bell-ringing', $unreadCount > 0 ? 'warning' : 'success', 'Pending attention'); ?>
</div>

<div class="d-flex flex-wrap gap-2 mb-3">
    <a href="notifications.php?view=all" class="btn <?php echo $tab === 'all' ? 'btn-primary' : 'btn-light border'; ?> btn-sm"><i class="ti ti-list me-1"></i>All</a>
    <a href="notifications.php?view=unread" class="btn <?php echo $tab === 'unread' ? 'btn-primary' : 'btn-light border'; ?> btn-sm"><i class="ti ti-bell-ringing me-1"></i>Unread<?php echo $unreadCount > 0 ? ' <span class="badge text-bg-danger ms-1">' . $unreadCount . '</span>' : ''; ?></a>
    <div class="ms-auto d-flex gap-2">
        <?php if ($unreadCount > 0): ?>
            <form method="post" action="notifications.php">
                <input type="hidden" name="action" value="mark_all_read">
                <button type="submit" class="btn btn-outline-success btn-sm"><i class="ti ti-checks me-1"></i>Mark all as read</button>
            </form>
        <?php endif; ?>
        <?php if ($totalCount > 0): ?>
            <form method="post" action="notifications.php" onsubmit="return confirm('Delete all notifications?');">
                <input type="hidden" name="action" value="clear_all">
                <button type="submit" class="btn btn-outline-danger btn-sm"><i class="ti ti-trash me-1"></i>Clear all</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php if ($items === []): ?>
    <div class="card clinic-card">
        <div class="card-body text-center text-muted py-5">
            <i class="ti ti-bell-off d-block mb-2" style="font-size:2.5rem"></i>
            <?php echo $tab === 'unread' ? 'No unread notifications.' : 'No notifications yet.'; ?>
        </div>
    </div>
<?php else: ?>
    <div class="card clinic-card">
        <div class="card-body p-0">
            <ul class="list-group list-group-flush">
                <?php foreach ($items as $n): ?>
                    <?php $type = (string) ($n['type'] ?? 'info'); ?>
                    <li class="list-group-item d-flex gap-3 align-items-start px-3 py-3">
                        <span class="badge <?php echo clinic_notif_color($type); ?> rounded-circle d-flex align-items-center justify-content-center" style="width:2.2rem;height:2.2rem;flex-shrink:0">
                            <i class="ti <?php echo clinic_notif_icon($type); ?>"></i>
                        </span>
                        <div class="flex-grow-1">
                            <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
                                <h6 class="mb-1"><?php echo clinic_h((string) ($n['title'] ?? '')); ?></h6>
                                <span class="small text-muted text-nowrap"><?php echo clinic_h(clinic_time_ago((string) ($n['created_at'] ?? ''))); ?></span>
                            </div>
                            <?php if (!empty($n['message'])): ?>
                                <p class="mb-1 small text-muted"><?php echo clinic_h((string) $n['message']); ?></p>
                            <?php endif; ?>
                            <div class="d-flex flex-wrap gap-2 align-items-center">
                                <?php if ((int) ($n['is_read'] ?? 0) === 1): ?>
                                    <span class="badge text-bg-light">Read</span>
                                <?php else: ?>
                                    <span class="badge text-bg-warning">New</span>
                                    <form method="post" action="notifications.php" class="m-0">
                                        <input type="hidden" name="action" value="mark_read">
                                        <input type="hidden" name="notification_id" value="<?php echo (int) $n['notification_id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-secondary"><i class="ti ti-eye me-1"></i>Mark as read</button>
                                    </form>
                                <?php endif; ?>
                                <?php if (!empty($n['link'])): ?>
                                    <a class="btn btn-sm btn-outline-primary" href="<?php echo clinic_h((string) $n['link']); ?>"><i class="ti ti-external-link me-1"></i>View</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
<?php endif; ?>

<?php clinic_page_end(); ?>

