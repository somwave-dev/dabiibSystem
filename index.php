<?php
require_once __DIR__ . '/config/session.php';
requireLogin();
?>
<!DOCTYPE html>
<html lang="en">

<head>

	<?php require_once 'includes/head.php'; ?>

</head>

<body>

	<!-- Begin Wrapper -->
	<div class="main-wrapper">

		<!-- Topbar Start -->
		<?php require_once'includes/header.php'; ?>
		<!-- Sidenav Menu Start -->
		<?php require_once'includes/sidebar.php'; ?>
		<!-- Sidenav Menu End -->

        <!-- ========================
			Start Page Content
		========================= -->

		<div class="page-wrapper">

			<!-- Start Content -->
			<?php require_once'includes/home.php'; ?>
			<!-- End Content -->

			<!-- Footer Start -->
			<?php require_once'includes/footer.php'; ?>
			<!-- Footer End -->

		</div>

        <!-- ========================
			End Page Content
		========================= -->

	</div>
	<!-- End Wrapper -->

	<?php require_once'includes/plugins.php'; ?>

</body>

</html>
