<?php
require_once __DIR__ . '/config/session.php';

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, (string) $p['path'], (string) $p['domain'], (bool) $p['secure'], (bool) $p['httponly']);
}
session_destroy();

header('Location: login.php', true, 303);
exit;
