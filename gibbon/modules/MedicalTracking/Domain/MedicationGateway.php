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

namespace Gibbon\Module\MedicalTracking\Domain;

use Gibbon\Domain\Traits\TableAware;
use Gibbon\Domain\QueryCriteria;
use Gibbon\Domain\QueryableGateway;

/**
 * Medical Tracking Medication Gateway
 *
 * Handles medication records for children including prescriptions, dosage schedules,
 * administration logs, and expiration monitoring for childcare settings.
 *
 * @version v1.0.00
 * @since   v1.0.00
 */
class MedicationGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'gibbonMedicalMedication';
    private static $primaryKey = 'gibbonMedicalMedicationID';

    private static $searchableColumns = ['gibbonPerson.preferredName', 'gibbonPerson.surname', 'gibbonMedicalMedication.medicationName', 'gibbonMedicalMedication.notes'];

    /**
     * Query medication records with criteria support.
     *
     * @param QueryCriteria $criteria
     * @return DataSet
     */
    public function queryMedications(QueryCriteria $criteria)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonMedicalMedication.gibbonMedicalMedicationID',
                'gibbonMedicalMedication.gibbonPersonID',
                'gibbonMedicalMedication.medicationName',
                'gibbonMedicalMedication.medicationType',
                'gibbonMedicalMedication.dosage',
                'gibbonMedicalMedication.frequency',
                'gibbonMedicalMedication.route',
                'gibbonMedicalMedication.prescribedBy',
                'gibbonMedicalMedication.prescriptionDate',
                'gibbonMedicalMedication.expirationDate',
                'gibbonMedicalMedication.purpose',
                'gibbonMedicalMedication.sideEffects',
                'gibbonMedicalMedication.storageLocation',
                'gibbonMedicalMedication.administeredBy',
                'gibbonMedicalMedication.parentConsent',
                'gibbonMedicalMedication.parentConsentDate',
                'gibbonMedicalMedication.verified',
                'gibbonMedicalMedication.verifiedDate',
                'gibbonMedicalMedication.notes',
                'gibbonMedicalMedication.active',
                'gibbonMedicalMedication.timestampCreated',
                'gibbonPerson.preferredName',
                'gibbonPerson.surname',
                'gibbonPerson.image_240',
                'createdBy.preferredName as createdByName',
                'createdBy.surname as createdBySurname',
                'verifiedBy.preferredName as verifiedByName',
                'verifiedBy.surname as verifiedBySurname',
            ])
            ->innerJoin('gibbonPerson', 'gibbonMedicalMedication.gibbonPersonID=gibbonPerson.gibbonPersonID')
            ->leftJoin('gibbonPerson as createdBy', 'gibbonMedicalMedication.createdByID=createdBy.gibbonPersonID')
            ->leftJoin('gibbonPerson as verifiedBy', 'gibbonMedicalMedication.verifiedByID=verifiedBy.gibbonPersonID');

        $criteria->addFilterRules([
            'child' => function ($query, $gibbonPersonID) {
                return $query
                    ->where('gibbonMedicalMedication.gibbonPersonID=:gibbonPersonID')
                    ->bindValue('gibbonPersonID', $gibbonPersonID);
            },
            'medicationType' => function ($query, $medicationType) {
                return $query
                    ->where('gibbonMedicalMedication.medicationType=:medicationType')
                    ->bindValue('medicationType', $medicationType);
            },
            'route' => function ($query, $route) {
                return $query
                    ->where('gibbonMedicalMedication.route=:route')
                    ->bindValue('route', $route);
            },
            'administeredBy' => function ($query, $administeredBy) {
                return $query
                    ->where('gibbonMedicalMedication.administeredBy=:administeredBy')
                    ->bindValue('administeredBy', $administeredBy);
            },
            'parentConsent' => function ($query, $value) {
                return $query
                    ->where('gibbonMedicalMedication.parentConsent=:parentConsent')
                    ->bindValue('parentConsent', $value);
            },
            'verified' => function ($query, $value) {
                return $query
                    ->where('gibbonMedicalMedication.verified=:verified')
                    ->bindValue('verified', $value);
            },
            'active' => function ($query, $value) {
                return $query
                    ->where('gibbonMedicalMedication.active=:active')
                    ->bindValue('active', $value);
            },
        ]);

        return $this->runQuery($query, $criteria);
    }

    /**
     * Query active medication records with criteria support.
     *
     * @param QueryCriteria $criteria
     * @return DataSet
     */
    public function queryActiveMedications(QueryCriteria $criteria)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonMedicalMedication.gibbonMedicalMedicationID',
                'gibbonMedicalMedication.gibbonPersonID',
                'gibbonMedicalMedication.medicationName',
                'gibbonMedicalMedication.medicationType',
                'gibbonMedicalMedication.dosage',
                'gibbonMedicalMedication.frequency',
                'gibbonMedicalMedication.route',
                'gibbonMedicalMedication.storageLocation',
                'gibbonMedicalMedication.administeredBy',
                'gibbonMedicalMedication.expirationDate',
                'gibbonMedicalMedication.parentConsent',
                'gibbonMedicalMedication.verified',
                'gibbonMedicalMedication.timestampCreated',
                'gibbonPerson.preferredName',
                'gibbonPerson.surname',
                'gibbonPerson.image_240',
                'gibbonPerson.dob',
            ])
            ->innerJoin('gibbonPerson', 'gibbonMedicalMedication.gibbonPersonID=gibbonPerson.gibbonPersonID')
            ->where('gibbonMedicalMedication.active=:active')
            ->bindValue('active', 'Y');

        $criteria->addFilterRules([
            'medicationType' => function ($query, $medicationType) {
                return $query
                    ->where('gibbonMedicalMedication.medicationType=:medicationType')
                    ->bindValue('medicationType', $medicationType);
            },
            'administeredBy' => function ($query, $administeredBy) {
                return $query
                    ->where('gibbonMedicalMedication.administeredBy=:administeredBy')
                    ->bindValue('administeredBy', $administeredBy);
            },
        ]);

        return $this->runQuery($query, $criteria);
    }

    /**
     * Query medication history for a specific child.
     *
     * @param QueryCriteria $criteria
     * @param int $gibbonPersonID
     * @return DataSet
     */
    public function queryMedicationsByPerson(QueryCriteria $criteria, $gibbonPersonID)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonMedicalMedication.gibbonMedicalMedicationID',
                'gibbonMedicalMedication.medicationName',
                'gibbonMedicalMedication.medicationType',
                'gibbonMedicalMedication.dosage',
                'gibbonMedicalMedication.frequency',
                'gibbonMedicalMedication.route',
                'gibbonMedicalMedication.prescribedBy',
                'gibbonMedicalMedication.prescriptionDate',
                'gibbonMedicalMedication.expirationDate',
                'gibbonMedicalMedication.purpose',
                'gibbonMedicalMedication.sideEffects',
                'gibbonMedicalMedication.storageLocation',
                'gibbonMedicalMedication.administeredBy',
                'gibbonMedicalMedication.parentConsent',
                'gibbonMedicalMedication.parentConsentDate',
                'gibbonMedicalMedication.verified',
                'gibbonMedicalMedication.verifiedDate',
                'gibbonMedicalMedication.notes',
                'gibbonMedicalMedication.active',
                'gibbonMedicalMedication.timestampCreated',
                'createdBy.preferredName as createdByName',
                'createdBy.surname as createdBySurname',
                'verifiedBy.preferredName as verifiedByName',
                'verifiedBy.surname as verifiedBySurname',
            ])
            ->leftJoin('gibbonPerson as createdBy', 'gibbonMedicalMedication.createdByID=createdBy.gibbonPersonID')
            ->leftJoin('gibbonPerson as verifiedBy', 'gibbonMedicalMedication.verifiedByID=verifiedBy.gibbonPersonID')
            ->where('gibbonMedicalMedication.gibbonPersonID=:gibbonPersonID')
            ->bindValue('gibbonPersonID', $gibbonPersonID);

        return $this->runQuery($query, $criteria);
    }

    /**
     * Get medications for a specific child.
     *
     * @param int $gibbonPersonID
     * @param bool $activeOnly
     * @return \Gibbon\Database\Result
     */
    public function selectMedicationsByPerson($gibbonPersonID, $activeOnly = true)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols([
                'gibbonMedicalMedication.*',
                'createdBy.preferredName as createdByName',
                'createdBy.surname as createdBySurname',
                'verifiedBy.preferredName as verifiedByName',
                'verifiedBy.surname as verifiedBySurname',
            ])
            ->leftJoin('gibbonPerson as createdBy', 'gibbonMedicalMedication.createdByID=createdBy.gibbonPersonID')
            ->leftJoin('gibbonPerson as verifiedBy', 'gibbonMedicalMedication.verifiedByID=verifiedBy.gibbonPersonID')
            ->where('gibbonMedicalMedication.gibbonPersonID=:gibbonPersonID')
            ->bindValue('gibbonPersonID', $gibbonPersonID)
            ->orderBy(['gibbonMedicalMedication.medicationName ASC']);

        if ($activeOnly) {
            $query->where('gibbonMedicalMedication.active=:active')
                ->bindValue('active', 'Y');
        }

        return $this->runSelect($query);
    }

    /**
     * Get medications requiring staff administration for a specific child.
     *
     * @param int $gibbonPersonID
     * @return \Gibbon\Database\Result
     */
    public function selectStaffAdministeredMedicationsByPerson($gibbonPersonID)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols([
                'gibbonMedicalMedication.gibbonMedicalMedicationID',
                'gibbonMedicalMedication.medicationName',
                'gibbonMedicalMedication.dosage',
                'gibbonMedicalMedication.frequency',
                'gibbonMedicalMedication.route',
                'gibbonMedicalMedication.storageLocation',
                'gibbonMedicalMedication.sideEffects',
                'gibbonMedicalMedication.expirationDate',
            ])
            ->where('gibbonMedicalMedication.gibbonPersonID=:gibbonPersonID')
            ->bindValue('gibbonPersonID', $gibbonPersonID)
            ->where('gibbonMedicalMedication.administeredBy IN (:administeredBy)')
            ->bindValue('administeredBy', ['Staff', 'Nurse'])
            ->where('gibbonMedicalMedication.active=:active')
            ->bindValue('active', 'Y')
            ->where('gibbonMedicalMedication.parentConsent=:parentConsent')
            ->bindValue('parentConsent', 'Y')
            ->orderBy(['gibbonMedicalMedication.medicationName ASC']);

        return $this->runSelect($query);
    }

    /**
     * Check if a child has a specific medication.
     *
     * @param int $gibbonPersonID
     * @param string $medicationName
     * @return array|false
     */
    public function getMedicationByPersonAndName($gibbonPersonID, $medicationName)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols(['*'])
            ->where('gibbonPersonID=:gibbonPersonID')
            ->bindValue('gibbonPersonID', $gibbonPersonID)
            ->where('medicationName=:medicationName')
            ->bindValue('medicationName', $medicationName)
            ->where('active=:active')
            ->bindValue('active', 'Y');

        $result = $this->runSelect($query);
        return $result->isNotEmpty() ? $result->fetch() : false;
    }

    /**
     * Get medication by ID with child details.
     *
     * @param int $gibbonMedicalMedicationID
     * @return array|false
     */
    public function getMedicationWithDetails($gibbonMedicalMedicationID)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols([
                'gibbonMedicalMedication.*',
                'gibbonPerson.preferredName',
                'gibbonPerson.surname',
                'gibbonPerson.image_240',
                'gibbonPerson.dob',
                'createdBy.preferredName as createdByName',
                'createdBy.surname as createdBySurname',
                'verifiedBy.preferredName as verifiedByName',
                'verifiedBy.surname as verifiedBySurname',
            ])
            ->innerJoin('gibbonPerson', 'gibbonMedicalMedication.gibbonPersonID=gibbonPerson.gibbonPersonID')
            ->leftJoin('gibbonPerson as createdBy', 'gibbonMedicalMedication.createdByID=createdBy.gibbonPersonID')
            ->leftJoin('gibbonPerson as verifiedBy', 'gibbonMedicalMedication.verifiedByID=verifiedBy.gibbonPersonID')
            ->where('gibbonMedicalMedication.gibbonMedicalMedicationID=:gibbonMedicalMedicationID')
            ->bindValue('gibbonMedicalMedicationID', $gibbonMedicalMedicationID);

        $result = $this->runSelect($query);
        return $result->isNotEmpty() ? $result->fetch() : false;
    }

    /**
     * Get medication summary for all children with medications.
     *
     * @return array
     */
    public function getMedicationSummary()
    {
        $data = [];
        $sql = "SELECT
                    medicationType,
                    administeredBy,
                    COUNT(*) as totalCount,
                    SUM(CASE WHEN parentConsent='Y' THEN 1 ELSE 0 END) as consentedCount,
                    SUM(CASE WHEN verified='Y' THEN 1 ELSE 0 END) as verifiedCount,
                    SUM(CASE WHEN verified='N' THEN 1 ELSE 0 END) as unverifiedCount,
                    SUM(CASE WHEN expirationDate IS NOT NULL AND expirationDate < CURDATE() THEN 1 ELSE 0 END) as expiredCount,
                    SUM(CASE WHEN expirationDate IS NOT NULL AND expirationDate BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as expiringSoonCount
                FROM gibbonMedicalMedication
                WHERE active='Y'
                GROUP BY medicationType, administeredBy
                ORDER BY
                    FIELD(medicationType, 'Prescription', 'Over-the-Counter', 'Supplement', 'Other'),
                    FIELD(administeredBy, 'Staff', 'Nurse', 'Self')";

        return $this->db()->select($sql, $data)->fetchAll();
    }

    /**
     * Query medications expiring within a specified number of days.
     *
     * @param QueryCriteria $criteria
     * @param int $days Number of days from today
     * @return DataSet
     */
    public function queryExpiringMedications(QueryCriteria $criteria, $days = 30)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonMedicalMedication.gibbonMedicalMedicationID',
                'gibbonMedicalMedication.gibbonPersonID',
                'gibbonMedicalMedication.medicationName',
                'gibbonMedicalMedication.medicationType',
                'gibbonMedicalMedication.dosage',
                'gibbonMedicalMedication.expirationDate',
                'gibbonMedicalMedication.storageLocation',
                'gibbonMedicalMedication.timestampCreated',
                'gibbonPerson.preferredName',
                'gibbonPerson.surname',
                'gibbonPerson.image_240',
                'DATEDIFF(gibbonMedicalMedication.expirationDate, CURDATE()) as daysUntilExpiry',
            ])
            ->innerJoin('gibbonPerson', 'gibbonMedicalMedication.gibbonPersonID=gibbonPerson.gibbonPersonID')
            ->where('gibbonMedicalMedication.active=:active')
            ->bindValue('active', 'Y')
            ->where('gibbonMedicalMedication.expirationDate IS NOT NULL')
            ->where('gibbonMedicalMedication.expirationDate <= DATE_ADD(CURDATE(), INTERVAL :days DAY)')
            ->bindValue('days', $days)
            ->where('gibbonPerson.status=:status')
            ->bindValue('status', 'Full');

        return $this->runQuery($query, $criteria);
    }

    /**
     * Get expired medications.
     *
     * @return \Gibbon\Database\Result
     */
    public function selectExpiredMedications()
    {
        $data = [];
        $sql = "SELECT
                    gibbonMedicalMedication.*,
                    gibbonPerson.preferredName,
                    gibbonPerson.surname,
                    gibbonPerson.image_240,
                    DATEDIFF(CURDATE(), gibbonMedicalMedication.expirationDate) as daysExpired
                FROM gibbonMedicalMedication
                INNER JOIN gibbonPerson ON gibbonMedicalMedication.gibbonPersonID=gibbonPerson.gibbonPersonID
                WHERE gibbonMedicalMedication.active='Y'
                AND gibbonMedicalMedication.expirationDate IS NOT NULL
                AND gibbonMedicalMedication.expirationDate < CURDATE()
                AND gibbonPerson.status='Full'
                ORDER BY gibbonMedicalMedication.expirationDate ASC, gibbonPerson.surname, gibbonPerson.preferredName";

        return $this->db()->select($sql, $data);
    }

    /**
     * Get medications expiring soon (within specified days).
     *
     * @param int $days Number of days to check ahead
     * @return \Gibbon\Database\Result
     */
    public function selectMedicationsExpiringSoon($days = 30)
    {
        $data = ['days' => $days];
        $sql = "SELECT
                    gibbonMedicalMedication.*,
                    gibbonPerson.preferredName,
                    gibbonPerson.surname,
                    gibbonPerson.image_240,
                    DATEDIFF(gibbonMedicalMedication.expirationDate, CURDATE()) as daysUntilExpiry
                FROM gibbonMedicalMedication
                INNER JOIN gibbonPerson ON gibbonMedicalMedication.gibbonPersonID=gibbonPerson.gibbonPersonID
                WHERE gibbonMedicalMedication.active='Y'
                AND gibbonMedicalMedication.expirationDate IS NOT NULL
                AND gibbonMedicalMedication.expirationDate >= CURDATE()
                AND gibbonMedicalMedication.expirationDate <= DATE_ADD(CURDATE(), INTERVAL :days DAY)
                AND gibbonPerson.status='Full'
                ORDER BY gibbonMedicalMedication.expirationDate ASC, gibbonPerson.surname, gibbonPerson.preferredName";

        return $this->db()->select($sql, $data);
    }

    /**
     * Get children with medications requiring staff administration.
     *
     * @return \Gibbon\Database\Result
     */
    public function selectChildrenWithStaffMedications()
    {
        $data = [];
        $sql = "SELECT DISTINCT
                    gibbonPerson.gibbonPersonID,
                    gibbonPerson.preferredName,
                    gibbonPerson.surname,
                    gibbonPerson.image_240,
                    GROUP_CONCAT(
                        CONCAT(gibbonMedicalMedication.medicationName, ' (', gibbonMedicalMedication.dosage, ' - ', gibbonMedicalMedication.frequency, ')')
                        ORDER BY gibbonMedicalMedication.medicationName
                        SEPARATOR '; '
                    ) as medicationList,
                    COUNT(*) as medicationCount
                FROM gibbonMedicalMedication
                INNER JOIN gibbonPerson ON gibbonMedicalMedication.gibbonPersonID=gibbonPerson.gibbonPersonID
                WHERE gibbonMedicalMedication.active='Y'
                AND gibbonMedicalMedication.administeredBy IN ('Staff', 'Nurse')
                AND gibbonMedicalMedication.parentConsent='Y'
                AND gibbonPerson.status='Full'
                GROUP BY gibbonPerson.gibbonPersonID
                ORDER BY gibbonPerson.surname, gibbonPerson.preferredName";

        return $this->db()->select($sql, $data);
    }

    /**
     * Get unverified medications needing staff verification.
     *
     * @return \Gibbon\Database\Result
     */
    public function selectUnverifiedMedications()
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols([
                'gibbonMedicalMedication.*',
                'gibbonPerson.preferredName',
                'gibbonPerson.surname',
                'gibbonPerson.image_240',
                'createdBy.preferredName as createdByName',
                'createdBy.surname as createdBySurname',
            ])
            ->innerJoin('gibbonPerson', 'gibbonMedicalMedication.gibbonPersonID=gibbonPerson.gibbonPersonID')
            ->leftJoin('gibbonPerson as createdBy', 'gibbonMedicalMedication.createdByID=createdBy.gibbonPersonID')
            ->where('gibbonMedicalMedication.active=:active')
            ->bindValue('active', 'Y')
            ->where('gibbonMedicalMedication.verified=:verified')
            ->bindValue('verified', 'N')
            ->where('gibbonPerson.status=:status')
            ->bindValue('status', 'Full')
            ->orderBy(['gibbonMedicalMedication.timestampCreated DESC']);

        return $this->runSelect($query);
    }

    /**
     * Get medications without parent consent.
     *
     * @return \Gibbon\Database\Result
     */
    public function selectMedicationsAwaitingConsent()
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols([
                'gibbonMedicalMedication.*',
                'gibbonPerson.preferredName',
                'gibbonPerson.surname',
                'gibbonPerson.image_240',
                'createdBy.preferredName as createdByName',
                'createdBy.surname as createdBySurname',
            ])
            ->innerJoin('gibbonPerson', 'gibbonMedicalMedication.gibbonPersonID=gibbonPerson.gibbonPersonID')
            ->leftJoin('gibbonPerson as createdBy', 'gibbonMedicalMedication.createdByID=createdBy.gibbonPersonID')
            ->where('gibbonMedicalMedication.active=:active')
            ->bindValue('active', 'Y')
            ->where('gibbonMedicalMedication.parentConsent=:parentConsent')
            ->bindValue('parentConsent', 'N')
            ->where('gibbonPerson.status=:status')
            ->bindValue('status', 'Full')
            ->orderBy(['gibbonMedicalMedication.timestampCreated DESC']);

        return $this->runSelect($query);
    }

    /**
     * Add a new medication record.
     *
     * @param int $gibbonPersonID
     * @param string $medicationName
     * @param string $medicationType
     * @param string $dosage
     * @param string $frequency
     * @param int $createdByID
     * @param array $additionalData
     * @return int|false
     */
    public function addMedication($gibbonPersonID, $medicationName, $medicationType, $dosage, $frequency, $createdByID, $additionalData = [])
    {
        // Check if this medication already exists for this child
        $existing = $this->getMedicationByPersonAndName($gibbonPersonID, $medicationName);

        if ($existing) {
            // Reactivate if inactive, or return existing ID
            if ($existing['active'] === 'N') {
                $this->update($existing['gibbonMedicalMedicationID'], array_merge([
                    'active' => 'Y',
                    'medicationType' => $medicationType,
                    'dosage' => $dosage,
                    'frequency' => $frequency,
                ], $additionalData));
                return $existing['gibbonMedicalMedicationID'];
            }
            return false; // Already exists and active
        }

        // Create new medication record
        return $this->insert(array_merge([
            'gibbonPersonID' => $gibbonPersonID,
            'medicationName' => $medicationName,
            'medicationType' => $medicationType,
            'dosage' => $dosage,
            'frequency' => $frequency,
            'createdByID' => $createdByID,
        ], $additionalData));
    }

    /**
     * Verify a medication record.
     *
     * @param int $gibbonMedicalMedicationID
     * @param int $verifiedByID
     * @return bool
     */
    public function verifyMedication($gibbonMedicalMedicationID, $verifiedByID)
    {
        return $this->update($gibbonMedicalMedicationID, [
            'verified' => 'Y',
            'verifiedByID' => $verifiedByID,
            'verifiedDate' => date('Y-m-d'),
        ]);
    }

    /**
     * Record parent consent for a medication.
     *
     * @param int $gibbonMedicalMedicationID
     * @return bool
     */
    public function recordParentConsent($gibbonMedicalMedicationID)
    {
        return $this->update($gibbonMedicalMedicationID, [
            'parentConsent' => 'Y',
            'parentConsentDate' => date('Y-m-d'),
        ]);
    }

    /**
     * Deactivate a medication record (soft delete).
     *
     * @param int $gibbonMedicalMedicationID
     * @return bool
     */
    public function deactivateMedication($gibbonMedicalMedicationID)
    {
        return $this->update($gibbonMedicalMedicationID, [
            'active' => 'N',
        ]);
    }

    /**
     * Get medication statistics for a specific child.
     *
     * @param int $gibbonPersonID
     * @return array
     */
    public function getMedicationStatsByPerson($gibbonPersonID)
    {
        $data = ['gibbonPersonID' => $gibbonPersonID];
        $sql = "SELECT
                    COUNT(*) as totalMedications,
                    SUM(CASE WHEN medicationType='Prescription' THEN 1 ELSE 0 END) as prescriptionCount,
                    SUM(CASE WHEN administeredBy IN ('Staff', 'Nurse') THEN 1 ELSE 0 END) as staffAdministeredCount,
                    SUM(CASE WHEN parentConsent='Y' THEN 1 ELSE 0 END) as consentedCount,
                    SUM(CASE WHEN verified='Y' THEN 1 ELSE 0 END) as verifiedCount,
                    SUM(CASE WHEN expirationDate IS NOT NULL AND expirationDate < CURDATE() THEN 1 ELSE 0 END) as expiredCount
                FROM gibbonMedicalMedication
                WHERE gibbonPersonID=:gibbonPersonID
                AND active='Y'";

        return $this->db()->selectOne($sql, $data) ?: [
            'totalMedications' => 0,
            'prescriptionCount' => 0,
            'staffAdministeredCount' => 0,
            'consentedCount' => 0,
            'verifiedCount' => 0,
            'expiredCount' => 0,
        ];
    }

    /**
     * Get expiration monitoring summary.
     *
     * @param int $warningDays Days before expiration to flag as warning
     * @return array
     */
    public function getExpirationMonitoringSummary($warningDays = 30)
    {
        $data = ['warningDays' => $warningDays];
        $sql = "SELECT
                    COUNT(*) as totalActive,
                    SUM(CASE WHEN expirationDate IS NULL THEN 1 ELSE 0 END) as noExpirationSet,
                    SUM(CASE WHEN expirationDate IS NOT NULL AND expirationDate < CURDATE() THEN 1 ELSE 0 END) as expiredCount,
                    SUM(CASE WHEN expirationDate IS NOT NULL AND expirationDate >= CURDATE() AND expirationDate <= DATE_ADD(CURDATE(), INTERVAL :warningDays DAY) THEN 1 ELSE 0 END) as expiringSoonCount,
                    SUM(CASE WHEN expirationDate IS NOT NULL AND expirationDate > DATE_ADD(CURDATE(), INTERVAL :warningDays DAY) THEN 1 ELSE 0 END) as validCount
                FROM gibbonMedicalMedication
                INNER JOIN gibbonPerson ON gibbonMedicalMedication.gibbonPersonID=gibbonPerson.gibbonPersonID
                WHERE gibbonMedicalMedication.active='Y'
                AND gibbonPerson.status='Full'";

        return $this->db()->selectOne($sql, $data) ?: [
            'totalActive' => 0,
            'noExpirationSet' => 0,
            'expiredCount' => 0,
            'expiringSoonCount' => 0,
            'validCount' => 0,
        ];
    }

    /**
     * Update medication expiration date.
     *
     * @param int $gibbonMedicalMedicationID
     * @param string $expirationDate
     * @return bool
     */
    public function updateExpirationDate($gibbonMedicalMedicationID, $expirationDate)
    {
        return $this->update($gibbonMedicalMedicationID, [
            'expirationDate' => $expirationDate,
        ]);
    }
}
