<?php
declare(strict_types=1);

require_once __DIR__ . '/config/auth_reset.php';

$site = auth_site_settings();

$token = trim((string) ($_GET['token'] ?? ''));
if ($token === '' || !preg_match('/^[a-f0-9]{64}$/', $token)) {
    header('Location: login.php?activation=invalid', true, 302);
    exit;
}

// Look up the account carrying this reset token.
$co = new Codes();
$db = $co->db;
$stmt = $db->prepare(
    'SELECT User_ID, Username, email, `status`, reset_token, reset_expires_at '
    . 'FROM users WHERE reset_token = ? AND deleted = 0 LIMIT 1'
);
$stmt->bind_param('s', $token);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    $db->close();
    header('Location: login.php?activation=invalid', true, 302);
    exit;
}

$expiresAt = (string) ($user['reset_expires_at'] ?? '');
if ($expiresAt === '' || strtotime($expiresAt) < time()) {
    $db->close();
    header('Location: login.php?activation=expired', true, 302);
    exit;
}

$userId = (int) $user['User_ID'];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = (string) ($_POST['password'] ?? '');
    $confirm = (string) ($_POST['confirm'] ?? '');
    if (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $up = $db->prepare('UPDATE users SET Password_Hash = ?, reset_token = NULL, reset_expires_at = NULL WHERE User_ID = ? AND reset_token = ?');
        $up->bind_param('sis', $hash, $userId, $token);
        $up->execute();
        $up->close();
        $db->close();

        clinic_audit_record('Password reset completed', 'Password was reset for user ' . (string) ($user['Username'] ?? ''), 'user', $userId);
        clinic_notify_record('Password reset', 'Your password has been reset successfully.', 'success', 'pages/profile.php', $userId);

        // Sign out the current browser session so the user can sign in fresh.
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }

        header('Location: login.php?reset=1', true, 303);
        exit;
    }
}

$passwordValue = (string) ($_POST['password'] ?? '');
$confirmValue = (string) ($_POST['confirm'] ?? '');
$passwordHasError = $error !== '' && $passwordValue === '';
$confirmHasError = $error !== '' && $confirmValue === '';

$pageTitle = 'Reset Password | ' . $site['name'];
require_once __DIR__ . '/includes/auth_head.php';
?>
			<div class="text-center mb-4">
				<h4 class="mb-1 fw-bold">Reset Password</h4>
				<p class="mb-0 text-muted"><?php echo htmlspecialchars((string) ($user['Username'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
			</div>
			<?php if ($error !== ''): ?>
			<div class="alert alert-danger small mb-3" role="alert"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
			<?php endif; ?>
			<form method="post" action="reset-password-link.php?token=<?php echo urlencode($token); ?>" id="resetLinkForm" novalidate autocomplete="off">
				<div class="field-group">
					<label class="form-label" for="password">New password</label>
					<div class="input-group">
						<span class="input-group-text bg-white" aria-hidden="true">
							<i class="ti ti-lock fs-14 text-dark"></i>
						</span>
						<input type="password" name="password" id="password" class="form-control <?php echo $passwordHasError ? 'is-invalid' : ''; ?>" placeholder="At least 6 characters" minlength="6" required>
						<span class="input-group-text bg-white">
							<i class="ti ti-eye-off text-dark fs-14 toggle-pass-btn" role="button" tabindex="0" aria-label="Show password" style="cursor:pointer"></i>
						</span>
					</div>
					<div class="validation-error">Password must be at least 6 characters.</div>
				</div>
				<div class="field-group">
					<label class="form-label" for="confirm">Confirm password</label>
					<div class="input-group">
						<span class="input-group-text bg-white" aria-hidden="true">
							<i class="ti ti-lock fs-14 text-dark"></i>
						</span>
						<input type="password" name="confirm" id="confirm" class="form-control <?php echo $confirmHasError ? 'is-invalid' : ''; ?>" placeholder="Repeat password" minlength="6" required>
						<span class="input-group-text bg-white">
							<i class="ti ti-eye-off text-dark fs-14 toggle-pass-btn" role="button" tabindex="0" aria-label="Show password" style="cursor:pointer"></i>
						</span>
					</div>
					<div class="validation-error">Please confirm your password.</div>
				</div>
				<button type="submit" class="btn btn-primary text-white w-100 py-2 mb-0">Update Password</button>
			</form>
			<div class="text-center mt-3">
				<p class="mb-0 fs-14 text-dark">Return to <a href="login.php" class="link-primary">login</a></p>
			</div>
<?php require_once __DIR__ . '/includes/auth_footer.php'; ?>
	<script>
	(function () {
		'use strict';
		var form = document.getElementById('resetLinkForm');
		if (!form) return;

		function checkGroup(group) {
			var input = group.querySelector('input');
			if (!input) return true;
			var ok = input.checkValidity();
			group.classList.toggle('is-invalid-group', !ok);
			return ok;
		}

		form.addEventListener('submit', function (event) {
			var groups = form.querySelectorAll('.field-group');
			var allValid = true;
			groups.forEach(function (group) {
				if (!checkGroup(group)) { allValid = false; }
			});
			if (!allValid) { event.preventDefault(); event.stopPropagation(); }
		}, false);

		form.querySelectorAll('input').forEach(function (input) {
			input.addEventListener('input', function () {
				var group = input.closest('.field-group');
				if (group && input.value !== '' && input.checkValidity()) {
					group.classList.remove('is-invalid-group');
				}
			});
		});

		document.querySelectorAll('.toggle-pass-btn').forEach(function (el) {
			el.addEventListener('click', function () {
				var g = el.closest('.input-group');
				if (!g) return;
				var inp = g.querySelector('input[type="password"], input[name="password"], input[name="confirm"]');
				if (!inp) return;
				var isText = inp.getAttribute('type') === 'text';
				inp.setAttribute('type', isText ? 'password' : 'text');
				el.classList.remove('ti-eye', 'ti-eye-off');
				el.classList.add(isText ? 'ti-eye-off' : 'ti-eye');
			});
		});
	})();
	</script>
</body>
</html>

