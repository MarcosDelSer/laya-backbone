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

namespace Gibbon\Module\System\Tests;

use PHPUnit\Framework\TestCase;
use Gibbon\Module\System\Domain\AdminAccountCreationStep;
use Gibbon\Module\System\Domain\InstallationDetector;
use Gibbon\Domain\System\SettingGateway;
use PDO;
use PDOStatement;

/**
 * Unit tests for AdminAccountCreationStep.
 *
 * These tests verify that the AdminAccountCreationStep correctly validates,
 * saves, and retrieves admin account information for the setup wizard.
 *
 * @covers \Gibbon\Module\System\Domain\AdminAccountCreationStep
 */
class AdminAccountCreationStepTest extends TestCase
{
    private $settingGateway;
    private $pdo;
    private $installationDetector;
    private $adminStep;

    protected function setUp(): void
    {
        // Create mock objects
        $this->settingGateway = $this->createMock(SettingGateway::class);
        $this->pdo = $this->createMock(PDO::class);
        $this->installationDetector = $this->createMock(InstallationDetector::class);

        $this->adminStep = new AdminAccountCreationStep(
            $this->settingGateway,
            $this->pdo,
            $this->installationDetector
        );
    }

    // =========================================================================
    // VALIDATION TESTS - BASIC FIELDS
    // =========================================================================

    /**
     * @test
     */
    public function validateReturnsNoErrorsForValidData(): void
    {
        $data = [
            'firstName' => 'John',
            'surname' => 'Doe',
            'email' => 'john.doe@example.com',
            'username' => 'johndoe',
            'password' => 'SecurePass123!',
            'passwordConfirm' => 'SecurePass123!',
        ];

        // Mock email/username checks
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('fetchColumn')->willReturn(0); // No existing users
        $this->pdo->method('prepare')->willReturn($stmt);

        $errors = $this->adminStep->validate($data);

        $this->assertEmpty($errors);
    }

    /**
     * @test
     */
    public function validateReturnsErrorWhenFirstNameIsEmpty(): void
    {
        $data = [
            'firstName' => '',
            'surname' => 'Doe',
            'email' => 'john@example.com',
            'password' => 'SecurePass123!',
            'passwordConfirm' => 'SecurePass123!',
        ];

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('fetchColumn')->willReturn(0);
        $this->pdo->method('prepare')->willReturn($stmt);

        $errors = $this->adminStep->validate($data);

        $this->assertArrayHasKey('firstName', $errors);
        $this->assertEquals('First name is required', $errors['firstName']);
    }

    /**
     * @test
     */
    public function validateReturnsErrorWhenFirstNameIsTooShort(): void
    {
        $data = [
            'firstName' => 'J',
            'surname' => 'Doe',
            'email' => 'john@example.com',
            'password' => 'SecurePass123!',
            'passwordConfirm' => 'SecurePass123!',
        ];

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('fetchColumn')->willReturn(0);
        $this->pdo->method('prepare')->willReturn($stmt);

        $errors = $this->adminStep->validate($data);

        $this->assertArrayHasKey('firstName', $errors);
        $this->assertEquals('First name must be at least 2 characters', $errors['firstName']);
    }

    /**
     * @test
     */
    public function validateReturnsErrorWhenFirstNameIsTooLong(): void
    {
        $data = [
            'firstName' => str_repeat('A', 101),
            'surname' => 'Doe',
            'email' => 'john@example.com',
            'password' => 'SecurePass123!',
            'passwordConfirm' => 'SecurePass123!',
        ];

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('fetchColumn')->willReturn(0);
        $this->pdo->method('prepare')->willReturn($stmt);

        $errors = $this->adminStep->validate($data);

        $this->assertArrayHasKey('firstName', $errors);
        $this->assertEquals('First name must not exceed 100 characters', $errors['firstName']);
    }

    /**
     * @test
     */
    public function validateReturnsErrorWhenSurnameIsEmpty(): void
    {
        $data = [
            'firstName' => 'John',
            'surname' => '',
            'email' => 'john@example.com',
            'password' => 'SecurePass123!',
            'passwordConfirm' => 'SecurePass123!',
        ];

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('fetchColumn')->willReturn(0);
        $this->pdo->method('prepare')->willReturn($stmt);

        $errors = $this->adminStep->validate($data);

        $this->assertArrayHasKey('surname', $errors);
        $this->assertEquals('Surname is required', $errors['surname']);
    }

    /**
     * @test
     */
    public function validateReturnsErrorWhenSurnameIsTooShort(): void
    {
        $data = [
            'firstName' => 'John',
            'surname' => 'D',
            'email' => 'john@example.com',
            'password' => 'SecurePass123!',
            'passwordConfirm' => 'SecurePass123!',
        ];

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('fetchColumn')->willReturn(0);
        $this->pdo->method('prepare')->willReturn($stmt);

        $errors = $this->adminStep->validate($data);

        $this->assertArrayHasKey('surname', $errors);
        $this->assertEquals('Surname must be at least 2 characters', $errors['surname']);
    }

    /**
     * @test
     */
    public function validateReturnsErrorWhenSurnameIsTooLong(): void
    {
        $data = [
            'firstName' => 'John',
            'surname' => str_repeat('A', 101),
            'email' => 'john@example.com',
            'password' => 'SecurePass123!',
            'passwordConfirm' => 'SecurePass123!',
        ];

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('fetchColumn')->willReturn(0);
        $this->pdo->method('prepare')->willReturn($stmt);

        $errors = $this->adminStep->validate($data);

        $this->assertArrayHasKey('surname', $errors);
        $this->assertEquals('Surname must not exceed 100 characters', $errors['surname']);
    }

    // =========================================================================
    // EMAIL VALIDATION TESTS
    // =========================================================================

    /**
     * @test
     */
    public function validateReturnsErrorWhenEmailIsEmpty(): void
    {
        $data = [
            'firstName' => 'John',
            'surname' => 'Doe',
            'email' => '',
            'password' => 'SecurePass123!',
            'passwordConfirm' => 'SecurePass123!',
        ];

        $errors = $this->adminStep->validate($data);

        $this->assertArrayHasKey('email', $errors);
        $this->assertEquals('Email address is required', $errors['email']);
    }

    /**
     * @test
     */
    public function validateReturnsErrorWhenEmailIsInvalid(): void
    {
        $data = [
            'firstName' => 'John',
            'surname' => 'Doe',
            'email' => 'invalid-email',
            'password' => 'SecurePass123!',
            'passwordConfirm' => 'SecurePass123!',
        ];

        $errors = $this->adminStep->validate($data);

        $this->assertArrayHasKey('email', $errors);
        $this->assertEquals('Invalid email address format', $errors['email']);
    }

    /**
     * @test
     */
    public function validateReturnsErrorWhenEmailIsTooLong(): void
    {
        $longEmail = str_repeat('a', 250) . '@test.com';
        $data = [
            'firstName' => 'John',
            'surname' => 'Doe',
            'email' => $longEmail,
            'password' => 'SecurePass123!',
            'passwordConfirm' => 'SecurePass123!',
        ];

        $errors = $this->adminStep->validate($data);

        $this->assertArrayHasKey('email', $errors);
        $this->assertEquals('Email must not exceed 255 characters', $errors['email']);
    }

    /**
     * @test
     */
    public function validateReturnsErrorWhenEmailAlreadyExists(): void
    {
        $data = [
            'firstName' => 'John',
            'surname' => 'Doe',
            'email' => 'existing@example.com',
            'password' => 'SecurePass123!',
            'passwordConfirm' => 'SecurePass123!',
        ];

        // Mock email exists check
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('fetchColumn')->willReturn(1); // Email exists
        $this->pdo->method('prepare')->willReturn($stmt);

        $errors = $this->adminStep->validate($data);

        $this->assertArrayHasKey('email', $errors);
        $this->assertEquals('Email address already exists', $errors['email']);
    }

    // =========================================================================
    // USERNAME VALIDATION TESTS
    // =========================================================================

    /**
     * @test
     */
    public function validateReturnsErrorWhenUsernameIsTooShort(): void
    {
        $data = [
            'firstName' => 'John',
            'surname' => 'Doe',
            'email' => 'john@example.com',
            'username' => 'ab',
            'password' => 'SecurePass123!',
            'passwordConfirm' => 'SecurePass123!',
        ];

        // First call for email (doesn't exist), second for username
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('fetchColumn')->willReturn(0);
        $this->pdo->method('prepare')->willReturn($stmt);

        $errors = $this->adminStep->validate($data);

        $this->assertArrayHasKey('username', $errors);
        $this->assertEquals('Username must be at least 3 characters', $errors['username']);
    }

    /**
     * @test
     */
    public function validateReturnsErrorWhenUsernameIsTooLong(): void
    {
        $data = [
            'firstName' => 'John',
            'surname' => 'Doe',
            'email' => 'john@example.com',
            'username' => str_repeat('a', 51),
            'password' => 'SecurePass123!',
            'passwordConfirm' => 'SecurePass123!',
        ];

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('fetchColumn')->willReturn(0);
        $this->pdo->method('prepare')->willReturn($stmt);

        $errors = $this->adminStep->validate($data);

        $this->assertArrayHasKey('username', $errors);
        $this->assertEquals('Username must not exceed 50 characters', $errors['username']);
    }

    /**
     * @test
     */
    public function validateReturnsErrorWhenUsernameHasInvalidCharacters(): void
    {
        $data = [
            'firstName' => 'John',
            'surname' => 'Doe',
            'email' => 'john@example.com',
            'username' => 'john@doe',
            'password' => 'SecurePass123!',
            'passwordConfirm' => 'SecurePass123!',
        ];

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('fetchColumn')->willReturn(0);
        $this->pdo->method('prepare')->willReturn($stmt);

        $errors = $this->adminStep->validate($data);

        $this->assertArrayHasKey('username', $errors);
        $this->assertStringContainsString('letters, numbers, dots, hyphens, and underscores', $errors['username']);
    }

    /**
     * @test
     */
    public function validateAcceptsValidUsernameFormats(): void
    {
        $validUsernames = [
            'john.doe',
            'john_doe',
            'john-doe',
            'johndoe123',
            'JohnDoe',
        ];

        foreach ($validUsernames as $username) {
            $data = [
                'firstName' => 'John',
                'surname' => 'Doe',
                'email' => 'john@example.com',
                'username' => $username,
                'password' => 'SecurePass123!',
                'passwordConfirm' => 'SecurePass123!',
            ];

            $stmt = $this->createMock(PDOStatement::class);
            $stmt->method('fetchColumn')->willReturn(0);
            $this->pdo->method('prepare')->willReturn($stmt);

            $errors = $this->adminStep->validate($data);

            $this->assertArrayNotHasKey('username', $errors, "Username '$username' should be valid");
        }
    }

    /**
     * @test
     */
    public function validateReturnsErrorWhenUsernameAlreadyExists(): void
    {
        $data = [
            'firstName' => 'John',
            'surname' => 'Doe',
            'email' => 'john@example.com',
            'username' => 'existinguser',
            'password' => 'SecurePass123!',
            'passwordConfirm' => 'SecurePass123!',
        ];

        $stmt = $this->createMock(PDOStatement::class);
        // First call: email doesn't exist (0), second call: username exists (1)
        $stmt->method('fetchColumn')->willReturnOnConsecutiveCalls(0, 1);
        $this->pdo->method('prepare')->willReturn($stmt);

        $errors = $this->adminStep->validate($data);

        $this->assertArrayHasKey('username', $errors);
        $this->assertEquals('Username already exists', $errors['username']);
    }

    // =========================================================================
    // PASSWORD VALIDATION TESTS
    // =========================================================================

    /**
     * @test
     */
    public function validateReturnsErrorWhenPasswordIsEmpty(): void
    {
        $data = [
            'firstName' => 'John',
            'surname' => 'Doe',
            'email' => 'john@example.com',
            'password' => '',
            'passwordConfirm' => '',
        ];

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('fetchColumn')->willReturn(0);
        $this->pdo->method('prepare')->willReturn($stmt);

        $errors = $this->adminStep->validate($data);

        $this->assertArrayHasKey('password', $errors);
        $this->assertEquals('Password is required', $errors['password']);
    }

    /**
     * @test
     */
    public function validateReturnsErrorWhenPasswordIsTooShort(): void
    {
        $data = [
            'firstName' => 'John',
            'surname' => 'Doe',
            'email' => 'john@example.com',
            'password' => 'Short1!',
            'passwordConfirm' => 'Short1!',
        ];

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('fetchColumn')->willReturn(0);
        $this->pdo->method('prepare')->willReturn($stmt);

        $errors = $this->adminStep->validate($data);

        $this->assertArrayHasKey('password', $errors);
        $this->assertStringContainsString('at least 8 characters', $errors['password']);
    }

    /**
     * @test
     */
    public function validateReturnsErrorWhenPasswordIsTooLong(): void
    {
        $data = [
            'firstName' => 'John',
            'surname' => 'Doe',
            'email' => 'john@example.com',
            'password' => str_repeat('A', 73) . '1!',
            'passwordConfirm' => str_repeat('A', 73) . '1!',
        ];

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('fetchColumn')->willReturn(0);
        $this->pdo->method('prepare')->willReturn($stmt);

        $errors = $this->adminStep->validate($data);

        $this->assertArrayHasKey('password', $errors);
        $this->assertStringContainsString('not exceed 72 characters', $errors['password']);
    }

    /**
     * @test
     */
    public function validateReturnsErrorWhenPasswordMissingUppercase(): void
    {
        $data = [
            'firstName' => 'John',
            'surname' => 'Doe',
            'email' => 'john@example.com',
            'password' => 'lowercase123!',
            'passwordConfirm' => 'lowercase123!',
        ];

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('fetchColumn')->willReturn(0);
        $this->pdo->method('prepare')->willReturn($stmt);

        $errors = $this->adminStep->validate($data);

        $this->assertArrayHasKey('password', $errors);
        $this->assertStringContainsString('uppercase letter', $errors['password']);
    }

    /**
     * @test
     */
    public function validateReturnsErrorWhenPasswordMissingLowercase(): void
    {
        $data = [
            'firstName' => 'John',
            'surname' => 'Doe',
            'email' => 'john@example.com',
            'password' => 'UPPERCASE123!',
            'passwordConfirm' => 'UPPERCASE123!',
        ];

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('fetchColumn')->willReturn(0);
        $this->pdo->method('prepare')->willReturn($stmt);

        $errors = $this->adminStep->validate($data);

        $this->assertArrayHasKey('password', $errors);
        $this->assertStringContainsString('lowercase letter', $errors['password']);
    }

    /**
     * @test
     */
    public function validateReturnsErrorWhenPasswordMissingNumber(): void
    {
        $data = [
            'firstName' => 'John',
            'surname' => 'Doe',
            'email' => 'john@example.com',
            'password' => 'NoNumbers!',
            'passwordConfirm' => 'NoNumbers!',
        ];

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('fetchColumn')->willReturn(0);
        $this->pdo->method('prepare')->willReturn($stmt);

        $errors = $this->adminStep->validate($data);

        $this->assertArrayHasKey('password', $errors);
        $this->assertStringContainsString('one number', $errors['password']);
    }

    /**
     * @test
     */
    public function validateReturnsErrorWhenPasswordMissingSpecialCharacter(): void
    {
        $data = [
            'firstName' => 'John',
            'surname' => 'Doe',
            'email' => 'john@example.com',
            'password' => 'NoSpecial123',
            'passwordConfirm' => 'NoSpecial123',
        ];

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('fetchColumn')->willReturn(0);
        $this->pdo->method('prepare')->willReturn($stmt);

        $errors = $this->adminStep->validate($data);

        $this->assertArrayHasKey('password', $errors);
        $this->assertStringContainsString('special character', $errors['password']);
    }

    /**
     * @test
     */
    public function validateReturnsErrorWhenPasswordConfirmIsEmpty(): void
    {
        $data = [
            'firstName' => 'John',
            'surname' => 'Doe',
            'email' => 'john@example.com',
            'password' => 'SecurePass123!',
            'passwordConfirm' => '',
        ];

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('fetchColumn')->willReturn(0);
        $this->pdo->method('prepare')->willReturn($stmt);

        $errors = $this->adminStep->validate($data);

        $this->assertArrayHasKey('passwordConfirm', $errors);
        $this->assertEquals('Password confirmation is required', $errors['passwordConfirm']);
    }

    /**
     * @test
     */
    public function validateReturnsErrorWhenPasswordsDoNotMatch(): void
    {
        $data = [
            'firstName' => 'John',
            'surname' => 'Doe',
            'email' => 'john@example.com',
            'password' => 'SecurePass123!',
            'passwordConfirm' => 'DifferentPass123!',
        ];

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('fetchColumn')->willReturn(0);
        $this->pdo->method('prepare')->willReturn($stmt);

        $errors = $this->adminStep->validate($data);

        $this->assertArrayHasKey('passwordConfirm', $errors);
        $this->assertEquals('Passwords do not match', $errors['passwordConfirm']);
    }

    // =========================================================================
    // SAVE OPERATION TESTS
    // =========================================================================

    /**
     * @test
     */
    public function saveReturnsFalseForInvalidData(): void
    {
        $data = [
            'firstName' => '', // Invalid
            'surname' => 'Doe',
            'email' => 'john@example.com',
            'password' => 'SecurePass123!',
            'passwordConfirm' => 'SecurePass123!',
        ];

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('fetchColumn')->willReturn(0);
        $this->pdo->method('prepare')->willReturn($stmt);

        $result = $this->adminStep->save($data);

        $this->assertFalse($result);
    }

    /**
     * @test
     */
    public function saveCreatesAdminAccountSuccessfully(): void
    {
        $data = [
            'firstName' => 'John',
            'surname' => 'Doe',
            'email' => 'john@example.com',
            'username' => 'johndoe',
            'password' => 'SecurePass123!',
            'passwordConfirm' => 'SecurePass123!',
        ];

        // Mock email/username checks (don't exist)
        $checkStmt = $this->createMock(PDOStatement::class);
        $checkStmt->method('fetchColumn')->willReturn(0);

        // Mock get admin role ID
        $roleStmt = $this->createMock(PDOStatement::class);
        $roleStmt->method('fetchColumn')->willReturn(1); // Admin role ID = 1

        // Mock insert statements
        $insertStmt = $this->createMock(PDOStatement::class);
        $insertStmt->method('execute')->willReturn(true);

        $this->pdo->method('prepare')->willReturn($checkStmt, $insertStmt);
        $this->pdo->method('query')->willReturn($roleStmt);
        $this->pdo->method('lastInsertId')->willReturn('1');
        $this->pdo->method('beginTransaction')->willReturn(true);
        $this->pdo->method('commit')->willReturn(true);

        $this->installationDetector
            ->method('saveWizardProgress')
            ->willReturn(true);

        $result = $this->adminStep->save($data);

        $this->assertTrue($result);
    }

    /**
     * @test
     */
    public function saveGeneratesUsernameWhenNotProvided(): void
    {
        $data = [
            'firstName' => 'John',
            'surname' => 'Doe',
            'email' => 'john.doe@example.com',
            'password' => 'SecurePass123!',
            'passwordConfirm' => 'SecurePass123!',
        ];

        $checkStmt = $this->createMock(PDOStatement::class);
        $checkStmt->method('fetchColumn')->willReturn(0);

        $roleStmt = $this->createMock(PDOStatement::class);
        $roleStmt->method('fetchColumn')->willReturn(1);

        $insertStmt = $this->createMock(PDOStatement::class);
        $executeParams = null;
        $insertStmt->method('execute')
            ->willReturnCallback(function($params) use (&$executeParams) {
                // Capture first insert (gibbonPerson)
                if (!$executeParams && isset($params[':username'])) {
                    $executeParams = $params;
                }
                return true;
            });

        $this->pdo->method('prepare')->willReturn($checkStmt, $insertStmt);
        $this->pdo->method('query')->willReturn($roleStmt);
        $this->pdo->method('lastInsertId')->willReturn('1');
        $this->pdo->method('beginTransaction')->willReturn(true);
        $this->pdo->method('commit')->willReturn(true);

        $this->installationDetector
            ->method('saveWizardProgress')
            ->willReturn(true);

        $result = $this->adminStep->save($data);

        $this->assertTrue($result);
        // Username should be generated from email (john.doe)
        $this->assertNotNull($executeParams);
    }

    /**
     * @test
     */
    public function saveReturnsFalseWhenAdminRoleNotFound(): void
    {
        $data = [
            'firstName' => 'John',
            'surname' => 'Doe',
            'email' => 'john@example.com',
            'password' => 'SecurePass123!',
            'passwordConfirm' => 'SecurePass123!',
        ];

        $checkStmt = $this->createMock(PDOStatement::class);
        $checkStmt->method('fetchColumn')->willReturn(0);

        // Mock get admin role ID - returns null (not found)
        $roleStmt = $this->createMock(PDOStatement::class);
        $roleStmt->method('fetchColumn')->willReturn(false);

        $this->pdo->method('prepare')->willReturn($checkStmt);
        $this->pdo->method('query')->willReturn($roleStmt);
        $this->pdo->method('beginTransaction')->willReturn(true);
        $this->pdo->method('rollBack')->willReturn(true);

        $result = $this->adminStep->save($data);

        $this->assertFalse($result);
    }

    /**
     * @test
     */
    public function saveRollsBackOnException(): void
    {
        $data = [
            'firstName' => 'John',
            'surname' => 'Doe',
            'email' => 'john@example.com',
            'password' => 'SecurePass123!',
            'passwordConfirm' => 'SecurePass123!',
        ];

        $checkStmt = $this->createMock(PDOStatement::class);
        $checkStmt->method('fetchColumn')->willReturn(0);

        $roleStmt = $this->createMock(PDOStatement::class);
        $roleStmt->method('fetchColumn')->willReturn(1);

        $insertStmt = $this->createMock(PDOStatement::class);
        $insertStmt->method('execute')
            ->will($this->throwException(new \PDOException('Database error')));

        $this->pdo->method('prepare')->willReturn($checkStmt, $insertStmt);
        $this->pdo->method('query')->willReturn($roleStmt);
        $this->pdo->method('beginTransaction')->willReturn(true);
        $this->pdo->method('rollBack')->willReturn(true);

        $result = $this->adminStep->save($data);

        $this->assertFalse($result);
    }

    // =========================================================================
    // IS COMPLETED TESTS
    // =========================================================================

    /**
     * @test
     */
    public function isCompletedReturnsTrueWhenAdminExists(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('fetchColumn')->willReturn(1); // One admin exists

        $this->pdo->method('query')->willReturn($stmt);

        $result = $this->adminStep->isCompleted();

        $this->assertTrue($result);
    }

    /**
     * @test
     */
    public function isCompletedReturnsFalseWhenNoAdminExists(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('fetchColumn')->willReturn(0); // No admins

        $this->pdo->method('query')->willReturn($stmt);

        $result = $this->adminStep->isCompleted();

        $this->assertFalse($result);
    }

    /**
     * @test
     */
    public function isCompletedReturnsFalseOnDatabaseException(): void
    {
        $this->pdo
            ->method('query')
            ->will($this->throwException(new \PDOException('Database error')));

        $result = $this->adminStep->isCompleted();

        $this->assertFalse($result);
    }

    // =========================================================================
    // WIZARD PROGRESS TESTS
    // =========================================================================

    /**
     * @test
     */
    public function getWizardProgressReturnsDataFromInstallationDetector(): void
    {
        $wizardData = [
            'gibbonSetupWizardID' => 1,
            'stepCompleted' => 'admin_account',
            'stepData' => [
                'firstName' => 'John',
                'surname' => 'Doe',
                'email' => 'john@example.com',
                'username' => 'johndoe',
            ],
        ];

        $this->installationDetector
            ->method('getWizardProgress')
            ->willReturn($wizardData);

        $result = $this->adminStep->getWizardProgress();

        $this->assertIsArray($result);
        $this->assertEquals('John', $result['firstName']);
        $this->assertEquals('john@example.com', $result['email']);
    }

    /**
     * @test
     */
    public function getWizardProgressReturnsNullWhenNoProgress(): void
    {
        $this->installationDetector
            ->method('getWizardProgress')
            ->willReturn(null);

        $result = $this->adminStep->getWizardProgress();

        $this->assertNull($result);
    }

    // =========================================================================
    // PREPARE DATA TESTS
    // =========================================================================

    /**
     * @test
     */
    public function prepareDataReturnsDefaultsWhenNoDataExists(): void
    {
        $this->installationDetector
            ->method('getWizardProgress')
            ->willReturn(null);

        $result = $this->adminStep->prepareData();

        $this->assertIsArray($result);
        $this->assertEquals('', $result['firstName']);
        $this->assertEquals('', $result['surname']);
        $this->assertEquals('', $result['email']);
        $this->assertEquals('', $result['username']);
    }

    /**
     * @test
     */
    public function prepareDataReturnsWizardProgressData(): void
    {
        $wizardData = [
            'gibbonSetupWizardID' => 1,
            'stepCompleted' => 'admin_account',
            'stepData' => [
                'firstName' => 'Jane',
                'surname' => 'Smith',
                'email' => 'jane@example.com',
            ],
        ];

        $this->installationDetector
            ->method('getWizardProgress')
            ->willReturn($wizardData);

        $result = $this->adminStep->prepareData();

        $this->assertEquals('Jane', $result['firstName']);
        $this->assertEquals('Smith', $result['surname']);
        $this->assertEquals('jane@example.com', $result['email']);
    }

    // =========================================================================
    // GET ADMIN ACCOUNT TESTS
    // =========================================================================

    /**
     * @test
     */
    public function getAdminAccountReturnsDataWhenExists(): void
    {
        $adminData = [
            'title' => 'Dr.',
            'firstName' => 'John',
            'surname' => 'Doe',
            'preferredName' => 'Johnny',
            'username' => 'johndoe',
            'email' => 'john@example.com',
        ];

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('fetch')->willReturn($adminData);

        $this->pdo->method('query')->willReturn($stmt);

        $result = $this->adminStep->getAdminAccount();

        $this->assertIsArray($result);
        $this->assertEquals('John', $result['firstName']);
        $this->assertEquals('john@example.com', $result['email']);
    }

    /**
     * @test
     */
    public function getAdminAccountReturnsNullWhenNoAdmin(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('fetch')->willReturn(false);

        $this->pdo->method('query')->willReturn($stmt);

        $result = $this->adminStep->getAdminAccount();

        $this->assertNull($result);
    }

    // =========================================================================
    // CLEAR TESTS
    // =========================================================================

    /**
     * @test
     */
    public function clearDeletesAdminAccounts(): void
    {
        $roleStmt = $this->createMock(PDOStatement::class);
        $roleStmt->method('fetchColumn')->willReturn(1); // Admin role ID

        $deleteStmt = $this->createMock(PDOStatement::class);
        $deleteStmt->method('execute')->willReturn(true);

        $this->pdo->method('query')->willReturn($roleStmt);
        $this->pdo->method('prepare')->willReturn($deleteStmt);
        $this->pdo->method('beginTransaction')->willReturn(true);
        $this->pdo->method('commit')->willReturn(true);

        $result = $this->adminStep->clear();

        $this->assertTrue($result);
    }

    /**
     * @test
     */
    public function clearReturnsFalseWhenAdminRoleNotFound(): void
    {
        $roleStmt = $this->createMock(PDOStatement::class);
        $roleStmt->method('fetchColumn')->willReturn(false); // No admin role

        $this->pdo->method('query')->willReturn($roleStmt);
        $this->pdo->method('beginTransaction')->willReturn(true);
        $this->pdo->method('rollBack')->willReturn(true);

        $result = $this->adminStep->clear();

        $this->assertFalse($result);
    }

    /**
     * @test
     */
    public function clearRollsBackOnException(): void
    {
        $roleStmt = $this->createMock(PDOStatement::class);
        $roleStmt->method('fetchColumn')->willReturn(1);

        $deleteStmt = $this->createMock(PDOStatement::class);
        $deleteStmt->method('execute')
            ->will($this->throwException(new \PDOException('Database error')));

        $this->pdo->method('query')->willReturn($roleStmt);
        $this->pdo->method('prepare')->willReturn($deleteStmt);
        $this->pdo->method('beginTransaction')->willReturn(true);
        $this->pdo->method('rollBack')->willReturn(true);

        $result = $this->adminStep->clear();

        $this->assertFalse($result);
    }
}
