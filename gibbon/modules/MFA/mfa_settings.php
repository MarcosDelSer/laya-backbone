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

if (isActionAccessible($guid, $connection2, '/modules/MFA/mfa_settings.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Proceed!
    $page->breadcrumbs->add(__('MFA Settings'));

    // Get current user
    $gibbonPersonID = $session->get('gibbonPersonID');

    // Get gateways and settings
    $mfaGateway = $container->get(MFAGateway::class);
    $settingGateway = $container->get(SettingGateway::class);

    $mfaRequired = $settingGateway->getSettingByScope('MFA', 'mfaRequired') ?? 'Y';
    $ipWhitelistEnabled = $settingGateway->getSettingByScope('MFA', 'ipWhitelistEnabled') ?? 'Y';

    // Get MFA settings for user
    $mfaSettings = $mfaGateway->getMFASettingsByPerson($gibbonPersonID);

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $URL = $session->get('absoluteURL') . '/index.php?q=/modules/MFA/mfa_settings.php';

        // CSRF check
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $session->get('csrf_token')) {
            $URL .= '&return=error0';
            header("Location: {$URL}");
            exit;
        }

        $action = $_POST['action'] ?? '';
        $clientIP = $_SERVER['REMOTE_ADDR'] ?? '';

        if ($action === 'disable') {
            // Require current password or TOTP to disable
            $password = $_POST['password'] ?? '';
            $totpCode = $_POST['totp_code'] ?? '';

            // Verify user identity
            $verified = false;

            // Try TOTP verification first if MFA is enabled
            if ($mfaSettings && $mfaSettings['isEnabled'] === 'Y' && !empty($totpCode)) {
                if (verifyTOTPCode($mfaSettings['totpSecret'], $totpCode)) {
                    $verified = true;
                }
            }

            // Fall back to password verification
            if (!$verified && !empty($password)) {
                // Get user's password hash
                $data = ['gibbonPersonID' => $gibbonPersonID];
                $sql = "SELECT passwordHash FROM gibbonPerson WHERE gibbonPersonID = :gibbonPersonID";
                $result = $pdo->select($sql, $data);
                $user = $result->fetch();

                if ($user && password_verify($password, $user['passwordHash'])) {
                    $verified = true;
                }
            }

            if (!$verified) {
                $URL .= '&return=error2';
                header("Location: {$URL}");
                exit;
            }

            // Disable MFA
            $mfaGateway->disableMFA($gibbonPersonID);

            // Delete backup codes
            $mfaGateway->deleteBackupCodes($gibbonPersonID);

            // Revoke all trusted devices
            $mfaGateway->revokeAllTrustedDevices($gibbonPersonID);

            // Log the action
            $mfaGateway->insertAuditLog(
                $gibbonPersonID,
                'disabled',
                $clientIP,
                $_SERVER['HTTP_USER_AGENT'] ?? null
            );

            $URL .= '&return=success1';
            header("Location: {$URL}");
            exit;

        } elseif ($action === 'revoke_device') {
            $deviceID = (int) ($_POST['device_id'] ?? 0);

            if ($deviceID > 0) {
                $mfaGateway->revokeTrustedDevice($deviceID);

                $mfaGateway->insertAuditLog(
                    $gibbonPersonID,
                    'disabled',
                    $clientIP,
                    $_SERVER['HTTP_USER_AGENT'] ?? null,
                    ['action' => 'device_revoked', 'device_id' => $deviceID]
                );
            }

            $URL .= '&return=success2';
            header("Location: {$URL}");
            exit;

        } elseif ($action === 'revoke_all_devices') {
            $mfaGateway->revokeAllTrustedDevices($gibbonPersonID);

            $mfaGateway->insertAuditLog(
                $gibbonPersonID,
                'disabled',
                $clientIP,
                $_SERVER['HTTP_USER_AGENT'] ?? null,
                ['action' => 'all_devices_revoked']
            );

            $URL .= '&return=success3';
            header("Location: {$URL}");
            exit;
        }
    }

    // Display return messages
    if (isset($_GET['return'])) {
        switch ($_GET['return']) {
            case 'success0':
                $page->addMessage(__('Your MFA settings have been updated.'));
                break;
            case 'success1':
                $page->addMessage(__('MFA has been disabled for your account.'));
                break;
            case 'success2':
                $page->addMessage(__('The trusted device has been revoked.'));
                break;
            case 'success3':
                $page->addMessage(__('All trusted devices have been revoked.'));
                break;
            case 'error0':
                $page->addError(__('Your request failed because you do not have access to this action.'));
                break;
            case 'error1':
                $page->addError(__('Your request failed due to a database error.'));
                break;
            case 'error2':
                $page->addError(__('Verification failed. Please check your password or authentication code.'));
                break;
        }
    }

    // Refresh MFA settings after any actions
    $mfaSettings = $mfaGateway->getMFASettingsByPerson($gibbonPersonID);
    $isMFAEnabled = $mfaSettings && $mfaSettings['isEnabled'] === 'Y' && $mfaSettings['isVerified'] === 'Y';

    // MFA Status Section
    echo '<h2>' . __('MFA Status') . '</h2>';

    if ($isMFAEnabled) {
        echo '<div class="success">';
        echo '<p><strong>' . __('MFA is enabled') . '</strong></p>';
        echo '<p>' . __('Your account is protected with multi-factor authentication.') . '</p>';
        if ($mfaSettings['enabledAt']) {
            echo '<p>' . sprintf(__('Enabled on: %s'), Format::dateTime($mfaSettings['enabledAt'])) . '</p>';
        }
        if ($mfaSettings['lastUsedAt']) {
            echo '<p>' . sprintf(__('Last used: %s'), Format::dateTime($mfaSettings['lastUsedAt'])) . '</p>';
        }
        echo '</div>';
    } else {
        echo '<div class="warning">';
        echo '<p><strong>' . __('MFA is not enabled') . '</strong></p>';
        if ($mfaRequired === 'Y') {
            echo '<p>' . __('MFA is required for administrator accounts. Please set up MFA to continue using your account.') . '</p>';
        } else {
            echo '<p>' . __('Enable MFA to add an extra layer of security to your account.') . '</p>';
        }
        echo '</div>';

        echo '<div class="linkTop">';
        echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/MFA/mfa_setup.php" class="btn btn-primary">';
        echo __('Set Up MFA');
        echo '</a>';
        echo '</div>';
    }

    if ($isMFAEnabled) {
        // Backup Codes Section
        $unusedCodesCount = $mfaGateway->countUnusedBackupCodes($gibbonPersonID);

        echo '<h2>' . __('Backup Codes') . '</h2>';

        if ($unusedCodesCount === 0) {
            echo '<div class="error">';
            echo '<p><strong>' . __('No backup codes remaining') . '</strong></p>';
            echo '<p>' . __('You should generate new backup codes in case you lose access to your authenticator app.') . '</p>';
            echo '</div>';
        } elseif ($unusedCodesCount <= 3) {
            echo '<div class="warning">';
            echo '<p><strong>' . sprintf(__('Only %d backup code(s) remaining'), $unusedCodesCount) . '</strong></p>';
            echo '<p>' . __('Consider generating new backup codes soon.') . '</p>';
            echo '</div>';
        } else {
            echo '<div class="message">';
            echo '<p>' . sprintf(__('You have %d backup codes remaining.'), $unusedCodesCount) . '</p>';
            echo '</div>';
        }

        echo '<div class="linkTop">';
        echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/MFA/mfa_backup_codes.php">';
        echo __('Manage Backup Codes');
        echo '</a>';
        echo '</div>';

        // Trusted Devices Section
        $trustedDevices = $mfaGateway->selectTrustedDevicesByPerson($gibbonPersonID);

        echo '<h2>' . __('Trusted Devices') . '</h2>';

        if (empty($trustedDevices)) {
            echo '<div class="message">';
            echo '<p>' . __('No trusted devices. You will need to enter your MFA code on each login.') . '</p>';
            echo '</div>';
        } else {
            echo '<div class="message">';
            echo '<p>' . __('These devices can skip MFA verification. Revoke access for any devices you no longer use.') . '</p>';
            echo '</div>';

            echo '<table class="w-full">';
            echo '<thead>';
            echo '<tr>';
            echo '<th>' . __('Device') . '</th>';
            echo '<th>' . __('Last Used') . '</th>';
            echo '<th>' . __('Expires') . '</th>';
            echo '<th>' . __('Actions') . '</th>';
            echo '</tr>';
            echo '</thead>';
            echo '<tbody>';

            foreach ($trustedDevices as $device) {
                echo '<tr>';
                echo '<td>';
                echo '<strong>' . htmlspecialchars($device['deviceName'] ?: __('Unknown Device')) . '</strong>';
                if ($device['lastIPAddress']) {
                    echo '<br><small class="text-gray-500">' . htmlspecialchars($device['lastIPAddress']) . '</small>';
                }
                echo '</td>';
                echo '<td>' . ($device['lastAccessAt'] ? Format::dateTime($device['lastAccessAt']) : __('Never')) . '</td>';
                echo '<td>' . Format::dateTime($device['expiresAt']) . '</td>';
                echo '<td>';
                // Revoke button form
                echo '<form method="post" action="' . $session->get('absoluteURL') . '/index.php?q=/modules/MFA/mfa_settings.php" style="display:inline;">';
                echo '<input type="hidden" name="address" value="' . $session->get('address') . '">';
                echo '<input type="hidden" name="csrf_token" value="' . $session->get('csrf_token') . '">';
                echo '<input type="hidden" name="action" value="revoke_device">';
                echo '<input type="hidden" name="device_id" value="' . $device['gibbonMFATrustedDeviceID'] . '">';
                echo '<button type="submit" class="text-red-600 hover:underline" onclick="return confirm(\'' . __('Are you sure you want to revoke this device?') . '\');">' . __('Revoke') . '</button>';
                echo '</form>';
                echo '</td>';
                echo '</tr>';
            }

            echo '</tbody>';
            echo '</table>';

            // Revoke all button
            echo '<div class="mt-4">';
            echo '<form method="post" action="' . $session->get('absoluteURL') . '/index.php?q=/modules/MFA/mfa_settings.php">';
            echo '<input type="hidden" name="address" value="' . $session->get('address') . '">';
            echo '<input type="hidden" name="csrf_token" value="' . $session->get('csrf_token') . '">';
            echo '<input type="hidden" name="action" value="revoke_all_devices">';
            echo '<button type="submit" class="text-red-600" onclick="return confirm(\'' . __('Are you sure you want to revoke all trusted devices?') . '\');">';
            echo __('Revoke All Devices');
            echo '</button>';
            echo '</form>';
            echo '</div>';
        }

        // IP Whitelist Section (if enabled)
        if ($ipWhitelistEnabled === 'Y') {
            $whitelistedIPs = $mfaGateway->selectWhitelistedIPsByPerson($gibbonPersonID);

            echo '<h2>' . __('Whitelisted IP Addresses') . '</h2>';

            if (empty($whitelistedIPs)) {
                echo '<div class="message">';
                echo '<p>' . __('No IP addresses are whitelisted for your account. MFA will be required from all locations.') . '</p>';
                echo '</div>';
            } else {
                echo '<div class="message">';
                echo '<p>' . __('MFA is not required when logging in from these IP addresses.') . '</p>';
                echo '</div>';

                echo '<table class="w-full">';
                echo '<thead>';
                echo '<tr>';
                echo '<th>' . __('IP Address') . '</th>';
                echo '<th>' . __('Description') . '</th>';
                echo '<th>' . __('Last Used') . '</th>';
                echo '</tr>';
                echo '</thead>';
                echo '<tbody>';

                foreach ($whitelistedIPs as $ip) {
                    echo '<tr>';
                    echo '<td><code>' . htmlspecialchars($ip['ipAddress']) . '</code></td>';
                    echo '<td>' . htmlspecialchars($ip['description'] ?: '-') . '</td>';
                    echo '<td>' . ($ip['lastAccessAt'] ? Format::dateTime($ip['lastAccessAt']) : __('Never')) . '</td>';
                    echo '</tr>';
                }

                echo '</tbody>';
                echo '</table>';
            }

            echo '<div class="text-gray-500 text-sm mt-2">';
            echo __('IP whitelist is managed by system administrators.');
            echo '</div>';
        }

        // Disable MFA Section
        echo '<h2>' . __('Disable MFA') . '</h2>';

        echo '<div class="warning">';
        echo '<p>' . __('Disabling MFA will remove the extra layer of security from your account.') . '</p>';
        if ($mfaRequired === 'Y') {
            echo '<p><strong>' . __('Note: MFA is required for administrator accounts. You may be prompted to set up MFA again on your next login.') . '</strong></p>';
        }
        echo '</div>';

        $form = Form::create('disableMFA', $session->get('absoluteURL') . '/index.php?q=/modules/MFA/mfa_settings.php');
        $form->setTitle(__('Confirm Disable MFA'));
        $form->setDescription(__('Enter your authentication code OR password to confirm.'));
        $form->addHiddenValue('address', $session->get('address'));
        $form->addHiddenValue('csrf_token', $session->get('csrf_token'));
        $form->addHiddenValue('action', 'disable');

        $row = $form->addRow();
            $row->addLabel('totp_code', __('Authentication Code'));
            $row->addTextField('totp_code')
                ->maxLength(6)
                ->setClass('w-48')
                ->placeholder('000000')
                ->setAttribute('autocomplete', 'off')
                ->setAttribute('inputmode', 'numeric');

        $row = $form->addRow();
            $row->addContent('<div class="text-center text-gray-500">— ' . __('OR') . ' —</div>');

        $row = $form->addRow();
            $row->addLabel('password', __('Current Password'));
            $row->addPassword('password')
                ->setClass('w-64');

        $row = $form->addRow();
            $row->addFooter();
            $row->addSubmit(__('Disable MFA'))
                ->setClass('bg-red-600 text-white')
                ->onClick('return confirm("' . __('Are you sure you want to disable MFA?') . '");');

        echo $form->getOutput();

        // Recent Activity Section
        $recentActivity = $mfaGateway->selectRecentAuditLog($gibbonPersonID, 10);

        if (!empty($recentActivity)) {
            echo '<h2>' . __('Recent MFA Activity') . '</h2>';

            echo '<table class="w-full">';
            echo '<thead>';
            echo '<tr>';
            echo '<th>' . __('Action') . '</th>';
            echo '<th>' . __('IP Address') . '</th>';
            echo '<th>' . __('Date/Time') . '</th>';
            echo '</tr>';
            echo '</thead>';
            echo '<tbody>';

            $actionLabels = [
                'setup_initiated' => __('Setup Started'),
                'setup_completed' => __('Setup Completed'),
                'setup_cancelled' => __('Setup Cancelled'),
                'enabled' => __('MFA Enabled'),
                'disabled' => __('MFA Disabled'),
                'verified' => __('Verification Successful'),
                'verification_failed' => __('Verification Failed'),
                'backup_codes_generated' => __('Backup Codes Generated'),
                'backup_code_used' => __('Backup Code Used'),
                'ip_whitelist_added' => __('IP Added to Whitelist'),
                'ip_whitelist_removed' => __('IP Removed from Whitelist'),
                'lockout' => __('Account Locked'),
                'lockout_cleared' => __('Lockout Cleared'),
            ];

            foreach ($recentActivity as $activity) {
                $actionLabel = $actionLabels[$activity['action']] ?? $activity['action'];
                $rowClass = '';
                if (in_array($activity['action'], ['verification_failed', 'lockout'])) {
                    $rowClass = 'bg-red-50';
                } elseif ($activity['action'] === 'verified') {
                    $rowClass = 'bg-green-50';
                }

                echo '<tr class="' . $rowClass . '">';
                echo '<td>' . $actionLabel . '</td>';
                echo '<td><code>' . htmlspecialchars($activity['ipAddress'] ?: '-') . '</code></td>';
                echo '<td>' . Format::dateTime($activity['timestampCreated']) . '</td>';
                echo '</tr>';
            }

            echo '</tbody>';
            echo '</table>';
        }
    }

    // Information Section
    echo '<h2>' . __('About Multi-Factor Authentication') . '</h2>';
    echo '<div class="message">';
    echo '<p>' . __('Multi-Factor Authentication (MFA) adds an extra layer of security to your account by requiring a second form of verification in addition to your password.') . '</p>';
    echo '<h4>' . __('How It Works') . '</h4>';
    echo '<ol class="list-decimal ml-6">';
    echo '<li>' . __('After entering your password, you will be prompted for a 6-digit code.') . '</li>';
    echo '<li>' . __('Open your authenticator app to get the current code.') . '</li>';
    echo '<li>' . __('Enter the code to complete your login.') . '</li>';
    echo '</ol>';
    echo '<h4>' . __('Supported Authenticator Apps') . '</h4>';
    echo '<ul class="list-disc ml-6">';
    echo '<li>Google Authenticator</li>';
    echo '<li>Microsoft Authenticator</li>';
    echo '<li>Authy</li>';
    echo '<li>1Password</li>';
    echo '<li>' . __('Any TOTP-compatible app') . '</li>';
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
