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
use Gibbon\Domain\User\FamilyGateway;
use Gibbon\Module\PhotoManagement\Domain\PhotoGateway;
use Gibbon\Module\PhotoManagement\Domain\PhotoTagGateway;

// Module includes
require_once __DIR__ . '/moduleFunctions.php';

if (isActionAccessible($guid, $connection2, '/modules/PhotoManagement/photos_gallery.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Proceed!
    $page->breadcrumbs->add(__('Photo Gallery'));

    $gibbonSchoolYearID = $session->get('gibbonSchoolYearID');
    $gibbonPersonID = $session->get('gibbonPersonID');

    // Get gateways
    $photoTagGateway = $container->get(PhotoTagGateway::class);
    $familyGateway = $container->get(FamilyGateway::class);

    // Determine user role and get accessible children
    $roleCategory = $session->get('gibbonRoleIDCurrentCategory');
    $childrenIDs = [];
    $childrenInfo = [];

    if ($roleCategory === 'Parent') {
        // Parent: Get their children
        $familyQuery = "SELECT gibbonFamilyChild.gibbonPersonID, gibbonPerson.preferredName, gibbonPerson.surname, gibbonPerson.image_240
                        FROM gibbonFamilyChild
                        INNER JOIN gibbonFamilyAdult ON gibbonFamilyAdult.gibbonFamilyID = gibbonFamilyChild.gibbonFamilyID
                        INNER JOIN gibbonPerson ON gibbonPerson.gibbonPersonID = gibbonFamilyChild.gibbonPersonID
                        INNER JOIN gibbonStudentEnrolment ON gibbonStudentEnrolment.gibbonPersonID = gibbonFamilyChild.gibbonPersonID
                        WHERE gibbonFamilyAdult.gibbonPersonID = :gibbonPersonID
                        AND gibbonStudentEnrolment.gibbonSchoolYearID = :gibbonSchoolYearID
                        AND gibbonPerson.status = 'Full'
                        ORDER BY gibbonPerson.surname, gibbonPerson.preferredName";

        $result = $connection2->prepare($familyQuery);
        $result->execute([
            'gibbonPersonID' => $gibbonPersonID,
            'gibbonSchoolYearID' => $gibbonSchoolYearID,
        ]);
        $children = $result->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($children as $child) {
            $childrenIDs[] = $child['gibbonPersonID'];
            $childrenInfo[$child['gibbonPersonID']] = [
                'name' => Format::name('', $child['preferredName'], $child['surname'], 'Student'),
                'image' => $child['image_240'],
            ];
        }
    } else {
        // Staff/Admin: Get all enrolled students
        $studentQuery = "SELECT gibbonPerson.gibbonPersonID, gibbonPerson.preferredName, gibbonPerson.surname, gibbonPerson.image_240
                         FROM gibbonPerson
                         INNER JOIN gibbonStudentEnrolment ON gibbonStudentEnrolment.gibbonPersonID = gibbonPerson.gibbonPersonID
                         WHERE gibbonStudentEnrolment.gibbonSchoolYearID = :gibbonSchoolYearID
                         AND gibbonPerson.status = 'Full'
                         ORDER BY gibbonPerson.surname, gibbonPerson.preferredName";

        $result = $connection2->prepare($studentQuery);
        $result->execute(['gibbonSchoolYearID' => $gibbonSchoolYearID]);
        $students = $result->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($students as $student) {
            $childrenIDs[] = $student['gibbonPersonID'];
            $childrenInfo[$student['gibbonPersonID']] = [
                'name' => Format::name('', $student['preferredName'], $student['surname'], 'Student'),
                'image' => $student['image_240'],
            ];
        }
    }

    // Check if parent has no children
    if ($roleCategory === 'Parent' && empty($childrenIDs)) {
        echo '<div class="message">';
        echo __('You do not have any children enrolled in the current school year.');
        echo '</div>';
        return;
    }

    // Get selected child filter
    $selectedChild = $_GET['child'] ?? '';

    // Filter form for selecting child
    if (count($childrenIDs) > 1) {
        $form = Form::create('filter', $session->get('absoluteURL') . '/index.php', 'get');
        $form->setTitle(__('Filter'));
        $form->setClass('noIntBorder fullWidth');

        $form->addHiddenValue('q', '/modules/PhotoManagement/photos_gallery.php');

        $childOptions = ['' => __('All Children')];
        foreach ($childrenInfo as $childID => $info) {
            $childOptions[$childID] = $info['name'];
        }

        $row = $form->addRow();
            $row->addLabel('child', __('Child'));
            $row->addSelect('child')
                ->fromArray($childOptions)
                ->selected($selectedChild);

        $row = $form->addRow();
            $row->addSearchSubmit($session, __('Clear Filters'));

        echo $form->getOutput();
    }

    // Get photos for selected children
    $photos = [];
    $childIDsToQuery = !empty($selectedChild) ? [$selectedChild] : $childrenIDs;

    foreach ($childIDsToQuery as $childID) {
        $childPhotos = $photoTagGateway->selectPhotosForParentByChild($childID, $gibbonSchoolYearID);
        foreach ($childPhotos as $photo) {
            // Avoid duplicates if a photo has multiple tagged children
            $photoKey = $photo['gibbonPhotoUploadID'];
            if (!isset($photos[$photoKey])) {
                $photos[$photoKey] = $photo;
                $photos[$photoKey]['taggedChildren'] = [];
            }
            $photos[$photoKey]['taggedChildren'][] = $childrenInfo[$childID]['name'] ?? '';
        }
    }

    // Sort photos by timestamp (newest first)
    usort($photos, function ($a, $b) {
        return strtotime($b['timestampCreated']) - strtotime($a['timestampCreated']);
    });

    // Display gallery
    if (empty($photos)) {
        echo '<div class="message">';
        if (!empty($selectedChild)) {
            echo __('No photos have been shared for this child yet.');
        } else {
            echo __('No photos have been shared for your children yet.');
        }
        echo '</div>';
    } else {
        // Photo count
        echo '<p style="margin-bottom: 15px;">' . sprintf(__('Showing %d photo(s)'), count($photos)) . '</p>';

        // Gallery grid
        echo '<div class="photo-gallery" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 20px;">';

        foreach ($photos as $photo) {
            $photoPath = $session->get('absoluteURL') . '/' . $photo['filePath'];
            $caption = !empty($photo['caption']) ? htmlspecialchars($photo['caption']) : '';
            $date = Format::dateTime($photo['timestampCreated']);
            $taggedNames = implode(', ', $photo['taggedChildren']);

            echo '<div class="photo-card" style="background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1); transition: transform 0.2s;">';

            // Photo image
            echo '<div class="photo-image" style="position: relative; padding-top: 75%; overflow: hidden;">';
            echo '<img src="' . htmlspecialchars($photoPath) . '" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; object-fit: cover;" alt="' . $caption . '">';
            echo '</div>';

            // Photo info
            echo '<div class="photo-info" style="padding: 15px;">';

            // Caption
            if (!empty($caption)) {
                echo '<p style="margin: 0 0 10px 0; font-size: 14px; color: #333;">' . $caption . '</p>';
            }

            // Tagged children
            echo '<p style="margin: 0 0 5px 0; font-size: 12px; color: #666;">';
            echo '<strong>' . __('With:') . '</strong> ' . htmlspecialchars($taggedNames);
            echo '</p>';

            // Date
            echo '<p style="margin: 0; font-size: 11px; color: #999;">' . $date . '</p>';

            echo '</div>';
            echo '</div>';
        }

        echo '</div>';

        // Lightbox script for full-size viewing
        echo '<style>
            .photo-card:hover {
                transform: translateY(-5px);
            }
            .photo-card .photo-image img {
                cursor: pointer;
            }
            .photo-lightbox {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0,0,0,0.9);
                z-index: 10000;
                justify-content: center;
                align-items: center;
            }
            .photo-lightbox img {
                max-width: 90%;
                max-height: 90%;
                object-fit: contain;
            }
            .photo-lightbox .close-btn {
                position: absolute;
                top: 20px;
                right: 30px;
                color: white;
                font-size: 40px;
                cursor: pointer;
            }
        </style>';

        echo '<div id="photoLightbox" class="photo-lightbox" onclick="closeLightbox()">
            <span class="close-btn">&times;</span>
            <img id="lightboxImage" src="" alt="">
        </div>';

        echo '<script>
            document.querySelectorAll(".photo-card .photo-image img").forEach(function(img) {
                img.addEventListener("click", function() {
                    document.getElementById("lightboxImage").src = this.src;
                    document.getElementById("photoLightbox").style.display = "flex";
                });
            });

            function closeLightbox() {
                document.getElementById("photoLightbox").style.display = "none";
            }

            document.addEventListener("keydown", function(e) {
                if (e.key === "Escape") {
                    closeLightbox();
                }
            });
        </script>';
    }

    // Display child selector for parents with multiple children
    if ($roleCategory === 'Parent' && count($childrenIDs) > 1) {
        echo '<div style="margin-top: 30px; padding: 15px; background: #f5f5f5; border-radius: 8px;">';
        echo '<h4 style="margin-top: 0;">' . __('Your Children') . '</h4>';
        echo '<div style="display: flex; flex-wrap: wrap; gap: 15px;">';

        foreach ($childrenInfo as $childID => $info) {
            $isSelected = $selectedChild == $childID;
            $style = $isSelected ? 'border: 2px solid #1976d2;' : 'border: 2px solid transparent;';
            $link = $session->get('absoluteURL') . '/index.php?q=/modules/PhotoManagement/photos_gallery.php&child=' . $childID;

            echo '<a href="' . $link . '" style="text-decoration: none;">';
            echo '<div style="display: flex; align-items: center; padding: 10px 15px; background: #fff; border-radius: 25px; ' . $style . '">';
            if (!empty($info['image'])) {
                echo '<img src="' . htmlspecialchars($session->get('absoluteURL') . '/' . $info['image']) . '" style="width: 32px; height: 32px; border-radius: 50%; margin-right: 10px; object-fit: cover;" alt="">';
            }
            echo '<span style="color: #333;">' . htmlspecialchars($info['name']) . '</span>';
            echo '</div>';
            echo '</a>';
        }

        echo '</div>';
        echo '</div>';
    }
}
