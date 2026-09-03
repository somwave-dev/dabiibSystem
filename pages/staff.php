<?php
require_once __DIR__ . '/../includes/advanced_components.php';

/** @see db/clinic.sql — Role_ID defaults */
define('CLINIC_STAFF_ROLE_ADMIN', 1);
define('CLINIC_STAFF_ROLE_DOCTOR', 2);

/**
 * @return array<string, mixed>|null
 */
function clinic_staff_doctor_row_for_staff(int $staffId): ?array
{
    return $staffId > 0 ? clinic_sp_one('sp_doctor_by_staff', [$staffId], 'i') : null;
}

$staffRows = clinic_sp_rows('sp_staff_list');
$roles = clinic_sp_rows('sp_roles_list');
$errors = [];
$repopStaffId = 0;

$editId = (int) ($_GET['edit'] ?? 0);
$editRow = $editId > 0 ? clinic_sp_one('sp_staff_get', [$editId], 'i') : null;
$doctorSnap = clinic_staff_doctor_row_for_staff($editId);

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $staffId = 0;
    $userId = 0;
    $fullName = '';
    $phone = '';
    $credential = '';
    $notes = '';
    $status = 'active';
    $salary = 0;
    $hireDate = null;
    try {
        clinic_check_csrf();
        $action = clinic_post_string('action');

        if ($action === 'delete_staff') {
            $sid = clinic_post_int('Staff_ID');
            if ($sid < 1) {
                throw new RuntimeException('Invalid staff.');
            }

            clinic_sp_exec('sp_staff_delete', [$sid], 'i');
            clinic_audit_log('Staff deleted', 'Staff record deleted (#' . $sid . ')', 'staff', $sid);
            clinic_flash('Staff record deleted.', 'success');
            clinic_redirect('staff.php');
        }

        if ($action !== 'save_staff') {
            throw new RuntimeException('Unknown action.');
        }

        $staffId = clinic_post_int('Staff_ID');
        $userId = clinic_post_int('User_ID');
        $roleId = clinic_post_int('Role_ID');
        $fullName = clinic_post_string('Full_Name');
        $phone = clinic_post_string('Phone_Number');
        $email = clinic_post_string('Email');
        $credential = clinic_post_string('Credential_Or_Badge');
        $credType = clinic_post_string('Credential_Type');
        $notes = clinic_post_string('Notes');
        $status = clinic_post_string('status') ?: 'active';
        $salary = clinic_post_float('Salary');
        $hireDate = trim(clinic_post_string('Hire_Date'));
        $isDoctor = clinic_post_string('is_doctor') === '1';

        if ($fullName === '') {
            throw new RuntimeException('Full name is required.');
        }

        // Editing an existing staff member keeps their user link (the form no
        // longer manages accounts) — only brand-new staff are saved without one.
        if ($userId === 0 && $staffId > 0) {
            $existing = clinic_sp_one('sp_staff_get', [$staffId], 'i');
            $userId = (int) ($existing['User_ID'] ?? 0);
        }

        $savedId = clinic_sp_exec(
            'sp_staff_save',
            [
                $staffId ?: 0,
                $userId > 0 ? $userId : null,
                $roleId > 0 ? $roleId : null,
                $fullName,
                $phone,
                $email,
                $credential,
                $credType,
                $notes,
                $status,
                $salary,
                $hireDate !== '' ? $hireDate : null,
                $staffId > 0 ? null : clinic_current_user_id(),
            ]
        );
        $staffId = $staffId ?: $savedId;

        if ($isDoctor && $staffId > 0) {
            $spec = clinic_post_string('doctor_specialization');
            $fee = clinic_post_float('doctor_consultation_fee');
            clinic_sp_exec('sp_doctor_upsert_by_staff', [$staffId, $fullName, $spec, $fee]);
        }

        // The staff role is the single source of truth — keep the linked
        // user's role in sync with it.
        if ($staffId > 0 && $roleId > 0) {
            global $conn;
            $stmt = $conn->prepare('UPDATE users SET Role_ID = ? WHERE User_ID = (SELECT User_ID FROM staff WHERE Staff_ID = ?)');
            $stmt->bind_param('ii', $roleId, $staffId);
            $stmt->execute();
            $stmt->close();
        }

        // Audit trail + notification for the affected account.
        clinic_audit_log(
            $staffId > 0 && (int) $userId === 0 ? 'Staff created' : 'Staff updated',
            'Staff record saved: ' . $fullName . ($staffId > 0 ? ' (#' . $staffId . ')' : ''),
            'staff',
            $staffId > 0 ? $staffId : null
        );
        if ($userId > 0) {
            clinic_notify('Staff record saved', 'Your staff record was updated: ' . $fullName, 'info', 'profile.php', $userId);
        }

        clinic_flash('Staff saved.', 'success');
        clinic_redirect('staff.php');
    } catch (Throwable $e) {
        $errors[] = $e->getMessage();
        $repopStaffId = (int) ($staffId ?? 0);
        if ($repopStaffId > 0) {
            $editRow = clinic_sp_one('sp_staff_get', [$repopStaffId], 'i');
            $editId = $repopStaffId;
        } else {
            $editRow = [
                'Staff_ID' => 0,
                'User_ID' => null,
                'Role_ID' => $roleId ?? 0,
                'Full_Name' => $fullName ?? '',
                'Phone_Number' => $phone ?? '',
                'Email' => $email ?? '',
                'Credential_Or_Badge' => $credential ?? '',
                'Credential_Type' => $credType ?? '',
                'Notes' => $notes ?? '',
                'Salary' => $salary ?? 0,
                'Hire_Date' => $hireDate ?? null,
                'status' => $status ?? 'active',
                'Created_At' => null,
            ];
            $editId = 0;
        }
        $doctorSnap = null;
        if (clinic_post_string('is_doctor') === '1') {
            $doctorSnap = [
                'Specialization' => clinic_post_string('doctor_specialization'),
                'Consultation_Fee' => clinic_post_float('doctor_consultation_fee'),
            ];
        }
    }
}

if ($errors === [] && $editRow !== null) {
    $doctorSnap = clinic_staff_doctor_row_for_staff((int) ($editRow['Staff_ID'] ?? 0));
}

$staffModalAutoOpen = ($errors !== [] || $editId > 0);

function clinic_staff_profile_summary(array $row): string
{
    $rid = (int) ($row['Role_ID'] ?? 0);
    if ($rid === CLINIC_STAFF_ROLE_DOCTOR) {
        $spec = trim((string) ($row['Specialization'] ?? ''));
        $feeRaw = $row['Consultation_Fee'] ?? null;
        $feeTxt = ($feeRaw !== null && $feeRaw !== '') ? clinic_money((float) $feeRaw) : '—';

        return ($spec !== '' ? clinic_h($spec) . ' · ' : '') . '<span class="text-nowrap">' . clinic_h($feeTxt) . '</span>';
    }

    $cred = trim((string) ($row['Credential_Or_Badge'] ?? ''));
    $credType = trim((string) ($row['Credential_Type'] ?? ''));

    if ($cred === '' && $credType === '') {
        return '—';
    }

    $parts = [];
    if ($credType !== '') {
        $parts[] = '<span class="badge text-bg-light border">' . clinic_h($credType) . '</span>';
    }
    if ($cred !== '') {
        $parts[] = '<span class="font-monospace">' . clinic_h($cred) . '</span>';
    }

    return implode(' ', $parts);
}

clinic_page_start('Staff directory');

$totalStaff = count($staffRows);
$activeStaff = count(array_filter($staffRows, static fn ($s) => strtolower((string) ($s['status'] ?? '')) === 'active'));
$inactiveStaff = $totalStaff - $activeStaff;
?>
<style>
.staff-prof-block{border:1px dashed rgba(var(--primary-rgb),.35);border-radius:.75rem;padding:1rem;margin-bottom:.75rem;background:rgba(var(--primary-rgb),.04)}
.staff-prof-block.is-hidden{display:none!important}
.staff-prof-block h6{font-size:.8rem;text-transform:uppercase;letter-spacing:.06em;color:#6c757d;margin-bottom:.75rem}
</style>

<div class="row g-3 mb-4">
    <?php clinic_metric_card('Total Staff', $totalStaff, 'ti-users-group', 'primary', 'All staff members'); ?>
    <?php clinic_metric_card('Active Staff', $activeStaff, 'ti-user-check', 'success', 'Currently active'); ?>
    <?php clinic_metric_card('Inactive Staff', $inactiveStaff, 'ti-user-off', 'secondary', 'Inactive accounts'); ?>
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
        <h5 class="mb-0">Staff</h5>
        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#staffModal">
            <i class="ti ti-user-plus me-1"></i> Add Staff
        </button>
    </div>
    <div class="card-body pt-2">
        <div class="table-responsive">
            <table class="table table-hover align-middle clinic-table mb-0">
                <thead>
                    <tr>
                        <th>Staff ID</th>
                        <th>Staff</th>
                        <th>Type / Role</th>
                        <th>Credential / ID</th>
                        <th>Phone</th>
                        <th>Email</th>
                        <th>Salary</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($staffRows as $s): ?>
                    <?php
                    $sid = (int) ($s['Staff_ID'] ?? 0);
                    $sName = (string) ($s['Full_Name'] ?? '');
                    $sUname = (string) ($s['Username'] ?? '');
                    $isDoctorRow = (int) ($s['Doctor_ID'] ?? 0) > 0 || ($s['Specialization'] ?? '') !== '';
                    ?>
                    <tr>
                        <td><span class="badge text-bg-light border font-monospace">STF-<?php echo str_pad((string) $sid, 4, '0', STR_PAD_LEFT); ?></span></td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <?php echo clinic_avatar((string) ($s['Image'] ?? ''), $sName, 'clinic-avatar clinic-avatar-sm rounded-circle'); ?>
                                <div class="min-w-0">
                                    <div class="fw-semibold text-truncate"><?php echo clinic_h($sName); ?></div>
                                    <div class="small text-muted"><?php echo $sUname !== '' ? '@' . clinic_h($sUname) : '<span class="text-muted">—</span>'; ?></div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <?php if ($isDoctorRow): ?>
                            <span class="badge text-bg-primary">Doctor</span>
                            <?php else: ?>
                            <?php $sRole = trim((string) ($s['Role_Name'] ?? '')); ?>
                            <span class="badge text-bg-light border"><?php echo clinic_h($sRole !== '' ? $sRole : 'Staff'); ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="small"><?php echo clinic_staff_profile_summary($s); ?></td>
                        <td><?php echo clinic_h((string) ($s['Phone_Number'] ?? '')) ?: '—'; ?></td>
                        <td class="text-break"><?php echo clinic_h((string) ($s['Email'] ?? '')) ?: '—'; ?></td>
                        <td><?php $__sal = (float) ($s['Salary'] ?? 0); echo $__sal > 0 ? clinic_money($__sal) : '—'; ?></td>
                        <td><?php echo clinic_status_badge((string) ($s['status'] ?? 'active')); ?></td>
                        <td class="text-end">
                            <?php
                            $rowImage = (string) ($s['Image'] ?? '');
                            $rowImageUrl = '';
                            if ($rowImage !== '' && !preg_match('#^[a-z][a-z0-9+.-]*://#i', $rowImage) && !str_starts_with($rowImage, '/')) {
                                if (is_file(__DIR__ . '/../' . ltrim($rowImage, '/'))) {
                                    $rowImageUrl = '../' . ltrim($rowImage, '/');
                                }
                            }
                            $sRole = trim((string) ($s['Role_Name'] ?? ''));
                            ?>
                            <button
                                type="button"
                                class="btn btn-sm btn-light border btn-icon"
                                title="View"
                                data-bs-toggle="modal"
                                data-bs-target="#staffViewModal"
                                data-staff-id="<?php echo $sid; ?>"
                                data-name="<?php echo clinic_h($sName); ?>"
                                data-username="<?php echo clinic_h($sUname); ?>"
                                data-role="<?php echo clinic_h($sRole); ?>"
                                data-type="<?php echo $isDoctorRow ? 'Doctor' : ($sRole !== '' ? $sRole : 'Staff'); ?>"
                                data-status="<?php echo clinic_h((string) ($s['status'] ?? 'active')); ?>"
                                data-phone="<?php echo clinic_h((string) ($s['Phone_Number'] ?? '')); ?>"
                                data-email="<?php echo clinic_h((string) ($s['Email'] ?? '')); ?>"
                                data-spec="<?php echo clinic_h((string) ($s['Specialization'] ?? '')); ?>"
                                data-fee="<?php echo clinic_h(isset($s['Consultation_Fee']) ? (string) (float) $s['Consultation_Fee'] : '0'); ?>"
                                data-cred-type="<?php echo clinic_h((string) ($s['Credential_Type'] ?? '')); ?>"
                                data-cred-id="<?php echo clinic_h((string) ($s['Credential_Or_Badge'] ?? '')); ?>"
                                data-notes="<?php echo clinic_h((string) ($s['Notes'] ?? '')); ?>"
                                data-created-at="<?php echo clinic_h((string) ($s['Created_At'] ?? '')); ?>"
                                data-last-login="<?php echo clinic_h((string) ($s['Last_Login'] ?? '')); ?>"
                                data-salary="<?php echo clinic_h((string) ($s['Salary'] ?? 0)); ?>"
                                data-hire-date="<?php echo clinic_h((string) ($s['Hire_Date'] ?? '')); ?>"
                                data-created-by="<?php echo clinic_h((string) ($s['Created_By_Name'] ?? '')); ?>"
                                data-image="<?php echo clinic_h($rowImageUrl); ?>"
                            ><i class="ti ti-eye"></i></button>
                            <a class="btn btn-sm btn-light border btn-icon" title="Edit" href="staff.php?edit=<?php echo $sid; ?>"><i class="ti ti-pencil"></i></a>
                            <form class="d-inline js-confirm-delete" method="post" data-confirm-text="Delete staff?">
                                <input type="hidden" name="csrf_token" value="<?php echo clinic_h(clinic_csrf_token()); ?>">
                                <input type="hidden" name="action" value="delete_staff">
                                <input type="hidden" name="Staff_ID" value="<?php echo $sid; ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger btn-icon" title="Delete"><i class="ti ti-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if ($staffRows === []): ?>
                    <tr><td colspan="9" class="text-center text-muted py-4">No staff registered yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="staffModal" tabindex="-1" aria-labelledby="staffModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <form method="post" class="modal-content" id="staffForm" autocomplete="off">
            <div class="modal-header">
                <h5 class="modal-title" id="staffModalLabel"><?php echo $editRow ? 'Edit Staff' : 'Add Staff'; ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?php echo clinic_h(clinic_csrf_token()); ?>">
                <input type="hidden" name="action" value="save_staff">
                <input type="hidden" name="Staff_ID" value="<?php echo (int) ($editRow['Staff_ID'] ?? 0); ?>">

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label" for="staff_full_name">Full Name <span class="text-danger">*</span></label>
                        <input class="form-control" id="staff_full_name" name="Full_Name" required value="<?php echo clinic_h((string) ($editRow['Full_Name'] ?? '')); ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label" for="staff_role">Role <span class="text-danger">*</span></label>
                        <select class="form-select" id="staff_role" name="Role_ID" required>
                            <option value="">-- Select role --</option>
                            <?php
                            $__roleId = (int) ($editRow['Role_ID'] ?? 0);
                            foreach ($roles as $rl):
                            ?>
                            <option value="<?php echo (int) ($rl['Role_ID'] ?? 0); ?>"<?php echo $__roleId === (int) ($rl['Role_ID'] ?? 0) ? ' selected' : ''; ?>><?php echo clinic_h($rl['Role_Name'] ?? ''); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php if (($editRow['Username'] ?? '') !== ''): ?>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Linked account</label>
                        <input class="form-control" value="<?php echo clinic_h(trim((string) $editRow['Username'] . ((string) ($editRow['Role_Name'] ?? '') !== '' ? ' — ' . $editRow['Role_Name'] : ''))); ?>" disabled>
                    </div>
                    <?php endif; ?>
                    <div class="col-md-6 mb-3">
                        <label class="form-label" for="staff_phone">Phone</label>
                        <input class="form-control" id="staff_phone" name="Phone_Number" type="tel" value="<?php echo clinic_h((string) ($editRow['Phone_Number'] ?? '')); ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label" for="staff_email">Email</label>
                        <input class="form-control" id="staff_email" name="Email" type="email" value="<?php echo clinic_h((string) ($editRow['Email'] ?? '')); ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label" for="staff_salary">Salary</label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input class="form-control" id="staff_salary" name="Salary" type="number" step="0.01" min="0" value="<?php echo clinic_h(isset($editRow['Salary']) && $editRow['Salary'] !== '' ? (string) (float) $editRow['Salary'] : '0'); ?>">
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label" for="staff_hire_date">Hire date</label>
                        <input class="form-control" id="staff_hire_date" name="Hire_Date" type="date" value="<?php echo clinic_h((string) ($editRow['Hire_Date'] ?? '')); ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label" for="staff_status">Status</label>
                        <select class="form-select" id="staff_status" name="status">
                            <?php
                            $__st = (string) ($editRow['status'] ?? 'active');
                            ?>
                            <option value="active"<?php echo $__st === 'active' ? ' selected' : ''; ?>>Active</option>
                            <option value="inactive"<?php echo $__st === 'inactive' ? ' selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Staff type</label>
                        <div class="form-check form-switch pt-2">
                            <input class="form-check-input" type="checkbox" id="staff_is_doctor" name="is_doctor" value="1"<?php echo $doctorSnap !== null ? ' checked' : ''; ?>>
                            <label class="form-check-label" for="staff_is_doctor">This staff member is a doctor</label>
                        </div>
                    </div>
                    <div class="col-12 mb-3">
                        <label class="form-label" for="staff_notes">Notes</label>
                        <textarea class="form-control" id="staff_notes" name="Notes" rows="2"><?php echo clinic_h((string) ($editRow['Notes'] ?? '')); ?></textarea>
                    </div>
                </div>

                <div id="staffBlockDoctor" class="staff-prof-block is-hidden">
                    <h6 class="mb-0"><i class="ti ti-stethoscope me-1"></i>Doctor (specialization • consultation fee)</h6>
                    <div class="row mt-2">
                        <div class="col-md-7 mb-2">
                            <label class="form-label" for="doc_spec">Specialization</label>
                            <input class="form-control" id="doc_spec" name="doctor_specialization" value="<?php echo clinic_h((string) ($doctorSnap['Specialization'] ?? '')); ?>">
                        </div>
                        <div class="col-md-5 mb-2">
                            <label class="form-label" for="doc_fee">Consultation fee</label>
                            <input class="form-control" id="doc_fee" name="doctor_consultation_fee" type="number" step="0.01" min="0" value="<?php echo clinic_h(isset($doctorSnap['Consultation_Fee']) ? (string) (float) $doctorSnap['Consultation_Fee'] : '0'); ?>">
                        </div>
                    </div>
                    <div class="form-text mb-0">Shared with the <strong>Doctors</strong> file; visits and appointments use this identity.</div>
                </div>

                <div id="staffBlockCred" class="staff-prof-block">
                    <h6 class="mb-0"><i class="ti ti-id-badge me-1"></i>License / certificate / professional ID</h6>
                    <div class="row mt-2">
                        <div class="col-md-5 mb-2">
                            <label class="form-label" for="staff_cred_type">Credential type</label>
                            <select class="form-select" id="staff_cred_type" name="Credential_Type">
                                <option value="">-- Select type --</option>
                                <?php
                                $__ct = (string) ($editRow['Credential_Type'] ?? '');
                                $__credTypes = ['Nurse License', 'Midwife License', 'Pharmacy License', 'Lab License', 'Radiology License', 'Dental License', 'Professional ID', 'National ID', 'Certificate', 'Other'];
                                foreach ($__credTypes as $__t):
                                ?>
                                <option value="<?php echo clinic_h($__t); ?>"<?php echo $__ct === $__t ? ' selected' : ''; ?>><?php echo clinic_h($__t); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-7 mb-2">
                            <label class="form-label" for="staff_cred">Credential / ID</label>
                            <input class="form-control" id="staff_cred" name="Credential_Or_Badge" value="<?php echo clinic_h((string) ($editRow['Credential_Or_Badge'] ?? '')); ?>" maxlength="120" placeholder="Example: NUR-12345 • PHM-7788 • ID number">
                        </div>
                        <div class="col-12">
                            <div class="form-text mb-0">Choose the license / ID type and enter the credential number (not for doctors).</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-danger" data-bs-dismiss="modal"><i class="ti ti-x me-1"></i>Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="ti ti-device-floppy me-1"></i>Save</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="staffViewModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Staff Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="d-flex align-items-center gap-3 mb-3">
                    <span id="svAvatar"></span>
                    <div>
                        <h5 class="mb-0" id="svName"></h5>
                        <div class="text-muted small" id="svUsername"></div>
                    </div>
                </div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="form-label small text-muted mb-0">Staff ID</div>
                        <div class="fw-semibold" id="svId"></div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-label small text-muted mb-0">Status</div>
                        <div id="svStatus"></div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-label small text-muted mb-0">Type / Role</div>
                        <div id="svType"></div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-label small text-muted mb-0">Linked account</div>
                        <div id="svAccount"></div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-label small text-muted mb-0">Phone</div>
                        <div id="svPhone"></div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-label small text-muted mb-0">Email</div>
                        <div class="text-break" id="svEmail"></div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-label small text-muted mb-0">Salary</div>
                        <div class="fw-semibold" id="svSalary"></div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-label small text-muted mb-0">Hire Date</div>
                        <div class="fw-semibold" id="svHireDate"></div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-label small text-muted mb-0">Date Created</div>
                        <div class="fw-semibold" id="svCreatedAt"></div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-label small text-muted mb-0">Last Login</div>
                        <div class="fw-semibold" id="svLastLogin"></div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-label small text-muted mb-0">Created By</div>
                        <div class="fw-semibold" id="svCreatedBy"></div>
                    </div>
                </div>
                <div class="staff-prof-block mt-3" id="svDocBlock" style="display:none;">
                    <h6 class="mb-0"><i class="ti ti-stethoscope me-1"></i>Doctor (specialization • consultation fee)</h6>
                    <div class="row mt-2">
                        <div class="col-md-7">
                            <div class="form-label small text-muted mb-0">Specialization</div>
                            <div class="fw-semibold" id="svSpec"></div>
                        </div>
                        <div class="col-md-5">
                            <div class="form-label small text-muted mb-0">Consultation fee</div>
                            <div class="fw-semibold" id="svFee"></div>
                        </div>
                    </div>
                </div>
                <div class="staff-prof-block mt-3" id="svCredBlock" style="display:none;">
                    <h6 class="mb-0"><i class="ti ti-id-badge me-1"></i>License / certificate / professional ID</h6>
                    <div class="row mt-2">
                        <div class="col-md-5">
                            <div class="form-label small text-muted mb-0">Credential type</div>
                            <div class="fw-semibold" id="svCredType"></div>
                        </div>
                        <div class="col-md-7">
                            <div class="form-label small text-muted mb-0">Credential / ID</div>
                            <div class="fw-semibold font-monospace" id="svCredId"></div>
                        </div>
                    </div>
                </div>
                <div class="mt-3" id="svNotesWrap" style="display:none;">
                    <div class="form-label small text-muted mb-0">Notes</div>
                    <div id="svNotes"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
(function(){
  var docChk = document.getElementById('staff_is_doctor');
  var bDoc = document.getElementById('staffBlockDoctor');
  var bCred = document.getElementById('staffBlockCred');

  function refresh(){
    var isDoc = docChk ? docChk.checked : false;
    // Default off: doctor section hidden until the checkbox is opened.
    // Opening it only ADDS the doctor inputs — the credential section is never hidden.
    if (bDoc) bDoc.classList.toggle('is-hidden', !isDoc);
    if (bCred) bCred.classList.remove('is-hidden');
  }

  if (docChk) docChk.addEventListener('change', refresh);
  refresh();

  <?php if (!empty($staffModalAutoOpen)): ?>
  document.addEventListener('DOMContentLoaded', function(){
    var m = document.getElementById('staffModal');
    if (m && typeof bootstrap !== 'undefined') new bootstrap.Modal(m).show();
  });
  <?php endif; ?>
})();

document.addEventListener('show.bs.modal', function (event) {
  var trigger = event.relatedTarget;
  if (!trigger || event.target.id !== 'staffViewModal') {
    return;
  }
  function val(key) { return trigger.getAttribute(key) || ''; }
  function set(id, text) { var el = document.getElementById(id); if (el) { el.textContent = text; } }
  function esc(s) {
    var d = document.createElement('div');
    d.textContent = s;
    return d.innerHTML;
  }

  var name = val('data-name') || '?';
  var image = val('data-image');

  // Avatar: photo when available, otherwise a letter circle.
  var av = document.getElementById('svAvatar');
  if (image) {
    av.innerHTML = '<img src="' + esc(image) + '" class="clinic-avatar clinic-avatar-lg rounded-circle clinic-avatar-img" alt="' + esc(name) + '">';
  } else {
    av.innerHTML = '<span class="clinic-avatar clinic-avatar-lg rounded-circle clinic-avatar-letter">' + esc((name.charAt(0) || '?').toUpperCase()) + '</span>';
  }

  set('svName', name);
  set('svUsername', val('data-username') ? '@' + val('data-username') : '—');
  set('svId', 'STF-' + String(parseInt(val('data-staff-id'), 10) || 0).padStart(4, '0'));

  // Status badge
  var st = document.getElementById('svStatus');
  var status = val('data-status') === 'active' ? 'active' : 'inactive';
  st.innerHTML = '<span class="badge ' + (status === 'active' ? 'text-bg-success' : 'text-bg-secondary') + '">' + (status === 'active' ? 'Active' : 'Inactive') + '</span>';

  // Type / Role badge
  var typeVal = val('data-type');
  var tp = document.getElementById('svType');
  if (typeVal === 'Doctor') {
    tp.innerHTML = '<span class="badge text-bg-primary">Doctor</span>';
  } else if (typeVal === 'Staff') {
    tp.innerHTML = '<span class="badge text-bg-light border">Staff</span>';
  } else {
    tp.innerHTML = '<span class="badge text-bg-light border">' + esc(typeVal) + '</span>';
  }

  // Linked account
  var acc = document.getElementById('svAccount');
  var uname = val('data-username');
  var role = val('data-role');
  acc.innerHTML = uname ? '@' + esc(uname) + (role ? ' <span class="text-muted">— ' + esc(role) + '</span>' : '') : '<span class="text-muted">—</span>';

  set('svPhone', val('data-phone') || '—');
  set('svEmail', val('data-email') || '—');
  set('svSpec', val('data-spec') || '—');
  set('svFee', val('data-fee') !== '' && val('data-fee') !== '0' ? '$' + parseFloat(val('data-fee')).toFixed(2) : '—');
  set('svCredType', val('data-cred-type') || '—');
  set('svCredId', val('data-cred-id') || '—');
  set('svNotes', val('data-notes') || '—');

  function fmtDT(s) {
    if (!s) { return '—'; }
    var d = new Date(String(s).replace(' ', 'T'));
    if (isNaN(d.getTime())) { return s; }
    return d.toLocaleString(undefined, { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
  }
  set('svCreatedAt', fmtDT(val('data-created-at')));
  set('svLastLogin', fmtDT(val('data-last-login')));
  set('svSalary', (val('data-salary') !== '' && parseFloat(val('data-salary')) > 0) ? '$' + parseFloat(val('data-salary')).toFixed(2) : '—');
  set('svHireDate', (function () {
    var h = val('data-hire-date');
    if (!h) { return '—'; }
    var hd = new Date(String(h).replace(' ', 'T'));
    if (isNaN(hd.getTime())) { return h; }
    return hd.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
  })());
  set('svCreatedBy', val('data-created-by') ? '@' + val('data-created-by') : '—');

  document.getElementById('svDocBlock').style.display = val('data-spec') ? '' : 'none';
  document.getElementById('svCredBlock').style.display = (val('data-cred-type') || val('data-cred-id')) ? '' : 'none';
  document.getElementById('svNotesWrap').style.display = val('data-notes') ? '' : 'none';
});
</script>

<?php
clinic_page_end();
