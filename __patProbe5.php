<?php
$lines = file(__DIR__ . '/pages/patients.php');
foreach (['id="patientModal"', 'Patient_Image', 'data-full-name'] as $n) {
    foreach ($lines as $i => $line) {
        if (str_contains($line, $n)) {
            echo $n . ' first at line ' . ($i + 1) . "\n";
            break;
        }
    }
}
