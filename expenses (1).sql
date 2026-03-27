-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 16, 2025 at 10:39 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.3.20

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
-- Table structure for table `expenses`
--

CREATE TABLE `expenses` (
  `id` int(11) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `supplier_name` varchar(255) DEFAULT NULL,
  `accounts_email` varchar(255) DEFAULT NULL,
  `contact_number` varchar(255) DEFAULT NULL,
  `payment_method` varchar(100) DEFAULT NULL,
  `terms` varchar(100) DEFAULT NULL,
  `payment_frequency` varchar(100) DEFAULT NULL,
  `amount_ex_vat` decimal(10,2) DEFAULT NULL,
  `vat_percent` decimal(5,2) DEFAULT NULL,
  `total` decimal(10,2) DEFAULT NULL,
  `is_variable` tinyint(1) DEFAULT 0,
  `client_id` int(11) DEFAULT NULL,
  `entity` varchar(100) DEFAULT NULL,
  `bank_name` varchar(100) DEFAULT NULL,
  `account_type` varchar(100) DEFAULT NULL,
  `account_number` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `payment_date` date DEFAULT NULL,
  `account_contact` varchar(100) DEFAULT NULL,
  `supplier_contact_number` varchar(50) DEFAULT NULL,
  `invoicing_company_id` int(11) DEFAULT NULL,
  `set_variable_text` varchar(255) NOT NULL,
  `st_account_number` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `expenses`
--

INSERT INTO `expenses` (`id`, `supplier_id`, `supplier_name`, `accounts_email`, `contact_number`, `payment_method`, `terms`, `payment_frequency`, `amount_ex_vat`, `vat_percent`, `total`, `is_variable`, `client_id`, `entity`, `bank_name`, `account_type`, `account_number`, `notes`, `created_at`, `updated_at`, `payment_date`, `account_contact`, `supplier_contact_number`, `invoicing_company_id`, `set_variable_text`, `st_account_number`) VALUES
(7, 3, NULL, NULL, NULL, 'Online Payment', NULL, 'Adipisci vitae nostr', 31.00, 94.00, 60.14, 0, 8, '0', 'Atque magna velit nemo tenetur reprehenderit deserunt eum deserunt ex vel adipisicing est quidem atq', 'Autem corporis enim perferendis et similique dolorem in maiores voluptatem quaerat ratione similique', '374', 'Quaerat enim veritat', '2025-05-14 07:23:57', '2025-05-14 07:32:49', '1974-02-25', 'In vitae et quasi qui aut odit placeat accusamus', '843', 3, 'Voluptatum necessitatibus id est laboriosam ea et', NULL),
(8, 4, NULL, NULL, NULL, 'Online Payment', NULL, 'asdas', 121.00, 12.00, 135.52, 0, 13, NULL, 'asdasd', '12312', 'asdasd', 'asdasdasd', '2025-05-16 08:19:35', '2025-05-16 08:19:35', '2025-05-30', NULL, NULL, 1, '12e12', NULL),
(10, 4, 'Baxter Joyner', 'garyf@mailinator.com', 'Quos expedita beatae', 'Internet Banking Payment', NULL, '0', 58.00, 87.00, 108.46, 0, 16, NULL, 'Eaque omnis enim ver', 'Dolor iusto qui susc', '134', 'Incididunt tempora a', '2025-05-16 08:32:48', '2025-05-16 08:32:48', '2023-02-07', NULL, NULL, 1, 'Alias reprehenderit ', '830'),
(11, 4, 'Baxter Joyner', 'garyf@mailinator.com', 'Quos expedita beatae', 'Online Payment', NULL, '0', 10.00, 90.00, 19.00, 0, 15, NULL, 'Nostrum anim iure do', 'Dolorum corporis opt', '831', 'Harum et molestiae c', '2025-05-16 08:34:26', '2025-05-16 08:34:26', '1980-01-14', 'In et et pariatur I', NULL, 3, 'Accusantium est dol', '834');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `expenses`
--
ALTER TABLE `expenses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `supplier_id` (`supplier_id`),
  ADD KEY `client_id` (`client_id`),
  ADD KEY `company` (`invoicing_company_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `expenses`
--
ALTER TABLE `expenses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `expenses`
--
ALTER TABLE `expenses`
  ADD CONSTRAINT `company` FOREIGN KEY (`invoicing_company_id`) REFERENCES `billing_invoice_companies` (`id`),
  ADD CONSTRAINT `expenses_ibfk_1` FOREIGN KEY (`supplier_id`) REFERENCES `billing_suppliers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `expenses_ibfk_2` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
