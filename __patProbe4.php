<?php
$t = file_get_contents(__DIR__ . '/pages/patients.php');
$needles = ['id="patientModal"', 'Patient_Image', 'data-full-name', 'fillPatientModal', 'patientModal', 'avatar'];
foreach ($needles as $n) {
    $p = strpos($t, $n);
    echo str_pad($n, 22) . ' @ ' . var_export($p, true) . "\n";
}
