CREATE DATABASE coop_system;

USE coop_system;

-- ============================================================
-- MEMBERS: personal + membership profile info (Manager-owned)
-- A member may or may not have a linked user account yet.
-- ============================================================
CREATE TABLE members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    membership_id VARCHAR(20) UNIQUE NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    middle_name VARCHAR(100) NULL,
    gender ENUM('Male', 'Female') NOT NULL,
    address VARCHAR(255) NOT NULL,
    contact_number VARCHAR(20) NOT NULL,
    membership_type ENUM('Regular', 'Associate') NOT NULL,
    date_joined DATE NOT NULL,

    -- Farming profile
    farmer_type ENUM('Livestock', 'Crops', 'Both') NULL,
    livestock_details VARCHAR(255) NULL,
    crops_details VARCHAR(255) NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ============================================================
-- USERS: login accounts (Admin-owned). Optionally linked to a
-- member profile (member_id). Admin/Manager staff accounts can
-- have member_id = NULL since they're not coop members.
-- ============================================================
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    member_id INT NULL UNIQUE,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'manager', 'user') DEFAULT 'user',
    account_status ENUM('Active', 'Inactive') DEFAULT 'Active',

    -- Email verification fields
    is_verified TINYINT(1) DEFAULT 0,
    verification_token VARCHAR(64),
    email_verification_expires DATETIME NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE SET NULL
);

-- Insert sample staff accounts (password is 'password123' for all)
-- These are staff (admin/manager) with no member profile attached.
INSERT INTO users (member_id, email, password, role, account_status, is_verified) VALUES
(NULL, 'admin@example.com', '$2y$10$HNfhClczEWBxcFuJwP53iu2Y75Tba7IEtmX8vX.1tp0dZ5EVt9CbO', 'admin', 'Active', 1),
(NULL, 'manager@example.com', '$2y$10$HNfhClczEWBxcFuJwP53iu2Y75Tba7IEtmX8vX.1tp0dZ5EVt9CbO', 'manager', 'Active', 1);

-- Sample member + linked regular user account, for testing
INSERT INTO members (membership_id, last_name, first_name, middle_name, gender, address, contact_number, membership_type, date_joined, farmer_type, livestock_details, crops_details)
VALUES ('SJFMC-0001', 'Dela Cruz', 'Juan', 'Santos', 'Male', 'Cagayan de Oro City', '09171234567', 'Regular', CURDATE(), 'Both', 'Chicken, Goat', 'Rice, Corn');

INSERT INTO users (member_id, email, password, role, account_status, is_verified)
VALUES (LAST_INSERT_ID(), 'user@example.com', '$2y$10$HNfhClczEWBxcFuJwP53iu2Y75Tba7IEtmX8vX.1tp0dZ5EVt9CbO', 'user', 'Active', 1);

CREATE TABLE IF NOT EXISTS activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,  -- NULL for failed login attempts
    email VARCHAR(255),
    action VARCHAR(50) NOT NULL,
    status ENUM('success', 'failed') DEFAULT 'success',
    ip_address VARCHAR(45),
    user_agent VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_user_id (user_id),
    INDEX idx_action (action),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- MIGRATION (run this instead of the CREATE TABLE statements
-- above if you already have a coop_system database with data
-- you want to keep - e.g. from the previous single-table version)
-- ============================================================
-- CREATE TABLE members (
--     id INT AUTO_INCREMENT PRIMARY KEY,
--     membership_id VARCHAR(20) UNIQUE NOT NULL,
--     last_name VARCHAR(100) NOT NULL,
--     first_name VARCHAR(100) NOT NULL,
--     middle_name VARCHAR(100) NULL,
--     gender ENUM('Male', 'Female') NOT NULL,
--     address VARCHAR(255) NOT NULL,
--     contact_number VARCHAR(20) NOT NULL,
--     membership_type ENUM('Regular', 'Associate') NOT NULL,
--     date_joined DATE NOT NULL,
--     created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
--     updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
-- );
--
-- ALTER TABLE users ADD COLUMN member_id INT NULL UNIQUE AFTER id;
-- ALTER TABLE users ADD CONSTRAINT fk_users_member FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE SET NULL;
-- -- If you previously ran the single-table migration (account_status, membership_id, names, etc. on `users`),
-- -- move that data into `members` first, then drop those columns from `users`:
-- -- ALTER TABLE users DROP COLUMN membership_id, DROP COLUMN last_name, DROP COLUMN first_name,
-- --   DROP COLUMN middle_name, DROP COLUMN gender, DROP COLUMN address, DROP COLUMN contact_number,
-- --   DROP COLUMN membership_type, DROP COLUMN date_joined;
-- ALTER TABLE users ADD COLUMN account_status ENUM('Active', 'Inactive') DEFAULT 'Active' AFTER role;
-- ALTER TABLE members ADD COLUMN farmer_type ENUM('Livestock', 'Crops', 'Both') NULL AFTER date_joined;
-- ALTER TABLE members ADD COLUMN livestock_details VARCHAR(255) NULL AFTER farmer_type;
-- ALTER TABLE members ADD COLUMN crops_details VARCHAR(255) NULL AFTER livestock_details;

-- Run this once against your database before using the updated forms.

ALTER TABLE members
    ADD COLUMN date_of_birth DATE NULL AFTER gender,
    ADD COLUMN occupation VARCHAR(100) NULL AFTER date_of_birth,
    ADD COLUMN hectares_cultivated VARCHAR(20) NULL AFTER date_joined;






    -- ============================================================
-- PAYMENTS & ASSEMBLY MEETING ATTENDANCE SCHEMA
-- Para sa SJFMC Coop System
-- ============================================================

-- 1. Payments table
-- Bawat row = isang payment entry (registration o investment)
CREATE TABLE payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    member_id INT NOT NULL,
    payment_type ENUM('registration', 'investment') NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    payment_date DATE NOT NULL,
    recorded_by INT NOT NULL,
    notes VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE,
    FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE RESTRICT,

    INDEX idx_member_type (member_id, payment_type)
);

-


-- ============================================================
-- MIGRATION: Idagdag ang status sa payments table
-- Para sa pending/confirmed workflow ng member-submitted payments
-- ============================================================

ALTER TABLE payments
ADD COLUMN status ENUM('pending', 'confirmed') NOT NULL DEFAULT 'confirmed' AFTER notes;

-- Note: 'confirmed' ang default, para lahat ng EXISTING records
-- (na dating ni-record na ng manager mismo) ay awtomatikong "confirmed"
-- Bagong records na galing sa member submission lang ang magiging 'pending'

-- ============================================================
-- MIGRATION: Idagdag ang attendance_finalized sa assembly_meetings
-- Kapag naka-set na ito, permanenteng naka-lock/view-only na
-- ang attendance checklist ng meeting na yun.
-- ============================================================

CREATE TABLE meetings (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title         VARCHAR(150) NOT NULL,
    meeting_date  DATE NOT NULL,
    `time`        TIME NOT NULL,
    location      VARCHAR(150) DEFAULT NULL,
    description   TEXT DEFAULT NULL,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE IF NOT EXISTS meeting_attendance (
    id           INT(11) NOT NULL AUTO_INCREMENT,
    meeting_id   INT(11) NOT NULL,
    member_id    INT(11) NOT NULL,
    recorded_by  INT(11) DEFAULT NULL,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_meeting_member (meeting_id, member_id),
    CONSTRAINT fk_attendance_meeting FOREIGN KEY (meeting_id) REFERENCES meetings(id) ON DELETE CASCADE,
    CONSTRAINT fk_attendance_member FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


ALTER TABLE payments 
MODIFY COLUMN payment_type ENUM('registration','investment','rental') NOT NULL;


ALTER TABLE members ADD COLUMN farmer_type ENUM('Livestock', 'Crops', 'Both') NULL AFTER date_joined;
ALTER TABLE members ADD COLUMN livestock_details VARCHAR(255) NULL AFTER farmer_type;
ALTER TABLE members ADD COLUMN crops_details VARCHAR(255) NULL AFTER livestock_details;


--Equipment Page--


CREATE TABLE IF NOT EXISTS equipment (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(100) NOT NULL,
    rate_member     DECIMAL(10,2) NOT NULL DEFAULT 0,
    rate_nonmember  DECIMAL(10,2) NOT NULL DEFAULT 0,
    unit_type       VARCHAR(20) NOT NULL DEFAULT 'day',
    status          ENUM('available', 'unavailable') NOT NULL DEFAULT 'available',
    photo_path      VARCHAR(255) NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS equipment_bookings (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    equipment_id      INT NOT NULL,
    user_id           INT NULL,
    renter_name       VARCHAR(150) NOT NULL,
    membership_type   ENUM('member', 'nonmember') NOT NULL DEFAULT 'member',
    quantity          DECIMAL(10,2) NOT NULL DEFAULT 1,
    start_date        DATE NOT NULL,
    end_date          DATE NOT NULL,
    status            ENUM('pending', 'ongoing', 'overdue', 'returned', 'rejected') NOT NULL DEFAULT 'pending',
    total_cost        DECIMAL(10,2) NOT NULL DEFAULT 0,
    created_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (equipment_id) REFERENCES equipment(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,

    INDEX idx_equipment_bookings_dates (start_date, end_date),
    INDEX idx_equipment_bookings_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--loan Page--

CREATE TABLE loans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    member_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    purpose VARCHAR(255) DEFAULT NULL,
    status ENUM('pending', 'approved', 'rejected', 'released') NOT NULL DEFAULT 'pending',
    approved_by INT DEFAULT NULL,
    manager_note VARCHAR(255) DEFAULT NULL,
    released_at DATETIME DEFAULT NULL,
    term_months INT NULL,
    due_date DATE NULL,
    interest_rate DECIMAL(5,2) NULL,
    total_due DECIMAL(10,2) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,

    INDEX idx_member_status (member_id, status)
);