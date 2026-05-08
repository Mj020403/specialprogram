CREATE TABLE IF NOT EXISTS final_validation_release_plan (
    release_plan_id INT PRIMARY KEY AUTO_INCREMENT,
    household_id INT,
    program_id INT,
    item_type VARCHAR(100),
    quantity INT,
    unit VARCHAR(50),
    estimated_release_date DATE,
    remarks TEXT,
    structure_ready TINYINT(1) DEFAULT 0,
    beneficiary_ready TINYINT(1) DEFAULT 0,
    storage_ready TINYINT(1) DEFAULT 0,
    implementation_ready TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS beneficiary_releases (
    release_id INT PRIMARY KEY AUTO_INCREMENT,
    beneficiary_id INT,
    program_id INT,
    item_type VARCHAR(100),
    quantity INT,
    release_date DATE,
    released_by INT,
    remarks TEXT,
    proof_photo VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS beneficiary_monitoring (
    monitoring_id INT PRIMARY KEY AUTO_INCREMENT,
    beneficiary_id INT,
    visit_date DATE,
    monitoring_type VARCHAR(100),
    remarks TEXT,
    status VARCHAR(50),
    photo VARCHAR(255),
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
