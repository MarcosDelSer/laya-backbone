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

namespace Gibbon\Module\ChildEnrollment\Domain;

use Gibbon\Domain\Traits\TableAware;
use Gibbon\Domain\QueryCriteria;
use Gibbon\Domain\QueryableGateway;

/**
 * Enrollment Nutrition Gateway
 *
 * Handles nutrition and dietary information for child enrollment forms.
 * Each enrollment form has one nutrition record (one-to-one relationship).
 *
 * @version v1.0.00
 * @since   v1.0.00
 */
class EnrollmentNutritionGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'gibbonChildEnrollmentNutrition';
    private static $primaryKey = 'gibbonChildEnrollmentNutritionID';

    private static $searchableColumns = [
        'gibbonChildEnrollmentNutrition.dietaryRestrictions',
        'gibbonChildEnrollmentNutrition.foodAllergies',
        'gibbonChildEnrollmentNutrition.feedingInstructions',
        'gibbonChildEnrollmentNutrition.foodPreferences',
        'gibbonChildEnrollmentNutrition.foodDislikes',
        'gibbonChildEnrollmentNutrition.mealPlanNotes',
    ];

    /**
     * Query nutrition records with criteria support.
     *
     * @param QueryCriteria $criteria
     * @return DataSet
     */
    public function queryNutritionRecords(QueryCriteria $criteria)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonChildEnrollmentNutrition.gibbonChildEnrollmentNutritionID',
                'gibbonChildEnrollmentNutrition.gibbonChildEnrollmentFormID',
                'gibbonChildEnrollmentNutrition.dietaryRestrictions',
                'gibbonChildEnrollmentNutrition.foodAllergies',
                'gibbonChildEnrollmentNutrition.feedingInstructions',
                'gibbonChildEnrollmentNutrition.isBottleFeeding',
                'gibbonChildEnrollmentNutrition.bottleFeedingInfo',
                'gibbonChildEnrollmentNutrition.foodPreferences',
                'gibbonChildEnrollmentNutrition.foodDislikes',
                'gibbonChildEnrollmentNutrition.mealPlanNotes',
                'gibbonChildEnrollmentNutrition.timestampCreated',
                'gibbonChildEnrollmentNutrition.timestampModified',
                'gibbonChildEnrollmentForm.formNumber',
                'gibbonChildEnrollmentForm.childFirstName',
                'gibbonChildEnrollmentForm.childLastName',
                'gibbonChildEnrollmentForm.status as formStatus',
            ])
            ->innerJoin('gibbonChildEnrollmentForm', 'gibbonChildEnrollmentNutrition.gibbonChildEnrollmentFormID=gibbonChildEnrollmentForm.gibbonChildEnrollmentFormID');

        $criteria->addFilterRules([
            'isBottleFeeding' => function ($query, $isBottleFeeding) {
                return $query
                    ->where('gibbonChildEnrollmentNutrition.isBottleFeeding=:isBottleFeeding')
                    ->bindValue('isBottleFeeding', $isBottleFeeding);
            },
            'hasDietaryRestrictions' => function ($query, $value) {
                if ($value === 'Y') {
                    return $query
                        ->where('gibbonChildEnrollmentNutrition.dietaryRestrictions IS NOT NULL AND gibbonChildEnrollmentNutrition.dietaryRestrictions != \'\'');
                }
                return $query
                    ->where('(gibbonChildEnrollmentNutrition.dietaryRestrictions IS NULL OR gibbonChildEnrollmentNutrition.dietaryRestrictions = \'\')');
            },
            'hasFoodAllergies' => function ($query, $value) {
                if ($value === 'Y') {
                    return $query
                        ->where('gibbonChildEnrollmentNutrition.foodAllergies IS NOT NULL AND gibbonChildEnrollmentNutrition.foodAllergies != \'\'');
                }
                return $query
                    ->where('(gibbonChildEnrollmentNutrition.foodAllergies IS NULL OR gibbonChildEnrollmentNutrition.foodAllergies = \'\')');
            },
            'hasSpecialFeedingInstructions' => function ($query, $value) {
                if ($value === 'Y') {
                    return $query
                        ->where('gibbonChildEnrollmentNutrition.feedingInstructions IS NOT NULL AND gibbonChildEnrollmentNutrition.feedingInstructions != \'\'');
                }
                return $query
                    ->where('(gibbonChildEnrollmentNutrition.feedingInstructions IS NULL OR gibbonChildEnrollmentNutrition.feedingInstructions = \'\')');
            },
        ]);

        return $this->runQuery($query, $criteria);
    }

    /**
     * Get nutrition information for an enrollment form.
     *
     * @param int $gibbonChildEnrollmentFormID
     * @return array|false
     */
    public function getNutritionByForm($gibbonChildEnrollmentFormID)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols(['*'])
            ->where('gibbonChildEnrollmentFormID=:gibbonChildEnrollmentFormID')
            ->bindValue('gibbonChildEnrollmentFormID', $gibbonChildEnrollmentFormID);

        $result = $this->runSelect($query);
        return $result->isNotEmpty() ? $result->fetch() : false;
    }

    /**
     * Get a specific nutrition record by ID.
     *
     * @param int $gibbonChildEnrollmentNutritionID
     * @return array|false
     */
    public function getNutritionByID($gibbonChildEnrollmentNutritionID)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols(['*'])
            ->where('gibbonChildEnrollmentNutritionID=:gibbonChildEnrollmentNutritionID')
            ->bindValue('gibbonChildEnrollmentNutritionID', $gibbonChildEnrollmentNutritionID);

        $result = $this->runSelect($query);
        return $result->isNotEmpty() ? $result->fetch() : false;
    }

    /**
     * Check if a nutrition record exists for an enrollment form.
     *
     * @param int $gibbonChildEnrollmentFormID
     * @return bool
     */
    public function nutritionRecordExists($gibbonChildEnrollmentFormID)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols(['gibbonChildEnrollmentNutritionID'])
            ->where('gibbonChildEnrollmentFormID=:gibbonChildEnrollmentFormID')
            ->bindValue('gibbonChildEnrollmentFormID', $gibbonChildEnrollmentFormID);

        $result = $this->runSelect($query);
        return $result->isNotEmpty();
    }

    /**
     * Insert or update nutrition information for an enrollment form.
     *
     * @param int $gibbonChildEnrollmentFormID
     * @param array $data
     * @return int|false Returns the nutrition record ID on success
     */
    public function saveNutrition($gibbonChildEnrollmentFormID, array $data)
    {
        $existing = $this->getNutritionByForm($gibbonChildEnrollmentFormID);

        if ($existing) {
            // Update existing record
            return $this->update($existing['gibbonChildEnrollmentNutritionID'], $data)
                ? $existing['gibbonChildEnrollmentNutritionID']
                : false;
        }

        // Create new record
        $data['gibbonChildEnrollmentFormID'] = $gibbonChildEnrollmentFormID;
        return $this->insert($data);
    }

    /**
     * Update nutrition information.
     *
     * @param int $gibbonChildEnrollmentNutritionID
     * @param array $data
     * @return bool
     */
    public function updateNutrition($gibbonChildEnrollmentNutritionID, array $data)
    {
        return $this->update($gibbonChildEnrollmentNutritionID, $data);
    }

    /**
     * Delete nutrition information for an enrollment form.
     *
     * @param int $gibbonChildEnrollmentFormID
     * @return bool
     */
    public function deleteNutritionByForm($gibbonChildEnrollmentFormID)
    {
        $data = ['gibbonChildEnrollmentFormID' => $gibbonChildEnrollmentFormID];
        $sql = "DELETE FROM gibbonChildEnrollmentNutrition
                WHERE gibbonChildEnrollmentFormID=:gibbonChildEnrollmentFormID";

        return $this->db()->statement($sql, $data) !== false;
    }

    /**
     * Get dietary restrictions for an enrollment form.
     *
     * @param int $gibbonChildEnrollmentFormID
     * @return string|null
     */
    public function getDietaryRestrictions($gibbonChildEnrollmentFormID)
    {
        $nutrition = $this->getNutritionByForm($gibbonChildEnrollmentFormID);
        if (!$nutrition || empty($nutrition['dietaryRestrictions'])) {
            return null;
        }

        return $nutrition['dietaryRestrictions'];
    }

    /**
     * Get food allergies for an enrollment form.
     *
     * @param int $gibbonChildEnrollmentFormID
     * @return string|null
     */
    public function getFoodAllergies($gibbonChildEnrollmentFormID)
    {
        $nutrition = $this->getNutritionByForm($gibbonChildEnrollmentFormID);
        if (!$nutrition || empty($nutrition['foodAllergies'])) {
            return null;
        }

        return $nutrition['foodAllergies'];
    }

    /**
     * Check if child requires bottle feeding.
     *
     * @param int $gibbonChildEnrollmentFormID
     * @return bool
     */
    public function isBottleFeeding($gibbonChildEnrollmentFormID)
    {
        $nutrition = $this->getNutritionByForm($gibbonChildEnrollmentFormID);
        return $nutrition && $nutrition['isBottleFeeding'] === 'Y';
    }

    /**
     * Get bottle feeding information for an enrollment form.
     *
     * @param int $gibbonChildEnrollmentFormID
     * @return array|null
     */
    public function getBottleFeedingInfo($gibbonChildEnrollmentFormID)
    {
        $nutrition = $this->getNutritionByForm($gibbonChildEnrollmentFormID);
        if (!$nutrition || $nutrition['isBottleFeeding'] !== 'Y') {
            return null;
        }

        return [
            'isBottleFeeding' => true,
            'details' => $nutrition['bottleFeedingInfo'],
        ];
    }

    /**
     * Get food preferences and dislikes for an enrollment form.
     *
     * @param int $gibbonChildEnrollmentFormID
     * @return array|null
     */
    public function getFoodPreferencesAndDislikes($gibbonChildEnrollmentFormID)
    {
        $nutrition = $this->getNutritionByForm($gibbonChildEnrollmentFormID);
        if (!$nutrition) {
            return null;
        }

        return [
            'preferences' => $nutrition['foodPreferences'],
            'dislikes' => $nutrition['foodDislikes'],
        ];
    }

    /**
     * Query children with bottle feeding requirements.
     *
     * @param QueryCriteria $criteria
     * @return DataSet
     */
    public function queryChildrenWithBottleFeeding(QueryCriteria $criteria)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonChildEnrollmentNutrition.gibbonChildEnrollmentNutritionID',
                'gibbonChildEnrollmentNutrition.gibbonChildEnrollmentFormID',
                'gibbonChildEnrollmentNutrition.bottleFeedingInfo',
                'gibbonChildEnrollmentNutrition.feedingInstructions',
                'gibbonChildEnrollmentForm.formNumber',
                'gibbonChildEnrollmentForm.childFirstName',
                'gibbonChildEnrollmentForm.childLastName',
                'gibbonChildEnrollmentForm.status as formStatus',
            ])
            ->innerJoin('gibbonChildEnrollmentForm', 'gibbonChildEnrollmentNutrition.gibbonChildEnrollmentFormID=gibbonChildEnrollmentForm.gibbonChildEnrollmentFormID')
            ->where('gibbonChildEnrollmentNutrition.isBottleFeeding=:isBottleFeeding')
            ->where('gibbonChildEnrollmentForm.status IN (\'Submitted\', \'Approved\')')
            ->bindValue('isBottleFeeding', 'Y');

        return $this->runQuery($query, $criteria);
    }

    /**
     * Query children with dietary restrictions.
     *
     * @param QueryCriteria $criteria
     * @return DataSet
     */
    public function queryChildrenWithDietaryRestrictions(QueryCriteria $criteria)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonChildEnrollmentNutrition.gibbonChildEnrollmentNutritionID',
                'gibbonChildEnrollmentNutrition.gibbonChildEnrollmentFormID',
                'gibbonChildEnrollmentNutrition.dietaryRestrictions',
                'gibbonChildEnrollmentNutrition.foodAllergies',
                'gibbonChildEnrollmentForm.formNumber',
                'gibbonChildEnrollmentForm.childFirstName',
                'gibbonChildEnrollmentForm.childLastName',
                'gibbonChildEnrollmentForm.status as formStatus',
            ])
            ->innerJoin('gibbonChildEnrollmentForm', 'gibbonChildEnrollmentNutrition.gibbonChildEnrollmentFormID=gibbonChildEnrollmentForm.gibbonChildEnrollmentFormID')
            ->where('gibbonChildEnrollmentNutrition.dietaryRestrictions IS NOT NULL')
            ->where('gibbonChildEnrollmentNutrition.dietaryRestrictions != \'\'')
            ->where('gibbonChildEnrollmentForm.status IN (\'Submitted\', \'Approved\')');

        return $this->runQuery($query, $criteria);
    }

    /**
     * Query children with food allergies.
     *
     * @param QueryCriteria $criteria
     * @return DataSet
     */
    public function queryChildrenWithFoodAllergies(QueryCriteria $criteria)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonChildEnrollmentNutrition.gibbonChildEnrollmentNutritionID',
                'gibbonChildEnrollmentNutrition.gibbonChildEnrollmentFormID',
                'gibbonChildEnrollmentNutrition.foodAllergies',
                'gibbonChildEnrollmentForm.formNumber',
                'gibbonChildEnrollmentForm.childFirstName',
                'gibbonChildEnrollmentForm.childLastName',
                'gibbonChildEnrollmentForm.status as formStatus',
            ])
            ->innerJoin('gibbonChildEnrollmentForm', 'gibbonChildEnrollmentNutrition.gibbonChildEnrollmentFormID=gibbonChildEnrollmentForm.gibbonChildEnrollmentFormID')
            ->where('gibbonChildEnrollmentNutrition.foodAllergies IS NOT NULL')
            ->where('gibbonChildEnrollmentNutrition.foodAllergies != \'\'')
            ->where('gibbonChildEnrollmentForm.status IN (\'Submitted\', \'Approved\')');

        return $this->runQuery($query, $criteria);
    }

    /**
     * Get nutrition information for display purposes (formatted).
     *
     * @param int $gibbonChildEnrollmentFormID
     * @return array|null Formatted nutrition information
     */
    public function getNutritionInfo($gibbonChildEnrollmentFormID)
    {
        $nutrition = $this->getNutritionByForm($gibbonChildEnrollmentFormID);
        if (!$nutrition) {
            return null;
        }

        return [
            'dietaryRestrictions' => $nutrition['dietaryRestrictions'],
            'foodAllergies' => $nutrition['foodAllergies'],
            'feedingInstructions' => $nutrition['feedingInstructions'],
            'bottleFeeding' => $this->getBottleFeedingInfo($gibbonChildEnrollmentFormID),
            'foodPreferences' => $nutrition['foodPreferences'],
            'foodDislikes' => $nutrition['foodDislikes'],
            'mealPlanNotes' => $nutrition['mealPlanNotes'],
            'hasSpecialRequirements' => !empty($nutrition['dietaryRestrictions']) ||
                                       !empty($nutrition['foodAllergies']) ||
                                       !empty($nutrition['feedingInstructions']) ||
                                       $nutrition['isBottleFeeding'] === 'Y',
        ];
    }

    /**
     * Validate nutrition data before insert/update.
     *
     * @param array $data
     * @return array Array of validation errors (empty if valid)
     */
    public function validateNutritionData(array $data)
    {
        $errors = [];

        // Validate bottle feeding requires details
        if (isset($data['isBottleFeeding']) && $data['isBottleFeeding'] === 'Y') {
            if (empty($data['bottleFeedingInfo'])) {
                $errors[] = 'Bottle feeding details are required when bottle feeding is indicated.';
            }
        }

        // Validate text field lengths (TEXT type max is 65,535 bytes)
        $textFields = [
            'dietaryRestrictions',
            'foodAllergies',
            'feedingInstructions',
            'bottleFeedingInfo',
            'foodPreferences',
            'foodDislikes',
            'mealPlanNotes',
        ];

        foreach ($textFields as $field) {
            if (isset($data[$field]) && strlen($data[$field]) > 65535) {
                $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' exceeds maximum length.';
            }
        }

        return $errors;
    }

    /**
     * Get nutrition summary statistics for reporting.
     *
     * @return array
     */
    public function getNutritionStatistics()
    {
        $sql = "SELECT
                    COUNT(*) as totalRecords,
                    SUM(CASE WHEN isBottleFeeding='Y' THEN 1 ELSE 0 END) as withBottleFeeding,
                    SUM(CASE WHEN dietaryRestrictions IS NOT NULL AND dietaryRestrictions != '' THEN 1 ELSE 0 END) as withDietaryRestrictions,
                    SUM(CASE WHEN foodAllergies IS NOT NULL AND foodAllergies != '' THEN 1 ELSE 0 END) as withFoodAllergies,
                    SUM(CASE WHEN feedingInstructions IS NOT NULL AND feedingInstructions != '' THEN 1 ELSE 0 END) as withSpecialInstructions
                FROM gibbonChildEnrollmentNutrition n
                INNER JOIN gibbonChildEnrollmentForm f
                    ON n.gibbonChildEnrollmentFormID = f.gibbonChildEnrollmentFormID
                WHERE f.status IN ('Submitted', 'Approved')";

        return $this->db()->selectOne($sql) ?: [
            'totalRecords' => 0,
            'withBottleFeeding' => 0,
            'withDietaryRestrictions' => 0,
            'withFoodAllergies' => 0,
            'withSpecialInstructions' => 0,
        ];
    }

    /**
     * Get a summary of all special dietary needs for meal planning.
     *
     * @return array
     */
    public function getMealPlanningReport()
    {
        $sql = "SELECT
                    f.formNumber,
                    f.childFirstName,
                    f.childLastName,
                    n.dietaryRestrictions,
                    n.foodAllergies,
                    n.feedingInstructions,
                    n.isBottleFeeding,
                    n.bottleFeedingInfo,
                    n.foodPreferences,
                    n.foodDislikes,
                    n.mealPlanNotes
                FROM gibbonChildEnrollmentNutrition n
                INNER JOIN gibbonChildEnrollmentForm f
                    ON n.gibbonChildEnrollmentFormID = f.gibbonChildEnrollmentFormID
                WHERE f.status IN ('Submitted', 'Approved')
                AND (
                    n.dietaryRestrictions IS NOT NULL AND n.dietaryRestrictions != ''
                    OR n.foodAllergies IS NOT NULL AND n.foodAllergies != ''
                    OR n.feedingInstructions IS NOT NULL AND n.feedingInstructions != ''
                    OR n.isBottleFeeding = 'Y'
                )
                ORDER BY f.childLastName, f.childFirstName";

        return $this->db()->select($sql)->fetchAll();
    }
}
