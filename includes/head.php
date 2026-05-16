<?php $assetBase = $GLOBALS['asset_base'] ?? ''; ?>
<!-- Meta Tags -->
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Admin Dashboard - Medical & Hospital - Bootstrap 5 Admin Template</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta name="author" content="Dreams Technologies">
	
    <!-- Favicon -->
    <link rel="shortcut icon" href="<?php echo htmlspecialchars($assetBase, ENT_QUOTES, 'UTF-8'); ?>assets/img/favicon.png">

    <!-- Apple Icon -->
    <link rel="apple-touch-icon" href="<?php echo htmlspecialchars($assetBase, ENT_QUOTES, 'UTF-8'); ?>assets/img/apple-icon.png">

    <!-- Theme Config Js -->
    <script src="<?php echo htmlspecialchars($assetBase, ENT_QUOTES, 'UTF-8'); ?>assets/js/theme-script.js" ></script>

    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="<?php echo htmlspecialchars($assetBase, ENT_QUOTES, 'UTF-8'); ?>assets/css/bootstrap.min.css">

    <!-- Datetimepicker CSS -->
	<link rel="stylesheet" href="<?php echo htmlspecialchars($assetBase, ENT_QUOTES, 'UTF-8'); ?>assets/css/bootstrap-datetimepicker.min.css">
    
    <!-- Daterangepikcer CSS -->
	<link rel="stylesheet" href="<?php echo htmlspecialchars($assetBase, ENT_QUOTES, 'UTF-8'); ?>assets/plugins/daterangepicker/daterangepicker.css">

    <!-- Fontawesome CSS -->
	<link rel="stylesheet" href="<?php echo htmlspecialchars($assetBase, ENT_QUOTES, 'UTF-8'); ?>assets/plugins/fontawesome/css/fontawesome.min.css">
	<link rel="stylesheet" href="<?php echo htmlspecialchars($assetBase, ENT_QUOTES, 'UTF-8'); ?>assets/plugins/fontawesome/css/all.min.css">

    <!-- Tabler Icon CSS -->
    <link rel="stylesheet" href="<?php echo htmlspecialchars($assetBase, ENT_QUOTES, 'UTF-8'); ?>assets/plugins/tabler-icons/tabler-icons.min.css">

    <!-- Simplebar CSS -->
    <link rel="stylesheet" href="<?php echo htmlspecialchars($assetBase, ENT_QUOTES, 'UTF-8'); ?>assets/plugins/simplebar/simplebar.min.css">

    <!-- SweetAlert2 CSS -->
    <link rel="stylesheet" href="<?php echo htmlspecialchars($assetBase, ENT_QUOTES, 'UTF-8'); ?>assets/plugins/sweetalert2/sweetalert2.min.css">

    <!-- Select2 CSS -->
    <link rel="stylesheet" href="<?php echo htmlspecialchars($assetBase, ENT_QUOTES, 'UTF-8'); ?>assets/plugins/select2/css/select2.min.css">

    <!-- Main CSS -->
    <link rel="stylesheet" href="<?php echo htmlspecialchars($assetBase, ENT_QUOTES, 'UTF-8'); ?>assets/css/style.css" id="app-style">

    <!-- Datatable CSS (see data-tables.html) -->
    <link rel="stylesheet" href="<?php echo htmlspecialchars($assetBase, ENT_QUOTES, 'UTF-8'); ?>assets/css/dataTables.bootstrap5.min.css">

    <style>
        .clinic-hero { background: linear-gradient(135deg, rgba(13,110,253,.08), rgba(255,255,255,0)); border: 1px solid rgba(0,0,0,.06); border-radius: 1rem; padding: 1.25rem; }
        .clinic-card { border: 1px solid rgba(0,0,0,.07); border-radius: .9rem; box-shadow: 0 8px 24px rgba(15,23,42,.04); }
        .clinic-metric-icon { width: 2.75rem; height: 2.75rem; display: inline-flex; align-items: center; justify-content: center; border-radius: .8rem; }
        .clinic-table th { font-size: .72rem; letter-spacing: .04em; text-transform: uppercase; color: #6c757d; }
        .clinic-patient-avatar { width: 2.75rem; height: 2.75rem; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-weight: 700; }
        .clinic-workflow-pill { border: 1px solid rgba(0,0,0,.08); background: #fff; border-radius: 999px; padding: .4rem .75rem; }
        .modal .modal-dialog:not(.modal-sm):not(.modal-xl):not(.modal-fullscreen):not(.modal-fullscreen-sm-down):not(.modal-fullscreen-md-down):not(.modal-fullscreen-lg-down):not(.modal-fullscreen-xl-down):not(.modal-fullscreen-xxl-down) { max-width: 860px; }
        .modal .modal-content { border: 0; border-radius: 22px; box-shadow: 0 24px 70px rgba(15, 23, 42, .22); overflow: hidden; }
        .modal .modal-header:not(.bg-danger):not(.bg-warning):not(.bg-success):not(.bg-info):not(.bg-primary):not(.text-danger) { background: linear-gradient(135deg, var(--primary, #0d6efd), #5b7cfa); color: #fff; padding: 1.25rem 1.5rem; }
        .modal .modal-header .modal-title { font-weight: 800; }
        .modal .modal-header:not(.text-danger) .text-muted,
        .modal .modal-header:not(.text-danger) .small { color: rgba(255,255,255,.78) !important; }
        .modal .modal-header:not(.text-danger) .btn-close { filter: brightness(0) invert(1); opacity: .9; }
        .modal .modal-body { background: #f8fafc; padding: 1.5rem; }
        .modal .modal-footer { background: #f8fafc; border-top: 1px solid rgba(15, 23, 42, .08); padding: 1rem 1.5rem; }
        .modal .form-control,
        .modal .form-select,
        .modal .select2-container--default .select2-selection--single { border-radius: 12px; min-height: 44px; }
        .modal .select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 42px; }
        .modal .select2-container--default .select2-selection--single .select2-selection__arrow { height: 42px; }
        .modal .form-label { font-weight: 700; }
    </style>
