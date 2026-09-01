<?php
$db = new mysqli('127.0.0.1', 'root', '', 'dabiibsystem');
$db->set_charset('utf8mb4');
$r = $db->query("SELECT ROUTINE_NAME FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA='dabiibsystem' AND ROUTINE_TYPE='PROCEDURE'");
while ($x = $r->fetch_row()) {
    $name = $x[0];
    $rr = $db->query("SHOW CREATE PROCEDURE `$name`");
    $row = $rr->fetch_assoc();
    $body = (string) ($row['Create Procedure'] ?? '');
    if (preg_match('/`image`|patients\.`image`|\bimage\b/i', $body)) {
        // only flag if it's the patients image column
        if (preg_match('/`patients`.*`image`|`image`.*`patients`|patients.*image|image.*patients/i', $body)) {
            echo "PATIENTS-IMAGE ref: $name\n";
        }
    }
}
echo "done\n";
