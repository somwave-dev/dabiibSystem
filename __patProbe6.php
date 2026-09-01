<?php
$lines = file(__DIR__ . '/pages/patients.php');
foreach ($lines as $i => $line) {
    $t = trim($line);
    if (str_contains($t, 'data-patient-mode') || (str_contains($t, 'btn-edit') ) || (str_contains($t, 'patientModal') && $i > 200 && $i < 470)) {
        if (!str_contains($t, 'function') && !str_contains($t, 'addEventListener')) {
            echo ($i + 1) . ': ' . $t . "\n";
        }
    }
}
