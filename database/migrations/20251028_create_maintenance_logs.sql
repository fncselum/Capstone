-- Migration: Create maintenance_logs table for tracking equipment maintenance
-- Date: 2025-10-28
-- Description: Tracks maintenance records, repairs, and equipment condition updates

CREATE TABLE IF NOT EXISTS maintenance_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    equipment_id VARCHAR(50) NOT NULL,
    equipment_name VARCHAR(255) NOT NULL,
    maintenance_type ENUM('Repair', 'Preventive', 'Inspection', 'Cleaning', 'Calibration', 'Replacement') NOT NULL,
    issue_description TEXT NOT NULL,
    severity ENUM('Low', 'Medium', 'High', 'Critical') DEFAULT 'Medium',
    status ENUM('Pending', 'In Progress', 'Completed', 'Cancelled') DEFAULT 'Pending',
    reported_by VARCHAR(100) NOT NULL,
    reported_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    assigned_to VARCHAR(100) NULL,
    started_date DATETIME NULL,
    completed_date DATETIME NULL,
    resolution_notes TEXT NULL,
    cost DECIMAL(10,2) NULL,
    parts_replaced TEXT NULL,
    downtime_hours DECIMAL(5,2) NULL,
    before_condition ENUM('Excellent', 'Good', 'Fair', 'Poor', 'Out of Service') NULL,
    after_condition ENUM('Excellent', 'Good', 'Fair', 'Poor', 'Out of Service') NULL,
    next_maintenance_date DATE NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_equipment_id (equipment_id),
    INDEX idx_status (status),
    INDEX idx_maintenance_type (maintenance_type),
    INDEX idx_reported_date (reported_date),
    
    FOREIGN KEY (equipment_id) REFERENCES equipment(rfid_tag) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert sample maintenance records
INSERT INTO maintenance_logs (
    equipment_id, equipment_name, maintenance_type, issue_description, 
    severity, status, reported_by, assigned_to, before_condition
) VALUES
('2', 'Laptop', 'Repair', 'Screen flickering and battery not charging properly', 'High', 'In Progress', 'Admin', 'Tech Team A', 'Fair'),
('1', 'Keyboard', 'Cleaning', 'Keys are sticky and need thorough cleaning', 'Low', 'Pending', 'Admin', NULL, 'Good'),
('602', 'Mouse', 'Inspection', 'Regular maintenance inspection', 'Low', 'Completed', 'Admin', 'Tech Team B', 'Good');

-- Update the completed record with resolution
UPDATE maintenance_logs 
SET 
    status = 'Completed',
    started_date = DATE_SUB(NOW(), INTERVAL 2 DAY),
    completed_date = DATE_SUB(NOW(), INTERVAL 1 DAY),
    resolution_notes = 'Inspection completed. All components working properly.',
    after_condition = 'Excellent',
    downtime_hours = 0.5
WHERE equipment_id = '602';

-- Verify the table
SELECT * FROM maintenance_logs ORDER BY reported_date DESC;
