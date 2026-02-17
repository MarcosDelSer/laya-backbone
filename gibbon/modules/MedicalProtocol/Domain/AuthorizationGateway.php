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

namespace Gibbon\Module\MedicalProtocol\Domain;

use Gibbon\Domain\Traits\TableAware;
use Gibbon\Domain\QueryCriteria;
use Gibbon\Domain\QueryableGateway;

/**
 * Medical Protocol Authorization Gateway
 *
 * Manages parent authorizations for Quebec-mandated medical protocols:
 * - Tracks consent with e-signatures
 * - Manages weight records with 3-month expiry
 * - Handles authorization status (Active, Expired, Revoked, Pending)
 *
 * @version v1.0.00
 * @since   v1.0.00
 */
class AuthorizationGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'gibbonMedicalProtocolAuthorization';
    private static $primaryKey = 'gibbonMedicalProtocolAuthorizationID';

    private static $searchableColumns = ['gibbonPerson.preferredName', 'gibbonPerson.surname', 'gibbonMedicalProtocol.name', 'gibbonMedicalProtocol.formCode'];

    /**
     * Query authorization records with criteria support.
     *
     * @param QueryCriteria $criteria
     * @param int $gibbonSchoolYearID
     * @return DataSet
     */
    public function queryAuthorizations(QueryCriteria $criteria, $gibbonSchoolYearID)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonMedicalProtocolAuthorization.gibbonMedicalProtocolAuthorizationID',
                'gibbonMedicalProtocolAuthorization.gibbonMedicalProtocolID',
                'gibbonMedicalProtocolAuthorization.gibbonPersonID',
                'gibbonMedicalProtocolAuthorization.status',
                'gibbonMedicalProtocolAuthorization.weightKg',
                'gibbonMedicalProtocolAuthorization.weightDate',
                'gibbonMedicalProtocolAuthorization.weightExpiryDate',
                'gibbonMedicalProtocolAuthorization.signatureDate',
                'gibbonMedicalProtocolAuthorization.expiryDate',
                'gibbonMedicalProtocolAuthorization.timestampCreated',
                'gibbonPerson.preferredName',
                'gibbonPerson.surname',
                'gibbonPerson.image_240',
                'gibbonPerson.dob',
                'gibbonMedicalProtocol.name as protocolName',
                'gibbonMedicalProtocol.formCode',
                'gibbonMedicalProtocol.type as protocolType',
                'authorizedBy.preferredName as authorizedByName',
                'authorizedBy.surname as authorizedBySurname',
            ])
            ->innerJoin('gibbonPerson', 'gibbonMedicalProtocolAuthorization.gibbonPersonID=gibbonPerson.gibbonPersonID')
            ->innerJoin('gibbonMedicalProtocol', 'gibbonMedicalProtocolAuthorization.gibbonMedicalProtocolID=gibbonMedicalProtocol.gibbonMedicalProtocolID')
            ->leftJoin('gibbonPerson as authorizedBy', 'gibbonMedicalProtocolAuthorization.authorizedByID=authorizedBy.gibbonPersonID')
            ->where('gibbonMedicalProtocolAuthorization.gibbonSchoolYearID=:gibbonSchoolYearID')
            ->bindValue('gibbonSchoolYearID', $gibbonSchoolYearID);

        $criteria->addFilterRules([
            'child' => function ($query, $gibbonPersonID) {
                return $query
                    ->where('gibbonMedicalProtocolAuthorization.gibbonPersonID=:gibbonPersonID')
                    ->bindValue('gibbonPersonID', $gibbonPersonID);
            },
            'protocol' => function ($query, $gibbonMedicalProtocolID) {
                return $query
                    ->where('gibbonMedicalProtocolAuthorization.gibbonMedicalProtocolID=:gibbonMedicalProtocolID')
                    ->bindValue('gibbonMedicalProtocolID', $gibbonMedicalProtocolID);
            },
            'status' => function ($query, $status) {
                return $query
                    ->where('gibbonMedicalProtocolAuthorization.status=:status')
                    ->bindValue('status', $status);
            },
            'formCode' => function ($query, $formCode) {
                return $query
                    ->where('gibbonMedicalProtocol.formCode=:formCode')
                    ->bindValue('formCode', $formCode);
            },
            'weightExpired' => function ($query, $value) {
                if ($value === 'Y') {
                    return $query->where('gibbonMedicalProtocolAuthorization.weightExpiryDate < CURDATE()');
                }
                return $query->where('gibbonMedicalProtocolAuthorization.weightExpiryDate >= CURDATE()');
            },
        ]);

        return $this->runQuery($query, $criteria);
    }

    /**
     * Query authorizations for a specific child.
     *
     * @param QueryCriteria $criteria
     * @param int $gibbonPersonID
     * @param int $gibbonSchoolYearID
     * @return DataSet
     */
    public function queryAuthorizationsByPerson(QueryCriteria $criteria, $gibbonPersonID, $gibbonSchoolYearID)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonMedicalProtocolAuthorization.gibbonMedicalProtocolAuthorizationID',
                'gibbonMedicalProtocolAuthorization.gibbonMedicalProtocolID',
                'gibbonMedicalProtocolAuthorization.status',
                'gibbonMedicalProtocolAuthorization.weightKg',
                'gibbonMedicalProtocolAuthorization.weightDate',
                'gibbonMedicalProtocolAuthorization.weightExpiryDate',
                'gibbonMedicalProtocolAuthorization.signatureDate',
                'gibbonMedicalProtocolAuthorization.expiryDate',
                'gibbonMedicalProtocolAuthorization.timestampCreated',
                'gibbonMedicalProtocol.name as protocolName',
                'gibbonMedicalProtocol.formCode',
                'gibbonMedicalProtocol.type as protocolType',
                'gibbonMedicalProtocol.requiresTemperature',
                'authorizedBy.preferredName as authorizedByName',
                'authorizedBy.surname as authorizedBySurname',
            ])
            ->innerJoin('gibbonMedicalProtocol', 'gibbonMedicalProtocolAuthorization.gibbonMedicalProtocolID=gibbonMedicalProtocol.gibbonMedicalProtocolID')
            ->leftJoin('gibbonPerson as authorizedBy', 'gibbonMedicalProtocolAuthorization.authorizedByID=authorizedBy.gibbonPersonID')
            ->where('gibbonMedicalProtocolAuthorization.gibbonPersonID=:gibbonPersonID')
            ->bindValue('gibbonPersonID', $gibbonPersonID)
            ->where('gibbonMedicalProtocolAuthorization.gibbonSchoolYearID=:gibbonSchoolYearID')
            ->bindValue('gibbonSchoolYearID', $gibbonSchoolYearID);

        return $this->runQuery($query, $criteria);
    }

    /**
     * Get authorization for a specific child and protocol.
     *
     * @param int $gibbonPersonID Child's person ID
     * @param int $gibbonMedicalProtocolID Protocol ID
     * @param int $gibbonSchoolYearID School year ID
     * @return array|false
     */
    public function getAuthorizationByChildAndProtocol($gibbonPersonID, $gibbonMedicalProtocolID, $gibbonSchoolYearID)
    {
        $data = [
            'gibbonPersonID' => $gibbonPersonID,
            'gibbonMedicalProtocolID' => $gibbonMedicalProtocolID,
            'gibbonSchoolYearID' => $gibbonSchoolYearID,
        ];
        $sql = "SELECT
                    gibbonMedicalProtocolAuthorization.*,
                    gibbonMedicalProtocol.name as protocolName,
                    gibbonMedicalProtocol.formCode,
                    gibbonMedicalProtocol.type as protocolType,
                    gibbonMedicalProtocol.intervalMinutes,
                    gibbonMedicalProtocol.maxDailyDoses,
                    gibbonMedicalProtocol.requiresTemperature,
                    authorizedBy.preferredName as authorizedByName,
                    authorizedBy.surname as authorizedBySurname
                FROM gibbonMedicalProtocolAuthorization
                INNER JOIN gibbonMedicalProtocol ON gibbonMedicalProtocolAuthorization.gibbonMedicalProtocolID=gibbonMedicalProtocol.gibbonMedicalProtocolID
                LEFT JOIN gibbonPerson as authorizedBy ON gibbonMedicalProtocolAuthorization.authorizedByID=authorizedBy.gibbonPersonID
                WHERE gibbonMedicalProtocolAuthorization.gibbonPersonID=:gibbonPersonID
                AND gibbonMedicalProtocolAuthorization.gibbonMedicalProtocolID=:gibbonMedicalProtocolID
                AND gibbonMedicalProtocolAuthorization.gibbonSchoolYearID=:gibbonSchoolYearID
                ORDER BY gibbonMedicalProtocolAuthorization.timestampCreated DESC
                LIMIT 1";

        return $this->db()->selectOne($sql, $data);
    }

    /**
     * Get active authorization for a specific child and protocol.
     *
     * @param int $gibbonPersonID Child's person ID
     * @param int $gibbonMedicalProtocolID Protocol ID
     * @param int $gibbonSchoolYearID School year ID
     * @return array|false
     */
    public function getActiveAuthorization($gibbonPersonID, $gibbonMedicalProtocolID, $gibbonSchoolYearID)
    {
        $data = [
            'gibbonPersonID' => $gibbonPersonID,
            'gibbonMedicalProtocolID' => $gibbonMedicalProtocolID,
            'gibbonSchoolYearID' => $gibbonSchoolYearID,
        ];
        $sql = "SELECT
                    gibbonMedicalProtocolAuthorization.*,
                    gibbonMedicalProtocol.name as protocolName,
                    gibbonMedicalProtocol.formCode,
                    gibbonMedicalProtocol.type as protocolType,
                    gibbonMedicalProtocol.intervalMinutes,
                    gibbonMedicalProtocol.maxDailyDoses,
                    gibbonMedicalProtocol.requiresTemperature
                FROM gibbonMedicalProtocolAuthorization
                INNER JOIN gibbonMedicalProtocol ON gibbonMedicalProtocolAuthorization.gibbonMedicalProtocolID=gibbonMedicalProtocol.gibbonMedicalProtocolID
                WHERE gibbonMedicalProtocolAuthorization.gibbonPersonID=:gibbonPersonID
                AND gibbonMedicalProtocolAuthorization.gibbonMedicalProtocolID=:gibbonMedicalProtocolID
                AND gibbonMedicalProtocolAuthorization.gibbonSchoolYearID=:gibbonSchoolYearID
                AND gibbonMedicalProtocolAuthorization.status='Active'
                AND (gibbonMedicalProtocolAuthorization.expiryDate IS NULL OR gibbonMedicalProtocolAuthorization.expiryDate >= CURDATE())
                LIMIT 1";

        return $this->db()->selectOne($sql, $data);
    }

    /**
     * Create a new authorization with e-signature.
     *
     * @param int $gibbonMedicalProtocolID Protocol ID
     * @param int $gibbonPersonID Child's person ID
     * @param int $gibbonSchoolYearID School year ID
     * @param int $authorizedByID Parent/guardian person ID
     * @param float $weightKg Child's weight in kg
     * @param string $signatureData Base64-encoded signature image
     * @param string $agreementText Full legal text agreed to
     * @param string|null $signatureIP IP address at time of signature
     * @param string|null $notes Additional notes
     * @return int|false The new authorization ID or false on failure
     */
    public function createAuthorization($gibbonMedicalProtocolID, $gibbonPersonID, $gibbonSchoolYearID, $authorizedByID, $weightKg, $signatureData, $agreementText, $signatureIP = null, $notes = null)
    {
        $today = date('Y-m-d');
        $weightExpiryDate = date('Y-m-d', strtotime('+3 months'));

        return $this->insert([
            'gibbonMedicalProtocolID' => $gibbonMedicalProtocolID,
            'gibbonPersonID' => $gibbonPersonID,
            'gibbonSchoolYearID' => $gibbonSchoolYearID,
            'authorizedByID' => $authorizedByID,
            'status' => 'Active',
            'weightKg' => $weightKg,
            'weightDate' => $today,
            'weightExpiryDate' => $weightExpiryDate,
            'signatureData' => $signatureData,
            'signatureDate' => date('Y-m-d H:i:s'),
            'signatureIP' => $signatureIP,
            'agreementText' => $agreementText,
            'notes' => $notes,
        ]);
    }

    /**
     * Revoke an existing authorization.
     *
     * @param int $gibbonMedicalProtocolAuthorizationID Authorization ID
     * @param int $revokedByID Person ID who revoked
     * @param string $reason Reason for revocation
     * @return bool
     */
    public function revokeAuthorization($gibbonMedicalProtocolAuthorizationID, $revokedByID, $reason)
    {
        return $this->update($gibbonMedicalProtocolAuthorizationID, [
            'status' => 'Revoked',
            'revokedDate' => date('Y-m-d H:i:s'),
            'revokedByID' => $revokedByID,
            'revokedReason' => $reason,
        ]);
    }

    /**
     * Check if a child is authorized for a specific protocol.
     *
     * @param int $gibbonPersonID Child's person ID
     * @param int $gibbonMedicalProtocolID Protocol ID
     * @param int $gibbonSchoolYearID School year ID
     * @return bool True if actively authorized
     */
    public function isAuthorized($gibbonPersonID, $gibbonMedicalProtocolID, $gibbonSchoolYearID)
    {
        $data = [
            'gibbonPersonID' => $gibbonPersonID,
            'gibbonMedicalProtocolID' => $gibbonMedicalProtocolID,
            'gibbonSchoolYearID' => $gibbonSchoolYearID,
        ];
        $sql = "SELECT COUNT(*) as count
                FROM gibbonMedicalProtocolAuthorization
                WHERE gibbonPersonID=:gibbonPersonID
                AND gibbonMedicalProtocolID=:gibbonMedicalProtocolID
                AND gibbonSchoolYearID=:gibbonSchoolYearID
                AND status='Active'
                AND (expiryDate IS NULL OR expiryDate >= CURDATE())";

        $result = $this->db()->selectOne($sql, $data);
        return $result && $result['count'] > 0;
    }

    /**
     * Check if the child's weight record has expired (3-month check).
     *
     * @param int $gibbonMedicalProtocolAuthorizationID Authorization ID
     * @return bool True if weight is expired
     */
    public function isWeightExpired($gibbonMedicalProtocolAuthorizationID)
    {
        $data = ['gibbonMedicalProtocolAuthorizationID' => $gibbonMedicalProtocolAuthorizationID];
        $sql = "SELECT weightExpiryDate
                FROM gibbonMedicalProtocolAuthorization
                WHERE gibbonMedicalProtocolAuthorizationID=:gibbonMedicalProtocolAuthorizationID";

        $result = $this->db()->selectOne($sql, $data);

        if (empty($result)) {
            return true;
        }

        return strtotime($result['weightExpiryDate']) < strtotime(date('Y-m-d'));
    }

    /**
     * Get the weight expiry date for an authorization.
     *
     * @param int $gibbonMedicalProtocolAuthorizationID Authorization ID
     * @return string|false Weight expiry date (Y-m-d format) or false if not found
     */
    public function getWeightExpiryDate($gibbonMedicalProtocolAuthorizationID)
    {
        $data = ['gibbonMedicalProtocolAuthorizationID' => $gibbonMedicalProtocolAuthorizationID];
        $sql = "SELECT weightExpiryDate
                FROM gibbonMedicalProtocolAuthorization
                WHERE gibbonMedicalProtocolAuthorizationID=:gibbonMedicalProtocolAuthorizationID";

        $result = $this->db()->selectOne($sql, $data);

        return !empty($result) ? $result['weightExpiryDate'] : false;
    }

    /**
     * Check if an authorization requires a weight update (3-month revalidation).
     *
     * This method checks if the weight data is expired or will expire soon,
     * requiring revalidation per Quebec protocol requirements.
     *
     * @param int $gibbonMedicalProtocolAuthorizationID Authorization ID
     * @param int $warningDays Number of days before expiry to trigger warning (default: 14)
     * @return bool True if weight update is required or will be needed soon
     */
    public function requiresWeightUpdate($gibbonMedicalProtocolAuthorizationID, $warningDays = 14)
    {
        $data = ['gibbonMedicalProtocolAuthorizationID' => $gibbonMedicalProtocolAuthorizationID];
        $sql = "SELECT weightExpiryDate
                FROM gibbonMedicalProtocolAuthorization
                WHERE gibbonMedicalProtocolAuthorizationID=:gibbonMedicalProtocolAuthorizationID";

        $result = $this->db()->selectOne($sql, $data);

        if (empty($result) || empty($result['weightExpiryDate'])) {
            return true;
        }

        $expiryDate = strtotime($result['weightExpiryDate']);
        $warningDate = strtotime("+{$warningDays} days");

        return $expiryDate <= $warningDate;
    }

    /**
     * Check if weight is expired for a child and protocol.
     *
     * @param int $gibbonPersonID Child's person ID
     * @param int $gibbonMedicalProtocolID Protocol ID
     * @param int $gibbonSchoolYearID School year ID
     * @return bool True if weight is expired or no authorization exists
     */
    public function isWeightExpiredByChildAndProtocol($gibbonPersonID, $gibbonMedicalProtocolID, $gibbonSchoolYearID)
    {
        $authorization = $this->getActiveAuthorization($gibbonPersonID, $gibbonMedicalProtocolID, $gibbonSchoolYearID);

        if (empty($authorization)) {
            return true;
        }

        return strtotime($authorization['weightExpiryDate']) < strtotime(date('Y-m-d'));
    }

    /**
     * Get authorizations with expiring weight (within specified days).
     *
     * @param int $gibbonSchoolYearID School year ID
     * @param int $daysUntilExpiry Number of days until weight expires
     * @return \Gibbon\Database\Result
     */
    public function selectExpiringAuthorizations($gibbonSchoolYearID, $daysUntilExpiry = 14)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols([
                'gibbonMedicalProtocolAuthorization.gibbonMedicalProtocolAuthorizationID',
                'gibbonMedicalProtocolAuthorization.gibbonPersonID',
                'gibbonMedicalProtocolAuthorization.gibbonMedicalProtocolID',
                'gibbonMedicalProtocolAuthorization.weightKg',
                'gibbonMedicalProtocolAuthorization.weightDate',
                'gibbonMedicalProtocolAuthorization.weightExpiryDate',
                'gibbonPerson.preferredName',
                'gibbonPerson.surname',
                'gibbonMedicalProtocol.name as protocolName',
                'gibbonMedicalProtocol.formCode',
                'authorizedBy.preferredName as authorizedByName',
                'authorizedBy.surname as authorizedBySurname',
                'authorizedBy.email as authorizedByEmail',
            ])
            ->innerJoin('gibbonPerson', 'gibbonMedicalProtocolAuthorization.gibbonPersonID=gibbonPerson.gibbonPersonID')
            ->innerJoin('gibbonMedicalProtocol', 'gibbonMedicalProtocolAuthorization.gibbonMedicalProtocolID=gibbonMedicalProtocol.gibbonMedicalProtocolID')
            ->leftJoin('gibbonPerson as authorizedBy', 'gibbonMedicalProtocolAuthorization.authorizedByID=authorizedBy.gibbonPersonID')
            ->where('gibbonMedicalProtocolAuthorization.gibbonSchoolYearID=:gibbonSchoolYearID')
            ->bindValue('gibbonSchoolYearID', $gibbonSchoolYearID)
            ->where("gibbonMedicalProtocolAuthorization.status='Active'")
            ->where('gibbonMedicalProtocolAuthorization.weightExpiryDate <= DATE_ADD(CURDATE(), INTERVAL :daysUntilExpiry DAY)')
            ->bindValue('daysUntilExpiry', $daysUntilExpiry)
            ->where('gibbonMedicalProtocolAuthorization.weightExpiryDate >= CURDATE()')
            ->orderBy(['gibbonMedicalProtocolAuthorization.weightExpiryDate ASC']);

        return $this->runSelect($query);
    }

    /**
     * Get authorizations where weight has already expired.
     *
     * @param int $gibbonSchoolYearID School year ID
     * @return \Gibbon\Database\Result
     */
    public function selectExpiredWeightAuthorizations($gibbonSchoolYearID)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols([
                'gibbonMedicalProtocolAuthorization.gibbonMedicalProtocolAuthorizationID',
                'gibbonMedicalProtocolAuthorization.gibbonPersonID',
                'gibbonMedicalProtocolAuthorization.gibbonMedicalProtocolID',
                'gibbonMedicalProtocolAuthorization.weightKg',
                'gibbonMedicalProtocolAuthorization.weightDate',
                'gibbonMedicalProtocolAuthorization.weightExpiryDate',
                'gibbonPerson.preferredName',
                'gibbonPerson.surname',
                'gibbonMedicalProtocol.name as protocolName',
                'gibbonMedicalProtocol.formCode',
            ])
            ->innerJoin('gibbonPerson', 'gibbonMedicalProtocolAuthorization.gibbonPersonID=gibbonPerson.gibbonPersonID')
            ->innerJoin('gibbonMedicalProtocol', 'gibbonMedicalProtocolAuthorization.gibbonMedicalProtocolID=gibbonMedicalProtocol.gibbonMedicalProtocolID')
            ->where('gibbonMedicalProtocolAuthorization.gibbonSchoolYearID=:gibbonSchoolYearID')
            ->bindValue('gibbonSchoolYearID', $gibbonSchoolYearID)
            ->where("gibbonMedicalProtocolAuthorization.status='Active'")
            ->where('gibbonMedicalProtocolAuthorization.weightExpiryDate < CURDATE()')
            ->orderBy(['gibbonMedicalProtocolAuthorization.weightExpiryDate ASC']);

        return $this->runSelect($query);
    }

    /**
     * Update the weight for an existing authorization.
     *
     * @param int $gibbonMedicalProtocolAuthorizationID Authorization ID
     * @param float $weightKg New weight in kg
     * @return bool
     */
    public function updateWeight($gibbonMedicalProtocolAuthorizationID, $weightKg)
    {
        $today = date('Y-m-d');
        $weightExpiryDate = date('Y-m-d', strtotime('+3 months'));

        return $this->update($gibbonMedicalProtocolAuthorizationID, [
            'weightKg' => $weightKg,
            'weightDate' => $today,
            'weightExpiryDate' => $weightExpiryDate,
        ]);
    }

    /**
     * Get authorization summary statistics for the school year.
     *
     * @param int $gibbonSchoolYearID School year ID
     * @return array Summary statistics
     */
    public function getAuthorizationSummary($gibbonSchoolYearID)
    {
        $data = ['gibbonSchoolYearID' => $gibbonSchoolYearID];
        $sql = "SELECT
                    COUNT(*) as totalAuthorizations,
                    SUM(CASE WHEN status='Active' THEN 1 ELSE 0 END) as activeAuthorizations,
                    SUM(CASE WHEN status='Expired' THEN 1 ELSE 0 END) as expiredAuthorizations,
                    SUM(CASE WHEN status='Revoked' THEN 1 ELSE 0 END) as revokedAuthorizations,
                    SUM(CASE WHEN status='Pending' THEN 1 ELSE 0 END) as pendingAuthorizations,
                    SUM(CASE WHEN status='Active' AND weightExpiryDate < CURDATE() THEN 1 ELSE 0 END) as expiredWeightAuthorizations,
                    SUM(CASE WHEN status='Active' AND weightExpiryDate >= CURDATE() AND weightExpiryDate <= DATE_ADD(CURDATE(), INTERVAL 14 DAY) THEN 1 ELSE 0 END) as expiringWeightAuthorizations,
                    COUNT(DISTINCT gibbonPersonID) as uniqueChildren
                FROM gibbonMedicalProtocolAuthorization
                WHERE gibbonSchoolYearID=:gibbonSchoolYearID";

        return $this->db()->selectOne($sql, $data) ?: [
            'totalAuthorizations' => 0,
            'activeAuthorizations' => 0,
            'expiredAuthorizations' => 0,
            'revokedAuthorizations' => 0,
            'pendingAuthorizations' => 0,
            'expiredWeightAuthorizations' => 0,
            'expiringWeightAuthorizations' => 0,
            'uniqueChildren' => 0,
        ];
    }

    /**
     * Get authorization summary by protocol type.
     *
     * @param int $gibbonSchoolYearID School year ID
     * @return array Summary by protocol
     */
    public function getAuthorizationSummaryByProtocol($gibbonSchoolYearID)
    {
        $data = ['gibbonSchoolYearID' => $gibbonSchoolYearID];
        $sql = "SELECT
                    gibbonMedicalProtocol.gibbonMedicalProtocolID,
                    gibbonMedicalProtocol.name,
                    gibbonMedicalProtocol.formCode,
                    COUNT(*) as totalAuthorizations,
                    SUM(CASE WHEN gibbonMedicalProtocolAuthorization.status='Active' THEN 1 ELSE 0 END) as activeAuthorizations,
                    SUM(CASE WHEN gibbonMedicalProtocolAuthorization.status='Active' AND gibbonMedicalProtocolAuthorization.weightExpiryDate < CURDATE() THEN 1 ELSE 0 END) as expiredWeightAuthorizations
                FROM gibbonMedicalProtocolAuthorization
                INNER JOIN gibbonMedicalProtocol ON gibbonMedicalProtocolAuthorization.gibbonMedicalProtocolID=gibbonMedicalProtocol.gibbonMedicalProtocolID
                WHERE gibbonMedicalProtocolAuthorization.gibbonSchoolYearID=:gibbonSchoolYearID
                GROUP BY gibbonMedicalProtocol.gibbonMedicalProtocolID
                ORDER BY gibbonMedicalProtocol.name ASC";

        return $this->db()->select($sql, $data)->fetchAll();
    }

    /**
     * Select all active authorizations for a school year.
     *
     * @param int $gibbonSchoolYearID School year ID
     * @return \Gibbon\Database\Result
     */
    public function selectActiveAuthorizations($gibbonSchoolYearID)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols([
                'gibbonMedicalProtocolAuthorization.gibbonMedicalProtocolAuthorizationID',
                'gibbonMedicalProtocolAuthorization.gibbonPersonID',
                'gibbonMedicalProtocolAuthorization.gibbonMedicalProtocolID',
                'gibbonMedicalProtocolAuthorization.weightKg',
                'gibbonMedicalProtocolAuthorization.weightDate',
                'gibbonMedicalProtocolAuthorization.weightExpiryDate',
                'gibbonMedicalProtocolAuthorization.signatureDate',
                'gibbonPerson.preferredName',
                'gibbonPerson.surname',
                'gibbonPerson.dob',
                'gibbonMedicalProtocol.name as protocolName',
                'gibbonMedicalProtocol.formCode',
                'gibbonMedicalProtocol.type as protocolType',
            ])
            ->innerJoin('gibbonPerson', 'gibbonMedicalProtocolAuthorization.gibbonPersonID=gibbonPerson.gibbonPersonID')
            ->innerJoin('gibbonMedicalProtocol', 'gibbonMedicalProtocolAuthorization.gibbonMedicalProtocolID=gibbonMedicalProtocol.gibbonMedicalProtocolID')
            ->where('gibbonMedicalProtocolAuthorization.gibbonSchoolYearID=:gibbonSchoolYearID')
            ->bindValue('gibbonSchoolYearID', $gibbonSchoolYearID)
            ->where("gibbonMedicalProtocolAuthorization.status='Active'")
            ->where("(gibbonMedicalProtocolAuthorization.expiryDate IS NULL OR gibbonMedicalProtocolAuthorization.expiryDate >= CURDATE())")
            ->orderBy(['gibbonPerson.surname ASC', 'gibbonPerson.preferredName ASC']);

        return $this->runSelect($query);
    }

    /**
     * Select children authorized for a specific protocol.
     *
     * @param int $gibbonMedicalProtocolID Protocol ID
     * @param int $gibbonSchoolYearID School year ID
     * @return \Gibbon\Database\Result
     */
    public function selectAuthorizedChildren($gibbonMedicalProtocolID, $gibbonSchoolYearID)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols([
                'gibbonMedicalProtocolAuthorization.gibbonMedicalProtocolAuthorizationID',
                'gibbonMedicalProtocolAuthorization.gibbonPersonID',
                'gibbonMedicalProtocolAuthorization.weightKg',
                'gibbonMedicalProtocolAuthorization.weightDate',
                'gibbonMedicalProtocolAuthorization.weightExpiryDate',
                'gibbonPerson.preferredName',
                'gibbonPerson.surname',
                'gibbonPerson.dob',
                'gibbonPerson.image_240',
            ])
            ->innerJoin('gibbonPerson', 'gibbonMedicalProtocolAuthorization.gibbonPersonID=gibbonPerson.gibbonPersonID')
            ->where('gibbonMedicalProtocolAuthorization.gibbonMedicalProtocolID=:gibbonMedicalProtocolID')
            ->bindValue('gibbonMedicalProtocolID', $gibbonMedicalProtocolID)
            ->where('gibbonMedicalProtocolAuthorization.gibbonSchoolYearID=:gibbonSchoolYearID')
            ->bindValue('gibbonSchoolYearID', $gibbonSchoolYearID)
            ->where("gibbonMedicalProtocolAuthorization.status='Active'")
            ->where("(gibbonMedicalProtocolAuthorization.expiryDate IS NULL OR gibbonMedicalProtocolAuthorization.expiryDate >= CURDATE())")
            ->orderBy(['gibbonPerson.surname ASC', 'gibbonPerson.preferredName ASC']);

        return $this->runSelect($query);
    }

    /**
     * Expire authorization (set status to Expired).
     *
     * @param int $gibbonMedicalProtocolAuthorizationID Authorization ID
     * @return bool
     */
    public function expireAuthorization($gibbonMedicalProtocolAuthorizationID)
    {
        return $this->update($gibbonMedicalProtocolAuthorizationID, [
            'status' => 'Expired',
        ]);
    }

    /**
     * Update authorization expiry date.
     *
     * @param int $gibbonMedicalProtocolAuthorizationID Authorization ID
     * @param string $expiryDate New expiry date (Y-m-d format)
     * @return bool
     */
    public function updateExpiryDate($gibbonMedicalProtocolAuthorizationID, $expiryDate)
    {
        return $this->update($gibbonMedicalProtocolAuthorizationID, [
            'expiryDate' => $expiryDate,
        ]);
    }
}
