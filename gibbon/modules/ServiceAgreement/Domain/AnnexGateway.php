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

namespace Gibbon\Module\ServiceAgreement\Domain;

use Gibbon\Domain\Traits\TableAware;
use Gibbon\Domain\QueryCriteria;
use Gibbon\Domain\QueryableGateway;

/**
 * Service Agreement Annex Gateway
 *
 * Handles CRUD operations for Quebec FO-0659 Service Agreement Annexes.
 * Manages Annexes A-D: Field Trips, Hygiene Items, Supplementary Meals, Extended Hours.
 *
 * @version v1.0.00
 * @since   v1.0.00
 */
class AnnexGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'gibbonServiceAgreementAnnex';
    private static $primaryKey = 'gibbonServiceAgreementAnnexID';

    private static $searchableColumns = [];

    /**
     * Annex type constants for Quebec FO-0659.
     */
    const ANNEX_A_FIELD_TRIPS = 'A';
    const ANNEX_B_HYGIENE_ITEMS = 'B';
    const ANNEX_C_SUPPLEMENTARY_MEALS = 'C';
    const ANNEX_D_EXTENDED_HOURS = 'D';

    /**
     * Get human-readable annex type names.
     *
     * @return array
     */
    public static function getAnnexTypeNames()
    {
        return [
            self::ANNEX_A_FIELD_TRIPS => 'Field Trips Authorization',
            self::ANNEX_B_HYGIENE_ITEMS => 'Hygiene Items',
            self::ANNEX_C_SUPPLEMENTARY_MEALS => 'Supplementary Meals',
            self::ANNEX_D_EXTENDED_HOURS => 'Extended Hours',
        ];
    }

    /**
     * Get French annex type names for Quebec compliance.
     *
     * @return array
     */
    public static function getAnnexTypeNamesFr()
    {
        return [
            self::ANNEX_A_FIELD_TRIPS => 'Autorisation de sorties',
            self::ANNEX_B_HYGIENE_ITEMS => 'Articles d\'hygiène',
            self::ANNEX_C_SUPPLEMENTARY_MEALS => 'Repas supplémentaires',
            self::ANNEX_D_EXTENDED_HOURS => 'Heures prolongées',
        ];
    }

    /**
     * Query annexes with criteria support.
     *
     * @param QueryCriteria $criteria
     * @param int $gibbonServiceAgreementID
     * @return DataSet
     */
    public function queryAnnexes(QueryCriteria $criteria, $gibbonServiceAgreementID)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonServiceAgreementAnnex.gibbonServiceAgreementAnnexID',
                'gibbonServiceAgreementAnnex.gibbonServiceAgreementID',
                'gibbonServiceAgreementAnnex.annexType',
                'gibbonServiceAgreementAnnex.status',
                'gibbonServiceAgreementAnnex.fieldTripsAuthorized',
                'gibbonServiceAgreementAnnex.fieldTripsConditions',
                'gibbonServiceAgreementAnnex.hygieneItemsIncluded',
                'gibbonServiceAgreementAnnex.hygieneItemsDescription',
                'gibbonServiceAgreementAnnex.hygieneItemsMonthlyFee',
                'gibbonServiceAgreementAnnex.supplementaryMealsIncluded',
                'gibbonServiceAgreementAnnex.supplementaryMealsDays',
                'gibbonServiceAgreementAnnex.supplementaryMealsDescription',
                'gibbonServiceAgreementAnnex.supplementaryMealsFee',
                'gibbonServiceAgreementAnnex.extendedHoursIncluded',
                'gibbonServiceAgreementAnnex.extendedHoursStart',
                'gibbonServiceAgreementAnnex.extendedHoursEnd',
                'gibbonServiceAgreementAnnex.extendedHoursHourlyRate',
                'gibbonServiceAgreementAnnex.extendedHoursMaxDaily',
                'gibbonServiceAgreementAnnex.signedDate',
                'gibbonServiceAgreementAnnex.signedByID',
                'gibbonServiceAgreementAnnex.timestampCreated',
                'gibbonServiceAgreementAnnex.timestampModified',
                'signedBy.preferredName as signedByName',
                'signedBy.surname as signedBySurname',
            ])
            ->leftJoin('gibbonPerson as signedBy', 'gibbonServiceAgreementAnnex.signedByID=signedBy.gibbonPersonID')
            ->where('gibbonServiceAgreementAnnex.gibbonServiceAgreementID=:gibbonServiceAgreementID')
            ->bindValue('gibbonServiceAgreementID', $gibbonServiceAgreementID);

        $criteria->addFilterRules([
            'annexType' => function ($query, $annexType) {
                return $query
                    ->where('gibbonServiceAgreementAnnex.annexType=:annexType')
                    ->bindValue('annexType', $annexType);
            },
            'status' => function ($query, $status) {
                return $query
                    ->where('gibbonServiceAgreementAnnex.status=:status')
                    ->bindValue('status', $status);
            },
            'signed' => function ($query, $value) {
                if ($value === 'Y') {
                    return $query->where("gibbonServiceAgreementAnnex.status='Signed'");
                }
                return $query;
            },
            'pending' => function ($query, $value) {
                if ($value === 'Y') {
                    return $query->where("gibbonServiceAgreementAnnex.status='Pending'");
                }
                return $query;
            },
        ]);

        return $this->runQuery($query, $criteria);
    }

    /**
     * Get all annexes for a service agreement.
     *
     * @param int $gibbonServiceAgreementID
     * @return \Gibbon\Database\Result
     */
    public function selectAnnexesByAgreement($gibbonServiceAgreementID)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols([
                'gibbonServiceAgreementAnnex.*',
                'signedBy.preferredName as signedByName',
                'signedBy.surname as signedBySurname',
            ])
            ->leftJoin('gibbonPerson as signedBy', 'gibbonServiceAgreementAnnex.signedByID=signedBy.gibbonPersonID')
            ->where('gibbonServiceAgreementAnnex.gibbonServiceAgreementID=:gibbonServiceAgreementID')
            ->bindValue('gibbonServiceAgreementID', $gibbonServiceAgreementID)
            ->orderBy(["FIELD(gibbonServiceAgreementAnnex.annexType, 'A', 'B', 'C', 'D')"]);

        return $this->runSelect($query);
    }

    /**
     * Get a specific annex by agreement and type.
     *
     * @param int $gibbonServiceAgreementID
     * @param string $annexType
     * @return array|false
     */
    public function getAnnexByAgreementAndType($gibbonServiceAgreementID, $annexType)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols([
                'gibbonServiceAgreementAnnex.*',
                'signedBy.preferredName as signedByName',
                'signedBy.surname as signedBySurname',
            ])
            ->leftJoin('gibbonPerson as signedBy', 'gibbonServiceAgreementAnnex.signedByID=signedBy.gibbonPersonID')
            ->where('gibbonServiceAgreementAnnex.gibbonServiceAgreementID=:gibbonServiceAgreementID')
            ->bindValue('gibbonServiceAgreementID', $gibbonServiceAgreementID)
            ->where('gibbonServiceAgreementAnnex.annexType=:annexType')
            ->bindValue('annexType', $annexType);

        $result = $this->runSelect($query);
        return $result->isNotEmpty() ? $result->fetch() : false;
    }

    /**
     * Get all signed annexes for an agreement.
     *
     * @param int $gibbonServiceAgreementID
     * @return \Gibbon\Database\Result
     */
    public function selectSignedAnnexes($gibbonServiceAgreementID)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols(['gibbonServiceAgreementAnnex.*'])
            ->where('gibbonServiceAgreementID=:gibbonServiceAgreementID')
            ->bindValue('gibbonServiceAgreementID', $gibbonServiceAgreementID)
            ->where("status='Signed'")
            ->orderBy(["FIELD(annexType, 'A', 'B', 'C', 'D')"]);

        return $this->runSelect($query);
    }

    /**
     * Get all pending annexes for an agreement.
     *
     * @param int $gibbonServiceAgreementID
     * @return \Gibbon\Database\Result
     */
    public function selectPendingAnnexes($gibbonServiceAgreementID)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols(['gibbonServiceAgreementAnnex.*'])
            ->where('gibbonServiceAgreementID=:gibbonServiceAgreementID')
            ->bindValue('gibbonServiceAgreementID', $gibbonServiceAgreementID)
            ->where("status='Pending'")
            ->orderBy(["FIELD(annexType, 'A', 'B', 'C', 'D')"]);

        return $this->runSelect($query);
    }

    /**
     * Create all four annexes for a new service agreement.
     *
     * @param int $gibbonServiceAgreementID
     * @return bool
     */
    public function createAnnexesForAgreement($gibbonServiceAgreementID)
    {
        $annexTypes = [
            self::ANNEX_A_FIELD_TRIPS,
            self::ANNEX_B_HYGIENE_ITEMS,
            self::ANNEX_C_SUPPLEMENTARY_MEALS,
            self::ANNEX_D_EXTENDED_HOURS,
        ];

        foreach ($annexTypes as $annexType) {
            $result = $this->insert([
                'gibbonServiceAgreementID' => $gibbonServiceAgreementID,
                'annexType' => $annexType,
                'status' => 'NotApplicable',
            ]);

            if ($result === false) {
                return false;
            }
        }

        return true;
    }

    /**
     * Update Annex A - Field Trips Authorization.
     *
     * @param int $gibbonServiceAgreementAnnexID
     * @param bool $authorized
     * @param string|null $conditions
     * @return bool
     */
    public function updateAnnexA($gibbonServiceAgreementAnnexID, $authorized, $conditions = null)
    {
        return $this->update($gibbonServiceAgreementAnnexID, [
            'fieldTripsAuthorized' => $authorized ? 'Y' : 'N',
            'fieldTripsConditions' => $conditions,
            'status' => 'Pending',
        ]);
    }

    /**
     * Update Annex B - Hygiene Items.
     *
     * @param int $gibbonServiceAgreementAnnexID
     * @param bool $included
     * @param string|null $description
     * @param float|null $monthlyFee
     * @return bool
     */
    public function updateAnnexB($gibbonServiceAgreementAnnexID, $included, $description = null, $monthlyFee = null)
    {
        return $this->update($gibbonServiceAgreementAnnexID, [
            'hygieneItemsIncluded' => $included ? 'Y' : 'N',
            'hygieneItemsDescription' => $description,
            'hygieneItemsMonthlyFee' => $monthlyFee,
            'status' => 'Pending',
        ]);
    }

    /**
     * Update Annex C - Supplementary Meals.
     *
     * @param int $gibbonServiceAgreementAnnexID
     * @param bool $included
     * @param string|null $days
     * @param string|null $description
     * @param float|null $fee
     * @return bool
     */
    public function updateAnnexC($gibbonServiceAgreementAnnexID, $included, $days = null, $description = null, $fee = null)
    {
        return $this->update($gibbonServiceAgreementAnnexID, [
            'supplementaryMealsIncluded' => $included ? 'Y' : 'N',
            'supplementaryMealsDays' => $days,
            'supplementaryMealsDescription' => $description,
            'supplementaryMealsFee' => $fee,
            'status' => 'Pending',
        ]);
    }

    /**
     * Update Annex D - Extended Hours.
     *
     * @param int $gibbonServiceAgreementAnnexID
     * @param bool $included
     * @param string|null $startTime
     * @param string|null $endTime
     * @param float|null $hourlyRate
     * @param float|null $maxDaily
     * @return bool
     */
    public function updateAnnexD($gibbonServiceAgreementAnnexID, $included, $startTime = null, $endTime = null, $hourlyRate = null, $maxDaily = null)
    {
        return $this->update($gibbonServiceAgreementAnnexID, [
            'extendedHoursIncluded' => $included ? 'Y' : 'N',
            'extendedHoursStart' => $startTime,
            'extendedHoursEnd' => $endTime,
            'extendedHoursHourlyRate' => $hourlyRate,
            'extendedHoursMaxDaily' => $maxDaily,
            'status' => 'Pending',
        ]);
    }

    /**
     * Sign an annex.
     *
     * @param int $gibbonServiceAgreementAnnexID
     * @param int $signedByID
     * @return bool
     */
    public function signAnnex($gibbonServiceAgreementAnnexID, $signedByID)
    {
        return $this->update($gibbonServiceAgreementAnnexID, [
            'status' => 'Signed',
            'signedDate' => date('Y-m-d H:i:s'),
            'signedByID' => $signedByID,
        ]);
    }

    /**
     * Decline an annex.
     *
     * @param int $gibbonServiceAgreementAnnexID
     * @return bool
     */
    public function declineAnnex($gibbonServiceAgreementAnnexID)
    {
        return $this->update($gibbonServiceAgreementAnnexID, [
            'status' => 'Declined',
        ]);
    }

    /**
     * Mark annex as not applicable.
     *
     * @param int $gibbonServiceAgreementAnnexID
     * @return bool
     */
    public function markNotApplicable($gibbonServiceAgreementAnnexID)
    {
        return $this->update($gibbonServiceAgreementAnnexID, [
            'status' => 'NotApplicable',
        ]);
    }

    /**
     * Check if all applicable annexes are signed for an agreement.
     *
     * @param int $gibbonServiceAgreementID
     * @return bool
     */
    public function areAllAnnexesSigned($gibbonServiceAgreementID)
    {
        $data = ['gibbonServiceAgreementID' => $gibbonServiceAgreementID];
        $sql = "SELECT COUNT(*) as pendingCount
                FROM gibbonServiceAgreementAnnex
                WHERE gibbonServiceAgreementID=:gibbonServiceAgreementID
                AND status='Pending'";

        $result = $this->db()->selectOne($sql, $data);
        return ($result['pendingCount'] ?? 0) == 0;
    }

    /**
     * Get annex summary for an agreement.
     *
     * @param int $gibbonServiceAgreementID
     * @return array
     */
    public function getAnnexSummary($gibbonServiceAgreementID)
    {
        $data = ['gibbonServiceAgreementID' => $gibbonServiceAgreementID];
        $sql = "SELECT
                    COUNT(*) as totalAnnexes,
                    SUM(CASE WHEN status='Signed' THEN 1 ELSE 0 END) as signedCount,
                    SUM(CASE WHEN status='Pending' THEN 1 ELSE 0 END) as pendingCount,
                    SUM(CASE WHEN status='Declined' THEN 1 ELSE 0 END) as declinedCount,
                    SUM(CASE WHEN status='NotApplicable' THEN 1 ELSE 0 END) as notApplicableCount
                FROM gibbonServiceAgreementAnnex
                WHERE gibbonServiceAgreementID=:gibbonServiceAgreementID";

        return $this->db()->selectOne($sql, $data) ?: [
            'totalAnnexes' => 0,
            'signedCount' => 0,
            'pendingCount' => 0,
            'declinedCount' => 0,
            'notApplicableCount' => 0,
        ];
    }

    /**
     * Calculate total monthly fees from all signed annexes.
     *
     * @param int $gibbonServiceAgreementID
     * @return float
     */
    public function calculateTotalMonthlyFees($gibbonServiceAgreementID)
    {
        $data = ['gibbonServiceAgreementID' => $gibbonServiceAgreementID];
        $sql = "SELECT
                    COALESCE(SUM(hygieneItemsMonthlyFee), 0) +
                    COALESCE(SUM(supplementaryMealsFee), 0) as totalMonthlyFees
                FROM gibbonServiceAgreementAnnex
                WHERE gibbonServiceAgreementID=:gibbonServiceAgreementID
                AND status='Signed'";

        $result = $this->db()->selectOne($sql, $data);
        return (float) ($result['totalMonthlyFees'] ?? 0);
    }

    /**
     * Get extended hours configuration for an agreement.
     *
     * @param int $gibbonServiceAgreementID
     * @return array|false
     */
    public function getExtendedHoursConfig($gibbonServiceAgreementID)
    {
        $annex = $this->getAnnexByAgreementAndType($gibbonServiceAgreementID, self::ANNEX_D_EXTENDED_HOURS);

        if ($annex && $annex['status'] === 'Signed' && $annex['extendedHoursIncluded'] === 'Y') {
            return [
                'startTime' => $annex['extendedHoursStart'],
                'endTime' => $annex['extendedHoursEnd'],
                'hourlyRate' => $annex['extendedHoursHourlyRate'],
                'maxDaily' => $annex['extendedHoursMaxDaily'],
            ];
        }

        return false;
    }

    /**
     * Check if field trips are authorized for an agreement.
     *
     * @param int $gibbonServiceAgreementID
     * @return bool
     */
    public function isFieldTripsAuthorized($gibbonServiceAgreementID)
    {
        $annex = $this->getAnnexByAgreementAndType($gibbonServiceAgreementID, self::ANNEX_A_FIELD_TRIPS);

        return $annex
            && $annex['status'] === 'Signed'
            && $annex['fieldTripsAuthorized'] === 'Y';
    }

    /**
     * Get field trips conditions for an agreement.
     *
     * @param int $gibbonServiceAgreementID
     * @return string|null
     */
    public function getFieldTripsConditions($gibbonServiceAgreementID)
    {
        $annex = $this->getAnnexByAgreementAndType($gibbonServiceAgreementID, self::ANNEX_A_FIELD_TRIPS);

        if ($annex && $annex['status'] === 'Signed') {
            return $annex['fieldTripsConditions'];
        }

        return null;
    }

    /**
     * Get supplementary meals configuration for an agreement.
     *
     * @param int $gibbonServiceAgreementID
     * @return array|false
     */
    public function getSupplementaryMealsConfig($gibbonServiceAgreementID)
    {
        $annex = $this->getAnnexByAgreementAndType($gibbonServiceAgreementID, self::ANNEX_C_SUPPLEMENTARY_MEALS);

        if ($annex && $annex['status'] === 'Signed' && $annex['supplementaryMealsIncluded'] === 'Y') {
            return [
                'days' => $annex['supplementaryMealsDays'],
                'description' => $annex['supplementaryMealsDescription'],
                'fee' => $annex['supplementaryMealsFee'],
            ];
        }

        return false;
    }

    /**
     * Get hygiene items configuration for an agreement.
     *
     * @param int $gibbonServiceAgreementID
     * @return array|false
     */
    public function getHygieneItemsConfig($gibbonServiceAgreementID)
    {
        $annex = $this->getAnnexByAgreementAndType($gibbonServiceAgreementID, self::ANNEX_B_HYGIENE_ITEMS);

        if ($annex && $annex['status'] === 'Signed' && $annex['hygieneItemsIncluded'] === 'Y') {
            return [
                'description' => $annex['hygieneItemsDescription'],
                'monthlyFee' => $annex['hygieneItemsMonthlyFee'],
            ];
        }

        return false;
    }

    /**
     * Bulk update annexes for an agreement based on form data.
     *
     * @param int $gibbonServiceAgreementID
     * @param array $annexData Associative array with annex type as key
     * @return bool
     */
    public function updateAnnexesFromFormData($gibbonServiceAgreementID, array $annexData)
    {
        foreach ($annexData as $annexType => $data) {
            $annex = $this->getAnnexByAgreementAndType($gibbonServiceAgreementID, $annexType);

            if (!$annex) {
                continue;
            }

            switch ($annexType) {
                case self::ANNEX_A_FIELD_TRIPS:
                    $this->updateAnnexA(
                        $annex['gibbonServiceAgreementAnnexID'],
                        $data['authorized'] ?? false,
                        $data['conditions'] ?? null
                    );
                    break;

                case self::ANNEX_B_HYGIENE_ITEMS:
                    $this->updateAnnexB(
                        $annex['gibbonServiceAgreementAnnexID'],
                        $data['included'] ?? false,
                        $data['description'] ?? null,
                        $data['monthlyFee'] ?? null
                    );
                    break;

                case self::ANNEX_C_SUPPLEMENTARY_MEALS:
                    $this->updateAnnexC(
                        $annex['gibbonServiceAgreementAnnexID'],
                        $data['included'] ?? false,
                        $data['days'] ?? null,
                        $data['description'] ?? null,
                        $data['fee'] ?? null
                    );
                    break;

                case self::ANNEX_D_EXTENDED_HOURS:
                    $this->updateAnnexD(
                        $annex['gibbonServiceAgreementAnnexID'],
                        $data['included'] ?? false,
                        $data['startTime'] ?? null,
                        $data['endTime'] ?? null,
                        $data['hourlyRate'] ?? null,
                        $data['maxDaily'] ?? null
                    );
                    break;
            }
        }

        return true;
    }

    /**
     * Sign all pending annexes for an agreement.
     *
     * @param int $gibbonServiceAgreementID
     * @param int $signedByID
     * @return int Number of annexes signed
     */
    public function signAllPendingAnnexes($gibbonServiceAgreementID, $signedByID)
    {
        $pendingAnnexes = $this->selectPendingAnnexes($gibbonServiceAgreementID);
        $signedCount = 0;

        foreach ($pendingAnnexes as $annex) {
            if ($this->signAnnex($annex['gibbonServiceAgreementAnnexID'], $signedByID)) {
                $signedCount++;
            }
        }

        return $signedCount;
    }

    /**
     * Get annexes requiring attention (pending signature).
     *
     * @param int $gibbonSchoolYearID
     * @return \Gibbon\Database\Result
     */
    public function selectAnnexesRequiringAttention($gibbonSchoolYearID)
    {
        $data = ['gibbonSchoolYearID' => $gibbonSchoolYearID];
        $sql = "SELECT
                    gibbonServiceAgreementAnnex.*,
                    gibbonServiceAgreement.agreementNumber,
                    gibbonServiceAgreement.childName,
                    gibbonServiceAgreement.parentName,
                    child.preferredName as childPreferredName,
                    child.surname as childSurname,
                    parent.preferredName as parentPreferredName,
                    parent.surname as parentSurname,
                    parent.email as parentEmail
                FROM gibbonServiceAgreementAnnex
                INNER JOIN gibbonServiceAgreement ON gibbonServiceAgreementAnnex.gibbonServiceAgreementID=gibbonServiceAgreement.gibbonServiceAgreementID
                INNER JOIN gibbonPerson as child ON gibbonServiceAgreement.gibbonPersonIDChild=child.gibbonPersonID
                INNER JOIN gibbonPerson as parent ON gibbonServiceAgreement.gibbonPersonIDParent=parent.gibbonPersonID
                WHERE gibbonServiceAgreement.gibbonSchoolYearID=:gibbonSchoolYearID
                AND gibbonServiceAgreement.status IN ('Active', 'Pending Signature')
                AND gibbonServiceAgreementAnnex.status='Pending'
                ORDER BY gibbonServiceAgreement.timestampCreated ASC, FIELD(gibbonServiceAgreementAnnex.annexType, 'A', 'B', 'C', 'D')";

        return $this->db()->select($sql, $data);
    }
}
