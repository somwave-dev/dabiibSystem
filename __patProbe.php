<?php
$t = file_get_contents(__DIR__ . '/pages/patients.php');
echo 'lines: ' . substr_count($t, "\n") . "\n";
foreach (['image', 'edit', 'modal', 'clinic_sp_one', 'data-', 'Full_Name', 'sp_patients_get'] as $n) {
    echo $n . ': ' . substr_count($t, $n) . "\n";
}
