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
use Gibbon\Tables\DataTable;
use Gibbon\Services\Format;
use Gibbon\Domain\QueryCriteria;
use Gibbon\Module\PhotoManagement\Domain\PhotoGateway;
use Gibbon\Module\PhotoManagement\Domain\PhotoTagGateway;

// Module includes
require_once __DIR__ . '/moduleFunctions.php';

if (isActionAccessible($guid, $connection2, '/modules/PhotoManagement/photos.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Proceed!
    $page->breadcrumbs->add(__('Photo Gallery'));

    // Get current school year
    $gibbonSchoolYearID = $session->get('gibbonSchoolYearID');

    // Get gateways
    $photoGateway = $container->get(PhotoGateway::class);
    $photoTagGateway = $container->get(PhotoTagGateway::class);

    // Filter form
    $form = Form::create('filter', $session->get('absoluteURL') . '/index.php', 'get');
    $form->setTitle(__('Filter'));
    $form->setClass('noIntBorder fullWidth');

    $form->addHiddenValue('q', '/modules/PhotoManagement/photos.php');

    $row = $form->addRow();
        $row->addLabel('search', __('Search'));
        $row->addTextField('search')
            ->setValue($_GET['search'] ?? '')
            ->placeholder(__('Caption or filename...'))
            ->maxLength(50);

    $row = $form->addRow();
        $row->addSearchSubmit($session, __('Clear Filters'));

    echo $form->getOutput();

    // Build query criteria
    $criteria = $photoGateway->newQueryCriteria(true)
        ->sortBy(['timestampCreated'], 'DESC')
        ->filterBy('search', $_GET['search'] ?? '')
        ->fromPOST();

    // Get photos
    $photos = $photoGateway->queryPhotos($criteria, $gibbonSchoolYearID);

    // Create data table
    $table = DataTable::createPaginated('photos', $criteria);
    $table->setTitle(__('Photos'));

    // Add header actions
    $table->addHeaderAction('add', __('Upload Photo'))
        ->setURL('/modules/PhotoManagement/photos_upload.php')
        ->displayLabel();

    // Add columns
    $table->addColumn('photo', __('Photo'))
        ->notSortable()
        ->format(function ($photo) use ($session) {
            $path = $session->get('absoluteURL') . '/' . $photo['filePath'];
            return '<img src="' . htmlspecialchars($path) . '" class="photo-thumbnail" style="max-width: 100px; max-height: 100px; object-fit: cover; border-radius: 4px;" alt="' . htmlspecialchars($photo['caption'] ?? '') . '">';
        });

    $table->addColumn('caption', __('Caption'))
        ->format(function ($photo) {
            return !empty($photo['caption']) ? htmlspecialchars($photo['caption']) : '<span class="tag dull">' . __('No caption') . '</span>';
        });

    $table->addColumn('uploader', __('Uploaded By'))
        ->format(function ($photo) {
            return Format::name('', $photo['uploaderPreferredName'], $photo['uploaderSurname'], 'Staff');
        });

    $table->addColumn('timestampCreated', __('Date'))
        ->format(function ($photo) {
            return Format::dateTime($photo['timestampCreated']);
        });

    $table->addColumn('sharedWithParent', __('Shared'))
        ->format(function ($photo) {
            return $photo['sharedWithParent'] === 'Y'
                ? '<span class="tag success">' . __('Yes') . '</span>'
                : '<span class="tag dull">' . __('No') . '</span>';
        });

    $table->addColumn('tags', __('Tags'))
        ->notSortable()
        ->format(function ($photo) use ($photoTagGateway) {
            $tags = $photoTagGateway->selectTagsByPhoto($photo['gibbonPhotoUploadID']);
            if (empty($tags)) {
                return '<span class="tag warning">' . __('Untagged') . '</span>';
            }
            $names = array_map(function ($tag) {
                return htmlspecialchars($tag['childPreferredName'] . ' ' . $tag['childSurname']);
            }, $tags);
            return implode(', ', array_slice($names, 0, 3)) . (count($names) > 3 ? ' +' . (count($names) - 3) . ' more' : '');
        });

    // Actions
    $table->addActionColumn()
        ->addParam('gibbonPhotoUploadID')
        ->format(function ($photo, $actions) {
            $actions->addAction('tag', __('Tag Children'))
                ->setURL('/modules/PhotoManagement/photos_tag.php')
                ->setIcon('attendance');

            $actions->addAction('delete', __('Delete'))
                ->setURL('/modules/PhotoManagement/photos_deleteProcess.php')
                ->setIcon('garbage')
                ->directLink()
                ->addConfirmation(__('Are you sure you wish to delete this photo?'));
        });

    echo $table->render($photos);

    // Photo count summary
    $photoCount = $photoGateway->countPhotosBySchoolYear($gibbonSchoolYearID);
    echo '<div class="message">';
    echo sprintf(__('Total photos this school year: %d'), $photoCount);
    echo '</div>';
}
