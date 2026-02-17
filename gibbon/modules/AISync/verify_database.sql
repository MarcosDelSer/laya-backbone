-- Database Verification Script for AISync Module
-- Run this script to verify that the database migration was successful

-- ============================================================================
-- STEP 1: Verify table exists
-- ============================================================================
SHOW TABLES LIKE 'gibbonAISyncLog';
-- Expected: 1 row returned showing the table exists

-- ============================================================================
-- STEP 2: Verify table schema
-- ============================================================================
DESCRIBE gibbonAISyncLog;
-- Expected columns:
-- - gibbonAISyncLogID: INT UNSIGNED, PRIMARY KEY, AUTO_INCREMENT
-- - eventType: VARCHAR(50), NOT NULL
-- - entityType: VARCHAR(50), NOT NULL
-- - entityID: INT UNSIGNED, NOT NULL
-- - payload: JSON, NULL
-- - status: ENUM('pending','success','failed'), NOT NULL, DEFAULT 'pending'
-- - response: TEXT, NULL
-- - retryCount: INT UNSIGNED, NOT NULL, DEFAULT 0
-- - errorMessage: TEXT, NULL
-- - timestampCreated: TIMESTAMP, DEFAULT CURRENT_TIMESTAMP
-- - timestampProcessed: DATETIME, NULL

-- ============================================================================
-- STEP 3: Verify indexes
-- ============================================================================
SHOW INDEXES FROM gibbonAISyncLog;
-- Expected indexes:
-- - PRIMARY KEY on gibbonAISyncLogID
-- - KEY eventType on eventType
-- - KEY entityType on entityType
-- - KEY status on status
-- - KEY timestampCreated on timestampCreated

-- ============================================================================
-- STEP 4: Verify settings
-- ============================================================================
SELECT * FROM gibbonSetting WHERE scope = 'AI Sync';
-- Expected 5 settings:
-- - aiServiceURL: 'http://ai-service:8000'
-- - syncEnabled: 'Y'
-- - maxRetryAttempts: '3'
-- - retryDelaySeconds: '30'
-- - webhookTimeout: '30'

-- ============================================================================
-- STEP 5: Test table operations
-- ============================================================================

-- Test INSERT
INSERT INTO gibbonAISyncLog (eventType, entityType, entityID, payload, status)
VALUES ('test_event', 'test_entity', 999, '{"test": true}', 'pending');

-- Test SELECT
SELECT * FROM gibbonAISyncLog WHERE entityType = 'test_entity';

-- Test UPDATE
UPDATE gibbonAISyncLog
SET status = 'success',
    response = 'Test successful',
    retryCount = retryCount + 1,
    timestampProcessed = NOW()
WHERE entityType = 'test_entity';

-- Verify UPDATE
SELECT * FROM gibbonAISyncLog WHERE entityType = 'test_entity';

-- Test DELETE (cleanup)
DELETE FROM gibbonAISyncLog WHERE entityType = 'test_entity';

-- Verify DELETE
SELECT * FROM gibbonAISyncLog WHERE entityType = 'test_entity';
-- Expected: 0 rows

-- ============================================================================
-- VERIFICATION COMPLETE
-- ============================================================================
-- If all queries above executed successfully, the database migration is complete.
