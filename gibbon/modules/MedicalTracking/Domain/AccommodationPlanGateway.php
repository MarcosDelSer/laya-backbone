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
 * Medical Tracking Accommodation Plan Gateway
 *
 * Handles accommodation plans for children including dietary substitutions,
 * emergency response plans, and staff training records for childcare settings.
 *
 * @version v1.0.00
 * @since   v1.0.00
 */
class AccommodationPlanGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'gibbonMedicalAccommodationPlan';
    private static $primaryKey = 'gibbonMedicalAccommodationPlanID';

    private static $searchableColumns = ['gibbonPerson.preferredName', 'gibbonPerson.surname', 'gibbonMedicalAccommodationPlan.planName', 'gibbonMedicalAccommodationPlan.notes'];

    /**
     * Query accommodation plans with criteria support.
     *
     * @param QueryCriteria $criteria
     * @return DataSet
     */
    public function queryAccommodationPlans(QueryCriteria $criteria)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonMedicalAccommodationPlan.gibbonMedicalAccommodationPlanID',
                'gibbonMedicalAccommodationPlan.gibbonPersonID',
                'gibbonMedicalAccommodationPlan.gibbonMedicalAllergyID',
                'gibbonMedicalAccommodationPlan.planName',
                'gibbonMedicalAccommodationPlan.planType',
                'gibbonMedicalAccommodationPlan.effectiveDate',
                'gibbonMedicalAccommodationPlan.expiryDate',
                'gibbonMedicalAccommodationPlan.approved',
                'gibbonMedicalAccommodationPlan.approvedDate',
                'gibbonMedicalAccommodationPlan.notes',
                'gibbonMedicalAccommodationPlan.active',
                'gibbonMedicalAccommodationPlan.timestampCreated',
                'gibbonPerson.preferredName',
                'gibbonPerson.surname',
                'gibbonPerson.image_240',
                'createdBy.preferredName as createdByName',
                'createdBy.surname as createdBySurname',
                'approvedBy.preferredName as approvedByName',
                'approvedBy.surname as approvedBySurname',
            ])
            ->innerJoin('gibbonPerson', 'gibbonMedicalAccommodationPlan.gibbonPersonID=gibbonPerson.gibbonPersonID')
            ->leftJoin('gibbonPerson as createdBy', 'gibbonMedicalAccommodationPlan.createdByID=createdBy.gibbonPersonID')
            ->leftJoin('gibbonPerson as approvedBy', 'gibbonMedicalAccommodationPlan.approvedByID=approvedBy.gibbonPersonID');

        $criteria->addFilterRules([
            'child' => function ($query, $gibbonPersonID) {
                return $query
                    ->where('gibbonMedicalAccommodationPlan.gibbonPersonID=:gibbonPersonID')
                    ->bindValue('gibbonPersonID', $gibbonPersonID);
            },
            'planType' => function ($query, $planType) {
                return $query
                    ->where('gibbonMedicalAccommodationPlan.planType=:planType')
                    ->bindValue('planType', $planType);
            },
            'approved' => function ($query, $value) {
                return $query
                    ->where('gibbonMedicalAccommodationPlan.approved=:approved')
                    ->bindValue('approved', $value);
            },
            'active' => function ($query, $value) {
                return $query
                    ->where('gibbonMedicalAccommodationPlan.active=:active')
                    ->bindValue('active', $value);
            },
            'allergy' => function ($query, $gibbonMedicalAllergyID) {
                return $query
                    ->where('gibbonMedicalAccommodationPlan.gibbonMedicalAllergyID=:gibbonMedicalAllergyID')
                    ->bindValue('gibbonMedicalAllergyID', $gibbonMedicalAllergyID);
            },
        ]);

        return $this->runQuery($query, $criteria);
    }

    /**
     * Query active accommodation plans with criteria support.
     *
     * @param QueryCriteria $criteria
     * @return DataSet
     */
    public function queryActiveAccommodationPlans(QueryCriteria $criteria)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonMedicalAccommodationPlan.gibbonMedicalAccommodationPlanID',
                'gibbonMedicalAccommodationPlan.gibbonPersonID',
                'gibbonMedicalAccommodationPlan.planName',
                'gibbonMedicalAccommodationPlan.planType',
                'gibbonMedicalAccommodationPlan.effectiveDate',
                'gibbonMedicalAccommodationPlan.expiryDate',
                'gibbonMedicalAccommodationPlan.approved',
                'gibbonMedicalAccommodationPlan.timestampCreated',
                'gibbonPerson.preferredName',
                'gibbonPerson.surname',
                'gibbonPerson.image_240',
                'gibbonPerson.dob',
            ])
            ->innerJoin('gibbonPerson', 'gibbonMedicalAccommodationPlan.gibbonPersonID=gibbonPerson.gibbonPersonID')
            ->where('gibbonMedicalAccommodationPlan.active=:active')
            ->bindValue('active', 'Y')
            ->where('(gibbonMedicalAccommodationPlan.expiryDate IS NULL OR gibbonMedicalAccommodationPlan.expiryDate >= CURDATE())');

        $criteria->addFilterRules([
            'planType' => function ($query, $planType) {
                return $query
                    ->where('gibbonMedicalAccommodationPlan.planType=:planType')
                    ->bindValue('planType', $planType);
            },
            'approved' => function ($query, $value) {
                return $query
                    ->where('gibbonMedicalAccommodationPlan.approved=:approved')
                    ->bindValue('approved', $value);
            },
        ]);

        return $this->runQuery($query, $criteria);
    }

    /**
     * Query accommodation plans for a specific child.
     *
     * @param QueryCriteria $criteria
     * @param int $gibbonPersonID
     * @return DataSet
     */
    public function queryAccommodationPlansByPerson(QueryCriteria $criteria, $gibbonPersonID)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonMedicalAccommodationPlan.gibbonMedicalAccommodationPlanID',
                'gibbonMedicalAccommodationPlan.gibbonMedicalAllergyID',
                'gibbonMedicalAccommodationPlan.planName',
                'gibbonMedicalAccommodationPlan.planType',
                'gibbonMedicalAccommodationPlan.effectiveDate',
                'gibbonMedicalAccommodationPlan.expiryDate',
                'gibbonMedicalAccommodationPlan.approved',
                'gibbonMedicalAccommodationPlan.approvedDate',
                'gibbonMedicalAccommodationPlan.notes',
                'gibbonMedicalAccommodationPlan.active',
                'gibbonMedicalAccommodationPlan.timestampCreated',
                'createdBy.preferredName as createdByName',
                'createdBy.surname as createdBySurname',
                'approvedBy.preferredName as approvedByName',
                'approvedBy.surname as approvedBySurname',
                'gibbonMedicalAllergy.allergenName',
            ])
            ->leftJoin('gibbonPerson as createdBy', 'gibbonMedicalAccommodationPlan.createdByID=createdBy.gibbonPersonID')
            ->leftJoin('gibbonPerson as approvedBy', 'gibbonMedicalAccommodationPlan.approvedByID=approvedBy.gibbonPersonID')
            ->leftJoin('gibbonMedicalAllergy', 'gibbonMedicalAccommodationPlan.gibbonMedicalAllergyID=gibbonMedicalAllergy.gibbonMedicalAllergyID')
            ->where('gibbonMedicalAccommodationPlan.gibbonPersonID=:gibbonPersonID')
            ->bindValue('gibbonPersonID', $gibbonPersonID);

        return $this->runQuery($query, $criteria);
    }

    /**
     * Get accommodation plans for a specific child.
     *
     * @param int $gibbonPersonID
     * @param bool $activeOnly
     * @return \Gibbon\Database\Result
     */
    public function selectAccommodationPlansByPerson($gibbonPersonID, $activeOnly = true)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols([
                'gibbonMedicalAccommodationPlan.*',
                'createdBy.preferredName as createdByName',
                'createdBy.surname as createdBySurname',
                'approvedBy.preferredName as approvedByName',
                'approvedBy.surname as approvedBySurname',
                'gibbonMedicalAllergy.allergenName',
            ])
            ->leftJoin('gibbonPerson as createdBy', 'gibbonMedicalAccommodationPlan.createdByID=createdBy.gibbonPersonID')
            ->leftJoin('gibbonPerson as approvedBy', 'gibbonMedicalAccommodationPlan.approvedByID=approvedBy.gibbonPersonID')
            ->leftJoin('gibbonMedicalAllergy', 'gibbonMedicalAccommodationPlan.gibbonMedicalAllergyID=gibbonMedicalAllergy.gibbonMedicalAllergyID')
            ->where('gibbonMedicalAccommodationPlan.gibbonPersonID=:gibbonPersonID')
            ->bindValue('gibbonPersonID', $gibbonPersonID)
            ->orderBy(['gibbonMedicalAccommodationPlan.planType ASC', 'gibbonMedicalAccommodationPlan.planName ASC']);

        if ($activeOnly) {
            $query->where('gibbonMedicalAccommodationPlan.active=:active')
                ->bindValue('active', 'Y');
        }

        return $this->runSelect($query);
    }

    /**
     * Get accommodation plan by ID with full details.
     *
     * @param int $gibbonMedicalAccommodationPlanID
     * @return array|false
     */
    public function getAccommodationPlanWithDetails($gibbonMedicalAccommodationPlanID)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols([
                'gibbonMedicalAccommodationPlan.*',
                'gibbonPerson.preferredName',
                'gibbonPerson.surname',
                'gibbonPerson.image_240',
                'gibbonPerson.dob',
                'createdBy.preferredName as createdByName',
                'createdBy.surname as createdBySurname',
                'approvedBy.preferredName as approvedByName',
                'approvedBy.surname as approvedBySurname',
                'gibbonMedicalAllergy.allergenName',
                'gibbonMedicalAllergy.allergenType',
                'gibbonMedicalAllergy.severity',
            ])
            ->innerJoin('gibbonPerson', 'gibbonMedicalAccommodationPlan.gibbonPersonID=gibbonPerson.gibbonPersonID')
            ->leftJoin('gibbonPerson as createdBy', 'gibbonMedicalAccommodationPlan.createdByID=createdBy.gibbonPersonID')
            ->leftJoin('gibbonPerson as approvedBy', 'gibbonMedicalAccommodationPlan.approvedByID=approvedBy.gibbonPersonID')
            ->leftJoin('gibbonMedicalAllergy', 'gibbonMedicalAccommodationPlan.gibbonMedicalAllergyID=gibbonMedicalAllergy.gibbonMedicalAllergyID')
            ->where('gibbonMedicalAccommodationPlan.gibbonMedicalAccommodationPlanID=:gibbonMedicalAccommodationPlanID')
            ->bindValue('gibbonMedicalAccommodationPlanID', $gibbonMedicalAccommodationPlanID);

        $result = $this->runSelect($query);
        return $result->isNotEmpty() ? $result->fetch() : false;
    }

    // ==================== DIETARY SUBSTITUTIONS ====================

    /**
     * Query dietary substitutions for all children.
     *
     * @param QueryCriteria $criteria
     * @return DataSet
     */
    public function queryDietarySubstitutions(QueryCriteria $criteria)
    {
        $query = $this
            ->newQuery()
            ->from('gibbonMedicalDietarySubstitution')
            ->cols([
                'gibbonMedicalDietarySubstitution.gibbonMedicalDietarySubstitutionID',
                'gibbonMedicalDietarySubstitution.gibbonMedicalAccommodationPlanID',
                'gibbonMedicalDietarySubstitution.originalItem',
                'gibbonMedicalDietarySubstitution.substituteItem',
                'gibbonMedicalDietarySubstitution.mealType',
                'gibbonMedicalDietarySubstitution.notes',
                'gibbonMedicalDietarySubstitution.active',
                'gibbonMedicalAccommodationPlan.gibbonPersonID',
                'gibbonMedicalAccommodationPlan.planName',
                'gibbonPerson.preferredName',
                'gibbonPerson.surname',
                'gibbonPerson.image_240',
            ])
            ->innerJoin('gibbonMedicalAccommodationPlan', 'gibbonMedicalDietarySubstitution.gibbonMedicalAccommodationPlanID=gibbonMedicalAccommodationPlan.gibbonMedicalAccommodationPlanID')
            ->innerJoin('gibbonPerson', 'gibbonMedicalAccommodationPlan.gibbonPersonID=gibbonPerson.gibbonPersonID');

        $criteria->addFilterRules([
            'child' => function ($query, $gibbonPersonID) {
                return $query
                    ->where('gibbonMedicalAccommodationPlan.gibbonPersonID=:gibbonPersonID')
                    ->bindValue('gibbonPersonID', $gibbonPersonID);
            },
            'mealType' => function ($query, $mealType) {
                return $query
                    ->where('gibbonMedicalDietarySubstitution.mealType=:mealType')
                    ->bindValue('mealType', $mealType);
            },
            'active' => function ($query, $value) {
                return $query
                    ->where('gibbonMedicalDietarySubstitution.active=:active')
                    ->bindValue('active', $value);
            },
        ]);

        return $this->runQuery($query, $criteria);
    }

    /**
     * Get dietary substitutions for a specific child.
     *
     * @param int $gibbonPersonID
     * @param bool $activeOnly
     * @return \Gibbon\Database\Result
     */
    public function selectDietarySubstitutionsByPerson($gibbonPersonID, $activeOnly = true)
    {
        $data = ['gibbonPersonID' => $gibbonPersonID];
        $sql = "SELECT
                    ds.gibbonMedicalDietarySubstitutionID,
                    ds.gibbonMedicalAccommodationPlanID,
                    ds.originalItem,
                    ds.substituteItem,
                    ds.mealType,
                    ds.notes,
                    ds.active,
                    ap.planName,
                    ma.allergenName
                FROM gibbonMedicalDietarySubstitution ds
                INNER JOIN gibbonMedicalAccommodationPlan ap ON ds.gibbonMedicalAccommodationPlanID=ap.gibbonMedicalAccommodationPlanID
                LEFT JOIN gibbonMedicalAllergy ma ON ap.gibbonMedicalAllergyID=ma.gibbonMedicalAllergyID
                WHERE ap.gibbonPersonID=:gibbonPersonID
                AND ap.active='Y'";

        if ($activeOnly) {
            $sql .= " AND ds.active='Y'";
        }

        $sql .= " ORDER BY ds.mealType, ds.originalItem";

        return $this->db()->select($sql, $data);
    }

    /**
     * Get dietary substitutions for a specific accommodation plan.
     *
     * @param int $gibbonMedicalAccommodationPlanID
     * @return \Gibbon\Database\Result
     */
    public function selectDietarySubstitutionsByPlan($gibbonMedicalAccommodationPlanID)
    {
        $query = $this
            ->newSelect()
            ->from('gibbonMedicalDietarySubstitution')
            ->cols(['*'])
            ->where('gibbonMedicalAccommodationPlanID=:gibbonMedicalAccommodationPlanID')
            ->bindValue('gibbonMedicalAccommodationPlanID', $gibbonMedicalAccommodationPlanID)
            ->where('active=:active')
            ->bindValue('active', 'Y')
            ->orderBy(['mealType ASC', 'originalItem ASC']);

        return $this->runSelect($query);
    }

    /**
     * Add a dietary substitution.
     *
     * @param int $gibbonMedicalAccommodationPlanID
     * @param string $originalItem
     * @param string $substituteItem
     * @param string $mealType
     * @param string|null $notes
     * @return int|false
     */
    public function addDietarySubstitution($gibbonMedicalAccommodationPlanID, $originalItem, $substituteItem, $mealType, $notes = null)
    {
        $data = [
            'gibbonMedicalAccommodationPlanID' => $gibbonMedicalAccommodationPlanID,
            'originalItem' => $originalItem,
            'substituteItem' => $substituteItem,
            'mealType' => $mealType,
            'notes' => $notes,
            'active' => 'Y',
        ];

        $sql = "INSERT INTO gibbonMedicalDietarySubstitution
                (gibbonMedicalAccommodationPlanID, originalItem, substituteItem, mealType, notes, active)
                VALUES (:gibbonMedicalAccommodationPlanID, :originalItem, :substituteItem, :mealType, :notes, :active)";

        return $this->db()->insert($sql, $data);
    }

    /**
     * Update a dietary substitution.
     *
     * @param int $gibbonMedicalDietarySubstitutionID
     * @param array $data
     * @return bool
     */
    public function updateDietarySubstitution($gibbonMedicalDietarySubstitutionID, $data)
    {
        $data['gibbonMedicalDietarySubstitutionID'] = $gibbonMedicalDietarySubstitutionID;
        $fields = [];

        foreach ($data as $key => $value) {
            if ($key !== 'gibbonMedicalDietarySubstitutionID') {
                $fields[] = "$key=:$key";
            }
        }

        $sql = "UPDATE gibbonMedicalDietarySubstitution SET " . implode(', ', $fields) .
               " WHERE gibbonMedicalDietarySubstitutionID=:gibbonMedicalDietarySubstitutionID";

        return $this->db()->update($sql, $data);
    }

    // ==================== EMERGENCY PLANS ====================

    /**
     * Query emergency plans with criteria support.
     *
     * @param QueryCriteria $criteria
     * @return DataSet
     */
    public function queryEmergencyPlans(QueryCriteria $criteria)
    {
        $query = $this
            ->newQuery()
            ->from('gibbonMedicalEmergencyPlan')
            ->cols([
                'gibbonMedicalEmergencyPlan.gibbonMedicalEmergencyPlanID',
                'gibbonMedicalEmergencyPlan.gibbonMedicalAccommodationPlanID',
                'gibbonMedicalEmergencyPlan.triggerCondition',
                'gibbonMedicalEmergencyPlan.severityLevel',
                'gibbonMedicalEmergencyPlan.immediateActions',
                'gibbonMedicalEmergencyPlan.medicationRequired',
                'gibbonMedicalEmergencyPlan.medicationLocation',
                'gibbonMedicalEmergencyPlan.callEmergencyServices',
                'gibbonMedicalEmergencyPlan.parentNotification',
                'gibbonMedicalEmergencyPlan.additionalInstructions',
                'gibbonMedicalEmergencyPlan.lastReviewedDate',
                'gibbonMedicalEmergencyPlan.active',
                'gibbonMedicalAccommodationPlan.gibbonPersonID',
                'gibbonMedicalAccommodationPlan.planName',
                'gibbonPerson.preferredName',
                'gibbonPerson.surname',
                'gibbonPerson.image_240',
            ])
            ->innerJoin('gibbonMedicalAccommodationPlan', 'gibbonMedicalEmergencyPlan.gibbonMedicalAccommodationPlanID=gibbonMedicalAccommodationPlan.gibbonMedicalAccommodationPlanID')
            ->innerJoin('gibbonPerson', 'gibbonMedicalAccommodationPlan.gibbonPersonID=gibbonPerson.gibbonPersonID');

        $criteria->addFilterRules([
            'child' => function ($query, $gibbonPersonID) {
                return $query
                    ->where('gibbonMedicalAccommodationPlan.gibbonPersonID=:gibbonPersonID')
                    ->bindValue('gibbonPersonID', $gibbonPersonID);
            },
            'severityLevel' => function ($query, $severityLevel) {
                return $query
                    ->where('gibbonMedicalEmergencyPlan.severityLevel=:severityLevel')
                    ->bindValue('severityLevel', $severityLevel);
            },
            'active' => function ($query, $value) {
                return $query
                    ->where('gibbonMedicalEmergencyPlan.active=:active')
                    ->bindValue('active', $value);
            },
        ]);

        return $this->runQuery($query, $criteria);
    }

    /**
     * Get emergency plans for a specific child.
     *
     * @param int $gibbonPersonID
     * @param bool $activeOnly
     * @return \Gibbon\Database\Result
     */
    public function selectEmergencyPlansByPerson($gibbonPersonID, $activeOnly = true)
    {
        $data = ['gibbonPersonID' => $gibbonPersonID];
        $sql = "SELECT
                    ep.gibbonMedicalEmergencyPlanID,
                    ep.gibbonMedicalAccommodationPlanID,
                    ep.triggerCondition,
                    ep.severityLevel,
                    ep.immediateActions,
                    ep.medicationRequired,
                    ep.medicationLocation,
                    ep.callEmergencyServices,
                    ep.parentNotification,
                    ep.additionalInstructions,
                    ep.lastReviewedDate,
                    ep.active,
                    ap.planName,
                    ma.allergenName,
                    ma.severity as allergySeverity
                FROM gibbonMedicalEmergencyPlan ep
                INNER JOIN gibbonMedicalAccommodationPlan ap ON ep.gibbonMedicalAccommodationPlanID=ap.gibbonMedicalAccommodationPlanID
                LEFT JOIN gibbonMedicalAllergy ma ON ap.gibbonMedicalAllergyID=ma.gibbonMedicalAllergyID
                WHERE ap.gibbonPersonID=:gibbonPersonID
                AND ap.active='Y'";

        if ($activeOnly) {
            $sql .= " AND ep.active='Y'";
        }

        $sql .= " ORDER BY ep.severityLevel DESC, ep.triggerCondition ASC";

        return $this->db()->select($sql, $data);
    }

    /**
     * Get emergency plans for a specific accommodation plan.
     *
     * @param int $gibbonMedicalAccommodationPlanID
     * @return \Gibbon\Database\Result
     */
    public function selectEmergencyPlansByAccommodationPlan($gibbonMedicalAccommodationPlanID)
    {
        $query = $this
            ->newSelect()
            ->from('gibbonMedicalEmergencyPlan')
            ->cols(['*'])
            ->where('gibbonMedicalAccommodationPlanID=:gibbonMedicalAccommodationPlanID')
            ->bindValue('gibbonMedicalAccommodationPlanID', $gibbonMedicalAccommodationPlanID)
            ->where('active=:active')
            ->bindValue('active', 'Y')
            ->orderBy(['severityLevel DESC', 'triggerCondition ASC']);

        return $this->runSelect($query);
    }

    /**
     * Get emergency plan by ID with full details.
     *
     * @param int $gibbonMedicalEmergencyPlanID
     * @return array|false
     */
    public function getEmergencyPlanWithDetails($gibbonMedicalEmergencyPlanID)
    {
        $data = ['gibbonMedicalEmergencyPlanID' => $gibbonMedicalEmergencyPlanID];
        $sql = "SELECT
                    ep.*,
                    ap.gibbonPersonID,
                    ap.planName,
                    p.preferredName,
                    p.surname,
                    p.image_240,
                    ma.allergenName,
                    ma.severity as allergySeverity,
                    ma.reaction,
                    ma.epiPenRequired,
                    ma.epiPenLocation
                FROM gibbonMedicalEmergencyPlan ep
                INNER JOIN gibbonMedicalAccommodationPlan ap ON ep.gibbonMedicalAccommodationPlanID=ap.gibbonMedicalAccommodationPlanID
                INNER JOIN gibbonPerson p ON ap.gibbonPersonID=p.gibbonPersonID
                LEFT JOIN gibbonMedicalAllergy ma ON ap.gibbonMedicalAllergyID=ma.gibbonMedicalAllergyID
                WHERE ep.gibbonMedicalEmergencyPlanID=:gibbonMedicalEmergencyPlanID";

        $result = $this->db()->select($sql, $data);
        return $result->isNotEmpty() ? $result->fetch() : false;
    }

    /**
     * Add an emergency plan.
     *
     * @param int $gibbonMedicalAccommodationPlanID
     * @param string $triggerCondition
     * @param string $severityLevel
     * @param string $immediateActions
     * @param array $additionalData
     * @return int|false
     */
    public function addEmergencyPlan($gibbonMedicalAccommodationPlanID, $triggerCondition, $severityLevel, $immediateActions, $additionalData = [])
    {
        $data = array_merge([
            'gibbonMedicalAccommodationPlanID' => $gibbonMedicalAccommodationPlanID,
            'triggerCondition' => $triggerCondition,
            'severityLevel' => $severityLevel,
            'immediateActions' => $immediateActions,
            'active' => 'Y',
        ], $additionalData);

        $fields = array_keys($data);
        $placeholders = array_map(function ($f) { return ":$f"; }, $fields);

        $sql = "INSERT INTO gibbonMedicalEmergencyPlan (" . implode(', ', $fields) . ")
                VALUES (" . implode(', ', $placeholders) . ")";

        return $this->db()->insert($sql, $data);
    }

    /**
     * Update an emergency plan.
     *
     * @param int $gibbonMedicalEmergencyPlanID
     * @param array $data
     * @return bool
     */
    public function updateEmergencyPlan($gibbonMedicalEmergencyPlanID, $data)
    {
        $data['gibbonMedicalEmergencyPlanID'] = $gibbonMedicalEmergencyPlanID;
        $fields = [];

        foreach ($data as $key => $value) {
            if ($key !== 'gibbonMedicalEmergencyPlanID') {
                $fields[] = "$key=:$key";
            }
        }

        $sql = "UPDATE gibbonMedicalEmergencyPlan SET " . implode(', ', $fields) .
               " WHERE gibbonMedicalEmergencyPlanID=:gibbonMedicalEmergencyPlanID";

        return $this->db()->update($sql, $data);
    }

    // ==================== STAFF TRAINING RECORDS ====================

    /**
     * Query staff training records with criteria support.
     *
     * @param QueryCriteria $criteria
     * @return DataSet
     */
    public function queryStaffTrainingRecords(QueryCriteria $criteria)
    {
        $query = $this
            ->newQuery()
            ->from('gibbonMedicalStaffTraining')
            ->cols([
                'gibbonMedicalStaffTraining.gibbonMedicalStaffTrainingID',
                'gibbonMedicalStaffTraining.gibbonPersonID',
                'gibbonMedicalStaffTraining.trainingType',
                'gibbonMedicalStaffTraining.trainingName',
                'gibbonMedicalStaffTraining.completedDate',
                'gibbonMedicalStaffTraining.expiryDate',
                'gibbonMedicalStaffTraining.certificationNumber',
                'gibbonMedicalStaffTraining.provider',
                'gibbonMedicalStaffTraining.notes',
                'gibbonMedicalStaffTraining.verified',
                'gibbonMedicalStaffTraining.verifiedDate',
                'gibbonMedicalStaffTraining.timestampCreated',
                'staff.preferredName',
                'staff.surname',
                'staff.image_240',
                'verifiedBy.preferredName as verifiedByName',
                'verifiedBy.surname as verifiedBySurname',
            ])
            ->innerJoin('gibbonPerson as staff', 'gibbonMedicalStaffTraining.gibbonPersonID=staff.gibbonPersonID')
            ->leftJoin('gibbonPerson as verifiedBy', 'gibbonMedicalStaffTraining.verifiedByID=verifiedBy.gibbonPersonID');

        $criteria->addFilterRules([
            'staff' => function ($query, $gibbonPersonID) {
                return $query
                    ->where('gibbonMedicalStaffTraining.gibbonPersonID=:gibbonPersonID')
                    ->bindValue('gibbonPersonID', $gibbonPersonID);
            },
            'trainingType' => function ($query, $trainingType) {
                return $query
                    ->where('gibbonMedicalStaffTraining.trainingType=:trainingType')
                    ->bindValue('trainingType', $trainingType);
            },
            'verified' => function ($query, $value) {
                return $query
                    ->where('gibbonMedicalStaffTraining.verified=:verified')
                    ->bindValue('verified', $value);
            },
            'expired' => function ($query, $value) {
                if ($value === 'Y') {
                    return $query->where('gibbonMedicalStaffTraining.expiryDate < CURDATE()');
                } else {
                    return $query->where('(gibbonMedicalStaffTraining.expiryDate IS NULL OR gibbonMedicalStaffTraining.expiryDate >= CURDATE())');
                }
            },
        ]);

        return $this->runQuery($query, $criteria);
    }

    /**
     * Get training records for a specific staff member.
     *
     * @param int $gibbonPersonID
     * @param bool $activeOnly
     * @return \Gibbon\Database\Result
     */
    public function selectTrainingRecordsByStaff($gibbonPersonID, $activeOnly = true)
    {
        $query = $this
            ->newSelect()
            ->from('gibbonMedicalStaffTraining')
            ->cols([
                'gibbonMedicalStaffTraining.*',
                'verifiedBy.preferredName as verifiedByName',
                'verifiedBy.surname as verifiedBySurname',
            ])
            ->leftJoin('gibbonPerson as verifiedBy', 'gibbonMedicalStaffTraining.verifiedByID=verifiedBy.gibbonPersonID')
            ->where('gibbonMedicalStaffTraining.gibbonPersonID=:gibbonPersonID')
            ->bindValue('gibbonPersonID', $gibbonPersonID)
            ->orderBy(['gibbonMedicalStaffTraining.expiryDate ASC', 'gibbonMedicalStaffTraining.trainingType ASC']);

        if ($activeOnly) {
            $query->where('(gibbonMedicalStaffTraining.expiryDate IS NULL OR gibbonMedicalStaffTraining.expiryDate >= CURDATE())');
        }

        return $this->runSelect($query);
    }

    /**
     * Get staff members trained for a specific training type.
     *
     * @param string $trainingType
     * @param bool $activeOnly
     * @return \Gibbon\Database\Result
     */
    public function selectStaffByTrainingType($trainingType, $activeOnly = true)
    {
        $data = ['trainingType' => $trainingType];
        $sql = "SELECT DISTINCT
                    p.gibbonPersonID,
                    p.preferredName,
                    p.surname,
                    p.image_240,
                    st.completedDate,
                    st.expiryDate,
                    st.certificationNumber,
                    st.verified
                FROM gibbonMedicalStaffTraining st
                INNER JOIN gibbonPerson p ON st.gibbonPersonID=p.gibbonPersonID
                WHERE st.trainingType=:trainingType
                AND p.status='Full'";

        if ($activeOnly) {
            $sql .= " AND (st.expiryDate IS NULL OR st.expiryDate >= CURDATE())";
        }

        $sql .= " ORDER BY p.surname, p.preferredName";

        return $this->db()->select($sql, $data);
    }

    /**
     * Get expiring training records within a specified number of days.
     *
     * @param int $daysUntilExpiry
     * @return \Gibbon\Database\Result
     */
    public function selectExpiringTrainingRecords($daysUntilExpiry = 30)
    {
        $data = ['daysUntilExpiry' => $daysUntilExpiry];
        $sql = "SELECT
                    st.gibbonMedicalStaffTrainingID,
                    st.gibbonPersonID,
                    st.trainingType,
                    st.trainingName,
                    st.expiryDate,
                    DATEDIFF(st.expiryDate, CURDATE()) as daysRemaining,
                    p.preferredName,
                    p.surname,
                    p.email
                FROM gibbonMedicalStaffTraining st
                INNER JOIN gibbonPerson p ON st.gibbonPersonID=p.gibbonPersonID
                WHERE st.expiryDate IS NOT NULL
                AND st.expiryDate >= CURDATE()
                AND st.expiryDate <= DATE_ADD(CURDATE(), INTERVAL :daysUntilExpiry DAY)
                AND p.status='Full'
                ORDER BY st.expiryDate ASC, p.surname, p.preferredName";

        return $this->db()->select($sql, $data);
    }

    /**
     * Get expired training records.
     *
     * @return \Gibbon\Database\Result
     */
    public function selectExpiredTrainingRecords()
    {
        $data = [];
        $sql = "SELECT
                    st.gibbonMedicalStaffTrainingID,
                    st.gibbonPersonID,
                    st.trainingType,
                    st.trainingName,
                    st.expiryDate,
                    DATEDIFF(CURDATE(), st.expiryDate) as daysExpired,
                    p.preferredName,
                    p.surname,
                    p.email
                FROM gibbonMedicalStaffTraining st
                INNER JOIN gibbonPerson p ON st.gibbonPersonID=p.gibbonPersonID
                WHERE st.expiryDate IS NOT NULL
                AND st.expiryDate < CURDATE()
                AND p.status='Full'
                ORDER BY st.expiryDate DESC, p.surname, p.preferredName";

        return $this->db()->select($sql, $data);
    }

    /**
     * Add a staff training record.
     *
     * @param int $gibbonPersonID
     * @param string $trainingType
     * @param string $trainingName
     * @param string $completedDate
     * @param array $additionalData
     * @return int|false
     */
    public function addTrainingRecord($gibbonPersonID, $trainingType, $trainingName, $completedDate, $additionalData = [])
    {
        $data = array_merge([
            'gibbonPersonID' => $gibbonPersonID,
            'trainingType' => $trainingType,
            'trainingName' => $trainingName,
            'completedDate' => $completedDate,
            'verified' => 'N',
        ], $additionalData);

        $fields = array_keys($data);
        $placeholders = array_map(function ($f) { return ":$f"; }, $fields);

        $sql = "INSERT INTO gibbonMedicalStaffTraining (" . implode(', ', $fields) . ")
                VALUES (" . implode(', ', $placeholders) . ")";

        return $this->db()->insert($sql, $data);
    }

    /**
     * Verify a staff training record.
     *
     * @param int $gibbonMedicalStaffTrainingID
     * @param int $verifiedByID
     * @return bool
     */
    public function verifyTrainingRecord($gibbonMedicalStaffTrainingID, $verifiedByID)
    {
        $data = [
            'gibbonMedicalStaffTrainingID' => $gibbonMedicalStaffTrainingID,
            'verifiedByID' => $verifiedByID,
            'verifiedDate' => date('Y-m-d'),
        ];
        $sql = "UPDATE gibbonMedicalStaffTraining
                SET verified='Y', verifiedByID=:verifiedByID, verifiedDate=:verifiedDate
                WHERE gibbonMedicalStaffTrainingID=:gibbonMedicalStaffTrainingID";

        return $this->db()->update($sql, $data);
    }

    /**
     * Get training summary by type.
     *
     * @return array
     */
    public function getTrainingSummaryByType()
    {
        $data = [];
        $sql = "SELECT
                    trainingType,
                    COUNT(DISTINCT gibbonPersonID) as totalStaff,
                    SUM(CASE WHEN verified='Y' THEN 1 ELSE 0 END) as verifiedCount,
                    SUM(CASE WHEN expiryDate IS NOT NULL AND expiryDate < CURDATE() THEN 1 ELSE 0 END) as expiredCount,
                    SUM(CASE WHEN expiryDate IS NOT NULL AND expiryDate >= CURDATE() AND expiryDate <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as expiringCount
                FROM gibbonMedicalStaffTraining st
                INNER JOIN gibbonPerson p ON st.gibbonPersonID=p.gibbonPersonID
                WHERE p.status='Full'
                GROUP BY trainingType
                ORDER BY trainingType";

        return $this->db()->select($sql, $data)->fetchAll();
    }

    /**
     * Check if a staff member has valid training for a specific type.
     *
     * @param int $gibbonPersonID
     * @param string $trainingType
     * @return array|false
     */
    public function getValidTrainingByStaffAndType($gibbonPersonID, $trainingType)
    {
        $data = [
            'gibbonPersonID' => $gibbonPersonID,
            'trainingType' => $trainingType,
        ];
        $sql = "SELECT *
                FROM gibbonMedicalStaffTraining
                WHERE gibbonPersonID=:gibbonPersonID
                AND trainingType=:trainingType
                AND (expiryDate IS NULL OR expiryDate >= CURDATE())
                ORDER BY expiryDate DESC
                LIMIT 1";

        $result = $this->db()->select($sql, $data);
        return $result->isNotEmpty() ? $result->fetch() : false;
    }

    // ==================== ACCOMMODATION PLAN MANAGEMENT ====================

    /**
     * Add a new accommodation plan.
     *
     * @param int $gibbonPersonID
     * @param string $planName
     * @param string $planType
     * @param int $createdByID
     * @param array $additionalData
     * @return int|false
     */
    public function addAccommodationPlan($gibbonPersonID, $planName, $planType, $createdByID, $additionalData = [])
    {
        return $this->insert(array_merge([
            'gibbonPersonID' => $gibbonPersonID,
            'planName' => $planName,
            'planType' => $planType,
            'createdByID' => $createdByID,
            'active' => 'Y',
            'approved' => 'N',
        ], $additionalData));
    }

    /**
     * Approve an accommodation plan.
     *
     * @param int $gibbonMedicalAccommodationPlanID
     * @param int $approvedByID
     * @return bool
     */
    public function approveAccommodationPlan($gibbonMedicalAccommodationPlanID, $approvedByID)
    {
        return $this->update($gibbonMedicalAccommodationPlanID, [
            'approved' => 'Y',
            'approvedByID' => $approvedByID,
            'approvedDate' => date('Y-m-d'),
        ]);
    }

    /**
     * Deactivate an accommodation plan (soft delete).
     *
     * @param int $gibbonMedicalAccommodationPlanID
     * @return bool
     */
    public function deactivateAccommodationPlan($gibbonMedicalAccommodationPlanID)
    {
        return $this->update($gibbonMedicalAccommodationPlanID, [
            'active' => 'N',
        ]);
    }

    /**
     * Get accommodation plan summary.
     *
     * @return array
     */
    public function getAccommodationPlanSummary()
    {
        $data = [];
        $sql = "SELECT
                    planType,
                    COUNT(*) as totalPlans,
                    SUM(CASE WHEN approved='Y' THEN 1 ELSE 0 END) as approvedCount,
                    SUM(CASE WHEN approved='N' THEN 1 ELSE 0 END) as pendingCount,
                    COUNT(DISTINCT gibbonPersonID) as childrenCount
                FROM gibbonMedicalAccommodationPlan
                WHERE active='Y'
                GROUP BY planType
                ORDER BY planType";

        return $this->db()->select($sql, $data)->fetchAll();
    }

    /**
     * Get children requiring accommodation plans.
     *
     * @return \Gibbon\Database\Result
     */
    public function selectChildrenWithAccommodationPlans()
    {
        $data = [];
        $sql = "SELECT DISTINCT
                    p.gibbonPersonID,
                    p.preferredName,
                    p.surname,
                    p.image_240,
                    GROUP_CONCAT(DISTINCT ap.planType ORDER BY ap.planType SEPARATOR ', ') as planTypes,
                    COUNT(ap.gibbonMedicalAccommodationPlanID) as planCount
                FROM gibbonMedicalAccommodationPlan ap
                INNER JOIN gibbonPerson p ON ap.gibbonPersonID=p.gibbonPersonID
                WHERE ap.active='Y'
                AND p.status='Full'
                GROUP BY p.gibbonPersonID
                ORDER BY p.surname, p.preferredName";

        return $this->db()->select($sql, $data);
    }
}
