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

/**
 * Enhanced Finance Module - Contract List View
 *
 * Displays paginated contract list with filtering by status, family, date range.
 * Provides links to view, edit, and add contracts.
 *
 * @package    Gibbon\Module\EnhancedFinance
 * @author     LAYA
 * @license    GPL-3.0
 */

use Gibbon\Forms\Form;
use Gibbon\Services\Format;
use Gibbon\Tables\DataTable;
use Gibbon\Domain\System\SettingGateway;
use Gibbon\Domain\User\FamilyGateway;
use Gibbon\Module\EnhancedFinance\Domain\ContractGateway;

// Access check
if (isActionAccessible($guid, $connection2, '/modules/EnhancedFinance/finance_contracts.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Proceed!
    $page->breadcrumbs
        ->add(__('Enhanced Finance'), 'finance.php')
        ->add(__('Manage Contracts'));

    // Return messages
    $page->return->addReturns([
        'success0' => __('Your request was completed successfully.'),
        'success1' => __('Contract created successfully.'),
        'success2' => __('Contract updated successfully.'),
        'success3' => __('Contract deleted successfully.'),
        'error1' => __('There was an error creating the contract.'),
        'error2' => __('The selected contract could not be found.'),
        'error3' => __('Required parameters were not provided.'),
        'error4' => __('There was an error updating the contract.'),
        'error5' => __('There was an error deleting the contract.'),
    ]);

    // Description
    echo '<p>';
    echo __('This section allows you to view, create, and manage childcare contracts. Contracts define the terms of service between families and the childcare facility, including weekly rates, days per week, and contract duration. Use the filters below to find specific contracts by status, family, or date range.');
    echo '</p>';

    // Get current school year
    $gibbonSchoolYearID = $_REQUEST['gibbonSchoolYearID'] ?? $session->get('gibbonSchoolYearID');

    if (!empty($gibbonSchoolYearID)) {
        // School year navigation
        $page->navigator->addSchoolYearNavigation($gibbonSchoolYearID);

        // Request parameters for filters
        $request = [
            'gibbonSchoolYearID' => $gibbonSchoolYearID,
            'status'             => $_GET['status'] ?? '',
            'gibbonFamilyID'     => $_GET['gibbonFamilyID'] ?? '',
            'startDateFrom'      => $_GET['startDateFrom'] ?? '',
            'startDateTo'        => $_GET['startDateTo'] ?? '',
        ];

        // Default to Active status if no filter set
        if (empty($_POST) && !isset($_GET['status'])) {
            $request['status'] = 'Active';
        }

        // Get gateways
        $contractGateway = $container->get(ContractGateway::class);
        $familyGateway = $container->get(FamilyGateway::class);
        $settingGateway = $container->get(SettingGateway::class);

        // Get currency from settings
        $currency = $settingGateway->getSettingByScope('System', 'currency') ?: 'CAD';

        // Build filter form
        $form = Form::create('contractFilters', $session->get('absoluteURL') . '/index.php', 'get');
        $form->setTitle(__('Filters'));
        $form->setClass('noIntBorder w-full');

        $form->addHiddenValue('q', '/modules/EnhancedFinance/finance_contracts.php');
        $form->addHiddenValue('gibbonSchoolYearID', $gibbonSchoolYearID);

        // Status filter
        $statusOptions = [
            ''           => __('All'),
            'Active'     => __('Active'),
            'Pending'    => __('Pending'),
            'Suspended'  => __('Suspended'),
            'Cancelled'  => __('Cancelled'),
            'Expired'    => __('Expired'),
            'Expiring'   => __('Expiring Soon (30 days)'),
        ];

        $row = $form->addRow();
            $row->addLabel('status', __('Status'));
            $row->addSelect('status')
                ->fromArray($statusOptions)
                ->selected($request['status']);

        // Family filter
        $families = $familyGateway->selectFamiliesWithActiveStudents($gibbonSchoolYearID)->fetchAll();
        $familyOptions = array_column($families, 'name', 'gibbonFamilyID');

        $row = $form->addRow();
            $row->addLabel('gibbonFamilyID', __('Family'));
            $row->addSelect('gibbonFamilyID')
                ->fromArray($familyOptions)
                ->placeholder()
                ->selected($request['gibbonFamilyID']);

        // Date range filters
        $row = $form->addRow();
            $row->addLabel('startDateFrom', __('Start Date From'));
            $row->addDate('startDateFrom')
                ->setValue($request['startDateFrom']);

        $row = $form->addRow();
            $row->addLabel('startDateTo', __('Start Date To'));
            $row->addDate('startDateTo')
                ->setValue($request['startDateTo']);

        $row = $form->addRow();
            $row->addSearchSubmit($session, __('Clear Filters'), ['gibbonSchoolYearID']);

        echo $form->getOutput();

        // Contract list section
        echo '<h3>';
        echo __('Contracts');
        echo '</h3>';

        // Build query criteria
        $criteria = $contractGateway->newQueryCriteria(true)
            ->sortBy(['defaultSortOrder', 'startDate', 'contractName'])
            ->filterBy('status', $request['status'])
            ->filterBy('family', $request['gibbonFamilyID'])
            ->filterBy('startDateFrom', $request['startDateFrom'])
            ->filterBy('startDateTo', $request['startDateTo'])
            ->fromPOST();

        // Execute query
        $contracts = $contractGateway->queryContractsByYear($criteria, $gibbonSchoolYearID);

        // Create DataTable
        $table = DataTable::createPaginated('contracts', $criteria);

        // Add contract button
        $table->addHeaderAction('add', __('Add'))
            ->setURL('/modules/EnhancedFinance/finance_contract_add.php')
            ->addParams($request)
            ->displayLabel();

        // Row modifier for status highlighting
        $table->modifyRows(function ($contract, $row) {
            // Highlight contracts expiring soon
            if ($contract['status'] == 'Active' && !empty($contract['endDate'])) {
                $daysUntilExpiry = (strtotime($contract['endDate']) - time()) / 86400;
                if ($daysUntilExpiry < 0) {
                    $row->addClass('error');
                } else if ($daysUntilExpiry <= 30) {
                    $row->addClass('warning');
                }
            }
            // Highlight active contracts
            if ($contract['status'] == 'Active') {
                $row->addClass('current');
            }
            // Suspended contracts
            else if ($contract['status'] == 'Suspended') {
                $row->addClass('warning');
            }
            // Cancelled or expired
            else if (in_array($contract['status'], ['Cancelled', 'Expired'])) {
                $row->addClass('dull');
            }
            return $row;
        });

        // Filter options for quick access
        $table->addMetaData('filterOptions', [
            'status:Active'    => __('Status') . ': ' . __('Active'),
            'status:Pending'   => __('Status') . ': ' . __('Pending'),
            'status:Suspended' => __('Status') . ': ' . __('Suspended'),
            'status:Cancelled' => __('Status') . ': ' . __('Cancelled'),
            'status:Expired'   => __('Status') . ': ' . __('Expired'),
            'status:Expiring'  => __('Status') . ': ' . __('Expiring Soon'),
        ]);

        // Column: Contract Name
        $table->addColumn('contractName', __('Contract'))
            ->sortable(['contractName'])
            ->width('15%')
            ->format(function ($contract) {
                return '<b>' . htmlspecialchars($contract['contractName']) . '</b>';
            });

        // Column: Child / Family
        $table->addColumn('child', __('Child'))
            ->description(__('Family'))
            ->sortable(['childSurname', 'childPreferredName'])
            ->format(function ($contract) {
                $output = '<b>' . Format::name('', $contract['childPreferredName'], $contract['childSurname'], 'Student', true) . '</b>';
                $output .= '<br/><span class="text-xs italic">' . htmlspecialchars($contract['familyName'] ?? '') . '</span>';
                return $output;
            });

        // Column: Contract Type
        $table->addColumn('contractType', __('Type'))
            ->sortable(['contractType'])
            ->width('10%')
            ->format(function ($contract) {
                $type = $contract['contractType'] ?? '-';
                $colors = [
                    'Full-time'  => 'bg-green-100 text-green-800',
                    'Part-time'  => 'bg-blue-100 text-blue-800',
                    'Drop-in'    => 'bg-yellow-100 text-yellow-800',
                    'Summer'     => 'bg-orange-100 text-orange-800',
                    'After-school' => 'bg-purple-100 text-purple-800',
                ];
                $colorClass = $colors[$type] ?? 'bg-gray-100 text-gray-800';
                return '<span class="text-xs px-2 py-1 rounded ' . $colorClass . '">' . __($type) . '</span>';
            });

        // Column: Start Date / End Date
        $table->addColumn('startDate', __('Start Date'))
            ->description(__('End Date'))
            ->sortable(['startDate'])
            ->format(function ($contract) {
                $output = Format::date($contract['startDate']);
                if (!empty($contract['endDate'])) {
                    $output .= '<br/><span class="text-xs italic">' . Format::date($contract['endDate']) . '</span>';
                } else {
                    $output .= '<br/><span class="text-xs italic text-gray-500">' . __('Ongoing') . '</span>';
                }
                return $output;
            });

        // Column: Billing
        $table->addColumn('amount', __('Weekly Rate') . ' <small><i>(' . $currency . ')</i></small>')
            ->description(__('Days/Week'))
            ->notSortable()
            ->format(function ($contract) {
                $output = Format::currency($contract['amount'] ?? 0);
                $daysPerWeek = $contract['billingFrequency'] ?? '-';
                $output .= '<br/><span class="text-xs italic">' . $daysPerWeek . ' ' . __('days') . '</span>';
                return $output;
            });

        // Column: Status
        $table->addColumn('status', __('Status'))
            ->sortable(['status'])
            ->format(function ($contract) {
                $status = $contract['status'];
                $class = '';

                // Check for expiring soon
                if ($status == 'Active' && !empty($contract['endDate'])) {
                    $daysUntilExpiry = (strtotime($contract['endDate']) - time()) / 86400;
                    if ($daysUntilExpiry < 0) {
                        $status = __('Expired');
                        $class = 'text-red-600 font-semibold';
                    } else if ($daysUntilExpiry <= 30) {
                        $status = __('Expiring');
                        $class = 'text-orange-600 font-semibold';
                    } else {
                        $class = 'text-green-600';
                    }
                } else if ($status == 'Active') {
                    $class = 'text-green-600';
                } else if ($status == 'Pending') {
                    $class = 'text-blue-600';
                } else if ($status == 'Suspended') {
                    $class = 'text-orange-600';
                } else if (in_array($status, ['Cancelled', 'Expired'])) {
                    $class = 'text-gray-500';
                }

                return '<span class="' . $class . '">' . __($status) . '</span>';
            });

        // Expandable notes column
        $table->addExpandableColumn('notes');

        // Actions column
        $table->addActionColumn()
            ->addParam('gibbonEnhancedFinanceContractID')
            ->addParams($request)
            ->format(function ($contract, $actions) {
                // View action - always available
                $actions->addAction('view', __('View'))
                    ->setURL('/modules/EnhancedFinance/finance_contract_view.php');

                // Edit action - not for cancelled or expired
                if (!in_array($contract['status'], ['Cancelled', 'Expired'])) {
                    $actions->addAction('edit', __('Edit'))
                        ->setURL('/modules/EnhancedFinance/finance_contract_edit.php');
                }

                // Create invoice from contract - for active contracts
                if ($contract['status'] == 'Active') {
                    $actions->addAction('invoice', __('Create Invoice'))
                        ->setURL('/modules/EnhancedFinance/finance_invoice_add.php')
                        ->setIcon('dollar')
                        ->addParam('gibbonPersonID', $contract['gibbonPersonID'])
                        ->addParam('gibbonFamilyID', $contract['gibbonFamilyID']);
                }

                // Delete action - only for pending contracts
                if ($contract['status'] == 'Pending') {
                    $actions->addAction('delete', __('Delete'))
                        ->setURL('/modules/EnhancedFinance/finance_contract_delete.php');
                }
            });

        echo $table->render($contracts);

        // Summary statistics
        $summary = $contractGateway->selectContractSummaryByYear($gibbonSchoolYearID);

        if (!empty($summary) && $summary['totalContracts'] > 0) {
            echo '<div class="mt-6 p-4 bg-gray-50 border rounded-lg">';
            echo '<h4 class="font-semibold mb-3">' . __('Contract Summary for School Year') . '</h4>';
            echo '<div class="grid grid-cols-2 md:grid-cols-5 gap-4">';

            // Total Contracts
            echo '<div class="text-center">';
            echo '<div class="text-sm text-gray-500">' . __('Total Contracts') . '</div>';
            echo '<div class="text-xl font-semibold text-blue-600">' . ($summary['totalContracts'] ?? 0) . '</div>';
            echo '</div>';

            // Active
            echo '<div class="text-center">';
            echo '<div class="text-sm text-gray-500">' . __('Active') . '</div>';
            echo '<div class="text-xl font-semibold text-green-600">' . ($summary['activeCount'] ?? 0) . '</div>';
            echo '</div>';

            // Pending
            echo '<div class="text-center">';
            echo '<div class="text-sm text-gray-500">' . __('Pending') . '</div>';
            echo '<div class="text-xl font-semibold text-blue-600">' . ($summary['pendingCount'] ?? 0) . '</div>';
            echo '</div>';

            // Suspended
            echo '<div class="text-center">';
            echo '<div class="text-sm text-gray-500">' . __('Suspended') . '</div>';
            echo '<div class="text-xl font-semibold text-orange-600">' . ($summary['suspendedCount'] ?? 0) . '</div>';
            echo '</div>';

            // Needs Renewal
            echo '<div class="text-center">';
            echo '<div class="text-sm text-gray-500">' . __('Needs Renewal') . '</div>';
            echo '<div class="text-xl font-semibold text-red-600">' . ($summary['needsRenewalCount'] ?? 0) . '</div>';
            echo '</div>';

            echo '</div>'; // End grid

            // Total Active Value
            if (!empty($summary['activeValue'])) {
                echo '<div class="mt-4 pt-3 border-t text-center">';
                echo '<span class="text-sm text-gray-500">' . __('Total Active Contract Value (Weekly)') . ': </span>';
                echo '<span class="text-lg font-semibold text-green-600">' . Format::currency($summary['activeValue']) . '</span>';
                echo '</div>';
            }

            echo '</div>'; // End summary box
        }

        // Information notice about contracts
        echo '<div class="mt-4 p-4 bg-blue-50 border border-blue-200 rounded-lg">';
        echo '<h4 class="font-semibold text-blue-800 mb-2">' . __('About Contracts') . '</h4>';
        echo '<ul class="list-disc list-inside text-blue-700 space-y-1">';
        echo '<li>' . __('Contracts define the service agreement between families and your childcare facility.') . '</li>';
        echo '<li>' . __('Active contracts can be used to generate invoices automatically.') . '</li>';
        echo '<li>' . __('Contracts expiring within 30 days are highlighted for renewal follow-up.') . '</li>';
        echo '<li>' . __('Suspended contracts can be reactivated when payments are resolved.') . '</li>';
        echo '</ul>';
        echo '</div>';

    } else {
        $page->addError(__('School year has not been specified.'));
    }
}
