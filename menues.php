<?php
/**
 * Menu and submenu admin.
 *
 * .cursorrules: session + `Codes` for DB; no mysqli here. `viewTable` / `all.php` + stored
 * procedures are not used on this screen because of tabs + drag-sort; data tables stay custom.
 */
require_once __DIR__ . '/config/session.php';
requireLogin();

require_once __DIR__ . '/config/codes.php';

$co = new Codes();

$flash     = null;
$flashType = 'success';
if (isset($_GET['saved']) && (string) $_GET['saved'] === '1') {
    $flash     = 'Changes saved.';
    $flashType = 'success';
}

$activeTab = (isset($_GET['tab']) && (string) $_GET['tab'] === 'sub') ? 'sub' : 'menu';

$parentMenus = $co->listParentMenusAdmin();

$filterAll = isset($_GET['all']) && (string) $_GET['all'] === '1';
$filterMenu = (int) ($_GET['menu_id'] ?? 0);
if ($filterAll) {
    $filterMenu = 0;
} elseif ($filterMenu < 1 && !empty($parentMenus)) {
    $filterMenu = (int) $parentMenus[0]['menu_id'];
}

$tabMenu = 'menues.php?saved=1&tab=menu';
$tabSub  = 'menues.php?saved=1&tab=sub';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw  = file_get_contents('php://input');
    $json = $raw && str_starts_with(trim($raw), '{') ? json_decode($raw, true) : null;
    $action = is_array($json) && isset($json['action'])
        ? (string) $json['action']
        : (string) ($_POST['action'] ?? '');

    if ($action === 'reorder_menu') {
        header('Content-Type: application/json; charset=utf-8');
        $order = (is_array($json) && isset($json['order'])) ? $json['order'] : ($_POST['order'] ?? null);
        if (!is_array($order)) {
            echo json_encode(['ok' => false, 'error' => 'Invalid order']);
            exit;
        }
        $co->adminReorderMenuIds($order);
        echo json_encode(['ok' => true]);
        exit;
    }

    if ($action === 'reorder_sub') {
        header('Content-Type: application/json; charset=utf-8');
        $order = (is_array($json) && isset($json['order'])) ? $json['order'] : null;
        $mid   = (is_array($json) && isset($json['menu_id'])) ? (int) $json['menu_id'] : (int) ($_POST['menu_id'] ?? 0);
        if (!is_array($order) || $mid < 1) {
            echo json_encode(['ok' => false, 'error' => 'Invalid data']);
            exit;
        }
        $co->adminReorderSubmenuIds($mid, $order);
        echo json_encode(['ok' => true]);
        exit;
    }

    if ($action === 'create_menu') {
        $name   = trim($_POST['menu_name'] ?? '');
        $icon   = trim($_POST['icon'] ?? '');
        $group  = trim($_POST['menu_group'] ?? '');
        $status = ($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';
        $err    = $co->adminCreateMenu($name, $icon, $group, $status);
        if ($err !== null) {
            $flash     = $err;
            $flashType = 'danger';
        } else {
            header('Location: ' . $tabMenu, true, 303);
            exit;
        }
    }

    if ($action === 'update_menu') {
        $id     = (int) ($_POST['menu_id'] ?? 0);
        $name   = trim($_POST['menu_name'] ?? '');
        $icon   = trim($_POST['icon'] ?? '');
        $group  = trim($_POST['menu_group'] ?? '');
        $status = ($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';
        $err    = $co->adminUpdateMenu($id, $name, $icon, $group, $status);
        if ($err !== null) {
            $flash     = $err;
            $flashType = 'danger';
        } else {
            header('Location: ' . $tabMenu, true, 303);
            exit;
        }
    }

    if ($action === 'delete_menu') {
        $id  = (int) ($_POST['menu_id'] ?? 0);
        $err = $co->adminDeleteMenu($id);
        if ($err !== null) {
            $flash     = $err;
            $flashType = 'danger';
        } else {
            header('Location: ' . $tabMenu, true, 303);
            exit;
        }
    }

    if ($action === 'create_sub') {
        $menuId = (int) ($_POST['menu_id'] ?? 0);
        $name   = trim($_POST['submenu_name'] ?? '');
        $url    = trim($_POST['menu_url'] ?? '');
        $status = ($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';
        $err    = $co->adminCreateSubmenu($menuId, $name, $url, $status);
        if ($err !== null) {
            $flash     = $err;
            $flashType = 'danger';
        } else {
            header('Location: ' . $tabSub . '&menu_id=' . $menuId, true, 303);
            exit;
        }
    }

    if ($action === 'update_sub') {
        $sid    = (int) ($_POST['submenu_id'] ?? 0);
        $menuId = (int) ($_POST['menu_id'] ?? 0);
        $name   = trim($_POST['submenu_name'] ?? '');
        $url    = trim($_POST['menu_url'] ?? '');
        $status = ($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';
        $err    = $co->adminUpdateSubmenu($sid, $menuId, $name, $url, $status);
        if ($err !== null) {
            $flash     = $err;
            $flashType = 'danger';
        } else {
            header('Location: ' . $tabSub . '&menu_id=' . $menuId, true, 303);
            exit;
        }
    }

    if ($action === 'delete_sub') {
        $sid = (int) ($_POST['submenu_id'] ?? 0);
        $err = $co->adminDeleteSubmenu($sid);
        if ($err !== null) {
            $flash     = $err;
            $flashType = 'danger';
        } else {
            if (isset($_POST['return_all']) && (string) $_POST['return_all'] === '1') {
                header('Location: ' . $tabSub . '&all=1', true, 303);
            } else {
                header('Location: ' . $tabSub . '&menu_id=' . (int) ($_POST['return_menu_id'] ?? 0), true, 303);
            }
            exit;
        }
    }
}

$menues    = $co->listMenuesAdmin();
$submenues = $co->listSubmenuesAdmin($filterMenu, $filterAll);

$subFormQ = $filterAll
    ? 'tab=sub&all=1'
    : 'tab=sub&menu_id=' . (int) $filterMenu;
$subFormAction = 'menues.php?' . $subFormQ;

$currentParentName = '';
foreach ($parentMenus as $pm) {
    if ((int) $pm['menu_id'] === $filterMenu) {
        $currentParentName = (string) $pm['menu_name'];
        break;
    }
}
$countMain    = count($menues);
$countSubList = count($submenues);
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<?php require_once 'includes/head.php'; ?>
	<title>Menu &amp; submenu management — Clinic</title>
	<style>
		.menus-erp-hero { background: linear-gradient(135deg, rgba(13, 110, 253, 0.04) 0%, rgba(255, 255, 255, 0) 55%); border-radius: 0.75rem; padding: 1.25rem 1.5rem; margin: -0.25rem -0.25rem 0.5rem; border: 1px solid rgba(0, 0, 0, 0.05); }
		.menus-erp-hero h2 { letter-spacing: -0.02em; }
		.menus-erp-breadcrumb { --bs-breadcrumb-divider: '›'; }
		.menus-erp-metric { min-width: 7.5rem; padding: 0.65rem 1rem; border-radius: 0.65rem; background: #fff; border: 1px solid rgba(0, 0, 0, 0.08); box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04); }
		.menus-erp-metric .metric-val { font-size: 1.35rem; font-weight: 700; line-height: 1.2; letter-spacing: -0.02em; }
		.menus-erp-metric .metric-lbl { font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.06em; color: #6c757d; }
		.menus-erp-metric.metric-accent { background: linear-gradient(145deg, #f8f9fa 0%, #fff 100%); }
		.nav-pills-erp { gap: 0.5rem; padding: 0.35rem; background: #f1f3f5; border-radius: 0.75rem; display: inline-flex; }
		.nav-pills-erp .nav-link { border-radius: 0.6rem; padding: 0.55rem 1.15rem; color: #495057; font-weight: 500; border: 1px solid transparent; transition: color 0.15s, background 0.15s, box-shadow 0.15s; }
		.nav-pills-erp .nav-link:hover { color: #0d6efd; background: rgba(255, 255, 255, 0.7); }
		.nav-pills-erp .nav-link.active { color: #fff; background: linear-gradient(135deg, #0d6efd, #0a58ca); box-shadow: 0 4px 12px rgba(13, 110, 253, 0.35); }
		.menus-erp-flash { border: none; border-left: 4px solid; border-radius: 0.65rem; box-shadow: 0 4px 20px rgba(0, 0, 0, 0.06); }
		.menus-erp-flash.alert-success { border-left-color: #198754; }
		.menus-erp-flash.alert-danger { border-left-color: #dc3545; }
		.menus-erp-flash .flash-icon { width: 2.5rem; height: 2.5rem; border-radius: 0.5rem; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; flex-shrink: 0; }
		.menus-erp-flash.alert-success .flash-icon { background: rgba(25, 135, 84, 0.12); color: #198754; }
		.menus-erp-flash.alert-danger .flash-icon { background: rgba(220, 53, 69, 0.1); color: #dc3545; }
		.menus-erp-card { border: 1px solid rgba(0, 0, 0, 0.07); border-radius: 0.75rem; overflow: hidden; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04); }
		.menus-erp-card .card-hd { padding: 0.85rem 1.25rem; background: linear-gradient(180deg, #fafbfc 0%, #f5f6f8 100%); border-bottom: 1px solid rgba(0, 0, 0, 0.07); }
		.menus-erp-table thead th { font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.05em; font-weight: 600; color: #6c757d; background: #f8f9fa; border-bottom: 1px solid #e9ecef; padding-top: 0.75rem; padding-bottom: 0.75rem; }
		.menus-erp-table tbody tr { border-color: rgba(0, 0, 0, 0.05); }
		.menus-erp-table tbody tr:hover { background: rgba(13, 110, 253, 0.03); }
		.menus-erp-table .td-mono { font-family: ui-monospace, Consolas, monospace; font-size: 0.8rem; }
		.menus-erp-table .btn-icon { width: 2.1rem; height: 2.1rem; padding: 0; display: inline-flex; align-items: center; justify-content: center; border-radius: 0.5rem; }
		.menus-erp-table .erp-badge { font-weight: 500; font-size: 0.72rem; padding: 0.35em 0.6em; border-radius: 0.35rem; }
		.menus-erp-toolbar { background: #fff; border: 1px solid rgba(0, 0, 0, 0.08); border-radius: 0.75rem; padding: 0.9rem 1.1rem; box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04); }
		.menus-erp-hint { font-size: 0.8rem; color: #6c757d; }
		.menus-erp-hint kbd, .menus-erp-hint .fake-kbd { background: #e9ecef; padding: 0.1rem 0.35rem; border-radius: 0.25rem; font-size: 0.75rem; }
		.menus-erp-empty { padding: 3rem 1.5rem; text-align: center; color: #868e96; }
		.menus-erp-empty .empty-ico { width: 3.5rem; height: 3.5rem; margin: 0 auto 1rem; border-radius: 0.75rem; background: #f1f3f5; color: #adb5bd; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; }
		#sortable-menues tr.sortable-ghost, #sortable-sub tr.sortable-ghost { opacity: 0.45; background: #e7f1ff; }
		#sortable-menues .drag-handle, #sortable-sub .drag-handle { cursor: grab; touch-action: none; user-select: none; -webkit-user-select: none; }
		#sortable-menues .drag-handle:active, #sortable-sub .drag-handle:active { cursor: grabbing; }
		.menus-erp-sublabel { max-width: 20rem; }
		@media (max-width: 576px) { .menus-erp-hero { padding: 1rem; } .menus-erp-metric { min-width: 0; flex: 1; } }
	</style>
</head>
<body>
	<div class="main-wrapper">
		<?php require_once 'includes/header.php'; ?>
		<?php require_once 'includes/sidebar.php'; ?>

		<div class="page-wrapper">
			<div class="content pb-0">
				<div class="menus-erp-hero">
					<nav class="mb-2" aria-label="Breadcrumb">
						<ol class="breadcrumb menus-erp-breadcrumb small mb-0">
							<li class="breadcrumb-item"><a class="text-decoration-none" href="index.php">Home</a></li>
							<li class="breadcrumb-item"><a class="text-decoration-none" href="#">System</a></li>
							<li class="breadcrumb-item active" aria-current="page">Navigation</li>
						</ol>
					</nav>
					<div class="d-flex flex-wrap align-items-end justify-content-between gap-3">
						<div>
							<h2 class="fw-bold mb-1 d-flex align-items-center gap-2">
								<span class="d-inline-flex align-items-center justify-content-center rounded-3 bg-primary text-white" style="width:2.5rem;height:2.5rem"><i class="ti ti-hierarchy-2"></i></span>
								Navigation &amp; menus
							</h2>
							<p class="text-muted mb-0" style="max-width: 38rem">Define sidebar structure for the clinic: top-level groups, Tabler icons, and page links. Same screen as a compact ERP &ldquo;org structure&rdquo; for the UI.</p>
						</div>
						<div class="d-flex flex-wrap gap-2">
							<div class="menus-erp-metric">
								<div class="metric-lbl">Main modules</div>
								<div class="metric-val text-primary"><?php echo (int) $countMain; ?></div>
							</div>
							<div class="menus-erp-metric metric-accent">
								<div class="metric-lbl">Links in this list</div>
								<div class="metric-val text-dark"><?php echo (int) $countSubList; ?></div>
							</div>
						</div>
					</div>
				</div>

				<?php if ($flash): ?>
				<div class="alert alert-<?php echo htmlspecialchars($flashType); ?> menus-erp-flash d-flex align-items-center gap-3 my-3 fade show" role="alert" id="erpFlash" data-auto-dismiss="<?php echo $flashType === 'success' ? '1' : '0'; ?>">
					<div class="flash-icon">
						<?php if ($flashType === 'success'): ?><i class="ti ti-check"></i>
						<?php else: ?><i class="ti ti-alert-triangle"></i>
						<?php endif; ?>
					</div>
					<div class="flex-grow-1 pe-1">
						<?php if ($flashType === 'success'): ?><div class="fw-semibold">Done</div><?php else: ?><div class="fw-semibold">Could not complete action</div><?php endif; ?>
						<div class="small <?php echo $flashType === 'danger' ? 'text-body' : 'text-body-secondary'; ?> opacity-90"><?php echo htmlspecialchars($flash); ?></div>
					</div>
					<button type="button" class="btn-close flex-shrink-0" data-bs-dismiss="alert" aria-label="Close"></button>
				</div>
				<?php endif; ?>

				<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
					<ul class="nav nav-pills nav-pills-erp mb-0" role="tablist">
						<li class="nav-item" role="presentation">
							<button class="nav-link d-inline-flex align-items-center gap-2<?php echo $activeTab === 'menu' ? ' active' : ''; ?>"
								id="tab-menu-btn" data-bs-toggle="tab" data-bs-target="#tab-pane-menu" type="button" role="tab"
								aria-selected="<?php echo $activeTab === 'menu' ? 'true' : 'false'; ?>"><i class="ti ti-layout-sidebar"></i> Main menus</button>
						</li>
						<li class="nav-item" role="presentation">
							<button class="nav-link d-inline-flex align-items-center gap-2<?php echo $activeTab === 'sub' ? ' active' : ''; ?>"
								id="tab-sub-btn" data-bs-toggle="tab" data-bs-target="#tab-pane-sub" type="button" role="tab"
								aria-selected="<?php echo $activeTab === 'sub' ? 'true' : 'false'; ?>"><i class="ti ti-link"></i> Submenus</button>
						</li>
					</ul>
				</div>

				<div class="tab-content">
					<div class="tab-pane fade<?php echo $activeTab === 'menu' ? ' show active' : ''; ?>" id="tab-pane-menu" role="tabpanel" tabindex="0">
						<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
							<p class="menus-erp-hint mb-0"><i class="ti ti-grip-vertical me-1 text-primary"></i> Drag <span class="fake-kbd">::</span> to reorder. Order updates the live sidebar for all users.</p>
							<button type="button" class="btn btn-primary rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#modalMenu">
								<i class="ti ti-plus me-1"></i>Add main menu
							</button>
						</div>
						<div class="card menus-erp-card bg-white">
							<div class="card-hd d-flex flex-wrap align-items-center justify-content-between gap-2">
								<div>
									<div class="fw-semibold">Main menu rows</div>
									<div class="small text-muted">ID, name, icon class, group label, status, display order</div>
								</div>
							</div>
							<?php if (count($menues) === 0): ?>
							<div class="menus-erp-empty">
								<div class="empty-ico"><i class="ti ti-menu-2"></i></div>
								<div class="fw-medium text-secondary mb-1">No main menus yet</div>
								<div class="small mb-3">Start by creating your first top-level entry (e.g. Dashboard, Patients, Reports).</div>
								<button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalMenu"><i class="ti ti-plus me-1"></i>Create main menu</button>
							</div>
							<?php else: ?>
							<div class="table-responsive">
								<table class="table table-hover align-middle mb-0 menus-erp-table">
									<thead>
										<tr>
											<th class="ps-3" style="width:2.5rem;" aria-label="Sort"></th>
											<th style="width:4.5rem;">ID</th>
											<th>Name</th>
											<th>Icon</th>
											<th>Group</th>
											<th style="width:6.5rem;">Status</th>
											<th style="width:5rem;">Order</th>
											<th class="text-end pe-3" style="width:7.5rem;">Actions</th>
										</tr>
									</thead>
									<tbody id="sortable-menues">
										<?php foreach ($menues as $m): ?>
										<tr data-id="<?php echo (int) $m['menu_id']; ?>">
											<td class="ps-3 text-primary drag-handle" title="Drag to reorder">
												<i class="ti ti-grip-vertical fs-20"></i>
											</td>
											<td class="td-mono text-muted">#<?php echo (int) $m['menu_id']; ?></td>
											<td class="fw-semibold"><?php echo htmlspecialchars($m['menu_name']); ?></td>
											<td class="td-mono text-body-secondary"><span class="text-body"><?php echo htmlspecialchars($m['icon'] ?? '—'); ?></span></td>
											<td><?php echo $m['menu_group'] !== null && (string) $m['menu_group'] !== '' ? htmlspecialchars($m['menu_group']) : '<span class="text-muted">—</span>'; ?></td>
											<td>
												<?php if (($m['status'] ?? '') === 'active'): ?>
												<span class="badge erp-badge text-bg-success">Active</span>
												<?php else: ?>
												<span class="badge erp-badge text-bg-secondary">Inactive</span>
												<?php endif; ?>
											</td>
											<td class="td-mono text-muted"><?php echo (int) $m['sort_order']; ?></td>
											<td class="text-end pe-3">
												<div class="d-inline-flex gap-1">
													<button type="button" class="btn btn-sm btn-light border btn-icon btn-edit-menu" title="Edit"
														data-id="<?php echo (int) $m['menu_id']; ?>"
														data-name="<?php echo htmlspecialchars($m['menu_name'], ENT_QUOTES, 'UTF-8'); ?>"
														data-icon="<?php echo htmlspecialchars($m['icon'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
														data-group="<?php echo htmlspecialchars($m['menu_group'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
														data-status="<?php echo htmlspecialchars($m['status'] ?? 'active', ENT_QUOTES, 'UTF-8'); ?>">
														<i class="ti ti-pencil"></i>
													</button>
													<button type="button" class="btn btn-sm btn-outline-danger btn-icon btn-delete-menu" title="Delete"
														data-id="<?php echo (int) $m['menu_id']; ?>"
														data-name="<?php echo htmlspecialchars($m['menu_name'], ENT_QUOTES, 'UTF-8'); ?>">
														<i class="ti ti-trash"></i>
													</button>
												</div>
											</td>
										</tr>
										<?php endforeach; ?>
									</tbody>
								</table>
							</div>
							<?php endif; ?>
						</div>
					</div>

					<div class="tab-pane fade<?php echo $activeTab === 'sub' ? ' show active' : ''; ?>" id="tab-pane-sub" role="tabpanel" tabindex="0">
						<div class="menus-erp-toolbar mb-3 d-flex flex-wrap align-items-center justify-content-between gap-3">
							<div class="d-flex flex-wrap align-items-center gap-2">
								<label class="mb-0 small text-muted text-uppercase fw-semibold" style="letter-spacing:.04em" for="selMenu">Parent</label>
								<select class="form-select form-select-sm menus-erp-sublabel shadow-none" id="selMenu" aria-label="Select parent menu">
									<?php foreach ($parentMenus as $pm): ?>
									<option value="<?php echo (int) $pm['menu_id']; ?>"<?php echo !$filterAll && (int) $pm['menu_id'] === $filterMenu ? ' selected' : ''; ?>>
										<?php echo htmlspecialchars($pm['menu_name']); ?>
									</option>
									<?php endforeach; ?>
								</select>
								<a href="menues.php?tab=sub&amp;all=1" class="btn btn-sm btn-light border<?php echo $filterAll ? ' border-primary text-primary' : ''; ?>">All parents</a>
							</div>
							<div class="d-flex flex-wrap align-items-center justify-content-end gap-2">
								<?php if ($filterAll): ?>
								<div class="menus-erp-hint mb-0 text-end"><i class="ti ti-info-circle me-1 text-primary"></i> <strong>All</strong> view — sorting off; pick one parent to reorder.</div>
								<?php else: ?>
								<div class="menus-erp-hint mb-0 text-end">Scope: <span class="fw-semibold text-body"><?php echo htmlspecialchars($currentParentName !== '' ? $currentParentName : '—'); ?></span></div>
								<?php endif; ?>
								<button type="button" class="btn btn-primary rounded-pill" data-bs-toggle="modal" data-bs-target="#modalSub">
									<i class="ti ti-plus me-1"></i>Add link
								</button>
							</div>
						</div>
						<div class="card menus-erp-card bg-white">
							<div class="card-hd d-flex flex-wrap align-items-center justify-content-between gap-2">
								<div>
									<div class="fw-semibold">Submenu links</div>
									<div class="small text-muted">Labels and PHP targets shown in the flyout / nested navigation</div>
								</div>
							</div>
							<?php if (count($submenues) === 0): ?>
							<div class="menus-erp-empty">
								<div class="empty-ico"><i class="ti ti-inbox"></i></div>
								<div class="fw-medium text-secondary mb-1">No submenus in this list</div>
								<div class="small">Switch parent above or add a new link. URLs should match real files (e.g. <code>patients.php</code>).</div>
							</div>
							<?php else: ?>
							<?php if ($filterAll): ?>
							<div class="px-3 py-2 small text-muted border-bottom bg-light">Read-only order column — use a single parent to edit sequence.</div>
							<?php endif; ?>
							<div class="table-responsive">
								<table class="table table-hover align-middle mb-0 menus-erp-table">
									<thead>
										<tr>
											<?php if (!$filterAll): ?>
											<th class="ps-3" style="width:2.5rem;"></th>
											<?php endif; ?>
											<?php if ($filterAll): ?>
											<th>Parent</th>
											<?php endif; ?>
											<th style="width:4.5rem;">ID</th>
											<th>Label</th>
											<th>URL</th>
											<th style="width:6.5rem;">Status</th>
											<th style="width:5rem;">Order</th>
											<th class="text-end pe-3" style="width:7.5rem;">Actions</th>
										</tr>
									</thead>
									<tbody id="sortable-sub">
										<?php foreach ($submenues as $s): ?>
										<tr data-id="<?php echo (int) $s['submenu_id']; ?>" data-menu="<?php echo (int) $s['menu_id']; ?>">
											<?php if (!$filterAll): ?>
											<td class="ps-3 text-primary drag-handle" title="Drag to reorder">
												<i class="ti ti-grip-vertical fs-20"></i>
											</td>
											<?php endif; ?>
											<?php if ($filterAll): ?>
											<td class="small text-body-secondary"><span class="text-body"><?php echo htmlspecialchars($s['menu_name'] ?? ''); ?></span></td>
											<?php endif; ?>
											<td class="td-mono text-muted">#<?php echo (int) $s['submenu_id']; ?></td>
											<td class="fw-semibold"><?php echo htmlspecialchars($s['submenu_name']); ?></td>
											<td class="td-mono text-body"><span class="text-body-secondary"><?php echo htmlspecialchars($s['menu_url']); ?></span></td>
											<td>
												<?php if (($s['status'] ?? '') === 'active'): ?>
												<span class="badge erp-badge text-bg-success">Active</span>
												<?php else: ?>
												<span class="badge erp-badge text-bg-secondary">Inactive</span>
												<?php endif; ?>
											</td>
											<td class="td-order td-mono text-muted"><?php echo (int) $s['sort_order']; ?></td>
											<td class="text-end pe-3">
												<div class="d-inline-flex gap-1">
													<button type="button" class="btn btn-sm btn-light border btn-icon btn-edit-sub" title="Edit"
														data-id="<?php echo (int) $s['submenu_id']; ?>"
														data-menu="<?php echo (int) $s['menu_id']; ?>"
														data-name="<?php echo htmlspecialchars($s['submenu_name'], ENT_QUOTES, 'UTF-8'); ?>"
														data-url="<?php echo htmlspecialchars($s['menu_url'], ENT_QUOTES, 'UTF-8'); ?>"
														data-status="<?php echo htmlspecialchars($s['status'] ?? 'active', ENT_QUOTES, 'UTF-8'); ?>">
														<i class="ti ti-pencil"></i>
													</button>
													<button type="button" class="btn btn-sm btn-outline-danger btn-icon btn-delete-sub" title="Delete"
														data-id="<?php echo (int) $s['submenu_id']; ?>"
														data-name="<?php echo htmlspecialchars($s['submenu_name'], ENT_QUOTES, 'UTF-8'); ?>">
														<i class="ti ti-trash"></i>
													</button>
												</div>
											</td>
										</tr>
										<?php endforeach; ?>
									</tbody>
								</table>
							</div>
							<?php endif; ?>
						</div>
					</div>
				</div>
			</div>
			<?php require_once 'includes/footer.php'; ?>
		</div>
	</div>

	<div class="modal fade" id="modalMenu" tabindex="-1">
		<div class="modal-dialog">
			<form method="post" class="modal-content" action="menues.php?tab=menu">
				<input type="hidden" name="action" value="create_menu">
				<div class="modal-header">
					<h5 class="modal-title">New menu</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
				</div>
				<div class="modal-body">
					<div class="mb-3">
						<label class="form-label">Menu name <span class="text-danger">*</span></label>
						<input type="text" name="menu_name" class="form-control" required maxlength="100" placeholder="e.g. Dashboard">
					</div>
					<div class="mb-3">
						<label class="form-label">Icon (Tabler)</label>
						<input type="text" name="icon" class="form-control" maxlength="50" placeholder="ti-layout-dashboard">
					</div>
					<div class="mb-3">
						<label class="form-label">Group</label>
						<input type="text" name="menu_group" class="form-control" maxlength="50" placeholder="Clinic, Finance…">
					</div>
					<div class="mb-0">
						<label class="form-label">Status</label>
						<select name="status" class="form-select">
							<option value="active">Active</option>
							<option value="inactive">Inactive</option>
						</select>
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
					<button type="submit" class="btn btn-primary">Save</button>
				</div>
			</form>
		</div>
	</div>

	<div class="modal fade" id="modalEditMenu" tabindex="-1">
		<div class="modal-dialog">
			<form method="post" class="modal-content" id="formEditMenu" action="menues.php?tab=menu">
				<input type="hidden" name="action" value="update_menu">
				<input type="hidden" name="menu_id" id="edit_menu_id" value="">
				<div class="modal-header">
					<h5 class="modal-title">Edit menu</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
				</div>
				<div class="modal-body">
					<div class="mb-3">
						<label class="form-label">Menu name</label>
						<input type="text" name="menu_name" id="edit_menu_name" class="form-control" required maxlength="100">
					</div>
					<div class="mb-3">
						<label class="form-label">Icon</label>
						<input type="text" name="icon" id="edit_icon" class="form-control" maxlength="50">
					</div>
					<div class="mb-3">
						<label class="form-label">Group</label>
						<input type="text" name="menu_group" id="edit_group" class="form-control" maxlength="50">
					</div>
					<div class="mb-0">
						<label class="form-label">Status</label>
						<select name="status" id="edit_status_menu" class="form-select">
							<option value="active">Active</option>
							<option value="inactive">Inactive</option>
						</select>
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
					<button type="submit" class="btn btn-primary">Update</button>
				</div>
			</form>
		</div>
	</div>

	<div class="modal fade" id="modalConfirmDeleteMenu" tabindex="-1" aria-hidden="true">
		<div class="modal-dialog modal-dialog-centered">
			<div class="modal-content border-0 shadow-lg rounded-3 overflow-hidden">
				<div class="modal-header border-0 bg-danger bg-opacity-10 text-danger">
					<div class="d-flex align-items-center gap-2">
						<span class="rounded-2 bg-danger text-white p-2 d-inline-flex"><i class="ti ti-trash"></i></span>
						<h5 class="modal-title mb-0">Delete main menu</h5>
					</div>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<div class="modal-body">
					<p class="text-muted mb-0">This will remove <strong id="confirmDeleteMenuName" class="text-body"></strong> from the navigation structure. Submenus may be affected if your rules allow it.</p>
				</div>
				<div class="modal-footer border-0 bg-light">
					<button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
					<button type="button" class="btn btn-danger" id="btnConfirmDeleteMenu"><i class="ti ti-trash me-1"></i>Delete</button>
				</div>
			</div>
		</div>
	</div>

	<div class="modal fade" id="modalConfirmDeleteSub" tabindex="-1" aria-hidden="true">
		<div class="modal-dialog modal-dialog-centered">
			<div class="modal-content border-0 shadow-lg rounded-3 overflow-hidden">
				<div class="modal-header border-0 bg-danger bg-opacity-10 text-danger">
					<div class="d-flex align-items-center gap-2">
						<span class="rounded-2 bg-danger text-white p-2 d-inline-flex"><i class="ti ti-trash"></i></span>
						<h5 class="modal-title mb-0">Delete submenu</h5>
					</div>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<div class="modal-body">
					<p class="text-muted mb-0">Remove the link <strong id="confirmDeleteSubName" class="text-body"></strong> from the sidebar.</p>
				</div>
				<div class="modal-footer border-0 bg-light">
					<button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
					<button type="button" class="btn btn-danger" id="btnConfirmDeleteSub"><i class="ti ti-trash me-1"></i>Delete</button>
				</div>
			</div>
		</div>
	</div>

	<form id="formDeleteMenu" method="post" class="d-none" action="menues.php?tab=menu">
		<input type="hidden" name="action" value="delete_menu">
		<input type="hidden" name="menu_id" id="delete_menu_id" value="">
	</form>

	<div class="modal fade" id="modalSub" tabindex="-1">
		<div class="modal-dialog">
			<form method="post" class="modal-content" action="<?php echo htmlspecialchars($subFormAction); ?>">
				<input type="hidden" name="action" value="create_sub">
				<div class="modal-header">
					<h5 class="modal-title">New submenu</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
				</div>
				<div class="modal-body">
					<div class="mb-3">
						<label class="form-label">Parent menu <span class="text-danger">*</span></label>
						<select name="menu_id" class="form-select" required>
							<?php foreach ($parentMenus as $pm): ?>
							<option value="<?php echo (int) $pm['menu_id']; ?>"<?php echo (int) $pm['menu_id'] === $filterMenu && !$filterAll ? ' selected' : ''; ?>>
								<?php echo htmlspecialchars($pm['menu_name']); ?>
							</option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="mb-3">
						<label class="form-label">Link label <span class="text-danger">*</span></label>
						<input type="text" name="submenu_name" class="form-control" required maxlength="100">
					</div>
					<div class="mb-3">
						<label class="form-label">URL (PHP) <span class="text-danger">*</span></label>
						<input type="text" name="menu_url" class="form-control" required maxlength="100" placeholder="patients.php">
					</div>
					<div class="mb-0">
						<label class="form-label">Status</label>
						<select name="status" class="form-select">
							<option value="active">Active</option>
							<option value="inactive">Inactive</option>
						</select>
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
					<button type="submit" class="btn btn-primary">Save</button>
				</div>
			</form>
		</div>
	</div>

	<div class="modal fade" id="modalEditSub" tabindex="-1">
		<div class="modal-dialog">
			<form method="post" class="modal-content" id="formEditSub" action="<?php echo htmlspecialchars($subFormAction); ?>">
				<input type="hidden" name="action" value="update_sub">
				<input type="hidden" name="submenu_id" id="edit_sub_id" value="">
				<div class="modal-header">
					<h5 class="modal-title">Edit submenu</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
				</div>
				<div class="modal-body">
					<div class="mb-3">
						<label class="form-label">Parent menu</label>
						<select name="menu_id" id="edit_sub_menu_id" class="form-select" required>
							<?php foreach ($parentMenus as $pm): ?>
							<option value="<?php echo (int) $pm['menu_id']; ?>"><?php echo htmlspecialchars($pm['menu_name']); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="mb-3">
						<label class="form-label">Name</label>
						<input type="text" name="submenu_name" id="edit_name_sub" class="form-control" required maxlength="100">
					</div>
					<div class="mb-3">
						<label class="form-label">URL</label>
						<input type="text" name="menu_url" id="edit_url_sub" class="form-control" required maxlength="100">
					</div>
					<div class="mb-0">
						<label class="form-label">Status</label>
						<select name="status" id="edit_status_sub" class="form-select">
							<option value="active">Active</option>
							<option value="inactive">Inactive</option>
						</select>
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
					<button type="submit" class="btn btn-primary">Update</button>
				</div>
			</form>
		</div>
	</div>

	<form id="formDeleteSub" method="post" class="d-none" action="<?php echo htmlspecialchars($subFormAction); ?>">
		<input type="hidden" name="action" value="delete_sub">
		<input type="hidden" name="submenu_id" id="delete_sub_id" value="">
		<input type="hidden" name="return_menu_id" value="<?php echo (int) $filterMenu; ?>">
		<input type="hidden" name="return_all" value="<?php echo $filterAll ? '1' : '0'; ?>">
	</form>

	<?php require_once 'includes/plugins.php'; ?>
	<script>
	(function() {
		var elFlash = document.getElementById('erpFlash');
		if (elFlash && elFlash.getAttribute('data-auto-dismiss') === '1') {
			setTimeout(function() {
				try {
					var a = bootstrap.Alert.getOrCreateInstance(elFlash);
					a.close();
				} catch (e) {}
			}, 5200);
		}
		var MEN = 'reorder_menu';
		var pageBase = 'menues.php';
		var tbodyM = document.getElementById('sortable-menues');
		if (tbodyM && typeof Sortable !== 'undefined') {
			Sortable.create(tbodyM, {
				handle: '.drag-handle',
				animation: 150,
				ghostClass: 'sortable-ghost',
				draggable: 'tr',
				onEnd: function() {
					var order = [];
					tbodyM.querySelectorAll('tr[data-id]').forEach(function(tr) {
						order.push(parseInt(tr.getAttribute('data-id'), 10));
					});
					fetch(pageBase, {
						method: 'POST',
						headers: { 'Content-Type': 'application/json' },
						body: JSON.stringify({ action: MEN, order: order })
					})
					.then(function(r) { return r.json(); })
					.then(function(data) {
						if (!data.ok) return;
						tbodyM.querySelectorAll('tr[data-id]').forEach(function(tr, i) {
							var c = tr.querySelectorAll('td');
							if (c.length > 6) c[6].textContent = String(i + 1);
						});
					})
					.catch(function() {});
				}
			});
		}
		var filterMenu = <?php echo (int) $filterMenu; ?>;
		var filterAll = <?php echo $filterAll ? 'true' : 'false'; ?>;
		var tbodyS = document.getElementById('sortable-sub');
		if (tbodyS && !filterAll && typeof Sortable !== 'undefined') {
			Sortable.create(tbodyS, {
				handle: '.drag-handle',
				animation: 150,
				ghostClass: 'sortable-ghost',
				draggable: 'tr',
				onEnd: function() {
					var order = [];
					tbodyS.querySelectorAll('tr[data-id]').forEach(function(tr) {
						order.push(parseInt(tr.getAttribute('data-id'), 10));
					});
					fetch(pageBase, {
						method: 'POST',
						headers: { 'Content-Type': 'application/json' },
						body: JSON.stringify({ action: 'reorder_sub', menu_id: filterMenu, order: order })
					})
					.then(function(r) { return r.json(); })
					.then(function(data) {
						if (!data.ok) return;
						tbodyS.querySelectorAll('tr .td-order').forEach(function(td, i) {
							td.textContent = String(i + 1);
						});
					})
					.catch(function() {});
				}
			});
		}
		var sel = document.getElementById('selMenu');
		if (sel) {
			sel.addEventListener('change', function() {
				window.location.href = 'menues.php?tab=sub&menu_id=' + encodeURIComponent(sel.value);
			});
		}
		document.querySelectorAll('.btn-edit-menu').forEach(function(btn) {
			btn.addEventListener('click', function() {
				document.getElementById('edit_menu_id').value = btn.getAttribute('data-id') || '';
				document.getElementById('edit_menu_name').value = btn.getAttribute('data-name') || '';
				document.getElementById('edit_icon').value = btn.getAttribute('data-icon') || '';
				document.getElementById('edit_group').value = btn.getAttribute('data-group') || '';
				document.getElementById('edit_status_menu').value = btn.getAttribute('data-status') === 'inactive' ? 'inactive' : 'active';
				new bootstrap.Modal(document.getElementById('modalEditMenu')).show();
			});
		});
		var modalDelMenu = document.getElementById('modalConfirmDeleteMenu');
		var btnGoMenu = document.getElementById('btnConfirmDeleteMenu');
		document.querySelectorAll('.btn-delete-menu').forEach(function(btn) {
			btn.addEventListener('click', function() {
				var n = btn.getAttribute('data-name') || '';
				var id = btn.getAttribute('data-id') || '';
				var nm = document.getElementById('confirmDeleteMenuName');
				if (nm) nm.textContent = n;
				document.getElementById('delete_menu_id').value = id;
				if (modalDelMenu) new bootstrap.Modal(modalDelMenu).show();
			});
		});
		if (btnGoMenu) {
			btnGoMenu.addEventListener('click', function() {
				document.getElementById('formDeleteMenu').submit();
			});
		}
		document.querySelectorAll('.btn-edit-sub').forEach(function(btn) {
			btn.addEventListener('click', function() {
				document.getElementById('edit_sub_id').value = btn.getAttribute('data-id') || '';
				document.getElementById('edit_name_sub').value = btn.getAttribute('data-name') || '';
				document.getElementById('edit_url_sub').value = btn.getAttribute('data-url') || '';
				document.getElementById('edit_status_sub').value = btn.getAttribute('data-status') === 'inactive' ? 'inactive' : 'active';
				var sm = document.getElementById('edit_sub_menu_id');
				if (sm) sm.value = String(btn.getAttribute('data-menu') || '');
				new bootstrap.Modal(document.getElementById('modalEditSub')).show();
			});
		});
		var modalDelSub = document.getElementById('modalConfirmDeleteSub');
		var btnGoSub = document.getElementById('btnConfirmDeleteSub');
		document.querySelectorAll('.btn-delete-sub').forEach(function(btn) {
			btn.addEventListener('click', function() {
				var n = btn.getAttribute('data-name') || '';
				var id = btn.getAttribute('data-id') || '';
				var sn = document.getElementById('confirmDeleteSubName');
				if (sn) sn.textContent = n;
				document.getElementById('delete_sub_id').value = id;
				if (modalDelSub) new bootstrap.Modal(modalDelSub).show();
			});
		});
		if (btnGoSub) {
			btnGoSub.addEventListener('click', function() {
				document.getElementById('formDeleteSub').submit();
			});
		}
	})();
	</script>
</body>
</html>
