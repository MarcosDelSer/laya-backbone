# Example Module Action Pages Overview

This document describes all action pages created for the Example Module.

## Main Pages

### 1. exampleModule.php (Dashboard)
- **Action**: Example Dashboard
- **Permission**: Admin, Teacher, Support Staff
- **Description**: Main entry point showing statistics, recent items, and quick actions
- **Features**:
  - Statistics dashboard (total, active, pending, inactive counts)
  - Recent items table using DataTable
  - Quick action buttons for navigation

### 2. exampleModule_manage.php (Manage Items List)
- **Action**: Manage Example Items
- **Permission**: Admin, Teacher
- **Description**: List view with CRUD operations
- **Features**:
  - Status filter (Active/Pending/Inactive/All)
  - Paginated DataTable with sorting
  - Edit and Delete action buttons
  - Add new item header action

### 3. exampleModule_manage_add.php (Add New Item Form)
- **Action**: Manage Example Items
- **Permission**: Admin, Teacher
- **Description**: Form to create new items
- **Features**:
  - Title field (required, max 100 chars)
  - Description textarea (max 1000 chars)
  - Status dropdown (Active/Pending/Inactive)
  - Person selector (using Gibbon's selectUsers)
  - Form validation
- **Process**: exampleModule_manage_addProcess.php

### 4. exampleModule_manage_edit.php (Edit Item Form)
- **Action**: Manage Example Items
- **Permission**: Admin, Teacher
- **Description**: Form to edit existing items
- **Features**:
  - Pre-populated form using loadAllValuesFrom()
  - Same fields as add form
  - Record existence validation
- **Process**: exampleModule_manage_editProcess.php

### 5. exampleModule_manage_delete.php (Delete Confirmation)
- **Action**: Manage Example Items
- **Permission**: Admin, Teacher
- **Description**: Confirmation page for deletion
- **Features**:
  - Shows item details before deletion
  - Uses Gibbon's DeleteForm prefab
  - Yellow warning styling
- **Process**: exampleModule_manage_deleteProcess.php

### 6. exampleModule_view.php (Read-Only View)
- **Action**: View Example Items
- **Permission**: All roles (Admin, Teacher, Student, Parent, Support)
- **Description**: Read-only list view
- **Features**:
  - Status filter
  - Paginated DataTable
  - No edit/delete actions
  - Shows full description with line breaks

### 7. exampleModule_settings.php (Module Settings)
- **Action**: Example Settings
- **Permission**: Admin only
- **Description**: Configure module-wide settings
- **Features**:
  - Enable/disable feature toggle
  - Maximum items per page setting (10-200)
  - Settings stored in gibbonSetting table
- **Process**: exampleModule_settingsProcess.php

## Security Features

All action pages implement:
1. ✓ **GPL-3.0 license header** - Full license block in every file
2. ✓ **Hard-coded permission addresses** - isActionAccessible() uses literal strings (never variables)
3. ✓ **XSS prevention** - htmlspecialchars() and Format:: methods for all output
4. ✓ **SQL injection prevention** - All database operations via Gateway with parameterized queries
5. ✓ **Form validation** - Required fields, maxLength, input types
6. ✓ **Access control** - Permission checks on every page

## Form Processing Pattern

Each form follows Gibbon's standard pattern:
- **Display page** (e.g., exampleModule_manage_add.php) - Shows form with Form API
- **Process page** (e.g., exampleModule_manage_addProcess.php) - Handles submission, validates, saves to DB
- **Process pages**:
  - Check permissions
  - Validate input
  - Use Gateway for database operations
  - Redirect with success/error codes
  - Use parameterized queries (via Gateway)

## Breadcrumb Navigation

All pages implement proper breadcrumbs using the page object and chained add() methods for hierarchical navigation.

## DataTable Usage

Pages using DataTable (following Gibbon patterns):
- **exampleModule.php** - Recent items (10 per page)
- **exampleModule_manage.php** - Manage items with actions (50 per page)
- **exampleModule_view.php** - Read-only view (50 per page)

All tables support:
- Sorting
- Pagination
- Custom formatting (status badges, dates, names)
- Responsive design

## Return Codes

Standard Gibbon return codes used:
- `return=success0` - Operation successful
- `return=error0` - Permission denied
- `return=error1` - Missing required parameters
- `return=error2` - Database operation failed

## Integration with Gateway

All database operations use ExampleEntityGateway:
- `queryExampleEntities()` - List with criteria
- `queryExampleEntitiesByStatus()` - Filtered by status
- `selectExampleEntityByID()` - Get single record
- `insertExampleEntity()` - Create new record
- `updateExampleEntity()` - Update existing record
- `deleteExampleEntity()` - Delete record
- `getStatistics()` - Dashboard stats

## File Naming Convention

Follows Gibbon standard:
- `moduleName.php` - Main/dashboard page
- `moduleName_action.php` - Secondary pages
- `moduleName_action_operation.php` - CRUD forms (add, edit, delete)
- `moduleName_action_operationProcess.php` - Form processing scripts

All pages are production-ready and follow Gibbon v30 best practices.
