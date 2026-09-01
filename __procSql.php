<?php
$t = file_get_contents(__DIR__ . '/db/clinic.sql');
foreach (['PROCEDURE `sp_patients_save`', 'PROCEDURE `sp_patients_get`', 'PROCEDURE `sp_patients_list`', 'PROCEDURE `sp_patient_profile`'] as $n) {
    $p = strpos($t, $n);
    $end = strpos($t, 'END$$', $p);
    echo "=== $n (len " . ($end - $p) . ") ===\n";
    echo substr($t, $p, $end - $p + 6) . "\n\n";
}
