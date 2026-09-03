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
            $grantedActions = 0;
            $widgetSaved = 0;
            $connG = $GLOBALS['conn'] ?? null;
            if (!($connG instanceof mysqli)) {
                throw new RuntimeException('Database connection unavailable.');
            }
            $connG->begin_transaction();
            try {
                foreach ($permissions as $submenuId => $flags) {
                    $submenuId = (int) $submenuId;
                    if ($submenuId < 1 || !is_array($flags)) {
                        continue;
                    }

                    $canView = !empty($flags['can_view']) ? 1 : 0;
                    $canInsert = !empty($flags['can_insert']) ? 1 : 0;
                    $canDelete = !empty($flags['can_delete']) ? 1 : 0;
                    $canUpdate = !empty($flags['can_update']) ? 1 : 0;
                    $canStatus = !empty($flags['can_status']) ? 1 : 0;
                    if (($canView + $canInsert + $canDelete + $canUpdate + $canStatus) === 0) {
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
                        $canStatus,
                    ]);
                    $saved++;
                }

                // Workflow/action grants for this user are replaced atomically.
                $stDel = $connG->prepare('DELETE FROM user_privilege_actions WHERE User_ID = ?');
                $stDel->bind_param('i', $userId);
                $stDel->execute();
                $stDel->close();

                $knownActions = [];
                if ($resA = $connG->query('SELECT action_id, submenu_id FROM privilege_actions')) {
                    while ($rowA = $resA->fetch_assoc()) {
                        $knownActions[(int) $rowA['action_id']] = (int) $rowA['submenu_id'];
                    }
                    $resA->free();
                }

                $postedActions = $_POST['actions'] ?? [];
                $stIns = $connG->prepare('INSERT INTO user_privilege_actions (User_ID, action_id, granted) VALUES (?, ?, 1)');
                if (is_array($postedActions)) {
                    foreach ($postedActions as $submenuIdP => $actionFlags) {
                        $submenuIdP = (int) $submenuIdP;
                        if ($submenuIdP < 1 || !is_array($actionFlags)) {
                            continue;
                        }
                        foreach ($actionFlags as $actionIdP => $val) {
                            $actionIdP = (int) $actionIdP;
                            if (($knownActions[$actionIdP] ?? 0) === $submenuIdP && !empty($val)) {
                                $stIns->bind_param('ii', $userId, $actionIdP);
                                $stIns->execute();
                                $grantedActions++;
                            }
                        }
                    }
                }
                $stIns->close();

                // Dashboard widget grants (explicit on/off stored for the user).
                $postedWidgets = $_POST['widgets'] ?? [];
                $widgetKeys = [];
                if ($resW = $connG->query('SELECT widget_key FROM dashboard_widgets WHERE status = "active"')) {
                    while ($rowW = $resW->fetch_assoc()) {
                        $widgetKeys[] = (string) $rowW['widget_key'];
                    }
                    $resW->free();
                }
                $stW = $connG->prepare(
                    'INSERT INTO user_dashboard_widgets (User_ID, widget_key, granted) VALUES (?, ?, ?)
                     ON DUPLICATE KEY UPDATE granted = VALUES(granted)'
                );
                foreach ($widgetKeys as $widgetKey) {
                    $grantedW = !empty($postedWidgets[$widgetKey]) ? 1 : 0;
                    $stW->bind_param('isi', $userId, $widgetKey, $grantedW);
                    $stW->execute();
                    $widgetSaved++;
                }
                $stW->close();
                $connG->commit();
            } catch (Throwable $e) {
                if ($connG->in_transaction()) {
                    $connG->rollback();
                }
                throw $e;
            }

            clinic_flash($saved . ' privilege row' . ($saved === 1 ? '' : 's') . ', ' . $grantedActions . ' action grant' . ($grantedActions === 1 ? '' : 's') . ' and ' . $widgetSaved . ' dashboard widget' . ($widgetSaved === 1 ? '' : 's') . ' saved.');
            clinic_redirect('user_privileges.php?user_id=' . $userId);
        }
    }
} catch (Throwable $e) {
    clinic_flash($e->getMessage(), 'danger');
    clinic_redirect('user_privileges.php');
}

$users = clinic_sp_rows('sp_users_list');
$submenus = clinic_sp_rows('sp_submenues_list');

// Only an explicit, valid selection is honoured — never auto-pick the first user.
$selectedUserId = 0;
$selectedUser = null;
$rawUserId = (int) ($_GET['user_id'] ?? 0);
if ($rawUserId > 0) {
    foreach ($users as $user) {
        if ((int) ($user['User_ID'] ?? 0) === $rawUserId) {
            $selectedUserId = $rawUserId;
            $selectedUser = $user;
            break;
        }
    }
}

// Access checks already stored for the chosen user.
$existing = [];
if ($selectedUserId > 0) {
    foreach (clinic_sp_rows('sp_user_privileges_list') as $row) {
        if ((int) ($row['User_ID'] ?? 0) === $selectedUserId) {
            $existing[(int) ($row['submenu_id'] ?? 0)] = $row;
        }
    }
}

// Workflow actions definitions + what the selected user is granted.
$privilegeActionDefs = [];
$grantedActionSet = [];
if ($selectedUserId > 0) {
    $connP = $GLOBALS['conn'] ?? null;
    if ($connP instanceof mysqli) {
        if ($resP = $connP->query('SELECT pa.action_id, pa.submenu_id, pa.action_key, pa.action_label FROM privilege_actions pa ORDER BY pa.submenu_id, pa.sort_order, pa.action_id')) {
            while ($rowP = $resP->fetch_assoc()) {
                $subIdP = (int) ($rowP['submenu_id'] ?? 0);
                $privilegeActionDefs[$subIdP][] = $rowP;
            }
            $resP->free();
        }
        $stP = $connP->prepare('SELECT action_id FROM user_privilege_actions WHERE User_ID = ? AND granted = 1');
        $stP->bind_param('i', $selectedUserId);
        $stP->execute();
        $resP = $stP->get_result();
        while ($rowP = $resP->fetch_assoc()) {
            $grantedActionSet[(int) ($rowP['action_id'] ?? 0)] = true;
        }
        $stP->close();
    }
}
$grantedRowCount = count($existing);
$grantedActionCount = count($grantedActionSet);

// Dashboard widget definitions + explicit per-user grants.
$widgetDefs = [];
$widgetState = [];
if ($selectedUserId > 0) {
    $connW = $GLOBALS['conn'] ?? null;
    if ($connW instanceof mysqli) {
        if ($resW = $connW->query('SELECT widget_key, widget_label, module_key, sort_order FROM dashboard_widgets WHERE status = "active" ORDER BY sort_order, widget_key')) {
            $widgetDefs = $resW->fetch_all(MYSQLI_ASSOC);
            $resW->free();
        }
        $stW = $connW->prepare('SELECT widget_key, granted FROM user_dashboard_widgets WHERE User_ID = ?');
        $stW->bind_param('i', $selectedUserId);
        $stW->execute();
        $resW = $stW->get_result();
        while ($rowW = $resW->fetch_assoc()) {
            $widgetState[(string) ($rowW['widget_key'] ?? '')] = (int) ($rowW['granted'] ?? 0) === 1;
        }
        $stW->close();
    }
}

// Group submenus under their parent module so every dashboard card is shown.
$menusGrouped = [];
foreach ($submenus as $submenu) {
    $menuName = trim((string) ($submenu['menu_name'] ?? ''));
    if ($menuName === '') {
        $menuName = 'Other';
    }
    $menusGrouped[$menuName][] = $submenu;
}

clinic_page_start('User Privileges', 'Grant per-module access checks — view, insert, delete and update.');
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
        white-space: nowrap;
    }
    .permission-check {
        align-items: center;
        display: inline-flex;
        justify-content: center;
        min-width: 72px;
    }
    /* Small checkboxes with a clearly visible border */
    .permission-check .form-check-input.privilege-check {
        width: 1.15rem !important;
        height: 1.15rem !important;
        border: 2px solid #9aa6b2 !important;
        border-radius: .3rem !important;
        cursor: pointer;
        margin: 0;
    }
    .permission-check .form-check-input.privilege-check:checked {
        background-color: var(--primary, #4f6df5) !important;
        border-color: var(--primary, #4f6df5) !important;
    }
    .permission-check .form-check-input.privilege-check:focus {
        box-shadow: 0 0 0 .15rem rgba(79, 109, 245, .2);
    }
    .priv-group-header td {
        background: rgba(var(--primary-rgb), .05);
        color: var(--primary, #4f6df5);
        font-weight: 700;
        font-size: .78rem;
        letter-spacing: .05em;
        text-transform: uppercase;
        border-top: 2px solid rgba(var(--primary-rgb), .25);
    }
    .priv-group-header .module-access-toggle {
        width: 1.05rem !important;
        height: 1.05rem !important;
        border: 2px solid var(--primary, #4f6df5) !important;
        border-radius: .3rem !important;
        cursor: pointer;
    }
    .priv-group-header label {
        cursor: pointer;
    }
    .priv-action-row td {
        background: #fbfcfe;
        border-top: 1px dashed #e2e8f0;
    }
    .priv-empty {
        border: 1px dashed var(--border-color);
        border-radius: 16px;
        padding: 2.75rem 1rem;
        text-align: center;
    }
    .priv-empty i {
        font-size: 2.6rem;
        color: var(--primary, #4f6df5);
        opacity: .55;
    }
</style>
<!-- Step 1: choose the user (the matrix below stays hidden until a user is picked) -->
<div class="card clinic-card mb-3">
    <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <h5 class="mb-1"><i class="ti ti-user-cog me-1 text-primary"></i>Choose a user</h5>
            <div class="text-muted small">Pick the account, then press <strong>Load</strong> to fetch its access checks.</div>
        </div>
        <form class="d-flex flex-wrap align-items-center gap-2" method="get" id="privLoadForm">
            <label class="form-label mb-0 fw-semibold" for="privUserSelect">User</label>
            <select class="form-select" name="user_id" id="privUserSelect" style="min-width: 240px;" <?php echo $users === [] ? 'disabled' : ''; ?>>
                <option value="">-- Select user --</option>
                <?php clinic_select_options($users, 'User_ID', 'Username', $selectedUserId); ?>
            </select>
            <button class="btn btn-primary" type="submit" id="privLoadBtn" <?php echo $users === [] ? 'disabled' : ''; ?>>
                <i class="ti ti-arrow-right me-1"></i>Load
            </button>
        </form>
    </div>
</div>

<?php if ($users === []): ?>
<div class="alert alert-warning border"><i class="ti ti-alert-triangle me-1"></i>No user accounts found yet — add users from the <strong>Users</strong> page first.</div>

<?php elseif ($selectedUserId > 0): ?>
<!-- Step 2: permission matrix for the loaded user -->
<form method="post" id="privForm">
    <input type="hidden" name="csrf_token" value="<?php echo clinic_h(clinic_csrf_token()); ?>">
    <input type="hidden" name="action" value="save_privileges">
    <input type="hidden" name="User_ID" value="<?php echo (int) $selectedUserId; ?>">

    <div class="card clinic-card">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <h5 class="mb-1"><i class="ti ti-shield-lock me-1 text-primary"></i>Permission Matrix</h5>
                <div class="text-muted small">
                    Access checks for
                    <strong>@<?php echo clinic_h((string) ($selectedUser['Username'] ?? '')); ?></strong>
                    <span class="text-muted">(<?php echo clinic_h((string) ($selectedUser['Role_Name'] ?? 'Role')); ?>)</span>
                    — tick what this user may do.
                    <?php if ($selectedUserId > 0): ?>
                    <span class="badge text-bg-info ms-1"><?php echo (int) $grantedRowCount; ?> module rows</span>
                    <span class="badge text-bg-light ms-1"><?php echo (int) $grantedActionCount; ?> workflow grants</span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="d-flex flex-wrap align-items-center gap-2">
                <button type="button" class="btn btn-outline-success btn-sm" id="privSelectAll"><i class="ti ti-checkbox me-1"></i>Select All</button>
                <button type="button" class="btn btn-outline-secondary btn-sm" id="privUnselectAll"><i class="ti ti-checkbox-off me-1"></i>Unselect All</button>
                <button class="btn btn-primary btn-sm" type="submit"><i class="ti ti-device-floppy me-1"></i>Save Privileges</button>
            </div>
        </div>
        <div class="card-body table-responsive">
            <table class="table table-hover align-middle privilege-table mb-0">
                <thead>
                    <tr>
                        <th>Module</th>
                        <th>Submenu</th>
                        <th class="text-center">Can View</th>
                        <th class="text-center">Can Insert</th>
                        <th class="text-center">Can Delete</th>
                        <th class="text-center">Can Update</th>
                        <th class="text-center" title="Approve/start/complete/cancel — status changes">Status</th>
                    </tr>
                </thead>
                <tbody id="privMatrixBody">                    <?php foreach ($menusGrouped as $menuName => $menuSubs): ?>
                    <?php
                    $anyGranted = false;
                    foreach ($menuSubs as $ms) {
                        $mRow = $existing[(int) ($ms['submenu_id'] ?? 0)] ?? [];
                        if (!empty($mRow['can_view']) || !empty($mRow['can_insert']) || !empty($mRow['can_delete']) || !empty($mRow['can_update']) || !empty($mRow['can_status'])) {
                            $anyGranted = true;
                            break;
                        }
                    }
                    ?>
                    <tr class="priv-group-header">
                        <td colspan="7">
                            <label class="form-check-label d-inline-flex align-items-center gap-2 mb-0" title="Grant access to the whole card">
                                <input class="form-check-input module-access-toggle m-0" type="checkbox">
                                <i class="ti ti-folder me-1"></i><?php echo clinic_h($menuName); ?>
                            </label>
                            <?php if ($anyGranted): ?><span class="badge text-bg-success ms-1">granted</span><?php endif; ?>
                            <span class="badge text-bg-light ms-1"><?php echo count($menuSubs); ?></span>
                            <span class="small text-muted ms-2">card access</span>
                        </td>
                    </tr>
                    <?php foreach ($menuSubs as $submenu): ?>
                    <?php
                    $submenuId = (int) ($submenu['submenu_id'] ?? 0);
                    $row = $existing[$submenuId] ?? [];
                    ?>
                    <tr>
                        <td class="text-muted small"></td>
                        <td>
                            <div class="fw-semibold"><?php echo clinic_h($submenu['submenu_name'] ?? '-'); ?></div>
                            <?php if (!empty($submenu['menu_url'])): ?>
                            <div class="small text-muted font-monospace"><?php echo clinic_h($submenu['menu_url']); ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="text-center"><label class="permission-check"><input class="form-check-input privilege-check perm-cb" type="checkbox" name="permissions[<?php echo $submenuId; ?>][can_view]" value="1"<?php echo !empty($row['can_view']) ? ' checked' : ''; ?>></label></td>
                        <td class="text-center"><label class="permission-check"><input class="form-check-input privilege-check perm-cb" type="checkbox" name="permissions[<?php echo $submenuId; ?>][can_insert]" value="1"<?php echo !empty($row['can_insert']) ? ' checked' : ''; ?>></label></td>
                        <td class="text-center"><label class="permission-check"><input class="form-check-input privilege-check perm-cb" type="checkbox" name="permissions[<?php echo $submenuId; ?>][can_delete]" value="1"<?php echo !empty($row['can_delete']) ? ' checked' : ''; ?>></label></td>
                        <td class="text-center"><label class="permission-check"><input class="form-check-input privilege-check perm-cb" type="checkbox" name="permissions[<?php echo $submenuId; ?>][can_update]" value="1"<?php echo !empty($row['can_update']) ? ' checked' : ''; ?>></label></td>
                        <td class="text-center"><label class="permission-check" title="Approve/start/complete/cancel — status changes"><input class="form-check-input privilege-check perm-cb" type="checkbox" name="permissions[<?php echo $submenuId; ?>][can_status]" value="1"<?php echo !empty($row['can_status']) ? ' checked' : ''; ?>></label></td>
                    </tr>
                    <?php $subActions = $privilegeActionDefs[$submenuId] ?? []; ?>
                    <?php if ($subActions !== []): ?>
                    <tr class="priv-action-row">
                        <td class="text-muted small"></td>
                        <td class="small text-muted text-nowrap pe-1"><i class="ti ti-bolt me-1 text-primary"></i>Workflow</td>
                        <td colspan="5">
                            <?php foreach ($subActions as $act): ?>
                            <?php $actId = (int) ($act['action_id'] ?? 0); ?>
                            <label class="permission-check me-2 mb-1" title="<?php echo clinic_h($act['action_key']); ?>">
                                <span class="small text-muted me-1"><?php echo clinic_h($act['action_label']); ?></span>
                                <input class="form-check-input privilege-check perm-cb" type="checkbox" name="actions[<?php echo $submenuId; ?>][<?php echo $actId; ?>]" value="1"<?php echo !empty($grantedActionSet[$actId]) ? ' checked' : ''; ?>>
                            </label>
                            <?php endforeach; ?>
                        </td>
                    </tr>
                    <?php endif; ?>
                    <?php endforeach; ?>
                    <?php endforeach; ?>
                    <?php if ($submenus === []): ?>
                    <tr><td class="text-center text-muted py-4" colspan="7">No submenus found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="card-footer d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span class="text-muted small"><i class="ti ti-info-circle me-1"></i>Only rows that have at least one check will be saved.</span>
            <button class="btn btn-primary" type="submit"><i class="ti ti-device-floppy me-1"></i>Save Privileges</button>
        </div>
    </div>

    <!-- Dashboard widgets: per-card/chart access for the home page -->
    <div class="card clinic-card mt-3">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <h5 class="mb-0"><i class="ti ti-dashboard me-1 text-primary"></i>Dashboard Widgets</h5>
                <div class="text-muted small">Cards/charts shown on the home page. An unticked widget is stored as <em>explicitly off</em> for this user.</div>
            </div>
        </div>
        <div class="card-body">
            <?php if ($widgetDefs === []): ?>
                <p class="text-muted mb-0">No dashboard widgets defined.</p>
            <?php else: ?>
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <button type="button" class="btn btn-sm btn-outline-success" id="privWidgetAll"><i class="ti ti-checkbox me-1"></i>Tick all widgets</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="privWidgetNone"><i class="ti ti-checkbox-off me-1"></i>Untick all widgets</button>
                </div>
                <div class="d-flex flex-wrap gap-2 mt-2">
                    <?php foreach ($widgetDefs as $wd): ?>
                    <?php $wdKey = (string) ($wd['widget_key'] ?? ''); ?>
                    <label class="permission-check border rounded px-3 py-2 me-0" title="<?php echo clinic_h((string) ($wd['module_key'] ?? '')); ?>">
                        <span class="small text-muted me-2"><?php echo clinic_h((string) ($wd['widget_label'] ?? $wdKey)); ?></span>
                        <input class="form-check-input privilege-check widget-cb" type="checkbox" name="widgets[<?php echo clinic_h($wdKey); ?>]" value="1"<?php echo ($widgetState[$wdKey] ?? true) ? ' checked' : ''; ?>>
                    </label>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</form>

<?php else: ?>
<!-- Matrix hidden until a user is chosen and Load is pressed -->
<div class="priv-empty">
    <i class="ti ti-user-shield d-block mb-2"></i>
    <h5 class="fw-bold">Select a user to begin</h5>
    <p class="text-muted mb-0">Pick an account above and click <strong>Load</strong> — the access checks that user already has will be loaded and ticked for you.</p>
</div>
<?php endif; ?>
<script>
(function () {
    'use strict';
    var select = document.getElementById('privUserSelect');
    var loadForm = document.getElementById('privLoadForm');

    // Load only fires when a user is actually chosen. If the selection is
    // empty the page simply stays put and the matrix is never requested.
    if (loadForm && select) {
        loadForm.addEventListener('submit', function (event) {
            if (!select.value) {
                event.preventDefault();
                select.classList.add('is-invalid');
                select.focus();
                return;
            }
        });
        if (select.addEventListener) {
            select.addEventListener('change', function () { select.classList.remove('is-invalid'); });
        }
    }

    var matrix = document.getElementById('privMatrixBody');
    if (!matrix) { return; }

    // Permission checkboxes only (the module/card toggles are UI helpers).
    function allChecks() {
        return Array.prototype.slice.call(matrix.querySelectorAll('input.perm-cb'));
    }

    var selAll = document.getElementById('privSelectAll');
    var unselAll = document.getElementById('privUnselectAll');
    if (selAll) {
        selAll.addEventListener('click', function () { allChecks().forEach(function (c) { c.checked = true; }); });
    }
    if (unselAll) {
        unselAll.addEventListener('click', function () { allChecks().forEach(function (c) { c.checked = false; }); });
    }

    // Module ("card") access checkbox: grants/revokes access for every submenu
    // row that belongs to that card.
    matrix.addEventListener('change', function (event) {
        var t = event.target;
        if (!t || !t.classList || !t.classList.contains('module-access-toggle')) {
            return;
        }
        var row = t.closest('tr');
        var el = row ? row.nextElementSibling : null;
        while (el) {
            if (el.classList && el.classList.contains('priv-group-header')) {
                break;
            }
            el.querySelectorAll('input.perm-cb').forEach(function (c) { c.checked = t.checked; });
            el = el.nextElementSibling;
        }
    });

    // Dashboard widget tick-all / untick-all.
    var widgetAll = document.getElementById('privWidgetAll');
    var widgetNone = document.getElementById('privWidgetNone');
    if (widgetAll) {
        widgetAll.addEventListener('click', function () {
            Array.prototype.slice.call(document.querySelectorAll('input.widget-cb')).forEach(function (c) { c.checked = true; });
        });
    }
    if (widgetNone) {
        widgetNone.addEventListener('click', function () {
            Array.prototype.slice.call(document.querySelectorAll('input.widget-cb')).forEach(function (c) { c.checked = false; });
        });
    }
})();
</script>

<?php clinic_page_end(); ?>