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
     * Log a meal for a child with a menu item reference.
     *
     * @param int $gibbonPersonID
     * @param int $gibbonSchoolYearID
     * @param string $date
     * @param string $mealType
     * @param int|null $gibbonCareMenuItemID
     * @param string $quantity
     * @param int $recordedByID
     * @param bool $allergyAlert
     * @param string|null $notes
     * @return int|false
     */
    public function logMealWithMenuItem($gibbonPersonID, $gibbonSchoolYearID, $date, $mealType, $gibbonCareMenuItemID, $quantity, $recordedByID, $allergyAlert = false, $notes = null)
    {
        // Check if this meal type already exists for this child on this date
        $existing = $this->getMealByPersonDateAndType($gibbonPersonID, $date, $mealType);

        if ($existing) {
            // Update existing record
            return $this->update($existing['gibbonCareMealID'], [
                'gibbonCareMenuItemID' => $gibbonCareMenuItemID,
                'quantity' => $quantity,
                'allergyAlert' => $allergyAlert ? 'Y' : 'N',
                'notes' => $notes,
                'recordedByID' => $recordedByID,
            ]) ? $existing['gibbonCareMealID'] : false;
        }

        // Create new meal record with menu item reference
        return $this->insert([
            'gibbonPersonID' => $gibbonPersonID,
            'gibbonSchoolYearID' => $gibbonSchoolYearID,
            'date' => $date,
            'mealType' => $mealType,
            'gibbonCareMenuItemID' => $gibbonCareMenuItemID,
            'quantity' => $quantity,
            'allergyAlert' => $allergyAlert ? 'Y' : 'N',
            'notes' => $notes,
            'recordedByID' => $recordedByID,
        ]);
    }

    /**
     * Get nutritional summary for a child over a date range.
     *
     * @param int $gibbonPersonID
     * @param string $dateStart
     * @param string $dateEnd
     * @return array
     */
    public function selectNutritionalSummaryByChild($gibbonPersonID, $dateStart, $dateEnd)
    {
        $data = [
            'gibbonPersonID' => $gibbonPersonID,
            'dateStart' => $dateStart,
            'dateEnd' => $dateEnd,
        ];
        $sql = "SELECT
                    SUM(CASE WHEN m.quantity='All' THEN 1.0
                             WHEN m.quantity='Most' THEN 0.75
                             WHEN m.quantity='Some' THEN 0.5
                             WHEN m.quantity='Little' THEN 0.25
                             ELSE 0 END * ni.calories) as totalCalories,
                    SUM(CASE WHEN m.quantity='All' THEN 1.0
                             WHEN m.quantity='Most' THEN 0.75
                             WHEN m.quantity='Some' THEN 0.5
                             WHEN m.quantity='Little' THEN 0.25
                             ELSE 0 END * ni.protein) as totalProtein,
                    SUM(CASE WHEN m.quantity='All' THEN 1.0
                             WHEN m.quantity='Most' THEN 0.75
                             WHEN m.quantity='Some' THEN 0.5
                             WHEN m.quantity='Little' THEN 0.25
                             ELSE 0 END * ni.carbohydrates) as totalCarbohydrates,
                    SUM(CASE WHEN m.quantity='All' THEN 1.0
                             WHEN m.quantity='Most' THEN 0.75
                             WHEN m.quantity='Some' THEN 0.5
                             WHEN m.quantity='Little' THEN 0.25
                             ELSE 0 END * ni.fat) as totalFat,
                    SUM(CASE WHEN m.quantity='All' THEN 1.0
                             WHEN m.quantity='Most' THEN 0.75
                             WHEN m.quantity='Some' THEN 0.5
                             WHEN m.quantity='Little' THEN 0.25
                             ELSE 0 END * ni.fiber) as totalFiber,
                    SUM(CASE WHEN m.quantity='All' THEN 1.0
                             WHEN m.quantity='Most' THEN 0.75
                             WHEN m.quantity='Some' THEN 0.5
                             WHEN m.quantity='Little' THEN 0.25
                             ELSE 0 END * ni.sugar) as totalSugar,
                    SUM(CASE WHEN m.quantity='All' THEN 1.0
                             WHEN m.quantity='Most' THEN 0.75
                             WHEN m.quantity='Some' THEN 0.5
                             WHEN m.quantity='Little' THEN 0.25
                             ELSE 0 END * ni.sodium) as totalSodium,
                    COUNT(*) as mealCount,
                    COUNT(DISTINCT m.date) as daysWithMeals
                FROM gibbonCareMeal m
                LEFT JOIN gibbonCareNutritionalInfo ni ON m.gibbonCareMenuItemID=ni.gibbonCareMenuItemID
                WHERE m.gibbonPersonID=:gibbonPersonID
                AND m.date >= :dateStart
                AND m.date <= :dateEnd
                AND m.gibbonCareMenuItemID IS NOT NULL";

        $result = $this->db()->selectOne($sql, $data);

        return $result ?: [
            'totalCalories' => 0,
            'totalProtein' => 0,
            'totalCarbohydrates' => 0,
            'totalFat' => 0,
            'totalFiber' => 0,
            'totalSugar' => 0,
            'totalSodium' => 0,
            'mealCount' => 0,
            'daysWithMeals' => 0,
        ];
    }

    /**
     * Get daily nutritional breakdown for a date range.
     *
     * @param int $gibbonPersonID
     * @param string $dateStart
     * @param string $dateEnd
     * @return array
     */
    public function selectNutritionalSummaryByDateRange($gibbonPersonID, $dateStart, $dateEnd)
    {
        $data = [
            'gibbonPersonID' => $gibbonPersonID,
            'dateStart' => $dateStart,
            'dateEnd' => $dateEnd,
        ];
        $sql = "SELECT
                    m.date,
                    SUM(CASE WHEN m.quantity='All' THEN 1.0
                             WHEN m.quantity='Most' THEN 0.75
                             WHEN m.quantity='Some' THEN 0.5
                             WHEN m.quantity='Little' THEN 0.25
                             ELSE 0 END * ni.calories) as dailyCalories,
                    SUM(CASE WHEN m.quantity='All' THEN 1.0
                             WHEN m.quantity='Most' THEN 0.75
                             WHEN m.quantity='Some' THEN 0.5
                             WHEN m.quantity='Little' THEN 0.25
                             ELSE 0 END * ni.protein) as dailyProtein,
                    SUM(CASE WHEN m.quantity='All' THEN 1.0
                             WHEN m.quantity='Most' THEN 0.75
                             WHEN m.quantity='Some' THEN 0.5
                             WHEN m.quantity='Little' THEN 0.25
                             ELSE 0 END * ni.carbohydrates) as dailyCarbohydrates,
                    SUM(CASE WHEN m.quantity='All' THEN 1.0
                             WHEN m.quantity='Most' THEN 0.75
                             WHEN m.quantity='Some' THEN 0.5
                             WHEN m.quantity='Little' THEN 0.25
                             ELSE 0 END * ni.fat) as dailyFat,
                    SUM(CASE WHEN m.quantity='All' THEN 1.0
                             WHEN m.quantity='Most' THEN 0.75
                             WHEN m.quantity='Some' THEN 0.5
                             WHEN m.quantity='Little' THEN 0.25
                             ELSE 0 END * ni.fiber) as dailyFiber,
                    SUM(CASE WHEN m.quantity='All' THEN 1.0
                             WHEN m.quantity='Most' THEN 0.75
                             WHEN m.quantity='Some' THEN 0.5
                             WHEN m.quantity='Little' THEN 0.25
                             ELSE 0 END * ni.sugar) as dailySugar,
                    SUM(CASE WHEN m.quantity='All' THEN 1.0
                             WHEN m.quantity='Most' THEN 0.75
                             WHEN m.quantity='Some' THEN 0.5
                             WHEN m.quantity='Little' THEN 0.25
                             ELSE 0 END * ni.sodium) as dailySodium,
                    COUNT(*) as mealCount
                FROM gibbonCareMeal m
                LEFT JOIN gibbonCareNutritionalInfo ni ON m.gibbonCareMenuItemID=ni.gibbonCareMenuItemID
                WHERE m.gibbonPersonID=:gibbonPersonID
                AND m.date >= :dateStart
                AND m.date <= :dateEnd
                AND m.gibbonCareMenuItemID IS NOT NULL
                GROUP BY m.date
                ORDER BY m.date ASC";

        return $this->db()->select($sql, $data)->fetchAll();
    }

    /**
     * Check if a menu item contains allergens that match a child's allergies.
     *
     * @param int $gibbonPersonID
     * @param int $gibbonCareMenuItemID
     * @return array Array of conflicting allergens with severity, or empty array if no conflicts
     */
    public function checkAllergenAlertForChild($gibbonPersonID, $gibbonCareMenuItemID)
    {
        // Get child's allergies from dietary profile
        $childData = ['gibbonPersonID' => $gibbonPersonID];
        $childSql = "SELECT allergies FROM gibbonCareChildDietary WHERE gibbonPersonID=:gibbonPersonID";

        $childProfile = $this->db()->selectOne($childSql, $childData);

        if (!$childProfile || empty($childProfile['allergies'])) {
            return [];
        }

        $childAllergies = json_decode($childProfile['allergies'], true);
        if (!is_array($childAllergies) || empty($childAllergies)) {
            return [];
        }

        // Get menu item's allergens
        $menuData = ['gibbonCareMenuItemID' => $gibbonCareMenuItemID];
        $menuSql = "SELECT allergen, severity FROM gibbonCareMenuItemAllergen WHERE gibbonCareMenuItemID=:gibbonCareMenuItemID";

        $menuAllergens = $this->db()->select($menuSql, $menuData)->fetchAll();

        if (empty($menuAllergens)) {
            return [];
        }

        // Extract child allergen names for comparison
        $childAllergenNames = [];
        $childAllergenSeverity = [];
        foreach ($childAllergies as $allergy) {
            $allergenName = is_array($allergy) && isset($allergy['allergen']) ? $allergy['allergen'] : (is_string($allergy) ? $allergy : '');
            if (!empty($allergenName)) {
                $childAllergenNames[] = $allergenName;
                $childAllergenSeverity[$allergenName] = is_array($allergy) && isset($allergy['severity']) ? $allergy['severity'] : 'Moderate';
            }
        }

        // Find conflicts
        $conflicts = [];
        foreach ($menuAllergens as $menuAllergen) {
            if (in_array($menuAllergen['allergen'], $childAllergenNames)) {
                $conflicts[] = [
                    'allergen' => $menuAllergen['allergen'],
                    'menuItemSeverity' => $menuAllergen['severity'],
                    'childSeverity' => $childAllergenSeverity[$menuAllergen['allergen']] ?? 'Moderate',
                ];
            }
        }

        return $conflicts;
    }

    /**
     * Get meals for a child with menu item and nutritional details.
     *
     * @param int $gibbonPersonID
     * @param string $dateStart
     * @param string $dateEnd
     * @return \Gibbon\Database\Result
     */
    public function selectMealsWithNutritionalInfo($gibbonPersonID, $dateStart, $dateEnd)
    {
        $data = [
            'gibbonPersonID' => $gibbonPersonID,
            'dateStart' => $dateStart,
            'dateEnd' => $dateEnd,
        ];
        $sql = "SELECT
                    m.gibbonCareMealID,
                    m.date,
                    m.mealType,
                    m.quantity,
                    m.allergyAlert,
                    m.notes,
                    m.gibbonCareMenuItemID,
                    mi.name as menuItemName,
                    mi.category as menuItemCategory,
                    ni.servingSize,
                    ni.calories,
                    ni.protein,
                    ni.carbohydrates,
                    ni.fat,
                    ni.fiber,
                    ni.sugar,
                    ni.sodium
                FROM gibbonCareMeal m
                LEFT JOIN gibbonCareMenuItem mi ON m.gibbonCareMenuItemID=mi.gibbonCareMenuItemID
                LEFT JOIN gibbonCareNutritionalInfo ni ON mi.gibbonCareMenuItemID=ni.gibbonCareMenuItemID
                WHERE m.gibbonPersonID=:gibbonPersonID
                AND m.date >= :dateStart
                AND m.date <= :dateEnd
                ORDER BY m.date ASC, FIELD(m.mealType, 'Breakfast', 'Morning Snack', 'Lunch', 'Afternoon Snack', 'Dinner')";

        return $this->db()->select($sql, $data);
    }

    /**
     * Query meals with menu item details for a school year.
     *
     * @param QueryCriteria $criteria
     * @param int $gibbonSchoolYearID
     * @return DataSet
     */
    public function queryMealsWithMenuItems(QueryCriteria $criteria, $gibbonSchoolYearID)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonCareMeal.gibbonCareMealID',
                'gibbonCareMeal.gibbonPersonID',
                'gibbonCareMeal.date',
                'gibbonCareMeal.mealType',
                'gibbonCareMeal.gibbonCareMenuItemID',
                'gibbonCareMeal.quantity',
                'gibbonCareMeal.allergyAlert',
                'gibbonCareMeal.notes',
                'gibbonCareMeal.timestampCreated',
                'gibbonPerson.preferredName',
                'gibbonPerson.surname',
                'gibbonPerson.image_240',
                'recordedBy.preferredName as recordedByName',
                'recordedBy.surname as recordedBySurname',
                'gibbonCareMenuItem.name as menuItemName',
                'gibbonCareMenuItem.category as menuItemCategory',
            ])
            ->innerJoin('gibbonPerson', 'gibbonCareMeal.gibbonPersonID=gibbonPerson.gibbonPersonID')
            ->leftJoin('gibbonPerson as recordedBy', 'gibbonCareMeal.recordedByID=recordedBy.gibbonPersonID')
            ->leftJoin('gibbonCareMenuItem', 'gibbonCareMeal.gibbonCareMenuItemID=gibbonCareMenuItem.gibbonCareMenuItemID')
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
            'menuItem' => function ($query, $gibbonCareMenuItemID) {
                return $query
                    ->where('gibbonCareMeal.gibbonCareMenuItemID=:gibbonCareMenuItemID')
                    ->bindValue('gibbonCareMenuItemID', $gibbonCareMenuItemID);
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
     * Get allergen exposure log for a child over a date range.
     *
     * @param int $gibbonPersonID
     * @param string $dateStart
     * @param string $dateEnd
     * @return array
     */
    public function selectAllergenExposureLog($gibbonPersonID, $dateStart, $dateEnd)
    {
        $data = [
            'gibbonPersonID' => $gibbonPersonID,
            'dateStart' => $dateStart,
            'dateEnd' => $dateEnd,
        ];
        $sql = "SELECT
                    m.gibbonCareMealID,
                    m.date,
                    m.mealType,
                    m.quantity,
                    mi.name as menuItemName,
                    mia.allergen,
                    mia.severity
                FROM gibbonCareMeal m
                INNER JOIN gibbonCareMenuItem mi ON m.gibbonCareMenuItemID=mi.gibbonCareMenuItemID
                INNER JOIN gibbonCareMenuItemAllergen mia ON mi.gibbonCareMenuItemID=mia.gibbonCareMenuItemID
                WHERE m.gibbonPersonID=:gibbonPersonID
                AND m.date >= :dateStart
                AND m.date <= :dateEnd
                AND m.allergyAlert='Y'
                ORDER BY m.date ASC, FIELD(m.mealType, 'Breakfast', 'Morning Snack', 'Lunch', 'Afternoon Snack', 'Dinner')";

        return $this->db()->select($sql, $data)->fetchAll();
    }

    /**
     * Get meal consumption summary by menu item for reporting.
     *
     * @param int $gibbonSchoolYearID
     * @param string $dateStart
     * @param string $dateEnd
     * @return array
     */
    public function selectMenuItemConsumptionSummary($gibbonSchoolYearID, $dateStart, $dateEnd)
    {
        $data = [
            'gibbonSchoolYearID' => $gibbonSchoolYearID,
            'dateStart' => $dateStart,
            'dateEnd' => $dateEnd,
        ];
        $sql = "SELECT
                    mi.gibbonCareMenuItemID,
                    mi.name as menuItemName,
                    mi.category,
                    COUNT(*) as totalServings,
                    SUM(CASE WHEN m.quantity='All' THEN 1 ELSE 0 END) as ateAllCount,
                    SUM(CASE WHEN m.quantity='Most' THEN 1 ELSE 0 END) as ateMostCount,
                    SUM(CASE WHEN m.quantity='Some' THEN 1 ELSE 0 END) as ateSomeCount,
                    SUM(CASE WHEN m.quantity='Little' THEN 1 ELSE 0 END) as ateLittleCount,
                    SUM(CASE WHEN m.quantity='None' THEN 1 ELSE 0 END) as ateNoneCount,
                    SUM(CASE WHEN m.allergyAlert='Y' THEN 1 ELSE 0 END) as allergyAlertCount,
                    AVG(CASE WHEN m.quantity='All' THEN 100
                             WHEN m.quantity='Most' THEN 75
                             WHEN m.quantity='Some' THEN 50
                             WHEN m.quantity='Little' THEN 25
                             ELSE 0 END) as avgConsumptionPercent
                FROM gibbonCareMeal m
                INNER JOIN gibbonCareMenuItem mi ON m.gibbonCareMenuItemID=mi.gibbonCareMenuItemID
                WHERE m.gibbonSchoolYearID=:gibbonSchoolYearID
                AND m.date >= :dateStart
                AND m.date <= :dateEnd
                AND m.gibbonCareMenuItemID IS NOT NULL
                GROUP BY mi.gibbonCareMenuItemID, mi.name, mi.category
                ORDER BY mi.category, mi.name";

        return $this->db()->select($sql, $data)->fetchAll();
    }
}
