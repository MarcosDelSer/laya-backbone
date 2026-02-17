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
 * FinanceSettingsStep
 *
 * Handles the finance settings configuration step of the setup wizard.
 * Validates and saves financial settings including daily rates, tax numbers,
 * payment terms, and currency settings for daycare management.
 *
 * @version v1.0.00
 * @since   v1.0.00
 */
class FinanceSettingsStep
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
     * Supported currencies (ISO 4217 codes)
     */
    const SUPPORTED_CURRENCIES = ['USD', 'EUR', 'GBP', 'CAD', 'AUD', 'JPY', 'CNY', 'INR'];

    /**
     * Payment term types
     */
    const PAYMENT_TERMS = ['immediate', 'net7', 'net15', 'net30', 'net60', 'net90', 'custom'];

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
     * Validate finance settings information.
     *
     * @param array $data Finance settings data
     * @return array Array of validation errors (empty if valid)
     */
    public function validate(array $data)
    {
        $errors = [];

        // Validate currency
        if (empty($data['currency'])) {
            $errors['currency'] = 'Currency is required';
        } elseif (!in_array($data['currency'], self::SUPPORTED_CURRENCIES)) {
            $errors['currency'] = 'Currency must be one of: ' . implode(', ', self::SUPPORTED_CURRENCIES);
        }

        // Validate daily rate
        if (isset($data['dailyRate'])) {
            if ($data['dailyRate'] === '' || $data['dailyRate'] === null) {
                $errors['dailyRate'] = 'Daily rate is required';
            } elseif (!is_numeric($data['dailyRate'])) {
                $errors['dailyRate'] = 'Daily rate must be a number';
            } elseif ((float)$data['dailyRate'] < 0) {
                $errors['dailyRate'] = 'Daily rate must be 0 or greater';
            } elseif ((float)$data['dailyRate'] > 9999.99) {
                $errors['dailyRate'] = 'Daily rate must not exceed 9999.99';
            }
        } else {
            $errors['dailyRate'] = 'Daily rate is required';
        }

        // Validate tax number (optional)
        if (isset($data['taxNumber']) && !empty($data['taxNumber'])) {
            if (strlen($data['taxNumber']) < 5) {
                $errors['taxNumber'] = 'Tax number must be at least 5 characters';
            } elseif (strlen($data['taxNumber']) > 50) {
                $errors['taxNumber'] = 'Tax number must not exceed 50 characters';
            }
        }

        // Validate VAT number (optional)
        if (isset($data['vatNumber']) && !empty($data['vatNumber'])) {
            if (strlen($data['vatNumber']) < 5) {
                $errors['vatNumber'] = 'VAT number must be at least 5 characters';
            } elseif (strlen($data['vatNumber']) > 50) {
                $errors['vatNumber'] = 'VAT number must not exceed 50 characters';
            }
        }

        // Validate payment terms
        if (empty($data['paymentTerms'])) {
            $errors['paymentTerms'] = 'Payment terms are required';
        } elseif (!in_array($data['paymentTerms'], self::PAYMENT_TERMS)) {
            $errors['paymentTerms'] = 'Payment terms must be one of: ' . implode(', ', self::PAYMENT_TERMS);
        } elseif ($data['paymentTerms'] === 'custom') {
            // Validate custom payment terms days
            if (empty($data['customPaymentDays'])) {
                $errors['customPaymentDays'] = 'Custom payment days are required when payment terms is custom';
            } elseif (!is_numeric($data['customPaymentDays'])) {
                $errors['customPaymentDays'] = 'Custom payment days must be a number';
            } elseif ((int)$data['customPaymentDays'] < 1) {
                $errors['customPaymentDays'] = 'Custom payment days must be at least 1';
            } elseif ((int)$data['customPaymentDays'] > 365) {
                $errors['customPaymentDays'] = 'Custom payment days must not exceed 365';
            }
        }

        // Validate late fee percentage (optional)
        if (isset($data['lateFeePercentage']) && $data['lateFeePercentage'] !== '' && $data['lateFeePercentage'] !== null) {
            if (!is_numeric($data['lateFeePercentage'])) {
                $errors['lateFeePercentage'] = 'Late fee percentage must be a number';
            } elseif ((float)$data['lateFeePercentage'] < 0) {
                $errors['lateFeePercentage'] = 'Late fee percentage must be 0 or greater';
            } elseif ((float)$data['lateFeePercentage'] > 100) {
                $errors['lateFeePercentage'] = 'Late fee percentage must not exceed 100';
            }
        }

        // Validate invoice prefix (optional)
        if (isset($data['invoicePrefix']) && !empty($data['invoicePrefix'])) {
            if (strlen($data['invoicePrefix']) > 20) {
                $errors['invoicePrefix'] = 'Invoice prefix must not exceed 20 characters';
            } elseif (!preg_match('/^[A-Z0-9-]+$/i', $data['invoicePrefix'])) {
                $errors['invoicePrefix'] = 'Invoice prefix must contain only letters, numbers, and hyphens';
            }
        }

        // Validate invoice start number (optional)
        if (isset($data['invoiceStartNumber']) && $data['invoiceStartNumber'] !== '' && $data['invoiceStartNumber'] !== null) {
            if (!is_numeric($data['invoiceStartNumber'])) {
                $errors['invoiceStartNumber'] = 'Invoice start number must be a number';
            } elseif ((int)$data['invoiceStartNumber'] < 1) {
                $errors['invoiceStartNumber'] = 'Invoice start number must be at least 1';
            } elseif ((int)$data['invoiceStartNumber'] > 999999) {
                $errors['invoiceStartNumber'] = 'Invoice start number must not exceed 999999';
            }
        }

        // Validate tax rate (optional)
        if (isset($data['taxRate']) && $data['taxRate'] !== '' && $data['taxRate'] !== null) {
            if (!is_numeric($data['taxRate'])) {
                $errors['taxRate'] = 'Tax rate must be a number';
            } elseif ((float)$data['taxRate'] < 0) {
                $errors['taxRate'] = 'Tax rate must be 0 or greater';
            } elseif ((float)$data['taxRate'] > 100) {
                $errors['taxRate'] = 'Tax rate must not exceed 100';
            }
        }

        return $errors;
    }

    /**
     * Save finance settings to the database.
     *
     * @param array $data Finance settings data
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
                // Save currency
                $this->saveSetting('Finance', 'currency', $data['currency']);

                // Save daily rate
                $this->saveSetting('Finance', 'dailyRate', (string)(float)$data['dailyRate']);

                // Save tax number (optional)
                if (!empty($data['taxNumber'])) {
                    $this->saveSetting('Finance', 'taxNumber', $data['taxNumber']);
                }

                // Save VAT number (optional)
                if (!empty($data['vatNumber'])) {
                    $this->saveSetting('Finance', 'vatNumber', $data['vatNumber']);
                }

                // Save payment terms
                $this->saveSetting('Finance', 'paymentTerms', $data['paymentTerms']);
                if ($data['paymentTerms'] === 'custom' && !empty($data['customPaymentDays'])) {
                    $this->saveSetting('Finance', 'customPaymentDays', (string)(int)$data['customPaymentDays']);
                }

                // Save late fee percentage (optional)
                if (isset($data['lateFeePercentage']) && $data['lateFeePercentage'] !== '' && $data['lateFeePercentage'] !== null) {
                    $this->saveSetting('Finance', 'lateFeePercentage', (string)(float)$data['lateFeePercentage']);
                }

                // Save invoice prefix (optional)
                if (!empty($data['invoicePrefix'])) {
                    $this->saveSetting('Finance', 'invoicePrefix', $data['invoicePrefix']);
                }

                // Save invoice start number (optional)
                if (isset($data['invoiceStartNumber']) && $data['invoiceStartNumber'] !== '' && $data['invoiceStartNumber'] !== null) {
                    $this->saveSetting('Finance', 'invoiceStartNumber', (string)(int)$data['invoiceStartNumber']);
                }

                // Save tax rate (optional)
                if (isset($data['taxRate']) && $data['taxRate'] !== '' && $data['taxRate'] !== null) {
                    $this->saveSetting('Finance', 'taxRate', (string)(float)$data['taxRate']);
                }

                // Save progress in wizard
                $this->installationDetector->saveWizardProgress('finance_settings', $data);

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
     * Get saved finance settings information.
     *
     * @return array|null Finance settings data or null if not found
     */
    public function getFinanceSettings()
    {
        try {
            $data = [];

            // Get all finance settings
            $stmt = $this->pdo->prepare("
                SELECT name, value FROM gibbonSetting
                WHERE scope = 'Finance'
            ");
            $stmt->execute();
            $settings = $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);

            if (empty($settings)) {
                return null;
            }

            // Map settings to data array
            $data['currency'] = $settings['currency'] ?? 'USD';
            $data['dailyRate'] = isset($settings['dailyRate']) ? (float)$settings['dailyRate'] : 0;
            $data['taxNumber'] = $settings['taxNumber'] ?? '';
            $data['vatNumber'] = $settings['vatNumber'] ?? '';
            $data['paymentTerms'] = $settings['paymentTerms'] ?? 'net30';
            $data['customPaymentDays'] = isset($settings['customPaymentDays']) ? (int)$settings['customPaymentDays'] : null;
            $data['lateFeePercentage'] = isset($settings['lateFeePercentage']) ? (float)$settings['lateFeePercentage'] : null;
            $data['invoicePrefix'] = $settings['invoicePrefix'] ?? '';
            $data['invoiceStartNumber'] = isset($settings['invoiceStartNumber']) ? (int)$settings['invoiceStartNumber'] : null;
            $data['taxRate'] = isset($settings['taxRate']) ? (float)$settings['taxRate'] : null;

            return $data;
        } catch (\PDOException $e) {
            return null;
        }
    }

    /**
     * Check if finance settings have been configured.
     *
     * @return bool True if finance settings configured
     */
    public function isCompleted()
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) FROM gibbonSetting
                WHERE scope = 'Finance' AND name IN ('currency', 'dailyRate', 'paymentTerms')
            ");
            $stmt->execute();
            $count = (int) $stmt->fetchColumn();
            return $count >= 3;
        } catch (\PDOException $e) {
            return false;
        }
    }

    /**
     * Get finance settings from wizard progress (for resume capability).
     *
     * @return array|null Finance settings data from wizard progress
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
     * Prepare finance settings data for display/editing.
     * Merges saved data with wizard progress.
     *
     * @return array Finance settings data
     */
    public function prepareData()
    {
        // Start with saved finance settings
        $data = $this->getFinanceSettings() ?: $this->getDefaultSettings();

        // Override with wizard progress if available (for resume)
        $wizardData = $this->getWizardProgress();
        if ($wizardData) {
            $data = array_merge($data, $wizardData);
        }

        return $data;
    }

    /**
     * Get default finance settings.
     *
     * @return array Default settings
     */
    public function getDefaultSettings()
    {
        return [
            'currency' => 'USD',
            'dailyRate' => 0,
            'taxNumber' => '',
            'vatNumber' => '',
            'paymentTerms' => 'net30',
            'customPaymentDays' => null,
            'lateFeePercentage' => null,
            'invoicePrefix' => 'INV',
            'invoiceStartNumber' => 1,
            'taxRate' => null,
        ];
    }

    /**
     * Clear finance settings (for testing/reset).
     *
     * @return bool True if successful
     */
    public function clear()
    {
        try {
            $this->pdo->beginTransaction();

            try {
                // Delete all finance settings
                $stmt = $this->pdo->prepare("
                    DELETE FROM gibbonSetting
                    WHERE scope = 'Finance'
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
     * Get supported currencies.
     *
     * @return array Supported currency codes
     */
    public function getSupportedCurrencies()
    {
        return self::SUPPORTED_CURRENCIES;
    }

    /**
     * Get payment term options.
     *
     * @return array Payment term types
     */
    public function getPaymentTermOptions()
    {
        return self::PAYMENT_TERMS;
    }
}
