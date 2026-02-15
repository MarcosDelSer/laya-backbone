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
 * PhotoTagGateway
 *
 * Gateway for photo-child tagging operations.
 * Links photos to children for gallery filtering.
 *
 * @version v1.0.00
 * @since   v1.0.00
 */
class PhotoTagGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'gibbonPhotoTag';
    private static $primaryKey = 'gibbonPhotoTagID';
    private static $searchableColumns = [];

    /**
     * Query tags with pagination support.
     *
     * @param QueryCriteria $criteria
     * @param int $gibbonPhotoUploadID
     * @return \Gibbon\Domain\DataSet
     */
    public function queryTagsByPhoto(QueryCriteria $criteria, $gibbonPhotoUploadID)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonPhotoTag.gibbonPhotoTagID',
                'gibbonPhotoTag.gibbonPhotoUploadID',
                'gibbonPhotoTag.gibbonPersonID',
                'gibbonPhotoTag.taggedByID',
                'gibbonPhotoTag.timestampCreated',
                'child.preferredName AS childPreferredName',
                'child.surname AS childSurname',
                'child.image_240 AS childImage',
                'tagger.preferredName AS taggerPreferredName',
                'tagger.surname AS taggerSurname',
            ])
            ->innerJoin('gibbonPerson AS child', 'child.gibbonPersonID = gibbonPhotoTag.gibbonPersonID')
            ->leftJoin('gibbonPerson AS tagger', 'tagger.gibbonPersonID = gibbonPhotoTag.taggedByID')
            ->where('gibbonPhotoTag.gibbonPhotoUploadID = :gibbonPhotoUploadID')
            ->bindValue('gibbonPhotoUploadID', $gibbonPhotoUploadID);

        return $this->runQuery($query, $criteria);
    }

    /**
     * Get all tags for a specific photo.
     *
     * @param int $gibbonPhotoUploadID
     * @return array
     */
    public function selectTagsByPhoto($gibbonPhotoUploadID)
    {
        $data = ['gibbonPhotoUploadID' => $gibbonPhotoUploadID];
        $sql = "SELECT gibbonPhotoTag.*,
                       child.preferredName AS childPreferredName,
                       child.surname AS childSurname,
                       child.image_240 AS childImage
                FROM gibbonPhotoTag
                INNER JOIN gibbonPerson AS child ON child.gibbonPersonID = gibbonPhotoTag.gibbonPersonID
                WHERE gibbonPhotoTag.gibbonPhotoUploadID = :gibbonPhotoUploadID
                ORDER BY child.surname, child.preferredName";

        return $this->db()->select($sql, $data)->fetchAll();
    }

    /**
     * Get all photos tagged with a specific child.
     *
     * @param int $gibbonPersonID Child's person ID
     * @return array
     */
    public function selectPhotosByChild($gibbonPersonID)
    {
        $data = ['gibbonPersonID' => $gibbonPersonID];
        $sql = "SELECT gibbonPhotoTag.gibbonPhotoUploadID,
                       gibbonPhotoTag.timestampCreated AS tagTimestamp,
                       gibbonPhotoUpload.filename,
                       gibbonPhotoUpload.filePath,
                       gibbonPhotoUpload.caption,
                       gibbonPhotoUpload.timestampCreated AS uploadTimestamp
                FROM gibbonPhotoTag
                INNER JOIN gibbonPhotoUpload ON gibbonPhotoUpload.gibbonPhotoUploadID = gibbonPhotoTag.gibbonPhotoUploadID
                WHERE gibbonPhotoTag.gibbonPersonID = :gibbonPersonID
                AND gibbonPhotoUpload.deletedAt IS NULL
                ORDER BY gibbonPhotoUpload.timestampCreated DESC";

        return $this->db()->select($sql, $data)->fetchAll();
    }

    /**
     * Get photos tagged with child for parent viewing.
     * Only returns photos shared with parents.
     *
     * @param int $gibbonPersonID Child's person ID
     * @param int $gibbonSchoolYearID
     * @return array
     */
    public function selectPhotosForParentByChild($gibbonPersonID, $gibbonSchoolYearID)
    {
        $data = [
            'gibbonPersonID' => $gibbonPersonID,
            'gibbonSchoolYearID' => $gibbonSchoolYearID,
        ];
        $sql = "SELECT gibbonPhotoTag.gibbonPhotoUploadID,
                       gibbonPhotoUpload.filename,
                       gibbonPhotoUpload.filePath,
                       gibbonPhotoUpload.caption,
                       gibbonPhotoUpload.timestampCreated
                FROM gibbonPhotoTag
                INNER JOIN gibbonPhotoUpload ON gibbonPhotoUpload.gibbonPhotoUploadID = gibbonPhotoTag.gibbonPhotoUploadID
                WHERE gibbonPhotoTag.gibbonPersonID = :gibbonPersonID
                AND gibbonPhotoUpload.gibbonSchoolYearID = :gibbonSchoolYearID
                AND gibbonPhotoUpload.sharedWithParent = 'Y'
                AND gibbonPhotoUpload.deletedAt IS NULL
                ORDER BY gibbonPhotoUpload.timestampCreated DESC";

        return $this->db()->select($sql, $data)->fetchAll();
    }

    /**
     * Check if a child is tagged in a photo.
     *
     * @param int $gibbonPhotoUploadID
     * @param int $gibbonPersonID
     * @return bool
     */
    public function isChildTagged($gibbonPhotoUploadID, $gibbonPersonID)
    {
        $data = [
            'gibbonPhotoUploadID' => $gibbonPhotoUploadID,
            'gibbonPersonID' => $gibbonPersonID,
        ];
        $sql = "SELECT COUNT(*) as count
                FROM gibbonPhotoTag
                WHERE gibbonPhotoUploadID = :gibbonPhotoUploadID
                AND gibbonPersonID = :gibbonPersonID";

        $result = $this->db()->selectOne($sql, $data);
        return ($result['count'] ?? 0) > 0;
    }

    /**
     * Insert a new tag.
     *
     * @param array $data Tag data
     * @return int|false The new tag ID or false on failure
     */
    public function insertTag(array $data)
    {
        $fields = [
            'gibbonPhotoUploadID',
            'gibbonPersonID',
            'taggedByID',
        ];

        $insertData = array_intersect_key($data, array_flip($fields));

        return $this->insert($insertData);
    }

    /**
     * Tag multiple children in a photo.
     *
     * @param int $gibbonPhotoUploadID
     * @param array $childIDs Array of gibbonPersonID values
     * @param int $taggedByID Person performing the tagging
     * @return int Number of tags created
     */
    public function tagChildren($gibbonPhotoUploadID, array $childIDs, $taggedByID)
    {
        $count = 0;
        foreach ($childIDs as $gibbonPersonID) {
            // Check if already tagged to avoid duplicate key error
            if (!$this->isChildTagged($gibbonPhotoUploadID, $gibbonPersonID)) {
                $result = $this->insertTag([
                    'gibbonPhotoUploadID' => $gibbonPhotoUploadID,
                    'gibbonPersonID' => $gibbonPersonID,
                    'taggedByID' => $taggedByID,
                ]);
                if ($result !== false) {
                    $count++;
                }
            }
        }
        return $count;
    }

    /**
     * Remove a tag by ID.
     *
     * @param int $gibbonPhotoTagID
     * @return bool
     */
    public function deleteTag($gibbonPhotoTagID)
    {
        $data = ['gibbonPhotoTagID' => $gibbonPhotoTagID];
        $sql = "DELETE FROM gibbonPhotoTag WHERE gibbonPhotoTagID = :gibbonPhotoTagID";

        return $this->db()->delete($sql, $data) > 0;
    }

    /**
     * Remove a specific child tag from a photo.
     *
     * @param int $gibbonPhotoUploadID
     * @param int $gibbonPersonID
     * @return bool
     */
    public function untagChild($gibbonPhotoUploadID, $gibbonPersonID)
    {
        $data = [
            'gibbonPhotoUploadID' => $gibbonPhotoUploadID,
            'gibbonPersonID' => $gibbonPersonID,
        ];
        $sql = "DELETE FROM gibbonPhotoTag
                WHERE gibbonPhotoUploadID = :gibbonPhotoUploadID
                AND gibbonPersonID = :gibbonPersonID";

        return $this->db()->delete($sql, $data) > 0;
    }

    /**
     * Remove all tags from a photo.
     *
     * @param int $gibbonPhotoUploadID
     * @return int Number of tags removed
     */
    public function deleteAllTagsForPhoto($gibbonPhotoUploadID)
    {
        $data = ['gibbonPhotoUploadID' => $gibbonPhotoUploadID];
        $sql = "DELETE FROM gibbonPhotoTag WHERE gibbonPhotoUploadID = :gibbonPhotoUploadID";

        return $this->db()->delete($sql, $data);
    }

    /**
     * Count tags for a photo.
     *
     * @param int $gibbonPhotoUploadID
     * @return int
     */
    public function countTagsByPhoto($gibbonPhotoUploadID)
    {
        $data = ['gibbonPhotoUploadID' => $gibbonPhotoUploadID];
        $sql = "SELECT COUNT(*) as total
                FROM gibbonPhotoTag
                WHERE gibbonPhotoUploadID = :gibbonPhotoUploadID";

        $result = $this->db()->selectOne($sql, $data);
        return $result['total'] ?? 0;
    }

    /**
     * Get children tagged by a specific person.
     *
     * @param int $taggedByID
     * @return array
     */
    public function selectTagsByTagger($taggedByID)
    {
        $data = ['taggedByID' => $taggedByID];
        $sql = "SELECT gibbonPhotoTag.*,
                       child.preferredName AS childPreferredName,
                       child.surname AS childSurname,
                       gibbonPhotoUpload.filename,
                       gibbonPhotoUpload.filePath
                FROM gibbonPhotoTag
                INNER JOIN gibbonPerson AS child ON child.gibbonPersonID = gibbonPhotoTag.gibbonPersonID
                INNER JOIN gibbonPhotoUpload ON gibbonPhotoUpload.gibbonPhotoUploadID = gibbonPhotoTag.gibbonPhotoUploadID
                WHERE gibbonPhotoTag.taggedByID = :taggedByID
                AND gibbonPhotoUpload.deletedAt IS NULL
                ORDER BY gibbonPhotoTag.timestampCreated DESC";

        return $this->db()->select($sql, $data)->fetchAll();
    }

    /**
     * Get tag statistics for a school year.
     *
     * @param int $gibbonSchoolYearID
     * @return array
     */
    public function getTagStatistics($gibbonSchoolYearID)
    {
        $data = ['gibbonSchoolYearID' => $gibbonSchoolYearID];
        $sql = "SELECT child.gibbonPersonID,
                       child.preferredName,
                       child.surname,
                       COUNT(gibbonPhotoTag.gibbonPhotoTagID) as tagCount
                FROM gibbonPhotoTag
                INNER JOIN gibbonPhotoUpload ON gibbonPhotoUpload.gibbonPhotoUploadID = gibbonPhotoTag.gibbonPhotoUploadID
                INNER JOIN gibbonPerson AS child ON child.gibbonPersonID = gibbonPhotoTag.gibbonPersonID
                WHERE gibbonPhotoUpload.gibbonSchoolYearID = :gibbonSchoolYearID
                AND gibbonPhotoUpload.deletedAt IS NULL
                GROUP BY child.gibbonPersonID, child.preferredName, child.surname
                ORDER BY tagCount DESC";

        return $this->db()->select($sql, $data)->fetchAll();
    }
}
