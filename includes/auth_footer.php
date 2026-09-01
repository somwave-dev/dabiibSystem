<?php
/**
 * Auth pages shared footer + close.
 * Expects: $site (array with name/footer).
 */
$authSiteName = (string) ($site['name'] ?? 'AYAAN BADAN MEDICAL CENTER');
$authSiteFooter = (string) ($site['footer'] ?? 'Powered by SomWave Solutions');
?>
			<hr class="my-3">
			<p class="mb-0 text-center small text-muted">&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($authSiteName, ENT_QUOTES, 'UTF-8'); ?>. <?php echo htmlspecialchars($authSiteFooter, ENT_QUOTES, 'UTF-8'); ?></p>
		</div>
	</div>
	<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
	<script src="assets/js/script.js"></script>
