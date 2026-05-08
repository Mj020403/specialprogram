ALTER TABLE household_special_programs ADD COLUMN IF NOT EXISTS scheduled_validation_date DATE NULL AFTER intake_notes;
ALTER TABLE household_special_programs ADD COLUMN IF NOT EXISTS first_validation_result VARCHAR(30) NULL AFTER scheduled_validation_date;
ALTER TABLE household_special_programs ADD COLUMN IF NOT EXISTS orientation_event_id BIGINT NULL AFTER first_validation_result;
ALTER TABLE household_special_programs ADD COLUMN IF NOT EXISTS orientation_attendance_status VARCHAR(20) NULL AFTER orientation_event_id;
ALTER TABLE household_special_programs ADD COLUMN IF NOT EXISTS orientation_attended_at DATETIME NULL AFTER orientation_attendance_status;
ALTER TABLE household_special_programs ADD COLUMN IF NOT EXISTS final_validation_date DATE NULL AFTER orientation_attended_at;
ALTER TABLE household_special_programs ADD COLUMN IF NOT EXISTS final_validation_result VARCHAR(30) NULL AFTER final_validation_date;
ALTER TABLE household_special_programs ADD COLUMN IF NOT EXISTS approved_chicks_qty INT NULL AFTER final_validation_result;
ALTER TABLE household_special_programs ADD COLUMN IF NOT EXISTS seminar_event_id BIGINT NULL AFTER approved_chicks_qty;
ALTER TABLE household_special_programs ADD COLUMN IF NOT EXISTS seminar_attendance_status VARCHAR(20) NULL AFTER seminar_event_id;
ALTER TABLE household_special_programs ADD COLUMN IF NOT EXISTS seminar_attended_at DATETIME NULL AFTER seminar_attendance_status;
ALTER TABLE household_special_programs ADD COLUMN IF NOT EXISTS release_date DATE NULL AFTER seminar_attended_at;
ALTER TABLE household_special_programs ADD COLUMN IF NOT EXISTS released_chicks_qty INT NULL AFTER release_date;
ALTER TABLE household_special_programs ADD COLUMN IF NOT EXISTS monitoring_day VARCHAR(20) NULL AFTER released_chicks_qty;
ALTER TABLE household_special_programs ADD COLUMN IF NOT EXISTS next_monitoring_date DATE NULL AFTER monitoring_day;

ALTER TABLE events ADD COLUMN IF NOT EXISTS event_type VARCHAR(40) NULL AFTER event_name;
ALTER TABLE events ADD COLUMN IF NOT EXISTS attendance_closed_at DATETIME NULL AFTER event_status;

UPDATE household_special_programs sp
JOIN special_programs p ON p.program_id = sp.program_id
SET sp.application_status = 'Pending First Validation'
WHERE p.program_name = 'Gamefowl' AND sp.application_status = 'Pending Validation';

UPDATE household_special_programs sp
JOIN special_programs p ON p.program_id = sp.program_id
SET sp.application_status = 'Pending Final Validation'
WHERE p.program_name = 'Gamefowl'
  AND sp.application_status = 'Pending Validation'
  AND COALESCE(sp.orientation_attendance_status, '') IN ('Present','Late');
