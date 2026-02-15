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
 * Medical Tracking Allergy Gateway
 *
 * Handles allergy records for children including severity levels, reaction types,
 * and treatment information for childcare settings.
 *
 * @version v1.0.00
 * @since   v1.0.00
 */
class AllergyGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'gibbonMedicalAllergy';
    private static $primaryKey = 'gibbonMedicalAllergyID';

    private static $searchableColumns = ['gibbonPerson.preferredName', 'gibbonPerson.surname', 'gibbonMedicalAllergy.allergenName', 'gibbonMedicalAllergy.notes'];

    /**
     * Query allergy records with criteria support.
     *
     * @param QueryCriteria $criteria
     * @return DataSet
     */
    public function queryAllergies(QueryCriteria $criteria)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonMedicalAllergy.gibbonMedicalAllergyID',
                'gibbonMedicalAllergy.gibbonPersonID',
                'gibbonMedicalAllergy.allergenName',
                'gibbonMedicalAllergy.allergenType',
                'gibbonMedicalAllergy.severity',
                'gibbonMedicalAllergy.reaction',
                'gibbonMedicalAllergy.treatment',
                'gibbonMedicalAllergy.epiPenRequired',
                'gibbonMedicalAllergy.epiPenLocation',
                'gibbonMedicalAllergy.diagnosedDate',
                'gibbonMedicalAllergy.diagnosedBy',
                'gibbonMedicalAllergy.verified',
                'gibbonMedicalAllergy.verifiedDate',
                'gibbonMedicalAllergy.notes',
                'gibbonMedicalAllergy.active',
                'gibbonMedicalAllergy.timestampCreated',
                'gibbonPerson.preferredName',
                'gibbonPerson.surname',
                'gibbonPerson.image_240',
                'createdBy.preferredName as createdByName',
                'createdBy.surname as createdBySurname',
                'verifiedBy.preferredName as verifiedByName',
                'verifiedBy.surname as verifiedBySurname',
            ])
            ->innerJoin('gibbonPerson', 'gibbonMedicalAllergy.gibbonPersonID=gibbonPerson.gibbonPersonID')
            ->leftJoin('gibbonPerson as createdBy', 'gibbonMedicalAllergy.createdByID=createdBy.gibbonPersonID')
            ->leftJoin('gibbonPerson as verifiedBy', 'gibbonMedicalAllergy.verifiedByID=verifiedBy.gibbonPersonID');

        $criteria->addFilterRules([
            'child' => function ($query, $gibbonPersonID) {
                return $query
                    ->where('gibbonMedicalAllergy.gibbonPersonID=:gibbonPersonID')
                    ->bindValue('gibbonPersonID', $gibbonPersonID);
            },
            'allergenType' => function ($query, $allergenType) {
                return $query
                    ->where('gibbonMedicalAllergy.allergenType=:allergenType')
                    ->bindValue('allergenType', $allergenType);
            },
            'severity' => function ($query, $severity) {
                return $query
                    ->where('gibbonMedicalAllergy.severity=:severity')
                    ->bindValue('severity', $severity);
            },
            'epiPenRequired' => function ($query, $value) {
                return $query
                    ->where('gibbonMedicalAllergy.epiPenRequired=:epiPenRequired')
                    ->bindValue('epiPenRequired', $value);
            },
            'verified' => function ($query, $value) {
                return $query
                    ->where('gibbonMedicalAllergy.verified=:verified')
                    ->bindValue('verified', $value);
            },
            'active' => function ($query, $value) {
                return $query
                    ->where('gibbonMedicalAllergy.active=:active')
                    ->bindValue('active', $value);
            },
        ]);

        return $this->runQuery($query, $criteria);
    }

    /**
     * Query active allergy records with criteria support.
     *
     * @param QueryCriteria $criteria
     * @return DataSet
     */
    public function queryActiveAllergies(QueryCriteria $criteria)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonMedicalAllergy.gibbonMedicalAllergyID',
                'gibbonMedicalAllergy.gibbonPersonID',
                'gibbonMedicalAllergy.allergenName',
                'gibbonMedicalAllergy.allergenType',
                'gibbonMedicalAllergy.severity',
                'gibbonMedicalAllergy.reaction',
                'gibbonMedicalAllergy.treatment',
                'gibbonMedicalAllergy.epiPenRequired',
                'gibbonMedicalAllergy.epiPenLocation',
                'gibbonMedicalAllergy.verified',
                'gibbonMedicalAllergy.timestampCreated',
                'gibbonPerson.preferredName',
                'gibbonPerson.surname',
                'gibbonPerson.image_240',
                'gibbonPerson.dob',
            ])
            ->innerJoin('gibbonPerson', 'gibbonMedicalAllergy.gibbonPersonID=gibbonPerson.gibbonPersonID')
            ->where('gibbonMedicalAllergy.active=:active')
            ->bindValue('active', 'Y');

        $criteria->addFilterRules([
            'allergenType' => function ($query, $allergenType) {
                return $query
                    ->where('gibbonMedicalAllergy.allergenType=:allergenType')
                    ->bindValue('allergenType', $allergenType);
            },
            'severity' => function ($query, $severity) {
                return $query
                    ->where('gibbonMedicalAllergy.severity=:severity')
                    ->bindValue('severity', $severity);
            },
        ]);

        return $this->runQuery($query, $criteria);
    }

    /**
     * Query allergy history for a specific child.
     *
     * @param QueryCriteria $criteria
     * @param int $gibbonPersonID
     * @return DataSet
     */
    public function queryAllergiesByPerson(QueryCriteria $criteria, $gibbonPersonID)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonMedicalAllergy.gibbonMedicalAllergyID',
                'gibbonMedicalAllergy.allergenName',
                'gibbonMedicalAllergy.allergenType',
                'gibbonMedicalAllergy.severity',
                'gibbonMedicalAllergy.reaction',
                'gibbonMedicalAllergy.treatment',
                'gibbonMedicalAllergy.epiPenRequired',
                'gibbonMedicalAllergy.epiPenLocation',
                'gibbonMedicalAllergy.diagnosedDate',
                'gibbonMedicalAllergy.diagnosedBy',
                'gibbonMedicalAllergy.verified',
                'gibbonMedicalAllergy.verifiedDate',
                'gibbonMedicalAllergy.notes',
                'gibbonMedicalAllergy.active',
                'gibbonMedicalAllergy.timestampCreated',
                'createdBy.preferredName as createdByName',
                'createdBy.surname as createdBySurname',
                'verifiedBy.preferredName as verifiedByName',
                'verifiedBy.surname as verifiedBySurname',
            ])
            ->leftJoin('gibbonPerson as createdBy', 'gibbonMedicalAllergy.createdByID=createdBy.gibbonPersonID')
            ->leftJoin('gibbonPerson as verifiedBy', 'gibbonMedicalAllergy.verifiedByID=verifiedBy.gibbonPersonID')
            ->where('gibbonMedicalAllergy.gibbonPersonID=:gibbonPersonID')
            ->bindValue('gibbonPersonID', $gibbonPersonID);

        return $this->runQuery($query, $criteria);
    }

    /**
     * Get allergies for a specific child.
     *
     * @param int $gibbonPersonID
     * @param bool $activeOnly
     * @return \Gibbon\Database\Result
     */
    public function selectAllergiesByPerson($gibbonPersonID, $activeOnly = true)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols([
                'gibbonMedicalAllergy.*',
                'createdBy.preferredName as createdByName',
                'createdBy.surname as createdBySurname',
                'verifiedBy.preferredName as verifiedByName',
                'verifiedBy.surname as verifiedBySurname',
            ])
            ->leftJoin('gibbonPerson as createdBy', 'gibbonMedicalAllergy.createdByID=createdBy.gibbonPersonID')
            ->leftJoin('gibbonPerson as verifiedBy', 'gibbonMedicalAllergy.verifiedByID=verifiedBy.gibbonPersonID')
            ->where('gibbonMedicalAllergy.gibbonPersonID=:gibbonPersonID')
            ->bindValue('gibbonPersonID', $gibbonPersonID)
            ->orderBy(['gibbonMedicalAllergy.severity DESC', 'gibbonMedicalAllergy.allergenName ASC']);

        if ($activeOnly) {
            $query->where('gibbonMedicalAllergy.active=:active')
                ->bindValue('active', 'Y');
        }

        return $this->runSelect($query);
    }

    /**
     * Get food allergies for a specific child (for meal integration).
     *
     * @param int $gibbonPersonID
     * @return \Gibbon\Database\Result
     */
    public function selectFoodAllergiesByPerson($gibbonPersonID)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols([
                'gibbonMedicalAllergy.gibbonMedicalAllergyID',
                'gibbonMedicalAllergy.allergenName',
                'gibbonMedicalAllergy.severity',
                'gibbonMedicalAllergy.reaction',
                'gibbonMedicalAllergy.treatment',
                'gibbonMedicalAllergy.epiPenRequired',
                'gibbonMedicalAllergy.epiPenLocation',
            ])
            ->where('gibbonMedicalAllergy.gibbonPersonID=:gibbonPersonID')
            ->bindValue('gibbonPersonID', $gibbonPersonID)
            ->where('gibbonMedicalAllergy.allergenType=:allergenType')
            ->bindValue('allergenType', 'Food')
            ->where('gibbonMedicalAllergy.active=:active')
            ->bindValue('active', 'Y')
            ->orderBy(['gibbonMedicalAllergy.severity DESC', 'gibbonMedicalAllergy.allergenName ASC']);

        return $this->runSelect($query);
    }

    /**
     * Check if a child has a specific allergy.
     *
     * @param int $gibbonPersonID
     * @param string $allergenName
     * @return array|false
     */
    public function getAllergyByPersonAndAllergen($gibbonPersonID, $allergenName)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols(['*'])
            ->where('gibbonPersonID=:gibbonPersonID')
            ->bindValue('gibbonPersonID', $gibbonPersonID)
            ->where('allergenName=:allergenName')
            ->bindValue('allergenName', $allergenName)
            ->where('active=:active')
            ->bindValue('active', 'Y');

        $result = $this->runSelect($query);
        return $result->isNotEmpty() ? $result->fetch() : false;
    }

    /**
     * Get allergy by ID with child details.
     *
     * @param int $gibbonMedicalAllergyID
     * @return array|false
     */
    public function getAllergyWithDetails($gibbonMedicalAllergyID)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols([
                'gibbonMedicalAllergy.*',
                'gibbonPerson.preferredName',
                'gibbonPerson.surname',
                'gibbonPerson.image_240',
                'gibbonPerson.dob',
                'createdBy.preferredName as createdByName',
                'createdBy.surname as createdBySurname',
                'verifiedBy.preferredName as verifiedByName',
                'verifiedBy.surname as verifiedBySurname',
            ])
            ->innerJoin('gibbonPerson', 'gibbonMedicalAllergy.gibbonPersonID=gibbonPerson.gibbonPersonID')
            ->leftJoin('gibbonPerson as createdBy', 'gibbonMedicalAllergy.createdByID=createdBy.gibbonPersonID')
            ->leftJoin('gibbonPerson as verifiedBy', 'gibbonMedicalAllergy.verifiedByID=verifiedBy.gibbonPersonID')
            ->where('gibbonMedicalAllergy.gibbonMedicalAllergyID=:gibbonMedicalAllergyID')
            ->bindValue('gibbonMedicalAllergyID', $gibbonMedicalAllergyID);

        $result = $this->runSelect($query);
        return $result->isNotEmpty() ? $result->fetch() : false;
    }

    /**
     * Get allergy summary for all children with allergies.
     *
     * @return array
     */
    public function getAllergySummary()
    {
        $data = [];
        $sql = "SELECT
                    allergenType,
                    severity,
                    COUNT(*) as totalCount,
                    SUM(CASE WHEN epiPenRequired='Y' THEN 1 ELSE 0 END) as epiPenCount,
                    SUM(CASE WHEN verified='Y' THEN 1 ELSE 0 END) as verifiedCount,
                    SUM(CASE WHEN verified='N' THEN 1 ELSE 0 END) as unverifiedCount
                FROM gibbonMedicalAllergy
                WHERE active='Y'
                GROUP BY allergenType, severity
                ORDER BY
                    FIELD(allergenType, 'Food', 'Medication', 'Environmental', 'Insect', 'Other'),
                    FIELD(severity, 'Life-Threatening', 'Severe', 'Moderate', 'Mild')";

        return $this->db()->select($sql, $data)->fetchAll();
    }

    /**
     * Get children with severe or life-threatening allergies.
     *
     * @return \Gibbon\Database\Result
     */
    public function selectChildrenWithSevereAllergies()
    {
        $data = [];
        $sql = "SELECT DISTINCT
                    gibbonPerson.gibbonPersonID,
                    gibbonPerson.preferredName,
                    gibbonPerson.surname,
                    gibbonPerson.image_240,
                    GROUP_CONCAT(
                        CONCAT(gibbonMedicalAllergy.allergenName, ' (', gibbonMedicalAllergy.severity, ')')
                        ORDER BY gibbonMedicalAllergy.severity DESC
                        SEPARATOR ', '
                    ) as allergyList,
                    MAX(CASE WHEN gibbonMedicalAllergy.epiPenRequired='Y' THEN 1 ELSE 0 END) as hasEpiPen
                FROM gibbonMedicalAllergy
                INNER JOIN gibbonPerson ON gibbonMedicalAllergy.gibbonPersonID=gibbonPerson.gibbonPersonID
                WHERE gibbonMedicalAllergy.active='Y'
                AND gibbonMedicalAllergy.severity IN ('Severe', 'Life-Threatening')
                AND gibbonPerson.status='Full'
                GROUP BY gibbonPerson.gibbonPersonID
                ORDER BY gibbonPerson.surname, gibbonPerson.preferredName";

        return $this->db()->select($sql, $data);
    }

    /**
     * Get children requiring EpiPen.
     *
     * @return \Gibbon\Database\Result
     */
    public function selectChildrenWithEpiPen()
    {
        $data = [];
        $sql = "SELECT DISTINCT
                    gibbonPerson.gibbonPersonID,
                    gibbonPerson.preferredName,
                    gibbonPerson.surname,
                    gibbonPerson.image_240,
                    GROUP_CONCAT(
                        CONCAT(gibbonMedicalAllergy.allergenName, ': ', COALESCE(gibbonMedicalAllergy.epiPenLocation, 'Location not specified'))
                        ORDER BY gibbonMedicalAllergy.allergenName
                        SEPARATOR '; '
                    ) as epiPenDetails
                FROM gibbonMedicalAllergy
                INNER JOIN gibbonPerson ON gibbonMedicalAllergy.gibbonPersonID=gibbonPerson.gibbonPersonID
                WHERE gibbonMedicalAllergy.active='Y'
                AND gibbonMedicalAllergy.epiPenRequired='Y'
                AND gibbonPerson.status='Full'
                GROUP BY gibbonPerson.gibbonPersonID
                ORDER BY gibbonPerson.surname, gibbonPerson.preferredName";

        return $this->db()->select($sql, $data);
    }

    /**
     * Get unverified allergies needing staff verification.
     *
     * @return \Gibbon\Database\Result
     */
    public function selectUnverifiedAllergies()
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols([
                'gibbonMedicalAllergy.*',
                'gibbonPerson.preferredName',
                'gibbonPerson.surname',
                'gibbonPerson.image_240',
                'createdBy.preferredName as createdByName',
                'createdBy.surname as createdBySurname',
            ])
            ->innerJoin('gibbonPerson', 'gibbonMedicalAllergy.gibbonPersonID=gibbonPerson.gibbonPersonID')
            ->leftJoin('gibbonPerson as createdBy', 'gibbonMedicalAllergy.createdByID=createdBy.gibbonPersonID')
            ->where('gibbonMedicalAllergy.active=:active')
            ->bindValue('active', 'Y')
            ->where('gibbonMedicalAllergy.verified=:verified')
            ->bindValue('verified', 'N')
            ->where('gibbonPerson.status=:status')
            ->bindValue('status', 'Full')
            ->orderBy(['gibbonMedicalAllergy.severity DESC', 'gibbonMedicalAllergy.timestampCreated DESC']);

        return $this->runSelect($query);
    }

    /**
     * Check allergen against child's food allergies (for meal integration).
     *
     * @param int $gibbonPersonID
     * @param string $allergenName
     * @return array|false Returns allergy details if match found
     */
    public function checkAllergenMatch($gibbonPersonID, $allergenName)
    {
        $data = [
            'gibbonPersonID' => $gibbonPersonID,
            'allergenName' => '%' . $allergenName . '%',
        ];
        $sql = "SELECT gibbonMedicalAllergy.*
                FROM gibbonMedicalAllergy
                WHERE gibbonPersonID=:gibbonPersonID
                AND active='Y'
                AND (
                    allergenName LIKE :allergenName
                )
                ORDER BY severity DESC
                LIMIT 1";

        $result = $this->db()->select($sql, $data);
        return $result->isNotEmpty() ? $result->fetch() : false;
    }

    /**
     * Add a new allergy record.
     *
     * @param int $gibbonPersonID
     * @param string $allergenName
     * @param string $allergenType
     * @param string $severity
     * @param int $createdByID
     * @param array $additionalData
     * @return int|false
     */
    public function addAllergy($gibbonPersonID, $allergenName, $allergenType, $severity, $createdByID, $additionalData = [])
    {
        // Check if this allergy already exists for this child
        $existing = $this->getAllergyByPersonAndAllergen($gibbonPersonID, $allergenName);

        if ($existing) {
            // Reactivate if inactive, or return existing ID
            if ($existing['active'] === 'N') {
                $this->update($existing['gibbonMedicalAllergyID'], array_merge([
                    'active' => 'Y',
                    'allergenType' => $allergenType,
                    'severity' => $severity,
                ], $additionalData));
                return $existing['gibbonMedicalAllergyID'];
            }
            return false; // Already exists and active
        }

        // Create new allergy record
        return $this->insert(array_merge([
            'gibbonPersonID' => $gibbonPersonID,
            'allergenName' => $allergenName,
            'allergenType' => $allergenType,
            'severity' => $severity,
            'createdByID' => $createdByID,
        ], $additionalData));
    }

    /**
     * Verify an allergy record.
     *
     * @param int $gibbonMedicalAllergyID
     * @param int $verifiedByID
     * @return bool
     */
    public function verifyAllergy($gibbonMedicalAllergyID, $verifiedByID)
    {
        return $this->update($gibbonMedicalAllergyID, [
            'verified' => 'Y',
            'verifiedByID' => $verifiedByID,
            'verifiedDate' => date('Y-m-d'),
        ]);
    }

    /**
     * Deactivate an allergy record (soft delete).
     *
     * @param int $gibbonMedicalAllergyID
     * @return bool
     */
    public function deactivateAllergy($gibbonMedicalAllergyID)
    {
        return $this->update($gibbonMedicalAllergyID, [
            'active' => 'N',
        ]);
    }

    /**
     * Get allergy statistics for a specific child.
     *
     * @param int $gibbonPersonID
     * @return array
     */
    public function getAllergyStatsByPerson($gibbonPersonID)
    {
        $data = ['gibbonPersonID' => $gibbonPersonID];
        $sql = "SELECT
                    COUNT(*) as totalAllergies,
                    SUM(CASE WHEN allergenType='Food' THEN 1 ELSE 0 END) as foodAllergies,
                    SUM(CASE WHEN severity IN ('Severe', 'Life-Threatening') THEN 1 ELSE 0 END) as severeAllergies,
                    SUM(CASE WHEN epiPenRequired='Y' THEN 1 ELSE 0 END) as epiPenRequired,
                    SUM(CASE WHEN verified='Y' THEN 1 ELSE 0 END) as verifiedCount
                FROM gibbonMedicalAllergy
                WHERE gibbonPersonID=:gibbonPersonID
                AND active='Y'";

        return $this->db()->selectOne($sql, $data) ?: [
            'totalAllergies' => 0,
            'foodAllergies' => 0,
            'severeAllergies' => 0,
            'epiPenRequired' => 0,
            'verifiedCount' => 0,
        ];
    }

    /**
     * Get common allergen list from menu.
     *
     * @param string|null $category
     * @return \Gibbon\Database\Result
     */
    public function selectCommonAllergens($category = null)
    {
        $query = $this
            ->newSelect()
            ->from('gibbonMedicalAllergenMenu')
            ->cols([
                'gibbonMedicalAllergenMenuID',
                'allergenName',
                'allergenCategory',
                'commonSymptoms',
                'avoidanceGuidelines',
                'emergencyResponse',
                'aliases',
            ])
            ->where('active=:active')
            ->bindValue('active', 'Y')
            ->orderBy(['displayOrder ASC']);

        if ($category !== null) {
            $query->where('allergenCategory=:category')
                ->bindValue('category', $category);
        }

        return $this->runSelect($query);
    }
}
