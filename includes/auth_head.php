<?php
/**
 * Auth pages shared head + card open.
 * Expects: $site (array with logo/name/footer) and optional $pageTitle.
 */
if (!isset($site)) {
    $site = ['logo' => '', 'name' => 'AYAAN BADAN MEDICAL CENTER', 'footer' => 'Powered by SomWave Solutions'];
}
$authLogo = (string) ($site['logo'] ?? '');
$authSiteName = (string) ($site['name'] ?? 'AYAAN BADAN MEDICAL CENTER');
$authPageTitle = (string) ($pageTitle ?? 'Dabiib System');
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<title><?php echo htmlspecialchars($authPageTitle, ENT_QUOTES, 'UTF-8'); ?></title>
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<link rel="shortcut icon" href="assets/img/favicon.png">
	<link rel="apple-touch-icon" href="assets/img/apple-icon.png">
	<link rel="stylesheet" href="assets/css/bootstrap.min.css">
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@3.35.0/dist/tabler-icons.min.css">
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/simplebar@6.2.7/dist/simplebar.min.css">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
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
		.otp-input {
			letter-spacing: 0.6rem;
			font-size: 1.6rem;
			font-weight: 700;
			text-align: center;
		}
	</style>
</head>
<body>
	<div class="card login-card bg-white rounded-4">
		<div class="card-body p-4 p-md-5">
			<div class="text-center mb-4">
				<?php if ($authLogo !== ''): ?>
					<img src="<?php echo htmlspecialchars($authLogo, ENT_QUOTES, 'UTF-8'); ?>" class="login-avatar" alt="System logo">
				<?php else: ?>
					<span class="login-avatar-placeholder" aria-label="Default user icon">
						<i class="ti ti-user fs-1 text-primary"></i>
					</span>
				<?php endif; ?>
			</div>
