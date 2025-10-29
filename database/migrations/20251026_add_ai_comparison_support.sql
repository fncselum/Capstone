-- Phase 3: AI comparison scaffolding
-- Adds AI status columns to transactions and creates ai_comparison_jobs queue table

ALTER TABLE transactions
    ADD COLUMN ai_analysis_status ENUM('pending','processing','completed','failed') NULL DEFAULT NULL AFTER severity_level,
    ADD COLUMN ai_analysis_message VARCHAR(255) NULL DEFAULT NULL AFTER ai_analysis_status,
    ADD COLUMN ai_similarity_score FLOAT NULL DEFAULT NULL AFTER ai_analysis_message,
    ADD COLUMN ai_severity_level ENUM('none','medium','high','critical') NULL DEFAULT NULL AFTER ai_similarity_score,
    ADD COLUMN ai_analysis_meta LONGTEXT NULL DEFAULT NULL AFTER ai_severity_level;

CREATE TABLE IF NOT EXISTS ai_comparison_jobs (
    job_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    transaction_id BIGINT UNSIGNED NOT NULL,
    status ENUM('pending','processing','completed','failed') NOT NULL DEFAULT 'pending',
    priority TINYINT UNSIGNED NOT NULL DEFAULT 3,
    payload LONGTEXT NULL,
    result LONGTEXT NULL,
    error_message TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    processed_at TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (job_id),
    INDEX idx_ai_jobs_status_priority (status, priority, created_at),
    INDEX idx_ai_jobs_txn (transaction_id)
);
