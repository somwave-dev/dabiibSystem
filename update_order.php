<?php
declare(strict_types=1);

/**
 * JSON endpoint used by menu-sort.php to persist the drag-and-drop order.
 * Receives: { menus: [{id, sort_order}], submenus: [{id, sort_order, menu_id}] }
 */
require_once __DIR__ . '/config/session.php';
requireLogin();

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/config/codes.php';

$raw = file_get_contents('php://input');
$json = json_decode(is_string($raw) ? $raw : '', true);

if (!is_array($json)) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON payload.']);
    exit;
}

$co = new Codes();

try {
    $updatedMenus = 0;
    $updatedSubmenus = 0;

    // --- main menu order ---
    if (!empty($json['menus']) && is_array($json['menus'])) {
        $menuIds = [];
        foreach ($json['menus'] as $m) {
            $id = (int) ($m['id'] ?? 0);
            if ($id > 0) {
                $menuIds[] = $id;
            }
        }
        if ($menuIds !== []) {
            $co->adminReorderMenuIds($menuIds);
            $updatedMenus = count($menuIds);
        }
    }

    // --- submenu order (grouped by parent menu) ---
    if (!empty($json['submenus']) && is_array($json['submenus'])) {
        $byMenu = [];
        foreach ($json['submenus'] as $s) {
            $sid = (int) ($s['id'] ?? 0);
            $mid = (int) ($s['menu_id'] ?? 0);
            if ($sid < 1) {
                continue;
            }
            if ($mid < 1) {
                $row = $co->db->query('SELECT menu_id FROM submenues WHERE submenu_id = ' . $sid)->fetch_assoc();
                $mid = (int) ($row['menu_id'] ?? 0);
            }
            if ($mid < 1) {
                continue;
            }
            $byMenu[$mid][] = $sid;
        }
        foreach ($byMenu as $mid => $ids) {
            $co->adminReorderSubmenuIds($mid, $ids);
            $updatedSubmenus += count($ids);
        }
    }

    echo json_encode([
        'success'         => true,
        'updated_menus'   => $updatedMenus,
        'updated_submenus' => $updatedSubmenus,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
