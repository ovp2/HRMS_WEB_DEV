-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: hrms
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `attendance`
--

DROP TABLE IF EXISTS `attendance`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `attendance` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `check_in` time DEFAULT NULL,
  `check_out` time DEFAULT NULL,
  `status` enum('present','absent','half-day','late','on-leave') NOT NULL DEFAULT 'present',
  `work_hours` decimal(4,2) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_employee_date` (`employee_id`,`date`),
  KEY `idx_date` (`date`),
  CONSTRAINT `fk_attendance_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=51 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `attendance`
--

LOCK TABLES `attendance` WRITE;
/*!40000 ALTER TABLE `attendance` DISABLE KEYS */;
INSERT INTO `attendance` VALUES (1,1,'2026-09-25','09:00:00','18:00:00','present',9.00,'On-time shift','2026-09-29 11:47:41','2026-09-29 11:47:41'),(2,1,'2026-09-26','09:12:00','18:15:00','present',9.05,'Regular shift','2026-09-29 11:47:41','2026-09-29 11:47:41'),(3,1,'2026-09-27','09:35:00','18:40:00','late',9.08,'Traffic on SG Highway','2026-09-29 11:47:41','2026-09-29 11:47:41'),(4,1,'2026-09-28','09:05:00','18:10:00','present',9.08,'Regular shift','2026-09-29 11:47:41','2026-09-29 11:47:41'),(5,1,'2026-09-29','09:02:00',NULL,'present',NULL,'Shift in progress','2026-09-29 11:47:41','2026-09-29 11:47:41'),(6,2,'2026-09-25','09:10:00','18:00:00','present',8.83,'Regular shift','2026-09-29 11:47:41','2026-09-29 11:47:41'),(7,2,'2026-09-26','09:00:00','18:05:00','present',9.08,'Regular shift','2026-09-29 11:47:41','2026-09-29 11:47:41'),(8,2,'2026-09-27',NULL,NULL,'on-leave',0.00,'Approved Sick Leave','2026-09-29 11:47:41','2026-09-29 11:47:41'),(9,2,'2026-09-28',NULL,NULL,'on-leave',0.00,'Approved Sick Leave','2026-09-29 11:47:41','2026-09-29 11:47:41'),(10,2,'2026-09-29','09:15:00',NULL,'present',NULL,'Resumed duty after recovery','2026-09-29 11:47:41','2026-09-29 11:47:41'),(11,3,'2026-09-25','09:00:00','18:00:00','present',9.00,'Regular shift','2026-09-29 11:47:41','2026-09-29 11:47:41'),(12,3,'2026-09-26','09:20:00','18:25:00','present',9.08,'Monthly reconciliation','2026-09-29 11:47:41','2026-09-29 11:47:41'),(13,3,'2026-09-27','09:00:00','18:00:00','present',9.00,'Regular shift','2026-09-29 11:47:41','2026-09-29 11:47:41'),(14,3,'2026-09-28','09:05:00','13:05:00','half-day',4.00,'Half day for bank audit','2026-09-29 11:47:41','2026-09-29 11:47:41'),(15,3,'2026-09-29','09:00:00',NULL,'present',NULL,'On-time shift','2026-09-29 11:47:41','2026-09-29 11:47:41'),(16,4,'2026-09-25','09:00:00','18:00:00','present',9.00,'Regular shift','2026-09-29 11:47:41','2026-09-29 11:47:41'),(17,4,'2026-09-26','09:40:00','18:30:00','late',8.83,'Local train delay','2026-09-29 11:47:41','2026-09-29 11:47:41'),(18,4,'2026-09-27','09:00:00','18:00:00','present',9.00,'Regular shift','2026-09-29 11:47:41','2026-09-29 11:47:41'),(19,4,'2026-09-28','09:10:00','18:15:00','present',9.08,'Regular shift','2026-09-29 11:47:41','2026-09-29 11:47:41'),(20,4,'2026-09-29','09:10:00',NULL,'present',NULL,'Morning checkin','2026-09-29 11:47:41','2026-09-29 11:47:41'),(21,5,'2026-09-25','08:55:00','18:00:00','present',9.08,'Early checkin','2026-09-29 11:47:41','2026-09-29 11:47:41'),(22,5,'2026-09-26','09:00:00','18:00:00','present',9.00,'Regular shift','2026-09-29 11:47:41','2026-09-29 11:47:41'),(23,5,'2026-09-27','09:05:00','18:10:00','present',9.08,'Sprint release','2026-09-29 11:47:41','2026-09-29 11:47:41'),(24,5,'2026-09-28',NULL,NULL,'on-leave',0.00,'Approved Sick Leave','2026-09-29 11:47:41','2026-09-29 11:47:41'),(25,5,'2026-09-29','08:58:00',NULL,'present',NULL,'Early checkin','2026-09-29 11:47:41','2026-09-29 11:47:41'),(26,6,'2026-09-27','09:00:00','18:00:00','present',9.00,'Admin verification','2026-09-29 11:47:41','2026-09-29 11:47:41'),(27,6,'2026-09-28','09:05:00','18:00:00','present',8.92,'Facility audit','2026-09-29 11:47:41','2026-09-29 11:47:41'),(28,6,'2026-09-29','09:05:00',NULL,'present',NULL,'Facility opened','2026-09-29 11:47:41','2026-09-29 11:47:41'),(29,7,'2026-09-27','09:15:00','18:15:00','present',9.00,'Client calls','2026-09-29 11:47:41','2026-09-29 11:47:41'),(30,7,'2026-09-28','09:00:00','18:00:00','present',9.00,'Regular shift','2026-09-29 11:47:41','2026-09-29 11:47:41'),(31,7,'2026-09-29','09:20:00',NULL,'present',NULL,'Sales briefing','2026-09-29 11:47:41','2026-09-29 11:47:41'),(32,8,'2026-09-27','09:00:00','18:00:00','present',9.00,'Design sprint','2026-09-29 11:47:41','2026-09-29 11:47:41'),(33,8,'2026-09-28','09:00:00','18:00:00','present',9.00,'Prototype review','2026-09-29 11:47:41','2026-09-29 11:47:41'),(34,8,'2026-09-29','09:00:00',NULL,'present',NULL,'UX wireframing','2026-09-29 11:47:41','2026-09-29 11:47:41'),(35,9,'2026-09-27','08:50:00','18:00:00','present',9.17,'Network maintenance','2026-09-29 11:47:41','2026-09-29 11:47:41'),(36,9,'2026-09-28','09:00:00','18:00:00','present',9.00,'Server backup','2026-09-29 11:47:41','2026-09-29 11:47:41'),(37,9,'2026-09-29','08:55:00',NULL,'present',NULL,'Infrastructure monitoring','2026-09-29 11:47:41','2026-09-29 11:47:41'),(38,10,'2026-09-27','09:00:00','18:00:00','present',9.00,'Accounts review','2026-09-29 11:47:41','2026-09-29 11:47:41'),(39,10,'2026-09-28','09:00:00','18:00:00','present',9.00,'GST filing verification','2026-09-29 11:47:41','2026-09-29 11:47:41'),(40,10,'2026-09-29','09:00:00',NULL,'present',NULL,'On-time shift','2026-09-29 11:47:41','2026-09-29 11:47:41'),(41,11,'2026-09-28','09:00:00','18:00:00','present',9.00,'Test automation run','2026-09-29 11:47:41','2026-09-29 11:47:41'),(42,11,'2026-09-29','09:05:00',NULL,'present',NULL,'CI/CD pipeline test','2026-09-29 11:47:41','2026-09-29 11:47:41'),(43,12,'2026-09-28','09:00:00','18:00:00','present',9.00,'Candidate interviews','2026-09-29 11:47:41','2026-09-29 11:47:41'),(44,12,'2026-09-29','09:00:00',NULL,'present',NULL,'Recruitment drive','2026-09-29 11:47:41','2026-09-29 11:47:41'),(45,13,'2026-09-28','09:10:00','18:15:00','present',9.08,'Corporate presentation','2026-09-29 11:47:41','2026-09-29 11:47:41'),(46,13,'2026-09-29','09:15:00',NULL,'present',NULL,'Client negotiation','2026-09-29 11:47:41','2026-09-29 11:47:41'),(47,14,'2026-09-28','09:00:00','18:00:00','present',9.00,'Campaign launch','2026-09-29 11:47:41','2026-09-29 11:47:41'),(48,14,'2026-09-29','09:00:00',NULL,'present',NULL,'SEO keyword analysis','2026-09-29 11:47:41','2026-09-29 11:47:41'),(49,15,'2026-09-28','09:00:00','18:00:00','present',9.00,'Hardware rollout','2026-09-29 11:47:41','2026-09-29 11:47:41'),(50,15,'2026-09-29','09:00:00',NULL,'present',NULL,'LAN speed test','2026-09-29 11:47:41','2026-09-29 11:47:41');
/*!40000 ALTER TABLE `attendance` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `departments`
--

DROP TABLE IF EXISTS `departments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `departments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `head_id` int(11) DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_department_name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `departments`
--

LOCK TABLES `departments` WRITE;
/*!40000 ALTER TABLE `departments` DISABLE KEYS */;
INSERT INTO `departments` VALUES (1,'Engineering','Software development, system architecture, and cloud infrastructure.',5,'active','2026-09-29 11:47:41','2026-09-29 11:47:41'),(2,'Human Resources','Recruitment, employee relations, and organizational development.',2,'active','2026-09-29 11:47:41','2026-09-29 11:47:41'),(3,'Finance','Financial planning, corporate accounting, and budget audits.',3,'active','2026-09-29 11:47:41','2026-09-29 11:47:41'),(4,'Marketing','Digital branding, growth marketing, and communications.',4,'active','2026-09-29 11:47:41','2026-09-29 11:47:41'),(5,'Sales','Enterprise business development and strategic client relationships.',13,'active','2026-09-29 11:47:41','2026-09-29 11:47:41'),(6,'Administration','Facilities management, office administration, and corporate compliance.',6,'active','2026-09-29 11:47:41','2026-09-29 11:47:41'),(7,'IT Support','Internal network infrastructure, hardware maintenance, and IT helpdesk.',9,'active','2026-09-29 11:47:41','2026-09-29 11:47:41');
/*!40000 ALTER TABLE `departments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `employees`
--

DROP TABLE IF EXISTS `employees`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `employees` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `employee_id` varchar(20) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `gender` enum('male','female','other') DEFAULT NULL,
  `address` text DEFAULT NULL,
  `city` varchar(50) DEFAULT NULL,
  `state` varchar(50) DEFAULT NULL,
  `zip_code` varchar(10) DEFAULT NULL,
  `department_id` int(11) DEFAULT NULL,
  `designation` varchar(100) DEFAULT NULL,
  `date_of_joining` date NOT NULL,
  `employment_type` enum('full-time','part-time','contract','intern') NOT NULL DEFAULT 'full-time',
  `salary` decimal(12,2) NOT NULL DEFAULT 0.00,
  `bank_name` varchar(100) DEFAULT NULL,
  `bank_account` varchar(50) DEFAULT NULL,
  `ifsc_code` varchar(20) DEFAULT NULL,
  `pan_number` varchar(20) DEFAULT NULL,
  `emergency_contact_name` varchar(100) DEFAULT NULL,
  `emergency_contact_phone` varchar(20) DEFAULT NULL,
  `status` enum('active','inactive','terminated') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_employee_id` (`employee_id`),
  UNIQUE KEY `uk_user_id` (`user_id`),
  KEY `idx_department` (`department_id`),
  CONSTRAINT `fk_employee_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_employee_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `employees`
--

LOCK TABLES `employees` WRITE;
/*!40000 ALTER TABLE `employees` DISABLE KEYS */;
INSERT INTO `employees` VALUES (1,2,'EMP001','Raj','Patel','raj.patel@hrms.local','9876543210','1993-04-18','male','14 Nilkanth Society, Navrangpura','Ahmedabad','Gujarat','380009',1,'Software Developer','2022-03-01','full-time',75000.00,'HDFC Bank','50100234567891','HDFC0000006','ABCDE1234F','Ramesh Patel','9876543201','active','2026-09-29 11:47:41','2026-09-29 11:47:41'),(2,3,'EMP002','Priya','Shah','priya.shah@hrms.local','9876543211','1994-08-12','female','302 Sea View Apts, Worli','Mumbai','Maharashtra','400018',2,'HR Executive','2021-07-15','full-time',58000.00,'ICICI Bank','000401567892','ICIC0000004','BKDPS2345G','Bipin Shah','9876543202','active','2026-09-29 11:47:41','2026-09-29 11:47:41'),(3,4,'EMP003','Rahul','Desai','rahul.desai@hrms.local','9876543212','1991-11-25','male','45 Shantiniketan, Ring Road','Surat','Gujarat','395002',3,'Accountant','2020-10-10','full-time',62000.00,'State Bank of India','31234567890','SBIN0000484','CKRPD3456H','Nita Desai','9876543203','active','2026-09-29 11:47:41','2026-09-29 11:47:41'),(4,5,'EMP004','Neha','Mehta','neha.mehta@hrms.local','9876543213','1995-02-14','female','B-12 Clover Park, Viman Nagar','Pune','Maharashtra','411014',4,'Marketing Executive','2022-09-01','full-time',55000.00,'Axis Bank','918010045678912','UTIB0000037','DKNPM4567J','Sanjay Mehta','9876543204','active','2026-09-29 11:47:41','2026-09-29 11:47:41'),(5,6,'EMP005','Arjun','Joshi','arjun.joshi@hrms.local','9876543214','1990-06-30','male','88 Green Glen Layout, Bellandur','Bengaluru','Karnataka','560103',1,'Software Engineer','2019-05-15','full-time',80000.00,'Kotak Mahindra Bank','4311029876','KKBK0000427','EAMPA5678K','Sunita Joshi','9876543205','active','2026-09-29 11:47:41','2026-09-29 11:47:41'),(6,7,'EMP006','Pooja','Trivedi','pooja.trivedi@hrms.local','9876543215','1993-09-05','female','22 Akota Gardens, Old Padra Road','Vadodara','Gujarat','390020',6,'Administrative Officer','2021-02-01','full-time',48000.00,'Bank of Baroda','02610100012345','BARB0AKOTAX','FKTPT6789L','Mahesh Trivedi','9876543206','active','2026-09-29 11:47:41','2026-09-29 11:47:41'),(7,8,'EMP007','Harsh','Patel','harsh.patel@hrms.local','9876543216','1996-01-20','male','702 Goyal Intercity, Drive-In Road','Ahmedabad','Gujarat','380054',5,'Sales Executive','2023-01-16','full-time',52000.00,'HDFC Bank','50100456789123','HDFC0000006','GKHPK7890M','Kishore Patel','9876543207','active','2026-09-29 11:47:41','2026-09-29 11:47:41'),(8,9,'EMP008','Anjali','Shah','anjali.shah@hrms.local','9876543217','1994-12-10','female','501 Hill Crest, Bandra West','Mumbai','Maharashtra','400050',1,'UI/UX Designer','2021-11-01','full-time',68000.00,'ICICI Bank','000401876543','ICIC0000004','HKAPS8901N','Meena Shah','9876543208','active','2026-09-29 11:47:41','2026-09-29 11:47:41'),(9,10,'EMP009','Dhruv','Mehta','dhruv.mehta@hrms.local','9876543218','1992-05-15','male','104 Madhapur Cyber Towers Rd','Hyderabad','Telangana','500081',7,'System Administrator','2020-04-01','full-time',60000.00,'State Bank of India','32345678901','SBIN0004161','JKDPM9012P','Girish Mehta','9876543209','active','2026-09-29 11:47:41','2026-09-29 11:47:41'),(10,11,'EMP010','Riya','Desai','riya.desai@hrms.local','9876543219','1996-07-22','female','18 Silver Bells, Piplod','Surat','Gujarat','395007',3,'Financial Analyst','2022-08-15','full-time',64000.00,'Axis Bank','919010056789123','UTIB0000037','LKRDP0123Q','Chetan Desai','9876543210','active','2026-09-29 11:47:41','2026-09-29 11:47:41'),(11,12,'EMP011','Yash','Patel','yash.patel@hrms.local','9876543220','1995-10-08','male','34 Radhika Park, Kalawad Road','Rajkot','Gujarat','360005',1,'QA Automation Engineer','2022-04-10','full-time',58000.00,'HDFC Bank','50100567891234','HDFC0000101','MKYPP1234R','Bhavna Patel','9876543211','active','2026-09-29 11:47:41','2026-09-29 11:47:41'),(12,13,'EMP012','Sneha','Joshi','sneha.joshi@hrms.local','9876543221','1993-03-17','female','12 Mayur Colony, Kothrud','Pune','Maharashtra','411038',2,'Talent Acquisition Specialist','2021-06-20','full-time',56000.00,'Kotak Mahindra Bank','4311038765','KKBK0000427','NKSJP2345S','Vijay Joshi','9876543212','active','2026-09-29 11:47:41','2026-09-29 11:47:41'),(13,14,'EMP013','Karan','Shah','karan.shah@hrms.local','9876543222','1989-11-04','male','602 Oberoi Woods, Goregaon East','Mumbai','Maharashtra','400063',5,'Business Development Manager','2018-09-01','full-time',72000.00,'ICICI Bank','000401987654','ICIC0000004','PKKSP3456T','Pradeep Shah','9876543213','active','2026-09-29 11:47:41','2026-09-29 11:47:41'),(14,15,'EMP014','Kavya','Mehta','kavya.mehta@hrms.local','9876543223','1997-04-29','female','C-48 Greater Kailash 1','New Delhi','Delhi','110048',4,'Content & SEO Strategist','2023-03-01','full-time',50000.00,'State Bank of India','33456789012','SBIN0000691','QKKMP4567U','Anand Mehta','9876543214','active','2026-09-29 11:47:41','2026-09-29 11:47:41'),(15,16,'EMP015','Akash','Desai','akash.desai@hrms.local','9876543224','1996-08-14','male','55 Iscon Platinum, Bopal','Ahmedabad','Gujarat','380058',7,'Network Support Engineer','2023-06-15','full-time',45000.00,'Bank of Baroda','02610100054321','BARB0BOPALX','RKADP5678V','Kirit Desai','9876543215','active','2026-09-29 11:47:41','2026-09-29 11:47:41');
/*!40000 ALTER TABLE `employees` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `leaves`
--

DROP TABLE IF EXISTS `leaves`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `leaves` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) NOT NULL,
  `leave_type` enum('sick','casual','earned','maternity','paternity','unpaid') NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `total_days` int(11) NOT NULL DEFAULT 1,
  `reason` text NOT NULL,
  `status` enum('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
  `approved_by` int(11) DEFAULT NULL,
  `admin_remarks` text DEFAULT NULL,
  `applied_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_employee` (`employee_id`),
  KEY `idx_status` (`status`),
  KEY `idx_dates` (`start_date`,`end_date`),
  KEY `fk_leave_approver` (`approved_by`),
  CONSTRAINT `fk_leave_approver` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_leave_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `leaves`
--

LOCK TABLES `leaves` WRITE;
/*!40000 ALTER TABLE `leaves` DISABLE KEYS */;
INSERT INTO `leaves` VALUES (1,1,'casual','2026-10-03','2026-10-04',2,'Attending family wedding reception in Ahmedabad','rejected',1,'Quick review from Dashboard','2026-09-29 11:47:41','2026-09-29 11:53:22'),(2,2,'sick','2026-09-26','2026-09-27',2,'High viral fever and doctor-advised bed rest','approved',1,'Medical certificate submitted. Approved.','2026-09-29 11:47:41','2026-09-29 11:47:41'),(3,3,'earned','2026-10-11','2026-10-15',5,'Annual family holiday trip to Udaipur','pending',NULL,NULL,'2026-09-29 11:47:41','2026-09-29 11:47:41'),(4,4,'casual','2026-09-19','2026-09-20',2,'Family function and property registration formalities','approved',1,'Approved by HR.','2026-09-29 11:47:41','2026-09-29 11:47:41'),(5,5,'sick','2026-09-28','2026-09-28',1,'Severe migraine attack','approved',1,'Approved.','2026-09-29 11:47:41','2026-09-29 11:47:41'),(6,7,'casual','2026-10-07','2026-10-08',2,'Diwali festival preparation with family','approved',1,'Approved in advance.','2026-09-29 11:47:41','2026-09-29 11:47:41'),(7,8,'earned','2026-10-19','2026-10-23',5,'Personal vacation to Goa','pending',NULL,NULL,'2026-09-29 11:47:41','2026-09-29 11:47:41'),(8,10,'sick','2026-09-22','2026-09-23',2,'Eye checkup and laser procedure consultation','approved',1,'Granted with medical receipt.','2026-09-29 11:47:41','2026-09-29 11:47:41'),(9,12,'casual','2026-09-15','2026-09-16',2,'Brother engagement ceremony in Pune','approved',1,'Approved.','2026-09-29 11:47:41','2026-09-29 11:47:41'),(10,14,'sick','2026-09-30','2026-09-30',1,'Dental extraction surgery scheduled','pending',NULL,NULL,'2026-09-29 11:47:41','2026-09-29 11:47:41');
/*!40000 ALTER TABLE `leaves` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `type` enum('info','success','warning','danger') NOT NULL DEFAULT 'info',
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `link` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_read` (`user_id`,`is_read`),
  CONSTRAINT `fk_notification_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notifications`
--

LOCK TABLES `notifications` WRITE;
/*!40000 ALTER TABLE `notifications` DISABLE KEYS */;
INSERT INTO `notifications` VALUES (1,1,'New Leave Application','Raj Patel has applied for 2 days Casual Leave.','info',0,'http://localhost/hrms/admin/leaves.php','2026-09-29 11:47:42'),(2,1,'New Leave Application','Rahul Desai has applied for 5 days Earned Leave.','info',0,'http://localhost/hrms/admin/leaves.php','2026-09-29 11:47:42'),(3,1,'Turnout Milestone','Workforce attendance reached 94% on yesterday shift.','success',1,'http://localhost/hrms/admin/attendance.php','2026-09-29 11:47:42'),(4,1,'Monthly Payroll Disbursed','August 2026 salary disbursal completed successfully for 15 employees.','success',1,'http://localhost/hrms/admin/payroll.php','2026-09-29 11:47:42'),(5,2,'Welcome to HRMS','Welcome Raj Patel! You can check your shift timer, apply for leaves, and view payslips here.','info',1,'http://localhost/hrms/employee/dashboard.php','2026-09-29 11:47:42'),(6,2,'August Payslip Published','Your salary slip for August 2026 has been generated and is ready for download.','success',0,'http://localhost/hrms/employee/payroll.php','2026-09-29 11:47:42'),(7,3,'Leave Approved','Your Sick Leave application from Sep 26 to Sep 27 was APPROVED by HR.','success',0,'http://localhost/hrms/employee/leaves.php','2026-09-29 11:47:42'),(8,3,'August Payslip Published','Your salary slip for August 2026 has been generated and is ready for download.','success',1,'http://localhost/hrms/employee/payroll.php','2026-09-29 11:47:42'),(9,4,'August Payslip Published','Your salary slip for August 2026 has been generated and is ready for download.','success',1,'http://localhost/hrms/employee/payroll.php','2026-09-29 11:47:42'),(10,5,'Leave Approved','Your Casual Leave application was APPROVED by HR.','success',1,'http://localhost/hrms/employee/leaves.php','2026-09-29 11:47:42'),(11,6,'August Payslip Published','Your salary slip for August 2026 has been generated and is ready for download.','success',1,'http://localhost/hrms/employee/payroll.php','2026-09-29 11:47:42'),(12,7,'August Payslip Published','Your salary slip for August 2026 has been generated and is ready for download.','success',1,'http://localhost/hrms/employee/payroll.php','2026-09-29 11:47:42'),(13,2,'Leave Application Rejected','Your Casual leave request was rejected by HR.','danger',0,'http://localhost/hrms/employee/leaves.php','2026-09-29 11:53:22');
/*!40000 ALTER TABLE `notifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payroll`
--

DROP TABLE IF EXISTS `payroll`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payroll` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) NOT NULL,
  `month` int(2) NOT NULL,
  `year` int(4) NOT NULL,
  `basic_salary` decimal(12,2) NOT NULL DEFAULT 0.00,
  `hra` decimal(12,2) NOT NULL DEFAULT 0.00,
  `da` decimal(12,2) NOT NULL DEFAULT 0.00,
  `transport_allowance` decimal(12,2) NOT NULL DEFAULT 0.00,
  `medical_allowance` decimal(12,2) NOT NULL DEFAULT 0.00,
  `other_allowances` decimal(12,2) NOT NULL DEFAULT 0.00,
  `gross_salary` decimal(12,2) NOT NULL DEFAULT 0.00,
  `pf_deduction` decimal(12,2) NOT NULL DEFAULT 0.00,
  `tax_deduction` decimal(12,2) NOT NULL DEFAULT 0.00,
  `other_deductions` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_deductions` decimal(12,2) NOT NULL DEFAULT 0.00,
  `net_salary` decimal(12,2) NOT NULL DEFAULT 0.00,
  `payment_status` enum('pending','paid','hold') NOT NULL DEFAULT 'pending',
  `payment_date` date DEFAULT NULL,
  `payment_method` enum('bank_transfer','cheque','cash') DEFAULT 'bank_transfer',
  `remarks` text DEFAULT NULL,
  `generated_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_employee_month_year` (`employee_id`,`month`,`year`),
  KEY `idx_period` (`month`,`year`),
  KEY `fk_payroll_generator` (`generated_by`),
  CONSTRAINT `fk_payroll_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_payroll_generator` FOREIGN KEY (`generated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payroll`
--

LOCK TABLES `payroll` WRITE;
/*!40000 ALTER TABLE `payroll` DISABLE KEYS */;
INSERT INTO `payroll` VALUES (1,1,8,2026,45000.00,13500.00,6000.00,3000.00,2500.00,5000.00,75000.00,5400.00,3750.00,200.00,9350.00,65650.00,'paid','2026-08-31','bank_transfer','Monthly salary processed',1,'2026-09-29 11:47:42','2026-09-29 11:47:42'),(2,2,8,2026,34800.00,10440.00,4640.00,3000.00,2500.00,2620.00,58000.00,4176.00,2900.00,200.00,7276.00,50724.00,'paid','2026-08-31','bank_transfer','Monthly salary processed',1,'2026-09-29 11:47:42','2026-09-29 11:47:42'),(3,3,8,2026,37200.00,11160.00,4960.00,3000.00,2500.00,3180.00,62000.00,4464.00,3100.00,200.00,7764.00,54236.00,'paid','2026-08-31','bank_transfer','Monthly salary processed',1,'2026-09-29 11:47:42','2026-09-29 11:47:42'),(4,4,8,2026,33000.00,9900.00,4400.00,3000.00,2500.00,2200.00,55000.00,3960.00,2750.00,200.00,6910.00,48090.00,'paid','2026-08-31','bank_transfer','Monthly salary processed',1,'2026-09-29 11:47:42','2026-09-29 11:47:42'),(5,5,8,2026,48000.00,14400.00,6400.00,3000.00,2500.00,5700.00,80000.00,5760.00,4000.00,200.00,9960.00,70040.00,'paid','2026-08-31','bank_transfer','Monthly salary processed',1,'2026-09-29 11:47:42','2026-09-29 11:47:42'),(6,6,8,2026,28800.00,8640.00,3840.00,2500.00,2000.00,2220.00,48000.00,3456.00,2400.00,200.00,6056.00,41944.00,'paid','2026-08-31','bank_transfer','Monthly salary processed',1,'2026-09-29 11:47:42','2026-09-29 11:47:42'),(7,7,8,2026,31200.00,9360.00,4160.00,3000.00,2000.00,2280.00,52000.00,3744.00,2600.00,200.00,6544.00,45456.00,'paid','2026-08-31','bank_transfer','Monthly salary processed',1,'2026-09-29 11:47:42','2026-09-29 11:47:42'),(8,8,8,2026,40800.00,12240.00,5440.00,3000.00,2500.00,4020.00,68000.00,4896.00,3400.00,200.00,8496.00,59504.00,'paid','2026-08-31','bank_transfer','Monthly salary processed',1,'2026-09-29 11:47:42','2026-09-29 11:47:42'),(9,9,8,2026,36000.00,10800.00,4800.00,3000.00,2500.00,2900.00,60000.00,4320.00,3000.00,200.00,7520.00,52480.00,'paid','2026-08-31','bank_transfer','Monthly salary processed',1,'2026-09-29 11:47:42','2026-09-29 11:47:42'),(10,10,8,2026,38400.00,11520.00,5120.00,3000.00,2500.00,3460.00,64000.00,4608.00,3200.00,200.00,8008.00,55992.00,'paid','2026-08-31','bank_transfer','Monthly salary processed',1,'2026-09-29 11:47:42','2026-09-29 11:47:42'),(11,11,8,2026,34800.00,10440.00,4640.00,3000.00,2500.00,2620.00,58000.00,4176.00,2900.00,200.00,7276.00,50724.00,'paid','2026-08-31','bank_transfer','Monthly salary processed',1,'2026-09-29 11:47:42','2026-09-29 11:47:42'),(12,12,8,2026,33600.00,10080.00,4480.00,3000.00,2500.00,2340.00,56000.00,4032.00,2800.00,200.00,7032.00,48968.00,'paid','2026-08-31','bank_transfer','Monthly salary processed',1,'2026-09-29 11:47:42','2026-09-29 11:47:42'),(13,13,8,2026,43200.00,12960.00,5760.00,3000.00,2500.00,4580.00,72000.00,5184.00,3600.00,200.00,8984.00,63016.00,'paid','2026-08-31','bank_transfer','Monthly salary processed',1,'2026-09-29 11:47:42','2026-09-29 11:47:42'),(14,14,8,2026,30000.00,9000.00,4000.00,2500.00,2000.00,2500.00,50000.00,3600.00,2500.00,200.00,6300.00,43700.00,'paid','2026-08-31','bank_transfer','Monthly salary processed',1,'2026-09-29 11:47:42','2026-09-29 11:47:42'),(15,15,8,2026,27000.00,8100.00,3600.00,2500.00,2000.00,1800.00,45000.00,3240.00,2250.00,200.00,5690.00,39310.00,'paid','2026-08-31','bank_transfer','Monthly salary processed',1,'2026-09-29 11:47:42','2026-09-29 11:47:42');
/*!40000 ALTER TABLE `payroll` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) NOT NULL,
  `role` enum('admin','employee') NOT NULL DEFAULT 'employee',
  `status` enum('active','inactive','suspended') NOT NULL DEFAULT 'active',
  `avatar` varchar(255) DEFAULT NULL,
  `last_login` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_username` (`username`),
  UNIQUE KEY `uk_email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'admin','$2y$10$Q/gV2tl/maY64rmeMx1gougynZMm8/PdR3225OeyYry675jAO0jdm','admin@hrms.local','admin','active',NULL,'2026-09-29 17:24:08','2026-09-29 11:47:41','2026-09-29 11:54:08'),(2,'employee','$2y$10$oFH3Cer4Nm9aYO4XUzv1XuhmI/am2o7VarHM3Ne0T1RtVtSgZEkoe','raj.patel@hrms.local','employee','active',NULL,'2026-09-29 17:19:51','2026-09-29 11:47:41','2026-09-29 11:49:51'),(3,'priya.shah','$2y$10$oFH3Cer4Nm9aYO4XUzv1XuhmI/am2o7VarHM3Ne0T1RtVtSgZEkoe','priya.shah@hrms.local','employee','active',NULL,NULL,'2026-09-29 11:47:41','2026-09-29 11:47:41'),(4,'rahul.desai','$2y$10$oFH3Cer4Nm9aYO4XUzv1XuhmI/am2o7VarHM3Ne0T1RtVtSgZEkoe','rahul.desai@hrms.local','employee','active',NULL,NULL,'2026-09-29 11:47:41','2026-09-29 11:47:41'),(5,'neha.mehta','$2y$10$oFH3Cer4Nm9aYO4XUzv1XuhmI/am2o7VarHM3Ne0T1RtVtSgZEkoe','neha.mehta@hrms.local','employee','active',NULL,NULL,'2026-09-29 11:47:41','2026-09-29 11:47:41'),(6,'arjun.joshi','$2y$10$oFH3Cer4Nm9aYO4XUzv1XuhmI/am2o7VarHM3Ne0T1RtVtSgZEkoe','arjun.joshi@hrms.local','employee','active',NULL,NULL,'2026-09-29 11:47:41','2026-09-29 11:47:41'),(7,'pooja.trivedi','$2y$10$oFH3Cer4Nm9aYO4XUzv1XuhmI/am2o7VarHM3Ne0T1RtVtSgZEkoe','pooja.trivedi@hrms.local','employee','active',NULL,NULL,'2026-09-29 11:47:41','2026-09-29 11:47:41'),(8,'harsh.patel','$2y$10$oFH3Cer4Nm9aYO4XUzv1XuhmI/am2o7VarHM3Ne0T1RtVtSgZEkoe','harsh.patel@hrms.local','employee','active',NULL,NULL,'2026-09-29 11:47:41','2026-09-29 11:47:41'),(9,'anjali.shah','$2y$10$oFH3Cer4Nm9aYO4XUzv1XuhmI/am2o7VarHM3Ne0T1RtVtSgZEkoe','anjali.shah@hrms.local','employee','active',NULL,NULL,'2026-09-29 11:47:41','2026-09-29 11:47:41'),(10,'dhruv.mehta','$2y$10$oFH3Cer4Nm9aYO4XUzv1XuhmI/am2o7VarHM3Ne0T1RtVtSgZEkoe','dhruv.mehta@hrms.local','employee','active',NULL,NULL,'2026-09-29 11:47:41','2026-09-29 11:47:41'),(11,'riya.desai','$2y$10$oFH3Cer4Nm9aYO4XUzv1XuhmI/am2o7VarHM3Ne0T1RtVtSgZEkoe','riya.desai@hrms.local','employee','active',NULL,NULL,'2026-09-29 11:47:41','2026-09-29 11:47:41'),(12,'yash.patel','$2y$10$oFH3Cer4Nm9aYO4XUzv1XuhmI/am2o7VarHM3Ne0T1RtVtSgZEkoe','yash.patel@hrms.local','employee','active',NULL,NULL,'2026-09-29 11:47:41','2026-09-29 11:47:41'),(13,'sneha.joshi','$2y$10$oFH3Cer4Nm9aYO4XUzv1XuhmI/am2o7VarHM3Ne0T1RtVtSgZEkoe','sneha.joshi@hrms.local','employee','active',NULL,NULL,'2026-09-29 11:47:41','2026-09-29 11:47:41'),(14,'karan.shah','$2y$10$oFH3Cer4Nm9aYO4XUzv1XuhmI/am2o7VarHM3Ne0T1RtVtSgZEkoe','karan.shah@hrms.local','employee','active',NULL,NULL,'2026-09-29 11:47:41','2026-09-29 11:47:41'),(15,'kavya.mehta','$2y$10$oFH3Cer4Nm9aYO4XUzv1XuhmI/am2o7VarHM3Ne0T1RtVtSgZEkoe','kavya.mehta@hrms.local','employee','active',NULL,NULL,'2026-09-29 11:47:41','2026-09-29 11:47:41'),(16,'akash.desai','$2y$10$oFH3Cer4Nm9aYO4XUzv1XuhmI/am2o7VarHM3Ne0T1RtVtSgZEkoe','akash.desai@hrms.local','employee','active',NULL,NULL,'2026-09-29 11:47:41','2026-09-29 11:47:41');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-09-29 17:27:44
