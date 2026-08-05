-- Run this in phpMyAdmin (SQL tab) on your coop_system database.
-- Safe to run even if you're not sure whether you already added these columns -
-- MySQL will just show an error like "Duplicate column name" for any that already exist,
-- which you can ignore. Run each line separately if you want to skip errors easily.

ALTER TABLE members ADD COLUMN farmer_type ENUM('Livestock', 'Crops', 'Both') NULL AFTER date_joined;
ALTER TABLE members ADD COLUMN livestock_details VARCHAR(255) NULL AFTER farmer_type;
ALTER TABLE members ADD COLUMN crops_details VARCHAR(255) NULL AFTER livestock_details;

-- To check if they're already there instead of guessing, run this first:
-- SHOW COLUMNS FROM members LIKE 'farmer_type';
-- (if it returns a row, you already have it - skip the ALTER TABLE above)
