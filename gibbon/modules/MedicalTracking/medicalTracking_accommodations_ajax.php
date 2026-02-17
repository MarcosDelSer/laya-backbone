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

use Gibbon\Module\MedicalTracking\Domain\AllergyGateway;

// Gibbon system-wide includes
require __DIR__ . '/../../gibbon.php';

// Set JSON content type
header('Content-Type: application/json');

// Access check
if (!$session->exists('gibbonPersonID')) {
    echo json_encode([]);
    exit;
}

$gibbonPersonID = $_GET['gibbonPersonID'] ?? null;

if (empty($gibbonPersonID)) {
    echo json_encode([]);
    exit;
}

// Get allergies for the selected child
$allergyGateway = $container->get(AllergyGateway::class);
$allergies = $allergyGateway->selectAllergiesByPerson($gibbonPersonID, true);

$result = [];
while ($allergy = $allergies->fetch()) {
    $result[] = [
        'id' => $allergy['gibbonMedicalAllergyID'],
        'name' => $allergy['allergenName'],
        'type' => $allergy['allergenType'],
        'severity' => $allergy['severity'],
    ];
}

echo json_encode($result);
