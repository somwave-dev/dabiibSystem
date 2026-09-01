<?php
$assetBase = $GLOBALS['asset_base'] ?? '';
$faviconSrc = $assetBase . 'assets/img/favicon.png';
try {
    require_once __DIR__ . '/../config/codes.php';
    $favSetting = (string) ((new Codes())->setting('favicon'));
    if ($favSetting !== '') {
        if (!preg_match('#^[a-z][a-z0-9+.-]*://#i', $favSetting) && !str_starts_with($favSetting, '/')) {
            $favSetting = $assetBase . $favSetting;
        }
        $faviconSrc = $favSetting;
    }
} catch (Throwable $e) {
    // keep the default favicon
}
?>
<!-- Meta Tags -->
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Admin Dashboard - Medical & Hospital - Bootstrap 5 Admin Template</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta name="author" content="Dreams Technologies">
	
    <!-- Favicon -->
    <link rel="shortcut icon" href="<?php echo htmlspecialchars($faviconSrc, ENT_QUOTES, 'UTF-8'); ?>">

    <!-- Apple Icon -->
    <link rel="apple-touch-icon" href="<?php echo htmlspecialchars($assetBase, ENT_QUOTES, 'UTF-8'); ?>assets/img/apple-icon.png">

    <!-- Theme Config Js -->
    <script src="<?php echo htmlspecialchars($assetBase, ENT_QUOTES, 'UTF-8'); ?>assets/js/theme-script.js" ></script>

    <!-- Bootstrap CSS (local, template-tuned) -->
    <link rel="stylesheet" href="<?php echo htmlspecialchars($assetBase, ENT_QUOTES, 'UTF-8'); ?>assets/css/bootstrap.min.css">

    <!-- Datetimepicker CSS (CDN) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/eonasdan-bootstrap-datetimepicker/4.17.47/css/bootstrap-datetimepicker.min.css">

    <!-- Daterangepicker CSS (CDN) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/daterangepicker@3.1.0/daterangepicker.css">

    <!-- Font Awesome (CDN) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <!-- Tabler Icons (CDN) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@3.35.0/dist/tabler-icons.min.css">

    <!-- Simplebar CSS (CDN) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/simplebar@6.2.7/dist/simplebar.min.css">

    <!-- SweetAlert2 CSS (CDN) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.23.0/dist/sweetalert2.min.css">

    <!-- Select2 CSS (CDN) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">

    <!-- Main CSS (local) -->
    <link rel="stylesheet" href="<?php echo htmlspecialchars($assetBase, ENT_QUOTES, 'UTF-8'); ?>assets/css/style.css" id="app-style">

    <!-- DataTables CSS (CDN) -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">

    <style>
        .clinic-hero { background: linear-gradient(135deg, rgba(13,110,253,.08), rgba(255,255,255,0)); border: 1px solid rgba(0,0,0,.06); border-radius: 1rem; padding: 1.25rem; }
        .clinic-card { border: 1px solid rgba(0,0,0,.07); border-radius: .9rem; box-shadow: 0 8px 24px rgba(15,23,42,.04); }
        .clinic-metric-icon { width: 2.75rem; height: 2.75rem; display: inline-flex; align-items: center; justify-content: center; border-radius: .8rem; }
        .clinic-table th { font-size: .72rem; letter-spacing: .04em; text-transform: uppercase; color: #6c757d; }
        .clinic-patient-avatar { width: 2.75rem; height: 2.75rem; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-weight: 700; }
        .clinic-workflow-pill { border: 1px solid rgba(0,0,0,.08); background: #fff; border-radius: 999px; padding: .4rem .75rem; }
        .modal .modal-dialog:not(.modal-sm):not(.modal-xl):not(.modal-fullscreen):not(.modal-fullscreen-sm-down):not(.modal-fullscreen-md-down):not(.modal-fullscreen-lg-down):not(.modal-fullscreen-xl-down):not(.modal-fullscreen-xxl-down) { max-width: 860px; }
        .modal .modal-content { border: 0; border-radius: 22px; box-shadow: 0 24px 70px rgba(15, 23, 42, .22); overflow: hidden; }
        .modal .modal-header:not(.bg-danger):not(.bg-warning):not(.bg-success):not(.bg-info):not(.bg-primary):not(.text-danger) {
            background: #f8fafc;
            border-bottom: 1px solid rgba(15, 23, 42, .1);
            color: var(--bs-body-color);
            padding: 1.25rem 1.5rem;
        }
        .modal .modal-header .modal-title { font-weight: 800; }
        .modal .modal-header .btn-close { filter: none; opacity: 1; }
        .modal .modal-body { background: #f8fafc; padding: 1.5rem; }
        .modal .modal-footer { background: #f8fafc; border-top: 1px solid rgba(15, 23, 42, .08); padding: 1rem 1.5rem; }
        .modal .form-control,
        .modal .form-select,
        .modal .select2-container--default .select2-selection--single { border-radius: 12px; min-height: 44px; }
        .modal .select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 42px; }
        .modal .select2-container--default .select2-selection--single .select2-selection__arrow { height: 42px; }
        .modal .form-label { font-weight: 700; }
        /* DataTables premium UI (theme-aware) */
        .dataTables_wrapper .dataTables_filter input,
        .dataTables_wrapper .dataTables_length select {
            border: 1.5px solid var(--bs-border-color);
            border-radius: .5rem;
            background: var(--bs-body-bg);
            color: var(--bs-body-color);
            padding: .35rem .7rem;
        }
        .dataTables_wrapper .dataTables_filter input:focus,
        .dataTables_wrapper .dataTables_length select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 .18rem rgba(46, 55, 164, .12);
            outline: none;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button {
            border-radius: .45rem;
            margin: 0 2px;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button.current,
        .dataTables_wrapper .dataTables_paginate .paginate_button.current:hover {
            background: var(--primary) !important;
            border-color: var(--primary) !important;
            color: #fff !important;
            box-shadow: 0 4px 10px rgba(46, 55, 164, .25);
        }
        /* ===== Colorful theme: backgrounds & cards stand out ===== */
        body { background: linear-gradient(160deg, #eef3fc 0%, #f8fafd 50%, #e9eff9 100%) !important; }
        .page-wrapper .card {
            border-color: rgba(46, 55, 164, .14) !important;
            box-shadow: 0 8px 22px rgba(46, 55, 164, .08) !important;
        }
        html[data-bs-theme="dark"] body { background: linear-gradient(160deg, #101418 0%, #0f1115 55%, #151a21 100%) !important; }
        html[data-bs-theme="dark"] .page-wrapper .card {
            border-color: #2a3140 !important;
            box-shadow: 0 8px 22px rgba(0, 0, 0, .35) !important;
        }
        /* ===== Icon-only action buttons ===== */
        .btn-icon { width: 32px; height: 32px; padding: 0; display: inline-flex; align-items: center; justify-content: center; border-radius: .45rem; }
        /* ===== Avatar (photo or initials fallback) ===== */
        .clinic-avatar { width: 40px; height: 40px; border-radius: 50%; flex-shrink: 0; }
        .clinic-avatar-img { object-fit: cover; display: inline-block; }
        .clinic-avatar-letter { display: inline-flex; align-items: center; justify-content: center; font-weight: 800; background: var(--primary-transparent); color: var(--primary); }
        .clinic-avatar-sm { width: 32px; height: 32px; font-size: .9rem; }
        .clinic-avatar-md { width: 48px; height: 48px; font-size: 1.15rem; }
        .clinic-avatar-lg { width: 96px; height: 96px; font-size: 2.1rem; border-radius: 1rem; }
        .clinic-avatar-xl { width: 120px; height: 120px; font-size: 2.6rem; border-radius: .9rem; }
        /* ===== Modal form fields — "border border-secondary" reference style ===== */
        .modal .form-control,
        .modal .form-select,
        .modal .form-check-input { border-color: #6c757d; }
        /* ===== Selects + Select2 — reference style ===== */
        .form-select { border-color: #6c757d; }
        .select2-container .select2-selection--single,
        .select2-container .select2-selection--multiple { min-height: 38px; }
        .select2-container--default .select2-selection--single {
            border: 1px solid #6c757d !important;
            border-radius: .375rem;
            background-color: #fff;
        }
        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 36px;
            padding-left: .75rem;
            color: var(--bs-body-color) !important;
        }
        .select2-container--default .select2-selection--single .select2-selection__placeholder { color: #6c757d !important; }
        .select2-container--default .select2-selection--single .select2-selection__arrow { height: 36px; right: 6px; }
        .select2-container--default .select2-selection--multiple {
            border: 1px solid #6c757d !important;
            border-radius: .375rem;
            padding: 2px 6px;
        }
        .select2-container--default .select2-selection--multiple .select2-selection__choice {
            border-radius: .375rem;
            background: var(--bs-primary-bg-subtle, #e7f1ff);
            border-color: var(--bs-primary-border-subtle, #b6d4fe);
            color: var(--bs-primary, #0d6efd);
        }
        .select2-container--default .select2-selection--multiple .select2-selection__choice__remove { color: var(--bs-primary, #0d6efd); }
        .select2-dropdown {
            border: 1px solid #6c757d;
            border-radius: .5rem;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(15, 23, 42, .12);
        }
        .select2-container--default .select2-results__option--highlighted[aria-selected] { background-color: var(--bs-primary) !important; }
        .select2-search--dropdown .select2-search__field {
            border: 1px solid #ced4da;
            border-radius: .375rem;
            padding: .3rem .5rem;
        }
        /* ===== Modals open from the top (no vertical centering) ===== */
        .modal .modal-dialog { align-items: flex-start; margin-top: 2rem; }
    </style>
    <script>
        // Every modal opens with a static backdrop (can't be dismissed by clicking
        // outside) and keyboard disabled (Esc won't close it). A modal that
        // explicitly opts out with data-bs-backdrop="true" stays dismissible.
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('.modal').forEach(function (m) {
                var bd = m.getAttribute('data-bs-backdrop');
                if (!bd || bd !== 'true') {
                    m.setAttribute('data-bs-backdrop', 'static');
                }
                m.setAttribute('data-bs-keyboard', 'false');
            });
        });
    </script>
