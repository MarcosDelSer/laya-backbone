<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright © 2010, Gibbon Foundation
Gibbon™, Gibbon Education Ltd. (Gibbon Core) and Gibbon LAYA are trademarks of Gibbon Education Ltd.

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

namespace Gibbon\Module\CareTracking\Service;

use Gibbon\Module\CareTracking\Domain\MealGateway;
use Gibbon\Domain\QueryCriteria;

/**
 * MealService
 *
 * Service layer for meal tracking business logic.
 * Provides a clean API for meal operations by wrapping MealGateway.
 *
 * @version v1.0.00
 * @since   v1.0.00
 */
class MealService
{
    /**
     * @var MealGateway
     */
    protected $mealGateway;

    /**
     * Constructor.
     *
     * @param MealGateway $mealGateway Meal gateway
     */
    public function __construct(MealGateway $mealGateway)
    {
        $this->mealGateway = $mealGateway;
    }

    /**
     * Query meals with criteria support.
     *
     * @param QueryCriteria $criteria Query criteria
     * @param int $gibbonSchoolYearID School year ID
     * @return \Gibbon\Domain\DataSet
     */
    public function queryMeals(QueryCriteria $criteria, $gibbonSchoolYearID)
    {
        return $this->mealGateway->queryMeals($criteria, $gibbonSchoolYearID);
    }

    /**
     * Query meals for a specific date.
     *
     * @param QueryCriteria $criteria Query criteria
     * @param int $gibbonSchoolYearID School year ID
     * @param string $date Date in Y-m-d format
     * @return \Gibbon\Domain\DataSet
     */
    public function queryMealsByDate(QueryCriteria $criteria, $gibbonSchoolYearID, $date)
    {
        return $this->mealGateway->queryMealsByDate($criteria, $gibbonSchoolYearID, $date);
    }

    /**
     * Query meal history for a specific child.
     *
     * @param QueryCriteria $criteria Query criteria
     * @param int $gibbonPersonID Child person ID
     * @param int $gibbonSchoolYearID School year ID
     * @return \Gibbon\Domain\DataSet
     */
    public function queryMealsByPerson(QueryCriteria $criteria, $gibbonPersonID, $gibbonSchoolYearID)
    {
        return $this->mealGateway->queryMealsByPerson($criteria, $gibbonPersonID, $gibbonSchoolYearID);
    }

    /**
     * Get meals for a specific child on a specific date.
     *
     * @param int $gibbonPersonID Child person ID
     * @param string $date Date in Y-m-d format
     * @return \Gibbon\Database\Result
     */
    public function getMealsByPersonAndDate($gibbonPersonID, $date)
    {
        return $this->mealGateway->selectMealsByPersonAndDate($gibbonPersonID, $date);
    }

    /**
     * Get meal summary statistics for a specific date.
     *
     * @param int $gibbonSchoolYearID School year ID
     * @param string $date Date in Y-m-d format
     * @return array Summary statistics
     */
    public function getMealSummaryByDate($gibbonSchoolYearID, $date)
    {
        return $this->mealGateway->getMealSummaryByDate($gibbonSchoolYearID, $date);
    }

    /**
     * Get meal statistics for a child over a date range.
     *
     * @param int $gibbonPersonID Child person ID
     * @param string $dateStart Start date in Y-m-d format
     * @param string $dateEnd End date in Y-m-d format
     * @return array Statistics
     */
    public function getMealStatsByPersonAndDateRange($gibbonPersonID, $dateStart, $dateEnd)
    {
        return $this->mealGateway->getMealStatsByPersonAndDateRange($gibbonPersonID, $dateStart, $dateEnd);
    }

    /**
     * Log a meal for a child.
     *
     * @param int $gibbonPersonID Child person ID
     * @param int $gibbonSchoolYearID School year ID
     * @param string $date Date in Y-m-d format
     * @param string $mealType Meal type (Breakfast, Morning Snack, Lunch, Afternoon Snack, Dinner)
     * @param string $quantity Quantity eaten (All, Most, Some, Little, None)
     * @param int $recordedByID ID of person recording
     * @param bool $allergyAlert Whether allergy alert was raised
     * @param string|null $notes Optional notes
     * @return int|false Meal ID or false on failure
     */
    public function logMeal($gibbonPersonID, $gibbonSchoolYearID, $date, $mealType, $quantity, $recordedByID, $allergyAlert = false, $notes = null)
    {
        return $this->mealGateway->logMeal($gibbonPersonID, $gibbonSchoolYearID, $date, $mealType, $quantity, $recordedByID, $allergyAlert, $notes);
    }

    /**
     * Check if a meal type has already been logged for a child on a specific date.
     *
     * @param int $gibbonPersonID Child person ID
     * @param string $date Date in Y-m-d format
     * @param string $mealType Meal type
     * @return array|false Meal record or false if not found
     */
    public function getMealByPersonDateAndType($gibbonPersonID, $date, $mealType)
    {
        return $this->mealGateway->getMealByPersonDateAndType($gibbonPersonID, $date, $mealType);
    }

    /**
     * Get children who have not had a specific meal logged for a date.
     *
     * @param int $gibbonSchoolYearID School year ID
     * @param string $date Date in Y-m-d format
     * @param string $mealType Meal type
     * @return \Gibbon\Database\Result
     */
    public function getChildrenWithoutMeal($gibbonSchoolYearID, $date, $mealType)
    {
        return $this->mealGateway->selectChildrenWithoutMeal($gibbonSchoolYearID, $date, $mealType);
    }

    /**
     * Validate meal data before logging.
     *
     * @param array $data Meal data
     * @return array Array with 'valid' boolean and 'errors' array
     */
    public function validateMealData(array $data)
    {
        $errors = [];

        if (empty($data['gibbonPersonID'])) {
            $errors[] = 'Child ID is required';
        }

        if (empty($data['date'])) {
            $errors[] = 'Date is required';
        } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['date'])) {
            $errors[] = 'Date must be in Y-m-d format';
        }

        $validMealTypes = ['Breakfast', 'Morning Snack', 'Lunch', 'Afternoon Snack', 'Dinner'];
        if (empty($data['mealType']) || !in_array($data['mealType'], $validMealTypes)) {
            $errors[] = 'Valid meal type is required';
        }

        $validQuantities = ['All', 'Most', 'Some', 'Little', 'None'];
        if (empty($data['quantity']) || !in_array($data['quantity'], $validQuantities)) {
            $errors[] = 'Valid quantity is required';
        }

        if (empty($data['recordedByID'])) {
            $errors[] = 'Recorded by ID is required';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }
}
