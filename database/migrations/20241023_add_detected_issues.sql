-- Add detected_issues column to transactions table
ALTER TABLE transactions 
ADD COLUMN detected_issues TEXT DEFAULT NULL 
AFTER return_verification_status;

-- Update existing rows to have a default value
UPDATE transactions 
SET detected_issues = 'No issues detected during verification.'
WHERE return_verification_status = 'Verified' AND detected_issues IS NULL;
