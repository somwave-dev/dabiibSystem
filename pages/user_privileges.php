<?php
require_once __DIR__ . '/../includes/advanced_components.php';

try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        clinic_check_csrf();
        if (clinic_post_string('action') === 'save_privileges') {
            $userId = clinic_post_int('User_ID');
            if ($userId < 1) {
                throw new RuntimeException('Select a user.');
            }

            foreach (clinic_sp_rows('sp_user_privileges_list') as $row) {
                if ((int) ($row['User_ID'] ?? 0) === $userId) {
                    clinic_sp_exec('sp_user_privileges_delete', [(int) $row['privilege_id']], 'i');
                }
            }

            $submenus = clinic_sp_rows('sp_submenues_list');
            $submenuNames = [];
            foreach ($submenus as $submenu) {
                $submenuNames[(int) ($submenu['submenu_id'] ?? 0)] = (string) ($submenu['submenu_name'] ?? '');
            }

            $permissions = $_POST['permissions'] ?? [];
            if (!is_array($permissions)) {
                $permissions = [];
            }

            $saved = 0;
            foreach ($permissions as $submenuId => $flags) {
                $submenuId = (int) $submenuId;
                if ($submenuId < 1 || !is_array($flags)) {
                    continue;
                }

                $canView = !empty($flags['can_view']) ? 1 : 0;
                $canInsert = !empty($flags['can_insert']) ? 1 : 0;
                $canDelete = !empty($flags['can_delete']) ? 1 : 0;
                $canUpdate = !empty($flags['can_update']) ? 1 : 0;
                if (($canView + $canInsert + $canDelete + $canUpdate) === 0) {
                    continue;
                }

                clinic_sp_exec('sp_user_privileges_save', [
                    0,
                    $userId,
                    $submenuId,
                    $submenuNames[$submenuId] ?? 'Submenu',
                    $canView,
                    $canInsert,
                    $canUpdate,
                    $canDelete,
                ]);
                $saved++;
            }

            clinic_flash($saved . ' privilege row' . ($saved === 1 ? '' : 's') . ' saved.');
            clinic_redirect('user_privileges.php?user_id=' . $userId);
        }
    }
} catch (Throwable $e) {
    clinic_flash($e->getMessage(), 'danger');
    clinic_redirect('user_privileges.php');
}

$users = clinic_sp_rows('sp_users_list');
$submenus = clinic_sp_rows('sp_submenues_list');
$selectedUserId = (int) ($_GET['user_id'] ?? ($users[0]['User_ID'] ?? 0));
$existing = [];
foreach (clinic_sp_rows('sp_user_privileges_list') as $row) {
    if ((int) ($row['User_ID'] ?? 0) === $selectedUserId) {
        $existing[(int) ($row['submenu_id'] ?? 0)] = $row;
    }
}

clinic_page_start('User Privileges', 'Grant can view, can insert, can delete, and can update permissions per submenu.');
?>
<style>
    .privilege-toolbar {
        background: var(--white);
        border: 1px solid var(--border-color);
        border-radius: 16px;
        box-shadow: var(--box-shadow-sm);
        padding: 1rem;
    }
    .privilege-table th {
        color: #6c757d;
        font-size: .72rem;
        letter-spacing: .04em;
        text-transform: uppercase;
    }
    .permission-check {
        align-items: center;
        display: inline-flex;
        justify-content: center;
        min-width: 92px;
    }
</style>

<div class="privilege-toolbar d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
    <form class="d-flex flex-wrap align-items-center gap-2" method="get">
        <label class="form-label mb-0 fw-semibold">User</label>
        <select class="form-select" name="user_id" style="min-width: 260px;">
            <?php clinic_select_options($users, 'User_ID', 'Username', $selectedUserId); ?>
        </select>
        <button class="btn btn-light border" type="submit"><i class="ti ti-filter me-1"></i>Load</button>
    </form>
    <span class="badge text-bg-light"><?php echo count($submenus); ?> submenu(s)</span>
</div>

<form method="post">
    <input type="hidden" name="csrf_token" value="<?php echo clinic_h(clinic_csrf_token()); ?>">
    <input type="hidden" name="action" value="save_privileges">
    <input type="hidden" name="User_ID" value="<?php echo (int) $selectedUserId; ?>">

    <div class="card clinic-card">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h5 class="mb-1">Permission Matrix</h5>
                <div class="text-muted small">Tick the access levels this user should have.</div>
            </div>
            <button class="btn btn-primary"><i class="ti ti-device-floppy me-1"></i>Save Privileges</button>
        </div>
        <div class="card-body table-responsive">
            <table class="table table-hover align-middle privilege-table">
                <thead>
                    <tr>
                        <th>Submenu</th>
                        <th>Parent</th>
                        <th class="text-center">Can View</th>
                        <th class="text-center">Can Insert</th>
                        <th class="text-center">Can Delete</th>
                        <th class="text-center">Can Update</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($submenus as $submenu): ?>
                    <?php
                    $submenuId = (int) ($submenu['submenu_id'] ?? 0);
                    $row = $existing[$submenuId] ?? [];
                    ?>
                    <tr>
                        <td class="fw-semibold"><?php echo clinic_h($submenu['submenu_name'] ?? '-'); ?></td>
                        <td class="text-muted"><?php echo clinic_h($submenu['menu_name'] ?? '-'); ?></td>
                        <td class="text-center"><label class="permission-check"><input class="form-check-input" type="checkbox" name="permissions[<?php echo $submenuId; ?>][can_view]" value="1" <?php echo !empty($row['can_view']) ? 'checked' : ''; ?>></label></td>
                        <td class="text-center"><label class="permission-check"><input class="form-check-input" type="checkbox" name="permissions[<?php echo $submenuId; ?>][can_insert]" value="1" <?php echo !empty($row['can_insert']) ? 'checked' : ''; ?>></label></td>
                        <td class="text-center"><label class="permission-check"><input class="form-check-input" type="checkbox" name="permissions[<?php echo $submenuId; ?>][can_delete]" value="1" <?php echo !empty($row['can_delete']) ? 'checked' : ''; ?>></label></td>
                        <td class="text-center"><label class="permission-check"><input class="form-check-input" type="checkbox" name="permissions[<?php echo $submenuId; ?>][can_update]" value="1" <?php echo !empty($row['can_update']) ? 'checked' : ''; ?>></label></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if ($submenus === []): ?>
                    <tr><td class="text-center text-muted py-4" colspan="6">No submenus found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</form>

<?php clinic_page_end(); ?>
