<?php
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/auth_login.php';
require_once __DIR__ . '/config/codes.php';

try {
	$co         = new Codes();
	$siteLogo   = $co->siteLogo();
	$siteName   = $co->siteName();
	$siteFooter = $co->siteFooter();
} catch (mysqli_sql_exception $e) {
	$siteLogo   = '';
	$siteName   = '';
	$siteFooter = '';
}

if (isLoggedIn()) {
	$target = $_SESSION['redirect_url'] ?? 'index.php';
	if (!is_string($target) || $target === '') {
		$target = 'index.php';
	}
	unset($_SESSION['redirect_url']);
	header('Location: ' . $target, true, 302);
	exit;
}

$error = '';
$resetDone = isset($_GET['reset']) && (string) $_GET['reset'] === '1';
$activatedDone = isset($_GET['activated']) && (string) $_GET['activated'] === '1';
$activationMsg = (string) ($_GET['activation'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$login    = trim((string) ($_POST['login'] ?? $_POST['username'] ?? ''));
	$password = (string) ($_POST['password'] ?? '');

	if ($login === '' || $password === '') {
		$error = 'Please enter your username and your password.';
	} else {
		require_once __DIR__ . '/config/codes.php';
		$co  = new Codes();
		$co->setConnect();
		$db  = $co->db;
		$sql = 'SELECT User_ID, Username, Password_Hash, `status`, deleted FROM users '
			. 'WHERE LOWER(TRIM(Username)) = LOWER(?) '
			. 'OR (email IS NOT NULL AND TRIM(email) != \'\' AND LOWER(TRIM(email)) = LOWER(?)) '
			. 'LIMIT 1';
		$stmt = $db->prepare($sql);
		if ($stmt) {
			$stmt->bind_param('ss', $login, $login);
			$stmt->execute();
			$res  = $stmt->get_result();
			$row  = $res ? $res->fetch_assoc() : null;
			$stmt->close();
			if ($row) {
				if ((int) $row['deleted'] === 1) {
					$error = 'This account is disabled.';
					clinic_audit_record('Login failed', 'Disabled account attempted login: ' . $login);
				} elseif (($row['status'] ?? '') !== 'active') {
					$error = 'This account is not active.';
					clinic_audit_record('Login failed', 'Inactive account attempted login: ' . $login, 'user', (int) $row['User_ID']);
				} elseif (verify_clinic_password($password, (string) $row['Password_Hash'])) {
					$_SESSION['logged_in'] = true;
					$_SESSION['user_no']   = (int) $row['User_ID'];
					$uid = (int) $row['User_ID'];
					$up  = $db->prepare('UPDATE users SET last_login = NOW() WHERE User_ID = ?');
					if ($up) {
						$up->bind_param('i', $uid);
						$up->execute();
						$up->close();
					}
					$db->close();
					if (function_exists('loadUserDataToSession')) {
						loadUserDataToSession($uid);
					}
					clinic_audit_record('Login', 'User logged in successfully', 'user', $uid, $uid);
					$redir = $_SESSION['redirect_url'] ?? 'index.php';
					unset($_SESSION['redirect_url']);
					if (!is_string($redir) || $redir === '' || str_starts_with($redir, '//')) {
						$redir = 'index.php';
					}
					header('Location: ' . $redir, true, 303);
					exit;
				} else {
					$error = 'Invalid email, username, or password.';
					clinic_audit_record('Login failed', 'Invalid credentials attempted for: ' . $login);
				}
			} else {
				$error = 'Invalid email, username, or password.';
			}
		} else {
			$error = 'Could not prepare login query.';
		}
		if (isset($db) && $db instanceof mysqli) {
			$db->close();
		}
	}
}
$loginValue = trim((string) ($_POST['login'] ?? $_POST['username'] ?? ''));
$passwordValue = (string) ($_POST['password'] ?? '');
$loginHasError = $error !== '' && $loginValue === '';
$passwordHasError = $error !== '' && $passwordValue === '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<title>Sign in | <?php echo htmlspecialchars($siteName !== '' ? $siteName : 'AYAAN BADAN MEDICAL CENTER', ENT_QUOTES, 'UTF-8'); ?></title>
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<link rel="shortcut icon" href="assets/img/favicon.png">
	<link rel="apple-touch-icon" href="assets/img/apple-icon.png">
	<link rel="stylesheet" href="assets/css/bootstrap.min.css">
	<link rel="stylesheet" href="assets/plugins/tabler-icons/tabler-icons.min.css">
	<link rel="stylesheet" href="assets/plugins/simplebar/simplebar.min.css">
	<link rel="stylesheet" href="assets/plugins/fontawesome/css/fontawesome.min.css">
	<link rel="stylesheet" href="assets/plugins/fontawesome/css/all.min.css">
	<link rel="stylesheet" href="assets/css/style.css" id="app-style">
	<style>
		body {
			min-height: 100vh;
			display: flex;
			align-items: center;
			justify-content: center;
			padding: 1rem;
			background: linear-gradient(135deg, #0d6efd 0%, #4f83e8 55%, #e8eefc 100%);
		}
		.login-card {
			width: 100%;
			max-width: 430px;
			border: 0;
			border-radius: 1rem;
			box-shadow: 0 1.25rem 2.5rem rgba(0, 0, 0, 0.18);
		}
		.login-avatar {
			width: 84px;
			height: 84px;
			border-radius: 50%;
			object-fit: cover;
			border: 3px solid #fff;
			box-shadow: 0 0.5rem 1rem rgba(13, 110, 253, 0.25);
		}
		.login-avatar-placeholder {
			width: 84px;
			height: 84px;
			border-radius: 50%;
			display: inline-flex;
			align-items: center;
			justify-content: center;
			background: #eef2fb;
			border: 3px solid #fff;
			box-shadow: 0 0.5rem 1rem rgba(13, 110, 253, 0.25);
		}
		.login-card .input-group {
			border: 1px solid #ced4da;
			border-radius: 0.5rem;
			overflow: hidden;
			transition: border-color 0.15s ease, box-shadow 0.15s ease;
		}
		.login-card .input-group .form-control,
		.login-card .input-group .input-group-text {
			border: 0;
		}
		.login-card .input-group:focus-within {
			border-color: #0d6efd;
			box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.15);
		}
		.login-card .input-group:has(.is-invalid) {
			border-color: #dc3545;
			box-shadow: 0 0 0 0.25rem rgba(220, 53, 69, 0.15);
		}
		.field-group {
			margin-bottom: 1rem;
		}
		.validation-error {
			display: none;
			margin-top: 4px;
			margin-bottom: 0;
			font-size: 13px;
			line-height: 1.3;
			color: #dc3545;
		}
		.field-group:has(.is-invalid) .validation-error,
		.field-group.is-invalid-group .validation-error {
			display: block;
		}
		.login-card .field-group.is-invalid-group .input-group {
			border-color: #dc3545;
			box-shadow: 0 0 0 0.25rem rgba(220, 53, 69, 0.15);
		}
		.login-card .toggle-pass-btn {
			cursor: pointer;
			transition: color 0.15s ease, transform 0.1s ease;
		}
		.login-card .toggle-pass-btn:hover {
			color: #0d6efd;
		}
		.login-card .toggle-pass-btn:active {
			color: #0a58ca;
			transform: scale(0.9);
		}
	</style>
</head>
<body>
	<div class="card login-card bg-white rounded-4">
		<div class="card-body p-4 p-md-5">
			<div class="text-center mb-4">
				<?php if ($siteLogo !== ''): ?>
					<img src="<?php echo htmlspecialchars($siteLogo, ENT_QUOTES, 'UTF-8'); ?>" class="login-avatar" alt="System logo">
				<?php else: ?>
					<span class="login-avatar-placeholder" aria-label="Default user icon">
						<i class="ti ti-user fs-1 text-primary"></i>
					</span>
				<?php endif; ?>
			</div>
			<div class="text-center mb-4">
				<h4 class="mb-1 fw-bold">Welcome Back</h4>
				<p class="mb-0 text-muted">Sign in to access the Clinical Center</p>
			</div>
			<?php if ($resetDone): ?>
			<div class="alert alert-success alert-dismissible fade show border border-success small mb-3" role="alert">
				<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
				<strong>Success - </strong> Your password has been reset. Please log in with your new password.
			</div>
			<?php endif; ?>
			<?php if ($activatedDone): ?>
			<div class="alert alert-success alert-dismissible fade show border border-success small mb-3" role="alert">
				<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
				<strong>Success - </strong> Your account is active. Please log in with your new password.
			</div>
			<?php elseif ($activationMsg === 'invalid'): ?>
			<div class="alert alert-danger alert-dismissible fade show border border-danger small mb-3" role="alert">
				<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
				<strong>Error - </strong> This activation link is invalid. Contact your administrator for a new one.
			</div>
			<?php elseif ($activationMsg === 'expired'): ?>
			<div class="alert alert-danger alert-dismissible fade show border border-danger small mb-3" role="alert">
				<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
				<strong>Error - </strong> This activation link has expired. Contact your administrator to resend it.
			</div>
			<?php elseif ($activationMsg === 'already'): ?>
			<div class="alert alert-warning alert-dismissible fade show border border-warning small mb-3" role="alert">
				<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
				<strong>Note - </strong> This account is already active. Please log in.
			</div>
			<?php endif; ?>
			<?php if ($error !== ''): ?>
			<div class="alert alert-danger alert-dismissible fade show border border-danger small mb-3" role="alert">
				<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
				<strong>Error - </strong> <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
			</div>
			<?php endif; ?>
			<form method="post" action="login.php" id="loginForm" novalidate autocomplete="on">
				<div class="field-group">
					<label class="form-label" for="login">Username</label>
					<div class="input-group">
						<span class="input-group-text bg-white" aria-hidden="true">
							<i class="ti ti-user fs-14 text-dark"></i>
						</span>
						<input type="text" name="login" id="login" class="form-control <?php echo $loginHasError ? 'is-invalid' : ''; ?>" placeholder="Enter username" value="<?php echo htmlspecialchars($loginValue, ENT_QUOTES, 'UTF-8'); ?>" required autocomplete="username">
					</div>
					<div class="validation-error">Please enter your username.</div>
				</div>
				<div class="field-group">
					<label class="form-label" for="password">Password</label>
					<div class="input-group">
						<span class="input-group-text bg-white" aria-hidden="true">
							<i class="ti ti-lock fs-14 text-dark"></i>
						</span>
						<input type="password" name="password" id="password" class="form-control <?php echo $passwordHasError ? 'is-invalid' : ''; ?>" placeholder="Enter password" required autocomplete="current-password" minlength="1">
						<span class="input-group-text bg-white">
							<i class="ti ti-eye-off text-dark fs-14 toggle-pass-btn" role="button" tabindex="0" aria-label="Show password" style="cursor:pointer"></i>
						</span>
					</div>
					<div class="validation-error">Please enter your password.</div>
				</div>
				<div class="d-flex align-items-center justify-content-end mb-3">
					<a href="forgot-password.php" class="text-primary">Forgot password?</a>
				</div>
				<button type="submit" class="btn btn-primary text-white w-100 py-2 mb-0">Log in</button>
			</form>

			<hr class="my-3">
			<p class="mb-0 text-center small text-muted">&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($siteName !== '' ? $siteName : 'AYAAN BADAN MEDICAL CENTER', ENT_QUOTES, 'UTF-8'); ?>. <?php echo htmlspecialchars($siteFooter !== '' ? $siteFooter : 'Powered by SomWave Solutions', ENT_QUOTES, 'UTF-8'); ?></p>
		</div>
	</div>
	<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
	<script src="assets/js/script.js"></script>
	<script>
	(function () {
		'use strict';
		var loginForm = document.getElementById('loginForm');
		if (!loginForm) return;

		function checkGroup(group) {
			var input = group.querySelector('input');
			if (!input) return true;
			var ok = input.checkValidity();
			group.classList.toggle('is-invalid-group', !ok);
			return ok;
		}

		loginForm.addEventListener('submit', function (event) {
			var groups = loginForm.querySelectorAll('.field-group');
			var allValid = true;
			groups.forEach(function (group) {
				if (!checkGroup(group)) { allValid = false; }
			});
			if (!allValid) { event.preventDefault(); event.stopPropagation(); }
		}, false);

		loginForm.querySelectorAll('input').forEach(function (input) {
			input.addEventListener('input', function () {
				var group = input.closest('.field-group');
				if (group && input.value !== '' && input.checkValidity()) {
					group.classList.remove('is-invalid-group');
				}
			});
		});
	})();
	document.querySelectorAll('.toggle-pass-btn').forEach(function (el) {
		el.addEventListener('click', function () {
			var g = el.closest('.input-group');
			if (!g) return;
			var inp = g.querySelector('input[type="password"], input[name="password"]');
			if (!inp) return;
			var isText = inp.getAttribute('type') === 'text';
			inp.setAttribute('type', isText ? 'password' : 'text');
			el.classList.remove('ti-eye', 'ti-eye-off');
			el.classList.add(isText ? 'ti-eye-off' : 'ti-eye');
		});
	});
	</script>
</body>
</html>

