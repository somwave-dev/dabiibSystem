<?php
session_start();
$_SESSION['csrf_token'] = 'test';
$_SESSION['logged_in'] = true;
$_SESSION['user_no'] = 1;
$_SESSION['username'] = 'adan';
$_SESSION['role_id'] = 1;
$_SESSION['role_name'] = 'Admin';
$_SESSION['user_image'] = 'default-user.png';
$_SERVER['SCRIPT_NAME'] = '/index.php';
ob_start();
include __DIR__ . '/index.php';
$html = ob_get_clean();
$out = 'render size=' . strlen($html) . "\n";
// Dark-mode CSS overrides present?
foreach ([
    '[data-bs-theme="dark"] .dash-stat-cell' => 'stat-cell dark bg',
    '[data-bs-theme="dark"] .dash-alert-row' => 'alert-row dark bg',
    '[data-bs-theme="dark"] .dash-list-item' => 'list-item dark border',
    '[data-bs-theme="dark"] .clinic-card' => 'clinic-card dark border',
    '[data-bs-theme="dark"] .progress' => 'progress dark',
] as $needle => $label) {
    $out .= (str_contains($html, $needle) ? 'OK  ' : 'MISS') . " css: $label\n";
}
// No hardcoded white/dark in rendered components?
$out .= 'bg-white remaining: ' . (str_contains($html, 'bg-white') ? 'YES' : 'no') . "\n";
$out .= 'text-dark remaining: ' . (str_contains($html, 'text-dark') ? 'YES' : 'no') . "\n";
file_put_contents(__DIR__ . '/__out.txt', $out);
