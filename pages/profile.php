<?php
require_once __DIR__ . '/../includes/advanced_components.php';
require_once __DIR__ . '/../config/auth_login.php';

if (!isLoggedIn()) {
    requireLogin();
}

$userId = (int) ($_SESSION['user_no'] ?? $_SESSION['user_id'] ?? 0);
$user = clinic_sp_one('sp_users_get', [$userId], 'i');
if (!$user) {
    requireLogin();
}

global $conn;

// Role name + linked staff + doctor profile for the current account.
$roleName = '';
$staffRow = null;
$doctorRow = null;
$res = $conn->query('SELECT r.Role_Name FROM users u LEFT JOIN roles r ON r.Role_ID = u.Role_ID WHERE u.User_ID = ' . $userId);
if ($res && ($r0 = $res->fetch_assoc())) {
    $roleName = (string) ($r0['Role_Name'] ?? '');
}
$res = $conn->query('SELECT * FROM staff WHERE User_ID = ' . $userId . ' LIMIT 1');
if ($res && $res->num_rows > 0) {
    $staffRow = $res->fetch_assoc();
}
if ($staffRow) {
    $res = $conn->query('SELECT * FROM doctors WHERE User_ID = ' . $userId . ' OR Staff_ID = ' . (int) $staffRow['Staff_ID'] . ' LIMIT 1');
    if ($res && $res->num_rows > 0) {
        $doctorRow = $res->fetch_assoc();
    }
}

$errors = [];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = clinic_post_string('action');
    try {
        clinic_check_csrf();

        if ($action === 'update_details') {
            $fullName = clinic_post_string('Full_Name');
            $phone = clinic_post_string('Phone_Number');
            $email = clinic_post_string('Email');

            if ($fullName === '') {
                throw new RuntimeException('Full name is required.');
            }
            if (!$staffRow) {
                throw new RuntimeException('No staff record is linked to your account.');
            }

            clinic_sp_exec('sp_staff_save', [
                (int) $staffRow['Staff_ID'],
                $userId,
                (int) ($staffRow['Role_ID'] ?? 0),
                $fullName,
                $phone,
                $email,
                (string) ($staffRow['Credential_Or_Badge'] ?? ''),
                (string) ($staffRow['Credential_Type'] ?? ''),
                (string) ($staffRow['Notes'] ?? ''),
                (string) ($staffRow['status'] ?? 'active'),
            ]);

            clinic_flash('Profile details updated.');
            clinic_redirect('profile.php');
        }

        if ($action === 'update_photo') {
            $image = clinic_handle_avatar_upload('image');
            $stmt = $conn->prepare('UPDATE users SET image = ? WHERE User_ID = ?');
            $stmt->bind_param('si', $image, $userId);
            $stmt->execute();
            $stmt->close();

            $_SESSION['user_image'] = $image;
            clinic_flash('Profile picture updated.');
            clinic_redirect('profile.php');
        }

        if ($action === 'change_password') {
            $current = (string) ($_POST['current_password'] ?? '');
            $newPassword = (string) ($_POST['new_password'] ?? '');
            $confirm = (string) ($_POST['confirm_password'] ?? '');

            if ($current === '' || $newPassword === '' || $confirm === '') {
                throw new RuntimeException('All password fields are required.');
            }
            if (strlen($newPassword) < 6) {
                throw new RuntimeException('New password must be at least 6 characters.');
            }
            if ($newPassword !== $confirm) {
                throw new RuntimeException('New password and confirm password do not match.');
            }
            if (!verify_clinic_password($current, (string) ($user['Password_Hash'] ?? ''))) {
                throw new RuntimeException('Current password is incorrect.');
            }

            $hash = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $conn->prepare('UPDATE users SET Password_Hash = ? WHERE User_ID = ?');
            $stmt->bind_param('si', $hash, $userId);
            $stmt->execute();
            $stmt->close();

            clinic_flash('Password changed successfully.');
            clinic_redirect('profile.php');
        }

        throw new RuntimeException('Unknown action.');
    } catch (Throwable $e) {
        $errors[] = $e->getMessage();
    }
}

// Refresh image from the session (fallback: letter avatar / default).
$currentImage = (string) ($_SESSION['user_image'] ?? ($user['image'] ?? 'default-user.png'));
$username = (string) ($user['Username'] ?? '');
$displayName = (string) ($staffRow['Full_Name'] ?? $username);
$userEmail = (string) ($staffRow['Email'] ?? ($user['email'] ?? ''));
$userPhone = (string) ($staffRow['Phone_Number'] ?? '');

clinic_page_start('My Profile');
?>
<style>
.profile-avatar-lg { width: 120px; height: 120px; border-radius: 50%; font-size: 2.6rem; border: 5px solid #fff; box-shadow: 0 8px 24px rgba(15,23,42,.10); }
.profile-avatar-lg.clinic-avatar-img { object-fit: cover; }
.profile-info-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 16px; }
.profile-info-item { background: #f8fafc; border: 1px solid #eef2f7; border-radius: .8rem; padding: 1rem; }
.profile-info-label { font-size: .72rem; text-transform: uppercase; letter-spacing: .05em; color: #6c757d; font-weight: 600; margin-bottom: 4px; }
.profile-info-value { font-size: .95rem; font-weight: 600; color: #0f172a; }
</style>

<div class="card clinic-card mb-3">
    <div class="card-body d-flex flex-column flex-md-row align-items-md-center gap-4">
        <div>
            <?php echo clinic_avatar($currentImage, $displayName, 'clinic-avatar profile-avatar-lg'); ?>
        </div>
        <div class="flex-grow-1">
            <h4 class="fw-bold mb-1"><?php echo clinic_h($displayName); ?></h4>
            <p class="text-muted mb-1">
                <i class="ti ti-user me-1"></i>@<?php echo clinic_h($username); ?>
                <?php if ($roleName !== ''): ?>
                <span class="mx-2">|</span><i class="ti ti-briefcase me-1"></i><?php echo clinic_h($roleName); ?>
                <?php endif; ?>
            </p>
            <div>
                <?php echo clinic_status_badge((string) ($user['status'] ?? 'active')); ?>
            </div>
        </div>
        <div class="text-md-end">
            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="tab" data-bs-target="#editTab"><i class="ti ti-edit me-1"></i>Edit Profile</button>
        </div>
    </div>
</div>

<?php if ($errors !== []): ?>
<div class="alert alert-danger alert-dismissible fade show border border-danger" role="alert">
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    <strong>Error - </strong>
    <?php foreach ($errors as $e): ?><div><?php echo clinic_h($e); ?></div><?php endforeach; ?>
</div>
<?php endif; ?>

<div class="card clinic-card">
    <div class="card-header">
        <ul class="nav nav-tabs card-header-tabs">
            <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#overviewTab"><i class="ti ti-info-circle me-1"></i>Overview</a></li>
            <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#editTab"><i class="ti ti-edit me-1"></i>Edit Info</a></li>
            <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#photoTab"><i class="ti ti-photo me-1"></i>Profile Picture</a></li>
            <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#securityTab"><i class="ti ti-lock me-1"></i>Security</a></li>
        </ul>
    </div>
    <div class="card-body">
        <div class="tab-content">
            <!-- OVERVIEW -->
            <div class="tab-pane fade show active" id="overviewTab">
                <h6 class="fw-bold text-uppercase small text-muted mb-3">Account</h6>
                <div class="profile-info-grid mb-4">
                    <div class="profile-info-item"><div class="profile-info-label">Username</div><div class="profile-info-value">@<?php echo clinic_h($username); ?></div></div>
                    <div class="profile-info-item"><div class="profile-info-label">Role</div><div class="profile-info-value"><?php echo clinic_h($roleName !== '' ? $roleName : '—'); ?></div></div>
                    <div class="profile-info-item"><div class="profile-info-label">Email</div><div class="profile-info-value text-break"><?php echo clinic_h($userEmail !== '' ? $userEmail : '—'); ?></div></div>
                    <div class="profile-info-item"><div class="profile-info-label">Phone</div><div class="profile-info-value"><?php echo clinic_h($userPhone !== '' ? $userPhone : '—'); ?></div></div>
                </div>
                <?php if ($doctorRow): ?>
                <h6 class="fw-bold text-uppercase small text-muted mb-3">Doctor</h6>
                <div class="profile-info-grid">
                    <div class="profile-info-item"><div class="profile-info-label">Specialization</div><div class="profile-info-value"><?php echo clinic_h((string) ($doctorRow['Specialization'] ?? '—')); ?></div></div>
                    <div class="profile-info-item"><div class="profile-info-label">Consultation fee</div><div class="profile-info-value"><?php echo clinic_money((float) ($doctorRow['Consultation_Fee'] ?? 0)); ?></div></div>
                </div>
                <?php endif; ?>
            </div>

            <!-- EDIT INFO -->
            <div class="tab-pane fade" id="editTab">
                <form method="post" autocomplete="off">
                    <input type="hidden" name="csrf_token" value="<?php echo clinic_h(clinic_csrf_token()); ?>">
                    <input type="hidden" name="action" value="update_details">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="p_full_name">Full Name <span class="text-danger">*</span></label>
                            <input class="form-control" id="p_full_name" name="Full_Name" required value="<?php echo clinic_h((string) ($staffRow['Full_Name'] ?? $displayName)); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="p_phone">Phone</label>
                            <input class="form-control" id="p_phone" name="Phone_Number" type="tel" value="<?php echo clinic_h((string) ($staffRow['Phone_Number'] ?? '')); ?>">
                        </div>
                        <div class="col-12 mb-3">
                            <label class="form-label" for="p_email">Email</label>
                            <input class="form-control" id="p_email" name="Email" type="email" value="<?php echo clinic_h((string) ($staffRow['Email'] ?? '')); ?>">
                        </div>
                    </div>
                    <?php if (!$staffRow): ?>
                    <div class="alert alert-warning alert-dismissible fade show border border-warning small" role="alert">
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        <strong>Note - </strong> No staff record is linked to your account, so only the account-level fields can be edited by the administrator.
                    </div>
                    <?php endif; ?>
                    <div class="text-end">
                        <button type="submit" class="btn btn-primary btn-sm"><i class="ti ti-device-floppy me-1"></i>Save Info</button>
                    </div>
                </form>
            </div>

            <!-- PROFILE PICTURE -->
            <div class="tab-pane fade" id="photoTab">
                <div class="text-center p-3">
                    <?php echo clinic_avatar($currentImage, $displayName, 'clinic-avatar profile-avatar-lg mb-3'); ?>
                    <form method="post" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?php echo clinic_h(clinic_csrf_token()); ?>">
                        <input type="hidden" name="action" value="update_photo">
                        <div class="mx-auto" style="max-width:340px">
                            <label class="form-label">Select profile image (max 2MB)</label>
                            <input class="form-control mb-3" type="file" name="image" accept="image/*" required>
                            <button type="submit" class="btn btn-primary w-100"><i class="ti ti-upload me-1"></i>Upload Picture</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- SECURITY -->
            <div class="tab-pane fade" id="securityTab">
                <form method="post" autocomplete="off">
                    <input type="hidden" name="csrf_token" value="<?php echo clinic_h(clinic_csrf_token()); ?>">
                    <input type="hidden" name="action" value="change_password">
                    <div class="row">
                        <div class="col-12 mb-3">
                            <label class="form-label" for="p_current">Current Password</label>
                            <input class="form-control" id="p_current" name="current_password" type="password" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="p_new">New Password</label>
                            <input class="form-control" id="p_new" name="new_password" type="password" minlength="6" placeholder="At least 6 characters" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="p_confirm">Confirm Password</label>
                            <input class="form-control" id="p_confirm" name="confirm_password" type="password" required>
                        </div>
                    </div>
                    <div class="text-end">
                        <button type="submit" class="btn btn-primary btn-sm"><i class="ti ti-shield-lock me-1"></i>Update Password</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php clinic_page_end(); ?>

