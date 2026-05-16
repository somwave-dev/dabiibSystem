<?php
require_once __DIR__ . '/../includes/advanced_components.php';

/** @see db/clinic.sql — Role_ID defaults */
define('CLINIC_STAFF_ROLE_ADMIN', 1);
define('CLINIC_STAFF_ROLE_DOCTOR', 2);

/**
 * @return array<string, mixed>|null
 */
function clinic_staff_doctor_row_for_user(int $userId): ?array
{
    if ($userId < 1) {
        return null;
    }

    foreach (clinic_sp_rows('sp_doctors_list') as $row) {
        if ((int) ($row['User_ID'] ?? 0) === $userId) {
            return $row;
        }
    }

    return null;
}

$userRowsForSelect = clinic_sp_rows('sp_users_list');
$staffRows = clinic_sp_rows('sp_staff_list');
$errors = [];
$repopStaffId = 0;
$repopUserId = 0;

$editId = (int) ($_GET['edit'] ?? 0);
$editRow = $editId > 0 ? clinic_sp_one('sp_staff_get', [$editId], 'i') : null;
$userIdEdit = $editRow ? (int) ($editRow['User_ID'] ?? 0) : 0;
$doctorSnap = clinic_staff_doctor_row_for_user($userIdEdit);

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $staffId = 0;
    $userId = 0;
    $fullName = '';
    $phone = '';
    $credential = '';
    $notes = '';
    $status = 'active';
    try {
        clinic_check_csrf();
        $action = clinic_post_string('action');

        if ($action === 'delete_staff') {
            $sid = clinic_post_int('Staff_ID');
            if ($sid < 1) {
                throw new RuntimeException('Invalid staff.');
            }

            clinic_sp_exec('sp_staff_delete', [$sid], 'i');
            clinic_flash('Staff record deleted.', 'success');
            clinic_redirect('staff.php');
        }

        if ($action !== 'save_staff') {
            throw new RuntimeException('Unknown action.');
        }

        $staffId = clinic_post_int('Staff_ID');
        $userId = clinic_post_int('User_ID');
        $fullName = clinic_post_string('Full_Name');
        $phone = clinic_post_string('Phone_Number');
        $credential = clinic_post_string('Credential_Or_Badge');
        $notes = clinic_post_string('Notes');
        $status = clinic_post_string('status') ?: 'active';

        if ($userId < 1 || $fullName === '') {
            throw new RuntimeException('Account and full name are required.');
        }

        $userAcct = clinic_sp_one('sp_users_get', [$userId], 'i');
        if (!$userAcct) {
            throw new RuntimeException('Selected user account was not found.');
        }

        $roleId = (int) ($userAcct['Role_ID'] ?? 0);

        if ($roleId === CLINIC_STAFF_ROLE_DOCTOR) {
            $credential = '';
        }

        clinic_sp_exec('sp_staff_save', [
            $staffId ?: 0,
            $userId,
            $fullName,
            $phone,
            $credential,
            $notes,
            $status,
        ]);

        if ($roleId === CLINIC_STAFF_ROLE_DOCTOR) {
            $existingDr = clinic_staff_doctor_row_for_user($userId);
            $docId = (int) ($existingDr['Doctor_ID'] ?? 0);
            $spec = clinic_post_string('doctor_specialization');
            $fee = clinic_post_float('doctor_consultation_fee');
            clinic_sp_exec('sp_doctors_save', [
                $docId,
                $fullName,
                $spec,
                $fee,
                $userId,
            ]);
        }

        clinic_flash('Staff saved.', 'success');
        clinic_redirect('staff.php');
    } catch (Throwable $e) {
        $errors[] = $e->getMessage();
        $repopStaffId = (int) ($staffId ?? 0);
        $repopUserId = (int) ($userId ?? 0);
        if ($repopStaffId > 0) {
            $editRow = clinic_sp_one('sp_staff_get', [$repopStaffId], 'i');
            $editId = $repopStaffId;
        } elseif ($repopUserId > 0) {
            $editRow = [
                'Staff_ID' => 0,
                'User_ID' => $repopUserId,
                'Full_Name' => $fullName ?? '',
                'Phone_Number' => $phone ?? '',
                'Credential_Or_Badge' => $credential ?? '',
                'Notes' => $notes ?? '',
                'status' => $status ?? 'active',
                'Created_At' => null,
            ];
            $editId = 0;
        }
        $userIdEdit = (int) ($editRow['User_ID'] ?? 0);
        $doctorSnap = clinic_staff_doctor_row_for_user($userIdEdit);
        $uErr = $userIdEdit > 0 ? clinic_sp_one('sp_users_get', [$userIdEdit], 'i') : null;
        if ($uErr && (int) ($uErr['Role_ID'] ?? 0) === CLINIC_STAFF_ROLE_DOCTOR) {
            $doctorSnap = [
                'Specialization' => clinic_post_string('doctor_specialization'),
                'Consultation_Fee' => clinic_post_float('doctor_consultation_fee'),
            ];
        }
    }
}

if ($errors === [] && $editRow !== null) {
    $doctorSnap = clinic_staff_doctor_row_for_user((int) ($editRow['User_ID'] ?? 0));
}

$selUserForEdit = (int) ($editRow['User_ID'] ?? 0);
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

    return $cred !== '' ? clinic_h($cred) : '—';
}

clinic_page_start(
    'Staff directory',
    'Shaqaalahooda oo dhan meel keliya — goobaha loo muujiyo ayaa ku xiran xirmo (doctor / lab / iwm).'
);

?>
<style>
.staff-prof-block{border:1px dashed rgba(var(--primary-rgb),.35);border-radius:.75rem;padding:1rem;margin-bottom:.75rem;background:rgba(var(--primary-rgb),.04)}
.staff-prof-block.is-hidden{display:none!important}
.staff-prof-block h6{font-size:.8rem;text-transform:uppercase;letter-spacing:.06em;color:#6c757d;margin-bottom:.75rem}
</style>

<div class="row mb-4">
    <div class="col-lg-10">
        <p class="text-muted small mb-0">
            <strong>Jagada</strong> waxaa lagu helayaa Role-ka akoonka <strong>Users</strong>.
            <strong>Dhakhtar:</strong> taxane (<em>Specialization</em>), <em>Consultation Fee</em> waxaa lagu keydiyaa <code>doctors</code>.
            <strong>Hawlwadeennada kale:</strong> liisan / aqoonsi / shahaado (credential).
            <strong>Maamule</strong> waxaa lagu diiwan geliyaa haddii rabto; xirrada sare ma jiraane.
        </p>
    </div>
</div>

<?php if ($errors !== []): ?>
<div class="alert alert-danger"><?php foreach ($errors as $e): ?><div><?php echo clinic_h($e); ?></div><?php endforeach; ?></div>
<?php endif; ?>

<div class="card clinic-card mb-3">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <h5 class="mb-0">Shaqaalaha</h5>
        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#staffModal">
            <i class="ti ti-user-plus me-1"></i> Ku dar shaqaale
        </button>
    </div>
    <div class="card-body pt-2">
        <div class="table-responsive">
            <table class="table table-hover align-middle clinic-table mb-0">
                <thead>
                    <tr>
                        <th>Magaca muuqda</th>
                        <th>Username</th>
                        <th>Jago / Role</th>
                        <th>Xirfad / aqoonsiga</th>
                        <th>Telefoon</th>
                        <th>Xaalad</th>
                        <th class="text-end">Farsamo</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($staffRows as $s): ?>
                    <tr>
                        <td class="fw-semibold"><?php echo clinic_h((string) ($s['Full_Name'] ?? '')); ?></td>
                        <td><?php echo clinic_h((string) ($s['Username'] ?? '')); ?></td>
                        <td><?php echo clinic_h((string) ($s['Role_Name'] ?? '')); ?></td>
                        <td class="small"><?php echo clinic_staff_profile_summary($s); ?></td>
                        <td><?php echo clinic_h((string) ($s['Phone_Number'] ?? '')) ?: '—'; ?></td>
                        <td><?php echo clinic_status_badge((string) ($s['status'] ?? 'active')); ?></td>
                        <td class="text-end">
                            <a class="btn btn-sm btn-light border" href="staff.php?edit=<?php echo (int) ($s['Staff_ID'] ?? 0); ?>">Wax ka beddel</a>
                            <form class="d-inline" method="post" onsubmit="return confirm('Tirtir staff?');">
                                <input type="hidden" name="csrf_token" value="<?php echo clinic_h(clinic_csrf_token()); ?>">
                                <input type="hidden" name="action" value="delete_staff">
                                <input type="hidden" name="Staff_ID" value="<?php echo (int) ($s['Staff_ID'] ?? 0); ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger">Tirtir</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if ($staffRows === []): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">Wali shaqaale lama diiwan gelin.</td></tr>
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
                <h5 class="modal-title" id="staffModalLabel"><?php echo $editRow ? 'Wax ka beddel staff' : 'Ku dar staff'; ?></h5>
                <a class="btn-close" href="staff.php" aria-label="Close"></a>
            </div>
            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?php echo clinic_h(clinic_csrf_token()); ?>">
                <input type="hidden" name="action" value="save_staff">
                <input type="hidden" name="Staff_ID" value="<?php echo (int) ($editRow['Staff_ID'] ?? 0); ?>">

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label" for="staff_user_id">Akoon user <span class="text-danger">*</span></label>
                        <select class="form-select" id="staff_user_id" name="User_ID" required>
                            <option value="">— Dooro —</option>
                            <?php foreach ($userRowsForSelect as $ur): ?>
                            <?php
                                $uid = (int) ($ur['User_ID'] ?? 0);
                                $rname = (string) ($ur['Role_Name'] ?? '');
                                $rid = (int) ($ur['Role_ID'] ?? 0);
                                $lbl = clinic_h(($ur['Username'] ?? '') . ' — ' . $rname);
                                $selected = $uid === $selUserForEdit ? ' selected' : '';
                                ?>
                            <option value="<?php echo $uid; ?>" data-role-id="<?php echo $rid; ?>" data-role-name="<?php echo clinic_h($rname); ?>"<?php echo $selected; ?>><?php echo $lbl; ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Dooro dhakhaatiirta akoon walba — hal akoon keliya Shaqaalahooda lagu keydiyaa.</div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label" for="staff_full_name">Magaca buuxda <span class="text-danger">*</span></label>
                        <input class="form-control" id="staff_full_name" name="Full_Name" required value="<?php echo clinic_h((string) ($editRow['Full_Name'] ?? '')); ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label" for="staff_phone">Telefoon</label>
                        <input class="form-control" id="staff_phone" name="Phone_Number" type="tel" value="<?php echo clinic_h((string) ($editRow['Phone_Number'] ?? '')); ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label" for="staff_status">Xaaladda</label>
                        <select class="form-select" id="staff_status" name="status">
                            <?php
                            $__st = (string) ($editRow['status'] ?? 'active');
                            ?>
                            <option value="active"<?php echo $__st === 'active' ? ' selected' : ''; ?>>Active</option>
                            <option value="inactive"<?php echo $__st === 'inactive' ? ' selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                    <div class="col-12 mb-3">
                        <label class="form-label" for="staff_notes">Faallo / qoraal dheer</label>
                        <textarea class="form-control" id="staff_notes" name="Notes" rows="2"><?php echo clinic_h((string) ($editRow['Notes'] ?? '')); ?></textarea>
                    </div>
                </div>

                <div id="staffBlockDoctor" class="staff-prof-block is-hidden">
                    <h6 class="mb-0"><i class="ti ti-stethoscope me-1"></i>Dhakhtar (taxane • lacag booqasho)</h6>
                    <div class="row mt-2">
                        <div class="col-md-7 mb-2">
                            <label class="form-label" for="doc_spec">Taxane — Specialization</label>
                            <input class="form-control" id="doc_spec" name="doctor_specialization" value="<?php echo clinic_h((string) ($doctorSnap['Specialization'] ?? '')); ?>">
                        </div>
                        <div class="col-md-5 mb-2">
                            <label class="form-label" for="doc_fee">Qiimaha la tashiga — Consultation fee</label>
                            <input class="form-control" id="doc_fee" name="doctor_consultation_fee" type="number" step="0.01" min="0" value="<?php echo clinic_h(isset($doctorSnap['Consultation_Fee']) ? (string) (float) $doctorSnap['Consultation_Fee'] : '0'); ?>">
                        </div>
                    </div>
                    <div class="form-text mb-0">Waxaa lagu wadaagaya faylka <strong>Doctors</strong>; booqashooyinka iyo balamahu waxay isticmaalaan aqoonsigan.</div>
                </div>

                <div id="staffBlockCred" class="staff-prof-block is-hidden">
                    <h6 class="mb-0"><i class="ti ti-id-badge me-1"></i>Liisan / shahaado / aqoonsiga xirfad</h6>
                    <div class="row mt-2">
                        <div class="col-12 mb-2">
                            <label class="form-label" for="staff_cred">Credential / aqoonsi</label>
                            <input class="form-control" id="staff_cred" name="Credential_Or_Badge" value="<?php echo clinic_h((string) ($editRow['Credential_Or_Badge'] ?? '')); ?>" maxlength="120" placeholder="Tusaale: Shahaado kalkaaliye • ID farmashiiste • aqoonsiga lab …">
                            <div class="form-text mb-0">Waxaa lagu keydiyaa <code>staff.Credential_Or_Badge</code> (ma aha dhakhaatiirrada).</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <a class="btn btn-light border" href="staff.php">Jooji</a>
                <button type="submit" class="btn btn-primary"><i class="ti ti-device-floppy me-1"></i>Kaydi</button>
            </div>
        </form>
    </div>
</div>

<script>
(function(){
  var ROLE_ADMIN = <?php echo (int) CLINIC_STAFF_ROLE_ADMIN; ?>;
  var ROLE_DOCTOR = <?php echo (int) CLINIC_STAFF_ROLE_DOCTOR; ?>;
  var sel = document.getElementById('staff_user_id');
  var bDoc = document.getElementById('staffBlockDoctor');
  var bCred = document.getElementById('staffBlockCred');

  function roleId(){
    var o = sel && sel.selectedOptions && sel.selectedOptions[0];
    if (!o) return 0;
    var rid = parseInt(o.getAttribute('data-role-id') || '0', 10);
    return isNaN(rid) ? 0 : rid;
  }

  function refresh(){
    var rid = roleId();
    var isDoc = rid === ROLE_DOCTOR;
    var showCred = !isDoc && rid > 0;
    // Maamule: muuji credential haddii loo baahdo — halkan waxaan haynaa aqoonsiga shaqaala kale kaliya:
    if (rid === ROLE_ADMIN) showCred = false;

    bDoc.classList.toggle('is-hidden', !isDoc);
    bCred.classList.toggle('is-hidden', !showCred);
  }

  if (sel) sel.addEventListener('change', refresh);
  refresh();

  <?php if (!empty($staffModalAutoOpen)): ?>
  document.addEventListener('DOMContentLoaded', function(){
    var m = document.getElementById('staffModal');
    if (m && typeof bootstrap !== 'undefined') new bootstrap.Modal(m).show();
  });
  <?php endif; ?>
})();
</script>

<?php
clinic_page_end();
