<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright © 2010, Gibbon Foundation
Gibbon™, Gibbon Education Ltd. (Hong Kong)

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

namespace Gibbon\Module\EnhancedFinance\Validator;

/**
 * InvoiceValidator
 *
 * Validation service for invoice data.
 * Handles input validation for invoice creation and updates.
 * Ensures data integrity and business rule compliance.
 *
 * @version v1.0.00
 * @since   v1.0.00
 */
class InvoiceValidator
{
    /**
     * @var array Validation errors
     */
    protected $errors = [];

    /**
     * @var array Valid invoice statuses
     */
    protected $validStatuses = ['Pending', 'Issued', 'Partial', 'Paid', 'Cancelled', 'Refunded'];

    /**
     * Validate invoice data.
     *
     * @param array $data Invoice data to validate
     * @return array Validation result with success and errors
     */
    public function validate($data)
    {
        $this->errors = [];

        // Validate required fields
        $this->validateRequired($data);

        // Validate IDs
        $this->validateIDs($data);

        // Validate amounts
        $this->validateAmounts($data);

        // Validate dates
        $this->validateDates($data);

        // Validate status
        $this->validateStatus($data);

        // Validate invoice number format
        $this->validateInvoiceNumber($data);

        return [
            'success' => empty($this->errors),
            'errors' => $this->errors,
        ];
    }

    /**
     * Validate required fields.
     *
     * @param array $data Invoice data
     * @return void
     */
    protected function validateRequired($data)
    {
        $requiredFields = [
            'gibbonSchoolYearID' => 'School Year',
            'gibbonFamilyID' => 'Family',
            'gibbonPersonID' => 'Child',
            'subtotal' => 'Subtotal',
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
     * @param array $data Invoice data
     * @return void
     */
    protected function validateIDs($data)
    {
        $idFields = ['gibbonSchoolYearID', 'gibbonFamilyID', 'gibbonPersonID', 'createdByID'];

        foreach ($idFields as $field) {
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
     * @param array $data Invoice data
     * @return void
     */
    protected function validateAmounts($data)
    {
        // Validate subtotal
        if (isset($data['subtotal'])) {
            if (!$this->isValidAmount($data['subtotal'])) {
                $this->addError('subtotal', 'Subtotal must be a valid positive number');
            } elseif ((float)$data['subtotal'] < 0) {
                $this->addError('subtotal', 'Subtotal cannot be negative');
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
                $this->addError('totalAmount', 'Total amount must be a valid positive number');
            } elseif ((float)$data['totalAmount'] < 0) {
                $this->addError('totalAmount', 'Total amount cannot be negative');
            }

            // Validate total = subtotal + tax
            if (isset($data['subtotal']) && isset($data['taxAmount'])) {
                $expectedTotal = round((float)$data['subtotal'] + (float)$data['taxAmount'], 2);
                $actualTotal = round((float)$data['totalAmount'], 2);

                if (abs($expectedTotal - $actualTotal) > 0.01) {
                    $this->addError('totalAmount', 'Total amount must equal subtotal plus tax amount');
                }
            }
        }

        // Validate paid amount
        if (isset($data['paidAmount']) && $data['paidAmount'] !== null && $data['paidAmount'] !== '') {
            if (!$this->isValidAmount($data['paidAmount'])) {
                $this->addError('paidAmount', 'Paid amount must be a valid number');
            } elseif ((float)$data['paidAmount'] < 0) {
                $this->addError('paidAmount', 'Paid amount cannot be negative');
            }

            // Paid amount should not exceed total amount
            if (isset($data['totalAmount'])) {
                if ((float)$data['paidAmount'] > (float)$data['totalAmount']) {
                    $this->addError('paidAmount', 'Paid amount cannot exceed total amount');
                }
            }
        }
    }

    /**
     * Validate date fields.
     *
     * @param array $data Invoice data
     * @return void
     */
    protected function validateDates($data)
    {
        // Validate invoice date
        if (isset($data['invoiceDate']) && $data['invoiceDate'] !== null && $data['invoiceDate'] !== '') {
            if (!$this->isValidDate($data['invoiceDate'])) {
                $this->addError('invoiceDate', 'Invoice date must be a valid date (Y-m-d format)');
            }
        }

        // Validate due date
        if (isset($data['dueDate']) && $data['dueDate'] !== null && $data['dueDate'] !== '') {
            if (!$this->isValidDate($data['dueDate'])) {
                $this->addError('dueDate', 'Due date must be a valid date (Y-m-d format)');
            }

            // Due date should not be before invoice date
            if (isset($data['invoiceDate']) && $this->isValidDate($data['invoiceDate']) && $this->isValidDate($data['dueDate'])) {
                if (strtotime($data['dueDate']) < strtotime($data['invoiceDate'])) {
                    $this->addError('dueDate', 'Due date cannot be before invoice date');
                }
            }
        }
    }

    /**
     * Validate status field.
     *
     * @param array $data Invoice data
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
     * Validate invoice number format.
     *
     * @param array $data Invoice data
     * @return void
     */
    protected function validateInvoiceNumber($data)
    {
        if (isset($data['invoiceNumber']) && $data['invoiceNumber'] !== null && $data['invoiceNumber'] !== '') {
            $invoiceNumber = $data['invoiceNumber'];

            // Check length (reasonable max: 50 characters)
            if (strlen($invoiceNumber) > 50) {
                $this->addError('invoiceNumber', 'Invoice number must not exceed 50 characters');
            }

            // Check for invalid characters (allow alphanumeric, dashes, underscores)
            if (!preg_match('/^[A-Za-z0-9\-_]+$/', $invoiceNumber)) {
                $this->addError('invoiceNumber', 'Invoice number contains invalid characters (use only letters, numbers, dashes, underscores)');
            }
        }
    }

    /**
     * Check if a value is a valid ID (positive integer).
     *
     * @param mixed $value Value to check
     * @return bool
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
     * @return bool
     */
    public function isValidAmount($value)
    {
        return is_numeric($value);
    }

    /**
     * Check if a value is a valid date in Y-m-d format.
     *
     * @param mixed $value Value to check
     * @return bool
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

        // Validate date
        return checkdate((int)$month, (int)$day, (int)$year);
    }

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
     * Get validation errors.
     *
     * @return array Errors array
     */
    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * Clear validation errors.
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
     * @return bool
     */
    public function hasErrors()
    {
        return !empty($this->errors);
    }

    /**
     * Get all errors as a flat array.
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
}
