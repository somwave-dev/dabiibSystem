<?php
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/auth_login.php';

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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim((string) ($_POST['login'] ?? $_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if ($login === '' || $password === '') {
        $error = 'Please enter your username or email and your password.';
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
                } elseif (($row['status'] ?? '') !== 'active') {
                    $error = 'This account is not active.';
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
                    $redir = $_SESSION['redirect_url'] ?? 'index.php';
                    unset($_SESSION['redirect_url']);
                    if (!is_string($redir) || $redir === '' || str_starts_with($redir, '//')) {
                        $redir = 'index.php';
                    }
                    header('Location: ' . $redir, true, 303);
                    exit;
                } else {
                    $error = 'Invalid email, username, or password.';
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
?><!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<title>Sign in | Clinic</title>
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<link rel="shortcut icon" href="assets/img/favicon.png">
	<link rel="apple-touch-icon" href="assets/img/apple-icon.png">
	<link rel="stylesheet" href="assets/css/bootstrap.min.css">
	<link rel="stylesheet" href="assets/plugins/tabler-icons/tabler-icons.min.css">
	<link rel="stylesheet" href="assets/plugins/simplebar/simplebar.min.css">
	<link rel="stylesheet" href="assets/plugins/fontawesome/css/fontawesome.min.css">
	<link rel="stylesheet" href="assets/plugins/fontawesome/css/all.min.css">
	<link rel="stylesheet" href="assets/css/style.css" id="app-style">
</head>
<body>
	<div class="main-wrapper auth-bg position-relative overflow-hidden">
		<div class="container-fuild position-relative z-1">
			<div class="w-100 overflow-hidden position-relative flex-wrap d-block vh-100 bg-white">
				<div class="row">
					<div class="col-lg-6 p-0">
						<div class="login-backgrounds login-covers bg-primary d-lg-flex align-items-center justify-content-center d-none flex-wrap p-4 position-relative h-100 z-0">
							<div class="authentication-card w-100">
								<div class="authen-overlay-item w-100">
									<div class="authen-head text-center">
										<h1 class="text-white fs-32 fw-bold mb-2">Seamless healthcare access <br> with smart, modern clinic</h1>
										<p class="text-light fw-normal">Experience efficient, secure, and user-friendly healthcare management designed for modern clinics and growing practices.</p>
									</div>
									<div class="mt-4 mx-auto authen-overlay-img">
										<img src="assets/img/auth/cover-imgs-1.png" alt="">
									</div>
								</div>
							</div>
							<img src="assets/img/auth/cover-imgs-2.png" alt="" class="img-fluid cover-img">
						</div>
					</div>
					<div class="col-lg-6 col-md-12 col-sm-12">
						<div class="row justify-content-center align-items-center overflow-auto flex-wrap vh-100">
							<div class="col-md-8 mx-auto">
								<form method="post" action="login.php" class="d-flex justify-content-center align-items-center" autocomplete="on">
									<div class="d-flex flex-column justify-content-lg-center p-4 p-lg-0 pb-0 flex-fill">
										<div class="mx-auto mb-4 text-center">
											<a href="index.php"><img src="assets/img/logo.svg" class="img-fluid" alt="Logo"></a>
										</div>
										<?php if ($error !== ''): ?>
										<div class="alert alert-danger small mb-3" role="alert"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
										<?php endif; ?>
										<div class="card border-1 p-lg-3 shadow-md rounded-3 m-0">
											<div class="card-body">
												<div class="text-center mb-3">
													<h5 class="mb-1 fs-20 fw-bold">Sign in</h5>
													<p class="mb-0 text-muted">Enter your account details to continue</p>
												</div>
												<div class="mb-3">
													<label class="form-label" for="login">Username or email</label>
													<div class="input-group">
														<span class="input-group-text border-end-0 bg-white" aria-hidden="true">
															<i class="ti ti-user fs-14 text-dark"></i>
														</span>
														<input type="text" name="login" id="login" class="form-control border-start-0 ps-0" placeholder="Username or email" value="<?php echo htmlspecialchars((string) ($_POST['login'] ?? $_POST['username'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required autocomplete="username">
													</div>
												</div>
												<div class="mb-3">
													<label class="form-label" for="password">Password</label>
													<div class="position-relative">
														<div class="pass-group input-group position-relative border rounded">
															<span class="input-group-text bg-white border-0">
																<i class="ti ti-lock text-dark fs-14"></i>
															</span>
															<input type="password" name="password" id="password" class="pass-input form-control ps-0 border-0" placeholder="********" required autocomplete="current-password" minlength="1">
															<span class="input-group-text bg-white border-0">
																<i class="ti ti-eye-off text-dark fs-14 toggle-password" role="button" tabindex="0" aria-label="Show password" style="cursor:pointer"></i>
															</span>
														</div>
													</div>
												</div>
												<div class="d-flex align-items-center justify-content-between mb-3">
													<div class="form-check form-check-md mb-0">
														<input class="form-check-input" id="remember_me" name="remember_me" type="checkbox" value="1" disabled>
														<label for="remember_me" class="form-check-label mt-0 text-dark text-muted">Remember me (soon)</label>
													</div>
													<div class="text-end">
														<a href="forgot-password-cover.html" class="text-danger">Forgot password?</a>
													</div>
												</div>
												<div class="mb-2">
													<button type="submit" class="btn bg-primary text-white w-100">Log in</button>
												</div>
												<div class="login-or position-relative mb-3">
													<span class="span-or">or</span>
												</div>
												<div class="text-center text-muted small">
													<p class="mb-0">Default dev password (seed DB): <code>clinic123</code> with user <code>maamule</code></p>
												</div>
											</div>
										</div>
									</div>
								</form>
								<p class="fs-14 text-dark text-center mt-4 mb-0">Back to <a href="index.php" class="link-primary">home</a></p>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
	<script src="assets/js/jquery-3.7.1.min.js"></script>
	<script src="assets/js/bootstrap.bundle.min.js"></script>
	<script src="assets/js/script.js"></script>
	<script>
	document.querySelectorAll('.toggle-password').forEach(function (el) {
		el.addEventListener('click', function () {
			var g = this.closest('.pass-group') || (this.closest('.input-group') && this.closest('.input-group').parentElement);
			if (!g) return;
			var inp = g.querySelector('.pass-input, input[type="password"], input[name="password"]');
			if (!inp) return;
			var t = inp.getAttribute('type') === 'password' ? 'text' : 'password';
			inp.setAttribute('type', t);
			this.classList.toggle('ti-eye', t === 'text');
			this.classList.toggle('ti-eye-off', t === 'password');
		});
	});
	</script>
</body>
</html>
