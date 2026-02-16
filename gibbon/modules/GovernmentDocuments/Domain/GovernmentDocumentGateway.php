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

namespace Gibbon\Module\GovernmentDocuments\Domain;

use Gibbon\Domain\Traits\TableAware;
use Gibbon\Domain\QueryCriteria;
use Gibbon\Domain\QueryableGateway;

/**
 * GovernmentDocumentGateway
 *
 * Gateway for government document CRUD operations, expiration queries, and compliance checklists.
 * Supports Quebec regulatory compliance for childcare document tracking.
 *
 * @version v1.0.00
 * @since   v1.0.00
 */
class GovernmentDocumentGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'gibbonGovernmentDocument';
    private static $primaryKey = 'gibbonGovernmentDocumentID';
    private static $searchableColumns = ['gibbonGovernmentDocument.documentNumber', 'gibbonGovernmentDocument.notes', 'gibbonPerson.preferredName', 'gibbonPerson.surname'];

    // =========================================================================
    // DOCUMENT QUERIES
    // =========================================================================

    /**
     * Query government documents with pagination support.
     *
     * @param QueryCriteria $criteria
     * @param int $gibbonSchoolYearID
     * @return \Gibbon\Domain\DataSet
     */
    public function queryDocuments(QueryCriteria $criteria, $gibbonSchoolYearID)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonGovernmentDocument.gibbonGovernmentDocumentID',
                'gibbonGovernmentDocument.gibbonGovernmentDocumentTypeID',
                'gibbonGovernmentDocument.gibbonPersonID',
                'gibbonGovernmentDocument.gibbonSchoolYearID',
                'gibbonGovernmentDocument.documentNumber',
                'gibbonGovernmentDocument.issueDate',
                'gibbonGovernmentDocument.expiryDate',
                'gibbonGovernmentDocument.filePath',
                'gibbonGovernmentDocument.originalFileName',
                'gibbonGovernmentDocument.fileSize',
                'gibbonGovernmentDocument.mimeType',
                'gibbonGovernmentDocument.status',
                'gibbonGovernmentDocument.verifiedAt',
                'gibbonGovernmentDocument.rejectionReason',
                'gibbonGovernmentDocument.notes',
                'gibbonGovernmentDocument.timestampCreated',
                'gibbonGovernmentDocumentType.name AS documentTypeName',
                'gibbonGovernmentDocumentType.nameDisplay AS documentTypeDisplay',
                'gibbonGovernmentDocumentType.category AS documentCategory',
                'gibbonGovernmentDocumentType.required AS documentRequired',
                'gibbonGovernmentDocumentType.expiryRequired',
                'gibbonPerson.preferredName',
                'gibbonPerson.surname',
                'gibbonPerson.image_240',
                'verifier.preferredName AS verifierPreferredName',
                'verifier.surname AS verifierSurname',
                'uploader.preferredName AS uploaderPreferredName',
                'uploader.surname AS uploaderSurname',
            ])
            ->innerJoin('gibbonGovernmentDocumentType', 'gibbonGovernmentDocument.gibbonGovernmentDocumentTypeID = gibbonGovernmentDocumentType.gibbonGovernmentDocumentTypeID')
            ->innerJoin('gibbonPerson', 'gibbonGovernmentDocument.gibbonPersonID = gibbonPerson.gibbonPersonID')
            ->leftJoin('gibbonPerson AS verifier', 'gibbonGovernmentDocument.verifiedByID = verifier.gibbonPersonID')
            ->leftJoin('gibbonPerson AS uploader', 'gibbonGovernmentDocument.uploadedByID = uploader.gibbonPersonID')
            ->where('gibbonGovernmentDocument.gibbonSchoolYearID = :gibbonSchoolYearID')
            ->bindValue('gibbonSchoolYearID', $gibbonSchoolYearID);

        $criteria->addFilterRules([
            'status' => function ($query, $status) {
                return $query
                    ->where('gibbonGovernmentDocument.status = :status')
                    ->bindValue('status', $status);
            },
            'category' => function ($query, $category) {
                return $query
                    ->where('gibbonGovernmentDocumentType.category = :category')
                    ->bindValue('category', $category);
            },
            'person' => function ($query, $gibbonPersonID) {
                return $query
                    ->where('gibbonGovernmentDocument.gibbonPersonID = :gibbonPersonID')
                    ->bindValue('gibbonPersonID', $gibbonPersonID);
            },
            'documentType' => function ($query, $gibbonGovernmentDocumentTypeID) {
                return $query
                    ->where('gibbonGovernmentDocument.gibbonGovernmentDocumentTypeID = :gibbonGovernmentDocumentTypeID')
                    ->bindValue('gibbonGovernmentDocumentTypeID', $gibbonGovernmentDocumentTypeID);
            },
        ]);

        return $this->runQuery($query, $criteria);
    }

    /**
     * Query documents by family members.
     *
     * @param QueryCriteria $criteria
     * @param int $gibbonFamilyID
     * @param int $gibbonSchoolYearID
     * @return \Gibbon\Domain\DataSet
     */
    public function queryDocumentsByFamily(QueryCriteria $criteria, $gibbonFamilyID, $gibbonSchoolYearID)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonGovernmentDocument.gibbonGovernmentDocumentID',
                'gibbonGovernmentDocument.gibbonGovernmentDocumentTypeID',
                'gibbonGovernmentDocument.gibbonPersonID',
                'gibbonGovernmentDocument.documentNumber',
                'gibbonGovernmentDocument.issueDate',
                'gibbonGovernmentDocument.expiryDate',
                'gibbonGovernmentDocument.filePath',
                'gibbonGovernmentDocument.originalFileName',
                'gibbonGovernmentDocument.status',
                'gibbonGovernmentDocument.verifiedAt',
                'gibbonGovernmentDocument.notes',
                'gibbonGovernmentDocument.timestampCreated',
                'gibbonGovernmentDocumentType.name AS documentTypeName',
                'gibbonGovernmentDocumentType.nameDisplay AS documentTypeDisplay',
                'gibbonGovernmentDocumentType.category AS documentCategory',
                'gibbonGovernmentDocumentType.required AS documentRequired',
                'gibbonGovernmentDocumentType.expiryRequired',
                'gibbonPerson.preferredName',
                'gibbonPerson.surname',
                'gibbonPerson.image_240',
            ])
            ->innerJoin('gibbonGovernmentDocumentType', 'gibbonGovernmentDocument.gibbonGovernmentDocumentTypeID = gibbonGovernmentDocumentType.gibbonGovernmentDocumentTypeID')
            ->innerJoin('gibbonPerson', 'gibbonGovernmentDocument.gibbonPersonID = gibbonPerson.gibbonPersonID')
            ->leftJoin('gibbonFamilyChild', 'gibbonPerson.gibbonPersonID = gibbonFamilyChild.gibbonPersonID')
            ->leftJoin('gibbonFamilyAdult', 'gibbonPerson.gibbonPersonID = gibbonFamilyAdult.gibbonPersonID')
            ->where('(gibbonFamilyChild.gibbonFamilyID = :gibbonFamilyID OR gibbonFamilyAdult.gibbonFamilyID = :gibbonFamilyID)')
            ->bindValue('gibbonFamilyID', $gibbonFamilyID)
            ->where('gibbonGovernmentDocument.gibbonSchoolYearID = :gibbonSchoolYearID')
            ->bindValue('gibbonSchoolYearID', $gibbonSchoolYearID)
            ->groupBy(['gibbonGovernmentDocument.gibbonGovernmentDocumentID']);

        return $this->runQuery($query, $criteria);
    }

    /**
     * Query documents that are expiring within a specified number of days.
     *
     * @param QueryCriteria $criteria
     * @param int $gibbonSchoolYearID
     * @param int $daysUntilExpiry Number of days from now to check for expiration
     * @return \Gibbon\Domain\DataSet
     */
    public function queryExpiringDocuments(QueryCriteria $criteria, $gibbonSchoolYearID, $daysUntilExpiry = 30)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonGovernmentDocument.gibbonGovernmentDocumentID',
                'gibbonGovernmentDocument.gibbonGovernmentDocumentTypeID',
                'gibbonGovernmentDocument.gibbonPersonID',
                'gibbonGovernmentDocument.documentNumber',
                'gibbonGovernmentDocument.expiryDate',
                'gibbonGovernmentDocument.status',
                'gibbonGovernmentDocumentType.name AS documentTypeName',
                'gibbonGovernmentDocumentType.nameDisplay AS documentTypeDisplay',
                'gibbonGovernmentDocumentType.category AS documentCategory',
                'gibbonGovernmentDocumentType.expiryWarningDays',
                'gibbonPerson.preferredName',
                'gibbonPerson.surname',
                'gibbonPerson.email',
                'DATEDIFF(gibbonGovernmentDocument.expiryDate, CURDATE()) AS daysUntilExpiry',
            ])
            ->innerJoin('gibbonGovernmentDocumentType', 'gibbonGovernmentDocument.gibbonGovernmentDocumentTypeID = gibbonGovernmentDocumentType.gibbonGovernmentDocumentTypeID')
            ->innerJoin('gibbonPerson', 'gibbonGovernmentDocument.gibbonPersonID = gibbonPerson.gibbonPersonID')
            ->where('gibbonGovernmentDocument.gibbonSchoolYearID = :gibbonSchoolYearID')
            ->bindValue('gibbonSchoolYearID', $gibbonSchoolYearID)
            ->where('gibbonGovernmentDocument.status = :status')
            ->bindValue('status', 'verified')
            ->where('gibbonGovernmentDocument.expiryDate IS NOT NULL')
            ->where('gibbonGovernmentDocument.expiryDate <= DATE_ADD(CURDATE(), INTERVAL :daysUntilExpiry DAY)')
            ->bindValue('daysUntilExpiry', $daysUntilExpiry)
            ->where('gibbonGovernmentDocument.expiryDate >= CURDATE()')
            ->orderBy(['gibbonGovernmentDocument.expiryDate ASC']);

        return $this->runQuery($query, $criteria);
    }

    /**
     * Select documents expiring within specified days for notification processing.
     *
     * @param int $gibbonSchoolYearID
     * @param int $daysUntilExpiry
     * @return array
     */
    public function selectExpiringDocuments($gibbonSchoolYearID, $daysUntilExpiry = 30)
    {
        $data = [
            'gibbonSchoolYearID' => $gibbonSchoolYearID,
            'daysUntilExpiry' => (int) $daysUntilExpiry,
        ];
        $sql = "SELECT d.*,
                       dt.name AS documentTypeName,
                       dt.nameDisplay AS documentTypeDisplay,
                       dt.category AS documentCategory,
                       dt.expiryWarningDays,
                       p.preferredName, p.surname, p.email,
                       DATEDIFF(d.expiryDate, CURDATE()) AS daysUntilExpiry
                FROM gibbonGovernmentDocument d
                INNER JOIN gibbonGovernmentDocumentType dt ON d.gibbonGovernmentDocumentTypeID = dt.gibbonGovernmentDocumentTypeID
                INNER JOIN gibbonPerson p ON d.gibbonPersonID = p.gibbonPersonID
                WHERE d.gibbonSchoolYearID = :gibbonSchoolYearID
                AND d.status = 'verified'
                AND d.expiryDate IS NOT NULL
                AND d.expiryDate <= DATE_ADD(CURDATE(), INTERVAL :daysUntilExpiry DAY)
                AND d.expiryDate >= CURDATE()
                ORDER BY d.expiryDate ASC";

        return $this->db()->select($sql, $data)->fetchAll();
    }

    /**
     * Query missing required documents for compliance tracking.
     *
     * @param QueryCriteria $criteria
     * @param int $gibbonSchoolYearID
     * @param string|null $category Filter by category (Child, Parent, Guardian, Staff)
     * @return \Gibbon\Domain\DataSet
     */
    public function queryMissingDocuments(QueryCriteria $criteria, $gibbonSchoolYearID, $category = null)
    {
        $query = $this
            ->newQuery()
            ->from('gibbonGovernmentDocumentType')
            ->cols([
                'gibbonGovernmentDocumentType.gibbonGovernmentDocumentTypeID',
                'gibbonGovernmentDocumentType.name AS documentTypeName',
                'gibbonGovernmentDocumentType.nameDisplay AS documentTypeDisplay',
                'gibbonGovernmentDocumentType.category AS documentCategory',
                'gibbonGovernmentDocumentType.required',
                'gibbonPerson.gibbonPersonID',
                'gibbonPerson.preferredName',
                'gibbonPerson.surname',
                'gibbonPerson.email',
            ])
            ->innerJoin('gibbonStudentEnrolment', 'gibbonStudentEnrolment.gibbonSchoolYearID = :gibbonSchoolYearID')
            ->bindValue('gibbonSchoolYearID', $gibbonSchoolYearID)
            ->innerJoin('gibbonPerson', 'gibbonStudentEnrolment.gibbonPersonID = gibbonPerson.gibbonPersonID')
            ->leftJoin('gibbonGovernmentDocument', 'gibbonGovernmentDocument.gibbonPersonID = gibbonPerson.gibbonPersonID
                AND gibbonGovernmentDocument.gibbonGovernmentDocumentTypeID = gibbonGovernmentDocumentType.gibbonGovernmentDocumentTypeID
                AND gibbonGovernmentDocument.gibbonSchoolYearID = :gibbonSchoolYearID
                AND gibbonGovernmentDocument.status IN ("pending", "verified")')
            ->where('gibbonGovernmentDocumentType.active = :active')
            ->bindValue('active', 'Y')
            ->where('gibbonGovernmentDocumentType.required = :required')
            ->bindValue('required', 'Y')
            ->where("gibbonPerson.status = 'Full'")
            ->where('gibbonGovernmentDocument.gibbonGovernmentDocumentID IS NULL');

        if ($category !== null) {
            $query->where('gibbonGovernmentDocumentType.category = :category')
                  ->bindValue('category', $category);
        }

        return $this->runQuery($query, $criteria);
    }

    /**
     * Select missing documents for a specific person.
     *
     * @param int $gibbonPersonID
     * @param int $gibbonSchoolYearID
     * @param string|null $category
     * @return array
     */
    public function selectMissingDocumentsByPerson($gibbonPersonID, $gibbonSchoolYearID, $category = null)
    {
        $data = [
            'gibbonPersonID' => $gibbonPersonID,
            'gibbonSchoolYearID' => $gibbonSchoolYearID,
        ];
        $sql = "SELECT dt.gibbonGovernmentDocumentTypeID,
                       dt.name AS documentTypeName,
                       dt.nameDisplay AS documentTypeDisplay,
                       dt.category AS documentCategory,
                       dt.required
                FROM gibbonGovernmentDocumentType dt
                LEFT JOIN gibbonGovernmentDocument d ON d.gibbonGovernmentDocumentTypeID = dt.gibbonGovernmentDocumentTypeID
                    AND d.gibbonPersonID = :gibbonPersonID
                    AND d.gibbonSchoolYearID = :gibbonSchoolYearID
                    AND d.status IN ('pending', 'verified')
                WHERE dt.active = 'Y'
                AND dt.required = 'Y'
                AND d.gibbonGovernmentDocumentID IS NULL";

        if ($category !== null) {
            $data['category'] = $category;
            $sql .= " AND dt.category = :category";
        }

        $sql .= " ORDER BY dt.sequenceNumber ASC, dt.nameDisplay ASC";

        return $this->db()->select($sql, $data)->fetchAll();
    }

    // =========================================================================
    // CHECKLIST OPERATIONS
    // =========================================================================

    /**
     * Get comprehensive document checklist for a family.
     *
     * @param int $gibbonFamilyID
     * @param int $gibbonSchoolYearID
     * @return array
     */
    public function getChecklistByFamily($gibbonFamilyID, $gibbonSchoolYearID)
    {
        $data = [
            'gibbonFamilyID' => $gibbonFamilyID,
            'gibbonSchoolYearID' => $gibbonSchoolYearID,
        ];

        // Get all family members (children and adults)
        $sqlMembers = "SELECT DISTINCT p.gibbonPersonID, p.preferredName, p.surname, p.image_240,
                              CASE
                                  WHEN fc.gibbonFamilyChildID IS NOT NULL THEN 'Child'
                                  WHEN fa.gibbonFamilyAdultID IS NOT NULL THEN 'Parent'
                                  ELSE 'Unknown'
                              END AS memberType
                       FROM gibbonPerson p
                       LEFT JOIN gibbonFamilyChild fc ON p.gibbonPersonID = fc.gibbonPersonID AND fc.gibbonFamilyID = :gibbonFamilyID
                       LEFT JOIN gibbonFamilyAdult fa ON p.gibbonPersonID = fa.gibbonPersonID AND fa.gibbonFamilyID = :gibbonFamilyID
                       WHERE (fc.gibbonFamilyID = :gibbonFamilyID OR fa.gibbonFamilyID = :gibbonFamilyID)
                       AND p.status = 'Full'
                       ORDER BY memberType, p.surname, p.preferredName";

        $members = $this->db()->select($sqlMembers, $data)->fetchAll();

        // Get all active document types
        $sqlTypes = "SELECT gibbonGovernmentDocumentTypeID, name, nameDisplay, description, category, required, expiryRequired
                     FROM gibbonGovernmentDocumentType
                     WHERE active = 'Y'
                     ORDER BY sequenceNumber ASC, nameDisplay ASC";

        $documentTypes = $this->db()->select($sqlTypes)->fetchAll();

        // Get all documents for family members
        $memberIDs = array_column($members, 'gibbonPersonID');
        if (empty($memberIDs)) {
            return [
                'members' => [],
                'documentTypes' => $documentTypes,
                'documents' => [],
                'summary' => [
                    'total' => 0,
                    'verified' => 0,
                    'pending' => 0,
                    'missing' => 0,
                    'expired' => 0,
                    'rejected' => 0,
                ],
            ];
        }

        $placeholders = implode(',', array_fill(0, count($memberIDs), '?'));
        $sqlDocuments = "SELECT d.gibbonGovernmentDocumentID, d.gibbonGovernmentDocumentTypeID, d.gibbonPersonID,
                                d.documentNumber, d.issueDate, d.expiryDate, d.filePath, d.originalFileName,
                                d.status, d.verifiedAt, d.rejectionReason, d.timestampCreated
                         FROM gibbonGovernmentDocument d
                         WHERE d.gibbonPersonID IN ($placeholders)
                         AND d.gibbonSchoolYearID = ?
                         ORDER BY d.timestampCreated DESC";

        $params = array_merge($memberIDs, [$gibbonSchoolYearID]);
        $documents = $this->db()->select($sqlDocuments, $params)->fetchAll();

        // Build summary statistics
        $summary = [
            'total' => 0,
            'verified' => 0,
            'pending' => 0,
            'missing' => 0,
            'expired' => 0,
            'rejected' => 0,
        ];

        // Index documents by person and type for lookup
        $documentIndex = [];
        foreach ($documents as $doc) {
            $key = $doc['gibbonPersonID'] . '_' . $doc['gibbonGovernmentDocumentTypeID'];
            if (!isset($documentIndex[$key]) || $doc['timestampCreated'] > $documentIndex[$key]['timestampCreated']) {
                $documentIndex[$key] = $doc;
            }
        }

        // Calculate statistics
        foreach ($members as $member) {
            foreach ($documentTypes as $type) {
                // Check if document type applies to this member type
                if ($type['category'] !== $member['memberType'] && $type['category'] !== 'Staff') {
                    continue;
                }

                $summary['total']++;
                $key = $member['gibbonPersonID'] . '_' . $type['gibbonGovernmentDocumentTypeID'];

                if (isset($documentIndex[$key])) {
                    $doc = $documentIndex[$key];
                    switch ($doc['status']) {
                        case 'verified':
                            // Check if expired
                            if ($doc['expiryDate'] && $doc['expiryDate'] < date('Y-m-d')) {
                                $summary['expired']++;
                            } else {
                                $summary['verified']++;
                            }
                            break;
                        case 'pending':
                            $summary['pending']++;
                            break;
                        case 'rejected':
                            $summary['rejected']++;
                            break;
                        case 'expired':
                            $summary['expired']++;
                            break;
                    }
                } else {
                    $summary['missing']++;
                }
            }
        }

        return [
            'members' => $members,
            'documentTypes' => $documentTypes,
            'documents' => $documents,
            'documentIndex' => $documentIndex,
            'summary' => $summary,
        ];
    }

    /**
     * Get checklist summary for all families (director view).
     *
     * @param int $gibbonSchoolYearID
     * @return array
     */
    public function selectChecklistSummaryAllFamilies($gibbonSchoolYearID)
    {
        $data = ['gibbonSchoolYearID' => $gibbonSchoolYearID];
        $sql = "SELECT f.gibbonFamilyID, f.name AS familyName,
                       COUNT(DISTINCT CASE WHEN d.status = 'verified' AND (d.expiryDate IS NULL OR d.expiryDate >= CURDATE()) THEN d.gibbonGovernmentDocumentID END) AS verifiedCount,
                       COUNT(DISTINCT CASE WHEN d.status = 'pending' THEN d.gibbonGovernmentDocumentID END) AS pendingCount,
                       COUNT(DISTINCT CASE WHEN d.status = 'rejected' THEN d.gibbonGovernmentDocumentID END) AS rejectedCount,
                       COUNT(DISTINCT CASE WHEN d.status = 'expired' OR (d.status = 'verified' AND d.expiryDate < CURDATE()) THEN d.gibbonGovernmentDocumentID END) AS expiredCount
                FROM gibbonFamily f
                INNER JOIN gibbonFamilyChild fc ON f.gibbonFamilyID = fc.gibbonFamilyID
                INNER JOIN gibbonStudentEnrolment se ON fc.gibbonPersonID = se.gibbonPersonID AND se.gibbonSchoolYearID = :gibbonSchoolYearID
                LEFT JOIN gibbonGovernmentDocument d ON (
                    d.gibbonPersonID IN (
                        SELECT gibbonPersonID FROM gibbonFamilyChild WHERE gibbonFamilyID = f.gibbonFamilyID
                        UNION
                        SELECT gibbonPersonID FROM gibbonFamilyAdult WHERE gibbonFamilyID = f.gibbonFamilyID
                    )
                    AND d.gibbonSchoolYearID = :gibbonSchoolYearID
                )
                GROUP BY f.gibbonFamilyID
                ORDER BY f.name ASC";

        return $this->db()->select($sql, $data)->fetchAll();
    }

    // =========================================================================
    // DOCUMENT CRUD OPERATIONS
    // =========================================================================

    /**
     * Get a single document by ID.
     *
     * @param int $gibbonGovernmentDocumentID
     * @return array|false
     */
    public function getDocumentByID($gibbonGovernmentDocumentID)
    {
        $data = ['gibbonGovernmentDocumentID' => $gibbonGovernmentDocumentID];
        $sql = "SELECT d.*,
                       dt.name AS documentTypeName,
                       dt.nameDisplay AS documentTypeDisplay,
                       dt.category AS documentCategory,
                       dt.required AS documentRequired,
                       dt.expiryRequired,
                       p.preferredName, p.surname,
                       verifier.preferredName AS verifierPreferredName,
                       verifier.surname AS verifierSurname,
                       uploader.preferredName AS uploaderPreferredName,
                       uploader.surname AS uploaderSurname
                FROM gibbonGovernmentDocument d
                INNER JOIN gibbonGovernmentDocumentType dt ON d.gibbonGovernmentDocumentTypeID = dt.gibbonGovernmentDocumentTypeID
                INNER JOIN gibbonPerson p ON d.gibbonPersonID = p.gibbonPersonID
                LEFT JOIN gibbonPerson verifier ON d.verifiedByID = verifier.gibbonPersonID
                LEFT JOIN gibbonPerson uploader ON d.uploadedByID = uploader.gibbonPersonID
                WHERE d.gibbonGovernmentDocumentID = :gibbonGovernmentDocumentID";

        return $this->db()->selectOne($sql, $data);
    }

    /**
     * Get document by person and type.
     *
     * @param int $gibbonPersonID
     * @param int $gibbonGovernmentDocumentTypeID
     * @param int $gibbonSchoolYearID
     * @return array|false
     */
    public function getDocumentByPersonAndType($gibbonPersonID, $gibbonGovernmentDocumentTypeID, $gibbonSchoolYearID)
    {
        $data = [
            'gibbonPersonID' => $gibbonPersonID,
            'gibbonGovernmentDocumentTypeID' => $gibbonGovernmentDocumentTypeID,
            'gibbonSchoolYearID' => $gibbonSchoolYearID,
        ];
        $sql = "SELECT d.*,
                       dt.name AS documentTypeName,
                       dt.nameDisplay AS documentTypeDisplay
                FROM gibbonGovernmentDocument d
                INNER JOIN gibbonGovernmentDocumentType dt ON d.gibbonGovernmentDocumentTypeID = dt.gibbonGovernmentDocumentTypeID
                WHERE d.gibbonPersonID = :gibbonPersonID
                AND d.gibbonGovernmentDocumentTypeID = :gibbonGovernmentDocumentTypeID
                AND d.gibbonSchoolYearID = :gibbonSchoolYearID
                ORDER BY d.timestampCreated DESC
                LIMIT 1";

        return $this->db()->selectOne($sql, $data);
    }

    /**
     * Insert a new government document.
     *
     * @param array $data Document data
     * @return int|false The new document ID or false on failure
     */
    public function insertDocument(array $data)
    {
        $fields = [
            'gibbonGovernmentDocumentTypeID',
            'gibbonPersonID',
            'gibbonSchoolYearID',
            'documentNumber',
            'issueDate',
            'expiryDate',
            'filePath',
            'originalFileName',
            'fileSize',
            'mimeType',
            'status',
            'notes',
            'uploadedByID',
        ];

        $insertData = array_intersect_key($data, array_flip($fields));

        // Default status to pending
        if (!isset($insertData['status'])) {
            $insertData['status'] = 'pending';
        }

        return $this->insert($insertData);
    }

    /**
     * Update document details.
     *
     * @param int $gibbonGovernmentDocumentID
     * @param array $data Updated data
     * @return bool
     */
    public function updateDocument($gibbonGovernmentDocumentID, array $data)
    {
        $allowedFields = [
            'documentNumber',
            'issueDate',
            'expiryDate',
            'filePath',
            'originalFileName',
            'fileSize',
            'mimeType',
            'status',
            'verifiedByID',
            'verifiedAt',
            'rejectionReason',
            'notes',
        ];

        $updateData = array_intersect_key($data, array_flip($allowedFields));

        if (empty($updateData)) {
            return false;
        }

        return $this->update($gibbonGovernmentDocumentID, $updateData);
    }

    // =========================================================================
    // VERIFICATION OPERATIONS
    // =========================================================================

    /**
     * Update document verification status.
     *
     * @param int $gibbonGovernmentDocumentID
     * @param string $status New status (verified, rejected, pending)
     * @param int $verifiedByID Staff person ID who verified
     * @param string|null $rejectionReason Reason for rejection (if rejected)
     * @return bool
     */
    public function updateVerificationStatus($gibbonGovernmentDocumentID, $status, $verifiedByID, $rejectionReason = null)
    {
        $data = [
            'gibbonGovernmentDocumentID' => $gibbonGovernmentDocumentID,
            'status' => $status,
            'verifiedByID' => $verifiedByID,
            'verifiedAt' => date('Y-m-d H:i:s'),
            'rejectionReason' => $rejectionReason,
        ];

        $sql = "UPDATE gibbonGovernmentDocument
                SET status = :status,
                    verifiedByID = :verifiedByID,
                    verifiedAt = :verifiedAt,
                    rejectionReason = :rejectionReason
                WHERE gibbonGovernmentDocumentID = :gibbonGovernmentDocumentID";

        return $this->db()->statement($sql, $data);
    }

    /**
     * Mark document as expired.
     *
     * @param int $gibbonGovernmentDocumentID
     * @return bool
     */
    public function markAsExpired($gibbonGovernmentDocumentID)
    {
        $data = ['gibbonGovernmentDocumentID' => $gibbonGovernmentDocumentID];
        $sql = "UPDATE gibbonGovernmentDocument
                SET status = 'expired'
                WHERE gibbonGovernmentDocumentID = :gibbonGovernmentDocumentID";

        return $this->db()->statement($sql, $data);
    }

    /**
     * Select documents that have expired but not yet marked as expired.
     *
     * @return array
     */
    public function selectExpiredDocumentsToUpdate()
    {
        $sql = "SELECT d.gibbonGovernmentDocumentID, d.gibbonPersonID,
                       dt.nameDisplay AS documentTypeDisplay
                FROM gibbonGovernmentDocument d
                INNER JOIN gibbonGovernmentDocumentType dt ON d.gibbonGovernmentDocumentTypeID = dt.gibbonGovernmentDocumentTypeID
                WHERE d.status = 'verified'
                AND d.expiryDate IS NOT NULL
                AND d.expiryDate < CURDATE()";

        return $this->db()->select($sql)->fetchAll();
    }

    // =========================================================================
    // AUDIT LOG OPERATIONS
    // =========================================================================

    /**
     * Insert an audit log entry.
     *
     * @param int $gibbonGovernmentDocumentID
     * @param int $gibbonPersonID Person performing the action
     * @param string $action Action performed
     * @param string|null $previousStatus
     * @param string|null $newStatus
     * @param string|null $details
     * @param string|null $ipAddress
     * @param string|null $userAgent
     * @return int|false
     */
    public function insertLog($gibbonGovernmentDocumentID, $gibbonPersonID, $action, $previousStatus = null, $newStatus = null, $details = null, $ipAddress = null, $userAgent = null)
    {
        $data = [
            'gibbonGovernmentDocumentID' => $gibbonGovernmentDocumentID,
            'gibbonPersonID' => $gibbonPersonID,
            'action' => $action,
            'previousStatus' => $previousStatus,
            'newStatus' => $newStatus,
            'details' => $details,
            'ipAddress' => $ipAddress,
            'userAgent' => $userAgent,
        ];

        $sql = "INSERT INTO gibbonGovernmentDocumentLog
                (gibbonGovernmentDocumentID, gibbonPersonID, action, previousStatus, newStatus, details, ipAddress, userAgent)
                VALUES (:gibbonGovernmentDocumentID, :gibbonPersonID, :action, :previousStatus, :newStatus, :details, :ipAddress, :userAgent)";

        $this->db()->statement($sql, $data);
        return $this->db()->getConnection()->lastInsertID();
    }

    /**
     * Get audit log for a document.
     *
     * @param int $gibbonGovernmentDocumentID
     * @return array
     */
    public function selectLogByDocument($gibbonGovernmentDocumentID)
    {
        $data = ['gibbonGovernmentDocumentID' => $gibbonGovernmentDocumentID];
        $sql = "SELECT l.*, p.preferredName, p.surname
                FROM gibbonGovernmentDocumentLog l
                INNER JOIN gibbonPerson p ON l.gibbonPersonID = p.gibbonPersonID
                WHERE l.gibbonGovernmentDocumentID = :gibbonGovernmentDocumentID
                ORDER BY l.timestampCreated DESC";

        return $this->db()->select($sql, $data)->fetchAll();
    }

    // =========================================================================
    // DOCUMENT TYPE OPERATIONS
    // =========================================================================

    /**
     * Get all active document types.
     *
     * @return array
     */
    public function selectActiveDocumentTypes()
    {
        $sql = "SELECT * FROM gibbonGovernmentDocumentType
                WHERE active = 'Y'
                ORDER BY sequenceNumber ASC, nameDisplay ASC";

        return $this->db()->select($sql)->fetchAll();
    }

    /**
     * Get document type by ID.
     *
     * @param int $gibbonGovernmentDocumentTypeID
     * @return array|false
     */
    public function getDocumentTypeByID($gibbonGovernmentDocumentTypeID)
    {
        $data = ['gibbonGovernmentDocumentTypeID' => $gibbonGovernmentDocumentTypeID];
        $sql = "SELECT * FROM gibbonGovernmentDocumentType
                WHERE gibbonGovernmentDocumentTypeID = :gibbonGovernmentDocumentTypeID";

        return $this->db()->selectOne($sql, $data);
    }

    /**
     * Get document type by name.
     *
     * @param string $name
     * @return array|false
     */
    public function getDocumentTypeByName($name)
    {
        $data = ['name' => $name];
        $sql = "SELECT * FROM gibbonGovernmentDocumentType
                WHERE name = :name";

        return $this->db()->selectOne($sql, $data);
    }

    // =========================================================================
    // STATISTICS
    // =========================================================================

    /**
     * Get document statistics for a school year.
     *
     * @param int $gibbonSchoolYearID
     * @return array
     */
    public function getDocumentStatistics($gibbonSchoolYearID)
    {
        $data = ['gibbonSchoolYearID' => $gibbonSchoolYearID];
        $sql = "SELECT
                    COUNT(*) as totalDocuments,
                    SUM(CASE WHEN status = 'verified' AND (expiryDate IS NULL OR expiryDate >= CURDATE()) THEN 1 ELSE 0 END) as verifiedCount,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pendingCount,
                    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejectedCount,
                    SUM(CASE WHEN status = 'expired' OR (status = 'verified' AND expiryDate < CURDATE()) THEN 1 ELSE 0 END) as expiredCount,
                    SUM(CASE WHEN expiryDate IS NOT NULL AND expiryDate BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as expiringSoonCount
                FROM gibbonGovernmentDocument
                WHERE gibbonSchoolYearID = :gibbonSchoolYearID";

        return $this->db()->selectOne($sql, $data) ?: [
            'totalDocuments' => 0,
            'verifiedCount' => 0,
            'pendingCount' => 0,
            'rejectedCount' => 0,
            'expiredCount' => 0,
            'expiringSoonCount' => 0,
        ];
    }

    /**
     * Get compliance rate (percentage of required documents that are verified).
     *
     * @param int $gibbonSchoolYearID
     * @return float
     */
    public function getComplianceRate($gibbonSchoolYearID)
    {
        $stats = $this->getDocumentStatistics($gibbonSchoolYearID);
        $total = $stats['verifiedCount'] + $stats['pendingCount'] + $stats['rejectedCount'] + $stats['expiredCount'];

        if ($total === 0) {
            return 0.0;
        }

        return round(($stats['verifiedCount'] / $total) * 100, 2);
    }

    // =========================================================================
    // SERVICE AGREEMENT INTEGRATION
    // =========================================================================

    /**
     * Check if a family has all critical documents required for service agreement.
     *
     * Critical documents for Quebec childcare compliance:
     * - Each child: birth certificate OR citizenship proof (verified, not expired)
     * - At least one parent: government ID (verified, not expired)
     * - Each child: health insurance card (verified, not expired)
     *
     * @param int $gibbonFamilyID
     * @param int $gibbonSchoolYearID
     * @return array{hasAll: bool, missingDocuments: array, childrenChecked: int, parentsChecked: int}
     */
    public function hasCriticalDocuments($gibbonFamilyID, $gibbonSchoolYearID)
    {
        $missingDocuments = [];
        $childrenChecked = 0;
        $parentsChecked = 0;

        // Get all family members (children enrolled in this school year and adults)
        $data = [
            'gibbonFamilyID' => $gibbonFamilyID,
            'gibbonSchoolYearID' => $gibbonSchoolYearID,
        ];

        // Get enrolled children in this family
        $sqlChildren = "SELECT p.gibbonPersonID, p.preferredName, p.surname
                        FROM gibbonPerson p
                        INNER JOIN gibbonFamilyChild fc ON p.gibbonPersonID = fc.gibbonPersonID
                        INNER JOIN gibbonStudentEnrolment se ON p.gibbonPersonID = se.gibbonPersonID
                            AND se.gibbonSchoolYearID = :gibbonSchoolYearID
                        WHERE fc.gibbonFamilyID = :gibbonFamilyID
                        AND p.status = 'Full'";

        $children = $this->db()->select($sqlChildren, $data)->fetchAll();
        $childrenChecked = count($children);

        // Get adults in this family
        $sqlAdults = "SELECT p.gibbonPersonID, p.preferredName, p.surname
                      FROM gibbonPerson p
                      INNER JOIN gibbonFamilyAdult fa ON p.gibbonPersonID = fa.gibbonPersonID
                      WHERE fa.gibbonFamilyID = :gibbonFamilyID
                      AND p.status = 'Full'";

        $adults = $this->db()->select($sqlAdults, ['gibbonFamilyID' => $gibbonFamilyID])->fetchAll();
        $parentsChecked = count($adults);

        // Define critical document types by name
        $childIdentityDocs = ['child_birth_certificate', 'child_citizenship_proof'];
        $parentIdDoc = 'parent_id';
        $healthCardDoc = 'health_card';

        // Check each child for required documents
        foreach ($children as $child) {
            $childName = $child['preferredName'] . ' ' . $child['surname'];
            $personID = $child['gibbonPersonID'];

            // Check for child identity document (birth certificate OR citizenship proof)
            $hasIdentity = $this->hasVerifiedDocument($personID, $childIdentityDocs, $gibbonSchoolYearID);
            if (!$hasIdentity) {
                $missingDocuments[] = [
                    'personID' => $personID,
                    'personName' => $childName,
                    'documentType' => 'child_identity',
                    'documentTypeDisplay' => 'Birth Certificate or Citizenship Proof',
                    'category' => 'Child',
                ];
            }

            // Check for health card
            $hasHealthCard = $this->hasVerifiedDocument($personID, [$healthCardDoc], $gibbonSchoolYearID);
            if (!$hasHealthCard) {
                $missingDocuments[] = [
                    'personID' => $personID,
                    'personName' => $childName,
                    'documentType' => 'health_card',
                    'documentTypeDisplay' => 'Health Insurance Card',
                    'category' => 'Child',
                ];
            }
        }

        // Check if at least one parent has a verified government ID
        $hasParentID = false;
        foreach ($adults as $adult) {
            if ($this->hasVerifiedDocument($adult['gibbonPersonID'], [$parentIdDoc], $gibbonSchoolYearID)) {
                $hasParentID = true;
                break;
            }
        }

        if (!$hasParentID && count($adults) > 0) {
            $missingDocuments[] = [
                'personID' => null,
                'personName' => 'Family',
                'documentType' => 'parent_id',
                'documentTypeDisplay' => 'Parent Government ID (at least one parent)',
                'category' => 'Parent',
            ];
        }

        return [
            'hasAll' => empty($missingDocuments),
            'missingDocuments' => $missingDocuments,
            'childrenChecked' => $childrenChecked,
            'parentsChecked' => $parentsChecked,
        ];
    }

    /**
     * Check if a person has a verified and non-expired document of specified types.
     *
     * @param int $gibbonPersonID
     * @param array $documentTypeNames Array of document type names to check (OR condition)
     * @param int $gibbonSchoolYearID
     * @return bool
     */
    private function hasVerifiedDocument($gibbonPersonID, array $documentTypeNames, $gibbonSchoolYearID)
    {
        if (empty($documentTypeNames)) {
            return false;
        }

        $placeholders = implode(',', array_fill(0, count($documentTypeNames), '?'));
        $params = array_merge(
            [$gibbonPersonID, $gibbonSchoolYearID],
            $documentTypeNames
        );

        $sql = "SELECT COUNT(*) as count
                FROM gibbonGovernmentDocument d
                INNER JOIN gibbonGovernmentDocumentType dt ON d.gibbonGovernmentDocumentTypeID = dt.gibbonGovernmentDocumentTypeID
                WHERE d.gibbonPersonID = ?
                AND d.gibbonSchoolYearID = ?
                AND dt.name IN ($placeholders)
                AND d.status = 'verified'
                AND (d.expiryDate IS NULL OR d.expiryDate >= CURDATE())";

        $result = $this->db()->selectOne($sql, $params);

        return $result && (int) $result['count'] > 0;
    }

    /**
     * Get list of critical document types for service agreement validation.
     *
     * @return array
     */
    public function getCriticalDocumentTypes()
    {
        return [
            'child' => [
                'identity' => ['child_birth_certificate', 'child_citizenship_proof'],
                'health' => ['health_card'],
            ],
            'parent' => [
                'id' => ['parent_id'],
            ],
        ];
    }
}
