<?php $assetBase = $GLOBALS['asset_base'] ?? ''; ?>
<!-- jQuery -->
	<script src="<?php echo htmlspecialchars($assetBase, ENT_QUOTES, 'UTF-8'); ?>assets/js/jquery-3.7.1.min.js"></script>

<!-- Datatable JS (must load before script.js so $.fn.DataTable exists for .datatable init in script.js) -->
	<script src="<?php echo htmlspecialchars($assetBase, ENT_QUOTES, 'UTF-8'); ?>assets/js/jquery.dataTables.min.js"></script>
	<script src="<?php echo htmlspecialchars($assetBase, ENT_QUOTES, 'UTF-8'); ?>assets/js/dataTables.bootstrap5.min.js"></script>

<!-- Bootstrap Core JS -->
<script src="<?php echo htmlspecialchars($assetBase, ENT_QUOTES, 'UTF-8'); ?>assets/js/bootstrap.bundle.min.js"></script>

<!-- SweetAlert2 -->
<script src="<?php echo htmlspecialchars($assetBase, ENT_QUOTES, 'UTF-8'); ?>assets/plugins/sweetalert2/sweetalert2.min.js"></script>

<!-- Select2 -->
<script src="<?php echo htmlspecialchars($assetBase, ENT_QUOTES, 'UTF-8'); ?>assets/plugins/select2/js/select2.min.js"></script>

<!-- Simplebar JS -->
<script src="<?php echo htmlspecialchars($assetBase, ENT_QUOTES, 'UTF-8'); ?>assets/plugins/simplebar/simplebar.min.js"></script>

<!-- ApexCharts -->
<script src="<?php echo htmlspecialchars($assetBase, ENT_QUOTES, 'UTF-8'); ?>assets/plugins/apexchart/apexcharts.min.js"></script>
<script src="<?php echo htmlspecialchars($assetBase, ENT_QUOTES, 'UTF-8'); ?>assets/plugins/apexchart/chart-data.js"></script>

<!-- Daterangepicker & Moment -->
<script src="<?php echo htmlspecialchars($assetBase, ENT_QUOTES, 'UTF-8'); ?>assets/js/moment.min.js"></script>
<script src="<?php echo htmlspecialchars($assetBase, ENT_QUOTES, 'UTF-8'); ?>assets/plugins/daterangepicker/daterangepicker.js"></script>
<script src="<?php echo htmlspecialchars($assetBase, ENT_QUOTES, 'UTF-8'); ?>assets/js/bootstrap-datetimepicker.min.js"></script>

<!-- Sortable (drag & drop, used by e.g. menues.php) -->
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>

<!-- Main JS (inits .datatable per assets/js/script.js — requires DataTables scripts above) -->
<script src="<?php echo htmlspecialchars($assetBase, ENT_QUOTES, 'UTF-8'); ?>assets/js/script.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.modal-dialog').forEach(function (dialog) {
        var isFullscreen = Array.prototype.some.call(dialog.classList, function (className) {
            return className.indexOf('modal-fullscreen') === 0;
        });

        if (!isFullscreen && !dialog.classList.contains('modal-dialog-centered')) {
            dialog.classList.add('modal-dialog-centered');
        }
        if (!dialog.classList.contains('modal-dialog-scrollable')) {
            dialog.classList.add('modal-dialog-scrollable');
        }
    });

    if (!window.jQuery || !jQuery.fn.select2) {
        return;
    }

    function clinicInitSelect2(scope) {
        jQuery(scope || document).find('select:not([multiple]):not(.no-select2)').each(function () {
            var $select = jQuery(this);
            if ($select.hasClass('select2-hidden-accessible')) {
                return;
            }

            $select.addClass('select2');
            $select.select2({
                dropdownParent: $select.closest('.modal').length ? $select.closest('.modal') : jQuery(document.body),
                width: '100%'
            });
        });
    }

    clinicInitSelect2(document);
    document.addEventListener('shown.bs.modal', function (event) {
        clinicInitSelect2(event.target);
    });
});
</script>
