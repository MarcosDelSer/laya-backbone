-- ============================================================================
-- 018-activate-laya-theme-rollback.sql
--
-- Reverts the LAYA theme activation and restores the Default theme.
-- This script is idempotent and can be safely re-run.
--
-- Apply: Use 018-activate-laya-theme.sql to re-activate the LAYA theme
-- ============================================================================

-- ----------------------------------------------------------------------------
-- 1. Set Default as the active theme in system settings
-- ----------------------------------------------------------------------------
UPDATE gibbonSetting
SET value = 'Default'
WHERE scope = 'System' AND name = 'gibbonThemeName';

-- ----------------------------------------------------------------------------
-- 2. Set default theme color back to purple (Default theme's color)
-- ----------------------------------------------------------------------------
UPDATE gibbonSetting
SET value = 'purple'
WHERE scope = 'System' AND name = 'themeColour';

-- ----------------------------------------------------------------------------
-- 3. Activate the Default theme in gibbonTheme table
-- ----------------------------------------------------------------------------
UPDATE gibbonTheme
SET active = 'Y'
WHERE name = 'Default';

-- ----------------------------------------------------------------------------
-- 4. Deactivate the LAYA theme (if it exists)
-- ----------------------------------------------------------------------------
UPDATE gibbonTheme
SET active = 'N'
WHERE name = 'LAYA';

-- ----------------------------------------------------------------------------
-- 5. Remove the LAYA theme record from the database
-- ----------------------------------------------------------------------------
DELETE FROM gibbonTheme
WHERE name = 'LAYA';

-- ============================================================================
-- Verification queries (can be run manually to confirm rollback)
-- ============================================================================
-- SELECT name, active FROM gibbonTheme;
-- SELECT name, value FROM gibbonSetting WHERE name IN ('gibbonThemeName', 'themeColour');
