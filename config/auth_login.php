<?php
declare(strict_types=1);

function verify_clinic_password(string $password, string $storedHash): bool
{
    if ($storedHash === '') {
        return false;
    }

    if (password_get_info($storedHash)['algo'] !== 0 && password_verify($password, $storedHash)) {
        return true;
    }

    // Seed data uses this placeholder hash; keep the documented dev password working.
    if ($storedHash === 'hashed_pass_123') {
        return hash_equals('clinic123', $password);
    }

    return hash_equals($storedHash, $password);
}

/**
 * Lightweight audit writer that works on any entry point (login, logout,
 * activation, reset...). Prefers the global $conn when present, otherwise
 * opens its own connection. Never throws.
 */
function clinic_audit_record(string $action, string $details = '', ?string $entity = null, ?int $entityId = null, ?int $userId = null): void
{
    global $conn;

    try {
        $db = (isset($conn) && $conn instanceof mysqli) ? $conn : null;
        $own = false;
        if ($db === null) {
            if (!class_exists('Codes', false)) {
                require_once __DIR__ . '/codes.php';
            }
            $db = (new Codes())->db;
            $own = true;
        }

        if ($userId === null) {
            $userId = (int) ($_SESSION['user_no'] ?? $_SESSION['User_ID'] ?? $_SESSION['user_id'] ?? 0);
        }
        $userId = $userId > 0 ? $userId : null;
        $username = trim((string) ($_SESSION['username'] ?? ''));
        $entityId = ($entityId !== null && $entityId > 0) ? $entityId : null;
        $details = mb_substr(trim($details), 0, 2000);
        $ip = substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);

        $stmt = $db->prepare('INSERT INTO audit_logs (user_id, username, action, entity, entity_id, details, ip_address) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $stmt->bind_param('isssiss', $userId, $username, $action, $entity, $entityId, $details, $ip);
        $stmt->execute();
        $stmt->close();
        if ($own) {
            $db->close();
        }
    } catch (Throwable $e) {
        // never break the request
    }
}

/**
 * Lightweight notification writer usable from any entry point. Returns the new id (0 on failure).
 */
function clinic_notify_record(string $title, string $message, string $type = 'info', ?string $link = null, ?int $userId = null): int
{
    global $conn;

    try {
        $db = (isset($conn) && $conn instanceof mysqli) ? $conn : null;
        $own = false;
        if ($db === null) {
            if (!class_exists('Codes', false)) {
                require_once __DIR__ . '/codes.php';
            }
            $db = (new Codes())->db;
            $own = true;
        }

        $userId = ($userId !== null && $userId > 0) ? $userId : null;
        $type = in_array($type, ['info', 'success', 'warning', 'danger'], true) ? $type : 'info';
        $title = mb_substr(trim($title), 0, 150);
        $message = mb_substr(trim($message), 0, 2000);
        $link = (trim((string) $link) === '') ? null : mb_substr(trim((string) $link), 0, 200);

        $stmt = $db->prepare('INSERT INTO notifications (user_id, type, title, message, link) VALUES (?, ?, ?, ?, ?)');
        $stmt->bind_param('issss', $userId, $type, $title, $message, $link);
        $stmt->execute();
        $id = (int) $stmt->insert_id;
        $stmt->close();
        if ($own) {
            $db->close();
        }

        return $id;
    } catch (Throwable $e) {
        return 0;
    }
}


/** Plain text is hashed; existing bcrypt/argon hashes are left unchanged (advanced migration use). */
function clinic_normalize_password_for_storage(string $input): string
{
    $t = trim($input);
    if ($t === '') {
        return '';
    }

    $info = password_get_info($t);
    if ($info['algo'] !== 0) {
        return $t;
    }

    return password_hash($t, PASSWORD_DEFAULT);
}
