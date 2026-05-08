
-- HARVEST FULL SYSTEM UPGRADE SCRIPT
-- Consolidated upgrade file

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- Example normalization
ALTER TABLE users ADD COLUMN IF NOT EXISTS last_login_at DATETIME NULL;
ALTER TABLE households ADD COLUMN IF NOT EXISTS status VARCHAR(50) DEFAULT 'active';

-- Index improvements
CREATE INDEX IF NOT EXISTS idx_users_email ON users(email);
CREATE INDEX IF NOT EXISTS idx_households_status ON households(status);

-- Audit log table
CREATE TABLE IF NOT EXISTS system_audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    action VARCHAR(255),
    module VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

SET FOREIGN_KEY_CHECKS = 1;
