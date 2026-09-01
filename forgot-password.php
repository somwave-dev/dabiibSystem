<?php
declare(strict_types=1);

require_once __DIR__ . '/config/auth_reset.php';

$site = auth_site_settings();

if (isLoggedIn()) {
    header('Location: index.php', true, 302);
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = trim((string) ($_POST['identifier'] ?? ''));
    if ($identifier === '') {
        $error = 'Please enter your username or email.';
    } else {
        $user = auth_find_user($identifier);
        if (!$user) {
            $error = 'No account found with that username or email. No code was sent. Please check and try again.';
        } elseif ((int) $user['deleted'] === 1) {
            $error = 'This account has been disabled. No code was sent.';
        } elseif (($user['status'] ?? '') !== 'active') {
            $error = 'This account is not active. No code was sent.';
        } else {
            $email = trim((string) ($user['email'] ?? ''));
            if ($email === '') {
                $error = 'This account has no email address on file. Please contact the administrator.';
            } else {
                [$token, $otp] = auth_create_reset((int) $user['User_ID'], $email);
                $result = auth_send_otp_email($email, (string) ($user['Username'] ?? ''), $otp, $site['name']);
                if ($result['ok']) {
                    clinic_audit_record('Password reset requested', 'Reset code sent for user ' . (string) ($user['Username'] ?? ''), 'user', (int) $user['User_ID']);
                    header('Location: verify-otp.php?token=' . urlencode($token), true, 303);
                    exit;
                }
                $error = 'Could not send the reset code. ' . htmlspecialchars($result['error'], ENT_QUOTES, 'UTF-8');
            }
        }
    }
}

$identifierValue = trim((string) ($_POST['identifier'] ?? ''));
$identifierHasError = $error !== '' && $identifierValue === '';

$pageTitle = 'Forgot Password | ' . $site['name'];
require_once __DIR__ . '/includes/auth_head.php';
?>
			<div class="text-center mb-4">
				<h4 class="mb-1 fw-bold">Forgot Password</h4>
				<p class="mb-0 text-muted">No worries, we&rsquo;ll send you a reset code</p>
			</div>
			<?php if ($error !== ''): ?>
			<div class="alert alert-danger small mb-3" role="alert"><?php echo $error; ?></div>
			<?php endif; ?>
			<form method="post" action="forgot-password.php" id="forgotForm" novalidate autocomplete="off">
				<div class="field-group">
					<label class="form-label" for="identifier">Username or email</label>
					<div class="input-group">
						<span class="input-group-text bg-white" aria-hidden="true">
							<i class="ti ti-mail fs-14 text-dark"></i>
						</span>
						<input type="text" name="identifier" id="identifier" class="form-control <?php echo $identifierHasError ? 'is-invalid' : ''; ?>" placeholder="Enter your username or email" value="<?php echo htmlspecialchars($identifierValue, ENT_QUOTES, 'UTF-8'); ?>" required>
					</div>
					<div class="validation-error">Please enter your username or email.</div>
				</div>
				<button type="submit" class="btn btn-primary text-white w-100 py-2 mb-0">Send Reset Code</button>
			</form>
			<div class="text-center mt-3">
				<p class="mb-0 fs-14 text-dark">Return to <a href="login.php" class="link-primary">login</a></p>
			</div>
<?php require_once __DIR__ . '/includes/auth_footer.php'; ?>
	<script>
	(function () {
		'use strict';
		var form = document.getElementById('forgotForm');
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
	})();
	</script>
</body>
</html>
