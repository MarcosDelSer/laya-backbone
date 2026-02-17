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

// This page is shown during login when MFA verification is required
// It should be accessible to anyone who has passed primary authentication

if (isActionAccessible($guid, $connection2, '/modules/MFA/mfa_verify.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Proceed!
    // Don't show breadcrumbs on verification page as it's part of login flow
    $page->breadcrumbs->add(__('MFA Verification'));

    // Get current user
    $gibbonPersonID = $session->get('gibbonPersonID');

    // Get gateways and settings
    $mfaGateway = $container->get(MFAGateway::class);
    $settingGateway = $container->get(SettingGateway::class);

    $maxFailedAttempts = (int) ($settingGateway->getSettingByScope('MFA', 'maxFailedAttempts') ?? 5);
    $lockoutDuration = (int) ($settingGateway->getSettingByScope('MFA', 'lockoutDuration') ?? 30);
    $ipWhitelistEnabled = $settingGateway->getSettingByScope('MFA', 'ipWhitelistEnabled') ?? 'Y';

    // Get MFA settings for user
    $mfaSettings = $mfaGateway->getMFASettingsByPerson($gibbonPersonID);

    // Check if user has MFA enabled
    if (!$mfaSettings || $mfaSettings['isEnabled'] !== 'Y' || $mfaSettings['isVerified'] !== 'Y') {
        // No MFA required - redirect to home
        header('Location: ' . $session->get('absoluteURL') . '/index.php');
        exit;
    }

    // Check if IP is whitelisted (bypass MFA)
    if ($ipWhitelistEnabled === 'Y') {
        $clientIP = $_SERVER['REMOTE_ADDR'] ?? '';
        if ($mfaGateway->isIPWhitelisted($gibbonPersonID, $clientIP)) {
            // IP is whitelisted - mark MFA as verified for this session
            $session->set('mfaVerified', true);
            $session->set('mfaVerifiedAt', time());

            // Log the bypass
            $mfaGateway->insertAuditLog(
                $gibbonPersonID,
                'verified',
                $clientIP,
                $_SERVER['HTTP_USER_AGENT'] ?? null,
                ['method' => 'ip_whitelist']
            );

            header('Location: ' . $session->get('absoluteURL') . '/index.php');
            exit;
        }
    }

    // Check if account is locked
    if ($mfaGateway->isAccountLocked($gibbonPersonID)) {
        $lockedUntil = $mfaSettings['lockedUntil'];
        $unlockTime = Format::dateTime($lockedUntil);

        $page->addError(sprintf(
            __('Your account has been temporarily locked due to too many failed MFA attempts. Please try again after %s or contact an administrator.'),
            $unlockTime
        ));
        return;
    }

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $URL = $session->get('absoluteURL') . '/index.php?q=/modules/MFA/mfa_verify.php';

        // CSRF check
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $session->get('csrf_token')) {
            $URL .= '&return=error0';
            header("Location: {$URL}");
            exit;
        }

        $code = $_POST['mfa_code'] ?? '';
        $useBackupCode = isset($_POST['use_backup_code']) && $_POST['use_backup_code'] === 'Y';
        $clientIP = $_SERVER['REMOTE_ADDR'] ?? '';

        $verified = false;

        if ($useBackupCode) {
            // Verify backup code
            $codeHash = password_hash($code, PASSWORD_DEFAULT);

            // Check each unused backup code
            $backupCodes = $mfaGateway->selectBackupCodesByPerson($gibbonPersonID, true);
            foreach ($backupCodes as $backupCode) {
                if (password_verify($code, $backupCode['codeHash'])) {
                    // Mark code as used
                    $mfaGateway->markBackupCodeUsed($backupCode['gibbonMFABackupCodeID'], $clientIP);

                    // Log the usage
                    $mfaGateway->insertAuditLog(
                        $gibbonPersonID,
                        'backup_code_used',
                        $clientIP,
                        $_SERVER['HTTP_USER_AGENT'] ?? null
                    );

                    $verified = true;
                    break;
                }
            }
        } else {
            // Verify TOTP code
            $totpSecret = $mfaSettings['totpSecret'];
            if (!empty($totpSecret) && verifyTOTPCode($totpSecret, $code)) {
                $verified = true;

                // Log successful verification
                $mfaGateway->insertAuditLog(
                    $gibbonPersonID,
                    'verified',
                    $clientIP,
                    $_SERVER['HTTP_USER_AGENT'] ?? null,
                    ['method' => 'totp']
                );
            }
        }

        if ($verified) {
            // Reset failed attempts
            $mfaGateway->resetFailedAttempts($gibbonPersonID);

            // Mark MFA as verified for this session
            $session->set('mfaVerified', true);
            $session->set('mfaVerifiedAt', time());

            // Check for "remember this device" option
            if (isset($_POST['remember_device']) && $_POST['remember_device'] === 'Y') {
                // Generate trusted device token
                $deviceToken = bin2hex(random_bytes(32));
                $deviceTokenHash = password_hash($deviceToken, PASSWORD_DEFAULT);
                $deviceName = detectDeviceName($_SERVER['HTTP_USER_AGENT'] ?? '');

                $mfaGateway->insertTrustedDevice(
                    $gibbonPersonID,
                    $deviceTokenHash,
                    $deviceName,
                    $clientIP,
                    $_SERVER['HTTP_USER_AGENT'] ?? null,
                    30 // 30 days
                );

                // Set cookie for trusted device
                setcookie(
                    'mfa_trusted_device',
                    $gibbonPersonID . ':' . $deviceToken,
                    time() + (30 * 24 * 60 * 60), // 30 days
                    '/',
                    '',
                    true, // Secure
                    true  // HttpOnly
                );
            }

            // Redirect to intended destination or home
            $returnTo = $session->get('mfaReturnTo') ?? ($session->get('absoluteURL') . '/index.php');
            $session->set('mfaReturnTo', null);

            header('Location: ' . $returnTo);
            exit;

        } else {
            // Increment failed attempts
            $result = $mfaGateway->incrementFailedAttempts($gibbonPersonID, $maxFailedAttempts, $lockoutDuration);

            // Log failed attempt
            $mfaGateway->insertAuditLog(
                $gibbonPersonID,
                'verification_failed',
                $clientIP,
                $_SERVER['HTTP_USER_AGENT'] ?? null
            );

            if ($result['locked']) {
                $mfaGateway->insertAuditLog(
                    $gibbonPersonID,
                    'lockout',
                    $clientIP,
                    $_SERVER['HTTP_USER_AGENT'] ?? null,
                    ['attempts' => $result['attempts']]
                );
            }

            $URL .= '&return=error1';
            header("Location: {$URL}");
            exit;
        }
    }

    // Display return messages
    if (isset($_GET['return'])) {
        switch ($_GET['return']) {
            case 'error0':
                $page->addError(__('Your request failed because you do not have access to this action.'));
                break;
            case 'error1':
                $remainingAttempts = $maxFailedAttempts - (int) $mfaSettings['failedAttempts'] - 1;
                if ($remainingAttempts > 0) {
                    $page->addError(sprintf(
                        __('Invalid verification code. You have %d attempt(s) remaining before your account is locked.'),
                        $remainingAttempts
                    ));
                } else {
                    $page->addError(__('Invalid verification code.'));
                }
                break;
        }
    }

    // Display verification form
    $useBackupCode = isset($_GET['backup']) && $_GET['backup'] === '1';

    if ($useBackupCode) {
        // Backup code form
        $unusedCodesCount = $mfaGateway->countUnusedBackupCodes($gibbonPersonID);

        echo '<h2>' . __('Use Backup Code') . '</h2>';

        if ($unusedCodesCount === 0) {
            $page->addError(__('You have no backup codes remaining. Please contact an administrator to reset your MFA.'));
        } else {
            echo '<div class="message">';
            echo '<p>' . sprintf(__('You have %d backup code(s) remaining.'), $unusedCodesCount) . '</p>';
            echo '<p>' . __('Enter one of your backup codes to sign in. Each code can only be used once.') . '</p>';
            echo '</div>';

            $form = Form::create('mfaBackupVerify', $session->get('absoluteURL') . '/index.php?q=/modules/MFA/mfa_verify.php');
            $form->setTitle(__('Enter Backup Code'));
            $form->addHiddenValue('address', $session->get('address'));
            $form->addHiddenValue('csrf_token', $session->get('csrf_token'));
            $form->addHiddenValue('use_backup_code', 'Y');

            $row = $form->addRow();
                $row->addLabel('mfa_code', __('Backup Code'));
                $row->addTextField('mfa_code')
                    ->required()
                    ->maxLength(9)
                    ->setClass('w-48 text-center text-xl tracking-widest font-mono')
                    ->placeholder('0000-0000')
                    ->setAttribute('autocomplete', 'off');

            $row = $form->addRow();
                $row->addFooter();
                $row->addSubmit(__('Verify'));

            echo $form->getOutput();

            echo '<div class="mt-4 text-center">';
            echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/MFA/mfa_verify.php" class="text-blue-600">';
            echo __('Use authenticator app instead');
            echo '</a>';
            echo '</div>';
        }

    } else {
        // TOTP code form
        echo '<h2>' . __('Two-Factor Authentication') . '</h2>';

        echo '<div class="message">';
        echo '<p>' . __('Enter the 6-digit code from your authenticator app.') . '</p>';
        echo '</div>';

        $form = Form::create('mfaVerify', $session->get('absoluteURL') . '/index.php?q=/modules/MFA/mfa_verify.php');
        $form->setTitle(__('Enter Verification Code'));
        $form->addHiddenValue('address', $session->get('address'));
        $form->addHiddenValue('csrf_token', $session->get('csrf_token'));

        $row = $form->addRow();
            $row->addLabel('mfa_code', __('Authentication Code'));
            $row->addTextField('mfa_code')
                ->required()
                ->maxLength(6)
                ->setClass('w-48 text-center text-2xl tracking-widest')
                ->placeholder('000000')
                ->setAttribute('autocomplete', 'one-time-code')
                ->setAttribute('inputmode', 'numeric')
                ->setAttribute('pattern', '[0-9]{6}')
                ->setAttribute('autofocus', 'autofocus');

        $row = $form->addRow();
            $row->addLabel('remember_device', __('Remember this device'))
                ->description(__('Skip MFA on this device for 30 days'));
            $row->addCheckbox('remember_device')
                ->setValue('Y');

        $row = $form->addRow();
            $row->addFooter();
            $row->addSubmit(__('Verify'));

        echo $form->getOutput();

        echo '<div class="mt-4 text-center">';
        echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/MFA/mfa_verify.php&backup=1" class="text-blue-600">';
        echo __('Use a backup code instead');
        echo '</a>';
        echo '</div>';
    }

    // Help section
    echo '<div class="mt-8 p-4 bg-gray-100 rounded">';
    echo '<h4>' . __('Need Help?') . '</h4>';
    echo '<ul class="list-disc ml-6">';
    echo '<li>' . __('Open your authenticator app and find the code for this account.') . '</li>';
    echo '<li>' . __('Codes refresh every 30 seconds. Wait for a new code if the current one is about to expire.') . '</li>';
    echo '<li>' . __('If you\'ve lost access to your authenticator app, use a backup code.') . '</li>';
    echo '<li>' . __('If you\'ve lost all access, contact your administrator for help.') . '</li>';
    echo '</ul>';
    echo '</div>';
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
 * Detect device name from user agent.
 *
 * @param string $userAgent
 * @return string
 */
function detectDeviceName($userAgent)
{
    $device = 'Unknown Device';

    if (preg_match('/iPhone/i', $userAgent)) {
        $device = 'iPhone';
    } elseif (preg_match('/iPad/i', $userAgent)) {
        $device = 'iPad';
    } elseif (preg_match('/Android/i', $userAgent)) {
        if (preg_match('/Mobile/i', $userAgent)) {
            $device = 'Android Phone';
        } else {
            $device = 'Android Tablet';
        }
    } elseif (preg_match('/Windows/i', $userAgent)) {
        $device = 'Windows PC';
    } elseif (preg_match('/Macintosh/i', $userAgent)) {
        $device = 'Mac';
    } elseif (preg_match('/Linux/i', $userAgent)) {
        $device = 'Linux PC';
    }

    // Add browser
    if (preg_match('/Chrome/i', $userAgent)) {
        $device .= ' (Chrome)';
    } elseif (preg_match('/Firefox/i', $userAgent)) {
        $device .= ' (Firefox)';
    } elseif (preg_match('/Safari/i', $userAgent) && !preg_match('/Chrome/i', $userAgent)) {
        $device .= ' (Safari)';
    } elseif (preg_match('/Edge/i', $userAgent)) {
        $device .= ' (Edge)';
    }

    return $device;
}
