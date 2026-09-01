<?php
require_once __DIR__ . '/../includes/advanced_components.php';
require_once __DIR__ . '/../config/auth_login.php';

$roles = clinic_sp_rows('sp_roles_list');
$staffWithoutUsers = clinic_sp_rows('sp_staff_without_users');
$users = clinic_sp_rows('sp_users_list');

// map: user account -> linked staff row (for display)
$staffByUser = [];
foreach (clinic_sp_rows('sp_staff_list') as $st) {
    $uid = (int) ($st['User_ID'] ?? 0);
    if ($uid > 0) {
        $staffByUser[$uid] = $st;
    }
}

/**
 * Generate an activation token for a user and send the activation email with a
 * direct link the user clicks to set their own password (account is inactive
 * until then).
 *
 * @return array{ok: bool, error: string}
 */
function clinic_send_user_activation(int $uid): array
{
    global $conn;
    global $staffByUser;

    $row = clinic_sp_one('sp_users_get', [$uid], 'i');
    if (!$row) {
        return ['ok' => false, 'error' => 'User not found.'];
    }

    $username = (string) ($row['Username'] ?? '');
    $email = (string) ($row['email'] ?? '');
    if ($email === '' && isset($staffByUser[$uid]['Email'])) {
        $email = (string) $staffByUser[$uid]['Email'];
    }
    if ($email === '') {
        return ['ok' => false, 'error' => 'This user has no email address on file. Add one on the Staff page.'];
    }

    $token = bin2hex(random_bytes(32));
    $expiresAt = date('Y-m-d H:i:s', time() + 72 * 3600);

    $stmt = $conn->prepare('UPDATE users SET activation_token = ?, activation_expires_at = ? WHERE User_ID = ?');
    $stmt->bind_param('ssi', $token, $expiresAt, $uid);
    $stmt->execute();
    $stmt->close();

    require_once __DIR__ . '/../config/mailer.php';
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    $appRoot = rtrim(dirname(dirname(str_replace('\\', '/', (string) $_SERVER['SCRIPT_NAME']))), '/');
    $activateUrl = $scheme . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $appRoot . '/activate-account.php?token=' . urlencode($token);

    return clinic_send_mail(
        $email,
        $username,
        'Activate your account',
        '<div style="font-family:Arial,sans-serif;max-width:480px;margin:0 auto;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden">'
            . '<div style="background:#0d6efd;padding:18px 24px"><span style="color:#fff;font-size:18px;font-weight:bold">Dabiib System</span></div>'
            . '<div style="padding:24px">'
            . '<h2 style="margin:0 0 8px;font-size:20px">Activate your account</h2>'
            . '<p style="margin:0 0 16px;color:#4b5563;font-size:14px">Hello <strong>' . clinic_h($username) . '</strong>, an account has been created for you. Click the button below to set your password and activate your account.</p>'
            . '<p style="text-align:center;margin:0 0 16px"><a href="' . clinic_h($activateUrl) . '" style="display:inline-block;background:#0d6efd;color:#fff;text-decoration:none;padding:10px 24px;border-radius:8px;font-weight:bold">Set password &amp; activate</a></p>'
            . '<p style="margin:0;color:#9ca3af;font-size:12px">The link expires in 72 hours. If you did not expect this email, you can safely ignore it.</p>'
            . '</div></div>',
        "Set your password and activate your account: {$activateUrl}"
    );
}

/**
 * Generate a password-reset token and email the user a direct link they can
 * click to set a new password (for already-active accounts).
 *
 * @return array{ok: bool, error: string}
 */
function clinic_send_user_reset(int $uid): array
{
    global $conn;
    global $staffByUser;

    $row = clinic_sp_one('sp_users_get', [$uid], 'i');
    if (!$row) {
        return ['ok' => false, 'error' => 'User not found.'];
    }

    $username = (string) ($row['Username'] ?? '');
    $email = (string) ($row['email'] ?? '');
    if ($email === '' && isset($staffByUser[$uid]['Email'])) {
        $email = (string) $staffByUser[$uid]['Email'];
    }
    if ($email === '') {
        return ['ok' => false, 'error' => 'This user has no email address on file. Add one on the Staff page.'];
    }

    $token = bin2hex(random_bytes(32));
    $expiresAt = date('Y-m-d H:i:s', time() + 72 * 3600);

    $stmt = $conn->prepare('UPDATE users SET reset_token = ?, reset_expires_at = ? WHERE User_ID = ?');
    $stmt->bind_param('ssi', $token, $expiresAt, $uid);
    $stmt->execute();
    $stmt->close();

    require_once __DIR__ . '/../config/mailer.php';
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    $appRoot = rtrim(dirname(dirname(str_replace('\\', '/', (string) $_SERVER['SCRIPT_NAME']))), '/');
    $resetUrl = $scheme . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $appRoot . '/reset-password-link.php?token=' . urlencode($token);

    return clinic_send_mail(
        $email,
        $username,
        'Reset your password',
        '<div style="font-family:Arial,sans-serif;max-width:480px;margin:0 auto;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden">'
            . '<div style="background:#0d6efd;padding:18px 24px"><span style="color:#fff;font-size:18px;font-weight:bold">Dabiib System</span></div>'
            . '<div style="padding:24px">'
            . '<h2 style="margin:0 0 8px;font-size:20px">Reset your password</h2>'
            . '<p style="margin:0 0 16px;color:#4b5563;font-size:14px">Hello <strong>' . clinic_h($username) . '</strong>, click the button below to set a new password for your account.</p>'
            . '<p style="text-align:center;margin:0 0 16px"><a href="' . clinic_h($resetUrl) . '" style="display:inline-block;background:#0d6efd;color:#fff;text-decoration:none;padding:10px 24px;border-radius:8px;font-weight:bold">Set new password</a></p>'
            . '<p style="margin:0;color:#9ca3af;font-size:12px">The link expires in 72 hours. If you did not request this, you can safely ignore it.</p>'
            . '</div></div>',
        "Set a new password for your account: {$resetUrl}"
    );
}

$errors = [];
$editId = (int) ($_GET['edit'] ?? 0);
$editRow = $editId > 0 ? clinic_sp_one('sp_users_get', [$editId], 'i') : null;

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = clinic_post_string('action');
    try {
        clinic_check_csrf();

        if ($action === 'delete_user') {
            $uid = clinic_post_int('User_ID');
            if ($uid < 1) {
                throw new RuntimeException('Invalid user.');
            }
            $target = clinic_sp_one('sp_users_get', [$uid], 'i');
            if ($target && (int) ($target['Role_ID'] ?? 0) === 1) {
                throw new RuntimeException('The admin account cannot be deleted.');
            }
            global $conn;
            $stmt = $conn->prepare('UPDATE staff SET User_ID = NULL WHERE User_ID = ?');
            $stmt->bind_param('i', $uid);
            $stmt->execute();
            $stmt->close();
            clinic_sp_exec('sp_users_delete', [$uid], 'i');
            clinic_audit_log('User deleted', 'User account deleted (#' . $uid . ')', 'user', $uid);
            clinic_flash('User deleted.');
            clinic_redirect('users.php');
        }

        if ($action === 'send_activation') {
            $uid = clinic_post_int('User_ID');
            $result = clinic_send_user_activation($uid);
            if ($result['ok']) {
                clinic_audit_log('Activation email sent', 'Activation email sent for user (#' . $uid . ')', 'user', $uid);
                clinic_flash('Activation email sent.');
            } else {
                clinic_flash($result['error'], 'warning');
            }
            clinic_redirect('users.php');
        }

        if ($action === 'send_reset') {
            $uid = clinic_post_int('User_ID');
            $result = clinic_send_user_reset($uid);
            if ($result['ok']) {
                clinic_audit_log('Password reset link sent', 'Password reset link emailed for user (#' . $uid . ')', 'user', $uid);
                clinic_flash('Password reset link sent.');
            } else {
                clinic_flash($result['error'], 'warning');
            }
            clinic_redirect('users.php');
        }

        if ($action === 'save_user') {
            $userId = clinic_post_int('User_ID');
            $username = clinic_post_string('Username');
            $password = (string) ($_POST['Password_Hash'] ?? '');
            $status = clinic_post_string('status') ?: 'active';
            $staffId = clinic_post_int('Staff_ID');

            if ($username === '') {
                throw new RuntimeException('Username is required.');
            }

            $isNew = $userId === 0;
            global $conn;

            // The role lives on the Staff record — new accounts inherit it.
            if ($isNew) {
                $roleId = 0;
                if ($staffId > 0) {
                    $res = $conn->query('SELECT Role_ID FROM staff WHERE Staff_ID = ' . (int) $staffId);
                    $roleId = (int) (($res ? $res->fetch_assoc() : null)['Role_ID'] ?? 0);
                }
                if ($roleId < 1) {
                    throw new RuntimeException('Role is required. Select a role on the Staff page first.');
                }
            } else {
                $roleId = (int) ($editRow['Role_ID'] ?? 0);
                if ($roleId < 1) {
                    throw new RuntimeException('Role is required.');
                }
            }

            // The email lives on the Staff record — copy it onto the account so
            // login features (password reset, SMTP test, activation) keep working.
            $email = '';
            if ($isNew && $staffId > 0) {
                $res = $conn->query('SELECT Email FROM staff WHERE Staff_ID = ' . (int) $staffId);
                $email = (string) (($res ? $res->fetch_assoc() : null)['Email'] ?? '');
            }

            if ($isNew) {
                // New accounts are created WITHOUT a password and stay inactive
                // until the user follows the activation email and sets their
                // own password (at which point the account becomes active).
                $hash = '';
                $status = 'inactive';
            } else {
                $hash = $password !== ''
                    ? clinic_normalize_password_for_storage($password)
                    : (string) ($editRow['Password_Hash'] ?? '');
            }

            // The admin account can never be deactivated — it always keeps
            // full access to the system.
            if ($isNew ? $roleId === 1 : (int) ($editRow['Role_ID'] ?? 0) === 1) {
                $status = 'active';
            }

            $userId = $userId ?: clinic_sp_exec('sp_users_save', [$userId, $username, $hash, $roleId, $email, 'default-user.png', $status]);

            if ($isNew && $staffId > 0 && $userId > 0) {
                $stmt = $conn->prepare('UPDATE staff SET User_ID = ? WHERE Staff_ID = ? AND (User_ID IS NULL OR User_ID = 0)');
                $stmt->bind_param('ii', $userId, $staffId);
                $stmt->execute();
                $stmt->close();
            }

            clinic_audit_log($isNew ? 'User created' : 'User updated', ($isNew ? 'Created' : 'Updated') . ' user account: ' . $username, 'user', $userId > 0 ? $userId : null);
            if (!$isNew && $userId > 0) {
                clinic_notify('Account updated', 'Your account was updated: ' . $username, 'info', 'profile.php', $userId);
            }

            if ($isNew && $userId > 0) {
                // Send the activation email (best effort) so the user can set
                // their own password and activate the account.
                $sent = clinic_send_user_activation($userId);
                if ($sent['ok']) {
                    clinic_flash('User saved. Activation email sent.');
                } else {
                    clinic_flash('User saved. ' . $sent['error'], 'warning');
                }
                clinic_redirect('users.php');
            }

            clinic_flash('User updated.');
            clinic_redirect('users.php');
        }

        throw new RuntimeException('Unknown action.');
    } catch (Throwable $e) {
        $errors[] = $e->getMessage();
    }
}

// Metrics exclude the admin account — it is never counted against the totals.
$nonAdmins = array_values(array_filter($users, static fn ($u) => (int) ($u['Role_ID'] ?? 0) !== 1));
$totalUsers = count($nonAdmins);
$activeUsers = count(array_filter($nonAdmins, static fn ($u) => strtolower((string) ($u['status'] ?? '')) === 'active'));
$inactiveUsers = $totalUsers - $activeUsers;

clinic_page_start('Users');
?>
<div class="row g-3 mb-4">
    <?php clinic_metric_card('Total Users', $totalUsers, 'ti-users', 'primary', 'All accounts'); ?>
    <?php clinic_metric_card('Active Users', $activeUsers, 'ti-user-check', 'success', 'Currently active'); ?>
    <?php clinic_metric_card('Inactive Users', $inactiveUsers, 'ti-user-off', 'secondary', 'Inactive accounts'); ?>
</div>

<?php if ($errors !== []): ?>
<div class="alert alert-danger alert-dismissible fade show border border-danger" role="alert">
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    <strong>Error - </strong>
    <?php foreach ($errors as $e): ?><div><?php echo clinic_h($e); ?></div><?php endforeach; ?>
</div>
<?php endif; ?>

<div class="card clinic-card mb-3">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <h5 class="mb-0">Users</h5>
        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#userModal" data-user-mode="new">
            <i class="ti ti-user-plus me-1"></i> New User
        </button>
    </div>
    <div class="card-body pt-2">
        <div class="table-responsive">
            <table class="table table-hover align-middle clinic-table mb-0">
                <thead>
                    <tr>
                        <th>User ID</th>
                        <th>Username</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                    <?php
                    $uid = (int) ($u['User_ID'] ?? 0);
                    $linkedStaff = $staffByUser[$uid] ?? null;
                    ?>
                    <tr>
                        <td><span class="badge text-bg-light border font-monospace">USR-<?php echo str_pad((string) $uid, 4, '0', STR_PAD_LEFT); ?></span></td>
                        <td>
                            <div class="fw-semibold"><?php echo clinic_h($u['Username'] ?? '-'); ?></div>
                            <?php if ($linkedStaff): ?><div class="small text-muted"><?php echo clinic_h((string) ($linkedStaff['Full_Name'] ?? '')); ?></div><?php endif; ?>
                        </td>
                        <td>
                            <?php echo clinic_status_badge((string) ($u['status'] ?? 'active')); ?>
                            <?php if ((int) ($u['Role_ID'] ?? 0) === 1): ?>
                            <span class="badge text-bg-primary ms-1" title="Full system access">Admin</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <div class="d-inline-flex align-items-center gap-2">
                                <?php
                                $uStatus = strtolower((string) ($u['status'] ?? 'active'));
                                $isAdminRow = (int) ($u['Role_ID'] ?? 0) === 1;
                                ?>
                                <?php if ($uStatus === 'active'): ?>
                                <form method="post" class="d-inline">
                                    <input type="hidden" name="csrf_token" value="<?php echo clinic_h(clinic_csrf_token()); ?>">
                                    <input type="hidden" name="action" value="send_reset">
                                    <input type="hidden" name="User_ID" value="<?php echo $uid; ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-secondary btn-icon" title="Send reset password link"><i class="ti ti-key"></i></button>
                                </form>
                                <?php endif; ?>
                                <?php if (!$isAdminRow && $uStatus !== 'active'): ?>
                                <form method="post" class="d-inline">
                                    <input type="hidden" name="csrf_token" value="<?php echo clinic_h(clinic_csrf_token()); ?>">
                                    <input type="hidden" name="action" value="send_activation">
                                    <input type="hidden" name="User_ID" value="<?php echo $uid; ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-info btn-icon" title="Send activation"><i class="ti ti-mail"></i></button>
                                </form>
                                <form method="post" class="d-inline">
                                    <input type="hidden" name="csrf_token" value="<?php echo clinic_h(clinic_csrf_token()); ?>">
                                    <input type="hidden" name="action" value="send_activation">
                                    <input type="hidden" name="User_ID" value="<?php echo $uid; ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-warning btn-icon" title="Resend activation"><i class="ti ti-rotate"></i></button>
                                </form>
                                <?php endif; ?>
                                <button
                                    class="btn btn-sm btn-light border btn-icon"
                                    type="button"
                                    title="Edit"
                                    data-bs-toggle="modal"
                                    data-bs-target="#userModal"
                                    data-user-mode="edit"
                                    data-user-id="<?php echo $uid; ?>"
                                    data-username="<?php echo clinic_h($u['Username'] ?? ''); ?>"
                                    data-role-id="<?php echo (int) ($u['Role_ID'] ?? 0); ?>"
                                    data-role-name="<?php echo clinic_h($u['Role_Name'] ?? ''); ?>"
                                    data-status="<?php echo clinic_h($u['status'] ?? 'active'); ?>"
                                    data-staff-name="<?php echo clinic_h((string) ($linkedStaff['Full_Name'] ?? '')); ?>"
                                    data-staff-email="<?php echo clinic_h((string) ($linkedStaff['Email'] ?? '')); ?>"
                                    data-staff-phone="<?php echo clinic_h((string) ($linkedStaff['Phone_Number'] ?? '')); ?>"
                                ><i class="ti ti-pencil"></i></button>
                                <?php if (!$isAdminRow): ?>
                                <form method="post" class="d-inline js-confirm-delete" data-confirm-text="Delete this user?">
                                    <input type="hidden" name="csrf_token" value="<?php echo clinic_h(clinic_csrf_token()); ?>">
                                    <input type="hidden" name="action" value="delete_user">
                                    <input type="hidden" name="User_ID" value="<?php echo $uid; ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger btn-icon" title="Delete"><i class="ti ti-trash"></i></button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if ($users === []): ?>
                    <tr><td colspan="4" class="text-center text-muted py-4">No users registered yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>


<div class="modal fade" id="userModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-lg">
        <form method="post" class="modal-content" id="userForm" autocomplete="off">
            <div class="modal-header">
                <h5 class="modal-title" id="userModalTitle">New User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?php echo clinic_h(clinic_csrf_token()); ?>">
                <input type="hidden" name="action" value="save_user">
                <input type="hidden" name="User_ID" id="user_id" value="0">
                <div class="row">
                    <div class="col-md-6 mb-3" id="userStaffWrap">
                        <label class="form-label" for="user_staff">Linked staff <span class="text-muted small">(no account yet)</span></label>
                        <select class="form-select" id="user_staff" name="Staff_ID">
                            <option value="">-- Select staff --</option>
                            <?php foreach ($staffWithoutUsers as $sw): ?>
                            <option value="<?php echo (int) $sw['Staff_ID']; ?>"><?php echo clinic_h($sw['Full_Name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Staff who do not have a user account yet.</div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label" for="user_username">Username <span class="text-danger">*</span></label>
                        <input class="form-control" id="user_username" name="Username" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Role <span class="text-danger">*</span></label>
                        <input class="form-control" id="user_role_display" disabled>
                        <div class="form-text">The role comes from the linked staff member (Staff page).</div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label" for="user_status">Status</label>
                        <select class="form-select" id="user_status" name="status">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                        <div class="form-text">New accounts start <strong>inactive</strong> and become active once the user activates from the email.</div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-danger" data-bs-dismiss="modal"><i class="ti ti-x me-1"></i>Cancel</button>
                <button type="submit" class="btn btn-success" id="userBtnUpdate" style="display:none;"><i class="ti ti-edit me-1"></i>Update User</button>
                <button type="submit" class="btn btn-primary" id="userBtnSave"><i class="ti ti-device-floppy me-1"></i>Save User</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('show.bs.modal', function (event) {
    var trigger = event.relatedTarget;
    if (!trigger || event.target.id !== 'userModal') {
        return;
    }
    var form = document.getElementById('userForm');
    var title = document.getElementById('userModalTitle');
    var isEdit = trigger.getAttribute('data-user-mode') === 'edit';

    form.reset();
    document.getElementById('user_id').value = '0';

    var staffWrap = document.getElementById('userStaffWrap');
    var btnSave = document.getElementById('userBtnSave');
    var btnUpdate = document.getElementById('userBtnUpdate');

    if (isEdit) {
        document.getElementById('user_id').value = trigger.getAttribute('data-user-id') || '0';
        document.getElementById('user_username').value = trigger.getAttribute('data-username') || '';
        document.getElementById('user_role_display').value = trigger.getAttribute('data-role-name') || '—';
        document.getElementById('user_status').value = trigger.getAttribute('data-status') || 'active';
        // Show the linked staff (FK) data read-only while editing.
        var staffName = trigger.getAttribute('data-staff-name') || '';
        var staffEmail = trigger.getAttribute('data-staff-email') || '';
        var staffPhone = trigger.getAttribute('data-staff-phone') || '';
        if (staffWrap) {
            var extra = [];
            if (staffEmail) { extra.push(staffEmail); }
            if (staffPhone) { extra.push(staffPhone); }
            staffWrap.innerHTML = '<label class="form-label">Linked staff</label>'
                + '<input class="form-control" value="' + (staffName || '—') + (extra.length ? ' (' + extra.join(' • ') + ')' : '') + '" disabled>';
        }
        var statusHint = document.getElementById('user_status').closest('.mb-3').querySelector('.form-text');
        if (statusHint) { statusHint.style.display = 'none'; }
        title.textContent = 'Edit User';
        btnSave.style.display = 'none';
        btnUpdate.style.display = '';
    } else {
        if (staffWrap) {
            staffWrap.innerHTML = '<label class="form-label">Linked staff <span class="text-muted small">(no account yet)</span></label>'
                + '<select class="form-select" id="user_staff" name="Staff_ID"><option value="">-- Select staff --</option>'
                + '<?php
                    $opts = '';
                    foreach ($staffWithoutUsers as $sw) {
                        $opts .= '<option value="' . (int) $sw['Staff_ID'] . '">' . clinic_h($sw['Full_Name']) . '</option>';
                    }
                    echo $opts;
                ?>'
                + '</select><div class="form-text">Staff who do not have a user account yet.</div>';
        }
        document.getElementById('user_role_display').value = '— from linked staff —';
        var statusHint = document.getElementById('user_status').closest('.mb-3').querySelector('.form-text');
        if (statusHint) { statusHint.style.display = ''; }
        title.textContent = 'New User';
        btnSave.style.display = '';
        btnUpdate.style.display = 'none';
    }
});
</script>
<?php clinic_page_end(); ?>

