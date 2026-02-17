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

namespace Gibbon\Module\CareTracking\Domain;

use Gibbon\Domain\Traits\TableAware;
use Gibbon\Domain\QueryCriteria;
use Gibbon\Domain\QueryableGateway;
use Gibbon\Module\MedicalTracking\Domain\AllergyGateway;

/**
 * Care Tracking Meal Gateway
 *
 * Handles meal and snack tracking for children in childcare settings.
 *
 * @version v1.0.00
 * @since   v1.0.00
 */
class MealGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'gibbonCareMeal';
    private static $primaryKey = 'gibbonCareMealID';

    private static $searchableColumns = ['gibbonPerson.preferredName', 'gibbonPerson.surname', 'gibbonCareMeal.notes'];

    /**
     * @var AllergyGateway|null
     */
    protected $allergyGateway;

    /**
     * Set the AllergyGateway for allergy checking integration.
     *
     * @param AllergyGateway $allergyGateway
     * @return self
     */
    public function setAllergyGateway(AllergyGateway $allergyGateway)
    {
        $this->allergyGateway = $allergyGateway;
        return $this;
    }

    /**
     * Get the AllergyGateway instance.
     *
     * @return AllergyGateway|null
     */
    public function getAllergyGateway()
    {
        return $this->allergyGateway;
    }

    /**
     * Query meal records with criteria support.
     *
     * @param QueryCriteria $criteria
     * @param int $gibbonSchoolYearID
     * @return DataSet
     */
    public function queryMeals(QueryCriteria $criteria, $gibbonSchoolYearID)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonCareMeal.gibbonCareMealID',
                'gibbonCareMeal.gibbonPersonID',
                'gibbonCareMeal.date',
                'gibbonCareMeal.mealType',
                'gibbonCareMeal.quantity',
                'gibbonCareMeal.allergyAlert',
                'gibbonCareMeal.notes',
                'gibbonCareMeal.timestampCreated',
                'gibbonPerson.preferredName',
                'gibbonPerson.surname',
                'gibbonPerson.image_240',
                'recordedBy.preferredName as recordedByName',
                'recordedBy.surname as recordedBySurname',
            ])
            ->innerJoin('gibbonPerson', 'gibbonCareMeal.gibbonPersonID=gibbonPerson.gibbonPersonID')
            ->leftJoin('gibbonPerson as recordedBy', 'gibbonCareMeal.recordedByID=recordedBy.gibbonPersonID')
            ->where('gibbonCareMeal.gibbonSchoolYearID=:gibbonSchoolYearID')
            ->bindValue('gibbonSchoolYearID', $gibbonSchoolYearID);

        $criteria->addFilterRules([
            'date' => function ($query, $date) {
                return $query
                    ->where('gibbonCareMeal.date=:date')
                    ->bindValue('date', $date);
            },
            'child' => function ($query, $gibbonPersonID) {
                return $query
                    ->where('gibbonCareMeal.gibbonPersonID=:gibbonPersonID')
                    ->bindValue('gibbonPersonID', $gibbonPersonID);
            },
            'mealType' => function ($query, $mealType) {
                return $query
                    ->where('gibbonCareMeal.mealType=:mealType')
                    ->bindValue('mealType', $mealType);
            },
            'quantity' => function ($query, $quantity) {
                return $query
                    ->where('gibbonCareMeal.quantity=:quantity')
                    ->bindValue('quantity', $quantity);
            },
            'allergyAlert' => function ($query, $value) {
                return $query
                    ->where('gibbonCareMeal.allergyAlert=:allergyAlert')
                    ->bindValue('allergyAlert', $value);
            },
        ]);

        return $this->runQuery($query, $criteria);
    }

    /**
     * Query meal records for a specific date.
     *
     * @param QueryCriteria $criteria
     * @param int $gibbonSchoolYearID
     * @param string $date
     * @return DataSet
     */
    public function queryMealsByDate(QueryCriteria $criteria, $gibbonSchoolYearID, $date)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonCareMeal.gibbonCareMealID',
                'gibbonCareMeal.gibbonPersonID',
                'gibbonCareMeal.date',
                'gibbonCareMeal.mealType',
                'gibbonCareMeal.quantity',
                'gibbonCareMeal.allergyAlert',
                'gibbonCareMeal.notes',
                'gibbonCareMeal.timestampCreated',
                'gibbonPerson.preferredName',
                'gibbonPerson.surname',
                'gibbonPerson.image_240',
                'gibbonPerson.dob',
            ])
            ->innerJoin('gibbonPerson', 'gibbonCareMeal.gibbonPersonID=gibbonPerson.gibbonPersonID')
            ->where('gibbonCareMeal.gibbonSchoolYearID=:gibbonSchoolYearID')
            ->bindValue('gibbonSchoolYearID', $gibbonSchoolYearID)
            ->where('gibbonCareMeal.date=:date')
            ->bindValue('date', $date);

        return $this->runQuery($query, $criteria);
    }

    /**
     * Query meal history for a specific child.
     *
     * @param QueryCriteria $criteria
     * @param int $gibbonPersonID
     * @param int $gibbonSchoolYearID
     * @return DataSet
     */
    public function queryMealsByPerson(QueryCriteria $criteria, $gibbonPersonID, $gibbonSchoolYearID)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonCareMeal.gibbonCareMealID',
                'gibbonCareMeal.date',
                'gibbonCareMeal.mealType',
                'gibbonCareMeal.quantity',
                'gibbonCareMeal.allergyAlert',
                'gibbonCareMeal.notes',
                'gibbonCareMeal.timestampCreated',
                'recordedBy.preferredName as recordedByName',
                'recordedBy.surname as recordedBySurname',
            ])
            ->leftJoin('gibbonPerson as recordedBy', 'gibbonCareMeal.recordedByID=recordedBy.gibbonPersonID')
            ->where('gibbonCareMeal.gibbonPersonID=:gibbonPersonID')
            ->bindValue('gibbonPersonID', $gibbonPersonID)
            ->where('gibbonCareMeal.gibbonSchoolYearID=:gibbonSchoolYearID')
            ->bindValue('gibbonSchoolYearID', $gibbonSchoolYearID);

        return $this->runQuery($query, $criteria);
    }

    /**
     * Get meals for a specific child on a specific date.
     *
     * @param int $gibbonPersonID
     * @param string $date
     * @return \Gibbon\Database\Result
     */
    public function selectMealsByPersonAndDate($gibbonPersonID, $date)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols([
                'gibbonCareMeal.*',
                'recordedBy.preferredName as recordedByName',
                'recordedBy.surname as recordedBySurname',
            ])
            ->leftJoin('gibbonPerson as recordedBy', 'gibbonCareMeal.recordedByID=recordedBy.gibbonPersonID')
            ->where('gibbonCareMeal.gibbonPersonID=:gibbonPersonID')
            ->bindValue('gibbonPersonID', $gibbonPersonID)
            ->where('gibbonCareMeal.date=:date')
            ->bindValue('date', $date)
            ->orderBy(['gibbonCareMeal.timestampCreated ASC']);

        return $this->runSelect($query);
    }

    /**
     * Check if a meal type has already been logged for a child on a specific date.
     *
     * @param int $gibbonPersonID
     * @param string $date
     * @param string $mealType
     * @return array|false
     */
    public function getMealByPersonDateAndType($gibbonPersonID, $date, $mealType)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols(['*'])
            ->where('gibbonPersonID=:gibbonPersonID')
            ->bindValue('gibbonPersonID', $gibbonPersonID)
            ->where('date=:date')
            ->bindValue('date', $date)
            ->where('mealType=:mealType')
            ->bindValue('mealType', $mealType);

        $result = $this->runSelect($query);
        return $result->isNotEmpty() ? $result->fetch() : false;
    }

    /**
     * Get meal summary for a specific date.
     *
     * @param int $gibbonSchoolYearID
     * @param string $date
     * @return array
     */
    public function getMealSummaryByDate($gibbonSchoolYearID, $date)
    {
        $data = ['gibbonSchoolYearID' => $gibbonSchoolYearID, 'date' => $date];
        $sql = "SELECT
                    mealType,
                    COUNT(*) as totalRecords,
                    SUM(CASE WHEN quantity='All' THEN 1 ELSE 0 END) as ateAll,
                    SUM(CASE WHEN quantity='Most' THEN 1 ELSE 0 END) as ateMost,
                    SUM(CASE WHEN quantity='Some' THEN 1 ELSE 0 END) as ateSome,
                    SUM(CASE WHEN quantity='Little' THEN 1 ELSE 0 END) as ateLittle,
                    SUM(CASE WHEN quantity='None' THEN 1 ELSE 0 END) as ateNone,
                    SUM(CASE WHEN allergyAlert='Y' THEN 1 ELSE 0 END) as allergyAlerts
                FROM gibbonCareMeal
                WHERE gibbonSchoolYearID=:gibbonSchoolYearID
                AND date=:date
                GROUP BY mealType
                ORDER BY FIELD(mealType, 'Breakfast', 'Morning Snack', 'Lunch', 'Afternoon Snack', 'Dinner')";

        return $this->db()->select($sql, $data)->fetchAll();
    }

    /**
     * Get meal statistics for a child over a date range.
     *
     * @param int $gibbonPersonID
     * @param string $dateStart
     * @param string $dateEnd
     * @return array
     */
    public function getMealStatsByPersonAndDateRange($gibbonPersonID, $dateStart, $dateEnd)
    {
        $data = ['gibbonPersonID' => $gibbonPersonID, 'dateStart' => $dateStart, 'dateEnd' => $dateEnd];
        $sql = "SELECT
                    COUNT(*) as totalMeals,
                    SUM(CASE WHEN quantity='All' THEN 1 ELSE 0 END) as ateAllCount,
                    SUM(CASE WHEN quantity='None' THEN 1 ELSE 0 END) as ateNoneCount,
                    SUM(CASE WHEN allergyAlert='Y' THEN 1 ELSE 0 END) as allergyAlertCount
                FROM gibbonCareMeal
                WHERE gibbonPersonID=:gibbonPersonID
                AND date >= :dateStart
                AND date <= :dateEnd";

        return $this->db()->selectOne($sql, $data) ?: [
            'totalMeals' => 0,
            'ateAllCount' => 0,
            'ateNoneCount' => 0,
            'allergyAlertCount' => 0,
        ];
    }

    /**
     * Get food allergies for a child (for meal logging context).
     *
     * @param int $gibbonPersonID
     * @return array Array of allergy records or empty array
     */
    public function getChildFoodAllergies($gibbonPersonID)
    {
        if ($this->allergyGateway === null) {
            return [];
        }

        $result = $this->allergyGateway->selectFoodAllergiesByPerson($gibbonPersonID);
        return $result->isNotEmpty() ? $result->fetchAll() : [];
    }

    /**
     * Check if a child has any food allergies.
     *
     * @param int $gibbonPersonID
     * @return bool
     */
    public function hasChildFoodAllergies($gibbonPersonID)
    {
        $allergies = $this->getChildFoodAllergies($gibbonPersonID);
        return !empty($allergies);
    }

    /**
     * Check if a child has severe or life-threatening allergies.
     *
     * @param int $gibbonPersonID
     * @return array Array of severe allergies or empty array
     */
    public function getChildSevereAllergies($gibbonPersonID)
    {
        $allergies = $this->getChildFoodAllergies($gibbonPersonID);
        return array_filter($allergies, function ($allergy) {
            return in_array($allergy['severity'], ['Severe', 'Life-Threatening']);
        });
    }

    /**
     * Check if a child requires an EpiPen for any food allergy.
     *
     * @param int $gibbonPersonID
     * @return bool
     */
    public function childRequiresEpiPen($gibbonPersonID)
    {
        $allergies = $this->getChildFoodAllergies($gibbonPersonID);
        foreach ($allergies as $allergy) {
            if ($allergy['epiPenRequired'] === 'Y') {
                return true;
            }
        }
        return false;
    }

    /**
     * Get allergy alert summary for a child (for display during meal logging).
     *
     * @param int $gibbonPersonID
     * @return array|null Allergy summary or null if no allergies
     */
    public function getAllergyAlertSummary($gibbonPersonID)
    {
        $allergies = $this->getChildFoodAllergies($gibbonPersonID);
        if (empty($allergies)) {
            return null;
        }

        $allergenNames = [];
        $severeCount = 0;
        $epiPenRequired = false;

        foreach ($allergies as $allergy) {
            $allergenNames[] = $allergy['allergenName'];
            if (in_array($allergy['severity'], ['Severe', 'Life-Threatening'])) {
                $severeCount++;
            }
            if ($allergy['epiPenRequired'] === 'Y') {
                $epiPenRequired = true;
            }
        }

        return [
            'totalAllergies' => count($allergies),
            'allergenNames' => $allergenNames,
            'allergenList' => implode(', ', $allergenNames),
            'hasSevereAllergies' => $severeCount > 0,
            'severeCount' => $severeCount,
            'epiPenRequired' => $epiPenRequired,
        ];
    }

    /**
     * Log a meal for a child.
     *
     * @param int $gibbonPersonID
     * @param int $gibbonSchoolYearID
     * @param string $date
     * @param string $mealType
     * @param string $quantity
     * @param int $recordedByID
     * @param bool $allergyAlert
     * @param string|null $notes
     * @return int|false
     */
    public function logMeal($gibbonPersonID, $gibbonSchoolYearID, $date, $mealType, $quantity, $recordedByID, $allergyAlert = false, $notes = null)
    {
        // Check if this meal type already exists for this child on this date
        $existing = $this->getMealByPersonDateAndType($gibbonPersonID, $date, $mealType);

        // Check child allergies if AllergyGateway is available
        $hasAllergies = $this->hasChildFoodAllergies($gibbonPersonID);

        // If allergyAlert is not explicitly set but child has allergies, auto-flag for review
        if (!$allergyAlert && $hasAllergies) {
            // Get allergy summary for potential notes enhancement
            $allergySummary = $this->getAllergyAlertSummary($gibbonPersonID);
            if ($allergySummary && $allergySummary['hasSevereAllergies']) {
                // Auto-set allergy alert for children with severe allergies
                $allergyAlert = true;
            }
        }

        if ($existing) {
            // Update existing record
            return $this->update($existing['gibbonCareMealID'], [
                'quantity' => $quantity,
                'allergyAlert' => $allergyAlert ? 'Y' : 'N',
                'notes' => $notes,
                'recordedByID' => $recordedByID,
            ]) ? $existing['gibbonCareMealID'] : false;
        }

        // Create new meal record
        return $this->insert([
            'gibbonPersonID' => $gibbonPersonID,
            'gibbonSchoolYearID' => $gibbonSchoolYearID,
            'date' => $date,
            'mealType' => $mealType,
            'quantity' => $quantity,
            'allergyAlert' => $allergyAlert ? 'Y' : 'N',
            'notes' => $notes,
            'recordedByID' => $recordedByID,
        ]);
    }

    /**
     * Select children who have not had a specific meal logged for a date.
     *
     * @param int $gibbonSchoolYearID
     * @param string $date
     * @param string $mealType
     * @return \Gibbon\Database\Result
     */
    public function selectChildrenWithoutMeal($gibbonSchoolYearID, $date, $mealType)
    {
        $data = ['gibbonSchoolYearID' => $gibbonSchoolYearID, 'date' => $date, 'mealType' => $mealType];
        $sql = "SELECT gibbonPerson.gibbonPersonID, gibbonPerson.preferredName, gibbonPerson.surname, gibbonPerson.image_240
                FROM gibbonStudentEnrolment
                INNER JOIN gibbonPerson ON gibbonStudentEnrolment.gibbonPersonID=gibbonPerson.gibbonPersonID
                INNER JOIN gibbonCareAttendance ON gibbonPerson.gibbonPersonID=gibbonCareAttendance.gibbonPersonID
                    AND gibbonCareAttendance.date=:date
                    AND gibbonCareAttendance.checkInTime IS NOT NULL
                WHERE gibbonStudentEnrolment.gibbonSchoolYearID=:gibbonSchoolYearID
                AND gibbonPerson.status='Full'
                AND NOT EXISTS (
                    SELECT 1 FROM gibbonCareMeal
                    WHERE gibbonCareMeal.gibbonPersonID=gibbonPerson.gibbonPersonID
                    AND gibbonCareMeal.date=:date
                    AND gibbonCareMeal.mealType=:mealType
                )
                ORDER BY gibbonPerson.surname, gibbonPerson.preferredName";

        return $this->db()->select($sql, $data);
    }

    /**
     * Select children who have not had a meal logged, with allergy information.
     *
     * @param int $gibbonSchoolYearID
     * @param string $date
     * @param string $mealType
     * @return \Gibbon\Database\Result
     */
    public function selectChildrenWithoutMealWithAllergyInfo($gibbonSchoolYearID, $date, $mealType)
    {
        $data = ['gibbonSchoolYearID' => $gibbonSchoolYearID, 'date' => $date, 'mealType' => $mealType];
        $sql = "SELECT gibbonPerson.gibbonPersonID, gibbonPerson.preferredName, gibbonPerson.surname, gibbonPerson.image_240,
                    (SELECT COUNT(*) FROM gibbonMedicalAllergy
                     WHERE gibbonMedicalAllergy.gibbonPersonID=gibbonPerson.gibbonPersonID
                     AND gibbonMedicalAllergy.allergenType='Food'
                     AND gibbonMedicalAllergy.active='Y') as allergyCount,
                    (SELECT GROUP_CONCAT(allergenName SEPARATOR ', ')
                     FROM gibbonMedicalAllergy
                     WHERE gibbonMedicalAllergy.gibbonPersonID=gibbonPerson.gibbonPersonID
                     AND gibbonMedicalAllergy.allergenType='Food'
                     AND gibbonMedicalAllergy.active='Y') as allergenList,
                    (SELECT MAX(CASE WHEN severity IN ('Severe', 'Life-Threatening') THEN 1 ELSE 0 END)
                     FROM gibbonMedicalAllergy
                     WHERE gibbonMedicalAllergy.gibbonPersonID=gibbonPerson.gibbonPersonID
                     AND gibbonMedicalAllergy.allergenType='Food'
                     AND gibbonMedicalAllergy.active='Y') as hasSevereAllergy,
                    (SELECT MAX(CASE WHEN epiPenRequired='Y' THEN 1 ELSE 0 END)
                     FROM gibbonMedicalAllergy
                     WHERE gibbonMedicalAllergy.gibbonPersonID=gibbonPerson.gibbonPersonID
                     AND gibbonMedicalAllergy.allergenType='Food'
                     AND gibbonMedicalAllergy.active='Y') as requiresEpiPen
                FROM gibbonStudentEnrolment
                INNER JOIN gibbonPerson ON gibbonStudentEnrolment.gibbonPersonID=gibbonPerson.gibbonPersonID
                INNER JOIN gibbonCareAttendance ON gibbonPerson.gibbonPersonID=gibbonCareAttendance.gibbonPersonID
                    AND gibbonCareAttendance.date=:date
                    AND gibbonCareAttendance.checkInTime IS NOT NULL
                WHERE gibbonStudentEnrolment.gibbonSchoolYearID=:gibbonSchoolYearID
                AND gibbonPerson.status='Full'
                AND NOT EXISTS (
                    SELECT 1 FROM gibbonCareMeal
                    WHERE gibbonCareMeal.gibbonPersonID=gibbonPerson.gibbonPersonID
                    AND gibbonCareMeal.date=:date
                    AND gibbonCareMeal.mealType=:mealType
                )
                ORDER BY gibbonPerson.surname, gibbonPerson.preferredName";

        return $this->db()->select($sql, $data);
    }

    /**
     * Get all children with food allergies who are logged in for a date.
     *
     * @param int $gibbonSchoolYearID
     * @param string $date
     * @return \Gibbon\Database\Result
     */
    public function selectChildrenWithAllergiesForDate($gibbonSchoolYearID, $date)
    {
        $data = ['gibbonSchoolYearID' => $gibbonSchoolYearID, 'date' => $date];
        $sql = "SELECT DISTINCT gibbonPerson.gibbonPersonID, gibbonPerson.preferredName, gibbonPerson.surname, gibbonPerson.image_240,
                    GROUP_CONCAT(gibbonMedicalAllergy.allergenName ORDER BY gibbonMedicalAllergy.severity DESC SEPARATOR ', ') as allergenList,
                    MAX(CASE WHEN gibbonMedicalAllergy.severity IN ('Severe', 'Life-Threatening') THEN 1 ELSE 0 END) as hasSevereAllergy,
                    MAX(CASE WHEN gibbonMedicalAllergy.epiPenRequired='Y' THEN 1 ELSE 0 END) as requiresEpiPen
                FROM gibbonStudentEnrolment
                INNER JOIN gibbonPerson ON gibbonStudentEnrolment.gibbonPersonID=gibbonPerson.gibbonPersonID
                INNER JOIN gibbonCareAttendance ON gibbonPerson.gibbonPersonID=gibbonCareAttendance.gibbonPersonID
                    AND gibbonCareAttendance.date=:date
                    AND gibbonCareAttendance.checkInTime IS NOT NULL
                INNER JOIN gibbonMedicalAllergy ON gibbonPerson.gibbonPersonID=gibbonMedicalAllergy.gibbonPersonID
                    AND gibbonMedicalAllergy.allergenType='Food'
                    AND gibbonMedicalAllergy.active='Y'
                WHERE gibbonStudentEnrolment.gibbonSchoolYearID=:gibbonSchoolYearID
                AND gibbonPerson.status='Full'
                GROUP BY gibbonPerson.gibbonPersonID
                ORDER BY hasSevereAllergy DESC, requiresEpiPen DESC, gibbonPerson.surname, gibbonPerson.preferredName";

        return $this->db()->select($sql, $data);
    }

    /**
     * Query meals for a date with allergy information included.
     *
     * @param QueryCriteria $criteria
     * @param int $gibbonSchoolYearID
     * @param string $date
     * @return DataSet
     */
    public function queryMealsByDateWithAllergies(QueryCriteria $criteria, $gibbonSchoolYearID, $date)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonCareMeal.gibbonCareMealID',
                'gibbonCareMeal.gibbonPersonID',
                'gibbonCareMeal.date',
                'gibbonCareMeal.mealType',
                'gibbonCareMeal.quantity',
                'gibbonCareMeal.allergyAlert',
                'gibbonCareMeal.notes',
                'gibbonCareMeal.timestampCreated',
                'gibbonPerson.preferredName',
                'gibbonPerson.surname',
                'gibbonPerson.image_240',
                'gibbonPerson.dob',
                "(SELECT COUNT(*) FROM gibbonMedicalAllergy
                  WHERE gibbonMedicalAllergy.gibbonPersonID=gibbonCareMeal.gibbonPersonID
                  AND gibbonMedicalAllergy.allergenType='Food'
                  AND gibbonMedicalAllergy.active='Y') as allergyCount",
                "(SELECT GROUP_CONCAT(allergenName SEPARATOR ', ')
                  FROM gibbonMedicalAllergy
                  WHERE gibbonMedicalAllergy.gibbonPersonID=gibbonCareMeal.gibbonPersonID
                  AND gibbonMedicalAllergy.allergenType='Food'
                  AND gibbonMedicalAllergy.active='Y') as allergenList",
            ])
            ->innerJoin('gibbonPerson', 'gibbonCareMeal.gibbonPersonID=gibbonPerson.gibbonPersonID')
            ->where('gibbonCareMeal.gibbonSchoolYearID=:gibbonSchoolYearID')
            ->bindValue('gibbonSchoolYearID', $gibbonSchoolYearID)
            ->where('gibbonCareMeal.date=:date')
            ->bindValue('date', $date);

        return $this->runQuery($query, $criteria);
    }
}
