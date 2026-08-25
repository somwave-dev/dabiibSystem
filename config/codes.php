<?php
declare(strict_types=1);

class Codes
{
    public mysqli $db;

    public function __construct()
    {
        $this->setConnect();
    }

    public function setConnect(): void
    {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        $this->db = new mysqli('localhost', 'root', '', 'dabiibsystem');
        $this->db->set_charset('utf8mb4');
    }

    public function listParentMenusAdmin(): array
    {
        return $this->rows('SELECT menu_id, menu_name, icon, menu_group, status, sort_order FROM menues WHERE deleted = 0 ORDER BY sort_order ASC, menu_id ASC');
    }

    public function listMenuesAdmin(): array
    {
        return $this->listParentMenusAdmin();
    }

    public function listSubmenuesAdmin(int $menuId = 0, bool $all = false): array
    {
        if ($all || $menuId < 1) {
            return $this->rows(
                'SELECT s.submenu_id, s.menu_id, m.menu_name, s.submenu_name, s.menu_url, s.status, s.sort_order
                 FROM submenues s
                 LEFT JOIN menues m ON m.menu_id = s.menu_id
                 WHERE s.deleted = 0
                 ORDER BY m.sort_order ASC, s.sort_order ASC, s.submenu_id ASC'
            );
        }

        $stmt = $this->db->prepare(
            'SELECT s.submenu_id, s.menu_id, m.menu_name, s.submenu_name, s.menu_url, s.status, s.sort_order
             FROM submenues s
             LEFT JOIN menues m ON m.menu_id = s.menu_id
             WHERE s.deleted = 0 AND s.menu_id = ?
             ORDER BY s.sort_order ASC, s.submenu_id ASC'
        );
        $stmt->bind_param('i', $menuId);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        return $rows;
    }

    public function adminCreateMenu(string $name, string $icon, string $group, string $status): ?string
    {
        if ($name === '') {
            return 'Menu name is required.';
        }

        $sort = $this->nextSort('menues', 'menu_id', 'deleted = 0');
        $stmt = $this->db->prepare('INSERT INTO menues (menu_name, icon, menu_group, status, sort_order, deleted) VALUES (?, NULLIF(?, \'\'), NULLIF(?, \'\'), ?, ?, 0)');
        $stmt->bind_param('ssssi', $name, $icon, $group, $status, $sort);
        $stmt->execute();
        $stmt->close();

        return null;
    }

    public function adminUpdateMenu(int $id, string $name, string $icon, string $group, string $status): ?string
    {
        if ($id < 1 || $name === '') {
            return 'Valid menu and name are required.';
        }

        $stmt = $this->db->prepare('UPDATE menues SET menu_name = ?, icon = NULLIF(?, \'\'), menu_group = NULLIF(?, \'\'), status = ? WHERE menu_id = ?');
        $stmt->bind_param('ssssi', $name, $icon, $group, $status, $id);
        $stmt->execute();
        $stmt->close();

        return null;
    }

    public function adminDeleteMenu(int $id): ?string
    {
        if ($id < 1) {
            return 'Valid menu is required.';
        }

        $stmt = $this->db->prepare('UPDATE menues SET deleted = 1, status = \'inactive\' WHERE menu_id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();

        return null;
    }

    public function adminCreateSubmenu(int $menuId, string $name, string $url, string $status): ?string
    {
        if ($menuId < 1 || $name === '' || $url === '') {
            return 'Parent menu, submenu name, and URL are required.';
        }

        $sort = $this->nextSort('submenues', 'submenu_id', 'deleted = 0 AND menu_id = ' . $menuId);
        $stmt = $this->db->prepare('INSERT INTO submenues (menu_id, submenu_name, menu_url, status, sort_order, deleted) VALUES (?, ?, ?, ?, ?, 0)');
        $stmt->bind_param('isssi', $menuId, $name, $url, $status, $sort);
        $stmt->execute();
        $stmt->close();

        return null;
    }

    public function adminUpdateSubmenu(int $id, int $menuId, string $name, string $url, string $status): ?string
    {
        if ($id < 1 || $menuId < 1 || $name === '' || $url === '') {
            return 'Valid submenu, parent menu, name, and URL are required.';
        }

        $stmt = $this->db->prepare('UPDATE submenues SET menu_id = ?, submenu_name = ?, menu_url = ?, status = ? WHERE submenu_id = ?');
        $stmt->bind_param('isssi', $menuId, $name, $url, $status, $id);
        $stmt->execute();
        $stmt->close();

        return null;
    }

    public function adminDeleteSubmenu(int $id): ?string
    {
        if ($id < 1) {
            return 'Valid submenu is required.';
        }

        $stmt = $this->db->prepare('UPDATE submenues SET deleted = 1, status = \'inactive\' WHERE submenu_id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();

        return null;
    }

    public function adminReorderMenuIds(array $order): void
    {
        $stmt = $this->db->prepare('UPDATE menues SET sort_order = ? WHERE menu_id = ?');
        foreach (array_values($order) as $index => $id) {
            $sort = $index + 1;
            $id = (int) $id;
            if ($id < 1) {
                continue;
            }
            $stmt->bind_param('ii', $sort, $id);
            $stmt->execute();
        }
        $stmt->close();
    }

    public function adminReorderSubmenuIds(int $menuId, array $order): void
    {
        $stmt = $this->db->prepare('UPDATE submenues SET sort_order = ?, menu_id = ? WHERE submenu_id = ?');
        foreach (array_values($order) as $index => $id) {
            $sort = $index + 1;
            $id = (int) $id;
            if ($id < 1) {
                continue;
            }
            $stmt->bind_param('iii', $sort, $menuId, $id);
            $stmt->execute();
        }
        $stmt->close();
    }

    private function rows(string $sql): array
    {
        $result = $this->db->query($sql);

        return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    }

    private function nextSort(string $table, string $pk, string $where): int
    {
        $sql = 'SELECT COALESCE(MAX(sort_order), 0) + 1 AS next_sort FROM `' . $table . '` WHERE ' . $where . ' AND `' . $pk . '` IS NOT NULL';
        $row = $this->rows($sql)[0] ?? [];

        return (int) ($row['next_sort'] ?? 1);
    }
}
