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
 * Care Tracking Weekly Menu Gateway
 *
 * Handles weekly menu scheduling and retrieval for childcare meal planning.
 *
 * @version v1.5.00
 * @since   v1.5.00
 */
class WeeklyMenuGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'gibbonCareWeeklyMenu';
    private static $primaryKey = 'gibbonCareWeeklyMenuID';

    private static $searchableColumns = ['gibbonCareMenuItem.name', 'gibbonCareWeeklyMenu.notes'];

    /**
     * Query weekly menu entries with criteria support.
     *
     * @param QueryCriteria $criteria
     * @param int $gibbonSchoolYearID
     * @return DataSet
     */
    public function queryWeeklyMenu(QueryCriteria $criteria, $gibbonSchoolYearID)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonCareWeeklyMenu.gibbonCareWeeklyMenuID',
                'gibbonCareWeeklyMenu.date',
                'gibbonCareWeeklyMenu.mealType',
                'gibbonCareWeeklyMenu.gibbonCareMenuItemID',
                'gibbonCareWeeklyMenu.servingSize',
                'gibbonCareWeeklyMenu.notes',
                'gibbonCareWeeklyMenu.timestampCreated',
                'gibbonCareWeeklyMenu.timestampModified',
                'menuItem.name as menuItemName',
                'menuItem.description as menuItemDescription',
                'menuItem.category as menuItemCategory',
                'menuItem.photoPath as menuItemPhotoPath',
                'createdBy.preferredName as createdByName',
                'createdBy.surname as createdBySurname',
            ])
            ->innerJoin('gibbonCareMenuItem as menuItem', 'gibbonCareWeeklyMenu.gibbonCareMenuItemID=menuItem.gibbonCareMenuItemID')
            ->leftJoin('gibbonPerson as createdBy', 'gibbonCareWeeklyMenu.createdByID=createdBy.gibbonPersonID')
            ->where('gibbonCareWeeklyMenu.gibbonSchoolYearID=:gibbonSchoolYearID')
            ->bindValue('gibbonSchoolYearID', $gibbonSchoolYearID);

        $criteria->addFilterRules([
            'date' => function ($query, $date) {
                return $query
                    ->where('gibbonCareWeeklyMenu.date=:date')
                    ->bindValue('date', $date);
            },
            'mealType' => function ($query, $mealType) {
                return $query
                    ->where('gibbonCareWeeklyMenu.mealType=:mealType')
                    ->bindValue('mealType', $mealType);
            },
            'dateFrom' => function ($query, $dateFrom) {
                return $query
                    ->where('gibbonCareWeeklyMenu.date>=:dateFrom')
                    ->bindValue('dateFrom', $dateFrom);
            },
            'dateTo' => function ($query, $dateTo) {
                return $query
                    ->where('gibbonCareWeeklyMenu.date<=:dateTo')
                    ->bindValue('dateTo', $dateTo);
            },
            'menuItemID' => function ($query, $menuItemID) {
                return $query
                    ->where('gibbonCareWeeklyMenu.gibbonCareMenuItemID=:menuItemID')
                    ->bindValue('menuItemID', $menuItemID);
            },
            'category' => function ($query, $category) {
                return $query
                    ->where('menuItem.category=:category')
                    ->bindValue('category', $category);
            },
        ]);

        return $this->runQuery($query, $criteria);
    }

    /**
     * Get all menu entries for a specific date.
     *
     * @param string $date
     * @param int $gibbonSchoolYearID
     * @return \Gibbon\Database\Result
     */
    public function getMenuForDate($date, $gibbonSchoolYearID)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols([
                'gibbonCareWeeklyMenu.gibbonCareWeeklyMenuID',
                'gibbonCareWeeklyMenu.date',
                'gibbonCareWeeklyMenu.mealType',
                'gibbonCareWeeklyMenu.gibbonCareMenuItemID',
                'gibbonCareWeeklyMenu.servingSize',
                'gibbonCareWeeklyMenu.notes',
                'menuItem.name as menuItemName',
                'menuItem.description as menuItemDescription',
                'menuItem.category as menuItemCategory',
                'menuItem.photoPath as menuItemPhotoPath',
                "GROUP_CONCAT(DISTINCT allergens.allergen SEPARATOR ', ') as allergenList",
            ])
            ->innerJoin('gibbonCareMenuItem as menuItem', 'gibbonCareWeeklyMenu.gibbonCareMenuItemID=menuItem.gibbonCareMenuItemID')
            ->leftJoin('gibbonCareMenuItemAllergen as allergens', 'menuItem.gibbonCareMenuItemID=allergens.gibbonCareMenuItemID')
            ->where('gibbonCareWeeklyMenu.date=:date')
            ->bindValue('date', $date)
            ->where('gibbonCareWeeklyMenu.gibbonSchoolYearID=:gibbonSchoolYearID')
            ->bindValue('gibbonSchoolYearID', $gibbonSchoolYearID)
            ->groupBy(['gibbonCareWeeklyMenu.gibbonCareWeeklyMenuID'])
            ->orderBy(["FIELD(gibbonCareWeeklyMenu.mealType, 'Breakfast', 'Morning Snack', 'Lunch', 'Afternoon Snack', 'Dinner')"]);

        return $this->runSelect($query);
    }

    /**
     * Get all menu entries for a week (Monday to Sunday).
     *
     * @param string $weekStartDate Monday date in Y-m-d format
     * @param int $gibbonSchoolYearID
     * @return \Gibbon\Database\Result
     */
    public function getMenuForWeek($weekStartDate, $gibbonSchoolYearID)
    {
        $weekEndDate = date('Y-m-d', strtotime($weekStartDate . ' +6 days'));

        $data = [
            'weekStartDate' => $weekStartDate,
            'weekEndDate' => $weekEndDate,
            'gibbonSchoolYearID' => $gibbonSchoolYearID,
        ];
        $sql = "SELECT
                    wm.gibbonCareWeeklyMenuID,
                    wm.date,
                    wm.mealType,
                    wm.gibbonCareMenuItemID,
                    wm.servingSize,
                    wm.notes,
                    mi.name as menuItemName,
                    mi.description as menuItemDescription,
                    mi.category as menuItemCategory,
                    mi.photoPath as menuItemPhotoPath,
                    GROUP_CONCAT(DISTINCT mia.allergen SEPARATOR ', ') as allergenList,
                    ni.calories,
                    ni.protein,
                    ni.carbohydrates,
                    ni.fat
                FROM gibbonCareWeeklyMenu wm
                INNER JOIN gibbonCareMenuItem mi ON wm.gibbonCareMenuItemID=mi.gibbonCareMenuItemID
                LEFT JOIN gibbonCareMenuItemAllergen mia ON mi.gibbonCareMenuItemID=mia.gibbonCareMenuItemID
                LEFT JOIN gibbonCareNutritionalInfo ni ON mi.gibbonCareMenuItemID=ni.gibbonCareMenuItemID
                WHERE wm.date >= :weekStartDate
                AND wm.date <= :weekEndDate
                AND wm.gibbonSchoolYearID=:gibbonSchoolYearID
                GROUP BY wm.gibbonCareWeeklyMenuID
                ORDER BY wm.date, FIELD(wm.mealType, 'Breakfast', 'Morning Snack', 'Lunch', 'Afternoon Snack', 'Dinner')";

        return $this->db()->select($sql, $data);
    }

    /**
     * Get menu structured by day and meal type for a week.
     *
     * @param string $weekStartDate Monday date in Y-m-d format
     * @param int $gibbonSchoolYearID
     * @return array Structured array [date][mealType][] = menuItem
     */
    public function getMenuForWeekStructured($weekStartDate, $gibbonSchoolYearID)
    {
        $result = $this->getMenuForWeek($weekStartDate, $gibbonSchoolYearID);
        $structured = [];

        while ($row = $result->fetch()) {
            $date = $row['date'];
            $mealType = $row['mealType'];
            if (!isset($structured[$date])) {
                $structured[$date] = [];
            }
            if (!isset($structured[$date][$mealType])) {
                $structured[$date][$mealType] = [];
            }
            $structured[$date][$mealType][] = $row;
        }

        return $structured;
    }

    /**
     * Set a menu item for a specific date and meal type.
     * If the same menu item already exists for that date/meal, updates it.
     * If not, inserts a new entry.
     *
     * @param int $gibbonSchoolYearID
     * @param string $date
     * @param string $mealType
     * @param int $gibbonCareMenuItemID
     * @param int $createdByID
     * @param string|null $servingSize
     * @param string|null $notes
     * @return int|false
     */
    public function setMenuForDateAndType($gibbonSchoolYearID, $date, $mealType, $gibbonCareMenuItemID, $createdByID, $servingSize = null, $notes = null)
    {
        // Check if this menu item already exists for this date and meal type
        $existing = $this->getMenuEntryByDateTypAndItem($date, $mealType, $gibbonCareMenuItemID);

        if ($existing) {
            // Update existing record
            $success = $this->update($existing['gibbonCareWeeklyMenuID'], [
                'servingSize' => $servingSize,
                'notes' => $notes,
                'createdByID' => $createdByID,
            ]);
            return $success ? $existing['gibbonCareWeeklyMenuID'] : false;
        }

        // Insert new menu entry
        return $this->insert([
            'gibbonSchoolYearID' => $gibbonSchoolYearID,
            'date' => $date,
            'mealType' => $mealType,
            'gibbonCareMenuItemID' => $gibbonCareMenuItemID,
            'servingSize' => $servingSize,
            'notes' => $notes,
            'createdByID' => $createdByID,
        ]);
    }

    /**
     * Delete a menu entry by ID.
     *
     * @param int $gibbonCareWeeklyMenuID
     * @return bool
     */
    public function deleteMenuEntry($gibbonCareWeeklyMenuID)
    {
        return $this->delete($gibbonCareWeeklyMenuID);
    }

    /**
     * Delete all menu entries for a specific date and meal type.
     *
     * @param string $date
     * @param string $mealType
     * @param int $gibbonSchoolYearID
     * @return bool
     */
    public function deleteMenuEntriesForDateAndType($date, $mealType, $gibbonSchoolYearID)
    {
        $data = [
            'date' => $date,
            'mealType' => $mealType,
            'gibbonSchoolYearID' => $gibbonSchoolYearID,
        ];
        $sql = "DELETE FROM gibbonCareWeeklyMenu
                WHERE date=:date
                AND mealType=:mealType
                AND gibbonSchoolYearID=:gibbonSchoolYearID";

        return $this->db()->statement($sql, $data);
    }

    /**
     * Select menu items scheduled for a date range.
     *
     * @param string $dateStart
     * @param string $dateEnd
     * @param int $gibbonSchoolYearID
     * @return \Gibbon\Database\Result
     */
    public function selectMenuItemsForDateRange($dateStart, $dateEnd, $gibbonSchoolYearID)
    {
        $data = [
            'dateStart' => $dateStart,
            'dateEnd' => $dateEnd,
            'gibbonSchoolYearID' => $gibbonSchoolYearID,
        ];
        $sql = "SELECT
                    wm.gibbonCareWeeklyMenuID,
                    wm.date,
                    wm.mealType,
                    mi.gibbonCareMenuItemID,
                    mi.name,
                    mi.category
                FROM gibbonCareWeeklyMenu wm
                INNER JOIN gibbonCareMenuItem mi ON wm.gibbonCareMenuItemID=mi.gibbonCareMenuItemID
                WHERE wm.date >= :dateStart
                AND wm.date <= :dateEnd
                AND wm.gibbonSchoolYearID=:gibbonSchoolYearID
                ORDER BY wm.date, FIELD(wm.mealType, 'Breakfast', 'Morning Snack', 'Lunch', 'Afternoon Snack', 'Dinner'), mi.name";

        return $this->db()->select($sql, $data);
    }

    /**
     * Get a specific menu entry by date, meal type, and menu item.
     *
     * @param string $date
     * @param string $mealType
     * @param int $gibbonCareMenuItemID
     * @return array|false
     */
    public function getMenuEntryByDateTypAndItem($date, $mealType, $gibbonCareMenuItemID)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols(['*'])
            ->where('date=:date')
            ->bindValue('date', $date)
            ->where('mealType=:mealType')
            ->bindValue('mealType', $mealType)
            ->where('gibbonCareMenuItemID=:gibbonCareMenuItemID')
            ->bindValue('gibbonCareMenuItemID', $gibbonCareMenuItemID);

        $result = $this->runSelect($query);
        return $result->isNotEmpty() ? $result->fetch() : false;
    }

    /**
     * Get menu entries for a specific date and meal type.
     *
     * @param string $date
     * @param string $mealType
     * @param int $gibbonSchoolYearID
     * @return \Gibbon\Database\Result
     */
    public function selectMenuByDateAndType($date, $mealType, $gibbonSchoolYearID)
    {
        $data = [
            'date' => $date,
            'mealType' => $mealType,
            'gibbonSchoolYearID' => $gibbonSchoolYearID,
        ];
        $sql = "SELECT
                    wm.gibbonCareWeeklyMenuID,
                    wm.gibbonCareMenuItemID,
                    wm.servingSize,
                    wm.notes,
                    mi.name as menuItemName,
                    mi.description as menuItemDescription,
                    mi.category as menuItemCategory,
                    mi.photoPath as menuItemPhotoPath,
                    GROUP_CONCAT(DISTINCT mia.allergen SEPARATOR ', ') as allergenList
                FROM gibbonCareWeeklyMenu wm
                INNER JOIN gibbonCareMenuItem mi ON wm.gibbonCareMenuItemID=mi.gibbonCareMenuItemID
                LEFT JOIN gibbonCareMenuItemAllergen mia ON mi.gibbonCareMenuItemID=mia.gibbonCareMenuItemID
                WHERE wm.date=:date
                AND wm.mealType=:mealType
                AND wm.gibbonSchoolYearID=:gibbonSchoolYearID
                GROUP BY wm.gibbonCareWeeklyMenuID
                ORDER BY mi.name";

        return $this->db()->select($sql, $data);
    }

    /**
     * Copy menu from one week to another.
     *
     * @param string $sourceWeekStart Source week Monday date
     * @param string $targetWeekStart Target week Monday date
     * @param int $gibbonSchoolYearID
     * @param int $createdByID
     * @return int Number of entries copied
     */
    public function copyWeekMenu($sourceWeekStart, $targetWeekStart, $gibbonSchoolYearID, $createdByID)
    {
        $sourceMenu = $this->getMenuForWeek($sourceWeekStart, $gibbonSchoolYearID);
        $copied = 0;

        while ($row = $sourceMenu->fetch()) {
            // Calculate day offset from source week
            $sourceDate = new \DateTime($row['date']);
            $sourceWeekStartDate = new \DateTime($sourceWeekStart);
            $dayOffset = $sourceDate->diff($sourceWeekStartDate)->days;

            // Calculate target date
            $targetDate = date('Y-m-d', strtotime($targetWeekStart . ' +' . $dayOffset . ' days'));

            // Insert new entry
            $result = $this->setMenuForDateAndType(
                $gibbonSchoolYearID,
                $targetDate,
                $row['mealType'],
                $row['gibbonCareMenuItemID'],
                $createdByID,
                $row['servingSize'],
                $row['notes']
            );

            if ($result) {
                $copied++;
            }
        }

        return $copied;
    }

    /**
     * Get allergen warnings for a specific date based on children's dietary profiles.
     *
     * @param string $date
     * @param int $gibbonSchoolYearID
     * @return \Gibbon\Database\Result
     */
    public function selectAllergenWarningsForDate($date, $gibbonSchoolYearID)
    {
        $data = [
            'date' => $date,
            'gibbonSchoolYearID' => $gibbonSchoolYearID,
        ];
        $sql = "SELECT
                    wm.gibbonCareWeeklyMenuID,
                    wm.mealType,
                    mi.name as menuItemName,
                    mia.allergen,
                    mia.severity,
                    p.gibbonPersonID,
                    p.preferredName,
                    p.surname
                FROM gibbonCareWeeklyMenu wm
                INNER JOIN gibbonCareMenuItem mi ON wm.gibbonCareMenuItemID=mi.gibbonCareMenuItemID
                INNER JOIN gibbonCareMenuItemAllergen mia ON mi.gibbonCareMenuItemID=mia.gibbonCareMenuItemID
                INNER JOIN gibbonCareChildDietary cd ON cd.allergies LIKE CONCAT('%\"', mia.allergen, '\"%')
                INNER JOIN gibbonPerson p ON cd.gibbonPersonID=p.gibbonPersonID
                WHERE wm.date=:date
                AND wm.gibbonSchoolYearID=:gibbonSchoolYearID
                AND p.status='Full'
                ORDER BY wm.mealType, mi.name, p.surname, p.preferredName";

        return $this->db()->select($sql, $data);
    }

    /**
     * Get summary of scheduled meals for a date range.
     *
     * @param string $dateStart
     * @param string $dateEnd
     * @param int $gibbonSchoolYearID
     * @return array
     */
    public function getMenuSummaryByDateRange($dateStart, $dateEnd, $gibbonSchoolYearID)
    {
        $data = [
            'dateStart' => $dateStart,
            'dateEnd' => $dateEnd,
            'gibbonSchoolYearID' => $gibbonSchoolYearID,
        ];
        $sql = "SELECT
                    COUNT(DISTINCT wm.date) as daysScheduled,
                    COUNT(*) as totalMenuItems,
                    SUM(CASE WHEN wm.mealType='Breakfast' THEN 1 ELSE 0 END) as breakfastCount,
                    SUM(CASE WHEN wm.mealType='Morning Snack' THEN 1 ELSE 0 END) as morningSnackCount,
                    SUM(CASE WHEN wm.mealType='Lunch' THEN 1 ELSE 0 END) as lunchCount,
                    SUM(CASE WHEN wm.mealType='Afternoon Snack' THEN 1 ELSE 0 END) as afternoonSnackCount,
                    SUM(CASE WHEN wm.mealType='Dinner' THEN 1 ELSE 0 END) as dinnerCount
                FROM gibbonCareWeeklyMenu wm
                WHERE wm.date >= :dateStart
                AND wm.date <= :dateEnd
                AND wm.gibbonSchoolYearID=:gibbonSchoolYearID";

        return $this->db()->selectOne($sql, $data) ?: [
            'daysScheduled' => 0,
            'totalMenuItems' => 0,
            'breakfastCount' => 0,
            'morningSnackCount' => 0,
            'lunchCount' => 0,
            'afternoonSnackCount' => 0,
            'dinnerCount' => 0,
        ];
    }

    /**
     * Check if a date has menu items scheduled for all meal types.
     *
     * @param string $date
     * @param int $gibbonSchoolYearID
     * @return array
     */
    public function getScheduledMealTypes($date, $gibbonSchoolYearID)
    {
        $data = [
            'date' => $date,
            'gibbonSchoolYearID' => $gibbonSchoolYearID,
        ];
        $sql = "SELECT DISTINCT mealType
                FROM gibbonCareWeeklyMenu
                WHERE date=:date
                AND gibbonSchoolYearID=:gibbonSchoolYearID";

        $result = $this->db()->select($sql, $data);
        $mealTypes = [];
        while ($row = $result->fetch()) {
            $mealTypes[] = $row['mealType'];
        }
        return $mealTypes;
    }
}
