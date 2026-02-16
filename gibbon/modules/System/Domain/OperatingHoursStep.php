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

namespace Gibbon\Module\System\Domain;

use Gibbon\Domain\System\SettingGateway;

/**
 * OperatingHoursStep
 *
 * Handles the operating hours configuration step of the setup wizard.
 * Validates and saves daycare operating schedule (Monday-Sunday) with
 * opening and closing times, plus closure/holiday days.
 *
 * @version v1.0.00
 * @since   v1.0.00
 */
class OperatingHoursStep
{
    /**
     * @var SettingGateway
     */
    protected $settingGateway;

    /**
     * @var \PDO
     */
    protected $pdo;

    /**
     * @var InstallationDetector
     */
    protected $installationDetector;

    /**
     * Days of the week
     */
    const DAYS_OF_WEEK = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

    /**
     * Constructor.
     *
     * @param SettingGateway $settingGateway Settings gateway
     * @param \PDO $pdo Database connection
     * @param InstallationDetector $installationDetector Installation detector
     */
    public function __construct(
        SettingGateway $settingGateway,
        \PDO $pdo,
        InstallationDetector $installationDetector
    ) {
        $this->settingGateway = $settingGateway;
        $this->pdo = $pdo;
        $this->installationDetector = $installationDetector;
    }

    /**
     * Validate operating hours information.
     *
     * @param array $data Operating hours data
     * @return array Array of validation errors (empty if valid)
     */
    public function validate(array $data)
    {
        $errors = [];

        // Validate schedule for each day
        if (empty($data['schedule']) || !is_array($data['schedule'])) {
            $errors['schedule'] = 'Schedule data is required';
            return $errors;
        }

        $hasAtLeastOneOpenDay = false;

        foreach (self::DAYS_OF_WEEK as $day) {
            if (!isset($data['schedule'][$day])) {
                continue;
            }

            $daySchedule = $data['schedule'][$day];

            // Check if day is marked as open
            if (!empty($daySchedule['isOpen'])) {
                $hasAtLeastOneOpenDay = true;

                // Validate open time
                if (empty($daySchedule['openTime'])) {
                    $errors["schedule.{$day}.openTime"] = ucfirst($day) . ' open time is required';
                } elseif (!$this->isValidTimeFormat($daySchedule['openTime'])) {
                    $errors["schedule.{$day}.openTime"] = ucfirst($day) . ' open time must be in HH:MM format';
                }

                // Validate close time
                if (empty($daySchedule['closeTime'])) {
                    $errors["schedule.{$day}.closeTime"] = ucfirst($day) . ' close time is required';
                } elseif (!$this->isValidTimeFormat($daySchedule['closeTime'])) {
                    $errors["schedule.{$day}.closeTime"] = ucfirst($day) . ' close time must be in HH:MM format';
                }

                // Validate that open time is before close time
                if (
                    !empty($daySchedule['openTime']) &&
                    !empty($daySchedule['closeTime']) &&
                    $this->isValidTimeFormat($daySchedule['openTime']) &&
                    $this->isValidTimeFormat($daySchedule['closeTime'])
                ) {
                    if (!$this->isOpenBeforeClose($daySchedule['openTime'], $daySchedule['closeTime'])) {
                        $errors["schedule.{$day}.time"] = ucfirst($day) . ' open time must be before close time';
                    }
                }
            }
        }

        // Ensure at least one day is marked as open
        if (!$hasAtLeastOneOpenDay) {
            $errors['schedule'] = 'At least one day must be marked as open';
        }

        // Validate closure days (optional)
        if (isset($data['closureDays']) && !empty($data['closureDays'])) {
            if (!is_array($data['closureDays'])) {
                $errors['closureDays'] = 'Closure days must be an array';
            } else {
                foreach ($data['closureDays'] as $index => $closureDay) {
                    if (empty($closureDay['date'])) {
                        $errors["closureDays.{$index}.date"] = 'Closure date is required';
                    } elseif (!$this->isValidDateFormat($closureDay['date'])) {
                        $errors["closureDays.{$index}.date"] = 'Closure date must be in YYYY-MM-DD format';
                    }

                    if (empty($closureDay['reason'])) {
                        $errors["closureDays.{$index}.reason"] = 'Closure reason is required';
                    } elseif (strlen($closureDay['reason']) > 255) {
                        $errors["closureDays.{$index}.reason"] = 'Closure reason must not exceed 255 characters';
                    }
                }
            }
        }

        return $errors;
    }

    /**
     * Check if time format is valid (HH:MM).
     *
     * @param string $time Time string
     * @return bool True if valid
     */
    protected function isValidTimeFormat($time)
    {
        return preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $time) === 1;
    }

    /**
     * Check if date format is valid (YYYY-MM-DD).
     *
     * @param string $date Date string
     * @return bool True if valid
     */
    protected function isValidDateFormat($date)
    {
        $d = \DateTime::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') === $date;
    }

    /**
     * Check if open time is before close time.
     *
     * @param string $openTime Open time (HH:MM)
     * @param string $closeTime Close time (HH:MM)
     * @return bool True if open time is before close time
     */
    protected function isOpenBeforeClose($openTime, $closeTime)
    {
        list($openHour, $openMin) = explode(':', $openTime);
        list($closeHour, $closeMin) = explode(':', $closeTime);

        $openMinutes = (int)$openHour * 60 + (int)$openMin;
        $closeMinutes = (int)$closeHour * 60 + (int)$closeMin;

        return $openMinutes < $closeMinutes;
    }

    /**
     * Save operating hours to the database.
     *
     * @param array $data Operating hours data
     * @return bool True if successful
     */
    public function save(array $data)
    {
        try {
            // Validate data first
            $errors = $this->validate($data);
            if (!empty($errors)) {
                return false;
            }

            // Begin transaction
            $this->pdo->beginTransaction();

            try {
                // Save schedule to settings
                $scheduleJson = json_encode($data['schedule']);
                $this->saveSetting('Daycare', 'operatingHoursSchedule', $scheduleJson);

                // Save closure days to database
                if (isset($data['closureDays']) && is_array($data['closureDays'])) {
                    // Clear existing closure days first
                    $this->clearClosureDays();

                    // Insert new closure days
                    foreach ($data['closureDays'] as $closureDay) {
                        $this->addClosureDay($closureDay);
                    }
                }

                // Save timezone if provided
                if (!empty($data['timezone'])) {
                    $this->saveSetting('System', 'timezone', $data['timezone']);
                }

                // Save progress in wizard
                $this->installationDetector->saveWizardProgress('operating_hours', $data);

                $this->pdo->commit();
                return true;
            } catch (\Exception $e) {
                $this->pdo->rollBack();
                return false;
            }
        } catch (\PDOException $e) {
            return false;
        }
    }

    /**
     * Save a setting to the database.
     *
     * @param string $scope Setting scope
     * @param string $name Setting name
     * @param string $value Setting value
     * @return bool True if successful
     */
    protected function saveSetting($scope, $name, $value)
    {
        try {
            // Check if setting exists
            $stmt = $this->pdo->prepare("
                SELECT gibbonSettingID FROM gibbonSetting
                WHERE scope = :scope AND name = :name
            ");
            $stmt->execute([':scope' => $scope, ':name' => $name]);
            $exists = $stmt->fetchColumn();

            if ($exists) {
                // Update existing setting
                $stmt = $this->pdo->prepare("
                    UPDATE gibbonSetting
                    SET value = :value
                    WHERE scope = :scope AND name = :name
                ");
            } else {
                // Insert new setting
                $stmt = $this->pdo->prepare("
                    INSERT INTO gibbonSetting (scope, name, value, nameDisplay, description)
                    VALUES (:scope, :name, :value, :nameDisplay, :description)
                ");
                $stmt->bindValue(':nameDisplay', ucfirst($name));
                $stmt->bindValue(':description', 'Auto-generated by setup wizard');
            }

            $stmt->bindValue(':scope', $scope);
            $stmt->bindValue(':name', $name);
            $stmt->bindValue(':value', $value);
            $stmt->execute();

            return true;
        } catch (\PDOException $e) {
            throw $e;
        }
    }

    /**
     * Clear all closure days.
     *
     * @return bool True if successful
     */
    protected function clearClosureDays()
    {
        try {
            // First check if table exists
            $stmt = $this->pdo->query("SHOW TABLES LIKE 'gibbonSchoolClosureDate'");
            if (!$stmt->fetchColumn()) {
                // Table doesn't exist, create it
                $this->createClosureDaysTable();
            }

            $stmt = $this->pdo->prepare("DELETE FROM gibbonSchoolClosureDate");
            $stmt->execute();
            return true;
        } catch (\PDOException $e) {
            throw $e;
        }
    }

    /**
     * Create closure days table if it doesn't exist.
     *
     * @return bool True if successful
     */
    protected function createClosureDaysTable()
    {
        try {
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS gibbonSchoolClosureDate (
                    gibbonSchoolClosureDateID INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    date DATE NOT NULL,
                    reason VARCHAR(255) NOT NULL,
                    PRIMARY KEY (gibbonSchoolClosureDateID),
                    INDEX idx_date (date)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
            return true;
        } catch (\PDOException $e) {
            throw $e;
        }
    }

    /**
     * Add a closure day.
     *
     * @param array $closureDay Closure day data (date, reason)
     * @return bool True if successful
     */
    protected function addClosureDay(array $closureDay)
    {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO gibbonSchoolClosureDate (date, reason)
                VALUES (:date, :reason)
            ");
            $stmt->execute([
                ':date' => $closureDay['date'],
                ':reason' => $closureDay['reason'],
            ]);
            return true;
        } catch (\PDOException $e) {
            throw $e;
        }
    }

    /**
     * Get saved operating hours information.
     *
     * @return array|null Operating hours data or null if not found
     */
    public function getOperatingHours()
    {
        try {
            $data = [];

            // Get schedule from settings
            $stmt = $this->pdo->prepare("
                SELECT value FROM gibbonSetting
                WHERE scope = 'Daycare' AND name = 'operatingHoursSchedule'
            ");
            $stmt->execute();
            $scheduleJson = $stmt->fetchColumn();

            if ($scheduleJson) {
                $data['schedule'] = json_decode($scheduleJson, true);
            } else {
                $data['schedule'] = $this->getDefaultSchedule();
            }

            // Get closure days
            $data['closureDays'] = $this->getClosureDays();

            // Get timezone
            $stmt = $this->pdo->prepare("
                SELECT value FROM gibbonSetting
                WHERE scope = 'System' AND name = 'timezone'
            ");
            $stmt->execute();
            $timezone = $stmt->fetchColumn();
            $data['timezone'] = $timezone ?: 'UTC';

            return $data;
        } catch (\PDOException $e) {
            return null;
        }
    }

    /**
     * Get default schedule (all days closed).
     *
     * @return array Default schedule
     */
    protected function getDefaultSchedule()
    {
        $schedule = [];
        foreach (self::DAYS_OF_WEEK as $day) {
            $schedule[$day] = [
                'isOpen' => false,
                'openTime' => '08:00',
                'closeTime' => '18:00',
            ];
        }
        return $schedule;
    }

    /**
     * Get closure days from database.
     *
     * @return array Closure days
     */
    protected function getClosureDays()
    {
        try {
            // Check if table exists
            $stmt = $this->pdo->query("SHOW TABLES LIKE 'gibbonSchoolClosureDate'");
            if (!$stmt->fetchColumn()) {
                return [];
            }

            $stmt = $this->pdo->query("
                SELECT date, reason
                FROM gibbonSchoolClosureDate
                ORDER BY date ASC
            ");
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            return [];
        }
    }

    /**
     * Check if operating hours have been configured.
     *
     * @return bool True if operating hours configured
     */
    public function isCompleted()
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) FROM gibbonSetting
                WHERE scope = 'Daycare' AND name = 'operatingHoursSchedule'
            ");
            $stmt->execute();
            $count = (int) $stmt->fetchColumn();
            return $count > 0;
        } catch (\PDOException $e) {
            return false;
        }
    }

    /**
     * Get operating hours from wizard progress (for resume capability).
     *
     * @return array|null Operating hours data from wizard progress
     */
    public function getWizardProgress()
    {
        $progress = $this->installationDetector->getWizardProgress();

        if ($progress && isset($progress['stepData']) && is_array($progress['stepData'])) {
            return $progress['stepData'];
        }

        return null;
    }

    /**
     * Prepare operating hours data for display/editing.
     * Merges saved data with wizard progress.
     *
     * @return array Operating hours data
     */
    public function prepareData()
    {
        // Start with saved operating hours
        $data = $this->getOperatingHours() ?: [
            'schedule' => $this->getDefaultSchedule(),
            'closureDays' => [],
            'timezone' => 'UTC',
        ];

        // Override with wizard progress if available (for resume)
        $wizardData = $this->getWizardProgress();
        if ($wizardData) {
            $data = array_merge($data, $wizardData);
        }

        return $data;
    }

    /**
     * Clear operating hours (for testing/reset).
     *
     * @return bool True if successful
     */
    public function clear()
    {
        try {
            $this->pdo->beginTransaction();

            try {
                // Delete settings
                $stmt = $this->pdo->prepare("
                    DELETE FROM gibbonSetting
                    WHERE scope = 'Daycare' AND name = 'operatingHoursSchedule'
                ");
                $stmt->execute();

                // Clear closure days
                $this->clearClosureDays();

                $this->pdo->commit();
                return true;
            } catch (\PDOException $e) {
                $this->pdo->rollBack();
                return false;
            }
        } catch (\PDOException $e) {
            return false;
        }
    }
}
