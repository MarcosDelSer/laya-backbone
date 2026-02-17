# Test Verification Guide

## Overview

This guide explains how to run the full PHPUnit test suite for the refactored Gibbon business logic services.

## Prerequisites

1. **PHP 8.3+** installed and available in PATH
2. **Composer** installed for dependency management
3. **Database** configured (if needed for integration tests)

## Installation Steps

### 1. Install Composer Dependencies

```bash
cd gibbon
composer install
```

This will install:
- PHPUnit 10.5+ (test framework)
- mpdf/mpdf (PDF generation library)
- All autoloading configurations

### 2. Verify Installation

```bash
vendor/bin/phpunit --version
```

Expected output: `PHPUnit 10.5.x`

## Running Tests

### Run All Tests

```bash
cd gibbon
vendor/bin/phpunit
```

### Run Tests by Module

```bash
# EnhancedFinance module tests
vendor/bin/phpunit --testsuite "EnhancedFinance Module Tests"

# CareTracking module tests
vendor/bin/phpunit --testsuite "CareTracking Module Tests"

# PhotoManagement module tests
vendor/bin/phpunit --testsuite "PhotoManagement Module Tests"

# NotificationEngine module tests
vendor/bin/phpunit --testsuite "NotificationEngine Module Tests"
```

### Run Tests with Coverage

```bash
# Generate coverage report (HTML)
vendor/bin/phpunit --coverage-html coverage

# Generate coverage report (text)
vendor/bin/phpunit --coverage-text

# Generate coverage report with minimum threshold check
vendor/bin/phpunit --coverage-text --coverage-filter modules/EnhancedFinance/src
```

### Run Specific Test Files

```bash
# Test a specific service
vendor/bin/phpunit modules/EnhancedFinance/tests/Unit/InvoiceServiceTest.php

# Test multiple files
vendor/bin/phpunit modules/EnhancedFinance/tests/Unit/InvoiceServiceTest.php modules/EnhancedFinance/tests/Unit/PaymentServiceTest.php
```

## Test Suites

The following test suites have been created:

### 1. EnhancedFinance Module Tests (NEW)
- **InvoiceServiceTest.php** - Tests invoice business logic (tax calculations, number generation, due dates)
- **PaymentServiceTest.php** - Tests payment processing logic (validation, balance calculations)
- **Releve24ServiceTest.php** - Tests RL-24 tax document generation (SIN validation, calculations)

**Test Count**: 60+ tests covering all service methods

### 2. CareTracking Module Tests (NEW)
- **MealServiceTest.php** - Tests meal tracking business logic
- **AttendanceServiceTest.php** - Tests attendance check-in/check-out logic
- **IncidentServiceTest.php** - Tests incident tracking and notification logic

**Test Count**: 76+ tests covering all service methods

### 3. PhotoManagement Module Tests (NEW)
- **PhotoAccessServiceTest.php** - Tests photo access control and permissions
- **PhotoTagServiceTest.php** - Tests photo tagging operations

**Test Count**: 105+ tests covering all service methods

### 4. NotificationEngine Module Tests (NEW)
- **DeliveryRulesServiceTest.php** - Tests notification delivery rules and retry logic
- **PreferenceServiceTest.php** - Tests user notification preferences

**Test Count**: 115+ tests covering all service methods

## Coverage Requirements

**Target**: >80% code coverage for all service layer classes

The phpunit.xml configuration includes coverage for:
- `modules/EnhancedFinance/src/` (Service & Validator classes)
- `modules/CareTracking/src/` (Service & Validator classes)
- `modules/PhotoManagement/src/` (Service classes)
- `modules/NotificationEngine/src/` (Service classes)

## Expected Results

### Success Criteria

✅ **All tests pass** with no failures or errors
✅ **Code coverage >80%** for service layer classes
✅ **No regressions** in existing functionality
✅ **All business logic** covered by unit tests

### Sample Output

```
PHPUnit 10.5.x by Sebastian Bergmann and contributors.

Runtime:       PHP 8.3.x
Configuration: /path/to/gibbon/phpunit.xml

............................................................  60 / 356 ( 16%)
............................................................  120 / 356 ( 33%)
............................................................  180 / 356 ( 50%)
............................................................  240 / 356 ( 67%)
............................................................  300 / 356 ( 84%)
........................................................      356 / 356 (100%)

Time: 00:05.123, Memory: 28.00 MB

OK (356 tests, 1024 assertions)

Code Coverage Report:
  2024-02-17 12:00:00

 Summary:
  Classes: 85.71% (18/21)
  Methods: 88.24% (195/221)
  Lines:   84.32% (1842/2184)

\Gibbon\Module\EnhancedFinance\Service
  InvoiceService.php            88.89%
  PaymentService.php            91.23%
  Releve24Service.php           86.45%

\Gibbon\Module\CareTracking\Service
  MealService.php               85.32%
  AttendanceService.php         87.65%
  IncidentService.php           84.21%
```

## Troubleshooting

### Issue: PHPUnit not found

```bash
# Ensure composer install was run
cd gibbon
composer install

# Verify vendor/bin exists
ls -la vendor/bin/
```

### Issue: Class not found errors

```bash
# Regenerate autoloader
composer dump-autoload
```

### Issue: Memory limit errors

```bash
# Increase PHP memory limit
php -d memory_limit=-1 vendor/bin/phpunit
```

### Issue: Database connection errors

Some tests may require a configured database. Ensure:
1. Database credentials are set in `.env` or `config.php`
2. Database migrations have been run
3. Test database is seeded with required data

## CI/CD Integration

### GitHub Actions Example

```yaml
name: PHPUnit Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest

    steps:
      - uses: actions/checkout@v2

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'
          extensions: mbstring, pdo, pdo_mysql
          coverage: xdebug

      - name: Install dependencies
        run: |
          cd gibbon
          composer install --prefer-dist --no-progress

      - name: Run tests
        run: |
          cd gibbon
          vendor/bin/phpunit --coverage-text --coverage-clover=coverage.xml

      - name: Check coverage threshold
        run: |
          # Ensure >80% coverage
          php scripts/check-coverage.php coverage.xml 80
```

## Test Development

### Adding New Tests

When creating new service classes, always add corresponding tests:

1. Create test file in `modules/YourModule/tests/Unit/YourServiceTest.php`
2. Extend `PHPUnit\Framework\TestCase`
3. Follow existing patterns from `InvoiceServiceTest.php`
4. Mock dependencies (gateways, settings)
5. Test all public methods
6. Include edge cases and error scenarios

### Test Naming Conventions

```php
// Test method names should be descriptive
public function testCalculateTaxReturnsCorrectAmount()
public function testValidateInvoiceThrowsExceptionWhenAmountNegative()
public function testProcessPaymentUpdatesInvoiceBalance()
```

## Additional Resources

- [PHPUnit Documentation](https://phpunit.de/documentation.html)
- [Architecture Guide](../../docs/ARCHITECTURE_GUIDE.md)
- [Service Layer Pattern](../../docs/SERVICE_LAYER_PATTERN.md)
- [Module Development Guide](../../docs/MODULE_DEVELOPMENT_GUIDE.md)

## Status

**Current Status**: Test suite configured and ready to run

**Environment Note**: PHP and PHPUnit are not available in the auto-claude environment. Tests must be run in a proper PHP development environment with:
- PHP 8.3+ installed
- Composer dependencies installed (`composer install`)
- Database configured (if needed)

**Next Steps**:
1. Run `composer install` in the gibbon directory
2. Execute `vendor/bin/phpunit` to run all tests
3. Verify >80% code coverage
4. Check that all tests pass
5. Review coverage report for any gaps

---

*Generated as part of Task 096: Refactor Gibbon Business Logic*
*Last Updated: 2026-02-17*
