-- Migration: Add maintenance quantity to maintenance_logs
-- Date: 2025-10-28
-- Description: Allows tracking how many units are tied to a maintenance record

ALTER TABLE maintenance_logs
ADD COLUMN maintenance_quantity INT NOT NULL DEFAULT 1 AFTER severity;

-- Backfill existing rows
UPDATE maintenance_logs
SET maintenance_quantity = 1
WHERE maintenance_quantity IS NULL OR maintenance_quantity < 1;

-- Verify schema
DESCRIBE maintenance_logs;
