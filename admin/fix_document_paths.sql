-- Migration: normalize stored document_file paths
-- Replaces any Windows-style backslashes with forward slashes
-- Ensures paths do not start with leading slashes

UPDATE penalty_guidelines
SET document_file = TRIM(BOTH '/' FROM REPLACE(document_file, '\\', '/'))
WHERE document_file IS NOT NULL AND document_file <> '';

-- Optional: ensure base directory prefix is consistent
UPDATE penalty_guidelines
SET document_file = CONCAT('uploads/penalty_documents/', SUBSTRING_INDEX(document_file, '/', -1))
WHERE document_file IS NOT NULL
  AND document_file <> ''
  AND document_file NOT LIKE 'uploads/penalty_documents/%';
