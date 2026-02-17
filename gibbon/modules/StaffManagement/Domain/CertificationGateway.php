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

namespace Gibbon\Module\StaffManagement\Domain;

use Gibbon\Domain\Traits\TableAware;
use Gibbon\Domain\QueryCriteria;
use Gibbon\Domain\QueryableGateway;

/**
 * Staff Certification Gateway
 *
 * Handles certification management with expiration tracking and renewal alerts.
 *
 * @version v1.0.00
 * @since   v1.0.00
 */
class CertificationGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'gibbonStaffCertification';
    private static $primaryKey = 'gibbonStaffCertificationID';

    private static $searchableColumns = ['gibbonPerson.preferredName', 'gibbonPerson.surname', 'gibbonStaffCertification.certificationName', 'gibbonStaffCertification.issuingOrganization', 'gibbonStaffCertification.certificateNumber'];

    /**
     * Query certifications with criteria support.
     *
     * @param QueryCriteria $criteria
     * @return DataSet
     */
    public function queryCertifications(QueryCriteria $criteria)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonStaffCertification.gibbonStaffCertificationID',
                'gibbonStaffCertification.gibbonPersonID',
                'gibbonStaffCertification.certificationType',
                'gibbonStaffCertification.certificationName',
                'gibbonStaffCertification.issuingOrganization',
                'gibbonStaffCertification.certificateNumber',
                'gibbonStaffCertification.issueDate',
                'gibbonStaffCertification.expiryDate',
                'gibbonStaffCertification.isRequired',
                'gibbonStaffCertification.status',
                'gibbonStaffCertification.documentPath',
                'gibbonStaffCertification.reminderSent',
                'gibbonStaffCertification.reminderSentDate',
                'gibbonStaffCertification.notes',
                'gibbonStaffCertification.timestampModified',
                'gibbonPerson.preferredName',
                'gibbonPerson.surname',
                'gibbonPerson.image_240',
                'gibbonPerson.email',
                'recordedBy.preferredName as recordedByName',
                'recordedBy.surname as recordedBySurname',
            ])
            ->innerJoin('gibbonPerson', 'gibbonStaffCertification.gibbonPersonID=gibbonPerson.gibbonPersonID')
            ->leftJoin('gibbonPerson as recordedBy', 'gibbonStaffCertification.recordedByID=recordedBy.gibbonPersonID');

        $criteria->addFilterRules([
            'status' => function ($query, $status) {
                return $query
                    ->where('gibbonStaffCertification.status=:status')
                    ->bindValue('status', $status);
            },
            'certificationType' => function ($query, $certificationType) {
                return $query
                    ->where('gibbonStaffCertification.certificationType=:certificationType')
                    ->bindValue('certificationType', $certificationType);
            },
            'isRequired' => function ($query, $isRequired) {
                return $query
                    ->where('gibbonStaffCertification.isRequired=:isRequired')
                    ->bindValue('isRequired', $isRequired);
            },
            'person' => function ($query, $gibbonPersonID) {
                return $query
                    ->where('gibbonStaffCertification.gibbonPersonID=:gibbonPersonID')
                    ->bindValue('gibbonPersonID', $gibbonPersonID);
            },
            'expiringBefore' => function ($query, $date) {
                return $query
                    ->where('gibbonStaffCertification.expiryDate <= :expiringBefore')
                    ->bindValue('expiringBefore', $date);
            },
            'expiringAfter' => function ($query, $date) {
                return $query
                    ->where('gibbonStaffCertification.expiryDate >= :expiringAfter')
                    ->bindValue('expiringAfter', $date);
            },
            'expired' => function ($query, $value) {
                if ($value == 'Y') {
                    return $query->where('gibbonStaffCertification.expiryDate < CURDATE()');
                } elseif ($value == 'N') {
                    return $query->where('(gibbonStaffCertification.expiryDate >= CURDATE() OR gibbonStaffCertification.expiryDate IS NULL)');
                }
                return $query;
            },
        ]);

        return $this->runQuery($query, $criteria);
    }

    /**
     * Query certifications for a specific person.
     *
     * @param QueryCriteria $criteria
     * @param int $gibbonPersonID
     * @return DataSet
     */
    public function queryCertificationsByPerson(QueryCriteria $criteria, $gibbonPersonID)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonStaffCertification.gibbonStaffCertificationID',
                'gibbonStaffCertification.certificationType',
                'gibbonStaffCertification.certificationName',
                'gibbonStaffCertification.issuingOrganization',
                'gibbonStaffCertification.certificateNumber',
                'gibbonStaffCertification.issueDate',
                'gibbonStaffCertification.expiryDate',
                'gibbonStaffCertification.isRequired',
                'gibbonStaffCertification.status',
                'gibbonStaffCertification.documentPath',
                'gibbonStaffCertification.reminderSent',
                'gibbonStaffCertification.notes',
                'gibbonStaffCertification.timestampModified',
                'recordedBy.preferredName as recordedByName',
                'recordedBy.surname as recordedBySurname',
            ])
            ->leftJoin('gibbonPerson as recordedBy', 'gibbonStaffCertification.recordedByID=recordedBy.gibbonPersonID')
            ->where('gibbonStaffCertification.gibbonPersonID=:gibbonPersonID')
            ->bindValue('gibbonPersonID', $gibbonPersonID);

        $criteria->addFilterRules([
            'status' => function ($query, $status) {
                return $query
                    ->where('gibbonStaffCertification.status=:status')
                    ->bindValue('status', $status);
            },
            'certificationType' => function ($query, $certificationType) {
                return $query
                    ->where('gibbonStaffCertification.certificationType=:certificationType')
                    ->bindValue('certificationType', $certificationType);
            },
        ]);

        return $this->runQuery($query, $criteria);
    }

    /**
     * Query certifications expiring within a date range (for renewal alerts).
     *
     * @param QueryCriteria $criteria
     * @param string $dateStart
     * @param string $dateEnd
     * @return DataSet
     */
    public function queryCertificationsExpiringSoon(QueryCriteria $criteria, $dateStart, $dateEnd)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonStaffCertification.gibbonStaffCertificationID',
                'gibbonStaffCertification.gibbonPersonID',
                'gibbonStaffCertification.certificationType',
                'gibbonStaffCertification.certificationName',
                'gibbonStaffCertification.issuingOrganization',
                'gibbonStaffCertification.expiryDate',
                'gibbonStaffCertification.isRequired',
                'gibbonStaffCertification.status',
                'gibbonStaffCertification.reminderSent',
                'gibbonStaffCertification.reminderSentDate',
                'gibbonPerson.preferredName',
                'gibbonPerson.surname',
                'gibbonPerson.email',
                'gibbonPerson.image_240',
                "DATEDIFF(gibbonStaffCertification.expiryDate, CURDATE()) as daysUntilExpiry",
            ])
            ->innerJoin('gibbonPerson', 'gibbonStaffCertification.gibbonPersonID=gibbonPerson.gibbonPersonID')
            ->innerJoin('gibbonStaffProfile', 'gibbonStaffCertification.gibbonPersonID=gibbonStaffProfile.gibbonPersonID')
            ->where("gibbonStaffCertification.status='Valid'")
            ->where("gibbonStaffProfile.status='Active'")
            ->where('gibbonStaffCertification.expiryDate >= :dateStart')
            ->bindValue('dateStart', $dateStart)
            ->where('gibbonStaffCertification.expiryDate <= :dateEnd')
            ->bindValue('dateEnd', $dateEnd)
            ->orderBy(['gibbonStaffCertification.expiryDate ASC']);

        return $this->runQuery($query, $criteria);
    }

    /**
     * Get certification by ID with full details.
     *
     * @param int $gibbonStaffCertificationID
     * @return array
     */
    public function getCertificationByID($gibbonStaffCertificationID)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols([
                'gibbonStaffCertification.*',
                'gibbonPerson.preferredName',
                'gibbonPerson.surname',
                'gibbonPerson.email',
                'gibbonPerson.image_240',
                'recordedBy.preferredName as recordedByName',
                'recordedBy.surname as recordedBySurname',
            ])
            ->innerJoin('gibbonPerson', 'gibbonStaffCertification.gibbonPersonID=gibbonPerson.gibbonPersonID')
            ->leftJoin('gibbonPerson as recordedBy', 'gibbonStaffCertification.recordedByID=recordedBy.gibbonPersonID')
            ->where('gibbonStaffCertification.gibbonStaffCertificationID=:gibbonStaffCertificationID')
            ->bindValue('gibbonStaffCertificationID', $gibbonStaffCertificationID);

        $result = $this->runSelect($query);
        return $result->isNotEmpty() ? $result->fetch() : [];
    }

    /**
     * Select certifications that have expired for active staff.
     *
     * @return \Gibbon\Database\Result
     */
    public function selectExpiredCertifications()
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols([
                'gibbonStaffCertification.gibbonStaffCertificationID',
                'gibbonStaffCertification.gibbonPersonID',
                'gibbonStaffCertification.certificationType',
                'gibbonStaffCertification.certificationName',
                'gibbonStaffCertification.expiryDate',
                'gibbonStaffCertification.isRequired',
                'gibbonPerson.preferredName',
                'gibbonPerson.surname',
                'gibbonPerson.email',
                "DATEDIFF(CURDATE(), gibbonStaffCertification.expiryDate) as daysExpired",
            ])
            ->innerJoin('gibbonPerson', 'gibbonStaffCertification.gibbonPersonID=gibbonPerson.gibbonPersonID')
            ->innerJoin('gibbonStaffProfile', 'gibbonStaffCertification.gibbonPersonID=gibbonStaffProfile.gibbonPersonID')
            ->where("gibbonStaffProfile.status='Active'")
            ->where('gibbonStaffCertification.expiryDate < CURDATE()')
            ->where("gibbonStaffCertification.status='Valid'")
            ->orderBy(['gibbonStaffCertification.expiryDate ASC']);

        return $this->runSelect($query);
    }

    /**
     * Select certifications that need renewal reminders sent.
     *
     * @param int $warningDays Number of days before expiry to send reminder
     * @return \Gibbon\Database\Result
     */
    public function selectCertificationsNeedingRenewalReminder($warningDays)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols([
                'gibbonStaffCertification.gibbonStaffCertificationID',
                'gibbonStaffCertification.gibbonPersonID',
                'gibbonStaffCertification.certificationType',
                'gibbonStaffCertification.certificationName',
                'gibbonStaffCertification.expiryDate',
                'gibbonStaffCertification.isRequired',
                'gibbonPerson.preferredName',
                'gibbonPerson.surname',
                'gibbonPerson.email',
                "DATEDIFF(gibbonStaffCertification.expiryDate, CURDATE()) as daysUntilExpiry",
            ])
            ->innerJoin('gibbonPerson', 'gibbonStaffCertification.gibbonPersonID=gibbonPerson.gibbonPersonID')
            ->innerJoin('gibbonStaffProfile', 'gibbonStaffCertification.gibbonPersonID=gibbonStaffProfile.gibbonPersonID')
            ->where("gibbonStaffProfile.status='Active'")
            ->where("gibbonStaffCertification.status='Valid'")
            ->where("gibbonStaffCertification.reminderSent='N'")
            ->where('gibbonStaffCertification.expiryDate IS NOT NULL')
            ->where('gibbonStaffCertification.expiryDate >= CURDATE()')
            ->where('DATEDIFF(gibbonStaffCertification.expiryDate, CURDATE()) <= :warningDays')
            ->bindValue('warningDays', $warningDays)
            ->orderBy(['gibbonStaffCertification.expiryDate ASC']);

        return $this->runSelect($query);
    }

    /**
     * Mark a certification reminder as sent.
     *
     * @param int $gibbonStaffCertificationID
     * @return bool
     */
    public function markReminderSent($gibbonStaffCertificationID)
    {
        return $this->update($gibbonStaffCertificationID, [
            'reminderSent' => 'Y',
            'reminderSentDate' => date('Y-m-d'),
        ]);
    }

    /**
     * Update certification status to expired for overdue certifications.
     *
     * @return int Number of records updated
     */
    public function updateExpiredCertifications()
    {
        $data = [];
        $sql = "UPDATE gibbonStaffCertification
                SET status='Expired'
                WHERE status='Valid'
                AND expiryDate IS NOT NULL
                AND expiryDate < CURDATE()";

        return $this->db()->executeQuery($data, $sql);
    }

    /**
     * Get certification summary statistics.
     *
     * @return array
     */
    public function getCertificationSummaryStatistics()
    {
        $data = [];
        $sql = "SELECT
                    SUM(CASE WHEN sc.status='Valid' THEN 1 ELSE 0 END) as totalValid,
                    SUM(CASE WHEN sc.status='Expired' THEN 1 ELSE 0 END) as totalExpired,
                    SUM(CASE WHEN sc.status='Pending' THEN 1 ELSE 0 END) as totalPending,
                    SUM(CASE WHEN sc.status='Revoked' THEN 1 ELSE 0 END) as totalRevoked,
                    SUM(CASE WHEN sc.isRequired='Y' AND sc.status='Valid' THEN 1 ELSE 0 END) as totalRequiredValid,
                    SUM(CASE WHEN sc.isRequired='Y' AND sc.status='Expired' THEN 1 ELSE 0 END) as totalRequiredExpired,
                    SUM(CASE WHEN sc.expiryDate IS NOT NULL AND sc.expiryDate >= CURDATE() AND DATEDIFF(sc.expiryDate, CURDATE()) <= 30 AND sc.status='Valid' THEN 1 ELSE 0 END) as expiringSoon
                FROM gibbonStaffCertification sc
                INNER JOIN gibbonStaffProfile sp ON sc.gibbonPersonID=sp.gibbonPersonID
                WHERE sp.status='Active'";

        return $this->db()->selectOne($sql, $data) ?: [
            'totalValid' => 0,
            'totalExpired' => 0,
            'totalPending' => 0,
            'totalRevoked' => 0,
            'totalRequiredValid' => 0,
            'totalRequiredExpired' => 0,
            'expiringSoon' => 0,
        ];
    }

    /**
     * Get certification count by type for reporting.
     *
     * @return \Gibbon\Database\Result
     */
    public function selectCertificationCountByType()
    {
        $data = [];
        $sql = "SELECT
                    sc.certificationType,
                    SUM(CASE WHEN sc.status='Valid' THEN 1 ELSE 0 END) as validCount,
                    SUM(CASE WHEN sc.status='Expired' THEN 1 ELSE 0 END) as expiredCount,
                    SUM(CASE WHEN sc.status='Pending' THEN 1 ELSE 0 END) as pendingCount,
                    COUNT(*) as totalCount
                FROM gibbonStaffCertification sc
                INNER JOIN gibbonStaffProfile sp ON sc.gibbonPersonID=sp.gibbonPersonID
                WHERE sp.status='Active'
                GROUP BY sc.certificationType
                ORDER BY totalCount DESC";

        return $this->db()->select($sql, $data);
    }

    /**
     * Select staff who are missing required certifications.
     *
     * @param array $requiredCertificationTypes Array of required certification type ENUMs
     * @return \Gibbon\Database\Result
     */
    public function selectStaffMissingRequiredCertifications($requiredCertificationTypes)
    {
        if (empty($requiredCertificationTypes)) {
            return $this->db()->select("SELECT NULL WHERE FALSE", []);
        }

        $placeholders = [];
        $data = [];
        foreach ($requiredCertificationTypes as $index => $type) {
            $placeholders[] = ':type' . $index;
            $data['type' . $index] = $type;
        }
        $placeholderList = implode(',', $placeholders);

        $sql = "SELECT
                    sp.gibbonPersonID,
                    p.preferredName,
                    p.surname,
                    p.email,
                    sp.position,
                    GROUP_CONCAT(DISTINCT rt.requiredType) as missingTypes
                FROM gibbonStaffProfile sp
                INNER JOIN gibbonPerson p ON sp.gibbonPersonID=p.gibbonPersonID
                CROSS JOIN (SELECT DISTINCT certificationType as requiredType FROM gibbonStaffCertification WHERE certificationType IN ({$placeholderList})) rt
                LEFT JOIN gibbonStaffCertification sc ON sp.gibbonPersonID=sc.gibbonPersonID
                    AND sc.certificationType=rt.requiredType
                    AND sc.status='Valid'
                WHERE sp.status='Active'
                AND sc.gibbonStaffCertificationID IS NULL
                GROUP BY sp.gibbonPersonID, p.preferredName, p.surname, p.email, sp.position
                ORDER BY p.surname, p.preferredName";

        return $this->db()->select($sql, $data);
    }

    /**
     * Check if a staff member has a valid certification of a specific type.
     *
     * @param int $gibbonPersonID
     * @param string $certificationType
     * @return bool
     */
    public function hasValidCertification($gibbonPersonID, $certificationType)
    {
        $data = ['gibbonPersonID' => $gibbonPersonID, 'certificationType' => $certificationType];
        $sql = "SELECT COUNT(*) FROM gibbonStaffCertification
                WHERE gibbonPersonID=:gibbonPersonID
                AND certificationType=:certificationType
                AND status='Valid'
                AND (expiryDate IS NULL OR expiryDate >= CURDATE())";

        return $this->db()->selectOne($sql, $data) > 0;
    }

    /**
     * Get all valid certifications for a person (for compliance checks).
     *
     * @param int $gibbonPersonID
     * @return \Gibbon\Database\Result
     */
    public function selectValidCertificationsByPerson($gibbonPersonID)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols([
                'gibbonStaffCertification.gibbonStaffCertificationID',
                'gibbonStaffCertification.certificationType',
                'gibbonStaffCertification.certificationName',
                'gibbonStaffCertification.expiryDate',
                'gibbonStaffCertification.isRequired',
            ])
            ->where('gibbonStaffCertification.gibbonPersonID=:gibbonPersonID')
            ->bindValue('gibbonPersonID', $gibbonPersonID)
            ->where("gibbonStaffCertification.status='Valid'")
            ->where('(gibbonStaffCertification.expiryDate IS NULL OR gibbonStaffCertification.expiryDate >= CURDATE())')
            ->orderBy(['gibbonStaffCertification.certificationType ASC']);

        return $this->runSelect($query);
    }

    /**
     * Get all unique issuing organizations for dropdown.
     *
     * @return \Gibbon\Database\Result
     */
    public function selectUniqueIssuingOrganizations()
    {
        $data = [];
        $sql = "SELECT DISTINCT issuingOrganization FROM gibbonStaffCertification WHERE issuingOrganization IS NOT NULL AND issuingOrganization <> '' ORDER BY issuingOrganization";

        return $this->db()->select($sql, $data);
    }
}
