-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: May 26, 2025 at 12:24 PM
-- Server version: 8.0.42-0ubuntu0.20.04.1
-- PHP Version: 8.2.28
SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `clientzone`
--

-- --------------------------------------------------------

--
-- Table structure for table `billing_category_prices`
--

CREATE TABLE `billing_category_prices` (
  `id` int NOT NULL,
  `service_category_id` int NOT NULL,
  `item_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `vat_rate` decimal(5,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `currency` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'USD'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `billing_category_prices`
--

-- --------------------------------------------------------

--
-- Table structure for table `billing_invoice_companies`
--

CREATE TABLE `billing_invoice_companies` (
  `id` int NOT NULL,
  `company_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `vat_rate` decimal(5,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `address` text COLLATE utf8mb4_general_ci NOT NULL,
  `vat_number` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `registration_number` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `contact_details` varchar(255) COLLATE utf8mb4_general_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `billing_invoice_companies`
--
-- --------------------------------------------------------

--
-- Table structure for table `billing_items`
--

CREATE TABLE `billing_items` (
  `id` int NOT NULL,
  `client_id` int DEFAULT NULL,
  `client_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `supplier_id` int DEFAULT NULL,
  `supplier_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `service_type_id` int DEFAULT NULL,
  `service_type_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `service_category_id` int DEFAULT NULL,
  `service_category_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `cpu` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `memory` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `hdd_sata` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `hdd_ssd` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `os` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `ip_address` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `qty` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `unit_price` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `vat_applied` int DEFAULT '0',
  `vat_rate` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `invoice_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `invoicing_company_id` int DEFAULT NULL,
  `invoicing_company_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `frequency` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `currency` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `is_deleted` tinyint NOT NULL DEFAULT '0',
  `currency_symbol` varchar(10) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `processed` tinyint(1) DEFAULT '0',
  `date_created` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_by` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `billing_items`
--


-- --------------------------------------------------------

--
-- Table structure for table `billing_service_categories`
--

CREATE TABLE `billing_service_categories` (
  `id` int NOT NULL,
  `service_type_id` int DEFAULT NULL,
  `category_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `has_vm_fields` tinyint NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `is_deleted` int NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `billing_service_categories`
--

-- --------------------------------------------------------

--
-- Table structure for table `billing_service_types`
--

CREATE TABLE `billing_service_types` (
  `id` int NOT NULL,
  `service_type_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `billing_service_types`
--

-- --------------------------------------------------------

--
-- Table structure for table `billing_suppliers`
--

CREATE TABLE `billing_suppliers` (
  `id` int NOT NULL,
  `supplier_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `contact_details` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `salesperson` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `accounts_email` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `accounts_contact` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `billing_suppliers`
--


-- --------------------------------------------------------

--
-- Table structure for table `clients`
--

CREATE TABLE `clients` (
  `id` int NOT NULL,
  `client_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `contact_person` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `office_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `accounts_contact` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `accounts_email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `address` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `vat_number` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `registration_number` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `billing_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `status` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `sales_person` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `billing_country` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `currency` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `currency_symbol` varchar(10) COLLATE utf8mb4_general_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `clients`
--


-- --------------------------------------------------------

--
-- Table structure for table `client_365`
--

CREATE TABLE `client_365` (
  `id` int NOT NULL,
  `client_id` int NOT NULL,
  `product` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `fullname` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `username` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `client_365`
--


-- --------------------------------------------------------

--
-- Table structure for table `client_custom_tabs`
--

CREATE TABLE `client_custom_tabs` (
  `id` int NOT NULL,
  `client_id` int DEFAULT NULL,
  `tab_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `client_custom_tabs`
--


-- --------------------------------------------------------

--
-- Table structure for table `client_devices`
--

CREATE TABLE `client_devices` (
  `id` int NOT NULL,
  `client_id` int NOT NULL,
  `device_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `device_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `device_ip` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `location` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `username` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `enable_username` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `enable_password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `access_port` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `is_deleted` int NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `client_devices`
--


-- --------------------------------------------------------

--
-- Table structure for table `client_documents`
--

CREATE TABLE `client_documents` (
  `id` int NOT NULL,
  `client_id` int DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `filename` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `uploaded_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `client_documents`
--


-- --------------------------------------------------------

--
-- Table structure for table `client_products`
--

CREATE TABLE `client_products` (
  `id` int NOT NULL,
  `client_id` int NOT NULL,
  `product_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `client_product_records`
--

CREATE TABLE `client_product_records` (
  `id` int NOT NULL,
  `client_id` int NOT NULL,
  `product_id` int NOT NULL,
  `full_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `username` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `client_services`
--

CREATE TABLE `client_services` (
  `id` int NOT NULL,
  `client_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `service_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `service_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `specs` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `status` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `billing_amount` decimal(10,2) DEFAULT NULL,
  `invoice_link` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `is_deleted` tinyint(1) DEFAULT '0',
  `currency` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'ZAR',
  `quantity` int DEFAULT '1',
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `exclude_vat` tinyint(1) DEFAULT '1',
  `ex_vat_amount` decimal(10,2) DEFAULT NULL,
  `vat_amount` decimal(10,2) DEFAULT NULL,
  `total_with_vat` decimal(10,2) DEFAULT NULL,
  `debit_order_day` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `client_services`
--


-- --------------------------------------------------------

--
-- Table structure for table `client_service_edits`
--

CREATE TABLE `client_service_edits` (
  `id` int NOT NULL,
  `service_id` int NOT NULL,
  `old_quantity` int DEFAULT NULL,
  `new_quantity` int DEFAULT NULL,
  `edited_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- --------------------------------------------------------

--
-- Table structure for table `client_support_items`
--

CREATE TABLE `client_support_items` (
  `id` int NOT NULL,
  `client_id` int DEFAULT NULL,
  `label` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `ip_address` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `username` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `extra` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `client_support_items`
--


-- --------------------------------------------------------

--
-- Table structure for table `exchange_domains`
--

CREATE TABLE `exchange_domains` (
  `id` int NOT NULL,
  `client_id` int NOT NULL,
  `domain` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `exchange_domains`
--


-- --------------------------------------------------------

--
-- Table structure for table `exchange_mailboxes`
--

CREATE TABLE `exchange_mailboxes` (
  `id` int NOT NULL,
  `client_id` int NOT NULL,
  `domain` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `full_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `spamtitan` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `exchange_mailboxes`
--


-- --------------------------------------------------------

--
-- Table structure for table `expenses`
--

CREATE TABLE `expenses` (
  `id` int NOT NULL,
  `supplier_id` int NOT NULL,
  `supplier_name` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `accounts_contact` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `accounts_email` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `contact_number` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `payment_method` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `payment_frequency` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `amount_ex_vat` decimal(10,2) DEFAULT NULL,
  `vat_percent` decimal(5,2) DEFAULT NULL,
  `total` decimal(10,2) DEFAULT NULL,
  `is_variable` tinyint(1) DEFAULT '0',
  `client_id` int DEFAULT NULL,
  `bank_name` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `account_type` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `account_number` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `notes` text COLLATE utf8mb4_general_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `payment_date` date DEFAULT NULL,
  `account_contact` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `invoicing_company_id` int DEFAULT NULL,
  `set_variable_text` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `st_account_number` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `expenses`
--


-- --------------------------------------------------------

--
-- Table structure for table `hosting_assets`
--

CREATE TABLE `hosting_assets` (
  `id` int NOT NULL,
  `client_id` int NOT NULL,
  `client_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `location` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `asset_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `host` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `server_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `os` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `cpu` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `mem` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `ram` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `sata` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `ssd` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `private_ip` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `public_ip` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `ip_address` int DEFAULT NULL,
  `username` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `password` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `spla` enum('Yes','No') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'No',
  `login_url` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `hosting_assets`
--


-- --------------------------------------------------------

--
-- Table structure for table `hosting_asset_types`
--

CREATE TABLE `hosting_asset_types` (
  `id` int NOT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `hosting_asset_types`
--


-- --------------------------------------------------------

--
-- Table structure for table `hosting_hosts`
--

CREATE TABLE `hosting_hosts` (
  `id` int NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `hosting_hosts`
--


-- --------------------------------------------------------

--
-- Table structure for table `hosting_locations`
--

CREATE TABLE `hosting_locations` (
  `id` int NOT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `hosting_locations`
--


-- --------------------------------------------------------

--
-- Table structure for table `hosting_logins`
--

CREATE TABLE `hosting_logins` (
  `id` int NOT NULL,
  `client_id` int NOT NULL,
  `device_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `device_ip` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `location` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `username` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `url` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `hosting_oss`
--

CREATE TABLE `hosting_oss` (
  `id` int NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `hosting_oss`
--
CREATE TABLE `roles` (
  `id` int NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`id`, `name`) VALUES
(3, 'admin');

CREATE TABLE `registers` (
  `id` int NOT NULL,
  `client_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `client_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `device_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `device_ip` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `device_ip_location` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `url` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `username` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `surname` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `address` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `role_id` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `registers`
--

INSERT INTO `registers` (`id`, `client_name`, `client_id`, `device_type`, `device_ip`, `device_ip_location`, `url`, `username`, `password`, `name`, `surname`, `email`, `address`, `role_id`) VALUES
(14, 'sautechadmin', '2', '', '', '', '', 'sautechadmin', 'admin1234', '', '', '', '', 3);
-- --------------------------------------------------------

--
-- Table structure for table `permissions`
--

CREATE TABLE `permissions` (
  `id` int NOT NULL,
  `role_id` int DEFAULT NULL,
  `page` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `function_name` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `allowed` tinyint(1) DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `permissions`
--

INSERT INTO `permissions` (`id`, `role_id`, `page`, `function_name`, `allowed`) VALUES
(588, 13, 'billing', 'billing', 1),
(589, 13, 'billing', 'wip', 1),
(590, 13, 'billing page', 'create', 1),
(591, 13, 'billing page', 'update', 1),
(592, 13, 'quotes', 'create', 1),
(593, 13, 'quotes', 'update', 1),
(594, 13, 'quotes', 'send_email', 1),
(595, 13, 'report and admin', 'reseller commission', 1),
(596, 13, 'reseller commission', 'Send Email', 1),
(751, 3, 'Hosting and Licensing', 'hosting', 1),
(752, 3, 'Hosting and Licensing', 'logins', 1),
(753, 3, 'Hosting and Licensing', 'spla', 1),
(754, 3, 'Hosting and Licensing', 'devices', 1),
(755, 3, 'hosting', 'create', 1),
(756, 3, 'hosting', 'update', 1),
(757, 3, 'hosting', 'delete', 1),
(758, 3, 'hosting', 'export csv', 1),
(759, 3, 'logins', 'create', 1),
(760, 3, 'logins', 'update', 1),
(761, 3, 'logins', 'delete', 1),
(762, 3, 'spla', 'create', 1),
(763, 3, 'spla', 'update', 1),
(764, 3, 'spla', 'delete', 1),
(765, 3, 'devices', 'create', 1),
(766, 3, 'devices', 'update', 1),
(767, 3, 'devices', 'delete', 1),
(768, 3, 'admin service', 'Manage Suppliers', 1),
(769, 3, 'admin service', 'Manage Service Types', 1),
(770, 3, 'admin service', 'Manage Service Categories', 1),
(771, 3, 'admin service', 'Unit Prices', 1),
(772, 3, 'admin service', 'Manage Hosting Assets', 1),
(773, 3, 'admin service', 'Manage Invoice Companies', 1),
(774, 3, 'admin service', 'Finance Calculator', 1),
(775, 3, 'admin service', 'Reseller', 1),
(776, 3, 'Manage Suppliers', 'create', 1),
(777, 3, 'Manage Suppliers', 'update', 1),
(778, 3, 'Manage Suppliers', 'delete', 1),
(779, 3, 'Manage Service Types', 'create', 1),
(780, 3, 'Manage Service Types', 'update', 1),
(781, 3, 'Manage Service Types', 'delete', 1),
(782, 3, 'Manage Service Categories', 'create', 1),
(783, 3, 'Manage Service Categories', 'update', 1),
(784, 3, 'Manage Service Categories', 'delete', 1),
(785, 3, 'Unit Prices', 'create', 1),
(786, 3, 'Unit Prices', 'update', 1),
(787, 3, 'Unit Prices', 'delete', 1),
(788, 3, 'Unit Prices', 'Bulk Price', 1),
(789, 3, 'Manage Hosting Assets', 'create', 1),
(790, 3, 'Manage Invoice Companies', 'create', 1),
(791, 3, 'Manage Invoice Companies', 'update', 1),
(792, 3, 'Manage Invoice Companies', 'delete', 1),
(793, 3, 'Reseller', 'create', 1),
(794, 3, 'Reseller', 'update', 1),
(795, 3, 'Reseller', 'delete', 1),
(796, 3, 'clients', 'create', 1),
(797, 3, 'clients', 'update', 1),
(798, 3, 'clients', 'delete', 1),
(799, 3, 'billing', 'billing', 1),
(800, 3, 'billing', 'wip', 1),
(801, 3, 'billing', 'quotes', 1),
(802, 3, 'billing', 'expenses', 1),
(803, 3, 'billing page', 'create', 1),
(804, 3, 'billing page', 'update', 1),
(805, 3, 'billing page', 'delete', 1),
(806, 3, 'billing page', 'extend expired', 1),
(807, 3, 'billing page', 'delete expired', 1),
(808, 3, 'wip', 'create', 1),
(809, 3, 'wip', 'update', 1),
(810, 3, 'wip', 'delete', 1),
(811, 3, 'quotes', 'create', 1),
(812, 3, 'quotes', 'update', 1),
(813, 3, 'quotes', 'delete', 1),
(814, 3, 'quotes', 'send_email', 1),
(815, 3, 'expenses', 'create', 1),
(816, 3, 'expenses', 'update', 1),
(817, 3, 'expenses', 'delete', 1),
(818, 3, 'report and admin', 'user logins', 1),
(819, 3, 'report and admin', 'billing report', 1),
(820, 3, 'report and admin', 'reseller commission', 1),
(821, 3, 'report and admin', 'role management', 1),
(822, 3, 'user logins', 'create', 1),
(823, 3, 'user logins', 'update', 1),
(824, 3, 'user logins', 'delete', 1),
(825, 3, 'billing report', 'Mark as procced', 1),
(826, 3, 'reseller commission', 'Send Email', 1),
(827, 3, 'reseller commission', 'View all', 1),
(833, 19, 'billing page', 'create', 1),
(834, 19, 'billing page', 'update', 1),
(835, 19, 'report and admin', 'reseller commission', 1),
(836, 19, 'reseller commission', 'Send Email', 1);

-- --------------------------------------------------------

--
-- Table structure for table `privnotes`
--

CREATE TABLE `privnotes` (
  `id` bigint NOT NULL,
  `note_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `privnotes`
--

-- --------------------------------------------------------

--
-- Table structure for table `products_365`
--

CREATE TABLE `products_365` (
  `id` int NOT NULL,
  `product` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products_365`
--


-- --------------------------------------------------------

--
-- Table structure for table `quotes`
--

CREATE TABLE `quotes` (
  `id` int NOT NULL,
  `quoted_company_id` int NOT NULL,
  `client_id` int NOT NULL,
  `quote_number` varchar(50) NOT NULL,
  `description` text,
  `total` decimal(10,2) DEFAULT NULL,
  `total_exclusive` decimal(10,2) DEFAULT NULL,
  `total_vat` decimal(10,2) DEFAULT NULL,
  `discount` decimal(10,2) DEFAULT '0.00',
  `status` enum('Quoted','Followed up','Declined','Approved') DEFAULT 'Quoted',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `reference` varchar(255) DEFAULT NULL,
  `sales_person` varchar(255) DEFAULT NULL,
  `quote_date` date DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `created_by` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `quotes`
--


-- --------------------------------------------------------

--
-- Table structure for table `quote_emails`
--

CREATE TABLE `quote_emails` (
  `id` int NOT NULL,
  `client_id` int DEFAULT NULL,
  `quote_id` int DEFAULT NULL,
  `sender_name` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `sender_email` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `recipient_name` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `recipient_email` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `sent_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `quote_email_log`
--

CREATE TABLE `quote_email_log` (
  `id` int NOT NULL,
  `quote_id` int NOT NULL,
  `sender_name` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `recipient_name` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `recipient_email` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `sent_at` datetime DEFAULT NULL,
  `message` text COLLATE utf8mb4_general_ci,
  `status` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `quote_email_log`
--


-- --------------------------------------------------------

--
-- Table structure for table `quote_items`
--

CREATE TABLE `quote_items` (
  `id` int NOT NULL,
  `quote_id` int NOT NULL,
  `service_type_id` int NOT NULL,
  `service_category_id` int NOT NULL,
  `description` text COLLATE utf8mb4_general_ci,
  `qty` int NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `price_ex_vat` decimal(10,2) NOT NULL,
  `vat` decimal(10,2) NOT NULL,
  `total_incl_vat` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `quote_items`
--

-- --------------------------------------------------------

--
-- Table structure for table `registers`
--



-- --------------------------------------------------------

--
-- Table structure for table `resellers`
--

CREATE TABLE `resellers` (
  `id` int NOT NULL,
  `client_id` json DEFAULT NULL,
  `register_id` int DEFAULT NULL,
  `description` text COLLATE utf8mb4_general_ci,
  `name` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `email` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `resellers`
--


-- --------------------------------------------------------

--
-- Table structure for table `roles`
--



-- --------------------------------------------------------

--
-- Table structure for table `service_names`
--

CREATE TABLE `service_names` (
  `id` int NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `service_types`
--

CREATE TABLE `service_types` (
  `id` int NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `id` int NOT NULL,
  `setting_key` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `setting_value` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `settings`
--

-- --------------------------------------------------------

--
-- Table structure for table `spamtitan_servers`
--

CREATE TABLE `spamtitan_servers` (
  `id` int NOT NULL,
  `client_id` int NOT NULL,
  `hostname` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `spamtitan_servers`
--


-- --------------------------------------------------------

--
-- Table structure for table `spla_custom_data`
--

CREATE TABLE `spla_custom_data` (
  `id` int NOT NULL,
  `spla_license_id` int DEFAULT NULL,
  `field_id` int DEFAULT NULL,
  `field_value` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `spla_custom_fields`
--

CREATE TABLE `spla_custom_fields` (
  `id` int NOT NULL,
  `field_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `field_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `is_required` tinyint(1) DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `spla_licenses`
--

CREATE TABLE `spla_licenses` (
  `id` int NOT NULL,
  `client` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `vm_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `vcpus` int DEFAULT NULL,
  `ram` int DEFAULT NULL,
  `disk` int DEFAULT NULL,
  `windows_version` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `ms_products` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `quantity` varchar(244) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `is_deleted` tinyint(1) DEFAULT '0',
  `type` enum('vm','license') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'vm'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `spla_licenses`
--


-- --------------------------------------------------------

--
-- Table structure for table `spla_licenses_edits`
--

CREATE TABLE `spla_licenses_edits` (
  `id` int NOT NULL,
  `spla_id` int NOT NULL,
  `field_changed` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `old_value` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `new_value` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `edited_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `spla_licenses_edits`
--


-- --------------------------------------------------------

--
-- Table structure for table `support_data`
--

CREATE TABLE `support_data` (
  `id` int NOT NULL,
  `client_id` int NOT NULL,
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `serial` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `make` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `model` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `location` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `username` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `ipaddress` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `support_data`
--


-- --------------------------------------------------------

--
-- Table structure for table `wip`
--

CREATE TABLE `wip` (
  `id` int NOT NULL,
  `client_id` int NOT NULL,
  `quote_id` varchar(255) DEFAULT NULL,
  `sales_person` varchar(255) DEFAULT NULL,
  `description` text,
  `monthly_price_incl_vat` decimal(10,2) DEFAULT NULL,
  `status` enum('Quoted','Followed up','Declined','Approved') DEFAULT 'Quoted',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `terms` enum('once_off','monthly') NOT NULL DEFAULT 'once_off'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `wip`
--


--
-- Indexes for dumped tables
--

--
-- Indexes for table `billing_category_prices`
--
ALTER TABLE `billing_category_prices`
  ADD PRIMARY KEY (`id`),
  ADD KEY `category_id` (`service_category_id`);

--
-- Indexes for table `billing_invoice_companies`
--
ALTER TABLE `billing_invoice_companies`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `billing_items`
--
ALTER TABLE `billing_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `client_id` (`client_id`),
  ADD KEY `supplier_id` (`supplier_id`),
  ADD KEY `billing_service_type_id` (`service_type_id`),
  ADD KEY `billing_service_category_id` (`service_category_id`),
  ADD KEY `invoice_company_id` (`invoicing_company_id`),
  ADD KEY `createdBy` (`created_by`);

--
-- Indexes for table `billing_service_categories`
--
ALTER TABLE `billing_service_categories`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_service_type` (`service_type_id`);

--
-- Indexes for table `billing_service_types`
--
ALTER TABLE `billing_service_types`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `billing_suppliers`
--
ALTER TABLE `billing_suppliers`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `clients`
--
ALTER TABLE `clients`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `client_365`
--
ALTER TABLE `client_365`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `client_custom_tabs`
--
ALTER TABLE `client_custom_tabs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `client_devices`
--
ALTER TABLE `client_devices`
  ADD PRIMARY KEY (`id`),
  ADD KEY `client_is` (`client_id`);

--
-- Indexes for table `client_documents`
--
ALTER TABLE `client_documents`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `client_products`
--
ALTER TABLE `client_products`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `client_product_records`
--
ALTER TABLE `client_product_records`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `client_services`
--
ALTER TABLE `client_services`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `client_service_edits`
--
ALTER TABLE `client_service_edits`
  ADD PRIMARY KEY (`id`),
  ADD KEY `service_id` (`service_id`);

--
-- Indexes for table `client_support_items`
--
ALTER TABLE `client_support_items`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `exchange_domains`
--
ALTER TABLE `exchange_domains`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `exchange_mailboxes`
--
ALTER TABLE `exchange_mailboxes`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `expenses`
--
ALTER TABLE `expenses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `supplier_id` (`supplier_id`),
  ADD KEY `client_id` (`client_id`),
  ADD KEY `company` (`invoicing_company_id`);

--
-- Indexes for table `hosting_assets`
--
ALTER TABLE `hosting_assets`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `hosting_asset_types`
--
ALTER TABLE `hosting_asset_types`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `hosting_hosts`
--
ALTER TABLE `hosting_hosts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `hosting_locations`
--
ALTER TABLE `hosting_locations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `hosting_logins`
--
ALTER TABLE `hosting_logins`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `hosting_oss`
--
ALTER TABLE `hosting_oss`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `permissions`
--
ALTER TABLE `permissions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `role_id` (`role_id`);

--
-- Indexes for table `privnotes`
--
ALTER TABLE `privnotes`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `products_365`
--
ALTER TABLE `products_365`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `quotes`
--
ALTER TABLE `quotes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `quote_number` (`quote_number`),
  ADD KEY `quoted_company_id` (`quoted_company_id`),
  ADD KEY `client_id` (`client_id`),
  ADD KEY `created_by12` (`created_by`);

--
-- Indexes for table `quote_emails`
--
ALTER TABLE `quote_emails`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `quote_email_log`
--
ALTER TABLE `quote_email_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `quote_email_log_ibfk_1` (`quote_id`);

--
-- Indexes for table `quote_items`
--
ALTER TABLE `quote_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `quote_id` (`quote_id`);

--
-- Indexes for table `registers`
--
ALTER TABLE `registers`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `resellers`
--
ALTER TABLE `resellers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `register` (`register_id`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `service_names`
--
ALTER TABLE `service_names`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `service_types`
--
ALTER TABLE `service_types`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indexes for table `spamtitan_servers`
--
ALTER TABLE `spamtitan_servers`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `spla_custom_data`
--
ALTER TABLE `spla_custom_data`
  ADD PRIMARY KEY (`id`),
  ADD KEY `spla_license_id` (`spla_license_id`),
  ADD KEY `field_id` (`field_id`);

--
-- Indexes for table `spla_custom_fields`
--
ALTER TABLE `spla_custom_fields`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `spla_licenses`
--
ALTER TABLE `spla_licenses`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `spla_licenses_edits`
--
ALTER TABLE `spla_licenses_edits`
  ADD PRIMARY KEY (`id`),
  ADD KEY `spla_id` (`spla_id`);

--
-- Indexes for table `support_data`
--
ALTER TABLE `support_data`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `wip`
--
ALTER TABLE `wip`
  ADD PRIMARY KEY (`id`),
  ADD KEY `client_id` (`client_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `billing_category_prices`
--
ALTER TABLE `billing_category_prices`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `billing_invoice_companies`
--
ALTER TABLE `billing_invoice_companies`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `billing_items`
--
ALTER TABLE `billing_items`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=51;

--
-- AUTO_INCREMENT for table `billing_service_categories`
--
ALTER TABLE `billing_service_categories`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=55;

--
-- AUTO_INCREMENT for table `billing_service_types`
--
ALTER TABLE `billing_service_types`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `billing_suppliers`
--
ALTER TABLE `billing_suppliers`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `clients`
--
ALTER TABLE `clients`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT for table `client_365`
--
ALTER TABLE `client_365`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `client_custom_tabs`
--
ALTER TABLE `client_custom_tabs`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `client_devices`
--
ALTER TABLE `client_devices`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `client_documents`
--
ALTER TABLE `client_documents`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `client_products`
--
ALTER TABLE `client_products`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `client_product_records`
--
ALTER TABLE `client_product_records`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `client_services`
--
ALTER TABLE `client_services`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `client_service_edits`
--
ALTER TABLE `client_service_edits`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `client_support_items`
--
ALTER TABLE `client_support_items`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `exchange_domains`
--
ALTER TABLE `exchange_domains`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `exchange_mailboxes`
--
ALTER TABLE `exchange_mailboxes`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `expenses`
--
ALTER TABLE `expenses`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `hosting_assets`
--
ALTER TABLE `hosting_assets`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `hosting_asset_types`
--
ALTER TABLE `hosting_asset_types`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `hosting_hosts`
--
ALTER TABLE `hosting_hosts`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `hosting_locations`
--
ALTER TABLE `hosting_locations`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `hosting_logins`
--
ALTER TABLE `hosting_logins`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `hosting_oss`
--
ALTER TABLE `hosting_oss`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `permissions`
--
ALTER TABLE `permissions`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=837;

--
-- AUTO_INCREMENT for table `privnotes`
--
ALTER TABLE `privnotes`
  MODIFY `id` bigint NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `products_365`
--
ALTER TABLE `products_365`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `quotes`
--
ALTER TABLE `quotes`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `quote_emails`
--
ALTER TABLE `quote_emails`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `quote_email_log`
--
ALTER TABLE `quote_email_log`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `quote_items`
--
ALTER TABLE `quote_items`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `registers`
--
ALTER TABLE `registers`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `resellers`
--
ALTER TABLE `resellers`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `service_names`
--
ALTER TABLE `service_names`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `service_types`
--
ALTER TABLE `service_types`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `spamtitan_servers`
--
ALTER TABLE `spamtitan_servers`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `spla_custom_data`
--
ALTER TABLE `spla_custom_data`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `spla_custom_fields`
--
ALTER TABLE `spla_custom_fields`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `spla_licenses`
--
ALTER TABLE `spla_licenses`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `spla_licenses_edits`
--
ALTER TABLE `spla_licenses_edits`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `support_data`
--
ALTER TABLE `support_data`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `wip`
--
ALTER TABLE `wip`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `billing_category_prices`
--
ALTER TABLE `billing_category_prices`
  ADD CONSTRAINT `category_id` FOREIGN KEY (`service_category_id`) REFERENCES `billing_service_categories` (`id`);

--
-- Constraints for table `billing_items`
--
ALTER TABLE `billing_items`
  ADD CONSTRAINT `billing_service_category_id` FOREIGN KEY (`service_category_id`) REFERENCES `billing_service_categories` (`id`),
  ADD CONSTRAINT `billing_service_type_id` FOREIGN KEY (`service_type_id`) REFERENCES `billing_service_types` (`id`),
  ADD CONSTRAINT `client_id` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`),
  ADD CONSTRAINT `createdBy` FOREIGN KEY (`created_by`) REFERENCES `registers` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  ADD CONSTRAINT `supplier_id` FOREIGN KEY (`supplier_id`) REFERENCES `billing_suppliers` (`id`);

--
-- Constraints for table `billing_service_categories`
--
ALTER TABLE `billing_service_categories`
  ADD CONSTRAINT `fk_service_type` FOREIGN KEY (`service_type_id`) REFERENCES `billing_service_types` (`id`);

--
-- Constraints for table `client_service_edits`
--
ALTER TABLE `client_service_edits`
  ADD CONSTRAINT `client_service_edits_ibfk_1` FOREIGN KEY (`service_id`) REFERENCES `client_services` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `expenses`
--
ALTER TABLE `expenses`
  ADD CONSTRAINT `client_xsx` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT,
  ADD CONSTRAINT `supplier` FOREIGN KEY (`supplier_id`) REFERENCES `billing_suppliers` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT;

--
-- Constraints for table `permissions`
--
ALTER TABLE `permissions`
  ADD CONSTRAINT `permissions_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `quotes`
--
ALTER TABLE `quotes`
  ADD CONSTRAINT `created_by12` FOREIGN KEY (`created_by`) REFERENCES `registers` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  ADD CONSTRAINT `fk_quotes_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_quotes_company` FOREIGN KEY (`quoted_company_id`) REFERENCES `billing_invoice_companies` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `quote_email_log`
--
ALTER TABLE `quote_email_log`
  ADD CONSTRAINT `quote_email_log_ibfk_1` FOREIGN KEY (`quote_id`) REFERENCES `quotes` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `quote_items`
--
ALTER TABLE `quote_items`
  ADD CONSTRAINT `quote_items_ibfk_1` FOREIGN KEY (`quote_id`) REFERENCES `quotes` (`id`);

--
-- Constraints for table `resellers`
--
ALTER TABLE `resellers`
  ADD CONSTRAINT `register` FOREIGN KEY (`register_id`) REFERENCES `registers` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT;

--
-- Constraints for table `spla_custom_data`
--
ALTER TABLE `spla_custom_data`
  ADD CONSTRAINT `spla_custom_data_ibfk_1` FOREIGN KEY (`spla_license_id`) REFERENCES `spla_licenses` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `spla_custom_data_ibfk_2` FOREIGN KEY (`field_id`) REFERENCES `spla_custom_fields` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `spla_licenses_edits`
--
ALTER TABLE `spla_licenses_edits`
  ADD CONSTRAINT `spla_licenses_edits_ibfk_1` FOREIGN KEY (`spla_id`) REFERENCES `spla_licenses` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `wip`
--
ALTER TABLE `wip`
  ADD CONSTRAINT `fk_wip_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE;

SET FOREIGN_KEY_CHECKS = 1;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
