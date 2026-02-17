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

use Gibbon\Forms\Form;
use Gibbon\Tables\DataTable;
use Gibbon\Services\Format;
use Gibbon\Module\MedicalProtocol\Domain\ProtocolGateway;
use Gibbon\Module\MedicalProtocol\Domain\AuthorizationGateway;

// Module setup - breadcrumbs
$page->breadcrumbs->add(__('Medical Protocol'), 'medicalProtocol.php');
$page->breadcrumbs->add(__('Authorizations'));

// Access check
if (!isActionAccessible($guid, $connection2, '/modules/MedicalProtocol/medicalProtocol_authorizations.php')) {
    $page->addError(__('You do not have access to this action.'));
} else {
    // Get school year ID from session
    $gibbonSchoolYearID = $session->get('gibbonSchoolYearID');
    $gibbonPersonID = $session->get('gibbonPersonID');

    // Get gateways via DI container
    $protocolGateway = $container->get(ProtocolGateway::class);
    $authorizationGateway = $container->get(AuthorizationGateway::class);

    // Handle revoke action
    $action = $_POST['action'] ?? '';
    $authorizationID = $_POST['gibbonMedicalProtocolAuthorizationID'] ?? null;

    if ($action === 'revokeAuthorization' && !empty($authorizationID)) {
        $revokeReason = $_POST['revokeReason'] ?? '';
        if (!empty($revokeReason)) {
            $result = $authorizationGateway->revokeAuthorization($authorizationID, $gibbonPersonID, $revokeReason);
            if ($result) {
                $page->addSuccess(__('Authorization has been revoked successfully.'));
            } else {
                $page->addError(__('Failed to revoke authorization.'));
            }
        } else {
            $page->addError(__('Please provide a reason for revocation.'));
        }
    }

    // Handle weight update action
    if ($action === 'updateWeight' && !empty($authorizationID)) {
        $newWeight = $_POST['newWeight'] ?? null;
        if (!empty($newWeight) && is_numeric($newWeight) && $newWeight >= 2 && $newWeight <= 50) {
            $result = $authorizationGateway->updateWeight($authorizationID, floatval($newWeight));
            if ($result) {
                $page->addSuccess(__('Weight has been updated successfully. New expiry date is in 3 months.'));
            } else {
                $page->addError(__('Failed to update weight.'));
            }
        } else {
            $page->addError(__('Please provide a valid weight between 2 and 50 kg.'));
        }
    }

    // Page header
    echo '<h2>' . __('Parent Authorizations') . '</h2>';

    // Get filter values from request
    $filterProtocol = $_GET['protocol'] ?? '';
    $filterStatus = $_GET['status'] ?? '';
    $filterWeightExpired = $_GET['weightExpired'] ?? '';
    $filterChild = $_GET['child'] ?? '';
    $search = $_GET['search'] ?? '';

    // Get protocols for filter dropdown
    $protocols = $protocolGateway->selectActiveProtocols()->fetchAll();
    $protocolOptions = ['' => __('All Protocols')];
    foreach ($protocols as $protocol) {
        $protocolOptions[$protocol['gibbonMedicalProtocolID']] = $protocol['name'] . ' (' . $protocol['formCode'] . ')';
    }

    // Status options
    $statusOptions = [
        '' => __('All Statuses'),
        'Active' => __('Active'),
        'Expired' => __('Expired'),
        'Revoked' => __('Revoked'),
        'Pending' => __('Pending'),
    ];

    // Weight expiry options
    $weightOptions = [
        '' => __('All'),
        'Y' => __('Weight Expired'),
        'N' => __('Weight Valid'),
    ];

    // Filter form
    $form = Form::create('authorizationFilter', $session->get('absoluteURL') . '/index.php');
    $form->setMethod('get');
    $form->setClass('noIntBorder fullWidth');
    $form->addHiddenValue('q', '/modules/MedicalProtocol/medicalProtocol_authorizations.php');

    $row = $form->addRow();
    $row->addLabel('protocol', __('Protocol'));
    $row->addSelect('protocol')
        ->fromArray($protocolOptions)
        ->selected($filterProtocol);

    $row = $form->addRow();
    $row->addLabel('status', __('Status'));
    $row->addSelect('status')
        ->fromArray($statusOptions)
        ->selected($filterStatus);

    $row = $form->addRow();
    $row->addLabel('weightExpired', __('Weight Status'));
    $row->addSelect('weightExpired')
        ->fromArray($weightOptions)
        ->selected($filterWeightExpired);

    $row = $form->addRow();
    $row->addLabel('search', __('Search'));
    $row->addTextField('search')
        ->setValue($search)
        ->placeholder(__('Child name or form code...'));

    $row = $form->addRow();
    $row->addSearchSubmit($session, __('Clear Filters'));

    echo $form->getOutput();

    // Get summary statistics
    $summary = $authorizationGateway->getAuthorizationSummary($gibbonSchoolYearID);

    // Display summary
    echo '<div class="bg-white rounded-lg shadow p-4 mb-4">';
    echo '<h3 class="text-lg font-semibold mb-3">' . __('Authorization Summary') . '</h3>';
    echo '<div class="grid grid-cols-2 md:grid-cols-6 gap-4 text-center">';

    echo '<div class="bg-gray-50 rounded p-2">';
    echo '<span class="block text-2xl font-bold">' . ($summary['totalAuthorizations'] ?? 0) . '</span>';
    echo '<span class="text-xs text-gray-500">' . __('Total') . '</span>';
    echo '</div>';

    echo '<div class="bg-green-50 rounded p-2">';
    echo '<span class="block text-2xl font-bold text-green-600">' . ($summary['activeAuthorizations'] ?? 0) . '</span>';
    echo '<span class="text-xs text-gray-500">' . __('Active') . '</span>';
    echo '</div>';

    echo '<div class="bg-yellow-50 rounded p-2">';
    echo '<span class="block text-2xl font-bold text-yellow-600">' . ($summary['pendingAuthorizations'] ?? 0) . '</span>';
    echo '<span class="text-xs text-gray-500">' . __('Pending') . '</span>';
    echo '</div>';

    echo '<div class="bg-gray-50 rounded p-2">';
    echo '<span class="block text-2xl font-bold text-gray-600">' . ($summary['expiredAuthorizations'] ?? 0) . '</span>';
    echo '<span class="text-xs text-gray-500">' . __('Expired') . '</span>';
    echo '</div>';

    echo '<div class="bg-red-50 rounded p-2">';
    echo '<span class="block text-2xl font-bold text-red-600">' . ($summary['expiredWeightAuthorizations'] ?? 0) . '</span>';
    echo '<span class="text-xs text-gray-500">' . __('Weight Expired') . '</span>';
    echo '</div>';

    echo '<div class="bg-blue-50 rounded p-2">';
    echo '<span class="block text-2xl font-bold text-blue-600">' . ($summary['uniqueChildren'] ?? 0) . '</span>';
    echo '<span class="text-xs text-gray-500">' . __('Children') . '</span>';
    echo '</div>';

    echo '</div>';
    echo '</div>';

    // Section: Weight Expired Alerts
    if ($filterWeightExpired !== 'N') {
        $expiredWeightAuthorizations = $authorizationGateway->selectExpiredWeightAuthorizations($gibbonSchoolYearID);

        if ($expiredWeightAuthorizations->rowCount() > 0) {
            echo '<h3 class="text-lg font-semibold mt-6 mb-3">' . __('Weight Updates Required') . '</h3>';
            echo '<div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-4">';
            echo '<p class="text-sm text-red-600 mb-3">' . __('These authorizations have expired weights and require an update per Quebec 3-month requirement.') . '</p>';

            echo '<div class="space-y-2">';
            $displayCount = 0;
            foreach ($expiredWeightAuthorizations as $auth) {
                if ($displayCount >= 5) break;
                $childName = Format::name('', $auth['preferredName'], $auth['surname'], 'Student', false, true);
                $weight = $auth['weightKg'] ?? 0;
                $expiredDate = Format::date($auth['weightExpiryDate']);
                $formCode = $auth['formCode'] ?? '';
                $authID = $auth['gibbonMedicalProtocolAuthorizationID'];

                echo '<div class="bg-white rounded p-3 flex items-center justify-between">';
                echo '<div>';
                echo '<span class="font-medium">' . htmlspecialchars($childName) . '</span>';
                echo ' - <span class="text-sm">' . htmlspecialchars($auth['protocolName']) . ' (' . htmlspecialchars($formCode) . ')</span>';
                echo '<p class="text-sm text-gray-600 mt-1">' . __('Current weight') . ': ' . $weight . ' kg | ' . __('Expired') . ': ' . $expiredDate . '</p>';
                echo '</div>';
                echo '<form method="post" action="' . $session->get('absoluteURL') . '/index.php?q=/modules/MedicalProtocol/medicalProtocol_authorizations.php" class="ml-4 flex items-center gap-2">';
                echo '<input type="hidden" name="action" value="updateWeight">';
                echo '<input type="hidden" name="gibbonMedicalProtocolAuthorizationID" value="' . $authID . '">';
                echo '<input type="number" name="newWeight" step="0.1" min="2" max="50" placeholder="' . __('New weight (kg)') . '" class="w-24 px-2 py-1 border rounded text-sm" required>';
                echo '<button type="submit" class="bg-green-500 text-white text-xs px-3 py-1 rounded hover:bg-green-600">' . __('Update Weight') . '</button>';
                echo '</form>';
                echo '</div>';
                $displayCount++;
            }
            if ($expiredWeightAuthorizations->rowCount() > 5) {
                echo '<p class="text-sm text-red-500 mt-2">' . sprintf(__('...and %d more authorizations with expired weights'), $expiredWeightAuthorizations->rowCount() - 5) . '</p>';
            }
            echo '</div>';

            echo '</div>';
        }
    }

    // Build query criteria
    $criteria = $authorizationGateway->newQueryCriteria()
        ->searchBy($authorizationGateway->getSearchableColumns(), $search)
        ->sortBy(['gibbonPerson.surname', 'gibbonPerson.preferredName', 'signatureDate'])
        ->fromPOST();

    // Apply filters
    if (!empty($filterProtocol)) {
        $criteria->filterBy('protocol', $filterProtocol);
    }
    if (!empty($filterStatus)) {
        $criteria->filterBy('status', $filterStatus);
    }
    if (!empty($filterWeightExpired)) {
        $criteria->filterBy('weightExpired', $filterWeightExpired);
    }
    if (!empty($filterChild)) {
        $criteria->filterBy('child', $filterChild);
    }

    // Get authorization data
    $authorizations = $authorizationGateway->queryAuthorizations($criteria, $gibbonSchoolYearID);

    // Build DataTable
    $table = DataTable::createPaginated('authorizations', $criteria);
    $table->setTitle(__('Authorization Records'));

    // Add columns
    $table->addColumn('image', __('Photo'))
        ->notSortable()
        ->format(function ($row) use ($session) {
            $image = !empty($row['image_240']) ? $row['image_240'] : 'themes/Default/img/anonymous_240.jpg';
            return '<img src="' . $session->get('absoluteURL') . '/' . $image . '" class="w-10 h-10 rounded-full object-cover" alt="">';
        });

    $table->addColumn('name', __('Child'))
        ->sortable(['surname', 'preferredName'])
        ->format(function ($row) {
            return Format::name('', $row['preferredName'], $row['surname'], 'Student', true, true);
        });

    $table->addColumn('protocolName', __('Protocol'))
        ->sortable()
        ->format(function ($row) {
            $color = $row['protocolType'] === 'Medication' ? 'blue' : 'green';
            return '<span class="bg-' . $color . '-100 text-' . $color . '-800 text-xs px-2 py-1 rounded">' .
                   htmlspecialchars($row['protocolName']) . '</span>' .
                   '<br><span class="text-xs text-gray-500">' . htmlspecialchars($row['formCode']) . '</span>';
        });

    $table->addColumn('status', __('Status'))
        ->sortable()
        ->format(function ($row) {
            $colors = [
                'Active'  => 'green',
                'Pending' => 'yellow',
                'Expired' => 'gray',
                'Revoked' => 'red',
            ];
            $color = $colors[$row['status']] ?? 'gray';
            return '<span class="bg-' . $color . '-100 text-' . $color . '-800 text-xs px-2 py-1 rounded">' . __($row['status']) . '</span>';
        });

    $table->addColumn('signatureDate', __('Signed'))
        ->sortable()
        ->format(function ($row) {
            return Format::date($row['signatureDate']);
        });

    $table->addColumn('weightKg', __('Weight'))
        ->sortable()
        ->format(function ($row) {
            $weight = $row['weightKg'] ?? 0;
            return $weight . ' kg';
        });

    $table->addColumn('weightExpiryDate', __('Weight Expiry'))
        ->sortable()
        ->format(function ($row) {
            $expiryDate = $row['weightExpiryDate'];
            $isExpired = strtotime($expiryDate) < strtotime(date('Y-m-d'));
            $isExpiringSoon = !$isExpired && strtotime($expiryDate) <= strtotime('+14 days');

            $class = 'text-green-600';
            $icon = '';
            if ($isExpired) {
                $class = 'text-red-600 font-bold';
                $icon = '<span class="mr-1" title="' . __('Expired') . '">&#9888;</span>';
            } elseif ($isExpiringSoon) {
                $class = 'text-yellow-600';
                $icon = '<span class="mr-1" title="' . __('Expiring Soon') . '">&#9888;</span>';
            }

            return '<span class="' . $class . '">' . $icon . Format::date($expiryDate) . '</span>';
        });

    $table->addColumn('authorizedBy', __('Authorized By'))
        ->notSortable()
        ->format(function ($row) {
            if (!empty($row['authorizedByName'])) {
                return Format::name('', $row['authorizedByName'], $row['authorizedBySurname'], 'Staff', false, false);
            }
            return '<span class="text-gray-400">-</span>';
        });

    // Actions column
    $table->addActionColumn()
        ->addParam('gibbonMedicalProtocolAuthorizationID')
        ->format(function ($row, $actions) use ($session) {
            // View Signature action
            $actions->addAction('view', __('View Signature'))
                ->setIcon('search')
                ->setURL('/modules/MedicalProtocol/medicalProtocol_authorizations_view.php');

            // Revoke action (only for Active authorizations)
            if ($row['status'] === 'Active') {
                $actions->addAction('delete', __('Revoke'))
                    ->setIcon('cross')
                    ->addParam('action', 'revoke')
                    ->setURL('/modules/MedicalProtocol/medicalProtocol_authorizations.php')
                    ->modalWindow('400', '250');
            }

            return $actions;
        });

    // Output table
    if ($authorizations->count() > 0) {
        echo $table->render($authorizations);
    } else {
        echo '<div class="bg-gray-50 rounded-lg p-4 text-center text-gray-500">';
        echo __('No authorization records found matching your criteria.');
        echo '</div>';
    }

    // Revoke Modal Form (hidden, triggered by JavaScript)
    echo '<div id="revokeModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">';
    echo '<div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">';
    echo '<h3 class="text-lg font-semibold mb-4">' . __('Revoke Authorization') . '</h3>';
    echo '<form method="post" action="' . $session->get('absoluteURL') . '/index.php?q=/modules/MedicalProtocol/medicalProtocol_authorizations.php">';
    echo '<input type="hidden" name="action" value="revokeAuthorization">';
    echo '<input type="hidden" name="gibbonMedicalProtocolAuthorizationID" id="revokeAuthID" value="">';
    echo '<div class="mb-4">';
    echo '<label class="block text-sm font-medium mb-1">' . __('Reason for Revocation') . ' <span class="text-red-500">*</span></label>';
    echo '<textarea name="revokeReason" rows="3" class="w-full border rounded px-3 py-2" placeholder="' . __('Please provide a reason...') . '" required></textarea>';
    echo '</div>';
    echo '<div class="flex justify-end gap-2">';
    echo '<button type="button" onclick="closeRevokeModal()" class="px-4 py-2 bg-gray-300 text-gray-700 rounded hover:bg-gray-400">' . __('Cancel') . '</button>';
    echo '<button type="submit" class="px-4 py-2 bg-red-500 text-white rounded hover:bg-red-600">' . __('Revoke') . '</button>';
    echo '</div>';
    echo '</form>';
    echo '</div>';
    echo '</div>';

    // JavaScript for modal
    echo '<script>
    function openRevokeModal(authID) {
        document.getElementById("revokeAuthID").value = authID;
        document.getElementById("revokeModal").classList.remove("hidden");
    }
    function closeRevokeModal() {
        document.getElementById("revokeModal").classList.add("hidden");
    }
    </script>';

    // Link back to dashboard
    echo '<div class="mt-4">';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/MedicalProtocol/medicalProtocol.php" class="text-blue-600 hover:underline">&larr; ' . __('Back to Dashboard') . '</a>';
    echo '</div>';
}
