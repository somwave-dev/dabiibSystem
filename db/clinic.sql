-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 16, 2026 at 01:22 PM
-- Server version: 10.4.28-MariaDB
-- PHP Version: 8.2.4

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `clinic`
--

DELIMITER $$
--
-- Procedures
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_accounts_delete` (IN `p_Account_ID` INT)   BEGIN
  DELETE FROM `accounts` WHERE `Account_ID` = p_Account_ID;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_accounts_get` (IN `p_Account_ID` INT)   BEGIN
  SELECT `Account_ID`, `Account_Name`, `Current_Balance` FROM `accounts` WHERE `Account_ID` = p_Account_ID;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_accounts_list` ()   BEGIN
  SELECT `Account_ID`, `Account_Name`, `Current_Balance` FROM `accounts` ORDER BY `Account_ID` DESC;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_accounts_save` (IN `p_Account_ID` INT, IN `p_Account_Name` VARCHAR(50), IN `p_Current_Balance` DECIMAL(15,2))   BEGIN
  IF p_Account_ID IS NULL OR p_Account_ID = 0 THEN
    INSERT INTO `accounts` (`Account_Name`, `Current_Balance`) VALUES (p_Account_Name, COALESCE(p_Current_Balance, 0.00));
  ELSE
    UPDATE `accounts` SET `Account_Name` = p_Account_Name, `Current_Balance` = COALESCE(p_Current_Balance, 0.00) WHERE `Account_ID` = p_Account_ID;
  END IF;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_account_transfers_delete` (IN `p_Transfer_ID` INT)   BEGIN
  DELETE FROM `account_transfers` WHERE `Transfer_ID` = p_Transfer_ID;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_account_transfers_get` (IN `p_Transfer_ID` INT)   BEGIN
  SELECT `Transfer_ID`, `From_Account_ID`, `To_Account_ID`, `Amount`, `Transfer_Date`, `User_ID` FROM `account_transfers` WHERE `Transfer_ID` = p_Transfer_ID;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_account_transfers_list` ()   BEGIN
  SELECT t.`Transfer_ID`, t.`From_Account_ID`, fa.`Account_Name` AS `From_Account_Name`, t.`To_Account_ID`, ta.`Account_Name` AS `To_Account_Name`, t.`Amount`, t.`Transfer_Date`, t.`User_ID`, u.`Username`
  FROM `account_transfers` t
  LEFT JOIN `accounts` fa ON fa.`Account_ID` = t.`From_Account_ID`
  LEFT JOIN `accounts` ta ON ta.`Account_ID` = t.`To_Account_ID`
  LEFT JOIN `users` u ON u.`User_ID` = t.`User_ID`
  ORDER BY t.`Transfer_ID` DESC;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_account_transfers_save` (IN `p_Transfer_ID` INT, IN `p_From_Account_ID` INT, IN `p_To_Account_ID` INT, IN `p_Amount` DECIMAL(15,2), IN `p_Transfer_Date` VARCHAR(25), IN `p_User_ID` INT)   BEGIN
  IF p_Transfer_ID IS NULL OR p_Transfer_ID = 0 THEN
    INSERT INTO `account_transfers` (`From_Account_ID`, `To_Account_ID`, `Amount`, `Transfer_Date`, `User_ID`) VALUES (p_From_Account_ID, p_To_Account_ID, p_Amount, COALESCE(NULLIF(p_Transfer_Date, ''), NOW()), p_User_ID);
  ELSE
    UPDATE `account_transfers` SET `From_Account_ID` = p_From_Account_ID, `To_Account_ID` = p_To_Account_ID, `Amount` = p_Amount, `Transfer_Date` = COALESCE(NULLIF(p_Transfer_Date, ''), `Transfer_Date`), `User_ID` = p_User_ID WHERE `Transfer_ID` = p_Transfer_ID;
  END IF;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_appointments_delete` (IN `p_Appointment_ID` INT)   BEGIN
  DELETE FROM `appointments` WHERE `Appointment_ID` = p_Appointment_ID;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_appointments_get` (IN `p_Appointment_ID` INT)   BEGIN
  SELECT `Appointment_ID`, `Patient_ID`, `Doctor_ID`, `Appointment_Date`, `Status` FROM `appointments` WHERE `Appointment_ID` = p_Appointment_ID;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_appointments_list` ()   BEGIN
  SELECT a.`Appointment_ID`, a.`Patient_ID`, p.`Full_Name` AS `Patient_Name`, a.`Doctor_ID`, d.`Full_Name` AS `Doctor_Name`, a.`Appointment_Date`, a.`Status`
  FROM `appointments` a
  LEFT JOIN `patients` p ON p.`Patient_ID` = a.`Patient_ID`
  LEFT JOIN `doctors` d ON d.`Doctor_ID` = a.`Doctor_ID`
  ORDER BY a.`Appointment_ID` DESC;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_appointments_save` (IN `p_Appointment_ID` INT, IN `p_Patient_ID` INT, IN `p_Doctor_ID` INT, IN `p_Appointment_Date` VARCHAR(25), IN `p_Status` VARCHAR(20))   BEGIN
  IF p_Appointment_ID IS NULL OR p_Appointment_ID = 0 THEN
    INSERT INTO `appointments` (`Patient_ID`, `Doctor_ID`, `Appointment_Date`, `Status`) VALUES (p_Patient_ID, p_Doctor_ID, p_Appointment_Date, COALESCE(NULLIF(p_Status, ''), 'Pending'));
  ELSE
    UPDATE `appointments` SET `Patient_ID` = p_Patient_ID, `Doctor_ID` = p_Doctor_ID, `Appointment_Date` = p_Appointment_Date, `Status` = COALESCE(NULLIF(p_Status, ''), 'Pending') WHERE `Appointment_ID` = p_Appointment_ID;
  END IF;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_collect_payment` (IN `p_Patient_ID` INT, IN `p_Account_ID` INT, IN `p_Amount` DECIMAL(10,2), IN `p_Payment_Method` VARCHAR(20), IN `p_Transaction_Ref` VARCHAR(50), IN `p_User_ID` INT)   BEGIN
  INSERT INTO `payments` (`Patient_ID`, `Account_ID`, `Amount`, `Payment_Method`, `Transaction_Ref`, `Payment_Date`, `User_ID`) VALUES (p_Patient_ID, p_Account_ID, p_Amount, p_Payment_Method, NULLIF(p_Transaction_Ref, ''), NOW(), NULLIF(p_User_ID, 0));
  UPDATE `patients` SET `Current_Balance` = GREATEST(`Current_Balance` - p_Amount, 0) WHERE `Patient_ID` = p_Patient_ID;
  UPDATE `accounts` SET `Current_Balance` = `Current_Balance` + p_Amount WHERE `Account_ID` = p_Account_ID;
  SELECT LAST_INSERT_ID() AS `Payment_ID`;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_complete_lab_result` (IN `p_Result_ID` INT, IN `p_Result_Details` TEXT)   BEGIN
  UPDATE `lab_results`
  SET `Result_Details` = NULLIF(p_Result_Details, ''), `Status` = 'Completed'
  WHERE `Result_ID` = p_Result_ID;
  SELECT p_Result_ID AS `Result_ID`;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_create_visit_with_actions` (IN `p_Patient_ID` INT, IN `p_Doctor_ID` INT, IN `p_Notes` TEXT, IN `p_Appointment_ID` INT)   BEGIN
  INSERT INTO `visits` (`Patient_ID`, `Doctor_ID`, `Visit_Date`, `Notes`) VALUES (p_Patient_ID, NULLIF(p_Doctor_ID, 0), NOW(), NULLIF(p_Notes, ''));
  IF p_Appointment_ID IS NOT NULL AND p_Appointment_ID > 0 THEN
    UPDATE `appointments` SET `Status` = 'Completed' WHERE `Appointment_ID` = p_Appointment_ID;
  END IF;
  SELECT LAST_INSERT_ID() AS `Visit_ID`;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_dashboard_summary` ()   BEGIN
  SELECT
    (SELECT COUNT(*) FROM `patients`) AS `patients_total`,
    (SELECT COUNT(*) FROM `doctors`) AS `doctors_total`,
    (SELECT COUNT(*) FROM `appointments` WHERE DATE(`Appointment_Date`) = CURDATE()) AS `appointments_today`,
    (SELECT COUNT(*) FROM `appointments` WHERE `Status` = 'Pending') AS `appointments_pending`,
    (SELECT COUNT(*) FROM `lab_results` WHERE `Status` = 'Pending') AS `lab_pending`,
    (SELECT COUNT(*) FROM `medicines` WHERE `Stock_Quantity` <= 100) AS `low_stock`,
    (SELECT COALESCE(SUM(`Amount`), 0) FROM `payments` WHERE DATE(`Payment_Date`) = CURDATE()) AS `revenue_today`,
    (SELECT COALESCE(SUM(`Amount`), 0) FROM `payments` WHERE `Payment_Date` >= DATE_SUB(NOW(), INTERVAL 7 DAY)) AS `revenue_week`,
    (SELECT COALESCE(SUM(`Current_Balance`), 0) FROM `patients`) AS `patient_debt`;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_doctors_delete` (IN `p_Doctor_ID` INT)   BEGIN
  DELETE FROM `doctors` WHERE `Doctor_ID` = p_Doctor_ID;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_doctors_get` (IN `p_Doctor_ID` INT)   BEGIN
  SELECT `Doctor_ID`, `Full_Name`, `Specialization`, `Consultation_Fee`, `User_ID` FROM `doctors` WHERE `Doctor_ID` = p_Doctor_ID;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_doctors_list` ()   BEGIN
  SELECT d.`Doctor_ID`, d.`Full_Name`, d.`Specialization`, d.`Consultation_Fee`, d.`User_ID`, u.`Username`
  FROM `doctors` d
  LEFT JOIN `users` u ON u.`User_ID` = d.`User_ID`
  ORDER BY d.`Doctor_ID` DESC;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_doctors_save` (IN `p_Doctor_ID` INT, IN `p_Full_Name` VARCHAR(100), IN `p_Specialization` VARCHAR(100), IN `p_Consultation_Fee` DECIMAL(10,2), IN `p_User_ID` INT)   BEGIN
  IF p_Doctor_ID IS NULL OR p_Doctor_ID = 0 THEN
    INSERT INTO `doctors` (`Full_Name`, `Specialization`, `Consultation_Fee`, `User_ID`) VALUES (p_Full_Name, NULLIF(p_Specialization, ''), COALESCE(p_Consultation_Fee, 0.00), p_User_ID);
  ELSE
    UPDATE `doctors` SET `Full_Name` = p_Full_Name, `Specialization` = NULLIF(p_Specialization, ''), `Consultation_Fee` = COALESCE(p_Consultation_Fee, 0.00), `User_ID` = p_User_ID WHERE `Doctor_ID` = p_Doctor_ID;
  END IF;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_lab_results_delete` (IN `p_Result_ID` INT)   BEGIN
  DELETE FROM `lab_results` WHERE `Result_ID` = p_Result_ID;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_lab_results_get` (IN `p_Result_ID` INT)   BEGIN
  SELECT `Result_ID`, `Visit_ID`, `Test_ID`, `Result_Details`, `Status` FROM `lab_results` WHERE `Result_ID` = p_Result_ID;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_lab_results_list` ()   BEGIN
  SELECT lr.`Result_ID`, lr.`Visit_ID`, p.`Full_Name` AS `Patient_Name`, lr.`Test_ID`, lt.`Test_Name`, lr.`Result_Details`, lr.`Status`
  FROM `lab_results` lr
  LEFT JOIN `visits` v ON v.`Visit_ID` = lr.`Visit_ID`
  LEFT JOIN `patients` p ON p.`Patient_ID` = v.`Patient_ID`
  LEFT JOIN `lab_tests` lt ON lt.`Test_ID` = lr.`Test_ID`
  ORDER BY lr.`Result_ID` DESC;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_lab_results_save` (IN `p_Result_ID` INT, IN `p_Visit_ID` INT, IN `p_Test_ID` INT, IN `p_Result_Details` TEXT, IN `p_Status` VARCHAR(20))   BEGIN
  IF p_Result_ID IS NULL OR p_Result_ID = 0 THEN
    INSERT INTO `lab_results` (`Visit_ID`, `Test_ID`, `Result_Details`, `Status`) VALUES (p_Visit_ID, p_Test_ID, NULLIF(p_Result_Details, ''), COALESCE(NULLIF(p_Status, ''), 'Pending'));
  ELSE
    UPDATE `lab_results` SET `Visit_ID` = p_Visit_ID, `Test_ID` = p_Test_ID, `Result_Details` = NULLIF(p_Result_Details, ''), `Status` = COALESCE(NULLIF(p_Status, ''), 'Pending') WHERE `Result_ID` = p_Result_ID;
  END IF;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_lab_tests_delete` (IN `p_Test_ID` INT)   BEGIN
  DELETE FROM `lab_tests` WHERE `Test_ID` = p_Test_ID;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_lab_tests_get` (IN `p_Test_ID` INT)   BEGIN
  SELECT `Test_ID`, `Test_Name`, `Price` FROM `lab_tests` WHERE `Test_ID` = p_Test_ID;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_lab_tests_list` ()   BEGIN
  SELECT `Test_ID`, `Test_Name`, `Price` FROM `lab_tests` ORDER BY `Test_ID` DESC;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_lab_tests_save` (IN `p_Test_ID` INT, IN `p_Test_Name` VARCHAR(100), IN `p_Price` DECIMAL(10,2))   BEGIN
  IF p_Test_ID IS NULL OR p_Test_ID = 0 THEN
    INSERT INTO `lab_tests` (`Test_Name`, `Price`) VALUES (p_Test_Name, p_Price);
  ELSE
    UPDATE `lab_tests` SET `Test_Name` = p_Test_Name, `Price` = p_Price WHERE `Test_ID` = p_Test_ID;
  END IF;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_low_stock_medicines` ()   BEGIN
  SELECT `Medicine_ID`, `Medicine_Name`, `Price`, `Stock_Quantity`, `Expiry_Date`
  FROM `medicines`
  WHERE `Stock_Quantity` <= 100
  ORDER BY `Stock_Quantity` ASC, `Expiry_Date` ASC;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_medicines_delete` (IN `p_Medicine_ID` INT)   BEGIN
  DELETE FROM `medicines` WHERE `Medicine_ID` = p_Medicine_ID;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_medicines_get` (IN `p_Medicine_ID` INT)   BEGIN
  SELECT `Medicine_ID`, `Medicine_Name`, `Price`, `Stock_Quantity`, `Expiry_Date` FROM `medicines` WHERE `Medicine_ID` = p_Medicine_ID;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_medicines_list` ()   BEGIN
  SELECT `Medicine_ID`, `Medicine_Name`, `Price`, `Stock_Quantity`, `Expiry_Date` FROM `medicines` ORDER BY `Medicine_ID` DESC;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_medicines_save` (IN `p_Medicine_ID` INT, IN `p_Medicine_Name` VARCHAR(100), IN `p_Price` DECIMAL(10,2), IN `p_Stock_Quantity` INT, IN `p_Expiry_Date` VARCHAR(20))   BEGIN
  IF p_Medicine_ID IS NULL OR p_Medicine_ID = 0 THEN
    INSERT INTO `medicines` (`Medicine_Name`, `Price`, `Stock_Quantity`, `Expiry_Date`) VALUES (p_Medicine_Name, p_Price, p_Stock_Quantity, NULLIF(p_Expiry_Date, ''));
  ELSE
    UPDATE `medicines` SET `Medicine_Name` = p_Medicine_Name, `Price` = p_Price, `Stock_Quantity` = p_Stock_Quantity, `Expiry_Date` = NULLIF(p_Expiry_Date, '') WHERE `Medicine_ID` = p_Medicine_ID;
  END IF;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_menues_delete` (IN `p_menu_id` INT)   BEGIN
  UPDATE `menues` SET `deleted` = 1, `status` = 'inactive' WHERE `menu_id` = p_menu_id;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_menues_get` (IN `p_menu_id` INT)   BEGIN
  SELECT `menu_id`, `menu_name`, `icon`, `menu_group`, `status`, `sort_order` FROM `menues` WHERE `menu_id` = p_menu_id AND `deleted` = 0;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_menues_list` ()   BEGIN
  SELECT `menu_id`, `menu_name`, `icon`, `menu_group`, `status`, `sort_order` FROM `menues` WHERE `deleted` = 0 ORDER BY `sort_order` ASC, `menu_id` DESC;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_menues_save` (IN `p_menu_id` INT, IN `p_menu_name` VARCHAR(100), IN `p_icon` VARCHAR(50), IN `p_menu_group` VARCHAR(50), IN `p_status` VARCHAR(20), IN `p_sort_order` INT)   BEGIN
  IF p_menu_id IS NULL OR p_menu_id = 0 THEN
    INSERT INTO `menues` (`menu_name`, `icon`, `menu_group`, `status`, `sort_order`, `deleted`) VALUES (p_menu_name, NULLIF(p_icon, ''), NULLIF(p_menu_group, ''), COALESCE(NULLIF(p_status, ''), 'active'), COALESCE(p_sort_order, 0), 0);
  ELSE
    UPDATE `menues` SET `menu_name` = p_menu_name, `icon` = NULLIF(p_icon, ''), `menu_group` = NULLIF(p_menu_group, ''), `status` = COALESCE(NULLIF(p_status, ''), 'active'), `sort_order` = COALESCE(p_sort_order, 0) WHERE `menu_id` = p_menu_id;
  END IF;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_nursing_records_delete` (IN `p_Record_ID` INT)   BEGIN
  DELETE FROM `nursing_records` WHERE `Record_ID` = p_Record_ID;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_nursing_records_get` (IN `p_Record_ID` INT)   BEGIN
  SELECT `Record_ID`, `Visit_ID`, `Service_ID`, `Medicine_Used`, `Administered_By`, `Record_Date` FROM `nursing_records` WHERE `Record_ID` = p_Record_ID;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_nursing_records_list` ()   BEGIN
  SELECT nr.`Record_ID`, nr.`Visit_ID`, p.`Full_Name` AS `Patient_Name`, nr.`Service_ID`, ns.`Service_Name`, nr.`Medicine_Used`, nr.`Administered_By`, u.`Username` AS `Administered_By_Name`, nr.`Record_Date`
  FROM `nursing_records` nr
  LEFT JOIN `visits` v ON v.`Visit_ID` = nr.`Visit_ID`
  LEFT JOIN `patients` p ON p.`Patient_ID` = v.`Patient_ID`
  LEFT JOIN `nursing_services` ns ON ns.`Service_ID` = nr.`Service_ID`
  LEFT JOIN `users` u ON u.`User_ID` = nr.`Administered_By`
  ORDER BY nr.`Record_ID` DESC;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_nursing_records_save` (IN `p_Record_ID` INT, IN `p_Visit_ID` INT, IN `p_Service_ID` INT, IN `p_Medicine_Used` VARCHAR(100), IN `p_Administered_By` INT, IN `p_Record_Date` VARCHAR(25))   BEGIN
  IF p_Record_ID IS NULL OR p_Record_ID = 0 THEN
    INSERT INTO `nursing_records` (`Visit_ID`, `Service_ID`, `Medicine_Used`, `Administered_By`, `Record_Date`) VALUES (p_Visit_ID, p_Service_ID, NULLIF(p_Medicine_Used, ''), p_Administered_By, COALESCE(NULLIF(p_Record_Date, ''), NOW()));
  ELSE
    UPDATE `nursing_records` SET `Visit_ID` = p_Visit_ID, `Service_ID` = p_Service_ID, `Medicine_Used` = NULLIF(p_Medicine_Used, ''), `Administered_By` = p_Administered_By, `Record_Date` = COALESCE(NULLIF(p_Record_Date, ''), `Record_Date`) WHERE `Record_ID` = p_Record_ID;
  END IF;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_nursing_services_delete` (IN `p_Service_ID` INT)   BEGIN
  DELETE FROM `nursing_services` WHERE `Service_ID` = p_Service_ID;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_nursing_services_get` (IN `p_Service_ID` INT)   BEGIN
  SELECT `Service_ID`, `Service_Name`, `Price` FROM `nursing_services` WHERE `Service_ID` = p_Service_ID;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_nursing_services_list` ()   BEGIN
  SELECT `Service_ID`, `Service_Name`, `Price` FROM `nursing_services` ORDER BY `Service_ID` DESC;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_nursing_services_save` (IN `p_Service_ID` INT, IN `p_Service_Name` VARCHAR(100), IN `p_Price` DECIMAL(10,2))   BEGIN
  IF p_Service_ID IS NULL OR p_Service_ID = 0 THEN
    INSERT INTO `nursing_services` (`Service_Name`, `Price`) VALUES (p_Service_Name, COALESCE(p_Price, 0.00));
  ELSE
    UPDATE `nursing_services` SET `Service_Name` = p_Service_Name, `Price` = COALESCE(p_Price, 0.00) WHERE `Service_ID` = p_Service_ID;
  END IF;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_patients_delete` (IN `p_Patient_ID` INT)   BEGIN
  DELETE FROM `patients` WHERE `Patient_ID` = p_Patient_ID;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_patients_get` (IN `p_Patient_ID` INT)   BEGIN
  SELECT `Patient_ID`, `Full_Name`, `Phone_Number`, `Sex`, `Age_Group`, `Patient_Type`, `Guarantor_ID`, `Relationship`, `Credit_Limit`, `Current_Balance`, `Created_At` FROM `patients` WHERE `Patient_ID` = p_Patient_ID;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_patients_list` ()   BEGIN
  SELECT p.`Patient_ID`, p.`Full_Name`, p.`Phone_Number`, p.`Sex`, p.`Age_Group`, p.`Patient_Type`, p.`Guarantor_ID`, g.`Full_Name` AS `Guarantor_Name`, p.`Relationship`, p.`Credit_Limit`, p.`Current_Balance`, p.`Created_At`
  FROM `patients` p
  LEFT JOIN `patients` g ON g.`Patient_ID` = p.`Guarantor_ID`
  ORDER BY p.`Patient_ID` DESC;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_patients_save` (IN `p_Patient_ID` INT, IN `p_Full_Name` VARCHAR(100), IN `p_Phone_Number` VARCHAR(20), IN `p_Sex` VARCHAR(10), IN `p_Age_Group` VARCHAR(10), IN `p_Patient_Type` VARCHAR(20), IN `p_Guarantor_ID` INT, IN `p_Relationship` VARCHAR(20), IN `p_Credit_Limit` DECIMAL(10,2), IN `p_Current_Balance` DECIMAL(10,2))   BEGIN
  IF p_Patient_ID IS NULL OR p_Patient_ID = 0 THEN
    INSERT INTO `patients` (`Full_Name`, `Phone_Number`, `Sex`, `Age_Group`, `Patient_Type`, `Guarantor_ID`, `Relationship`, `Credit_Limit`, `Current_Balance`) VALUES (p_Full_Name, NULLIF(p_Phone_Number, ''), COALESCE(NULLIF(p_Sex, ''), 'Male'), COALESCE(NULLIF(p_Age_Group, ''), 'Adult'), COALESCE(NULLIF(p_Patient_Type, ''), 'Maalinle'), p_Guarantor_ID, COALESCE(NULLIF(p_Relationship, ''), 'Self'), COALESCE(p_Credit_Limit, 0.00), COALESCE(p_Current_Balance, 0.00));
  ELSE
    UPDATE `patients` SET `Full_Name` = p_Full_Name, `Phone_Number` = NULLIF(p_Phone_Number, ''), `Sex` = COALESCE(NULLIF(p_Sex, ''), 'Male'), `Age_Group` = COALESCE(NULLIF(p_Age_Group, ''), 'Adult'), `Patient_Type` = COALESCE(NULLIF(p_Patient_Type, ''), 'Maalinle'), `Guarantor_ID` = p_Guarantor_ID, `Relationship` = COALESCE(NULLIF(p_Relationship, ''), 'Self'), `Credit_Limit` = COALESCE(p_Credit_Limit, 0.00), `Current_Balance` = COALESCE(p_Current_Balance, 0.00) WHERE `Patient_ID` = p_Patient_ID;
  END IF;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_patient_profile` (IN `p_Patient_ID` INT)   BEGIN
  SELECT p.`Patient_ID`, p.`Full_Name`, p.`Phone_Number`, p.`Sex`, p.`Age_Group`, p.`Patient_Type`, p.`Guarantor_ID`, g.`Full_Name` AS `Guarantor_Name`, p.`Relationship`, p.`Credit_Limit`, p.`Current_Balance`, p.`Created_At`,
    (SELECT COUNT(*) FROM `visits` v WHERE v.`Patient_ID` = p.`Patient_ID`) AS `visit_count`,
    (SELECT COUNT(*) FROM `appointments` a WHERE a.`Patient_ID` = p.`Patient_ID`) AS `appointment_count`,
    (SELECT COUNT(*) FROM `payments` py WHERE py.`Patient_ID` = p.`Patient_ID`) AS `payment_count`,
    (SELECT COALESCE(SUM(py.`Amount`), 0) FROM `payments` py WHERE py.`Patient_ID` = p.`Patient_ID`) AS `total_paid`,
    (SELECT MAX(v.`Visit_Date`) FROM `visits` v WHERE v.`Patient_ID` = p.`Patient_ID`) AS `last_visit_date`
  FROM `patients` p
  LEFT JOIN `patients` g ON g.`Patient_ID` = p.`Guarantor_ID`
  WHERE p.`Patient_ID` = p_Patient_ID;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_patient_timeline` (IN `p_Patient_ID` INT)   BEGIN
  SELECT 'Visit' AS `event_type`, v.`Visit_ID` AS `event_id`, v.`Visit_Date` AS `event_at`, COALESCE(v.`Notes`, 'Visit recorded') AS `description`, d.`Full_Name` AS `related_name`
  FROM `visits` v
  LEFT JOIN `doctors` d ON d.`Doctor_ID` = v.`Doctor_ID`
  WHERE v.`Patient_ID` = p_Patient_ID
  UNION ALL
  SELECT 'Payment', py.`Payment_ID`, py.`Payment_Date`, CONCAT('Payment ', py.`Payment_Method`, ' ', py.`Amount`), a.`Account_Name`
  FROM `payments` py
  LEFT JOIN `accounts` a ON a.`Account_ID` = py.`Account_ID`
  WHERE py.`Patient_ID` = p_Patient_ID
  UNION ALL
  SELECT 'Lab', lr.`Result_ID`, v.`Visit_Date`, CONCAT(lt.`Test_Name`, ' - ', lr.`Status`), lr.`Result_Details`
  FROM `lab_results` lr
  INNER JOIN `visits` v ON v.`Visit_ID` = lr.`Visit_ID`
  LEFT JOIN `lab_tests` lt ON lt.`Test_ID` = lr.`Test_ID`
  WHERE v.`Patient_ID` = p_Patient_ID
  UNION ALL
  SELECT 'Pharmacy', ps.`Sale_ID`, ps.`Sale_Date`, CONCAT(m.`Medicine_Name`, ' x', ps.`Quantity`, ' = ', ps.`Total_Price`), m.`Medicine_Name`
  FROM `pharmacy_sales` ps
  LEFT JOIN `medicines` m ON m.`Medicine_ID` = ps.`Medicine_ID`
  WHERE ps.`Patient_ID` = p_Patient_ID
  ORDER BY `event_at` DESC;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_payments_delete` (IN `p_Payment_ID` INT)   BEGIN
  DELETE FROM `payments` WHERE `Payment_ID` = p_Payment_ID;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_payments_get` (IN `p_Payment_ID` INT)   BEGIN
  SELECT `Payment_ID`, `Patient_ID`, `Account_ID`, `Amount`, `Payment_Method`, `Transaction_Ref`, `Payment_Date`, `User_ID` FROM `payments` WHERE `Payment_ID` = p_Payment_ID;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_payments_list` ()   BEGIN
  SELECT py.`Payment_ID`, py.`Patient_ID`, p.`Full_Name` AS `Patient_Name`, py.`Account_ID`, a.`Account_Name`, py.`Amount`, py.`Payment_Method`, py.`Transaction_Ref`, py.`Payment_Date`, py.`User_ID`, u.`Username`
  FROM `payments` py
  LEFT JOIN `patients` p ON p.`Patient_ID` = py.`Patient_ID`
  LEFT JOIN `accounts` a ON a.`Account_ID` = py.`Account_ID`
  LEFT JOIN `users` u ON u.`User_ID` = py.`User_ID`
  ORDER BY py.`Payment_ID` DESC;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_payments_save` (IN `p_Payment_ID` INT, IN `p_Patient_ID` INT, IN `p_Account_ID` INT, IN `p_Amount` DECIMAL(10,2), IN `p_Payment_Method` VARCHAR(20), IN `p_Transaction_Ref` VARCHAR(50), IN `p_Payment_Date` VARCHAR(25), IN `p_User_ID` INT)   BEGIN
  IF p_Payment_ID IS NULL OR p_Payment_ID = 0 THEN
    INSERT INTO `payments` (`Patient_ID`, `Account_ID`, `Amount`, `Payment_Method`, `Transaction_Ref`, `Payment_Date`, `User_ID`) VALUES (p_Patient_ID, p_Account_ID, p_Amount, p_Payment_Method, NULLIF(p_Transaction_Ref, ''), COALESCE(NULLIF(p_Payment_Date, ''), NOW()), p_User_ID);
  ELSE
    UPDATE `payments` SET `Patient_ID` = p_Patient_ID, `Account_ID` = p_Account_ID, `Amount` = p_Amount, `Payment_Method` = p_Payment_Method, `Transaction_Ref` = NULLIF(p_Transaction_Ref, ''), `Payment_Date` = COALESCE(NULLIF(p_Payment_Date, ''), `Payment_Date`), `User_ID` = p_User_ID WHERE `Payment_ID` = p_Payment_ID;
  END IF;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_pharmacy_sales_delete` (IN `p_Sale_ID` INT)   BEGIN
  DELETE FROM `pharmacy_sales` WHERE `Sale_ID` = p_Sale_ID;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_pharmacy_sales_get` (IN `p_Sale_ID` INT)   BEGIN
  SELECT `Sale_ID`, `Patient_ID`, `Medicine_ID`, `Quantity`, `Total_Price`, `Sale_Date`, `User_ID` FROM `pharmacy_sales` WHERE `Sale_ID` = p_Sale_ID;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_pharmacy_sales_list` ()   BEGIN
  SELECT ps.`Sale_ID`, ps.`Patient_ID`, p.`Full_Name` AS `Patient_Name`, ps.`Medicine_ID`, m.`Medicine_Name`, ps.`Quantity`, ps.`Total_Price`, ps.`Sale_Date`, ps.`User_ID`, u.`Username`
  FROM `pharmacy_sales` ps
  LEFT JOIN `patients` p ON p.`Patient_ID` = ps.`Patient_ID`
  LEFT JOIN `medicines` m ON m.`Medicine_ID` = ps.`Medicine_ID`
  LEFT JOIN `users` u ON u.`User_ID` = ps.`User_ID`
  ORDER BY ps.`Sale_ID` DESC;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_pharmacy_sales_save` (IN `p_Sale_ID` INT, IN `p_Patient_ID` INT, IN `p_Medicine_ID` INT, IN `p_Quantity` INT, IN `p_Total_Price` DECIMAL(10,2), IN `p_Sale_Date` VARCHAR(25), IN `p_User_ID` INT)   BEGIN
  IF p_Sale_ID IS NULL OR p_Sale_ID = 0 THEN
    INSERT INTO `pharmacy_sales` (`Patient_ID`, `Medicine_ID`, `Quantity`, `Total_Price`, `Sale_Date`, `User_ID`) VALUES (p_Patient_ID, p_Medicine_ID, p_Quantity, p_Total_Price, COALESCE(NULLIF(p_Sale_Date, ''), NOW()), p_User_ID);
  ELSE
    UPDATE `pharmacy_sales` SET `Patient_ID` = p_Patient_ID, `Medicine_ID` = p_Medicine_ID, `Quantity` = p_Quantity, `Total_Price` = p_Total_Price, `Sale_Date` = COALESCE(NULLIF(p_Sale_Date, ''), `Sale_Date`), `User_ID` = p_User_ID WHERE `Sale_ID` = p_Sale_ID;
  END IF;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_prescriptions_delete` (IN `p_Prescription_ID` INT)   BEGIN
  DELETE FROM `prescriptions` WHERE `Prescription_ID` = p_Prescription_ID;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_prescriptions_get` (IN `p_Prescription_ID` INT)   BEGIN
  SELECT `Prescription_ID`, `Visit_ID`, `Medicine_ID`, `Dosage` FROM `prescriptions` WHERE `Prescription_ID` = p_Prescription_ID;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_prescriptions_list` ()   BEGIN
  SELECT pr.`Prescription_ID`, pr.`Visit_ID`, p.`Full_Name` AS `Patient_Name`, pr.`Medicine_ID`, m.`Medicine_Name`, pr.`Dosage`
  FROM `prescriptions` pr
  LEFT JOIN `visits` v ON v.`Visit_ID` = pr.`Visit_ID`
  LEFT JOIN `patients` p ON p.`Patient_ID` = v.`Patient_ID`
  LEFT JOIN `medicines` m ON m.`Medicine_ID` = pr.`Medicine_ID`
  ORDER BY pr.`Prescription_ID` DESC;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_prescriptions_save` (IN `p_Prescription_ID` INT, IN `p_Visit_ID` INT, IN `p_Medicine_ID` INT, IN `p_Dosage` VARCHAR(100))   BEGIN
  IF p_Prescription_ID IS NULL OR p_Prescription_ID = 0 THEN
    INSERT INTO `prescriptions` (`Visit_ID`, `Medicine_ID`, `Dosage`) VALUES (p_Visit_ID, p_Medicine_ID, NULLIF(p_Dosage, ''));
  ELSE
    UPDATE `prescriptions` SET `Visit_ID` = p_Visit_ID, `Medicine_ID` = p_Medicine_ID, `Dosage` = NULLIF(p_Dosage, '') WHERE `Prescription_ID` = p_Prescription_ID;
  END IF;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_record_pharmacy_sale` (IN `p_Patient_ID` INT, IN `p_Medicine_ID` INT, IN `p_Quantity` INT, IN `p_User_ID` INT)   BEGIN
  DECLARE v_price DECIMAL(10,2) DEFAULT 0.00;
  DECLARE v_total DECIMAL(10,2) DEFAULT 0.00;
  SELECT `Price` INTO v_price FROM `medicines` WHERE `Medicine_ID` = p_Medicine_ID;
  SET v_total = COALESCE(v_price, 0.00) * COALESCE(p_Quantity, 0);
  INSERT INTO `pharmacy_sales` (`Patient_ID`, `Medicine_ID`, `Quantity`, `Total_Price`, `Sale_Date`, `User_ID`) VALUES (p_Patient_ID, p_Medicine_ID, p_Quantity, v_total, NOW(), NULLIF(p_User_ID, 0));
  UPDATE `medicines` SET `Stock_Quantity` = GREATEST(`Stock_Quantity` - p_Quantity, 0) WHERE `Medicine_ID` = p_Medicine_ID;
  UPDATE `patients` SET `Current_Balance` = `Current_Balance` + v_total WHERE `Patient_ID` = p_Patient_ID;
  SELECT LAST_INSERT_ID() AS `Sale_ID`, v_total AS `Total_Price`;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_roles_delete` (IN `p_Role_ID` INT)   BEGIN
  DELETE FROM `roles` WHERE `Role_ID` = p_Role_ID;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_roles_get` (IN `p_Role_ID` INT)   BEGIN
  SELECT `Role_ID`, `Role_Name` FROM `roles` WHERE `Role_ID` = p_Role_ID;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_roles_list` ()   BEGIN
  SELECT `Role_ID`, `Role_Name` FROM `roles` ORDER BY `Role_ID` DESC;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_roles_save` (IN `p_Role_ID` INT, IN `p_Role_Name` VARCHAR(50))   BEGIN
  IF p_Role_ID IS NULL OR p_Role_ID = 0 THEN
    INSERT INTO `roles` (`Role_Name`) VALUES (p_Role_Name);
  ELSE
    UPDATE `roles` SET `Role_Name` = p_Role_Name WHERE `Role_ID` = p_Role_ID;
  END IF;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_sms_logs_delete` (IN `p_Log_ID` INT)   BEGIN
  DELETE FROM `sms_logs` WHERE `Log_ID` = p_Log_ID;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_sms_logs_get` (IN `p_Log_ID` INT)   BEGIN
  SELECT `Log_ID`, `Patient_ID`, `Message_Body`, `Message_Type`, `Sent_Date` FROM `sms_logs` WHERE `Log_ID` = p_Log_ID;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_sms_logs_list` ()   BEGIN
  SELECT sl.`Log_ID`, sl.`Patient_ID`, p.`Full_Name` AS `Patient_Name`, sl.`Message_Body`, sl.`Message_Type`, sl.`Sent_Date`
  FROM `sms_logs` sl
  LEFT JOIN `patients` p ON p.`Patient_ID` = sl.`Patient_ID`
  ORDER BY sl.`Log_ID` DESC;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_sms_logs_save` (IN `p_Log_ID` INT, IN `p_Patient_ID` INT, IN `p_Message_Body` TEXT, IN `p_Message_Type` VARCHAR(20), IN `p_Sent_Date` VARCHAR(25))   BEGIN
  IF p_Log_ID IS NULL OR p_Log_ID = 0 THEN
    INSERT INTO `sms_logs` (`Patient_ID`, `Message_Body`, `Message_Type`, `Sent_Date`) VALUES (p_Patient_ID, p_Message_Body, p_Message_Type, COALESCE(NULLIF(p_Sent_Date, ''), NOW()));
  ELSE
    UPDATE `sms_logs` SET `Patient_ID` = p_Patient_ID, `Message_Body` = p_Message_Body, `Message_Type` = p_Message_Type, `Sent_Date` = COALESCE(NULLIF(p_Sent_Date, ''), `Sent_Date`) WHERE `Log_ID` = p_Log_ID;
  END IF;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_staff_delete` (IN `p_Staff_ID` INT)   BEGIN
  DELETE FROM `staff` WHERE `Staff_ID` = p_Staff_ID;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_staff_get` (IN `p_Staff_ID` INT)   BEGIN
  SELECT `Staff_ID`, `User_ID`, `Full_Name`, `Phone_Number`, `Credential_Or_Badge`, `Notes`, `status`, `Created_At` FROM `staff` WHERE `Staff_ID` = p_Staff_ID;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_staff_list` ()   BEGIN
  SELECT s.`Staff_ID`, s.`User_ID`, u.`Username`, u.`Role_ID`, r.`Role_Name`, s.`Full_Name`, s.`Phone_Number`,
         s.`Credential_Or_Badge`,
         d.`Doctor_ID`, d.`Specialization`, d.`Consultation_Fee`,
         s.`Notes`, s.`status`, s.`Created_At`
  FROM `staff` s
  INNER JOIN `users` u ON u.`User_ID` = s.`User_ID` AND u.`deleted` = 0
  LEFT JOIN `roles` r ON r.`Role_ID` = u.`Role_ID`
  LEFT JOIN `doctors` d ON d.`User_ID` = s.`User_ID`
  ORDER BY r.`Role_Name` ASC, s.`Full_Name` ASC;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_staff_save` (IN `p_Staff_ID` INT, IN `p_User_ID` INT, IN `p_Full_Name` VARCHAR(100), IN `p_Phone_Number` VARCHAR(50), IN `p_Credential_Or_Badge` VARCHAR(120), IN `p_Notes` TEXT, IN `p_status` VARCHAR(20))   BEGIN
  IF p_User_ID IS NOT NULL AND p_User_ID <> 0 THEN
    IF EXISTS (
      SELECT 1 FROM `staff`
      WHERE `User_ID` = p_User_ID
        AND `Staff_ID` <> COALESCE(NULLIF(p_Staff_ID, 0), -1)
    ) THEN
      SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Hal akoon user ah hal shaqaale ayuu kaliya ku diiwan galmayaa.';
    END IF;
  END IF;
  IF p_Staff_ID IS NULL OR p_Staff_ID = 0 THEN
    INSERT INTO `staff` (`User_ID`, `Full_Name`, `Phone_Number`, `Credential_Or_Badge`, `Notes`, `status`) VALUES (p_User_ID, p_Full_Name, NULLIF(p_Phone_Number, ''), NULLIF(p_Credential_Or_Badge, ''), NULLIF(p_Notes, ''), COALESCE(NULLIF(p_status, ''), 'active'));
  ELSE
    UPDATE `staff` SET `User_ID` = p_User_ID, `Full_Name` = p_Full_Name, `Phone_Number` = NULLIF(p_Phone_Number, ''), `Credential_Or_Badge` = NULLIF(p_Credential_Or_Badge, ''), `Notes` = NULLIF(p_Notes, ''), `status` = COALESCE(NULLIF(p_status, ''), 'active') WHERE `Staff_ID` = p_Staff_ID;
  END IF;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_submenues_delete` (IN `p_submenu_id` INT)   BEGIN
  UPDATE `submenues` SET `deleted` = 1, `status` = 'inactive' WHERE `submenu_id` = p_submenu_id;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_submenues_get` (IN `p_submenu_id` INT)   BEGIN
  SELECT `submenu_id`, `menu_id`, `submenu_name`, `menu_url`, `status`, `sort_order` FROM `submenues` WHERE `submenu_id` = p_submenu_id AND `deleted` = 0;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_submenues_list` ()   BEGIN
  SELECT s.`submenu_id`, s.`menu_id`, m.`menu_name`, s.`submenu_name`, s.`menu_url`, s.`status`, s.`sort_order`
  FROM `submenues` s
  LEFT JOIN `menues` m ON m.`menu_id` = s.`menu_id`
  WHERE s.`deleted` = 0
  ORDER BY s.`menu_id` ASC, s.`sort_order` ASC, s.`submenu_id` DESC;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_submenues_save` (IN `p_submenu_id` INT, IN `p_menu_id` INT, IN `p_submenu_name` VARCHAR(100), IN `p_menu_url` VARCHAR(100), IN `p_status` VARCHAR(20), IN `p_sort_order` INT)   BEGIN
  IF p_submenu_id IS NULL OR p_submenu_id = 0 THEN
    INSERT INTO `submenues` (`menu_id`, `submenu_name`, `menu_url`, `status`, `sort_order`, `deleted`) VALUES (p_menu_id, p_submenu_name, p_menu_url, COALESCE(NULLIF(p_status, ''), 'active'), COALESCE(p_sort_order, 0), 0);
  ELSE
    UPDATE `submenues` SET `menu_id` = p_menu_id, `submenu_name` = p_submenu_name, `menu_url` = p_menu_url, `status` = COALESCE(NULLIF(p_status, ''), 'active'), `sort_order` = COALESCE(p_sort_order, 0) WHERE `submenu_id` = p_submenu_id;
  END IF;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_users_delete` (IN `p_User_ID` INT)   BEGIN
  UPDATE `users` SET `deleted` = 1, `status` = 'inactive' WHERE `User_ID` = p_User_ID;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_users_get` (IN `p_User_ID` INT)   BEGIN
  SELECT `User_ID`, `Username`, `Password_Hash`, `Role_ID`, `email`, `image`, `status`, `last_login` FROM `users` WHERE `User_ID` = p_User_ID AND `deleted` = 0;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_users_list` ()   BEGIN
  SELECT u.`User_ID`, u.`Username`, u.`Password_Hash`, u.`Role_ID`, r.`Role_Name`, u.`email`, u.`image`, u.`status`, u.`last_login`
  FROM `users` u
  LEFT JOIN `roles` r ON r.`Role_ID` = u.`Role_ID`
  WHERE u.`deleted` = 0
  ORDER BY u.`User_ID` DESC;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_users_save` (IN `p_User_ID` INT, IN `p_Username` VARCHAR(50), IN `p_Password_Hash` VARCHAR(255), IN `p_Role_ID` INT, IN `p_email` VARCHAR(100), IN `p_image` VARCHAR(100), IN `p_status` VARCHAR(20))   BEGIN
  IF p_User_ID IS NULL OR p_User_ID = 0 THEN
    INSERT INTO `users` (`Username`, `Password_Hash`, `Role_ID`, `email`, `image`, `status`, `deleted`) VALUES (p_Username, p_Password_Hash, p_Role_ID, NULLIF(p_email, ''), COALESCE(NULLIF(p_image, ''), 'default-user.png'), COALESCE(NULLIF(p_status, ''), 'active'), 0);
  ELSE
    UPDATE `users` SET `Username` = p_Username, `Password_Hash` = p_Password_Hash, `Role_ID` = p_Role_ID, `email` = NULLIF(p_email, ''), `image` = COALESCE(NULLIF(p_image, ''), 'default-user.png'), `status` = COALESCE(NULLIF(p_status, ''), 'active') WHERE `User_ID` = p_User_ID;
  END IF;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_user_privileges_delete` (IN `p_privilege_id` INT)   BEGIN
  DELETE FROM `user_privileges` WHERE `privilege_id` = p_privilege_id;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_user_privileges_get` (IN `p_privilege_id` INT)   BEGIN
  SELECT `privilege_id`, `User_ID`, `submenu_id`, `submenu_name`, `can_view`, `can_insert`, `can_update`, `can_delete` FROM `user_privileges` WHERE `privilege_id` = p_privilege_id;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_user_privileges_list` ()   BEGIN
  SELECT up.`privilege_id`, up.`User_ID`, u.`Username`, up.`submenu_id`, s.`submenu_name`, up.`can_view`, up.`can_insert`, up.`can_update`, up.`can_delete`
  FROM `user_privileges` up
  LEFT JOIN `users` u ON u.`User_ID` = up.`User_ID`
  LEFT JOIN `submenues` s ON s.`submenu_id` = up.`submenu_id`
  ORDER BY up.`privilege_id` DESC;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_user_privileges_save` (IN `p_privilege_id` INT, IN `p_User_ID` INT, IN `p_submenu_id` INT, IN `p_submenu_name` VARCHAR(100), IN `p_can_view` TINYINT, IN `p_can_insert` TINYINT, IN `p_can_update` TINYINT, IN `p_can_delete` TINYINT)   BEGIN
  IF p_privilege_id IS NULL OR p_privilege_id = 0 THEN
    INSERT INTO `user_privileges` (`User_ID`, `submenu_id`, `submenu_name`, `can_view`, `can_insert`, `can_update`, `can_delete`) VALUES (p_User_ID, p_submenu_id, p_submenu_name, COALESCE(p_can_view, 0), COALESCE(p_can_insert, 0), COALESCE(p_can_update, 0), COALESCE(p_can_delete, 0));
  ELSE
    UPDATE `user_privileges` SET `User_ID` = p_User_ID, `submenu_id` = p_submenu_id, `submenu_name` = p_submenu_name, `can_view` = COALESCE(p_can_view, 0), `can_insert` = COALESCE(p_can_insert, 0), `can_update` = COALESCE(p_can_update, 0), `can_delete` = COALESCE(p_can_delete, 0) WHERE `privilege_id` = p_privilege_id;
  END IF;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_visits_delete` (IN `p_Visit_ID` INT)   BEGIN
  DELETE FROM `visits` WHERE `Visit_ID` = p_Visit_ID;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_visits_get` (IN `p_Visit_ID` INT)   BEGIN
  SELECT `Visit_ID`, `Patient_ID`, `Doctor_ID`, `Visit_Date`, `Notes` FROM `visits` WHERE `Visit_ID` = p_Visit_ID;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_visits_list` ()   BEGIN
  SELECT v.`Visit_ID`, v.`Patient_ID`, p.`Full_Name` AS `Patient_Name`, v.`Doctor_ID`, d.`Full_Name` AS `Doctor_Name`, v.`Visit_Date`, v.`Notes`
  FROM `visits` v
  LEFT JOIN `patients` p ON p.`Patient_ID` = v.`Patient_ID`
  LEFT JOIN `doctors` d ON d.`Doctor_ID` = v.`Doctor_ID`
  ORDER BY v.`Visit_ID` DESC;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_visits_save` (IN `p_Visit_ID` INT, IN `p_Patient_ID` INT, IN `p_Doctor_ID` INT, IN `p_Visit_Date` VARCHAR(25), IN `p_Notes` TEXT)   BEGIN
  IF p_Visit_ID IS NULL OR p_Visit_ID = 0 THEN
    INSERT INTO `visits` (`Patient_ID`, `Doctor_ID`, `Visit_Date`, `Notes`) VALUES (p_Patient_ID, p_Doctor_ID, COALESCE(NULLIF(p_Visit_Date, ''), NOW()), NULLIF(p_Notes, ''));
  ELSE
    UPDATE `visits` SET `Patient_ID` = p_Patient_ID, `Doctor_ID` = p_Doctor_ID, `Visit_Date` = COALESCE(NULLIF(p_Visit_Date, ''), `Visit_Date`), `Notes` = NULLIF(p_Notes, '') WHERE `Visit_ID` = p_Visit_ID;
  END IF;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_visit_workspace` (IN `p_Visit_ID` INT)   BEGIN
  SELECT v.`Visit_ID`, v.`Patient_ID`, p.`Full_Name` AS `Patient_Name`, p.`Phone_Number`, p.`Patient_Type`, p.`Current_Balance`, v.`Doctor_ID`, d.`Full_Name` AS `Doctor_Name`, v.`Visit_Date`, v.`Notes`,
    (SELECT COUNT(*) FROM `lab_results` lr WHERE lr.`Visit_ID` = v.`Visit_ID`) AS `lab_count`,
    (SELECT COUNT(*) FROM `prescriptions` pr WHERE pr.`Visit_ID` = v.`Visit_ID`) AS `prescription_count`,
    (SELECT COUNT(*) FROM `nursing_records` nr WHERE nr.`Visit_ID` = v.`Visit_ID`) AS `nursing_count`
  FROM `visits` v
  LEFT JOIN `patients` p ON p.`Patient_ID` = v.`Patient_ID`
  LEFT JOIN `doctors` d ON d.`Doctor_ID` = v.`Doctor_ID`
  WHERE v.`Visit_ID` = p_Visit_ID;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `accounts`
--

CREATE TABLE `accounts` (
  `Account_ID` int(11) NOT NULL,
  `Account_Name` varchar(50) NOT NULL,
  `Current_Balance` decimal(15,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `accounts`
--

INSERT INTO `accounts` (`Account_ID`, `Account_Name`, `Current_Balance`) VALUES
(1, 'EVC Plus (Shirkadda)', 1500.00),
(2, 'Cash Box (Xarunta)', 350.00),
(3, 'Salaam Bank (Doolar)', 5003.50),
(4, 'eDahab', 200.00),
(5, 'dsdxgbf', 5.50);

-- --------------------------------------------------------

--
-- Table structure for table `account_transfers`
--

CREATE TABLE `account_transfers` (
  `Transfer_ID` int(11) NOT NULL,
  `From_Account_ID` int(11) DEFAULT NULL,
  `To_Account_ID` int(11) DEFAULT NULL,
  `Amount` decimal(15,2) NOT NULL,
  `Transfer_Date` datetime DEFAULT current_timestamp(),
  `User_ID` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `account_transfers`
--

INSERT INTO `account_transfers` (`Transfer_ID`, `From_Account_ID`, `To_Account_ID`, `Amount`, `Transfer_Date`, `User_ID`) VALUES
(1, 2, 3, 100.00, '2026-04-24 17:00:00', 1);

-- --------------------------------------------------------

--
-- Table structure for table `appointments`
--

CREATE TABLE `appointments` (
  `Appointment_ID` int(11) NOT NULL,
  `Patient_ID` int(11) DEFAULT NULL,
  `Doctor_ID` int(11) DEFAULT NULL,
  `Appointment_Date` datetime NOT NULL,
  `Status` enum('Pending','Completed','Cancelled') DEFAULT 'Pending',
  `Updated_At` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `appointments`
--

INSERT INTO `appointments` (`Appointment_ID`, `Patient_ID`, `Doctor_ID`, `Appointment_Date`, `Status`, `Updated_At`) VALUES
(1, 4, 1, '2026-04-26 09:00:00', 'Completed', '2026-04-29 15:32:47'),
(2, 5, 2, '2026-04-25 10:30:00', 'Completed', '2026-04-29 07:02:21'),
(3, 1, 3, '2026-04-29 00:13:00', 'Completed', '2026-04-29 08:13:14'),
(4, 8, 3, '2026-04-30 09:25:00', 'Completed', '2026-04-30 14:02:26'),
(5, 9, 3, '2026-04-30 10:09:00', 'Completed', '2026-04-30 07:17:14'),
(6, 10, 3, '2026-05-03 16:19:00', 'Pending', '2026-05-03 13:19:43'),
(7, 11, 4, '2026-05-03 17:31:00', 'Completed', '2026-05-14 08:53:36'),
(8, 12, 1, '2026-05-13 16:38:00', 'Completed', '2026-05-13 12:45:42'),
(9, 13, 1, '2026-05-14 00:54:00', 'Completed', '2026-05-14 08:55:35');

-- --------------------------------------------------------

--
-- Table structure for table `doctors`
--

CREATE TABLE `doctors` (
  `Doctor_ID` int(11) NOT NULL,
  `Full_Name` varchar(100) NOT NULL,
  `Specialization` varchar(100) DEFAULT NULL,
  `Consultation_Fee` decimal(10,2) DEFAULT 0.00,
  `User_ID` int(11) DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `doctors`
--

INSERT INTO `doctors` (`Doctor_ID`, `Full_Name`, `Specialization`, `Consultation_Fee`, `User_ID`, `deleted`) VALUES
(1, 'Dr. Xasan Maxamuud', 'General Practitioner', 10.00, 2, 0),
(2, 'Dr. Faadumo Cali', 'Pediatrics (Caruurta)', 15.00, 3, 0),
(3, 'Dr. Cumar Saciid', 'Internal Medicine', 15.00, NULL, 0),
(4, 'amin', 'cunaha', 7.00, NULL, 0);

-- --------------------------------------------------------

--
-- Table structure for table `lab_results`
--

CREATE TABLE `lab_results` (
  `Result_ID` int(11) NOT NULL,
  `Visit_ID` int(11) DEFAULT NULL,
  `Test_ID` int(11) DEFAULT NULL,
  `Result_Details` text DEFAULT NULL,
  `Status` enum('Pending','Completed') DEFAULT 'Pending',
  `Created_By` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `lab_results`
--

INSERT INTO `lab_results` (`Result_ID`, `Visit_ID`, `Test_ID`, `Result_Details`, `Status`, `Created_By`) VALUES
(1, 1, 1, 'Negative (Duumo ma qabo)', 'Completed', NULL),
(2, 1, 3, 'WBC waa sareeyaa, caabuq baa jira', 'Completed', NULL),
(3, 2, 5, 'Positive (Gaastari wuu leeyahay)', 'Completed', NULL),
(4, 3, 5, 'Result: Positive', 'Completed', NULL),
(17, 4, 1, 'Result: Positive', 'Completed', NULL),
(18, 4, 2, 'Result: Positive', 'Completed', NULL),
(19, 4, 3, 'Result: Positive', 'Completed', NULL),
(20, 4, 4, 'Result: Positive', 'Completed', NULL),
(21, 4, 5, 'Result: Negative', 'Completed', NULL),
(22, 5, 1, 'Result: Positive', 'Completed', NULL),
(23, 5, 2, 'Result: Positive', 'Completed', NULL),
(24, 5, 3, 'Result: Positive', 'Completed', NULL),
(25, 5, 4, 'Result: Positive', 'Completed', NULL),
(26, 5, 5, 'Result: Positive', 'Completed', NULL),
(27, 5, 6, 'Result: Positive', 'Completed', NULL),
(28, 7, 4, NULL, 'Pending', NULL),
(29, 7, 5, NULL, 'Pending', NULL),
(30, 7, 6, NULL, 'Pending', NULL),
(31, 11, 1, NULL, 'Pending', NULL),
(32, 11, 2, NULL, 'Pending', NULL),
(33, 11, 3, NULL, 'Pending', NULL),
(34, 11, 4, NULL, 'Pending', NULL),
(35, 11, 5, NULL, 'Pending', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `lab_tests`
--

CREATE TABLE `lab_tests` (
  `Test_ID` int(11) NOT NULL,
  `Test_Name` varchar(100) NOT NULL,
  `Price` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `lab_tests`
--

INSERT INTO `lab_tests` (`Test_ID`, `Test_Name`, `Price`) VALUES
(1, 'Malaria RDT', 3.00),
(2, 'Widal Test (Typhoid)', 5.00),
(3, 'CBC (Dhiigga Guud)', 8.00),
(4, 'Blood Sugar (Sokorta)', 2.00),
(5, 'H. Pylori (Gaastari)', 6.00),
(6, 'Urinalysis (Kaadida)', 4.00);

-- --------------------------------------------------------

--
-- Table structure for table `medicines`
--

CREATE TABLE `medicines` (
  `Medicine_ID` int(11) NOT NULL,
  `Medicine_Name` varchar(100) NOT NULL,
  `Price` decimal(10,2) NOT NULL,
  `Stock_Quantity` int(11) NOT NULL DEFAULT 0,
  `Expiry_Date` date DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `medicines`
--

INSERT INTO `medicines` (`Medicine_ID`, `Medicine_Name`, `Price`, `Stock_Quantity`, `Expiry_Date`, `deleted`) VALUES
(1, 'Paracetamol 500mg (Kaniini)', 1.00, 499, '2027-05-10', 0),
(2, 'Amoxicillin 250mg (Kabsal)', 2.50, 200, '2026-12-01', 0),
(3, 'Omeprazole 20mg (Gaastari)', 3.00, 149, '2026-08-15', 0),
(4, 'ORS (Cusbo Biyood)', 0.50, 298, '2028-01-01', 0),
(5, 'Ciprofloxacin 500mg', 4.00, 99, '2026-11-20', 0),
(6, 'Normal Saline (Faleebo)', 2.00, 50, '2027-02-28', 0);

-- --------------------------------------------------------

--
-- Table structure for table `menues`
--

CREATE TABLE `menues` (
  `menu_id` int(11) NOT NULL,
  `menu_name` varchar(100) NOT NULL,
  `icon` varchar(50) DEFAULT NULL,
  `menu_group` varchar(50) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `sort_order` int(11) DEFAULT 0,
  `deleted` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `menues`
--

INSERT INTO `menues` (`menu_id`, `menu_name`, `icon`, `menu_group`, `status`, `sort_order`, `deleted`) VALUES
(1, 'Dashboard', 'ti-layout-dashboard', 'Command', 'active', 1, 0),
(2, 'Patients & Appointments', 'ti-user-heart', 'Clinic Workflow', 'active', 2, 0),
(3, 'Nursing', 'ti-stethoscope', 'Admin & Setup', 'active', 3, 0),
(4, 'Laboratory', 'ti-microscope', 'Clinic Workflow', 'active', 4, 0),
(5, 'Pharmacy', 'ti-medicine', 'Clinic Workflow', 'active', 5, 0),
(6, 'Finance', 'ti-wallet', 'Admin & Setup', 'active', 6, 0),
(7, 'Admin & Staff', 'ti-settings', 'Admin & Setup', 'active', 7, 0),
(8, 'Reports', 'ti-chart-bar', 'Reports', 'inactive', 8, 1),
(9, 'ASDFGHJKL;', NULL, 'ASDSDDSFDF', 'active', 9, 1),
(10, 'Warbixinada (Reports)', 'ti-chart-bar', 'Reports', 'active', 8, 0);

-- --------------------------------------------------------

--
-- Table structure for table `nursing_records`
--

CREATE TABLE `nursing_records` (
  `Record_ID` int(11) NOT NULL,
  `Visit_ID` int(11) DEFAULT NULL,
  `Service_ID` int(11) DEFAULT NULL,
  `Medicine_Used` varchar(100) DEFAULT NULL,
  `Administered_By` int(11) DEFAULT NULL,
  `Record_Date` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `nursing_records`
--

INSERT INTO `nursing_records` (`Record_ID`, `Visit_ID`, `Service_ID`, `Medicine_Used`, `Administered_By`, `Record_Date`) VALUES
(1, 3, 1, 'Diclofenac Ampoule (Wuu la yimid)', 6, '2026-04-25 11:10:00'),
(2, 1, 5, NULL, 6, '2026-04-25 08:35:00'),
(3, 7, 5, NULL, 1, '2026-05-03 16:07:11'),
(4, 2, 5, NULL, 2, '2026-05-14 12:02:02');

-- --------------------------------------------------------

--
-- Table structure for table `nursing_services`
--

CREATE TABLE `nursing_services` (
  `Service_ID` int(11) NOT NULL,
  `Service_Name` varchar(100) NOT NULL,
  `Price` decimal(10,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `nursing_services`
--

INSERT INTO `nursing_services` (`Service_ID`, `Service_Name`, `Price`) VALUES
(1, 'Duriin Muruq (IM)', 1.00),
(2, 'Duriin Xidid (IV)', 1.50),
(3, 'Faleebo Xirid', 2.00),
(4, 'Dhaawac Dhayid (Dressing)', 5.00),
(5, 'Cabirka Dhiig-Karka (BP)', 0.00);

-- --------------------------------------------------------

--
-- Table structure for table `patients`
--

CREATE TABLE `patients` (
  `Patient_ID` int(11) NOT NULL,
  `Full_Name` varchar(100) NOT NULL,
  `Phone_Number` varchar(20) DEFAULT NULL,
  `Sex` enum('Male','Female') DEFAULT 'Male',
  `Age_Group` enum('Child','Adult') DEFAULT 'Adult',
  `Patient_Type` enum('Bille','Maalinle') DEFAULT 'Maalinle',
  `Guarantor_ID` int(11) DEFAULT NULL,
  `Relationship` enum('Self','Child','Spouse','Other') DEFAULT 'Self',
  `Credit_Limit` decimal(10,2) DEFAULT 0.00,
  `Current_Balance` decimal(10,2) DEFAULT 0.00,
  `Created_At` timestamp NOT NULL DEFAULT current_timestamp(),
  `deleted` tinyint(1) DEFAULT 0,
  `Updated_At` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `patients`
--

INSERT INTO `patients` (`Patient_ID`, `Full_Name`, `Phone_Number`, `Sex`, `Age_Group`, `Patient_Type`, `Guarantor_ID`, `Relationship`, `Credit_Limit`, `Current_Balance`, `Created_At`, `deleted`, `Updated_At`) VALUES
(1, 'Cali Xuseen', '0615112233', 'Male', 'Adult', 'Bille', NULL, 'Self', 100.00, 15.00, '2026-04-25 13:13:46', 0, '2026-04-29 07:02:21'),
(2, 'Axmed Cali Xuseen', NULL, 'Male', 'Adult', 'Bille', 1, 'Child', 0.00, 0.00, '2026-04-25 13:13:46', 0, '2026-04-29 07:02:21'),
(3, 'Canab Daahir', '0616998877', 'Male', 'Adult', 'Bille', 1, 'Spouse', 0.00, 0.00, '2026-04-25 13:13:46', 0, '2026-04-29 07:02:21'),
(4, 'Xaawo Maxamed', '0612334455', 'Male', 'Adult', 'Maalinle', NULL, 'Self', 0.00, 0.00, '2026-04-25 13:13:46', 0, '2026-04-29 07:02:21'),
(5, 'Nuur Jaamac', '0619776655', 'Male', 'Adult', 'Maalinle', NULL, 'Self', 0.00, 5.00, '2026-04-25 13:13:46', 0, '2026-04-29 09:09:49'),
(6, 'amin', '8765643', 'Male', 'Adult', 'Bille', 2, 'Self', 1000.00, 0.00, '2026-04-28 11:03:19', 0, '2026-04-29 07:02:21'),
(7, 'tijaabo', '0612803434', 'Male', 'Adult', 'Maalinle', 6, 'Self', 0.00, 0.00, '2026-04-28 13:39:52', 0, '2026-04-30 06:45:10'),
(8, 'nawaaf', '618123132', 'Male', 'Adult', 'Maalinle', NULL, 'Self', 0.00, 0.00, '2026-04-30 06:25:13', 0, '2026-04-30 06:25:13'),
(9, 'nawaaf amed', '2324646769978', 'Male', 'Child', 'Maalinle', NULL, 'Self', 0.00, 0.00, '2026-04-30 07:09:30', 0, '2026-04-30 07:09:30'),
(10, 'hasan', '213456768', 'Male', 'Adult', 'Maalinle', NULL, 'Self', 0.00, 0.00, '2026-05-03 13:19:43', 0, '2026-05-03 13:19:43'),
(11, 'asdfghjkl', NULL, 'Male', 'Adult', 'Maalinle', NULL, 'Self', 0.00, 0.00, '2026-05-03 14:31:13', 0, '2026-05-03 14:31:13'),
(12, 'adan', '615665577', 'Male', 'Adult', 'Maalinle', NULL, 'Self', 0.00, 0.00, '2026-05-13 12:38:35', 0, '2026-05-13 12:38:35'),
(13, 'muuse', '5745673463', 'Male', 'Adult', 'Maalinle', NULL, 'Self', 0.00, 0.00, '2026-05-14 08:54:28', 0, '2026-05-14 08:54:28');

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `Payment_ID` int(11) NOT NULL,
  `Patient_ID` int(11) DEFAULT NULL,
  `Account_ID` int(11) DEFAULT NULL,
  `Amount` decimal(10,2) NOT NULL,
  `Payment_Method` enum('EVC Plus','eDahab','Cash','Bank') NOT NULL,
  `Transaction_Ref` varchar(50) DEFAULT NULL,
  `Payment_Date` datetime DEFAULT current_timestamp(),
  `User_ID` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`Payment_ID`, `Patient_ID`, `Account_ID`, `Amount`, `Payment_Method`, `Transaction_Ref`, `Payment_Date`, `User_ID`) VALUES
(1, 4, 1, 10.00, 'EVC Plus', 'M123456789', '2026-04-25 09:20:00', 4),
(2, 4, 2, 3.00, 'Cash', NULL, '2026-04-25 09:45:00', 4),
(3, 5, 1, 1.00, 'EVC Plus', 'M987654321', '2026-04-25 11:15:00', 4),
(4, 5, 3, 3.50, 'Cash', 'PHARMACY-POS', '2026-04-29 11:54:53', 1),
(5, 5, 5, 4.00, 'Cash', 'PHARMACY-POS', '2026-04-29 12:09:31', 1),
(6, 5, 5, 0.50, 'Cash', 'PHARMACY-POS', '2026-04-29 12:09:43', 1),
(7, 5, 5, 1.00, 'Cash', 'PHARMACY-POS', '2026-04-29 12:09:49', 1);

-- --------------------------------------------------------

--
-- Table structure for table `pharmacy_sales`
--

CREATE TABLE `pharmacy_sales` (
  `Sale_ID` int(11) NOT NULL,
  `Patient_ID` int(11) DEFAULT NULL,
  `Medicine_ID` int(11) DEFAULT NULL,
  `Quantity` int(11) NOT NULL,
  `Total_Price` decimal(10,2) NOT NULL,
  `Sale_Date` datetime DEFAULT current_timestamp(),
  `User_ID` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pharmacy_sales`
--

INSERT INTO `pharmacy_sales` (`Sale_ID`, `Patient_ID`, `Medicine_ID`, `Quantity`, `Total_Price`, `Sale_Date`, `User_ID`) VALUES
(1, 1, 1, 15, 1.00, '2026-04-25 09:00:00', 5),
(2, 1, 2, 10, 2.50, '2026-04-25 09:00:00', 5),
(3, 4, 3, 14, 3.00, '2026-04-25 09:45:00', 5),
(4, 5, 3, 1, 3.00, '2026-04-29 11:54:53', 1),
(5, 5, 4, 1, 0.50, '2026-04-29 11:54:53', 1),
(6, 5, 5, 1, 4.00, '2026-04-29 12:09:31', 1),
(7, 5, 4, 1, 0.50, '2026-04-29 12:09:43', 1),
(8, 5, 1, 1, 1.00, '2026-04-29 12:09:49', 1);

-- --------------------------------------------------------

--
-- Table structure for table `prescriptions`
--

CREATE TABLE `prescriptions` (
  `Prescription_ID` int(11) NOT NULL,
  `Visit_ID` int(11) DEFAULT NULL,
  `Medicine_ID` int(11) DEFAULT NULL,
  `Dosage` varchar(100) DEFAULT NULL,
  `Created_By` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `prescriptions`
--

INSERT INTO `prescriptions` (`Prescription_ID`, `Visit_ID`, `Medicine_ID`, `Dosage`, `Created_By`) VALUES
(1, 1, 1, '1 Kaniini x 3 jeer maalintii (5 cisho)', NULL),
(2, 1, 2, '1 Kabsal x 2 jeer maalintii (5 cisho)', NULL),
(3, 2, 3, '1 Kaniini subax kasta caloosha oo maran (14 cisho)', NULL),
(4, 4, 1, NULL, NULL),
(5, 4, 2, NULL, NULL),
(6, 4, 3, NULL, NULL),
(7, 4, 4, '1x3', NULL),
(8, 4, 6, '1x2', NULL),
(9, 5, 4, 'tohkbn', NULL),
(10, 5, 5, 'ygvbkjb', NULL),
(11, 5, 6, 'hgvjbhkjbm', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `Role_ID` int(11) NOT NULL,
  `Role_Name` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`Role_ID`, `Role_Name`) VALUES
(1, 'Admin'),
(2, 'Doctor'),
(3, 'Receptionist'),
(4, 'Pharmacist'),
(5, 'Lab Technician'),
(6, 'Nurse');

-- --------------------------------------------------------

--
-- Table structure for table `sms_logs`
--

CREATE TABLE `sms_logs` (
  `Log_ID` int(11) NOT NULL,
  `Patient_ID` int(11) DEFAULT NULL,
  `Message_Body` text NOT NULL,
  `Message_Type` enum('Bill Reminder','Lab Result','General') NOT NULL,
  `Sent_Date` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sms_logs`
--

INSERT INTO `sms_logs` (`Log_ID`, `Patient_ID`, `Message_Body`, `Message_Type`, `Sent_Date`) VALUES
(1, 1, 'Mudane Cali, fadlan iska bixi haraadiga lagugu leeyahay oo ah $15.00', 'Bill Reminder', '2026-04-25 12:00:00'),
(2, 4, 'Natiijadii sheybaarkaaga waa diyaar, fadlan xarunta imaw.', 'Lab Result', '2026-04-25 09:30:00');

-- --------------------------------------------------------

--
-- Table structure for table `staff`
--

CREATE TABLE `staff` (
  `Staff_ID` int(11) NOT NULL,
  `User_ID` int(11) NOT NULL,
  `Full_Name` varchar(100) NOT NULL,
  `Phone_Number` varchar(50) DEFAULT NULL,
  `Credential_Or_Badge` varchar(120) DEFAULT NULL COMMENT 'Liisan / aqoonsi',
  `Notes` text DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `Created_At` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `submenues`
--

CREATE TABLE `submenues` (
  `submenu_id` int(11) NOT NULL,
  `menu_id` int(11) NOT NULL,
  `submenu_name` varchar(100) NOT NULL,
  `menu_url` varchar(100) NOT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `sort_order` int(11) DEFAULT 0,
  `deleted` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `submenues`
--

INSERT INTO `submenues` (`submenu_id`, `menu_id`, `submenu_name`, `menu_url`, `status`, `sort_order`, `deleted`) VALUES
(1, 1, 'Dashboard', 'index.php', 'active', 1, 0),
(2, 2, 'Patient Desk', 'pages/patients.php', 'active', 1, 0),
(3, 2, 'Appointment Board', 'appointments.php', 'active', 2, 0),
(4, 2, 'Visit Workspace', 'visits.php', 'active', 3, 0),
(5, 2, 'Doctors', 'doctors.php', 'active', 4, 0),
(6, 3, 'Nursing Records', 'nursing_records.php', 'active', 1, 0),
(7, 3, 'Nursing Services', 'nursing_services.php', 'active', 2, 0),
(8, 4, 'Lab Queue', 'lab_results.php', 'active', 1, 0),
(9, 4, 'Lab Tests', 'lab_tests.php', 'active', 2, 0),
(10, 5, 'Pharmacy POS', 'pharmacy_sales.php', 'active', 1, 0),
(11, 5, 'Prescriptions', 'prescriptions.php', 'active', 2, 0),
(12, 5, 'Medicines', 'medicines.php', 'active', 3, 0),
(13, 6, 'Payment Desk', 'payments.php', 'active', 1, 0),
(14, 6, 'Accounts', 'pages/accounts.php', 'active', 2, 0),
(15, 6, 'Account Transfers', 'account_transfers.php', 'active', 3, 0),
(16, 7, 'Shaqaale (Staff hub)', 'pages/staff.php', 'active', 1, 0),
(17, 7, 'Roles', 'roles.php', 'active', 3, 0),
(18, 7, 'User Privileges', 'privileges.php', 'active', 4, 0),
(19, 7, 'SMS Logs', 'sms_logs.php', 'active', 5, 0),
(20, 8, 'Finance Report', 'report_finance.php', 'active', 1, 0),
(21, 8, 'Lab Report', 'report_lab.php', 'active', 2, 0),
(22, 8, 'Pharmacy Report', 'report_pharmacy.php', 'active', 3, 0),
(23, 7, 'Menus', 'menues.php', 'active', 6, 0),
(24, 7, 'Submenus', 'menues.php?tab=sub', 'active', 7, 0),
(25, 9, 'Fixed Assets', 'fixed_assets.php', 'active', 1, 0),
(26, 9, 'Fixed Assetsgdgfhjkl', 'fixed_assets.php', 'active', 1, 0),
(27, 6, 'accounts', 'pages/accounts.php', 'active', 4, 0),
(28, 7, 'Users (akoonno buuxa)', 'pages/users.php', 'active', 2, 0),
(29, 10, 'Daymaha Bukaanka', 'pages/reports.php', 'active', 1, 0);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `User_ID` int(11) NOT NULL,
  `Username` varchar(50) NOT NULL,
  `Password_Hash` varchar(255) NOT NULL,
  `Role_ID` int(11) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `image` varchar(100) DEFAULT 'default-user.png',
  `status` enum('active','inactive') DEFAULT 'active',
  `deleted` tinyint(1) DEFAULT 0,
  `last_login` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`User_ID`, `Username`, `Password_Hash`, `Role_ID`, `email`, `image`, `status`, `deleted`, `last_login`) VALUES
(1, 'maamule', 'hashed_pass_123', 1, NULL, 'default-user.png', 'active', 0, '2026-05-13 15:35:42'),
(2, 'dr_xasan', '1234', 2, NULL, 'default-user.png', 'active', 0, '2026-05-13 15:41:18'),
(3, 'dr_faadumo', '12345', 2, NULL, 'default-user.png', 'active', 0, NULL),
(4, 'hodan_rec', 'hashed_pass_123', 3, NULL, 'default-user.png', 'active', 0, NULL),
(5, 'jaamac_pharmacy', 'hashed_pass_123', 4, NULL, 'default-user.png', 'active', 0, NULL),
(6, 'kaltuun_nurse', 'hashed_pass_123', 6, NULL, 'default-user.png', 'active', 0, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `user_privileges`
--

CREATE TABLE `user_privileges` (
  `privilege_id` int(11) NOT NULL,
  `User_ID` int(11) NOT NULL,
  `submenu_id` int(11) NOT NULL,
  `submenu_name` varchar(100) NOT NULL,
  `can_view` tinyint(1) DEFAULT 0,
  `can_insert` tinyint(1) DEFAULT 0,
  `can_update` tinyint(1) DEFAULT 0,
  `can_delete` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_privileges`
--

INSERT INTO `user_privileges` (`privilege_id`, `User_ID`, `submenu_id`, `submenu_name`, `can_view`, `can_insert`, `can_update`, `can_delete`) VALUES
(1, 1, 2, 'Diiwaanka Bukaanka', 1, 1, 1, 1);

-- --------------------------------------------------------

--
-- Table structure for table `visits`
--

CREATE TABLE `visits` (
  `Visit_ID` int(11) NOT NULL,
  `Patient_ID` int(11) DEFAULT NULL,
  `Doctor_ID` int(11) DEFAULT NULL,
  `Visit_Date` datetime DEFAULT current_timestamp(),
  `Notes` text DEFAULT NULL,
  `Created_By` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `visits`
--

INSERT INTO `visits` (`Visit_ID`, `Patient_ID`, `Doctor_ID`, `Visit_Date`, `Notes`, `Created_By`) VALUES
(1, 2, 2, '2026-04-25 08:30:00', 'Wiilka xandho iyo matag ayuu leeyahay 2 maalin.', NULL),
(2, 4, 1, '2026-04-25 09:15:00', 'Bukaanka madax xanuun iyo daal ayay ka cabanaysaa.', NULL),
(3, 5, NULL, '2026-04-25 11:00:00', 'Bukaanku wuxuu u yimid duriin kaliya.', NULL),
(4, 7, 3, '2026-04-29 13:32:09', NULL, NULL),
(5, 9, NULL, '2026-04-30 10:17:14', 'cunaha aa laga haayaa', NULL),
(6, 8, 2, '2026-04-30 17:01:16', 'fgfgf', NULL),
(7, 9, NULL, '2026-05-02 15:13:15', NULL, NULL),
(8, 11, 4, '2026-05-03 17:31:42', NULL, NULL),
(9, 12, NULL, '2026-05-13 15:44:19', 'xbkghvn', NULL),
(10, 12, 1, '2026-05-13 15:45:42', 'amiin', NULL),
(11, 13, 1, '2026-05-14 11:57:04', 'ma caafimaad qabtaa mise malabaani aa cuntay', NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `accounts`
--
ALTER TABLE `accounts`
  ADD PRIMARY KEY (`Account_ID`);

--
-- Indexes for table `account_transfers`
--
ALTER TABLE `account_transfers`
  ADD PRIMARY KEY (`Transfer_ID`),
  ADD KEY `From_Account_ID` (`From_Account_ID`),
  ADD KEY `To_Account_ID` (`To_Account_ID`),
  ADD KEY `User_ID` (`User_ID`);

--
-- Indexes for table `appointments`
--
ALTER TABLE `appointments`
  ADD PRIMARY KEY (`Appointment_ID`),
  ADD KEY `Patient_ID` (`Patient_ID`),
  ADD KEY `Doctor_ID` (`Doctor_ID`),
  ADD KEY `idx_appointment_date` (`Appointment_Date`);

--
-- Indexes for table `doctors`
--
ALTER TABLE `doctors`
  ADD PRIMARY KEY (`Doctor_ID`),
  ADD KEY `User_ID` (`User_ID`);

--
-- Indexes for table `lab_results`
--
ALTER TABLE `lab_results`
  ADD PRIMARY KEY (`Result_ID`),
  ADD KEY `Visit_ID` (`Visit_ID`),
  ADD KEY `Test_ID` (`Test_ID`),
  ADD KEY `fk_lab_user` (`Created_By`);

--
-- Indexes for table `lab_tests`
--
ALTER TABLE `lab_tests`
  ADD PRIMARY KEY (`Test_ID`);

--
-- Indexes for table `medicines`
--
ALTER TABLE `medicines`
  ADD PRIMARY KEY (`Medicine_ID`);

--
-- Indexes for table `menues`
--
ALTER TABLE `menues`
  ADD PRIMARY KEY (`menu_id`);

--
-- Indexes for table `nursing_records`
--
ALTER TABLE `nursing_records`
  ADD PRIMARY KEY (`Record_ID`),
  ADD KEY `Visit_ID` (`Visit_ID`),
  ADD KEY `Service_ID` (`Service_ID`),
  ADD KEY `Administered_By` (`Administered_By`);

--
-- Indexes for table `nursing_services`
--
ALTER TABLE `nursing_services`
  ADD PRIMARY KEY (`Service_ID`);

--
-- Indexes for table `patients`
--
ALTER TABLE `patients`
  ADD PRIMARY KEY (`Patient_ID`),
  ADD UNIQUE KEY `Phone_Number` (`Phone_Number`),
  ADD KEY `Guarantor_ID` (`Guarantor_ID`),
  ADD KEY `idx_patient_name` (`Full_Name`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`Payment_ID`),
  ADD KEY `Patient_ID` (`Patient_ID`),
  ADD KEY `Account_ID` (`Account_ID`),
  ADD KEY `User_ID` (`User_ID`);

--
-- Indexes for table `pharmacy_sales`
--
ALTER TABLE `pharmacy_sales`
  ADD PRIMARY KEY (`Sale_ID`),
  ADD KEY `Patient_ID` (`Patient_ID`),
  ADD KEY `Medicine_ID` (`Medicine_ID`),
  ADD KEY `User_ID` (`User_ID`);

--
-- Indexes for table `prescriptions`
--
ALTER TABLE `prescriptions`
  ADD PRIMARY KEY (`Prescription_ID`),
  ADD KEY `Visit_ID` (`Visit_ID`),
  ADD KEY `Medicine_ID` (`Medicine_ID`),
  ADD KEY `fk_presc_user` (`Created_By`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`Role_ID`);

--
-- Indexes for table `sms_logs`
--
ALTER TABLE `sms_logs`
  ADD PRIMARY KEY (`Log_ID`),
  ADD KEY `Patient_ID` (`Patient_ID`);

--
-- Indexes for table `staff`
--
ALTER TABLE `staff`
  ADD PRIMARY KEY (`Staff_ID`),
  ADD UNIQUE KEY `ux_staff_user_id` (`User_ID`);

--
-- Indexes for table `submenues`
--
ALTER TABLE `submenues`
  ADD PRIMARY KEY (`submenu_id`),
  ADD KEY `menu_id` (`menu_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`User_ID`),
  ADD UNIQUE KEY `Username` (`Username`),
  ADD KEY `Role_ID` (`Role_ID`);

--
-- Indexes for table `user_privileges`
--
ALTER TABLE `user_privileges`
  ADD PRIMARY KEY (`privilege_id`),
  ADD KEY `User_ID` (`User_ID`),
  ADD KEY `submenu_id` (`submenu_id`);

--
-- Indexes for table `visits`
--
ALTER TABLE `visits`
  ADD PRIMARY KEY (`Visit_ID`),
  ADD KEY `Patient_ID` (`Patient_ID`),
  ADD KEY `Doctor_ID` (`Doctor_ID`),
  ADD KEY `fk_visits_user` (`Created_By`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `accounts`
--
ALTER TABLE `accounts`
  MODIFY `Account_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `account_transfers`
--
ALTER TABLE `account_transfers`
  MODIFY `Transfer_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `appointments`
--
ALTER TABLE `appointments`
  MODIFY `Appointment_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `doctors`
--
ALTER TABLE `doctors`
  MODIFY `Doctor_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `lab_results`
--
ALTER TABLE `lab_results`
  MODIFY `Result_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- AUTO_INCREMENT for table `lab_tests`
--
ALTER TABLE `lab_tests`
  MODIFY `Test_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `medicines`
--
ALTER TABLE `medicines`
  MODIFY `Medicine_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `menues`
--
ALTER TABLE `menues`
  MODIFY `menu_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `nursing_records`
--
ALTER TABLE `nursing_records`
  MODIFY `Record_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `nursing_services`
--
ALTER TABLE `nursing_services`
  MODIFY `Service_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `patients`
--
ALTER TABLE `patients`
  MODIFY `Patient_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `Payment_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `pharmacy_sales`
--
ALTER TABLE `pharmacy_sales`
  MODIFY `Sale_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `prescriptions`
--
ALTER TABLE `prescriptions`
  MODIFY `Prescription_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `Role_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `sms_logs`
--
ALTER TABLE `sms_logs`
  MODIFY `Log_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `staff`
--
ALTER TABLE `staff`
  MODIFY `Staff_ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `submenues`
--
ALTER TABLE `submenues`
  MODIFY `submenu_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `User_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `user_privileges`
--
ALTER TABLE `user_privileges`
  MODIFY `privilege_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `visits`
--
ALTER TABLE `visits`
  MODIFY `Visit_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `account_transfers`
--
ALTER TABLE `account_transfers`
  ADD CONSTRAINT `account_transfers_ibfk_1` FOREIGN KEY (`From_Account_ID`) REFERENCES `accounts` (`Account_ID`),
  ADD CONSTRAINT `account_transfers_ibfk_2` FOREIGN KEY (`To_Account_ID`) REFERENCES `accounts` (`Account_ID`),
  ADD CONSTRAINT `account_transfers_ibfk_3` FOREIGN KEY (`User_ID`) REFERENCES `users` (`User_ID`);

--
-- Constraints for table `appointments`
--
ALTER TABLE `appointments`
  ADD CONSTRAINT `appointments_ibfk_1` FOREIGN KEY (`Patient_ID`) REFERENCES `patients` (`Patient_ID`),
  ADD CONSTRAINT `appointments_ibfk_2` FOREIGN KEY (`Doctor_ID`) REFERENCES `doctors` (`Doctor_ID`);

--
-- Constraints for table `doctors`
--
ALTER TABLE `doctors`
  ADD CONSTRAINT `doctors_ibfk_1` FOREIGN KEY (`User_ID`) REFERENCES `users` (`User_ID`);

--
-- Constraints for table `lab_results`
--
ALTER TABLE `lab_results`
  ADD CONSTRAINT `fk_lab_user` FOREIGN KEY (`Created_By`) REFERENCES `users` (`User_ID`),
  ADD CONSTRAINT `lab_results_ibfk_1` FOREIGN KEY (`Visit_ID`) REFERENCES `visits` (`Visit_ID`),
  ADD CONSTRAINT `lab_results_ibfk_2` FOREIGN KEY (`Test_ID`) REFERENCES `lab_tests` (`Test_ID`);

--
-- Constraints for table `nursing_records`
--
ALTER TABLE `nursing_records`
  ADD CONSTRAINT `nursing_records_ibfk_1` FOREIGN KEY (`Visit_ID`) REFERENCES `visits` (`Visit_ID`),
  ADD CONSTRAINT `nursing_records_ibfk_2` FOREIGN KEY (`Service_ID`) REFERENCES `nursing_services` (`Service_ID`),
  ADD CONSTRAINT `nursing_records_ibfk_3` FOREIGN KEY (`Administered_By`) REFERENCES `users` (`User_ID`);

--
-- Constraints for table `patients`
--
ALTER TABLE `patients`
  ADD CONSTRAINT `patients_ibfk_1` FOREIGN KEY (`Guarantor_ID`) REFERENCES `patients` (`Patient_ID`);

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`Patient_ID`) REFERENCES `patients` (`Patient_ID`),
  ADD CONSTRAINT `payments_ibfk_2` FOREIGN KEY (`Account_ID`) REFERENCES `accounts` (`Account_ID`),
  ADD CONSTRAINT `payments_ibfk_3` FOREIGN KEY (`User_ID`) REFERENCES `users` (`User_ID`);

--
-- Constraints for table `pharmacy_sales`
--
ALTER TABLE `pharmacy_sales`
  ADD CONSTRAINT `pharmacy_sales_ibfk_1` FOREIGN KEY (`Patient_ID`) REFERENCES `patients` (`Patient_ID`),
  ADD CONSTRAINT `pharmacy_sales_ibfk_2` FOREIGN KEY (`Medicine_ID`) REFERENCES `medicines` (`Medicine_ID`),
  ADD CONSTRAINT `pharmacy_sales_ibfk_3` FOREIGN KEY (`User_ID`) REFERENCES `users` (`User_ID`);

--
-- Constraints for table `prescriptions`
--
ALTER TABLE `prescriptions`
  ADD CONSTRAINT `fk_presc_user` FOREIGN KEY (`Created_By`) REFERENCES `users` (`User_ID`),
  ADD CONSTRAINT `prescriptions_ibfk_1` FOREIGN KEY (`Visit_ID`) REFERENCES `visits` (`Visit_ID`),
  ADD CONSTRAINT `prescriptions_ibfk_2` FOREIGN KEY (`Medicine_ID`) REFERENCES `medicines` (`Medicine_ID`);

--
-- Constraints for table `sms_logs`
--
ALTER TABLE `sms_logs`
  ADD CONSTRAINT `sms_logs_ibfk_1` FOREIGN KEY (`Patient_ID`) REFERENCES `patients` (`Patient_ID`);

--
-- Constraints for table `staff`
--
ALTER TABLE `staff`
  ADD CONSTRAINT `staff_ibfk_1` FOREIGN KEY (`User_ID`) REFERENCES `users` (`User_ID`);

--
-- Constraints for table `submenues`
--
ALTER TABLE `submenues`
  ADD CONSTRAINT `submenues_ibfk_1` FOREIGN KEY (`menu_id`) REFERENCES `menues` (`menu_id`);

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`Role_ID`) REFERENCES `roles` (`Role_ID`);

--
-- Constraints for table `user_privileges`
--
ALTER TABLE `user_privileges`
  ADD CONSTRAINT `user_privileges_ibfk_1` FOREIGN KEY (`User_ID`) REFERENCES `users` (`User_ID`),
  ADD CONSTRAINT `user_privileges_ibfk_2` FOREIGN KEY (`submenu_id`) REFERENCES `submenues` (`submenu_id`);

--
-- Constraints for table `visits`
--
ALTER TABLE `visits`
  ADD CONSTRAINT `fk_visits_user` FOREIGN KEY (`Created_By`) REFERENCES `users` (`User_ID`),
  ADD CONSTRAINT `visits_ibfk_1` FOREIGN KEY (`Patient_ID`) REFERENCES `patients` (`Patient_ID`),
  ADD CONSTRAINT `visits_ibfk_2` FOREIGN KEY (`Doctor_ID`) REFERENCES `doctors` (`Doctor_ID`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
