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

use Gibbon\Module\ExampleModule\Domain\ExampleEntityGateway;

require_once '../../gibbon.php';

$gibbonExampleEntityID = $_GET['gibbonExampleEntityID'] ?? '';
$URL = $session->get('absoluteURL') . '/index.php?q=/modules/ExampleModule/exampleModule_manage.php';

// Access check - CRITICAL: Address must be HARD-CODED (never use variables)
if (!isActionAccessible($guid, $connection2, '/modules/ExampleModule/exampleModule_manage.php')) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
} else {
    // Validate ID
    if (empty($gibbonExampleEntityID)) {
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit;
    }

    // Get gateway via DI container
    $exampleEntityGateway = $container->get(ExampleEntityGateway::class);

    // Verify record exists
    $existing = $exampleEntityGateway->selectExampleEntityByID($gibbonExampleEntityID);
    if (empty($existing)) {
        $URL .= '&return=error2';
        header("Location: {$URL}");
        exit;
    }

    // Delete record
    $deleted = $exampleEntityGateway->deleteExampleEntity($gibbonExampleEntityID);

    if ($deleted === false) {
        $URL .= '&return=error2';
        header("Location: {$URL}");
        exit;
    } else {
        $URL .= '&return=success0';
        header("Location: {$URL}");
        exit;
    }
}
