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
 * Medical Protocol Gateway
 *
 * Manages protocol definitions for Quebec-mandated protocols:
 * - Acetaminophen (FO-0647) - weight-based dosing for fever reduction
 * - Insect Repellent (FO-0646) - topical application guidelines
 *
 * @version v1.0.00
 * @since   v1.0.00
 */
class ProtocolGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'gibbonMedicalProtocol';
    private static $primaryKey = 'gibbonMedicalProtocolID';

    private static $searchableColumns = ['gibbonMedicalProtocol.name', 'gibbonMedicalProtocol.formCode', 'gibbonMedicalProtocol.description'];

    /**
     * Query protocol records with criteria support.
     *
     * @param QueryCriteria $criteria
     * @return DataSet
     */
    public function queryProtocols(QueryCriteria $criteria)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonMedicalProtocol.gibbonMedicalProtocolID',
                'gibbonMedicalProtocol.name',
                'gibbonMedicalProtocol.formCode',
                'gibbonMedicalProtocol.type',
                'gibbonMedicalProtocol.description',
                'gibbonMedicalProtocol.legalText',
                'gibbonMedicalProtocol.dosingInstructions',
                'gibbonMedicalProtocol.ageRestrictionMonths',
                'gibbonMedicalProtocol.intervalMinutes',
                'gibbonMedicalProtocol.maxDailyDoses',
                'gibbonMedicalProtocol.requiresTemperature',
                'gibbonMedicalProtocol.active',
                'gibbonMedicalProtocol.timestampCreated',
                'gibbonMedicalProtocol.timestampModified',
            ]);

        $criteria->addFilterRules([
            'type' => function ($query, $type) {
                return $query
                    ->where('gibbonMedicalProtocol.type=:type')
                    ->bindValue('type', $type);
            },
            'formCode' => function ($query, $formCode) {
                return $query
                    ->where('gibbonMedicalProtocol.formCode=:formCode')
                    ->bindValue('formCode', $formCode);
            },
            'active' => function ($query, $active) {
                return $query
                    ->where('gibbonMedicalProtocol.active=:active')
                    ->bindValue('active', $active);
            },
        ]);

        return $this->runQuery($query, $criteria);
    }

    /**
     * Select all active protocols.
     *
     * @return \Gibbon\Database\Result
     */
    public function selectActiveProtocols()
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols([
                'gibbonMedicalProtocol.gibbonMedicalProtocolID',
                'gibbonMedicalProtocol.name',
                'gibbonMedicalProtocol.formCode',
                'gibbonMedicalProtocol.type',
                'gibbonMedicalProtocol.description',
                'gibbonMedicalProtocol.legalText',
                'gibbonMedicalProtocol.dosingInstructions',
                'gibbonMedicalProtocol.ageRestrictionMonths',
                'gibbonMedicalProtocol.intervalMinutes',
                'gibbonMedicalProtocol.maxDailyDoses',
                'gibbonMedicalProtocol.requiresTemperature',
            ])
            ->where("gibbonMedicalProtocol.active='Y'")
            ->orderBy(['gibbonMedicalProtocol.name ASC']);

        return $this->runSelect($query);
    }

    /**
     * Get a protocol by its ID.
     *
     * @param int $gibbonMedicalProtocolID
     * @return array|false
     */
    public function getProtocolByID($gibbonMedicalProtocolID)
    {
        $data = ['gibbonMedicalProtocolID' => $gibbonMedicalProtocolID];
        $sql = "SELECT
                    gibbonMedicalProtocol.*
                FROM gibbonMedicalProtocol
                WHERE gibbonMedicalProtocolID=:gibbonMedicalProtocolID";

        return $this->db()->selectOne($sql, $data);
    }

    /**
     * Get a protocol by its form code (e.g., FO-0647, FO-0646).
     *
     * @param string $formCode
     * @return array|false
     */
    public function getProtocolByFormCode($formCode)
    {
        $data = ['formCode' => $formCode];
        $sql = "SELECT
                    gibbonMedicalProtocol.*
                FROM gibbonMedicalProtocol
                WHERE formCode=:formCode";

        return $this->db()->selectOne($sql, $data);
    }

    /**
     * Get dosage information for a given weight and concentration.
     * Calculates the appropriate dose based on the weight-based dosing table.
     *
     * @param int $gibbonMedicalProtocolID
     * @param float $weightKg Child's weight in kilograms
     * @param string|null $concentration Optional specific concentration (e.g., '80mg/mL')
     * @return array Array of dosing options or empty array if no match
     */
    public function getDosageForWeight($gibbonMedicalProtocolID, $weightKg, $concentration = null)
    {
        $data = [
            'gibbonMedicalProtocolID' => $gibbonMedicalProtocolID,
            'weightKg' => $weightKg,
        ];

        $sql = "SELECT
                    gibbonMedicalProtocolDosing.gibbonMedicalProtocolDosingID,
                    gibbonMedicalProtocolDosing.weightMinKg,
                    gibbonMedicalProtocolDosing.weightMaxKg,
                    gibbonMedicalProtocolDosing.concentration,
                    gibbonMedicalProtocolDosing.doseAmount,
                    gibbonMedicalProtocolDosing.doseMg,
                    gibbonMedicalProtocolDosing.notes,
                    gibbonMedicalProtocol.name as protocolName,
                    gibbonMedicalProtocol.formCode
                FROM gibbonMedicalProtocolDosing
                INNER JOIN gibbonMedicalProtocol ON gibbonMedicalProtocolDosing.gibbonMedicalProtocolID=gibbonMedicalProtocol.gibbonMedicalProtocolID
                WHERE gibbonMedicalProtocolDosing.gibbonMedicalProtocolID=:gibbonMedicalProtocolID
                AND :weightKg >= gibbonMedicalProtocolDosing.weightMinKg
                AND :weightKg <= gibbonMedicalProtocolDosing.weightMaxKg";

        if ($concentration !== null) {
            $data['concentration'] = $concentration;
            $sql .= " AND gibbonMedicalProtocolDosing.concentration=:concentration";
        }

        $sql .= " ORDER BY gibbonMedicalProtocolDosing.concentration ASC";

        return $this->db()->select($sql, $data)->fetchAll();
    }

    /**
     * Get all dosing entries for a protocol.
     *
     * @param int $gibbonMedicalProtocolID
     * @return \Gibbon\Database\Result
     */
    public function selectDosingByProtocol($gibbonMedicalProtocolID)
    {
        $query = $this
            ->newSelect()
            ->from('gibbonMedicalProtocolDosing')
            ->cols([
                'gibbonMedicalProtocolDosing.gibbonMedicalProtocolDosingID',
                'gibbonMedicalProtocolDosing.weightMinKg',
                'gibbonMedicalProtocolDosing.weightMaxKg',
                'gibbonMedicalProtocolDosing.concentration',
                'gibbonMedicalProtocolDosing.doseAmount',
                'gibbonMedicalProtocolDosing.doseMg',
                'gibbonMedicalProtocolDosing.notes',
            ])
            ->where('gibbonMedicalProtocolDosing.gibbonMedicalProtocolID=:gibbonMedicalProtocolID')
            ->bindValue('gibbonMedicalProtocolID', $gibbonMedicalProtocolID)
            ->orderBy(['gibbonMedicalProtocolDosing.concentration ASC', 'gibbonMedicalProtocolDosing.weightMinKg ASC']);

        return $this->runSelect($query);
    }

    /**
     * Get all available concentrations for a protocol.
     *
     * @param int $gibbonMedicalProtocolID
     * @return array List of unique concentrations
     */
    public function getConcentrationsByProtocol($gibbonMedicalProtocolID)
    {
        $data = ['gibbonMedicalProtocolID' => $gibbonMedicalProtocolID];
        $sql = "SELECT DISTINCT concentration
                FROM gibbonMedicalProtocolDosing
                WHERE gibbonMedicalProtocolID=:gibbonMedicalProtocolID
                ORDER BY concentration ASC";

        $result = $this->db()->select($sql, $data);
        $concentrations = [];

        while ($row = $result->fetch()) {
            $concentrations[] = $row['concentration'];
        }

        return $concentrations;
    }

    /**
     * Get the weight range supported by a protocol.
     *
     * @param int $gibbonMedicalProtocolID
     * @return array ['minWeight' => float, 'maxWeight' => float] or empty array
     */
    public function getWeightRangeByProtocol($gibbonMedicalProtocolID)
    {
        $data = ['gibbonMedicalProtocolID' => $gibbonMedicalProtocolID];
        $sql = "SELECT
                    MIN(weightMinKg) as minWeight,
                    MAX(weightMaxKg) as maxWeight
                FROM gibbonMedicalProtocolDosing
                WHERE gibbonMedicalProtocolID=:gibbonMedicalProtocolID";

        return $this->db()->selectOne($sql, $data) ?: ['minWeight' => null, 'maxWeight' => null];
    }

    /**
     * Check if a child's age meets protocol requirements.
     *
     * @param int $gibbonMedicalProtocolID
     * @param int $ageMonths Child's age in months
     * @return bool True if age requirements are met or no restriction exists
     */
    public function isAgeAllowed($gibbonMedicalProtocolID, $ageMonths)
    {
        $protocol = $this->getByID($gibbonMedicalProtocolID);

        if (empty($protocol)) {
            return false;
        }

        // No age restriction
        if ($protocol['ageRestrictionMonths'] === null) {
            return true;
        }

        return $ageMonths >= $protocol['ageRestrictionMonths'];
    }

    /**
     * Check if a weight is within the protocol's supported range.
     *
     * @param int $gibbonMedicalProtocolID
     * @param float $weightKg Child's weight in kilograms
     * @return bool True if weight is within supported range
     */
    public function isWeightInRange($gibbonMedicalProtocolID, $weightKg)
    {
        $range = $this->getWeightRangeByProtocol($gibbonMedicalProtocolID);

        if ($range['minWeight'] === null || $range['maxWeight'] === null) {
            return true; // No dosing table, so weight doesn't matter (e.g., insect repellent)
        }

        return $weightKg >= $range['minWeight'] && $weightKg <= $range['maxWeight'];
    }

    /**
     * Get protocol summary statistics.
     *
     * @return array Summary of protocols including counts by type
     */
    public function getProtocolSummary()
    {
        $data = [];
        $sql = "SELECT
                    COUNT(*) as totalProtocols,
                    SUM(CASE WHEN active='Y' THEN 1 ELSE 0 END) as activeProtocols,
                    SUM(CASE WHEN type='Medication' THEN 1 ELSE 0 END) as medicationProtocols,
                    SUM(CASE WHEN type='Topical' THEN 1 ELSE 0 END) as topicalProtocols
                FROM gibbonMedicalProtocol";

        return $this->db()->selectOne($sql, $data) ?: [
            'totalProtocols' => 0,
            'activeProtocols' => 0,
            'medicationProtocols' => 0,
            'topicalProtocols' => 0,
        ];
    }

    /**
     * Calculate dose based on weight using the 10-15 mg/kg guideline.
     * This is a helper method for when a specific dosing entry doesn't exist.
     *
     * @param float $weightKg Child's weight in kilograms
     * @param float $mgPerKg Milligrams per kilogram (default 12.5 for middle of 10-15 range)
     * @return array ['doseMinMg' => float, 'doseMaxMg' => float, 'doseRecommendedMg' => float]
     */
    public function calculateDoseByWeight($weightKg, $mgPerKg = 12.5)
    {
        return [
            'doseMinMg' => round($weightKg * 10, 2),
            'doseMaxMg' => round($weightKg * 15, 2),
            'doseRecommendedMg' => round($weightKg * $mgPerKg, 2),
        ];
    }
}
