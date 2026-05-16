<?php
require_once __DIR__ . '/../includes/crud_page.php';

$subtitle = 'Staff accounts: each person signs in separately; many users can work at the same time from different browsers or PCs.';
$introHtml = '<div class="alert alert-info border-0 shadow-sm mb-4" role="status">'
    . '<div class="fw-semibold mb-1">Multi-user</div>'
    . '<p class="mb-0 small">Create one login per staff member (doctor, reception, admin, etc.). '
    . 'PHP keeps a separate session per browser, so concurrent use is supported.'
    . ' <span lang="so">Shaqaale kasta u samee akoon — dad badan ayaa isku mar isticmaali kara.</span></p>'
    . '</div>';

clinic_render_crud_page('users', $subtitle, $introHtml);
