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
 * Care Tracking Child Dietary Gateway
 *
 * Handles child dietary profile management and allergen cross-referencing for childcare meal planning.
 *
 * @version v1.5.00
 * @since   v1.5.00
 */
class ChildDietaryGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'gibbonCareChildDietary';
    private static $primaryKey = 'gibbonCareChildDietaryID';

    private static $searchableColumns = ['gibbonPerson.preferredName', 'gibbonPerson.surname', 'gibbonCareChildDietary.allergies', 'gibbonCareChildDietary.restrictions'];

    /**
     * Query child dietary profiles with criteria support.
     *
     * @param QueryCriteria $criteria
     * @param int $gibbonSchoolYearID
     * @return DataSet
     */
    public function queryChildDietary(QueryCriteria $criteria, $gibbonSchoolYearID)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonCareChildDietary.gibbonCareChildDietaryID',
                'gibbonCareChildDietary.gibbonPersonID',
                'gibbonCareChildDietary.dietaryType',
                'gibbonCareChildDietary.allergies',
                'gibbonCareChildDietary.restrictions',
                'gibbonCareChildDietary.notes',
                'gibbonCareChildDietary.parentNotified',
                'gibbonCareChildDietary.parentNotifiedTime',
                'gibbonCareChildDietary.timestampCreated',
                'gibbonCareChildDietary.timestampModified',
                'gibbonPerson.preferredName',
                'gibbonPerson.surname',
                'gibbonPerson.image_240',
                'gibbonPerson.dob',
                'lastUpdatedBy.preferredName as lastUpdatedByName',
                'lastUpdatedBy.surname as lastUpdatedBySurname',
            ])
            ->innerJoin('gibbonPerson', 'gibbonCareChildDietary.gibbonPersonID=gibbonPerson.gibbonPersonID')
            ->innerJoin('gibbonStudentEnrolment', 'gibbonPerson.gibbonPersonID=gibbonStudentEnrolment.gibbonPersonID')
            ->leftJoin('gibbonPerson as lastUpdatedBy', 'gibbonCareChildDietary.lastUpdatedByID=lastUpdatedBy.gibbonPersonID')
            ->where('gibbonStudentEnrolment.gibbonSchoolYearID=:gibbonSchoolYearID')
            ->bindValue('gibbonSchoolYearID', $gibbonSchoolYearID)
            ->where("gibbonPerson.status='Full'");

        $criteria->addFilterRules([
            'dietaryType' => function ($query, $dietaryType) {
                return $query
                    ->where('gibbonCareChildDietary.dietaryType=:dietaryType')
                    ->bindValue('dietaryType', $dietaryType);
            },
            'hasAllergies' => function ($query, $value) {
                if ($value === 'Y') {
                    return $query->where("gibbonCareChildDietary.allergies IS NOT NULL AND gibbonCareChildDietary.allergies != ''");
                }
                return $query;
            },
            'allergen' => function ($query, $allergen) {
                return $query
                    ->where("gibbonCareChildDietary.allergies LIKE :allergen")
                    ->bindValue('allergen', '%"' . $allergen . '"%');
            },
            'parentNotified' => function ($query, $parentNotified) {
                return $query
                    ->where('gibbonCareChildDietary.parentNotified=:parentNotified')
                    ->bindValue('parentNotified', $parentNotified);
            },
        ]);

        return $this->runQuery($query, $criteria);
    }

    /**
     * Query all children with their dietary profiles (including those without profiles).
     *
     * @param QueryCriteria $criteria
     * @param int $gibbonSchoolYearID
     * @return DataSet
     */
    public function queryChildrenWithDietary(QueryCriteria $criteria, $gibbonSchoolYearID)
    {
        $query = $this
            ->newQuery()
            ->from('gibbonStudentEnrolment')
            ->cols([
                'gibbonPerson.gibbonPersonID',
                'gibbonPerson.preferredName',
                'gibbonPerson.surname',
                'gibbonPerson.image_240',
                'gibbonPerson.dob',
                'gibbonCareChildDietary.gibbonCareChildDietaryID',
                'gibbonCareChildDietary.dietaryType',
                'gibbonCareChildDietary.allergies',
                'gibbonCareChildDietary.restrictions',
                'gibbonCareChildDietary.notes',
                'gibbonCareChildDietary.parentNotified',
                'gibbonCareChildDietary.timestampModified',
            ])
            ->innerJoin('gibbonPerson', 'gibbonStudentEnrolment.gibbonPersonID=gibbonPerson.gibbonPersonID')
            ->leftJoin('gibbonCareChildDietary', 'gibbonPerson.gibbonPersonID=gibbonCareChildDietary.gibbonPersonID')
            ->where('gibbonStudentEnrolment.gibbonSchoolYearID=:gibbonSchoolYearID')
            ->bindValue('gibbonSchoolYearID', $gibbonSchoolYearID)
            ->where("gibbonPerson.status='Full'");

        $criteria->addFilterRules([
            'dietaryType' => function ($query, $dietaryType) {
                if ($dietaryType === 'None') {
                    return $query->where("(gibbonCareChildDietary.dietaryType='None' OR gibbonCareChildDietary.dietaryType IS NULL)");
                }
                return $query
                    ->where('gibbonCareChildDietary.dietaryType=:dietaryType')
                    ->bindValue('dietaryType', $dietaryType);
            },
            'hasAllergies' => function ($query, $value) {
                if ($value === 'Y') {
                    return $query->where("gibbonCareChildDietary.allergies IS NOT NULL AND gibbonCareChildDietary.allergies != ''");
                }
                return $query;
            },
        ]);

        return $this->runQuery($query, $criteria);
    }

    /**
     * Get dietary profile for a specific child.
     *
     * @param int $gibbonPersonID
     * @return array|false
     */
    public function getDietaryByChild($gibbonPersonID)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols([
                'gibbonCareChildDietary.*',
                'gibbonPerson.preferredName',
                'gibbonPerson.surname',
                'gibbonPerson.image_240',
                'gibbonPerson.dob',
                'lastUpdatedBy.preferredName as lastUpdatedByName',
                'lastUpdatedBy.surname as lastUpdatedBySurname',
            ])
            ->innerJoin('gibbonPerson', 'gibbonCareChildDietary.gibbonPersonID=gibbonPerson.gibbonPersonID')
            ->leftJoin('gibbonPerson as lastUpdatedBy', 'gibbonCareChildDietary.lastUpdatedByID=lastUpdatedBy.gibbonPersonID')
            ->where('gibbonCareChildDietary.gibbonPersonID=:gibbonPersonID')
            ->bindValue('gibbonPersonID', $gibbonPersonID);

        $result = $this->runSelect($query);
        return $result->isNotEmpty() ? $result->fetch() : false;
    }

    /**
     * Insert a new dietary profile for a child.
     *
     * @param int $gibbonPersonID
     * @param string $dietaryType
     * @param array|null $allergies Array of allergens with severity
     * @param string|null $restrictions
     * @param string|null $notes
     * @param int $lastUpdatedByID
     * @return int|false
     */
    public function insertDietaryProfile($gibbonPersonID, $dietaryType, $allergies = null, $restrictions = null, $notes = null, $lastUpdatedByID = null)
    {
        // Check if profile already exists
        $existing = $this->getDietaryByChild($gibbonPersonID);
        if ($existing) {
            // Update existing profile instead
            return $this->updateDietaryProfile(
                $existing['gibbonCareChildDietaryID'],
                $dietaryType,
                $allergies,
                $restrictions,
                $notes,
                $lastUpdatedByID
            ) ? $existing['gibbonCareChildDietaryID'] : false;
        }

        $data = [
            'gibbonPersonID' => $gibbonPersonID,
            'dietaryType' => $dietaryType,
            'allergies' => $allergies !== null ? json_encode($allergies) : null,
            'restrictions' => $restrictions,
            'notes' => $notes,
            'lastUpdatedByID' => $lastUpdatedByID,
        ];

        return $this->insert($data);
    }

    /**
     * Update an existing dietary profile.
     *
     * @param int $gibbonCareChildDietaryID
     * @param string $dietaryType
     * @param array|null $allergies Array of allergens with severity
     * @param string|null $restrictions
     * @param string|null $notes
     * @param int $lastUpdatedByID
     * @return bool
     */
    public function updateDietaryProfile($gibbonCareChildDietaryID, $dietaryType, $allergies = null, $restrictions = null, $notes = null, $lastUpdatedByID = null)
    {
        $data = [
            'dietaryType' => $dietaryType,
            'allergies' => $allergies !== null ? json_encode($allergies) : null,
            'restrictions' => $restrictions,
            'notes' => $notes,
            'lastUpdatedByID' => $lastUpdatedByID,
        ];

        return $this->update($gibbonCareChildDietaryID, $data);
    }

    /**
     * Update a dietary profile by child ID.
     *
     * @param int $gibbonPersonID
     * @param array $data
     * @return bool
     */
    public function updateDietaryByChild($gibbonPersonID, array $data)
    {
        $existing = $this->getDietaryByChild($gibbonPersonID);
        if (!$existing) {
            return false;
        }

        // Convert allergies to JSON if it's an array
        if (isset($data['allergies']) && is_array($data['allergies'])) {
            $data['allergies'] = json_encode($data['allergies']);
        }

        return $this->update($existing['gibbonCareChildDietaryID'], $data);
    }

    /**
     * Get all allergies for a specific child.
     *
     * @param int $gibbonPersonID
     * @return array Array of allergen objects with name and severity
     */
    public function selectChildAllergies($gibbonPersonID)
    {
        $profile = $this->getDietaryByChild($gibbonPersonID);

        if (!$profile || empty($profile['allergies'])) {
            return [];
        }

        $allergies = json_decode($profile['allergies'], true);
        return is_array($allergies) ? $allergies : [];
    }

    /**
     * Get all children with a specific allergen.
     *
     * @param string $allergen
     * @param int|null $gibbonSchoolYearID
     * @return \Gibbon\Database\Result
     */
    public function selectChildrenByAllergen($allergen, $gibbonSchoolYearID = null)
    {
        $data = ['allergen' => '%"' . $allergen . '"%'];
        $sql = "SELECT
                    cd.gibbonCareChildDietaryID,
                    cd.gibbonPersonID,
                    cd.allergies,
                    p.preferredName,
                    p.surname,
                    p.image_240
                FROM gibbonCareChildDietary cd
                INNER JOIN gibbonPerson p ON cd.gibbonPersonID=p.gibbonPersonID
                WHERE cd.allergies LIKE :allergen
                AND p.status='Full'";

        if ($gibbonSchoolYearID !== null) {
            $data['gibbonSchoolYearID'] = $gibbonSchoolYearID;
            $sql .= " AND EXISTS (
                SELECT 1 FROM gibbonStudentEnrolment se
                WHERE se.gibbonPersonID=p.gibbonPersonID
                AND se.gibbonSchoolYearID=:gibbonSchoolYearID
            )";
        }

        $sql .= " ORDER BY p.surname, p.preferredName";

        return $this->db()->select($sql, $data);
    }

    /**
     * Check if a menu item's allergens conflict with a child's allergies.
     *
     * @param int $gibbonPersonID
     * @param int $gibbonCareMenuItemID
     * @return array Array of conflicting allergens with severity
     */
    public function checkAllergenConflict($gibbonPersonID, $gibbonCareMenuItemID)
    {
        // Get child's allergies
        $childAllergies = $this->selectChildAllergies($gibbonPersonID);
        if (empty($childAllergies)) {
            return [];
        }

        // Get menu item's allergens
        $data = ['gibbonCareMenuItemID' => $gibbonCareMenuItemID];
        $sql = "SELECT allergen, severity
                FROM gibbonCareMenuItemAllergen
                WHERE gibbonCareMenuItemID=:gibbonCareMenuItemID";

        $menuAllergens = $this->db()->select($sql, $data)->fetchAll();

        // Find conflicts
        $conflicts = [];
        $childAllergenNames = array_map(function ($a) {
            return is_array($a) && isset($a['allergen']) ? $a['allergen'] : (is_string($a) ? $a : '');
        }, $childAllergies);

        foreach ($menuAllergens as $menuAllergen) {
            if (in_array($menuAllergen['allergen'], $childAllergenNames)) {
                // Find the child's severity for this allergen
                $childSeverity = 'Moderate';
                foreach ($childAllergies as $childAllergen) {
                    $allergenName = is_array($childAllergen) && isset($childAllergen['allergen']) ? $childAllergen['allergen'] : (is_string($childAllergen) ? $childAllergen : '');
                    if ($allergenName === $menuAllergen['allergen']) {
                        $childSeverity = is_array($childAllergen) && isset($childAllergen['severity']) ? $childAllergen['severity'] : 'Moderate';
                        break;
                    }
                }

                $conflicts[] = [
                    'allergen' => $menuAllergen['allergen'],
                    'menuItemSeverity' => $menuAllergen['severity'],
                    'childSeverity' => $childSeverity,
                ];
            }
        }

        return $conflicts;
    }

    /**
     * Get all children with allergies to a specific menu item.
     *
     * @param int $gibbonCareMenuItemID
     * @param int|null $gibbonSchoolYearID
     * @return array Array of children with their conflicting allergens
     */
    public function getChildrenWithAllergyToItem($gibbonCareMenuItemID, $gibbonSchoolYearID = null)
    {
        // Get menu item's allergens
        $data = ['gibbonCareMenuItemID' => $gibbonCareMenuItemID];
        $sql = "SELECT allergen, severity
                FROM gibbonCareMenuItemAllergen
                WHERE gibbonCareMenuItemID=:gibbonCareMenuItemID";

        $menuAllergens = $this->db()->select($sql, $data)->fetchAll();

        if (empty($menuAllergens)) {
            return [];
        }

        // Build a list of allergen names
        $allergenNames = array_column($menuAllergens, 'allergen');
        $menuAllergenLookup = [];
        foreach ($menuAllergens as $ma) {
            $menuAllergenLookup[$ma['allergen']] = $ma['severity'];
        }

        // Find all children with matching allergies
        $childrenData = ['status' => 'Full'];
        $childSql = "SELECT
                    cd.gibbonCareChildDietaryID,
                    cd.gibbonPersonID,
                    cd.allergies,
                    p.preferredName,
                    p.surname,
                    p.image_240
                FROM gibbonCareChildDietary cd
                INNER JOIN gibbonPerson p ON cd.gibbonPersonID=p.gibbonPersonID
                WHERE p.status=:status
                AND cd.allergies IS NOT NULL
                AND cd.allergies != ''";

        if ($gibbonSchoolYearID !== null) {
            $childrenData['gibbonSchoolYearID'] = $gibbonSchoolYearID;
            $childSql .= " AND EXISTS (
                SELECT 1 FROM gibbonStudentEnrolment se
                WHERE se.gibbonPersonID=p.gibbonPersonID
                AND se.gibbonSchoolYearID=:gibbonSchoolYearID
            )";
        }

        $childSql .= " ORDER BY p.surname, p.preferredName";

        $children = $this->db()->select($childSql, $childrenData)->fetchAll();

        $result = [];
        foreach ($children as $child) {
            $childAllergies = json_decode($child['allergies'], true);
            if (!is_array($childAllergies)) {
                continue;
            }

            $conflicts = [];
            foreach ($childAllergies as $childAllergen) {
                $allergenName = is_array($childAllergen) && isset($childAllergen['allergen']) ? $childAllergen['allergen'] : (is_string($childAllergen) ? $childAllergen : '');
                $childSeverity = is_array($childAllergen) && isset($childAllergen['severity']) ? $childAllergen['severity'] : 'Moderate';

                if (in_array($allergenName, $allergenNames)) {
                    $conflicts[] = [
                        'allergen' => $allergenName,
                        'menuItemSeverity' => $menuAllergenLookup[$allergenName] ?? 'Moderate',
                        'childSeverity' => $childSeverity,
                    ];
                }
            }

            if (!empty($conflicts)) {
                $result[] = [
                    'gibbonPersonID' => $child['gibbonPersonID'],
                    'preferredName' => $child['preferredName'],
                    'surname' => $child['surname'],
                    'image_240' => $child['image_240'],
                    'conflicts' => $conflicts,
                ];
            }
        }

        return $result;
    }

    /**
     * Get all children with allergies for a specific date's menu.
     *
     * @param string $date
     * @param int $gibbonSchoolYearID
     * @return array
     */
    public function getChildrenWithAllergyWarningsForDate($date, $gibbonSchoolYearID)
    {
        $data = [
            'date' => $date,
            'gibbonSchoolYearID' => $gibbonSchoolYearID,
        ];
        $sql = "SELECT DISTINCT
                    wm.gibbonCareWeeklyMenuID,
                    wm.mealType,
                    mi.gibbonCareMenuItemID,
                    mi.name as menuItemName,
                    mia.allergen,
                    mia.severity as menuItemSeverity
                FROM gibbonCareWeeklyMenu wm
                INNER JOIN gibbonCareMenuItem mi ON wm.gibbonCareMenuItemID=mi.gibbonCareMenuItemID
                INNER JOIN gibbonCareMenuItemAllergen mia ON mi.gibbonCareMenuItemID=mia.gibbonCareMenuItemID
                WHERE wm.date=:date
                AND wm.gibbonSchoolYearID=:gibbonSchoolYearID
                ORDER BY wm.mealType, mi.name, mia.allergen";

        $menuAllergens = $this->db()->select($sql, $data)->fetchAll();

        if (empty($menuAllergens)) {
            return [];
        }

        // Group menu allergens by allergen type
        $allergenList = array_unique(array_column($menuAllergens, 'allergen'));

        // Get all children with these allergies
        $warnings = [];
        foreach ($allergenList as $allergen) {
            $children = $this->selectChildrenByAllergen($allergen, $gibbonSchoolYearID)->fetchAll();

            foreach ($children as $child) {
                // Find matching menu items
                foreach ($menuAllergens as $ma) {
                    if ($ma['allergen'] === $allergen) {
                        $key = $child['gibbonPersonID'] . '_' . $ma['gibbonCareMenuItemID'];
                        if (!isset($warnings[$key])) {
                            $warnings[$key] = [
                                'gibbonPersonID' => $child['gibbonPersonID'],
                                'preferredName' => $child['preferredName'],
                                'surname' => $child['surname'],
                                'image_240' => $child['image_240'],
                                'mealType' => $ma['mealType'],
                                'menuItemName' => $ma['menuItemName'],
                                'allergens' => [],
                            ];
                        }
                        $warnings[$key]['allergens'][] = [
                            'allergen' => $allergen,
                            'severity' => $ma['menuItemSeverity'],
                        ];
                    }
                }
            }
        }

        return array_values($warnings);
    }

    /**
     * Set parent notification status for a dietary profile.
     *
     * @param int $gibbonCareChildDietaryID
     * @param bool $notified
     * @return bool
     */
    public function setParentNotified($gibbonCareChildDietaryID, $notified = true)
    {
        $data = [
            'parentNotified' => $notified ? 'Y' : 'N',
            'parentNotifiedTime' => $notified ? date('Y-m-d H:i:s') : null,
        ];

        return $this->update($gibbonCareChildDietaryID, $data);
    }

    /**
     * Get dietary type options.
     *
     * @return array
     */
    public function getDietaryTypeOptions()
    {
        return [
            'None' => 'None',
            'Vegetarian' => 'Vegetarian',
            'Vegan' => 'Vegan',
            'Halal' => 'Halal',
            'Kosher' => 'Kosher',
            'Medical' => 'Medical',
            'Other' => 'Other',
        ];
    }

    /**
     * Get common allergen options.
     *
     * @return array
     */
    public function getAllergenOptions()
    {
        return [
            'Milk' => 'Milk',
            'Eggs' => 'Eggs',
            'Peanuts' => 'Peanuts',
            'Tree Nuts' => 'Tree Nuts',
            'Fish' => 'Fish',
            'Shellfish' => 'Shellfish',
            'Wheat' => 'Wheat',
            'Soy' => 'Soy',
            'Sesame' => 'Sesame',
            'Gluten' => 'Gluten',
            'Mustard' => 'Mustard',
            'Celery' => 'Celery',
            'Lupin' => 'Lupin',
            'Molluscs' => 'Molluscs',
            'Sulphites' => 'Sulphites',
            'Other' => 'Other',
        ];
    }

    /**
     * Get severity options for allergens.
     *
     * @return array
     */
    public function getSeverityOptions()
    {
        return [
            'Mild' => 'Mild',
            'Moderate' => 'Moderate',
            'Severe' => 'Severe',
        ];
    }

    /**
     * Get summary of children by dietary type.
     *
     * @param int $gibbonSchoolYearID
     * @return array
     */
    public function getDietarySummary($gibbonSchoolYearID)
    {
        $data = ['gibbonSchoolYearID' => $gibbonSchoolYearID];
        $sql = "SELECT
                    COALESCE(cd.dietaryType, 'None') as dietaryType,
                    COUNT(DISTINCT se.gibbonPersonID) as childCount
                FROM gibbonStudentEnrolment se
                INNER JOIN gibbonPerson p ON se.gibbonPersonID=p.gibbonPersonID
                LEFT JOIN gibbonCareChildDietary cd ON p.gibbonPersonID=cd.gibbonPersonID
                WHERE se.gibbonSchoolYearID=:gibbonSchoolYearID
                AND p.status='Full'
                GROUP BY COALESCE(cd.dietaryType, 'None')
                ORDER BY FIELD(COALESCE(cd.dietaryType, 'None'), 'None', 'Vegetarian', 'Vegan', 'Halal', 'Kosher', 'Medical', 'Other')";

        return $this->db()->select($sql, $data)->fetchAll();
    }

    /**
     * Get summary of children by allergen.
     *
     * @param int $gibbonSchoolYearID
     * @return array
     */
    public function getAllergenSummary($gibbonSchoolYearID)
    {
        $allergens = $this->getAllergenOptions();
        $summary = [];

        foreach (array_keys($allergens) as $allergen) {
            $children = $this->selectChildrenByAllergen($allergen, $gibbonSchoolYearID);
            $count = 0;
            while ($children->fetch()) {
                $count++;
            }
            if ($count > 0) {
                $summary[] = [
                    'allergen' => $allergen,
                    'childCount' => $count,
                ];
            }
        }

        // Sort by count descending
        usort($summary, function ($a, $b) {
            return $b['childCount'] - $a['childCount'];
        });

        return $summary;
    }
}
