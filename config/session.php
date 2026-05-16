<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function isLoggedIn(): bool
{
    return !empty($_SESSION['logged_in']) && (int) ($_SESSION['user_no'] ?? 0) > 0;
}

function requireLogin(): void
{
    if (isLoggedIn()) {
        return;
    }

    $requestUri = (string) ($_SERVER['REQUEST_URI'] ?? 'index.php');
    if ($requestUri === '' || str_starts_with($requestUri, '//')) {
        $requestUri = 'index.php';
    }

    $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/clinic/index.php'));
    $scriptDir = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
    $appBase = basename($scriptDir) === 'pages' ? rtrim(dirname($scriptDir), '/') : $scriptDir;
    $loginPath = ($appBase === '' ? '' : $appBase) . '/login.php';

    $_SESSION['error'] = 'Please log in to access this page.';
    $_SESSION['redirect_url'] = $requestUri;
    header('Location: ' . $loginPath, true, 302);
    exit;
}

function loadUserDataToSession(int $userId): void
{
    if ($userId < 1) {
        return;
    }

    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $db = new mysqli('127.0.0.1', 'root', '', 'clinic');
    $db->set_charset('utf8mb4');

    $stmt = $db->prepare(
        'SELECT u.User_ID, u.Username, u.Role_ID, u.email, u.image, r.Role_Name
         FROM users u
         LEFT JOIN roles r ON r.Role_ID = u.Role_ID
         WHERE u.User_ID = ? AND u.deleted = 0
         LIMIT 1'
    );
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $db->close();

    if (!$row) {
        return;
    }

    $_SESSION['user_no'] = (int) $row['User_ID'];
    $_SESSION['username'] = (string) $row['Username'];
    $_SESSION['role_id'] = (int) ($row['Role_ID'] ?? 0);
    $_SESSION['role_name'] = (string) ($row['Role_Name'] ?? '');
    $_SESSION['user_email'] = (string) ($row['email'] ?? '');
    $_SESSION['user_image'] = (string) ($row['image'] ?? 'default-user.png');
}
