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
use Gibbon\Domain\User\FamilyGateway;
use Gibbon\Domain\System\SettingGateway;

/**
 * PhotoAccessService
 *
 * Handles photo access control business logic.
 * Determines photo visibility based on user role (Staff/Admin/Parent),
 * sharing settings, and child relationships.
 *
 * @version v1.0.00
 * @since   v1.0.00
 */
class PhotoAccessService
{
    /**
     * @var PhotoGateway
     */
    protected $photoGateway;

    /**
     * @var PhotoTagGateway
     */
    protected $photoTagGateway;

    /**
     * @var FamilyGateway
     */
    protected $familyGateway;

    /**
     * @var SettingGateway
     */
    protected $settingGateway;

    /**
     * Constructor.
     *
     * @param PhotoGateway $photoGateway Photo gateway
     * @param PhotoTagGateway $photoTagGateway Photo tag gateway
     * @param FamilyGateway $familyGateway Family gateway
     * @param SettingGateway $settingGateway Settings gateway
     */
    public function __construct(
        PhotoGateway $photoGateway,
        PhotoTagGateway $photoTagGateway,
        FamilyGateway $familyGateway,
        SettingGateway $settingGateway
    ) {
        $this->photoGateway = $photoGateway;
        $this->photoTagGateway = $photoTagGateway;
        $this->familyGateway = $familyGateway;
        $this->settingGateway = $settingGateway;
    }

    /**
     * Get accessible children for a user based on their role.
     * Parents see their own children, staff/admin see all students.
     *
     * @param int $gibbonPersonID User's person ID
     * @param string $roleCategory User's role category (Parent, Staff, Student)
     * @param int $gibbonSchoolYearID Current school year ID
     * @return array Array of accessible child IDs and info
     */
    public function getAccessibleChildren($gibbonPersonID, $roleCategory, $gibbonSchoolYearID)
    {
        $result = [
            'childIDs' => [],
            'childInfo' => [],
        ];

        if ($roleCategory === 'Parent') {
            // Get parent's children
            $children = $this->familyGateway->selectFamilyChildrenByAdult($gibbonPersonID)->fetchAll();

            foreach ($children as $child) {
                // Only include currently enrolled children
                if ($child['gibbonSchoolYearID'] == $gibbonSchoolYearID && $child['status'] === 'Full') {
                    $result['childIDs'][] = $child['gibbonPersonID'];
                    $result['childInfo'][$child['gibbonPersonID']] = [
                        'name' => $child['preferredName'] . ' ' . $child['surname'],
                        'image' => $child['image_240'] ?? '',
                        'preferredName' => $child['preferredName'],
                        'surname' => $child['surname'],
                    ];
                }
            }
        } else {
            // Staff/Admin: Get all enrolled students
            // This would typically use a StudentGateway, but for now we'll return empty
            // as the actual implementation would require additional dependencies
            // In production, this should use proper gateway methods
        }

        return $result;
    }

    /**
     * Check if a user can view a specific photo.
     * Access rules:
     * - Staff/Admin can view all photos
     * - Parents can only view photos marked as sharedWithParent='Y' and tagged with their children
     * - Photo must not be soft-deleted
     *
     * @param int $gibbonPhotoUploadID Photo ID
     * @param int $gibbonPersonID User's person ID
     * @param string $roleCategory User's role category
     * @param int $gibbonSchoolYearID Current school year ID
     * @return bool True if user can view the photo
     */
    public function canViewPhoto($gibbonPhotoUploadID, $gibbonPersonID, $roleCategory, $gibbonSchoolYearID)
    {
        // Get photo details
        $photo = $this->photoGateway->getPhotoByID($gibbonPhotoUploadID);

        if (!$photo) {
            return false;
        }

        // Staff and Admin can view all non-deleted photos
        if (in_array($roleCategory, ['Staff', 'Admin'])) {
            return true;
        }

        // Parents can only view shared photos tagged with their children
        if ($roleCategory === 'Parent') {
            // Photo must be shared with parents
            if ($photo['sharedWithParent'] !== 'Y') {
                return false;
            }

            // Check if photo is tagged with any of the parent's children
            $accessibleChildren = $this->getAccessibleChildren($gibbonPersonID, $roleCategory, $gibbonSchoolYearID);
            $childIDs = $accessibleChildren['childIDs'];

            if (empty($childIDs)) {
                return false;
            }

            // Check if photo is tagged with at least one of parent's children
            foreach ($childIDs as $childID) {
                if ($this->photoTagGateway->isChildTagged($gibbonPhotoUploadID, $childID)) {
                    return true;
                }
            }

            return false;
        }

        // Default: deny access
        return false;
    }

    /**
     * Check if a user can edit a photo.
     * Edit permissions:
     * - Admin can edit any photo
     * - Staff can edit photos they uploaded
     * - Parents cannot edit photos
     *
     * @param int $gibbonPhotoUploadID Photo ID
     * @param int $gibbonPersonID User's person ID
     * @param string $roleCategory User's role category
     * @return bool True if user can edit the photo
     */
    public function canEditPhoto($gibbonPhotoUploadID, $gibbonPersonID, $roleCategory)
    {
        // Admins can edit any photo
        if ($roleCategory === 'Admin') {
            return true;
        }

        // Get photo details
        $photo = $this->photoGateway->getPhotoByID($gibbonPhotoUploadID);

        if (!$photo) {
            return false;
        }

        // Staff can edit photos they uploaded
        if ($roleCategory === 'Staff' && $photo['uploadedByID'] == $gibbonPersonID) {
            return true;
        }

        // Default: deny edit access
        return false;
    }

    /**
     * Check if a user can delete a photo.
     * Delete permissions follow the same rules as edit permissions.
     *
     * @param int $gibbonPhotoUploadID Photo ID
     * @param int $gibbonPersonID User's person ID
     * @param string $roleCategory User's role category
     * @return bool True if user can delete the photo
     */
    public function canDeletePhoto($gibbonPhotoUploadID, $gibbonPersonID, $roleCategory)
    {
        return $this->canEditPhoto($gibbonPhotoUploadID, $gibbonPersonID, $roleCategory);
    }

    /**
     * Check if a user can upload photos.
     * Upload permissions:
     * - Admin and Staff can upload
     * - Parents cannot upload
     *
     * @param string $roleCategory User's role category
     * @return bool True if user can upload photos
     */
    public function canUploadPhotos($roleCategory)
    {
        return in_array($roleCategory, ['Staff', 'Admin']);
    }

    /**
     * Check if a user can tag children in photos.
     * Tagging permissions:
     * - Admin and Staff can tag children
     * - Parents cannot tag
     *
     * @param string $roleCategory User's role category
     * @return bool True if user can tag children
     */
    public function canTagChildren($roleCategory)
    {
        return in_array($roleCategory, ['Staff', 'Admin']);
    }

    /**
     * Check if a photo is visible to parents.
     *
     * @param int $gibbonPhotoUploadID Photo ID
     * @return bool True if photo is shared with parents
     */
    public function isSharedWithParents($gibbonPhotoUploadID)
    {
        $photo = $this->photoGateway->getPhotoByID($gibbonPhotoUploadID);

        if (!$photo) {
            return false;
        }

        return $photo['sharedWithParent'] === 'Y';
    }

    /**
     * Get photos accessible by a parent for a specific child.
     *
     * @param int $gibbonPersonID Parent's person ID
     * @param int $childID Child's person ID
     * @param int $gibbonSchoolYearID School year ID
     * @return array Array of accessible photos
     */
    public function getPhotosForParentByChild($gibbonPersonID, $childID, $gibbonSchoolYearID)
    {
        // Verify parent has access to this child
        $accessibleChildren = $this->getAccessibleChildren($gibbonPersonID, 'Parent', $gibbonSchoolYearID);

        if (!in_array($childID, $accessibleChildren['childIDs'])) {
            return [];
        }

        // Get photos tagged with this child that are shared with parents
        return $this->photoTagGateway->selectPhotosForParentByChild($childID, $gibbonSchoolYearID);
    }

    /**
     * Validate photo ownership before modification.
     *
     * @param int $gibbonPhotoUploadID Photo ID
     * @param int $gibbonPersonID User's person ID
     * @return bool True if user owns the photo
     */
    public function isPhotoOwner($gibbonPhotoUploadID, $gibbonPersonID)
    {
        $photo = $this->photoGateway->getPhotoByID($gibbonPhotoUploadID);

        if (!$photo) {
            return false;
        }

        return $photo['uploadedByID'] == $gibbonPersonID;
    }

    /**
     * Get retention expiry date for a deleted photo.
     * Quebec compliance requires 5-year retention.
     *
     * @param string $deletedAt Deletion timestamp
     * @param int $retentionYears Retention period in years (default 5)
     * @return string Expiry date
     */
    public function getRetentionExpiry($deletedAt, $retentionYears = 5)
    {
        $deletedTime = strtotime($deletedAt);
        $expiryTime = strtotime("+{$retentionYears} years", $deletedTime);
        return date('Y-m-d H:i:s', $expiryTime);
    }

    /**
     * Check if a deleted photo has expired past retention period.
     *
     * @param string $deletedAt Deletion timestamp
     * @param int $retentionYears Retention period in years (default 5)
     * @return bool True if retention period has expired
     */
    public function isRetentionExpired($deletedAt, $retentionYears = 5)
    {
        $expiryDate = $this->getRetentionExpiry($deletedAt, $retentionYears);
        return strtotime($expiryDate) < time();
    }

    /**
     * Calculate remaining retention days for a deleted photo.
     *
     * @param string $deletedAt Deletion timestamp
     * @param int $retentionYears Retention period in years (default 5)
     * @return int Remaining days (negative if expired)
     */
    public function getRetentionDaysRemaining($deletedAt, $retentionYears = 5)
    {
        $expiryDate = $this->getRetentionExpiry($deletedAt, $retentionYears);
        $expiryTime = strtotime($expiryDate);
        $now = time();

        return (int) floor(($expiryTime - $now) / (60 * 60 * 24));
    }

    /**
     * Validate file type for photo upload.
     *
     * @param string $mimeType MIME type of the file
     * @param string $allowedTypes Comma-separated list of allowed extensions
     * @return bool True if valid photo type
     */
    public function isValidPhotoType($mimeType, $allowedTypes = 'jpg,jpeg,png,gif,webp')
    {
        $mimeMap = [
            'image/jpeg' => ['jpg', 'jpeg'],
            'image/png' => ['png'],
            'image/gif' => ['gif'],
            'image/webp' => ['webp'],
        ];

        $allowedArray = array_map('trim', explode(',', strtolower($allowedTypes)));

        if (!isset($mimeMap[$mimeType])) {
            return false;
        }

        foreach ($mimeMap[$mimeType] as $ext) {
            if (in_array($ext, $allowedArray)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get maximum upload file size from settings.
     *
     * @return int Maximum file size in bytes
     */
    public function getMaxUploadSize()
    {
        $maxSize = $this->settingGateway->getSettingByScope('PhotoManagement', 'maxUploadSize');

        // Default to 5MB if not set
        return $maxSize ? (int) $maxSize : 5242880;
    }

    /**
     * Validate file size against maximum allowed.
     *
     * @param int $fileSize File size in bytes
     * @return bool True if within size limit
     */
    public function isValidFileSize($fileSize)
    {
        $maxSize = $this->getMaxUploadSize();

        return $fileSize > 0 && $fileSize <= $maxSize;
    }
}
