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
 * SampleDataImportStep
 *
 * Handles the optional sample data import step of the setup wizard.
 * Allows users to import sample data for various entities (students, parents,
 * staff, attendance records, invoices, etc.) to get started quickly.
 *
 * @version v1.0.00
 * @since   v1.0.00
 */
class SampleDataImportStep
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
     * Available sample data categories
     */
    const SAMPLE_DATA_CATEGORIES = [
        'students',
        'parents',
        'staff',
        'attendance',
        'invoices',
        'meals',
        'activities',
    ];

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
     * Validate sample data import information.
     *
     * @param array $data Sample data import configuration
     * @return array Array of validation errors (empty if valid)
     */
    public function validate(array $data)
    {
        $errors = [];

        // Import is optional, so if not requested, validation passes
        if (empty($data['importSampleData']) || $data['importSampleData'] === false) {
            return $errors;
        }

        // Validate categories if specified
        if (isset($data['categories']) && is_array($data['categories'])) {
            foreach ($data['categories'] as $category) {
                if (!in_array($category, self::SAMPLE_DATA_CATEGORIES)) {
                    $errors['categories'] = 'Invalid category: ' . $category;
                    break;
                }
            }
        }

        // Validate record counts (if specified)
        if (isset($data['studentCount']) && $data['studentCount'] !== '' && $data['studentCount'] !== null) {
            if (!is_numeric($data['studentCount'])) {
                $errors['studentCount'] = 'Student count must be a number';
            } elseif ((int)$data['studentCount'] < 0) {
                $errors['studentCount'] = 'Student count must be 0 or greater';
            } elseif ((int)$data['studentCount'] > 1000) {
                $errors['studentCount'] = 'Student count must not exceed 1000';
            }
        }

        if (isset($data['parentCount']) && $data['parentCount'] !== '' && $data['parentCount'] !== null) {
            if (!is_numeric($data['parentCount'])) {
                $errors['parentCount'] = 'Parent count must be a number';
            } elseif ((int)$data['parentCount'] < 0) {
                $errors['parentCount'] = 'Parent count must be 0 or greater';
            } elseif ((int)$data['parentCount'] > 1000) {
                $errors['parentCount'] = 'Parent count must not exceed 1000';
            }
        }

        if (isset($data['staffCount']) && $data['staffCount'] !== '' && $data['staffCount'] !== null) {
            if (!is_numeric($data['staffCount'])) {
                $errors['staffCount'] = 'Staff count must be a number';
            } elseif ((int)$data['staffCount'] < 0) {
                $errors['staffCount'] = 'Staff count must be 0 or greater';
            } elseif ((int)$data['staffCount'] > 100) {
                $errors['staffCount'] = 'Staff count must not exceed 100';
            }
        }

        return $errors;
    }

    /**
     * Save sample data import configuration and optionally import data.
     *
     * @param array $data Sample data import configuration
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
                // Save the import decision
                $this->saveSetting('System', 'sampleDataImported',
                    !empty($data['importSampleData']) ? 'Y' : 'N');

                // If user chose to import sample data, do it
                if (!empty($data['importSampleData']) && $data['importSampleData'] === true) {
                    $categories = $data['categories'] ?? self::SAMPLE_DATA_CATEGORIES;

                    // Import sample data for each selected category
                    foreach ($categories as $category) {
                        $this->importCategoryData($category, $data);
                    }

                    // Save what was imported
                    $this->saveSetting('System', 'sampleDataCategories',
                        json_encode($categories));
                }

                // Save progress in wizard
                $this->installationDetector->saveWizardProgress('sample_data_import', $data);

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
     * Import sample data for a specific category.
     *
     * @param string $category Category to import
     * @param array $config Configuration data
     * @return bool True if successful
     */
    protected function importCategoryData($category, array $config)
    {
        try {
            switch ($category) {
                case 'students':
                    return $this->importStudents($config);
                case 'parents':
                    return $this->importParents($config);
                case 'staff':
                    return $this->importStaff($config);
                case 'attendance':
                    return $this->importAttendance($config);
                case 'invoices':
                    return $this->importInvoices($config);
                case 'meals':
                    return $this->importMeals($config);
                case 'activities':
                    return $this->importActivities($config);
                default:
                    return false;
            }
        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * Import sample students.
     *
     * @param array $config Configuration data
     * @return bool True if successful
     */
    protected function importStudents(array $config)
    {
        try {
            $count = isset($config['studentCount']) ? (int)$config['studentCount'] : 10;
            $count = max(0, min($count, 1000)); // Clamp between 0 and 1000

            // Get current school year
            $schoolYearID = $this->getCurrentSchoolYearID();
            if (!$schoolYearID) {
                return false;
            }

            // Get available groups
            $groups = $this->getAvailableGroups();
            if (empty($groups)) {
                return false;
            }

            $firstNames = ['Emma', 'Liam', 'Olivia', 'Noah', 'Ava', 'Ethan', 'Sophia', 'Mason', 'Isabella', 'William'];
            $lastNames = ['Smith', 'Johnson', 'Williams', 'Brown', 'Jones', 'Garcia', 'Miller', 'Davis', 'Rodriguez', 'Martinez'];

            for ($i = 0; $i < $count; $i++) {
                $firstName = $firstNames[array_rand($firstNames)];
                $lastName = $lastNames[array_rand($lastNames)];
                $group = $groups[array_rand($groups)];

                // Create student in gibbonPerson table
                $stmt = $this->pdo->prepare("
                    INSERT INTO gibbonPerson (title, surname, firstName, preferredName, officialName,
                        gender, dob, username, password, status, canLogin)
                    VALUES (:title, :surname, :firstName, :preferredName, :officialName,
                        :gender, :dob, :username, :password, :status, :canLogin)
                ");

                $gender = rand(0, 1) ? 'M' : 'F';
                $age = rand($group['minAge'] ?? 0, $group['maxAge'] ?? 5);
                $dob = date('Y-m-d', strtotime("-{$age} years"));
                $username = strtolower(substr($firstName, 0, 1) . $lastName . rand(1000, 9999));

                $stmt->execute([
                    ':title' => $gender === 'M' ? 'Mr' : 'Ms',
                    ':surname' => $lastName,
                    ':firstName' => $firstName,
                    ':preferredName' => $firstName,
                    ':officialName' => $firstName . ' ' . $lastName,
                    ':gender' => $gender,
                    ':dob' => $dob,
                    ':username' => $username,
                    ':password' => password_hash('password', PASSWORD_DEFAULT),
                    ':status' => 'Full',
                    ':canLogin' => 'N',
                ]);

                $personID = $this->pdo->lastInsertId();

                // Create student enrollment
                $stmt = $this->pdo->prepare("
                    INSERT INTO gibbonStudentEnrolment (gibbonPersonID, gibbonSchoolYearID, gibbonYearGroupID)
                    VALUES (:gibbonPersonID, :gibbonSchoolYearID, :gibbonYearGroupID)
                ");
                $stmt->execute([
                    ':gibbonPersonID' => $personID,
                    ':gibbonSchoolYearID' => $schoolYearID,
                    ':gibbonYearGroupID' => 1, // Default year group
                ]);
            }

            return true;
        } catch (\PDOException $e) {
            throw $e;
        }
    }

    /**
     * Import sample parents.
     *
     * @param array $config Configuration data
     * @return bool True if successful
     */
    protected function importParents(array $config)
    {
        try {
            $count = isset($config['parentCount']) ? (int)$config['parentCount'] : 10;
            $count = max(0, min($count, 1000));

            $firstNames = ['John', 'Jane', 'Michael', 'Sarah', 'David', 'Emily', 'Robert', 'Jessica', 'James', 'Ashley'];
            $lastNames = ['Smith', 'Johnson', 'Williams', 'Brown', 'Jones', 'Garcia', 'Miller', 'Davis', 'Rodriguez', 'Martinez'];

            for ($i = 0; $i < $count; $i++) {
                $firstName = $firstNames[array_rand($firstNames)];
                $lastName = $lastNames[array_rand($lastNames)];
                $gender = rand(0, 1) ? 'M' : 'F';
                $username = strtolower(substr($firstName, 0, 1) . $lastName . rand(1000, 9999));
                $email = strtolower($firstName . '.' . $lastName . '@example.com');

                $stmt = $this->pdo->prepare("
                    INSERT INTO gibbonPerson (title, surname, firstName, preferredName, officialName,
                        gender, username, password, email, status, canLogin)
                    VALUES (:title, :surname, :firstName, :preferredName, :officialName,
                        :gender, :username, :password, :email, :status, :canLogin)
                ");

                $stmt->execute([
                    ':title' => $gender === 'M' ? 'Mr' : 'Ms',
                    ':surname' => $lastName,
                    ':firstName' => $firstName,
                    ':preferredName' => $firstName,
                    ':officialName' => $firstName . ' ' . $lastName,
                    ':gender' => $gender,
                    ':username' => $username,
                    ':password' => password_hash('password', PASSWORD_DEFAULT),
                    ':email' => $email,
                    ':status' => 'Full',
                    ':canLogin' => 'Y',
                ]);
            }

            return true;
        } catch (\PDOException $e) {
            throw $e;
        }
    }

    /**
     * Import sample staff.
     *
     * @param array $config Configuration data
     * @return bool True if successful
     */
    protected function importStaff(array $config)
    {
        try {
            $count = isset($config['staffCount']) ? (int)$config['staffCount'] : 5;
            $count = max(0, min($count, 100));

            $firstNames = ['Alice', 'Bob', 'Carol', 'Daniel', 'Eve', 'Frank', 'Grace', 'Henry', 'Ivy', 'Jack'];
            $lastNames = ['Anderson', 'Thomas', 'Taylor', 'Moore', 'Jackson', 'Martin', 'Lee', 'White', 'Harris', 'Clark'];
            $jobTitles = ['Teacher', 'Aide', 'Administrator', 'Caregiver', 'Supervisor'];

            for ($i = 0; $i < $count; $i++) {
                $firstName = $firstNames[array_rand($firstNames)];
                $lastName = $lastNames[array_rand($lastNames)];
                $gender = rand(0, 1) ? 'M' : 'F';
                $username = strtolower(substr($firstName, 0, 1) . $lastName . rand(1000, 9999));
                $email = strtolower($firstName . '.' . $lastName . '@daycare.example.com');
                $jobTitle = $jobTitles[array_rand($jobTitles)];

                $stmt = $this->pdo->prepare("
                    INSERT INTO gibbonPerson (title, surname, firstName, preferredName, officialName,
                        gender, username, password, email, jobTitle, status, canLogin)
                    VALUES (:title, :surname, :firstName, :preferredName, :officialName,
                        :gender, :username, :password, :email, :jobTitle, :status, :canLogin)
                ");

                $stmt->execute([
                    ':title' => $gender === 'M' ? 'Mr' : 'Ms',
                    ':surname' => $lastName,
                    ':firstName' => $firstName,
                    ':preferredName' => $firstName,
                    ':officialName' => $firstName . ' ' . $lastName,
                    ':gender' => $gender,
                    ':username' => $username,
                    ':password' => password_hash('password', PASSWORD_DEFAULT),
                    ':email' => $email,
                    ':jobTitle' => $jobTitle,
                    ':status' => 'Full',
                    ':canLogin' => 'Y',
                ]);
            }

            return true;
        } catch (\PDOException $e) {
            throw $e;
        }
    }

    /**
     * Import sample attendance records.
     *
     * @param array $config Configuration data
     * @return bool True if successful
     */
    protected function importAttendance(array $config)
    {
        // Placeholder - would create sample attendance records
        // This would require students to exist first
        return true;
    }

    /**
     * Import sample invoices.
     *
     * @param array $config Configuration data
     * @return bool True if successful
     */
    protected function importInvoices(array $config)
    {
        // Placeholder - would create sample invoice records
        // This would require students/parents to exist first
        return true;
    }

    /**
     * Import sample meals.
     *
     * @param array $config Configuration data
     * @return bool True if successful
     */
    protected function importMeals(array $config)
    {
        // Placeholder - would create sample meal records
        return true;
    }

    /**
     * Import sample activities.
     *
     * @param array $config Configuration data
     * @return bool True if successful
     */
    protected function importActivities(array $config)
    {
        // Placeholder - would create sample activity records
        return true;
    }

    /**
     * Get current school year ID.
     *
     * @return int|null School year ID or null if not found
     */
    protected function getCurrentSchoolYearID()
    {
        try {
            $stmt = $this->pdo->query("
                SELECT gibbonSchoolYearID FROM gibbonSchoolYear
                WHERE status = 'Current'
                ORDER BY sequenceNumber DESC
                LIMIT 1
            ");
            $result = $stmt->fetchColumn();
            return $result ? (int)$result : null;
        } catch (\PDOException $e) {
            return null;
        }
    }

    /**
     * Get available daycare groups.
     *
     * @return array Available groups
     */
    protected function getAvailableGroups()
    {
        try {
            // Check if table exists
            $stmt = $this->pdo->query("SHOW TABLES LIKE 'gibbonDaycareGroup'");
            if (!$stmt->fetchColumn()) {
                return [];
            }

            $stmt = $this->pdo->query("
                SELECT * FROM gibbonDaycareGroup
                WHERE isActive = 'Y'
                ORDER BY minAge ASC
            ");
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            return [];
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
     * Get saved sample data import information.
     *
     * @return array|null Sample data import data or null if not found
     */
    public function getSampleDataImport()
    {
        try {
            $data = [];

            $stmt = $this->pdo->prepare("
                SELECT name, value FROM gibbonSetting
                WHERE scope = 'System' AND name IN ('sampleDataImported', 'sampleDataCategories')
            ");
            $stmt->execute();
            $settings = $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);

            if (empty($settings)) {
                return null;
            }

            $data['importSampleData'] = ($settings['sampleDataImported'] ?? 'N') === 'Y';
            $data['categories'] = isset($settings['sampleDataCategories'])
                ? json_decode($settings['sampleDataCategories'], true)
                : [];

            return $data;
        } catch (\PDOException $e) {
            return null;
        }
    }

    /**
     * Check if sample data import has been completed.
     *
     * @return bool True if sample data import configured
     */
    public function isCompleted()
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT value FROM gibbonSetting
                WHERE scope = 'System' AND name = 'sampleDataImported'
            ");
            $stmt->execute();
            $value = $stmt->fetchColumn();

            // Completed if setting exists (whether Y or N - user made a choice)
            return $value !== false;
        } catch (\PDOException $e) {
            return false;
        }
    }

    /**
     * Get sample data import from wizard progress (for resume capability).
     *
     * @return array|null Sample data import data from wizard progress
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
     * Prepare sample data import data for display/editing.
     * Merges saved data with wizard progress.
     *
     * @return array Sample data import data
     */
    public function prepareData()
    {
        // Start with saved sample data import
        $data = $this->getSampleDataImport() ?: $this->getDefaultSettings();

        // Override with wizard progress if available (for resume)
        $wizardData = $this->getWizardProgress();
        if ($wizardData) {
            $data = array_merge($data, $wizardData);
        }

        return $data;
    }

    /**
     * Get default sample data import settings.
     *
     * @return array Default settings
     */
    public function getDefaultSettings()
    {
        return [
            'importSampleData' => false,
            'categories' => [],
            'studentCount' => 10,
            'parentCount' => 10,
            'staffCount' => 5,
        ];
    }

    /**
     * Clear sample data import settings (for testing/reset).
     *
     * @return bool True if successful
     */
    public function clear()
    {
        try {
            $this->pdo->beginTransaction();

            try {
                // Delete sample data import settings
                $stmt = $this->pdo->prepare("
                    DELETE FROM gibbonSetting
                    WHERE scope = 'System' AND name IN ('sampleDataImported', 'sampleDataCategories')
                ");
                $stmt->execute();

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

    /**
     * Get available sample data categories.
     *
     * @return array Available categories
     */
    public function getAvailableCategories()
    {
        return self::SAMPLE_DATA_CATEGORIES;
    }
}
