-- Migration: Update photo_path column to store file paths instead of binary data
-- Date: 2025-11-26
-- Purpose: Change photo_path from LONGBLOB to VARCHAR to store file paths like equipment table

-- First, backup existing data if needed
-- CREATE TABLE users_photo_backup AS SELECT id, photo_path FROM users WHERE photo_path IS NOT NULL;

-- Update the photo_path column to VARCHAR
ALTER TABLE users MODIFY COLUMN photo_path VARCHAR(255) NULL;

-- Add index for better performance
CREATE INDEX idx_users_photo_path ON users(photo_path);

-- Update any existing longblob data to NULL (will need manual re-upload)
-- UPDATE users SET photo_path = NULL WHERE photo_path IS NOT NULL;

-- Add comment to column
ALTER TABLE users MODIFY COLUMN photo_path VARCHAR(255) NULL COMMENT 'File path to user profile photo (e.g., /Capstone/uploads/users/user_123.jpg)';
