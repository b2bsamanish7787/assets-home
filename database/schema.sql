-- ============================================================
-- Buzznation Assets Management System
-- File: database/schema.sql
-- Description: Complete database schema for the assets portal
-- Author: Buzznation IT Team
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';
SET time_zone = '+00:00';

-- Users / Employees
CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  employee_id VARCHAR(20) UNIQUE,
  first_name VARCHAR(100) NOT NULL,
  last_name VARCHAR(100) NOT NULL,
  email VARCHAR(150) UNIQUE NOT NULL,
  password VARCHAR(255) NOT NULL,
  role ENUM('admin','hr','employee') DEFAULT 'employee',
  manager_name VARCHAR(150) NOT NULL DEFAULT '',
  manager_email VARCHAR(150) NOT NULL DEFAULT '',
  designation VARCHAR(100),
  department VARCHAR(100),
  status ENUM('active','inactive') DEFAULT 'active',
  is_first_login TINYINT(1) DEFAULT 1,
  temp_password VARCHAR(255),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Asset Categories
CREATE TABLE IF NOT EXISTS categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  description TEXT,
  status ENUM('active','inactive') DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Assets
CREATE TABLE IF NOT EXISTS assets (
  id INT AUTO_INCREMENT PRIMARY KEY,
  category_id INT,
  asset_name VARCHAR(150) NOT NULL,
  model_number VARCHAR(100),
  serial_number VARCHAR(100),
  receive_date DATE,
  purchased_by ENUM('me','company') DEFAULT 'company',
  bill_file VARCHAR(255),
  assigned_to INT NULL,
  status ENUM('available','assigned','returned','in_service') DEFAULT 'available',
  submitted_by INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
  FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
  FOREIGN KEY (submitted_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Asset History
CREATE TABLE IF NOT EXISTS asset_history (
  id INT AUTO_INCREMENT PRIMARY KEY,
  asset_id INT NOT NULL,
  action ENUM('submitted','assigned','transferred','returned','service_requested','service_approved','service_completed') NOT NULL,
  from_user INT NULL,
  to_user INT NULL,
  notes TEXT,
  created_by INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Asset Requests
CREATE TABLE IF NOT EXISTS asset_requests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  employee_id INT NOT NULL,
  asset_requirement VARCHAR(200) NOT NULL,
  description TEXT,
  status ENUM('pending','approved','rejected') DEFAULT 'pending',
  admin_remarks TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (employee_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Service Requests
CREATE TABLE IF NOT EXISTS service_requests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  asset_id INT NOT NULL,
  employee_id INT NOT NULL,
  problem_description TEXT NOT NULL,
  problem_since DATE,
  impact_on_work TEXT,
  approx_amount DECIMAL(10,2),
  status ENUM('pending','approved','rejected','completed') DEFAULT 'pending',
  service_bill VARCHAR(255),
  remarks TEXT,
  admin_remarks TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE CASCADE,
  FOREIGN KEY (employee_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Asset Transfer Consent
CREATE TABLE IF NOT EXISTS transfer_consent (
  id INT AUTO_INCREMENT PRIMARY KEY,
  asset_id INT NOT NULL,
  from_user INT NOT NULL,
  to_user INT NOT NULL,
  receive_date DATE,
  consent_given TINYINT(1) DEFAULT 0,
  status ENUM('pending','accepted') DEFAULT 'pending',
  accepted_at TIMESTAMP NULL DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Notifications
CREATE TABLE IF NOT EXISTS notifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,
  type VARCHAR(50),
  message TEXT,
  link VARCHAR(255),
  is_read TINYINT(1) DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Activity Logs
CREATE TABLE IF NOT EXISTS activity_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT,
  action VARCHAR(100),
  description TEXT,
  ip_address VARCHAR(50),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- NOTE: Run database/seed.php to insert the default admin user
-- Default admin credentials: admin@buzznationmarketing.com / Admin@123

-- Migration: add accepted_at to transfer_consent (safe to run on existing databases)
ALTER TABLE transfer_consent ADD COLUMN IF NOT EXISTS accepted_at TIMESTAMP NULL DEFAULT NULL AFTER status;
