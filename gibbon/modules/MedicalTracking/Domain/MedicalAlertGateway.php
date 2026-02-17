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
 * Medical Alert Gateway
 *
 * Gateway for medical alert operations including real-time allergen exposure alerts,
 * dashboard notifications, and integration with NotificationEngine for staff alerts.
 *
 * @version v1.0.00
 * @since   v1.0.00
 */
class MedicalAlertGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'gibbonMedicalAlert';
    private static $primaryKey = 'gibbonMedicalAlertID';

    private static $searchableColumns = ['gibbonPerson.preferredName', 'gibbonPerson.surname', 'gibbonMedicalAlert.title', 'gibbonMedicalAlert.description'];

    // =========================================================================
    // QUERY OPERATIONS
    // =========================================================================

    /**
     * Query medical alerts with criteria support.
     *
     * @param QueryCriteria $criteria
     * @return DataSet
     */
    public function queryAlerts(QueryCriteria $criteria)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonMedicalAlert.gibbonMedicalAlertID',
                'gibbonMedicalAlert.gibbonPersonID',
                'gibbonMedicalAlert.alertType',
                'gibbonMedicalAlert.alertLevel',
                'gibbonMedicalAlert.title',
                'gibbonMedicalAlert.description',
                'gibbonMedicalAlert.actionRequired',
                'gibbonMedicalAlert.displayOnDashboard',
                'gibbonMedicalAlert.displayOnAttendance',
                'gibbonMedicalAlert.displayOnReports',
                'gibbonMedicalAlert.notifyOnCheckIn',
                'gibbonMedicalAlert.relatedAllergyID',
                'gibbonMedicalAlert.relatedMedicationID',
                'gibbonMedicalAlert.relatedPlanID',
                'gibbonMedicalAlert.effectiveDate',
                'gibbonMedicalAlert.expirationDate',
                'gibbonMedicalAlert.active',
                'gibbonMedicalAlert.timestampCreated',
                'gibbonPerson.preferredName',
                'gibbonPerson.surname',
                'gibbonPerson.image_240',
                'createdBy.preferredName as createdByName',
                'createdBy.surname as createdBySurname',
            ])
            ->innerJoin('gibbonPerson', 'gibbonMedicalAlert.gibbonPersonID=gibbonPerson.gibbonPersonID')
            ->leftJoin('gibbonPerson as createdBy', 'gibbonMedicalAlert.createdByID=createdBy.gibbonPersonID');

        $criteria->addFilterRules([
            'child' => function ($query, $gibbonPersonID) {
                return $query
                    ->where('gibbonMedicalAlert.gibbonPersonID=:gibbonPersonID')
                    ->bindValue('gibbonPersonID', $gibbonPersonID);
            },
            'alertType' => function ($query, $alertType) {
                return $query
                    ->where('gibbonMedicalAlert.alertType=:alertType')
                    ->bindValue('alertType', $alertType);
            },
            'alertLevel' => function ($query, $alertLevel) {
                return $query
                    ->where('gibbonMedicalAlert.alertLevel=:alertLevel')
                    ->bindValue('alertLevel', $alertLevel);
            },
            'displayOnDashboard' => function ($query, $value) {
                return $query
                    ->where('gibbonMedicalAlert.displayOnDashboard=:displayOnDashboard')
                    ->bindValue('displayOnDashboard', $value);
            },
            'active' => function ($query, $value) {
                return $query
                    ->where('gibbonMedicalAlert.active=:active')
                    ->bindValue('active', $value);
            },
        ]);

        return $this->runQuery($query, $criteria);
    }

    /**
     * Query active alerts with criteria support.
     *
     * @param QueryCriteria $criteria
     * @return DataSet
     */
    public function queryActiveAlerts(QueryCriteria $criteria)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonMedicalAlert.gibbonMedicalAlertID',
                'gibbonMedicalAlert.gibbonPersonID',
                'gibbonMedicalAlert.alertType',
                'gibbonMedicalAlert.alertLevel',
                'gibbonMedicalAlert.title',
                'gibbonMedicalAlert.description',
                'gibbonMedicalAlert.actionRequired',
                'gibbonMedicalAlert.displayOnDashboard',
                'gibbonMedicalAlert.notifyOnCheckIn',
                'gibbonMedicalAlert.timestampCreated',
                'gibbonPerson.preferredName',
                'gibbonPerson.surname',
                'gibbonPerson.image_240',
            ])
            ->innerJoin('gibbonPerson', 'gibbonMedicalAlert.gibbonPersonID=gibbonPerson.gibbonPersonID')
            ->where('gibbonMedicalAlert.active=:active')
            ->bindValue('active', 'Y')
            ->where('(gibbonMedicalAlert.expirationDate IS NULL OR gibbonMedicalAlert.expirationDate >= CURDATE())');

        $criteria->addFilterRules([
            'alertType' => function ($query, $alertType) {
                return $query
                    ->where('gibbonMedicalAlert.alertType=:alertType')
                    ->bindValue('alertType', $alertType);
            },
            'alertLevel' => function ($query, $alertLevel) {
                return $query
                    ->where('gibbonMedicalAlert.alertLevel=:alertLevel')
                    ->bindValue('alertLevel', $alertLevel);
            },
        ]);

        return $this->runQuery($query, $criteria);
    }

    /**
     * Query alerts for a specific child.
     *
     * @param QueryCriteria $criteria
     * @param int $gibbonPersonID
     * @return DataSet
     */
    public function queryAlertsByPerson(QueryCriteria $criteria, $gibbonPersonID)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonMedicalAlert.gibbonMedicalAlertID',
                'gibbonMedicalAlert.alertType',
                'gibbonMedicalAlert.alertLevel',
                'gibbonMedicalAlert.title',
                'gibbonMedicalAlert.description',
                'gibbonMedicalAlert.actionRequired',
                'gibbonMedicalAlert.displayOnDashboard',
                'gibbonMedicalAlert.displayOnAttendance',
                'gibbonMedicalAlert.displayOnReports',
                'gibbonMedicalAlert.notifyOnCheckIn',
                'gibbonMedicalAlert.effectiveDate',
                'gibbonMedicalAlert.expirationDate',
                'gibbonMedicalAlert.active',
                'gibbonMedicalAlert.timestampCreated',
                'createdBy.preferredName as createdByName',
                'createdBy.surname as createdBySurname',
            ])
            ->leftJoin('gibbonPerson as createdBy', 'gibbonMedicalAlert.createdByID=createdBy.gibbonPersonID')
            ->where('gibbonMedicalAlert.gibbonPersonID=:gibbonPersonID')
            ->bindValue('gibbonPersonID', $gibbonPersonID);

        return $this->runQuery($query, $criteria);
    }

    // =========================================================================
    // SELECT OPERATIONS
    // =========================================================================

    /**
     * Get all active alerts for a child.
     *
     * @param int $gibbonPersonID
     * @return \Gibbon\Database\Result
     */
    public function selectAlertsByPerson($gibbonPersonID)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols([
                'gibbonMedicalAlert.*',
                'createdBy.preferredName as createdByName',
                'createdBy.surname as createdBySurname',
            ])
            ->leftJoin('gibbonPerson as createdBy', 'gibbonMedicalAlert.createdByID=createdBy.gibbonPersonID')
            ->where('gibbonMedicalAlert.gibbonPersonID=:gibbonPersonID')
            ->bindValue('gibbonPersonID', $gibbonPersonID)
            ->where('gibbonMedicalAlert.active=:active')
            ->bindValue('active', 'Y')
            ->where('(gibbonMedicalAlert.expirationDate IS NULL OR gibbonMedicalAlert.expirationDate >= CURDATE())')
            ->orderBy(['FIELD(gibbonMedicalAlert.alertLevel, "Critical", "Warning", "Info")', 'gibbonMedicalAlert.timestampCreated DESC']);

        return $this->runSelect($query);
    }

    /**
     * Get dashboard alerts for a child.
     *
     * @param int $gibbonPersonID
     * @return \Gibbon\Database\Result
     */
    public function selectDashboardAlertsByPerson($gibbonPersonID)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols([
                'gibbonMedicalAlert.gibbonMedicalAlertID',
                'gibbonMedicalAlert.alertType',
                'gibbonMedicalAlert.alertLevel',
                'gibbonMedicalAlert.title',
                'gibbonMedicalAlert.description',
                'gibbonMedicalAlert.actionRequired',
            ])
            ->where('gibbonMedicalAlert.gibbonPersonID=:gibbonPersonID')
            ->bindValue('gibbonPersonID', $gibbonPersonID)
            ->where('gibbonMedicalAlert.active=:active')
            ->bindValue('active', 'Y')
            ->where('gibbonMedicalAlert.displayOnDashboard=:displayOnDashboard')
            ->bindValue('displayOnDashboard', 'Y')
            ->where('(gibbonMedicalAlert.expirationDate IS NULL OR gibbonMedicalAlert.expirationDate >= CURDATE())')
            ->orderBy(['FIELD(gibbonMedicalAlert.alertLevel, "Critical", "Warning", "Info")']);

        return $this->runSelect($query);
    }

    /**
     * Get check-in alerts for a child (alerts to show when child checks in).
     *
     * @param int $gibbonPersonID
     * @return \Gibbon\Database\Result
     */
    public function selectCheckInAlerts($gibbonPersonID)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols([
                'gibbonMedicalAlert.gibbonMedicalAlertID',
                'gibbonMedicalAlert.alertType',
                'gibbonMedicalAlert.alertLevel',
                'gibbonMedicalAlert.title',
                'gibbonMedicalAlert.description',
                'gibbonMedicalAlert.actionRequired',
            ])
            ->where('gibbonMedicalAlert.gibbonPersonID=:gibbonPersonID')
            ->bindValue('gibbonPersonID', $gibbonPersonID)
            ->where('gibbonMedicalAlert.active=:active')
            ->bindValue('active', 'Y')
            ->where('gibbonMedicalAlert.notifyOnCheckIn=:notifyOnCheckIn')
            ->bindValue('notifyOnCheckIn', 'Y')
            ->where('(gibbonMedicalAlert.expirationDate IS NULL OR gibbonMedicalAlert.expirationDate >= CURDATE())')
            ->orderBy(['FIELD(gibbonMedicalAlert.alertLevel, "Critical", "Warning", "Info")']);

        return $this->runSelect($query);
    }

    /**
     * Get critical alerts for all children (for staff dashboard).
     *
     * @return \Gibbon\Database\Result
     */
    public function selectCriticalAlerts()
    {
        $data = [];
        $sql = "SELECT
                    gibbonMedicalAlert.*,
                    gibbonPerson.preferredName,
                    gibbonPerson.surname,
                    gibbonPerson.image_240
                FROM gibbonMedicalAlert
                INNER JOIN gibbonPerson ON gibbonMedicalAlert.gibbonPersonID=gibbonPerson.gibbonPersonID
                WHERE gibbonMedicalAlert.active='Y'
                AND gibbonMedicalAlert.alertLevel='Critical'
                AND gibbonPerson.status='Full'
                AND (gibbonMedicalAlert.expirationDate IS NULL OR gibbonMedicalAlert.expirationDate >= CURDATE())
                ORDER BY gibbonMedicalAlert.timestampCreated DESC";

        return $this->db()->select($sql, $data);
    }

    /**
     * Get alerts by type.
     *
     * @param string $alertType
     * @return \Gibbon\Database\Result
     */
    public function selectAlertsByType($alertType)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols([
                'gibbonMedicalAlert.*',
                'gibbonPerson.preferredName',
                'gibbonPerson.surname',
                'gibbonPerson.image_240',
            ])
            ->innerJoin('gibbonPerson', 'gibbonMedicalAlert.gibbonPersonID=gibbonPerson.gibbonPersonID')
            ->where('gibbonMedicalAlert.alertType=:alertType')
            ->bindValue('alertType', $alertType)
            ->where('gibbonMedicalAlert.active=:active')
            ->bindValue('active', 'Y')
            ->where('gibbonPerson.status=:status')
            ->bindValue('status', 'Full')
            ->where('(gibbonMedicalAlert.expirationDate IS NULL OR gibbonMedicalAlert.expirationDate >= CURDATE())')
            ->orderBy(['FIELD(gibbonMedicalAlert.alertLevel, "Critical", "Warning", "Info")', 'gibbonPerson.surname', 'gibbonPerson.preferredName']);

        return $this->runSelect($query);
    }

    /**
     * Get all children with active medical alerts (grouped).
     *
     * @return \Gibbon\Database\Result
     */
    public function selectChildrenWithAlerts()
    {
        $data = [];
        $sql = "SELECT DISTINCT
                    gibbonPerson.gibbonPersonID,
                    gibbonPerson.preferredName,
                    gibbonPerson.surname,
                    gibbonPerson.image_240,
                    MAX(CASE WHEN gibbonMedicalAlert.alertLevel='Critical' THEN 1 ELSE 0 END) as hasCritical,
                    MAX(CASE WHEN gibbonMedicalAlert.alertLevel='Warning' THEN 1 ELSE 0 END) as hasWarning,
                    COUNT(gibbonMedicalAlert.gibbonMedicalAlertID) as alertCount,
                    GROUP_CONCAT(DISTINCT gibbonMedicalAlert.alertType ORDER BY gibbonMedicalAlert.alertType SEPARATOR ', ') as alertTypes
                FROM gibbonMedicalAlert
                INNER JOIN gibbonPerson ON gibbonMedicalAlert.gibbonPersonID=gibbonPerson.gibbonPersonID
                WHERE gibbonMedicalAlert.active='Y'
                AND gibbonPerson.status='Full'
                AND (gibbonMedicalAlert.expirationDate IS NULL OR gibbonMedicalAlert.expirationDate >= CURDATE())
                GROUP BY gibbonPerson.gibbonPersonID
                ORDER BY hasCritical DESC, hasWarning DESC, gibbonPerson.surname, gibbonPerson.preferredName";

        return $this->db()->select($sql, $data);
    }

    // =========================================================================
    // SINGLE RECORD OPERATIONS
    // =========================================================================

    /**
     * Get alert by ID with details.
     *
     * @param int $gibbonMedicalAlertID
     * @return array|false
     */
    public function getAlertWithDetails($gibbonMedicalAlertID)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols([
                'gibbonMedicalAlert.*',
                'gibbonPerson.preferredName',
                'gibbonPerson.surname',
                'gibbonPerson.image_240',
                'gibbonPerson.dob',
                'createdBy.preferredName as createdByName',
                'createdBy.surname as createdBySurname',
            ])
            ->innerJoin('gibbonPerson', 'gibbonMedicalAlert.gibbonPersonID=gibbonPerson.gibbonPersonID')
            ->leftJoin('gibbonPerson as createdBy', 'gibbonMedicalAlert.createdByID=createdBy.gibbonPersonID')
            ->where('gibbonMedicalAlert.gibbonMedicalAlertID=:gibbonMedicalAlertID')
            ->bindValue('gibbonMedicalAlertID', $gibbonMedicalAlertID);

        $result = $this->runSelect($query);
        return $result->isNotEmpty() ? $result->fetch() : false;
    }

    /**
     * Get existing alert for an allergy.
     *
     * @param int $gibbonMedicalAllergyID
     * @return array|false
     */
    public function getAlertByAllergyID($gibbonMedicalAllergyID)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols(['*'])
            ->where('relatedAllergyID=:relatedAllergyID')
            ->bindValue('relatedAllergyID', $gibbonMedicalAllergyID)
            ->where('active=:active')
            ->bindValue('active', 'Y');

        $result = $this->runSelect($query);
        return $result->isNotEmpty() ? $result->fetch() : false;
    }

    /**
     * Get existing alert for a medication.
     *
     * @param int $gibbonMedicalMedicationID
     * @return array|false
     */
    public function getAlertByMedicationID($gibbonMedicalMedicationID)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols(['*'])
            ->where('relatedMedicationID=:relatedMedicationID')
            ->bindValue('relatedMedicationID', $gibbonMedicalMedicationID)
            ->where('active=:active')
            ->bindValue('active', 'Y');

        $result = $this->runSelect($query);
        return $result->isNotEmpty() ? $result->fetch() : false;
    }

    /**
     * Get existing alert for an accommodation plan.
     *
     * @param int $gibbonMedicalAccommodationPlanID
     * @return array|false
     */
    public function getAlertByPlanID($gibbonMedicalAccommodationPlanID)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols(['*'])
            ->where('relatedPlanID=:relatedPlanID')
            ->bindValue('relatedPlanID', $gibbonMedicalAccommodationPlanID)
            ->where('active=:active')
            ->bindValue('active', 'Y');

        $result = $this->runSelect($query);
        return $result->isNotEmpty() ? $result->fetch() : false;
    }

    // =========================================================================
    // CREATE/UPDATE OPERATIONS
    // =========================================================================

    /**
     * Create a new medical alert.
     *
     * @param int $gibbonPersonID
     * @param string $alertType
     * @param string $alertLevel
     * @param string $title
     * @param string $description
     * @param int $createdByID
     * @param array $additionalData
     * @return int|false
     */
    public function createAlert($gibbonPersonID, $alertType, $alertLevel, $title, $description, $createdByID, $additionalData = [])
    {
        return $this->insert(array_merge([
            'gibbonPersonID' => $gibbonPersonID,
            'alertType' => $alertType,
            'alertLevel' => $alertLevel,
            'title' => $title,
            'description' => $description,
            'createdByID' => $createdByID,
        ], $additionalData));
    }

    /**
     * Create an alert from an allergy record.
     *
     * @param array $allergy Allergy record data
     * @param int $createdByID
     * @return int|false
     */
    public function createAllergyAlert($allergy, $createdByID)
    {
        // Determine alert level based on severity
        $alertLevel = 'Warning';
        if (in_array($allergy['severity'], ['Severe', 'Life-Threatening'])) {
            $alertLevel = 'Critical';
        } elseif ($allergy['severity'] === 'Mild') {
            $alertLevel = 'Info';
        }

        // Build action required text
        $actionRequired = $allergy['treatment'] ?? '';
        if ($allergy['epiPenRequired'] === 'Y') {
            $actionRequired .= "\nEpiPen required. Location: " . ($allergy['epiPenLocation'] ?? 'Not specified');
        }

        return $this->createAlert(
            $allergy['gibbonPersonID'],
            'Allergy',
            $alertLevel,
            "Allergy: " . $allergy['allergenName'] . " ({$allergy['severity']})",
            $allergy['reaction'] ?? "Allergic to {$allergy['allergenName']}",
            $createdByID,
            [
                'actionRequired' => trim($actionRequired),
                'displayOnDashboard' => 'Y',
                'displayOnAttendance' => ($alertLevel === 'Critical') ? 'Y' : 'N',
                'notifyOnCheckIn' => ($alertLevel === 'Critical') ? 'Y' : 'N',
                'relatedAllergyID' => $allergy['gibbonMedicalAllergyID'],
            ]
        );
    }

    /**
     * Deactivate an alert.
     *
     * @param int $gibbonMedicalAlertID
     * @return bool
     */
    public function deactivateAlert($gibbonMedicalAlertID)
    {
        return $this->update($gibbonMedicalAlertID, [
            'active' => 'N',
        ]);
    }

    /**
     * Deactivate alerts related to an allergy.
     *
     * @param int $gibbonMedicalAllergyID
     * @return bool
     */
    public function deactivateAlertsByAllergyID($gibbonMedicalAllergyID)
    {
        $data = ['relatedAllergyID' => $gibbonMedicalAllergyID];
        $sql = "UPDATE gibbonMedicalAlert SET active='N' WHERE relatedAllergyID=:relatedAllergyID";

        return $this->db()->statement($sql, $data);
    }

    /**
     * Deactivate alerts related to a medication.
     *
     * @param int $gibbonMedicalMedicationID
     * @return bool
     */
    public function deactivateAlertsByMedicationID($gibbonMedicalMedicationID)
    {
        $data = ['relatedMedicationID' => $gibbonMedicalMedicationID];
        $sql = "UPDATE gibbonMedicalAlert SET active='N' WHERE relatedMedicationID=:relatedMedicationID";

        return $this->db()->statement($sql, $data);
    }

    // =========================================================================
    // ALLERGEN DETECTION (FOR MEAL INTEGRATION)
    // =========================================================================

    /**
     * Detect if a meal item contains allergens for a child.
     *
     * @param int $gibbonPersonID Child's person ID
     * @param string $mealItem Food item to check
     * @param array $allergens List of allergens in the meal
     * @return array Array of detected allergen matches with alert info
     */
    public function detectAllergenExposure($gibbonPersonID, $mealItem, $allergens = [])
    {
        $detectedAllergens = [];

        // Get child's active food allergies
        $data = ['gibbonPersonID' => $gibbonPersonID];
        $sql = "SELECT a.*, m.aliases
                FROM gibbonMedicalAllergy a
                LEFT JOIN gibbonMedicalAllergenMenu m ON LOWER(a.allergenName) = LOWER(m.allergenName)
                WHERE a.gibbonPersonID = :gibbonPersonID
                AND a.allergenType = 'Food'
                AND a.active = 'Y'";

        $childAllergies = $this->db()->select($sql, $data)->fetchAll();

        if (empty($childAllergies)) {
            return $detectedAllergens;
        }

        // Check meal item and provided allergens against child's allergies
        $itemLower = strtolower($mealItem);
        $allergenListLower = array_map('strtolower', $allergens);

        foreach ($childAllergies as $allergy) {
            $allergenNameLower = strtolower($allergy['allergenName']);
            $aliasesLower = !empty($allergy['aliases']) ? strtolower($allergy['aliases']) : '';

            $matched = false;
            $matchReason = '';

            // Check direct allergen name match in meal item
            if (strpos($itemLower, $allergenNameLower) !== false) {
                $matched = true;
                $matchReason = "Meal item contains '{$allergy['allergenName']}'";
            }

            // Check if allergen is in the provided allergen list
            if (!$matched && in_array($allergenNameLower, $allergenListLower)) {
                $matched = true;
                $matchReason = "Meal is flagged with allergen '{$allergy['allergenName']}'";
            }

            // Check aliases
            if (!$matched && !empty($aliasesLower)) {
                $aliases = array_map('trim', explode(',', $aliasesLower));
                foreach ($aliases as $alias) {
                    if (strpos($itemLower, $alias) !== false) {
                        $matched = true;
                        $matchReason = "Meal item contains '{$alias}' (alias for {$allergy['allergenName']})";
                        break;
                    }
                    if (in_array($alias, $allergenListLower)) {
                        $matched = true;
                        $matchReason = "Meal is flagged with '{$alias}' (alias for {$allergy['allergenName']})";
                        break;
                    }
                }
            }

            if ($matched) {
                $detectedAllergens[] = [
                    'allergyID' => $allergy['gibbonMedicalAllergyID'],
                    'allergenName' => $allergy['allergenName'],
                    'severity' => $allergy['severity'],
                    'reaction' => $allergy['reaction'],
                    'treatment' => $allergy['treatment'],
                    'epiPenRequired' => $allergy['epiPenRequired'],
                    'epiPenLocation' => $allergy['epiPenLocation'],
                    'matchReason' => $matchReason,
                ];
            }
        }

        return $detectedAllergens;
    }

    /**
     * Create an allergen exposure alert record.
     *
     * @param int $gibbonPersonID
     * @param array $allergyData Allergy information
     * @param string $mealItem Food item that triggered alert
     * @param int $createdByID
     * @return int|false Alert ID
     */
    public function createAllergenExposureAlert($gibbonPersonID, $allergyData, $mealItem, $createdByID)
    {
        $alertLevel = in_array($allergyData['severity'], ['Severe', 'Life-Threatening']) ? 'Critical' : 'Warning';

        $title = "ALLERGEN ALERT: {$allergyData['allergenName']} detected";
        $description = "Potential allergen exposure detected.\n\n"
            . "Food Item: {$mealItem}\n"
            . "Allergen: {$allergyData['allergenName']}\n"
            . "Severity: {$allergyData['severity']}\n"
            . ($allergyData['matchReason'] ?? '');

        $actionRequired = $allergyData['treatment'] ?? "Review child's allergy file immediately.";
        if ($allergyData['epiPenRequired'] === 'Y') {
            $actionRequired .= "\n\nEPIPEN REQUIRED - Location: " . ($allergyData['epiPenLocation'] ?? 'Check allergy file');
        }

        return $this->createAlert(
            $gibbonPersonID,
            'Allergy',
            $alertLevel,
            $title,
            $description,
            $createdByID,
            [
                'actionRequired' => $actionRequired,
                'displayOnDashboard' => 'Y',
                'displayOnAttendance' => 'Y',
                'notifyOnCheckIn' => 'Y',
                'relatedAllergyID' => $allergyData['allergyID'] ?? null,
            ]
        );
    }

    // =========================================================================
    // NOTIFICATION ENGINE INTEGRATION
    // =========================================================================

    /**
     * Queue staff notifications for a medical alert.
     * Integrates with NotificationEngine to send real-time alerts.
     *
     * @param int $gibbonMedicalAlertID Alert ID
     * @param array $staffIDs Array of staff person IDs to notify
     * @param object $notificationGateway NotificationGateway instance
     * @return int Number of notifications queued
     */
    public function queueAlertNotifications($gibbonMedicalAlertID, $staffIDs, $notificationGateway)
    {
        $alert = $this->getAlertWithDetails($gibbonMedicalAlertID);
        if (!$alert) {
            return 0;
        }

        $childName = $alert['preferredName'] . ' ' . $alert['surname'];
        $notificationType = 'medical_alert';

        // Determine urgency-based channel
        $channel = ($alert['alertLevel'] === 'Critical') ? 'both' : 'push';

        $title = "[{$alert['alertLevel']}] Medical Alert: {$childName}";
        $body = $alert['title'] . "\n\n" . $alert['description'];

        if (!empty($alert['actionRequired'])) {
            $body .= "\n\nAction Required:\n" . $alert['actionRequired'];
        }

        $payloadData = [
            'alertID' => $gibbonMedicalAlertID,
            'alertType' => $alert['alertType'],
            'alertLevel' => $alert['alertLevel'],
            'childID' => $alert['gibbonPersonID'],
            'childName' => $childName,
        ];

        return $notificationGateway->queueBulkNotification(
            $staffIDs,
            $notificationType,
            $title,
            $body,
            $payloadData,
            $channel
        );
    }

    /**
     * Get staff IDs who should be notified for a child's alerts.
     * Returns teachers, nurses, and administrators assigned to the child.
     *
     * @param int $gibbonPersonID Child's person ID
     * @return array Staff person IDs
     */
    public function getStaffToNotify($gibbonPersonID)
    {
        // Get staff from child's class/roll group
        $sql = "SELECT DISTINCT tutor.gibbonPersonID
                FROM gibbonStudentEnrolment se
                INNER JOIN gibbonFormGroup fg ON se.gibbonFormGroupID = fg.gibbonFormGroupID
                INNER JOIN gibbonFormGroupTutor fgt ON fg.gibbonFormGroupID = fgt.gibbonFormGroupID
                INNER JOIN gibbonPerson tutor ON fgt.gibbonPersonID = tutor.gibbonPersonID
                WHERE se.gibbonPersonID = :gibbonPersonID
                AND tutor.status = 'Full'
                AND se.gibbonSchoolYearID = (SELECT gibbonSchoolYearID FROM gibbonSchoolYear WHERE status = 'Current' LIMIT 1)

                UNION

                -- Get staff with medical tracking permissions
                SELECT DISTINCT p.gibbonPersonID
                FROM gibbonPerson p
                INNER JOIN gibbonStaff s ON p.gibbonPersonID = s.gibbonPersonID
                WHERE p.status = 'Full'
                AND (s.jobTitle LIKE '%Nurse%' OR s.jobTitle LIKE '%Medical%' OR s.jobTitle LIKE '%Health%')";

        $data = ['gibbonPersonID' => $gibbonPersonID];
        $result = $this->db()->select($sql, $data);

        return $result->fetchAll(\PDO::FETCH_COLUMN, 0);
    }

    /**
     * Trigger real-time alert for allergen exposure during meal logging.
     *
     * @param int $gibbonPersonID Child's person ID
     * @param string $mealItem Food item being logged
     * @param array $allergens Allergens in the meal
     * @param int $loggedByID Staff who logged the meal
     * @param object $notificationGateway NotificationGateway instance (optional)
     * @return array Result with detected allergens and alert info
     */
    public function triggerMealAllergenAlert($gibbonPersonID, $mealItem, $allergens, $loggedByID, $notificationGateway = null)
    {
        $result = [
            'hasAllergens' => false,
            'detectedAllergens' => [],
            'alertsCreated' => [],
            'notificationsSent' => 0,
        ];

        // Detect allergen exposure
        $detected = $this->detectAllergenExposure($gibbonPersonID, $mealItem, $allergens);

        if (empty($detected)) {
            return $result;
        }

        $result['hasAllergens'] = true;
        $result['detectedAllergens'] = $detected;

        // Create exposure alerts for each detected allergen
        foreach ($detected as $allergen) {
            $alertID = $this->createAllergenExposureAlert(
                $gibbonPersonID,
                $allergen,
                $mealItem,
                $loggedByID
            );

            if ($alertID) {
                $result['alertsCreated'][] = $alertID;

                // Queue notifications if gateway provided
                if ($notificationGateway !== null) {
                    $staffIDs = $this->getStaffToNotify($gibbonPersonID);
                    $notified = $this->queueAlertNotifications($alertID, $staffIDs, $notificationGateway);
                    $result['notificationsSent'] += $notified;
                }
            }
        }

        return $result;
    }

    // =========================================================================
    // STATISTICS & REPORTING
    // =========================================================================

    /**
     * Get alert statistics summary.
     *
     * @return array
     */
    public function getAlertStatistics()
    {
        $data = [];
        $sql = "SELECT
                    alertType,
                    alertLevel,
                    COUNT(*) as totalCount
                FROM gibbonMedicalAlert
                WHERE active='Y'
                AND (expirationDate IS NULL OR expirationDate >= CURDATE())
                GROUP BY alertType, alertLevel
                ORDER BY
                    FIELD(alertLevel, 'Critical', 'Warning', 'Info'),
                    alertType";

        return $this->db()->select($sql, $data)->fetchAll();
    }

    /**
     * Get summary of alerts for a specific child.
     *
     * @param int $gibbonPersonID
     * @return array
     */
    public function getAlertStatsByPerson($gibbonPersonID)
    {
        $data = ['gibbonPersonID' => $gibbonPersonID];
        $sql = "SELECT
                    COUNT(*) as totalAlerts,
                    SUM(CASE WHEN alertLevel='Critical' THEN 1 ELSE 0 END) as criticalCount,
                    SUM(CASE WHEN alertLevel='Warning' THEN 1 ELSE 0 END) as warningCount,
                    SUM(CASE WHEN alertLevel='Info' THEN 1 ELSE 0 END) as infoCount,
                    SUM(CASE WHEN alertType='Allergy' THEN 1 ELSE 0 END) as allergyAlerts,
                    SUM(CASE WHEN alertType='Medication' THEN 1 ELSE 0 END) as medicationAlerts
                FROM gibbonMedicalAlert
                WHERE gibbonPersonID=:gibbonPersonID
                AND active='Y'
                AND (expirationDate IS NULL OR expirationDate >= CURDATE())";

        return $this->db()->selectOne($sql, $data) ?: [
            'totalAlerts' => 0,
            'criticalCount' => 0,
            'warningCount' => 0,
            'infoCount' => 0,
            'allergyAlerts' => 0,
            'medicationAlerts' => 0,
        ];
    }

    /**
     * Purge old/expired alerts.
     *
     * @param int $daysOld Days to keep expired alerts
     * @return int Number of records purged
     */
    public function purgeExpiredAlerts($daysOld = 90)
    {
        $cutoffDate = date('Y-m-d', strtotime("-{$daysOld} days"));

        $data = ['cutoffDate' => $cutoffDate];
        $sql = "DELETE FROM gibbonMedicalAlert
                WHERE (active = 'N' OR expirationDate < :cutoffDate)
                AND timestampCreated < :cutoffDate";

        return $this->db()->delete($sql, $data);
    }
}
