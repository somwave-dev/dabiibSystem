<?php
declare(strict_types=1);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$db = new mysqli('127.0.0.1', 'root', '', 'dabiibsystem');
$db->set_charset('utf8mb4');

// 1) drop the image column
$db->query('ALTER TABLE `patients` DROP COLUMN `image`');
echo "column image dropped\n";

// 2) recreate procedures without image
$db->query('DROP PROCEDURE IF EXISTS sp_patients_get');
$db->query("CREATE PROCEDURE sp_patients_get (IN p_Patient_ID INT)
BEGIN
  SELECT `Patient_ID`, `Full_Name`, `Phone_Number`, `Sex`, `Age_Group`, `Patient_Type`, `Guarantor_ID`, `Relationship`, `Credit_Limit`, `Current_Balance`, `Created_At`
  FROM `patients` WHERE `Patient_ID` = p_Patient_ID;
END");
echo "sp_patients_get recreated\n";

$db->query('DROP PROCEDURE IF EXISTS sp_patients_list');
$db->query("CREATE PROCEDURE sp_patients_list ()
BEGIN
  SELECT p.`Patient_ID`, p.`Full_Name`, p.`Phone_Number`, p.`Sex`, p.`Age_Group`, p.`Patient_Type`, p.`Guarantor_ID`, g.`Full_Name` AS `Guarantor_Name`, p.`Relationship`, p.`Credit_Limit`, p.`Current_Balance`, p.`Created_At`
  FROM `patients` p
  LEFT JOIN `patients` g ON g.`Patient_ID` = p.`Guarantor_ID`
  ORDER BY p.`Patient_ID` DESC;
END");
echo "sp_patients_list recreated\n";

$db->query('DROP PROCEDURE IF EXISTS sp_patient_profile');
$db->query("CREATE PROCEDURE sp_patient_profile (IN p_Patient_ID INT)
BEGIN
  SELECT p.`Patient_ID`, p.`Full_Name`, p.`Phone_Number`, p.`Sex`, p.`Age_Group`, p.`Patient_Type`, p.`Guarantor_ID`, g.`Full_Name` AS `Guarantor_Name`, p.`Relationship`, p.`Credit_Limit`, p.`Current_Balance`, p.`Created_At`,
    (SELECT COUNT(*) FROM `visits` v WHERE v.`Patient_ID` = p.`Patient_ID`) AS `visit_count`,
    (SELECT COUNT(*) FROM `appointments` a WHERE a.`Patient_ID` = p.`Patient_ID`) AS `appointment_count`,
    (SELECT COUNT(*) FROM `payments` py WHERE py.`Patient_ID` = p.`Patient_ID`) AS `payment_count`,
    (SELECT COALESCE(SUM(py.`Amount`), 0) FROM `payments` py WHERE py.`Patient_ID` = p.`Patient_ID`) AS `total_paid`,
    (SELECT MAX(v.`Visit_Date`) FROM `visits` v WHERE v.`Patient_ID` = p.`Patient_ID`) AS `last_visit_date`
  FROM `patients` p
  LEFT JOIN `patients` g ON g.`Patient_ID` = p.`Guarantor_ID`
  WHERE p.`Patient_ID` = p_Patient_ID;
END");
echo "sp_patient_profile recreated\n";

$db->query('DROP PROCEDURE IF EXISTS sp_patients_save');
$db->query("CREATE PROCEDURE sp_patients_save (IN p_Patient_ID INT, IN p_Full_Name VARCHAR(100), IN p_Phone_Number VARCHAR(20), IN p_Sex VARCHAR(10), IN p_Age_Group VARCHAR(10), IN p_Patient_Type VARCHAR(20), IN p_Guarantor_ID INT, IN p_Relationship VARCHAR(20), IN p_Credit_Limit DECIMAL(10,2), IN p_Current_Balance DECIMAL(10,2))
BEGIN
  IF p_Patient_ID IS NULL OR p_Patient_ID = 0 THEN
    INSERT INTO `patients` (`Full_Name`, `Phone_Number`, `Sex`, `Age_Group`, `Patient_Type`, `Guarantor_ID`, `Relationship`, `Credit_Limit`, `Current_Balance`)
    VALUES (p_Full_Name, NULLIF(p_Phone_Number, ''), COALESCE(NULLIF(p_Sex, ''), 'Male'), COALESCE(NULLIF(p_Age_Group, ''), 'Adult'), COALESCE(NULLIF(p_Patient_Type, ''), 'Walk-in'), p_Guarantor_ID, COALESCE(NULLIF(p_Relationship, ''), 'Self'), COALESCE(p_Credit_Limit, 0.00), COALESCE(p_Current_Balance, 0.00));
  ELSE
    UPDATE `patients` SET `Full_Name` = p_Full_Name, `Phone_Number` = NULLIF(p_Phone_Number, ''), `Sex` = COALESCE(NULLIF(p_Sex, ''), 'Male'), `Age_Group` = COALESCE(NULLIF(p_Age_Group, ''), 'Adult'), `Patient_Type` = COALESCE(NULLIF(p_Patient_Type, ''), 'Walk-in'), `Guarantor_ID` = p_Guarantor_ID, `Relationship` = COALESCE(NULLIF(p_Relationship, ''), 'Self'), `Credit_Limit` = COALESCE(p_Credit_Limit, 0.00), `Current_Balance` = COALESCE(p_Current_Balance, 0.00) WHERE `Patient_ID` = p_Patient_ID;
  END IF;
END");
echo "sp_patients_save recreated\n";

// verify
$cols = $db->query('SHOW COLUMNS FROM patients');
$hasImage = false;
while ($c = $cols->fetch_row()) { if (strtolower($c[0]) === 'image') $hasImage = true; }
echo 'patients still has image: ' . ($hasImage ? 'YES(bad)' : 'no(good)') . "\n";
echo "DONE\n";
