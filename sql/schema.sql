-- ============================================================
-- CLINIC MANAGEMENT SYSTEM - DATABASE SCHEMA
-- ============================================================

CREATE DATABASE IF NOT EXISTS clinic_system;
USE clinic_system;

-- ------------------------------------------------------------
-- 1. PATIENTS TABLE
-- ------------------------------------------------------------
CREATE TABLE patients (
    patient_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    address TEXT,
    age INT,
    contact_number VARCHAR(20) NOT NULL,
    patient_type ENUM('Regular', 'Monthly', 'Normal') DEFAULT 'Normal',
    allergies TEXT NULL,                     -- Displayed prominently for Regular patients
    monthly_fee DECIMAL(10,2) NULL,          -- Special fee for Regular/Monthly patients
    report_due_date DATE NULL,               -- Used for SMS reminder scheduling
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_name (name),
    INDEX idx_contact (contact_number)
);

-- ------------------------------------------------------------
-- 2. DOCTORS TABLE
-- ------------------------------------------------------------
CREATE TABLE doctors (
    doctor_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    specialization VARCHAR(150),
    default_fee DECIMAL(10,2) NOT NULL DEFAULT 0,   -- Auto-added to bill
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ------------------------------------------------------------
-- 3. VISITS TABLE (one row per patient visit / consultation)
-- ------------------------------------------------------------
CREATE TABLE visits (
    visit_id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    doctor_id INT NOT NULL,
    visit_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    diagnosis TEXT,
    consultation_fee DECIMAL(10,2) NOT NULL DEFAULT 0,
    status ENUM('With Doctor','Sent to Pharmacy','Billed','Completed') DEFAULT 'With Doctor',
    FOREIGN KEY (patient_id) REFERENCES patients(patient_id),
    FOREIGN KEY (doctor_id) REFERENCES doctors(doctor_id),
    INDEX idx_patient_visit (patient_id, visit_date)
);

-- ------------------------------------------------------------
-- 4. MEDICINES / INVENTORY TABLE
-- ------------------------------------------------------------
CREATE TABLE medicines (
    medicine_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    category VARCHAR(100),
    is_proprietary TINYINT(1) DEFAULT 0,      -- flags the "6 proprietary products" set
    unit_price DECIMAL(10,2) NOT NULL,
    cost_price DECIMAL(10,2) NULL,            -- wholesale/purchase cost, used for profit margin
    stock_qty INT NOT NULL DEFAULT 0,
    expiry_date DATE,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_medicine_name (name)
);

-- ------------------------------------------------------------
-- 4b. STOCK HISTORY (for week-over-week comparison)
-- ------------------------------------------------------------
CREATE TABLE stock_history (
    history_id INT AUTO_INCREMENT PRIMARY KEY,
    medicine_id INT NOT NULL,
    stock_qty INT NOT NULL,
    price_at_time DECIMAL(10,2) NOT NULL,
    recorded_date DATE NOT NULL,
    FOREIGN KEY (medicine_id) REFERENCES medicines(medicine_id),
    INDEX idx_med_date (medicine_id, recorded_date)
);

-- ------------------------------------------------------------
-- 4c. PRICE CHANGE LOG (mid-day price updates -> apply to ALL stock)
-- ------------------------------------------------------------
CREATE TABLE price_change_log (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    medicine_id INT NOT NULL,
    old_price DECIMAL(10,2) NOT NULL,
    new_price DECIMAL(10,2) NOT NULL,
    changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (medicine_id) REFERENCES medicines(medicine_id)
);

-- ------------------------------------------------------------
-- 5. PRESCRIPTIONS TABLE (medicines prescribed per visit)
-- ------------------------------------------------------------
CREATE TABLE prescriptions (
    prescription_id INT AUTO_INCREMENT PRIMARY KEY,
    visit_id INT NOT NULL,
    medicine_id INT NOT NULL,
    dosage VARCHAR(100),
    duration VARCHAR(100),
    quantity INT DEFAULT 1,
    unit_price DECIMAL(10,2) NOT NULL,        -- price captured at prescribing time
    removed_by_cashier TINYINT(1) DEFAULT 0,  -- cashier can remove item before invoice
    FOREIGN KEY (visit_id) REFERENCES visits(visit_id),
    FOREIGN KEY (medicine_id) REFERENCES medicines(medicine_id)
);

-- ------------------------------------------------------------
-- 6. INVOICES TABLE (final bill after cashier adjustments)
-- ------------------------------------------------------------
CREATE TABLE invoices (
    invoice_id INT AUTO_INCREMENT PRIMARY KEY,
    visit_id INT NOT NULL,
    patient_id INT NOT NULL,
    consultation_fee DECIMAL(10,2) NOT NULL DEFAULT 0,
    medicine_total DECIMAL(10,2) NOT NULL DEFAULT 0,
    grand_total DECIMAL(10,2) NOT NULL DEFAULT 0,
    payment_method ENUM('Cash','Card','Other') DEFAULT 'Cash',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (visit_id) REFERENCES visits(visit_id),
    FOREIGN KEY (patient_id) REFERENCES patients(patient_id)
);

-- ------------------------------------------------------------
-- 7. SMS REMINDERS LOG
-- ------------------------------------------------------------
CREATE TABLE sms_log (
    sms_id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    message TEXT,
    scheduled_for DATE,          -- report_due_date - 7 days
    sent_at TIMESTAMP NULL,
    status ENUM('Pending','Sent','Failed') DEFAULT 'Pending',
    FOREIGN KEY (patient_id) REFERENCES patients(patient_id)
);

-- ------------------------------------------------------------
-- 8. USERS TABLE (login for Reception / Doctor / Cashier roles)
-- ------------------------------------------------------------
CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('Reception','Doctor','Cashier','Admin') NOT NULL,
    linked_doctor_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (linked_doctor_id) REFERENCES doctors(doctor_id)
);

-- ------------------------------------------------------------
-- 9. QUEUE TOKENS TABLE (Reception -> Doctor waiting queue)
-- ------------------------------------------------------------
CREATE TABLE queue_tokens (
    token_id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    token_number INT NOT NULL,
    queue_date DATE NOT NULL,
    status ENUM('Waiting','With Doctor') DEFAULT 'Waiting',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(patient_id),
    INDEX idx_queue_date_status (queue_date, status, token_number)
);
