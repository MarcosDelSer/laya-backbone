<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright © 2010, Gibbon Foundation
Gibbon™, Gibbon Education Ltd. (Gibbon Core) and Gibbon LAYA are trademarks of Gibbon Education Ltd.

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program. If not, see <http://www.gnu.org/licenses/>.
*/

namespace Gibbon\Module\ExampleModule\Validator;

/**
 * ExampleValidator - Complete Validator Class Example
 *
 * This is a comprehensive example demonstrating all the key patterns and
 * best practices for building a Validator class in Gibbon LAYA.
 *
 * A Validator class is responsible for:
 * 1. Validating input data (from forms, API calls, etc.)
 * 2. Enforcing data format requirements
 * 3. Checking business rule constraints
 * 4. Collecting and reporting validation errors
 *
 * KEY CONCEPTS DEMONSTRATED:
 * 1. Structured Error Reporting - Errors organized by field
 * 2. Comprehensive Validation - Required fields, types, formats, ranges
 * 3. Business Rule Validation - Context-aware constraints
 * 4. Reusable Validators - Common validation methods
 * 5. Clear Error Messages - User-friendly, actionable feedback
 * 6. Separation of Concerns - Validation logic isolated from business logic
 *
 * @version v1.0.00
 * @since   v1.0.00
 */
class ExampleValidator
{
    // ========================================
    // SECTION 1: PROPERTIES
    // ========================================
    //
    // Validators typically only need to store error state.
    // Avoid complex dependencies - validators should be simple and focused.

    /**
     * @var array Validation errors organized by field
     *
     * Structure:
     * [
     *     'fieldName' => ['Error message 1', 'Error message 2'],
     *     'anotherField' => ['Error message'],
     * ]
     */
    protected $errors = [];

    /**
     * @var array Valid status values (example of validation constraints)
     */
    protected $validStatuses = ['Pending', 'Active', 'Processed', 'Cancelled', 'Archived'];

    /**
     * @var array Valid priority levels
     */
    protected $validPriorities = [1, 2, 3, 4];

    // ========================================
    // SECTION 2: MAIN VALIDATION METHODS
    // ========================================
    //
    // These are the primary entry points that services call.
    // Each method validates a specific context or operation.

    /**
     * PATTERN 1: FULL RECORD VALIDATION
     *
     * Validates all fields required to create or update a complete record.
     * This is the most common validation pattern.
     *
     * @param array $data Record data to validate
     * @return array Validation result with success boolean and errors array
     *
     * @example
     * $validation = $validator->validate($data);
     * if ($validation['success']) {
     *     // Proceed with operation
     * } else {
     *     // Display errors: $validation['errors']
     * }
     */
    public function validate($data)
    {
        // Clear previous errors
        $this->errors = [];

        // Run validation checks in logical order
        $this->validateRequired($data);
        $this->validateIDs($data);
        $this->validateAmounts($data);
        $this->validateDates($data);
        $this->validateStrings($data);
        $this->validateStatus($data);
        $this->validatePriority($data);
        $this->validateCustomRules($data);

        // Return structured result
        return [
            'success' => empty($this->errors),
            'errors' => $this->errors,
        ];
    }

    /**
     * PATTERN 2: CONTEXT-SPECIFIC VALIDATION
     *
     * Validates data for a specific operation with its own requirements.
     * Different operations may have different validation rules.
     *
     * @param array $data Processing data to validate
     * @return array Validation result
     *
     * @example
     * // Validating data for a processing operation (different rules than creation)
     * $validation = $validator->validateProcessing($processingData);
     */
    public function validateProcessing($data)
    {
        $this->errors = [];

        // Only validate fields relevant to processing
        if (empty($data['processedByID'])) {
            $this->addError('processedByID', 'Processor ID is required');
        }

        if (empty($data['notes'])) {
            $this->addError('notes', 'Processing notes are required');
        }

        if (isset($data['notes']) && strlen($data['notes']) > 1000) {
            $this->addError('notes', 'Notes must not exceed 1000 characters');
        }

        return [
            'success' => empty($this->errors),
            'errors' => $this->errors,
        ];
    }

    /**
     * PATTERN 3: UPDATE VALIDATION
     *
     * Validates partial data for updates (where some fields may be optional).
     *
     * @param array $data Update data (may be partial)
     * @return array Validation result
     */
    public function validateUpdate($data)
    {
        $this->errors = [];

        // Only validate fields that are present
        if (isset($data['amount'])) {
            if (!$this->isValidAmount($data['amount'])) {
                $this->addError('amount', 'Amount must be a valid positive number');
            }
        }

        if (isset($data['status'])) {
            if (!in_array($data['status'], $this->validStatuses)) {
                $this->addError('status', 'Invalid status value');
            }
        }

        if (isset($data['dueDate'])) {
            if (!$this->isValidDate($data['dueDate'])) {
                $this->addError('dueDate', 'Due date must be in Y-m-d format');
            }
        }

        return [
            'success' => empty($this->errors),
            'errors' => $this->errors,
        ];
    }

    // ========================================
    // SECTION 3: VALIDATION RULE METHODS
    // ========================================
    //
    // These protected methods implement specific validation rules.
    // They're called by the main validation methods above.

    /**
     * Validate required fields.
     *
     * Checks that essential fields are present and not empty.
     *
     * @param array $data Data to validate
     * @return void
     */
    protected function validateRequired($data)
    {
        $requiredFields = [
            'gibbonPersonID' => 'Person',
            'gibbonSchoolYearID' => 'School Year',
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

    /**
     * Validate ID fields.
     *
     * Ensures IDs are positive integers.
     *
     * @param array $data Data to validate
     * @return void
     */
    protected function validateIDs($data)
    {
        $idFields = ['gibbonPersonID', 'gibbonSchoolYearID', 'createdByID', 'modifiedByID'];

        foreach ($idFields as $field) {
            // Only validate if field is present
            if (isset($data[$field]) && $data[$field] !== null && $data[$field] !== '') {
                if (!$this->isValidID($data[$field])) {
                    $this->addError($field, "{$field} must be a valid positive integer");
                }
            }
        }
    }

    /**
     * Validate amount fields.
     *
     * Ensures amounts are valid positive numbers.
     *
     * @param array $data Data to validate
     * @return void
     */
    protected function validateAmounts($data)
    {
        // Validate main amount
        if (isset($data['amount'])) {
            if (!$this->isValidAmount($data['amount'])) {
                $this->addError('amount', 'Amount must be a valid number');
            } elseif ((float)$data['amount'] < 0) {
                $this->addError('amount', 'Amount cannot be negative');
            } elseif ((float)$data['amount'] > 999999.99) {
                $this->addError('amount', 'Amount cannot exceed 999,999.99');
            }
        }

        // Validate tax amount
        if (isset($data['taxAmount']) && $data['taxAmount'] !== null && $data['taxAmount'] !== '') {
            if (!$this->isValidAmount($data['taxAmount'])) {
                $this->addError('taxAmount', 'Tax amount must be a valid number');
            } elseif ((float)$data['taxAmount'] < 0) {
                $this->addError('taxAmount', 'Tax amount cannot be negative');
            }
        }

        // Validate total amount
        if (isset($data['totalAmount']) && $data['totalAmount'] !== null && $data['totalAmount'] !== '') {
            if (!$this->isValidAmount($data['totalAmount'])) {
                $this->addError('totalAmount', 'Total amount must be a valid number');
            }

            // Business rule: total should equal amount + tax
            if (isset($data['amount']) && isset($data['taxAmount'])) {
                $expectedTotal = round((float)$data['amount'] + (float)$data['taxAmount'], 2);
                $actualTotal = round((float)$data['totalAmount'], 2);

                if (abs($expectedTotal - $actualTotal) > 0.01) {
                    $this->addError('totalAmount', 'Total amount must equal amount plus tax');
                }
            }
        }
    }

    /**
     * Validate date fields.
     *
     * Ensures dates are in correct format and follow business rules.
     *
     * @param array $data Data to validate
     * @return void
     */
    protected function validateDates($data)
    {
        // Validate main date
        if (isset($data['date']) && $data['date'] !== null && $data['date'] !== '') {
            if (!$this->isValidDate($data['date'])) {
                $this->addError('date', 'Date must be in Y-m-d format');
            }
        }

        // Validate due date
        if (isset($data['dueDate']) && $data['dueDate'] !== null && $data['dueDate'] !== '') {
            if (!$this->isValidDate($data['dueDate'])) {
                $this->addError('dueDate', 'Due date must be in Y-m-d format');
            }

            // Business rule: due date should not be before main date
            if (isset($data['date']) && $this->isValidDate($data['date']) && $this->isValidDate($data['dueDate'])) {
                if (strtotime($data['dueDate']) < strtotime($data['date'])) {
                    $this->addError('dueDate', 'Due date cannot be before the record date');
                }
            }
        }

        // Validate start and end dates
        if (isset($data['startDate']) && isset($data['endDate'])) {
            if ($this->isValidDate($data['startDate']) && $this->isValidDate($data['endDate'])) {
                if (strtotime($data['endDate']) < strtotime($data['startDate'])) {
                    $this->addError('endDate', 'End date cannot be before start date');
                }
            }
        }
    }

    /**
     * Validate string fields.
     *
     * Ensures strings meet length and format requirements.
     *
     * @param array $data Data to validate
     * @return void
     */
    protected function validateStrings($data)
    {
        // Validate name
        if (isset($data['name'])) {
            $name = trim($data['name']);
            if (strlen($name) < 2) {
                $this->addError('name', 'Name must be at least 2 characters');
            }
            if (strlen($name) > 100) {
                $this->addError('name', 'Name must not exceed 100 characters');
            }
        }

        // Validate description (optional field)
        if (isset($data['description']) && $data['description'] !== null && $data['description'] !== '') {
            if (strlen($data['description']) > 1000) {
                $this->addError('description', 'Description must not exceed 1000 characters');
            }
        }

        // Validate reference number format
        if (isset($data['referenceNumber']) && $data['referenceNumber'] !== null && $data['referenceNumber'] !== '') {
            if (!preg_match('/^[A-Za-z0-9\-_]+$/', $data['referenceNumber'])) {
                $this->addError('referenceNumber', 'Reference number can only contain letters, numbers, dashes, and underscores');
            }
            if (strlen($data['referenceNumber']) > 50) {
                $this->addError('referenceNumber', 'Reference number must not exceed 50 characters');
            }
        }

        // Validate email format (if present)
        if (isset($data['email']) && $data['email'] !== null && $data['email'] !== '') {
            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                $this->addError('email', 'Email must be a valid email address');
            }
        }
    }

    /**
     * Validate status field.
     *
     * Ensures status is from allowed values.
     *
     * @param array $data Data to validate
     * @return void
     */
    protected function validateStatus($data)
    {
        if (isset($data['status']) && $data['status'] !== null && $data['status'] !== '') {
            if (!in_array($data['status'], $this->validStatuses)) {
                $this->addError('status', 'Status must be one of: ' . implode(', ', $this->validStatuses));
            }
        }
    }

    /**
     * Validate priority field.
     *
     * Ensures priority is within valid range.
     *
     * @param array $data Data to validate
     * @return void
     */
    protected function validatePriority($data)
    {
        if (isset($data['priority']) && $data['priority'] !== null && $data['priority'] !== '') {
            if (!in_array((int)$data['priority'], $this->validPriorities)) {
                $this->addError('priority', 'Priority must be between 1 and 4');
            }
        }
    }

    /**
     * Validate custom business rules.
     *
     * Implements context-specific validation logic.
     *
     * @param array $data Data to validate
     * @return void
     */
    protected function validateCustomRules($data)
    {
        // Example: Amount must be multiple of 10 for certain types
        if (isset($data['type']) && $data['type'] === 'Bulk' && isset($data['amount'])) {
            if (fmod((float)$data['amount'], 10) != 0) {
                $this->addError('amount', 'Bulk records must have amounts in multiples of 10');
            }
        }

        // Example: Cannot set due date for cancelled status
        if (isset($data['status']) && $data['status'] === 'Cancelled' && !empty($data['dueDate'])) {
            $this->addError('dueDate', 'Cancelled records cannot have a due date');
        }
    }

    // ========================================
    // SECTION 4: VALIDATION HELPER METHODS
    // ========================================
    //
    // Reusable validation logic for common data types.
    // These are the building blocks used by the validation rule methods.

    /**
     * Check if a value is a valid ID (positive integer).
     *
     * @param mixed $value Value to check
     * @return bool True if valid
     */
    public function isValidID($value)
    {
        if (!is_numeric($value)) {
            return false;
        }

        $intValue = (int)$value;

        // Must be positive integer and equal to original value when cast
        return $intValue > 0 && $intValue == $value;
    }

    /**
     * Check if a value is a valid amount (numeric).
     *
     * @param mixed $value Value to check
     * @return bool True if valid
     */
    public function isValidAmount($value)
    {
        return is_numeric($value);
    }

    /**
     * Check if a value is a valid date in Y-m-d format.
     *
     * @param mixed $value Value to check
     * @return bool True if valid
     */
    public function isValidDate($value)
    {
        if (!is_string($value)) {
            return false;
        }

        // Check format Y-m-d
        $parts = explode('-', $value);
        if (count($parts) !== 3) {
            return false;
        }

        $year = $parts[0];
        $month = $parts[1];
        $day = $parts[2];

        // Validate using PHP's checkdate function
        return checkdate((int)$month, (int)$day, (int)$year);
    }

    /**
     * Check if a value is a valid time in H:i or H:i:s format.
     *
     * @param mixed $value Value to check
     * @return bool True if valid
     */
    public function isValidTime($value)
    {
        if (!is_string($value)) {
            return false;
        }

        return preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $value);
    }

    /**
     * Check if a value is a valid datetime in Y-m-d H:i:s format.
     *
     * @param mixed $value Value to check
     * @return bool True if valid
     */
    public function isValidDateTime($value)
    {
        if (!is_string($value)) {
            return false;
        }

        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return false;
        }

        // Verify it matches expected format
        return date('Y-m-d H:i:s', $timestamp) === $value;
    }

    /**
     * Check if a value is a valid boolean (for checkbox/toggle fields).
     *
     * @param mixed $value Value to check
     * @return bool True if valid
     */
    public function isValidBoolean($value)
    {
        return in_array($value, [true, false, 0, 1, '0', '1', 'Y', 'N'], true);
    }

    /**
     * Check if a string is within length limits.
     *
     * @param string $value String to check
     * @param int $min Minimum length
     * @param int $max Maximum length
     * @return bool True if valid
     */
    public function isValidLength($value, $min, $max)
    {
        $length = strlen($value);
        return $length >= $min && $length <= $max;
    }

    // ========================================
    // SECTION 5: ERROR MANAGEMENT METHODS
    // ========================================
    //
    // Methods for managing the errors collection.

    /**
     * Add a validation error.
     *
     * @param string $field Field name
     * @param string $message Error message
     * @return void
     */
    protected function addError($field, $message)
    {
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = [];
        }

        $this->errors[$field][] = $message;
    }

    /**
     * Get all validation errors.
     *
     * @return array Errors organized by field
     */
    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * Get errors for a specific field.
     *
     * @param string $field Field name
     * @return array Error messages for the field
     */
    public function getFieldErrors($field)
    {
        return $this->errors[$field] ?? [];
    }

    /**
     * Clear all validation errors.
     *
     * @return void
     */
    public function clearErrors()
    {
        $this->errors = [];
    }

    /**
     * Check if there are any validation errors.
     *
     * @return bool True if errors exist
     */
    public function hasErrors()
    {
        return !empty($this->errors);
    }

    /**
     * Check if a specific field has errors.
     *
     * @param string $field Field name
     * @return bool True if field has errors
     */
    public function hasFieldErrors($field)
    {
        return !empty($this->errors[$field]);
    }

    /**
     * Get all errors as a flat array of messages.
     *
     * Useful for displaying all errors in a simple list.
     *
     * @return array Flat array of error messages
     */
    public function getAllErrorMessages()
    {
        $messages = [];

        foreach ($this->errors as $field => $fieldErrors) {
            foreach ($fieldErrors as $error) {
                $messages[] = $error;
            }
        }

        return $messages;
    }

    /**
     * Get error count.
     *
     * @return int Total number of error messages
     */
    public function getErrorCount()
    {
        $count = 0;

        foreach ($this->errors as $fieldErrors) {
            $count += count($fieldErrors);
        }

        return $count;
    }
}
