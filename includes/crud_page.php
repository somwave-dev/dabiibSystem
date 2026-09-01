<?php
require_once __DIR__ . '/advanced_components.php';
require_once __DIR__ . '/../config/auth_login.php';

if (!function_exists('clinic_h')) {
    function clinic_h(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

function clinic_current_path(): string
{
    return basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
}

if (!function_exists('clinic_csrf_token')) {
    function clinic_csrf_token(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return (string) $_SESSION['csrf_token'];
    }
}

if (!function_exists('clinic_check_csrf')) {
    function clinic_check_csrf(): void
    {
        $sent = (string) ($_POST['csrf_token'] ?? '');
        if ($sent === '' || !hash_equals(clinic_csrf_token(), $sent)) {
            throw new RuntimeException('Invalid form token. Refresh and try again.');
        }
    }
}

function clinic_field_value(array $field, ?array $row): mixed
{
    return $row[$field['name']] ?? '';
}

function clinic_input_value(array $field, ?array $row): string
{
    if (($field['type'] ?? 'text') === 'password') {
        return '';
    }

    $value = clinic_field_value($field, $row);
    if ($value === null) {
        return '';
    }

    if (($field['type'] ?? 'text') === 'datetime-local' && is_string($value) && $value !== '') {
        return str_replace(' ', 'T', substr($value, 0, 16));
    }

    return (string) $value;
}

function clinic_post_value(array $field): mixed
{
    if (!empty($field['current_user'])) {
        return clinic_current_user_id();
    }

    $name = $field['name'];
    $type = $field['type'] ?? 'text';

    if ($type === 'checkbox') {
        return isset($_POST[$name]) ? 1 : 0;
    }

    $value = trim((string) ($_POST[$name] ?? ''));
    if ($type === 'datetime-local') {
        $value = str_replace('T', ' ', $value);
        if (strlen($value) === 16) {
            $value .= ':00';
        }
    }

    if ($value === '') {
        return !empty($field['required']) ? '' : null;
    }

    if ($type === 'number' && (($field['step'] ?? '') === '1')) {
        return (int) $value;
    }

    if ($type === 'number') {
        return (float) $value;
    }

    return $value;
}

function clinic_validate_required(array $config): array
{
    $errors = [];
    foreach ($config['fields'] as $field) {
        if (!empty($field['current_user'])) {
            continue;
        }

        if (empty($field['required'])) {
            continue;
        }

        $name = $field['name'];
        $type = $field['type'] ?? 'text';
        if ($type !== 'checkbox' && trim((string) ($_POST[$name] ?? '')) === '') {
            $errors[] = ($field['label'] ?? $name) . ' is required.';
        }
    }

    return $errors;
}

function clinic_lookup_options(array $field): array
{
    if (empty($field['lookup']) || !is_array($field['lookup'])) {
        return [];
    }

    try {
        return clinic_sp_rows((string) $field['lookup']['procedure']);
    } catch (Throwable $e) {
        return [];
    }
}

function clinic_render_field(array $field, ?array $editRow): void
{
    if (!empty($field['current_user'])) {
        return;
    }

    $name = $field['name'];
    $label = $field['label'] ?? $name;
    $type = $field['type'] ?? 'text';
    $value = clinic_input_value($field, $editRow);
    $required = !empty($field['required']) ? ' required' : '';
    $id = 'field_' . preg_replace('/[^A-Za-z0-9_]/', '_', $name);

    echo '<div class="col-md-6 mb-3">';
    if ($type === 'checkbox') {
        $checked = ((string) $value === '1') ? ' checked' : '';
        echo '<div class="form-check mt-4 pt-2">';
        echo '<input class="form-check-input" type="checkbox" id="' . clinic_h($id) . '" name="' . clinic_h($name) . '" value="1"' . $checked . '>';
        echo '<label class="form-check-label" for="' . clinic_h($id) . '">' . clinic_h($label) . '</label>';
        echo '</div></div>';
        return;
    }

    echo '<label class="form-label" for="' . clinic_h($id) . '">' . clinic_h($label);
    if ($required !== '') {
        echo ' <span class="text-danger">*</span>';
    }
    echo '</label>';

    if ($type === 'password') {
        echo '<input class="form-control" type="password" id="' . clinic_h($id) . '" name="' . clinic_h($name) . '" value="" autocomplete="new-password"' . $required . '>';
        if (!empty($field['hint'])) {
            echo '<div class="form-text">' . clinic_h((string) $field['hint']) . '</div>';
        }
        echo '</div>';
        return;
    }

    if ($type === 'textarea') {
        echo '<textarea class="form-control" id="' . clinic_h($id) . '" name="' . clinic_h($name) . '" rows="3"' . $required . '>' . clinic_h($value) . '</textarea>';
    } elseif ($type === 'select') {
        echo '<select class="form-select" id="' . clinic_h($id) . '" name="' . clinic_h($name) . '"' . $required . '>';
        echo '<option value="">-- Select --</option>';
        if (!empty($field['options'])) {
            foreach ($field['options'] as $option) {
                $selected = ((string) $option === $value) ? ' selected' : '';
                echo '<option value="' . clinic_h($option) . '"' . $selected . '>' . clinic_h($option) . '</option>';
            }
        } else {
            $lookup = $field['lookup'] ?? [];
            $valueKey = (string) ($lookup['value'] ?? '');
            $labelKey = (string) ($lookup['label'] ?? '');
            foreach (clinic_lookup_options($field) as $option) {
                $optionValue = (string) ($option[$valueKey] ?? '');
                $optionLabel = (string) ($option[$labelKey] ?? $optionValue);
                $selected = ($optionValue === $value) ? ' selected' : '';
                echo '<option value="' . clinic_h($optionValue) . '"' . $selected . '>' . clinic_h($optionLabel) . '</option>';
            }
        }
        echo '</select>';
        if (!empty($field['hint'])) {
            echo '<div class="form-text">' . clinic_h((string) $field['hint']) . '</div>';
        }
    } elseif ($type === 'file') {
        echo '<input class="form-control" type="file" id="' . clinic_h($id) . '" name="' . clinic_h($name) . '" accept="image/png,image/jpeg,image/gif,image/webp"' . $required . '>';
        echo '<input type="hidden" name="' . clinic_h($name) . '_Current" value="' . clinic_h($value) . '">';
        echo '<input type="hidden" name="' . clinic_h($name) . '_Remove" value="">';
        if ($value !== '' && $value !== null) {
            echo '<div class="form-check mt-2">';
            echo '<input class="form-check-input" type="checkbox" id="' . clinic_h($id) . '_remove" onchange="var f=document.querySelector(\'input[name=' . clinic_h($name) . '_Remove]\'); if(f){f.value=this.checked?\'1\':\'\';}">';
            echo '<label class="form-check-label" for="' . clinic_h($id) . '_remove">Remove current photo</label>';
            echo '</div>';
        }
        if (!empty($field['hint'])) {
            echo '<div class="form-text">' . clinic_h((string) $field['hint']) . '</div>';
        }
    } else {
        $step = isset($field['step']) ? ' step="' . clinic_h($field['step']) . '"' : '';
        echo '<input class="form-control" type="' . clinic_h($type) . '" id="' . clinic_h($id) . '" name="' . clinic_h($name) . '" value="' . clinic_h($value) . '"' . $step . $required . '>';
    }

    echo '</div>';
}

function clinic_display_value(string $key, mixed $value): string
{
    if ($value === null || $value === '') {
        return '-';
    }

    if ($key === 'Password_Hash') {
        return '********';
    }

    if ($key === 'Status') {
        $map = [
            'Pending' => 'warning',
            'Completed' => 'success',
            'Cancelled' => 'secondary',
            'active' => 'success',
            'inactive' => 'secondary',
            'Paid' => 'success',
            'Unpaid' => 'danger',
        ];
        $color = $map[(string) $value] ?? 'primary';

        return '<span class="badge text-bg-' . clinic_h($color) . '">' . clinic_h($value) . '</span>';
    }

    return clinic_h($value);
}

function clinic_render_crud_page(string $pageKey, ?string $subtitle = null, ?string $introHtml = null): void
{
    $schemas = require __DIR__ . '/../config/crud_schema.php';
    if (!isset($schemas[$pageKey])) {
        throw new RuntimeException('Unknown CRUD page.');
    }

    $config = $schemas[$pageKey];
    $pk = $config['pk'];
    $procedures = $config['procedures'];
    $errors = [];
    $notice = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            clinic_check_csrf();
            $action = (string) ($_POST['action'] ?? '');
            $id = (int) ($_POST[$pk] ?? 0);

            if ($action === 'delete') {
                clinic_sp_exec($procedures['delete'], [$id], 'i');
                header('Location: ' . clinic_current_path() . '?deleted=1', true, 303);
                exit;
            }

            if ($action === 'save') {
                $errors = clinic_validate_required($config);
                if ($pageKey === 'users' && $errors === []) {
                    $rawPwd = trim((string) ($_POST['Password_Hash'] ?? ''));
                    if ($id === 0 && $rawPwd === '') {
                        $errors[] = 'Password is required for new users.';
                    }
                    if ($errors === []) {
                        if ($rawPwd !== '') {
                            $_POST['Password_Hash'] = clinic_normalize_password_for_storage($rawPwd);
                        } elseif ($id > 0) {
                            $existing = clinic_sp_one($procedures['get'], [$id], 'i');
                            $_POST['Password_Hash'] = (string) ($existing['Password_Hash'] ?? '');
                        }
                    }
                }
                if ($pageKey === 'doctors' && $errors === []) {
                    $image = clinic_post_string('image_Current');
                    if (!empty($_FILES['image']) && (int) ($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                        $image = clinic_handle_avatar_upload('image');
                    } elseif (clinic_post_string('image_Remove') !== '') {
                        $image = '';
                    }
                    $_POST['image'] = $image;
                }
                if ($errors === []) {
                    $params = [$id];
                    foreach ($config['fields'] as $field) {
                        $params[] = clinic_post_value($field);
                    }
                    clinic_sp_exec($procedures['save'], $params);
                    header('Location: ' . clinic_current_path() . '?saved=1', true, 303);
                    exit;
                }
            }
        } catch (Throwable $e) {
            $errors[] = $e->getMessage();
        }
    }

    if (isset($_GET['saved'])) {
        $notice = 'Saved successfully.';
    } elseif (isset($_GET['deleted'])) {
        $notice = 'Deleted successfully.';
    }

    $editId = (int) ($_GET['edit'] ?? 0);
    $editRow = $editId > 0 ? clinic_sp_one($procedures['get'], [$editId], 'i') : null;
    $showModal = $editRow !== null;
    if ($errors !== [] && ($_POST['action'] ?? '') === 'save') {
        $editRow = [$pk => (int) ($_POST[$pk] ?? 0)];
        foreach ($config['fields'] as $field) {
            $name = $field['name'];
            $editRow[$name] = ($field['type'] ?? 'text') === 'checkbox'
                ? (isset($_POST[$name]) ? 1 : 0)
                : ($_POST[$name] ?? '');
        }
        $showModal = true;
    }
    $rows = clinic_sp_rows($procedures['list']);

    $GLOBALS['asset_base'] = '../';
    $GLOBALS['app_base'] = '../';
    $pageSubtitle = $subtitle ?? 'Stored procedure backed management page.';
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php require __DIR__ . '/head.php'; ?>
    <title><?php echo clinic_h($config['title']); ?> - Clinic</title>
</head>
<body>
    <div class="main-wrapper">
        <?php require __DIR__ . '/header.php'; ?>
        <?php require __DIR__ . '/sidebar.php'; ?>

        <div class="page-wrapper">
            <div class="content pb-0">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
                    <div>
                        <h4 class="fw-bold mb-1"><?php echo clinic_h($config['title']); ?></h4>
                        <p class="text-muted mb-0"><?php echo clinic_h($pageSubtitle); ?></p>
                    </div>
                    <?php if ($editRow): ?>
                        <a class="btn btn-outline-primary" href="<?php echo clinic_h(clinic_current_path()); ?>">
                            <i class="ti ti-plus me-1"></i>New
                        </a>
                    <?php else: ?>
                        <button class="btn btn-outline-primary" type="button" data-bs-toggle="modal" data-bs-target="#crudFormModal">
                            <i class="ti ti-plus me-1"></i>New
                        </button>
                    <?php endif; ?>
                </div>

                <?php if ($introHtml !== null && $introHtml !== ''): ?>
                    <?php echo $introHtml; ?>
                <?php endif; ?>

                <?php if ($notice !== ''): ?>
                    <script>
                        document.addEventListener('DOMContentLoaded', function () {
                            if (window.Swal) {
                                Swal.fire({
                                    toast: true,
                                    position: 'top-end',
                                    icon: 'success',
                                    title: 'Done',
                                    text: <?php echo json_encode($notice, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>,
                                    showConfirmButton: false,
                                    timer: 3200,
                                    timerProgressBar: true
                                });
                            }
                        });
                    </script>
                <?php endif; ?>
                <?php if ($errors !== []): ?>
                    <script>
                        document.addEventListener('DOMContentLoaded', function () {
                            if (window.Swal) {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Save failed',
                                    html: <?php echo json_encode(implode('<br>', array_map('clinic_h', $errors)), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>
                                });
                            }
                        });
                    </script>
                <?php endif; ?>

                <div class="card shadow-sm">
                    <div class="card-header d-flex align-items-center justify-content-between">
                        <h5 class="mb-0">Records</h5>
                        <span class="badge text-bg-light"><?php echo count($rows); ?> rows</span>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle datatable">
                                <thead>
                                    <tr>
                                        <?php $columns = $rows ? array_keys($rows[0]) : array_merge([$pk], array_column($config['fields'], 'name')); ?>
                                        <?php $avatarCfg = $config['avatar'] ?? null; ?>
                                        <?php foreach ($columns as $column): ?>
                                            <?php if ($column === 'image') { continue; } ?>
                                            <th><?php echo clinic_h(str_replace('_', ' ', $column)); ?></th>
                                        <?php endforeach; ?>
                                        <th class="text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($rows as $row): ?>
                                        <tr>
                                            <?php foreach ($columns as $column): ?>
                                                <?php if ($column === 'image') { continue; } ?>
                                                <?php if ($avatarCfg && $column === ($avatarCfg['name'] ?? '')): ?>
                                                <td>
                                                    <div class="d-flex align-items-center gap-2">
                                                        <?php echo clinic_avatar($row[$avatarCfg['image'] ?? 'image'] ?? '', $row[$column] ?? '', 'clinic-avatar-sm'); ?>
                                                        <span><?php echo clinic_display_value($column, $row[$column] ?? ''); ?></span>
                                                    </div>
                                                </td>
                                                <?php else: ?>
                                                <td><?php echo clinic_display_value($column, $row[$column] ?? ''); ?></td>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                            <td class="text-end">
                                                <?php if (!empty($config['profile'])): ?>
                                                <a class="btn btn-sm btn-outline-primary btn-icon" title="Profile" href="<?php echo clinic_h(clinic_current_path()); ?>?profile_id=<?php echo (int) ($row[$pk] ?? 0); ?>"><i class="ti ti-user"></i></a>
                                                <?php endif; ?>
                                                <a class="btn btn-sm btn-light border btn-icon" title="Edit" href="<?php echo clinic_h(clinic_current_path()); ?>?edit=<?php echo (int) ($row[$pk] ?? 0); ?>"><i class="ti ti-pencil"></i></a>
                                                <form class="d-inline js-confirm-delete" method="post" data-confirm-text="Delete this record?">
                                                    <input type="hidden" name="csrf_token" value="<?php echo clinic_h(clinic_csrf_token()); ?>">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="<?php echo clinic_h($pk); ?>" value="<?php echo (int) ($row[$pk] ?? 0); ?>">
                                                    <button class="btn btn-sm btn-outline-danger btn-icon" type="submit" title="Delete"><i class="ti ti-trash"></i></button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal fade" id="crudFormModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
                <div class="modal-dialog modal-lg modal-dialog-scrollable">
                    <form method="post" autocomplete="off" class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title"><?php echo $editRow ? 'Edit' : 'Add'; ?> <?php echo clinic_h($config['title']); ?></h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <input type="hidden" name="csrf_token" value="<?php echo clinic_h(clinic_csrf_token()); ?>">
                            <input type="hidden" name="action" value="save">
                            <input type="hidden" name="<?php echo clinic_h($pk); ?>" value="<?php echo clinic_h($editRow[$pk] ?? 0); ?>">
                            <div class="row">
                                <?php foreach ($config['fields'] as $field): ?>
                                    <?php clinic_render_field($field, $editRow); ?>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <a class="btn btn-danger" href="<?php echo clinic_h(clinic_current_path()); ?>"><i class="ti ti-x me-1"></i>Cancel</a>
                            <button class="btn btn-success" type="submit" id="crudBtnUpdate"<?php echo $editRow ? '' : ' style="display:none;"'; ?>><i class="ti ti-edit me-1"></i>Update</button>
                            <button class="btn btn-primary" type="submit" id="crudBtnSave"<?php echo $editRow ? ' style="display:none;"' : ''; ?>><i class="ti ti-device-floppy me-1"></i>Save</button>
                        </div>
                    </form>
                </div>
            </div>

            <?php require __DIR__ . '/footer.php'; ?>
        </div>
    </div>
    <?php require __DIR__ . '/plugins.php'; ?>
    <?php if ($showModal): ?>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        var modal = document.getElementById('crudFormModal');
        if (modal && window.bootstrap) {
            bootstrap.Modal.getOrCreateInstance(modal).show();
        }
    });
    </script>
    <?php endif; ?>
</body>
</html>
<?php
}
