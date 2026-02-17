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

if (isActionAccessible($guid, $connection2, '/modules/MFA/mfa_backup_codes.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Proceed!
    $page->breadcrumbs
        ->add(__('MFA Settings'), 'mfa_settings.php')
        ->add(__('Backup Codes'));

    // Get current user
    $gibbonPersonID = $session->get('gibbonPersonID');

    // Get gateways and settings
    $mfaGateway = $container->get(MFAGateway::class);
    $settingGateway = $container->get(SettingGateway::class);

    $backupCodesCount = (int) ($settingGateway->getSettingByScope('MFA', 'backupCodesCount') ?? 10);

    // Get MFA settings for user
    $mfaSettings = $mfaGateway->getMFASettingsByPerson($gibbonPersonID);

    // Check if MFA is enabled
    if (!$mfaSettings || $mfaSettings['isEnabled'] !== 'Y' || $mfaSettings['isVerified'] !== 'Y') {
        $page->addError(__('MFA is not enabled for your account. Please set up MFA first.'));
        echo '<div class="linkTop">';
        echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/MFA/mfa_setup.php">' . __('Set Up MFA') . '</a>';
        echo '</div>';
        return;
    }

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $URL = $session->get('absoluteURL') . '/index.php?q=/modules/MFA/mfa_backup_codes.php';

        // CSRF check
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $session->get('csrf_token')) {
            $URL .= '&return=error0';
            header("Location: {$URL}");
            exit;
        }

        $action = $_POST['action'] ?? '';
        $clientIP = $_SERVER['REMOTE_ADDR'] ?? '';

        if ($action === 'regenerate') {
            // Require verification to regenerate codes
            $totpCode = $_POST['totp_code'] ?? '';

            // Verify TOTP code
            if (empty($totpCode) || !verifyTOTPCode($mfaSettings['totpSecret'], $totpCode)) {
                $URL .= '&return=error2';
                header("Location: {$URL}");
                exit;
            }

            // Generate new backup codes
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

            // Log the action
            $mfaGateway->insertAuditLog(
                $gibbonPersonID,
                'backup_codes_generated',
                $clientIP,
                $_SERVER['HTTP_USER_AGENT'] ?? null
            );

            // Store backup codes in session for display
            $session->set('mfa_new_backup_codes', $backupCodes);

            $URL .= '&return=success0&show=1';
            header("Location: {$URL}");
            exit;
        }
    }

    // Display return messages
    if (isset($_GET['return'])) {
        switch ($_GET['return']) {
            case 'success0':
                $page->addMessage(__('New backup codes have been generated.'));
                break;
            case 'error0':
                $page->addError(__('Your request failed because you do not have access to this action.'));
                break;
            case 'error1':
                $page->addError(__('Your request failed due to a database error.'));
                break;
            case 'error2':
                $page->addError(__('Verification failed. Please check your authentication code.'));
                break;
        }
    }

    // Show newly generated codes
    if (isset($_GET['show']) && $session->get('mfa_new_backup_codes')) {
        $newCodes = $session->get('mfa_new_backup_codes');
        $session->set('mfa_new_backup_codes', null);

        echo '<div class="warning">';
        echo '<h3>' . __('Save Your New Backup Codes') . '</h3>';
        echo '<p>' . __('Your previous backup codes have been invalidated. Save these new codes in a safe place.') . '</p>';
        echo '<p><strong>' . __('Each code can only be used once.') . '</strong></p>';
        echo '<p><strong>' . __('These codes will not be shown again!') . '</strong></p>';
        echo '</div>';

        echo '<div class="p-4 bg-gray-100 rounded-lg my-4">';
        echo '<div class="grid grid-cols-2 gap-2 font-mono text-lg">';
        foreach ($newCodes as $code) {
            echo '<div class="p-2 bg-white rounded border text-center">' . htmlspecialchars($code) . '</div>';
        }
        echo '</div>';
        echo '<div class="mt-4 text-center">';
        echo '<button onclick="window.print()" class="btn btn-default">' . __('Print Codes') . '</button>';
        echo ' ';
        echo '<button onclick="copyBackupCodes()" class="btn btn-default">' . __('Copy to Clipboard') . '</button>';
        echo '</div>';
        echo '</div>';

        // JavaScript for copying codes
        echo '<script>';
        echo 'function copyBackupCodes() {';
        echo '  var codes = ' . json_encode(implode("\n", $newCodes)) . ';';
        echo '  navigator.clipboard.writeText(codes).then(function() {';
        echo '    alert("' . __('Backup codes copied to clipboard!') . '");';
        echo '  });';
        echo '}';
        echo '</script>';

        echo '<hr class="my-6">';
    }

    // Current backup codes status
    $backupCodes = $mfaGateway->selectBackupCodesByPerson($gibbonPersonID);
    $unusedCount = 0;
    $usedCount = 0;

    foreach ($backupCodes as $code) {
        if ($code['isUsed'] === 'Y') {
            $usedCount++;
        } else {
            $unusedCount++;
        }
    }

    echo '<h2>' . __('Backup Codes Status') . '</h2>';

    if ($unusedCount === 0) {
        echo '<div class="error">';
        echo '<p><strong>' . __('You have no backup codes remaining!') . '</strong></p>';
        echo '<p>' . __('Generate new backup codes immediately to ensure you can access your account if you lose your authenticator app.') . '</p>';
        echo '</div>';
    } elseif ($unusedCount <= 3) {
        echo '<div class="warning">';
        echo '<p><strong>' . sprintf(__('Only %d backup code(s) remaining'), $unusedCount) . '</strong></p>';
        echo '<p>' . __('Consider generating new backup codes soon.') . '</p>';
        echo '</div>';
    } else {
        echo '<div class="success">';
        echo '<p><strong>' . sprintf(__('%d backup codes remaining'), $unusedCount) . '</strong></p>';
        echo '</div>';
    }

    // Show backup code usage history
    if (!empty($backupCodes)) {
        echo '<h3>' . __('Backup Code Usage') . '</h3>';
        echo '<table class="w-full">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>#</th>';
        echo '<th>' . __('Status') . '</th>';
        echo '<th>' . __('Used At') . '</th>';
        echo '<th>' . __('Used From IP') . '</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';

        $i = 1;
        foreach ($backupCodes as $code) {
            $rowClass = $code['isUsed'] === 'Y' ? 'bg-gray-100 text-gray-500' : '';
            echo '<tr class="' . $rowClass . '">';
            echo '<td>' . $i++ . '</td>';
            echo '<td>';
            if ($code['isUsed'] === 'Y') {
                echo '<span class="text-red-600">' . __('Used') . '</span>';
            } else {
                echo '<span class="text-green-600">' . __('Available') . '</span>';
            }
            echo '</td>';
            echo '<td>' . ($code['usedAt'] ? Format::dateTime($code['usedAt']) : '-') . '</td>';
            echo '<td>' . ($code['usedIP'] ? '<code>' . htmlspecialchars($code['usedIP']) . '</code>' : '-') . '</td>';
            echo '</tr>';
        }

        echo '</tbody>';
        echo '</table>';
    }

    // Regenerate form
    echo '<h2>' . __('Generate New Backup Codes') . '</h2>';

    echo '<div class="warning">';
    echo '<p><strong>' . __('Warning:') . '</strong> ' . __('Generating new backup codes will invalidate all existing codes.') . '</p>';
    echo '</div>';

    $form = Form::create('regenerateCodes', $session->get('absoluteURL') . '/index.php?q=/modules/MFA/mfa_backup_codes.php');
    $form->setTitle(__('Regenerate Backup Codes'));
    $form->setDescription(__('Enter your authentication code to confirm.'));
    $form->addHiddenValue('address', $session->get('address'));
    $form->addHiddenValue('csrf_token', $session->get('csrf_token'));
    $form->addHiddenValue('action', 'regenerate');

    $row = $form->addRow();
        $row->addLabel('totp_code', __('Authentication Code'));
        $row->addTextField('totp_code')
            ->required()
            ->maxLength(6)
            ->setClass('w-48 text-center text-xl tracking-widest')
            ->placeholder('000000')
            ->setAttribute('autocomplete', 'off')
            ->setAttribute('inputmode', 'numeric')
            ->setAttribute('pattern', '[0-9]{6}');

    $row = $form->addRow();
        $row->addFooter();
        $row->addSubmit(__('Generate New Codes'));

    echo $form->getOutput();

    // Information section
    echo '<h2>' . __('About Backup Codes') . '</h2>';
    echo '<div class="message">';
    echo '<h4>' . __('What are backup codes?') . '</h4>';
    echo '<p>' . __('Backup codes are one-time use codes that you can use to sign in if you lose access to your authenticator app.') . '</p>';

    echo '<h4>' . __('Best Practices') . '</h4>';
    echo '<ul class="list-disc ml-6">';
    echo '<li>' . __('Store your backup codes in a safe place, separate from your phone.') . '</li>';
    echo '<li>' . __('Print them out and keep them in a secure location.') . '</li>';
    echo '<li>' . __('Do not share your backup codes with anyone.') . '</li>';
    echo '<li>' . __('Generate new codes if you suspect they have been compromised.') . '</li>';
    echo '<li>' . __('Generate new codes when you run low on remaining codes.') . '</li>';
    echo '</ul>';

    echo '<h4>' . __('Using a Backup Code') . '</h4>';
    echo '<p>' . __('During login, if you cannot access your authenticator app, click "Use a backup code instead" and enter one of your unused codes.') . '</p>';
    echo '</div>';

    // Back link
    echo '<div class="linkTop mt-4">';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/MFA/mfa_settings.php">' . __('Back to MFA Settings') . '</a>';
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
