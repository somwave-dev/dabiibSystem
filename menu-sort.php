<?php
/**
 * Menu & Submenu drag-sort page.
 * Drag and drop to reorder main menus and their submenus.
 */
require_once __DIR__ . '/config/session.php';
requireLogin();

require_once __DIR__ . '/config/codes.php';

$co   = new Codes();
$conn = $co->db;

$sql = "SELECT m.* FROM menues m WHERE m.status='active' AND m.deleted=0 AND EXISTS (
    SELECT *
    FROM submenues s
    WHERE s.menu_id = m.menu_id
    AND s.status='active'
    AND s.deleted=0
)
ORDER BY m.sort_order ASC, m.menu_id ASC";
$menus_result = $conn->query($sql);

if (!$menus_result) {
    die('Query failed: ' . $conn->error);
}

$all_submenus_sql = "SELECT * FROM submenues WHERE status='active' AND deleted=0 ORDER BY sort_order ASC";
$all_submenus_result = $conn->query($all_submenus_sql);
$submenus_by_menu = [];
if ($all_submenus_result) {
    while ($sub = $all_submenus_result->fetch_assoc()) {
        $submenus_by_menu[$sub['menu_id']][] = $sub;
    }
}
$total_menus = (int) $menus_result->num_rows;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php require_once 'includes/head.php'; ?>
    <title>Menu &amp; Submenu Sorting — Clinic</title>
    <style>
        :root {
            --ms-card-bg: #fff;
            --ms-card-border: #e2e8f0;
            --ms-header-bg: #f8fafc;
            --ms-header-text: #475569;
            --ms-row-bg: #fff;
            --ms-row-hover: #f8fafc;
            --ms-submenu-bg: #f8fafc;
            --ms-submenu-row-bg: #fff;
            --ms-text-primary: #0f172a;
            --ms-text-secondary: #475569;
            --ms-text-muted: #64748b;
            --ms-border: #e2e8f0;
            --ms-drag-handle: #94a3b8;
            --ms-badge-bg: #eef2ff;
            --ms-badge-text: #4f46e5;
            --ms-order-bg: #f1f5f9;
        }
        [data-bs-theme="dark"], [data-theme="dark"] {
            --ms-card-bg: #1F242D;
            --ms-card-border: #303845;
            --ms-header-bg: #181B22;
            --ms-header-text: #A8B0BD;
            --ms-row-bg: #1F242D;
            --ms-row-hover: #272D37;
            --ms-submenu-bg: #181B22;
            --ms-submenu-row-bg: #1F242D;
            --ms-text-primary: #FFFFFF;
            --ms-text-secondary: #A8B0BD;
            --ms-text-muted: #8E98A8;
            --ms-border: #303845;
            --ms-drag-handle: #64748b;
            --ms-badge-bg: #1e3a5f;
            --ms-badge-text: #60a5fa;
            --ms-order-bg: #2d2d3f;
        }
        .sorting-container {
            background: var(--ms-card-bg);
            border: 1px solid var(--ms-card-border);
            border-radius: 12px;
            margin-bottom: 80px;
            overflow: hidden;
        }
        .sorting-header {
            display: grid;
            grid-template-columns: 40px 30px 1fr 100px 40px;
            background: var(--ms-header-bg);
            padding: 12px 15px;
            border-bottom: 2px solid var(--ms-border);
            font-weight: 600;
            color: var(--ms-header-text);
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .menu-row { border-bottom: 1px solid var(--ms-border); }
        .menu-row:last-child { border-bottom: none; }
        .menu-main {
            display: grid;
            grid-template-columns: 40px 30px 1fr 100px 40px;
            padding: 12px 15px;
            background: var(--ms-row-bg);
            align-items: center;
            color: var(--ms-text-primary);
        }
        .menu-main:hover { background: var(--ms-row-hover); }
        .submenu-wrapper {
            background: var(--ms-submenu-bg);
            border-top: 1px dashed var(--ms-border);
            padding: 10px 0 10px 70px;
        }
        .submenu-row {
            display: grid;
            grid-template-columns: 30px 1fr 150px 80px;
            padding: 8px 15px;
            margin: 2px 0;
            background: var(--ms-submenu-row-bg);
            border: 1px solid var(--ms-border);
            border-radius: 8px;
            align-items: center;
            color: var(--ms-text-primary);
        }
        .submenu-row:hover { background: var(--ms-row-hover); border-color: var(--ms-text-muted); }
        .drag-handle { color: var(--ms-text-muted); cursor: grab; font-size: 14px; text-align: center; }
        .drag-handle:hover { color: var(--ms-text-primary); }
        .drag-handle:active { cursor: grabbing; }
        .menu-icon {
            width: 24px; height: 24px;
            background: var(--ms-order-bg);
            border-radius: 6px;
            display: flex; align-items: center; justify-content: center;
            color: var(--ms-text-secondary); font-size: 12px;
        }
        .menu-name { font-weight: 500; color: var(--ms-text-primary); font-size: 14px; }
        .submenu-name { color: var(--ms-text-primary); font-size: 13px; }
        .submenu-url { color: var(--ms-text-muted); font-size: 12px; font-family: monospace; }
        .badge-menu { background: var(--ms-badge-bg); color: var(--ms-badge-text); padding: 3px 8px; border-radius: 6px; font-size: 11px; font-weight: 500; }
        .badge-submenu { background: #fef3c7; color: #92400e; padding: 3px 8px; border-radius: 6px; font-size: 11px; font-weight: 500; }
        .badge-count { background: var(--ms-order-bg); color: var(--ms-text-secondary); padding: 2px 6px; border-radius: 6px; font-size: 11px; }
        .order-num { background: var(--ms-order-bg); color: var(--ms-text-primary); padding: 3px 8px; border-radius: 6px; font-size: 12px; font-weight: 500; text-align: center; }
        .expand-btn {
            background: transparent; border: 1px solid var(--ms-border);
            width: 28px; height: 28px; border-radius: 6px;
            display: flex; align-items: center; justify-content: center;
            color: var(--ms-text-muted); cursor: pointer; font-size: 12px;
        }
        .expand-btn:hover { background: var(--ms-row-hover); border-color: var(--ms-text-muted); color: var(--ms-text-primary); }
        .submenu-wrapper.collapsed { display: none; }
        .action-bar { display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap; }
        .simple-btn {
            padding: 6px 15px; background: var(--ms-card-bg); border: 1px solid var(--ms-border);
            border-radius: 8px; color: var(--ms-text-secondary); font-size: 13px;
            display: inline-flex; align-items: center; gap: 6px; cursor: pointer; transition: all 0.15s;
        }
        .simple-btn:hover { background: var(--ms-row-hover); border-color: var(--ms-text-muted); color: var(--ms-text-primary); }
        .simple-btn.primary { background: #1971c2; border-color: #1864ab; color: white; }
        .simple-btn.primary:hover { background: #1864ab; }
        .search-simple { margin-bottom: 20px; }
        .search-simple input {
            width: 100%; max-width: 300px; padding: 8px 12px;
            border: 1px solid var(--ms-border); border-radius: 8px; font-size: 13px;
            background: var(--ms-card-bg); color: var(--ms-text-primary);
        }
        .search-simple input:focus { outline: none; border-color: #1971c2; box-shadow: 0 0 0 3px rgba(25, 113, 194, 0.1); }
        .filter-group { display: flex; gap: 5px; margin-bottom: 20px; flex-wrap: wrap; }
        .filter-tag { padding: 4px 12px; background: var(--ms-card-bg); border: 1px solid var(--ms-border); border-radius: 8px; color: var(--ms-text-secondary); font-size: 12px; cursor: pointer; }
        .filter-tag.active { background: #1971c2; border-color: #1864ab; color: white; }
        .save-btn-fixed {
            position: fixed; bottom: 30px; right: 30px;
            padding: 10px 25px; background: #1971c2; border: none; border-radius: 3px;
            color: white; font-weight: 500; font-size: 14px;
            display: flex; align-items: center; gap: 8px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1); cursor: pointer; z-index: 1000;
        }
        .save-btn-fixed:hover { background: #1864ab; }
        .save-btn-fixed:disabled { opacity: 0.6; cursor: not-allowed; }
        .status-simple { padding: 10px 15px; border-radius: 3px; margin-bottom: 20px; font-size: 13px; border: 1px solid transparent; }
        .status-simple.success { background: #d3f9d8; border-color: #8ce99a; color: #2b8a3e; }
        .status-simple.error { background: #ffe3e3; border-color: #ffa8a8; color: #c92a2a; }
        .status-simple.info { background: #d0ebff; border-color: #74c0fc; color: #1971c2; }
        .status-simple.warning { background: #fff3bf; border-color: #ffe066; color: #e67700; }
        .empty-state { text-align: center; padding: 60px 20px; background: var(--ms-card-bg); border: 1px dashed var(--ms-border); border-radius: 12px; }
        .empty-state i { font-size: 40px; color: var(--ms-text-muted); margin-bottom: 15px; }
        .empty-state h5 { color: var(--ms-text-primary); margin-bottom: 10px; }
        .empty-state p { color: var(--ms-text-secondary); margin-bottom: 20px; }
        .loading-overlay {
            display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0;
            background: var(--ms-card-bg); opacity: 0.9; z-index: 9999;
            align-items: center; justify-content: center;
        }
        .loading-overlay p { color: var(--ms-text-primary); }
        .sortable-ghost { opacity: 0.5; background: var(--ms-header-bg); border: 1px dashed #3B82F6; }
        .sortable-drag { opacity: 0.8; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1); }
    </style>
</head>
<body>
    <div class="main-wrapper">
        <?php require_once 'includes/header.php'; ?>
        <?php require_once 'includes/sidebar.php'; ?>

        <div class="page-wrapper">
            <div class="content pb-0">
                <div class="container-fluid">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                        <div>
                            <h4 class="fw-bold mb-0"><i class="ti ti-arrows-sort me-2"></i>Menu &amp; Submenu Sorting</h4>
                            <p class="text-muted small mb-0 mt-1">Drag and drop to reorder menus and submenus</p>
                        </div>
                        <a class="btn btn-light border btn-sm" href="index.php"><i class="ti ti-layout-dashboard me-1"></i>Dashboard</a>
                    </div>

                    <div class="row mt-4">
                        <div class="col-lg-12">
                            <!-- Status Message -->
                            <div id="statusMsg" class="status-simple d-none">
                                <span class="status-text"></span>
                            </div>

                            <?php if ($total_menus > 0): ?>
                                <!-- Search and Filter -->
                                <div class="search-simple">
                                    <input type="text" id="searchInput" placeholder="Search menus and submenus...">
                                </div>

                                <div class="filter-group">
                                    <span class="filter-tag active" data-filter="all">All</span>
                                    <span class="filter-tag" data-filter="menus">Menus</span>
                                    <span class="filter-tag" data-filter="with-submenus">With Submenus</span>
                                    <span class="filter-tag" data-filter="without-submenus">Without Submenus</span>
                                </div>

                                <!-- Action Buttons -->
                                <div class="action-bar">
                                    <button class="simple-btn" onclick="expandAll()">
                                        <i class="fas fa-chevron-down"></i> Expand All
                                    </button>
                                    <button class="simple-btn" onclick="collapseAll()">
                                        <i class="fas fa-chevron-up"></i> Collapse All
                                    </button>
                                    <button class="simple-btn" onclick="resetToDefault()">
                                        <i class="fas fa-undo"></i> Reset
                                    </button>
                                </div>
                            <?php endif; ?>
                            <?php if ($total_menus > 0): ?>
                                <div class="sorting-container">
                                    <div class="sorting-header">
                                        <div></div>
                                        <div></div>
                                        <div>Name</div>
                                        <div>Order</div>
                                        <div></div>
                                    </div>

                                    <div id="menuSortContainer">
                                        <?php
                                        $menu_position = 1;
                                        $menus_result->data_seek(0);
                                        while ($menu = $menus_result->fetch_assoc()):
                                            $has_submenus = isset($submenus_by_menu[$menu['menu_id']]) && count($submenus_by_menu[$menu['menu_id']]) > 0;
                                            $submenu_count = $has_submenus ? count($submenus_by_menu[$menu['menu_id']]) : 0;
                                            ?>
                                            <div class="menu-row" data-id="<?= (int) $menu['menu_id']; ?>" data-type="menu"
                                                data-has-submenus="<?= $has_submenus ? 'true' : 'false' ?>"
                                                data-menu-name="<?= htmlspecialchars(strtolower($menu['menu_name']), ENT_QUOTES, 'UTF-8'); ?>">
                                                <div class="menu-main">
                                                    <div class="drag-handle" title="Drag to reorder">
                                                        <i class="fas fa-grip-vertical"></i>
                                                    </div>
                                                    <div class="menu-icon">
                                                        <i class="<?= htmlspecialchars((string) $menu['icon'], ENT_QUOTES, 'UTF-8'); ?>"></i>
                                                    </div>
                                                    <div class="menu-name">
                                                        <?= htmlspecialchars((string) $menu['menu_name'], ENT_QUOTES, 'UTF-8'); ?>
                                                        <?php if ($submenu_count > 0): ?>
                                                            <span class="badge-count ms-2"><?= $submenu_count ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="order-num">
                                                        <span class="current-order"><?= $menu_position; ?></span>
                                                    </div>
                                                    <div>
                                                        <?php if ($has_submenus): ?>
                                                            <button class="expand-btn" onclick="toggleSubmenu(this)">
                                                                <i class="fas fa-chevron-down"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                <?php if ($has_submenus): ?>
                                                    <div class="submenu-wrapper collapsed">
                                                        <?php
                                                        $sub_position = 1;
                                                        foreach ($submenus_by_menu[$menu['menu_id']] as $sub):
                                                            ?>
                                                            <div class="submenu-row submenu-item"
                                                                data-id="<?= (int) $sub['submenu_id']; ?>" data-type="submenu"
                                                                data-parent-id="<?= (int) $menu['menu_id']; ?>"
                                                                data-submenu-name="<?= htmlspecialchars(strtolower($sub['submenu_name']), ENT_QUOTES, 'UTF-8'); ?>">
                                                                <div class="drag-handle" title="Drag to reorder">
                                                                    <i class="fas fa-grip-vertical"></i>
                                                                </div>
                                                                <div>
                                                                    <div class="submenu-name"><?= htmlspecialchars((string) $sub['submenu_name'], ENT_QUOTES, 'UTF-8'); ?></div>
                                                                    <div class="submenu-url"><?= htmlspecialchars((string) $sub['menu_url'], ENT_QUOTES, 'UTF-8'); ?></div>
                                                                </div>
                                                                <div class="badge-submenu">SUBMENU</div>
                                                                <div class="order-num">
                                                                    <span class="current-suborder"><?= $sub_position; ?></span>
                                                                </div>
                                                            </div>
                                                            <?php
                                                            $sub_position++;
                                                        endforeach;
                                                        ?>
                                                    </div>
                                                <?php endif; ?>

                                            </div>
                                            <?php
                                            $menu_position++;
                                        endwhile;
                                        ?>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="empty-state">
                                    <i class="fas fa-list"></i>
                                    <h5>No Active Menus</h5>
                                    <p>Add menus to start sorting</p>
                                    <a href="menues.php" class="simple-btn primary">
                                        <i class="fas fa-plus"></i> Add Menus
                                    </a>
                                </div>
                            <?php endif; ?>

                        </div>
                    </div>
                </div>
            </div>
            <?php require_once 'includes/footer.php'; ?>
        </div>
    </div>

    <?php if ($total_menus > 0): ?>
        <!-- Save Button -->
        <button id="saveOrder" class="save-btn-fixed">
            <i class="fas fa-save"></i> Save Order
        </button>

        <!-- Loading Overlay -->
        <div class="loading-overlay" id="loadingOverlay">
            <div class="text-center">
                <div class="spinner-border text-primary mb-3" role="status"></div>
                <p>Saving changes...</p>
            </div>
        </div>
    <?php endif; ?>

    <?php require_once 'includes/plugins.php'; ?>
    <script>
        let mainSortable = null;
        let submenuSortables = [];
        const menuContainer = document.getElementById('menuSortContainer');
        const saveBtn = document.getElementById('saveOrder');
        const statusBox = document.getElementById('statusMsg');
        const statusText = statusBox ? statusBox.querySelector('.status-text') : null;
        const loadingOverlay = document.getElementById('loadingOverlay');

        if (menuContainer && menuContainer.querySelector('.menu-row')) {
            mainSortable = new Sortable(menuContainer, {
                animation: 150,
                handle: '.drag-handle',
                ghostClass: 'sortable-ghost',
                dragClass: 'sortable-drag',
                onEnd: function () {
                    updateAllOrderNumbers();
                    showStatus('info', 'Order changed - click Save to apply', 3000);
                }
            });

            document.querySelectorAll('.submenu-wrapper').forEach(wrapper => {
                const sortable = new Sortable(wrapper, {
                    animation: 150,
                    handle: '.drag-handle',
                    ghostClass: 'sortable-ghost',
                    dragClass: 'sortable-drag',
                    onEnd: function () {
                        updateAllOrderNumbers();
                        showStatus('info', 'Order changed - click Save to apply', 3000);
                    }
                });
                submenuSortables.push(sortable);
            });
        }

        function toggleSubmenu(btn) {
            const wrapper = btn.closest('.menu-row').querySelector('.submenu-wrapper');
            const icon = btn.querySelector('i');

            wrapper.classList.toggle('collapsed');
            icon.className = wrapper.classList.contains('collapsed') ? 'fas fa-chevron-down' : 'fas fa-chevron-up';
        }

        function expandAll() {
            document.querySelectorAll('.submenu-wrapper').forEach(w => w.classList.remove('collapsed'));
            document.querySelectorAll('.expand-btn i').forEach(i => i.className = 'fas fa-chevron-up');
        }

        function collapseAll() {
            document.querySelectorAll('.submenu-wrapper').forEach(w => w.classList.add('collapsed'));
            document.querySelectorAll('.expand-btn i').forEach(i => i.className = 'fas fa-chevron-down');
        }

        function resetToDefault() {
            Swal.fire({
                title: 'Reset to Default?',
                text: 'Are you sure you want to reset all menus to their default order?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Yes, reset',
                cancelButtonText: 'Cancel',
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                customClass: {
                    confirmButton: 'btn btn-primary me-2',
                    cancelButton: 'btn btn-secondary'
                },
                buttonsStyling: false
            }).then((result) => {
                if (result.isConfirmed) {
                    location.reload();
                }
            });
        }
        document.getElementById('searchInput')?.addEventListener('input', function (e) {
            const term = e.target.value.toLowerCase();

            document.querySelectorAll('.menu-row').forEach(row => {
                const menuName = row.dataset.menuName || '';
                let matches = menuName.includes(term);

                row.querySelectorAll('.submenu-item').forEach(item => {
                    const subName = item.dataset.submenuName || '';
                    if (subName.includes(term)) matches = true;
                });

                row.style.display = matches ? 'block' : 'none';
            });
        });

        document.querySelectorAll('.filter-tag').forEach(tag => {
            tag.addEventListener('click', function () {
                document.querySelectorAll('.filter-tag').forEach(t => t.classList.remove('active'));
                this.classList.add('active');

                const filter = this.dataset.filter;

                document.querySelectorAll('.menu-row').forEach(row => {
                    const hasSubmenus = row.dataset.hasSubmenus === 'true';

                    switch (filter) {
                        case 'all':
                            row.style.display = 'block';
                            break;
                        case 'menus':
                            row.style.display = 'block';
                            break;
                        case 'with-submenus':
                            row.style.display = hasSubmenus ? 'block' : 'none';
                            break;
                        case 'without-submenus':
                            row.style.display = !hasSubmenus ? 'block' : 'none';
                            break;
                    }
                });
            });
        });

        function updateAllOrderNumbers() {
            document.querySelectorAll('.menu-row').forEach((row, index) => {
                const orderSpan = row.querySelector('.current-order');
                if (orderSpan) orderSpan.textContent = index + 1;
            });

            document.querySelectorAll('.submenu-wrapper').forEach(wrapper => {
                wrapper.querySelectorAll('.submenu-item').forEach((item, index) => {
                    const orderSpan = item.querySelector('.current-suborder');
                    if (orderSpan) orderSpan.textContent = index + 1;
                });
            });
        }

        function getCurrentOrder() {
            const order = { menus: [], submenus: [] };

            document.querySelectorAll('.menu-row').forEach((row, index) => {
                const menuId = row.dataset.id;
                order.menus.push({ id: parseInt(menuId), sort_order: index + 1 });

                row.querySelectorAll('.submenu-item').forEach((item, subIndex) => {
                    const subId = item.dataset.id;
                    order.submenus.push({
                        id: parseInt(subId),
                        sort_order: subIndex + 1,
                        menu_id: parseInt(item.dataset.parentId)
                    });
                });
            });

            return order;
        }

        function showStatus(type, message, duration = 3000) {
            if (!statusBox || !statusText) return;

            statusBox.className = 'status-simple ' + type;
            statusBox.classList.remove('d-none');
            statusText.textContent = message;

            if (duration > 0) {
                setTimeout(() => statusBox.classList.add('d-none'), duration);
            }
        }

        if (saveBtn) {
            saveBtn.addEventListener('click', () => {
                const order = getCurrentOrder();

                if (order.menus.length === 0 && order.submenus.length === 0) {
                    showStatus('warning', 'No items to save');
                    return;
                }

                loadingOverlay.style.display = 'flex';
                saveBtn.disabled = true;

                fetch('update_order.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(order)
                })
                    .then(response => response.json())
                    .then(data => {
                        loadingOverlay.style.display = 'none';

                        if (data.success) {
                            showStatus('success', `Saved! Updated ${data.updated_menus || 0} menus and ${data.updated_submenus || 0} submenus`, 3000);
                            updateAllOrderNumbers();
                        } else {
                            showStatus('error', 'Error: ' + (data.message || 'Save failed'), 5000);
                        }

                        saveBtn.disabled = false;
                    })
                    .catch(error => {
                        loadingOverlay.style.display = 'none';
                        showStatus('error', 'Network error', 5000);
                        saveBtn.disabled = false;
                    });
            });
        }

        updateAllOrderNumbers();

        document.addEventListener('keydown', (e) => {
            if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                e.preventDefault();
                if (saveBtn && !saveBtn.disabled) saveBtn.click();
            }
        });

    </script>
</body>

</html>
