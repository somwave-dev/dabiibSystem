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
