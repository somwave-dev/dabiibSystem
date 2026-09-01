<?php
declare(strict_types=1);

require_once __DIR__ . '/config/auth_reset.php';

$site = auth_site_settings();

if (isLoggedIn()) {
    header('Location: index.php', true, 302);
    exit;
}

$token = trim((string) ($_GET['token'] ?? ''));
if ($token === '' || !preg_match('/^[a-f0-9]{64}$/', $token)) {
    header('Location: forgot-password.php', true, 302);
    exit;
}

// Load the reset row so we can show the masked email and guard against misuse.
$co = new Codes();
$db = $co->db;
$stmt = $db->prepare('SELECT id, user_id, email, expires_at, used FROM password_resets WHERE token = ? LIMIT 1');
$stmt->bind_param('s', $token);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();
$db->close();

if (!$row || (int) $row['used'] === 1 || strtotime((string) $row['expires_at']) < time()) {
    header('Location: forgot-password.php', true, 302);
    exit;
}
$email = (string) $row['email'];
$userId = (int) $row['user_id'];

$error = '';
$flash = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? 'verify');

    if ($action === 'resend') {
        $otp = auth_generate_otp(6);
        $otpHash = hash('sha256', $token . $otp);
        $expiresAt = date('Y-m-d H:i:s', time() + 600);

        $co2 = new Codes();
        $db2 = $co2->db;
        $u = $db2->prepare('UPDATE password_resets SET otp_hash = ?, expires_at = ?, used = 0 WHERE token = ?');
        $u->bind_param('sss', $otpHash, $expiresAt, $token);
        $u->execute();
        $u->close();
        $db2->close();

        $co3 = new Codes();
        $db3 = $co3->db;
        $s3 = $db3->prepare('SELECT Username FROM users WHERE User_ID = ? LIMIT 1');
        $s3->bind_param('i', $userId);
        $s3->execute();
        $u3 = $s3->get_result()->fetch_assoc();
        $s3->close();
        $db3->close();
        $userName = (string) ($u3['Username'] ?? '');

        $result = auth_send_otp_email($email, $userName, $otp, $site['name']);
        if ($result['ok']) {
            $flash = 'A new code has been sent to your email.';
        } else {
            $error = 'Could not resend the code. ' . htmlspecialchars($result['error'], ENT_QUOTES, 'UTF-8');
        }
    } else {
        $otpInput = trim((string) ($_POST['otp'] ?? ''));
        if ($otpInput === '') {
            $error = 'Please enter the code.';
        } else {
            $reset = auth_verify_otp($token, $otpInput);
            if ($reset) {
                $co2 = new Codes();
                $db2 = $co2->db;
                $u = $db2->prepare('UPDATE password_resets SET used = 1 WHERE token = ?');
                $u->bind_param('s', $token);
                $u->execute();
                $u->close();
                $db2->close();

                $_SESSION['reset_token'] = $token;
                $_SESSION['reset_user_id'] = (int) $reset['user_id'];
                header('Location: reset-password.php?token=' . urlencode($token), true, 303);
                exit;
            }
            $error = 'Invalid or expired code. Please try again or resend a new code.';
        }
    }
}

$otpValue = trim((string) ($_POST['otp'] ?? ''));
$otpHasError = $error !== '' && $otpValue === '';

$pageTitle = 'Verify OTP | ' . $site['name'];
require_once __DIR__ . '/includes/auth_head.php';
?>
			<div class="text-center mb-4">
				<h4 class="mb-1 fw-bold">2 Step Verification</h4>
				<p class="mb-0 text-muted">Enter the code sent to <strong class="text-dark"><?php echo htmlspecialchars(auth_mask_email($email), ENT_QUOTES, 'UTF-8'); ?></strong></p>
			</div>
			<?php if ($error !== ''): ?>
			<div class="alert alert-danger small mb-3" role="alert"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
			<?php endif; ?>
			<?php if ($flash !== ''): ?>
			<div class="alert alert-success small mb-3" role="alert"><?php echo htmlspecialchars($flash, ENT_QUOTES, 'UTF-8'); ?></div>
			<?php endif; ?>
			<form method="post" action="verify-otp.php?token=<?php echo urlencode($token); ?>" id="otpForm" novalidate autocomplete="off">
				<input type="hidden" name="action" value="verify">
				<div class="field-group">
					<label class="form-label" for="otp">Verification code</label>
					<input type="text" name="otp" id="otp" class="form-control otp-input <?php echo $otpHasError ? 'is-invalid' : ''; ?>" placeholder="000000" maxlength="6" inputmode="numeric" pattern="[0-9]{6}" required autofocus>
					<div class="validation-error">Please enter the 6-digit code.</div>
				</div>
				<button type="submit" class="btn btn-primary text-white w-100 py-2 mb-0">Verify &amp; Continue</button>
			</form>
			<div class="d-flex align-items-center justify-content-center mt-3">
				<p class="mb-0 fs-14 text-dark me-2">Didn&rsquo;t receive the code?</p>
				<form method="post" action="verify-otp.php?token=<?php echo urlencode($token); ?>" class="d-inline">
					<input type="hidden" name="action" value="resend">
					<button type="submit" id="btnResend" class="btn btn-link p-0 text-primary text-decoration-underline">Resend Code</button>
				</form>
			</div>
			<div class="text-center mt-2">
				<p class="mb-0 fs-14 text-dark">Return to <a href="login.php" class="link-primary">login</a></p>
			</div>
<?php require_once __DIR__ . '/includes/auth_footer.php'; ?>
	<script>
	(function () {
		'use strict';
		var form = document.getElementById('otpForm');
		if (!form) return;

		function checkGroup(group) {
			var input = group.querySelector('input');
			if (!input) return true;
			var ok = input.checkValidity();
			group.classList.toggle('is-invalid-group', !ok);
			return ok;
		}

		form.addEventListener('submit', function (event) {
			var otp = document.getElementById('otp');
			if (otp) { otp.value = otp.value.replace(/\D/g, ''); }
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
