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

use Gibbon\Tables\DataTable;
use Gibbon\Services\Format;
use Gibbon\Domain\QueryCriteria;
use Gibbon\Module\RL24Submission\Domain\RL24TransmissionGateway;
use Gibbon\Module\RL24Submission\Domain\RL24SlipGateway;

// Module setup - breadcrumbs
$page->breadcrumbs->add(__('RL-24 Transmissions'), 'rl24_transmissions.php');
$page->breadcrumbs->add(__('View Transmission'));

// Access check
if (!isActionAccessible($guid, $connection2, '/modules/RL24Submission/rl24_transmissions.php')) {
    $page->addError(__('You do not have access to this action.'));
} else {
    // Get transmission ID from URL
    $gibbonRL24TransmissionID = $_GET['gibbonRL24TransmissionID'] ?? '';

    if (empty($gibbonRL24TransmissionID)) {
        $page->addError(__('You have not specified one or more required parameters.'));
        return;
    }

    // Get gateways via DI container
    $transmissionGateway = $container->get(RL24TransmissionGateway::class);
    $slipGateway = $container->get(RL24SlipGateway::class);

    // Get transmission details
    $transmission = $transmissionGateway->getTransmissionByID($gibbonRL24TransmissionID);

    if (empty($transmission)) {
        $page->addError(__('The specified record does not exist.'));
        return;
    }

    // Get slip summary statistics
    $slipStats = $slipGateway->getSlipSummaryByTransmission($gibbonRL24TransmissionID);

    // Status badge classes
    $statusClasses = [
        'Draft' => 'tag dull',
        'Generated' => 'tag message',
        'Validated' => 'tag',
        'Submitted' => 'tag warning',
        'Accepted' => 'tag success',
        'Rejected' => 'tag error',
        'Cancelled' => 'tag dull',
    ];

    // Transmission header information
    echo '<div class="bg-white border border-gray-200 rounded-lg p-6 mb-6">';
    echo '<div class="flex justify-between items-start">';

    // Left side - main info
    echo '<div>';
    echo '<h2 class="text-2xl font-bold text-gray-800 mb-2">' . __('Transmission') . ' ';
    echo '<span class="' . ($statusClasses[$transmission['status']] ?? 'tag dull') . '">' . htmlspecialchars($transmission['status']) . '</span>';
    echo '</h2>';

    if (!empty($transmission['fileName'])) {
        echo '<p class="text-lg"><code class="bg-gray-100 px-2 py-1 rounded">' . htmlspecialchars($transmission['fileName']) . '</code></p>';
    }
    echo '</div>';

    // Right side - action buttons
    echo '<div class="flex gap-2">';

    // Download XML button (if file exists and transmission is in appropriate status)
    if (!empty($transmission['fileName']) && in_array($transmission['status'], ['Generated', 'Validated', 'Submitted', 'Accepted'])) {
        echo '<a href="' . $session->get('absoluteURL') . '/modules/RL24Submission/rl24_transmissions_download.php?gibbonRL24TransmissionID=' . $gibbonRL24TransmissionID . '" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium">';
        echo '<span class="mr-1">&#x21E9;</span> ' . __('Download XML');
        echo '</a>';
    }

    // Mark as Submitted button (only for validated transmissions)
    if ($transmission['status'] === 'Validated') {
        echo '<a href="' . $session->get('absoluteURL') . '/modules/RL24Submission/rl24_transmissions_submitProcess.php?gibbonRL24TransmissionID=' . $gibbonRL24TransmissionID . '" class="bg-yellow-500 hover:bg-yellow-600 text-white px-4 py-2 rounded-lg text-sm font-medium" onclick="return confirm(\'' . __('Are you sure you want to mark this transmission as submitted to Revenu Quebec?') . '\');">';
        echo __('Mark as Submitted');
        echo '</a>';
    }

    // Cancel button (only for draft/generated)
    if (in_array($transmission['status'], ['Draft', 'Generated'])) {
        echo '<a href="' . $session->get('absoluteURL') . '/modules/RL24Submission/rl24_transmissions_cancelProcess.php?gibbonRL24TransmissionID=' . $gibbonRL24TransmissionID . '" class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg text-sm font-medium" onclick="return confirm(\'' . __('Are you sure you want to cancel this transmission?') . '\');">';
        echo __('Cancel');
        echo '</a>';
    }

    echo '</div>';
    echo '</div>';
    echo '</div>';

    // Transmission Details Grid
    echo '<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-6">';

    // Basic Information Card
    echo '<div class="bg-white border border-gray-200 rounded-lg p-4">';
    echo '<h4 class="font-semibold text-gray-800 mb-3 pb-2 border-b">' . __('Transmission Details') . '</h4>';
    echo '<div class="space-y-2 text-sm">';
    echo '<div class="flex justify-between"><span class="text-gray-600">' . __('Tax Year') . ':</span><span class="font-medium">' . htmlspecialchars($transmission['taxYear']) . '</span></div>';
    echo '<div class="flex justify-between"><span class="text-gray-600">' . __('Sequence Number') . ':</span><span class="font-medium">' . str_pad($transmission['sequenceNumber'], 3, '0', STR_PAD_LEFT) . '</span></div>';
    echo '<div class="flex justify-between"><span class="text-gray-600">' . __('Preparer Number') . ':</span><span class="font-medium">' . htmlspecialchars($transmission['preparerNumber'] ?? '-') . '</span></div>';

    // XML Validation status
    echo '<div class="flex justify-between"><span class="text-gray-600">' . __('XML Validated') . ':</span>';
    if ($transmission['xmlValidated'] === 'Y') {
        echo '<span class="tag success">' . __('Yes') . '</span>';
    } elseif ($transmission['xmlValidated'] === 'N') {
        echo '<span class="tag error">' . __('No') . '</span>';
    } else {
        echo '<span class="text-gray-400">-</span>';
    }
    echo '</div>';
    echo '</div>';
    echo '</div>';

    // Provider Information Card
    echo '<div class="bg-white border border-gray-200 rounded-lg p-4">';
    echo '<h4 class="font-semibold text-gray-800 mb-3 pb-2 border-b">' . __('Provider Information') . '</h4>';
    echo '<div class="space-y-2 text-sm">';
    echo '<div class="flex justify-between"><span class="text-gray-600">' . __('Name') . ':</span><span class="font-medium">' . htmlspecialchars($transmission['providerName'] ?? '-') . '</span></div>';

    // Format NEQ for display
    $neq = $transmission['providerNEQ'] ?? '';
    if (!empty($neq) && strlen($neq) === 10) {
        $formattedNEQ = substr($neq, 0, 4) . ' ' . substr($neq, 4, 3) . ' ' . substr($neq, 7, 3);
    } else {
        $formattedNEQ = $neq ?: '-';
    }
    echo '<div class="flex justify-between"><span class="text-gray-600">' . __('NEQ') . ':</span><span class="font-medium">' . htmlspecialchars($formattedNEQ) . '</span></div>';

    if (!empty($transmission['providerAddress'])) {
        echo '<div class="mt-2"><span class="text-gray-600">' . __('Address') . ':</span><br><span class="font-medium text-xs">' . nl2br(htmlspecialchars($transmission['providerAddress'])) . '</span></div>';
    }
    echo '</div>';
    echo '</div>';

    // Summary Totals Card
    echo '<div class="bg-white border border-gray-200 rounded-lg p-4">';
    echo '<h4 class="font-semibold text-gray-800 mb-3 pb-2 border-b">' . __('Summary Totals') . '</h4>';
    echo '<div class="space-y-2 text-sm">';
    echo '<div class="flex justify-between"><span class="text-gray-600">' . __('Total Slips') . ':</span><span class="font-bold text-lg">' . number_format($transmission['totalSlips'] ?? 0) . '</span></div>';
    echo '<div class="flex justify-between"><span class="text-gray-600">' . __('Total Days (Box 10)') . ':</span><span class="font-medium">' . number_format($transmission['totalDays'] ?? 0) . '</span></div>';
    echo '<div class="flex justify-between"><span class="text-gray-600">' . __('Amount Case 11') . ':</span><span class="font-medium">$' . number_format((float) ($transmission['totalAmountCase11'] ?? 0), 2) . '</span></div>';
    echo '<div class="flex justify-between"><span class="text-gray-600">' . __('Amount Case 12') . ':</span><span class="font-bold text-green-600">$' . number_format((float) ($transmission['totalAmountCase12'] ?? 0), 2) . '</span></div>';
    echo '</div>';
    echo '</div>';

    echo '</div>';

    // Submission Information (if submitted)
    if (in_array($transmission['status'], ['Submitted', 'Accepted', 'Rejected'])) {
        echo '<div class="bg-white border border-gray-200 rounded-lg p-4 mb-6">';
        echo '<h4 class="font-semibold text-gray-800 mb-3 pb-2 border-b">' . __('Submission Information') . '</h4>';
        echo '<div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">';

        echo '<div><span class="text-gray-600">' . __('Submission Date') . ':</span><br><span class="font-medium">';
        echo !empty($transmission['submissionDate']) ? Format::date($transmission['submissionDate']) : '-';
        echo '</span></div>';

        echo '<div><span class="text-gray-600">' . __('Submitted By') . ':</span><br><span class="font-medium">';
        if (!empty($transmission['submittedByName']) || !empty($transmission['submittedBySurname'])) {
            echo Format::name('', $transmission['submittedByName'] ?? '', $transmission['submittedBySurname'] ?? '', 'Staff');
        } else {
            echo '-';
        }
        echo '</span></div>';

        echo '<div><span class="text-gray-600">' . __('Confirmation Number') . ':</span><br><span class="font-medium">';
        echo !empty($transmission['confirmationNumber']) ? '<code>' . htmlspecialchars($transmission['confirmationNumber']) . '</code>' : '-';
        echo '</span></div>';

        if ($transmission['status'] === 'Rejected' && !empty($transmission['rejectionReason'])) {
            echo '<div class="col-span-2 md:col-span-4"><span class="text-gray-600">' . __('Rejection Reason') . ':</span><br><span class="font-medium text-red-600">' . htmlspecialchars($transmission['rejectionReason']) . '</span></div>';
        }

        echo '</div>';
        echo '</div>';
    }

    // XML Validation Errors (if any)
    if (!empty($transmission['xmlValidationErrors'])) {
        echo '<div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">';
        echo '<h4 class="font-semibold text-red-800 mb-2">' . __('XML Validation Errors') . '</h4>';
        echo '<pre class="text-sm text-red-700 whitespace-pre-wrap">' . htmlspecialchars($transmission['xmlValidationErrors']) . '</pre>';
        echo '</div>';
    }

    // Notes (if any)
    if (!empty($transmission['notes'])) {
        echo '<div class="bg-gray-50 border border-gray-200 rounded-lg p-4 mb-6">';
        echo '<h4 class="font-semibold text-gray-800 mb-2">' . __('Notes') . '</h4>';
        echo '<p class="text-sm text-gray-600">' . nl2br(htmlspecialchars($transmission['notes'])) . '</p>';
        echo '</div>';
    }

    // Slip Statistics Cards
    echo '<div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">';

    // Total slips
    echo '<div class="bg-indigo-50 border border-indigo-200 rounded-lg p-3 text-center">';
    echo '<div class="text-2xl font-bold text-indigo-600">' . ($slipStats['totalSlips'] ?? 0) . '</div>';
    echo '<div class="text-xs text-indigo-700">' . __('Total Slips') . '</div>';
    echo '</div>';

    // Draft slips
    echo '<div class="bg-gray-50 border border-gray-200 rounded-lg p-3 text-center">';
    echo '<div class="text-2xl font-bold text-gray-600">' . ($slipStats['draftCount'] ?? 0) . '</div>';
    echo '<div class="text-xs text-gray-700">' . __('Draft') . '</div>';
    echo '</div>';

    // Included slips
    echo '<div class="bg-green-50 border border-green-200 rounded-lg p-3 text-center">';
    echo '<div class="text-2xl font-bold text-green-600">' . ($slipStats['includedCount'] ?? 0) . '</div>';
    echo '<div class="text-xs text-green-700">' . __('Included') . '</div>';
    echo '</div>';

    // Amended slips
    echo '<div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3 text-center">';
    echo '<div class="text-2xl font-bold text-yellow-600">' . ($slipStats['amendedCount'] ?? 0) . '</div>';
    echo '<div class="text-xs text-yellow-700">' . __('Amended') . '</div>';
    echo '</div>';

    // Cancelled slips
    echo '<div class="bg-red-50 border border-red-200 rounded-lg p-3 text-center">';
    echo '<div class="text-2xl font-bold text-red-600">' . ($slipStats['cancelledCount'] ?? 0) . '</div>';
    echo '<div class="text-xs text-red-700">' . __('Cancelled') . '</div>';
    echo '</div>';

    echo '</div>';

    // Individual Slips Table
    $criteria = $slipGateway->newQueryCriteria(true)
        ->sortBy(['slipNumber'], 'ASC')
        ->filterBy('status', $_GET['slipStatus'] ?? '')
        ->fromPOST();

    $slips = $slipGateway->querySlips($criteria, $gibbonRL24TransmissionID);

    $table = DataTable::createPaginated('rl24Slips', $criteria);
    $table->setTitle(__('Individual RL-24 Slips'));

    // Slip Number column
    $table->addColumn('slipNumber', __('Slip #'))
        ->width('6%')
        ->format(function ($row) {
            return '<span class="font-mono font-semibold">' . str_pad($row['slipNumber'], 3, '0', STR_PAD_LEFT) . '</span>';
        });

    // Type/Code column
    $table->addColumn('caseACode', __('Type'))
        ->width('8%')
        ->format(function ($row) {
            $codeLabels = [
                'O' => ['label' => __('Original'), 'class' => 'tag'],
                'A' => ['label' => __('Amended'), 'class' => 'tag warning'],
                'D' => ['label' => __('Cancelled'), 'class' => 'tag error'],
            ];
            $info = $codeLabels[$row['caseACode']] ?? ['label' => $row['caseACode'], 'class' => 'tag dull'];
            return '<span class="' . $info['class'] . '">' . $info['label'] . '</span>';
        });

    // Status column
    $table->addColumn('status', __('Status'))
        ->width('10%')
        ->format(function ($row) {
            $statusClasses = [
                'Draft' => 'tag dull',
                'Included' => 'tag success',
                'Amended' => 'tag warning',
                'Cancelled' => 'tag error',
            ];
            $class = $statusClasses[$row['status']] ?? 'tag dull';
            return '<span class="' . $class . '">' . htmlspecialchars($row['status']) . '</span>';
        });

    // Child column
    $table->addColumn('childName', __('Child'))
        ->width('18%')
        ->format(function ($row) {
            $name = htmlspecialchars($row['childFirstName'] . ' ' . $row['childLastName']);
            if (!empty($row['childDateOfBirth'])) {
                $name .= '<br><span class="text-xs text-gray-500">' . __('DOB') . ': ' . Format::date($row['childDateOfBirth']) . '</span>';
            }
            return $name;
        });

    // Parent column
    $table->addColumn('parentName', __('Parent/Recipient'))
        ->width('18%')
        ->format(function ($row) {
            return htmlspecialchars($row['parentFirstName'] . ' ' . $row['parentLastName']);
        });

    // Service Period column
    $table->addColumn('servicePeriod', __('Service Period'))
        ->width('14%')
        ->format(function ($row) {
            if (!empty($row['servicePeriodStart']) && !empty($row['servicePeriodEnd'])) {
                return Format::date($row['servicePeriodStart']) . '<br><span class="text-xs text-gray-500">' . __('to') . '</span><br>' . Format::date($row['servicePeriodEnd']);
            }
            return '-';
        });

    // Days (Box 10) column
    $table->addColumn('totalDays', __('Days'))
        ->width('6%')
        ->format(function ($row) {
            return '<span class="font-medium">' . number_format($row['totalDays'] ?? 0) . '</span>';
        });

    // Amount Case 11 column
    $table->addColumn('case11Amount', __('Box 11'))
        ->width('10%')
        ->format(function ($row) {
            return '$' . number_format((float) ($row['case11Amount'] ?? 0), 2);
        });

    // Amount Case 12 column
    $table->addColumn('case12Amount', __('Box 12'))
        ->width('10%')
        ->format(function ($row) {
            $amount = (float) ($row['case12Amount'] ?? 0);
            return '<span class="font-semibold text-green-600">$' . number_format($amount, 2) . '</span>';
        });

    echo $table->render($slips);

    // Audit Information
    echo '<div class="mt-6 p-4 bg-gray-50 rounded-lg">';
    echo '<h4 class="font-semibold mb-2">' . __('Record Information') . '</h4>';
    echo '<div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm text-gray-600">';

    echo '<div><strong>' . __('Created') . ':</strong><br>' . Format::dateTime($transmission['timestampCreated']);
    if (!empty($transmission['generatedByName']) || !empty($transmission['generatedBySurname'])) {
        echo '<br>' . __('by') . ' ' . Format::name('', $transmission['generatedByName'] ?? '', $transmission['generatedBySurname'] ?? '', 'Staff');
    }
    echo '</div>';

    if (!empty($transmission['timestampModified'])) {
        echo '<div><strong>' . __('Last Modified') . ':</strong><br>' . Format::dateTime($transmission['timestampModified']) . '</div>';
    }

    if (!empty($transmission['xmlFilePath'])) {
        echo '<div><strong>' . __('XML File Path') . ':</strong><br><code class="text-xs">' . htmlspecialchars($transmission['xmlFilePath']) . '</code></div>';
    }

    echo '</div>';
    echo '</div>';

    // Information box
    echo '<div class="mt-4 p-4 bg-blue-50 border border-blue-200 rounded-lg">';
    echo '<h4 class="font-semibold text-blue-800 mb-2">' . __('Slip Type Legend') . '</h4>';
    echo '<ul class="text-sm text-blue-700 list-disc list-inside space-y-1">';
    echo '<li><strong>' . __('Original (O)') . ':</strong> ' . __('First-time slip for this child/tax year.') . '</li>';
    echo '<li><strong>' . __('Amended (A)') . ':</strong> ' . __('Replacement slip correcting a previous submission.') . '</li>';
    echo '<li><strong>' . __('Cancelled (D)') . ':</strong> ' . __('Slip cancelling a previous submission.') . '</li>';
    echo '</ul>';
    echo '<p class="mt-3 text-xs text-blue-600">' . __('Box 10 = Total days of attendance. Box 11 = Eligible childcare expenses. Box 12 = Total amount for tax credit.') . '</p>';
    echo '</div>';
}
