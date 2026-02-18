# Example Module - Gibbon Module Template

⚠️ **THIS IS A TEMPLATE MODULE** - Copy and adapt for your own module development.

## Purpose

This module serves as a reference template demonstrating Gibbon module development patterns and best practices. It shows the correct structure, file organization, and coding conventions for creating custom Gibbon modules.

## How to Use This Template

1. **Copy the entire ExampleModule directory** to a new name:
   ```bash
   cp -r modules/ExampleModule modules/YourModuleName
   ```

2. **Update manifest.php** with your module details:
   - Change `$name`, `$description`, `$entryURL`, `$category`, `$author`, `$url`
   - Define your database tables in `$moduleTables`
   - Configure your gibbonSettings in `$gibbonSetting`
   - Set up your action pages and permissions in `$actionRows`

3. **Update namespace references**:
   - In `src/Domain/` classes, change namespace from `Gibbon\Module\ExampleModule\Domain` to `Gibbon\Module\YourModuleName\Domain`
   - Update table names from `gibbonExampleEntity` to `gibbonYourEntity`

4. **Implement CHANGEDB.php** migrations:
   - Uncomment and adapt the example migrations
   - Add your table creation statements
   - Include gibbonSetting INSERT statements
   - Follow the one-way migration pattern (never edit existing lines)

5. **Update version.php** with your starting version

6. **Create Gateway classes** in `src/Domain/`:
   - Extend `QueryableGateway`
   - Use `TableAware` trait
   - Implement CRUD methods with parameterized queries

7. **Create action pages** (the actual PHP files users interact with):
   - Follow the naming convention: `yourModuleName_action.php`
   - Use `isActionAccessible()` with hard-coded addresses (security requirement)
   - Use Gibbon's Form API for user input
   - Integrate with your Gateway classes for data access

## Key Files

| File | Purpose |
|------|---------|
| `manifest.php` | Module metadata, actions, permissions, database schema |
| `CHANGEDB.php` | One-way database migrations |
| `version.php` | Code version tracking |
| `src/Domain/*.php` | QueryableGateway data access classes (auto-loaded) |
| `yourModule_*.php` | Action pages (user-facing pages) |

## Gibbon Module Patterns Demonstrated

### 1. Manifest Pattern
- GPL-3.0 license block (mandatory)
- Module metadata ($name, $description, $version, etc.)
- Database table definitions ($moduleTables)
- Settings configuration ($gibbonSetting)
- Action definitions with permissions ($actionRows)
- Optional hooks integration ($hooks)

### 2. Database Migration Pattern
- One-way migrations only (append, never edit)
- Each statement ends with `;end`
- Use utf8mb4_unicode_ci collation
- CREATE TABLE IF NOT EXISTS for safety
- ON DUPLICATE KEY UPDATE for settings

### 3. Gateway Pattern (in src/Domain/)
- Extend QueryableGateway
- Use TableAware trait
- Parameterized queries (security requirement)
- Leverage Aura\SqlQuery for complex queries

### 4. Security Pattern
- Hard-coded addresses in isActionAccessible() (NEVER use variables)
- Parameterized PDO queries (prevent SQL injection)
- Input validation and sanitization
- GPL-3.0 license in all PHP files

### 5. Form API Pattern
- Use Form::create() to initialize forms
- Add rows with labels and inputs
- Use built-in validation (required(), maxLength(), etc.)
- loadAllValuesFrom() for edit forms

## Critical Security Requirements

⚠️ **MUST FOLLOW**:

1. **SQL Injection Prevention**: ALL database queries must use parameterized binding
   ```php
   // GOOD ✓
   $data = ['id' => $id];
   $sql = "SELECT * FROM table WHERE id = :id";
   $result = $this->db()->selectOne($sql, $data);

   // BAD ✗
   $sql = "SELECT * FROM table WHERE id = $id";
   ```

2. **Permission Check Security**: isActionAccessible() addresses MUST be hard-coded
   ```php
   // GOOD ✓
   if (!isActionAccessible($guid, $connection2, '/modules/YourModule/page.php')) {
       exit;
   }

   // BAD ✗ - NEVER USE VARIABLES!
   if (!isActionAccessible($guid, $connection2, $dynamicPath)) {
       exit;
   }
   ```

3. **License Requirement**: GPL-3.0 license block required in all PHP files

4. **Database Collation**: Use utf8mb4_unicode_ci for all tables (project standard)

## Development Workflow

1. **Setup**: Copy and rename module
2. **Plan**: Define your data model and actions
3. **Manifest**: Configure manifest.php
4. **Database**: Create CHANGEDB.php migrations
5. **Gateway**: Implement data access layer
6. **Actions**: Create user-facing pages
7. **Test**: Install in Gibbon and verify
8. **Iterate**: Add features incrementally

## Installation

1. Copy module to `modules/` directory
2. Log in to Gibbon as Admin
3. Go to System Admin > Manage Modules
4. Find your module and click Install
5. Run database migrations (CHANGEDB.php)
6. Configure permissions for different user roles
7. Module appears in navigation based on permissions

## Reference Modules

Study these existing modules for real-world examples:

- **CareTracking**: Comprehensive module with multiple tables, Gateways, and actions
- **AISync**: Simpler module good for basic patterns
- **PhotoManagement**: Example of file handling

## Resources

- Gibbon Developer Docs: https://docs.gibbonedu.org/developers
- Gibbon GitHub: https://github.com/GibbonEdu/core
- GPL-3.0 License: https://www.gnu.org/licenses/gpl-3.0.en.html

## Notes

- This is a framework template - actual implementation depends on your requirements
- Always backup database before running migrations
- Test in development environment before deploying to production
- Follow Gibbon's coding standards and conventions
- Use existing modules as reference patterns
