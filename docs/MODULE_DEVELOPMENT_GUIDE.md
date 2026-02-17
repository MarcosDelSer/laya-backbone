# Module Development Guide

## Table of Contents

1. [Introduction](#introduction)
2. [Module Architecture Overview](#module-architecture-overview)
3. [Quick Start](#quick-start)
4. [Directory Structure](#directory-structure)
5. [Creating a Service](#creating-a-service)
6. [Creating a Validator](#creating-a-validator)
7. [Creating a Gateway](#creating-a-gateway)
8. [Using Services in View Files](#using-services-in-view-files)
9. [Testing Your Module](#testing-your-module)
10. [Best Practices](#best-practices)
11. [Common Patterns](#common-patterns)
12. [Troubleshooting](#troubleshooting)
13. [Examples and References](#examples-and-references)

---

## Introduction

This guide provides comprehensive instructions for developing Gibbon LAYA modules using the **Service Layer Architecture**. By following these patterns, you'll create maintainable, testable, and well-organized code.

### What You'll Learn

- How to structure a module using service layers
- How to separate business logic from presentation
- How to create testable services and validators
- How to follow Gibbon LAYA conventions
- How to avoid common pitfalls

### Prerequisites

- PHP 8.1+ knowledge
- Understanding of object-oriented programming
- Familiarity with Gibbon's database structure
- Basic understanding of dependency injection

---

## Module Architecture Overview

### The Three Layers

Gibbon LAYA modules follow a three-layer architecture:

```
┌─────────────────────────────────────┐
│     VIEW LAYER (*.php files)        │
│  - Handles HTTP requests/responses  │
│  - Renders UI                       │
│  - Minimal logic                    │
└─────────────┬───────────────────────┘
              │ calls
              ▼
┌─────────────────────────────────────┐
│     SERVICE LAYER (Service/*.php)   │
│  - Business rules                   │
│  - Calculations                     │
│  - Workflow orchestration           │
│  - Uses Validators and Gateways     │
└─────────────┬───────────────────────┘
              │ uses
              ▼
┌─────────────────────────────────────┐
│    DATA LAYER (Domain/*Gateway.php) │
│  - Database queries                 │
│  - Data persistence                 │
│  - No business logic                │
└─────────────────────────────────────┘
```

### Why This Architecture?

**Before (Anti-pattern)**:
```php
// Old approach: Everything mixed together in view file
<?php
$gstRate = 0.05;
$qstRate = 0.09975;
$total = $subtotal * (1 + $gstRate + $qstRate);

$pdo->query("INSERT INTO invoices ...");
echo "<p>Invoice created: $total</p>";
```

**After (Service Layer)**:
```php
// New approach: Clean separation
<?php
$invoiceService = $container->get(InvoiceService::class);
$result = $invoiceService->createInvoice($data);

if ($result['success']) {
    echo "<p>Invoice created: {$result['invoice']['total']}</p>";
}
```

**Benefits**:
- ✅ Testable (can unit test business logic)
- ✅ Reusable (same logic for web, API, CLI)
- ✅ Maintainable (business rules in one place)
- ✅ Flexible (easy to change without breaking)

---

## Quick Start

### Step 1: Create Module Structure

```bash
cd gibbon/modules/
mkdir -p YourModule/src/{Service,Validator}
mkdir -p YourModule/Domain
```

### Step 2: Create a Gateway (Data Layer)

```php
<?php
// gibbon/modules/YourModule/Domain/RecordGateway.php
namespace Gibbon\Module\YourModule\Domain;

use Gibbon\Domain\Gateway;

class RecordGateway extends Gateway
{
    public function selectAll()
    {
        $sql = "SELECT * FROM yourTable ORDER BY name";
        return $this->db()->select($sql);
    }

    public function selectByID($id)
    {
        $sql = "SELECT * FROM yourTable WHERE id = :id";
        return $this->db()->selectOne($sql, ['id' => $id]);
    }

    public function insert($data)
    {
        return $this->db()->insert('yourTable', $data);
    }

    public function update($id, $data)
    {
        return $this->db()->update('yourTable', $data, ['id' => $id]);
    }
}
```

### Step 3: Create a Validator

```php
<?php
// gibbon/modules/YourModule/src/Validator/RecordValidator.php
namespace Gibbon\Module\YourModule\Validator;

class RecordValidator
{
    protected $errors = [];

    public function validate($data)
    {
        $this->errors = [];

        if (empty($data['name'])) {
            $this->errors['name'][] = 'Name is required';
        }

        if (!is_numeric($data['amount'])) {
            $this->errors['amount'][] = 'Amount must be numeric';
        }

        return [
            'success' => empty($this->errors),
            'errors' => $this->errors,
        ];
    }
}
```

### Step 4: Create a Service (Business Logic Layer)

```php
<?php
// gibbon/modules/YourModule/src/Service/RecordService.php
namespace Gibbon\Module\YourModule\Service;

use Gibbon\Module\YourModule\Domain\RecordGateway;
use Gibbon\Module\YourModule\Validator\RecordValidator;

class RecordService
{
    protected $gateway;
    protected $validator;

    public function __construct(
        RecordGateway $gateway,
        RecordValidator $validator
    ) {
        $this->gateway = $gateway;
        $this->validator = $validator;
    }

    public function createRecord($data)
    {
        // Validate
        $validation = $this->validator->validate($data);
        if (!$validation['success']) {
            return [
                'success' => false,
                'errors' => $validation['errors'],
            ];
        }

        // Business logic
        $data['createdAt'] = date('Y-m-d H:i:s');
        $data['status'] = 'Active';

        // Persist
        $recordID = $this->gateway->insert($data);

        return [
            'success' => true,
            'recordID' => $recordID,
        ];
    }

    public function calculateTotal($amount)
    {
        $taxRate = 0.15; // 15% tax
        return round($amount * (1 + $taxRate), 2);
    }
}
```

### Step 5: Use Service in View File

```php
<?php
// gibbon/modules/YourModule/record_add.php

// Get service from container
$recordService = $container->get(RecordService::class);

if ($_POST) {
    $data = [
        'name' => $_POST['name'],
        'amount' => $_POST['amount'],
    ];

    $result = $recordService->createRecord($data);

    if ($result['success']) {
        $page->addSuccess('Record created successfully!');
    } else {
        foreach ($result['errors'] as $field => $messages) {
            $page->addError(implode(', ', $messages));
        }
    }
}

// Render form...
```

---

## Directory Structure

### Standard Module Layout

```
gibbon/modules/YourModule/
├── manifest.php                 # Module metadata
├── version.php                  # Version info
├── CHANGELOG.txt               # Version history
├── src/
│   ├── Service/
│   │   ├── CoreService.php     # Main business logic
│   │   ├── CalculationService.php  # Calculations
│   │   └── WorkflowService.php     # Multi-step processes
│   └── Validator/
│       ├── RecordValidator.php     # Input validation
│       └── SettingsValidator.php   # Settings validation
├── Domain/
│   ├── RecordGateway.php       # Data access
│   └── SettingsGateway.php     # Settings data access
├── tests/
│   ├── Service/
│   │   └── CoreServiceTest.php     # Service tests
│   └── Validator/
│       └── RecordValidatorTest.php # Validator tests
└── [view files].php            # UI pages
```

### Naming Conventions

| Component | Pattern | Example |
|-----------|---------|---------|
| Service | `{Domain}Service.php` | `InvoiceService.php` |
| Validator | `{Domain}Validator.php` | `InvoiceValidator.php` |
| Gateway | `{Domain}Gateway.php` | `InvoiceGateway.php` |
| Test | `{Class}Test.php` | `InvoiceServiceTest.php` |

### Namespace Conventions

```php
// Services
namespace Gibbon\Module\YourModule\Service;

// Validators
namespace Gibbon\Module\YourModule\Validator;

// Gateways (Domain)
namespace Gibbon\Module\YourModule\Domain;
```

---

## Creating a Service

### Service Anatomy

A service class has five main sections:

```php
<?php
namespace Gibbon\Module\YourModule\Service;

class ExampleService
{
    // 1. DEPENDENCIES (protected properties)
    protected $gateway;
    protected $validator;

    // 2. STATE (cache only - minimal)
    protected $config = null;

    // 3. CONSTRUCTOR (dependency injection)
    public function __construct($gateway, $validator) {
        $this->gateway = $gateway;
        $this->validator = $validator;
    }

    // 4. PUBLIC API METHODS (business logic)
    public function createRecord($data) { }
    public function calculateTotal($amount) { }

    // 5. PROTECTED HELPERS (internal)
    protected function getTaxRate() { }
    protected function isValid($data) { }
}
```

### Service Design Principles

#### 1. Single Responsibility

Each service focuses on one domain or aggregate.

✅ **Good**:
```php
class InvoiceService { /* Invoice operations */ }
class PaymentService { /* Payment operations */ }
```

❌ **Bad**:
```php
class FinanceService { /* Everything finance-related! */ }
```

#### 2. Dependency Injection

Always inject dependencies via constructor.

✅ **Good**:
```php
public function __construct(
    InvoiceGateway $gateway,
    InvoiceValidator $validator
) {
    $this->gateway = $gateway;
    $this->validator = $validator;
}
```

❌ **Bad**:
```php
public function __construct() {
    $this->gateway = new InvoiceGateway(); // Hard-coded!
}
```

#### 3. Statelessness

Don't store business data between method calls.

✅ **Good**:
```php
public function calculateTotal($invoice) {
    return $invoice['subtotal'] * 1.15;
}
```

❌ **Bad**:
```php
protected $currentInvoice; // Storing state!

public function setInvoice($invoice) {
    $this->currentInvoice = $invoice;
}

public function calculateTotal() {
    return $this->currentInvoice['subtotal'] * 1.15;
}
```

#### 4. Return Structured Data

Always return arrays with success/error information.

✅ **Good**:
```php
return [
    'success' => true,
    'recordID' => $id,
    'errors' => [],
];
```

❌ **Bad**:
```php
echo "Record created!"; // Don't output in services!
return $id; // Missing error context
```

### Common Service Patterns

#### Pattern 1: Create Operation

```php
public function createRecord($data)
{
    // Step 1: Validate
    $validation = $this->validator->validate($data);
    if (!$validation['success']) {
        return [
            'success' => false,
            'errors' => $validation['errors'],
        ];
    }

    // Step 2: Business logic
    $data['createdAt'] = date('Y-m-d H:i:s');
    $data['status'] = 'Active';

    // Step 3: Persist
    $recordID = $this->gateway->insert($data);

    // Step 4: Return result
    return [
        'success' => true,
        'recordID' => $recordID,
        'errors' => [],
    ];
}
```

#### Pattern 2: Calculation

```php
public function calculateTotal($subtotal)
{
    $taxRate = $this->getTaxRate();
    $tax = $subtotal * $taxRate;
    $total = $subtotal + $tax;

    return [
        'subtotal' => round($subtotal, 2),
        'tax' => round($tax, 2),
        'total' => round($total, 2),
    ];
}

protected function getTaxRate()
{
    // Cache tax rate to avoid repeated DB queries
    if ($this->taxRate === null) {
        $this->taxRate = $this->settingGateway->getSettingByScope(
            'Module',
            'taxRate'
        ) ?: 0.15;
    }
    return (float)$this->taxRate;
}
```

#### Pattern 3: Business Rule Checker

```php
public function canDelete($record, $userID)
{
    if ($record['status'] === 'Processed') {
        return [
            'allowed' => false,
            'reason' => 'Cannot delete processed records',
        ];
    }

    if ($record['createdByID'] != $userID) {
        return [
            'allowed' => false,
            'reason' => 'Only the creator can delete this record',
        ];
    }

    return ['allowed' => true, 'reason' => ''];
}
```

#### Pattern 4: Workflow Orchestration

```php
public function processPayment($paymentData)
{
    // Step 1: Validate
    $validation = $this->validator->validate($paymentData);
    if (!$validation['success']) {
        return ['success' => false, 'errors' => $validation['errors']];
    }

    // Step 2: Check business rules
    $invoice = $this->invoiceGateway->getByID($paymentData['invoiceID']);
    if ($this->exceedsBalance($invoice, $paymentData['amount'])) {
        return [
            'success' => false,
            'errors' => ['amount' => 'Exceeds balance'],
        ];
    }

    // Step 3: Record payment
    $paymentID = $this->paymentGateway->insert($paymentData);

    // Step 4: Update invoice
    $newBalance = $this->calculateBalance($invoice, $paymentData['amount']);
    $this->invoiceGateway->update($invoice['id'], [
        'balance' => $newBalance,
        'status' => $newBalance == 0 ? 'Paid' : 'Partial',
    ]);

    // Step 5: Return success
    return [
        'success' => true,
        'paymentID' => $paymentID,
        'newBalance' => $newBalance,
    ];
}
```

---

## Creating a Validator

### Validator Anatomy

```php
<?php
namespace Gibbon\Module\YourModule\Validator;

class RecordValidator
{
    // 1. ERROR STORAGE
    protected $errors = [];

    // 2. VALIDATION CONSTRAINTS
    protected $validStatuses = ['Pending', 'Active', 'Cancelled'];

    // 3. MAIN VALIDATION METHOD
    public function validate($data)
    {
        $this->errors = [];

        $this->validateRequired($data);
        $this->validateTypes($data);
        $this->validateRanges($data);
        $this->validateBusinessRules($data);

        return [
            'success' => empty($this->errors),
            'errors' => $this->errors,
        ];
    }

    // 4. VALIDATION RULE METHODS
    protected function validateRequired($data) { }
    protected function validateTypes($data) { }

    // 5. HELPER METHODS
    public function isValidID($value) { }
    public function isValidDate($value) { }

    // 6. ERROR MANAGEMENT
    protected function addError($field, $message) { }
    public function getErrors() { }
}
```

### Validator Design Principles

#### 1. Clear Error Messages

✅ **Good**:
```php
$this->addError('amount', 'Amount must be a positive number');
```

❌ **Bad**:
```php
$this->addError('amount', 'Invalid'); // Too vague!
```

#### 2. Organized Errors

```php
// Errors organized by field
[
    'name' => ['Name is required', 'Name must be at least 2 characters'],
    'amount' => ['Amount must be positive'],
]
```

#### 3. Comprehensive Validation

Check in logical order:
1. Required fields
2. Type validation (is it a number, date, etc.)
3. Format validation (email, phone, etc.)
4. Range validation (min/max)
5. Business rules (contextual constraints)

### Common Validation Patterns

#### Pattern 1: Required Fields

```php
protected function validateRequired($data)
{
    $requiredFields = [
        'name' => 'Name',
        'amount' => 'Amount',
        'date' => 'Date',
    ];

    foreach ($requiredFields as $field => $label) {
        if (!isset($data[$field]) || $data[$field] === '' || $data[$field] === null) {
            $this->addError($field, "{$label} is required");
        }
    }
}
```

#### Pattern 2: Type Validation

```php
protected function validateTypes($data)
{
    // Validate IDs
    if (isset($data['gibbonPersonID'])) {
        if (!$this->isValidID($data['gibbonPersonID'])) {
            $this->addError('gibbonPersonID', 'Person ID must be a valid positive integer');
        }
    }

    // Validate amounts
    if (isset($data['amount'])) {
        if (!is_numeric($data['amount'])) {
            $this->addError('amount', 'Amount must be numeric');
        } elseif ((float)$data['amount'] < 0) {
            $this->addError('amount', 'Amount cannot be negative');
        }
    }

    // Validate dates
    if (isset($data['date'])) {
        if (!$this->isValidDate($data['date'])) {
            $this->addError('date', 'Date must be in Y-m-d format');
        }
    }
}
```

#### Pattern 3: Business Rules

```php
protected function validateBusinessRules($data)
{
    // Due date must be after invoice date
    if (isset($data['invoiceDate']) && isset($data['dueDate'])) {
        if (strtotime($data['dueDate']) < strtotime($data['invoiceDate'])) {
            $this->addError('dueDate', 'Due date cannot be before invoice date');
        }
    }

    // Total must equal subtotal + tax
    if (isset($data['subtotal']) && isset($data['tax']) && isset($data['total'])) {
        $expected = $data['subtotal'] + $data['tax'];
        if (abs($expected - $data['total']) > 0.01) {
            $this->addError('total', 'Total must equal subtotal plus tax');
        }
    }
}
```

### Reusable Validation Helpers

```php
public function isValidID($value)
{
    if (!is_numeric($value)) {
        return false;
    }
    $intValue = (int)$value;
    return $intValue > 0 && $intValue == $value;
}

public function isValidDate($value)
{
    if (!is_string($value)) {
        return false;
    }
    $parts = explode('-', $value);
    if (count($parts) !== 3) {
        return false;
    }
    return checkdate((int)$parts[1], (int)$parts[2], (int)$parts[0]);
}

public function isValidEmail($value)
{
    return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
}

public function isValidLength($value, $min, $max)
{
    $length = strlen($value);
    return $length >= $min && $length <= $max;
}
```

---

## Creating a Gateway

### Gateway Anatomy

Gateways handle **ONLY** database operations. No business logic!

```php
<?php
namespace Gibbon\Module\YourModule\Domain;

use Gibbon\Domain\Gateway;

class RecordGateway extends Gateway
{
    // SELECT operations
    public function selectAll()
    {
        $sql = "SELECT * FROM yourTable ORDER BY name";
        return $this->db()->select($sql);
    }

    public function selectByID($id)
    {
        $sql = "SELECT * FROM yourTable WHERE id = :id";
        return $this->db()->selectOne($sql, ['id' => $id]);
    }

    public function selectByPerson($gibbonPersonID)
    {
        $sql = "SELECT * FROM yourTable
                WHERE gibbonPersonID = :personID
                ORDER BY date DESC";
        return $this->db()->select($sql, ['personID' => $gibbonPersonID]);
    }

    // INSERT operations
    public function insert($data)
    {
        return $this->db()->insert('yourTable', $data);
    }

    // UPDATE operations
    public function update($id, $data)
    {
        return $this->db()->update('yourTable', $data, ['id' => $id]);
    }

    // DELETE operations
    public function delete($id)
    {
        return $this->db()->delete('yourTable', ['id' => $id]);
    }
}
```

### Gateway Rules

1. **No business logic** - Only database queries
2. **Return raw data** - Don't transform or calculate
3. **Use prepared statements** - Prevent SQL injection
4. **Clear method names** - `selectByID`, `selectByPerson`, etc.

❌ **Bad** (business logic in gateway):
```php
public function getActiveRecords()
{
    $records = $this->selectAll();
    return array_filter($records, function($r) {
        return $r['status'] === 'Active'; // Business logic!
    });
}
```

✅ **Good** (query only):
```php
public function selectByStatus($status)
{
    $sql = "SELECT * FROM yourTable WHERE status = :status";
    return $this->db()->select($sql, ['status' => $status]);
}
```

---

## Using Services in View Files

### Step 1: Get Service from Container

```php
<?php
// At the top of your view file
use Gibbon\Module\YourModule\Service\RecordService;

// Get service from container (dependencies auto-injected)
$recordService = $container->get(RecordService::class);
```

### Step 2: Handle Form Submission

```php
if ($_POST) {
    $data = [
        'gibbonPersonID' => $_POST['gibbonPersonID'] ?? null,
        'name' => $_POST['name'] ?? '',
        'amount' => $_POST['amount'] ?? 0,
        'date' => $_POST['date'] ?? date('Y-m-d'),
    ];

    $result = $recordService->createRecord($data);

    if ($result['success']) {
        $page->addSuccess('Record created successfully!');
        // Redirect to view page
        header("Location: record_view.php?id={$result['recordID']}");
        exit;
    } else {
        // Display errors
        foreach ($result['errors'] as $field => $messages) {
            foreach ($messages as $message) {
                $page->addError($message);
            }
        }
    }
}
```

### Step 3: Fetch and Display Data

```php
// Get data using service
$records = $recordService->getRecordsForPerson($gibbonPersonID);

// Render table
echo '<table>';
echo '<thead><tr><th>Name</th><th>Amount</th><th>Status</th></tr></thead>';
echo '<tbody>';
foreach ($records as $record) {
    echo '<tr>';
    echo '<td>' . htmlspecialchars($record['name']) . '</td>';
    echo '<td>' . number_format($record['amount'], 2) . '</td>';
    echo '<td>' . htmlspecialchars($record['displayStatus']) . '</td>';
    echo '</tr>';
}
echo '</tbody>';
echo '</table>';
```

### Complete View File Example

```php
<?php
use Gibbon\Module\YourModule\Service\RecordService;

// Get service
$recordService = $container->get(RecordService::class);

// Handle POST (create/update)
if ($_POST) {
    $data = [
        'name' => $_POST['name'] ?? '',
        'amount' => $_POST['amount'] ?? 0,
    ];

    $result = $recordService->createRecord($data);

    if ($result['success']) {
        $page->addSuccess('Record created!');
    } else {
        foreach ($result['errors'] as $messages) {
            foreach ($messages as $msg) {
                $page->addError($msg);
            }
        }
    }
}

// Get data for display
$records = $recordService->getAllRecords();

// Render form
$form = Form::create('recordForm', $session->get('absoluteURL') . '/index.php');
$form->addRow()->addTextField('name')->required();
$form->addRow()->addNumber('amount')->required();
$form->addRow()->addSubmit();
echo $form->getOutput();

// Render data table
echo '<h3>Records</h3>';
echo '<table class="fullWidth">';
foreach ($records as $record) {
    echo '<tr>';
    echo '<td>' . htmlspecialchars($record['name']) . '</td>';
    echo '<td>' . number_format($record['amount'], 2) . '</td>';
    echo '</tr>';
}
echo '</table>';
```

---

## Testing Your Module

### Unit Testing Services

Create test files in `tests/Service/`:

```php
<?php
// tests/Service/RecordServiceTest.php
namespace Gibbon\Module\YourModule\Tests\Service;

use PHPUnit\Framework\TestCase;
use Gibbon\Module\YourModule\Service\RecordService;
use Gibbon\Module\YourModule\Domain\RecordGateway;
use Gibbon\Module\YourModule\Validator\RecordValidator;

class RecordServiceTest extends TestCase
{
    protected $service;
    protected $gateway;
    protected $validator;

    protected function setUp(): void
    {
        // Create mocks
        $this->gateway = $this->createMock(RecordGateway::class);
        $this->validator = $this->createMock(RecordValidator::class);

        // Create service with mocks
        $this->service = new RecordService(
            $this->gateway,
            $this->validator
        );
    }

    public function testCalculateTotal()
    {
        $result = $this->service->calculateTotal(100.00);
        $this->assertEquals(115.00, $result);
    }

    public function testCreateRecordValidatesData()
    {
        // Setup validator to return errors
        $this->validator->method('validate')
            ->willReturn([
                'success' => false,
                'errors' => ['name' => ['Name is required']],
            ]);

        // Call service
        $result = $this->service->createRecord([]);

        // Assert validation was checked
        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('errors', $result);
    }

    public function testCreateRecordSuccess()
    {
        // Setup validator to pass
        $this->validator->method('validate')
            ->willReturn(['success' => true, 'errors' => []]);

        // Setup gateway to return ID
        $this->gateway->method('insert')
            ->willReturn(123);

        // Call service
        $result = $this->service->createRecord(['name' => 'Test']);

        // Assert success
        $this->assertTrue($result['success']);
        $this->assertEquals(123, $result['recordID']);
    }
}
```

### Running Tests

```bash
# Run all tests
vendor/bin/phpunit

# Run specific test file
vendor/bin/phpunit tests/Service/RecordServiceTest.php

# Run with coverage
vendor/bin/phpunit --coverage-html coverage/
```

---

## Best Practices

### 1. Code Organization

- ✅ One class per file
- ✅ Follow namespace conventions
- ✅ Use meaningful names
- ✅ Group related methods together
- ✅ Document with PHPDoc

### 2. Error Handling

- ✅ Validate all inputs
- ✅ Return structured errors
- ✅ Provide clear error messages
- ✅ Handle edge cases
- ❌ Don't use generic "Error occurred" messages

### 3. Performance

- ✅ Cache configuration settings
- ✅ Use efficient queries
- ✅ Avoid N+1 query problems
- ✅ Batch database operations when possible
- ❌ Don't make unnecessary database calls

### 4. Security

- ✅ Validate and sanitize all inputs
- ✅ Use prepared statements
- ✅ Check permissions before operations
- ✅ Escape output in views
- ❌ Never trust user input

### 5. Testing

- ✅ Write tests for business logic
- ✅ Mock dependencies
- ✅ Test edge cases
- ✅ Aim for >80% coverage
- ❌ Don't skip validation tests

---

## Common Patterns

### Pattern 1: Configuration Service

```php
class ConfigService
{
    protected $settingGateway;
    protected $config = null;

    public function getConfig()
    {
        if ($this->config === null) {
            $this->config = [
                'taxRate' => $this->settingGateway->getSetting('taxRate') ?: 0.15,
                'currency' => $this->settingGateway->getSetting('currency') ?: 'CAD',
            ];
        }
        return $this->config;
    }

    public function getTaxRate()
    {
        return $this->getConfig()['taxRate'];
    }
}
```

### Pattern 2: Query Builder Service

```php
class RecordQueryService
{
    public function buildQuery($filters = [])
    {
        $sql = "SELECT * FROM records WHERE 1=1";
        $params = [];

        if (!empty($filters['status'])) {
            $sql .= " AND status = :status";
            $params['status'] = $filters['status'];
        }

        if (!empty($filters['dateFrom'])) {
            $sql .= " AND date >= :dateFrom";
            $params['dateFrom'] = $filters['dateFrom'];
        }

        return ['sql' => $sql, 'params' => $params];
    }
}
```

### Pattern 3: Notification Service

```php
class NotificationService
{
    public function sendRecordCreated($recordID, $recipientID)
    {
        $record = $this->recordGateway->getByID($recordID);

        $notification = [
            'type' => 'Record Created',
            'recipientID' => $recipientID,
            'title' => 'New Record',
            'message' => "Record {$record['name']} was created",
            'link' => "/record_view.php?id={$recordID}",
        ];

        return $this->notificationGateway->insert($notification);
    }
}
```

---

## Troubleshooting

### Issue: Service not found by container

**Problem**: `Class not found` error when calling `$container->get(YourService::class)`

**Solution**:
1. Check namespace is correct
2. Verify file path matches namespace
3. Clear cache if using opcache

### Issue: Dependencies not injected

**Problem**: Constructor parameters are null

**Solution**:
1. Ensure dependencies are registered in container
2. Check constructor type hints
3. Verify gateway/validator classes exist

### Issue: Validation errors not showing

**Problem**: Form submits without showing validation errors

**Solution**:
1. Check `validate()` returns `['success' => false, 'errors' => [...]]`
2. Verify view file displays errors
3. Ensure service checks validation result

### Issue: Tests failing

**Problem**: PHPUnit tests fail unexpectedly

**Solution**:
1. Check mock setup is correct
2. Verify expected values match actual values
3. Use `var_dump()` to debug test data
4. Run tests with `--verbose` flag

---

## Examples and References

### Code Examples

Refer to these complete examples in `docs/examples/`:

- **ServiceExample.php** - Comprehensive service with all patterns
- **ValidatorExample.php** - Complete validator implementation

### Real Module Examples

Study these modules for reference:

1. **EnhancedFinance**
   - `InvoiceService.php` - Tax calculations, invoice generation
   - `InvoiceValidator.php` - Comprehensive validation
   - `Releve24Service.php` - Complex business logic

2. **CareTracking**
   - `MealService.php` - Simple service pattern
   - `AttendanceValidator.php` - Business rule validation
   - `AttendanceService.php` - Workflow orchestration

3. **NotificationEngine**
   - `FCMService.php` - External API integration
   - `DeliveryRulesService.php` - Complex business rules

### Documentation

- [Service Layer Pattern Guide](./SERVICE_LAYER_PATTERN.md) - Deep dive into service patterns
- [Architecture Guide](./ARCHITECTURE_GUIDE.md) - Overall system architecture
- [Gibbon Documentation](https://docs.gibbonedu.org/) - Official Gibbon docs

---

## Conclusion

By following this guide, you'll create well-structured, maintainable Gibbon modules using the Service Layer pattern. Remember:

1. **Separate concerns** - View, Service, Validator, Gateway
2. **Write tests** - Ensure code quality and catch regressions
3. **Follow conventions** - Consistent code is maintainable code
4. **Start simple** - Build incrementally, don't over-engineer
5. **Refer to examples** - Learn from existing modules

Happy coding! 🚀

---

## Quick Reference

### Service Template

```php
<?php
namespace Gibbon\Module\YourModule\Service;

class YourService
{
    protected $gateway;
    protected $validator;

    public function __construct($gateway, $validator) {
        $this->gateway = $gateway;
        $this->validator = $validator;
    }

    public function createRecord($data) {
        $validation = $this->validator->validate($data);
        if (!$validation['success']) {
            return ['success' => false, 'errors' => $validation['errors']];
        }

        $recordID = $this->gateway->insert($data);
        return ['success' => true, 'recordID' => $recordID];
    }
}
```

### Validator Template

```php
<?php
namespace Gibbon\Module\YourModule\Validator;

class YourValidator
{
    protected $errors = [];

    public function validate($data) {
        $this->errors = [];

        if (empty($data['name'])) {
            $this->errors['name'][] = 'Name is required';
        }

        return [
            'success' => empty($this->errors),
            'errors' => $this->errors,
        ];
    }

    protected function addError($field, $message) {
        $this->errors[$field][] = $message;
    }
}
```

### Gateway Template

```php
<?php
namespace Gibbon\Module\YourModule\Domain;

use Gibbon\Domain\Gateway;

class YourGateway extends Gateway
{
    public function selectAll() {
        return $this->db()->select("SELECT * FROM yourTable");
    }

    public function selectByID($id) {
        return $this->db()->selectOne(
            "SELECT * FROM yourTable WHERE id = :id",
            ['id' => $id]
        );
    }

    public function insert($data) {
        return $this->db()->insert('yourTable', $data);
    }

    public function update($id, $data) {
        return $this->db()->update('yourTable', $data, ['id' => $id]);
    }
}
```
