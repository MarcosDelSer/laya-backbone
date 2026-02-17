<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuiber and the Gibbon community (https://gibbonedu.org/about/)
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

use Gibbon\Forms\Form;
use Gibbon\Services\Format;
use Gibbon\Domain\System\SettingGateway;
use Gibbon\Module\MFA\Domain\MFAGateway;

if (isActionAccessible($guid, $connection2, '/modules/MFA/mfa_setup.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Proceed!
    $page->breadcrumbs->add(__('MFA Setup'));

    // Get current user
    $gibbonPersonID = $session->get('gibbonPersonID');
    $username = $session->get('username');
    $email = $session->get('email');

    // Get gateways and settings
    $mfaGateway = $container->get(MFAGateway::class);
    $settingGateway = $container->get(SettingGateway::class);

    $totpIssuer = $settingGateway->getSettingByScope('MFA', 'totpIssuer') ?? 'LAYA';
    $backupCodesCount = (int) ($settingGateway->getSettingByScope('MFA', 'backupCodesCount') ?? 10);

    // Get current MFA settings
    $mfaSettings = $mfaGateway->getMFASettingsByPerson($gibbonPersonID);

    // Check if MFA is already enabled
    if ($mfaSettings && $mfaSettings['isEnabled'] === 'Y' && $mfaSettings['isVerified'] === 'Y') {
        $page->addMessage(__('MFA is already enabled for your account. You can manage your settings from the MFA Settings page.'));
        echo '<div class="linkTop">';
        echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/MFA/mfa_settings.php">' . __('Go to MFA Settings') . '</a>';
        echo '</div>';
        return;
    }

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $URL = $session->get('absoluteURL') . '/index.php?q=/modules/MFA/mfa_setup.php';

        // CSRF check
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $session->get('csrf_token')) {
            $URL .= '&return=error0';
            header("Location: {$URL}");
            exit;
        }

        $action = $_POST['action'] ?? '';

        if ($action === 'initiate') {
            // Generate new TOTP secret
            $totpSecret = generateTOTPSecret();

            // Store the secret temporarily (not enabled yet)
            if ($mfaSettings) {
                // Update existing record
                $mfaGateway->updateMFASettings($gibbonPersonID, [
                    'totpSecret' => $totpSecret,
                    'isEnabled' => 'N',
                    'isVerified' => 'N',
                ]);
            } else {
                // Insert new record
                $mfaGateway->insertMFASettings([
                    'gibbonPersonID' => $gibbonPersonID,
                    'totpSecret' => $totpSecret,
                    'mfaMethod' => 'totp',
                    'isEnabled' => 'N',
                    'isVerified' => 'N',
                ]);
            }

            // Log the action
            $mfaGateway->insertAuditLog(
                $gibbonPersonID,
                'setup_initiated',
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null
            );

            $URL .= '&step=verify';
            header("Location: {$URL}");
            exit;

        } elseif ($action === 'verify') {
            // Verify the TOTP code
            $code = $_POST['totp_code'] ?? '';

            // Get the stored secret
            $mfaSettings = $mfaGateway->getMFASettingsByPerson($gibbonPersonID);
            if (!$mfaSettings || empty($mfaSettings['totpSecret'])) {
                $URL .= '&return=error1';
                header("Location: {$URL}");
                exit;
            }

            // Verify the code
            if (!verifyTOTPCode($mfaSettings['totpSecret'], $code)) {
                $URL .= '&step=verify&return=error2';
                header("Location: {$URL}");
                exit;
            }

            // Generate backup codes
            $backupCodes = [];
            $backupCodeHashes = [];
            for ($i = 0; $i < $backupCodesCount; $i++) {
                $code = generateBackupCode();
                $backupCodes[] = $code;
                $backupCodeHashes[] = password_hash($code, PASSWORD_DEFAULT);
            }

            // Delete old backup codes and insert new ones
            $mfaGateway->deleteBackupCodes($gibbonPersonID);
            $mfaGateway->insertBackupCodes($gibbonPersonID, $backupCodeHashes);

            // Enable MFA
            $mfaGateway->enableMFA($gibbonPersonID);

            // Log the action
            $mfaGateway->insertAuditLog(
                $gibbonPersonID,
                'setup_completed',
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null
            );

            // Store backup codes in session for display
            $session->set('mfa_backup_codes', $backupCodes);

            $URL .= '&step=complete&return=success0';
            header("Location: {$URL}");
            exit;

        } elseif ($action === 'cancel') {
            // Cancel setup - clear the pending secret
            if ($mfaSettings && $mfaSettings['isEnabled'] === 'N') {
                $mfaGateway->updateMFASettings($gibbonPersonID, [
                    'totpSecret' => null,
                ]);
            }

            // Log the action
            $mfaGateway->insertAuditLog(
                $gibbonPersonID,
                'setup_cancelled',
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null
            );

            $URL = $session->get('absoluteURL') . '/index.php?q=/modules/MFA/mfa_settings.php';
            header("Location: {$URL}");
            exit;
        }
    }

    // Display return messages
    if (isset($_GET['return'])) {
        switch ($_GET['return']) {
            case 'success0':
                $page->addMessage(__('MFA has been successfully enabled for your account.'));
                break;
            case 'error0':
                $page->addError(__('Your request failed because you do not have access to this action.'));
                break;
            case 'error1':
                $page->addError(__('Your request failed. Please start the setup process again.'));
                break;
            case 'error2':
                $page->addError(__('The verification code was incorrect. Please try again.'));
                break;
        }
    }

    $step = $_GET['step'] ?? 'start';
    $mfaSettings = $mfaGateway->getMFASettingsByPerson($gibbonPersonID);

    if ($step === 'complete' && isset($_SESSION['mfa_backup_codes'])) {
        // Show backup codes
        $backupCodes = $session->get('mfa_backup_codes');
        $session->set('mfa_backup_codes', null);

        echo '<div class="success">';
        echo '<h3>' . __('MFA Setup Complete!') . '</h3>';
        echo '<p>' . __('Your account is now protected with multi-factor authentication.') . '</p>';
        echo '</div>';

        echo '<div class="warning">';
        echo '<h3>' . __('Important: Save Your Backup Codes') . '</h3>';
        echo '<p>' . __('Save these backup codes in a safe place. You can use them to access your account if you lose access to your authenticator app.') . '</p>';
        echo '<p><strong>' . __('Each code can only be used once.') . '</strong></p>';
        echo '</div>';

        echo '<div class="p-4 bg-gray-100 rounded-lg my-4">';
        echo '<div class="grid grid-cols-2 gap-2 font-mono text-lg">';
        foreach ($backupCodes as $code) {
            echo '<div class="p-2 bg-white rounded border text-center">' . htmlspecialchars($code) . '</div>';
        }
        echo '</div>';
        echo '<div class="mt-4 text-center">';
        echo '<button onclick="window.print()" class="btn btn-default">' . __('Print Codes') . '</button>';
        echo '</div>';
        echo '</div>';

        echo '<div class="linkTop">';
        echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/MFA/mfa_settings.php">' . __('Go to MFA Settings') . '</a>';
        echo '</div>';

    } elseif ($step === 'verify' && $mfaSettings && !empty($mfaSettings['totpSecret'])) {
        // Show QR code and verification form
        $totpSecret = $mfaSettings['totpSecret'];
        $accountName = $email ?: $username;
        $otpauthUrl = 'otpauth://totp/' . urlencode($totpIssuer) . ':' . urlencode($accountName) . '?secret=' . $totpSecret . '&issuer=' . urlencode($totpIssuer);

        echo '<h2>' . __('Step 2: Scan QR Code') . '</h2>';

        echo '<div class="message">';
        echo '<p>' . __('Scan this QR code with your authenticator app (Google Authenticator, Authy, etc.):') . '</p>';
        echo '</div>';

        // QR Code display
        echo '<div class="text-center my-4">';
        // Using a QR code API for display (fallback to manual entry)
        $qrCodeUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . urlencode($otpauthUrl);
        echo '<img src="' . htmlspecialchars($qrCodeUrl) . '" alt="' . __('QR Code') . '" class="mx-auto border rounded p-2 bg-white" style="width: 200px; height: 200px;">';
        echo '</div>';

        // Manual entry option
        echo '<details class="my-4">';
        echo '<summary class="cursor-pointer text-blue-600">' . __("Can't scan the QR code? Enter the code manually") . '</summary>';
        echo '<div class="p-4 bg-gray-100 rounded mt-2">';
        echo '<p><strong>' . __('Account') . ':</strong> ' . htmlspecialchars($accountName) . '</p>';
        echo '<p><strong>' . __('Secret Key') . ':</strong> <code class="font-mono bg-white p-1 rounded">' . htmlspecialchars($totpSecret) . '</code></p>';
        echo '<p><strong>' . __('Type') . ':</strong> TOTP (Time-based)</p>';
        echo '<p><strong>' . __('Algorithm') . ':</strong> SHA1</p>';
        echo '<p><strong>' . __('Digits') . ':</strong> 6</p>';
        echo '<p><strong>' . __('Period') . ':</strong> 30 seconds</p>';
        echo '</div>';
        echo '</details>';

        // Verification form
        $form = Form::create('verifyMFA', $session->get('absoluteURL') . '/index.php?q=/modules/MFA/mfa_setup.php');
        $form->setTitle(__('Verify Setup'));
        $form->setDescription(__('Enter the 6-digit code from your authenticator app to verify setup.'));
        $form->addHiddenValue('address', $session->get('address'));
        $form->addHiddenValue('csrf_token', $session->get('csrf_token'));
        $form->addHiddenValue('action', 'verify');

        $row = $form->addRow();
            $row->addLabel('totp_code', __('Verification Code'));
            $row->addTextField('totp_code')
                ->required()
                ->maxLength(6)
                ->setClass('w-48 text-center text-2xl tracking-widest')
                ->placeholder('000000')
                ->setAttribute('autocomplete', 'off')
                ->setAttribute('inputmode', 'numeric')
                ->setAttribute('pattern', '[0-9]{6}');

        $row = $form->addRow();
            $row->addFooter();
            $row->addSubmit(__('Verify and Enable MFA'));

        echo $form->getOutput();

        // Cancel form
        $cancelForm = Form::create('cancelMFA', $session->get('absoluteURL') . '/index.php?q=/modules/MFA/mfa_setup.php');
        $cancelForm->addHiddenValue('address', $session->get('address'));
        $cancelForm->addHiddenValue('csrf_token', $session->get('csrf_token'));
        $cancelForm->addHiddenValue('action', 'cancel');

        $row = $cancelForm->addRow();
            $row->addContent('<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/MFA/mfa_settings.php" class="text-gray-600">' . __('Cancel Setup') . '</a>');

        echo $cancelForm->getOutput();

    } else {
        // Step 1: Introduction and initiate
        echo '<h2>' . __('Enable Multi-Factor Authentication') . '</h2>';

        echo '<div class="message">';
        echo '<p>' . __('Multi-Factor Authentication (MFA) adds an extra layer of security to your account. When enabled, you will need to enter a code from your authenticator app in addition to your password.') . '</p>';
        echo '</div>';

        echo '<div class="my-4">';
        echo '<h3>' . __('Before You Begin') . '</h3>';
        echo '<p>' . __('You will need an authenticator app on your phone. Popular options include:') . '</p>';
        echo '<ul class="list-disc ml-6 my-2">';
        echo '<li><strong>Google Authenticator</strong> - ' . __('Available for iOS and Android') . '</li>';
        echo '<li><strong>Microsoft Authenticator</strong> - ' . __('Available for iOS and Android') . '</li>';
        echo '<li><strong>Authy</strong> - ' . __('Available for iOS, Android, and Desktop') . '</li>';
        echo '<li><strong>1Password</strong> - ' . __('Built-in TOTP support') . '</li>';
        echo '</ul>';
        echo '</div>';

        echo '<div class="warning">';
        echo '<p><strong>' . __('Important:') . '</strong> ' . __('Make sure you save your backup codes after setup. If you lose access to your authenticator app, you will need them to regain access to your account.') . '</p>';
        echo '</div>';

        // Initiate form
        $form = Form::create('initiateMFA', $session->get('absoluteURL') . '/index.php?q=/modules/MFA/mfa_setup.php');
        $form->addHiddenValue('address', $session->get('address'));
        $form->addHiddenValue('csrf_token', $session->get('csrf_token'));
        $form->addHiddenValue('action', 'initiate');

        $row = $form->addRow();
            $row->addFooter();
            $row->addSubmit(__('Begin Setup'));

        echo $form->getOutput();
    }
}

/**
 * Generate a random TOTP secret (Base32 encoded).
 *
 * @return string
 */
function generateTOTPSecret()
{
    $base32Chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $secret = '';
    for ($i = 0; $i < 16; $i++) {
        $secret .= $base32Chars[random_int(0, 31)];
    }
    return $secret;
}

/**
 * Verify a TOTP code against a secret.
 *
 * @param string $secret Base32 encoded secret
 * @param string $code 6-digit code to verify
 * @param int $window Number of time periods to check before/after current
 * @return bool
 */
function verifyTOTPCode($secret, $code, $window = 1)
{
    $code = preg_replace('/[^0-9]/', '', $code);
    if (strlen($code) !== 6) {
        return false;
    }

    // Decode Base32 secret
    $secretBytes = base32Decode($secret);
    if ($secretBytes === false) {
        return false;
    }

    // Current time period (30 seconds)
    $currentPeriod = floor(time() / 30);

    // Check codes within the window
    for ($i = -$window; $i <= $window; $i++) {
        $expectedCode = generateTOTP($secretBytes, $currentPeriod + $i);
        if (hash_equals($expectedCode, $code)) {
            return true;
        }
    }

    return false;
}

/**
 * Generate a TOTP code for a given time period.
 *
 * @param string $secret Binary secret
 * @param int $period Time period
 * @return string 6-digit code
 */
function generateTOTP($secret, $period)
{
    // Pack the period as 8-byte big-endian
    $periodBytes = pack('N*', 0) . pack('N*', $period);

    // Calculate HMAC-SHA1
    $hash = hash_hmac('sha1', $periodBytes, $secret, true);

    // Dynamic truncation
    $offset = ord($hash[19]) & 0x0F;
    $code = (
        ((ord($hash[$offset]) & 0x7F) << 24) |
        ((ord($hash[$offset + 1]) & 0xFF) << 16) |
        ((ord($hash[$offset + 2]) & 0xFF) << 8) |
        (ord($hash[$offset + 3]) & 0xFF)
    ) % 1000000;

    return str_pad($code, 6, '0', STR_PAD_LEFT);
}

/**
 * Decode a Base32 encoded string.
 *
 * @param string $encoded
 * @return string|false
 */
function base32Decode($encoded)
{
    $base32Chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $encoded = strtoupper($encoded);
    $encoded = str_replace('=', '', $encoded);

    $buffer = 0;
    $bufferLength = 0;
    $result = '';

    for ($i = 0; $i < strlen($encoded); $i++) {
        $char = $encoded[$i];
        $value = strpos($base32Chars, $char);
        if ($value === false) {
            return false;
        }

        $buffer = ($buffer << 5) | $value;
        $bufferLength += 5;

        if ($bufferLength >= 8) {
            $bufferLength -= 8;
            $result .= chr(($buffer >> $bufferLength) & 0xFF);
        }
    }

    return $result;
}

/**
 * Generate a random backup code.
 *
 * @return string
 */
function generateBackupCode()
{
    // Generate 8 random digits in format XXXX-XXXX
    $part1 = str_pad(random_int(0, 9999), 4, '0', STR_PAD_LEFT);
    $part2 = str_pad(random_int(0, 9999), 4, '0', STR_PAD_LEFT);
    return $part1 . '-' . $part2;
}
