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
use Gibbon\Module\StaffManagement\Domain\ScheduleGateway;
use Gibbon\Module\StaffManagement\Domain\AuditLogGateway;

// Module setup - breadcrumbs
$page->breadcrumbs
    ->add(__('Staff Management'), 'staffManagement.php')
    ->add(__('Shift Templates'));

// Access check
if (!isActionAccessible($guid, $connection2, '/modules/StaffManagement/staffManagement_shiftTemplates.php')) {
    $page->addError(__('You do not have access to this action.'));
} else {
    // Get session info
    $gibbonPersonID = $session->get('gibbonPersonID');

    // Get gateways via DI container
    $scheduleGateway = $container->get(ScheduleGateway::class);
    $auditLogGateway = $container->get(AuditLogGateway::class);

    // Handle form submissions (add/edit/delete)
    $mode = $_POST['mode'] ?? '';
    $success = false;

    if ($mode === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        // Add new shift template
        $data = [
            'name' => trim($_POST['name'] ?? ''),
            'description' => !empty($_POST['description']) ? trim($_POST['description']) : null,
            'startTime' => $_POST['startTime'] ?? '08:00:00',
            'endTime' => $_POST['endTime'] ?? '17:00:00',
            'breakDuration' => intval($_POST['breakDuration'] ?? 30),
            'color' => !empty($_POST['color']) ? $_POST['color'] : null,
            'active' => isset($_POST['active']) ? 'Y' : 'N',
            'createdByID' => $gibbonPersonID,
        ];

        if (empty($data['name'])) {
            $page->addError(__('Please enter a template name.'));
        } elseif ($data['startTime'] >= $data['endTime']) {
            $page->addError(__('End time must be after start time.'));
        } else {
            $insertID = $scheduleGateway->insertShiftTemplate($data);
            if ($insertID) {
                $auditLogGateway->logInsert('gibbonStaffShiftTemplate', $insertID, $gibbonPersonID);
                $page->addSuccess(__('Shift template added successfully.'));
                $success = true;
            } else {
                $page->addError(__('Failed to add shift template.'));
            }
        }
    } elseif ($mode === 'edit' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        // Edit existing shift template
        $gibbonStaffShiftTemplateID = $_POST['gibbonStaffShiftTemplateID'] ?? '';
        if (!empty($gibbonStaffShiftTemplateID)) {
            $existing = $scheduleGateway->getShiftTemplateByID($gibbonStaffShiftTemplateID);

            $data = [
                'name' => trim($_POST['name'] ?? ''),
                'description' => !empty($_POST['description']) ? trim($_POST['description']) : null,
                'startTime' => $_POST['startTime'] ?? '08:00:00',
                'endTime' => $_POST['endTime'] ?? '17:00:00',
                'breakDuration' => intval($_POST['breakDuration'] ?? 30),
                'color' => !empty($_POST['color']) ? $_POST['color'] : null,
                'active' => isset($_POST['active']) ? 'Y' : 'N',
            ];

            if (empty($data['name'])) {
                $page->addError(__('Please enter a template name.'));
            } elseif ($data['startTime'] >= $data['endTime']) {
                $page->addError(__('End time must be after start time.'));
            } else {
                $updated = $scheduleGateway->updateShiftTemplate($gibbonStaffShiftTemplateID, $data);
                if ($updated) {
                    $auditLogGateway->logUpdate('gibbonStaffShiftTemplate', $gibbonStaffShiftTemplateID, $gibbonPersonID, $existing, $data);
                    $page->addSuccess(__('Shift template updated successfully.'));
                    $success = true;
                } else {
                    $page->addError(__('Failed to update shift template.'));
                }
            }
        }
    } elseif ($mode === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        // Delete shift template
        $gibbonStaffShiftTemplateID = $_POST['gibbonStaffShiftTemplateID'] ?? '';
        if (!empty($gibbonStaffShiftTemplateID)) {
            $existing = $scheduleGateway->getShiftTemplateByID($gibbonStaffShiftTemplateID);
            $deleted = $scheduleGateway->deleteShiftTemplate($gibbonStaffShiftTemplateID);
            if ($deleted) {
                $auditLogGateway->logDelete('gibbonStaffShiftTemplate', $gibbonStaffShiftTemplateID, $gibbonPersonID, $existing);
                $page->addSuccess(__('Shift template deleted successfully.'));
                $success = true;
            } else {
                $page->addError(__('Failed to delete shift template.'));
            }
        }
    }

    // Page header
    echo '<h2>' . __('Shift Templates') . '</h2>';
    echo '<p class="text-gray-600 mb-4">' . __('Manage reusable shift templates for quick scheduling. Templates define standard start/end times, break durations, and visual colors for easy identification.') . '</p>';

    // Filter form for active status
    $filterActive = $_GET['active'] ?? '';

    $form = Form::create('templateFilter', $session->get('absoluteURL') . '/index.php');
    $form->setMethod('GET');
    $form->addHiddenValue('q', '/modules/StaffManagement/staffManagement_shiftTemplates.php');

    $row = $form->addRow();
    $row->addLabel('active', __('Status'));
    $row->addSelect('active')
        ->fromArray([
            '' => __('All Templates'),
            'Y' => __('Active Only'),
            'N' => __('Inactive Only'),
        ])
        ->selected($filterActive);

    $row = $form->addRow();
    $row->addSearchSubmit($session, __('Clear Filter'));

    echo $form->getOutput();

    // Add Template Button
    echo '<div class="mb-4">';
    echo '<button onclick="openAddModal()" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">' . __('Add Shift Template') . '</button>';
    echo '</div>';

    // Build query criteria
    $criteria = $scheduleGateway->newQueryCriteria()
        ->sortBy(['name'])
        ->fromPOST();

    if (!empty($filterActive)) {
        $criteria->filterBy('active', $filterActive);
    }

    // Get shift templates
    $templates = $scheduleGateway->queryShiftTemplates($criteria);

    // Build DataTable
    $table = DataTable::createPaginated('shiftTemplates', $criteria);
    $table->setTitle(__('Shift Templates'));

    // Add columns
    $table->addColumn('color', __('Color'))
        ->notSortable()
        ->format(function ($row) {
            if (!empty($row['color'])) {
                return '<div class="w-6 h-6 rounded" style="background-color: ' . htmlspecialchars($row['color']) . ';"></div>';
            }
            return '<div class="w-6 h-6 rounded bg-gray-200"></div>';
        });

    $table->addColumn('name', __('Name'))
        ->sortable()
        ->format(function ($row) {
            $output = '<span class="font-semibold">' . htmlspecialchars($row['name']) . '</span>';
            if (!empty($row['description'])) {
                $output .= '<br><span class="text-sm text-gray-500">' . htmlspecialchars($row['description']) . '</span>';
            }
            return $output;
        });

    $table->addColumn('time', __('Time'))
        ->sortable(['startTime'])
        ->format(function ($row) {
            return Format::time($row['startTime']) . ' - ' . Format::time($row['endTime']);
        });

    $table->addColumn('duration', __('Duration'))
        ->notSortable()
        ->format(function ($row) {
            $start = strtotime($row['startTime']);
            $end = strtotime($row['endTime']);
            $totalMinutes = ($end - $start) / 60;
            $workMinutes = $totalMinutes - intval($row['breakDuration']);
            $hours = floor($workMinutes / 60);
            $mins = $workMinutes % 60;
            return sprintf('%dh %dm', $hours, $mins) . ' <span class="text-xs text-gray-500">(' . __('excl. break') . ')</span>';
        });

    $table->addColumn('breakDuration', __('Break'))
        ->sortable()
        ->format(function ($row) {
            return intval($row['breakDuration']) . ' ' . __('min');
        });

    $table->addColumn('active', __('Status'))
        ->sortable()
        ->format(function ($row) {
            if ($row['active'] === 'Y') {
                return '<span class="bg-green-100 text-green-800 text-xs px-2 py-1 rounded">' . __('Active') . '</span>';
            }
            return '<span class="bg-gray-100 text-gray-600 text-xs px-2 py-1 rounded">' . __('Inactive') . '</span>';
        });

    $table->addColumn('createdBy', __('Created By'))
        ->notSortable()
        ->format(function ($row) {
            if (!empty($row['createdByName'])) {
                return Format::name('', $row['createdByName'], $row['createdBySurname'], 'Staff', false, true);
            }
            return '-';
        });

    // Add action column
    $table->addActionColumn()
        ->addParam('gibbonStaffShiftTemplateID')
        ->format(function ($row, $actions) {
            $actions->addAction('edit', __('Edit'))
                ->setIcon('config')
                ->onClick('openEditModal(' . $row['gibbonStaffShiftTemplateID'] . '); return false;');

            $actions->addAction('delete', __('Delete'))
                ->setIcon('garbage')
                ->onClick('confirmDelete(' . $row['gibbonStaffShiftTemplateID'] . ', \'' . htmlspecialchars(addslashes($row['name'])) . '\'); return false;');
        });

    // Output table
    if ($templates->count() > 0) {
        echo $table->render($templates);
    } else {
        echo '<div class="bg-gray-50 rounded-lg p-4 text-center text-gray-500">';
        echo __('No shift templates found. Create one to get started.');
        echo '</div>';
    }

    // Link back to schedule
    echo '<div class="mt-4">';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/StaffManagement/staffManagement_schedule.php" class="text-blue-600 hover:underline">&larr; ' . __('Back to Schedule') . '</a>';
    echo '</div>';

    // Prepare templates JSON for JavaScript
    $templatesJSON = [];
    foreach ($templates as $template) {
        $templatesJSON[$template['gibbonStaffShiftTemplateID']] = $template;
    }

    // Add Modal
    echo '<div id="addModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50">';
    echo '<div class="bg-white rounded-lg shadow-xl max-w-lg w-full mx-4 max-h-[90vh] overflow-y-auto">';
    echo '<div class="p-4 border-b flex justify-between items-center">';
    echo '<h3 class="text-lg font-semibold">' . __('Add Shift Template') . '</h3>';
    echo '<button onclick="closeAddModal()" class="text-gray-500 hover:text-gray-700">&times;</button>';
    echo '</div>';
    echo '<form method="POST" action="' . $session->get('absoluteURL') . '/index.php?q=/modules/StaffManagement/staffManagement_shiftTemplates.php" class="p-4">';
    echo '<input type="hidden" name="mode" value="add">';

    // Template name
    echo '<div class="mb-4">';
    echo '<label class="block text-sm font-medium mb-1">' . __('Template Name') . ' *</label>';
    echo '<input type="text" name="name" class="w-full border rounded px-3 py-2" placeholder="' . __('e.g., Morning Shift, Full Day') . '" required>';
    echo '</div>';

    // Description
    echo '<div class="mb-4">';
    echo '<label class="block text-sm font-medium mb-1">' . __('Description') . '</label>';
    echo '<textarea name="description" class="w-full border rounded px-3 py-2" rows="2" placeholder="' . __('Optional description...') . '"></textarea>';
    echo '</div>';

    // Times
    echo '<div class="grid grid-cols-2 gap-4 mb-4">';
    echo '<div>';
    echo '<label class="block text-sm font-medium mb-1">' . __('Start Time') . ' *</label>';
    echo '<input type="time" name="startTime" class="w-full border rounded px-3 py-2" value="08:00" required>';
    echo '</div>';
    echo '<div>';
    echo '<label class="block text-sm font-medium mb-1">' . __('End Time') . ' *</label>';
    echo '<input type="time" name="endTime" class="w-full border rounded px-3 py-2" value="17:00" required>';
    echo '</div>';
    echo '</div>';

    // Break duration
    echo '<div class="mb-4">';
    echo '<label class="block text-sm font-medium mb-1">' . __('Break Duration (minutes)') . '</label>';
    echo '<input type="number" name="breakDuration" class="w-full border rounded px-3 py-2" value="30" min="0" max="120">';
    echo '</div>';

    // Color
    echo '<div class="mb-4">';
    echo '<label class="block text-sm font-medium mb-1">' . __('Color') . '</label>';
    echo '<div class="flex items-center gap-2">';
    echo '<input type="color" name="color" class="w-12 h-10 border rounded cursor-pointer" value="#3B82F6">';
    echo '<span class="text-sm text-gray-500">' . __('Select a color for visual identification on schedules') . '</span>';
    echo '</div>';
    echo '</div>';

    // Active checkbox
    echo '<div class="mb-4">';
    echo '<label class="inline-flex items-center">';
    echo '<input type="checkbox" name="active" class="mr-2" checked>';
    echo '<span class="text-sm font-medium">' . __('Active') . '</span>';
    echo '</label>';
    echo '<p class="text-xs text-gray-500 mt-1">' . __('Inactive templates will not appear in the schedule dropdown.') . '</p>';
    echo '</div>';

    echo '<div class="flex justify-end gap-2">';
    echo '<button type="button" onclick="closeAddModal()" class="px-4 py-2 border rounded hover:bg-gray-100">' . __('Cancel') . '</button>';
    echo '<button type="submit" class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600">' . __('Add Template') . '</button>';
    echo '</div>';
    echo '</form>';
    echo '</div>';
    echo '</div>';

    // Edit Modal
    echo '<div id="editModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50">';
    echo '<div class="bg-white rounded-lg shadow-xl max-w-lg w-full mx-4 max-h-[90vh] overflow-y-auto">';
    echo '<div class="p-4 border-b flex justify-between items-center">';
    echo '<h3 class="text-lg font-semibold">' . __('Edit Shift Template') . '</h3>';
    echo '<button onclick="closeEditModal()" class="text-gray-500 hover:text-gray-700">&times;</button>';
    echo '</div>';
    echo '<div id="editModalContent" class="p-4">';
    echo '<div class="text-center py-4">' . __('Loading...') . '</div>';
    echo '</div>';
    echo '</div>';
    echo '</div>';

    // Delete Confirmation Modal
    echo '<div id="deleteModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50">';
    echo '<div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4">';
    echo '<div class="p-4 border-b">';
    echo '<h3 class="text-lg font-semibold text-red-600">' . __('Confirm Delete') . '</h3>';
    echo '</div>';
    echo '<div class="p-4">';
    echo '<p class="mb-4">' . __('Are you sure you want to delete the shift template:') . ' <strong id="deleteTemplateName"></strong>?</p>';
    echo '<p class="text-sm text-yellow-600 mb-4">' . __('Note: This will not affect existing schedules that use this template.') . '</p>';
    echo '<form method="POST" action="' . $session->get('absoluteURL') . '/index.php?q=/modules/StaffManagement/staffManagement_shiftTemplates.php">';
    echo '<input type="hidden" name="mode" value="delete">';
    echo '<input type="hidden" name="gibbonStaffShiftTemplateID" id="deleteTemplateID">';
    echo '<div class="flex justify-end gap-2">';
    echo '<button type="button" onclick="closeDeleteModal()" class="px-4 py-2 border rounded hover:bg-gray-100">' . __('Cancel') . '</button>';
    echo '<button type="submit" class="px-4 py-2 bg-red-500 text-white rounded hover:bg-red-600">' . __('Delete') . '</button>';
    echo '</div>';
    echo '</form>';
    echo '</div>';
    echo '</div>';
    echo '</div>';

    // JavaScript
    ?>
    <script>
    var templateData = <?php echo json_encode($templatesJSON); ?>;

    function openAddModal() {
        document.getElementById('addModal').classList.remove('hidden');
    }

    function closeAddModal() {
        document.getElementById('addModal').classList.add('hidden');
    }

    function openEditModal(templateID) {
        document.getElementById('editModal').classList.remove('hidden');
        loadEditForm(templateID);
    }

    function closeEditModal() {
        document.getElementById('editModal').classList.add('hidden');
    }

    function confirmDelete(templateID, templateName) {
        document.getElementById('deleteTemplateID').value = templateID;
        document.getElementById('deleteTemplateName').textContent = templateName;
        document.getElementById('deleteModal').classList.remove('hidden');
    }

    function closeDeleteModal() {
        document.getElementById('deleteModal').classList.add('hidden');
    }

    function loadEditForm(templateID) {
        var contentDiv = document.getElementById('editModalContent');
        var template = templateData[templateID];

        if (!template) {
            contentDiv.innerHTML = '<div class="text-center py-4 text-red-600"><?php echo __('Template not found'); ?></div>';
            return;
        }

        var formHTML = '<form method="POST" action="<?php echo $session->get('absoluteURL'); ?>/index.php?q=/modules/StaffManagement/staffManagement_shiftTemplates.php">';
        formHTML += '<input type="hidden" name="mode" value="edit">';
        formHTML += '<input type="hidden" name="gibbonStaffShiftTemplateID" value="' + templateID + '">';

        // Template name
        formHTML += '<div class="mb-4">';
        formHTML += '<label class="block text-sm font-medium mb-1"><?php echo __('Template Name'); ?> *</label>';
        formHTML += '<input type="text" name="name" class="w-full border rounded px-3 py-2" value="' + escapeHtml(template.name) + '" required>';
        formHTML += '</div>';

        // Description
        formHTML += '<div class="mb-4">';
        formHTML += '<label class="block text-sm font-medium mb-1"><?php echo __('Description'); ?></label>';
        formHTML += '<textarea name="description" class="w-full border rounded px-3 py-2" rows="2">' + escapeHtml(template.description || '') + '</textarea>';
        formHTML += '</div>';

        // Times
        formHTML += '<div class="grid grid-cols-2 gap-4 mb-4">';
        formHTML += '<div>';
        formHTML += '<label class="block text-sm font-medium mb-1"><?php echo __('Start Time'); ?> *</label>';
        formHTML += '<input type="time" name="startTime" class="w-full border rounded px-3 py-2" value="' + template.startTime.substring(0, 5) + '" required>';
        formHTML += '</div>';
        formHTML += '<div>';
        formHTML += '<label class="block text-sm font-medium mb-1"><?php echo __('End Time'); ?> *</label>';
        formHTML += '<input type="time" name="endTime" class="w-full border rounded px-3 py-2" value="' + template.endTime.substring(0, 5) + '" required>';
        formHTML += '</div>';
        formHTML += '</div>';

        // Break duration
        formHTML += '<div class="mb-4">';
        formHTML += '<label class="block text-sm font-medium mb-1"><?php echo __('Break Duration (minutes)'); ?></label>';
        formHTML += '<input type="number" name="breakDuration" class="w-full border rounded px-3 py-2" value="' + template.breakDuration + '" min="0" max="120">';
        formHTML += '</div>';

        // Color
        formHTML += '<div class="mb-4">';
        formHTML += '<label class="block text-sm font-medium mb-1"><?php echo __('Color'); ?></label>';
        formHTML += '<div class="flex items-center gap-2">';
        formHTML += '<input type="color" name="color" class="w-12 h-10 border rounded cursor-pointer" value="' + (template.color || '#3B82F6') + '">';
        formHTML += '<span class="text-sm text-gray-500"><?php echo __('Select a color for visual identification'); ?></span>';
        formHTML += '</div>';
        formHTML += '</div>';

        // Active checkbox
        formHTML += '<div class="mb-4">';
        formHTML += '<label class="inline-flex items-center">';
        formHTML += '<input type="checkbox" name="active" class="mr-2"' + (template.active === 'Y' ? ' checked' : '') + '>';
        formHTML += '<span class="text-sm font-medium"><?php echo __('Active'); ?></span>';
        formHTML += '</label>';
        formHTML += '<p class="text-xs text-gray-500 mt-1"><?php echo __('Inactive templates will not appear in the schedule dropdown.'); ?></p>';
        formHTML += '</div>';

        formHTML += '<div class="flex justify-end gap-2">';
        formHTML += '<button type="button" onclick="closeEditModal()" class="px-4 py-2 border rounded hover:bg-gray-100"><?php echo __('Cancel'); ?></button>';
        formHTML += '<button type="submit" class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600"><?php echo __('Update Template'); ?></button>';
        formHTML += '</div>';
        formHTML += '</form>';

        contentDiv.innerHTML = formHTML;
    }

    function escapeHtml(text) {
        if (!text) return '';
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(text));
        return div.innerHTML;
    }

    // Close modals on outside click
    document.getElementById('addModal').addEventListener('click', function(e) {
        if (e.target === this) closeAddModal();
    });
    document.getElementById('editModal').addEventListener('click', function(e) {
        if (e.target === this) closeEditModal();
    });
    document.getElementById('deleteModal').addEventListener('click', function(e) {
        if (e.target === this) closeDeleteModal();
    });

    // Close modals on escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeAddModal();
            closeEditModal();
            closeDeleteModal();
        }
    });
    </script>
    <?php
}
