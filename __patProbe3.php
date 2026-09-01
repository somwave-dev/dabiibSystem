<?php
$t = file_get_contents(__DIR__ . '/pages/patients.php');
foreach (['id="patientModal"', 'Patient_Image', 'data-full-name', 'formPatient', 'id="editPatient', 'fillPatientModal', 'patientModal"] as $n) {
    $p = strpos($t, $n);
    echo str_pad($n, 22) . ' @ ' . var_export($p, true) . "\n";
}
