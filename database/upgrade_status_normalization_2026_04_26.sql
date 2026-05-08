-- Matag-ob Harvest Platform database upgrade
-- Safe to run multiple times. This normalizes old/new program workflow statuses
-- so dashboards and golden-household summaries do not throw undefined-key warnings.

UPDATE household_special_programs
SET application_status = 'Pending First Validation'
WHERE application_status IN ('Pending', 'Pending Validation');

UPDATE household_special_programs
SET application_status = 'Declined'
WHERE application_status = 'Rejected';

-- Keep old Approved rows valid; the PHP system now also supports Approved, Active,
-- Completed, and Pending Release as qualified/ready statuses.
