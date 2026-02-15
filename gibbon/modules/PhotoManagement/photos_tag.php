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
use Gibbon\Domain\Students\StudentGateway;
use Gibbon\Module\PhotoManagement\Domain\PhotoGateway;
use Gibbon\Module\PhotoManagement\Domain\PhotoTagGateway;

// Module includes
require_once __DIR__ . '/moduleFunctions.php';

if (isActionAccessible($guid, $connection2, '/modules/PhotoManagement/photos.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Get photo ID
    $gibbonPhotoUploadID = $_GET['gibbonPhotoUploadID'] ?? '';

    if (empty($gibbonPhotoUploadID)) {
        $page->addError(__('You have not specified one or more required parameters.'));
        return;
    }

    // Get gateways
    $photoGateway = $container->get(PhotoGateway::class);
    $photoTagGateway = $container->get(PhotoTagGateway::class);
    $studentGateway = $container->get(StudentGateway::class);

    // Get photo
    $photo = $photoGateway->getPhotoByID($gibbonPhotoUploadID);

    if (empty($photo)) {
        $page->addError(__('The specified record cannot be found.'));
        return;
    }

    // Breadcrumbs
    $page->breadcrumbs
        ->add(__('Photo Gallery'), 'photos.php')
        ->add(__('Tag Children'));

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $URL = $session->get('absoluteURL') . '/index.php?q=/modules/PhotoManagement/photos_tag.php&gibbonPhotoUploadID=' . $gibbonPhotoUploadID;

        // CSRF check
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $session->get('csrf_token')) {
            $URL .= '&return=error0';
            header("Location: {$URL}");
            exit;
        }

        $gibbonPersonID = $session->get('gibbonPersonID');
        $selectedChildren = $_POST['children'] ?? [];

        // Get existing tags
        $existingTags = $photoTagGateway->selectTagsByPhoto($gibbonPhotoUploadID);
        $existingChildIDs = array_column($existingTags, 'gibbonPersonID');

        // Determine children to add and remove
        $childrenToAdd = array_diff($selectedChildren, $existingChildIDs);
        $childrenToRemove = array_diff($existingChildIDs, $selectedChildren);

        // Remove unselected children
        foreach ($childrenToRemove as $childID) {
            $photoTagGateway->untagChild($gibbonPhotoUploadID, $childID);
        }

        // Add new children
        $photoTagGateway->tagChildren($gibbonPhotoUploadID, $childrenToAdd, $gibbonPersonID);

        $URL .= '&return=success0';
        header("Location: {$URL}");
        exit;
    }

    // Display return messages
    if (isset($_GET['return'])) {
        switch ($_GET['return']) {
            case 'success0':
                $page->addMessage(__('Tags updated successfully.'));
                break;
            case 'error0':
                $page->addError(__('Your request failed because you do not have access to this action.'));
                break;
        }
    }

    // Display photo
    echo '<div class="photo-preview" style="text-align: center; margin-bottom: 20px; padding: 20px; background: #f5f5f5; border-radius: 8px;">';
    $photoPath = $session->get('absoluteURL') . '/' . $photo['filePath'];
    echo '<img src="' . htmlspecialchars($photoPath) . '" style="max-width: 100%; max-height: 400px; border-radius: 4px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);" alt="' . htmlspecialchars($photo['caption'] ?? '') . '">';
    if (!empty($photo['caption'])) {
        echo '<p style="margin-top: 10px; font-style: italic;">' . htmlspecialchars($photo['caption']) . '</p>';
    }
    echo '<p style="margin-top: 5px; color: #666;">' . __('Uploaded by') . ': ' . Format::name('', $photo['uploaderPreferredName'], $photo['uploaderSurname'], 'Staff') . ' - ' . Format::dateTime($photo['timestampCreated']) . '</p>';
    echo '</div>';

    // Get enrolled students (children)
    $gibbonSchoolYearID = $session->get('gibbonSchoolYearID');
    $criteria = $studentGateway->newQueryCriteria()
        ->sortBy(['surname', 'preferredName'])
        ->pageSize(0); // Get all students

    // Query active students
    $students = [];
    $studentQuery = "SELECT gibbonPerson.gibbonPersonID, gibbonPerson.preferredName, gibbonPerson.surname, gibbonPerson.image_240
                     FROM gibbonPerson
                     INNER JOIN gibbonStudentEnrolment ON gibbonStudentEnrolment.gibbonPersonID = gibbonPerson.gibbonPersonID
                     WHERE gibbonStudentEnrolment.gibbonSchoolYearID = :gibbonSchoolYearID
                     AND gibbonPerson.status = 'Full'
                     ORDER BY gibbonPerson.surname, gibbonPerson.preferredName";

    $result = $connection2->prepare($studentQuery);
    $result->execute(['gibbonSchoolYearID' => $gibbonSchoolYearID]);
    $students = $result->fetchAll(\PDO::FETCH_ASSOC);

    // Get currently tagged children
    $existingTags = $photoTagGateway->selectTagsByPhoto($gibbonPhotoUploadID);
    $taggedChildIDs = array_column($existingTags, 'gibbonPersonID');

    // Build child selection options
    $childOptions = [];
    foreach ($students as $student) {
        $childOptions[$student['gibbonPersonID']] = Format::name('', $student['preferredName'], $student['surname'], 'Student');
    }

    // Create form
    $form = Form::create('tagPhoto', $session->get('absoluteURL') . '/index.php?q=/modules/PhotoManagement/photos_tag.php&gibbonPhotoUploadID=' . $gibbonPhotoUploadID);
    $form->setTitle(__('Tag Children'));
    $form->setDescription(__('Select the children who appear in this photo. Parents will be able to see photos tagged with their children.'));
    $form->addHiddenValue('address', $session->get('address'));
    $form->addHiddenValue('csrf_token', $session->get('csrf_token'));

    if (empty($childOptions)) {
        $form->addRow()->addAlert(__('There are no enrolled children in the current school year.'), 'warning');
    } else {
        // Children multi-select
        $row = $form->addRow();
            $row->addLabel('children', __('Children in Photo'))
                ->description(__('Hold Ctrl/Cmd to select multiple children.'));
            $row->addSelect('children')
                ->fromArray($childOptions)
                ->selectMultiple()
                ->setSize(min(count($childOptions), 15))
                ->selected($taggedChildIDs);

        $row = $form->addRow();
            $row->addFooter();
            $row->addSubmit(__('Save Tags'));
    }

    echo $form->getOutput();

    // Currently tagged children display
    if (!empty($existingTags)) {
        echo '<div class="tagged-children" style="margin-top: 20px;">';
        echo '<h4>' . __('Currently Tagged') . '</h4>';
        echo '<div style="display: flex; flex-wrap: wrap; gap: 10px;">';
        foreach ($existingTags as $tag) {
            $childName = Format::name('', $tag['childPreferredName'], $tag['childSurname'], 'Student');
            echo '<div class="tag-chip" style="display: inline-flex; align-items: center; padding: 5px 10px; background: #e3f2fd; border-radius: 20px;">';
            if (!empty($tag['childImage'])) {
                echo '<img src="' . htmlspecialchars($session->get('absoluteURL') . '/' . $tag['childImage']) . '" style="width: 24px; height: 24px; border-radius: 50%; margin-right: 8px; object-fit: cover;" alt="">';
            }
            echo '<span>' . htmlspecialchars($childName) . '</span>';
            echo '</div>';
        }
        echo '</div>';
        echo '</div>';
    }

    // Warning for untagged photos
    if (empty($existingTags)) {
        echo '<div class="warning" style="margin-top: 20px; padding: 10px; background: #fff3cd; border-left: 4px solid #ffc107; border-radius: 4px;">';
        echo '<strong>' . __('Note:') . '</strong> ' . __('This photo has no children tagged. Parents will not be able to see this photo until children are tagged.');
        echo '</div>';
    }

    // Back button
    echo '<div style="margin-top: 20px;">';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/PhotoManagement/photos.php" class="button">' . __('Back to Gallery') . '</a>';
    echo '</div>';
}
