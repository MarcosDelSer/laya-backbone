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

namespace Gibbon\Module\PhotoManagement\Domain;

use Gibbon\Domain\Traits\TableAware;
use Gibbon\Domain\QueryCriteria;
use Gibbon\Domain\QueryableGateway;

/**
 * PhotoGateway
 *
 * Gateway for photo upload operations with soft-delete support
 * for 5-year Quebec regulatory compliance.
 *
 * @version v1.0.00
 * @since   v1.0.00
 */
class PhotoGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'gibbonPhotoUpload';
    private static $primaryKey = 'gibbonPhotoUploadID';
    private static $searchableColumns = ['gibbonPhotoUpload.caption', 'gibbonPhotoUpload.filename'];

    /**
     * Query photos with pagination support.
     * Excludes soft-deleted records for compliance.
     *
     * @param QueryCriteria $criteria
     * @param int $gibbonSchoolYearID
     * @return \Gibbon\Domain\DataSet
     */
    public function queryPhotos(QueryCriteria $criteria, $gibbonSchoolYearID)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonPhotoUpload.gibbonPhotoUploadID',
                'gibbonPhotoUpload.gibbonSchoolYearID',
                'gibbonPhotoUpload.filename',
                'gibbonPhotoUpload.filePath',
                'gibbonPhotoUpload.caption',
                'gibbonPhotoUpload.mimeType',
                'gibbonPhotoUpload.fileSize',
                'gibbonPhotoUpload.uploadedByID',
                'gibbonPhotoUpload.sharedWithParent',
                'gibbonPhotoUpload.timestampCreated',
                'uploader.preferredName AS uploaderPreferredName',
                'uploader.surname AS uploaderSurname',
            ])
            ->leftJoin('gibbonPerson AS uploader', 'uploader.gibbonPersonID = gibbonPhotoUpload.uploadedByID')
            ->where('gibbonPhotoUpload.gibbonSchoolYearID = :gibbonSchoolYearID')
            ->where('gibbonPhotoUpload.deletedAt IS NULL')
            ->bindValue('gibbonSchoolYearID', $gibbonSchoolYearID);

        return $this->runQuery($query, $criteria);
    }

    /**
     * Query photos shared with parents.
     * Used for parent gallery view.
     *
     * @param QueryCriteria $criteria
     * @param int $gibbonSchoolYearID
     * @return \Gibbon\Domain\DataSet
     */
    public function queryPhotosForParents(QueryCriteria $criteria, $gibbonSchoolYearID)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonPhotoUpload.gibbonPhotoUploadID',
                'gibbonPhotoUpload.gibbonSchoolYearID',
                'gibbonPhotoUpload.filename',
                'gibbonPhotoUpload.filePath',
                'gibbonPhotoUpload.caption',
                'gibbonPhotoUpload.mimeType',
                'gibbonPhotoUpload.timestampCreated',
            ])
            ->where('gibbonPhotoUpload.gibbonSchoolYearID = :gibbonSchoolYearID')
            ->where('gibbonPhotoUpload.sharedWithParent = :sharedWithParent')
            ->where('gibbonPhotoUpload.deletedAt IS NULL')
            ->bindValue('gibbonSchoolYearID', $gibbonSchoolYearID)
            ->bindValue('sharedWithParent', 'Y');

        return $this->runQuery($query, $criteria);
    }

    /**
     * Query photos by tagged child for parent gallery.
     *
     * @param QueryCriteria $criteria
     * @param int $gibbonSchoolYearID
     * @param int $gibbonPersonID Child's person ID
     * @return \Gibbon\Domain\DataSet
     */
    public function queryPhotosByChild(QueryCriteria $criteria, $gibbonSchoolYearID, $gibbonPersonID)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonPhotoUpload.gibbonPhotoUploadID',
                'gibbonPhotoUpload.gibbonSchoolYearID',
                'gibbonPhotoUpload.filename',
                'gibbonPhotoUpload.filePath',
                'gibbonPhotoUpload.caption',
                'gibbonPhotoUpload.mimeType',
                'gibbonPhotoUpload.timestampCreated',
            ])
            ->innerJoin('gibbonPhotoTag', 'gibbonPhotoTag.gibbonPhotoUploadID = gibbonPhotoUpload.gibbonPhotoUploadID')
            ->where('gibbonPhotoUpload.gibbonSchoolYearID = :gibbonSchoolYearID')
            ->where('gibbonPhotoTag.gibbonPersonID = :gibbonPersonID')
            ->where('gibbonPhotoUpload.sharedWithParent = :sharedWithParent')
            ->where('gibbonPhotoUpload.deletedAt IS NULL')
            ->bindValue('gibbonSchoolYearID', $gibbonSchoolYearID)
            ->bindValue('gibbonPersonID', $gibbonPersonID)
            ->bindValue('sharedWithParent', 'Y');

        return $this->runQuery($query, $criteria);
    }

    /**
     * Get a single photo by ID.
     * Excludes soft-deleted records.
     *
     * @param int $gibbonPhotoUploadID
     * @return array|false
     */
    public function getPhotoByID($gibbonPhotoUploadID)
    {
        $data = ['gibbonPhotoUploadID' => $gibbonPhotoUploadID];
        $sql = "SELECT gibbonPhotoUpload.*,
                       uploader.preferredName AS uploaderPreferredName,
                       uploader.surname AS uploaderSurname
                FROM gibbonPhotoUpload
                LEFT JOIN gibbonPerson AS uploader ON uploader.gibbonPersonID = gibbonPhotoUpload.uploadedByID
                WHERE gibbonPhotoUploadID = :gibbonPhotoUploadID
                AND deletedAt IS NULL";

        return $this->db()->selectOne($sql, $data);
    }

    /**
     * Get photos by uploader.
     *
     * @param int $uploadedByID
     * @param int $gibbonSchoolYearID
     * @return array
     */
    public function selectPhotosByUploader($uploadedByID, $gibbonSchoolYearID)
    {
        $data = [
            'uploadedByID' => $uploadedByID,
            'gibbonSchoolYearID' => $gibbonSchoolYearID,
        ];
        $sql = "SELECT gibbonPhotoUploadID, filename, filePath, caption, timestampCreated
                FROM gibbonPhotoUpload
                WHERE uploadedByID = :uploadedByID
                AND gibbonSchoolYearID = :gibbonSchoolYearID
                AND deletedAt IS NULL
                ORDER BY timestampCreated DESC";

        return $this->db()->select($sql, $data)->fetchAll();
    }

    /**
     * Insert a new photo record.
     *
     * @param array $data Photo data
     * @return int|false The new photo ID or false on failure
     */
    public function insertPhoto(array $data)
    {
        $fields = [
            'gibbonSchoolYearID',
            'filename',
            'filePath',
            'caption',
            'mimeType',
            'fileSize',
            'uploadedByID',
            'sharedWithParent',
        ];

        $insertData = array_intersect_key($data, array_flip($fields));

        return $this->insert($insertData);
    }

    /**
     * Update a photo record.
     *
     * @param int $gibbonPhotoUploadID
     * @param array $data Updated photo data
     * @return bool
     */
    public function updatePhoto($gibbonPhotoUploadID, array $data)
    {
        $allowedFields = ['caption', 'sharedWithParent'];
        $updateData = array_intersect_key($data, array_flip($allowedFields));

        return $this->update($gibbonPhotoUploadID, $updateData);
    }

    /**
     * Soft delete a photo.
     * CRITICAL: Do not use hard delete - Quebec compliance requires 5-year retention.
     *
     * @param int $gibbonPhotoUploadID
     * @param int $deletedByID The person performing the delete
     * @return bool
     */
    public function softDelete($gibbonPhotoUploadID, $deletedByID)
    {
        return $this->update($gibbonPhotoUploadID, [
            'deletedAt' => date('Y-m-d H:i:s'),
            'deletedByID' => $deletedByID,
        ]);
    }

    /**
     * Restore a soft-deleted photo.
     *
     * @param int $gibbonPhotoUploadID
     * @return bool
     */
    public function restore($gibbonPhotoUploadID)
    {
        return $this->update($gibbonPhotoUploadID, [
            'deletedAt' => null,
            'deletedByID' => null,
        ]);
    }

    /**
     * Purge records deleted more than specified years ago.
     * CRITICAL: Only call from CLI scheduled task for Quebec compliance.
     *
     * @param int $retentionYears Default 5 years for Quebec compliance
     * @return int Number of records purged
     */
    public function purgeExpiredRecords($retentionYears = 5)
    {
        $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$retentionYears} years"));

        $data = ['cutoffDate' => $cutoffDate];
        $sql = "DELETE FROM gibbonPhotoUpload
                WHERE deletedAt IS NOT NULL
                AND deletedAt < :cutoffDate";

        return $this->db()->delete($sql, $data);
    }

    /**
     * Count photos by school year.
     *
     * @param int $gibbonSchoolYearID
     * @return int
     */
    public function countPhotosBySchoolYear($gibbonSchoolYearID)
    {
        $data = ['gibbonSchoolYearID' => $gibbonSchoolYearID];
        $sql = "SELECT COUNT(*) as total
                FROM gibbonPhotoUpload
                WHERE gibbonSchoolYearID = :gibbonSchoolYearID
                AND deletedAt IS NULL";

        $result = $this->db()->selectOne($sql, $data);
        return $result['total'] ?? 0;
    }

    /**
     * Get soft-deleted photos for admin view.
     *
     * @param int $gibbonSchoolYearID
     * @return array
     */
    public function selectDeletedPhotos($gibbonSchoolYearID)
    {
        $data = ['gibbonSchoolYearID' => $gibbonSchoolYearID];
        $sql = "SELECT gibbonPhotoUpload.*,
                       deleter.preferredName AS deleterPreferredName,
                       deleter.surname AS deleterSurname
                FROM gibbonPhotoUpload
                LEFT JOIN gibbonPerson AS deleter ON deleter.gibbonPersonID = gibbonPhotoUpload.deletedByID
                WHERE gibbonSchoolYearID = :gibbonSchoolYearID
                AND deletedAt IS NOT NULL
                ORDER BY deletedAt DESC";

        return $this->db()->select($sql, $data)->fetchAll();
    }

    /**
     * Get expiring records count (for admin dashboard).
     *
     * @param int $retentionYears
     * @return int
     */
    public function countExpiringRecords($retentionYears = 5)
    {
        $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$retentionYears} years"));

        $data = ['cutoffDate' => $cutoffDate];
        $sql = "SELECT COUNT(*) as total
                FROM gibbonPhotoUpload
                WHERE deletedAt IS NOT NULL
                AND deletedAt < :cutoffDate";

        $result = $this->db()->selectOne($sql, $data);
        return $result['total'] ?? 0;
    }
}
