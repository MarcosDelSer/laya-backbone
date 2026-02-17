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

$URL = $session->get('absoluteURL') . '/index.php?q=/modules/ExampleModule/exampleModule_manage_add.php';

// Access check - CRITICAL: Address must be HARD-CODED (never use variables)
if (!isActionAccessible($guid, $connection2, '/modules/ExampleModule/exampleModule_manage.php')) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
} else {
    // Proceed with form submission
    $gibbonSchoolYearID = $session->get('gibbonSchoolYearID');
    $gibbonPersonIDCurrent = $session->get('gibbonPersonID');

    // Get form data
    $title = $_POST['title'] ?? '';
    $description = $_POST['description'] ?? null;
    $status = $_POST['status'] ?? 'Active';
    $gibbonPersonID = $_POST['gibbonPersonID'] ?? null;

    // Validate required fields
    if (empty($title) || empty($gibbonPersonID)) {
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit;
    }

    // Get gateway via DI container
    $exampleEntityGateway = $container->get(ExampleEntityGateway::class);

    // Prepare data - CRITICAL: Use parameterized queries (Gateway handles this)
    $data = [
        'gibbonPersonID' => $gibbonPersonID,
        'gibbonSchoolYearID' => $gibbonSchoolYearID,
        'title' => $title,
        'description' => $description,
        'status' => $status,
        'createdByID' => $gibbonPersonIDCurrent,
    ];

    // Insert record
    $gibbonExampleEntityID = $exampleEntityGateway->insertExampleEntity($data);

    if ($gibbonExampleEntityID === false) {
        $URL .= '&return=error2';
        header("Location: {$URL}");
        exit;
    } else {
        $URL = $session->get('absoluteURL') . '/index.php?q=/modules/ExampleModule/exampleModule_manage.php';
        $URL .= '&return=success0';
        header("Location: {$URL}");
        exit;
    }
}
