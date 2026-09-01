<?php $assetBase = $GLOBALS['asset_base'] ?? ''; ?>
<!-- jQuery (CDN) -->
	<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<!-- DataTables (CDN — must load before script.js so $.fn.DataTable exists for .datatable init in script.js) -->
	<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
	<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<!-- Bootstrap Bundle (CDN) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- SweetAlert2 (CDN) -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.23.0/dist/sweetalert2.min.js"></script>

<!-- Select2 (CDN) -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<!-- Simplebar JS (CDN) -->
<script src="https://cdn.jsdelivr.net/npm/simplebar@6.2.7/dist/simplebar.min.js"></script>

<!-- ApexCharts (CDN) -->
<script src="https://cdn.jsdelivr.net/npm/apexcharts@5.3.5/dist/apexcharts.min.js"></script>
<script src="<?php echo htmlspecialchars($assetBase, ENT_QUOTES, 'UTF-8'); ?>assets/plugins/apexchart/chart-data.js"></script>

<!-- Moment + Daterangepicker + Datetimepicker (CDN) -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.4/moment.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/daterangepicker@3.1.0/daterangepicker.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/eonasdan-bootstrap-datetimepicker/4.17.47/js/bootstrap-datetimepicker.min.js"></script>

<!-- Sortable (drag & drop, used by e.g. menues.php) -->
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>

<!-- Main JS (local — inits .datatable per assets/js/script.js, requires DataTables scripts above) -->
<script src="<?php echo htmlspecialchars($assetBase, ENT_QUOTES, 'UTF-8'); ?>assets/js/script.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.modal-dialog').forEach(function (dialog) {
        var isFullscreen = Array.prototype.some.call(dialog.classList, function (className) {
            return className.indexOf('modal-fullscreen') === 0;
        });

        // Modals open from the top (no vertical centering).
        dialog.classList.remove('modal-dialog-centered');
        if (!isFullscreen && !dialog.classList.contains('modal-dialog-scrollable')) {
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

    // SweetAlert2 delete confirmation (used by generic CRUD pages)
    document.querySelectorAll('.js-confirm-delete').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            if (!window.Swal) { return; }
            var text = form.getAttribute('data-confirm-text') || 'Delete this record?';
            Swal.fire({
                title: 'Are you sure?',
                text: text,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, delete it!',
                cancelButtonText: 'Cancel'
            }).then(function (result) {
                if (result.isConfirmed) {
                    form.submit();
                }
            });
        });
    });
});
</script>
