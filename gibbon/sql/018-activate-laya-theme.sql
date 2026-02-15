-- ============================================================================
-- 018-activate-laya-theme.sql
--
-- Registers and activates the LAYA theme for Gibbon CMS.
-- This script is idempotent and can be safely re-run.
--
-- Rollback: Use 018-activate-laya-theme-rollback.sql to revert to Default theme
-- ============================================================================

-- ----------------------------------------------------------------------------
-- 1. Register the LAYA theme (or update if it already exists)
-- ----------------------------------------------------------------------------
INSERT INTO gibbonTheme (name, description, author, url, version, active)
VALUES (
    'LAYA',
    'Modern kindergarten-friendly theme for LAYA Childcare',
    'LAYA Childcare',
    '',
    '1.0.00',
    'Y'
)
ON DUPLICATE KEY UPDATE
    description = VALUES(description),
    author = VALUES(author),
    version = VALUES(version),
    active = 'Y';

-- ----------------------------------------------------------------------------
-- 2. Deactivate all other themes (only LAYA should be active)
-- ----------------------------------------------------------------------------
UPDATE gibbonTheme
SET active = 'N'
WHERE name != 'LAYA';

-- ----------------------------------------------------------------------------
-- 3. Set LAYA as the active theme in system settings
-- ----------------------------------------------------------------------------
UPDATE gibbonSetting
SET value = 'LAYA'
WHERE scope = 'System' AND name = 'gibbonThemeName';

-- ----------------------------------------------------------------------------
-- 4. Set default theme color to teal (warm, kindergarten-friendly)
-- ----------------------------------------------------------------------------
UPDATE gibbonSetting
SET value = 'teal'
WHERE scope = 'System' AND name = 'themeColour';

-- ============================================================================
-- Verification queries (can be run manually to confirm activation)
-- ============================================================================
-- SELECT name, active FROM gibbonTheme;
-- SELECT name, value FROM gibbonSetting WHERE name IN ('gibbonThemeName', 'themeColour');
