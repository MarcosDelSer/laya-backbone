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
use Gibbon\Domain\System\SettingGateway;

// Module setup - breadcrumbs
$page->breadcrumbs
    ->add(__('Example Module'), 'exampleModule.php')
    ->add(__('Settings'));

// Access check - CRITICAL: Address must be HARD-CODED (never use variables)
if (!isActionAccessible($guid, $connection2, '/modules/ExampleModule/exampleModule_settings.php')) {
    $page->addError(__('You do not have access to this action.'));
} else {
    // Page header
    echo '<h2>' . __('Example Module Settings') . '</h2>';
    echo '<p>' . __('Configure module-wide settings.') . '</p>';

    // Get settings gateway
    $settingGateway = $container->get(SettingGateway::class);

    // Get current settings
    $enableFeature = $settingGateway->getSettingByScope('Example Module', 'enableFeature', true);
    $maxItems = $settingGateway->getSettingByScope('Example Module', 'maxItems', true);

    // Create form
    $form = Form::create('moduleSettings', $session->get('absoluteURL') . '/modules/ExampleModule/exampleModule_settingsProcess.php');

    $form->addHiddenValue('address', $session->get('address'));

    // Enable Feature setting
    $row = $form->addRow();
    $row->addLabel('enableFeature', __('Enable Feature'))
        ->description(__('Enable or disable the example feature functionality.'));
    $row->addYesNo('enableFeature')
        ->selected($enableFeature)
        ->required();

    // Max Items setting
    $row = $form->addRow();
    $row->addLabel('maxItems', __('Maximum Items'))
        ->description(__('Maximum number of items to display per page.'));
    $row->addNumber('maxItems')
        ->setValue($maxItems)
        ->minimum(10)
        ->maximum(200)
        ->required();

    // Information section
    $form->addRow()->addHeading(__('Information'));

    $infoText = __('These settings control the behavior of the Example Module. Changes will take effect immediately for all users.');
    $row = $form->addRow();
    $row->addContent($infoText)->wrap('<div class="bg-blue-50 border border-blue-200 rounded p-4">', '</div>');

    // Submit buttons
    $row = $form->addRow();
    $row->addFooter();
    $row->addSubmit();

    echo $form->getOutput();
}
