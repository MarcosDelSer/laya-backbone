# Service Layer Pattern Guide

## Table of Contents

1. [Introduction](#introduction)
2. [What is the Service Layer Pattern?](#what-is-the-service-layer-pattern)
3. [Why Use a Service Layer?](#why-use-a-service-layer)
4. [Service Layer in Gibbon](#service-layer-in-gibbon)
5. [Anatomy of a Service](#anatomy-of-a-service)
6. [Service Design Principles](#service-design-principles)
7. [Common Service Patterns](#common-service-patterns)
8. [Working with Dependencies](#working-with-dependencies)
9. [Error Handling](#error-handling)
10. [Testing Services](#testing-services)
11. [Real-World Examples](#real-world-examples)
12. [Anti-Patterns to Avoid](#anti-patterns-to-avoid)
13. [Migration Strategies](#migration-strategies)

## Introduction

The Service Layer pattern is a key architectural pattern that separates business logic from presentation and data access concerns. This guide provides a comprehensive reference for implementing and using services in Gibbon LAYA modules.

## What is the Service Layer Pattern?

The Service Layer pattern creates a well-defined boundary between:
- **Presentation Logic** (UI/View layer)
- **Business Logic** (Service layer) ← **This layer**
- **Data Access Logic** (Gateway/Repository layer)

### Visual Representation

```
┌─────────────────────────────────────┐
│     Presentation Layer (View)       │
│  - Handles HTTP requests/responses  │
│  - Renders UI                       │
│  - Minimal logic                    │
└─────────────┬───────────────────────┘
              │ calls
              ▼
┌─────────────────────────────────────┐
│      Service Layer (Business)       │  ← Service Layer Pattern
│  - Business rules                   │
│  - Calculations                     │
│  - Workflow orchestration           │
│  - Transaction boundaries           │
└─────────────┬───────────────────────┘
              │ uses
              ▼
┌─────────────────────────────────────┐
│     Gateway Layer (Data Access)     │
│  - Database queries                 │
│  - Data persistence                 │
│  - No business logic                │
└─────────────────────────────────────┘
```

### Key Characteristics

A Service Layer:
1. **Encapsulates** business logic
2. **Orchestrates** operations across multiple data sources
3. **Defines** transaction boundaries
4. **Provides** a clean API for the presentation layer
5. **Contains** no presentation or data access code

## Why Use a Service Layer?

### Problems Solved

#### Problem 1: Scattered Business Logic

**Before**:
```php
// finance_invoice_add.php (View file with embedded logic)
$gstRate = 0.05;
$qstRate = 0.09975;
$gst = $subtotal * $gstRate;
$qst = $subtotal * $qstRate;
$total = $subtotal + $gst + $qst;

// Later in another file...
// finance_invoice_view.php (Same logic duplicated!)
$gstRate = 0.05; // Duplication!
$qstRate = 0.09975;
// ... same calculation ...
```

**After**:
```php
// Any view file
$invoiceService = $container->get(InvoiceService::class);
$result = $invoiceService->calculateTotal($subtotal);
$total = $result['total'];
```

#### Problem 2: Hard to Test

**Before**: Can't test business logic without running the entire page with database.

**After**: Can unit test business logic in isolation with mocked dependencies.

#### Problem 3: Tight Coupling

**Before**: View files directly access database, making changes risky.

**After**: Views depend on stable service interfaces, not implementation details.

### Benefits

1. **Single Source of Truth**: Business rules live in one place
2. **Testability**: Unit test business logic without UI or database
3. **Reusability**: Same logic used by web UI, API, CLI, etc.
4. **Maintainability**: Clear organization makes code easier to understand
5. **Flexibility**: Easy to change implementation without breaking callers
6. **Transaction Management**: Clear boundaries for database transactions

## Service Layer in Gibbon

### Gibbon's Architecture Layers

```
View Files (*.php)
    ↓ uses
Service Layer (src/Service/*Service.php)
    ↓ uses
Validator Layer (src/Validator/*Validator.php)
    ↓ uses
Gateway Layer (Domain/*Gateway.php)
    ↓ uses
Database (PDO)
```

### Where Services Live

```
modules/ModuleName/
├── src/
│   └── Service/
│       ├── CoreService.php         # Main business logic
│       ├── CalculationService.php  # Specific calculations
│       └── WorkflowService.php     # Multi-step processes
```

### Namespace Convention

```php
namespace Gibbon\Module\ModuleName\Service;
```

## Anatomy of a Service

### Complete Service Example

```php
<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary.
Copyright © 2010, Gibbon Foundation
Gibbon™, Gibbon Education Ltd. (Hong Kong)

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.
*/

namespace Gibbon\Module\EnhancedFinance\Service;

use Gibbon\Domain\System\SettingGateway;
use Gibbon\Module\EnhancedFinance\Domain\InvoiceGateway;
use Gibbon\Module\EnhancedFinance\Validator\InvoiceValidator;

/**
 * InvoiceService
 *
 * Business logic service for invoice operations.
 * Handles tax calculations, invoice number generation, and invoice totals.
 *
 * @version v1.0.00
 * @since   v1.0.00
 */
class InvoiceService
{
    // ========================================
    // 1. DEPENDENCIES (Protected Properties)
    // ========================================

    /**
     * @var SettingGateway
     */
    protected $settingGateway;

    /**
     * @var InvoiceGateway
     */
    protected $invoiceGateway;

    /**
     * @var InvoiceValidator
     */
    protected $validator;

    // ========================================
    // 2. STATE (Cached Data Only)
    // ========================================

    /**
     * @var array|null Tax rate cache
     */
    protected $taxRates = null;

    // ========================================
    // 3. CONSTRUCTOR (Dependency Injection)
    // ========================================

    /**
     * Constructor.
     *
     * @param SettingGateway $settingGateway Settings gateway
     * @param InvoiceGateway $invoiceGateway Invoice gateway
     * @param InvoiceValidator $validator Invoice validator
     */
    public function __construct(
        SettingGateway $settingGateway,
        InvoiceGateway $invoiceGateway,
        InvoiceValidator $validator
    ) {
        $this->settingGateway = $settingGateway;
        $this->invoiceGateway = $invoiceGateway;
        $this->validator = $validator;
    }

    // ========================================
    // 4. PUBLIC METHODS (Business Logic API)
    // ========================================

    /**
     * Calculate invoice total with taxes.
     *
     * Applies GST and QST to the subtotal and returns a breakdown
     * of all amounts for display and storage.
     *
     * @param float $subtotal Invoice subtotal before taxes
     * @return array Array containing subtotal, gst, qst, and total
     */
    public function calculateTotal($subtotal)
    {
        $taxRates = $this->getTaxRates();

        $gst = $subtotal * $taxRates['gst'];
        $qst = $subtotal * $taxRates['qst'];
        $total = $subtotal + $gst + $qst;

        return [
            'subtotal' => round($subtotal, 2),
            'gst' => round($gst, 2),
            'qst' => round($qst, 2),
            'total' => round($total, 2),
        ];
    }

    /**
     * Generate next invoice number.
     *
     * Creates a unique invoice number using configured prefix and
     * sequential numbering.
     *
     * @return string Invoice number (e.g., "INV-2024-0001")
     */
    public function generateInvoiceNumber()
    {
        $prefix = $this->settingGateway->getSettingByScope(
            'Enhanced Finance',
            'invoiceNumberPrefix'
        ) ?: 'INV';

        $year = date('Y');
        $lastNumber = $this->invoiceGateway->getLastInvoiceNumber($year);
        $nextNumber = $lastNumber + 1;

        return sprintf('%s-%s-%04d', $prefix, $year, $nextNumber);
    }

    // ========================================
    // 5. PROTECTED METHODS (Internal Helpers)
    // ========================================

    /**
     * Get tax rates from settings with caching.
     *
     * @return array Tax rates [gst, qst, combined]
     */
    protected function getTaxRates()
    {
        if ($this->taxRates !== null) {
            return $this->taxRates;
        }

        $gstRate = $this->settingGateway->getSettingByScope(
            'Enhanced Finance',
            'gstRate'
        ) ?: '0.05';

        $qstRate = $this->settingGateway->getSettingByScope(
            'Enhanced Finance',
            'qstRate'
        ) ?: '0.09975';

        $this->taxRates = [
            'gst' => (float)$gstRate,
            'qst' => (float)$qstRate,
            'combined' => (float)$gstRate + (float)$qstRate,
        ];

        return $this->taxRates;
    }
}
```

### Service Components Explained

#### 1. Dependencies
```php
protected $settingGateway;
protected $invoiceGateway;
protected $validator;
```
- Injected via constructor
- Always protected (not private or public)
- Typically gateways, validators, or other services

#### 2. State (Minimal!)
```php
protected $taxRates = null; // Cache only
```
- Services should be mostly stateless
- Only cache data for performance
- Never store business data between method calls

#### 3. Constructor
```php
public function __construct(
    SettingGateway $settingGateway,
    InvoiceGateway $invoiceGateway,
    InvoiceValidator $validator
) {
    $this->settingGateway = $settingGateway;
    $this->invoiceGateway = $invoiceGateway;
    $this->validator = $validator;
}
```
- Type-hint all dependencies
- Document with PHPDoc
- No complex logic in constructor

#### 4. Public Methods
```php
public function calculateTotal($subtotal)
{
    // Business logic here
    return $result;
}
```
- Clean API for callers
- Return arrays or value objects
- Comprehensive PHPDoc

#### 5. Protected Methods
```php
protected function getTaxRates()
{
    // Internal helper
}
```
- Break down complex operations
- Not part of public API
- Can change without breaking callers

## Service Design Principles

### 1. Single Responsibility

Each service should focus on one domain or aggregate.

✅ **Good**:
```php
// InvoiceService.php - Handles invoice business logic
class InvoiceService
{
    public function calculateTotal($subtotal) { }
    public function generateInvoiceNumber() { }
    public function isOverdue($invoice) { }
}

// PaymentService.php - Handles payment business logic
class PaymentService
{
    public function processPayment($data) { }
    public function calculateBalance($invoiceID) { }
}
```

❌ **Bad**:
```php
// FinanceService.php - Too broad!
class FinanceService
{
    public function createInvoice($data) { }
    public function processPayment($data) { }
    public function generateTaxReport($year) { }
    public function sendReminder($invoiceID) { }
}
```

### 2. Dependency Inversion

Depend on abstractions (gateways), not concrete implementations.

✅ **Good**:
```php
class InvoiceService
{
    public function __construct(InvoiceGateway $gateway)
    {
        $this->gateway = $gateway;
    }
}
```

❌ **Bad**:
```php
class InvoiceService
{
    public function __construct()
    {
        $this->pdo = new PDO(...); // Tight coupling!
    }
}
```

### 3. Statelessness

Services should not store business data between method calls.

✅ **Good**:
```php
class InvoiceService
{
    protected $taxRates = null; // Cache only - OK

    public function calculateTotal($subtotal)
    {
        // Calculation using input parameter
        return $result;
    }
}
```

❌ **Bad**:
```php
class InvoiceService
{
    protected $currentInvoice; // Storing business data - BAD!

    public function setInvoice($invoice)
    {
        $this->currentInvoice = $invoice;
    }

    public function calculateTotal()
    {
        // Uses stored state - not thread-safe, hard to test
        return $this->currentInvoice['subtotal'] * 1.15;
    }
}
```

### 4. Return Values

Services should return structured data, not output directly.

✅ **Good**:
```php
public function processPayment($data)
{
    // ... process ...

    return [
        'success' => true,
        'paymentID' => $paymentID,
        'newBalance' => $balance,
    ];
}
```

❌ **Bad**:
```php
public function processPayment($data)
{
    // ... process ...

    echo "Payment processed!"; // NO! Don't output in service
    return $paymentID;
}
```

### 5. Error Handling

Handle errors gracefully and return error information.

✅ **Good**:
```php
public function processPayment($data)
{
    $validation = $this->validator->validate($data);
    if (!$validation['success']) {
        return [
            'success' => false,
            'errors' => $validation['errors'],
        ];
    }

    // ... process payment ...

    return ['success' => true, 'paymentID' => $paymentID];
}
```

❌ **Bad**:
```php
public function processPayment($data)
{
    // No validation
    // Assumes data is valid - will crash on bad input
    $paymentID = $this->gateway->insert($data);
    return $paymentID;
}
```

## Common Service Patterns

### Pattern 1: Calculation Service

**Use Case**: Complex calculations that may be reused.

```php
class InvoiceService
{
    /**
     * Calculate invoice total with taxes.
     */
    public function calculateTotal($subtotal)
    {
        $taxes = $this->calculateTaxes($subtotal);

        return [
            'subtotal' => $subtotal,
            'gst' => $taxes['gst'],
            'qst' => $taxes['qst'],
            'total' => $subtotal + $taxes['gst'] + $taxes['qst'],
        ];
    }

    /**
     * Calculate taxes separately for itemized display.
     */
    protected function calculateTaxes($subtotal)
    {
        $rates = $this->getTaxRates();

        return [
            'gst' => $subtotal * $rates['gst'],
            'qst' => $subtotal * $rates['qst'],
        ];
    }

    /**
     * Get configured tax rates.
     */
    protected function getTaxRates()
    {
        return [
            'gst' => 0.05,
            'qst' => 0.09975,
        ];
    }
}
```

### Pattern 2: Workflow Orchestration

**Use Case**: Multi-step business process.

```php
class PaymentService
{
    /**
     * Process a payment through the complete workflow.
     */
    public function processPayment($paymentData)
    {
        // Step 1: Validate
        $validation = $this->validator->validate($paymentData);
        if (!$validation['success']) {
            return [
                'success' => false,
                'errors' => $validation['errors'],
            ];
        }

        // Step 2: Check invoice exists and isn't overpaid
        $invoice = $this->invoiceGateway->getByID($paymentData['invoiceID']);
        if ($this->exceedsBalance($invoice, $paymentData['amount'])) {
            return [
                'success' => false,
                'errors' => ['amount' => 'Payment exceeds invoice balance'],
            ];
        }

        // Step 3: Record payment
        $paymentID = $this->paymentGateway->insert($paymentData);

        // Step 4: Update invoice
        $newBalance = $this->calculateBalance($invoice, $paymentData['amount']);
        $this->invoiceGateway->update($invoice['id'], [
            'amountPaid' => $newBalance['paid'],
            'status' => $newBalance['status'],
        ]);

        // Step 5: Return success
        return [
            'success' => true,
            'paymentID' => $paymentID,
            'newBalance' => $newBalance,
        ];
    }
}
```

### Pattern 3: Query Facade

**Use Case**: Simplify complex queries for view layer.

```php
class PhotoAccessService
{
    /**
     * Get photos accessible to a parent for a specific child.
     *
     * Handles access control logic transparently.
     */
    public function getPhotosForParentByChild($gibbonPersonID, $gibbonPersonIDChild)
    {
        // Check parent-child relationship
        if (!$this->isParentOfChild($gibbonPersonID, $gibbonPersonIDChild)) {
            return [];
        }

        // Get photos tagged with child
        $photos = $this->photoGateway->selectPhotosByChild($gibbonPersonIDChild);

        // Filter by retention policy
        $activePhotos = array_filter($photos, function($photo) {
            return !$this->isRetentionExpired($photo['uploadDate']);
        });

        return $activePhotos;
    }
}
```

### Pattern 4: Configuration Provider

**Use Case**: Centralize configuration and options.

```php
class MealService
{
    /**
     * Get available meal types.
     *
     * @return array Meal types [value => label]
     */
    public function getMealTypes()
    {
        return [
            'breakfast' => 'Breakfast',
            'lunch' => 'Lunch',
            'pm_snack' => 'PM Snack',
            'dinner' => 'Dinner',
        ];
    }

    /**
     * Get quantity options for meal portions.
     *
     * @return array Quantity options
     */
    public function getQuantityOptions()
    {
        return ['None', '1/4', '1/2', '3/4', 'Full'];
    }

    /**
     * Validate meal type.
     */
    public function isValidMealType($type)
    {
        return array_key_exists($type, $this->getMealTypes());
    }
}
```

### Pattern 5: Business Rule Enforcer

**Use Case**: Complex business rules and validations.

```php
class AttendanceService
{
    /**
     * Check if check-in time is valid.
     *
     * Business rules:
     * - Cannot check in before facility opens
     * - Cannot check in after facility closes
     * - Cannot check in if already checked in
     */
    public function canCheckIn($gibbonPersonIDChild, $checkInTime)
    {
        // Rule 1: Within operating hours
        $openTime = $this->settingGateway->getSettingByScope(
            'CareTracking',
            'facilityOpenTime'
        );

        if ($checkInTime < $openTime) {
            return [
                'allowed' => false,
                'reason' => 'Facility not open yet',
            ];
        }

        // Rule 2: Not already checked in
        $currentStatus = $this->attendanceGateway->getCurrentStatus($gibbonPersonIDChild);

        if ($currentStatus === 'checked_in') {
            return [
                'allowed' => false,
                'reason' => 'Already checked in',
            ];
        }

        return ['allowed' => true];
    }
}
```

## Working with Dependencies

### Injecting Dependencies

Services receive dependencies via constructor injection:

```php
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
}
```

### Resolving Services (Using Container)

In view files:

```php
// Gibbon's container automatically resolves dependencies
$invoiceService = $container->get(InvoiceService::class);

// Container creates:
// 1. SettingGateway
// 2. InvoiceGateway
// 3. InvoiceValidator
// 4. InvoiceService with all dependencies
```

### Common Dependencies

| Dependency Type | Purpose | Example |
|----------------|---------|---------|
| Gateway | Data access | `InvoiceGateway` |
| Validator | Input validation | `InvoiceValidator` |
| Service | Business logic | `TaxService` |
| SettingGateway | Configuration | `SettingGateway` |

## Error Handling

### Return Error Arrays

Services should return structured error information:

```php
public function processPayment($data)
{
    // Validate input
    $validation = $this->validator->validate($data);
    if (!$validation['success']) {
        return [
            'success' => false,
            'errors' => $validation['errors'],
        ];
    }

    // Business rule check
    if ($this->exceedsBalance($data)) {
        return [
            'success' => false,
            'errors' => ['amount' => 'Payment exceeds balance'],
        ];
    }

    // Success
    return [
        'success' => true,
        'paymentID' => $paymentID,
    ];
}
```

### Exception vs Error Array

**Use Error Arrays When**:
- Validation failures (expected)
- Business rule violations (expected)
- User errors

**Use Exceptions When**:
- Unexpected errors (database down, file not found)
- Programming errors (invalid state)
- System failures

```php
public function processPayment($data)
{
    // Expected error - return array
    if (!$this->validator->validate($data)) {
        return ['success' => false, 'errors' => [...]];
    }

    // Unexpected error - throw exception
    if (!$this->paymentGateway->isConnected()) {
        throw new RuntimeException('Database connection failed');
    }
}
```

## Testing Services

### Unit Test Structure

```php
use PHPUnit\Framework\TestCase;

class InvoiceServiceTest extends TestCase
{
    protected $settingGateway;
    protected $invoiceGateway;
    protected $validator;
    protected $service;

    protected function setUp(): void
    {
        // Create mocks
        $this->settingGateway = $this->createMock(SettingGateway::class);
        $this->invoiceGateway = $this->createMock(InvoiceGateway::class);
        $this->validator = $this->createMock(InvoiceValidator::class);

        // Create service with mocks
        $this->service = new InvoiceService(
            $this->settingGateway,
            $this->invoiceGateway,
            $this->validator
        );
    }

    public function testCalculateTotalWithTaxes()
    {
        // Setup mocks
        $this->settingGateway->method('getSettingByScope')
            ->willReturnMap([
                ['Enhanced Finance', 'gstRate', '0.05'],
                ['Enhanced Finance', 'qstRate', '0.09975'],
            ]);

        // Test business logic
        $result = $this->service->calculateTotal(100.00);

        // Assert results
        $this->assertEquals(100.00, $result['subtotal']);
        $this->assertEquals(5.00, $result['gst']);
        $this->assertEquals(9.975, $result['qst']);
        $this->assertEquals(114.975, $result['total']);
    }
}
```

### Testing Patterns

#### Test Calculations
```php
public function testCalculateTotal()
{
    $result = $this->service->calculateTotal(100);
    $this->assertEquals(115.00, $result['total']);
}
```

#### Test Validation
```php
public function testRejectsInvalidAmount()
{
    $this->validator->method('validate')
        ->willReturn(['success' => false, 'errors' => ['amount' => 'Invalid']]);

    $result = $this->service->processPayment(['amount' => -10]);

    $this->assertFalse($result['success']);
    $this->assertArrayHasKey('errors', $result);
}
```

#### Test Business Rules
```php
public function testPreventsOverpayment()
{
    $invoice = ['balance' => 100];
    $payment = ['amount' => 150];

    $result = $this->service->canProcessPayment($invoice, $payment);

    $this->assertFalse($result);
}
```

## Real-World Examples

See the following services for complete implementations:

### EnhancedFinance
- `InvoiceService.php` - Tax calculations, invoice numbers
- `PaymentService.php` - Payment workflow
- `Releve24Service.php` - Tax document generation

### CareTracking
- `MealService.php` - Meal configuration
- `AttendanceService.php` - Check-in/check-out rules
- `IncidentService.php` - Incident workflow

### PhotoManagement
- `PhotoAccessService.php` - Access control
- `PhotoTagService.php` - Tagging operations

### NotificationEngine
- `DeliveryRulesService.php` - Retry logic
- `PreferenceService.php` - User preferences

## Anti-Patterns to Avoid

### Anti-Pattern 1: Fat Service (God Object)

❌ **Bad**:
```php
class FinanceService
{
    public function createInvoice() { }
    public function processPayment() { }
    public function generateReport() { }
    public function sendEmail() { }
    public function calculateTax() { }
    public function processRefund() { }
    // ... 50 more methods ...
}
```

✅ **Good**:
```php
class InvoiceService { /* Invoice logic */ }
class PaymentService { /* Payment logic */ }
class ReportService { /* Reporting logic */ }
class EmailService { /* Email logic */ }
```

### Anti-Pattern 2: Anemic Service (No Logic)

❌ **Bad**:
```php
class InvoiceService
{
    public function getInvoice($id)
    {
        return $this->gateway->getByID($id); // Just passes through!
    }
}
```

✅ **Good**:
```php
class InvoiceService
{
    public function getInvoice($id)
    {
        $invoice = $this->gateway->getByID($id);

        // Add business logic
        $invoice['isOverdue'] = $this->isOverdue($invoice);
        $invoice['daysOverdue'] = $this->getDaysOverdue($invoice);

        return $invoice;
    }
}
```

### Anti-Pattern 3: Stateful Service

❌ **Bad**:
```php
class InvoiceService
{
    protected $currentInvoice; // State!

    public function loadInvoice($id)
    {
        $this->currentInvoice = $this->gateway->getByID($id);
    }

    public function calculateTotal()
    {
        return $this->currentInvoice['subtotal'] * 1.15;
    }
}
```

✅ **Good**:
```php
class InvoiceService
{
    public function calculateTotal($invoice)
    {
        return $invoice['subtotal'] * 1.15;
    }
}
```

### Anti-Pattern 4: Service Depends on View

❌ **Bad**:
```php
class InvoiceService
{
    public function createInvoice($data)
    {
        $invoice = $this->gateway->insert($data);

        echo "Invoice created!"; // NO! Service outputs to view

        return $invoice;
    }
}
```

✅ **Good**:
```php
class InvoiceService
{
    public function createInvoice($data)
    {
        $invoice = $this->gateway->insert($data);

        return [
            'success' => true,
            'invoice' => $invoice,
            'message' => 'Invoice created successfully',
        ];
    }
}
```

## Migration Strategies

### Strategy 1: Extract and Call

1. **Copy** business logic from view to service
2. **Test** service logic
3. **Replace** view logic with service call
4. **Remove** old code

```php
// Step 1: Copy logic to service
class InvoiceService
{
    public function calculateTotal($subtotal)
    {
        // Logic from view file
        return $subtotal * 1.15;
    }
}

// Step 2: Update view to call service
$service = $container->get(InvoiceService::class);
$total = $service->calculateTotal($subtotal);
// Remove old inline calculation
```

### Strategy 2: Parallel Implementation

1. **Create** service with new logic
2. **Run both** old and new code
3. **Verify** results match
4. **Switch** to service only
5. **Remove** old code

### Strategy 3: Gradual Refactor

1. **Identify** one business operation
2. **Extract** to service method
3. **Test** thoroughly
4. **Repeat** for next operation
5. Gradually build complete service

## Best Practices Checklist

- [ ] Service has single, clear responsibility
- [ ] Dependencies injected via constructor
- [ ] Service is mostly stateless
- [ ] Methods return structured data
- [ ] Comprehensive PHPDoc comments
- [ ] Public methods form clean API
- [ ] Protected methods for internal use
- [ ] No database queries (use gateways)
- [ ] No output (echo/print)
- [ ] Proper error handling
- [ ] Unit tests with >80% coverage
- [ ] Follows Gibbon naming conventions

## Conclusion

The Service Layer pattern is essential for building maintainable Gibbon modules. By following these guidelines and patterns, developers can create clean, testable business logic that stands the test of time.

For more information:
- [Architecture Guide](./ARCHITECTURE_GUIDE.md)
- [Module Development Guide](./MODULE_DEVELOPMENT_GUIDE.md)
- [Gibbon Documentation](https://docs.gibbonedu.org/)
