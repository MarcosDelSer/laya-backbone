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

namespace Gibbon\Module\ExampleModule\Service;

use Gibbon\Domain\System\SettingGateway;
use Gibbon\Module\ExampleModule\Domain\ExampleGateway;
use Gibbon\Module\ExampleModule\Validator\ExampleValidator;

/**
 * ExampleService - Complete Service Layer Example
 *
 * This is a comprehensive example demonstrating all the key patterns and
 * best practices for building a Service class in Gibbon LAYA.
 *
 * A Service class encapsulates business logic and acts as a bridge between
 * the presentation layer (view files) and the data layer (gateways).
 *
 * KEY CONCEPTS DEMONSTRATED:
 * 1. Dependency Injection - All dependencies injected via constructor
 * 2. Single Responsibility - Focused on one business domain
 * 3. Stateless Design - No business data stored between method calls
 * 4. Return Structured Data - Always return arrays with success/error info
 * 5. Error Handling - Comprehensive validation and error reporting
 * 6. Clean Public API - Public methods form the service contract
 * 7. Protected Helpers - Internal logic hidden from callers
 * 8. Caching - Performance optimization where appropriate
 *
 * @version v1.0.00
 * @since   v1.0.00
 */
class ExampleService
{
    // ========================================
    // SECTION 1: DEPENDENCIES
    // ========================================
    //
    // Dependencies are injected via constructor to enable:
    // - Testability (can inject mocks)
    // - Flexibility (can swap implementations)
    // - Clear contracts (type-hinted interfaces)
    //
    // RULES:
    // - Always use protected visibility (not private or public)
    // - Type-hint all dependencies
    // - Document with PHPDoc

    /**
     * @var SettingGateway Settings gateway for configuration
     */
    protected $settingGateway;

    /**
     * @var ExampleGateway Data gateway for database operations
     */
    protected $exampleGateway;

    /**
     * @var ExampleValidator Input validator
     */
    protected $validator;

    // ========================================
    // SECTION 2: STATE (Minimal - Caching Only)
    // ========================================
    //
    // Services should be mostly STATELESS.
    // Only use instance properties for caching data fetched from external sources.
    //
    // AVOID storing business data between method calls as it:
    // - Makes services non-thread-safe
    // - Complicates testing
    // - Creates hidden dependencies

    /**
     * @var array|null Cached configuration settings
     */
    protected $config = null;

    /**
     * @var array Tax rate cache (performance optimization)
     */
    protected $taxRates = null;

    // ========================================
    // SECTION 3: CONSTRUCTOR
    // ========================================
    //
    // The constructor should:
    // - Accept all dependencies as parameters
    // - Type-hint each dependency
    // - Assign dependencies to properties
    // - NOT contain complex logic
    //
    // Gibbon's container will automatically inject dependencies
    // when you call: $container->get(ExampleService::class)

    /**
     * Constructor.
     *
     * @param SettingGateway $settingGateway Settings gateway
     * @param ExampleGateway $exampleGateway Example data gateway
     * @param ExampleValidator $validator Validator for input data
     */
    public function __construct(
        SettingGateway $settingGateway,
        ExampleGateway $exampleGateway,
        ExampleValidator $validator
    ) {
        $this->settingGateway = $settingGateway;
        $this->exampleGateway = $exampleGateway;
        $this->validator = $validator;
    }

    // ========================================
    // SECTION 4: PUBLIC API METHODS
    // ========================================
    //
    // Public methods form the service's contract.
    // These are the methods that view files and other services can call.
    //
    // PUBLIC METHOD RULES:
    // 1. Accept input parameters (never use stored state)
    // 2. Validate input using validators
    // 3. Execute business logic
    // 4. Return structured arrays (never echo/print)
    // 5. Include comprehensive PHPDoc
    // 6. Handle errors gracefully

    /**
     * PATTERN 1: CREATE OPERATION
     *
     * Demonstrates complete create workflow with validation and error handling.
     *
     * @param array $data Record data to create
     * @return array Result with success status, errors, and created record ID
     *
     * @example
     * $result = $service->createRecord([
     *     'name' => 'Example',
     *     'amount' => 100.00,
     *     'date' => '2024-01-15',
     * ]);
     *
     * if ($result['success']) {
     *     echo "Created record with ID: " . $result['recordID'];
     * } else {
     *     foreach ($result['errors'] as $field => $messages) {
     *         echo "$field: " . implode(', ', $messages);
     *     }
     * }
     */
    public function createRecord($data)
    {
        // Step 1: Validate input data
        $validation = $this->validator->validate($data);
        if (!$validation['success']) {
            return [
                'success' => false,
                'errors' => $validation['errors'],
                'recordID' => null,
            ];
        }

        // Step 2: Apply business rules (if needed)
        if (!$this->isWithinBusinessHours($data['date'])) {
            return [
                'success' => false,
                'errors' => ['date' => ['Records can only be created during business hours']],
                'recordID' => null,
            ];
        }

        // Step 3: Enrich data with calculated values
        $data['totalAmount'] = $this->calculateTotal($data['amount']);
        $data['status'] = 'Pending';
        $data['createdAt'] = date('Y-m-d H:i:s');

        // Step 4: Persist to database
        $recordID = $this->exampleGateway->insert($data);

        // Step 5: Return success result
        return [
            'success' => true,
            'recordID' => $recordID,
            'errors' => [],
        ];
    }

    /**
     * PATTERN 2: CALCULATION METHOD
     *
     * Demonstrates pure calculation logic without side effects.
     * These methods are easy to test and reuse.
     *
     * @param float $amount Base amount
     * @return array Breakdown of amounts with subtotal, tax, and total
     *
     * @example
     * $result = $service->calculateAmounts(100.00);
     * // Returns:
     * // [
     * //     'subtotal' => 100.00,
     * //     'gst' => 5.00,
     * //     'qst' => 9.98,
     * //     'totalTax' => 14.98,
     * //     'total' => 114.98,
     * // ]
     */
    public function calculateAmounts($amount)
    {
        $taxRates = $this->getTaxRates();

        $gst = $amount * $taxRates['gst'];
        $qst = $amount * $taxRates['qst'];
        $totalTax = $gst + $qst;
        $total = $amount + $totalTax;

        return [
            'subtotal' => round($amount, 2),
            'gst' => round($gst, 2),
            'qst' => round($qst, 2),
            'totalTax' => round($totalTax, 2),
            'total' => round($total, 2),
        ];
    }

    /**
     * PATTERN 3: QUERY FACADE
     *
     * Simplifies complex data retrieval by hiding query complexity
     * and applying business logic to filter/transform results.
     *
     * @param int $gibbonPersonID Person ID
     * @param array $filters Optional filters (status, dateFrom, dateTo)
     * @return array Array of records with calculated fields
     *
     * @example
     * $records = $service->getRecordsForPerson(123, [
     *     'status' => 'Active',
     *     'dateFrom' => '2024-01-01',
     * ]);
     */
    public function getRecordsForPerson($gibbonPersonID, $filters = [])
    {
        // Step 1: Fetch raw data from gateway
        $records = $this->exampleGateway->selectByPerson($gibbonPersonID, $filters);

        // Step 2: Apply business logic to enrich each record
        $enrichedRecords = [];
        foreach ($records as $record) {
            $record['isOverdue'] = $this->isOverdue($record);
            $record['daysOverdue'] = $this->getDaysOverdue($record);
            $record['canEdit'] = $this->canEdit($record);
            $record['displayStatus'] = $this->getDisplayStatus($record);

            $enrichedRecords[] = $record;
        }

        return $enrichedRecords;
    }

    /**
     * PATTERN 4: WORKFLOW ORCHESTRATION
     *
     * Coordinates multi-step business processes that involve
     * multiple validations, database operations, and business rules.
     *
     * @param int $recordID Record to process
     * @param array $data Processing data
     * @return array Result with success, errors, and updated record
     */
    public function processRecord($recordID, $data)
    {
        // Step 1: Fetch existing record
        $record = $this->exampleGateway->getByID($recordID);
        if (!$record) {
            return [
                'success' => false,
                'errors' => ['record' => ['Record not found']],
            ];
        }

        // Step 2: Check business rules
        if (!$this->canProcess($record)) {
            return [
                'success' => false,
                'errors' => ['status' => ['Record cannot be processed in current state']],
            ];
        }

        // Step 3: Validate processing data
        $validation = $this->validator->validateProcessing($data);
        if (!$validation['success']) {
            return [
                'success' => false,
                'errors' => $validation['errors'],
            ];
        }

        // Step 4: Execute business logic
        $updates = [
            'status' => 'Processed',
            'processedAt' => date('Y-m-d H:i:s'),
            'processedByID' => $data['processedByID'],
            'notes' => $data['notes'],
        ];

        // Step 5: Update database
        $this->exampleGateway->update($recordID, $updates);

        // Step 6: Fetch and return updated record
        $updatedRecord = $this->exampleGateway->getByID($recordID);

        return [
            'success' => true,
            'record' => $updatedRecord,
            'errors' => [],
        ];
    }

    /**
     * PATTERN 5: BUSINESS RULE CHECKER
     *
     * Encapsulates complex business rules that determine if an action is allowed.
     * Returns detailed information about why the rule passed or failed.
     *
     * @param array $record Record to check
     * @param int $userID User attempting the action
     * @return array Result with 'allowed' boolean and 'reason' string
     *
     * @example
     * $check = $service->canDelete($record, $currentUserID);
     * if (!$check['allowed']) {
     *     echo "Cannot delete: " . $check['reason'];
     * }
     */
    public function canDelete($record, $userID)
    {
        // Rule 1: Cannot delete processed records
        if ($record['status'] === 'Processed') {
            return [
                'allowed' => false,
                'reason' => 'Cannot delete processed records',
            ];
        }

        // Rule 2: Cannot delete if locked
        if ($record['locked'] ?? false) {
            return [
                'allowed' => false,
                'reason' => 'Record is locked',
            ];
        }

        // Rule 3: Only creator or admin can delete
        if ($record['createdByID'] != $userID && !$this->isAdmin($userID)) {
            return [
                'allowed' => false,
                'reason' => 'Only the creator or an administrator can delete this record',
            ];
        }

        // Rule 4: Cannot delete if retention period has passed
        if ($this->isRetentionExpired($record['createdAt'])) {
            return [
                'allowed' => false,
                'reason' => 'Record retention period has expired',
            ];
        }

        return [
            'allowed' => true,
            'reason' => '',
        ];
    }

    /**
     * PATTERN 6: CONFIGURATION PROVIDER
     *
     * Centralizes configuration and options, making them easy to maintain
     * and reuse across the application.
     *
     * @return array Available status options [value => label]
     */
    public function getStatusOptions()
    {
        return [
            'Pending' => 'Pending',
            'Active' => 'Active',
            'Processed' => 'Processed',
            'Cancelled' => 'Cancelled',
            'Archived' => 'Archived',
        ];
    }

    /**
     * Get priority levels.
     *
     * @return array Priority levels [value => label]
     */
    public function getPriorityLevels()
    {
        return [
            1 => 'Low',
            2 => 'Medium',
            3 => 'High',
            4 => 'Urgent',
        ];
    }

    // ========================================
    // SECTION 5: PROTECTED HELPER METHODS
    // ========================================
    //
    // Protected methods are internal implementation details.
    // They break down complex operations and promote code reuse.
    //
    // PROTECTED METHOD RULES:
    // 1. Use to break down complex public methods
    // 2. Keep focused on single tasks
    // 3. Can be changed without breaking the public API
    // 4. Document with PHPDoc
    // 5. Avoid making them public unless truly needed

    /**
     * Calculate total amount with taxes.
     *
     * @param float $amount Base amount
     * @return float Total with taxes
     */
    protected function calculateTotal($amount)
    {
        $taxRates = $this->getTaxRates();
        $tax = $amount * $taxRates['combined'];
        return round($amount + $tax, 2);
    }

    /**
     * Get tax rates from settings with caching.
     *
     * Demonstrates caching pattern to avoid repeated database queries.
     *
     * @return array Tax rates [gst, qst, combined]
     */
    protected function getTaxRates()
    {
        // Return cached value if available
        if ($this->taxRates !== null) {
            return $this->taxRates;
        }

        // Fetch from settings
        $gstRate = $this->settingGateway->getSettingByScope('Example Module', 'gstRate') ?: '0.05';
        $qstRate = $this->settingGateway->getSettingByScope('Example Module', 'qstRate') ?: '0.09975';

        // Cache for future calls
        $this->taxRates = [
            'gst' => (float)$gstRate,
            'qst' => (float)$qstRate,
            'combined' => (float)$gstRate + (float)$qstRate,
        ];

        return $this->taxRates;
    }

    /**
     * Get module configuration.
     *
     * @return array Configuration settings
     */
    protected function getConfig()
    {
        if ($this->config !== null) {
            return $this->config;
        }

        $this->config = [
            'businessHoursStart' => $this->settingGateway->getSettingByScope('Example Module', 'businessHoursStart') ?: '09:00',
            'businessHoursEnd' => $this->settingGateway->getSettingByScope('Example Module', 'businessHoursEnd') ?: '17:00',
            'retentionDays' => (int)($this->settingGateway->getSettingByScope('Example Module', 'retentionDays') ?: 365),
        ];

        return $this->config;
    }

    /**
     * Check if a record is overdue.
     *
     * @param array $record Record data
     * @return bool True if overdue
     */
    protected function isOverdue($record)
    {
        if ($record['status'] === 'Processed' || $record['status'] === 'Cancelled') {
            return false;
        }

        if (empty($record['dueDate'])) {
            return false;
        }

        return strtotime($record['dueDate']) < strtotime('today');
    }

    /**
     * Get number of days a record is overdue.
     *
     * @param array $record Record data
     * @return int Days overdue (0 if not overdue)
     */
    protected function getDaysOverdue($record)
    {
        if (!$this->isOverdue($record)) {
            return 0;
        }

        $dueDate = strtotime($record['dueDate']);
        $today = strtotime('today');
        $diff = $today - $dueDate;

        return (int)($diff / 86400); // Convert seconds to days
    }

    /**
     * Check if a record can be edited.
     *
     * @param array $record Record data
     * @return bool True if editable
     */
    protected function canEdit($record)
    {
        // Processed or cancelled records cannot be edited
        if (in_array($record['status'], ['Processed', 'Cancelled', 'Archived'])) {
            return false;
        }

        // Locked records cannot be edited
        if ($record['locked'] ?? false) {
            return false;
        }

        return true;
    }

    /**
     * Get display-friendly status text.
     *
     * @param array $record Record data
     * @return string Display status
     */
    protected function getDisplayStatus($record)
    {
        $status = $record['status'];

        if ($status === 'Pending' && $this->isOverdue($record)) {
            return 'Overdue';
        }

        return $status;
    }

    /**
     * Check if a date/time is within business hours.
     *
     * @param string $datetime Date/time string
     * @return bool True if within business hours
     */
    protected function isWithinBusinessHours($datetime)
    {
        $config = $this->getConfig();
        $time = date('H:i', strtotime($datetime));

        return $time >= $config['businessHoursStart'] && $time <= $config['businessHoursEnd'];
    }

    /**
     * Check if a record can be processed.
     *
     * @param array $record Record data
     * @return bool True if can be processed
     */
    protected function canProcess($record)
    {
        // Only pending or active records can be processed
        if (!in_array($record['status'], ['Pending', 'Active'])) {
            return false;
        }

        // Cannot process locked records
        if ($record['locked'] ?? false) {
            return false;
        }

        return true;
    }

    /**
     * Check if retention period has expired.
     *
     * @param string $createdAt Creation timestamp
     * @return bool True if expired
     */
    protected function isRetentionExpired($createdAt)
    {
        $config = $this->getConfig();
        $retentionDays = $config['retentionDays'];

        $created = strtotime($createdAt);
        $expiryDate = strtotime("+{$retentionDays} days", $created);

        return time() > $expiryDate;
    }

    /**
     * Check if a user is an administrator.
     *
     * @param int $userID User ID
     * @return bool True if admin
     */
    protected function isAdmin($userID)
    {
        // This is a simplified example
        // In real code, this would check the user's role
        return false; // Placeholder implementation
    }
}
