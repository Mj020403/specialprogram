
CREATE TABLE IF NOT EXISTS programs (
 program_id INT AUTO_INCREMENT PRIMARY KEY,
 program_name VARCHAR(150) NOT NULL,
 description TEXT,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS program_items (
 item_id INT AUTO_INCREMENT PRIMARY KEY,
 program_id INT,
 item_name VARCHAR(150),
 description TEXT,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (program_id) REFERENCES programs(program_id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS household_special_programs (
 application_id INT AUTO_INCREMENT PRIMARY KEY,
 household_id INT,
 program_id INT,
 item_id INT,
 applicant_contact VARCHAR(100),
 land_location TEXT,
 land_area_text VARCHAR(100),
 ownership_type VARCHAR(50),
 validation_notes TEXT,
 program_name VARCHAR(150),
 item_name VARCHAR(150),
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS orientation_events (
 orientation_id INT AUTO_INCREMENT PRIMARY KEY,
 program_id INT,
 orientation_date DATE,
 location VARCHAR(150),
 notes TEXT,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
