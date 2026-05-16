<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/report_bootstrap.php';

$users = clinic_sp_rows('sp_users_list');

if ((string) ($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="users-activity-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['User', 'Role', 'Status', 'Last login']);
    foreach ($users as $u) {
        fputcsv($out, [(string) ($u['Username'] ?? ''), (string) ($u['Role_Name'] ?? ''), (string) ($u['status'] ?? ''), (string) ($u['last_login'] ?? '')]);
    }
    fclose($out);
    exit;
}

$sortedUsers = $users;
usort($sortedUsers, static function ($u1, $u2) {
    $t1 = strtotime((string) ($u1['last_login'] ?? '')) ?: 0;
    $t2 = strtotime((string) ($u2['last_login'] ?? '')) ?: 0;

    return $t2 <=> $t1;
});

$withLogin = count(array_filter($sortedUsers, static fn ($u) => (string) ($u['last_login'] ?? '') !== ''));

clinic_reports_page_shell_start('User Activity', 'Directory snapshot with last login — add an audit table later for granular trails.');
?>

<div class="report-actions-bar mb-4">
    <a class="btn btn-outline-secondary btn-sm" href="reports.php"><i class="ti ti-layout-grid-add me-1"></i>All reports</a>
    <div class="report-actions-push">
        <a class="btn btn-primary btn-sm" href="report_user_activity.php?export=csv"><i class="ti ti-file-spreadsheet me-1"></i>Download CSV</a>
        <button class="btn btn-light border btn-sm" type="button" onclick="window.print()"><i class="ti ti-printer me-1"></i>Print</button>
    </div>
</div>

<div class="row g-3 mb-4">
    <?php clinic_metric_card('Users', count($sortedUsers), 'ti-users', 'primary', ''); ?>
    <?php clinic_metric_card('Recorded logins', $withLogin, 'ti-history', 'success', 'Has last_login value'); ?>
</div>

<div class="card report-data-card">
    <div class="card-header bg-white border-0 pt-3"><h5 class="mb-0">Accounts</h5></div>
    <div class="card-body table-responsive">
        <table class="table table-hover clinic-table align-middle">
            <thead><tr><th>Username</th><th>Role</th><th>Status</th><th>Last login</th></tr></thead>
            <tbody>
                <?php foreach ($sortedUsers as $u): ?>
                    <tr>
                        <td><?php echo clinic_h((string) ($u['Username'] ?? '')); ?></td>
                        <td><?php echo clinic_h((string) ($u['Role_Name'] ?? '—')); ?></td>
                        <td><?php echo clinic_status_badge((string) ($u['status'] ?? '')); ?></td>
                        <td><?php echo clinic_h((string) ($u['last_login'] ?? 'Never')); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php clinic_page_end(); ?>
