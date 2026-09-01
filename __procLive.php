<?php
$db = new mysqli('127.0.0.1', 'root', '', 'dabiibsystem');
$db->set_charset('utf8mb4');
foreach (['sp_patients_save', 'sp_patients_get', 'sp_patients_list', 'sp_patient_profile'] as $name) {
    $r = $db->query("SHOW CREATE PROCEDURE `$name`");
    $row = $r->fetch_assoc();
    echo "=== $name ===\n" . ($row['Create Procedure'] ?? 'MISSING') . "\n\n";
}
