<?php
$t = file_get_contents(__DIR__ . '/pages/patients.php');
$p = strpos($t, 'patientModal');
echo "=== MODAL @ $p ===\n";
echo substr($t, $p - 100, 2600) . "\n";
