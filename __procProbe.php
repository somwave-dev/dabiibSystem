<?php
$t = file_get_contents(__DIR__ . '/db/clinic.sql');
foreach (['PROCEDURE `sp_patients_save`', 'PROCEDURE `sp_patients_get`', 'PROCEDURE `sp_patients_list`', 'PROCEDURE `sp_patient_profile`', 'PROCEDURE `sp_patients_delete`'] as $n) {
    $p = strpos($t, $n);
    echo "--- $n @ " . var_export($p, true) . " ---\n";
    if ($p !== false) {
        echo substr($t, $p, 700) . "\n\n";
    }
}
