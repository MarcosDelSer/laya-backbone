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
 * AdminAccountCreationStep
 *
 * Handles the admin account creation step of the setup wizard.
 * Validates and creates the first administrator account with appropriate
 * security measures including password hashing and strength validation.
 *
 * @version v1.0.00
 * @since   v1.0.00
 */
class AdminAccountCreationStep
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
     * Validate admin account information.
     *
     * @param array $data Admin account data
     * @return array Array of validation errors (empty if valid)
     */
    public function validate(array $data)
    {
        $errors = [];

        // Validate first name
        if (empty($data['firstName'])) {
            $errors['firstName'] = 'First name is required';
        } elseif (strlen($data['firstName']) < 2) {
            $errors['firstName'] = 'First name must be at least 2 characters';
        } elseif (strlen($data['firstName']) > 100) {
            $errors['firstName'] = 'First name must not exceed 100 characters';
        }

        // Validate surname
        if (empty($data['surname'])) {
            $errors['surname'] = 'Surname is required';
        } elseif (strlen($data['surname']) < 2) {
            $errors['surname'] = 'Surname must be at least 2 characters';
        } elseif (strlen($data['surname']) > 100) {
            $errors['surname'] = 'Surname must not exceed 100 characters';
        }

        // Validate email
        if (empty($data['email'])) {
            $errors['email'] = 'Email address is required';
        } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Invalid email address format';
        } elseif (strlen($data['email']) > 255) {
            $errors['email'] = 'Email must not exceed 255 characters';
        } elseif ($this->emailExists($data['email'])) {
            $errors['email'] = 'Email address already exists';
        }

        // Validate username (optional - use email if not provided)
        if (!empty($data['username'])) {
            if (strlen($data['username']) < 3) {
                $errors['username'] = 'Username must be at least 3 characters';
            } elseif (strlen($data['username']) > 50) {
                $errors['username'] = 'Username must not exceed 50 characters';
            } elseif (!preg_match('/^[a-zA-Z0-9._-]+$/', $data['username'])) {
                $errors['username'] = 'Username can only contain letters, numbers, dots, hyphens, and underscores';
            } elseif ($this->usernameExists($data['username'])) {
                $errors['username'] = 'Username already exists';
            }
        }

        // Validate password
        if (empty($data['password'])) {
            $errors['password'] = 'Password is required';
        } else {
            $passwordErrors = $this->validatePasswordStrength($data['password']);
            if (!empty($passwordErrors)) {
                $errors['password'] = implode('; ', $passwordErrors);
            }
        }

        // Validate password confirmation
        if (empty($data['passwordConfirm'])) {
            $errors['passwordConfirm'] = 'Password confirmation is required';
        } elseif (isset($data['password']) && $data['password'] !== $data['passwordConfirm']) {
            $errors['passwordConfirm'] = 'Passwords do not match';
        }

        return $errors;
    }

    /**
     * Validate password strength.
     * Password must meet security requirements.
     *
     * @param string $password Password to validate
     * @return array Array of validation errors
     */
    protected function validatePasswordStrength($password)
    {
        $errors = [];

        // Minimum length
        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters';
        }

        // Maximum length (for bcrypt compatibility)
        if (strlen($password) > 72) {
            $errors[] = 'Password must not exceed 72 characters';
        }

        // Require at least one uppercase letter
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Password must contain at least one uppercase letter';
        }

        // Require at least one lowercase letter
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = 'Password must contain at least one lowercase letter';
        }

        // Require at least one number
        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = 'Password must contain at least one number';
        }

        // Require at least one special character
        if (!preg_match('/[^a-zA-Z0-9]/', $password)) {
            $errors[] = 'Password must contain at least one special character';
        }

        return $errors;
    }

    /**
     * Check if email already exists in the database.
     *
     * @param string $email Email address
     * @return bool True if email exists
     */
    protected function emailExists($email)
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) FROM gibbonPerson WHERE email = :email
            ");
            $stmt->execute([':email' => $email]);
            $count = (int) $stmt->fetchColumn();
            return $count > 0;
        } catch (\PDOException $e) {
            return false;
        }
    }

    /**
     * Check if username already exists in the database.
     *
     * @param string $username Username
     * @return bool True if username exists
     */
    protected function usernameExists($username)
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) FROM gibbonPerson WHERE username = :username
            ");
            $stmt->execute([':username' => $username]);
            $count = (int) $stmt->fetchColumn();
            return $count > 0;
        } catch (\PDOException $e) {
            return false;
        }
    }

    /**
     * Save admin account to the database.
     *
     * @param array $data Admin account data
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
                // Get Administrator role ID
                $adminRoleId = $this->getAdminRoleId();
                if (!$adminRoleId) {
                    throw new \Exception('Administrator role not found');
                }

                // Create username if not provided
                $username = $data['username'] ?? $this->generateUsername($data['email']);

                // Hash password
                $passwordHash = password_hash($data['password'], PASSWORD_DEFAULT);

                // Insert admin user
                $stmt = $this->pdo->prepare("
                    INSERT INTO gibbonPerson
                    (title, surname, firstName, preferredName, username, email, password,
                     status, canLogin, gibbonRoleIDPrimary, dateStart)
                    VALUES (:title, :surname, :firstName, :preferredName, :username, :email, :password,
                            'Full', 'Y', :roleId, CURDATE())
                ");

                $preferredName = $data['preferredName'] ?? $data['firstName'];
                $title = $data['title'] ?? '';

                $stmt->execute([
                    ':title' => $title,
                    ':surname' => $data['surname'],
                    ':firstName' => $data['firstName'],
                    ':preferredName' => $preferredName,
                    ':username' => $username,
                    ':email' => $data['email'],
                    ':password' => $passwordHash,
                    ':roleId' => $adminRoleId,
                ]);

                $personId = $this->pdo->lastInsertId();

                // Assign admin role in gibbonPersonRole table
                $stmt = $this->pdo->prepare("
                    INSERT INTO gibbonPersonRole (gibbonPersonID, gibbonRoleID)
                    VALUES (:personId, :roleId)
                ");
                $stmt->execute([
                    ':personId' => $personId,
                    ':roleId' => $adminRoleId,
                ]);

                // Save progress in wizard (without storing password)
                $progressData = [
                    'firstName' => $data['firstName'],
                    'surname' => $data['surname'],
                    'email' => $data['email'],
                    'username' => $username,
                    'title' => $title,
                    'preferredName' => $preferredName,
                ];
                $this->installationDetector->saveWizardProgress('admin_account', $progressData);

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
     * Get the Administrator role ID.
     *
     * @return int|null Role ID or null if not found
     */
    protected function getAdminRoleId()
    {
        try {
            $stmt = $this->pdo->query("
                SELECT gibbonRoleID
                FROM gibbonRole
                WHERE category = 'Staff' AND name = 'Administrator'
                LIMIT 1
            ");
            $result = $stmt->fetchColumn();
            return $result ? (int) $result : null;
        } catch (\PDOException $e) {
            return null;
        }
    }

    /**
     * Generate username from email address.
     * Takes the part before @ and ensures uniqueness.
     *
     * @param string $email Email address
     * @return string Generated username
     */
    protected function generateUsername($email)
    {
        // Get part before @
        $parts = explode('@', $email);
        $baseUsername = $parts[0];

        // Clean username (remove special chars except dots, hyphens, underscores)
        $username = preg_replace('/[^a-zA-Z0-9._-]/', '', $baseUsername);

        // Ensure minimum length
        if (strlen($username) < 3) {
            $username = 'admin';
        }

        // Limit to 50 characters
        if (strlen($username) > 50) {
            $username = substr($username, 0, 50);
        }

        // Check if username exists and add number if needed
        $finalUsername = $username;
        $counter = 1;
        while ($this->usernameExists($finalUsername)) {
            $finalUsername = $username . $counter;
            $counter++;
            // Prevent infinite loop
            if ($counter > 999) {
                $finalUsername = $username . uniqid();
                break;
            }
        }

        return $finalUsername;
    }

    /**
     * Check if admin account has been created.
     *
     * @return bool True if admin account exists
     */
    public function isCompleted()
    {
        try {
            $stmt = $this->pdo->query("
                SELECT COUNT(*)
                FROM gibbonPerson
                WHERE status = 'Full'
                AND canLogin = 'Y'
                AND gibbonRoleIDPrimary IN (
                    SELECT gibbonRoleID
                    FROM gibbonRole
                    WHERE category = 'Staff' AND name = 'Administrator'
                )
            ");
            $count = (int) $stmt->fetchColumn();
            return $count > 0;
        } catch (\PDOException $e) {
            return false;
        }
    }

    /**
     * Get admin account info from wizard progress (for resume capability).
     *
     * @return array|null Admin account data from wizard progress
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
     * Prepare admin account data for display/editing.
     * Returns wizard progress data if available.
     *
     * @return array Admin account data
     */
    public function prepareData()
    {
        // Get wizard progress if available (for resume)
        $wizardData = $this->getWizardProgress();

        // Ensure all fields have default values
        return array_merge([
            'title' => '',
            'firstName' => '',
            'surname' => '',
            'preferredName' => '',
            'email' => '',
            'username' => '',
        ], $wizardData ?: []);
    }

    /**
     * Get the first admin account information.
     *
     * @return array|null Admin account data or null if not found
     */
    public function getAdminAccount()
    {
        try {
            $stmt = $this->pdo->query("
                SELECT title, surname, firstName, preferredName, username, email
                FROM gibbonPerson
                WHERE status = 'Full'
                AND canLogin = 'Y'
                AND gibbonRoleIDPrimary IN (
                    SELECT gibbonRoleID
                    FROM gibbonRole
                    WHERE category = 'Staff' AND name = 'Administrator'
                )
                ORDER BY gibbonPersonID ASC
                LIMIT 1
            ");

            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            return $result ?: null;
        } catch (\PDOException $e) {
            return null;
        }
    }

    /**
     * Clear admin accounts (for testing/reset).
     * WARNING: This removes all admin users!
     *
     * @return bool True if successful
     */
    public function clear()
    {
        try {
            $this->pdo->beginTransaction();

            try {
                // Get admin role ID
                $adminRoleId = $this->getAdminRoleId();
                if (!$adminRoleId) {
                    $this->pdo->rollBack();
                    return false;
                }

                // Delete from gibbonPersonRole
                $stmt = $this->pdo->prepare("
                    DELETE FROM gibbonPersonRole
                    WHERE gibbonRoleID = :roleId
                ");
                $stmt->execute([':roleId' => $adminRoleId]);

                // Delete from gibbonPerson
                $stmt = $this->pdo->prepare("
                    DELETE FROM gibbonPerson
                    WHERE gibbonRoleIDPrimary = :roleId
                ");
                $stmt->execute([':roleId' => $adminRoleId]);

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
