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

namespace Gibbon\Module\PhotoManagement\Service;

use Gibbon\Module\PhotoManagement\Domain\PhotoGateway;
use Gibbon\Module\PhotoManagement\Domain\PhotoTagGateway;

/**
 * PhotoTagService
 *
 * Handles photo tagging business logic.
 * Manages tagging children in photos for parent gallery filtering.
 *
 * @version v1.0.00
 * @since   v1.0.00
 */
class PhotoTagService
{
    /**
     * @var PhotoTagGateway
     */
    protected $photoTagGateway;

    /**
     * @var PhotoGateway
     */
    protected $photoGateway;

    /**
     * Constructor.
     *
     * @param PhotoTagGateway $photoTagGateway Photo tag gateway
     * @param PhotoGateway $photoGateway Photo gateway
     */
    public function __construct(
        PhotoTagGateway $photoTagGateway,
        PhotoGateway $photoGateway
    ) {
        $this->photoTagGateway = $photoTagGateway;
        $this->photoGateway = $photoGateway;
    }

    /**
     * Tag multiple children in a photo.
     * Validates photo exists and skips already-tagged children.
     *
     * @param int $gibbonPhotoUploadID Photo ID
     * @param array $childIDs Array of gibbonPersonID values
     * @param int $taggedByID Person performing the tagging
     * @return array Result with success count and details
     */
    public function tagChildren($gibbonPhotoUploadID, array $childIDs, $taggedByID)
    {
        // Validate photo exists
        $photo = $this->photoGateway->getPhotoByID($gibbonPhotoUploadID);

        if (!$photo) {
            return [
                'success' => false,
                'error' => 'Photo not found',
                'tagged' => 0,
                'skipped' => 0,
            ];
        }

        $tagged = 0;
        $skipped = 0;

        foreach ($childIDs as $childID) {
            // Skip if already tagged
            if ($this->photoTagGateway->isChildTagged($gibbonPhotoUploadID, $childID)) {
                $skipped++;
                continue;
            }

            // Tag the child
            $result = $this->photoTagGateway->insertTag([
                'gibbonPhotoUploadID' => $gibbonPhotoUploadID,
                'gibbonPersonID' => $childID,
                'taggedByID' => $taggedByID,
            ]);

            if ($result !== false) {
                $tagged++;
            }
        }

        return [
            'success' => true,
            'tagged' => $tagged,
            'skipped' => $skipped,
            'total' => count($childIDs),
        ];
    }

    /**
     * Tag a single child in a photo.
     *
     * @param int $gibbonPhotoUploadID Photo ID
     * @param int $gibbonPersonID Child's person ID
     * @param int $taggedByID Person performing the tagging
     * @return array Result with success status
     */
    public function tagChild($gibbonPhotoUploadID, $gibbonPersonID, $taggedByID)
    {
        // Validate photo exists
        $photo = $this->photoGateway->getPhotoByID($gibbonPhotoUploadID);

        if (!$photo) {
            return [
                'success' => false,
                'error' => 'Photo not found',
            ];
        }

        // Check if already tagged
        if ($this->photoTagGateway->isChildTagged($gibbonPhotoUploadID, $gibbonPersonID)) {
            return [
                'success' => false,
                'error' => 'Child already tagged in this photo',
            ];
        }

        // Tag the child
        $result = $this->photoTagGateway->insertTag([
            'gibbonPhotoUploadID' => $gibbonPhotoUploadID,
            'gibbonPersonID' => $gibbonPersonID,
            'taggedByID' => $taggedByID,
        ]);

        if ($result === false) {
            return [
                'success' => false,
                'error' => 'Failed to tag child',
            ];
        }

        return [
            'success' => true,
            'gibbonPhotoTagID' => $result,
        ];
    }

    /**
     * Remove a child tag from a photo.
     *
     * @param int $gibbonPhotoUploadID Photo ID
     * @param int $gibbonPersonID Child's person ID
     * @return array Result with success status
     */
    public function untagChild($gibbonPhotoUploadID, $gibbonPersonID)
    {
        // Check if child is tagged
        if (!$this->photoTagGateway->isChildTagged($gibbonPhotoUploadID, $gibbonPersonID)) {
            return [
                'success' => false,
                'error' => 'Child not tagged in this photo',
            ];
        }

        // Remove the tag
        $result = $this->photoTagGateway->untagChild($gibbonPhotoUploadID, $gibbonPersonID);

        if (!$result) {
            return [
                'success' => false,
                'error' => 'Failed to untag child',
            ];
        }

        return [
            'success' => true,
        ];
    }

    /**
     * Remove all tags from a photo.
     *
     * @param int $gibbonPhotoUploadID Photo ID
     * @return array Result with success status and count
     */
    public function removeAllTags($gibbonPhotoUploadID)
    {
        $count = $this->photoTagGateway->deleteAllTagsForPhoto($gibbonPhotoUploadID);

        return [
            'success' => true,
            'removed' => $count,
        ];
    }

    /**
     * Get all tags for a photo.
     *
     * @param int $gibbonPhotoUploadID Photo ID
     * @return array Array of tag records with child details
     */
    public function getTagsForPhoto($gibbonPhotoUploadID)
    {
        return $this->photoTagGateway->selectTagsByPhoto($gibbonPhotoUploadID);
    }

    /**
     * Get photos tagged with a specific child.
     *
     * @param int $gibbonPersonID Child's person ID
     * @return array Array of photo records
     */
    public function getPhotosByChild($gibbonPersonID)
    {
        return $this->photoTagGateway->selectPhotosByChild($gibbonPersonID);
    }

    /**
     * Check if a child is tagged in a photo.
     *
     * @param int $gibbonPhotoUploadID Photo ID
     * @param int $gibbonPersonID Child's person ID
     * @return bool True if child is tagged
     */
    public function isChildTagged($gibbonPhotoUploadID, $gibbonPersonID)
    {
        return $this->photoTagGateway->isChildTagged($gibbonPhotoUploadID, $gibbonPersonID);
    }

    /**
     * Count tags for a photo.
     *
     * @param int $gibbonPhotoUploadID Photo ID
     * @return int Number of tags
     */
    public function countTags($gibbonPhotoUploadID)
    {
        return $this->photoTagGateway->countTagsByPhoto($gibbonPhotoUploadID);
    }

    /**
     * Bulk tag children across multiple photos.
     * Useful for tagging class photos or event photos.
     *
     * @param array $photoIDs Array of gibbonPhotoUploadID values
     * @param array $childIDs Array of gibbonPersonID values
     * @param int $taggedByID Person performing the tagging
     * @return array Summary of tagging results
     */
    public function bulkTagPhotos(array $photoIDs, array $childIDs, $taggedByID)
    {
        $results = [
            'success' => true,
            'photosProcessed' => 0,
            'totalTagged' => 0,
            'totalSkipped' => 0,
            'errors' => [],
        ];

        foreach ($photoIDs as $photoID) {
            $result = $this->tagChildren($photoID, $childIDs, $taggedByID);

            if ($result['success']) {
                $results['photosProcessed']++;
                $results['totalTagged'] += $result['tagged'];
                $results['totalSkipped'] += $result['skipped'];
            } else {
                $results['errors'][] = [
                    'photoID' => $photoID,
                    'error' => $result['error'] ?? 'Unknown error',
                ];
            }
        }

        return $results;
    }

    /**
     * Replace all tags for a photo with a new set of children.
     * Removes existing tags and adds new ones.
     *
     * @param int $gibbonPhotoUploadID Photo ID
     * @param array $childIDs Array of gibbonPersonID values
     * @param int $taggedByID Person performing the tagging
     * @return array Result with success status
     */
    public function replaceTags($gibbonPhotoUploadID, array $childIDs, $taggedByID)
    {
        // Validate photo exists
        $photo = $this->photoGateway->getPhotoByID($gibbonPhotoUploadID);

        if (!$photo) {
            return [
                'success' => false,
                'error' => 'Photo not found',
            ];
        }

        // Remove all existing tags
        $this->photoTagGateway->deleteAllTagsForPhoto($gibbonPhotoUploadID);

        // Add new tags
        $tagResult = $this->tagChildren($gibbonPhotoUploadID, $childIDs, $taggedByID);

        return [
            'success' => true,
            'removed' => 'all',
            'tagged' => $tagResult['tagged'],
        ];
    }

    /**
     * Get tagging statistics for a school year.
     * Returns how many photos each child is tagged in.
     *
     * @param int $gibbonSchoolYearID School year ID
     * @return array Array of statistics per child
     */
    public function getTagStatistics($gibbonSchoolYearID)
    {
        return $this->photoTagGateway->getTagStatistics($gibbonSchoolYearID);
    }

    /**
     * Validate tag data before insertion.
     *
     * @param int $gibbonPhotoUploadID Photo ID
     * @param int $gibbonPersonID Child's person ID
     * @param int $taggedByID Tagger person ID
     * @return array Validation result
     */
    public function validateTag($gibbonPhotoUploadID, $gibbonPersonID, $taggedByID)
    {
        $errors = [];

        // Check photo exists
        $photo = $this->photoGateway->getPhotoByID($gibbonPhotoUploadID);
        if (!$photo) {
            $errors[] = 'Photo not found';
        }

        // Check if already tagged
        if ($this->photoTagGateway->isChildTagged($gibbonPhotoUploadID, $gibbonPersonID)) {
            $errors[] = 'Child already tagged in this photo';
        }

        // Validate IDs are positive integers
        if ($gibbonPhotoUploadID <= 0) {
            $errors[] = 'Invalid photo ID';
        }

        if ($gibbonPersonID <= 0) {
            $errors[] = 'Invalid child ID';
        }

        if ($taggedByID <= 0) {
            $errors[] = 'Invalid tagger ID';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Get tags created by a specific user.
     *
     * @param int $taggedByID Tagger person ID
     * @return array Array of tag records
     */
    public function getTagsByTagger($taggedByID)
    {
        return $this->photoTagGateway->selectTagsByTagger($taggedByID);
    }
}
