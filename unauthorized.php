<?php
require_once __DIR__ . '/config/session.php';

if (!isLoggedIn()) {
    $_SESSION['error'] = 'Please log in to access this page.';
    $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'] ?? 'index.php';
    header('Location: login.php', true, 302);
    exit;
}

$message = (string) ($_SESSION['error'] ?? 'You do not have the required permission.');
unset($_SESSION['error']);
?><!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Access denied | Clinic</title>
	<link rel="stylesheet" href="assets/css/bootstrap.min.css">
	<link rel="stylesheet" href="assets/plugins/tabler-icons/tabler-icons.min.css">
	<link rel="stylesheet" href="assets/css/style.css" id="app-style">
</head>
<body class="auth-bg d-flex min-vh-100 align-items-center">
	<div class="container">
		<div class="row justify-content-center">
			<div class="col-md-6 col-lg-5">
				<div class="card border-0 shadow-sm rounded-3 p-4 text-center">
					<div class="text-danger fs-1 mb-3"><i class="ti ti-shield-x"></i></div>
					<h1 class="h4 fw-bold mb-2">Access denied</h1>
					<p class="text-muted mb-4"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></p>
					<div class="d-flex flex-wrap justify-content-center gap-2">
						<a href="index.php" class="btn btn-primary">Home</a>
						<a href="logout.php" class="btn btn-outline-secondary">Log out</a>
					</div>
				</div>
			</div>
		</div>
	</div>
	<script src="assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>
