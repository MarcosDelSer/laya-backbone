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

use Gibbon\Domain\System\SettingGateway;

require_once '../../gibbon.php';

$URL = $session->get('absoluteURL') . '/index.php?q=/modules/ExampleModule/exampleModule_settings.php';

// Access check - CRITICAL: Address must be HARD-CODED (never use variables)
if (!isActionAccessible($guid, $connection2, '/modules/ExampleModule/exampleModule_settings.php')) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
} else {
    // Get form data
    $enableFeature = $_POST['enableFeature'] ?? 'N';
    $maxItems = $_POST['maxItems'] ?? 50;

    // Validate
    if (empty($maxItems) || !is_numeric($maxItems) || $maxItems < 10 || $maxItems > 200) {
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit;
    }

    // Get settings gateway
    $settingGateway = $container->get(SettingGateway::class);

    // Update settings
    $updated1 = $settingGateway->updateSettingByScope('Example Module', 'enableFeature', $enableFeature);
    $updated2 = $settingGateway->updateSettingByScope('Example Module', 'maxItems', $maxItems);

    if ($updated1 === false || $updated2 === false) {
        $URL .= '&return=error2';
        header("Location: {$URL}");
        exit;
    } else {
        $URL .= '&return=success0';
        header("Location: {$URL}");
        exit;
    }
}
