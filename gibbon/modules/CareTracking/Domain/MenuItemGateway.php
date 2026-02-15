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
 * Care Tracking Menu Item Gateway
 *
 * Handles menu item catalog and allergen associations for childcare meal planning.
 *
 * @version v1.5.00
 * @since   v1.5.00
 */
class MenuItemGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'gibbonCareMenuItem';
    private static $primaryKey = 'gibbonCareMenuItemID';

    private static $searchableColumns = ['gibbonCareMenuItem.name', 'gibbonCareMenuItem.description'];

    /**
     * Query menu items with criteria support.
     *
     * @param QueryCriteria $criteria
     * @return DataSet
     */
    public function queryMenuItems(QueryCriteria $criteria)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonCareMenuItem.gibbonCareMenuItemID',
                'gibbonCareMenuItem.name',
                'gibbonCareMenuItem.description',
                'gibbonCareMenuItem.category',
                'gibbonCareMenuItem.photoPath',
                'gibbonCareMenuItem.isActive',
                'gibbonCareMenuItem.timestampCreated',
                'gibbonCareMenuItem.timestampModified',
                'createdBy.preferredName as createdByName',
                'createdBy.surname as createdBySurname',
                'nutrition.calories',
                'nutrition.protein',
                'nutrition.carbohydrates',
                'nutrition.fat',
                'nutrition.fiber',
            ])
            ->leftJoin('gibbonPerson as createdBy', 'gibbonCareMenuItem.createdByID=createdBy.gibbonPersonID')
            ->leftJoin('gibbonCareNutritionalInfo as nutrition', 'gibbonCareMenuItem.gibbonCareMenuItemID=nutrition.gibbonCareMenuItemID');

        $criteria->addFilterRules([
            'category' => function ($query, $category) {
                return $query
                    ->where('gibbonCareMenuItem.category=:category')
                    ->bindValue('category', $category);
            },
            'isActive' => function ($query, $isActive) {
                return $query
                    ->where('gibbonCareMenuItem.isActive=:isActive')
                    ->bindValue('isActive', $isActive);
            },
            'name' => function ($query, $name) {
                return $query
                    ->where('gibbonCareMenuItem.name LIKE :name')
                    ->bindValue('name', '%' . $name . '%');
            },
        ]);

        return $this->runQuery($query, $criteria);
    }

    /**
     * Get a single menu item by ID with nutritional info.
     *
     * @param int $gibbonCareMenuItemID
     * @return array|false
     */
    public function getMenuItemByID($gibbonCareMenuItemID)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols([
                'gibbonCareMenuItem.*',
                'nutrition.servingSize',
                'nutrition.calories',
                'nutrition.protein',
                'nutrition.carbohydrates',
                'nutrition.fat',
                'nutrition.fiber',
                'nutrition.sugar',
                'nutrition.sodium',
                'createdBy.preferredName as createdByName',
                'createdBy.surname as createdBySurname',
            ])
            ->leftJoin('gibbonPerson as createdBy', 'gibbonCareMenuItem.createdByID=createdBy.gibbonPersonID')
            ->leftJoin('gibbonCareNutritionalInfo as nutrition', 'gibbonCareMenuItem.gibbonCareMenuItemID=nutrition.gibbonCareMenuItemID')
            ->where('gibbonCareMenuItem.gibbonCareMenuItemID=:gibbonCareMenuItemID')
            ->bindValue('gibbonCareMenuItemID', $gibbonCareMenuItemID);

        $result = $this->runSelect($query);
        return $result->isNotEmpty() ? $result->fetch() : false;
    }

    /**
     * Select all menu items with their allergens.
     *
     * @param bool $activeOnly
     * @return \Gibbon\Database\Result
     */
    public function selectMenuItemsWithAllergens($activeOnly = true)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols([
                'gibbonCareMenuItem.gibbonCareMenuItemID',
                'gibbonCareMenuItem.name',
                'gibbonCareMenuItem.description',
                'gibbonCareMenuItem.category',
                'gibbonCareMenuItem.photoPath',
                'gibbonCareMenuItem.isActive',
                "GROUP_CONCAT(DISTINCT allergens.allergen SEPARATOR ', ') as allergenList",
            ])
            ->leftJoin('gibbonCareMenuItemAllergen as allergens', 'gibbonCareMenuItem.gibbonCareMenuItemID=allergens.gibbonCareMenuItemID')
            ->groupBy(['gibbonCareMenuItem.gibbonCareMenuItemID'])
            ->orderBy(['gibbonCareMenuItem.name ASC']);

        if ($activeOnly) {
            $query->where("gibbonCareMenuItem.isActive='Y'");
        }

        return $this->runSelect($query);
    }

    /**
     * Insert a new menu item with nutritional info.
     *
     * @param array $data Menu item data
     * @param array|null $nutritionData Optional nutritional info
     * @return int|false
     */
    public function insertMenuItem(array $data, array $nutritionData = null)
    {
        $menuItemID = $this->insert($data);

        if ($menuItemID && $nutritionData !== null) {
            $nutritionData['gibbonCareMenuItemID'] = $menuItemID;
            $this->db()->insert('gibbonCareNutritionalInfo', $nutritionData);
        }

        return $menuItemID;
    }

    /**
     * Update a menu item and its nutritional info.
     *
     * @param int $gibbonCareMenuItemID
     * @param array $data Menu item data
     * @param array|null $nutritionData Optional nutritional info
     * @return bool
     */
    public function updateMenuItem($gibbonCareMenuItemID, array $data, array $nutritionData = null)
    {
        $success = $this->update($gibbonCareMenuItemID, $data);

        if ($success && $nutritionData !== null) {
            // Check if nutrition record exists
            $existingNutrition = $this->db()->selectOne(
                "SELECT gibbonCareNutritionalInfoID FROM gibbonCareNutritionalInfo WHERE gibbonCareMenuItemID=:id",
                ['id' => $gibbonCareMenuItemID]
            );

            if ($existingNutrition) {
                $this->db()->update(
                    'gibbonCareNutritionalInfo',
                    $nutritionData,
                    ['gibbonCareMenuItemID' => $gibbonCareMenuItemID]
                );
            } else {
                $nutritionData['gibbonCareMenuItemID'] = $gibbonCareMenuItemID;
                $this->db()->insert('gibbonCareNutritionalInfo', $nutritionData);
            }
        }

        return $success;
    }

    /**
     * Select allergens for a specific menu item.
     *
     * @param int $gibbonCareMenuItemID
     * @return \Gibbon\Database\Result
     */
    public function selectAllergensByMenuItem($gibbonCareMenuItemID)
    {
        $data = ['gibbonCareMenuItemID' => $gibbonCareMenuItemID];
        $sql = "SELECT
                    gibbonCareMenuItemAllergenID,
                    allergen,
                    severity,
                    notes,
                    timestampCreated
                FROM gibbonCareMenuItemAllergen
                WHERE gibbonCareMenuItemID=:gibbonCareMenuItemID
                ORDER BY allergen ASC";

        return $this->db()->select($sql, $data);
    }

    /**
     * Insert an allergen association for a menu item.
     *
     * @param int $gibbonCareMenuItemID
     * @param string $allergen
     * @param string $severity
     * @param string|null $notes
     * @return int|false
     */
    public function insertMenuItemAllergen($gibbonCareMenuItemID, $allergen, $severity = 'Moderate', $notes = null)
    {
        $data = [
            'gibbonCareMenuItemID' => $gibbonCareMenuItemID,
            'allergen' => $allergen,
            'severity' => $severity,
            'notes' => $notes,
        ];

        return $this->db()->insert('gibbonCareMenuItemAllergen', $data);
    }

    /**
     * Delete all allergen associations for a menu item.
     *
     * @param int $gibbonCareMenuItemID
     * @return bool
     */
    public function deleteMenuItemAllergens($gibbonCareMenuItemID)
    {
        $data = ['gibbonCareMenuItemID' => $gibbonCareMenuItemID];
        $sql = "DELETE FROM gibbonCareMenuItemAllergen WHERE gibbonCareMenuItemID=:gibbonCareMenuItemID";

        return $this->db()->statement($sql, $data);
    }

    /**
     * Select menu items by category.
     *
     * @param string $category
     * @param bool $activeOnly
     * @return \Gibbon\Database\Result
     */
    public function selectMenuItemsByCategory($category, $activeOnly = true)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols([
                'gibbonCareMenuItem.gibbonCareMenuItemID',
                'gibbonCareMenuItem.name',
                'gibbonCareMenuItem.description',
                'gibbonCareMenuItem.category',
                'gibbonCareMenuItem.photoPath',
            ])
            ->where('gibbonCareMenuItem.category=:category')
            ->bindValue('category', $category)
            ->orderBy(['gibbonCareMenuItem.name ASC']);

        if ($activeOnly) {
            $query->where("gibbonCareMenuItem.isActive='Y'");
        }

        return $this->runSelect($query);
    }

    /**
     * Select active menu items as options for dropdowns.
     *
     * @return array
     */
    public function selectActiveMenuItemsAsOptions()
    {
        $data = [];
        $sql = "SELECT
                    gibbonCareMenuItemID as value,
                    CONCAT(name, ' (', category, ')') as name
                FROM gibbonCareMenuItem
                WHERE isActive='Y'
                ORDER BY category, name";

        $result = $this->db()->select($sql, $data);
        $options = [];
        while ($row = $result->fetch()) {
            $options[$row['value']] = $row['name'];
        }
        return $options;
    }

    /**
     * Select active menu items grouped by category.
     *
     * @return array
     */
    public function selectActiveMenuItemsByCategory()
    {
        $data = [];
        $sql = "SELECT
                    gibbonCareMenuItemID,
                    name,
                    category,
                    photoPath
                FROM gibbonCareMenuItem
                WHERE isActive='Y'
                ORDER BY category, name";

        $result = $this->db()->select($sql, $data);
        $grouped = [];
        while ($row = $result->fetch()) {
            $grouped[$row['category']][] = $row;
        }
        return $grouped;
    }

    /**
     * Check if a menu item has any allergens.
     *
     * @param int $gibbonCareMenuItemID
     * @return bool
     */
    public function hasAllergens($gibbonCareMenuItemID)
    {
        $data = ['gibbonCareMenuItemID' => $gibbonCareMenuItemID];
        $sql = "SELECT COUNT(*) as count FROM gibbonCareMenuItemAllergen WHERE gibbonCareMenuItemID=:gibbonCareMenuItemID";

        $result = $this->db()->selectOne($sql, $data);
        return ($result['count'] ?? 0) > 0;
    }

    /**
     * Get menu items that contain a specific allergen.
     *
     * @param string $allergen
     * @return \Gibbon\Database\Result
     */
    public function selectMenuItemsByAllergen($allergen)
    {
        $data = ['allergen' => $allergen];
        $sql = "SELECT
                    mi.gibbonCareMenuItemID,
                    mi.name,
                    mi.category,
                    mia.severity
                FROM gibbonCareMenuItem mi
                INNER JOIN gibbonCareMenuItemAllergen mia ON mi.gibbonCareMenuItemID=mia.gibbonCareMenuItemID
                WHERE mia.allergen=:allergen
                AND mi.isActive='Y'
                ORDER BY mi.name";

        return $this->db()->select($sql, $data);
    }

    /**
     * Soft delete a menu item by setting isActive to 'N'.
     *
     * @param int $gibbonCareMenuItemID
     * @return bool
     */
    public function deactivateMenuItem($gibbonCareMenuItemID)
    {
        return $this->update($gibbonCareMenuItemID, ['isActive' => 'N']);
    }

    /**
     * Get all categories that have active menu items.
     *
     * @return array
     */
    public function getActiveCategories()
    {
        $sql = "SELECT DISTINCT category
                FROM gibbonCareMenuItem
                WHERE isActive='Y'
                ORDER BY FIELD(category, 'Main Course', 'Side Dish', 'Snack', 'Beverage', 'Dessert', 'Fruit', 'Vegetable', 'Dairy', 'Protein', 'Grain')";

        $result = $this->db()->select($sql, []);
        $categories = [];
        while ($row = $result->fetch()) {
            $categories[] = $row['category'];
        }
        return $categories;
    }
}
