<?php
// clinic_avatar() lives in includes/advanced_components.php — make it available
// on every layout (e.g. index.php) even when the pages/ helpers were not loaded.
if (!function_exists('clinic_avatar')) {
    require_once __DIR__ . '/advanced_components.php';
}
$assetBase = $GLOBALS['asset_base'] ?? '';
$appBase = $GLOBALS['app_base'] ?? '';
$currentUserName = (string) ($_SESSION['username'] ?? 'User');
$currentUserRole = (string) ($_SESSION['role_name'] ?? 'Clinic user');
$currentUserImage = (string) ($_SESSION['user_image'] ?? 'default-user.png');

// Notifications for the topbar bell (safe: never breaks the layout).
$headerUnread = 0;
$headerNotifs = [];
try {
    $headerUnread = clinic_unread_notifications();
    $connHdr = $GLOBALS['conn'] ?? null;
    if ($connHdr instanceof mysqli) {
        $uidHdr = (int) ($_SESSION['user_no'] ?? $_SESSION['User_ID'] ?? 0);
        $stmtHdr = $connHdr->prepare('SELECT notification_id, type, title, message, link, is_read, created_at FROM notifications WHERE user_id IS NULL OR user_id = ? ORDER BY created_at DESC, notification_id DESC LIMIT 5');
        $stmtHdr->bind_param('i', $uidHdr);
        $stmtHdr->execute();
        $headerNotifs = $stmtHdr->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmtHdr->close();
    }
} catch (Throwable $e) {
    $headerNotifs = [];
}

// Branding: use the clinic logo uploaded in System Settings when available.
$headerLogoUrl = '';
$headerLogoDarkUrl = '';
try {
    require_once __DIR__ . '/../config/codes.php';
    $brandingCo = new Codes();
    $brandingPrimary = (string) $brandingCo->setting('site_logo');
    $brandingLight = (string) $brandingCo->setting('logo_light');
    $headerLogoUrl = $brandingPrimary === '' ? '' : (preg_match('#^[a-z][a-z0-9+.-]*://#i', $brandingPrimary) || str_starts_with($brandingPrimary, '/') ? $brandingPrimary : $assetBase . $brandingPrimary);
    $darkLogo = $brandingLight !== '' ? $brandingLight : $brandingPrimary;
    $headerLogoDarkUrl = $darkLogo === '' ? '' : (preg_match('#^[a-z][a-z0-9+.-]*://#i', $darkLogo) || str_starts_with($darkLogo, '/') ? $darkLogo : $assetBase . $darkLogo);
} catch (Throwable $e) {
    // keep the default template logos
}
?>
   <header class="navbar-header">
            <div class="page-container topbar-menu">
                <div class="d-flex align-items-center gap-2">

                    <!-- Logo -->
                    <a href="<?php echo htmlspecialchars($appBase, ENT_QUOTES, 'UTF-8'); ?>index.php" class="logo">

                        <!-- Logo Normal -->
                        <span class="logo-light">
                            <span class="logo-lg">
                                <?php if ($headerLogoUrl !== ''): ?>
                                    <img src="<?php echo htmlspecialchars($headerLogoUrl, ENT_QUOTES, 'UTF-8'); ?>" alt="logo">
                                <?php else: ?>
                                    <img src="<?php echo htmlspecialchars($assetBase, ENT_QUOTES, 'UTF-8'); ?>assets/img/logo.svg" alt="logo">
                                <?php endif; ?>
                            </span>
                            <span class="logo-sm"><img src="<?php echo htmlspecialchars($assetBase, ENT_QUOTES, 'UTF-8'); ?>assets/img/logo-small.svg" alt="small logo"></span>
                        </span>

                        <!-- Logo Dark -->
                        <span class="logo-dark">
                            <span class="logo-lg">
                                <?php if ($headerLogoDarkUrl !== ''): ?>
                                    <img src="<?php echo htmlspecialchars($headerLogoDarkUrl, ENT_QUOTES, 'UTF-8'); ?>" alt="dark logo">
                                <?php else: ?>
                                    <img src="<?php echo htmlspecialchars($assetBase, ENT_QUOTES, 'UTF-8'); ?>assets/img/logo-white.svg" alt="dark logo">
                                <?php endif; ?>
                            </span>
                        </span>
                    </a>

                    <!-- Sidebar Mobile Button -->
                    <a id="mobile_btn" class="mobile-btn" href="#sidebar">
                        <i class="ti ti-menu-deep fs-24"></i>
                    </a>

                    <button class="sidenav-toggle-btn btn border-0 p-0 active" id="toggle_btn2"> 
                        <i class="ti ti-arrow-right"></i>
                    </button> 

                    <!-- Page Greeting -->
                    <div class="page-title-box align-self-center d-none d-md-block ms-2">
                        <h4 class="page-title mb-0 fs-15 fw-semibold lh-sm">Hi, <?php echo htmlspecialchars($currentUserName, ENT_QUOTES, 'UTF-8'); ?> 👋 Welcome Back!</h4>
                       
                    </div>
					

					
                </div>

                <div class="d-flex align-items-center">
				

					
                  

                                    

                    <!-- Light/Dark Mode Button -->
                    <div class="header-item d-none d-sm-flex me-2">
                        <button class="topbar-link btn btn-icon topbar-link" id="light-dark-mode" type="button" title="Toggle dark / light mode">
                            <i class="ti ti-moon fs-16"></i>
                        </button>
                    </div>
                    <script>
                    (function () {
                        function syncThemeIcon() {
                            var el = document.getElementById('light-dark-mode');
                            var i = el ? el.querySelector('i') : null;
                            if (!i) { return; }
                            var dark = document.documentElement.getAttribute('data-bs-theme') === 'dark'
                                || document.documentElement.getAttribute('data-theme') === 'dark';
                            i.className = dark ? 'ti ti-sun fs-16' : 'ti ti-moon fs-16';
                        }
                        syncThemeIcon();
                        var t = document.getElementById('light-dark-mode');
                        if (t) {
                            t.addEventListener('click', function () {
                                setTimeout(syncThemeIcon, 80);
                            });
                        }
                        var mo = new MutationObserver(syncThemeIcon);
                        mo.observe(document.documentElement, { attributes: true, attributeFilter: ['data-bs-theme', 'data-theme'] });
                    })();
                    </script>

                    <!-- Notification Dropdown -->
                    <div class="header-item">
						<div class="dropdown me-3">
						
							<button class="topbar-link btn btn-icon topbar-link dropdown-toggle drop-arrow-none" data-bs-toggle="dropdown" data-bs-offset="0,24" type="button" aria-haspopup="false" aria-expanded="false">
								<i class="ti ti-bell-check fs-16 animate-ring"></i>
								<span class="notification-badge"><?php echo $headerUnread > 0 ? (int) $headerUnread : ''; ?></span>
							</button>
							
							<div class="dropdown-menu p-0 dropdown-menu-end dropdown-menu-lg" style="min-height: 300px;">
							
								<div class="p-2 border-bottom">
									<div class="row align-items-center">
										<div class="col">
											<h6 class="m-0 fs-16 fw-semibold"> Notifications</h6>
										</div>
									</div>
								</div>
								
								<!-- Notification Body -->
								<div class="notification-body position-relative z-2 rounded-0" data-simplebar="">
									<?php if ($headerNotifs === []): ?>
										<div class="dropdown-item notification-item py-4 text-center text-muted">
											<i class="ti ti-bell-off d-block mb-1 fs-20"></i>
											No notifications
										</div>
									<?php else: ?>
										<?php foreach ($headerNotifs as $hn): ?>
											<?php $hnType = (string) ($hn['type'] ?? 'info'); ?>
											<?php $hnIcon = ['success' => 'ti-circle-check', 'warning' => 'ti-alert-triangle', 'danger' => 'ti-alert-octagon', 'info' => 'ti-info-circle'][$hnType] ?? 'ti-info-circle'; ?>
											<?php $hnColor = ['success' => 'bg-success', 'warning' => 'bg-warning', 'danger' => 'bg-danger', 'info' => 'bg-info'][$hnType] ?? 'bg-info'; ?>
											<div class="dropdown-item notification-item py-3 text-wrap border-bottom">
												<div class="d-flex">
													<div class="me-2 position-relative flex-shrink-0">
														<span class="d-inline-flex align-items-center justify-content-center rounded-circle text-white <?php echo $hnColor; ?>" style="width:40px;height:40px"><i class="ti <?php echo $hnIcon; ?> fs-18"></i></span>
													</div>
													<div class="flex-grow-1">
														<p class="mb-0 fw-medium" style="color:var(--bs-body-color)"><?php echo htmlspecialchars((string) ($hn['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
														<?php if (!empty($hn['message'])): ?>
															<p class="mb-1 text-wrap text-muted"><?php echo htmlspecialchars((string) $hn['message'], ENT_QUOTES, 'UTF-8'); ?></p>
														<?php endif; ?>
														<div class="d-flex justify-content-between align-items-center">
															<span class="fs-12"><i class="ti ti-clock me-1"></i><?php echo htmlspecialchars((string) ($hn['created_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
															<?php if ((int) ($hn['is_read'] ?? 0) === 0): ?>
																<span class="badge text-bg-warning">New</span>
															<?php endif; ?>
														</div>
													</div>
												</div>
											</div>
										<?php endforeach; ?>
									<?php endif; ?>
								</div>
								<!-- View All-->
								<div class="p-2 rounded-bottom border-top text-center">
									<a href="<?php echo htmlspecialchars($appBase, ENT_QUOTES, 'UTF-8'); ?>pages/notifications.php" class="text-center text-decoration-underline fs-14 mb-0">
										View All Notifications
									</a>
								</div>
								
							</div>
						</div>
					</div>
					
					<!-- User Dropdown -->
					<div class="dropdown profile-dropdown d-flex align-items-center justify-content-center">
                        <a href="javascript:void(0);" class="topbar-link dropdown-toggle drop-arrow-none position-relative" data-bs-toggle="dropdown" data-bs-offset="0,22" aria-haspopup="false" aria-expanded="false">
                            <?php echo clinic_avatar($currentUserImage, $currentUserName, 'clinic-avatar clinic-avatar-sm rounded-circle d-flex'); ?>
                            <span class="online text-success"><i class="ti ti-circle-filled d-flex bg-white rounded-circle border border-1 border-white"></i></span>
                        </a>
                        <div class="dropdown-menu dropdown-menu-end dropdown-menu-md p-2">
                        
                            <div class="d-flex align-items-center bg-light rounded-3 p-2 mb-2">
                                <?php echo clinic_avatar($currentUserImage, $currentUserName, 'clinic-avatar clinic-avatar-md rounded-circle'); ?>
                                <div class="ms-2">
                                    <p class="fw-medium text-dark mb-0"><?php echo htmlspecialchars($currentUserName, ENT_QUOTES, 'UTF-8'); ?></p>
                                    <span class="d-block fs-13"><?php echo htmlspecialchars($currentUserRole !== '' ? $currentUserRole : 'Clinic user', ENT_QUOTES, 'UTF-8'); ?></span>
                                </div>
                            </div>

                            <!-- Item-->
                            <a href="<?php echo htmlspecialchars($appBase, ENT_QUOTES, 'UTF-8'); ?>pages/profile.php" class="dropdown-item">
                                <i class="ti ti-user-circle me-1 align-middle"></i>
                                <span class="align-middle">My Profile</span>
                            </a>


                                        
                            
                            <!-- Item-->
                            <div class="pt-2 mt-2 border-top">
                                <a href="<?php echo htmlspecialchars($appBase, ENT_QUOTES, 'UTF-8'); ?>logout.php" class="dropdown-item text-danger">
                                    <i class="ti ti-logout me-1 fs-17 align-middle"></i>
                                    <span class="align-middle">Log Out</span>
                                </a>
                            </div>
                        </div>
                    </div>
						
                </div>
            </div>
        </header>
        <!-- Topbar End -->

   