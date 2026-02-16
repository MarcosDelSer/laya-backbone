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
 * OrganizationInfoStep
 *
 * Handles the organization information step of the setup wizard.
 * Validates and saves daycare organization details including name, address,
 * phone number, and license information.
 *
 * @version v1.0.00
 * @since   v1.0.00
 */
class OrganizationInfoStep
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
     * Validate organization information.
     *
     * @param array $data Organization data
     * @return array Array of validation errors (empty if valid)
     */
    public function validate(array $data)
    {
        $errors = [];

        // Validate organization name
        if (empty($data['name'])) {
            $errors['name'] = 'Organization name is required';
        } elseif (strlen($data['name']) < 2) {
            $errors['name'] = 'Organization name must be at least 2 characters';
        } elseif (strlen($data['name']) > 255) {
            $errors['name'] = 'Organization name must not exceed 255 characters';
        }

        // Validate address
        if (empty($data['address'])) {
            $errors['address'] = 'Address is required';
        } elseif (strlen($data['address']) < 5) {
            $errors['address'] = 'Address must be at least 5 characters';
        } elseif (strlen($data['address']) > 500) {
            $errors['address'] = 'Address must not exceed 500 characters';
        }

        // Validate phone number
        if (empty($data['phone'])) {
            $errors['phone'] = 'Phone number is required';
        } elseif (!$this->isValidPhone($data['phone'])) {
            $errors['phone'] = 'Phone number must contain only digits, spaces, hyphens, parentheses, and plus sign';
        } elseif (strlen($data['phone']) > 50) {
            $errors['phone'] = 'Phone number must not exceed 50 characters';
        }

        // Validate license number (optional but must meet format if provided)
        if (!empty($data['licenseNumber'])) {
            if (strlen($data['licenseNumber']) > 100) {
                $errors['licenseNumber'] = 'License number must not exceed 100 characters';
            }
        }

        // Validate email (optional but must be valid if provided)
        if (!empty($data['email'])) {
            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                $errors['email'] = 'Invalid email address format';
            } elseif (strlen($data['email']) > 255) {
                $errors['email'] = 'Email must not exceed 255 characters';
            }
        }

        // Validate website (optional but must be valid if provided)
        if (!empty($data['website'])) {
            if (!filter_var($data['website'], FILTER_VALIDATE_URL)) {
                $errors['website'] = 'Invalid website URL format';
            } elseif (strlen($data['website']) > 255) {
                $errors['website'] = 'Website URL must not exceed 255 characters';
            }
        }

        return $errors;
    }

    /**
     * Check if phone number format is valid.
     * Allows digits, spaces, hyphens, parentheses, and plus sign.
     *
     * @param string $phone Phone number
     * @return bool True if valid
     */
    protected function isValidPhone($phone)
    {
        // Allow digits, spaces, hyphens, parentheses, and plus sign
        return preg_match('/^[\d\s\-\(\)\+]+$/', $phone) === 1;
    }

    /**
     * Save organization information to the database.
     *
     * @param array $data Organization data
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

            // Check if organization record already exists
            $existing = $this->getOrganizationInfo();

            if ($existing) {
                // Update existing record
                return $this->updateOrganization($data);
            } else {
                // Create new record
                return $this->createOrganization($data);
            }
        } catch (\PDOException $e) {
            return false;
        }
    }

    /**
     * Create a new organization record.
     *
     * @param array $data Organization data
     * @return bool True if successful
     */
    protected function createOrganization(array $data)
    {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO gibbonSchool
                (name, nameShort, address, phone, email, website, licenseNumber)
                VALUES (:name, :nameShort, :address, :phone, :email, :website, :licenseNumber)
            ");

            $nameShort = $this->generateShortName($data['name']);

            $stmt->execute([
                ':name' => $data['name'],
                ':nameShort' => $data['nameShort'] ?? $nameShort,
                ':address' => $data['address'],
                ':phone' => $data['phone'],
                ':email' => $data['email'] ?? null,
                ':website' => $data['website'] ?? null,
                ':licenseNumber' => $data['licenseNumber'] ?? null,
            ]);

            // Save progress in wizard
            $this->installationDetector->saveWizardProgress('organization', $data);

            return true;
        } catch (\PDOException $e) {
            return false;
        }
    }

    /**
     * Update existing organization record.
     *
     * @param array $data Organization data
     * @return bool True if successful
     */
    protected function updateOrganization(array $data)
    {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE gibbonSchool
                SET name = :name,
                    nameShort = :nameShort,
                    address = :address,
                    phone = :phone,
                    email = :email,
                    website = :website,
                    licenseNumber = :licenseNumber
                WHERE gibbonSchoolID = (
                    SELECT MIN(gibbonSchoolID) FROM (SELECT gibbonSchoolID FROM gibbonSchool) AS t
                )
            ");

            $nameShort = $data['nameShort'] ?? $this->generateShortName($data['name']);

            $stmt->execute([
                ':name' => $data['name'],
                ':nameShort' => $nameShort,
                ':address' => $data['address'],
                ':phone' => $data['phone'],
                ':email' => $data['email'] ?? null,
                ':website' => $data['website'] ?? null,
                ':licenseNumber' => $data['licenseNumber'] ?? null,
            ]);

            // Save progress in wizard
            $this->installationDetector->saveWizardProgress('organization', $data);

            return true;
        } catch (\PDOException $e) {
            return false;
        }
    }

    /**
     * Generate a short name from the full organization name.
     * Takes first word or first 20 characters.
     *
     * @param string $name Full organization name
     * @return string Short name
     */
    protected function generateShortName($name)
    {
        // Get first word
        $words = explode(' ', trim($name));
        $shortName = $words[0];

        // Limit to 20 characters
        if (strlen($shortName) > 20) {
            $shortName = substr($shortName, 0, 20);
        }

        return $shortName;
    }

    /**
     * Get saved organization information.
     *
     * @return array|null Organization data or null if not found
     */
    public function getOrganizationInfo()
    {
        try {
            $stmt = $this->pdo->query("
                SELECT name, nameShort, address, phone, email, website, licenseNumber
                FROM gibbonSchool
                ORDER BY gibbonSchoolID ASC
                LIMIT 1
            ");

            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            return $result ?: null;
        } catch (\PDOException $e) {
            return null;
        }
    }

    /**
     * Check if organization information has been saved.
     *
     * @return bool True if organization info exists
     */
    public function isCompleted()
    {
        $info = $this->getOrganizationInfo();
        return $info !== null && !empty($info['name']);
    }

    /**
     * Get organization info from wizard progress (for resume capability).
     *
     * @return array|null Organization data from wizard progress
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
     * Prepare organization data for display/editing.
     * Merges saved data with wizard progress.
     *
     * @return array Organization data
     */
    public function prepareData()
    {
        // Start with saved organization info
        $data = $this->getOrganizationInfo() ?: [];

        // Override with wizard progress if available (for resume)
        $wizardData = $this->getWizardProgress();
        if ($wizardData) {
            $data = array_merge($data, $wizardData);
        }

        // Ensure all fields have default values
        return array_merge([
            'name' => '',
            'nameShort' => '',
            'address' => '',
            'phone' => '',
            'email' => '',
            'website' => '',
            'licenseNumber' => '',
        ], $data);
    }

    /**
     * Clear organization information (for testing/reset).
     *
     * @return bool True if successful
     */
    public function clear()
    {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM gibbonSchool");
            $stmt->execute();
            return true;
        } catch (\PDOException $e) {
            return false;
        }
    }
}
