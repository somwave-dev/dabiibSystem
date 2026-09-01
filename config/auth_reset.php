<?php
declare(strict_types=1);

/**
 * Shared helpers for the forgot-password / verify-otp / reset-password flow.
 */

require_once __DIR__ . '/session.php';
require_once __DIR__ . '/auth_login.php';
require_once __DIR__ . '/codes.php';
require_once __DIR__ . '/mailer.php';

/** Site branding from system_settings with safe defaults. */
function auth_site_settings(): array
{
    $out = [
        'logo'   => '',
        'name'   => 'AYAAN BADAN MEDICAL CENTER',
        'footer' => 'Powered by SomWave Solutions',
    ];
    try {
        $co = new Codes();
        $out['logo'] = $co->siteLogo();
        $name = $co->siteName();
        $footer = $co->siteFooter();
        if ($name !== '') {
            $out['name'] = $name;
        }
        if ($footer !== '') {
            $out['footer'] = $footer;
        }
    } catch (mysqli_sql_exception $e) {
        // keep defaults
    }

    return $out;
}

function auth_generate_otp(int $length = 6): string
{
    $otp = '';
    for ($i = 0; $i < $length; $i++) {
        $otp .= (string) random_int(0, 9);
    }

    return $otp;
}

/** Insert a fresh reset record; invalidates previous ones. Returns [token, otp]. */
function auth_create_reset(int $userId, string $email): array
{
    $co = new Codes();
    $db = $co->db;

    $token = bin2hex(random_bytes(32));
    $otp = auth_generate_otp(6);
    $otpHash = hash('sha256', $token . $otp);
    $expiresAt = date('Y-m-d H:i:s', time() + 600);

    $esc = $db->real_escape_string($email);
    $db->query("UPDATE password_resets SET used = 1 WHERE email = '{$esc}' AND used = 0");

    $stmt = $db->prepare('INSERT INTO password_resets (user_id, email, otp_hash, token, expires_at, used) VALUES (?, ?, ?, ?, ?, 0)');
    $stmt->bind_param('issss', $userId, $email, $otpHash, $token, $expiresAt);
    $stmt->execute();
    $stmt->close();
    $db->close();

    return [$token, $otp];
}

/** Validate an OTP for a token; returns the reset row (array) or null. */
function auth_verify_otp(string $token, string $otp): ?array
{
    $co = new Codes();
    $db = $co->db;

    $stmt = $db->prepare('SELECT id, user_id, email, otp_hash, expires_at, used FROM password_resets WHERE token = ? LIMIT 1');
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $db->close();

    if (!$row) {
        return null;
    }
    if ((int) $row['used'] === 1) {
        return null;
    }
    if (strtotime((string) $row['expires_at']) < time()) {
        return null;
    }
    if (!hash_equals((string) $row['otp_hash'], hash('sha256', $token . $otp))) {
        return null;
    }

    return $row;
}

/** Mask an email for display, e.g. jo***@example.com */
function auth_mask_email(string $email): string
{
    $pos = strpos($email, '@');
    if ($pos === false) {
        return $email;
    }
    $user = substr($email, 0, $pos);
    $domain = substr($email, $pos);
    if (strlen($user) <= 2) {
        return str_repeat('*', strlen($user)) . $domain;
    }

    return substr($user, 0, 2) . str_repeat('*', max(3, strlen($user) - 2)) . $domain;
}

/** Look up a user by username or email. Returns the users row or null. */
function auth_find_user(string $identifier): ?array
{
    $co = new Codes();
    $db = $co->db;
    $stmt = $db->prepare(
        'SELECT User_ID, Username, email, `status`, deleted FROM users '
        . 'WHERE (LOWER(TRIM(Username)) = LOWER(?) '
        . 'OR (email IS NOT NULL AND TRIM(email) != \'\' AND LOWER(TRIM(email)) = LOWER(?))) '
        . 'LIMIT 1'
    );
    $stmt->bind_param('ss', $identifier, $identifier);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $db->close();

    return $row ?: null;
}

/** Send the OTP reset email. Returns the result array from clinic_send_mail(). */
function auth_send_otp_email(string $toEmail, string $toName, string $otp, string $siteName): array
{
    $subject = 'Your OTP code to reset your password';
    $html = '<div style="font-family:Arial,sans-serif;max-width:480px;margin:0 auto;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden">'
        . '<div style="background:#0d6efd;padding:18px 24px"><span style="color:#fff;font-size:18px;font-weight:bold">' . htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8') . '</span></div>'
        . '<div style="padding:24px">'
        . '<h2 style="margin:0 0 8px;font-size:20px">Password Reset Code</h2>'
        . '<p style="margin:0 0 16px;color:#4b5563;font-size:14px">Use the code below to reset your password. It expires in <b>10 minutes</b>.</p>'
        . '<div style="display:inline-block;background:#eef2fb;border:1px solid #d6e0f5;border-radius:8px;padding:14px 28px;font-size:28px;letter-spacing:8px;font-weight:bold;color:#0d6efd">' . htmlspecialchars($otp, ENT_QUOTES, 'UTF-8') . '</div>'
        . '<p style="margin:16px 0 0;color:#9ca3af;font-size:12px">If you did not request this, you can safely ignore this email.</p>'
        . '</div></div>';
    $alt = "Your OTP code is {$otp}. It expires in 10 minutes.";

    return clinic_send_mail($toEmail, $toName, $subject, $html, $alt);
}
