# Gibbon Module Architecture Guide

## Table of Contents

1. [Overview](#overview)
2. [Architecture Goals](#architecture-goals)
3. [Architectural Layers](#architectural-layers)
4. [Design Patterns](#design-patterns)
5. [Module Structure](#module-structure)
6. [Refactored Modules](#refactored-modules)
7. [Best Practices](#best-practices)
8. [Testing Strategy](#testing-strategy)
9. [Migration Guide](#migration-guide)

## Overview

This guide documents the modernized architecture for Gibbon LAYA modules, specifically focusing on the refactored business logic layer. The architecture addresses common anti-patterns found in legacy PHP applications by introducing clear separation of concerns and testable business logic.

### What Changed?

**Before (Anti-pattern):**
```
module_page.php
  ├─ Database queries mixed in
  ├─ Business logic scattered
  ├─ Validation interspersed
  ├─ HTML rendering
  └─ All tightly coupled
```

**After (Service Layer Pattern):**
```
module_page.php (Presentation Layer)
  └─ Calls Service classes

Service Layer (Business Logic)
  ├─ Uses Gateway classes (Data)
  ├─ Uses Validator classes (Validation)
  └─ Contains pure business rules

Gateway Layer (Data Access)
  └─ Database queries only

Validator Layer (Input Validation)
  └─ Data validation and sanitization
```

## Architecture Goals

### Primary Objectives

1. **Separation of Concerns**: Each class has a single, well-defined responsibility
2. **Testability**: Business logic can be unit tested without database or UI
3. **Maintainability**: Clear structure makes code easier to understand and modify
4. **Reusability**: Business logic can be shared across multiple entry points
5. **Backward Compatibility**: Existing functionality continues to work during migration

### SOLID Principles Applied

- **Single Responsibility**: Services handle business logic, Gateways handle data, Validators handle validation
- **Open/Closed**: Services are open for extension (inheritance) but closed for modification
- **Dependency Inversion**: Services depend on abstractions (Gateways) not concrete implementations

## Architectural Layers

### 1. Presentation Layer (View Files)

**Location**: `modules/{ModuleName}/*.php`

**Responsibilities**:
- Handle HTTP request/response
- Render HTML/JSON output
- Call service methods
- Display validation errors

**Example**:
```php
// finance_invoice_add.php
$invoiceService = $container->get(InvoiceService::class);
$invoiceValidator = $container->get(InvoiceValidator::class);

// Validate input
$validation = $invoiceValidator->validate($_POST);
if (!$validation['success']) {
    // Display errors
    return;
}

// Use service for business logic
$invoice = $invoiceService->createInvoice([
    'gibbonPersonID' => $gibbonPersonID,
    'amount' => $amount,
    'dueDate' => $dueDate,
]);
```

**Anti-patterns to Avoid**:
- ❌ Business logic in view files
- ❌ Direct database queries
- ❌ Complex calculations
- ❌ Business rule enforcement

### 2. Service Layer (Business Logic)

**Location**: `modules/{ModuleName}/src/Service/*Service.php`

**Responsibilities**:
- Implement business rules
- Coordinate between gateways
- Perform calculations
- Orchestrate workflows
- Return structured results

**Key Characteristics**:
- Stateless (no instance variables storing business data)
- Dependency injection via constructor
- Returns arrays or value objects
- Throws exceptions for business rule violations
- Comprehensive PHPDoc

**Example**:
```php
namespace Gibbon\Module\EnhancedFinance\Service;

class InvoiceService
{
    protected $settingGateway;
    protected $invoiceGateway;
    protected $validator;

    public function __construct(
        SettingGateway $settingGateway,
        InvoiceGateway $invoiceGateway,
        InvoiceValidator $validator
    ) {
        $this->settingGateway = $settingGateway;
        $this->invoiceGateway = $invoiceGateway;
        $this->validator = $validator;
    }

    /**
     * Calculate total invoice amount with taxes.
     *
     * @param float $subtotal Invoice subtotal
     * @return array Array with breakdown of taxes and total
     */
    public function calculateTotal($subtotal)
    {
        $taxRates = $this->getTaxRates();

        $gst = $subtotal * $taxRates['gst'];
        $qst = $subtotal * $taxRates['qst'];
        $total = $subtotal + $gst + $qst;

        return [
            'subtotal' => $subtotal,
            'gst' => $gst,
            'qst' => $qst,
            'total' => $total,
        ];
    }
}
```

### 3. Gateway Layer (Data Access)

**Location**: `modules/{ModuleName}/Domain/*Gateway.php`

**Responsibilities**:
- Execute database queries
- Map database rows to arrays/objects
- Handle database transactions
- Return raw data (no business logic)

**Key Characteristics**:
- Extends Gibbon's `QueryableGateway`
- One gateway per database table/aggregate
- CRUD operations only
- No business logic
- Returns PDO results or arrays

**Example**:
```php
namespace Gibbon\Module\EnhancedFinance\Domain;

use Gibbon\Domain\QueryableGateway;

class InvoiceGateway extends QueryableGateway
{
    public function selectInvoicesByPerson($gibbonPersonID)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->where('gibbonPersonID = :gibbonPersonID')
            ->bindValue('gibbonPersonID', $gibbonPersonID);

        return $this->runSelect($query);
    }
}
```

### 4. Validator Layer (Input Validation)

**Location**: `modules/{ModuleName}/src/Validator/*Validator.php`

**Responsibilities**:
- Validate user input
- Sanitize data
- Check business constraints
- Return validation errors

**Key Characteristics**:
- No dependencies on gateways
- Pure validation logic
- Returns structured error arrays
- Stateless

**Example**:
```php
namespace Gibbon\Module\EnhancedFinance\Validator;

class InvoiceValidator
{
    protected $errors = [];

    public function validate($data)
    {
        $this->errors = [];

        if (empty($data['amount']) || $data['amount'] <= 0) {
            $this->errors['amount'] = 'Amount must be greater than zero';
        }

        if (empty($data['dueDate'])) {
            $this->errors['dueDate'] = 'Due date is required';
        }

        return [
            'success' => empty($this->errors),
            'errors' => $this->errors,
        ];
    }
}
```

## Design Patterns

### 1. Service Layer Pattern

See [SERVICE_LAYER_PATTERN.md](./SERVICE_LAYER_PATTERN.md) for detailed explanation.

**Summary**: Encapsulate business logic in service classes that coordinate between data access and presentation.

### 2. Gateway Pattern (Data Mapper)

**Purpose**: Isolate database access from business logic.

**Benefits**:
- Database changes don't affect business logic
- Easy to test business logic with mock gateways
- Clear data access API

### 3. Dependency Injection

**Purpose**: Provide dependencies to classes rather than having them create dependencies.

**Benefits**:
- Easier testing (inject mocks)
- Loose coupling
- Clear dependencies

**Example**:
```php
// Good: Dependencies injected
class InvoiceService
{
    public function __construct(
        SettingGateway $settingGateway,
        InvoiceGateway $invoiceGateway
    ) {
        $this->settingGateway = $settingGateway;
        $this->invoiceGateway = $invoiceGateway;
    }
}

// Bad: Creating dependencies
class InvoiceService
{
    public function __construct()
    {
        $this->settingGateway = new SettingGateway(); // Hard to test!
    }
}
```

### 4. Factory Pattern (Container)

**Purpose**: Create complex objects with their dependencies.

**Usage**: Gibbon's dependency injection container creates services with their dependencies.

```php
// Container automatically resolves dependencies
$invoiceService = $container->get(InvoiceService::class);
```

## Module Structure

### Standard Directory Layout

```
modules/
└── ModuleName/
    ├── Domain/                    # Data Access Layer
    │   ├── EntityGateway.php     # Database queries
    │   └── AnotherGateway.php
    ├── src/
    │   ├── Service/              # Business Logic Layer
    │   │   ├── CoreService.php
    │   │   └── HelperService.php
    │   └── Validator/            # Validation Layer
    │       └── EntityValidator.php
    ├── tests/
    │   └── Unit/                 # Unit Tests
    │       ├── CoreServiceTest.php
    │       └── HelperServiceTest.php
    ├── module_page.php           # View files
    └── manifest.php              # Module metadata
```

### File Naming Conventions

- **Services**: `{Entity}Service.php` (e.g., `InvoiceService.php`)
- **Validators**: `{Entity}Validator.php` (e.g., `InvoiceValidator.php`)
- **Gateways**: `{Entity}Gateway.php` (e.g., `InvoiceGateway.php`)
- **Tests**: `{ClassName}Test.php` (e.g., `InvoiceServiceTest.php`)

## Refactored Modules

The following modules have been refactored to follow this architecture:

### 1. EnhancedFinance

**Services**:
- `InvoiceService`: Tax calculations, invoice number generation, totals
- `PaymentService`: Payment processing, balance calculations
- `Releve24Service`: Quebec RL-24 tax document generation

**Validators**:
- `InvoiceValidator`: Invoice data validation

**Key Business Logic**:
- GST/QST tax calculations
- Invoice number generation with prefixes
- Due date calculation based on settings
- Payment balance tracking
- RL-24 eligibility and calculations

### 2. CareTracking

**Services**:
- `MealService`: Meal tracking and configuration
- `AttendanceService`: Check-in/check-out operations
- `IncidentService`: Incident tracking and parent notifications

**Validators**:
- `AttendanceValidator`: Attendance data validation

**Key Business Logic**:
- Meal type configuration
- Attendance time validation
- Incident severity determination
- Parent notification rules

### 3. PhotoManagement

**Services**:
- `PhotoAccessService`: Access control and permissions
- `PhotoTagService`: Photo tagging operations

**Key Business Logic**:
- Role-based photo access
- Parent-child relationship validation
- Photo retention policies
- Tagging permissions

### 4. NotificationEngine

**Services**:
- `DeliveryRulesService`: Retry logic and scheduling
- `PreferenceService`: User preference management
- `FCMService`: Firebase Cloud Messaging wrapper

**Key Business Logic**:
- Exponential backoff retry logic
- Delivery scheduling
- Queue health monitoring
- Preference validation

## Best Practices

### Service Design

✅ **DO**:
- Keep services focused on a single domain
- Use dependency injection
- Return structured arrays or value objects
- Add comprehensive PHPDoc
- Make methods stateless
- Use descriptive method names

❌ **DON'T**:
- Put database queries in services (use gateways)
- Store business data in instance variables
- Echo or print output
- Use global variables
- Mix business logic with presentation

### Gateway Design

✅ **DO**:
- Extend `QueryableGateway`
- Use query builder methods
- Return PDO results or arrays
- One gateway per table/aggregate
- Use prepared statements (via query builder)

❌ **DON'T**:
- Put business logic in gateways
- Use raw SQL strings (use query builder)
- Return formatted/calculated data
- Mix concerns from multiple tables

### Validator Design

✅ **DO**:
- Validate one entity type per validator
- Return structured error arrays
- Check all validation rules
- Use descriptive error messages
- Keep validators stateless

❌ **DON'T**:
- Access databases in validators
- Throw exceptions (return errors)
- Modify input data
- Depend on gateways or services

### View File Design

✅ **DO**:
- Call service methods for business logic
- Handle HTTP request/response
- Render HTML/JSON
- Display validation errors
- Keep files thin and focused

❌ **DON'T**:
- Implement business logic
- Execute database queries directly
- Perform complex calculations
- Enforce business rules

## Testing Strategy

### Unit Testing Services

**Goal**: Test business logic in isolation without database.

**Approach**:
1. Mock gateway dependencies
2. Test business logic methods
3. Verify calculations and rules
4. Test edge cases and error handling

**Example**:
```php
class InvoiceServiceTest extends TestCase
{
    public function testCalculateTotalWithTaxes()
    {
        // Mock dependencies
        $settingGateway = $this->createMock(SettingGateway::class);
        $settingGateway->method('getSettingByScope')
            ->willReturnMap([
                ['Enhanced Finance', 'gstRate', '0.05'],
                ['Enhanced Finance', 'qstRate', '0.09975'],
            ]);

        $invoiceGateway = $this->createMock(InvoiceGateway::class);
        $validator = $this->createMock(InvoiceValidator::class);

        // Create service
        $service = new InvoiceService(
            $settingGateway,
            $invoiceGateway,
            $validator
        );

        // Test business logic
        $result = $service->calculateTotal(100.00);

        $this->assertEquals(100.00, $result['subtotal']);
        $this->assertEquals(5.00, $result['gst']);
        $this->assertEquals(9.975, $result['qst']);
        $this->assertEquals(114.975, $result['total']);
    }
}
```

### Test Coverage Goals

- **Services**: >80% code coverage
- **Validators**: >90% code coverage
- **Gateways**: Integration tests only (not unit tests)
- **View Files**: Manual/E2E testing

## Migration Guide

### Migrating Legacy Code

Follow these steps to refactor existing code to the new architecture:

#### Step 1: Identify Business Logic

Review view files and identify:
- Calculations
- Business rules
- Data processing
- Workflow orchestration

#### Step 2: Create Service Class

```php
// 1. Create service class
namespace Gibbon\Module\ModuleName\Service;

class EntityService
{
    protected $gateway;

    public function __construct(EntityGateway $gateway)
    {
        $this->gateway = $gateway;
    }

    // 2. Extract business logic into methods
    public function calculateSomething($input)
    {
        // Business logic here
        return $result;
    }
}
```

#### Step 3: Create Tests

```php
// 3. Write unit tests
class EntityServiceTest extends TestCase
{
    public function testCalculateSomething()
    {
        $gateway = $this->createMock(EntityGateway::class);
        $service = new EntityService($gateway);

        $result = $service->calculateSomething(100);

        $this->assertEquals(150, $result);
    }
}
```

#### Step 4: Update View File

```php
// 4. Replace inline logic with service calls
// Before:
$tax = $amount * 0.05;
$total = $amount + $tax;

// After:
$service = $container->get(EntityService::class);
$result = $service->calculateTotal($amount);
$total = $result['total'];
```

#### Step 5: Remove Old Code

After verifying everything works, remove the old inline business logic.

### Backward Compatibility

During migration:
- Keep old code functional
- Services are additive (don't break existing code)
- Gradual migration of view files
- Run tests frequently
- Manual testing of affected pages

## Common Patterns

### Pattern: Calculation Service

**Use When**: You need to perform complex calculations

```php
class InvoiceService
{
    public function calculateTotal($subtotal)
    {
        $taxes = $this->calculateTaxes($subtotal);
        return $subtotal + array_sum($taxes);
    }

    protected function calculateTaxes($subtotal)
    {
        $rates = $this->getTaxRates();
        return [
            'gst' => $subtotal * $rates['gst'],
            'qst' => $subtotal * $rates['qst'],
        ];
    }
}
```

### Pattern: Workflow Service

**Use When**: You need to orchestrate multiple steps

```php
class PaymentService
{
    public function processPayment($paymentData)
    {
        // 1. Validate
        $validation = $this->validator->validate($paymentData);
        if (!$validation['success']) {
            return $validation;
        }

        // 2. Save payment
        $paymentID = $this->paymentGateway->insert($paymentData);

        // 3. Update invoice
        $this->invoiceGateway->update($paymentData['invoiceID'], [
            'amountPaid' => $this->calculateNewBalance($paymentData),
        ]);

        // 4. Return result
        return ['success' => true, 'paymentID' => $paymentID];
    }
}
```

### Pattern: Configuration Service

**Use When**: You need to provide configuration options

```php
class MealService
{
    public function getMealTypes()
    {
        return [
            'breakfast' => 'Breakfast',
            'lunch' => 'Lunch',
            'snack' => 'Snack',
        ];
    }

    public function getQuantityOptions()
    {
        return ['1/4', '1/2', '3/4', 'Full'];
    }
}
```

## Troubleshooting

### Issue: Circular Dependencies

**Problem**: Service A depends on Service B which depends on Service A.

**Solution**:
- Extract shared logic to a third service
- Use events/callbacks instead of direct dependencies
- Reconsider service boundaries

### Issue: Too Many Dependencies

**Problem**: Service constructor has 5+ parameters.

**Solution**:
- Service is doing too much (split it)
- Some dependencies might be gateways that should be combined
- Consider using a facade pattern

### Issue: Testing is Difficult

**Problem**: Can't test service without database.

**Solution**:
- Mock gateway dependencies
- Extract complex queries to gateway methods
- Ensure business logic is pure (no side effects)

## References

- [Service Layer Pattern Guide](./SERVICE_LAYER_PATTERN.md)
- [Module Development Guide](./MODULE_DEVELOPMENT_GUIDE.md) (see subtask-4-3)
- [Gibbon Core Documentation](https://docs.gibbonedu.org/)

## Conclusion

This architecture provides a solid foundation for maintainable, testable Gibbon modules. By following these patterns and best practices, developers can:

- Write cleaner, more focused code
- Test business logic effectively
- Reduce coupling between components
- Make future changes with confidence

The refactored modules (EnhancedFinance, CareTracking, PhotoManagement, NotificationEngine) serve as reference implementations of this architecture.
