-- Task 023: Cross-Service Authentication Bridge
-- Token Exchange Logging and Audit Trail

-- Create table for authentication token exchange audit logs
CREATE TABLE IF NOT EXISTS gibbonAuthTokenLog (
    gibbonAuthTokenLogID INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    gibbonPersonID INT UNSIGNED NOT NULL COMMENT 'FK to gibbonPerson',
    username VARCHAR(255) NOT NULL,
    sessionID VARCHAR(255) NOT NULL COMMENT 'PHP session ID',
    tokenStatus ENUM('success', 'failed', 'expired') NOT NULL DEFAULT 'success',
    ipAddress VARCHAR(45) NULL COMMENT 'IPv4 or IPv6 address',
    userAgent TEXT NULL COMMENT 'Browser user agent',
    gibbonRoleIDPrimary CHAR(3) NULL COMMENT 'Primary role at time of token exchange',
    aiRole VARCHAR(50) NULL COMMENT 'Mapped AI service role',
    errorMessage TEXT NULL COMMENT 'Error message if token generation failed',
    expiresAt DATETIME NULL COMMENT 'Token expiration timestamp',
    timestampCreated DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX (gibbonPersonID),
    INDEX (username),
    INDEX (sessionID),
    INDEX (tokenStatus),
    INDEX (timestampCreated),
    INDEX (ipAddress)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Audit trail for JWT token exchanges from Gibbon sessions';

-- Add foreign key constraint (if gibbonPerson table exists)
-- This should be added conditionally in production
-- ALTER TABLE gibbonAuthTokenLog ADD CONSTRAINT fk_gibbonAuthTokenLog_gibbonPerson
--     FOREIGN KEY (gibbonPersonID) REFERENCES gibbonPerson(gibbonPersonID) ON DELETE CASCADE;
