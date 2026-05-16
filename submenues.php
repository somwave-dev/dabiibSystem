<?php
require_once __DIR__ . '/config/session.php';
requireLogin();
// Alias → unified admin (submenu tab)
$params = array_merge($_GET, ['tab' => 'sub']);
header('Location: menues.php?' . http_build_query($params), true, 301);
exit;
